<?php
defined('ABSPATH') || exit;

/** Admin-approved, payment-only WooCommerce record for a captured payment. */
class SuperWoo_Order_Recovery_Payment_Record {
    const STATUS = 'superwoo-review';

    public static function register_status($statuses) {
        $statuses['wc-' . self::STATUS] = [
            'label' => __('Recovery review', 'superwoo'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Recovery review <span class="count">(%s)</span>', 'Recovery review <span class="count">(%s)</span>', 'superwoo'),
        ];
        return $statuses;
    }

    public static function add_status($statuses) {
        $statuses['wc-' . self::STATUS] = __('Recovery review', 'superwoo');
        return $statuses;
    }

    public static function render_admin_details($order) {
        if (!$order instanceof WC_Order || 'yes' !== $order->get_meta('_superwoo_recovery_payment_only', true)) { return; }
        echo '<div class="order_data_column"><h4>' . esc_html__('Payment-only recovery', 'superwoo') . '</h4>';
        $source = (string) $order->get_meta('_superwoo_recovery_payment_evidence', true);
        echo '<p>' . esc_html('csv' === $source ? __('This payment was reported by an imported CSV and was not checked with Razorpay. Products, free gifts, shipping and taxes require separate review before fulfillment.', 'superwoo') : __('This order contains verified Razorpay payment facts only. Products, free gifts, shipping and taxes were unavailable in the source and require separate review before fulfillment.', 'superwoo')) . '</p>';
        echo '<p><strong>' . esc_html__('Historical WooCommerce reference', 'superwoo') . ':</strong> ' . esc_html((string) $order->get_meta('_superwoo_recovery_historical_order_id', true)) . '</p>';
        $historical_number = (string) $order->get_meta('_superwoo_recovery_historical_order_number', true);
        if ($historical_number !== '') { echo '<p><strong>' . esc_html__('Historical order number', 'superwoo') . ':</strong> ' . esc_html($historical_number) . '</p>'; }
        echo '<p><strong>' . esc_html('csv' === $source ? __('CSV-reported Razorpay status', 'superwoo') : __('Verified Razorpay payment status', 'superwoo')) . ':</strong> ' . esc_html((string) $order->get_meta('_superwoo_recovery_razorpay_status', true)) . '</p>';
        if ('csv' !== $source) { echo '<p><strong>' . esc_html__('Razorpay status checked at', 'superwoo') . ':</strong> ' . esc_html((string) $order->get_meta('_superwoo_recovery_razorpay_verified_at_utc', true)) . ' UTC</p>'; }
        if ('csv' === $source) { echo '<p><strong>' . esc_html__('CSV payment timestamp', 'superwoo') . ':</strong> ' . esc_html((string) $order->get_meta('_superwoo_recovery_provider_created_at_utc', true)) . ' UTC</p>'; }
        foreach ([
            '_superwoo_recovery_csv_method' => __('Payment method in CSV', 'superwoo'),
            '_superwoo_recovery_csv_description' => __('Payment description in CSV', 'superwoo'),
            '_superwoo_recovery_csv_shipping_method_name' => __('Shipping method name in CSV', 'superwoo'),
        ] as $key => $label) {
            $value = (string) $order->get_meta($key, true);
            if ($value !== '') { echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</p>'; }
        }
        foreach (['_superwoo_recovery_csv_gateway_fee_minor' => __('Gateway fee (paise)', 'superwoo'), '_superwoo_recovery_csv_gateway_fee_tax_minor' => __('Gateway fee tax (paise)', 'superwoo')] as $key => $label) {
            $value = $order->get_meta($key, true);
            if ($value !== '') { echo '<p><strong>' . esc_html($label) . ':</strong> ' . esc_html((string) $value) . '</p>'; }
        }
        echo '</div>';
    }

    public static function add_order_list_column($columns) {
        $columns['superwoo_razorpay_status'] = __('Razorpay status', 'superwoo');
        return $columns;
    }

    public static function render_hpos_order_list_column($column, $order) {
        if ('superwoo_razorpay_status' === $column && $order instanceof WC_Order) { self::print_razorpay_status($order); }
    }

    public static function render_legacy_order_list_column($column, $post_id) {
        if ('superwoo_razorpay_status' === $column && function_exists('wc_get_order')) { self::print_razorpay_status(wc_get_order(absint($post_id))); }
    }

    private static function print_razorpay_status($order) {
        $status = $order instanceof WC_Order ? (string) $order->get_meta('_superwoo_recovery_razorpay_status', true) : '';
        $source = $order instanceof WC_Order ? (string) $order->get_meta('_superwoo_recovery_payment_evidence', true) : '';
        echo esc_html($status !== '' ? $status . ('csv' === $source ? ' (CSV)' : ' (verified)') : '—');
    }

    private static function claim_key($payment_id) {
        return 'superwoo_recovery_payment_' . hash('sha256', (string) $payment_id);
    }

    private static function historical_claim_key($historical_id) {
        return 'superwoo_recovery_historical_' . absint($historical_id);
    }

    public static function claim($payment_id) {
        return get_option(self::claim_key($payment_id), false);
    }

    /** Create payment-only review evidence; no product, gift, shipping or tax is invented. */
    public static function create($record, $rules, $actor_id, $evidence_mode = 'provider') {
        if (!current_user_can('manage_woocommerce')) {
            return new WP_Error('payment_record_forbidden', __('You are not allowed to create a recovery review order.', 'superwoo'));
        }
        $payment_id = (string) ($record['payment_id'] ?? '');
        if (!preg_match('/^pay_[A-Za-z0-9]{5,64}$/D', $payment_id) || empty($record['id']) || empty($record['wc_order_id']) || !preg_match('/^[a-f0-9]{64}$/D', (string) ($record['source_row_hash'] ?? ''))) {
            return new WP_Error('payment_record_missing_reference', __('A payment, historical WooCommerce ID and source fingerprint are required.', 'superwoo'));
        }
        if (self::claim($payment_id)) { return new WP_Error('payment_record_already_claimed', __('This payment has already been claimed for review.', 'superwoo')); }
        if (!function_exists('wc_get_orders') || !class_exists('WC_Order')) { return new WP_Error('payment_record_woocommerce_unavailable', __('WooCommerce order CRUD is unavailable.', 'superwoo')); }

        $match = SuperWoo_Order_Recovery_Classifier::find_existing_order($payment_id, absint($record['wc_order_id']), (string) ($record['wc_order_number'] ?? ''));
        $row = [
            'payment_id' => $payment_id, 'wc_order_id' => absint($record['wc_order_id']),
            'receipt' => (string) ($record['receipt'] ?? ''), 'description' => (string) ($record['description'] ?? ''),
            'notes' => json_decode((string) ($record['notes_json'] ?? ''), true) ?: [],
            'reported_status' => (string) ($record['reported_status'] ?? ''),
        ];
        $classification = SuperWoo_Order_Recovery_Classifier::classify($row, $match, $rules, !empty($record['duplicate_count']));
        if ('missing_order_candidate' !== $classification['classification']) {
            return new WP_Error('payment_record_not_missing_store_order', __('This payment is existing, Workshop-related, ambiguous or otherwise not a missing store order.', 'superwoo'));
        }

        if ('csv' === $evidence_mode) {
            $provider = self::csv_payment_facts($record);
            if (is_wp_error($provider)) { return $provider; }
        } elseif ('provider' === $evidence_mode) {
            $provider = (new SuperWoo_Order_Recovery_Provider())->verify($payment_id);
            if (is_wp_error($provider)) { return $provider; }
            $provider = SuperWoo_Order_Recovery_Provider::correlate($record, $provider, $match);
            if ('verified' !== $provider['state'] || 'captured' !== $provider['status'] || true !== $provider['captured'] || empty($provider['refund_evidence_complete']) || (int) $provider['amount_refunded_minor'] !== 0 || !empty($provider['refunds'])) {
                return new WP_Error('payment_record_provider_conflict', __('The provider did not verify an unrefunded captured payment matching this import.', 'superwoo'));
            }
            if ('INR' !== $provider['currency'] || (int) $provider['amount_minor'] <= 0 || empty($record['currency']) || 'INR' !== strtoupper((string) $record['currency']) || null === $record['gross_minor'] || (int) $record['gross_minor'] !== (int) $provider['amount_minor']) {
                return new WP_Error('payment_record_amount_conflict', __('The provider amount or currency does not match the imported payment.', 'superwoo'));
            }
        } else { return new WP_Error('payment_record_evidence_mode_invalid', __('The recovery evidence mode is invalid.', 'superwoo')); }

        // The unique option name is a database-backed claim across PHP workers.
        $key = self::claim_key($payment_id);
        $claimed = add_option($key, [
            'state' => 'claimed', 'record_id' => absint($record['id']), 'source_row_hash' => $record['source_row_hash'],
            'actor_id' => absint($actor_id), 'claimed_at_utc' => gmdate('Y-m-d H:i:s'), 'order_id' => 0,
        ], '', false);
        if (!$claimed) { return new WP_Error('payment_record_already_claimed', __('Another request has already claimed this payment.', 'superwoo')); }
        if (!add_option(self::historical_claim_key($record['wc_order_id']), [
            'payment_id_hash' => hash('sha256', $payment_id), 'record_id' => absint($record['id']),
            'claimed_at_utc' => gmdate('Y-m-d H:i:s'),
        ], '', false)) {
            return new WP_Error('payment_record_historical_id_claimed', __('This historical WooCommerce ID was already claimed; reconcile both claims before retrying.', 'superwoo'));
        }

        // A changed import or a newly visible order after the claim requires manual reconciliation.
        $fresh = (new SuperWoo_Order_Recovery_Repository())->transaction(absint($record['id']));
        if (!$fresh || !hash_equals((string) $record['source_row_hash'], (string) $fresh['source_row_hash'])) {
            return new WP_Error('payment_record_source_changed', __('The import changed during approval; reconcile the claim before retrying.', 'superwoo'));
        }
        $fresh_match = SuperWoo_Order_Recovery_Classifier::find_existing_order($payment_id, absint($record['wc_order_id']), (string) ($record['wc_order_number'] ?? ''));
        if (null !== $fresh_match) { return new WP_Error('payment_record_order_changed', __('An order appeared during approval; reconcile the claim before retrying.', 'superwoo')); }

        try {
            $order = new WC_Order();
            $order->set_status(self::STATUS);
            $order->set_created_via('superwoo-recovery');
            $order->set_currency('INR');
            $order->set_payment_method('razorpay');
            $csv_method = sanitize_key((string) ($record['payment_method'] ?? ''));
            $order->set_payment_method_title('csv' === $evidence_mode ? ($csv_method ? 'Razorpay ' . strtoupper($csv_method) . ' (CSV reported)' : 'Razorpay (CSV reported)') : 'Razorpay');
            $order->set_transaction_id($payment_id);
            $order->set_total(number_format(((int) $provider['amount_minor']) / 100, 2, '.', ''));
            if (!empty($record['customer_email']) && is_email($record['customer_email'])) { $order->set_billing_email($record['customer_email']); }
            if (!empty($record['customer_phone'])) { $order->set_billing_phone($record['customer_phone']); }
            $order->update_meta_data('_superwoo_recovery_payment_only', 'yes');
            $order->update_meta_data('_superwoo_recovery_record_id', absint($record['id']));
            $order->update_meta_data('_superwoo_recovery_historical_order_id', absint($record['wc_order_id']));
            $order->update_meta_data('_superwoo_recovery_historical_order_number', sanitize_text_field((string) ($record['wc_order_number'] ?? '')));
            $order->update_meta_data('_superwoo_recovery_source_row_hash', $record['source_row_hash']);
            $order->update_meta_data('_superwoo_recovery_provider_created_at_utc', (string) ($provider['created_at_utc'] ?? ''));
            $order->update_meta_data('_superwoo_recovery_provider_amount_minor', (int) $provider['amount_minor']);
            $order->update_meta_data('_superwoo_recovery_payment_evidence', $evidence_mode);
            if ('csv' === $evidence_mode) {
                $order->update_meta_data('_superwoo_recovery_csv_batch_id', absint($record['last_batch_id'] ?? 0));
                if ($csv_method) { $order->update_meta_data('_superwoo_recovery_csv_method', $csv_method); }
                $csv_description = substr(sanitize_text_field((string) ($record['description'] ?? '')), 0, 500);
                if ($csv_description !== '') { $order->update_meta_data('_superwoo_recovery_csv_description', $csv_description); }
                $csv_notes = json_decode((string) ($record['notes_json'] ?? ''), true);
                if (is_array($csv_notes) && isset($csv_notes['shipping_method_name']) && is_scalar($csv_notes['shipping_method_name'])) { $order->update_meta_data('_superwoo_recovery_csv_shipping_method_name', substr(sanitize_text_field((string) $csv_notes['shipping_method_name']), 0, 190)); }
                if (null !== ($record['fee_minor'] ?? null)) { $order->update_meta_data('_superwoo_recovery_csv_gateway_fee_minor', (int) $record['fee_minor']); }
                if (null !== ($record['fee_gst_minor'] ?? null)) { $order->update_meta_data('_superwoo_recovery_csv_gateway_fee_tax_minor', (int) $record['fee_gst_minor']); }
            }
            $order->update_meta_data('_superwoo_recovery_razorpay_status', sanitize_key((string) $provider['status']));
            if ('provider' === $evidence_mode) { $order->update_meta_data('_superwoo_recovery_razorpay_verified_at_utc', gmdate('Y-m-d H:i:s')); }
            $order->update_meta_data('_superwoo_recovery_razorpay_refunded_minor', (int) $provider['amount_refunded_minor']);
            $order->update_meta_data('_superwoo_recovery_razorpay_order_id', (string) ($provider['order_id'] ?? $record['razorpay_order_id'] ?? ''));
            $order->update_meta_data('_superwoo_recovery_receipt', (string) ($record['receipt'] ?? ''));
            $order->update_meta_data('_superwoo_recovery_imported_fee_minor', null === ($record['fee_minor'] ?? null) ? '' : (int) $record['fee_minor']);
            $order->update_meta_data('_superwoo_recovery_imported_fee_gst_minor', null === ($record['fee_gst_minor'] ?? null) ? '' : (int) $record['fee_gst_minor']);
            $order->update_meta_data('_superwoo_recovery_items_missing', 'yes');
            $order_id = $order->save();
            if (!$order_id) { return new WP_Error('payment_record_save_failed', __('WooCommerce did not save the review order; inspect the claim before retrying.', 'superwoo')); }
            update_option($key, [
                'state' => 'created', 'record_id' => absint($record['id']), 'source_row_hash' => $record['source_row_hash'],
                'actor_id' => absint($actor_id), 'claimed_at_utc' => gmdate('Y-m-d H:i:s'), 'order_id' => absint($order_id),
            ], false);
            $repo = new SuperWoo_Order_Recovery_Repository();
            if ('provider' === $evidence_mode) { $repo->save_provider_verification(absint($record['id']), $provider); }
            $repo->mark_recovered(absint($record['id']), absint($order_id));
            return absint($order_id);
        } catch (Throwable $error) {
            return new WP_Error('payment_record_save_failed', __('WooCommerce did not complete the review order; inspect the claim before retrying.', 'superwoo'));
        }
    }

    public static function csv_payment_facts($record) {
        if ('captured' !== strtolower((string) ($record['reported_status'] ?? '')) || 'INR' !== strtoupper((string) ($record['currency'] ?? '')) || !isset($record['gross_minor']) || (int) $record['gross_minor'] <= 0 || !isset($record['refund_minor']) || 0 !== (int) $record['refund_minor'] || !empty($record['refund_status']) || empty($record['razorpay_order_id'])) {
            return new WP_Error('payment_record_csv_evidence_incomplete', __('CSV-only recovery requires a captured INR payment, positive amount, zero reported refund, and Razorpay order ID.', 'superwoo'));
        }
        return [
            'status' => 'captured', 'amount_minor' => (int) $record['gross_minor'], 'currency' => 'INR',
            'amount_refunded_minor' => 0, 'order_id' => (string) $record['razorpay_order_id'],
            'created_at_utc' => (string) ($record['paid_at_utc'] ?? ''),
        ];
    }

    /** Add only fields explicitly present in a second CSV to an existing CSV-only review order. */
    public static function enrich_from_csv_row($row, $file_hash) {
        if (!current_user_can('manage_woocommerce')) { return new WP_Error('recovery_enrich_forbidden', __('You are not allowed to enrich recovery orders.', 'superwoo')); }
        $payment_id = (string) ($row['payment_id'] ?? '');
        $claim = $payment_id !== '' ? self::claim($payment_id) : false;
        if (!is_array($claim) || 'created' !== ($claim['state'] ?? '') || empty($claim['order_id']) || empty($row['wc_order_id'])) {
            return new WP_Error('recovery_enrich_no_claim', __('No completed recovery claim matches this CSV row.', 'superwoo'));
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) $file_hash) || !function_exists('wc_get_order')) { return new WP_Error('recovery_enrich_invalid_source', __('The enrichment source is invalid.', 'superwoo')); }
        $facts = self::csv_payment_facts($row);
        if (is_wp_error($facts)) { return $facts; }
        $order = wc_get_order(absint($claim['order_id']));
        if (!$order instanceof WC_Order || 'yes' !== $order->get_meta('_superwoo_recovery_payment_only', true) || 'csv' !== $order->get_meta('_superwoo_recovery_payment_evidence', true) || absint($order->get_meta('_superwoo_recovery_historical_order_id', true)) !== absint($row['wc_order_id']) || (string) $order->get_transaction_id() !== $payment_id || strtoupper((string) $order->get_currency()) !== 'INR' || (int) round((float) $order->get_total() * 100) !== (int) $facts['amount_minor']) {
            return new WP_Error('recovery_enrich_order_mismatch', __('The recovered order does not match the CSV payment ID, original order ID, currency and amount.', 'superwoo'));
        }
        if ((string) $order->get_meta('_superwoo_recovery_enrichment_file_sha256', true) === (string) $file_hash && (string) $order->get_meta('_superwoo_recovery_enrichment_source_row_hash', true) === (string) ($row['source_row_hash'] ?? '')) {
            return ['order_id' => $order->get_id(), 'changed' => false];
        }
        $method = sanitize_key((string) ($row['payment_method'] ?? ''));
        if ($method !== '') {
            $order->update_meta_data('_superwoo_recovery_csv_method', $method);
            $order->set_payment_method_title('Razorpay ' . strtoupper($method) . ' (CSV reported)');
        }
        $description = substr(sanitize_text_field((string) ($row['description'] ?? '')), 0, 500);
        if ($description !== '') { $order->update_meta_data('_superwoo_recovery_csv_description', $description); }
        $notes = is_array($row['notes'] ?? null) ? $row['notes'] : [];
        $shipping_name = isset($notes['shipping_method_name']) && is_scalar($notes['shipping_method_name']) ? substr(sanitize_text_field((string) $notes['shipping_method_name']), 0, 190) : '';
        if ($shipping_name !== '') { $order->update_meta_data('_superwoo_recovery_csv_shipping_method_name', $shipping_name); }
        if (null !== ($row['fee_minor'] ?? null)) { $order->update_meta_data('_superwoo_recovery_csv_gateway_fee_minor', (int) $row['fee_minor']); }
        if (null !== ($row['fee_gst_minor'] ?? null)) { $order->update_meta_data('_superwoo_recovery_csv_gateway_fee_tax_minor', (int) $row['fee_gst_minor']); }
        if (!$order->get_billing_email() && !empty($row['customer_email']) && is_email($row['customer_email'])) { $order->set_billing_email($row['customer_email']); }
        if (!$order->get_billing_phone() && !empty($row['customer_phone'])) { $order->set_billing_phone($row['customer_phone']); }
        $order->update_meta_data('_superwoo_recovery_enrichment_file_sha256', (string) $file_hash);
        $order->update_meta_data('_superwoo_recovery_enrichment_source_row_hash', (string) ($row['source_row_hash'] ?? ''));
        $order->update_meta_data('_superwoo_recovery_enriched_at_utc', gmdate('Y-m-d H:i:s'));
        $saved = $order->save();
        return $saved ? ['order_id' => absint($saved), 'changed' => true] : new WP_Error('recovery_enrich_save_failed', __('The recovered order could not be updated.', 'superwoo'));
    }
}

<?php
defined('ABSPATH') || exit;

/** Pure dry-run evaluator. It never saves or mutates WooCommerce or provider data. */
class SuperWoo_Order_Recovery_Preview {
    private const REQUIRED_ADDRESS = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];

    public static function build($record, $evidence = null, $order_match = null) {
        $result = [
            'outcome' => 'incomplete_evidence', 'reason_codes' => [], 'missing_fields' => [],
            'proposed_order' => null, 'provenance' => [],
            'payment' => [
                'payment_id' => (string) ($record['payment_id'] ?? ''),
                'verification_state' => (string) ($record['verification_state'] ?? 'unverified'),
                'provider_status' => (string) ($record['provider_status'] ?? ''),
                'captured' => isset($record['provider_captured']) ? (int) $record['provider_captured'] === 1 : null,
                'provider_gross_minor' => self::nullable_int($record['provider_amount_minor'] ?? null),
                'currency' => (string) ($record['provider_currency'] ?? $record['currency'] ?? ''),
                'refund_minor' => self::nullable_int($record['provider_amount_refunded_minor'] ?? $record['refund_minor'] ?? null),
                'refund_status' => (string) ($record['provider_refund_status'] ?? $record['refund_status'] ?? ''),
                'refund_evidence_complete' => !empty($record['provider_refunds_complete']),
                'fee_minor' => self::nullable_int($record['fee_minor'] ?? null),
                'fee_gst_minor' => self::nullable_int($record['fee_gst_minor'] ?? null),
            ],
            'reconciliation' => [
                'imported_woo_total_minor' => self::nullable_int($record['wc_total_minor'] ?? null),
                'imported_difference_minor' => self::nullable_int($record['reconciled_difference_minor'] ?? null),
                'gateway_fee_separate' => true,
            ],
            'side_effects' => ['order_write' => false, 'payment_write' => false, 'email' => false, 'stock' => false, 'fulfillment' => false],
        ];

        $classification = (string) ($record['classification'] ?? 'manual_review');
        if ('workshop_excluded' === $classification) { return self::conflict($result, 'workshop_excluded'); }
        if ('duplicate_ambiguous' === $classification || !empty($record['duplicate_count'])) { return self::conflict($result, 'duplicate_payment_reference'); }
        if ('existing_order' === $classification) { return self::conflict($result, 'existing_order_classification'); }
        if ('verified_conflict' === ($record['verification_state'] ?? '')) { return self::conflict($result, 'provider_evidence_conflict'); }

        if (!function_exists('wc_get_orders') || !function_exists('wc_get_order')) {
            return self::incomplete($result, 'woocommerce_crud_unavailable', ['woocommerce_order_absence_check']);
        }
        if (!class_exists('SuperWoo_Order_Recovery_Classifier')) { return self::incomplete($result, 'woocommerce_matcher_unavailable', ['woocommerce_order_absence_check']); }
        if (null === $order_match) {
            $order_match = SuperWoo_Order_Recovery_Classifier::find_existing_order(
                (string) ($record['payment_id'] ?? ''),
                absint($record['wc_order_id'] ?? 0),
                (string) ($record['wc_order_number'] ?? '')
            );
        }
        if (!empty($order_match['ambiguous'])) { return self::conflict($result, 'ambiguous_woocommerce_match'); }
        if (!empty($order_match['conflict'])) { return self::conflict($result, 'woocommerce_reference_conflict'); }
        if (!empty($order_match['order'])) { return self::conflict($result, 'existing_woocommerce_order'); }

        $status = strtolower((string) ($record['provider_status'] ?? ''));
        $refund_status = strtolower((string) ($record['provider_refund_status'] ?? ''));
        $provider_amount = (int) ($record['provider_amount_minor'] ?? 0);
        $refunded_amount = (int) ($record['provider_amount_refunded_minor'] ?? 0);
        if (in_array($status, ['failed', 'refunded'], true) || in_array($refund_status, ['full', 'refunded'], true) || ($provider_amount > 0 && $refunded_amount >= $provider_amount)) {
            return self::conflict($result, 'provider_payment_failed_or_fully_refunded');
        }
        if (in_array($refund_status, ['partial', 'partially_refunded', 'partially refunded'], true) || $refunded_amount > 0) {
            return self::conflict($result, 'provider_payment_partially_refunded');
        }
        if ('verified' !== (string) ($record['verification_state'] ?? '')) {
            return self::incomplete($result, 'provider_payment_unverified');
        }
        if (empty($record['provider_refunds_complete'])) { return self::incomplete($result, 'refund_evidence_incomplete', ['provider.refunds_complete']); }
        if ('captured' !== $status || 1 !== (int) ($record['provider_captured'] ?? 0)) {
            return self::incomplete($result, in_array($status, ['authorized', 'created'], true) ? 'provider_payment_not_captured' : 'provider_capture_not_confirmed');
        }

        if (!is_array($evidence)) { return self::incomplete($result, 'authoritative_checkout_evidence_unavailable', ['customer.billing', 'line_items', 'currency', 'discounts', 'shipping', 'taxes', 'order_total']); }
        $source_type = (string) ($evidence['source_type'] ?? '');
        $source_reference = trim((string) ($evidence['source_reference'] ?? ''));
        if (!in_array($source_type, ['checkout_snapshot', 'verified_backup'], true) || '' === $source_reference || empty($evidence['authority_confirmed'])) {
            return self::incomplete($result, 'evidence_source_not_authoritative');
        }
        if (empty($evidence['payment_id']) || !hash_equals((string) ($record['payment_id'] ?? ''), (string) $evidence['payment_id'])) {
            return self::conflict($result, 'checkout_payment_id_mismatch');
        }
        $missing = self::validate_evidence($evidence);
        if ($missing) { return self::incomplete($result, 'authoritative_evidence_incomplete', $missing); }

        if (strtoupper((string) $evidence['currency']) !== strtoupper((string) $result['payment']['currency'])) {
            return self::conflict($result, 'provider_checkout_currency_mismatch');
        }
        if (self::sum_amount_rows($evidence['discounts']) !== (int) $evidence['discount_total_minor'] || self::calculate_discount($evidence) !== (int) $evidence['discount_total_minor']) {
            return self::conflict($result, 'authoritative_discount_arithmetic_mismatch');
        }
        if (self::sum_amount_rows($evidence['shipping_lines']) !== (int) $evidence['shipping_total_minor'] || self::sum_tax_rows($evidence['shipping_lines']) !== (int) $evidence['shipping_tax_minor']) {
            return self::conflict($result, 'authoritative_shipping_arithmetic_mismatch');
        }
        if (self::sum_tax_rows($evidence['tax_lines']) !== (int) $evidence['tax_total_minor'] || self::calculate_tax($evidence) !== (int) $evidence['tax_total_minor'] || !self::tax_detail_consistent($evidence)) {
            return self::conflict($result, 'authoritative_tax_arithmetic_mismatch');
        }

        $expected = self::calculate_order_total($evidence);
        if ($expected !== (int) $evidence['order_total_minor']) {
            return self::conflict($result, 'authoritative_order_total_arithmetic_mismatch');
        }
        $result['proposed_order'] = self::proposed_order($evidence);
        $provenance = ['source_type' => $source_type, 'source_reference' => $source_reference, 'evidence_class' => 'authoritative'];
        $result['provenance'] = self::provenance_map($result['proposed_order'], '', $provenance);
        $result['reconciliation']['proposed_woo_total_minor'] = (int) $evidence['order_total_minor'];
        $result['reconciliation']['provider_minus_proposed_minor'] = null === $result['payment']['provider_gross_minor'] ? null : $result['payment']['provider_gross_minor'] - (int) $evidence['order_total_minor'];
        $result['outcome'] = 'ready_for_review';
        $result['reason_codes'][] = 'complete_authoritative_evidence';
        return $result;
    }

    private static function validate_evidence($evidence) {
        $missing = [];
        $required = ['currency', 'discount_total_minor', 'discounts', 'shipping_required', 'shipping_total_minor', 'shipping_tax_minor', 'shipping_lines', 'tax_total_minor', 'tax_lines', 'fee_lines', 'order_total_minor', 'line_items', 'billing'];
        foreach ($required as $key) { if (!array_key_exists($key, $evidence) || null === $evidence[$key] || '' === $evidence[$key]) { $missing[] = $key; } }
        if (!isset($evidence['currency']) || !preg_match('/^[A-Z]{3}$/D', (string) $evidence['currency'])) { $missing[] = 'currency'; }
        if (!isset($evidence['billing']) || !is_array($evidence['billing'])) { $missing[] = 'customer.billing'; }
        else {
            foreach (self::REQUIRED_ADDRESS as $key) { if (empty($evidence['billing'][$key])) { $missing[] = 'customer.billing.' . $key; } }
            if (empty($evidence['billing']['email']) || !is_email($evidence['billing']['email'])) { $missing[] = 'customer.billing.email'; }
        }
        if (!empty($evidence['shipping_required'])) {
            if (empty($evidence['shipping']) || !is_array($evidence['shipping'])) { $missing[] = 'customer.shipping'; }
            else { foreach (self::REQUIRED_ADDRESS as $key) { if (empty($evidence['shipping'][$key])) { $missing[] = 'customer.shipping.' . $key; } } }
        }
        if (!isset($evidence['line_items']) || !is_array($evidence['line_items']) || !$evidence['line_items']) { $missing[] = 'line_items'; }
        else {
            foreach ($evidence['line_items'] as $index => $line) {
                if (!is_array($line)) { $missing[] = 'line_items.' . $index; continue; }
                foreach (['product_id', 'variation_id', 'name', 'quantity', 'subtotal_minor', 'total_minor', 'tax_minor', 'taxes'] as $key) {
                    if (!array_key_exists($key, $line) || (in_array($key, ['product_id','name','quantity','subtotal_minor','total_minor','tax_minor'], true) && '' === $line[$key])) { $missing[] = 'line_items.' . $index . '.' . $key; }
                }
                foreach (['product_id','variation_id','subtotal_minor','total_minor','tax_minor'] as $key) { if (isset($line[$key]) && !self::is_minor_unit($line[$key])) { $missing[] = 'line_items.' . $index . '.' . $key; } }
                if (isset($line['quantity']) && (!is_numeric($line['quantity']) || (float)$line['quantity'] <= 0)) { $missing[] = 'line_items.' . $index . '.quantity'; }
                if (!empty($line['superwoo_free_gift']) && ((int) ($line['subtotal_minor'] ?? -1) !== 0 || (int) ($line['total_minor'] ?? -1) !== 0 || (int) ($line['tax_minor'] ?? -1) !== 0)) { $missing[] = 'line_items.' . $index . '.free_gift_amounts'; }
                if (empty($line['product_id']) || !isset($line['variation_id']) || !function_exists('wc_get_product')) { $missing[] = 'line_items.' . $index . '.woocommerce_product'; }
                else {
                    $product = wc_get_product(absint($line['product_id']));
                    $variation = absint($line['variation_id']) ? wc_get_product(absint($line['variation_id'])) : false;
                    if (!$product || (absint($line['variation_id']) && (!$variation || !method_exists($variation, 'get_parent_id') || absint($variation->get_parent_id()) !== absint($line['product_id'])))) { $missing[] = 'line_items.' . $index . '.woocommerce_product'; }
                }
                if (isset($line['taxes']) && is_array($line['taxes'])) { foreach ($line['taxes'] as $tax_index => $tax) { if (!is_array($tax) || !isset($tax['rate_id'], $tax['tax_minor']) || !self::is_minor_unit($tax['tax_minor'])) { $missing[] = 'line_items.' . $index . '.taxes.' . $tax_index; } } }
            }
        }
        foreach (['discount_total_minor','shipping_total_minor','shipping_tax_minor','tax_total_minor','order_total_minor'] as $key) {
            if (!isset($evidence[$key]) || !self::is_minor_unit($evidence[$key])) { $missing[] = $key; }
        }
        foreach (['discounts','shipping_lines','tax_lines','fee_lines'] as $list) { if (!isset($evidence[$list]) || !is_array($evidence[$list])) { $missing[] = $list; } }
        if (!empty($evidence['shipping_required']) && empty($evidence['shipping_lines'])) { $missing[] = 'shipping_lines'; }
        if (isset($evidence['discounts']) && is_array($evidence['discounts'])) { foreach ($evidence['discounts'] as $index => $discount) { if (!is_array($discount) || !isset($discount['code'], $discount['amount_minor']) || !self::is_minor_unit($discount['amount_minor'])) { $missing[] = 'discounts.' . $index; } } }
        if (isset($evidence['shipping_lines']) && is_array($evidence['shipping_lines']) && self::sum_amount_rows($evidence['shipping_lines']) !== (int) ($evidence['shipping_total_minor'] ?? 0)) { $missing[] = 'shipping_breakdown_mismatch'; }
        if (isset($evidence['shipping_lines']) && is_array($evidence['shipping_lines'])) { foreach ($evidence['shipping_lines'] as $index => $shipping) { if (!is_array($shipping) || !isset($shipping['method_id'], $shipping['title'], $shipping['total_minor'], $shipping['tax_minor'], $shipping['taxes']) || !is_array($shipping['taxes']) || !self::is_minor_unit($shipping['total_minor']) || !self::is_minor_unit($shipping['tax_minor']) || self::sum_tax_rows($shipping['taxes']) !== (int) ($shipping['tax_minor'] ?? 0)) { $missing[] = 'shipping_lines.' . $index; } elseif (self::missing_tax_rate_ids($shipping['taxes'])) { $missing[] = 'shipping_lines.' . $index . '.taxes'; } } }
        if (isset($evidence['tax_lines']) && is_array($evidence['tax_lines']) && self::missing_tax_rate_ids($evidence['tax_lines'])) { $missing[] = 'tax_lines'; }
        if (isset($evidence['fee_lines']) && is_array($evidence['fee_lines'])) {
            foreach ($evidence['fee_lines'] as $index => $fee) { if (!is_array($fee) || !isset($fee['name'], $fee['total_minor'], $fee['tax_minor'], $fee['taxes']) || !self::is_minor_unit($fee['total_minor']) || !self::is_minor_unit($fee['tax_minor']) || !is_array($fee['taxes']) || self::sum_tax_rows($fee['taxes']) !== (int) ($fee['tax_minor'] ?? 0) || self::missing_tax_rate_ids($fee['taxes'])) { $missing[] = 'fee_lines.' . $index; } }
        }
        return array_values(array_unique($missing));
    }

    private static function calculate_order_total($evidence) {
        $line_total = 0; $fee_total = 0;
        foreach ($evidence['line_items'] as $line) { $line_total += (int) $line['total_minor']; }
        foreach ($evidence['fee_lines'] as $fee) { $fee_total += (int) ($fee['total_minor'] ?? 0); }
        return $line_total + (int) $evidence['shipping_total_minor'] + $fee_total + (int) $evidence['tax_total_minor'];
    }

    private static function calculate_discount($evidence) {
        $discount = 0;
        foreach ($evidence['line_items'] as $line) { $discount += (int) $line['subtotal_minor'] - (int) $line['total_minor']; }
        return $discount;
    }

    private static function calculate_tax($evidence) {
        $tax = (int) $evidence['shipping_tax_minor'];
        foreach ($evidence['line_items'] as $line) { $tax += (int) $line['tax_minor']; }
        foreach ($evidence['fee_lines'] as $fee) { $tax += (int) ($fee['tax_minor'] ?? 0); }
        return $tax;
    }

    private static function tax_detail_consistent($evidence) {
        foreach ($evidence['line_items'] as $line) { if (self::sum_tax_rows($line['taxes']) !== (int) $line['tax_minor']) { return false; } }
        foreach ($evidence['shipping_lines'] as $shipping) { if (self::sum_tax_rows($shipping['taxes']) !== (int) $shipping['tax_minor']) { return false; } }
        foreach ($evidence['fee_lines'] as $fee) { if (self::sum_tax_rows($fee['taxes']) !== (int) $fee['tax_minor']) { return false; } }
        return true;
    }

    private static function sum_amount_rows($rows) { $sum = 0; foreach ($rows as $row) { if (is_array($row)) { $sum += (int) ($row['amount_minor'] ?? $row['total_minor'] ?? 0); } } return $sum; }
    private static function sum_tax_rows($rows) { $sum = 0; foreach ($rows as $row) { if (is_array($row)) { $sum += (int) ($row['tax_minor'] ?? 0); } } return $sum; }
    private static function missing_tax_rate_ids($rows) { foreach ($rows as $row) { if (!is_array($row) || !isset($row['rate_id'], $row['tax_minor']) || !self::is_minor_unit($row['tax_minor'])) { return true; } } return false; }
    private static function is_minor_unit($value) { return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value)); }

    private static function provenance_map($value, $path, $source) {
        $map = [];
        if ('' !== $path) { $map[$path] = $source; }
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $child_path = '' === $path ? (string) $key : $path . '.' . $key;
                $map += self::provenance_map($child, $child_path, $source);
            }
        }
        return $map;
    }

    private static function proposed_order($evidence) {
        return [
            'customer' => ['billing' => $evidence['billing'], 'shipping_required' => (bool) $evidence['shipping_required'], 'shipping' => $evidence['shipping'] ?? null],
            'line_items' => array_values($evidence['line_items']), 'currency' => $evidence['currency'],
            'discounts' => array_values($evidence['discounts']), 'discount_total_minor' => (int) $evidence['discount_total_minor'],
            'shipping' => ['required' => (bool)$evidence['shipping_required'], 'lines' => array_values($evidence['shipping_lines']), 'total_minor' => (int) $evidence['shipping_total_minor'], 'tax_minor' => (int) $evidence['shipping_tax_minor']],
            'tax_lines' => array_values($evidence['tax_lines']), 'tax_total_minor' => (int) $evidence['tax_total_minor'], 'fee_lines' => array_values($evidence['fee_lines']),
            'order_total_minor' => (int) $evidence['order_total_minor'], 'status' => 'pending_review_only',
        ];
    }

    private static function conflict($result, $reason) { $result['outcome'] = 'conflict'; $result['reason_codes'][] = $reason; return $result; }
    private static function incomplete($result, $reason, $missing = []) { $result['outcome'] = 'incomplete_evidence'; $result['reason_codes'][] = $reason; $result['missing_fields'] = array_values(array_unique(array_merge($result['missing_fields'], $missing))); return $result; }
    private static function nullable_int($value) { return null === $value || '' === $value ? null : (int) $value; }
}

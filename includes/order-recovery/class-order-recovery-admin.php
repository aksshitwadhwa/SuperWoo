<?php
defined('ABSPATH') || exit;

/** WordPress admin UI and secure CSV intake for read-only Order Recovery. */
class SuperWoo_Order_Recovery_Admin {
    const CAPABILITY = 'manage_woocommerce';

    public function hooks() {
        add_action('admin_init', [$this, 'maybe_install_schema']);
        add_action('admin_init', [$this, 'export_previews']);
        add_action('admin_post_superwoo_order_recovery_import', [$this, 'import_csv']);
        add_action('admin_post_superwoo_order_recovery_save_rules', [$this, 'save_rules']);
        add_action('admin_post_superwoo_order_recovery_verify', [$this, 'verify_provider']);
        add_action('admin_post_superwoo_order_recovery_create_payment_record', [$this, 'create_payment_record']);
    }

    public function maybe_install_schema() {
        if (!is_admin() || !isset($_GET['page']) || !is_scalar($_GET['page']) || 'superwoo-order-recovery' !== sanitize_key(wp_unslash((string) $_GET['page'])) || !current_user_can(self::CAPABILITY)) { return; }
        SuperWoo_Order_Recovery_Schema::maybe_install();
    }

    private static function repository() { return new SuperWoo_Order_Recovery_Repository(); }

    public function import_csv() {
        if (!current_user_can(self::CAPABILITY) || !check_admin_referer('superwoo_order_recovery_import')) {
            wp_die(esc_html__('You are not allowed to import recovery data.', 'superwoo'));
        }
        SuperWoo_Order_Recovery_Schema::maybe_install();
        if (empty($_FILES['razorpay_csv']) || !is_array($_FILES['razorpay_csv'])) { $this->fail('missing_upload'); }
        $file = $_FILES['razorpay_csv'];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        $tmp_name = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        if (UPLOAD_ERR_OK !== $error || $tmp_name === '' || !is_uploaded_file($tmp_name)) { $this->fail('upload_error_' . $error); }
        $raw_name = isset($file['name']) && is_scalar($file['name']) ? (string) $file['name'] : 'razorpay.csv';
        $name = sanitize_file_name(wp_unslash($raw_name));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') { $this->fail('invalid_extension'); }
        $size = isset($file['size']) ? absint($file['size']) : 0;
        if ($size < 1 || $size > SuperWoo_Order_Recovery_CSV::MAX_BYTES) { $this->fail('invalid_size'); }
        $parsed = SuperWoo_Order_Recovery_CSV::parse($tmp_name);
        if (is_wp_error($parsed)) { $this->fail($parsed->get_error_code()); }

        $file_hash = hash_file('sha256', $tmp_name);
        if (!$file_hash) { $this->fail('hash_failed'); }
        $repo = self::repository();
        $batch = $repo->create_batch($name, $file_hash, get_current_user_id());
        if (is_wp_error($batch)) { $this->fail($batch->get_error_code()); }
        if (!empty($batch['existing'])) {
            superwoo_log('Order recovery import skipped: identical file already imported', ['batch_id' => (int) $batch['id']], 'orders');
            wp_safe_redirect(add_query_arg(['page' => 'superwoo-order-recovery', 'recovery_notice' => 'already_imported', 'batch_id' => (int) $batch['id'], 'classification' => 'missing_order_candidate'], admin_url('admin.php')));
            exit;
        }
        $counts = SuperWoo_Order_Recovery_CSV::import_rows($parsed['rows'], $repo, (int) $batch['id'], self::workshop_rules());
        $repo->finish_batch((int) $batch['id'], $counts);
        superwoo_log('Order recovery CSV import completed', ['batch_id' => (int) $batch['id'], 'rows' => $counts['rows'], 'inserted' => $counts['inserted'], 'updated' => $counts['updated'], 'duplicates' => $counts['duplicates'], 'errors' => $counts['errors']], 'orders');
        wp_safe_redirect(add_query_arg(['page' => 'superwoo-order-recovery', 'recovery_notice' => 'imported', 'batch_id' => (int) $batch['id'], 'classification' => 'missing_order_candidate'], admin_url('admin.php')));
        exit;
    }

    private function fail($reason) {
        superwoo_log('Order recovery CSV import rejected', ['reason' => sanitize_key($reason)], 'warning');
        wp_safe_redirect(add_query_arg(['page' => 'superwoo-order-recovery', 'recovery_notice' => 'import_error'], admin_url('admin.php')));
        exit;
    }

    public function save_rules() {
        if (!current_user_can(self::CAPABILITY) || !check_admin_referer('superwoo_order_recovery_save_rules')) {
            wp_die(esc_html__('You are not allowed to update Workshop exclusion rules.', 'superwoo'));
        }
        $prefixes = $this->lines($_POST['receipt_prefixes'] ?? '');
        $sources = $this->lines($_POST['checkout_sources'] ?? '');
        $metadata = [];
        foreach ($this->lines($_POST['metadata_rules'] ?? '') as $rule) {
            if (strpos($rule, '=') === false) { continue; }
            [$key, $value] = array_map('trim', explode('=', $rule, 2));
            $key = sanitize_key($key); $value = sanitize_text_field($value);
            if ($key !== '' && $value !== '') { $metadata[$key] = substr($value, 0, 190); }
        }
        update_option('superwoo_order_recovery_workshop_rules', ['receipt_prefixes' => $prefixes, 'checkout_sources' => $sources, 'metadata' => $metadata], false);
        superwoo_log('Order recovery Workshop rules updated', ['receipt_prefix_count' => count($prefixes), 'source_count' => count($sources), 'metadata_rule_count' => count($metadata)], 'orders');
        wp_safe_redirect(add_query_arg(['page' => 'superwoo-order-recovery', 'recovery_notice' => 'rules_saved'], admin_url('admin.php')));
        exit;
    }

    private function lines($value) {
        if (!is_scalar($value)) { return []; }
        $lines = preg_split('/\r\n|\r|\n/', sanitize_textarea_field(wp_unslash((string) $value)));
        return array_slice(array_values(array_unique(array_filter(array_map(static function ($line) { return substr(trim(sanitize_text_field($line)), 0, 190); }, $lines)))), 0, 100);
    }

    private static function workshop_rules() { $rules = get_option('superwoo_order_recovery_workshop_rules', []); return is_array($rules) ? $rules : []; }

    private static function preview($record) {
        $match = SuperWoo_Order_Recovery_Classifier::find_existing_order((string) ($record['payment_id'] ?? ''), absint($record['wc_order_id'] ?? 0), (string) ($record['wc_order_number'] ?? ''));
        $classification_row = [
            'payment_id' => (string) ($record['payment_id'] ?? ''), 'razorpay_order_id' => (string) ($record['razorpay_order_id'] ?? ''),
            'wc_order_id' => absint($record['wc_order_id'] ?? 0), 'wc_order_number' => (string) ($record['wc_order_number'] ?? ''),
            'receipt' => (string) ($record['receipt'] ?? ''), 'reported_status' => (string) ($record['reported_status'] ?? ''),
            'description' => (string) ($record['description'] ?? ''),
            'notes' => json_decode((string) ($record['notes_json'] ?? ''), true) ?: [],
        ];
        $classification = SuperWoo_Order_Recovery_Classifier::classify($classification_row, $match, self::workshop_rules(), !empty($record['duplicate_count']));
        $record['classification'] = $classification['classification'];
        $preview = SuperWoo_Order_Recovery_Preview::build($record, null, $match);
        $preview['current_classification'] = $classification;
        return $preview;
    }

    public function export_previews() {
        if (!isset($_GET['page'], $_GET['recovery_export']) || 'superwoo-order-recovery' !== sanitize_key((string) $_GET['page']) || '1' !== (string) $_GET['recovery_export']) { return; }
        if (!current_user_can(self::CAPABILITY) || !check_admin_referer('superwoo_order_recovery_export')) { wp_die(esc_html__('You are not allowed to export recovery previews.', 'superwoo')); }
        $filters = self::filters_from_request();
        $filters['per_page'] = 20;
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=superwoo-recovery-review.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['record_id','outcome','reason_codes','payment_id','razorpay_order_id','woocommerce_order_id','verification_state','provider_status','provider_captured','provider_currency','provider_gross_minor','refund_minor','fee_minor','fee_gst_minor','woo_total_minor','gross_minus_woo_minor','missing_fields','provenance']);
        $result = self::repository()->transactions($filters);
        foreach ($result['items'] as $record) {
            $preview = self::preview($record);
            $row = [(int)$record['id'], $preview['outcome'], implode('|',$preview['reason_codes']), $record['payment_id'], $record['razorpay_order_id'], $record['wc_order_id'], $record['verification_state'], $record['provider_status'], null === $record['provider_captured'] ? '' : (int)$record['provider_captured'], $record['provider_currency'], $record['provider_amount_minor'], $record['provider_amount_refunded_minor'], $record['fee_minor'], $record['fee_gst_minor'], $record['wc_total_minor'], $record['reconciled_difference_minor'], implode('|',$preview['missing_fields']), wp_json_encode($preview['provenance'])];
            fputcsv($output, array_map([__CLASS__, 'spreadsheet_safe'], $row));
        }
        fclose($output);
        exit;
    }

    public static function spreadsheet_safe($value) {
        if (is_string($value) && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) { return "'" . $value; }
        return $value;
    }

    private static function filters_from_request() {
        return [
            'classification' => sanitize_key(self::query_value('classification')),
            'reported_status' => sanitize_text_field(self::query_value('reported_status')),
            'currency' => sanitize_text_field(self::query_value('currency')),
            'date_from' => self::valid_date(self::query_value('date_from')),
            'date_to' => self::valid_date(self::query_value('date_to')),
            'amount_min' => sanitize_text_field(self::query_value('amount_min')),
            'amount_max' => sanitize_text_field(self::query_value('amount_max')),
            'batch_id' => absint(self::query_value('batch_id')),
            'verification_state' => in_array(self::query_value('verification_state'), ['unverified','verified','verified_conflict','error'], true) ? self::query_value('verification_state') : '',
            'page' => max(1, absint(self::query_value('paged'))), 'per_page' => 20,
        ];
    }

    public function verify_provider() {
        if (!current_user_can(self::CAPABILITY) || !check_admin_referer('superwoo_order_recovery_verify')) { wp_die(esc_html__('You are not allowed to verify recovery records.', 'superwoo')); }
        SuperWoo_Order_Recovery_Schema::maybe_install();
        $id = isset($_POST['transaction_id']) ? absint($_POST['transaction_id']) : 0;
        $repo = self::repository(); $record = $repo->transaction($id);
        if (!$record || empty($record['payment_id'])) { wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'verify_unavailable'], admin_url('admin.php'))); exit; }
        $result = (new SuperWoo_Order_Recovery_Provider())->verify((string) $record['payment_id']);
        if (is_wp_error($result)) {
            $repo->save_provider_verification($id, ['state'=>'error','error'=>$result->get_error_code()]);
            superwoo_log('Order recovery provider verification did not complete', ['transaction_id'=>$id,'reason'=>sanitize_key($result->get_error_code())], 'warning');
            wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'verify_error'], admin_url('admin.php'))); exit;
        }
        $local_match = SuperWoo_Order_Recovery_Classifier::find_existing_order((string) $record['payment_id'], 0);
        $result = SuperWoo_Order_Recovery_Provider::correlate($record, $result, $local_match);
        $repo->save_provider_verification($id, $result);
        superwoo_log('Order recovery provider verification completed', ['transaction_id'=>$id,'state'=>$result['state'],'provider_status'=>$result['status'],'match_result'=>$result['match_result']], 'orders');
        wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'verified'], admin_url('admin.php'))); exit;
    }

    public function create_payment_record() {
        $id = isset($_POST['transaction_id']) && is_scalar($_POST['transaction_id']) ? absint(wp_unslash($_POST['transaction_id'])) : 0;
        if (!current_user_can(self::CAPABILITY) || !$id || !check_admin_referer('superwoo_order_recovery_create_' . $id)) {
            wp_die(esc_html__('You are not allowed to create this recovery review record.', 'superwoo'));
        }
        if (!isset($_POST['acknowledge_payment_only']) || !is_scalar($_POST['acknowledge_payment_only']) || '1' !== (string) wp_unslash($_POST['acknowledge_payment_only'])) {
            wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'payment_record_ack_required'], admin_url('admin.php'))); exit;
        }
        $record = self::repository()->transaction($id);
        $result = $record ? SuperWoo_Order_Recovery_Payment_Record::create($record, self::workshop_rules(), get_current_user_id()) : new WP_Error('payment_record_not_found', __('The recovery record no longer exists.', 'superwoo'));
        if (is_wp_error($result)) {
            superwoo_log('Payment-only recovery review order was not created', ['record_id'=>$id,'reason'=>sanitize_key($result->get_error_code())], 'warning');
            wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'payment_record_error','recovery_reason'=>sanitize_key($result->get_error_code())], admin_url('admin.php'))); exit;
        }
        superwoo_log('Payment-only recovery review order created', ['record_id'=>$id,'order_id'=>absint($result)], 'orders');
        wp_safe_redirect(add_query_arg(['page'=>'superwoo-order-recovery','transaction'=>$id,'recovery_notice'=>'payment_record_created'], admin_url('admin.php'))); exit;
    }

    public static function render_page() {
        if (!current_user_can(self::CAPABILITY)) { wp_die(esc_html__('You are not allowed to view recovery records.', 'superwoo')); }
        $repo = self::repository();
        $filters = self::filters_from_request();
        $detail = absint(self::query_value('transaction')) ? $repo->transaction(absint(self::query_value('transaction'))) : null;
        $result = $repo->transactions($filters);
        $summary = $repo->summary();
        $batches = $repo->batches(20);
        $rules = self::workshop_rules();
        ?>
        <div class="wrap superwoo-admin-page">
            <h1><?php esc_html_e('Order Recovery', 'superwoo'); ?></h1>
            <p><?php esc_html_e('Exact product-order reconstruction still requires complete checkout evidence. A referenced captured payment can instead be recorded as a payment-only WooCommerce review order after individual administrator approval and fresh provider verification.', 'superwoo'); ?></p>
            <div class="notice notice-warning inline"><p><strong><?php esc_html_e('Payment-only records contain no products or free gifts.', 'superwoo'); ?></strong> <?php esc_html_e('WooCommerce assigns a new order ID. The historical ID is retained in protected metadata. These review orders are not fulfillment-ready.', 'superwoo'); ?></p></div>
            <?php self::notice(); ?>
            <h2><?php esc_html_e('Import Razorpay CSV', 'superwoo'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="superwoo_order_recovery_import">
                <?php wp_nonce_field('superwoo_order_recovery_import'); ?>
                <input type="file" name="razorpay_csv" accept=".csv,text/csv" required>
                <?php submit_button(__('Import for review', 'superwoo'), 'primary', 'submit', false); ?>
                <p class="description"><?php esc_html_e('Maximum 10 MB and 50,000 rows. The temporary upload is parsed in place and is not copied to the media library or retained after the request.', 'superwoo'); ?></p>
                <p class="description"><?php esc_html_e('Use the Razorpay payment report or captured-payment reconciliation CSV. A mixed transactions ledger with settlement and refund rows is not accepted as an order source.', 'superwoo'); ?></p>
            </form>

            <h2><?php esc_html_e('Imported record summary', 'superwoo'); ?></h2>
            <div class="notice notice-info inline"><p><?php
                $parts = [];
                foreach ((array) $summary as $item) { $parts[] = sprintf('%s: %d', sanitize_text_field($item['classification']), (int) $item['total']); }
                echo esc_html($parts ? implode(' · ', $parts) : __('No imported records.', 'superwoo'));
            ?></p></div>

            <h2><?php esc_html_e('Workshop exclusions', 'superwoo'); ?></h2>
            <p><?php esc_html_e('Only exact configured receipt prefixes, metadata key/value pairs, or checkout source values exclude records. Generic Workshop wording only sends a record to manual review.', 'superwoo'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="superwoo_order_recovery_save_rules">
                <?php wp_nonce_field('superwoo_order_recovery_save_rules'); ?>
                <p><label for="superwoo-workshop-receipts"><strong><?php esc_html_e('Exact receipt prefixes (one per line)', 'superwoo'); ?></strong></label><br><textarea class="large-text" rows="3" id="superwoo-workshop-receipts" name="receipt_prefixes"><?php echo esc_textarea(implode("\n", (array) ($rules['receipt_prefixes'] ?? []))); ?></textarea></p>
                <p><label for="superwoo-workshop-metadata"><strong><?php esc_html_e('Exact metadata key=value rules (one per line)', 'superwoo'); ?></strong></label><br><textarea class="large-text" rows="3" id="superwoo-workshop-metadata" name="metadata_rules"><?php
                    $metadata_lines = []; foreach ((array) ($rules['metadata'] ?? []) as $key => $value) { $metadata_lines[] = $key . '=' . $value; }
                    echo esc_textarea(implode("\n", $metadata_lines));
                ?></textarea></p>
                <p><label for="superwoo-workshop-sources"><strong><?php esc_html_e('Exact checkout source values (one per line)', 'superwoo'); ?></strong></label><br><textarea class="large-text" rows="3" id="superwoo-workshop-sources" name="checkout_sources"><?php echo esc_textarea(implode("\n", (array) ($rules['checkout_sources'] ?? []))); ?></textarea></p>
                <?php submit_button(__('Save exclusion rules', 'superwoo'), 'secondary'); ?>
            </form>

        <?php if ($detail) : ?>
                <?php self::render_detail($detail); ?>
            <?php endif; ?>

            <h2><?php esc_html_e('Recovery records', 'superwoo'); ?></h2>
            <p class="description"><?php esc_html_e('No authoritative checkout/session/backup evidence source is currently connected; records without it remain incomplete. Verification is manual and read-only; provider failures do not establish a payment status.', 'superwoo'); ?></p>
            <form method="get">
                <input type="hidden" name="page" value="superwoo-order-recovery">
                <select name="classification"><option value=""><?php esc_html_e('All classifications', 'superwoo'); ?></option><?php foreach (['existing_order','missing_order_candidate','duplicate_ambiguous','workshop_excluded','manual_review'] as $classification) : ?><option value="<?php echo esc_attr($classification); ?>" <?php selected($filters['classification'], $classification); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $classification))); ?></option><?php endforeach; ?></select>
                <select name="verification_state"><option value=""><?php esc_html_e('All verification states', 'superwoo'); ?></option><?php foreach (['unverified','verified','verified_conflict','error'] as $state) : ?><option value="<?php echo esc_attr($state); ?>" <?php selected($filters['verification_state'], $state); ?>><?php echo esc_html(ucwords(str_replace('_', ' ', $state))); ?></option><?php endforeach; ?></select>
                <input type="text" name="reported_status" placeholder="<?php esc_attr_e('Reported status', 'superwoo'); ?>" value="<?php echo esc_attr($filters['reported_status']); ?>">
                <input type="text" name="currency" maxlength="3" size="4" placeholder="<?php esc_attr_e('Currency', 'superwoo'); ?>" value="<?php echo esc_attr($filters['currency']); ?>">
                <input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>"><input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
                <input type="number" step="0.01" name="amount_min" placeholder="<?php esc_attr_e('Min amount', 'superwoo'); ?>" value="<?php echo esc_attr($filters['amount_min']); ?>">
                <input type="number" step="0.01" name="amount_max" placeholder="<?php esc_attr_e('Max amount', 'superwoo'); ?>" value="<?php echo esc_attr($filters['amount_max']); ?>">
                <select name="batch_id"><option value="0"><?php esc_html_e('All import batches', 'superwoo'); ?></option><?php foreach ($batches as $batch) : ?><option value="<?php echo esc_attr((string) $batch['id']); ?>" <?php selected($filters['batch_id'], (int) $batch['id']); ?>><?php echo esc_html(sprintf('#%d · %s', $batch['id'], $batch['created_at'])); ?></option><?php endforeach; ?></select>
                <?php submit_button(__('Filter', 'superwoo'), 'secondary', '', false); ?>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=superwoo-order-recovery')); ?>"><?php esc_html_e('Reset', 'superwoo'); ?></a>
            </form>
            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_merge($_GET, ['page'=>'superwoo-order-recovery','recovery_export'=>'1']), admin_url('admin.php')), 'superwoo_order_recovery_export')); ?>"><?php esc_html_e('Export this filtered page (CSV)', 'superwoo'); ?></a></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('ID / details', 'superwoo'); ?></th><th><?php esc_html_e('Preview outcome', 'superwoo'); ?></th><th><?php esc_html_e('Classification', 'superwoo'); ?></th><th><?php esc_html_e('References', 'superwoo'); ?></th><th><?php esc_html_e('Amounts', 'superwoo'); ?></th><th><?php esc_html_e('Reported status', 'superwoo'); ?></th><th><?php esc_html_e('Timestamp (UTC)', 'superwoo'); ?></th></tr></thead><tbody>
                <?php if (!$result['items']) : ?><tr><td colspan="7"><?php esc_html_e('No records match these filters.', 'superwoo'); ?></td></tr><?php endif; ?>
                <?php foreach ($result['items'] as $item) : ?><tr>
                    <td><a href="<?php echo esc_url(add_query_arg(['page' => 'superwoo-order-recovery', 'transaction' => (int) $item['id'] ], admin_url('admin.php'))); ?>">#<?php echo esc_html((string) $item['id']); ?></a><br><?php echo esc_html(sprintf(__('Batch %d', 'superwoo'), (int) $item['last_batch_id'])); ?></td>
                    <td><?php $preview = self::preview($item); ?><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $preview['outcome']))); ?></strong><br><small><?php echo esc_html(implode(', ', $preview['reason_codes'])); ?></small></td>
                    <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $preview['current_classification']['classification']))); ?></strong><br><?php echo esc_html($preview['current_classification']['reason']); ?><br><small><?php echo esc_html($item['verification_state']); ?></small></td>
                    <td><?php echo esc_html('Payment: ' . ($item['payment_id'] ?: '—')); ?><br><?php echo esc_html('Razorpay order: ' . ($item['razorpay_order_id'] ?: '—')); ?><br><?php echo esc_html('Woo order: ' . ($item['wc_order_id'] ?: ($item['wc_order_number'] ?: '—'))); ?><br><?php echo esc_html('Receipt: ' . ($item['receipt'] ?: '—')); ?></td>
                    <td><?php echo esc_html(self::money($item['gross_minor'], $item['currency'])); ?><br><small><?php echo esc_html(sprintf(__('Fee %1$s; fee GST %2$s; refund %3$s', 'superwoo'), self::money($item['fee_minor'], $item['currency']), self::money($item['fee_gst_minor'], $item['currency']), self::money($item['refund_minor'], $item['currency']))); ?></small><br><small><?php echo esc_html(sprintf(__('Woo total %1$s; difference %2$s (fee kept separate)', 'superwoo'), self::money($item['wc_total_minor'], $item['currency']), self::money($item['reconciled_difference_minor'], $item['currency']))); ?></small></td>
                    <td><?php echo esc_html($item['reported_status'] ?: '—'); ?><br><small><?php esc_html_e('CSV reported; unverified', 'superwoo'); ?></small></td><td><?php echo esc_html($item['paid_at_utc'] ?: '—'); ?></td>
                </tr><?php endforeach; ?>
            </tbody></table>
            <?php self::pagination($result['pages'], $filters['page']); ?>

            <h2><?php esc_html_e('Import batch history', 'superwoo'); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Batch', 'superwoo'); ?></th><th><?php esc_html_e('File', 'superwoo'); ?></th><th><?php esc_html_e('SHA-256', 'superwoo'); ?></th><th><?php esc_html_e('Rows / inserted / updated / duplicates / errors', 'superwoo'); ?></th><th><?php esc_html_e('Imported (UTC)', 'superwoo'); ?></th></tr></thead><tbody>
                <?php foreach ($batches as $batch) : ?><tr><td><a href="<?php echo esc_url(add_query_arg(['page'=>'superwoo-order-recovery','batch_id'=>(int)$batch['id']], admin_url('admin.php'))); ?>">#<?php echo esc_html((string)$batch['id']); ?></a></td><td><?php echo esc_html($batch['filename']); ?></td><td><code><?php echo esc_html($batch['file_sha256']); ?></code></td><td><?php echo esc_html(implode(' / ', [(int)$batch['row_count'],(int)$batch['inserted_count'],(int)$batch['updated_count'],(int)$batch['duplicate_count'],(int)$batch['error_count']])); ?></td><td><?php echo esc_html($batch['created_at']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private static function render_detail($item) {
        $preview = self::preview($item);
        $missing = json_decode((string) $item['missing_fields'], true);
        $notes = json_decode((string) $item['notes_json'], true);
        $source = json_decode((string) $item['source_values_json'], true);
        $warnings = json_decode((string) ($item['data_warnings'] ?? '[]'), true);
        $batches = self::repository()->transaction_batches((int) $item['id']);
        ?>
        <div class="notice notice-info inline"><h2><?php echo esc_html(sprintf(__('Transaction record #%d', 'superwoo'), (int) $item['id'])); ?></h2>
            <h3><?php esc_html_e('Read-only reconstruction preview', 'superwoo'); ?></h3>
            <p><strong><?php esc_html_e('Outcome', 'superwoo'); ?>:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $preview['outcome']))); ?> · <?php echo esc_html(implode(', ', $preview['reason_codes'])); ?></p>
            <p><strong><?php esc_html_e('Original references', 'superwoo'); ?>:</strong> <?php echo esc_html(sprintf(__('Payment %1$s; Razorpay order %2$s; WooCommerce order ID %3$s; order number %4$s; receipt %5$s', 'superwoo'), $item['payment_id'] ?: '—', $item['razorpay_order_id'] ?: '—', $item['wc_order_id'] ?: '—', $item['wc_order_number'] ?: '—', $item['receipt'] ?: '—')); ?></p>
            <p><strong><?php esc_html_e('Payment evidence', 'superwoo'); ?>:</strong> <?php echo esc_html(sprintf(__('Verification %1$s; provider status %2$s; captured %3$s; provider gross %4$s; refunded %5$s; provider order %6$s', 'superwoo'), $preview['payment']['verification_state'], $preview['payment']['provider_status'] ?: '—', null === $preview['payment']['captured'] ? __('unknown', 'superwoo') : ($preview['payment']['captured'] ? __('yes', 'superwoo') : __('no', 'superwoo')), self::money($preview['payment']['provider_gross_minor'], $preview['payment']['currency']), self::money($preview['payment']['refund_minor'], $preview['payment']['currency']), $item['provider_order_id'] ?: '—')); ?></p>
            <p><strong><?php esc_html_e('Amount reconciliation', 'superwoo'); ?>:</strong> <?php echo esc_html(sprintf(__('Provider gross %1$s; imported WooCommerce total %2$s; gross/order difference %3$s; gateway fee %4$s; fee GST %5$s. Values remain separate.', 'superwoo'), self::money($preview['payment']['provider_gross_minor'], $preview['payment']['currency']), self::money($preview['reconciliation']['imported_woo_total_minor'], $item['currency']), self::money($preview['reconciliation']['imported_difference_minor'], $item['currency']), self::money($preview['payment']['fee_minor'], $item['currency']), self::money($preview['payment']['fee_gst_minor'], $item['currency']))); ?></p>
            <?php self::render_proposed_order($preview); ?>
            <p><strong><?php esc_html_e('Missing fields / evidence', 'superwoo'); ?>:</strong> <?php echo esc_html(implode(', ', $preview['missing_fields']) ?: __('None identified by the preview evaluator.', 'superwoo')); ?></p>
            <p><strong><?php esc_html_e('Field provenance', 'superwoo'); ?>:</strong> <code><?php echo esc_html(wp_json_encode($preview['provenance'])); ?></code></p>
            <p><strong><?php esc_html_e('Side effects', 'superwoo'); ?>:</strong> <?php esc_html_e('None. This preview performs no order/payment writes, email, stock, fulfillment, or provider request.', 'superwoo'); ?></p>
            <p><strong><?php esc_html_e('Current classification / reason', 'superwoo'); ?>:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $preview['current_classification']['classification'])) . ': ' . $preview['current_classification']['reason']); ?></p>
            <p><strong><?php esc_html_e('Missing fields', 'superwoo'); ?>:</strong> <?php echo esc_html(implode(', ', (array) $missing) ?: __('None', 'superwoo')); ?></p>
            <p><strong><?php esc_html_e('Data warnings', 'superwoo'); ?>:</strong> <?php echo esc_html(implode(' · ', (array) $warnings) ?: __('None', 'superwoo')); ?></p>
            <p><strong><?php esc_html_e('Refund', 'superwoo'); ?>:</strong> <?php echo esc_html(implode(' · ', array_filter([$item['refund_reference'] ?: '', $item['refund_status'] ?: '', $item['refund_at_utc'] ?: ''])) ?: '—'); ?></p>
            <p><strong><?php esc_html_e('Customer email', 'superwoo'); ?>:</strong> <?php echo esc_html(self::mask_email($item['customer_email'])); ?> &nbsp; <strong><?php esc_html_e('Phone', 'superwoo'); ?>:</strong> <?php echo esc_html(self::mask_phone($item['customer_phone'])); ?></p>
            <p><strong><?php esc_html_e('Provider verification', 'superwoo'); ?>:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $item['verification_state']))); ?><?php if (!empty($item['verified_at_utc'])) : ?> · <?php echo esc_html($item['verified_at_utc']); ?> UTC<?php endif; ?><?php if (!empty($item['verification_error'])) : ?> · <?php echo esc_html($item['verification_error']); ?><?php endif; ?></p>
            <?php if (!empty($item['provider_status'])) : ?><p><strong><?php esc_html_e('Provider evidence', 'superwoo'); ?>:</strong> <?php echo esc_html(sprintf(__('Status %1$s; captured %2$s; amount %3$s; refunded %4$s; currency %5$s; Razorpay order %6$s', 'superwoo'), $item['provider_status'], null === $item['provider_captured'] ? __('unknown', 'superwoo') : ((int)$item['provider_captured'] ? __('yes', 'superwoo') : __('no', 'superwoo')), self::money($item['provider_amount_minor'], $item['provider_currency']), self::money($item['provider_amount_refunded_minor'], $item['provider_currency']), $item['provider_currency'] ?: '—', $item['provider_order_id'] ?: '—')); ?></p><p><strong><?php esc_html_e('Evidence correlation', 'superwoo'); ?>:</strong> <?php echo esc_html($item['provider_match_result'] ?: 'unmatched'); ?>. <?php esc_html_e('An exact payment ID identifies the provider record; amount, customer and time do not establish a WooCommerce order match. WooCommerce checkout metadata is not mapped because the active gateway contract is unavailable.', 'superwoo'); ?></p><?php endif; ?>
            <?php if (!empty($item['payment_id'])) : ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="superwoo_order_recovery_verify"><input type="hidden" name="transaction_id" value="<?php echo esc_attr((string)$item['id']); ?>"><?php wp_nonce_field('superwoo_order_recovery_verify'); ?><?php submit_button(__('Verify with Razorpay (read-only)', 'superwoo'), 'secondary', 'submit', false); ?><p class="description"><?php esc_html_e('Requires SUPERWOO_RAZORPAY_KEY_ID and SUPERWOO_RAZORPAY_KEY_SECRET constants in protected site configuration. Does not create an order, call capture/refund operations, or send notifications.', 'superwoo'); ?></p></form><?php endif; ?>
            <?php $claim = !empty($item['payment_id']) ? SuperWoo_Order_Recovery_Payment_Record::claim((string) $item['payment_id']) : false; ?>
            <?php if ($claim) : ?>
                <p><strong><?php esc_html_e('Payment-only recovery claim', 'superwoo'); ?>:</strong> <?php echo esc_html((string) ($claim['state'] ?? 'claimed')); ?><?php if (!empty($claim['order_id'])) : ?> · <?php echo esc_html(sprintf(__('New WooCommerce order #%d', 'superwoo'), (int) $claim['order_id'])); ?><?php $created_order = function_exists('wc_get_order') ? wc_get_order(absint($claim['order_id'])) : false; if ($created_order && method_exists($created_order, 'get_edit_order_url')) : ?> <a href="<?php echo esc_url($created_order->get_edit_order_url()); ?>"><?php esc_html_e('Open order', 'superwoo'); ?></a><?php endif; ?><?php endif; ?></p>
            <?php elseif ('missing_order_candidate' === $preview['current_classification']['classification'] && !empty($item['wc_order_id']) && 'verified' === $item['verification_state']) : ?>
                <h3><?php esc_html_e('Create payment-only review order', 'superwoo'); ?></h3>
                <p><?php esc_html_e('This creates a WooCommerce order in Recovery review status with the verified Razorpay amount, payment ID, currency, timestamp reference and available contact details. It has no product, free-gift, shipping or tax lines and is not ready for fulfillment. WooCommerce assigns a new ID; the historical ID stays in metadata. The payment is verified again when you submit.', 'superwoo'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="superwoo_order_recovery_create_payment_record">
                    <input type="hidden" name="transaction_id" value="<?php echo esc_attr((string) $item['id']); ?>">
                    <?php wp_nonce_field('superwoo_order_recovery_create_' . (int) $item['id']); ?>
                    <p><label><input type="checkbox" name="acknowledge_payment_only" value="1" required> <?php esc_html_e('I confirm this payment-only review order has no known product or gift lines and must not be fulfilled as a complete store order.', 'superwoo'); ?></label></p>
                    <?php submit_button(__('Create payment-only review order', 'superwoo'), 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>
            <p><strong><?php esc_html_e('Source values', 'superwoo'); ?>:</strong> <code><?php echo esc_html(wp_json_encode(is_array($source) ? $source : [])); ?></code></p>
            <p><strong><?php esc_html_e('Redacted notes / metadata', 'superwoo'); ?>:</strong> <code><?php echo esc_html(wp_json_encode(is_array($notes) ? $notes : [])); ?></code></p>
            <p><strong><?php esc_html_e('Source row fingerprint', 'superwoo'); ?>:</strong> <code><?php echo esc_html($item['source_row_hash']); ?></code></p>
            <p><strong><?php esc_html_e('Import sources', 'superwoo'); ?>:</strong></p><ul><?php foreach ($batches as $batch) : ?><li><?php echo esc_html(sprintf(__('Batch #%1$d · row %2$d · %3$s · file SHA-256 %4$s', 'superwoo'), (int) $batch['id'], (int) $batch['source_row_number'], $batch['filename'], $batch['file_sha256'])); ?></li><?php endforeach; ?></ul>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=superwoo-order-recovery')); ?>"><?php esc_html_e('Close details', 'superwoo'); ?></a>
        </div>
        <?php
    }

    private static function render_proposed_order($preview) {
        if (empty($preview['proposed_order'])) {
            echo '<p><strong>' . esc_html__('Proposed order representation', 'superwoo') . ':</strong> ' . esc_html__('None. No order data is proposed without complete authoritative checkout or verified backup evidence.', 'superwoo') . '</p>';
            return;
        }
        $order = $preview['proposed_order'];
        echo '<h4>' . esc_html__('Proposed order data (review only)', 'superwoo') . '</h4>';
        echo '<p><strong>' . esc_html__('Customer billing', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['customer']['billing'])) . '</p>';
        if (!empty($order['customer']['shipping_required'])) { echo '<p><strong>' . esc_html__('Customer shipping', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['customer']['shipping'])) . '</p>'; }
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Line item', 'superwoo') . '</th><th>' . esc_html__('Quantity', 'superwoo') . '</th><th>' . esc_html__('Subtotal / total / tax (minor)', 'superwoo') . '</th><th>' . esc_html__('Tax detail', 'superwoo') . '</th></tr></thead><tbody>';
        foreach ($order['line_items'] as $line) {
            echo '<tr><td>' . esc_html(sprintf(__('%1$s (product %2$d; variation %3$d)', 'superwoo'), $line['name'], (int)$line['product_id'], (int)$line['variation_id'])) . '</td><td>' . esc_html((string)$line['quantity']) . '</td><td>' . esc_html(implode(' / ', [(string)$line['subtotal_minor'],(string)$line['total_minor'],(string)$line['tax_minor']])) . '</td><td>' . esc_html(wp_json_encode($line['taxes'])) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<p><strong>' . esc_html__('Discounts', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['discounts'])) . ' · ' . esc_html((string)$order['discount_total_minor']) . ' ' . esc_html($order['currency']) . ' minor units</p>';
        echo '<p><strong>' . esc_html__('Shipping methods', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['shipping']['lines'])) . ' · ' . esc_html((string)$order['shipping']['total_minor']) . ' ' . esc_html($order['currency']) . ' minor units</p>';
        echo '<p><strong>' . esc_html__('Tax lines', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['tax_lines'])) . ' · ' . esc_html((string)$order['tax_total_minor']) . ' ' . esc_html($order['currency']) . ' minor units</p>';
        echo '<p><strong>' . esc_html__('Fee lines', 'superwoo') . ':</strong> ' . esc_html(wp_json_encode($order['fee_lines'])) . '</p>';
        echo '<p><strong>' . esc_html__('Proposed WooCommerce total', 'superwoo') . ':</strong> ' . esc_html((string)$order['order_total_minor']) . ' ' . esc_html($order['currency']) . ' minor units. ' . esc_html__('No payment amount or gateway fee is applied to this total.', 'superwoo') . '</p>';
        echo '<h4>' . esc_html__('Provenance for each proposed field', 'superwoo') . '</h4><table class="widefat striped"><thead><tr><th>' . esc_html__('Field', 'superwoo') . '</th><th>' . esc_html__('Evidence source', 'superwoo') . '</th><th>' . esc_html__('Reference', 'superwoo') . '</th></tr></thead><tbody>';
        foreach ($preview['provenance'] as $field => $source) { echo '<tr><td>' . esc_html($field) . '</td><td>' . esc_html($source['source_type'] . ' (' . $source['evidence_class'] . ')') . '</td><td>' . esc_html($source['source_reference']) . '</td></tr>'; }
        echo '</tbody></table>';
    }

    private static function money($minor, $currency) {
        if (null === $minor || '' === $minor || '' === $currency) { return '—'; }
        $precision = in_array($currency, ['JPY','KRW','VND','CLP','XAF','XOF'], true) ? 0 : (in_array($currency, ['BHD','KWD','OMR','JOD','TND'], true) ? 3 : 2);
        if (in_array($currency, ['CLF','UYW'], true)) { $precision = 4; }
        return $currency . ' ' . number_format(((int) $minor) / pow(10, $precision), $precision, '.', ',');
    }
    private static function mask_email($email) { if (!$email || strpos($email, '@') === false) { return '—'; } [$name,$host] = explode('@',$email,2); return substr($name,0,1) . '***@' . $host; }
    private static function mask_phone($phone) { return $phone ? str_repeat('*', max(0, strlen($phone)-4)) . substr($phone,-4) : '—'; }

    private static function pagination($pages, $current) {
        if ($pages <= 1) { return; }
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($page = 1; $page <= $pages; $page++) {
            $url = add_query_arg(array_merge($_GET, ['page' => 'superwoo-order-recovery', 'paged' => $page]), admin_url('admin.php'));
            if ($page === (int) $current) { echo '<span class="button button-primary">' . esc_html((string) $page) . '</span> '; }
            else { echo '<a class="button" href="' . esc_url($url) . '">' . esc_html((string) $page) . '</a> '; }
        }
        echo '</div></div>';
    }

    private static function notice() {
        $notice = sanitize_key(self::query_value('recovery_notice'));
        $messages = [
            'imported' => __('CSV import completed. Imported status values remain unverified.', 'superwoo'),
            'already_imported' => __('This exact CSV file was already imported; no duplicate batch was created.', 'superwoo'),
            'rules_saved' => __('Workshop exclusion rules saved.', 'superwoo'),
            'verified' => __('Provider response recorded. Review any evidence mismatch before drawing conclusions.', 'superwoo'),
            'verify_error' => __('Provider verification did not complete. The payment remains unverified; inspect the record for a safe error code.', 'superwoo'),
            'verify_unavailable' => __('This record has no usable provider payment ID.', 'superwoo'),
            'import_error' => __('CSV import was rejected. Check the file format, size, headers, and upload status.', 'superwoo'),
            'payment_record_created' => __('Payment-only WooCommerce review order created. It has no product lines and must be completed manually only after item evidence is found.', 'superwoo'),
            'payment_record_error' => __('Payment-only order creation stopped. Review the reason and any existing claim before retrying.', 'superwoo'),
            'payment_record_ack_required' => __('Confirm that this is a payment-only record before creating it.', 'superwoo'),
        ];
        if (isset($messages[$notice])) { echo '<div class="notice notice-' . (in_array($notice, ['import_error','payment_record_error'], true) ? 'error' : 'success') . '"><p>' . esc_html($messages[$notice]) . '</p></div>'; }
        if ('payment_record_error' === $notice && self::query_value('recovery_reason')) { echo '<p><code>' . esc_html(sanitize_key(self::query_value('recovery_reason'))) . '</code></p>'; }
    }

    private static function query_value($key) { return isset($_GET[$key]) && is_scalar($_GET[$key]) ? (string) wp_unslash($_GET[$key]) : ''; }
    private static function valid_date($value) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value,5,2), (int) substr($value,8,2), (int) substr($value,0,4)) ? $value : ''; }
}

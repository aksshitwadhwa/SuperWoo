<?php
defined('ABSPATH') || exit;

/** Prepared-query persistence for imported recovery evidence and audit batches. */
class SuperWoo_Order_Recovery_Repository {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    private function table($name) { return SuperWoo_Order_Recovery_Schema::table($name); }
    private function now() { return gmdate('Y-m-d H:i:s'); }

    public function create_batch($filename, $file_hash, $actor_id) {
        $existing = $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM ' . $this->table('batches') . ' WHERE batch_key = %s', $file_hash));
        if ($existing) { return ['id' => (int) $existing, 'existing' => true]; }
        $inserted = $this->wpdb->insert($this->table('batches'), [
            'batch_key' => $file_hash, 'filename' => sanitize_file_name($filename), 'file_sha256' => $file_hash,
            'actor_id' => absint($actor_id), 'created_at' => $this->now(),
        ]);
        if (false === $inserted || !$this->wpdb->insert_id) {
            $existing = $this->wpdb->get_var($this->wpdb->prepare('SELECT id FROM ' . $this->table('batches') . ' WHERE batch_key = %s', $file_hash));
            if ($existing) { return ['id' => (int) $existing, 'existing' => true]; }
            return new WP_Error('recovery_batch_insert', __('Could not create the import batch.', 'superwoo'));
        }
        return ['id' => (int) $this->wpdb->insert_id, 'existing' => false];
    }

    public function add_transaction($batch_id, $row_number, $row, $duplicate_in_file = false) {
        $now = $this->now();
        $key = $row['payment_id'] !== '' ? hash('sha256', 'razorpay-payment:' . strtolower($row['payment_id'])) : hash('sha256', 'razorpay-row:' . $row['source_row_hash']);
        $table = $this->table('transactions');
        $existing = $this->wpdb->get_row($this->wpdb->prepare("SELECT id,duplicate_count FROM {$table} WHERE idempotency_key=%s", $key), ARRAY_A);
        $data = [
            'idempotency_key' => $key, 'payment_id' => $row['payment_id'], 'razorpay_order_id' => $row['razorpay_order_id'],
            'wc_order_id' => $row['wc_order_id'], 'wc_order_number' => $row['wc_order_number'], 'receipt' => $row['receipt'],
            'description' => $row['description'], 'notes_json' => wp_json_encode($row['notes']),
            'reported_status' => $row['reported_status'], 'verification_state' => 'unverified',
            'classification' => $row['classification'], 'classification_reason' => $row['classification_reason'],
            'currency' => $row['currency'], 'gross_minor' => $row['gross_minor'], 'wc_total_minor' => $row['wc_total_minor'] ?? null, 'fee_minor' => $row['fee_minor'],
            'fee_gst_minor' => $row['fee_gst_minor'], 'refund_minor' => $row['refund_minor'],
            'refund_reference' => $row['refund_reference'], 'refund_status' => $row['refund_status'], 'refund_at_utc' => $row['refund_at_utc'],
            'reconciled_difference_minor' => $row['reconciled_difference_minor'] ?? null,
            'paid_at_utc' => $row['paid_at_utc'], 'customer_email' => $row['customer_email'], 'customer_phone' => $row['customer_phone'],
            'missing_fields' => wp_json_encode($row['missing_fields']), 'data_warnings' => wp_json_encode($row['data_warnings'] ?? []), 'source_row_hash' => $row['source_row_hash'],
            'source_values_json' => wp_json_encode($row['source_values'] ?? []),
            'last_batch_id' => absint($batch_id), 'updated_at' => $now,
        ];
        if ($existing) {
            $old_source_hash = (string) $this->wpdb->get_var($this->wpdb->prepare("SELECT source_row_hash FROM {$table} WHERE id=%d", (int) $existing['id']));
            if (!hash_equals($old_source_hash, (string) $row['source_row_hash'])) {
                // An updated source row invalidates all provider-derived facts.
                $data = array_merge($data, [
                    'verification_state' => 'unverified', 'verified_at_utc' => null, 'verification_error' => '',
                    'provider_status' => '', 'provider_currency' => '', 'provider_amount_minor' => null,
                    'provider_amount_refunded_minor' => null, 'provider_refund_status' => '',
                    'provider_refunds_complete' => 0, 'provider_captured' => null, 'provider_order_id' => '',
                    'provider_match_result' => '', 'provider_created_at_utc' => null, 'provider_refunds_json' => null,
                ]);
            }
            $new_count = (int) $existing['duplicate_count'] + ($duplicate_in_file ? 1 : 0);
            $data['duplicate_count'] = $new_count;
            if ($duplicate_in_file) {
                $data['classification'] = 'duplicate_ambiguous';
                $data['classification_reason'] = 'Repeated provider payment identifier; verify before treating as a distinct transaction.';
            }
            $this->wpdb->update($table, $data, ['id' => (int) $existing['id']]);
            $transaction_id = (int) $existing['id'];
            $was_new = false;
        } else {
            $data['first_batch_id'] = absint($batch_id);
            $data['duplicate_count'] = $duplicate_in_file ? 1 : 0;
            $data['created_at'] = $now;
            if ($duplicate_in_file) {
                $data['classification'] = 'duplicate_ambiguous';
                $data['classification_reason'] = 'Repeated provider payment identifier within this file.';
            }
            $inserted = $this->wpdb->insert($table, $data);
            $transaction_id = false === $inserted ? 0 : (int) $this->wpdb->insert_id;
            $was_new = (bool) $transaction_id;
            if (!$transaction_id) {
                // A concurrent importer may have won the unique idempotency key race.
                $raced = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$table} WHERE idempotency_key=%s", $key));
                if ($raced) { $transaction_id = (int) $raced; $this->wpdb->update($table, $data, ['id' => $transaction_id]); }
            }
        }
        if (!$transaction_id) { return new WP_Error('recovery_transaction_write', __('Could not save an imported transaction.', 'superwoo')); }
        $this->wpdb->query($this->wpdb->prepare('INSERT INTO ' . $this->table('batch_transactions') . ' (batch_id,transaction_id,source_row_number,source_row_hash,created_at) VALUES (%d,%d,%d,%s,%s) ON DUPLICATE KEY UPDATE transaction_id=VALUES(transaction_id),source_row_hash=VALUES(source_row_hash)', absint($batch_id), $transaction_id, absint($row_number), $row['source_row_hash'], $now));
        return ['id' => $transaction_id, 'new' => $was_new, 'duplicate' => !$was_new || $duplicate_in_file];
    }

    public function finish_batch($batch_id, $counts) {
        $this->wpdb->update($this->table('batches'), [
            'row_count' => absint($counts['rows'] ?? 0), 'inserted_count' => absint($counts['inserted'] ?? 0),
            'updated_count' => absint($counts['updated'] ?? 0), 'duplicate_count' => absint($counts['duplicates'] ?? 0),
            'error_count' => absint($counts['errors'] ?? 0),
        ], ['id' => absint($batch_id)]);
    }

    public function batch($id) { return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . $this->table('batches') . ' WHERE id=%d', absint($id)), ARRAY_A); }
    public function batches($limit = 20) { return $this->wpdb->get_results($this->wpdb->prepare('SELECT * FROM ' . $this->table('batches') . ' ORDER BY created_at DESC LIMIT %d', min(100, max(1, absint($limit)))), ARRAY_A); }
    public function summary() { return $this->wpdb->get_results('SELECT classification,COUNT(*) AS total FROM ' . $this->table('transactions') . ' GROUP BY classification', ARRAY_A); }

    public function transactions($args) {
        $where = ['1=1']; $values = [];
        if (!empty($args['missing_wc_only'])) {
            // A provider payment without an explicit WooCommerce ID cannot establish a missing store order.
            $where[] = 'classification=%s'; $values[] = 'missing_order_candidate';
            $where[] = 'wc_order_id > 0';
            // Keep workshop-related text out even when an older import classified it before that rule existed.
            foreach (['workshop', 'masterclass', 'webinar'] as $word) {
                $where[] = "LOWER(CONCAT(COALESCE(description,''),' ',COALESCE(notes_json,''),' ',receipt)) NOT LIKE %s";
                $values[] = '%' . $this->wpdb->esc_like($word) . '%';
            }
        }
        foreach (['classification', 'reported_status', 'verification_state', 'currency'] as $field) {
            if (!empty($args[$field])) { $where[] = "{$field}=%s"; $values[] = sanitize_text_field($args[$field]); }
        }
        if (!empty($args['date_from'])) { $where[] = 'paid_at_utc >= %s'; $values[] = $args['date_from'] . ' 00:00:00'; }
        if (!empty($args['date_to'])) { $where[] = 'paid_at_utc <= %s'; $values[] = $args['date_to'] . ' 23:59:59'; }
        if (!empty($args['batch_id'])) { $where[] = '(first_batch_id=%d OR last_batch_id=%d)'; $values[] = absint($args['batch_id']); $values[] = absint($args['batch_id']); }
        $currency = strtoupper(substr(sanitize_text_field($args['currency'] ?? ''), 0, 3));
        $precision = in_array($currency, ['JPY','KRW','VND','CLP','XAF','XOF'], true) ? 0 : (in_array($currency, ['BHD','KWD','OMR','JOD','TND'], true) ? 3 : (in_array($currency, ['CLF','UYW'], true) ? 4 : 2));
        foreach (['amount_min' => '>=', 'amount_max' => '<='] as $field => $operator) {
            if (isset($args[$field]) && $args[$field] !== '' && is_numeric($args[$field])) { $where[] = "gross_minor {$operator} %d"; $values[] = (int) round((float) $args[$field] * pow(10, $precision)); }
        }
        if (($args['amount_min'] ?? '') !== '' || ($args['amount_max'] ?? '') !== '') { if (!$currency) { $where[] = '1=0'; } }
        $base = ' FROM ' . $this->table('transactions') . ' WHERE ' . implode(' AND ', $where);
        $count_sql = 'SELECT COUNT(*)' . $base;
        $total = (int) $this->wpdb->get_var($values ? $this->wpdb->prepare($count_sql, $values) : $count_sql);
        $per_page = min(100, max(1, absint($args['per_page'] ?? 20)));
        $page = max(1, absint($args['page'] ?? 1));
        $values[] = $per_page; $values[] = ($page - 1) * $per_page;
        $sql = 'SELECT *' . $base . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $values), ARRAY_A);
        return ['items' => $rows, 'total' => $total, 'pages' => (int) ceil($total / $per_page)];
    }

    public function transaction($id) { return $this->wpdb->get_row($this->wpdb->prepare('SELECT * FROM ' . $this->table('transactions') . ' WHERE id=%d', absint($id)), ARRAY_A); }
    public function save_provider_verification($id, $result) {
        $data = [
            'verification_state' => $result['state'], 'verified_at_utc' => $this->now(),
            'verification_error' => sanitize_key($result['error'] ?? ''),
            'provider_status' => sanitize_key($result['status'] ?? ''),
            'provider_currency' => strtoupper(substr(sanitize_text_field($result['currency'] ?? ''), 0, 3)),
            'provider_amount_minor' => $result['amount_minor'] ?? null,
            'provider_amount_refunded_minor' => $result['amount_refunded_minor'] ?? null,
            'provider_refund_status' => sanitize_key($result['refund_status'] ?? ''),
            'provider_refunds_complete' => !empty($result['refund_evidence_complete']) ? 1 : 0,
            'provider_captured' => array_key_exists('captured', $result) ? (int) (bool) $result['captured'] : null,
            'provider_order_id' => sanitize_text_field($result['order_id'] ?? ''),
            'provider_match_result' => substr(preg_replace('/[^a-z0-9_,]/i', '', (string) ($result['match_result'] ?? '')), 0, 190),
            'provider_created_at_utc' => $result['created_at_utc'] ?? null,
            'provider_refunds_json' => wp_json_encode($result['refunds'] ?? []),
            'updated_at' => $this->now(),
        ];
        return false !== $this->wpdb->update($this->table('transactions'), $data, ['id' => absint($id)]);
    }
    public function transaction_batches($id) {
        return $this->wpdb->get_results($this->wpdb->prepare('SELECT b.id,b.filename,b.file_sha256,bt.source_row_number,bt.source_row_hash,bt.created_at FROM ' . $this->table('batch_transactions') . ' bt INNER JOIN ' . $this->table('batches') . ' b ON b.id=bt.batch_id WHERE bt.transaction_id=%d ORDER BY bt.created_at DESC', absint($id)), ARRAY_A);
    }
}

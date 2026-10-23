<?php
defined('ABSPATH') || exit;

/** Safe, header-tolerant parser for user-supplied Razorpay CSV exports. */
class SuperWoo_Order_Recovery_CSV {
    const MAX_BYTES = 10485760;
    const MAX_ROWS = 50000;

    private static function aliases() {
        return [
            'payment_id' => ['payment id','payment_id','paymentid','razorpay payment id','id'],
            'razorpay_order_id' => ['order id','order_id','razorpay order id','razorpay_order_id'],
            'wc_order_id' => ['woocommerce order id','woo order id','wc order id','order reference','woo order reference'],
            'wc_order_number' => ['woocommerce order number','woo order number','order number','order no'],
            'receipt' => ['receipt','receipt id','receipt number'],
            'description' => ['description','payment description','product description'],
            'notes' => ['notes','note','metadata','meta data','payment notes'],
            'reported_status' => ['status','payment status','payment_status'],
            'gross' => ['amount','gross amount','payment amount','captured amount','amount in rupees','gross'],
            'gross_minor' => ['amount in paise','amount (paise)','amount minor','amount in minor units'],
            'fee' => ['fee','fees','gateway fee','razorpay fee','total fee'],
            'fee_minor' => ['fee in paise','fee (paise)','fee minor'],
            'fee_gst' => ['fee gst','gst on fee','tax on fee','fee tax'],
            'fee_gst_minor' => ['fee gst in paise','fee gst (paise)','fee gst minor'],
            'refund' => ['refund','refund amount','amount refunded','total refund'],
            'refund_minor' => ['refund in paise','refund (paise)','refund minor'],
            'refund_reference' => ['refund id','refund ids','refund reference','refund reference id'],
            'refund_status' => ['refund status'],
            'refund_at' => ['refund date','refund timestamp','refunded at'],
            'paid_at' => ['created at','created_at','payment date','payment time','paid at','captured at','date'],
            'currency' => ['currency','payment currency'],
            'customer_email' => ['email','customer email','contact email'],
            'customer_phone' => ['contact','phone','mobile','customer phone','contact number'],
        ];
    }

    public static function normalize_header($header) {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = preg_replace('/[\s\-_]+/', ' ', $header);
        $header = preg_replace('/[^a-z0-9 ]/', ' ', $header);
        return trim(preg_replace('/\s+/', ' ', $header));
    }

    public static function parse($path) {
        if (!is_file($path) || !is_readable($path)) { return new WP_Error('recovery_csv_unreadable', __('The uploaded CSV cannot be read.', 'superwoo')); }
        $size = filesize($path);
        if (false === $size || $size < 1 || $size > self::MAX_BYTES) { return new WP_Error('recovery_csv_size', __('CSV must be between 1 byte and 10 MB.', 'superwoo')); }
        $bytes = file_get_contents($path);
        if (false === $bytes || false === preg_match('//u', $bytes)) { return new WP_Error('recovery_csv_encoding', __('CSV must use valid UTF-8 encoding.', 'superwoo')); }
        if (strpos($bytes, "\0") !== false) { return new WP_Error('recovery_csv_encoding', __('CSV contains unsupported binary data.', 'superwoo')); }
        $bytes = preg_replace('/^\xEF\xBB\xBF/', '', $bytes);
        $stream = fopen('php://temp', 'w+b');
        if (!$stream || false === fwrite($stream, $bytes)) { return new WP_Error('recovery_csv_read', __('Could not parse the uploaded CSV.', 'superwoo')); }
        rewind($stream);
        $first_line = fgets($stream);
        rewind($stream);
        $delimiter = ',';
        foreach (["\t", ';', ','] as $candidate) {
            if (count(str_getcsv((string) $first_line, $candidate, '"', '')) > count(str_getcsv((string) $first_line, $delimiter, '"', ''))) { $delimiter = $candidate; }
        }
        $header = fgetcsv($stream, 0, $delimiter, '"', '');
        if (!is_array($header) || count($header) < 2 || count($header) > 200) { fclose($stream); return new WP_Error('recovery_csv_header', __('CSV header row is missing, malformed, or too wide.', 'superwoo')); }
        $aliases = self::aliases(); $columns = [];
        foreach ($header as $index => $label) {
            $normalized = self::normalize_header($label);
            if (preg_match('/^(?:notes|metadata) (.+)$/', $normalized, $note_match)) {
                $columns['notes_fields'][sanitize_key(str_replace(' ', '_', $note_match[1]))] = (int) $index;
                continue;
            }
            foreach ($aliases as $field => $names) {
                if (in_array($normalized, array_map([self::class, 'normalize_header'], $names), true)) {
                    if (!isset($columns[$field])) { $columns[$field] = (int) $index; }
                    break;
                }
            }
        }
        if (!isset($columns['payment_id']) && !isset($columns['razorpay_order_id']) && !isset($columns['receipt']) && !isset($columns['wc_order_id'])) {
            fclose($stream); return new WP_Error('recovery_csv_header', __('No recognized payment or order reference column was found.', 'superwoo'));
        }
        if (!isset($columns['reported_status']) && !isset($columns['gross']) && !isset($columns['gross_minor'])) {
            fclose($stream); return new WP_Error('recovery_csv_header', __('CSV needs a recognized payment status or amount column.', 'superwoo'));
        }
        $rows = []; $line = 1;
        while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;
            if (count($rows) >= self::MAX_ROWS) { fclose($stream); return new WP_Error('recovery_csv_rows', __('CSV exceeds the 50,000 row limit.', 'superwoo')); }
            if (!is_array($values) || (count($values) === 1 && trim((string) $values[0]) === '')) { continue; }
            if (count($values) > count($header)) { fclose($stream); return new WP_Error('recovery_csv_malformed', __('A CSV row contains more columns than the header.', 'superwoo')); }
            if (count($values) < count($header)) { $values = array_pad($values, count($header), ''); }
            $rows[] = self::normalize_row($values, $columns, $line);
        }
        fclose($stream);
        if (!$rows) { return new WP_Error('recovery_csv_empty', __('CSV contains no transaction rows.', 'superwoo')); }
        return ['columns' => array_keys($columns), 'rows' => $rows];
    }

    public static function import_rows($rows, $repository, $batch_id, $rules = []) {
        $counts = ['rows' => count($rows), 'inserted' => 0, 'updated' => 0, 'duplicates' => 0, 'errors' => 0];
        $seen = [];
        foreach ($rows as $row) {
            $duplicate = false;
            if ($row['payment_id'] !== '') {
                $key = strtolower($row['payment_id']);
                $duplicate = isset($seen[$key]);
                $seen[$key] = true;
            }
            $order_match = SuperWoo_Order_Recovery_Classifier::find_existing_order($row['payment_id'], $row['wc_order_id'], $row['wc_order_number']);
            $row = SuperWoo_Order_Recovery_Classifier::add_order_reference($row, $order_match);
            $classification = SuperWoo_Order_Recovery_Classifier::classify($row, $order_match, $rules, $duplicate);
            $row['classification'] = $classification['classification'];
            $row['classification_reason'] = $classification['reason'];
            $row['wc_total_minor'] = null;
            $row['reconciled_difference_minor'] = null;
            if (!empty($order_match['order'])) { $row = SuperWoo_Order_Recovery_Classifier::attach_order_totals($row, $order_match['order']); }
            $result = $repository->add_transaction((int) $batch_id, $row['source_row_number'], $row, $duplicate);
            if (is_wp_error($result)) { $counts['errors']++; continue; }
            if ($result['new']) { $counts['inserted']++; } else { $counts['updated']++; }
            if ($result['duplicate']) { $counts['duplicates']++; }
        }
        return $counts;
    }

    private static function cell($values, $columns, $key) {
        return isset($columns[$key]) && isset($values[$columns[$key]]) && is_scalar($values[$columns[$key]]) ? trim((string) $values[$columns[$key]]) : '';
    }

    private static function parse_money($value, $currency, $already_minor = false) {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^[A-Z]{3}$/', strtoupper((string) $currency))) { return null; }
        $value = str_replace([',', '₹', 'Rs.', 'Rs', '$', '€', '£', ' '], '', $value);
        if (!preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) { return null; }
        if ($already_minor) { return (int) ($matches[1] . $matches[2]); }
        $precision = self::currency_precision($currency);
        $fraction = $matches[3] ?? '';
        $kept = substr(str_pad($fraction, $precision, '0'), 0, $precision);
        $minor = ((int) $matches[2] * (int) pow(10, $precision)) + (int) ($kept === '' ? 0 : $kept);
        if (strlen($fraction) > $precision && (int) $fraction[$precision] >= 5) { $minor++; }
        return (int) ($matches[1] . $minor);
    }

    private static function currency_precision($currency) {
        if (in_array(strtoupper($currency), ['JPY','KRW','VND','CLP','XAF','XOF'], true)) { return 0; }
        if (in_array(strtoupper($currency), ['BHD','KWD','OMR','JOD','TND'], true)) { return 3; }
        if (in_array(strtoupper($currency), ['CLF','UYW'], true)) { return 4; }
        return 2;
    }

    private static function notes($value) {
        if ($value === '') { return []; }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) { return self::sanitize_notes($decoded); }
        $pairs = [];
        foreach (preg_split('/[;\n|]+/', $value) as $piece) {
            if (preg_match('/^\s*([^:=]+)\s*[:=]\s*(.*?)\s*$/', $piece, $matches)) {
                $pairs[sanitize_key($matches[1])] = $matches[2];
            }
        }
        return self::sanitize_notes($pairs);
    }

    private static function sanitize_notes($notes) {
        $safe = []; $count = 0;
        foreach ($notes as $key => $value) {
            if ($count++ >= 50) { break; }
            $key = substr(sanitize_key((string) $key), 0, 80);
            if (preg_match('/email|phone|mobile|address|token|secret|password|credential|card|cvv/', $key)) { $safe[$key] = '[redacted]'; continue; }
            if (is_scalar($value)) {
                $value = sanitize_text_field((string) $value);
                $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
                $value = preg_replace('/\b\+?\d[\d ()-]{7,}\d\b/', '[redacted-number]', $value);
                $safe[$key] = substr($value, 0, 500);
            } else { $safe[$key] = '[non-scalar]'; }
        }
        return $safe;
    }

    private static function normalize_row($values, $columns, $line) {
        $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', self::cell($values, $columns, 'currency')), 0, 3));
        $notes = self::notes(self::cell($values, $columns, 'notes'));
        foreach ((array) ($columns['notes_fields'] ?? []) as $key => $index) {
            if (isset($values[$index]) && is_scalar($values[$index]) && trim((string) $values[$index]) !== '') { $notes[$key] = (string) $values[$index]; }
        }
        $notes = self::sanitize_notes($notes);
        $payment_id = substr(sanitize_text_field(self::cell($values, $columns, 'payment_id')), 0, 190);
        $order_id_raw = self::cell($values, $columns, 'wc_order_id');
        if ($order_id_raw === '' && isset($notes['woocommerce_order_id'])) { $order_id_raw = (string) $notes['woocommerce_order_id']; }
        if ($order_id_raw === '' && isset($notes['order_id'])) { $order_id_raw = (string) $notes['order_id']; }
        $order_id = preg_match('/^\d+$/', $order_id_raw) ? absint($order_id_raw) : 0;
        $order_number = substr(sanitize_text_field(self::cell($values, $columns, 'wc_order_number')), 0, 190);
        $gross = self::parse_money(self::cell($values, $columns, 'gross'), $currency);
        if (null === $gross) { $gross = self::parse_money(self::cell($values, $columns, 'gross_minor'), $currency, true); }
        $fee = self::parse_money(self::cell($values, $columns, 'fee'), $currency);
        if (null === $fee) { $fee = self::parse_money(self::cell($values, $columns, 'fee_minor'), $currency, true); }
        $fee_gst = self::parse_money(self::cell($values, $columns, 'fee_gst'), $currency);
        if (null === $fee_gst) { $fee_gst = self::parse_money(self::cell($values, $columns, 'fee_gst_minor'), $currency, true); }
        $refund = self::parse_money(self::cell($values, $columns, 'refund'), $currency);
        if (null === $refund) { $refund = self::parse_money(self::cell($values, $columns, 'refund_minor'), $currency, true); }
        $paid_at = self::date_utc(self::cell($values, $columns, 'paid_at'));
        $refund_at = self::date_utc(self::cell($values, $columns, 'refund_at'));
        $status = strtolower(substr(sanitize_text_field(self::cell($values, $columns, 'reported_status')), 0, 64));
        $row = [
            'payment_id' => $payment_id, 'razorpay_order_id' => substr(sanitize_text_field(self::cell($values, $columns, 'razorpay_order_id')), 0, 190),
            'wc_order_id' => $order_id, 'wc_order_number' => $order_number, 'receipt' => substr(sanitize_text_field(self::cell($values, $columns, 'receipt')), 0, 190),
            'description' => substr(self::redact_free_text(self::cell($values, $columns, 'description')), 0, 2000), 'notes' => $notes,
            'reported_status' => $status, 'currency' => preg_match('/^[A-Z]{3}$/', $currency) ? $currency : '',
            'gross_minor' => $gross, 'fee_minor' => $fee, 'fee_gst_minor' => $fee_gst, 'refund_minor' => $refund,
            'refund_reference' => substr(sanitize_text_field(self::cell($values, $columns, 'refund_reference')), 0, 500),
            'refund_status' => substr(strtolower(sanitize_text_field(self::cell($values, $columns, 'refund_status'))), 0, 64),
            'refund_at_utc' => $refund_at,
            'paid_at_utc' => $paid_at, 'customer_email' => sanitize_email(self::cell($values, $columns, 'customer_email')),
            'customer_phone' => substr(preg_replace('/[^0-9+]/', '', self::cell($values, $columns, 'customer_phone')), 0, 40),
            'missing_fields' => [], 'data_warnings' => [], 'source_row_number' => (int) $line,
        ];
        $row['source_values'] = [];
        foreach (['payment_id','razorpay_order_id','wc_order_id','wc_order_number','receipt','reported_status','gross','gross_minor','fee','fee_minor','fee_gst','fee_gst_minor','refund','refund_minor','refund_reference','refund_status','refund_at','paid_at','currency'] as $source_field) {
            $raw = self::cell($values, $columns, $source_field);
            if ($raw !== '') { $row['source_values'][$source_field] = substr(sanitize_text_field($raw), 0, 500); }
        }
        foreach (['payment_id','razorpay_order_id','wc_order_id','wc_order_number','receipt','description','notes','reported_status','currency','gross_minor','fee_minor','fee_gst_minor','refund_minor','refund_reference','refund_status','refund_at_utc','paid_at_utc','customer_email','customer_phone'] as $field) {
            if ($row[$field] === '' || $row[$field] === null) { $row['missing_fields'][] = $field; }
        }
        $raw_date = self::cell($values, $columns, 'paid_at');
        if ($raw_date !== '' && !preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $raw_date)) { $row['data_warnings'][] = 'Timestamp had no timezone; interpreted as UTC.'; }
        if ($currency === '' && array_filter(['gross','gross_minor','fee','fee_minor','fee_gst','fee_gst_minor','refund','refund_minor'], static function ($field) use ($values, $columns) { return self::cell($values, $columns, $field) !== ''; })) {
            $row['data_warnings'][] = 'Currency is missing; monetary minor units cannot be interpreted.';
        }
        $fingerprint_row = $row; unset($fingerprint_row['source_row_number'], $fingerprint_row['customer_email'], $fingerprint_row['customer_phone']);
        $row['source_row_hash'] = hash('sha256', wp_json_encode($fingerprint_row));
        return $row;
    }

    private static function date_utc($value) {
        if ($value === '') { return null; }
        try {
            $timezone = preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value) ? null : new DateTimeZone('UTC');
            $date = new DateTimeImmutable($value, $timezone);
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $error) { return null; }
    }

    private static function redact_free_text($value) {
        $value = sanitize_text_field($value);
        $value = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[redacted-email]', $value);
        return preg_replace('/\b\+?\d[\d ()-]{7,}\d\b/', '[redacted-number]', $value);
    }
}

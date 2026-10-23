<?php
defined('ABSPATH') || exit;

/** Optional, bounded, read-only Razorpay payment verification. Credentials are supplied by wp-config.php constants. */
class SuperWoo_Order_Recovery_Provider {
    private $key_id;
    private $secret;

    public function __construct($key_id = null, $secret = null) {
        $this->key_id = null === $key_id && defined('SUPERWOO_RAZORPAY_KEY_ID') ? (string) SUPERWOO_RAZORPAY_KEY_ID : (string) $key_id;
        $this->secret = null === $secret && defined('SUPERWOO_RAZORPAY_KEY_SECRET') ? (string) SUPERWOO_RAZORPAY_KEY_SECRET : (string) $secret;
    }

    public function verify($payment_id) {
        if ('' === $this->key_id || '' === $this->secret) { return new WP_Error('credentials_unavailable', 'Provider credentials are not configured.'); }
        if (!is_string($payment_id) || !preg_match('/^pay_[A-Za-z0-9]{5,64}$/D', $payment_id)) { return new WP_Error('invalid_payment_id', 'A valid provider payment ID is required.'); }
        $payment = $this->get('/payments/' . rawurlencode($payment_id));
        if (is_wp_error($payment)) { return $payment; }
        if (($payment['id'] ?? '') !== $payment_id) { return new WP_Error('provider_id_mismatch', 'The provider returned a different payment ID.'); }

        $refunds = [];
        $refund_evidence_complete = false;
        $refunds_consistent = true;
        $processed_refund_total = 0;
        for ($skip = 0; $skip < 300; $skip += 100) {
            $page = $this->get('/payments/' . rawurlencode($payment_id) . '/refunds?count=100&skip=' . $skip);
            if (is_wp_error($page)) { return $page; }
            if (!isset($page['items']) || !is_array($page['items'])) { return new WP_Error('provider_invalid_refunds', 'Provider returned an invalid refunds response.'); }
            foreach ($page['items'] as $refund) {
                if (!is_array($refund) || ($refund['payment_id'] ?? '') !== $payment_id || empty($refund['id']) || !isset($refund['amount'], $refund['currency'], $refund['status']) || !is_numeric($refund['amount'])) { return new WP_Error('provider_invalid_refund', 'Provider returned an invalid refund record.'); }
                $refund_status = sanitize_key($refund['status']);
                if (!in_array($refund_status, ['processed', 'failed'], true)) { $refunds_consistent = false; }
                if ('processed' === $refund_status) { $processed_refund_total += (int) $refund['amount']; }
                $refunds[] = ['id' => sanitize_text_field($refund['id']), 'amount_minor' => (int) $refund['amount'], 'currency' => strtoupper(substr(sanitize_text_field($refund['currency']), 0, 3)), 'status' => $refund_status, 'created_at' => absint($refund['created_at'] ?? 0)];
            }
            if (count($page['items']) < 100) { $refund_evidence_complete = true; break; }
        }
        if (!isset($payment['amount'], $payment['currency'], $payment['status'], $payment['amount_refunded']) || !is_numeric($payment['amount']) || !is_numeric($payment['amount_refunded'])) { return new WP_Error('provider_invalid_payment', 'Provider returned incomplete payment details.'); }
        $currency = strtoupper(substr(sanitize_text_field($payment['currency']), 0, 3));
        foreach ($refunds as $refund) { if ($refund['currency'] !== $currency) { $refunds_consistent = false; } }
        $refund_evidence_complete = $refund_evidence_complete && $refunds_consistent && $processed_refund_total === (int) $payment['amount_refunded'];
        $created = absint($payment['created_at'] ?? 0);
        return [
            'state' => 'verified', 'status' => sanitize_key($payment['status']),
            'amount_minor' => (int) $payment['amount'], 'currency' => $currency,
            'captured' => array_key_exists('captured', $payment) ? (bool) $payment['captured'] : null,
            'amount_refunded_minor' => isset($payment['amount_refunded']) && is_numeric($payment['amount_refunded']) ? (int) $payment['amount_refunded'] : null,
            'refund_evidence_complete' => $refund_evidence_complete,
            'refund_status' => sanitize_key($payment['refund_status'] ?? ''), 'order_id' => sanitize_text_field($payment['order_id'] ?? ''),
            'created_at_utc' => $created ? gmdate('Y-m-d H:i:s', $created) : null, 'refunds' => $refunds,
        ];
    }

    /** Correlate provider facts with imported evidence and WooCommerce CRUD lookup only. */
    public static function correlate($record, $result, $local_match) {
        $conflicts = [];
        if ('' !== (string) ($record['currency'] ?? '') && strtoupper((string) $record['currency']) !== $result['currency']) { $conflicts[] = 'currency_mismatch'; }
        if (null !== ($record['gross_minor'] ?? null) && (int) $record['gross_minor'] !== (int) $result['amount_minor']) { $conflicts[] = 'amount_mismatch'; }
        if ('' !== (string) ($record['razorpay_order_id'] ?? '') && '' !== $result['order_id'] && (string) $record['razorpay_order_id'] !== $result['order_id']) { $conflicts[] = 'provider_order_id_mismatch'; }
        if (!empty($local_match['ambiguous'])) { $conflicts[] = 'multiple_woocommerce_orders'; }
        if (!empty($local_match['order'])) {
            $local_order_id = absint($local_match['order']->get_id());
            if (!empty($record['wc_order_id']) && absint($record['wc_order_id']) !== $local_order_id) { $conflicts[] = 'woocommerce_order_reference_mismatch'; }
            $result['match_result'] = 'payment_id_exact_wc_order_' . $local_order_id;
        } else {
            $result['match_result'] = $result['order_id'] ? 'payment_id_exact_provider_order_only' : 'payment_id_exact_no_order_reference';
        }
        $result['state'] = $conflicts ? 'verified_conflict' : 'verified';
        if ($conflicts) { $result['match_result'] .= '_conflict_' . implode('_', $conflicts); }
        $result['error'] = $conflicts ? 'evidence_mismatch' : '';
        return $result;
    }

    private function get($path) {
        $url = 'https://api.razorpay.com/v1' . $path;
        $response = wp_safe_remote_get($url, [
            'timeout' => 10, 'redirection' => 0, 'sslverify' => true,
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->key_id . ':' . $this->secret), 'Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) { return new WP_Error('provider_transport_error', 'The provider request did not complete.'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code < 200 || $code >= 300) {
            $error = (429 === $code) ? 'provider_rate_limited' : (in_array($code, [401, 403], true) ? 'provider_auth_error' : (404 === $code ? 'provider_not_found' : 'provider_http_error'));
            return new WP_Error($error, 'The provider did not return a successful response.', ['http_status' => $code]);
        }
        if (!is_string($body) || strlen($body) > 1048576) { return new WP_Error('provider_response_too_large', 'Provider response was invalid or too large.'); }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !empty($decoded['error'])) { return new WP_Error('provider_invalid_response', 'Provider response could not be validated.'); }
        return $decoded;
    }
}

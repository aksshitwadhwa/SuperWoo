<?php
defined('ABSPATH') || exit;

/** Receive-only listener for Razorpay Magic Checkout abandoned-cart notices. */
class SuperWoo_Razorpay_Abandoned_Webhook {
    const TOKEN_OPTION = 'superwoo_razorpay_abandoned_webhook_token';
    const SHORT_TOKEN_OPTION = 'superwoo_razorpay_abandoned_webhook_short_token';
    const LOG_FILE = 'razorpay-abandoned-cart.log';
    const MAX_BODY_BYTES = 262144;

    public function hooks() {
        add_action('rest_api_init', [$this, 'register_route']);
        add_action('admin_post_superwoo_rotate_razorpay_webhook_token', [$this, 'rotate_token']);
    }

    public function register_route() {
        $route = [
            // Razorpay's dashboard may probe a callback URL before saving it.
            // Read-only probes must succeed without creating a receipt.
            'methods' => ['GET', 'HEAD', 'POST'],
            'callback' => [$this, 'receive'],
            'args' => [
                'token' => ['required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ];
        register_rest_route('superwoo/v1', '/razorpay-abandoned-cart/(?P<token>[A-Za-z0-9]{48,128})', array_merge($route, [
            'permission_callback' => [$this, 'authorize'],
        ]));
        // Keep the original route alive while offering a compact URL for dashboards with restrictive URL fields.
        register_rest_route('sw/v1', '/r/(?P<token>[A-Za-z0-9]{24})', array_merge($route, [
            'permission_callback' => [$this, 'authorize_short'],
        ]));
    }

    /** The random URL token is a bearer credential because Razorpay documents no signature for this webhook. */
    public function authorize($request) {
        return $this->authorize_token($request, self::get_token());
    }

    public function authorize_short($request) {
        return $this->authorize_token($request, self::get_short_token());
    }

    private function authorize_token($request, $expected) {
        if (is_object($request) && method_exists($request, 'get_url_params')) {
            $url_params = $request->get_url_params();
            $provided = is_array($url_params) && isset($url_params['token']) ? (string) $url_params['token'] : '';
        } else {
            $provided = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('token') : '';
        }
        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            return new WP_Error('rest_not_found', __('Webhook endpoint not found.', 'superwoo'), ['status' => 404]);
        }

        if (!self::uses_https()) {
            return new WP_Error('superwoo_webhook_requires_https', __('The abandoned-cart callback requires an HTTPS site URL.', 'superwoo'), ['status' => 403]);
        }

        return true;
    }

    public function receive($request) {
        $method = is_object($request) && method_exists($request, 'get_method')
            ? strtoupper((string) $request->get_method())
            : 'POST';
        $body = (string) $request->get_body();

        if (!superwoo_rate_limit('razorpay_abandoned_cart', 60, MINUTE_IN_SECONDS)) {
            return new WP_Error('superwoo_webhook_rate_limited', __('Please retry later.', 'superwoo'), ['status' => 429]);
        }

        if ('GET' === $method || 'HEAD' === $method || '' === $body) {
            return new WP_REST_Response(['ready' => true], 200);
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            return new WP_Error('superwoo_webhook_invalid_size', __('Invalid webhook payload size.', 'superwoo'), ['status' => 413]);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || !$payload || JSON_ERROR_NONE !== json_last_error()) {
            return new WP_Error('superwoo_webhook_invalid_json', __('Invalid JSON payload.', 'superwoo'), ['status' => 400]);
        }

        $receipt_id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : bin2hex(random_bytes(16));
        $summary = self::build_redacted_summary($payload, $body, $receipt_id);
        if (!self::append_receipt($summary)) {
            return new WP_Error('superwoo_webhook_log_unavailable', __('Webhook receipt could not be recorded.', 'superwoo'), ['status' => 503]);
        }

        // Do not return or retain any part of the customer/cart payload.
        return new WP_REST_Response(['received' => true, 'receipt_id' => $receipt_id], 202);
    }

    public static function get_token() {
        $token = get_option(self::TOKEN_OPTION, '');
        if (is_string($token) && preg_match('/^[A-Za-z0-9]{48,128}$/', $token)) {
            return $token;
        }

        $token = wp_generate_password(64, false, false);
        update_option(self::TOKEN_OPTION, $token, false);
        return $token;
    }

    public static function get_short_token() {
        $token = get_option(self::SHORT_TOKEN_OPTION, '');
        if (is_string($token) && preg_match('/^[A-Za-z0-9]{24}$/', $token)) {
            return $token;
        }

        $token = wp_generate_password(24, false, false);
        update_option(self::SHORT_TOKEN_OPTION, $token, false);
        return $token;
    }

    public static function callback_url() {
        return rest_url('sw/v1/r/' . rawurlencode(self::get_short_token()));
    }

    public static function uses_https() {
        $home = home_url('/');
        return 'https' === strtolower((string) wp_parse_url($home, PHP_URL_SCHEME));
    }

    public static function log_file_path() {
        $uploads = wp_upload_dir();
        return empty($uploads['error']) && !empty($uploads['basedir'])
            ? trailingslashit($uploads['basedir']) . 'superwoo-logs/' . self::LOG_FILE
            : '';
    }

    public static function recent_receipts($limit = 100) {
        $path = self::log_file_path();
        if (!$path || !is_readable($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        $lines = $contents ? array_slice(array_filter(explode("\n", $contents)), -max(1, absint($limit))) : [];
        $receipts = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry)) {
                $receipts[] = $entry;
            }
        }
        return $receipts;
    }

    public function rotate_token() {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('superwoo_rotate_razorpay_webhook_token')) {
            wp_die(esc_html__('You are not allowed to rotate this webhook URL.', 'superwoo'));
        }
        update_option(self::TOKEN_OPTION, wp_generate_password(64, false, false), false);
        update_option(self::SHORT_TOKEN_OPTION, wp_generate_password(24, false, false), false);
        wp_safe_redirect(admin_url('admin.php?page=superwoo-logs&webhook_token_rotated=1'));
        exit;
    }

    private static function build_redacted_summary($payload, $body, $receipt_id) {
        $items = isset($payload['line_items']) && is_array($payload['line_items']) ? $payload['line_items'] : [];
        $utm = isset($payload['utm_parameters']) && is_array($payload['utm_parameters']) ? $payload['utm_parameters'] : [];
        $customer = isset($payload['customer']) && is_array($payload['customer']);
        return [
            'received_at_utc' => gmdate('Y-m-d H:i:s'),
            'receipt_id' => sanitize_text_field($receipt_id),
            'event' => 'magic_checkout_abandoned_cart',
            'payload_sha256' => hash('sha256', $body),
            'top_level_field_count' => count($payload),
            'platform' => isset($payload['platform']) && is_scalar($payload['platform']) ? sanitize_key((string) $payload['platform']) : 'unknown',
            'currency' => isset($payload['currency']) && is_scalar($payload['currency']) && preg_match('/^[A-Za-z]{3}$/', (string) $payload['currency']) ? strtoupper((string) $payload['currency']) : '',
            'line_item_count' => count($items),
            'promotion_count' => isset($payload['promotions']) && is_array($payload['promotions']) ? count($payload['promotions']) : 0,
            'utm_parameter_count' => count($utm),
            'customer_object_present' => $customer,
            'email_field_present' => array_key_exists('email', $payload) || ($customer && array_key_exists('email', $payload['customer'])),
            'phone_field_present' => array_key_exists('phone', $payload) || ($customer && array_key_exists('phone', $payload['customer'])),
        ];
    }

    private static function append_receipt($summary) {
        $path = self::log_file_path();
        if (!$path || !superwoo_protect_log_directory(dirname($path))) {
            return false;
        }

        if (file_exists($path) && filesize($path) > 524288) {
            $recent = file_get_contents($path, false, null, max(0, filesize($path) - 262144));
            file_put_contents($path, $recent ?: '', LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        $line = wp_json_encode($summary, JSON_UNESCAPED_SLASHES) . "\n";
        return false !== file_put_contents($path, $line, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }
}

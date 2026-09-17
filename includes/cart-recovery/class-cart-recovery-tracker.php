<?php
defined('ABSPATH') || exit;

class SuperWoo_Cart_Recovery_Tracker {
    private $repository;
    public function __construct($repository) { $this->repository = $repository; }

    public function session_id() {
        $name = 'superwoo_recovery_session';
        $value = isset($_COOKIE[$name]) ? sanitize_text_field(wp_unslash($_COOKIE[$name])) : '';
        if (!preg_match('/^[a-f0-9-]{36}$/i', $value)) {
            $value = wp_generate_uuid4();
            if (!headers_sent()) { setcookie($name, $value, time() + YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true); }
            $_COOKIE[$name] = $value;
        }
        return $value;
    }

    public function capture($reason = 'cart_updated', $identity = []) {
        $cart = superwoo_get_cart();
        if (!$cart || $cart->is_empty()) { return null; }
        try {
            $items = []; $fingerprint_items = [];
            foreach ($cart->get_cart() as $item) {
                $product = isset($item['data']) && $item['data'] instanceof WC_Product ? $item['data'] : null;
                if (!$product) { continue; }
                $row = [
                    'product_id' => absint($item['product_id'] ?? $product->get_parent_id() ?: $product->get_id()),
                    'variation_id' => absint($item['variation_id'] ?? 0),
                    'product_name' => $product->get_name(),
                    'variation_attributes' => wp_json_encode($item['variation'] ?? []),
                    'quantity' => max(1, absint($item['quantity'] ?? 1)),
                    'unit_price' => (float) $product->get_price(),
                    'line_subtotal' => (float) ($item['line_subtotal'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ];
                $items[] = $row; $fingerprint_items[] = [$row['product_id'], $row['variation_id'], $row['quantity'], $row['line_total'], $row['variation_attributes']];
            }
            if (!$items) { return null; }
            $customer = WC()->customer;
            $identity = array_merge([
                'first_name' => $customer ? $customer->get_billing_first_name() : '', 'last_name' => $customer ? $customer->get_billing_last_name() : '',
                'email' => $customer ? $customer->get_billing_email() : '', 'phone' => $customer ? $customer->get_billing_phone() : '',
            ], $identity);
            $fingerprint = hash('sha256', wp_json_encode([$fingerprint_items, $cart->get_applied_coupons(), $cart->get_total('edit'), $identity['email'], $identity['phone']]));
            $existing = $this->repository->find_cart_by_session($this->session_id());
            if ($existing && hash_equals((string) $existing['cart_fingerprint'], $fingerprint) && strtotime((string) $existing['updated_at']) > time() - 45) { return $existing; }
            $context = $this->context();
            $email = sanitize_email($identity['email']); $phone = preg_replace('/[^0-9+]/', '', (string) $identity['phone']);
            $data = [
                'customer_id' => get_current_user_id(), 'first_name' => sanitize_text_field($identity['first_name']), 'last_name' => sanitize_text_field($identity['last_name']),
                'email' => $email, 'email_hash' => $email ? $this->repository->hash(strtolower($email)) : '', 'phone' => $phone, 'phone_hash' => $phone ? $this->repository->hash($phone) : '',
                'currency' => get_woocommerce_currency(), 'cart_fingerprint' => $fingerprint, 'subtotal' => (float) $cart->get_subtotal(), 'total' => (float) $cart->get_total('edit'),
                'coupons' => wp_json_encode(array_values($cart->get_applied_coupons())), 'context' => wp_json_encode($context), 'last_activity_at' => $this->repository->now(),
            ];
            $saved = $this->repository->save_cart($this->session_id(), $data);
            $this->repository->replace_items((int) $saved['id'], $items);
            $this->repository->event((int) $saved['id'], $reason, ['item_count' => count($items), 'total' => $data['total']], '', $reason . ':' . $fingerprint);
            return $saved;
        } catch (Throwable $error) { superwoo_log('Cart Recovery capture failed', ['reason' => $reason, 'error_class' => get_class($error)], 'warning'); return null; }
    }

    public function context() {
        $utm = []; foreach (['utm_source','utm_medium','utm_campaign','utm_content','utm_term'] as $key) { if (!empty($_GET[$key])) { $utm[$key] = sanitize_text_field(wp_unslash($_GET[$key])); } }
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        return ['landing_page' => esc_url_raw((is_ssl() ? 'https://' : 'http://') . $host . $uri), 'referrer' => esc_url_raw(wp_get_raw_referer() ?: ''), 'device' => wp_is_mobile() ? 'mobile' : 'desktop', 'utm' => $utm, 'user_agent_hash' => $agent ? $this->repository->hash($agent) : ''];
    }
}

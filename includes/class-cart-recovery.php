<?php
defined('ABSPATH') || exit;

/** Cart Recovery coordinator. Keeps all recovery failures isolated from WooCommerce checkout. */
class SuperWoo_Cart_Recovery {
    private $repository;
    private $tracker;
    private $email;
    private $admin;
    private $automation;
    const GROUP = 'superwoo-cart-recovery';

    public function __construct() { $this->repository = new SuperWoo_Cart_Recovery_Repository(); $this->tracker = new SuperWoo_Cart_Recovery_Tracker($this->repository); $this->email = new SuperWoo_Cart_Recovery_Email($this->repository); $this->admin = new SuperWoo_Cart_Recovery_Admin($this->repository); $this->automation = new SuperWoo_Cart_Recovery_Automation($this->repository,$this->email); }
    public function hooks() {
        if (empty(superwoo_get_settings()['enable_cart_recovery'])) { return; }
        $this->admin->hooks();
        $this->automation->hooks();
        add_action('admin_init', ['SuperWoo_Cart_Recovery_Schema', 'maybe_install']);
        add_action('woocommerce_add_to_cart', [$this, 'capture_cart'], 20);
        add_action('woocommerce_cart_item_removed', [$this, 'capture_cart'], 20);
        add_action('woocommerce_cart_updated', [$this, 'capture_cart'], 20);
        add_action('woocommerce_applied_coupon', [$this, 'capture_cart'], 20);
        add_action('woocommerce_removed_coupon', [$this, 'capture_cart'], 20);
        add_action('woocommerce_checkout_update_order_review', [$this, 'checkout_started'], 20);
        add_action('woocommerce_checkout_order_processed', [$this, 'mark_order_recovered'], 20, 3);
        add_action('woocommerce_payment_complete', [$this, 'mark_order_recovered']);
        add_action('wp_ajax_superwoo_recovery_capture', [$this, 'ajax_capture']);
        add_action('wp_ajax_nopriv_superwoo_recovery_capture', [$this, 'ajax_capture']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('template_redirect', [$this, 'recover_cart']);
        add_action('superwoo_cart_recovery_process', [$this, 'process_abandonment']);
        add_action('superwoo_cart_recovery_send_email', [$this, 'send_recovery_email']);
        add_action('init', [$this, 'schedule']);
    }
    public static function install() { SuperWoo_Cart_Recovery_Schema::install(); }
    public function assets() {
        if (!is_checkout() || is_order_received_page()) { return; }
        wp_enqueue_script('superwoo-cart-recovery', SUPERWOO_URL . 'public/js/cart-recovery.js', ['jquery'], SUPERWOO_VERSION, true);
        wp_localize_script('superwoo-cart-recovery', 'SuperWooRecovery', ['ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('superwoo_recovery_capture'), 'blocksRequiresRuntimeValidation' => true]);
    }
    public function capture_cart() { $this->tracker->capture('cart_updated'); }
    public function checkout_started() { $this->tracker->capture('checkout_started'); }
    public function ajax_capture() {
        if (!check_ajax_referer('superwoo_recovery_capture', 'nonce', false)) { wp_send_json_error(['message' => __('Invalid request.', 'superwoo')], 403); }
        $limit = 'superwoo_recovery_capture_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? '')); if ((int) get_transient($limit) > 30) { wp_send_json_error(['message' => __('Too many requests.', 'superwoo')], 429); }
        set_transient($limit, (int) get_transient($limit) + 1, MINUTE_IN_SECONDS);
        $identity = ['first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')), 'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')), 'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')), 'phone' => sanitize_text_field(wp_unslash($_POST['phone'] ?? ''))];
        $cart = $this->tracker->capture('checkout_identity_captured', $identity);
        wp_send_json_success(['tracked' => (bool) $cart]);
    }
    public function schedule() {
        if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('superwoo_cart_recovery_process', [], self::GROUP)) { as_schedule_recurring_action(time() + 300, 300, 'superwoo_cart_recovery_process', [], self::GROUP); }
    }
    public function process_abandonment() {
        $minutes = max(5, absint(superwoo_get_settings()['cart_recovery_abandonment_minutes'] ?? 30));
        foreach ($this->repository->abandoned_candidates(gmdate('Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS)) as $cart) {
            $token = wp_generate_password(48, false, false); $expires = gmdate('Y-m-d H:i:s', time() + max(1, absint(superwoo_get_settings()['cart_recovery_token_days'] ?? 7)) * DAY_IN_SECONDS);
            if ($this->repository->update_status((int) $cart['id'], 'abandoned', ['abandoned_at' => $this->repository->now(), 'recovery_token_hash' => $this->repository->hash($token), 'recovery_token_expires_at' => $expires])) {
                $this->repository->event((int) $cart['id'], 'cart_abandoned');
                $this->automation->start($this->repository->find_cart((int) $cart['id']));
            }
        }
    }
    public function send_recovery_email($cart_id) {
        $cart = $this->repository->find_cart($cart_id);
        if (!$cart || !in_array($cart['status'], ['abandoned','recovery_in_progress'], true) || empty($cart['email'])) { return; }
        $token = wp_generate_password(48, false, false); $expires = gmdate('Y-m-d H:i:s', time() + max(1, absint(superwoo_get_settings()['cart_recovery_token_days'] ?? 7)) * DAY_IN_SECONDS);
        $this->repository->update_status((int) $cart['id'], 'recovery_in_progress', ['recovery_token_hash' => $this->repository->hash($token), 'recovery_token_expires_at' => $expires]);
        $this->email->send($this->repository->find_cart((int) $cart['id']), $this->recovery_url($token));
    }
    public function recovery_url($token) { return add_query_arg('superwoo_recover', rawurlencode($token), home_url('/')); }
    public function recover_cart() {
        $token = isset($_GET['superwoo_recover']) ? sanitize_text_field(wp_unslash($_GET['superwoo_recover'])) : '';
        if (!$token || strlen($token) < 32) { return; }
        $cart = $this->repository->find_cart_by_token($token);
        if (!$cart || in_array($cart['status'], ['recovered','expired','unsubscribed','suppressed'], true) || (!$cart['recovery_token_expires_at'] || strtotime($cart['recovery_token_expires_at']) < time())) { wp_die(esc_html__('This cart recovery link is no longer available.', 'superwoo'), esc_html__('Cart recovery', 'superwoo'), ['response' => 410]); }
        if (!WC()->cart) { return; }
        WC()->cart->empty_cart(); $unavailable = 0;
        foreach ($this->repository->items((int) $cart['id']) as $item) {
            $product = wc_get_product($item['variation_id'] ?: $item['product_id']);
            $attributes = $item['variation_attributes'] ? json_decode($item['variation_attributes'], true) : [];
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) { $unavailable++; continue; }
            WC()->cart->add_to_cart((int) $item['product_id'], max(1, (int) $item['quantity']), (int) $item['variation_id'], is_array($attributes) ? $attributes : []);
        }
        foreach ((array) json_decode($cart['coupons'], true) as $coupon) { if ($coupon && WC()->cart->has_discount($coupon) === false && (new WC_Coupon($coupon))->is_valid()) { WC()->cart->apply_coupon($coupon); } }
        WC()->cart->calculate_totals(); WC()->cart->set_session();
        $this->repository->update_status((int) $cart['id'], 'recovery_in_progress'); $this->repository->event((int) $cart['id'], 'recovery_link_clicked', ['unavailable_items' => $unavailable]);
        if ($unavailable) { wc_add_notice(__('Some unavailable products could not be restored.', 'superwoo'), 'notice'); }
        wp_safe_redirect(wc_get_checkout_url()); exit;
    }
    public function mark_order_recovered($order_id) {
        $cart = $this->repository->find_cart_by_session($this->tracker->session_id()); if (!$cart || !in_array($cart['status'], ['abandoned','recovery_in_progress'], true)) { return; }
        $this->repository->update_status((int) $cart['id'], 'recovered', ['recovered_at' => $this->repository->now(), 'order_id' => absint($order_id)]); $this->repository->event((int) $cart['id'], 'order_recovered', ['order_id' => absint($order_id)]);
        $order = wc_get_order($order_id); if ($order) { $order->update_meta_data('_superwoo_recovery_cart_id', (string) $cart['public_id']); $order->update_meta_data('_superwoo_recovery_original_total', (string) $cart['total']); $order->save(); }
    }
}

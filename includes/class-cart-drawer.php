<?php
defined('ABSPATH') || exit;

class SuperWoo_Cart_Drawer {
    public function hooks() {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_cart_drawer'])) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_footer', [$this, 'render']);
        add_filter('body_class', [$this, 'body_classes']);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'fragments']);
        add_action('wp_ajax_superwoo_update_cart_item', [$this, 'ajax_update_cart_item']);
        add_action('wp_ajax_nopriv_superwoo_update_cart_item', [$this, 'ajax_update_cart_item']);
        add_action('wp_ajax_superwoo_remove_cart_item', [$this, 'ajax_remove_cart_item']);
        add_action('wp_ajax_nopriv_superwoo_remove_cart_item', [$this, 'ajax_remove_cart_item']);
        add_action('wp_ajax_superwoo_refresh_cart_drawer', [$this, 'ajax_refresh_cart_drawer']);
        add_action('wp_ajax_nopriv_superwoo_refresh_cart_drawer', [$this, 'ajax_refresh_cart_drawer']);
        add_action('wc_ajax_superwoo_update_cart_item', [$this, 'ajax_update_cart_item']);
        add_action('wc_ajax_superwoo_remove_cart_item', [$this, 'ajax_remove_cart_item']);
        add_action('wp_ajax_superwoo_add_cross_sell', [$this, 'ajax_add_cross_sell']);
        add_action('wp_ajax_nopriv_superwoo_add_cross_sell', [$this, 'ajax_add_cross_sell']);
        add_action('wp_ajax_superwoo_add_product_to_cart', [$this, 'ajax_add_product_to_cart']);
        add_action('wp_ajax_nopriv_superwoo_add_product_to_cart', [$this, 'ajax_add_product_to_cart']);
    }

    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        $deps = ['jquery'];
        if (wp_script_is('wc-cart-fragments', 'registered')) {
            $deps[] = 'wc-cart-fragments';
        }

        wp_enqueue_style('superwoo-cart-drawer', SUPERWOO_URL . 'public/css/cart-drawer.css', [], SUPERWOO_VERSION);
        wp_enqueue_style('superwoo-bundle-offers', SUPERWOO_URL . 'public/css/bundle-offers.css', [], SUPERWOO_VERSION);

        wp_enqueue_script('superwoo-cart-drawer', SUPERWOO_URL . 'public/js/cart-drawer.js', $deps, SUPERWOO_VERSION, true);

        $settings = superwoo_get_settings();

        wp_localize_script('superwoo-cart-drawer', 'SuperWooCart', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'wcAjaxUrl'     => class_exists('WC_AJAX') ? WC_AJAX::get_endpoint('%%endpoint%%') : '',
            'nonce'         => wp_create_nonce('superwoo_cart_nonce'),
            'cartUrl'       => wc_get_cart_url(),
            'cartApiUrl'    => rest_url('wc/store/v1/cart'),
            'cartCount'     => superwoo_cart_count(),
            'autoOpen'      => !empty($settings['cart_auto_open']),
            'dashboardPage' => $this->is_dashboard_page(),
            'offerState'    => superwoo_cart_offer_state(),
            'addToCartDiagnostics' => !empty($settings['enable_add_to_cart_diagnostics']),
            'headerCartIcon' => sanitize_key($settings['header_cart_icon'] ?? 'outline-bag'),
            'i18n'          => [
                'updating'      => __('Updating...', 'superwoo'),
                'error'         => __('Could not update the cart. Please try again.', 'superwoo'),
                'cartItems'     => __('Cart items', 'superwoo'),
                'chooseOptions' => __('Please choose product options before adding this product to your cart.', 'superwoo'),
            ],
        ]);
    }

    public function render() {
        if (is_checkout() || !superwoo_get_cart()) {
            return;
        }

        echo superwoo_template('cart-drawer.php', [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'drawer' => $this,
        ]);
    }

    public function body_classes($classes) {
        $is_dashboard_page = $this->is_dashboard_page();

        if ($is_dashboard_page) {
            $classes[] = 'superwoo-dashboard-page';
        }

        if (!is_checkout() && !$is_dashboard_page) {
            $classes[] = 'superwoo-has-mobile-bottom-nav';
        }

        if (is_singular('product') && !is_checkout()) {
            $classes[] = 'superwoo-mobile-cart-enabled';
        }

        return $classes;
    }

    public function should_render_mobile_bottom_nav() {
        return !is_checkout() && !$this->is_dashboard_page();
    }

    public function fragments($fragments) {
        foreach (superwoo_cart_drawer_fragments() as $selector => $html) {
            $fragments[$selector] = $html;
        }

        return $fragments;
    }

    public function get_inner_html() {
        $cart = superwoo_get_cart();
        if (!$cart) {
            return '';
        }

        return superwoo_template('cart-drawer-inner.php', [
            'drawer'    => $this,
            'cart'      => $cart,
            'settings'  => superwoo_get_settings(),
            'crosssell' => $this->get_cross_sell_products(),
        ]);
    }

    public function ajax_update_cart_item() {
        $this->verify_ajax();

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';
        $quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;

        if (!$cart_item_key || !$this->get_cart_item($cart_item_key)) {
            superwoo_log('Cart drawer quantity update rejected: invalid cart item', [], 'warning');
            wp_send_json_error(['message' => __('Invalid cart item.', 'superwoo')], 400);
        }

        $previous_offer_state = $this->get_offer_state();

        if (!WC()->cart->set_quantity($cart_item_key, $quantity, true)) {
            superwoo_log('Cart drawer quantity update failed', ['quantity' => $quantity], 'error');
            wp_send_json_error(['message' => __('This item could not be updated.', 'superwoo')], 400);
        }

        WC()->cart->calculate_totals();
        $this->persist_cart_session();

        $this->send_fragments($previous_offer_state);
    }

    public function ajax_remove_cart_item() {
        $this->verify_ajax();

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field(wp_unslash($_POST['cart_item_key'])) : '';

        if (!$cart_item_key || !$this->get_cart_item($cart_item_key)) {
            superwoo_log('Cart drawer removal rejected: invalid cart item', [], 'warning');
            wp_send_json_error(['message' => __('Invalid cart item.', 'superwoo')], 400);
        }

        $previous_offer_state = $this->get_offer_state();

        if (!WC()->cart->remove_cart_item($cart_item_key)) {
            superwoo_log('Cart drawer item removal failed', [], 'error');
            wp_send_json_error(['message' => __('This item could not be removed from the cart.', 'superwoo')], 400);
        }

        WC()->cart->calculate_totals();
        $this->persist_cart_session();

        // A cart removal is already a complete WooCommerce state change. Do
        // not restore a SuperWoo quantity snapshot afterwards.
        $this->send_fragments($previous_offer_state);
    }

    /**
     * Read-only endpoint used after WooCommerce Store API cart mutations.
     */
    public function ajax_refresh_cart_drawer() {
        $this->verify_ajax();
        superwoo_log('Cart drawer refresh requested', ['cart_count' => superwoo_cart_count()]);
        $this->send_fragments();
    }

    public function ajax_add_cross_sell() {
        $this->verify_ajax();

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id) {
            superwoo_log('Cross-sell add rejected: missing product ID', [], 'warning');
            wp_send_json_error(['message' => __('Invalid product.', 'superwoo')], 400);
        }

        $previous_offer_state = $this->get_offer_state();

        $added = WC()->cart->add_to_cart($product_id, 1);
        superwoo_log('Cross-sell add attempted', ['product_id' => $product_id, 'success' => (bool) $added]);
        if (!$added) {
            superwoo_log('Cross-sell add failed', ['product_id' => $product_id], 'error');
            wp_send_json_error(['message' => __('Product could not be added.', 'superwoo')], 400);
        }

        $allowed_quantities = $this->get_customer_cart_quantities();

        WC()->cart->calculate_totals();
        WC()->cart->set_session();
        $this->send_fragments($previous_offer_state, $allowed_quantities);
    }

    public function ajax_add_product_to_cart() {
        $this->verify_ajax();

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        if (!$product_id && isset($_POST['add-to-cart'])) {
            $product_id = absint($_POST['add-to-cart']);
        }

        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $quantity = isset($_POST['quantity']) && !is_array($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : 1;
        $quantity = max(1, $quantity);
        $variation = [];

        foreach ($_POST as $key => $value) {
            if (0 !== strpos($key, 'attribute_') || is_array($value)) {
                continue;
            }
            $variation[sanitize_key(wp_unslash($key))] = wc_clean(wp_unslash($value));
        }

        if (!$product_id && $variation_id) {
            $variation_product = wc_get_product($variation_id);
            $product_id = $variation_product ? $variation_product->get_parent_id() : 0;
        }

        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product || !$product_id || in_array($product->get_type(), ['external', 'grouped'], true)) {
            wp_send_json_error(['message' => __('This product cannot be added with AJAX.', 'superwoo')], 400);
        }

        $action_id = isset($_POST['superwoo_action_id']) ? sanitize_key(wp_unslash($_POST['superwoo_action_id'])) : '';
        $before_quantity = $this->get_matching_cart_quantity($product_id, $variation_id);
        if ($this->is_duplicate_product_add_action($action_id)) {
            $this->send_fragments(null, null, $this->get_product_add_diagnostic_data($action_id, $product_id, $variation_id, $quantity, $before_quantity, $before_quantity, 'duplicate_skipped'));
        }

        $add_signature = $this->get_product_add_signature($product_id, $variation_id, $quantity, $variation);
        if ($this->is_recent_product_add_signature($add_signature)) {
            $this->send_fragments(null, null, $this->get_product_add_diagnostic_data($action_id, $product_id, $variation_id, $quantity, $before_quantity, $before_quantity, 'duplicate_skipped'));
        }

        $passed = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation);
        if (!$passed) {
            wp_send_json_error(['message' => $this->get_cart_error_message()], 400);
        }

        $previous_offer_state = $this->get_offer_state();
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
        if (!$added) {
            wp_send_json_error(['message' => $this->get_cart_error_message()], 400);
        }

        // Some variation/bundle integrations mutate line quantities from cart
        // hooks even though this request was submitted once. The cart line
        // returned by WooCommerce belongs to this request, so lock it to the
        // exact customer-requested result before totals and fragments run.
        $expected_quantity = max(1, $before_quantity + $quantity);
        $current_added_quantity = isset(WC()->cart->cart_contents[$added]['quantity'])
            ? max(1, absint(WC()->cart->cart_contents[$added]['quantity']))
            : 0;
        if ($current_added_quantity !== $expected_quantity) {
            WC()->cart->set_quantity($added, $expected_quantity, false);
        }
        $protected_quantities = [$added => $expected_quantity];
        $allowed_quantities = $this->get_customer_cart_quantities();
        $allowed_quantities[$added] = $expected_quantity;

        $this->remember_product_add_action($action_id);
        $this->remember_product_add_signature($add_signature);
        $this->log_product_add_diagnostic('added', $product_id, $variation_id, $quantity, $action_id);
        do_action('woocommerce_ajax_added_to_cart', $product_id);

        $this->send_fragments(
            $previous_offer_state,
            $allowed_quantities,
            $this->get_product_add_diagnostic_data($action_id, $product_id, $variation_id, $quantity, $before_quantity, $this->get_matching_cart_quantity($product_id, $variation_id), 'added'),
            $protected_quantities
        );
    }

    public function get_cross_sell_products() {
        $settings = superwoo_get_settings();
        if (empty($settings['cart_drawer_crosssell']) || !WC()->cart || WC()->cart->is_empty()) {
            return [];
        }

        $ids = [];
        foreach (WC()->cart->get_cart() as $item) {
            if (empty($item['data']) || !($item['data'] instanceof WC_Product)) {
                continue;
            }

            $ids = array_merge($ids, $item['data']->get_cross_sell_ids());
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (empty($ids)) {
            $cart_product_ids = array_map(static function ($item) {
                return absint($item['product_id'] ?? 0);
            }, WC()->cart->get_cart());

            $ids = wc_get_related_products(reset($cart_product_ids), 6, $cart_product_ids);
        }

        $ids = array_slice($ids, 0, 6);
        $products = [];

        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if ($product && $product->is_visible() && $product->is_purchasable() && $product->is_in_stock() && 'simple' === $product->get_type()) {
                $products[] = $product;
            }
        }

        return $products;
    }

    public function get_free_shipping_message() {
        return '';
    }

    public function get_cart_notice_message() {
        if (function_exists('superwoo_cart_offer_state')) {
            $state = superwoo_cart_offer_state();
            if (is_array($state)) {
                foreach (['gifts', 'discounts'] as $type) {
                    $items = isset($state[$type]) && is_array($state[$type]) ? $state[$type] : [];
                    foreach ($items as $item) {
                        if (is_array($item) && !empty($item['message'])) {
                            return wp_strip_all_tags((string) $item['message']);
                        }
                    }
                }
            }
        }

        $threshold = $this->get_free_delivery_threshold();
        if ($threshold <= 0 || !WC()->cart || WC()->cart->is_empty()) {
            return '';
        }

        $subtotal = $this->get_notice_cart_subtotal();
        if ($subtotal >= $threshold) {
            return __('Free delivery unlocked on this order.', 'superwoo');
        }

        $remaining = max(0, $threshold - $subtotal);
        /* translators: %s: amount remaining before free delivery is unlocked. */
        return sprintf(
            __('Add %1$s more to unlock free delivery.', 'superwoo'),
            wp_strip_all_tags(superwoo_format_selected_currency_amount($remaining))
        );
    }

    private function get_free_delivery_threshold() {
        return (float) get_option('superwoo_offer_free_delivery_threshold', 0);
    }

    private function get_notice_cart_subtotal() {
        if (!WC()->cart) {
            return 0;
        }

        $subtotal = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if (!empty($item['superwoo_free_gift'])) {
                continue;
            }

            $subtotal += isset($item['line_subtotal']) ? (float) $item['line_subtotal'] : 0;
        }

        return max(0, $subtotal);
    }

    public function get_mobile_nav_items() {
        return [
            [
                'key'   => 'home',
                'label' => __('Home', 'superwoo'),
                'url'   => home_url('/'),
                'active' => is_front_page() || is_home(),
            ],
            [
                'key'    => 'search',
                'label'  => __('Search', 'superwoo'),
                'url'    => add_query_arg(['s' => '', 'post_type' => 'product'], home_url('/')),
                'button' => true,
                'active' => is_search(),
            ],
            [
                'key'   => 'account',
                'label' => __('Account', 'superwoo'),
                'url'   => home_url('/dashboard/'),
                'active' => $this->is_dashboard_page(),
            ],
            [
                'key'    => 'cart',
                'label'  => __('Cart', 'superwoo'),
                'url'    => wc_get_cart_url(),
                'button' => true,
                'active' => is_cart(),
            ],
        ];
    }

    public function mobile_nav_icon($key) {
        $icons = [
            'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8"/><path d="M5 9.5V21h14V9.5M9 21v-7h6v7"/></svg>',
            'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>',
            'account' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7.5" r="4"/><path d="M4.5 21v-1.5a7.5 7.5 0 0 1 15 0V21"/></svg>',
            'cart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg>',
        ];

        return $icons[$key] ?? '';
    }

    private function is_dashboard_page() {
        if (is_page('dashboard')) {
            return true;
        }

        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH) : '';
        $path = strtolower(trim(rawurldecode((string) $path), '/'));
        $home_path = strtolower(trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/'));

        if ($home_path && (0 === strpos($path, $home_path . '/') || $path === $home_path)) {
            $path = trim(substr($path, strlen($home_path)), '/');
        }

        return 'dashboard' === $path || 0 === strpos($path, 'dashboard/') || false !== strpos('/' . $path . '/', '/dashboard/');
    }

    private function verify_ajax() {
        if (!superwoo_is_woocommerce_active() || !WC()->cart) {
            superwoo_log('Cart drawer AJAX failed: WooCommerce cart unavailable', [], 'error');
            wp_send_json_error(['message' => __('WooCommerce cart is unavailable.', 'superwoo')], 400);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!$nonce && isset($_POST['security'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['security']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'superwoo_cart_nonce')) {
            superwoo_log('Cart drawer AJAX failed: invalid security token', [], 'warning');
            wp_send_json_error(['message' => __('Security check failed.', 'superwoo')], 403);
        }
    }

    private function get_cart_item($cart_item_key) {
        $cart = WC()->cart ? WC()->cart->get_cart() : [];
        return isset($cart[$cart_item_key]) ? $cart[$cart_item_key] : null;
    }

    /**
     * Prevent the same browser action from adding a product twice when a
     * theme, Elementor, or a duplicated frontend script submits it again.
     */
    private function is_duplicate_product_add_action($action_id) {
        if (!$action_id || !WC()->session) {
            return false;
        }

        $actions = WC()->session->get('superwoo_product_add_actions', []);
        if (!is_array($actions)) {
            return false;
        }

        $now = time();
        foreach ($actions as $id => $timestamp) {
            if ($now - absint($timestamp) > 300) {
                unset($actions[$id]);
            }
        }

        return isset($actions[$action_id]);
    }

    private function remember_product_add_action($action_id) {
        if (!$action_id || !WC()->session) {
            return;
        }

        $actions = WC()->session->get('superwoo_product_add_actions', []);
        $actions = is_array($actions) ? $actions : [];
        $actions[$action_id] = time();

        if (count($actions) > 20) {
            asort($actions, SORT_NUMERIC);
            $actions = array_slice($actions, -20, null, true);
        }

        WC()->session->set('superwoo_product_add_actions', $actions);
    }

    private function get_product_add_signature($product_id, $variation_id, $quantity, $variation) {
        ksort($variation);

        return md5(wp_json_encode([
            absint($product_id),
            absint($variation_id),
            max(1, absint($quantity)),
            $variation,
        ]));
    }

    private function is_recent_product_add_signature($signature) {
        if (!$signature || !WC()->session) {
            return false;
        }

        $recent = WC()->session->get('superwoo_recent_product_adds', []);
        $recent = is_array($recent) ? $recent : [];
        $now = microtime(true);

        foreach ($recent as $key => $timestamp) {
            if ($now - (float) $timestamp > 5) {
                unset($recent[$key]);
            }
        }

        WC()->session->set('superwoo_recent_product_adds', $recent);

        return isset($recent[$signature]) && $now - (float) $recent[$signature] < 2.5;
    }

    private function remember_product_add_signature($signature) {
        if (!$signature || !WC()->session) {
            return;
        }

        $recent = WC()->session->get('superwoo_recent_product_adds', []);
        $recent = is_array($recent) ? $recent : [];
        $recent[$signature] = microtime(true);
        WC()->session->set('superwoo_recent_product_adds', array_slice($recent, -20, null, true));
    }

    private function log_product_add_diagnostic($stage, $product_id, $variation_id, $requested_quantity, $action_id) {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_add_to_cart_diagnostics']) || !WC()->cart) {
            return;
        }

        superwoo_log('Add-to-cart diagnostic', [
            'action_id' => $action_id,
            'cart_quantity' => $this->get_matching_cart_quantity($product_id, $variation_id),
            'product_id' => absint($product_id),
            'requested_quantity' => absint($requested_quantity),
            'stage' => sanitize_key($stage),
            'variation_id' => absint($variation_id),
        ], 'debug');
    }

    private function get_matching_cart_quantity($product_id, $variation_id) {
        if (!WC()->cart) {
            return 0;
        }

        $matching_quantity = 0;
        foreach (WC()->cart->get_cart() as $item) {
            if (absint($item['product_id'] ?? 0) !== absint($product_id) || absint($item['variation_id'] ?? 0) !== absint($variation_id)) {
                continue;
            }

            $matching_quantity += absint($item['quantity'] ?? 0);
        }

        return $matching_quantity;
    }

    private function get_product_add_diagnostic_data($action_id, $product_id, $variation_id, $requested_quantity, $before_quantity, $after_quantity, $stage, $filtered_quantity = null) {
        if (!$this->should_trace_product_add()) {
            return null;
        }

        return [
            'action_id' => $action_id,
            'after_quantity' => absint($after_quantity),
            'before_quantity' => absint($before_quantity),
            'filtered_quantity' => null === $filtered_quantity ? null : absint($filtered_quantity),
            'product_id' => absint($product_id),
            'requested_quantity' => absint($requested_quantity),
            'stage' => sanitize_key($stage),
            'variation_id' => absint($variation_id),
        ];
    }

    private function should_trace_product_add() {
        $settings = superwoo_get_settings();

        return !empty($settings['enable_add_to_cart_diagnostics']) || !empty($_POST['superwoo_quantity_trace']);
    }

    private function get_cart_error_message() {
        $notices = wc_get_notices('error');
        if (!empty($notices)) {
            $notice = reset($notices);
            $message = is_array($notice) && isset($notice['notice']) ? $notice['notice'] : $notice;
            wc_clear_notices();

            return wp_strip_all_tags((string) $message);
        }

        return __('Product could not be added to the cart.', 'superwoo');
    }

    private function get_offer_state() {
        if (!function_exists('superwoo_cart_offer_state')) {
            return [
                'discounts' => [],
                'gifts'     => [],
            ];
        }

        $state = superwoo_cart_offer_state();

        return is_array($state) ? $state : [
            'discounts' => [],
            'gifts'     => [],
        ];
    }

    private function get_offer_events($previous_offer_state, $next_offer_state) {
        if (!is_array($previous_offer_state) || !is_array($next_offer_state)) {
            return [];
        }

        $events = [];
        foreach (['gifts', 'discounts'] as $type) {
            $previous_items = isset($previous_offer_state[$type]) && is_array($previous_offer_state[$type]) ? $previous_offer_state[$type] : [];
            $next_items = isset($next_offer_state[$type]) && is_array($next_offer_state[$type]) ? $next_offer_state[$type] : [];

            foreach ($next_items as $id => $item) {
                if (array_key_exists($id, $previous_items)) {
                    continue;
                }

                $message = is_array($item) && !empty($item['message']) ? wp_strip_all_tags($item['message']) : '';
                if ('' === $message) {
                    continue;
                }

                $events[] = [
                    'type'    => $type,
                    'id'      => (string) $id,
                    'message' => $message,
                ];
            }
        }

        return $events;
    }

    private function get_customer_cart_quantities() {
        $quantities = [];

        if (!WC()->cart) {
            return $quantities;
        }

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['superwoo_free_gift'])) {
                continue;
            }

            $quantities[$cart_item_key] = max(1, absint($cart_item['quantity'] ?? 1));
        }

        return $quantities;
    }

    private function restore_customer_cart_quantities($allowed_quantities) {
        if (!is_array($allowed_quantities) || !WC()->cart) {
            return false;
        }

        $changed = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['superwoo_free_gift'])) {
                continue;
            }

            if (!array_key_exists($cart_item_key, $allowed_quantities)) {
                WC()->cart->remove_cart_item($cart_item_key);
                $changed = true;
                continue;
            }

            $allowed_quantity = max(1, absint($allowed_quantities[$cart_item_key]));
            $current_quantity = max(1, absint($cart_item['quantity'] ?? 1));
            if ($current_quantity !== $allowed_quantity) {
                WC()->cart->set_quantity($cart_item_key, $allowed_quantity, false);
                $changed = true;
            }
        }

        return $changed;
    }

    private function persist_cart_session() {
        if (!WC()->cart) {
            return;
        }

        // Let WooCommerce persist the complete cart payload (cart, totals,
        // coupons and chosen shipping), instead of writing an isolated `cart`
        // session key that can leave an older state behind on the next request.
        WC()->cart->set_session();

        if (WC()->session && is_callable([WC()->session, 'set_customer_session_cookie'])) {
            WC()->session->set_customer_session_cookie(true);
        }
    }

    private function send_fragments($previous_offer_state = null, $allowed_quantities = null, $diagnostic = null, $protected_quantities = []) {
        wc_clear_notices();

        // Some third-party callbacks run during calculate_totals().  Run one
        // calculation only and restore the cart line(s) owned by this request at
        // the end of that hook, before WooCommerce derives totals and fragments.
        $quantity_protector = null;
        if (!empty($protected_quantities)) {
            $quantity_protector = static function ($cart) use ($protected_quantities) {
                if (!is_object($cart)) {
                    return;
                }

                foreach ($protected_quantities as $cart_item_key => $expected_quantity) {
                    $current_quantity = isset($cart->cart_contents[$cart_item_key]['quantity'])
                        ? max(1, absint($cart->cart_contents[$cart_item_key]['quantity']))
                        : 0;
                    if ($current_quantity && $current_quantity !== max(1, absint($expected_quantity))) {
                        $cart->set_quantity($cart_item_key, max(1, absint($expected_quantity)), false);
                    }
                }
            };
            add_action('woocommerce_before_calculate_totals', $quantity_protector, PHP_INT_MAX);
        }

        WC()->cart->calculate_totals();
        if ($quantity_protector) {
            remove_action('woocommerce_before_calculate_totals', $quantity_protector, PHP_INT_MAX);
        }

        if (null !== $allowed_quantities) {
            $this->restore_customer_cart_quantities($allowed_quantities);
        }
        $this->persist_cart_session();

        $offer_state = $this->get_offer_state();

        $response = [
            // Totals were calculated above. Avoid re-running third-party cart
            // hooks while merely rendering the AJAX response.
            'fragments'   => superwoo_cart_drawer_fragments(false),
            'cart_hash'   => WC()->cart->get_cart_hash(),
            'count'       => superwoo_cart_count(),
            'offerState'  => $offer_state,
            'offerEvents' => null === $previous_offer_state ? [] : $this->get_offer_events($previous_offer_state, $offer_state),
        ];

        if (is_array($diagnostic)) {
            $response['diagnostic'] = $diagnostic;
        }

        wp_send_json_success($response);
    }
}

<?php
defined('ABSPATH') || exit;

class SuperWoo_Plugin {
    private static $instance = null;
    private $guard;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->guard = new SuperWoo_WooCommerce_Guard();
        superwoo_log('Plugin loaded');

        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_post_superwoo_clear_logs', [$this, 'clear_logs']);
        add_action('admin_init', [$this, 'save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_appearance'], 99);
        add_action('woocommerce_add_to_cart', [$this, 'log_cart_add'], 10, 6);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'log_cart_add_validation'], PHP_INT_MAX, 6);
        add_action('woocommerce_cart_item_removed', [$this, 'log_cart_remove'], 10, 2);
        add_action('woocommerce_after_checkout_validation', [$this, 'log_checkout_validation'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'log_checkout_success'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'log_order_status_change'], 10, 4);
        // Observation only: these hooks record WooCommerce's result and never
        // call a gateway, alter an order, or participate in checkout handling.
        add_action('woocommerce_payment_complete', [$this, 'log_payment_complete'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'log_payment_failure'], 10, 1);

        if (!$this->guard->is_available()) {
            $this->guard->hooks();
            return;
        }

        // Ordinary wp-admin screens only need SuperWoo's administration
        // modules. Loading cart, pricing, shortcode and display integrations
        // here creates unnecessary conflicts on Plugins and Updates pages.
        // AJAX requests are excluded because the cart drawer uses admin-ajax.
        $is_regular_admin = is_admin() && !wp_doing_ajax();

        (new SuperWoo_Benefit_Taxonomy())->hooks();
        (new SuperWoo_Product_Meta())->hooks();
        (new SuperWoo_Bundle_Offers())->hooks();
        (new SuperWoo_Elementor_Dynamic_Tags())->hooks();
        // Elementor loads its widget library from wp-admin. Register Shop
        // Filters before the regular-admin early return so it is discoverable
        // in the editor as well as on the storefront.
        if (!empty(superwoo_get_settings()['enable_shop_filters'])) {
            (new SuperWoo_Shop_Filters())->hooks();
        }
        if (!empty(superwoo_get_settings()['enable_shoppable_videos'])) {
            (new SuperWoo_Shoppable_Videos())->hooks();
        }
        if ($is_regular_admin) {
            return;
        }

        // WooCommerce remains the sole source of truth for currency and
        // product/cart prices. SuperWoo only renders the cart UI.
        (new SuperWoo_Discount_Percentage())->hooks();
        (new SuperWoo_Shortcodes())->hooks();
        (new SuperWoo_Product_Reviews())->hooks();
        (new SuperWoo_Variation_Cards())->hooks();
        (new SuperWoo_Cart_Drawer())->hooks();
        if (!empty(superwoo_get_settings()['enable_elementor_products_carousel']) && class_exists('SuperWoo_Elementor_Products_Carousel')) {
            (new SuperWoo_Elementor_Products_Carousel())->hooks();
        }

    }

    public static function activate() {
        if (!get_option('superwoo_settings')) {
            add_option('superwoo_settings', superwoo_get_settings());
        }
        if (class_exists('SuperWoo_Shoppable_Videos')) {
            SuperWoo_Shoppable_Videos::install();
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('superwoo_video_cleanup_analytics');
        flush_rewrite_rules();
    }

    public function register_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        add_menu_page(
            __('SuperWoo', 'superwoo'),
            __('SuperWoo', 'superwoo'),
            'manage_woocommerce',
            'superwoo-settings',
            [$this, 'render_settings_page'],
            'dashicons-cart',
            56
        );
        add_submenu_page('superwoo-settings', __('Health', 'superwoo'), __('Health', 'superwoo'), 'manage_woocommerce', 'superwoo-health', [$this, 'render_health_page']);
        add_submenu_page('superwoo-settings', __('Logs', 'superwoo'), __('Logs', 'superwoo'), 'manage_woocommerce', 'superwoo-logs', [$this, 'render_logs_page']);
    }

    public function render_health_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $report = superwoo_health_report();
        include SUPERWOO_PATH . 'admin/views/health-page.php';
    }

    public function render_logs_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $path = superwoo_log_file_path();
        $contents = $path && file_exists($path) ? file_get_contents($path) : '';
        $raw_lines = $contents ? array_slice(array_filter(explode("\n", $contents)), -500) : [];
        $entries = [];
        foreach ($raw_lines as $line) {
            $entry = ['line' => $line, 'category' => 'diagnostics'];
            if (preg_match('/^\[[^]]+\] \[([A-Z]+)\] .* (\{.*\})$/', $line, $matches)) {
                $context = json_decode($matches[2], true);
                if (is_array($context) && !empty($context['log_category'])) {
                    $entry['category'] = sanitize_key($context['log_category']);
                } elseif (in_array(strtolower($matches[1]), ['emergency', 'alert', 'critical', 'error', 'warning'], true)) {
                    $entry['category'] = 'errors';
                }
            }
            $entries[] = $entry;
        }
        $lines_by_category = [
            'orders' => [],
            'payments' => [],
            'errors' => [],
            'diagnostics' => [],
        ];
        foreach ($entries as $entry) {
            $category = isset($lines_by_category[$entry['category']]) ? $entry['category'] : 'diagnostics';
            $lines_by_category[$category][] = $entry['line'];
        }
        foreach ($lines_by_category as $category => $category_lines) {
            $lines_by_category[$category] = array_slice($category_lines, -150);
        }
        $fatal_path = superwoo_fatal_log_file_path();
        $fatal_contents = $fatal_path && file_exists($fatal_path) ? file_get_contents($fatal_path) : '';
        $fatal_lines = $fatal_contents ? array_slice(array_filter(explode("\n", $fatal_contents)), -100) : [];
        $logging_enabled = !empty(superwoo_get_settings()['enable_logging']);
        ?>
        <div class="wrap superwoo-admin-page">
            <h1><?php esc_html_e('SuperWoo Logs', 'superwoo'); ?></h1>
            <p><?php esc_html_e('Recent WooCommerce order and payment status events plus SuperWoo diagnostics. Payment entries only record WooCommerce status signals; they never include gateway, transaction, amount, or customer details.', 'superwoo'); ?></p>
            <?php if (!$logging_enabled) : ?><div class="notice notice-warning inline"><p><?php esc_html_e('Diagnostic logging is currently disabled. Enable it from SuperWoo → Settings → Cart to record new events.', 'superwoo'); ?></p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="superwoo_clear_logs">
                <?php wp_nonce_field('superwoo_clear_logs'); ?>
                <?php submit_button(__('Clear Logs', 'superwoo'), 'delete', 'submit', false); ?>
            </form>
            <h2><?php esc_html_e('Order & checkout log', 'superwoo'); ?></h2>
            <pre style="background:#111827;color:#e5e7eb;max-height:360px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $lines_by_category['orders']) ?: __('No order or checkout events recorded.', 'superwoo')); ?></pre>
            <h2><?php esc_html_e('Payment status log', 'superwoo'); ?></h2>
            <pre style="background:#111827;color:#e5e7eb;max-height:300px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $lines_by_category['payments']) ?: __('No payment status events recorded.', 'superwoo')); ?></pre>
            <h2><?php esc_html_e('Errors', 'superwoo'); ?></h2>
            <pre style="background:#3b0d0d;color:#fee2e2;max-height:300px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $lines_by_category['errors']) ?: __('No SuperWoo errors recorded.', 'superwoo')); ?></pre>
            <h2><?php esc_html_e('Other diagnostics', 'superwoo'); ?></h2>
            <pre style="background:#111827;color:#e5e7eb;max-height:360px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $lines_by_category['diagnostics']) ?: __('No other diagnostic logs available.', 'superwoo')); ?></pre>
            <h2><?php esc_html_e('Fatal error log', 'superwoo'); ?></h2>
            <pre style="background:#3b0d0d;color:#fee2e2;max-height:300px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $fatal_lines) ?: __('No fatal errors recorded.', 'superwoo')); ?></pre>
        </div>
        <?php
    }

    public function clear_logs() {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('superwoo_clear_logs')) {
            wp_die(esc_html__('You are not allowed to clear these logs.', 'superwoo'));
        }
        $path = superwoo_log_file_path();
        if ($path && file_exists($path)) {
            file_put_contents($path, ''); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        $fatal_path = superwoo_fatal_log_file_path();
        if ($fatal_path && file_exists($fatal_path)) {
            file_put_contents($fatal_path, ''); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        wp_safe_redirect(admin_url('admin.php?page=superwoo-logs&cleared=1'));
        exit;
    }

    public function render_settings_page() {
        $settings = superwoo_get_settings();
        include SUPERWOO_PATH . 'admin/views/settings-page.php';
    }

    public function save_settings() {
        if (empty($_POST['superwoo_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['superwoo_settings_nonce'])), 'superwoo_save_settings')) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = [
            'enable_benefits'       => !empty($_POST['enable_benefits']),
            'enable_how_to_use'     => !empty($_POST['enable_how_to_use']),
            'enable_faqs'           => !empty($_POST['enable_faqs']),
            'enable_reviews'        => !empty($_POST['enable_reviews']),
            'enable_variation_cards' => !empty($_POST['enable_variation_cards']),
            'enable_shop_filters'    => !empty($_POST['enable_shop_filters']),
            'enable_shoppable_videos' => !empty($_POST['enable_shoppable_videos']),
            'shoppable_videos_fullscreen' => !empty($_POST['shoppable_videos_fullscreen']),
            'shoppable_videos_autoplay' => !empty($_POST['shoppable_videos_autoplay']),
            'shoppable_videos_muted' => !empty($_POST['shoppable_videos_muted']),
            'shoppable_videos_quick_buy' => !empty($_POST['shoppable_videos_quick_buy']),
            'shoppable_videos_product_page' => !empty($_POST['shoppable_videos_product_page']),
            'shoppable_videos_product_position' => in_array($_POST['shoppable_videos_product_position'] ?? '', ['after_summary', 'after_tabs', 'before_related'], true) ? sanitize_key(wp_unslash($_POST['shoppable_videos_product_position'])) : 'after_summary',
            'shop_filter_show_search' => !empty($_POST['shop_filter_show_search']),
            'shop_filter_show_categories' => !empty($_POST['shop_filter_show_categories']),
            'shop_filter_show_price' => !empty($_POST['shop_filter_show_price']),
            'shop_filter_show_attributes' => !empty($_POST['shop_filter_show_attributes']),
            'shop_filter_show_stock' => !empty($_POST['shop_filter_show_stock']),
            'shop_filter_show_sale' => !empty($_POST['shop_filter_show_sale']),
            'shop_filter_show_rating' => !empty($_POST['shop_filter_show_rating']),
            'shop_filter_show_sort' => !empty($_POST['shop_filter_show_sort']),
            'enable_bundle_offers'  => !empty($_POST['enable_bundle_offers']),
            'enable_cart_drawer'    => !empty($_POST['enable_cart_drawer']),
            'enable_elementor_products_carousel' => !empty($_POST['enable_elementor_products_carousel']),
            'cart_auto_open'        => !empty($_POST['cart_auto_open']),
            'mobile_bottom_nav_hidden_pages' => isset($_POST['mobile_bottom_nav_hidden_pages']) && is_array($_POST['mobile_bottom_nav_hidden_pages']) ? array_values(array_unique(array_filter(array_map('absint', wp_unslash($_POST['mobile_bottom_nav_hidden_pages']))))) : [],
            'cart_drawer_crosssell' => !empty($_POST['cart_drawer_crosssell']),
            'cart_drawer_coupon'    => isset($_POST['cart_drawer_coupon']) && 'disabled' === $_POST['cart_drawer_coupon'] ? 'disabled' : 'checkout_link',
            'enable_add_to_cart_diagnostics' => !empty($_POST['enable_add_to_cart_diagnostics']),
            'enable_logging'        => !empty($_POST['enable_logging']),
            'show_discount_percentage' => !empty($_POST['show_discount_percentage']),
            'header_cart_icon'      => in_array($_POST['header_cart_icon'] ?? '', ['outline-bag', 'filled-bag', 'basket'], true) ? sanitize_key(wp_unslash($_POST['header_cart_icon'])) : 'outline-bag',
            'color_primary'         => $this->sanitize_color('color_primary', '#005b7f'),
            'color_secondary'       => $this->sanitize_color('color_secondary', '#74bf2e'),
            'color_button'          => $this->sanitize_color('color_button', '#005b7f'),
            'color_button_text'     => $this->sanitize_color('color_button_text', '#ffffff'),
            'color_button_hover'    => $this->sanitize_color('color_button_hover', '#004866'),
            'color_cart_icon'       => $this->sanitize_color('color_cart_icon', '#0b3d4d'),
            'color_cart_badge'      => $this->sanitize_color('color_cart_badge', '#ef5b4f'),
            'color_body_text'       => $this->sanitize_color('color_body_text', '#17212b'),
            'color_star'            => $this->sanitize_color('color_star', '#ffb400'),
            'enable_multi_currency' => !empty($_POST['enable_multi_currency']),
            'enabled_currency_codes' => $this->sanitize_currency_codes(isset($_POST['enabled_currency_codes']) ? sanitize_text_field(wp_unslash($_POST['enabled_currency_codes'])) : ''),
            'default_currency'      => $this->sanitize_default_currency(isset($_POST['default_currency']) ? sanitize_text_field(wp_unslash($_POST['default_currency'])) : 'INR', isset($_POST['enabled_currency_codes']) ? sanitize_text_field(wp_unslash($_POST['enabled_currency_codes'])) : ''),
            'currency_auto_detect'  => !empty($_POST['currency_auto_detect']),
            'exchange_rate_api_url' => isset($_POST['exchange_rate_api_url']) ? sanitize_text_field(trim(wp_unslash($_POST['exchange_rate_api_url']))) : '',
            'exchange_rate_api_key' => isset($_POST['exchange_rate_api_key']) ? sanitize_text_field(wp_unslash($_POST['exchange_rate_api_key'])) : '',
            'exchange_rate_cache_minutes' => isset($_POST['exchange_rate_cache_hours']) ? max(1, absint($_POST['exchange_rate_cache_hours'])) * 60 : 720,
            'manual_exchange_rates' => $this->sanitize_manual_rates(isset($_POST['manual_exchange_rates']) ? sanitize_textarea_field(wp_unslash($_POST['manual_exchange_rates'])) : ''),
        ];

        foreach (superwoo_review_color_fields() as $key => $field) {
            $settings[$key] = $this->sanitize_color($key, $field['default']);
        }

        update_option('superwoo_settings', $settings);

        $active_tab = isset($_POST['superwoo_active_tab']) ? sanitize_key(wp_unslash($_POST['superwoo_active_tab'])) : 'general';
        $active_tab = in_array($active_tab, ['general', 'cart', 'appearance', 'currency'], true) ? $active_tab : 'general';

        wp_safe_redirect(add_query_arg(['page' => 'superwoo-settings', 'updated' => 'true', 'tab' => $active_tab], admin_url('admin.php')));
        exit;
    }

    public function enqueue_admin_assets($hook) {
        if (in_array($hook, ['toplevel_page_superwoo-settings', 'superwoo_page_superwoo-health', 'superwoo_page_superwoo-bundle-offers'], true)) {
            wp_enqueue_style('superwoo-admin', SUPERWOO_URL . 'public/css/admin.css', [], SUPERWOO_VERSION);
            if ('toplevel_page_superwoo-settings' === $hook) {
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
            }
        }
    }

    public function enqueue_appearance() {
        $settings = superwoo_get_settings();
        $colors = [
            '--superwoo-primary'     => $settings['color_primary'],
            '--superwoo-secondary'   => $settings['color_secondary'],
            '--superwoo-button'      => $settings['color_button'],
            '--superwoo-button-text' => $settings['color_button_text'],
            '--superwoo-button-hover'=> $settings['color_button_hover'],
            '--superwoo-cart-icon'   => $settings['color_cart_icon'],
            '--superwoo-cart-badge'  => $settings['color_cart_badge'],
            '--superwoo-body-text'   => $settings['color_body_text'],
            '--superwoo-star'        => $settings['color_star'],
        ];
        foreach (superwoo_review_color_fields() as $key => $field) {
            $colors[$field['property']] = $settings[$key];
        }
        $declarations = [];
        foreach ($colors as $property => $color) {
            $clean = sanitize_hex_color($color);
            if ($clean) {
                $declarations[] = $property . ':' . $clean;
            }
        }

        wp_enqueue_style('superwoo-appearance', SUPERWOO_URL . 'public/css/appearance.css', [], SUPERWOO_VERSION . '.' . filemtime(SUPERWOO_PATH . 'public/css/appearance.css'));
        wp_add_inline_style('superwoo-appearance', ':root{' . implode(';', $declarations) . '}');
    }

    public function log_cart_add($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        static $request_add_counts = [];
        $signature = absint($product_id) . ':' . absint($variation_id) . ':' . (string) $cart_item_key;
        $request_add_counts[$signature] = ($request_add_counts[$signature] ?? 0) + 1;

        superwoo_log('Cart item added', ['cart_item_key' => (string) $cart_item_key, 'product_id' => absint($product_id), 'quantity' => absint($quantity), 'variation_id' => absint($variation_id)]);

        $settings = superwoo_get_settings();
        if ($request_add_counts[$signature] < 2 || empty($settings['enable_add_to_cart_diagnostics'])) {
            return;
        }

        $trace = [];
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
            $file = isset($frame['file']) ? wp_normalize_path((string) $frame['file']) : '';
            if ($file && defined('ABSPATH')) {
                $file = str_replace(wp_normalize_path(ABSPATH), '', $file);
            }
            $trace[] = [
                'call' => (string) ($frame['class'] ?? '') . (string) ($frame['type'] ?? '') . (string) ($frame['function'] ?? ''),
                'file' => $file,
                'line' => absint($frame['line'] ?? 0),
            ];
        }

        superwoo_log('Duplicate cart-add call stack', [
            'cart_item_key' => (string) $cart_item_key,
            'product_id' => absint($product_id),
            'variation_id' => absint($variation_id),
            'trace' => $trace,
        ], 'warning');
    }

    public function log_cart_add_validation($passed, $product_id, $quantity, $variation_id = 0, $variation = [], $cart_item_data = []) {
        if (!$passed) {
            superwoo_log('Cart add validation failed', ['product_id' => absint($product_id), 'quantity' => absint($quantity), 'variation_id' => absint($variation_id)], 'warning');
        }
        return $passed;
    }

    public function log_cart_remove($cart_item_key, $cart) {
        superwoo_log('Cart item removed', ['cart_item_key' => (string) $cart_item_key, 'cart_count' => $cart ? $cart->get_cart_contents_count() : 0]);
    }

    public function log_checkout_validation($data, $errors) {
        if ($errors instanceof WP_Error && $errors->has_errors()) {
            superwoo_log('Checkout validation failed', ['error_codes' => $errors->get_error_codes(), 'error_count' => count($errors->get_error_codes())], 'warning', 'orders');
        }
    }

    public function log_checkout_success($order_id, $posted_data, $order) {
        superwoo_log('Checkout order created', ['order_id' => absint($order_id), 'item_count' => $order instanceof WC_Order ? count($order->get_items()) : 0], 'info', 'orders');
    }

    public function log_order_status_change($order_id, $from_status, $to_status, $order = null) {
        superwoo_log('WooCommerce order status changed', [
            'order_id' => absint($order_id),
            'from_status' => sanitize_key((string) $from_status),
            'to_status' => sanitize_key((string) $to_status),
        ], 'info', 'orders');
    }

    public function log_payment_complete($order_id) {
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : false;
        superwoo_log('WooCommerce reported payment complete', [
            'order_id' => absint($order_id),
            'order_status' => $order instanceof WC_Order ? sanitize_key($order->get_status()) : '',
        ], 'info', 'payments');
    }

    public function log_payment_failure($order_id) {
        superwoo_log('WooCommerce order entered failed status', [
            'order_id' => absint($order_id),
            'order_status' => 'failed',
        ], 'warning', 'payments');
    }

    private function sanitize_color($key, $fallback) {
        $value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        return sanitize_hex_color($value) ?: $fallback;
    }



    private function sanitize_currency_codes($value) {
        $codes = is_array($value) ? $value : preg_split('/[\s,]+/', (string) wp_unslash($value));
        $codes[] = 'INR';
        $clean = [];

        foreach ($codes as $code) {
            if (preg_match('/([A-Z]{3})/i', (string) $code, $matches)) {
                $clean[] = strtoupper($matches[1]);
            }
        }

        return array_values(array_unique($clean)) ?: ['INR'];
    }

    private function sanitize_default_currency($value, $enabled_value) {
        $enabled = $this->sanitize_currency_codes($enabled_value);
        $currency = strtoupper(preg_replace('/[^A-Z]/', '', (string) wp_unslash($value)));

        if (3 !== strlen($currency) || !in_array($currency, $enabled, true)) {
            return 'INR';
        }

        return $currency;
    }

    private function sanitize_manual_rates($value) {
        $rates = [];
        $lines = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) wp_unslash($value));

        foreach ($lines as $key => $line) {
            if (is_array($value)) {
                $code = strtoupper(preg_replace('/[^A-Z]/', '', (string) $key));
                $rate = (float) wc_format_decimal($line);
            } else {
                if (!preg_match('/^\s*([A-Z]{3})\s*[=:,]\s*([0-9.]+)\s*$/i', (string) $line, $matches)) {
                    continue;
                }

                $code = strtoupper($matches[1]);
                $rate = (float) wc_format_decimal($matches[2]);
            }

            if (3 === strlen($code) && 'INR' !== $code && $rate > 0) {
                $rates[$code] = $rate;
            }
        }

        return $rates;
    }
}

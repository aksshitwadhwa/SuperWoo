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
        $lines = $contents ? array_slice(array_filter(explode("\n", $contents)), -300) : [];
        ?>
        <div class="wrap superwoo-admin-page">
            <h1><?php esc_html_e('SuperWoo Logs', 'superwoo'); ?></h1>
            <p><?php esc_html_e('Recent diagnostic events from SuperWoo. Enable logging from SuperWoo → Settings → Cart.', 'superwoo'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="superwoo_clear_logs">
                <?php wp_nonce_field('superwoo_clear_logs'); ?>
                <?php submit_button(__('Clear Logs', 'superwoo'), 'delete', 'submit', false); ?>
            </form>
            <pre style="background:#111827;color:#e5e7eb;max-height:650px;overflow:auto;padding:18px;white-space:pre-wrap;"><?php echo esc_html(implode("\n", $lines) ?: __('No logs available.', 'superwoo')); ?></pre>
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
        $declarations = [];
        foreach ($colors as $property => $color) {
            $clean = sanitize_hex_color($color);
            if ($clean) {
                $declarations[] = $property . ':' . $clean;
            }
        }

        wp_enqueue_style('superwoo-appearance', SUPERWOO_URL . 'public/css/appearance.css', [], SUPERWOO_VERSION);
        wp_add_inline_style('superwoo-appearance', ':root{' . implode(';', $declarations) . '}');
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

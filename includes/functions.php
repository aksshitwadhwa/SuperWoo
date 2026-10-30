<?php
defined('ABSPATH') || exit;

function superwoo_is_woocommerce_active() {
    return class_exists('WooCommerce') && function_exists('WC');
}

/**
 * Return the initialized WooCommerce cart for a frontend cart request.
 *
 * WooCommerce intentionally does not initialize cart/session objects for every
 * request type (for example, background and some API requests). Callers that
 * only render cart UI must skip rendering when it is unavailable.
 *
 * @return WC_Cart|null
 */
function superwoo_get_cart() {
    if (!superwoo_is_woocommerce_active()) {
        return null;
    }

    $woocommerce = WC();
    if (!$woocommerce || !isset($woocommerce->cart) || !($woocommerce->cart instanceof WC_Cart)) {
        return null;
    }

    return $woocommerce->cart;
}

function superwoo_template($template, $args = []) {
    $path = SUPERWOO_PATH . 'templates/' . ltrim($template, '/');

    if (!file_exists($path)) {
        return '';
    }

    if (!empty($args) && is_array($args)) {
        extract($args, EXTR_SKIP);
    }

    ob_start();
    include $path;
    return ob_get_clean();
}

/** Review appearance defaults shared by settings, sanitization, and CSS output. */
function superwoo_review_color_fields() {
    return [
        'review_color_accent' => ['label' => __('Accent / rating bars', 'superwoo'), 'default' => '#2d8619', 'property' => '--superwoo-review-accent'],
        'review_color_highlight' => ['label' => __('Results heading', 'superwoo'), 'default' => '#5d9f80', 'property' => '--superwoo-review-highlight'],
        'review_color_button' => ['label' => __('Button gradient start', 'superwoo'), 'default' => '#28830f', 'property' => '--superwoo-review-button'],
        'review_color_button_end' => ['label' => __('Button gradient end', 'superwoo'), 'default' => '#104c1e', 'property' => '--superwoo-review-button-end'],
        'review_color_button_hover' => ['label' => __('Button hover', 'superwoo'), 'default' => '#1b6313', 'property' => '--superwoo-review-button-hover'],
        'review_color_button_text' => ['label' => __('Button text', 'superwoo'), 'default' => '#ffffff', 'property' => '--superwoo-review-button-text'],
        'review_color_heading' => ['label' => __('Headings / customer names', 'superwoo'), 'default' => '#111827', 'property' => '--superwoo-review-heading'],
        'review_color_text' => ['label' => __('Review text', 'superwoo'), 'default' => '#293244', 'property' => '--superwoo-review-text'],
        'review_color_muted' => ['label' => __('Secondary text', 'superwoo'), 'default' => '#717c92', 'property' => '--superwoo-review-muted'],
        'review_color_background' => ['label' => __('Section background', 'superwoo'), 'default' => '#f7faf8', 'property' => '--superwoo-review-background'],
        'review_color_surface' => ['label' => __('Cards / panels', 'superwoo'), 'default' => '#ffffff', 'property' => '--superwoo-review-surface'],
        'review_color_soft' => ['label' => __('Soft accents / badges', 'superwoo'), 'default' => '#f0f7ef', 'property' => '--superwoo-review-soft'],
        'review_color_border' => ['label' => __('Borders / empty rating bars', 'superwoo'), 'default' => '#e3e8e9', 'property' => '--superwoo-review-border'],
    ];
}

function superwoo_get_settings() {
    $currencylayer_api_url = 'https://api.currencylayer.com/live?access_key={api_key}&source={base}&currencies={symbols}';
    $defaults = [
        'enable_benefits'       => true,
        'enable_how_to_use'     => true,
        'enable_faqs'           => true,
        'enable_reviews'        => true,
        'enable_variation_cards' => true,
        'enable_shop_filters'    => true,
        'enable_shoppable_videos' => false,
        'shoppable_videos_fullscreen' => true,
        'shoppable_videos_autoplay' => true,
        'shoppable_videos_muted' => true,
        'shoppable_videos_quick_buy' => false,
        'shoppable_videos_product_page' => false,
        'shoppable_videos_product_position' => 'after_summary',
        'shop_filter_show_search' => true,
        'shop_filter_show_categories' => true,
        'shop_filter_show_price' => true,
        'shop_filter_show_attributes' => true,
        'shop_filter_show_stock' => true,
        'shop_filter_show_sale' => true,
        'shop_filter_show_rating' => true,
        'shop_filter_show_sort' => true,
        'enable_bundle_offers'  => true,
        'enable_cart_drawer'    => true,
        'enable_cart_recovery'  => true,
        'cart_recovery_abandonment_minutes' => 30,
        'cart_recovery_token_days' => 7,
        'enable_elementor_products_carousel' => false,
        'cart_auto_open'        => true,
        'mobile_bottom_nav_hidden_pages' => [],
        'cart_drawer_crosssell' => true,
        'cart_drawer_coupon'    => 'inline',
        'cart_recommendation_heading' => __('Frequently bought together', 'superwoo'),
        'cart_trust_enabled' => false,
        'cart_trust_items' => [],
        'enable_add_to_cart_diagnostics' => false,
        'enable_logging'        => false,
        // Meta tracking is disabled by default and is browser-only when opted in.
        'enable_meta_tracking' => false,
        'meta_tracking_kill_switch' => false,
        'meta_tracking_diagnostics' => false,
        'meta_primary_pixel_id' => '',
        'show_discount_percentage' => false,
        'header_cart_icon'      => 'outline-bag',
        'color_primary'         => '#005b7f',
        'color_secondary'       => '#74bf2e',
        'color_button'          => '#005b7f',
        'color_button_text'     => '#ffffff',
        'color_button_hover'    => '#004866',
        'color_cart_icon'       => '#0b3d4d',
        'color_cart_badge'      => '#ef5b4f',
        'color_body_text'       => '#17212b',
        'color_star'            => '#ffb400',
        'enable_multi_currency' => false,
        'enabled_currency_codes' => ['INR', 'USD', 'EUR'],
        'default_currency'      => 'INR',
        'currency_auto_detect'  => true,
        'exchange_rate_api_url' => $currencylayer_api_url,
        'exchange_rate_api_key' => '',
        'exchange_rate_cache_minutes' => 720,
        'manual_exchange_rates' => [],
    ];

    foreach (superwoo_review_color_fields() as $key => $field) {
        $defaults[$key] = $field['default'];
    }

    $settings = get_option('superwoo_settings', []);
    $settings = wp_parse_args(is_array($settings) ? $settings : [], $defaults);
    if (empty($settings['exchange_rate_api_url']) || false !== strpos((string) $settings['exchange_rate_api_url'], 'api.frankfurter.dev')) {
        $settings['exchange_rate_api_url'] = $currencylayer_api_url;
    }

    return $settings;
}

function superwoo_log($message, $context = [], $level = 'info', $category = 'diagnostics') {
    if (empty(superwoo_get_settings()['enable_logging'])) {
        return;
    }

    $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
    $level = in_array($level, $levels, true) ? $level : 'info';
    $categories = ['diagnostics', 'orders', 'payments', 'errors'];
    $category = in_array($category, $categories, true) ? $category : 'diagnostics';
    if ('diagnostics' === $category && in_array($level, ['emergency', 'alert', 'critical', 'error', 'warning'], true)) {
        $category = 'errors';
    }
    $context = superwoo_sanitize_log_context(is_array($context) ? $context : []);
    $context['log_category'] = $category;
    $context['plugin_version'] = defined('SUPERWOO_VERSION') ? SUPERWOO_VERSION : '';
    $context['request_id'] = superwoo_log_request_id();
    $context['request_type'] = wp_doing_ajax() ? 'ajax' : (is_admin() ? 'admin' : 'frontend');
    $line = '[' . current_time('mysql') . '] [' . strtoupper($level) . '] ' . (string) $message . ' ' . wp_json_encode($context) . "\n";

    $log_file = superwoo_log_file_path();
    if ($log_file && superwoo_protect_log_directory(dirname($log_file))) {
        if (file_exists($log_file) && filesize($log_file) > 2 * MB_IN_BYTES) {
            $recent = file_get_contents($log_file, false, null, max(0, filesize($log_file) - MB_IN_BYTES));
            file_put_contents($log_file, $recent ?: ''); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    }

    if (function_exists('wc_get_logger')) {
        wc_get_logger()->log($level, (string) $message, ['source' => 'superwoo', 'context' => $context]);
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback when WooCommerce logging is unavailable.
        error_log('SuperWoo [' . strtoupper($level) . '] ' . $message . ' ' . wp_json_encode($context));
    }
}

function superwoo_log_request_id() {
    static $request_id = null;
    if (null === $request_id) {
        $request_id = substr(wp_generate_uuid4(), 0, 8);
    }
    return $request_id;
}

function superwoo_sanitize_log_context($context) {
    $blocked = ['address', 'authorization', 'card', 'cookie', 'cvv', 'email', 'nonce', 'password', 'payment', 'phone', 'token'];
    $clean = [];
    foreach ((array) $context as $key => $value) {
        $key = is_int($key) ? 'item_' . $key : sanitize_key((string) $key);
        if (!$key || in_array($key, $blocked, true) || preg_match('/(address|authorization|card|cookie|cvv|email|nonce|password|payment|phone|token)/', $key)) {
            continue;
        }
        if (is_array($value)) {
            $clean[$key] = superwoo_sanitize_log_context($value);
        } elseif (is_scalar($value) || null === $value) {
            $clean[$key] = is_string($value) ? wp_strip_all_tags($value) : $value;
        }
    }
    return $clean;
}

function superwoo_log_file_path() {
    $uploads = wp_upload_dir();
    return empty($uploads['error']) && !empty($uploads['basedir']) ? trailingslashit($uploads['basedir']) . 'superwoo-logs/superwoo.log' : '';
}

function superwoo_fatal_log_file_path() {
    $log_file = superwoo_log_file_path();
    if (!$log_file) {
        return '';
    }

    $fatal_file = trailingslashit(dirname($log_file)) . 'superwoo-fatal.log';
    $legacy_file = defined('WP_CONTENT_DIR') ? trailingslashit(WP_CONTENT_DIR) . 'superwoo-fatal.log' : '';
    if ($legacy_file && $legacy_file !== $fatal_file && file_exists($legacy_file) && superwoo_protect_log_directory(dirname($fatal_file)) && !file_exists($fatal_file)) {
        // Relocate the old web-root diagnostic file into the protected log directory.
        if (!@rename($legacy_file, $fatal_file) && @copy($legacy_file, $fatal_file)) {
            @unlink($legacy_file);
        }
    }

    return $fatal_file;
}

/** Best-effort per-client throttling for public, non-commerce tracking endpoints. */
function superwoo_rate_limit($bucket, $limit, $window = MINUTE_IN_SECONDS) {
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])
        ? trim($_SERVER['REMOTE_ADDR'])
        : '';
    $fingerprint = hash_hmac('sha256', $remote_addr ?: 'unknown', wp_salt('auth'));
    $transient_key = 'superwoo_rl_' . sanitize_key($bucket) . '_' . substr($fingerprint, 0, 32);
    $count = (int) get_transient($transient_key);

    if ($count >= max(1, absint($limit))) {
        return false;
    }

    set_transient($transient_key, $count + 1, max(1, absint($window)));
    return true;
}

/**
 * Create common web-server access blocks beside diagnostic files.
 * Nginx sites must also deny this directory in server configuration because
 * Nginx does not read .htaccess files.
 */
function superwoo_protect_log_directory($directory) {
    if (!$directory || !wp_mkdir_p($directory)) {
        return false;
    }

    $protections = [
        'index.php' => "<?php\n// Silence is golden.\n",
        '.htaccess' => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
        'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
    ];

    foreach ($protections as $filename => $contents) {
        $path = trailingslashit($directory) . $filename;
        if (!file_exists($path)) {
            file_put_contents($path, $contents, LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }

    return true;
}

function superwoo_health_report() {
    $settings = superwoo_get_settings();
    $log_path = superwoo_log_file_path();
    $fatal_path = superwoo_fatal_log_file_path();
    $report = [
        'environment' => [
            ['label' => __('SuperWoo version', 'superwoo'), 'value' => defined('SUPERWOO_VERSION') ? SUPERWOO_VERSION : __('Unknown', 'superwoo'), 'status' => 'ok'],
            ['label' => __('WordPress version', 'superwoo'), 'value' => get_bloginfo('version'), 'status' => 'ok'],
            ['label' => __('WooCommerce', 'superwoo'), 'value' => superwoo_is_woocommerce_active() ? (defined('WC_VERSION') ? WC_VERSION : __('Active', 'superwoo')) : __('Not available', 'superwoo'), 'status' => superwoo_is_woocommerce_active() ? 'ok' : 'warning'],
            ['label' => __('PHP version', 'superwoo'), 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'warning'],
            ['label' => __('Plugin folder', 'superwoo'), 'value' => defined('SUPERWOO_FILE') ? plugin_basename(SUPERWOO_FILE) : __('Unknown', 'superwoo'), 'status' => (defined('SUPERWOO_FILE') && 'superwoo/superwoo.php' === strtolower(plugin_basename(SUPERWOO_FILE))) ? 'ok' : 'warning'],
        ],
        'diagnostics' => [
            ['label' => __('SuperWoo logging', 'superwoo'), 'value' => !empty($settings['enable_logging']) ? __('Enabled', 'superwoo') : __('Disabled', 'superwoo'), 'status' => !empty($settings['enable_logging']) ? 'ok' : 'neutral'],
            ['label' => __('Application log', 'superwoo'), 'value' => $log_path ? ($log_path . (file_exists($log_path) ? ' (' . size_format(filesize($log_path)) . ')' : '')) : __('Unavailable', 'superwoo'), 'status' => ($log_path && is_dir(dirname($log_path)) && wp_is_writable(dirname($log_path))) ? 'ok' : 'warning'],
            ['label' => __('Fatal error log', 'superwoo'), 'value' => $fatal_path . (file_exists($fatal_path) ? ' (' . size_format(filesize($fatal_path)) . ')' : ''), 'status' => file_exists($fatal_path) ? 'warning' : 'neutral'],
            ['label' => __('WordPress.org updater', 'superwoo'), 'value' => __('Provided by WordPress.org', 'superwoo'), 'status' => 'ok'],
        ],
        'cart' => [],
    ];

    $cart = superwoo_get_cart();
    if (!$cart) {
        $report['cart'][] = ['label' => __('Cart context', 'superwoo'), 'value' => __('Cart is not initialized in this admin request.', 'superwoo'), 'status' => 'neutral'];
        return $report;
    }

    foreach ($cart->get_cart() as $key => $item) {
        $product = isset($item['data']) && $item['data'] instanceof WC_Product ? $item['data'] : null;
        $catalog_product = $product ? wc_get_product($product->get_id()) : null;
        $cart_price = $product ? (float) $product->get_price() : 0;
        $catalog_price = $catalog_product ? (float) $catalog_product->get_price('edit') : 0;
        $status = ($catalog_price > 0 && abs($cart_price - $catalog_price) > 0.01) ? 'warning' : 'ok';
        /* translators: 1: product ID, 2: quantity. */
        $label = sprintf(__('Product #%1$s × %2$s', 'superwoo'), $product ? $product->get_id() : 'n/a', isset($item['quantity']) ? (int) $item['quantity'] : 0);
        /* translators: 1: catalog price, 2: cart price, 3: line total. */
        $value = sprintf(__('Catalog %1$s / Cart %2$s / Line %3$s', 'superwoo'), wc_price($catalog_price), wc_price($cart_price), wc_price((float) ($item['line_total'] ?? 0)));
        $report['cart'][] = ['label' => $label, 'value' => $value, 'status' => $status];
    }
    return $report;
}

function superwoo_format_selected_currency_amount($base_inr_amount) {
    if (function_exists('superwoo_currency') && superwoo_currency()->is_enabled()) {
        return superwoo_currency()->format_amount(superwoo_currency()->convert_amount($base_inr_amount));
    }

    return wc_price($base_inr_amount);
}

function superwoo_cart_count() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return 0;
    }

    return (int) $cart->get_cart_contents_count();
}


function superwoo_cart_drawer_fragments($calculate_totals = true) {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return [];
    }

    if ($calculate_totals) {
        $cart->calculate_totals();
    }
    if (WC()->session) {
        $cart->set_session();
        WC()->session->set('cart', $cart->get_cart_for_session());
    }

    $cart_drawer = new SuperWoo_Cart_Drawer();

    return [
        '.superwoo-cart-drawer__inner' => superwoo_cart_drawer_inner_fragment_html($cart_drawer),
        '.superwoo-cart-offer-notices' => superwoo_cart_offer_notices_html(),
        '.superwoo-cart-count'         => '<span class="superwoo-cart-count">' . esc_html(superwoo_cart_count()) . '</span>',
        '.superwoo-cart-total'         => superwoo_cart_total_html(),
        '.superwoo-cart-primary'       => superwoo_cart_primary_button_html(),
    ];
}

function superwoo_cart_drawer_inner_fragment_html($cart_drawer = null, $include_cross_sells = true) {
    if (!$cart_drawer instanceof SuperWoo_Cart_Drawer) {
        $cart_drawer = new SuperWoo_Cart_Drawer();
    }

    return '<div class="superwoo-cart-drawer__inner">' . $cart_drawer->get_inner_html($include_cross_sells) . '</div>';
}

function superwoo_cart_offer_notices_html() {
    $cart = superwoo_get_cart();
    $offer_html = class_exists('SuperWoo_Bundle_Offers') && $cart
        ? (new SuperWoo_Bundle_Offers())->get_notices_html() : '';
    $delivery = class_exists('SuperWoo_Cart_Drawer') && $cart
        ? (new SuperWoo_Cart_Drawer())->get_delivery_progress() : [];

    ob_start();
    ?>
    <div class="superwoo-cart-offer-notices">
        <?php
        echo $delivery
            ? superwoo_template('cart-progress.php', ['delivery' => $delivery, 'offer_html' => $offer_html])
            : $offer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?>
    </div>
    <?php
    return ob_get_clean();
}

function superwoo_cart_offer_state() {
    $cart = superwoo_get_cart();

    if (!class_exists('SuperWoo_Bundle_Offers') || !$cart) {
        return [
            'discounts' => [],
            'gifts'     => [],
        ];
    }

    return (new SuperWoo_Bundle_Offers())->get_cart_offer_state($cart);
}

function superwoo_cart_total_html() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return '';
    }

    $summary = superwoo_cart_summary_amounts($cart);

    ob_start();
    ?>
    <div class="superwoo-cart-total">
        <div class="superwoo-cart-total__row">
            <span><?php esc_html_e('Subtotal', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post(wc_price($summary['subtotal'])); ?></strong>
        </div>
        <div class="superwoo-cart-total__row superwoo-cart-total__row--discount">
            <span><?php esc_html_e('Discount', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post(wc_price(-$summary['discount'])); ?></strong>
        </div>
        <div class="superwoo-cart-total__row">
            <span><?php esc_html_e('Shipping', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post($cart->get_cart_shipping_total()); ?></strong>
        </div>
        <?php if ($summary['fees'] > 0) : ?>
            <div class="superwoo-cart-total__row">
                <span><?php esc_html_e('Fees', 'superwoo'); ?></span>
                <strong><?php echo wp_kses_post(wc_price($summary['fees'])); ?></strong>
            </div>
        <?php endif; ?>
        <?php if ($summary['tax'] > 0) : ?>
            <div class="superwoo-cart-total__row">
                <span><?php esc_html_e('Tax', 'superwoo'); ?></span>
                <strong><?php echo wp_kses_post(wc_price($summary['tax'])); ?></strong>
            </div>
        <?php endif; ?>
        <div class="superwoo-cart-total__row superwoo-cart-total__row--grand-total">
            <span><?php esc_html_e('Total', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post(wc_price((float) $cart->get_total('edit'))); ?></strong>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** Return the cart item's price before a SuperWoo offer, in the display currency. */
function superwoo_cart_item_pre_offer_price($cart_item) {
    $product = $cart_item['data'] ?? null;
    if (!$product instanceof WC_Product) {
        return 0.0;
    }

    // The currency integration records the catalog price before conversion.
    // Bundle offers also save their starting price before changing set_price().
    foreach (['_superwoo_base_inr_price', '_superwoo_offer_base_price'] as $key) {
        if (isset($cart_item[$key])) {
            $price = (float) $cart_item[$key];
            if (function_exists('superwoo_currency') && superwoo_currency()->is_enabled()) {
                $price = superwoo_currency()->convert_product_price($product, $price);
            }
            return $price;
        }
    }

    return (float) $product->get_price('edit');
}

/** Return one cart row's subtotal, including its free-gift value when present. */
function superwoo_cart_item_subtotal_amount($item, $including_tax = false) {
    $product = $item['data'] ?? null;
    if (!$product instanceof WC_Product) {
        return 0.0;
    }

    $quantity = max(1, (int) ($item['quantity'] ?? 1));
    if (!empty($item['superwoo_free_gift'])) {
        $base_price = $product->get_regular_price('edit');
        if ('' === $base_price || null === $base_price) {
            $base_price = $product->get_price('edit');
        }
        $base_price = (float) $base_price;
        if (function_exists('superwoo_currency') && superwoo_currency()->is_enabled()) {
            $base_price = superwoo_currency()->convert_product_price($product, $base_price);
        }
    } else {
        $base_price = superwoo_cart_item_pre_offer_price($item);
    }

    $base_display = (float) wc_get_price_to_display($product, ['price' => $base_price, 'qty' => $quantity]);
    $payable_line = array_key_exists('line_total', $item)
        ? (float) $item['line_total'] + ($including_tax ? (float) ($item['line_tax'] ?? 0) : 0.0)
        : (float) wc_get_price_to_display($product, ['price' => $product->get_price('edit'), 'qty' => $quantity]);

    // A custom cart price can exceed the saved base. Keep the row subtotal
    // at least as high as its payable amount, without inventing a discount.
    return max($base_display, $payable_line);
}

/**
 * Use the price before the cart offer as the subtotal. This is the catalog
 * sale price when a product is on sale, so the discount row measures the
 * additional cart savings rather than counting the catalog sale twice.
 * Free gifts keep their regular value in the subtotal.
 */
function superwoo_cart_summary_amounts($cart) {
    $including_tax = $cart->display_prices_including_tax();
    $subtotal = 0.0;

    foreach ($cart->get_cart() as $item) {
        $product = $item['data'] ?? null;
        if (!$product instanceof WC_Product) {
            continue;
        }

        $subtotal += superwoo_cart_item_subtotal_amount($item, $including_tax);
    }

    $payable_merchandise = (float) $cart->get_cart_contents_total()
        + ($including_tax ? (float) $cart->get_cart_contents_tax() : 0.0);
    $subtotal = max($subtotal, $payable_merchandise);
    $discount = max(0.0, $subtotal - $payable_merchandise);

    $fees = (float) $cart->get_fee_total() + ($including_tax ? (float) $cart->get_fee_tax() : 0.0);
    if ($fees < 0) {
        $discount += -$fees;
        $fees = 0.0;
    }

    return [
        'subtotal' => round($subtotal, wc_get_price_decimals()),
        'discount' => round($discount, wc_get_price_decimals()),
        'fees' => round($fees, wc_get_price_decimals()),
        'tax' => $including_tax ? 0.0 : round((float) $cart->get_total_tax(), wc_get_price_decimals()),
    ];
}

function superwoo_razorpay_magic_checkout_available() {
    $razorpay_user = wp_get_current_user();
    $test_mode_allowed = !function_exists('isTestModeEnabled')
        || !isTestModeEnabled()
        || current_user_can('administrator')
        || ($razorpay_user->exists() && preg_match('/@razorpay\.com$/i', $razorpay_user->user_email));

    return $test_mode_allowed
        && function_exists('is1ccEnabled')
        && function_exists('isMiniCartCheckoutEnabled')
        && is1ccEnabled()
        && isMiniCartCheckoutEnabled();
}

function superwoo_cart_primary_button_html() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return '';
    }

    ob_start();
    if ($cart->is_empty()) :
        ?>
        <a class="superwoo-cart-primary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
            <?php esc_html_e('Continue Shopping', 'superwoo'); ?>
        </a>
        <?php
    else :
        $razorpay_magic_checkout = superwoo_razorpay_magic_checkout_available();
        ?>
        <?php if ($razorpay_magic_checkout) : ?>
        <button id="btn-1cc-mini-cart" class="superwoo-cart-primary checkout wc-forward" type="button">
        <?php else : ?>
        <a class="superwoo-cart-primary checkout wc-forward" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
        <?php endif; ?>
            <span class="superwoo-cart-primary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M17 9V7A5 5 0 0 0 7 7v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1ZM9 7a3 3 0 0 1 6 0v2H9V7Z"/></svg>
            </span>
            <span>
                <?php
                printf(
                    /* translators: %s: formatted WooCommerce cart grand total. */
                    esc_html__('Proceed to Checkout · %s', 'superwoo'),
                    esc_html(wp_strip_all_tags(wc_price((float) $cart->get_total('edit'))))
                );
                ?>
            </span>
        <?php if ($razorpay_magic_checkout) : ?>
        </button>
        <?php else : ?>
        </a>
        <?php endif; ?>
        <?php
    endif;

    return ob_get_clean();
}

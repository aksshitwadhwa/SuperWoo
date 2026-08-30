<?php
/**
 * Plugin Name: SuperWoo
 * Description: WooCommerce product benefits, how-to content, FAQs, modern reviews, offers, and AJAX cart drawer.
 * Version: 1.0.162
 * Author: Aksshit Wadhwa
 * Author URI: https://digtize.com/
 * License: GPLv2 or later
 * Update URI: https://github.com/aksshitwadhwa/SuperWoo
 * Text Domain: superwoo
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

defined('ABSPATH') || exit;

// Prevent a fatal "Cannot redeclare" error when WordPress has more than one
// extracted copy of SuperWoo (for example, superwoo and SuperWoo-3). This can
// happen after manually uploading the same ZIP multiple times. The first copy
// loaded remains active and later duplicate copies stop before declaring any
// constants, functions, or classes.
if (defined('SUPERWOO_FILE')) {
    return;
}

// Capture shutdown-level PHP errors even when WordPress cannot finish loading
// its normal debug logger. This file is intentionally separate from the
// optional SuperWoo activity log so activation failures are always recorded.
register_shutdown_function(static function () {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    $file = isset($error['file']) ? (string) $error['file'] : '';
    if (false === stripos($file, 'superwoo')) {
        return;
    }

    $line = sprintf(
        "[%s] %s in %s:%d\n",
        gmdate('Y-m-d H:i:s') . ' UTC',
        isset($error['message']) ? (string) $error['message'] : 'Unknown fatal error',
        $file,
        isset($error['line']) ? (int) $error['line'] : 0
    );
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Emergency fallback.
    error_log('SuperWoo fatal: ' . trim($line));

    if (defined('WP_CONTENT_DIR') && is_dir(WP_CONTENT_DIR) && wp_is_writable(WP_CONTENT_DIR)) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Emergency fallback log.
        error_log($line, 3, WP_CONTENT_DIR . '/superwoo-fatal.log');
    }
});

define('SUPERWOO_VERSION', '1.0.162');
define('SUPERWOO_FILE', __FILE__);
define('SUPERWOO_PATH', plugin_dir_path(__FILE__));
define('SUPERWOO_URL', plugin_dir_url(__FILE__));

require_once SUPERWOO_PATH . 'includes/functions.php';
require_once SUPERWOO_PATH . 'includes/class-currency.php';
require_once SUPERWOO_PATH . 'includes/class-discount-percentage.php';
require_once SUPERWOO_PATH . 'includes/class-woocommerce-guard.php';
require_once SUPERWOO_PATH . 'includes/class-benefit-taxonomy.php';
require_once SUPERWOO_PATH . 'includes/class-product-meta.php';
require_once SUPERWOO_PATH . 'includes/class-product-reviews.php';
require_once SUPERWOO_PATH . 'includes/class-variation-cards.php';
require_once SUPERWOO_PATH . 'includes/class-shortcodes.php';
require_once SUPERWOO_PATH . 'includes/class-bundle-offers.php';
require_once SUPERWOO_PATH . 'includes/class-cart-drawer.php';
require_once SUPERWOO_PATH . 'includes/class-elementor-dynamic-tags.php';
// The carousel module is optional; never let a missing optional file prevent
// the core plugin (admin menu, cart drawer, and product hooks) from loading.
$superwoo_carousel_file = SUPERWOO_PATH . 'includes/class-elementor-products-carousel.php';
if (file_exists($superwoo_carousel_file)) {
    require_once $superwoo_carousel_file;
}
require_once SUPERWOO_PATH . 'includes/class-plugin.php';
require_once SUPERWOO_PATH . 'includes/class-github-updater.php';
(new SuperWoo_GitHub_Updater())->hooks();
register_activation_hook(__FILE__, ['SuperWoo_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SuperWoo_Plugin', 'deactivate']);

function superwoo_boot_plugin() {
    try {
        SuperWoo_Plugin::instance();
    } catch (Throwable $error) {
        // Keep a plugin runtime error from taking down the entire WordPress
        // request. The error contains the file and line needed to fix it.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Emergency fallback log.
        error_log('SuperWoo boot failure: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
    }
}

add_action('plugins_loaded', 'superwoo_boot_plugin');

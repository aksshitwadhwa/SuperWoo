<?php
/**
 * Plugin Name: SuperWoo
 * Description: WooCommerce product benefits, how-to content, FAQs, modern reviews, offers, and AJAX cart drawer.
 * Version: 1.0.139
 * Author: Aksshit Wadhwa
 * Author URI: https://digtize.com/
 * Update URI: https://github.com/aksshitwadhwa/SuperWoo
 * Text Domain: superwoo
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

defined('ABSPATH') || exit;

define('SUPERWOO_VERSION', '1.0.139');
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

$superwoo_github_updater = new SuperWoo_GitHub_Updater();
$superwoo_github_updater->hooks();

register_activation_hook(__FILE__, ['SuperWoo_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SuperWoo_Plugin', 'deactivate']);

add_action('plugins_loaded', ['SuperWoo_Plugin', 'instance']);

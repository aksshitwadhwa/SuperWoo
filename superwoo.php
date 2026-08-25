<?php
/**
 * Plugin Name: SuperWoo
 * Description: WooCommerce product benefits, how-to content, FAQs, modern reviews, offers, and AJAX cart drawer.
 * Version: 1.0.115
 * Author: Rakesh Raushan
 * Text Domain: superwoo
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

defined('ABSPATH') || exit;

define('SUPERWOO_VERSION', '1.0.115');
define('SUPERWOO_FILE', __FILE__);
define('SUPERWOO_PATH', plugin_dir_path(__FILE__));
define('SUPERWOO_URL', plugin_dir_url(__FILE__));

require_once SUPERWOO_PATH . 'includes/functions.php';
require_once SUPERWOO_PATH . 'includes/class-currency.php';
require_once SUPERWOO_PATH . 'includes/class-woocommerce-guard.php';
require_once SUPERWOO_PATH . 'includes/class-benefit-taxonomy.php';
require_once SUPERWOO_PATH . 'includes/class-product-meta.php';
require_once SUPERWOO_PATH . 'includes/class-product-reviews.php';
require_once SUPERWOO_PATH . 'includes/class-variation-cards.php';
require_once SUPERWOO_PATH . 'includes/class-shortcodes.php';
require_once SUPERWOO_PATH . 'includes/class-bundle-offers.php';
require_once SUPERWOO_PATH . 'includes/class-cart-drawer.php';
require_once SUPERWOO_PATH . 'includes/class-elementor-dynamic-tags.php';
require_once SUPERWOO_PATH . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['SuperWoo_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SuperWoo_Plugin', 'deactivate']);

add_action('plugins_loaded', ['SuperWoo_Plugin', 'instance']);

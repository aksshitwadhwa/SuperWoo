<?php
defined('ABSPATH') || exit;

class SuperWoo_WooCommerce_Guard {
    public function is_available() {
        return superwoo_is_woocommerce_active();
    }

    public function hooks() {
        add_action('admin_notices', [$this, 'admin_notice']);
    }

    public function admin_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('SuperWoo requires WooCommerce to enable product and cart features.', 'superwoo'); ?></p>
        </div>
        <?php
    }
}

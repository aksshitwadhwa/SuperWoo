<?php
defined('ABSPATH') || exit;

class SuperWoo_Elementor_Cart_Drawer_Trigger_Tag extends \Elementor\Core\DynamicTags\Tag {
    public function get_name() {
        return 'superwoo-cart-drawer-trigger';
    }

    public function get_title() {
        return __('SuperWoo Cart Drawer Trigger', 'superwoo');
    }

    public function get_group() {
        return 'superwoo';
    }

    public function get_categories() {
        if (class_exists('\Elementor\Modules\DynamicTags\Module')) {
            return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY];
        }

        return ['url'];
    }

    public function get_value(array $options = []) {
        return '#superwoo-cart';
    }

    public function render() {
        echo esc_url($this->get_value()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

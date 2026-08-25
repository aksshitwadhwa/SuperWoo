<?php
defined('ABSPATH') || exit;

class SuperWoo_Elementor_Dynamic_Tags {
    public function hooks() {
        add_action('elementor/dynamic_tags/register', [$this, 'register']);
    }

    public function register($dynamic_tags) {
        if (!class_exists('\Elementor\Core\DynamicTags\Tag')) {
            return;
        }

        if (method_exists($dynamic_tags, 'register_group')) {
            $dynamic_tags->register_group('superwoo', [
                'title' => __('SuperWoo', 'superwoo'),
            ]);
        }

        if (!class_exists('SuperWoo_Elementor_Cart_Drawer_Trigger_Tag')) {
            require_once SUPERWOO_PATH . 'includes/class-elementor-cart-drawer-trigger-tag.php';
        }

        if (method_exists($dynamic_tags, 'register')) {
            $dynamic_tags->register(new SuperWoo_Elementor_Cart_Drawer_Trigger_Tag());
            return;
        }

        if (method_exists($dynamic_tags, 'register_tag')) {
            $dynamic_tags->register_tag('SuperWoo_Elementor_Cart_Drawer_Trigger_Tag');
        }
    }
}

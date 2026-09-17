<?php
defined('ABSPATH') || exit;

class SuperWoo_Elementor_Product_Reviews_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'superwoo-product-reviews';
    }

    public function get_title() {
        return __('SuperWoo Product Reviews', 'superwoo');
    }

    public function get_icon() {
        return 'eicon-testimonial';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_style_depends() {
        return ['superwoo-reviews'];
    }

    public function get_script_depends() {
        return ['superwoo-reviews'];
    }

    protected function register_controls() {
        $this->start_controls_section('superwoo_reviews_content', [
            'label' => __('Product Reviews', 'superwoo'),
        ]);

        $this->add_control('product_id', [
            'label'       => __('Product ID', 'superwoo'),
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'min'         => 0,
            'default'     => 0,
            'description' => __('Leave empty on a product template to use the current product.', 'superwoo'),
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $product_id = !empty($settings['product_id']) ? absint($settings['product_id']) : 0;

        if (!$product_id && is_singular('product')) {
            $product_id = get_the_ID();
        }

        if (!$product_id && function_exists('wc_get_product')) {
            global $product;
            if ($product instanceof WC_Product) {
                $product_id = $product->get_id();
            }
        }

        if (!$product_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="superwoo-elementor-reviews-placeholder">' . esc_html__('Choose a product ID, or preview this widget on a product template.', 'superwoo') . '</div>';
            }
            return;
        }

        echo do_shortcode('[superwoo_product_reviews id="' . absint($product_id) . '"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

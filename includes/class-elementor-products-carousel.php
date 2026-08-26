<?php
defined('ABSPATH') || exit;

/** Extends Elementor Pro's Products widget with optional carousel controls. */
class SuperWoo_Elementor_Products_Carousel {
    const WIDGET_NAME = 'woocommerce-products';

    public function hooks() {
        add_action('elementor/element/' . self::WIDGET_NAME . '/section_layout/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/' . self::WIDGET_NAME . '/section_content/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/after_section_end', [$this, 'register_controls_fallback'], 10, 3);
        add_action('elementor/frontend/widget/before_render', [$this, 'before_render']);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_styles']);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function register_controls($element, $args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if (!class_exists('Elementor\\Controls_Manager') || self::WIDGET_NAME !== $element->get_name()) {
            return;
        }
        if (method_exists($element, 'get_controls') && $element->get_controls('superwoo_carousel_enabled')) {
            return;
        }

        $element->start_controls_section('superwoo_carousel_section', [
            'label' => __('Carousel', 'superwoo'),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);
        $element->add_control('superwoo_carousel_enabled', [
            'label' => __('Enable Carousel', 'superwoo'), 'type' => \Elementor\Controls_Manager::SWITCHER,
            'label_on' => __('Yes', 'superwoo'), 'label_off' => __('No', 'superwoo'),
            'return_value' => 'yes', 'default' => '',
        ]);

        $condition = ['superwoo_carousel_enabled' => 'yes'];
        $element->add_responsive_control('superwoo_carousel_slides_to_show', [
            'label' => __('Slides to Show', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1, 'max' => 8, 'step' => 0.1, 'default' => 4,
            'tablet_default' => 2, 'mobile_default' => 1, 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_slides_to_scroll', [
            'label' => __('Slides to Scroll', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1, 'max' => 8, 'default' => 1, 'condition' => $condition,
        ]);
        $element->add_responsive_control('superwoo_carousel_space_between', [
            'label' => __('Space Between', 'superwoo'), 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 100]],
            'default' => ['size' => 20, 'unit' => 'px'],
            'tablet_default' => ['size' => 20, 'unit' => 'px'],
            'mobile_default' => ['size' => 20, 'unit' => 'px'], 'condition' => $condition,
        ]);

        foreach ([
            'superwoo_carousel_arrows' => [__('Navigation Arrows', 'superwoo'), 'yes'],
            'superwoo_carousel_dots' => [__('Pagination Dots', 'superwoo'), ''],
            'superwoo_carousel_autoplay' => [__('Autoplay', 'superwoo'), ''],
            'superwoo_carousel_loop' => [__('Infinite Loop', 'superwoo'), 'yes'],
        ] as $id => $control) {
            $element->add_control($id, [
                'label' => $control[0], 'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes', 'default' => $control[1], 'condition' => $condition,
            ]);
        }
        $element->add_control('superwoo_carousel_autoplay_speed', [
            'label' => __('Autoplay Speed', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 500, 'step' => 100, 'default' => 3000,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_autoplay' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_pause_hover', [
            'label' => __('Pause on Hover', 'superwoo'), 'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes', 'default' => 'yes',
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_autoplay' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_extended', [
            'label' => __('Extended / Peek Mode', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT,
            'options' => ['none' => __('None', 'superwoo'), 'both' => __('Both Sides', 'superwoo'), 'left' => __('Left Side', 'superwoo'), 'right' => __('Right Side', 'superwoo')],
            'default' => 'none', 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_arrow_color', [
            'label' => __('Arrow Color', 'superwoo'), 'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}}.superwoo-products-carousel-enabled' => '--superwoo-carousel-arrow-color: {{VALUE}};'], 'condition' => $condition,
        ]);
        $element->add_responsive_control('superwoo_carousel_arrow_size', [
            'label' => __('Arrow Size', 'superwoo'), 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 20, 'max' => 80]], 'selectors' => ['{{WRAPPER}}.superwoo-products-carousel-enabled .superwoo-carousel__arrow' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'], 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_dot_color', [
            'label' => __('Dot Color', 'superwoo'), 'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}}.superwoo-products-carousel-enabled' => '--superwoo-carousel-dot-color: {{VALUE}};'], 'condition' => $condition,
        ]);
        $element->add_responsive_control('superwoo_carousel_dot_size', [
            'label' => __('Dot Size', 'superwoo'), 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 4, 'max' => 24]], 'selectors' => ['{{WRAPPER}}.superwoo-products-carousel-enabled .superwoo-carousel__dots button' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'], 'condition' => $condition,
        ]);
        $element->end_controls_section();
    }

    public function register_controls_fallback($element, $section_id, $args) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        $this->register_controls($element, $args);
    }

    public function before_render($element) {
        if (!is_object($element) || !method_exists($element, 'get_name') || self::WIDGET_NAME !== $element->get_name()) {
            return;
        }
        $settings = $element->get_settings_for_display();
        if ('yes' !== ($settings['superwoo_carousel_enabled'] ?? '')) {
            return;
        }

        $config = [
            'slidesToShow' => [
                'desktop' => $this->number($settings['superwoo_carousel_slides_to_show'] ?? 4, 4, 1, 8),
                'tablet' => $this->number($settings['superwoo_carousel_slides_to_show_tablet'] ?? 2, 2, 1, 8),
                'mobile' => $this->number($settings['superwoo_carousel_slides_to_show_mobile'] ?? 1, 1, 1, 8),
            ],
            'spaceBetween' => [
                'desktop' => $this->slider_size($settings['superwoo_carousel_space_between'] ?? [], 20),
                'tablet' => $this->slider_size($settings['superwoo_carousel_space_between_tablet'] ?? [], 20),
                'mobile' => $this->slider_size($settings['superwoo_carousel_space_between_mobile'] ?? [], 20),
            ],
            'slidesToScroll' => $this->number($settings['superwoo_carousel_slides_to_scroll'] ?? 1, 1, 1, 8),
            'arrows' => 'yes' === ($settings['superwoo_carousel_arrows'] ?? 'yes'),
            'dots' => 'yes' === ($settings['superwoo_carousel_dots'] ?? ''),
            'autoplay' => 'yes' === ($settings['superwoo_carousel_autoplay'] ?? ''),
            'autoplaySpeed' => (int) $this->number($settings['superwoo_carousel_autoplay_speed'] ?? 3000, 3000, 500, 60000),
            'loop' => 'yes' === ($settings['superwoo_carousel_loop'] ?? 'yes'),
            'pauseOnHover' => 'yes' === ($settings['superwoo_carousel_pause_hover'] ?? 'yes'),
            'extended' => in_array(($settings['superwoo_carousel_extended'] ?? 'none'), ['none', 'both', 'left', 'right'], true) ? $settings['superwoo_carousel_extended'] : 'none',
        ];
        $element->add_render_attribute('_wrapper', 'class', 'superwoo-products-carousel-enabled');
        $element->add_render_attribute('_wrapper', 'data-superwoo-products-carousel', wp_json_encode($config));
        $element->add_render_attribute('_wrapper', 'role', 'region');
        $element->add_render_attribute('_wrapper', 'aria-label', __('Products carousel', 'superwoo'));
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    public function enqueue_styles() {
        wp_enqueue_style('superwoo-elementor-products-carousel', SUPERWOO_URL . 'public/css/elementor-products-carousel.css', [], SUPERWOO_VERSION);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('superwoo-elementor-products-carousel', SUPERWOO_URL . 'public/js/elementor-products-carousel.js', [], SUPERWOO_VERSION, true);
    }

    private function number($value, $default, $min, $max) {
        $value = is_numeric($value) ? (float) $value : (float) $default;
        return max($min, min($max, $value));
    }

    private function slider_size($value, $default) {
        return $this->number(is_array($value) ? ($value['size'] ?? $default) : $value, $default, 0, 100);
    }
}

<?php
defined('ABSPATH') || exit;

/** Extends Elementor Pro's Products widget with optional carousel controls. */
class SuperWoo_Elementor_Products_Carousel {
    const WIDGET_NAME = 'woocommerce-products';

    public function hooks() {
        add_action('elementor/element/' . self::WIDGET_NAME . '/section_layout/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/' . self::WIDGET_NAME . '/section_content/after_section_end', [$this, 'register_controls'], 10, 2);
        add_action('elementor/element/after_section_end', [$this, 'register_controls_fallback'], 10, 3);
        add_action('elementor/frontend/widget/before_render', [$this, 'before_render'], 1);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_styles']);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('rocket_delay_js_exclusions', [$this, 'exclude_from_rocket_delay']);
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
            'return_value' => 'yes', 'default' => '', 'prefix_class' => 'superwoo-carousel-enabled-',
        ]);

        $condition = ['superwoo_carousel_enabled' => 'yes'];
        $element->add_responsive_control('superwoo_carousel_slides_to_show', [
            'label' => __('Slides to Show', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1, 'max' => 8, 'step' => 0.1, 'default' => 4,
            'tablet_default' => 2, 'mobile_default' => 1, 'condition' => $condition,
            'selectors' => ['{{WRAPPER}}' => '--superwoo-preview-slides: {{VALUE}};'],
        ]);
        $element->add_control('superwoo_carousel_slides_to_scroll', [
            'label' => __('Slides to Scroll', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1, 'max' => 8, 'default' => 1, 'condition' => $condition,
            'selectors' => ['{{WRAPPER}}' => '--superwoo-preview-scroll: {{VALUE}};'],
        ]);
        $element->add_control('superwoo_carousel_products_limit', [
            'label' => __('Products to Display', 'superwoo'),
            'description' => __('The total number of products loaded into this carousel.', 'superwoo'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => 1,
            'max' => 100,
            'default' => 12,
            'condition' => $condition,
            'selectors' => ['{{WRAPPER}}' => '--superwoo-preview-product-limit: {{VALUE}};'],
        ]);
        $element->add_responsive_control('superwoo_carousel_space_between', [
            'label' => __('Space Between', 'superwoo'), 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 100]],
            'default' => ['size' => 20, 'unit' => 'px'],
            'tablet_default' => ['size' => 20, 'unit' => 'px'],
            'mobile_default' => ['size' => 20, 'unit' => 'px'], 'condition' => $condition,
            'selectors' => ['{{WRAPPER}}' => '--superwoo-preview-gap: {{SIZE}};'],
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
                'prefix_class' => 'superwoo-carousel-' . str_replace('superwoo_carousel_', '', $id) . '-',
            ]);
        }
        $element->add_control('superwoo_carousel_infinite_scroll', [
            'label' => __('Infinite Scroll', 'superwoo'),
            'description' => __('Enable mouse-wheel and trackpad navigation that continues from the last product to the first.', 'superwoo'),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default' => '',
            'condition' => $condition,
            'prefix_class' => 'superwoo-carousel-infinite-scroll-',
        ]);
        $element->add_control('superwoo_carousel_arrow_style', [
            'label' => __('Arrow Icon', 'superwoo'),
            'type' => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'chevron'  => __('Chevron ‹ ›', 'superwoo'),
                'angle'    => __('Angle ❮ ❯', 'superwoo'),
                'arrow'    => __('Arrow ← →', 'superwoo'),
                'triangle' => __('Triangle ◀ ▶', 'superwoo'),
            ],
            'default' => 'chevron',
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_arrows' => 'yes'],
            'prefix_class' => 'superwoo-carousel-arrow-style-',
        ]);
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
        $element->add_responsive_control('superwoo_carousel_extended', [
            'label' => __('Extended / Peek Mode', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT,
            'options' => ['none' => __('None', 'superwoo'), 'both' => __('Both Sides', 'superwoo'), 'left' => __('Left Side', 'superwoo'), 'right' => __('Right Side', 'superwoo')],
            'default' => 'none', 'tablet_default' => 'none', 'mobile_default' => 'none', 'condition' => $condition,
            'selectors' => ['{{WRAPPER}}' => '--superwoo-preview-extended: {{VALUE}};'],
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
        $element->add_responsive_control('superwoo_carousel_arrow_left_offset', [
            'label' => __('Left Arrow Offset', 'superwoo'),
            'description' => __('Move the previous arrow horizontally. Use a negative or positive pixel value.', 'superwoo'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => -300,
            'max' => 300,
            'step' => 1,
            'default' => 0,
            'tablet_default' => 0,
            'mobile_default' => 0,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_arrows' => 'yes'],
            'selectors' => ['{{WRAPPER}}' => '--superwoo-carousel-arrow-left-offset: {{VALUE}}px;'],
        ]);
        $element->add_responsive_control('superwoo_carousel_arrow_right_offset', [
            'label' => __('Right Arrow Offset', 'superwoo'),
            'description' => __('Move the next arrow horizontally. Use a negative or positive pixel value.', 'superwoo'),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'min' => -300,
            'max' => 300,
            'step' => 1,
            'default' => 0,
            'tablet_default' => 0,
            'mobile_default' => 0,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_arrows' => 'yes'],
            'selectors' => ['{{WRAPPER}}' => '--superwoo-carousel-arrow-right-offset: {{VALUE}}px;'],
        ]);
        $element->add_responsive_control('superwoo_carousel_dot_size', [
            'label' => __('Dot Size', 'superwoo'), 'type' => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px'], 'range' => ['px' => ['min' => 4, 'max' => 24]], 'selectors' => ['{{WRAPPER}}.superwoo-products-carousel-enabled .superwoo-carousel__dots button' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'], 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_accessibility_label', [
            'label' => __('Carousel Accessibility Label', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('Products carousel', 'superwoo'), 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_show_heading', [
            'label' => __('Show Heading', 'superwoo'), 'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes', 'default' => '', 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_heading', [
            'label' => __('Heading', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_heading' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_heading_tag', [
            'label' => __('Heading HTML Tag', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT,
            'options' => ['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div'], 'default' => 'h2',
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_heading' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_subheading', [
            'label' => __('Subheading', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_heading' => 'yes'],
        ]);
        $element->add_responsive_control('superwoo_carousel_header_alignment', [
            'label' => __('Header Alignment', 'superwoo'), 'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => ['left' => ['title' => __('Left', 'superwoo'), 'icon' => 'eicon-text-align-left'], 'center' => ['title' => __('Center', 'superwoo'), 'icon' => 'eicon-text-align-center'], 'right' => ['title' => __('Right', 'superwoo'), 'icon' => 'eicon-text-align-right']],
            'default' => 'left', 'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_heading' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_show_view_all', [
            'label' => __('Show View All', 'superwoo'), 'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes', 'default' => '', 'condition' => $condition,
        ]);
        $element->add_control('superwoo_carousel_view_all_text', [
            'label' => __('View All Text', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT,
            'default' => __('View All Products', 'superwoo'), 'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_view_all' => 'yes'],
        ]);
        $element->add_control('superwoo_carousel_view_all_url', [
            'label' => __('View All URL', 'superwoo'), 'type' => \Elementor\Controls_Manager::URL,
            'condition' => ['superwoo_carousel_enabled' => 'yes', 'superwoo_carousel_show_view_all' => 'yes'],
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

        $settings = $element->get_settings();
        if ('yes' !== ($settings['superwoo_carousel_enabled'] ?? '')) {
            return;
        }

        $products_limit = (int) $this->number($settings['superwoo_carousel_products_limit'] ?? 12, 12, 1, 100);
        $query_columns = max(1, (int) $this->number($settings['columns'] ?? 4, 4, 1, 12));
        $query_rows = (int) ceil($products_limit / $query_columns);
        if (method_exists($element, 'set_settings')) {
            $element->set_settings('posts_per_page', $products_limit);
            $element->set_settings('rows', $query_rows);
        }
        // Elementor computes and caches get_settings_for_display() before
        // this hook. Reset that cache after applying the carousel query limit
        // so Products_Renderer receives the new posts_per_page/rows values.
        if (method_exists($element, 'reset_render_state')) {
            $element->reset_render_state();
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
            'productsLimit' => $products_limit,
            'arrows' => 'yes' === ($settings['superwoo_carousel_arrows'] ?? 'yes'),
            'arrowStyle' => $this->arrow_style_value($settings['superwoo_carousel_arrow_style'] ?? 'chevron'),
            'dots' => 'yes' === ($settings['superwoo_carousel_dots'] ?? ''),
            'autoplay' => 'yes' === ($settings['superwoo_carousel_autoplay'] ?? ''),
            'autoplaySpeed' => (int) $this->number($settings['superwoo_carousel_autoplay_speed'] ?? 3000, 3000, 500, 60000),
            'loop' => 'yes' === ($settings['superwoo_carousel_loop'] ?? 'yes'),
            'infiniteScroll' => 'yes' === ($settings['superwoo_carousel_infinite_scroll'] ?? ''),
            'pauseOnHover' => 'yes' === ($settings['superwoo_carousel_pause_hover'] ?? 'yes'),
            'extended' => [
                'desktop' => $this->extended_value($settings['superwoo_carousel_extended'] ?? 'none'),
                'tablet' => $this->extended_value($settings['superwoo_carousel_extended_tablet'] ?? ($settings['superwoo_carousel_extended'] ?? 'none')),
                'mobile' => $this->extended_value($settings['superwoo_carousel_extended_mobile'] ?? ($settings['superwoo_carousel_extended_tablet'] ?? ($settings['superwoo_carousel_extended'] ?? 'none'))),
            ],
            'label' => sanitize_text_field($settings['superwoo_carousel_accessibility_label'] ?? __('Products carousel', 'superwoo')) ?: __('Products carousel', 'superwoo'),
            'header' => [
                'show' => 'yes' === ($settings['superwoo_carousel_show_heading'] ?? ''),
                'heading' => sanitize_text_field($settings['superwoo_carousel_heading'] ?? ''),
                'tag' => in_array(($settings['superwoo_carousel_heading_tag'] ?? 'h2'), ['h2', 'h3', 'h4', 'div'], true) ? $settings['superwoo_carousel_heading_tag'] : 'h2',
                'subheading' => sanitize_text_field($settings['superwoo_carousel_subheading'] ?? ''),
                'alignment' => in_array(($settings['superwoo_carousel_header_alignment'] ?? 'left'), ['left', 'center', 'right'], true) ? $settings['superwoo_carousel_header_alignment'] : 'left',
                'viewAll' => 'yes' === ($settings['superwoo_carousel_show_view_all'] ?? ''),
                'viewAllText' => sanitize_text_field($settings['superwoo_carousel_view_all_text'] ?? __('View All Products', 'superwoo')),
                'viewAllUrl' => !empty($settings['superwoo_carousel_view_all_url']['url']) ? esc_url_raw($settings['superwoo_carousel_view_all_url']['url']) : '',
                'viewAllNewTab' => !empty($settings['superwoo_carousel_view_all_url']['is_external']),
                'viewAllNofollow' => !empty($settings['superwoo_carousel_view_all_url']['nofollow']),
            ],
        ];
        $element->add_render_attribute('_wrapper', 'class', 'superwoo-products-carousel-enabled');
        $element->add_render_attribute('_wrapper', 'data-superwoo-products-carousel', wp_json_encode($config));
        $element->add_render_attribute('_wrapper', 'role', 'region');
        $element->add_render_attribute('_wrapper', 'aria-label', $config['label']);
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    public function enqueue_styles() {
        wp_enqueue_style('superwoo-elementor-products-carousel', SUPERWOO_URL . 'public/css/elementor-products-carousel.css', [], SUPERWOO_VERSION);
    }

    public function enqueue_scripts() {
        wp_enqueue_script('superwoo-elementor-products-carousel', SUPERWOO_URL . 'public/js/elementor-products-carousel.js', [], SUPERWOO_VERSION, true);
    }

    /** The carousel must initialize before the first product-card interaction. */
    public function exclude_from_rocket_delay($exclusions) {
        $exclusions[] = 'superwoo-elementor-products-carousel';
        $exclusions[] = '/superwoo/public/js/elementor-products-carousel.js';
        return array_unique($exclusions);
    }

    private function number($value, $default, $min, $max) {
        $value = is_numeric($value) ? (float) $value : (float) $default;
        return max($min, min($max, $value));
    }

    private function slider_size($value, $default) {
        return $this->number(is_array($value) ? ($value['size'] ?? $default) : $value, $default, 0, 100);
    }

    private function extended_value($value) {
        return in_array($value, ['none', 'both', 'left', 'right'], true) ? $value : 'none';
    }

    private function arrow_style_value($value) {
        return in_array($value, ['chevron', 'angle', 'arrow', 'triangle'], true) ? $value : 'chevron';
    }

}

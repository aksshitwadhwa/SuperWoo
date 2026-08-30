<?php
defined('ABSPATH') || exit;

class SuperWoo_Elementor_Shop_Filters_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'superwoo-shop-filters'; }
    public function get_title() { return __('SuperWoo Shop Filters', 'superwoo'); }
    public function get_icon() { return 'eicon-filter'; }
    public function get_categories() { return ['general']; }

    protected function register_controls() {
        $this->start_controls_section('content', ['label' => __('Shop Filters', 'superwoo')]);
        $this->add_control('title', ['label' => __('Title', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT, 'default' => __('Filter products', 'superwoo')]);
        $this->add_control('show_title', ['label' => __('Show title', 'superwoo'), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        $this->add_control('filter_visibility_heading', ['label' => __('Filters to show', 'superwoo'), 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before']);
        foreach ([
            'show_search' => __('Product search', 'superwoo'),
            'show_categories' => __('Categories', 'superwoo'),
            'show_price' => __('Price range', 'superwoo'),
            'show_attributes' => __('Product attributes', 'superwoo'),
            'show_stock' => __('Availability', 'superwoo'),
            'show_sale' => __('On-sale toggle', 'superwoo'),
            'show_rating' => __('Customer rating', 'superwoo'),
            'show_sort' => __('Sort order', 'superwoo'),
        ] as $key => $label) {
            $this->add_control($key, ['label' => $label, 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes']);
        }
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo SuperWoo_Shop_Filters::render([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'title' => $settings['title'] ?? '',
            'show_title' => 'yes' === ($settings['show_title'] ?? 'yes'),
            'show_search' => 'yes' === ($settings['show_search'] ?? 'yes'),
            'show_categories' => 'yes' === ($settings['show_categories'] ?? 'yes'),
            'show_price' => 'yes' === ($settings['show_price'] ?? 'yes'),
            'show_attributes' => 'yes' === ($settings['show_attributes'] ?? 'yes'),
            'show_stock' => 'yes' === ($settings['show_stock'] ?? 'yes'),
            'show_sale' => 'yes' === ($settings['show_sale'] ?? 'yes'),
            'show_rating' => 'yes' === ($settings['show_rating'] ?? 'yes'),
            'show_sort' => 'yes' === ($settings['show_sort'] ?? 'yes'),
        ]);
    }
}

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
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        echo SuperWoo_Shop_Filters::render([ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'title' => $settings['title'] ?? '',
            'show_title' => 'yes' === ($settings['show_title'] ?? 'yes'),
        ]);
    }
}

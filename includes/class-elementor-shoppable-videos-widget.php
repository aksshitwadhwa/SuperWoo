<?php
defined('ABSPATH') || exit;

class SuperWoo_Elementor_Shoppable_Videos_Widget extends \Elementor\Widget_Base {
    public function get_name() { return 'superwoo-shoppable-videos'; }
    public function get_title() { return __('SuperWoo Shoppable Videos', 'superwoo'); }
    public function get_icon() { return 'eicon-video-camera'; }
    public function get_categories() { return ['general']; }
    public function get_style_depends() { return ['superwoo-shoppable-videos', 'superwoo-shoppable-video-viewer', 'superwoo-shoppable-video-timed']; }
    public function get_script_depends() { return ['superwoo-shoppable-videos']; }
    protected function register_controls() {
        $collections = ['' => __('Select a collection', 'superwoo')];
        $terms = get_terms(['taxonomy' => SuperWoo_Shoppable_Videos::COLLECTION, 'hide_empty' => false]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $collection) {
                $collections[$collection->slug] = $collection->name;
            }
        }
        $this->start_controls_section('superwoo_video_content', ['label' => __('Shoppable Videos', 'superwoo')]);
        $this->add_control('source', ['label' => __('Source', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'all', 'options' => ['all' => __('All videos', 'superwoo'), 'collection' => __('Collection', 'superwoo'), 'selected' => __('Selected videos', 'superwoo')]]);
        $this->add_control('collection', ['label' => __('Collection', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $collections, 'condition' => ['source' => 'collection']]);
        $this->add_control('videos', ['label' => __('Video IDs', 'superwoo'), 'type' => \Elementor\Controls_Manager::TEXT, 'description' => __('Comma-separated Shoppable Video IDs.', 'superwoo'), 'condition' => ['source' => 'selected']]);
        $this->add_control('layout', ['label' => __('Layout', 'superwoo'), 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'reels', 'options' => ['reels' => __('Reels', 'superwoo'), 'stories' => __('Stories', 'superwoo'), 'carousel' => __('Carousel', 'superwoo'), 'grid' => __('Grid', 'superwoo'), 'inline' => __('Inline', 'superwoo'), 'side-products' => __('Video + Products', 'superwoo'), 'bubbles' => __('Bubbles', 'superwoo')]]);
        $this->add_control('limit', ['label' => __('Videos to display', 'superwoo'), 'type' => \Elementor\Controls_Manager::NUMBER, 'default' => 12, 'min' => 1, 'max' => 50]);
        $this->end_controls_section();
    }
    protected function render() {
        $settings = $this->get_settings_for_display();
        $module = new SuperWoo_Shoppable_Videos();
        echo $module->shortcode(['collection' => 'collection' === ($settings['source'] ?? '') ? ($settings['collection'] ?? '') : '', 'videos' => 'selected' === ($settings['source'] ?? '') ? ($settings['videos'] ?? '') : '', 'layout' => $settings['layout'] ?? 'reels', 'limit' => $settings['limit'] ?? 12]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

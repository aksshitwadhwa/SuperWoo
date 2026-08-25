<?php
defined('ABSPATH') || exit;

class SuperWoo_Benefit_Taxonomy {
    public function hooks() {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_benefits'])) {
            return;
        }

        add_action('init', [$this, 'register_taxonomy']);
        add_action('admin_notices', [$this, 'shortcode_notice']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('product_benefit_add_form_fields', [$this, 'add_icon_field']);
        add_action('product_benefit_edit_form_fields', [$this, 'edit_icon_field']);
        add_action('created_product_benefit', [$this, 'save_icon']);
        add_action('edited_product_benefit', [$this, 'save_icon']);
        add_filter('manage_edit-product_benefit_columns', [$this, 'columns']);
        add_filter('manage_product_benefit_custom_column', [$this, 'column_content'], 10, 3);
    }

    public function register_taxonomy() {
        register_taxonomy('product_benefit', 'product', [
            'labels' => [
                'name'          => __('Benefit Icons', 'superwoo'),
                'singular_name' => __('Benefit Icon', 'superwoo'),
                'menu_name'     => __('Benefit Icons', 'superwoo'),
                'add_new_item'  => __('Add New Benefit', 'superwoo'),
                'edit_item'     => __('Edit Benefit', 'superwoo'),
                'search_items'  => __('Search Benefits', 'superwoo'),
                'all_items'     => __('All Benefits', 'superwoo'),
            ],
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_menu'      => true,
            'show_in_rest'      => false,
            'query_var'         => false,
            'rewrite'           => false,
        ]);
    }

    public function shortcode_notice() {
        $screen = get_current_screen();
        if (!$screen || 'edit-product_benefit' !== $screen->id) {
            return;
        }
        ?>
        <div class="notice notice-info superwoo-shortcode-notice">
            <p><strong><?php esc_html_e('Display product benefits:', 'superwoo'); ?></strong></p>
            <p>
                <?php esc_html_e('Use', 'superwoo'); ?>
                <code id="superwoo-benefits-shortcode">[product_benefits]</code>
                <?php esc_html_e('on a single product page, or', 'superwoo'); ?>
                <code>[product_benefits id="123"]</code>
                <?php esc_html_e('for a specific product.', 'superwoo'); ?>
                <button type="button" class="button button-small" id="superwoo-copy-benefits-shortcode"><?php esc_html_e('Copy', 'superwoo'); ?></button>
            </p>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) {
            return;
        }

        if (empty($_GET['taxonomy']) || 'product_benefit' !== sanitize_key(wp_unslash($_GET['taxonomy']))) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('superwoo-admin-benefits', SUPERWOO_URL . 'public/js/admin-benefits.js', ['jquery'], SUPERWOO_VERSION, true);
        wp_enqueue_style('superwoo-admin', SUPERWOO_URL . 'public/css/admin.css', [], SUPERWOO_VERSION);
    }

    public function add_icon_field() {
        wp_nonce_field('superwoo_save_benefit_icon', 'superwoo_benefit_icon_nonce');
        include SUPERWOO_PATH . 'admin/views/benefit-icon-field.php';
    }

    public function edit_icon_field($term) {
        wp_nonce_field('superwoo_save_benefit_icon', 'superwoo_benefit_icon_nonce');
        $icon_id  = get_term_meta($term->term_id, 'benefit_icon', true);
        $icon_url = $icon_id ? wp_get_attachment_image_url($icon_id, 'thumbnail') : '';
        include SUPERWOO_PATH . 'admin/views/benefit-icon-field-edit.php';
    }

    public function save_icon($term_id) {
        if (empty($_POST['superwoo_benefit_icon_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['superwoo_benefit_icon_nonce'])), 'superwoo_save_benefit_icon')) {
            return;
        }

        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_categories')) {
            return;
        }

        $icon_id = isset($_POST['benefit_icon']) ? absint($_POST['benefit_icon']) : 0;
        update_term_meta($term_id, 'benefit_icon', $icon_id);
    }

    public function columns($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('name' === $key) {
                $new['benefit_icon'] = __('Icon', 'superwoo');
            }
        }

        return $new;
    }

    public function column_content($content, $column, $term_id) {
        if ('benefit_icon' !== $column) {
            return $content;
        }

        $icon_id = get_term_meta($term_id, 'benefit_icon', true);
        return $icon_id ? wp_get_attachment_image($icon_id, [32, 32]) : '&mdash;';
    }
}

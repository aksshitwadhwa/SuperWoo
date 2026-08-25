<?php
defined('ABSPATH') || exit;

class SuperWoo_Product_Meta {
    public function hooks() {
        $settings = superwoo_get_settings();

        add_action('admin_init', [$this, 'seed_how_to_use_meta']);

        $needs_admin_assets = false;

        if (!empty($settings['enable_multi_currency'])) {
            add_action('add_meta_boxes', [$this, 'add_manual_currency_extras_box']);
            add_action('save_post_product', [$this, 'save_manual_currency_extras']);
            $needs_admin_assets = true;
        }

        if (!empty($settings['enable_how_to_use'])) {
            add_action('add_meta_boxes', [$this, 'add_how_to_use_box']);
            add_action('save_post_product', [$this, 'save_how_to_use']);
        }

        if (!empty($settings['enable_faqs'])) {
            add_action('add_meta_boxes', [$this, 'add_faqs_box']);
            add_action('save_post_product', [$this, 'save_faqs']);
            $needs_admin_assets = true;
        }

        if ($needs_admin_assets) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    public function add_manual_currency_extras_box() {
        if (!function_exists('superwoo_currency') || empty(superwoo_currency()->get_non_base_enabled_currencies())) {
            return;
        }

        add_meta_box(
            'superwoo_manual_currency_extras_box',
            __('Manual Currency Extras', 'superwoo'),
            [$this, 'render_manual_currency_extras_box'],
            'product',
            'side',
            'default'
        );
    }

    public function render_manual_currency_extras_box($post) {
        wp_nonce_field('superwoo_save_manual_currency_extras', 'superwoo_manual_currency_extras_nonce');
        $extras = get_post_meta($post->ID, '_superwoo_manual_currency_extras', true);
        $extras = is_array($extras) ? $extras : [];
        $currencies = function_exists('superwoo_currency') ? superwoo_currency()->get_non_base_enabled_currencies() : [];
        ?>
        <p class="description"><?php esc_html_e('Optional fixed amount added after converting the INR product price.', 'superwoo'); ?></p>
        <?php foreach ($currencies as $code) : ?>
            <p class="superwoo-currency-extra-row">
                <label for="superwoo_manual_currency_extra_<?php echo esc_attr(strtolower($code)); ?>"><?php echo esc_html($code); ?></label>
                <input type="number" min="0" step="0.01" id="superwoo_manual_currency_extra_<?php echo esc_attr(strtolower($code)); ?>" name="superwoo_manual_currency_extras[<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr(isset($extras[$code]) ? $extras[$code] : ''); ?>" placeholder="0.00">
            </p>
        <?php endforeach; ?>
        <?php
    }

    public function save_manual_currency_extras($post_id) {
        if (!$this->can_save_product($post_id, 'superwoo_manual_currency_extras_nonce', 'superwoo_save_manual_currency_extras')) {
            return;
        }

        $extras = [];
        $currencies = function_exists('superwoo_currency') ? superwoo_currency()->get_non_base_enabled_currencies() : [];
        $posted = !empty($_POST['superwoo_manual_currency_extras']) && is_array($_POST['superwoo_manual_currency_extras']) ? wp_unslash($_POST['superwoo_manual_currency_extras']) : [];

        foreach ($currencies as $code) {
            if (!isset($posted[$code]) || '' === trim((string) $posted[$code])) {
                continue;
            }

            $amount = (float) wc_format_decimal($posted[$code]);
            if ($amount >= 0) {
                $extras[$code] = $amount;
            }
        }

        if (empty($extras)) {
            delete_post_meta($post_id, '_superwoo_manual_currency_extras');
            return;
        }

        update_post_meta($post_id, '_superwoo_manual_currency_extras', $extras);
    }

    public function seed_how_to_use_meta() {
        if (get_option('pbi_how_to_use_seeded')) {
            return;
        }

        $product_ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 200,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'product_how_to_use',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        foreach ($product_ids as $product_id) {
            add_post_meta($product_id, 'product_how_to_use', '', true);
        }

        update_option('pbi_how_to_use_seeded', 1);
    }

    public function add_how_to_use_box() {
        add_meta_box(
            'product_how_to_use_box',
            __('How to Use', 'superwoo'),
            [$this, 'render_how_to_use_box'],
            'product',
            'normal',
            'default'
        );
    }

    public function render_how_to_use_box($post) {
        wp_nonce_field('superwoo_save_how_to_use', 'superwoo_how_to_use_nonce');
        $content = get_post_meta($post->ID, 'product_how_to_use', true);

        wp_editor($content, 'product_how_to_use_editor', [
            'textarea_name' => 'product_how_to_use',
            'media_buttons' => true,
            'textarea_rows' => 8,
            'teeny'         => false,
        ]);
        ?>
        <p class="description">
            <?php esc_html_e('Elementor custom field key:', 'superwoo'); ?>
            <code>product_how_to_use</code>
        </p>
        <?php
    }

    public function save_how_to_use($post_id) {
        if (!$this->can_save_product($post_id, 'superwoo_how_to_use_nonce', 'superwoo_save_how_to_use')) {
            return;
        }

        if (isset($_POST['product_how_to_use'])) {
            update_post_meta($post_id, 'product_how_to_use', wp_kses_post(wp_unslash($_POST['product_how_to_use'])));
        }
    }

    public function add_faqs_box() {
        add_meta_box(
            'product_faqs_box',
            __('Product FAQs', 'superwoo'),
            [$this, 'render_faqs_box'],
            'product',
            'normal',
            'default'
        );
    }

    public function render_faqs_box($post) {
        wp_nonce_field('superwoo_save_faqs', 'superwoo_faqs_nonce');
        $faqs = get_post_meta($post->ID, 'product_faqs', true);
        if (!is_array($faqs)) {
            $faqs = [];
        }

        include SUPERWOO_PATH . 'admin/views/faqs-box.php';
    }

    public function save_faqs($post_id) {
        if (!$this->can_save_product($post_id, 'superwoo_faqs_nonce', 'superwoo_save_faqs')) {
            return;
        }

        $faqs = [];
        if (!empty($_POST['product_faqs']) && is_array($_POST['product_faqs'])) {
            foreach (wp_unslash($_POST['product_faqs']) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $question = isset($row['question']) ? sanitize_text_field($row['question']) : '';
                $answer   = isset($row['answer']) ? wp_kses_post($row['answer']) : '';

                if ('' !== $question || '' !== $answer) {
                    $faqs[] = [
                        'question' => $question,
                        'answer'   => $answer,
                    ];
                }
            }
        }

        update_post_meta($post_id, 'product_faqs', $faqs);
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || 'product' !== $screen->post_type) {
            return;
        }

        if (!empty(superwoo_get_settings()['enable_faqs'])) {
            wp_enqueue_script('superwoo-admin-faqs', SUPERWOO_URL . 'public/js/admin-faqs.js', ['jquery'], SUPERWOO_VERSION, true);
        }
        wp_enqueue_style('superwoo-admin', SUPERWOO_URL . 'public/css/admin.css', [], SUPERWOO_VERSION);
    }

    private function can_save_product($post_id, $nonce_key, $action) {
        if (empty($_POST[$nonce_key]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_key])), $action)) {
            return false;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (wp_is_post_revision($post_id)) {
            return false;
        }

        return current_user_can('edit_product', $post_id);
    }
}

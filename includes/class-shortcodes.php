<?php
defined('ABSPATH') || exit;

class SuperWoo_Shortcodes {
    public function hooks() {
        $settings = superwoo_get_settings();

        if (!empty($settings['enable_benefits'])) {
            add_shortcode('product_benefits', [$this, 'product_benefits']);
        }

        if (!empty($settings['enable_faqs'])) {
            add_shortcode('product_faqs', [$this, 'product_faqs']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_faq_assets']);
        }

        add_shortcode('wcpbi_cart_drawer_button', [$this, 'cart_drawer_button']);
        add_shortcode('superwoo_cart_button', [$this, 'cart_drawer_button']);
    }

    public function product_benefits($atts) {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_benefits'])) {
            return '';
        }

        $product_id = $this->resolve_product_id($atts);
        if (!$product_id) {
            return '';
        }

        $terms = get_the_terms($product_id, 'product_benefit');
        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        wp_enqueue_style('superwoo-benefits', SUPERWOO_URL . 'public/css/benefits.css', [], SUPERWOO_VERSION);

        return superwoo_template('benefits-list.php', [
            'terms' => $terms,
        ]);
    }

    public function product_faqs($atts) {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_faqs'])) {
            return '';
        }

        $product_id = $this->resolve_product_id($atts);
        if (!$product_id) {
            return '';
        }

        $faqs = get_post_meta($product_id, 'product_faqs', true);
        if (empty($faqs) || !is_array($faqs)) {
            return '';
        }

        wp_enqueue_style('superwoo-faqs', SUPERWOO_URL . 'public/css/faqs.css', [], SUPERWOO_VERSION);
        wp_enqueue_script('superwoo-faqs', SUPERWOO_URL . 'public/js/faqs.js', [], SUPERWOO_VERSION, true);

        return superwoo_template('faqs.php', [
            'faqs' => $faqs,
        ]);
    }

    public function cart_drawer_button($atts) {
        $atts = shortcode_atts([
            'label' => __('Cart', 'superwoo'),
        ], $atts);

        ob_start();
        ?>
        <button type="button" class="superwoo-cart-button" data-superwoo-open-cart aria-label="<?php echo esc_attr($atts['label']); ?>">
            <span class="superwoo-cart-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 8h14l1 13H4L5 8Z"/><path d="M8 8V6a4 4 0 0 1 8 0v2"/></svg></span>
            <span class="superwoo-cart-count"><?php echo esc_html(superwoo_cart_count()); ?></span>
        </button>
        <?php
        return ob_get_clean();
    }

    public function enqueue_faq_assets() {
        if (is_singular('product')) {
            wp_register_style('superwoo-faqs', SUPERWOO_URL . 'public/css/faqs.css', [], SUPERWOO_VERSION);
            wp_register_script('superwoo-faqs', SUPERWOO_URL . 'public/js/faqs.js', [], SUPERWOO_VERSION, true);
        }
    }

    private function resolve_product_id($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $product_id = absint($atts['id']);

        if ($product_id) {
            return $product_id;
        }

        global $product;
        if ($product instanceof WC_Product) {
            return $product->get_id();
        }

        if (is_singular('product')) {
            return get_the_ID();
        }

        return 0;
    }
}

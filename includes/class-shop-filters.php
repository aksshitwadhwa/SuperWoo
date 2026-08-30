<?php
defined('ABSPATH') || exit;

/** Reusable, WooCommerce-compatible filters for shop and product archive pages. */
class SuperWoo_Shop_Filters {
    const CATEGORY_PARAM = 'superwoo_product_cat';
    const STOCK_PARAM = 'superwoo_stock_status';
    const SALE_PARAM = 'superwoo_on_sale';
    const RATING_PARAM = 'superwoo_rating';

    public function hooks() {
        add_shortcode('superwoo_shop_filters', [$this, 'shortcode']);
        add_action('pre_get_posts', [$this, 'apply_archive_filters']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
    }

    public function shortcode($atts) {
        $atts = shortcode_atts([
            'title' => __('Filter products', 'superwoo'),
            'show_title' => 'yes',
        ], $atts, 'superwoo_shop_filters');

        return self::render([
            'title' => (string) $atts['title'],
            'show_title' => 'yes' === $atts['show_title'],
        ]);
    }

    public function register_assets() {
        wp_register_style('superwoo-shop-filters', SUPERWOO_URL . 'public/css/shop-filters.css', [], SUPERWOO_VERSION);
    }

    public function register_elementor_widget($widgets_manager) {
        if (!class_exists('Elementor\\Widget_Base')) {
            return;
        }

        require_once SUPERWOO_PATH . 'includes/class-elementor-shop-filters-widget.php';
        $widgets_manager->register(new SuperWoo_Elementor_Shop_Filters_Widget());
    }

    public static function render($args = []) {
        if (!function_exists('wc_get_page_permalink')) {
            return '';
        }

        $args = wp_parse_args($args, [
            'title' => __('Filter products', 'superwoo'),
            'show_title' => true,
        ]);
        wp_enqueue_style('superwoo-shop-filters');

        $attributes = self::attributes();
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        ob_start();
        ?>
        <form class="superwoo-shop-filters" method="get" action="">
            <?php if (!empty($args['show_title']) && '' !== trim((string) $args['title'])) : ?>
                <h3 class="superwoo-shop-filters__title"><?php echo esc_html($args['title']); ?></h3>
            <?php endif; ?>

            <div class="superwoo-shop-filters__field superwoo-shop-filters__field--search">
                <label for="superwoo-filter-search"><?php esc_html_e('Search products', 'superwoo'); ?></label>
                <input id="superwoo-filter-search" type="search" name="s" value="<?php echo esc_attr(self::request_string('s')); ?>" placeholder="<?php esc_attr_e('Search products…', 'superwoo'); ?>">
                <input type="hidden" name="post_type" value="product">
            </div>

            <?php if (!empty($categories) && !is_wp_error($categories)) : ?>
                <fieldset class="superwoo-shop-filters__field">
                    <legend><?php esc_html_e('Categories', 'superwoo'); ?></legend>
                    <div class="superwoo-shop-filters__choices">
                        <?php foreach ($categories as $category) : ?>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::CATEGORY_PARAM); ?>[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, self::request_array(self::CATEGORY_PARAM), true)); ?>> <span><?php echo esc_html($category->name); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <fieldset class="superwoo-shop-filters__field">
                <legend><?php esc_html_e('Price', 'superwoo'); ?></legend>
                <div class="superwoo-shop-filters__range">
                    <input type="number" name="min_price" min="0" step="0.01" value="<?php echo esc_attr(self::request_number('min_price')); ?>" placeholder="<?php esc_attr_e('Min', 'superwoo'); ?>">
                    <span aria-hidden="true">–</span>
                    <input type="number" name="max_price" min="0" step="0.01" value="<?php echo esc_attr(self::request_number('max_price')); ?>" placeholder="<?php esc_attr_e('Max', 'superwoo'); ?>">
                </div>
            </fieldset>

            <?php foreach ($attributes as $attribute) : ?>
                <?php $terms = get_terms(['taxonomy' => $attribute['taxonomy'], 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']); ?>
                <?php if (empty($terms) || is_wp_error($terms)) { continue; } ?>
                <?php $param = 'superwoo_filter_' . $attribute['taxonomy']; $selected = self::request_array($param); ?>
                <fieldset class="superwoo-shop-filters__field">
                    <legend><?php echo esc_html($attribute['label']); ?></legend>
                    <div class="superwoo-shop-filters__choices">
                        <?php foreach ($terms as $term) : ?>
                            <label><input type="checkbox" name="<?php echo esc_attr($param); ?>[]" value="<?php echo esc_attr($term->slug); ?>" <?php checked(in_array($term->slug, $selected, true)); ?>> <span><?php echo esc_html($term->name); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>

            <fieldset class="superwoo-shop-filters__field">
                <legend><?php esc_html_e('Availability', 'superwoo'); ?></legend>
                <select name="<?php echo esc_attr(self::STOCK_PARAM); ?>">
                    <option value=""><?php esc_html_e('Any availability', 'superwoo'); ?></option>
                    <?php foreach (['instock' => __('In stock', 'superwoo'), 'outofstock' => __('Out of stock', 'superwoo'), 'onbackorder' => __('On backorder', 'superwoo')] as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, self::request_string(self::STOCK_PARAM)); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset>

            <fieldset class="superwoo-shop-filters__field">
                <legend><?php esc_html_e('Customer rating', 'superwoo'); ?></legend>
                <select name="<?php echo esc_attr(self::RATING_PARAM); ?>">
                    <option value=""><?php esc_html_e('Any rating', 'superwoo'); ?></option>
                    <?php foreach ([4 => __('4 stars & up', 'superwoo'), 3 => __('3 stars & up', 'superwoo'), 2 => __('2 stars & up', 'superwoo'), 1 => __('1 star & up', 'superwoo')] as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected((string) $value, self::request_string(self::RATING_PARAM)); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset>

            <label class="superwoo-shop-filters__toggle"><input type="checkbox" name="<?php echo esc_attr(self::SALE_PARAM); ?>" value="1" <?php checked('1', self::request_string(self::SALE_PARAM)); ?>> <?php esc_html_e('On sale only', 'superwoo'); ?></label>

            <div class="superwoo-shop-filters__field">
                <label for="superwoo-filter-sort"><?php esc_html_e('Sort by', 'superwoo'); ?></label>
                <select id="superwoo-filter-sort" name="orderby">
                    <?php foreach (self::sorting_options() as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, self::request_string('orderby')); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="superwoo-shop-filters__actions">
                <button type="submit"><?php esc_html_e('Apply filters', 'superwoo'); ?></button>
                <a href="<?php echo esc_url(self::clear_url()); ?>"><?php esc_html_e('Clear filters', 'superwoo'); ?></a>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    public function apply_archive_filters($query) {
        if (is_admin() || !$query->is_main_query() || (!is_shop() && !is_product_taxonomy())) {
            return;
        }

        $tax_query = (array) $query->get('tax_query');
        $categories = self::request_array(self::CATEGORY_PARAM);
        if ($categories) {
            $tax_query[] = ['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $categories, 'operator' => 'IN'];
        }
        foreach (self::attributes() as $attribute) {
            $terms = self::request_array('superwoo_filter_' . $attribute['taxonomy']);
            if ($terms) {
                $tax_query[] = ['taxonomy' => $attribute['taxonomy'], 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN'];
            }
        }
        if ($tax_query) {
            $query->set('tax_query', $tax_query);
        }

        $meta_query = (array) $query->get('meta_query');
        $stock_status = self::request_string(self::STOCK_PARAM);
        if (in_array($stock_status, ['instock', 'outofstock', 'onbackorder'], true)) {
            $meta_query[] = ['key' => '_stock_status', 'value' => $stock_status];
        }
        $rating = absint(self::request_string(self::RATING_PARAM));
        if ($rating >= 1 && $rating <= 5) {
            $meta_query[] = ['key' => '_wc_average_rating', 'value' => $rating, 'compare' => '>=', 'type' => 'DECIMAL'];
        }
        if ($meta_query) {
            $query->set('meta_query', $meta_query);
        }

        if ('1' === self::request_string(self::SALE_PARAM) && function_exists('wc_get_product_ids_on_sale')) {
            $sale_ids = array_map('absint', wc_get_product_ids_on_sale());
            $query->set('post__in', $sale_ids ?: [0]);
        }
    }

    public static function attributes() {
        $attributes = [];
        foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            if (taxonomy_exists($taxonomy)) {
                $attributes[] = ['taxonomy' => $taxonomy, 'label' => $attribute->attribute_label ?: wc_attribute_label($taxonomy)];
            }
        }
        return $attributes;
    }

    private static function sorting_options() {
        return [
            'menu_order' => __('Default sorting', 'superwoo'),
            'popularity' => __('Sort by popularity', 'superwoo'),
            'rating' => __('Sort by average rating', 'superwoo'),
            'date' => __('Sort by latest', 'superwoo'),
            'price' => __('Sort by price: low to high', 'superwoo'),
            'price-desc' => __('Sort by price: high to low', 'superwoo'),
        ];
    }

    private static function request_string($key) {
        return isset($_GET[$key]) && !is_array($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
    }

    private static function request_number($key) {
        $value = self::request_string($key);
        return is_numeric($value) && (float) $value >= 0 ? (string) $value : '';
    }

    private static function request_array($key) {
        $values = isset($_GET[$key]) ? (array) wp_unslash($_GET[$key]) : [];
        return array_values(array_unique(array_filter(array_map('sanitize_title', $values))));
    }

    private static function clear_url() {
        global $wp;
        $path = isset($wp->request) ? $wp->request : '';
        return home_url('/' . ltrim($path, '/'));
    }
}

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
        $visibility = self::visibility_defaults();
        $atts = shortcode_atts([
            'title' => __('Filter products', 'superwoo'),
            'show_title' => 'yes',
            'show_search' => $visibility['show_search'] ? 'yes' : 'no',
            'show_categories' => $visibility['show_categories'] ? 'yes' : 'no',
            'show_price' => $visibility['show_price'] ? 'yes' : 'no',
            'show_attributes' => $visibility['show_attributes'] ? 'yes' : 'no',
            'show_stock' => $visibility['show_stock'] ? 'yes' : 'no',
            'show_sale' => $visibility['show_sale'] ? 'yes' : 'no',
            'show_rating' => $visibility['show_rating'] ? 'yes' : 'no',
            'show_sort' => $visibility['show_sort'] ? 'yes' : 'no',
        ], $atts, 'superwoo_shop_filters');

        return self::render([
            'title' => (string) $atts['title'],
            'show_title' => 'yes' === $atts['show_title'],
            'show_search' => 'yes' === $atts['show_search'],
            'show_categories' => 'yes' === $atts['show_categories'],
            'show_price' => 'yes' === $atts['show_price'],
            'show_attributes' => 'yes' === $atts['show_attributes'],
            'show_stock' => 'yes' === $atts['show_stock'],
            'show_sale' => 'yes' === $atts['show_sale'],
            'show_rating' => 'yes' === $atts['show_rating'],
            'show_sort' => 'yes' === $atts['show_sort'],
        ]);
    }

    public function register_assets() {
        wp_register_style('superwoo-shop-filters', SUPERWOO_URL . 'public/css/shop-filters.css', [], SUPERWOO_VERSION);
        wp_register_script('superwoo-shop-filters', SUPERWOO_URL . 'public/js/shop-filters.js', [], SUPERWOO_VERSION, true);
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

        $visibility = self::visibility_defaults();
        $args = wp_parse_args($args, [
            'title' => __('Filter products', 'superwoo'),
            'show_title' => true,
            'show_search' => $visibility['show_search'],
            'show_categories' => $visibility['show_categories'],
            'show_price' => $visibility['show_price'],
            'show_attributes' => $visibility['show_attributes'],
            'show_stock' => $visibility['show_stock'],
            'show_sale' => $visibility['show_sale'],
            'show_rating' => $visibility['show_rating'],
            'show_sort' => $visibility['show_sort'],
        ]);

        // SuperWoo settings are the global visibility source of truth. This
        // also keeps existing Elementor widgets in sync after an admin
        // disables a filter, even when that widget has older saved controls.
        foreach ($visibility as $key => $enabled) {
            if (!$enabled) {
                $args[$key] = false;
            }
        }
        wp_enqueue_style('superwoo-shop-filters');
        wp_enqueue_script('superwoo-shop-filters');

        $attributes = self::attributes();
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        $selected_categories = self::selected_categories();
        $filter_id = wp_unique_id('superwoo-shop-filters-');
        $price_bounds = self::price_bounds();
        $price_min = self::request_number('min_price');
        $price_max = self::request_number('max_price');
        $price_min = '' !== $price_min ? (float) $price_min : $price_bounds['min'];
        $price_max = '' !== $price_max ? (float) $price_max : $price_bounds['max'];
        $price_min = min(max($price_min, $price_bounds['min']), $price_bounds['max']);
        $price_max = max(min($price_max, $price_bounds['max']), $price_min);

        ob_start();
        ?>
        <div class="superwoo-shop-filters-shell" data-superwoo-filter-shell>
        <button class="superwoo-shop-filters__mobile-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($filter_id); ?>" data-superwoo-filter-toggle>
            <span><?php esc_html_e('Filter products', 'superwoo'); ?></span>
            <span class="superwoo-shop-filters__mobile-toggle-icon" aria-hidden="true"></span>
        </button>
        <form id="<?php echo esc_attr($filter_id); ?>" class="superwoo-shop-filters" method="get" action="" data-superwoo-auto-apply>
            <?php if (!empty($args['show_title']) && '' !== trim((string) $args['title'])) : ?>
                <h4 class="superwoo-shop-filters__title"><?php echo esc_html($args['title']); ?></h4>
            <?php endif; ?>

            <?php if (!empty($args['show_search'])) : ?>
                <div class="superwoo-shop-filters__field superwoo-shop-filters__field--search">
                    <label for="superwoo-filter-search"><?php esc_html_e('Search products', 'superwoo'); ?></label>
                    <input id="superwoo-filter-search" type="search" name="s" value="<?php echo esc_attr(self::request_string('s')); ?>" placeholder="<?php esc_attr_e('Search products…', 'superwoo'); ?>">
                    <input type="hidden" name="post_type" value="product">
                </div>
            <?php endif; ?>

            <?php if (!empty($args['show_categories']) && !empty($categories) && !is_wp_error($categories)) : ?>
                <fieldset class="superwoo-shop-filters__field">
                    <legend><?php esc_html_e('Categories', 'superwoo'); ?></legend>
                    <div class="superwoo-shop-filters__choices">
                        <?php foreach ($categories as $category) : ?>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::CATEGORY_PARAM); ?>[]" value="<?php echo esc_attr($category->slug); ?>" <?php checked(in_array($category->slug, $selected_categories, true)); ?>> <span><?php echo esc_html($category->name); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <?php if (!empty($args['show_price'])) : ?>
                <fieldset class="superwoo-shop-filters__field">
                    <legend><?php esc_html_e('Price', 'superwoo'); ?></legend>
                    <div class="superwoo-shop-filters__price-slider" data-superwoo-price-slider data-min="<?php echo esc_attr($price_bounds['min']); ?>" data-max="<?php echo esc_attr($price_bounds['max']); ?>" data-currency="<?php echo esc_attr(get_woocommerce_currency()); ?>">
                        <div class="superwoo-shop-filters__price-track" aria-hidden="true"><span></span></div>
                        <input class="superwoo-shop-filters__price-range superwoo-shop-filters__price-range--min" type="range" name="min_price" min="<?php echo esc_attr($price_bounds['min']); ?>" max="<?php echo esc_attr($price_bounds['max']); ?>" step="<?php echo esc_attr($price_bounds['step']); ?>" value="<?php echo esc_attr($price_min); ?>" aria-label="<?php esc_attr_e('Minimum price', 'superwoo'); ?>">
                        <input class="superwoo-shop-filters__price-range superwoo-shop-filters__price-range--max" type="range" name="max_price" min="<?php echo esc_attr($price_bounds['min']); ?>" max="<?php echo esc_attr($price_bounds['max']); ?>" step="<?php echo esc_attr($price_bounds['step']); ?>" value="<?php echo esc_attr($price_max); ?>" aria-label="<?php esc_attr_e('Maximum price', 'superwoo'); ?>">
                    </div>
                    <div class="superwoo-shop-filters__price-values" aria-live="polite">
                        <span data-superwoo-price-min><?php echo wp_kses_post(wc_price($price_min)); ?></span>
                        <span data-superwoo-price-max><?php echo wp_kses_post(wc_price($price_max)); ?></span>
                    </div>
                </fieldset>
            <?php endif; ?>

            <?php if (!empty($args['show_attributes'])) : foreach ($attributes as $attribute) : ?>
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
            <?php endforeach; endif; ?>

            <?php if (!empty($args['show_stock'])) : ?><fieldset class="superwoo-shop-filters__field">
                <legend><?php esc_html_e('Availability', 'superwoo'); ?></legend>
                <select name="<?php echo esc_attr(self::STOCK_PARAM); ?>">
                    <option value=""><?php esc_html_e('Any availability', 'superwoo'); ?></option>
                    <?php foreach (['instock' => __('In stock', 'superwoo'), 'outofstock' => __('Out of stock', 'superwoo'), 'onbackorder' => __('On backorder', 'superwoo')] as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, self::request_string(self::STOCK_PARAM)); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </fieldset><?php endif; ?>

            <?php if (!empty($args['show_rating'])) : ?><fieldset class="superwoo-shop-filters__field">
                <legend><?php esc_html_e('Customer rating', 'superwoo'); ?></legend>
                <div class="superwoo-shop-filters__ratings">
                    <label class="superwoo-shop-filters__rating">
                        <input type="radio" name="<?php echo esc_attr(self::RATING_PARAM); ?>" value="" <?php checked('', self::request_string(self::RATING_PARAM)); ?>>
                        <span><?php esc_html_e('Any rating', 'superwoo'); ?></span>
                    </label>
                    <?php foreach ([5, 4, 3, 2, 1] as $value) : ?>
                        <label class="superwoo-shop-filters__rating">
                            <input type="radio" name="<?php echo esc_attr(self::RATING_PARAM); ?>" value="<?php echo esc_attr($value); ?>" <?php checked((string) $value, self::request_string(self::RATING_PARAM)); ?>>
                            <span class="superwoo-shop-filters__stars" aria-hidden="true"><?php echo esc_html(str_repeat('★', $value) . str_repeat('☆', 5 - $value)); ?></span>
                            <span class="screen-reader-text"><?php echo esc_html(sprintf(_n('%d star and up', '%d stars and up', $value, 'superwoo'), $value)); ?></span>
                            <span class="superwoo-shop-filters__rating-label"><?php echo esc_html(sprintf(_n('%d star & up', '%d stars & up', $value, 'superwoo'), $value)); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset><?php endif; ?>

            <?php if (!empty($args['show_sale'])) : ?><label class="superwoo-shop-filters__toggle"><input type="checkbox" name="<?php echo esc_attr(self::SALE_PARAM); ?>" value="1" <?php checked('1', self::request_string(self::SALE_PARAM)); ?>> <?php esc_html_e('On sale only', 'superwoo'); ?></label><?php endif; ?>

            <?php if (!empty($args['show_sort'])) : ?><div class="superwoo-shop-filters__field">
                <label for="superwoo-filter-sort"><?php esc_html_e('Sort by', 'superwoo'); ?></label>
                <select id="superwoo-filter-sort" name="orderby">
                    <?php foreach (self::sorting_options() as $value => $label) : ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($value, self::request_string('orderby')); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div><?php endif; ?>

            <div class="superwoo-shop-filters__actions">
                <button type="submit"><?php esc_html_e('Apply filters', 'superwoo'); ?></button>
                <a href="<?php echo esc_url(self::clear_url()); ?>"><?php esc_html_e('Clear filters', 'superwoo'); ?></a>
            </div>
        </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Default filter visibility configured from WooCommerce > SuperWoo. */
    public static function visibility_defaults() {
        $settings = superwoo_get_settings();
        $visibility = [];
        foreach (['search', 'categories', 'price', 'attributes', 'stock', 'sale', 'rating', 'sort'] as $filter) {
            $visibility['show_' . $filter] = !empty($settings['shop_filter_show_' . $filter]);
        }
        return $visibility;
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

    /** Return a safe slider range from WooCommerce's indexed product prices. */
    private static function price_bounds() {
        global $wpdb;

        $minimum = 0.0;
        $maximum = 1000.0;
        if (!empty($wpdb->wc_product_meta_lookup)) {
            $lookup = $wpdb->wc_product_meta_lookup;
            $row = $wpdb->get_row("SELECT MIN(lookup.min_price) AS minimum, MAX(lookup.max_price) AS maximum FROM {$lookup} AS lookup INNER JOIN {$wpdb->posts} AS products ON products.ID = lookup.product_id WHERE products.post_type = 'product' AND products.post_status = 'publish'"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($row && null !== $row->maximum) {
                $minimum = max(0, (float) $row->minimum);
                $maximum = max($minimum + 1, (float) $row->maximum);
            }
        }

        $decimals = function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
        $step = $decimals > 0 ? (string) (1 / (10 ** $decimals)) : '1';
        return ['min' => $minimum, 'max' => $maximum, 'step' => $step];
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

    /** Include the current product-category archive in the visible selection. */
    private static function selected_categories() {
        $categories = self::request_array(self::CATEGORY_PARAM);

        if (is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term && 'product_cat' === $term->taxonomy) {
                $categories[] = $term->slug;
            }
        }

        return array_values(array_unique(array_filter($categories)));
    }

    private static function clear_url() {
        global $wp;
        $path = isset($wp->request) ? $wp->request : '';
        return home_url('/' . ltrim($path, '/'));
    }
}

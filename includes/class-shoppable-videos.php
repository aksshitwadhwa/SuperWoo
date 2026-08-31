<?php
defined('ABSPATH') || exit;

/** Core content, rendering and WooCommerce attribution for Shoppable Videos. */
class SuperWoo_Shoppable_Videos {
    const POST_TYPE = 'superwoo_video';
    const COLLECTION = 'superwoo_video_collection';
    const EVENT_TABLE = 'superwoo_video_events';

    public function hooks() {
        add_action('init', [$this, 'register_content']);
        add_action('admin_init', [$this, 'maybe_install_analytics']);
        add_action('init', [$this, 'schedule_analytics_cleanup']);
        add_action('superwoo_video_cleanup_analytics', [$this, 'cleanup_analytics']);
        add_action('admin_menu', [$this, 'analytics_menu']);
        add_filter('post_row_actions', [$this, 'video_row_actions'], 10, 2);
        add_action('admin_post_superwoo_duplicate_video', [$this, 'duplicate_video']);
        add_action('admin_post_superwoo_disable_video', [$this, 'disable_video']);
        add_action('add_meta_boxes', [$this, 'meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('woocommerce_after_single_product_summary', [$this, 'render_product_page_after_summary'], 6);
        add_action('woocommerce_after_single_product_summary', [$this, 'render_product_page_after_tabs'], 25);
        add_action('woocommerce_after_single_product_summary', [$this, 'render_product_page_before_related'], 45);
        add_shortcode('superwoo_shoppable_videos', [$this, 'shortcode']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
        add_action('wp_ajax_superwoo_video_search_products', [$this, 'search_products']);
        add_action('wp_ajax_superwoo_video_variations', [$this, 'variations']);
        add_action('wp_ajax_nopriv_superwoo_video_variations', [$this, 'variations']);
        add_action('wp_ajax_superwoo_video_event', [$this, 'track_event']);
        add_action('wp_ajax_nopriv_superwoo_video_event', [$this, 'track_event']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'cart_attribution'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'order_attribution'], 10, 4);
    }

    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . self::EVENT_TABLE;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,video_id bigint(20) unsigned NOT NULL,product_id bigint(20) unsigned NOT NULL DEFAULT 0,event_type varchar(32) NOT NULL,session_hash char(64) NOT NULL,created_at datetime NOT NULL,PRIMARY KEY (id),KEY video_event (video_id,event_type),KEY created_at (created_at)) {$charset_collate};");
        update_option('superwoo_video_analytics_schema', '1');
    }
    public function maybe_install_analytics() { if ('1' !== get_option('superwoo_video_analytics_schema')) { self::install(); } }
    public function schedule_analytics_cleanup() { if (!wp_next_scheduled('superwoo_video_cleanup_analytics')) { wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'superwoo_video_cleanup_analytics'); } }
    public function cleanup_analytics() { global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM ' . $wpdb->prefix . self::EVENT_TABLE . ' WHERE created_at < %s', gmdate('Y-m-d H:i:s', strtotime('-90 days')))); }
    public function analytics_menu() { add_submenu_page('superwoo-settings', __('Video Analytics', 'superwoo'), __('Video Analytics', 'superwoo'), 'manage_woocommerce', 'superwoo-video-analytics', [$this, 'analytics_page']); }

    public function register_content() {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name' => __('Shoppable Videos', 'superwoo'), 'singular_name' => __('Shoppable Video', 'superwoo'), 'add_new_item' => __('Add Shoppable Video', 'superwoo'), 'edit_item' => __('Edit Shoppable Video', 'superwoo')],
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'superwoo-settings',
            'supports' => ['title', 'thumbnail'], 'map_meta_cap' => true, 'menu_icon' => 'dashicons-video-alt3',
        ]);
        register_taxonomy(self::COLLECTION, self::POST_TYPE, [
            'labels' => ['name' => __('Video Collections', 'superwoo'), 'singular_name' => __('Video Collection', 'superwoo')],
            'show_ui' => true, 'show_admin_column' => true, 'hierarchical' => false, 'rewrite' => false,
        ]);
    }
    public function video_row_actions($actions, $post) {
        if (self::POST_TYPE !== $post->post_type || !current_user_can('edit_post', $post->ID)) { return $actions; }
        $url = wp_nonce_url(admin_url('admin-post.php?action=superwoo_duplicate_video&video_id=' . $post->ID), 'superwoo_duplicate_video_' . $post->ID);
        $actions['superwoo_duplicate'] = '<a href="' . esc_url($url) . '">' . esc_html__('Duplicate', 'superwoo') . '</a>';
        if ('publish' === $post->post_status) {
            $disable_url = wp_nonce_url(admin_url('admin-post.php?action=superwoo_disable_video&video_id=' . $post->ID), 'superwoo_disable_video_' . $post->ID);
            $actions['superwoo_disable'] = '<a href="' . esc_url($disable_url) . '">' . esc_html__('Disable', 'superwoo') . '</a>';
        }
        return $actions;
    }
    public function duplicate_video() {
        $id = absint($_GET['video_id'] ?? 0);
        if (!$id || !current_user_can('edit_post', $id) || !check_admin_referer('superwoo_duplicate_video_' . $id)) { wp_die(esc_html__('You are not allowed to duplicate this video.', 'superwoo')); }
        $original = get_post($id);
        if (!$original || self::POST_TYPE !== $original->post_type) { wp_die(esc_html__('Video not found.', 'superwoo')); }
        $new_id = wp_insert_post(['post_type' => self::POST_TYPE, 'post_status' => 'draft', 'post_title' => sprintf(__('%s (Copy)', 'superwoo'), $original->post_title)]);
        foreach (get_post_meta($id) as $key => $values) { foreach ($values as $value) { add_post_meta($new_id, $key, maybe_unserialize($value)); } }
        $terms = wp_get_object_terms($id, self::COLLECTION, ['fields' => 'ids']); if (!is_wp_error($terms)) { wp_set_object_terms($new_id, $terms, self::COLLECTION); }
        wp_safe_redirect(get_edit_post_link($new_id, 'url')); exit;
    }
    public function disable_video() {
        $id = absint($_GET['video_id'] ?? 0);
        if (!$id || !current_user_can('edit_post', $id) || !check_admin_referer('superwoo_disable_video_' . $id)) { wp_die(esc_html__('You are not allowed to disable this video.', 'superwoo')); }
        if (self::POST_TYPE === get_post_type($id)) { wp_update_post(['ID' => $id, 'post_status' => 'draft']); }
        wp_safe_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE)); exit;
    }

    public function meta_boxes() { add_meta_box('superwoo-video-commerce', __('Video Commerce', 'superwoo'), [$this, 'meta_box'], self::POST_TYPE, 'normal', 'high'); }

    public function meta_box($post) {
        $data = $this->data($post->ID);
        wp_nonce_field('superwoo_video_save', 'superwoo_video_nonce');
        ?>
        <p><label><strong><?php esc_html_e('Video source', 'superwoo'); ?></strong><br><select name="superwoo_video_source"><option value="media" <?php selected($data['source'], 'media'); ?>><?php esc_html_e('WordPress Media / MP4', 'superwoo'); ?></option><option value="url" <?php selected($data['source'], 'url'); ?>><?php esc_html_e('Direct MP4/WebM URL', 'superwoo'); ?></option><option value="youtube" <?php selected($data['source'], 'youtube'); ?>>YouTube</option><option value="vimeo" <?php selected($data['source'], 'vimeo'); ?>>Vimeo</option></select></label></p>
        <p><label><?php esc_html_e('Media attachment ID', 'superwoo'); ?><br><input class="regular-text" type="number" id="superwoo_video_media_id" name="superwoo_video_media_id" value="<?php echo esc_attr($data['media_id']); ?>"> <button type="button" class="button" data-superwoo-media-target="superwoo_video_media_id" data-superwoo-media-type="video"><?php esc_html_e('Choose video', 'superwoo'); ?></button></label> <label><?php esc_html_e('Video URL', 'superwoo'); ?><br><input class="regular-text" type="url" name="superwoo_video_url" value="<?php echo esc_url($data['url']); ?>"></label></p>
        <p><label><?php esc_html_e('Poster attachment ID', 'superwoo'); ?><br><input type="number" id="superwoo_video_poster_id" name="superwoo_video_poster_id" value="<?php echo esc_attr($data['poster_id']); ?>"> <button type="button" class="button" data-superwoo-media-target="superwoo_video_poster_id" data-superwoo-media-type="image"><?php esc_html_e('Choose poster', 'superwoo'); ?></button></label> <label><?php esc_html_e('CTA text', 'superwoo'); ?><br><input class="regular-text" name="superwoo_video_cta" value="<?php echo esc_attr($data['cta']); ?>"></label></p>
        <p><label><input type="checkbox" name="superwoo_video_autoplay" value="1" <?php checked($data['autoplay']); ?>> <?php esc_html_e('Autoplay muted when visible', 'superwoo'); ?></label> &nbsp; <label><input type="checkbox" name="superwoo_video_muted" value="1" <?php checked($data['muted']); ?>> <?php esc_html_e('Muted', 'superwoo'); ?></label> &nbsp; <label><input type="checkbox" name="superwoo_video_loop" value="1" <?php checked($data['loop']); ?>> <?php esc_html_e('Loop', 'superwoo'); ?></label></p>
        <p><strong><?php esc_html_e('Linked products', 'superwoo'); ?></strong><br><input class="regular-text" id="superwoo-video-product-search" placeholder="<?php esc_attr_e('Search product or SKU', 'superwoo'); ?>"><button class="button" type="button" id="superwoo-video-add-product"><?php esc_html_e('Add product', 'superwoo'); ?></button></p>
        <ul id="superwoo-video-products"><?php foreach ($data['products'] as $item) : $product = wc_get_product($item['product_id']); if ($product) : ?><li data-product-id="<?php echo esc_attr($product->get_id()); ?>"><strong><?php echo esc_html($product->get_name()); ?></strong> <label><?php esc_html_e('Start', 'superwoo'); ?> <input type="number" step="0.1" class="superwoo-video-start" value="<?php echo esc_attr($item['start']); ?>"></label> <label><?php esc_html_e('End', 'superwoo'); ?> <input type="number" step="0.1" class="superwoo-video-end" value="<?php echo esc_attr($item['end']); ?>"></label> <button type="button" class="button-link-delete superwoo-video-remove-product"><?php esc_html_e('Remove', 'superwoo'); ?></button></li><?php endif; endforeach; ?></ul>
        <input type="hidden" id="superwoo-video-products-value" name="superwoo_video_products" value="<?php echo esc_attr(wp_json_encode($data['products'])); ?>">
        <p class="description"><?php esc_html_e('Products are read live from WooCommerce; stored timing data is ready for timed product cards.', 'superwoo'); ?></p>
        <?php
    }

    public function save($post_id, $post) {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id) || empty($_POST['superwoo_video_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['superwoo_video_nonce'])), 'superwoo_video_save')) { return; }
        $source = sanitize_key(wp_unslash($_POST['superwoo_video_source'] ?? 'media'));
        update_post_meta($post_id, '_superwoo_video_source', in_array($source, ['media', 'url', 'youtube', 'vimeo'], true) ? $source : 'media');
        update_post_meta($post_id, '_superwoo_video_media_id', absint($_POST['superwoo_video_media_id'] ?? 0));
        update_post_meta($post_id, '_superwoo_video_url', esc_url_raw(wp_unslash($_POST['superwoo_video_url'] ?? '')));
        update_post_meta($post_id, '_superwoo_video_poster_id', absint($_POST['superwoo_video_poster_id'] ?? 0));
        update_post_meta($post_id, '_superwoo_video_cta', sanitize_text_field(wp_unslash($_POST['superwoo_video_cta'] ?? 'Shop now')));
        foreach (['autoplay', 'muted', 'loop'] as $key) { update_post_meta($post_id, '_superwoo_video_' . $key, !empty($_POST['superwoo_video_' . $key]) ? 'yes' : ''); }
        $raw = json_decode(wp_unslash($_POST['superwoo_video_products'] ?? '[]'), true); $products = [];
        foreach (is_array($raw) ? $raw : [] as $item) { $id = absint($item['product_id'] ?? 0); if ($id && wc_get_product($id)) { $products[] = ['product_id' => $id, 'start' => max(0, (float) ($item['start'] ?? 0)), 'end' => max(0, (float) ($item['end'] ?? 0))]; } }
        update_post_meta($post_id, '_superwoo_video_products', $products);
    }

    public function admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || self::POST_TYPE !== get_post_type()) { return; }
        wp_enqueue_media();
        wp_enqueue_script('superwoo-shoppable-videos-admin', SUPERWOO_URL . 'public/js/shoppable-videos-admin.js', ['jquery', 'jquery-ui-sortable'], SUPERWOO_VERSION, true);
        wp_localize_script('superwoo-shoppable-videos-admin', 'SuperWooVideoAdmin', ['ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('superwoo_video_admin')]);
    }
    public function assets() { wp_register_style('superwoo-shoppable-videos', SUPERWOO_URL . 'public/css/shoppable-videos.css', [], SUPERWOO_VERSION); wp_register_style('superwoo-shoppable-video-viewer', SUPERWOO_URL . 'public/css/shoppable-video-viewer.css', [], SUPERWOO_VERSION); wp_register_style('superwoo-shoppable-video-timed', SUPERWOO_URL . 'public/css/shoppable-video-timed.css', [], SUPERWOO_VERSION); wp_register_script('superwoo-shoppable-videos', SUPERWOO_URL . 'public/js/shoppable-videos.js', ['jquery'], SUPERWOO_VERSION, true); }

    public function shortcode($atts) {
        $atts = shortcode_atts(['collection' => '', 'layout' => 'reels', 'videos' => '', 'limit' => 12], $atts, 'superwoo_shoppable_videos');
        $ids = array_filter(array_map('absint', explode(',', (string) $atts['videos']))); $layouts = ['reels', 'stories', 'carousel', 'grid', 'inline', 'side-products', 'bubbles'];
        return $this->render(['collection' => sanitize_title($atts['collection']), 'layout' => in_array($atts['layout'], $layouts, true) ? $atts['layout'] : 'reels', 'ids' => $ids, 'limit' => min(50, max(1, absint($atts['limit'])))]);
    }
    public function register_elementor_widget($manager) {
        if (!class_exists('Elementor\\Widget_Base')) { return; }
        require_once SUPERWOO_PATH . 'includes/class-elementor-shoppable-videos-widget.php';
        $manager->register(new SuperWoo_Elementor_Shoppable_Videos_Widget());
    }
    public function render($args) {
        $query_args = ['post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => absint($args['limit'])];
        if (!empty($args['ids'])) { $query_args['post__in'] = $args['ids']; $query_args['orderby'] = 'post__in'; }
        if (!empty($args['collection'])) { $query_args['tax_query'] = [['taxonomy' => self::COLLECTION, 'field' => 'slug', 'terms' => $args['collection']]]; }
        if (!empty($args['product_id'])) { $query_args['meta_query'] = [['key' => '_superwoo_video_products', 'value' => '"product_id";i:' . absint($args['product_id']) . ';', 'compare' => 'LIKE']]; }
        $query = new WP_Query($query_args);
        if (!$query->have_posts()) { return ''; }
        $videos = []; foreach ($query->posts as $post) { $videos[] = $this->data($post->ID); }
        wp_enqueue_style('superwoo-shoppable-videos'); wp_enqueue_style('superwoo-shoppable-video-viewer'); wp_enqueue_style('superwoo-shoppable-video-timed'); wp_enqueue_script('superwoo-shoppable-videos');
        $settings = superwoo_get_settings();
        return superwoo_template('shoppable-videos.php', ['videos' => $videos, 'layout' => $args['layout'], 'nonce' => wp_create_nonce('superwoo_video_frontend'), 'quick_buy' => !empty($settings['shoppable_videos_quick_buy']), 'fullscreen' => !empty($settings['shoppable_videos_fullscreen'])]);
    }
    public function render_product_page_after_summary() { $this->render_product_page_videos('after_summary'); }
    public function render_product_page_after_tabs() { $this->render_product_page_videos('after_tabs'); }
    public function render_product_page_before_related() { $this->render_product_page_videos('before_related'); }
    public function render_product_page_videos($position) {
        $settings = superwoo_get_settings();
        if (empty($settings['shoppable_videos_product_page']) || ($settings['shoppable_videos_product_position'] ?? 'after_summary') !== $position || !is_singular('product')) { return; }
        global $product;
        if (!$product instanceof WC_Product) { return; }
        static $rendered = false;
        if ($rendered) { return; }
        $rendered = true;
        echo $this->render(['collection' => '', 'layout' => 'carousel', 'ids' => [], 'limit' => 12, 'product_id' => $product->get_id()]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    public function data($id) {
        $settings = superwoo_get_settings();
        $source = get_post_meta($id, '_superwoo_video_source', true) ?: 'media';
        $media = absint(get_post_meta($id, '_superwoo_video_media_id', true));
        $autoplay = get_post_meta($id, '_superwoo_video_autoplay', true);
        $muted = get_post_meta($id, '_superwoo_video_muted', true);
        return ['id' => absint($id), 'title' => get_the_title($id), 'source' => $source, 'url' => 'media' === $source && $media ? wp_get_attachment_url($media) : get_post_meta($id, '_superwoo_video_url', true), 'poster' => wp_get_attachment_image_url(absint(get_post_meta($id, '_superwoo_video_poster_id', true)), 'large') ?: get_the_post_thumbnail_url($id, 'large'), 'cta' => get_post_meta($id, '_superwoo_video_cta', true) ?: __('Shop now', 'superwoo'), 'autoplay' => '' === $autoplay ? !empty($settings['shoppable_videos_autoplay']) : 'yes' === $autoplay, 'muted' => '' === $muted ? !empty($settings['shoppable_videos_muted']) : 'yes' === $muted, 'loop' => 'yes' === get_post_meta($id, '_superwoo_video_loop', true), 'products' => (array) get_post_meta($id, '_superwoo_video_products', true)];
    }
    public function search_products() {
        if (!current_user_can('manage_woocommerce') || !check_ajax_referer('superwoo_video_admin', 'nonce', false)) { wp_send_json_error([], 403); }
        $term = sanitize_text_field(wp_unslash($_GET['term'] ?? ''));
        if ('' === $term) { wp_send_json_success(['results' => []]); }
        $products = wc_get_products(['s' => $term, 'limit' => 20]);
        $sku_match = wc_get_product_id_by_sku($term);
        if ($sku_match) { $products[] = wc_get_product($sku_match); }
        $items = [];
        foreach ($products as $product) {
            if (!$product || isset($items[$product->get_id()])) { continue; }
            $sku = $product->get_sku();
            $items[$product->get_id()] = ['id' => $product->get_id(), 'text' => $product->get_name() . ($sku ? ' (SKU: ' . $sku . ')' : '')];
        }
        wp_send_json_success(['results' => array_values($items)]);
    }
    public function variations() {
        if (!check_ajax_referer('superwoo_video_frontend', 'nonce', false)) { wp_send_json_error([], 403); }
        $product = wc_get_product(absint($_POST['product_id'] ?? 0));
        if (!$product || !$product->is_type('variable')) { wp_send_json_error([], 400); }
        $items = [];
        foreach ($product->get_available_variations() as $variation) { if (!empty($variation['is_purchasable']) && !empty($variation['is_in_stock'])) { $items[] = ['id' => absint($variation['variation_id']), 'attributes' => $variation['attributes']]; } }
        wp_send_json_success(['attributes' => $product->get_variation_attributes(), 'variations' => $items]);
    }
    public function track_event() {
        if (!check_ajax_referer('superwoo_video_frontend', 'nonce', false)) { wp_send_json_error([], 403); }
        $video = absint($_POST['video_id'] ?? 0); $type = sanitize_key($_POST['event_type'] ?? '');
        $allowed = ['impression','start','watch_25','watch_50','watch_75','complete','product_view','product_click','cart_add','viewer_open','viewer_close'];
        if (!$video || self::POST_TYPE !== get_post_type($video) || !in_array($type, $allowed, true)) { wp_send_json_error([], 400); }
        global $wpdb; $session = function_exists('WC') && WC()->session ? WC()->session->get_customer_id() : wp_get_session_token();
        $wpdb->insert($wpdb->prefix . self::EVENT_TABLE, ['video_id' => $video, 'product_id' => absint($_POST['product_id'] ?? 0), 'event_type' => $type, 'session_hash' => hash_hmac('sha256', (string) $session, wp_salt('nonce')), 'created_at' => current_time('mysql', true)], ['%d','%d','%s','%s','%s']);
        wp_send_json_success();
    }
    public function cart_attribution($data, $product_id, $variation_id) { $video_id = absint($_REQUEST['superwoo_video_id'] ?? 0); if ($video_id && self::POST_TYPE === get_post_type($video_id)) { $data['superwoo_video_id'] = $video_id; } return $data; }
    public function order_attribution($item, $key, $values, $order) { if (!empty($values['superwoo_video_id'])) { $item->add_meta_data('_superwoo_video_id', absint($values['superwoo_video_id']), true); } }
    public function analytics_page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        global $wpdb; $table = $wpdb->prefix . self::EVENT_TABLE;
        $rows = $wpdb->get_results("SELECT video_id,SUM(event_type='impression') views,SUM(event_type='product_click') clicks,SUM(event_type='cart_add') cart_adds,SUM(event_type='complete') completes FROM {$table} GROUP BY video_id ORDER BY views DESC LIMIT 30"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        echo '<div class="wrap"><h1>' . esc_html__('Shoppable Video Analytics', 'superwoo') . '</h1><p>' . esc_html__('Only pseudonymous, event-level engagement is stored. Records are automatically removed after 90 days.', 'superwoo') . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__('Video', 'superwoo') . '</th><th>' . esc_html__('Views', 'superwoo') . '</th><th>' . esc_html__('Product clicks', 'superwoo') . '</th><th>' . esc_html__('Added to cart', 'superwoo') . '</th><th>' . esc_html__('Completed', 'superwoo') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr><td>' . esc_html(get_the_title($row->video_id)) . '</td><td>' . esc_html($row->views) . '</td><td>' . esc_html($row->clicks) . '</td><td>' . esc_html($row->cart_adds) . '</td><td>' . esc_html($row->completes) . '</td></tr>'; }
        if (!$rows) { echo '<tr><td colspan="5">' . esc_html__('No activity yet.', 'superwoo') . '</td></tr>'; } echo '</tbody></table></div>';
    }
}

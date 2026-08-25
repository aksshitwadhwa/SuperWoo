<?php
defined('ABSPATH') || exit;

class SuperWoo_Product_Reviews {
    public function hooks() {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_reviews'])) {
            return;
        }

        add_filter('woocommerce_product_tabs', [$this, 'replace_reviews_tab'], 98);
        add_shortcode('superwoo_product_reviews', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('comment_post', [$this, 'save_review_images'], 20, 3);
    }

    public function replace_reviews_tab($tabs) {
        if (isset($tabs['reviews'])) {
            $tabs['reviews']['callback'] = [$this, 'render_reviews_tab'];
            $tabs['reviews']['title'] = __('Reviews', 'superwoo');
        }

        return $tabs;
    }

    public function render_reviews_tab() {
        global $product;

        if (!$product instanceof WC_Product) {
            return;
        }

        echo $this->render($product->get_id()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(['id' => 0], $atts);
        $product_id = absint($atts['id']);

        if (!$product_id && is_singular('product')) {
            $product_id = get_the_ID();
        }

        if (!$product_id) {
            global $product;
            if ($product instanceof WC_Product) {
                $product_id = $product->get_id();
            }
        }

        return $product_id ? $this->render($product_id) : '';
    }

    public function register_assets() {
        wp_register_style('superwoo-reviews', SUPERWOO_URL . 'public/css/reviews.css', [], SUPERWOO_VERSION);
        wp_register_script('superwoo-reviews', SUPERWOO_URL . 'public/js/reviews.js', [], SUPERWOO_VERSION, true);

        if (is_singular('product')) {
            wp_enqueue_style('superwoo-reviews');
            wp_enqueue_script('superwoo-reviews');
        }
    }

    public function render($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !comments_open($product_id) && 0 === (int) $product->get_review_count()) {
            return '';
        }

        $reviews = $this->get_reviews($product_id);
        $summary = $this->get_summary($product, $reviews);

        wp_enqueue_style('superwoo-reviews');
        wp_enqueue_script('superwoo-reviews');

        return superwoo_template('product-reviews.php', [
            'product'        => $product,
            'reviews'        => $reviews,
            'summary'        => $summary,
            'review_form'    => $this->get_review_form_html($product),
            'write_enabled'  => comments_open($product_id),
        ]);
    }

    private function get_reviews($product_id) {
        $comments = get_comments([
            'post_id' => $product_id,
            'status'  => 'approve',
            'type'    => 'review',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'number'  => 0,
        ]);

        if (empty($comments)) {
            $comments = get_comments([
                'post_id' => $product_id,
                'status'  => 'approve',
                'orderby' => 'comment_date_gmt',
                'order'   => 'DESC',
                'number'  => 0,
            ]);
        }

        $reviews = [];
        foreach ($comments as $comment) {
            $rating = (int) get_comment_meta($comment->comment_ID, 'rating', true);
            if ($rating < 1 || $rating > 5) {
                $rating = 0;
            }

            $content = trim(wp_strip_all_tags($comment->comment_content));
            $title = $this->get_review_title($comment, $content);
            $images = $this->get_review_images($comment->comment_ID);
            $videos = $this->get_review_videos($comment->comment_ID);

            $reviews[] = [
                'id'        => (int) $comment->comment_ID,
                'author'    => $comment->comment_author ? $comment->comment_author : __('Customer', 'superwoo'),
                'date'      => mysql2date(get_option('date_format'), $comment->comment_date),
                'timestamp' => strtotime($comment->comment_date_gmt),
                'rating'    => $rating,
                'title'     => $title,
                'content'   => $content,
                'images'    => $images,
                'videos'    => $videos,
                'verified'  => function_exists('wc_review_is_from_verified_owner') ? wc_review_is_from_verified_owner($comment->comment_ID) : false,
            ];
        }

        return $reviews;
    }

    private function get_summary($product, $reviews) {
        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $image_reviews = [];

        foreach ($reviews as $review) {
            if (!empty($breakdown[$review['rating']])) {
                $breakdown[$review['rating']] += 1;
            } elseif (isset($breakdown[$review['rating']])) {
                $breakdown[$review['rating']] = 1;
            }

            if (!empty($review['images'])) {
                foreach ($review['images'] as $image) {
                    $image_reviews[] = $image;
                }
            }
        }

        $count = count($reviews);
        $average = $product->get_average_rating();
        if (!$average && $count) {
            $total = 0;
            foreach ($reviews as $review) {
                $total += (int) $review['rating'];
            }
            $average = $total ? $total / $count : 0;
        }

        return [
            'average'      => $average ? number_format((float) $average, 1) : '0.0',
            'count'        => $count,
            'breakdown'    => $breakdown,
            'image_thumbs' => array_slice($image_reviews, 0, 8),
        ];
    }

    private function get_review_title($comment, $content) {
        $meta_keys = ['title', 'review_title', 'ivole_review_title', 'cr_review_title'];
        foreach ($meta_keys as $key) {
            $value = get_comment_meta($comment->comment_ID, $key, true);
            if (is_string($value) && '' !== trim($value)) {
                return sanitize_text_field($value);
            }
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $content);
        $first = is_array($sentences) && !empty($sentences[0]) ? $sentences[0] : $content;
        return wp_trim_words($first, 8, '');
    }

    private function get_review_images($comment_id) {
        $meta = get_comment_meta($comment_id);
        $image_keys = [
            'superwoo_review_images',
            'reviews-images',
            'reviews_images',
            'review_images',
            'ivole_review_image',
            'ivole_review_images',
            'cr_review_image',
            'cr_review_images',
            'comment_image',
            'comment_images',
            'photo',
            'photos',
            'attachment_id',
            'attachment_ids',
        ];

        $images = [];
        foreach ($image_keys as $key) {
            if (empty($meta[$key])) {
                continue;
            }

            foreach ((array) $meta[$key] as $raw_value) {
                $images = array_merge($images, $this->normalize_image_value($raw_value));
            }
        }

        $unique = [];
        foreach ($images as $image) {
            if (empty($image['src']) || isset($unique[$image['src']])) {
                continue;
            }
            $unique[$image['src']] = $image;
        }

        return array_values($unique);
    }

    private function get_review_videos($comment_id) {
        $meta = get_comment_meta($comment_id);
        $video_keys = [
            'superwoo_review_videos',
            'review_videos',
            'reviews_videos',
            'video',
            'videos',
            'comment_video',
            'comment_videos',
        ];

        $videos = [];
        foreach ($video_keys as $key) {
            if (empty($meta[$key])) {
                continue;
            }

            foreach ((array) $meta[$key] as $raw_value) {
                $videos = array_merge($videos, $this->normalize_video_value($raw_value));
            }
        }

        $unique = [];
        foreach ($videos as $video) {
            if (empty($video['src']) || isset($unique[$video['src']])) {
                continue;
            }
            $unique[$video['src']] = $video;
        }

        return array_values($unique);
    }

    private function normalize_image_value($value) {
        $decoded = maybe_unserialize($value);
        if (is_string($decoded) && $this->looks_like_json($decoded)) {
            $json = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $json;
            }
        }

        if (is_string($decoded) && false !== strpos($decoded, ',')) {
            $decoded = array_map('trim', explode(',', $decoded));
        }

        if (is_array($decoded)) {
            $images = [];
            foreach ($decoded as $item) {
                $images = array_merge($images, $this->normalize_image_value($item));
            }
            return $images;
        }

        if (is_numeric($decoded)) {
            $src = wp_get_attachment_image_url((int) $decoded, 'woocommerce_thumbnail');
            $full = wp_get_attachment_image_url((int) $decoded, 'large');
            return $src ? [[
                'src'  => $src,
                'full' => $full ? $full : $src,
                'alt'  => get_post_meta((int) $decoded, '_wp_attachment_image_alt', true),
            ]] : [];
        }

        if (is_string($decoded) && preg_match('#^https?://#i', $decoded)) {
            return [[
                'src'  => esc_url_raw($decoded),
                'full' => esc_url_raw($decoded),
                'alt'  => '',
            ]];
        }

        return [];
    }

    private function normalize_video_value($value) {
        $decoded = maybe_unserialize($value);
        if (is_string($decoded) && $this->looks_like_json($decoded)) {
            $json = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $json;
            }
        }

        if (is_string($decoded) && false !== strpos($decoded, ',')) {
            $decoded = array_map('trim', explode(',', $decoded));
        }

        if (is_array($decoded)) {
            $videos = [];
            foreach ($decoded as $item) {
                $videos = array_merge($videos, $this->normalize_video_value($item));
            }
            return $videos;
        }

        if (is_numeric($decoded)) {
            $attachment_id = (int) $decoded;
            $src = wp_get_attachment_url($attachment_id);
            $mime = get_post_mime_type($attachment_id);
            return $src && $this->is_video_mime($mime) ? [[
                'src'   => $src,
                'type'  => $mime,
                'title' => get_the_title($attachment_id),
            ]] : [];
        }

        if (is_string($decoded) && preg_match('#^https?://#i', $decoded)) {
            return [[
                'src'   => esc_url_raw($decoded),
                'type'  => $this->video_mime_from_url($decoded),
                'title' => '',
            ]];
        }

        return [];
    }

    private function is_video_mime($mime) {
        return is_string($mime) && 0 === strpos($mime, 'video/');
    }

    private function video_mime_from_url($url) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $ext = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';
        $types = [
            'mp4'  => 'video/mp4',
            'm4v'  => 'video/mp4',
            'webm' => 'video/webm',
            'ogv'  => 'video/ogg',
            'mov'  => 'video/quicktime',
        ];

        return isset($types[$ext]) ? $types[$ext] : '';
    }

    private function looks_like_json($value) {
        $value = trim($value);
        return (0 === strpos($value, '[') && substr($value, -1) === ']') || (0 === strpos($value, '{') && substr($value, -1) === '}');
    }

    private function get_review_form_html($product) {
        if (!comments_open($product->get_id())) {
            return '';
        }

        ob_start();

        $commenter = wp_get_current_commenter();
        $comment_form = [
            'title_reply'         => $product->get_review_count() ? __('Add a review', 'woocommerce') : sprintf(__('Be the first to review &ldquo;%s&rdquo;', 'woocommerce'), $product->get_name()),
            'title_reply_to'      => __('Leave a Reply to %s', 'woocommerce'),
            'title_reply_before'  => '<h3 id="reply-title" class="comment-reply-title">',
            'title_reply_after'   => '</h3>',
            'comment_notes_after' => '',
            'label_submit'        => __('Submit', 'woocommerce'),
            'logged_in_as'        => '',
            'comment_field'       => '',
            'fields'              => [
                'author' => '<p class="comment-form-author"><label for="author">' . esc_html__('Name', 'woocommerce') . '&nbsp;<span class="required">*</span></label><input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" required></p>',
                'email'  => '<p class="comment-form-email"><label for="email">' . esc_html__('Email', 'woocommerce') . '&nbsp;<span class="required">*</span></label><input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" required></p>',
            ],
        ];

        if (wc_review_ratings_enabled()) {
            $comment_form['comment_field'] .= '<p class="comment-form-rating"><label for="rating">' . esc_html__('Your rating', 'woocommerce') . (wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '') . '</label><select name="rating" id="rating" required>
                <option value="">' . esc_html__('Rate&hellip;', 'woocommerce') . '</option>
                <option value="5">' . esc_html__('Perfect', 'woocommerce') . '</option>
                <option value="4">' . esc_html__('Good', 'woocommerce') . '</option>
                <option value="3">' . esc_html__('Average', 'woocommerce') . '</option>
                <option value="2">' . esc_html__('Not that bad', 'woocommerce') . '</option>
                <option value="1">' . esc_html__('Very poor', 'woocommerce') . '</option>
            </select></p>';
        }

        $comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">' . esc_html__('Your review', 'woocommerce') . '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="6" required></textarea></p>';
        $comment_form['comment_field'] .= wp_nonce_field('superwoo_review_images_' . $product->get_id(), 'superwoo_review_images_nonce', true, false);
        $comment_form['comment_field'] .= '<p class="comment-form-superwoo-media"><label for="superwoo_review_media">' . esc_html__('Upload product photos or videos', 'superwoo') . '</label><input id="superwoo_review_media" name="superwoo_review_media[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/ogg" multiple data-superwoo-review-media><span class="superwoo-review-upload-help" data-superwoo-review-media-help>' . esc_html__('Optional. Upload up to 4 photos and 2 videos.', 'superwoo') . '</span></p>';

        comment_form(apply_filters('woocommerce_product_review_comment_form_args', $comment_form), $product->get_id());

        return ob_get_clean();
    }

    public function save_review_images($comment_id, $comment_approved, $commentdata) {
        $post_id = isset($commentdata['comment_post_ID']) ? absint($commentdata['comment_post_ID']) : 0;
        if (!$post_id || 'product' !== get_post_type($post_id)) {
            return;
        }

        $nonce = isset($_POST['superwoo_review_images_nonce']) ? sanitize_text_field(wp_unslash($_POST['superwoo_review_images_nonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'superwoo_review_images_' . $post_id)) {
            return;
        }

        $media_files = !empty($_FILES['superwoo_review_media']) && !empty($_FILES['superwoo_review_media']['name']) ? $this->normalize_uploaded_files($_FILES['superwoo_review_media']) : [];
        $legacy_image_files = !empty($_FILES['superwoo_review_images']) && !empty($_FILES['superwoo_review_images']['name']) ? $this->normalize_uploaded_files($_FILES['superwoo_review_images']) : [];
        $legacy_video_files = !empty($_FILES['superwoo_review_videos']) && !empty($_FILES['superwoo_review_videos']['name']) ? $this->normalize_uploaded_files($_FILES['superwoo_review_videos']) : [];
        $files_by_type = $this->split_review_media_files(array_merge($media_files, $legacy_image_files, $legacy_video_files));

        if (empty($files_by_type['images']) && empty($files_by_type['videos'])) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_ids = $this->upload_review_attachments($files_by_type['images'], $post_id, 4, 5 * MB_IN_BYTES, [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        ]);
        $video_ids = $this->upload_review_attachments($files_by_type['videos'], $post_id, 2, 50 * MB_IN_BYTES, [
            'mp4|m4v' => 'video/mp4',
            'webm'    => 'video/webm',
            'ogv'     => 'video/ogg',
            'mov|qt'  => 'video/quicktime',
        ]);

        if (!empty($image_ids)) {
            update_comment_meta($comment_id, 'superwoo_review_images', $image_ids);
        }

        if (!empty($video_ids)) {
            update_comment_meta($comment_id, 'superwoo_review_videos', $video_ids);
        }
    }

    private function split_review_media_files($files) {
        $split = [
            'images' => [],
            'videos' => [],
        ];

        foreach ($files as $file) {
            if (empty($file['name'])) {
                continue;
            }

            $type = !empty($file['type']) ? (string) $file['type'] : '';
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (0 === strpos($type, 'image/') || in_array($ext, ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp'], true)) {
                $split['images'][] = $file;
                continue;
            }

            if (0 === strpos($type, 'video/') || in_array($ext, ['mp4', 'm4v', 'webm', 'ogv', 'mov', 'qt'], true)) {
                $split['videos'][] = $file;
            }
        }

        return $split;
    }

    private function upload_review_attachments($files, $post_id, $max_files, $max_size, $allowed_mimes) {
        $attachment_ids = [];

        foreach (array_slice($files, 0, $max_files) as $file) {
            if (empty($file['name']) || !empty($file['error']) || empty($file['tmp_name'])) {
                continue;
            }

            if (!empty($file['size']) && $file['size'] > $max_size) {
                continue;
            }

            $upload = wp_handle_upload($file, [
                'test_form' => false,
                'mimes'     => $allowed_mimes,
            ]);

            if (empty($upload['file']) || !empty($upload['error'])) {
                continue;
            }

            $attachment_id = wp_insert_attachment([
                'post_mime_type' => $upload['type'],
                'post_title'     => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
                'post_content'   => '',
                'post_status'    => 'inherit',
                'post_parent'    => $post_id,
            ], $upload['file'], $post_id);

            if (is_wp_error($attachment_id) || !$attachment_id) {
                continue;
            }

            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
            $attachment_ids[] = (int) $attachment_id;
        }

        return $attachment_ids;
    }

    private function normalize_uploaded_files($files) {
        if (!is_array($files) || empty($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => isset($files['type'][$index]) ? $files['type'][$index] : '',
                'tmp_name' => isset($files['tmp_name'][$index]) ? $files['tmp_name'][$index] : '',
                'error'    => isset($files['error'][$index]) ? $files['error'][$index] : 0,
                'size'     => isset($files['size'][$index]) ? $files['size'][$index] : 0,
            ];
        }

        return $normalized;
    }
}

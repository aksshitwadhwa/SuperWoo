<?php
defined('ABSPATH') || exit;

class SuperWoo_Bundle_Offers {
    public function hooks() {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_bundle_offers'])) {
            return;
        }

        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_superwoo_get_bundle_offer_form', [$this, 'ajax_get_offer_form']);
        add_action('wp_ajax_superwoo_get_bundle_offers_list', [$this, 'ajax_get_offers_list']);
        add_action('wp_ajax_superwoo_json_search_products_and_variations', [$this, 'ajax_search_products']);
        add_action('wp_ajax_superwoo_save_bundle_offer', [$this, 'ajax_save_offer']);
        add_action('wp_ajax_superwoo_delete_bundle_offer', [$this, 'ajax_delete_offer']);
        add_action('wp_ajax_superwoo_toggle_bundle_offer', [$this, 'ajax_toggle_offer']);
        add_action('admin_post_superwoo_save_offer_settings', [$this, 'save_offer_settings']);
        add_action('woocommerce_before_calculate_totals', [$this, 'apply_discounts'], 20);
        add_filter('woocommerce_cart_item_name', [$this, 'gift_cart_item_name'], 10, 3);
        add_action('woocommerce_before_cart_table', [$this, 'render_notices']);
        add_shortcode('bundle_offers_notice', [$this, 'notice_shortcode']);
    }

    public function admin_menu() {
        add_submenu_page(
            'superwoo-settings',
            __('Offers', 'superwoo'),
            __('Offers', 'superwoo'),
            'manage_woocommerce',
            'superwoo-bundle-offers',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if ('superwoo_page_superwoo-bundle-offers' !== $hook) {
            return;
        }

        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_style('select2');
        wp_enqueue_script('selectWoo');
        wp_enqueue_script('wc-enhanced-select');
        wp_enqueue_script('superwoo-admin-bundle-offers', SUPERWOO_URL . 'public/js/admin-bundle-offers.js', ['jquery', 'selectWoo', 'wc-enhanced-select'], SUPERWOO_VERSION, true);
        wp_enqueue_style('superwoo-admin', SUPERWOO_URL . 'public/css/admin.css', [], SUPERWOO_VERSION);

        wp_localize_script('superwoo-admin-bundle-offers', 'SuperWooOffers', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('superwoo_bundle_offer_ajax'),
            'listUrl' => admin_url('admin.php?page=superwoo-bundle-offers'),
            'i18n'    => [
                'saving'  => __('Saving...', 'superwoo'),
                'saved'   => __('Offer saved.', 'superwoo'),
                'error'   => __('Could not save the offer. Please check the fields and try again.', 'superwoo'),
                'confirm' => __('Delete this offer?', 'superwoo'),
                'deleted' => __('Offer deleted.', 'superwoo'),
                'save'    => __('Save Offer', 'superwoo'),
                'loading' => __('Loading...', 'superwoo'),
            ],
        ]);
    }

    public function get_rules() {
        $rules = get_option('pbi_bundle_rules', []);
        if (!is_array($rules)) {
            return [];
        }

        $changed = false;
        foreach ($rules as $index => $rule) {
            if (!is_array($rule)) {
                unset($rules[$index]);
                $changed = true;
                continue;
            }

            if (empty($rule['id'])) {
                $rules[$index]['id'] = $this->new_rule_id();
                $changed = true;
            }

            if (!isset($rule['title'])) {
                $rules[$index]['title'] = '';
                $changed = true;
            }

            if ('price_gift' === ($rule['offer_type'] ?? '') && empty($rule['free_product_ids']) && !empty($rule['free_product_id'])) {
                $rules[$index]['free_product_ids'] = [absint($rule['free_product_id'])];
                $changed = true;
            }
        }

        $rules = array_values($rules);

        if ($changed) {
            update_option('pbi_bundle_rules', $rules);
        }

        return $rules;
    }

    public function get_rule_by_id($offer_id) {
        $offer_id = sanitize_key($offer_id);
        foreach ($this->get_rules() as $rule) {
            if (($rule['id'] ?? '') === $offer_id) {
                return $rule;
            }
        }

        return null;
    }

    public function get_default_rule() {
        return [
            'id'               => '',
            'title'            => '',
            'enabled'          => true,
            'offer_type'       => 'product_discount',
            'applies_to'       => 'products',
            'category_id'      => 0,
            'product_ids'      => [],
            'min_qty'          => '',
            'discount'         => '',
            'min_amount'       => '',
            'max_amount'       => '',
            'free_product_id'  => 0,
            'free_product_ids' => [],
        ];
    }

    public function get_free_delivery_threshold() {
        return (float) get_option('superwoo_offer_free_delivery_threshold', 0);
    }

    public function save_offer_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage offers.', 'superwoo'));
        }

        check_admin_referer('superwoo_save_offer_settings');

        $threshold = isset($_POST['superwoo_free_delivery_threshold']) ? (float) wc_format_decimal(wp_unslash($_POST['superwoo_free_delivery_threshold'])) : 0;
        $threshold = max(0, $threshold);

        if ($threshold > 0) {
            update_option('superwoo_offer_free_delivery_threshold', $threshold);
        } else {
            delete_option('superwoo_offer_free_delivery_threshold');
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'superwoo-bundle-offers',
            'settings-updated' => 'true',
        ], admin_url('admin.php')));
        exit;
    }

    public function get_offer_title($rule) {
        if (!empty($rule['title'])) {
            return $rule['title'];
        }

        if ('price_gift' === ($rule['offer_type'] ?? '')) {
            return __('Price range free products', 'superwoo');
        }

        return __('Flat product discount', 'superwoo');
    }

    public function get_offer_row_html($rule) {
        ob_start();
        include SUPERWOO_PATH . 'admin/views/bundle-offer-row.php';
        return ob_get_clean();
    }

    public function get_offer_rows_html() {
        $html = '';
        foreach ($this->get_rules() as $rule) {
            $html .= $this->get_offer_row_html($rule);
        }

        return $html;
    }

    public function get_inline_offer_form_html($rule, $editor_title = '') {
        if (!$editor_title) {
            $editor_title = !empty($rule['id']) ? __('Edit Offer', 'superwoo') : __('Create Offer', 'superwoo');
        }

        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        if (!is_array($categories)) {
            $categories = [];
        }

        ob_start();
        include SUPERWOO_PATH . 'admin/views/bundle-offer-inline-form.php';
        return ob_get_clean();
    }

    public function get_free_product_ids($rule) {
        if (!empty($rule['free_product_ids']) && is_array($rule['free_product_ids'])) {
            return array_values(array_unique(array_filter(array_map('absint', $rule['free_product_ids']))));
        }

        $free_product_id = absint($rule['free_product_id'] ?? 0);
        return $free_product_id ? [$free_product_id] : [];
    }

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ]);
        if (!is_array($categories)) {
            $categories = [];
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if (in_array($action, ['new', 'edit'], true)) {
            $offer_id = isset($_GET['offer']) ? sanitize_text_field(wp_unslash($_GET['offer'])) : '';
            $rule = 'edit' === $action ? $this->get_rule_by_id($offer_id) : $this->get_default_rule();
            if ('edit' === $action && !$rule) {
                echo '<div class="wrap superwoo-admin-page"><h1>' . esc_html__('Offer not found', 'superwoo') . '</h1><p><a class="button" href="' . esc_url(admin_url('admin.php?page=superwoo-bundle-offers')) . '">' . esc_html__('Back to Offers', 'superwoo') . '</a></p></div>';
                return;
            }

            include SUPERWOO_PATH . 'admin/views/bundle-offer-edit-page.php';
            return;
        }

        $rules = $this->get_rules();
        include SUPERWOO_PATH . 'admin/views/bundle-offers-page.php';
    }

    public function sanitize_rules($posted_rules) {
        $rules = [];
        if (!is_array($posted_rules)) {
            return $rules;
        }

        foreach (wp_unslash($posted_rules) as $rule_row) {
            if (!is_array($rule_row)) {
                continue;
            }

            $sanitized = $this->sanitize_rule($rule_row);
            if ($sanitized) {
                $rules[] = $sanitized;
            }
        }

        return $rules;
    }

    public function ajax_save_offer() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to manage offers.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $posted_rule = isset($_POST['pbi_bundle_rule']) ? wp_unslash($_POST['pbi_bundle_rule']) : [];
        if (!is_array($posted_rule)) {
            wp_send_json_error(['message' => __('Offer data is missing.', 'superwoo')], 400);
        }

        $offer_id = !empty($posted_rule['id']) ? sanitize_key($posted_rule['id']) : $this->new_rule_id();
        $rule = $this->sanitize_rule($posted_rule, $offer_id);
        if (!$rule) {
            wp_send_json_error(['message' => __('Please complete the required offer fields.', 'superwoo')], 400);
        }

        $rules = $this->get_rules();
        $updated = false;
        foreach ($rules as $index => $existing_rule) {
            if (($existing_rule['id'] ?? '') === $offer_id) {
                $rules[$index] = $rule;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $rules[] = $rule;
        }

        update_option('pbi_bundle_rules', array_values($rules));

        wp_send_json_success([
            'message' => __('Offer saved.', 'superwoo'),
            'offerId' => $rule['id'],
            'editUrl' => admin_url('admin.php?page=superwoo-bundle-offers&action=edit&offer=' . rawurlencode($rule['id'])),
            'listUrl' => admin_url('admin.php?page=superwoo-bundle-offers'),
            'rowHtml' => $this->get_offer_row_html($rule),
            'rowsHtml' => $this->get_offer_rows_html(),
            'offersCount' => count($this->get_rules()),
        ]);
    }

    public function ajax_get_offer_form() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to manage offers.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $offer_id = isset($_POST['offer_id']) ? sanitize_key(wp_unslash($_POST['offer_id'])) : '';
        $rule = $offer_id ? $this->get_rule_by_id($offer_id) : $this->get_default_rule();

        if (!$rule) {
            wp_send_json_error(['message' => __('Offer not found.', 'superwoo')], 404);
        }

        wp_send_json_success([
            'offerId'  => $rule['id'] ?? '',
            'formHtml' => $this->get_inline_offer_form_html($rule),
        ]);
    }

    public function ajax_get_offers_list() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to manage offers.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $rules = $this->get_rules();

        wp_send_json_success([
            'rowsHtml'   => $this->get_offer_rows_html(),
            'offersCount' => count($rules),
        ]);
    }

    public function ajax_search_products() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to search products.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        if (!$term && isset($_POST['term'])) {
            $term = sanitize_text_field(wp_unslash($_POST['term']));
        }

        if ('' === $term) {
            wp_send_json(['results' => []]);
        }

        $data_store = WC_Data_Store::load('product');
        $product_ids = $data_store->search_products($term, '', true, false, 20);
        $results = [];
        $seen = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $this->add_product_search_result($results, $seen, $variation);
                    }
                }
            } else {
                $this->add_product_search_result($results, $seen, $product);
            }

            if (count($results) >= 50) {
                break;
            }
        }

        wp_send_json(['results' => $results]);
    }

    private function add_product_search_result(&$results, &$seen, $product) {
        if (!$product || !($product instanceof WC_Product)) {
            return;
        }

        $product_id = $product->get_id();
        if (isset($seen[$product_id])) {
            return;
        }

        $seen[$product_id] = true;
        $results[] = [
            'id'    => $product_id,
            'text'  => $this->get_product_search_name($product),
            'image' => $this->get_product_image_url($product),
            'price' => $this->get_product_price_label($product),
        ];
    }

    public function get_product_search_name($product) {
        if (!$product || !($product instanceof WC_Product)) {
            return '';
        }

        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $parent_name = $parent ? $parent->get_name() : $product->get_name();
            $attributes = wc_get_formatted_variation($product, true, false, true);
            $attributes = html_entity_decode(wp_strip_all_tags((string) $attributes), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');

            return $attributes ? $parent_name . ' - ' . $attributes : $product->get_name();
        }

        return $product->get_name();
    }

    public function get_product_image_url($product) {
        if (!$product || !($product instanceof WC_Product)) {
            return wc_placeholder_img_src('thumbnail');
        }

        $image_id = $product->get_image_id();

        if (!$image_id && $product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            $image_id = $parent ? $parent->get_image_id() : 0;
        }

        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';

        return $image_url ? $image_url : wc_placeholder_img_src('thumbnail');
    }

    public function get_product_price_label($product) {
        if (!$product || !($product instanceof WC_Product)) {
            return '';
        }

        if ($product->is_type('variable')) {
            $min_price = $product->get_variation_price('min', true);
            $max_price = $product->get_variation_price('max', true);

            if ('' === $min_price && '' === $max_price) {
                return '';
            }

            if ((float) $min_price !== (float) $max_price) {
                return $this->clean_price_label(wc_price((float) $min_price) . ' - ' . wc_price((float) $max_price));
            }

            return $this->clean_price_label(wc_price((float) $min_price));
        }

        $price = wc_get_price_to_display($product);
        if ('' === $price) {
            return '';
        }

        return $this->clean_price_label(wc_price((float) $price));
    }

    private function clean_price_label($price_html) {
        return html_entity_decode(wp_strip_all_tags((string) $price_html), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    }

    public function ajax_delete_offer() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to manage offers.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $offer_id = isset($_POST['offer_id']) ? sanitize_key(wp_unslash($_POST['offer_id'])) : '';
        if (!$offer_id) {
            wp_send_json_error(['message' => __('Offer ID is missing.', 'superwoo')], 400);
        }

        $rules = array_values(array_filter($this->get_rules(), function ($rule) use ($offer_id) {
            return ($rule['id'] ?? '') !== $offer_id;
        }));

        update_option('pbi_bundle_rules', $rules);

        wp_send_json_success([
            'message'    => __('Offer deleted.', 'superwoo'),
            'rowsHtml'   => $this->get_offer_rows_html(),
            'offersCount' => count($rules),
        ]);
    }

    public function ajax_toggle_offer() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to manage offers.', 'superwoo')], 403);
        }

        check_ajax_referer('superwoo_bundle_offer_ajax', 'nonce');

        $offer_id = isset($_POST['offer_id']) ? sanitize_key(wp_unslash($_POST['offer_id'])) : '';
        $enabled = !empty($_POST['enabled']);
        $rules = $this->get_rules();
        $found = false;

        foreach ($rules as $index => $rule) {
            if (($rule['id'] ?? '') !== $offer_id) {
                continue;
            }

            $rules[$index]['enabled'] = $enabled;
            $found = true;
            break;
        }

        if (!$found) {
            wp_send_json_error(['message' => __('Offer not found.', 'superwoo')], 404);
        }

        update_option('pbi_bundle_rules', $rules);

        wp_send_json_success([
            'message'    => __('Offer updated.', 'superwoo'),
            'rowsHtml'   => $this->get_offer_rows_html(),
            'offersCount' => count($rules),
        ]);
    }

    private function sanitize_rule($rule_row, $offer_id = '') {
        if (!is_array($rule_row)) {
            return null;
        }

            $offer_type = !empty($rule_row['offer_type']) && 'price_gift' === $rule_row['offer_type'] ? 'price_gift' : 'product_discount';
            $applies_to = $this->sanitize_applies_to($rule_row['applies_to'] ?? 'products');
            $category_id = 'category' === $applies_to ? absint($rule_row['category_id'] ?? 0) : 0;
            $product_ids = [];
            if (!empty($rule_row['product_ids']) && is_array($rule_row['product_ids'])) {
                $product_ids = array_values(array_unique(array_filter(array_map('absint', $rule_row['product_ids']))));
            }

            if ('category' === $applies_to && $category_id <= 0) {
                return null;
            }

            if ('products' === $applies_to && empty($product_ids)) {
                return null;
            }

            $base_rule = [
                'id'          => $offer_id ? sanitize_key($offer_id) : $this->new_rule_id(),
                'title'       => !empty($rule_row['title']) ? sanitize_text_field($rule_row['title']) : '',
                'enabled'     => !empty($rule_row['enabled']),
                'offer_type'  => $offer_type,
                'applies_to'  => $applies_to,
                'category_id' => $category_id,
                'product_ids' => $product_ids,
            ];

            if ('product_discount' === $offer_type) {
                $min_qty  = absint($rule_row['min_qty'] ?? 0);
                $discount = (float) wc_format_decimal($rule_row['discount'] ?? 0);

                if ($min_qty > 0 && $discount > 0 && $discount <= 100) {
                    return array_merge($base_rule, [
                        'min_qty'     => $min_qty,
                        'discount'    => $discount,
                    ]);
                }

                return null;
            }

            $min_amount = (float) wc_format_decimal($rule_row['min_amount'] ?? 0);
            $max_amount = (float) wc_format_decimal($rule_row['max_amount'] ?? 0);
            $free_product_ids = $this->sanitize_free_product_ids($rule_row);

            if ($min_amount >= 0 && (0.0 === $max_amount || $max_amount >= $min_amount) && !empty($free_product_ids)) {
                return array_merge($base_rule, [
                    'min_amount'      => $min_amount,
                    'max_amount'      => $max_amount,
                    'free_product_id' => $free_product_ids[0],
                    'free_product_ids' => $free_product_ids,
                ]);
            }

        return null;
    }

    private function sanitize_free_product_ids($rule_row) {
        $free_product_ids = [];

        if (!empty($rule_row['free_product_ids']) && is_array($rule_row['free_product_ids'])) {
            $free_product_ids = array_map('absint', $rule_row['free_product_ids']);
        } elseif (!empty($rule_row['free_product_id'])) {
            $free_product_ids = [absint($rule_row['free_product_id'])];
        }

        return array_values(array_unique(array_filter($free_product_ids)));
    }

    private function new_rule_id() {
        return sanitize_key(uniqid('offer_', false));
    }

    public function apply_discounts($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Razorpay 1CC must receive the product's catalog price. Applying a
        // cart offer while its order endpoint is building the payment payload
        // can send a discounted/stale amount instead of the real price.
        if ($this->is_payment_request()) {
            return;
        }

        if (!$cart || $cart->is_empty()) {
            return;
        }

        $rules = $this->get_rules();
        if (empty($rules)) {
            return;
        }

        $customer_quantities = $this->get_customer_cart_quantities($cart);
        $discount_matches = [];
        $gift_matches = [];

        foreach ($rules as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }

            if ($this->is_legacy_rule($rule)) {
                $qty  = $this->get_cart_qty_for_rule($cart, $rule);
                $tier = $this->get_matched_tier($rule['tiers'], $qty);
                if ($tier) {
                    $discount_matches[] = [
                        'rule' => $rule,
                        'tier' => $tier,
                    ];
                }
                continue;
            }

            if ('price_gift' === ($rule['offer_type'] ?? '')) {
                if ($this->cart_subtotal_in_price_range($cart, $rule)) {
                    $gift_matches[] = $rule;
                }
                continue;
            }

            if ('product_discount' === ($rule['offer_type'] ?? 'product_discount')) {
                $qty = $this->get_cart_qty_for_offer_rule($cart, $rule);
                if ($qty >= (int) ($rule['min_qty'] ?? 0)) {
                    $discount_matches[] = [
                        'rule' => $rule,
                        'tier' => [
                            'discount' => (float) ($rule['discount'] ?? 0),
                        ],
                    ];
                }
            }
        }

        $this->sync_free_gifts($cart, $gift_matches);

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
                continue;
            }

            $product = $cart_item['data'];

            if (!empty($cart_item['superwoo_free_gift'])) {
                if ((int) ($cart_item['quantity'] ?? 1) !== 1) {
                    $cart->set_quantity($cart_item_key, 1, false);
                }
                $product->set_price(0);
                continue;
            }

            $product_id = !empty($cart_item['product_id']) ? absint($cart_item['product_id']) : $product->get_id();
            $variation_id = !empty($cart_item['variation_id']) ? absint($cart_item['variation_id']) : 0;
            $best_discount = $this->get_best_discount_for_product($product_id, $discount_matches, $variation_id);

            if ($best_discount <= 0) {
                continue;
            }

            $regular_price = (float) $product->get_regular_price();
            $current_price = (float) $product->get_price();

            if ($regular_price <= 0 || $current_price <= 0) {
                continue;
            }

            $bundle_price = round($regular_price * (1 - $best_discount / 100), wc_get_price_decimals());
            if ($bundle_price < $current_price) {
                $product->set_price($bundle_price);
            }
        }

        $this->restore_customer_cart_quantities($cart, $customer_quantities);
    }

    private function is_payment_request() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $rest_route = defined('REST_REQUEST') && REST_REQUEST && isset($_REQUEST['rest_route'])
            ? sanitize_text_field(wp_unslash($_REQUEST['rest_route']))
            : '';

        return false !== strpos($request_uri, '/1cc/v1/order/create') || false !== strpos($rest_route, '/1cc/v1/order/create');
    }

    public function render_notices() {
        if (!wp_style_is('superwoo-bundle-offers', 'enqueued')) {
            wp_enqueue_style('superwoo-bundle-offers', SUPERWOO_URL . 'public/css/bundle-offers.css', [], SUPERWOO_VERSION);
        }
        echo $this->get_notices_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function notice_shortcode() {
        if (!wp_style_is('superwoo-bundle-offers', 'enqueued')) {
            wp_enqueue_style('superwoo-bundle-offers', SUPERWOO_URL . 'public/css/bundle-offers.css', [], SUPERWOO_VERSION);
        }
        return $this->get_notices_html();
    }

    public function get_notices_html() {
        if (!WC()->cart) {
            return '';
        }

        $rules = $this->get_rules();
        if (empty($rules)) {
            return '';
        }

        $notices = [];
        foreach ($this->get_contextual_notice_rules(WC()->cart, $rules) as $rule) {
            $html = superwoo_template('offer-notice.php', [
                'rule'     => $rule,
                'cart'     => WC()->cart,
                'discount' => $this,
            ]);

            if ($html) {
                $notices[] = $html;
            }
        }

        if (!empty($notices)) {
            return implode('', $notices);
        }

        foreach ($rules as $rule) {
            if (empty($rule['enabled']) || !$this->is_legacy_rule($rule)) {
                continue;
            }

            $qty = $this->get_cart_qty_for_rule(WC()->cart, $rule);
            if ($qty <= 0) {
                continue;
            }

            $current_tier = $this->get_matched_tier($rule['tiers'], $qty);
            $next_tier = null;
            foreach ($rule['tiers'] as $tier) {
                $min_qty = (int) ($tier['min_qty'] ?? $tier['qty']);
                if ($qty < $min_qty) {
                    $next_tier = $tier;
                    break;
                }
            }

            $notices[] = superwoo_template('bundle-notice.php', [
                'rule'         => $rule,
                'qty'          => $qty,
                'current_tier' => $current_tier,
                'next_tier'    => $next_tier,
                'scope_label'  => $this->get_scope_label($rule),
            ]);

            break;
        }

        return implode('', $notices);
    }

    public function get_contextual_notice_rules($cart, $rules) {
        $selected = [];
        $discount_rule = $this->get_contextual_discount_notice_rule($cart, $rules);
        $gift_rule = $this->get_contextual_gift_notice_rule($cart, $rules);

        if ($discount_rule) {
            $selected[] = $discount_rule;
        }

        if ($gift_rule) {
            $selected[] = $gift_rule;
        }

        return $selected;
    }

    public function get_cart_offer_state($cart) {
        $state = [
            'discounts' => [],
            'gifts'     => [],
        ];

        if (!$cart || $cart->is_empty()) {
            return $state;
        }

        foreach ($this->get_rules() as $rule) {
            if (empty($rule['enabled']) || $this->is_legacy_rule($rule)) {
                continue;
            }

            if ('product_discount' === ($rule['offer_type'] ?? '')) {
                $qty = $this->get_cart_qty_for_offer_rule($cart, $rule);
                $min_qty = absint($rule['min_qty'] ?? 0);
                $discount = (float) ($rule['discount'] ?? 0);

                if ($min_qty > 0 && $discount > 0 && $qty >= $min_qty) {
                    $key = !empty($rule['id']) ? $rule['id'] : 'discount_' . $min_qty . '_' . $discount;
                    $state['discounts'][$key] = [
                        'message' => sprintf(__('Cart updated: offer applied. You got %s%% off eligible products.', 'superwoo'), wc_format_decimal($discount)),
                    ];
                }

                continue;
            }

        }

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['superwoo_free_gift']) || empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
                continue;
            }

            $gift_id = absint($cart_item['superwoo_gift_id'] ?? 0);
            if (!$gift_id) {
                $gift_id = absint($cart_item['variation_id'] ?? 0);
            }
            if (!$gift_id) {
                $gift_id = absint($cart_item['product_id'] ?? 0);
            }

            $worth = $this->get_product_worth_label($cart_item['data']);
            $state['gifts']['gift_' . $gift_id] = [
                'message' => $worth
                    ? sprintf(__('Cart updated: free item added. You got %1$s free worth %2$s.', 'superwoo'), $cart_item['data']->get_name(), $worth)
                    : sprintf(__('Cart updated: free item added. You got %s free.', 'superwoo'), $cart_item['data']->get_name()),
            ];
        }

        return $state;
    }

    private function get_product_worth_label($product) {
        if (!$product || !($product instanceof WC_Product)) {
            return '';
        }

        $worth = (float) $product->get_regular_price('edit');
        if ($worth <= 0) {
            $worth = (float) $product->get_meta('_regular_price', true);
        }
        if ($worth <= 0) {
            $worth = (float) $product->get_meta('_price', true);
        }

        if ($worth <= 0) {
            return '';
        }

        return html_entity_decode(wp_strip_all_tags(superwoo_format_selected_currency_amount($worth)), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
    }

    private function get_contextual_discount_notice_rule($cart, $rules) {
        $upcoming = [];
        $unlocked = [];

        foreach ($rules as $rule) {
            if (empty($rule['enabled']) || $this->is_legacy_rule($rule) || 'product_discount' !== ($rule['offer_type'] ?? '')) {
                continue;
            }

            $min_qty = absint($rule['min_qty'] ?? 0);
            $discount = (float) ($rule['discount'] ?? 0);
            if ($min_qty <= 0 || $discount <= 0) {
                continue;
            }

            $qty = $this->get_cart_qty_for_offer_rule($cart, $rule);
            if ($qty <= 0) {
                continue;
            }

            $candidate = [
                'rule'      => $rule,
                'qty'       => $qty,
                'min_qty'   => $min_qty,
                'remaining' => max(0, $min_qty - $qty),
                'discount'  => $discount,
            ];

            if ($qty < $min_qty) {
                $upcoming[] = $candidate;
            } else {
                $unlocked[] = $candidate;
            }
        }

        if (!empty($upcoming)) {
            usort($upcoming, function ($a, $b) {
                if ($a['remaining'] === $b['remaining']) {
                    return $a['min_qty'] <=> $b['min_qty'];
                }

                return $a['remaining'] <=> $b['remaining'];
            });

            return $upcoming[0]['rule'];
        }

        if (!empty($unlocked)) {
            usort($unlocked, function ($a, $b) {
                if ($a['min_qty'] === $b['min_qty']) {
                    return $b['discount'] <=> $a['discount'];
                }

                return $b['min_qty'] <=> $a['min_qty'];
            });

            return $unlocked[0]['rule'];
        }

        return null;
    }

    private function get_contextual_gift_notice_rule($cart, $rules) {
        $upcoming = [];
        $unlocked = [];

        foreach ($rules as $rule) {
            if (empty($rule['enabled']) || $this->is_legacy_rule($rule) || 'price_gift' !== ($rule['offer_type'] ?? '')) {
                continue;
            }

            if (empty($this->get_free_product_ids($rule))) {
                continue;
            }

            $min = (float) ($rule['min_amount'] ?? 0);
            $max = (float) ($rule['max_amount'] ?? 0);
            $subtotal = $this->get_cart_subtotal_excluding_gifts($cart, $rule);

            if ($subtotal <= 0 && $min > 0) {
                continue;
            }

            $candidate = [
                'rule'      => $rule,
                'subtotal'  => $subtotal,
                'min'       => $min,
                'max'       => $max,
                'remaining' => max(0, $min - $subtotal),
            ];

            if ($subtotal < $min) {
                $upcoming[] = $candidate;
                continue;
            }

            if (0.0 === $max || $subtotal <= $max) {
                $unlocked[] = $candidate;
            }
        }

        if (!empty($upcoming)) {
            usort($upcoming, function ($a, $b) {
                if ($a['remaining'] === $b['remaining']) {
                    return $a['min'] <=> $b['min'];
                }

                return $a['remaining'] <=> $b['remaining'];
            });

            return $upcoming[0]['rule'];
        }

        if (!empty($unlocked)) {
            usort($unlocked, function ($a, $b) {
                return $b['min'] <=> $a['min'];
            });

            return $unlocked[0]['rule'];
        }

        return null;
    }

    public function get_matched_tier($tiers, $qty) {
        $matched = null;
        foreach ($tiers as $tier) {
            $min_qty = (int) ($tier['min_qty'] ?? $tier['qty']);
            $max_qty = (int) ($tier['max_qty'] ?? 0);

            if ($qty >= $min_qty && (0 === $max_qty || $qty <= $max_qty)) {
                $matched = $tier;
            }
        }

        return $matched;
    }

    public function get_cart_qty_for_rule($cart, $rule) {
        $qty = 0;
        foreach ($cart->get_cart() as $item) {
            if (!empty($item['superwoo_free_gift'])) {
                continue;
            }

            if ('category' === ($rule['scope'] ?? 'global')) {
                $product_id = absint($item['product_id'] ?? 0);
                if (!$product_id || !has_term(absint($rule['category_id']), 'product_cat', $product_id)) {
                    continue;
                }
            }

            $qty += absint($item['quantity'] ?? 0);
        }

        return $qty;
    }

    private function get_best_discount_for_product($product_id, $matches, $variation_id = 0) {
        $best = 0;

        foreach ($matches as $match) {
            $rule = $match['rule'];

            if (!$this->rule_applies_to_product($rule, $product_id, $variation_id)) {
                continue;
            }

            $discount = (float) ($match['tier']['discount'] ?? 0);
            if ($discount > $best) {
                $best = $discount;
            }
        }

        return $best;
    }

    private function sync_free_gifts($cart, $gift_rules) {
        static $syncing = false;

        if ($syncing) {
            return;
        }

        $syncing = true;
        $desired = [];

        foreach ($gift_rules as $rule) {
            foreach ($this->get_free_product_ids($rule) as $free_product_id) {
                $desired[$free_product_id] = true;
            }
        }

        $existing = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['superwoo_free_gift'])) {
                continue;
            }

            $gift_product_id = absint($cart_item['variation_id'] ?? 0);
            if (!$gift_product_id) {
                $gift_product_id = absint($cart_item['product_id'] ?? 0);
            }

            if (empty($desired[$gift_product_id]) || isset($existing[$gift_product_id])) {
                $cart->remove_cart_item($cart_item_key);
                continue;
            }

            $existing[$gift_product_id] = true;
        }

        foreach (array_keys($desired) as $gift_product_id) {
            if (!empty($existing[$gift_product_id])) {
                continue;
            }

            $product = wc_get_product($gift_product_id);
            if (!$product || !$product->is_purchasable() || !$product->is_in_stock()) {
                continue;
            }

            $product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $variation_id = $product->is_type('variation') ? $product->get_id() : 0;

            $cart->add_to_cart($product_id, 1, $variation_id, [], [
                'superwoo_free_gift' => true,
                'superwoo_gift_id'   => $gift_product_id,
            ]);
        }

        $syncing = false;
    }

    private function get_customer_cart_quantities($cart) {
        $quantities = [];

        if (!$cart) {
            return $quantities;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['superwoo_free_gift'])) {
                continue;
            }

            $quantities[$cart_item_key] = max(1, absint($cart_item['quantity'] ?? 1));
        }

        return $quantities;
    }

    private function restore_customer_cart_quantities($cart, $quantities) {
        if (!$cart || !is_array($quantities)) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!empty($cart_item['superwoo_free_gift']) || !array_key_exists($cart_item_key, $quantities)) {
                continue;
            }

            $allowed_quantity = max(1, absint($quantities[$cart_item_key]));
            $current_quantity = max(1, absint($cart_item['quantity'] ?? 1));
            if ($current_quantity !== $allowed_quantity) {
                $cart->set_quantity($cart_item_key, $allowed_quantity, false);
            }
        }
    }

    public function gift_cart_item_name($name, $cart_item, $cart_item_key) {
        if (empty($cart_item['superwoo_free_gift'])) {
            return $name;
        }

        return $name . ' <small class="superwoo-free-gift-label">' . esc_html__('Free Gift', 'superwoo') . '</small>';
    }

    public function get_cart_qty_for_products($cart, $product_ids) {
        $product_ids = array_map('absint', is_array($product_ids) ? $product_ids : []);
        if (empty($product_ids)) {
            return 0;
        }

        $qty = 0;
        foreach ($cart->get_cart() as $item) {
            if (!empty($item['superwoo_free_gift'])) {
                continue;
            }

            $product_id = absint($item['product_id'] ?? 0);
            $variation_id = absint($item['variation_id'] ?? 0);
            if (in_array($product_id, $product_ids, true) || ($variation_id && in_array($variation_id, $product_ids, true))) {
                $qty += absint($item['quantity'] ?? 0);
            }
        }

        return $qty;
    }

    public function get_cart_qty_for_offer_rule($cart, $rule) {
        $qty = 0;

        foreach ($cart->get_cart() as $item) {
            if (!empty($item['superwoo_free_gift'])) {
                continue;
            }

            $product_id = absint($item['product_id'] ?? 0);
            $variation_id = absint($item['variation_id'] ?? 0);

            if ($this->rule_applies_to_product($rule, $product_id, $variation_id)) {
                $qty += absint($item['quantity'] ?? 0);
            }
        }

        return $qty;
    }

    public function get_cart_subtotal_excluding_gifts($cart, $rule = null) {
        $subtotal = 0.0;

        foreach ($cart->get_cart() as $item) {
            if (!empty($item['superwoo_free_gift']) || empty($item['data']) || !($item['data'] instanceof WC_Product)) {
                continue;
            }

            if ($rule) {
                $product_id = absint($item['product_id'] ?? 0);
                $variation_id = absint($item['variation_id'] ?? 0);
                if (!$this->rule_applies_to_product($rule, $product_id, $variation_id)) {
                    continue;
                }
            }

            if (isset($item['_superwoo_current_inr_price'])) {
                $price = (float) $item['_superwoo_current_inr_price'];
            } elseif (isset($item['_superwoo_base_inr_price'])) {
                $price = (float) $item['_superwoo_base_inr_price'];
            } else {
                $price = (float) $item['data']->get_price();
            }

            $subtotal += $price * absint($item['quantity'] ?? 0);
        }

        return $subtotal;
    }

    public function cart_subtotal_in_price_range($cart, $rule) {
        $subtotal = $this->get_cart_subtotal_excluding_gifts($cart, $rule);
        $min = (float) ($rule['min_amount'] ?? 0);
        $max = (float) ($rule['max_amount'] ?? 0);

        return $subtotal >= $min && (0.0 === $max || $subtotal <= $max);
    }

    public function rule_applies_to_product($rule, $product_id, $variation_id = 0) {
        if ($this->is_legacy_rule($rule)) {
            if ('category' === ($rule['scope'] ?? 'global')) {
                return $product_id > 0 && has_term(absint($rule['category_id'] ?? 0), 'product_cat', $product_id);
            }

            return true;
        }

        $applies_to = $rule['applies_to'] ?? 'products';

        if ('global' === $applies_to) {
            return true;
        }

        if ('category' === $applies_to) {
            return $product_id > 0 && has_term(absint($rule['category_id'] ?? 0), 'product_cat', $product_id);
        }

        $product_ids = array_map('absint', is_array($rule['product_ids'] ?? null) ? $rule['product_ids'] : []);
        return in_array($product_id, $product_ids, true) || ($variation_id && in_array($variation_id, $product_ids, true));
    }

    private function sanitize_applies_to($applies_to) {
        $applies_to = sanitize_key($applies_to);
        return in_array($applies_to, ['global', 'category', 'products'], true) ? $applies_to : 'products';
    }

    private function is_legacy_rule($rule) {
        return empty($rule['offer_type']) && !empty($rule['tiers']);
    }

    private function get_scope_label($rule) {
        if ('category' !== ($rule['scope'] ?? 'global')) {
            return __('product', 'superwoo');
        }

        $term = get_term(absint($rule['category_id']), 'product_cat');
        if ($term && !is_wp_error($term)) {
            return sprintf(__('%s product', 'superwoo'), $term->name);
        }

        return __('product', 'superwoo');
    }
}

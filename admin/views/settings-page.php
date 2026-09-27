<?php defined('ABSPATH') || exit; ?>
<?php
$enabled_currency_codes = function_exists('superwoo_currency') ? superwoo_currency()->get_enabled_currencies($settings) : ['INR'];
$currency_labels = [
    'INR' => 'INR(₹)',
    'USD' => 'USD($)',
    'EUR' => 'EUR(€)',
    'GBP' => 'GBP(£)',
    'AED' => 'AED(AED)',
    'SAR' => 'SAR(SAR)',
    'AUD' => 'AUD(A$)',
    'CAD' => 'CAD(C$)',
    'SGD' => 'SGD(S$)',
    'NZD' => 'NZD(NZ$)',
    'JPY' => 'JPY(¥)',
];
$currency_names = [
    'INR' => __('Indian Rupee', 'superwoo'),
    'USD' => __('US Dollar', 'superwoo'),
    'EUR' => __('Euro', 'superwoo'),
    'GBP' => __('British Pound', 'superwoo'),
    'AED' => __('UAE Dirham', 'superwoo'),
    'SAR' => __('Saudi Riyal', 'superwoo'),
    'AUD' => __('Australian Dollar', 'superwoo'),
    'CAD' => __('Canadian Dollar', 'superwoo'),
    'SGD' => __('Singapore Dollar', 'superwoo'),
    'NZD' => __('New Zealand Dollar', 'superwoo'),
    'JPY' => __('Japanese Yen', 'superwoo'),
];
$enabled_currency_parts = [];
foreach ($enabled_currency_codes as $code) {
    $enabled_currency_parts[] = $currency_labels[$code] ?? $code;
}
$enabled_currency_value = implode(',', $enabled_currency_parts);
$manual_rates = is_array($settings['manual_exchange_rates'] ?? null) ? $settings['manual_exchange_rates'] : [];
$manual_rates_value = '';
foreach ($manual_rates as $code => $rate) {
    $manual_rates_value .= strtoupper($code) . '=' . $rate . "\n";
}
$exchange_rate_cache_hours = max(1, (int) ceil(absint($settings['exchange_rate_cache_minutes']) / 60));
$suggested_currency_text = implode(', ', array_values($currency_labels));
$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
$active_tab = in_array($active_tab, ['general', 'cart', 'appearance', 'currency'], true) ? $active_tab : 'general';
?>
<div class="wrap superwoo-admin-page">
    <h1><?php esc_html_e('SuperWoo', 'superwoo'); ?></h1>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('SuperWoo settings saved.', 'superwoo'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=superwoo-settings')); ?>">
        <?php wp_nonce_field('superwoo_save_settings', 'superwoo_settings_nonce'); ?>
        <input type="hidden" name="superwoo_active_tab" value="<?php echo esc_attr($active_tab); ?>" data-superwoo-active-tab>

        <nav class="nav-tab-wrapper superwoo-settings-tabs" aria-label="<?php esc_attr_e('SuperWoo settings tabs', 'superwoo'); ?>">
            <a href="#superwoo-general-settings" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>" data-superwoo-settings-tab="general"><?php esc_html_e('Product Page', 'superwoo'); ?></a>
            <a href="#superwoo-cart-settings" class="nav-tab <?php echo 'cart' === $active_tab ? 'nav-tab-active' : ''; ?>" data-superwoo-settings-tab="cart"><?php esc_html_e('Cart', 'superwoo'); ?></a>
            <a href="#superwoo-appearance-settings" class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>" data-superwoo-settings-tab="appearance"><?php esc_html_e('Colors & Style', 'superwoo'); ?></a>
            <a href="#superwoo-currency-settings" class="nav-tab <?php echo 'currency' === $active_tab ? 'nav-tab-active' : ''; ?>" data-superwoo-settings-tab="currency"><?php esc_html_e('Multi-Currency', 'superwoo'); ?></a>
        </nav>

        <div id="superwoo-general-settings" class="superwoo-settings-panel <?php echo 'general' === $active_tab ? 'is-active' : ''; ?>" data-superwoo-settings-panel="general">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Modules', 'superwoo'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="enable_benefits" value="1" <?php checked(!empty($settings['enable_benefits'])); ?>> <?php esc_html_e('Benefit Icons', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_how_to_use" value="1" <?php checked(!empty($settings['enable_how_to_use'])); ?>> <?php esc_html_e('How to Use field', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_faqs" value="1" <?php checked(!empty($settings['enable_faqs'])); ?>> <?php esc_html_e('Product FAQs', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_reviews" value="1" <?php checked(!empty($settings['enable_reviews'])); ?>> <?php esc_html_e('Modern Reviews', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_variation_cards" value="1" <?php checked(!empty($settings['enable_variation_cards'])); ?>> <?php esc_html_e('Variation Cards', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_shop_filters" value="1" <?php checked(!empty($settings['enable_shop_filters'])); ?>> <?php esc_html_e('Shop Filters', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_shoppable_videos" value="1" <?php checked(!empty($settings['enable_shoppable_videos'])); ?>> <?php esc_html_e('Shoppable Videos', 'superwoo'); ?></label><br>
                            <span class="description"><?php esc_html_e('Configure customer-facing video behavior below.', 'superwoo'); ?></span><br>
                            <label><input type="checkbox" name="enable_bundle_offers" value="1" <?php checked(!empty($settings['enable_bundle_offers'])); ?>> <?php esc_html_e('Offers', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_cart_drawer" value="1" <?php checked(!empty($settings['enable_cart_drawer'])); ?>> <?php esc_html_e('Cart Drawer', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_elementor_products_carousel" value="1" <?php checked(!empty($settings['enable_elementor_products_carousel'])); ?>> <?php esc_html_e('Elementor Products Carousel', 'superwoo'); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Shoppable Videos', 'superwoo'); ?></th>
                    <td><fieldset>
                        <label><input type="checkbox" name="shoppable_videos_fullscreen" value="1" <?php checked(!empty($settings['shoppable_videos_fullscreen'])); ?>> <?php esc_html_e('Enable fullscreen viewer', 'superwoo'); ?></label><br>
                        <label><input type="checkbox" name="shoppable_videos_autoplay" value="1" <?php checked(!empty($settings['shoppable_videos_autoplay'])); ?>> <?php esc_html_e('Autoplay videos when meaningfully visible', 'superwoo'); ?></label><br>
                        <label><input type="checkbox" name="shoppable_videos_muted" value="1" <?php checked(!empty($settings['shoppable_videos_muted'])); ?>> <?php esc_html_e('Mute autoplay by default', 'superwoo'); ?></label><br>
                        <label><input type="checkbox" name="shoppable_videos_quick_buy" value="1" <?php checked(!empty($settings['shoppable_videos_quick_buy'])); ?>> <?php esc_html_e('Enable Quick Buy redirect after add to cart', 'superwoo'); ?></label><br>
                        <label><input type="checkbox" name="shoppable_videos_product_page" value="1" <?php checked(!empty($settings['shoppable_videos_product_page'])); ?>> <?php esc_html_e('Automatically show matching videos on product pages', 'superwoo'); ?></label><br>
                        <label><?php esc_html_e('Product-page position:', 'superwoo'); ?> <select name="shoppable_videos_product_position"><option value="after_summary" <?php selected($settings['shoppable_videos_product_position'] ?? 'after_summary', 'after_summary'); ?>><?php esc_html_e('After summary', 'superwoo'); ?></option><option value="after_tabs" <?php selected($settings['shoppable_videos_product_position'] ?? '', 'after_tabs'); ?>><?php esc_html_e('After tabs', 'superwoo'); ?></option><option value="before_related" <?php selected($settings['shoppable_videos_product_position'] ?? '', 'before_related'); ?>><?php esc_html_e('Before related products', 'superwoo'); ?></option></select></label>
                    </fieldset></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Shop filters to show', 'superwoo'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Shop filters to show', 'superwoo'); ?></legend>
                            <?php foreach ([
                                'search' => __('Product search', 'superwoo'),
                                'categories' => __('Categories', 'superwoo'),
                                'price' => __('Price range', 'superwoo'),
                                'attributes' => __('Product attributes', 'superwoo'),
                                'stock' => __('Availability', 'superwoo'),
                                'sale' => __('On-sale toggle', 'superwoo'),
                                'rating' => __('Customer rating', 'superwoo'),
                                'sort' => __('Sort order', 'superwoo'),
                            ] as $filter_key => $filter_label) : ?>
                                <label><input type="checkbox" name="shop_filter_show_<?php echo esc_attr($filter_key); ?>" value="1" <?php checked(!empty($settings['shop_filter_show_' . $filter_key])); ?>> <?php echo esc_html($filter_label); ?></label><br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description"><?php esc_html_e('These settings control filter visibility globally for the Shop Filters shortcode and all Elementor Shop Filters widgets.', 'superwoo'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="superwoo-appearance-settings" class="superwoo-settings-panel <?php echo 'appearance' === $active_tab ? 'is-active' : ''; ?>" data-superwoo-settings-panel="appearance">
            <?php
            $color_themes = [
                'ocean' => [
                    'label' => __('Ocean', 'superwoo'),
                    'description' => __('Blue and fresh green', 'superwoo'),
                    'colors' => ['color_primary' => '#005b7f', 'color_secondary' => '#74bf2e', 'color_button' => '#005b7f', 'color_button_text' => '#ffffff', 'color_button_hover' => '#004866', 'color_cart_icon' => '#0b3d4d', 'color_cart_badge' => '#ef5b4f', 'color_body_text' => '#17212b', 'color_star' => '#ffb400', 'review_color_accent' => '#2d8619', 'review_color_highlight' => '#5d9f80', 'review_color_button' => '#28830f', 'review_color_button_end' => '#104c1e', 'review_color_button_hover' => '#1b6313', 'review_color_button_text' => '#ffffff', 'review_color_heading' => '#111827', 'review_color_text' => '#293244', 'review_color_muted' => '#717c92', 'review_color_background' => '#f7faf8', 'review_color_surface' => '#ffffff', 'review_color_soft' => '#f0f7ef', 'review_color_border' => '#e3e8e9'],
                ],
                'forest' => [
                    'label' => __('Forest', 'superwoo'),
                    'description' => __('Deep green and lime', 'superwoo'),
                    'colors' => ['color_primary' => '#14532d', 'color_secondary' => '#84cc16', 'color_button' => '#166534', 'color_button_text' => '#ffffff', 'color_button_hover' => '#14532d', 'color_cart_icon' => '#14532d', 'color_cart_badge' => '#dc2626', 'color_body_text' => '#1f2937', 'color_star' => '#eab308', 'review_color_accent' => '#3f7d20', 'review_color_highlight' => '#5b9b70', 'review_color_button' => '#3f7d20', 'review_color_button_end' => '#14532d', 'review_color_button_hover' => '#286313', 'review_color_button_text' => '#ffffff', 'review_color_heading' => '#163020', 'review_color_text' => '#334155', 'review_color_muted' => '#64748b', 'review_color_background' => '#f6faf5', 'review_color_surface' => '#ffffff', 'review_color_soft' => '#edf6ea', 'review_color_border' => '#d8e5d5'],
                ],
                'violet' => [
                    'label' => __('Violet', 'superwoo'),
                    'description' => __('Purple and lilac', 'superwoo'),
                    'colors' => ['color_primary' => '#5b21b6', 'color_secondary' => '#c084fc', 'color_button' => '#6d28d9', 'color_button_text' => '#ffffff', 'color_button_hover' => '#4c1d95', 'color_cart_icon' => '#4c1d95', 'color_cart_badge' => '#ec4899', 'color_body_text' => '#1f2937', 'color_star' => '#f59e0b', 'review_color_accent' => '#7c3aed', 'review_color_highlight' => '#9d77cc', 'review_color_button' => '#7c3aed', 'review_color_button_end' => '#4c1d95', 'review_color_button_hover' => '#5b21b6', 'review_color_button_text' => '#ffffff', 'review_color_heading' => '#27134d', 'review_color_text' => '#374151', 'review_color_muted' => '#7c7194', 'review_color_background' => '#faf8ff', 'review_color_surface' => '#ffffff', 'review_color_soft' => '#f3efff', 'review_color_border' => '#e4dcf2'],
                ],
                'sunset' => [
                    'label' => __('Sunset', 'superwoo'),
                    'description' => __('Coral and warm gold', 'superwoo'),
                    'colors' => ['color_primary' => '#9a3412', 'color_secondary' => '#fb7185', 'color_button' => '#ea580c', 'color_button_text' => '#ffffff', 'color_button_hover' => '#c2410c', 'color_cart_icon' => '#9a3412', 'color_cart_badge' => '#dc2626', 'color_body_text' => '#292524', 'color_star' => '#f59e0b', 'review_color_accent' => '#d65a23', 'review_color_highlight' => '#ce7f57', 'review_color_button' => '#ea580c', 'review_color_button_end' => '#9a3412', 'review_color_button_hover' => '#c2410c', 'review_color_button_text' => '#ffffff', 'review_color_heading' => '#3b2219', 'review_color_text' => '#44403c', 'review_color_muted' => '#83716a', 'review_color_background' => '#fffaf7', 'review_color_surface' => '#ffffff', 'review_color_soft' => '#fff0e7', 'review_color_border' => '#f0ddd3'],
                ],
                'midnight' => [
                    'label' => __('Midnight', 'superwoo'),
                    'description' => __('Navy and electric teal', 'superwoo'),
                    'colors' => ['color_primary' => '#0f172a', 'color_secondary' => '#22d3ee', 'color_button' => '#0f766e', 'color_button_text' => '#ffffff', 'color_button_hover' => '#115e59', 'color_cart_icon' => '#0f172a', 'color_cart_badge' => '#f43f5e', 'color_body_text' => '#111827', 'color_star' => '#fbbf24', 'review_color_accent' => '#0f766e', 'review_color_highlight' => '#3c9c9b', 'review_color_button' => '#0f766e', 'review_color_button_end' => '#164e63', 'review_color_button_hover' => '#115e59', 'review_color_button_text' => '#ffffff', 'review_color_heading' => '#102338', 'review_color_text' => '#334155', 'review_color_muted' => '#718096', 'review_color_background' => '#f7fafc', 'review_color_surface' => '#ffffff', 'review_color_soft' => '#e8f7f6', 'review_color_border' => '#d9e3e8'],
                ],
            ];
            ?>
            <div class="superwoo-appearance-card">
                <h2><?php esc_html_e('Color Themes', 'superwoo'); ?></h2>
                <p><?php esc_html_e('Choose a starting palette. It updates all storefront and review colors; you can still adjust individual colors below before saving.', 'superwoo'); ?></p>
                <div class="superwoo-color-themes" role="group" aria-label="<?php esc_attr_e('Predefined color themes', 'superwoo'); ?>">
                    <?php foreach ($color_themes as $theme_key => $theme) : ?>
                        <button type="button" class="superwoo-color-theme" data-superwoo-color-theme data-superwoo-theme-colors="<?php echo esc_attr(wp_json_encode($theme['colors'])); ?>" aria-pressed="false">
                            <span class="superwoo-color-theme__preview" aria-hidden="true">
                                <i style="background-color: <?php echo esc_attr($theme['colors']['color_primary']); ?>;"></i><i style="background-color: <?php echo esc_attr($theme['colors']['color_button']); ?>;"></i><i style="background-color: <?php echo esc_attr($theme['colors']['color_secondary']); ?>;"></i>
                            </span>
                            <strong><?php echo esc_html($theme['label']); ?></strong>
                            <small><?php echo esc_html($theme['description']); ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
                <h2><?php esc_html_e('Storefront Colors', 'superwoo'); ?></h2>
                <p><?php esc_html_e('These colors are shared by SuperWoo product controls, reviews, cart drawer, cart icons, badges, and buttons.', 'superwoo'); ?></p>
                <div class="superwoo-color-grid">
                    <?php
                    $color_fields = [
                        'color_primary'     => __('Primary color', 'superwoo'),
                        'color_secondary'   => __('Secondary color', 'superwoo'),
                        'color_button'      => __('Button background', 'superwoo'),
                        'color_button_text' => __('Button text', 'superwoo'),
                        'color_button_hover'=> __('Button hover', 'superwoo'),
                        'color_cart_icon'   => __('Cart icon', 'superwoo'),
                        'color_cart_badge'  => __('Cart count badge', 'superwoo'),
                        'color_body_text'   => __('Text color', 'superwoo'),
                        'color_star'        => __('Review star color', 'superwoo'),
                    ];
                    foreach ($color_fields as $field_name => $field_label) :
                        $color_value = sanitize_hex_color($settings[$field_name] ?? '') ?: '#000000';
                    ?>
                        <label class="superwoo-color-field" for="<?php echo esc_attr($field_name); ?>">
                            <span><?php echo esc_html($field_label); ?></span>
                            <span class="superwoo-color-control">
                                <input type="color" class="superwoo-color-picker" value="<?php echo esc_attr($color_value); ?>" data-superwoo-color-picker="<?php echo esc_attr($field_name); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s color picker', 'superwoo'), $field_label)); ?>">
                                <input type="text" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($color_value); ?>" class="superwoo-color-value" data-superwoo-color-value="<?php echo esc_attr($field_name); ?>" maxlength="7" spellcheck="false">
                                <span class="superwoo-color-swatch" data-superwoo-color-swatch="<?php echo esc_attr($field_name); ?>" style="--superwoo-active-color: <?php echo esc_attr($color_value); ?>;" aria-label="<?php echo esc_attr(sprintf(__('Current active color: %s', 'superwoo'), $color_value)); ?>"></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <h2><?php esc_html_e('Review Section Colors', 'superwoo'); ?></h2>
                <p><?php esc_html_e('Customize the review section independently. Review stars use the Review star color above.', 'superwoo'); ?></p>
                <div class="superwoo-color-grid">
                    <?php foreach (superwoo_review_color_fields() as $field_name => $field) : ?>
                        <?php $color_value = sanitize_hex_color($settings[$field_name] ?? '') ?: $field['default']; ?>
                        <label class="superwoo-color-field" for="<?php echo esc_attr($field_name); ?>">
                            <span><?php echo esc_html($field['label']); ?></span>
                            <span class="superwoo-color-control">
                                <input type="color" class="superwoo-color-picker" value="<?php echo esc_attr($color_value); ?>" data-superwoo-color-picker="<?php echo esc_attr($field_name); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s color picker', 'superwoo'), $field['label'])); ?>">
                                <input type="text" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($color_value); ?>" class="superwoo-color-value" data-superwoo-color-value="<?php echo esc_attr($field_name); ?>" maxlength="7" spellcheck="false">
                                <span class="superwoo-color-swatch" data-superwoo-color-swatch="<?php echo esc_attr($field_name); ?>" style="--superwoo-active-color: <?php echo esc_attr($color_value); ?>;" aria-label="<?php echo esc_attr(sprintf(__('Current active color: %s', 'superwoo'), $color_value)); ?>"></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description"><?php esc_html_e('Clear any custom page-builder color overrides if they should inherit these global SuperWoo colors.', 'superwoo'); ?></p>
            </div>
        </div>

        <div id="superwoo-cart-settings" class="superwoo-settings-panel <?php echo 'cart' === $active_tab ? 'is-active' : ''; ?>" data-superwoo-settings-panel="cart">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Cart Drawer', 'superwoo'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="cart_auto_open" value="1" <?php checked(!empty($settings['cart_auto_open'])); ?>> <?php esc_html_e('Open drawer after AJAX add to cart', 'superwoo'); ?></label><br>
                            <p><strong><?php esc_html_e('Hide mobile bottom navigation on these pages', 'superwoo'); ?></strong></p>
                            <div style="max-height: 220px; overflow: auto; padding: 10px; border: 1px solid #c3c4c7; background: #fff;">
                                <?php $nav_hidden_pages = array_map('absint', (array) ($settings['mobile_bottom_nav_hidden_pages'] ?? [])); ?>
                                <?php foreach (get_pages(['sort_column' => 'post_title', 'post_status' => ['publish', 'private', 'draft']]) as $nav_page) : ?>
                                    <label style="display: block; margin-bottom: 8px;"><input type="checkbox" name="mobile_bottom_nav_hidden_pages[]" value="<?php echo esc_attr($nav_page->ID); ?>" <?php checked(in_array((int) $nav_page->ID, $nav_hidden_pages, true)); ?>> <?php echo esc_html($nav_page->post_title ?: __('(Untitled page)', 'superwoo')); ?> <span class="description">(#<?php echo esc_html($nav_page->ID); ?>)</span></label>
                                <?php endforeach; ?>
                            </div>
                            <p class="description"><?php esc_html_e('Select pages where the mobile bottom bar should be hidden. Leave all unchecked to keep the current behavior. Checkout and dashboard pages remain hidden automatically.', 'superwoo'); ?></p>
                            <label><input type="checkbox" name="cart_drawer_crosssell" value="1" <?php checked(!empty($settings['cart_drawer_crosssell'])); ?>> <?php esc_html_e('Show cross-sell recommendations', 'superwoo'); ?></label><br>
                            <label>
                                <?php esc_html_e('Coupon row:', 'superwoo'); ?>
                                <select name="cart_drawer_coupon">
                                    <option value="checkout_link" <?php selected($settings['cart_drawer_coupon'], 'checkout_link'); ?>><?php esc_html_e('Link to checkout', 'superwoo'); ?></option>
                                    <option value="disabled" <?php selected($settings['cart_drawer_coupon'], 'disabled'); ?>><?php esc_html_e('Disabled', 'superwoo'); ?></option>
                                </select>
                            </label>
                            <br>
                            <label><input type="checkbox" name="enable_add_to_cart_diagnostics" value="1" <?php checked(!empty($settings['enable_add_to_cart_diagnostics'])); ?>> <?php esc_html_e('Temporarily log product Add to Cart diagnostics', 'superwoo'); ?></label>
                            <br>
                            <label><input type="checkbox" name="show_discount_percentage" value="1" <?php checked(!empty($settings['show_discount_percentage'])); ?>> <?php esc_html_e('Show sale discount percentage', 'superwoo'); ?></label>
                            <p><strong><?php esc_html_e('Header cart icon', 'superwoo'); ?></strong></p>
                            <div class="superwoo-cart-icon-choices">
                                <?php foreach (['outline-bag' => __('Outlined bag', 'superwoo'), 'filled-bag' => __('Filled bag', 'superwoo'), 'basket' => __('Basket', 'superwoo')] as $icon_key => $icon_label) : ?>
                                    <label class="superwoo-cart-icon-choice"><input type="radio" name="header_cart_icon" value="<?php echo esc_attr($icon_key); ?>" <?php checked($settings['header_cart_icon'] ?? 'outline-bag', $icon_key); ?>><span class="superwoo-cart-icon-choice__preview superwoo-cart-icon-choice__preview--<?php echo esc_attr($icon_key); ?>" aria-hidden="true"></span><span><?php echo esc_html($icon_label); ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <br>
                            <label><input type="checkbox" name="enable_logging" value="1" <?php checked(!empty($settings['enable_logging'])); ?>> <?php esc_html_e('Enable SuperWoo logs', 'superwoo'); ?></label>
                            <p class="description"><?php esc_html_e('Logs are stored in WooCommerce → Status → Logs with source “superwoo”. Enable only while troubleshooting and disable afterward.', 'superwoo'); ?></p>
                        </fieldset>
                        <p class="description"><?php esc_html_e('Use shortcode [superwoo_cart_button] or add data-superwoo-open-cart to any button/link. Diagnostics log only request IDs, product IDs, requested quantities, and matching cart quantities to the PHP error log; disable it after testing.', 'superwoo'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="superwoo-currency-settings" class="superwoo-settings-panel <?php echo 'currency' === $active_tab ? 'is-active' : ''; ?>" data-superwoo-settings-panel="currency">
            <div class="superwoo-currency-card">
                <div class="superwoo-currency-card__header">
                    <h2><?php esc_html_e('Multi Currency Settings', 'superwoo'); ?></h2>
                    <p><?php esc_html_e('Show course and workshop prices in the visitor currency using exchange rates, with optional per-item extra amounts.', 'superwoo'); ?></p>
                </div>

                <table class="form-table superwoo-currency-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Multi Currency', 'superwoo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_multi_currency" value="1" <?php checked(!empty($settings['enable_multi_currency'])); ?>> <?php esc_html_e('Show enabled currencies and charge checkout in the selected currency.', 'superwoo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_currency"><?php esc_html_e('Default Currency', 'superwoo'); ?></label></th>
                        <td>
                            <select id="default_currency" name="default_currency" class="regular-text">
                                <?php foreach ($enabled_currency_codes as $code) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['default_currency'], $code); ?>>
                                        <?php echo esc_html(sprintf('%1$s - %2$s', $code, $currency_names[$code] ?? $code)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Base pricing is still read from INR amounts. Other currencies are converted from this base.', 'superwoo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="enabled_currency_codes"><?php esc_html_e('Enabled Currencies', 'superwoo'); ?></label></th>
                        <td>
                            <input type="text" id="enabled_currency_codes" name="enabled_currency_codes" class="large-text" value="<?php echo esc_attr($enabled_currency_value); ?>" placeholder="INR(₹),USD($),EUR(€)">
                            <p class="description"><?php esc_html_e('Comma-separated ISO currency codes. The base currency is always enabled automatically.', 'superwoo'); ?></p>
                            <p class="description"><?php echo esc_html($suggested_currency_text); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Location Currency Detection', 'superwoo'); ?></th>
                        <td>
                            <label><input type="checkbox" name="currency_auto_detect" value="1" <?php checked(!empty($settings['currency_auto_detect'])); ?>> <?php esc_html_e('Automatically choose currency from visitor IP/location when available. If no location is detected, INR is used.', 'superwoo'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exchange_rate_api_url"><?php esc_html_e('Exchange Rate API URL', 'superwoo'); ?></label></th>
                        <td>
                            <input type="text" id="exchange_rate_api_url" name="exchange_rate_api_url" class="large-text" value="<?php echo esc_attr($settings['exchange_rate_api_url']); ?>" placeholder="https://api.currencylayer.com/live?access_key={api_key}&source={base}&currencies={symbols}">
                            <p class="description"><?php esc_html_e('Use Currencylayer placeholders: {api_key}, {base}, and {symbols}. Default endpoint: /live with source=INR and currencies limited to enabled codes.', 'superwoo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exchange_rate_api_key"><?php esc_html_e('Exchange Rate API Key', 'superwoo'); ?></label></th>
                        <td>
                            <input type="password" id="exchange_rate_api_key" name="exchange_rate_api_key" class="regular-text" value="<?php echo esc_attr($settings['exchange_rate_api_key']); ?>" autocomplete="new-password">
                            <p class="description"><?php esc_html_e('Required by Currencylayer as the access_key value.', 'superwoo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exchange_rate_cache_hours"><?php esc_html_e('Rate Cache Hours', 'superwoo'); ?></label></th>
                        <td>
                            <input type="number" id="exchange_rate_cache_hours" name="exchange_rate_cache_hours" class="small-text" min="1" step="1" value="<?php echo esc_attr($exchange_rate_cache_hours); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="manual_exchange_rates"><?php esc_html_e('Manual Fallback Rates', 'superwoo'); ?></label></th>
                        <td>
                            <textarea id="manual_exchange_rates" name="manual_exchange_rates" rows="6" class="large-text code" placeholder="USD=0.012&#10;EUR=0.011&#10;AED=0.044"><?php echo esc_textarea(trim($manual_rates_value)); ?></textarea>
                            <p class="description"><?php esc_html_e('One per line: CODE=rate, where rate means 1 INR equals that currency. Used when the API has no rate for a currency.', 'superwoo'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php submit_button(__('Save SuperWoo Settings', 'superwoo')); ?>
    </form>
</div>
<script>
(function () {
    var tabs = document.querySelectorAll('[data-superwoo-settings-tab]');
    var panels = document.querySelectorAll('[data-superwoo-settings-panel]');
    var activeInput = document.querySelector('[data-superwoo-active-tab]');

    function activate(tabName) {
        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-superwoo-settings-tab') === tabName;
            tab.classList.toggle('nav-tab-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-superwoo-settings-panel') === tabName);
        });

        if (activeInput) {
            activeInput.value = tabName;
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            activate(tab.getAttribute('data-superwoo-settings-tab'));
        });
    });

    function validHex(value) {
        return /^#[0-9a-f]{6}$/i.test(value || '');
    }

    function syncColor(fieldName, value) {
        var picker = document.querySelector('[data-superwoo-color-picker="' + fieldName + '"]');
        var text = document.querySelector('[data-superwoo-color-value="' + fieldName + '"]');
        var swatch = document.querySelector('[data-superwoo-color-swatch="' + fieldName + '"]');

        if (!validHex(value)) {
            return;
        }

        value = value.toLowerCase();
        if (picker) { picker.value = value; }
        if (text) { text.value = value; }
        if (swatch) {
            swatch.style.setProperty('--superwoo-active-color', value);
            swatch.setAttribute('aria-label', '<?php echo esc_js(__('Current active color:', 'superwoo')); ?> ' + value);
        }
    }

    document.querySelectorAll('[data-superwoo-color-picker]').forEach(function (picker) {
        picker.addEventListener('input', function () {
            syncColor(picker.getAttribute('data-superwoo-color-picker'), picker.value);
        });
    });

    document.querySelectorAll('[data-superwoo-color-value]').forEach(function (text) {
        text.addEventListener('input', function () {
            if (validHex(text.value)) {
                syncColor(text.getAttribute('data-superwoo-color-value'), text.value);
            }
        });
        text.addEventListener('blur', function () {
            var picker = document.querySelector('[data-superwoo-color-picker="' + text.getAttribute('data-superwoo-color-value') + '"]');
            syncColor(text.getAttribute('data-superwoo-color-value'), picker ? picker.value : text.value);
        });
    });

    document.querySelectorAll('[data-superwoo-color-theme]').forEach(function (theme) {
        theme.addEventListener('click', function () {
            var colors;
            try {
                colors = JSON.parse(theme.getAttribute('data-superwoo-theme-colors') || '{}');
            } catch (error) {
                return;
            }
            Object.keys(colors).forEach(function (fieldName) {
                syncColor(fieldName, colors[fieldName]);
            });
            document.querySelectorAll('[data-superwoo-color-theme]').forEach(function (item) {
                var selected = item === theme;
                item.classList.toggle('is-selected', selected);
                item.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        });
    });
})();
</script>

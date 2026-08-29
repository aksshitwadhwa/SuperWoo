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
$active_tab = in_array($active_tab, ['general', 'cart', 'currency'], true) ? $active_tab : 'general';
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
                            <label><input type="checkbox" name="enable_bundle_offers" value="1" <?php checked(!empty($settings['enable_bundle_offers'])); ?>> <?php esc_html_e('Offers', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_cart_drawer" value="1" <?php checked(!empty($settings['enable_cart_drawer'])); ?>> <?php esc_html_e('Cart Drawer', 'superwoo'); ?></label><br>
                            <label><input type="checkbox" name="enable_elementor_products_carousel" value="1" <?php checked(!empty($settings['enable_elementor_products_carousel'])); ?>> <?php esc_html_e('Elementor Products Carousel', 'superwoo'); ?></label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <div id="superwoo-cart-settings" class="superwoo-settings-panel <?php echo 'cart' === $active_tab ? 'is-active' : ''; ?>" data-superwoo-settings-panel="cart">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Cart Drawer', 'superwoo'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="cart_auto_open" value="1" <?php checked(!empty($settings['cart_auto_open'])); ?>> <?php esc_html_e('Open drawer after AJAX add to cart', 'superwoo'); ?></label><br>
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
})();
</script>

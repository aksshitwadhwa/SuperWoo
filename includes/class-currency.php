<?php
defined('ABSPATH') || exit;

class SuperWoo_Currency {
    const BASE_CURRENCY = 'INR';
    const COOKIE_NAME = 'superwoo_currency';

    private $cart_converting = false;

    public function hooks() {
        add_action('init', [$this, 'capture_requested_currency'], 1);
        add_action('template_redirect', [$this, 'mark_currency_pages_uncacheable'], 0);
        add_filter('woocommerce_currency', [$this, 'filter_woocommerce_currency'], 1000);
        add_filter('woocommerce_get_price_html', [$this, 'filter_price_html'], 1000, 2);
        add_filter('woocommerce_available_variation', [$this, 'filter_available_variation'], 1000, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'restore_cart_item_prices'], 1);
        add_action('woocommerce_before_calculate_totals', [$this, 'convert_cart_item_prices'], 1000);
        add_action('woocommerce_cart_calculate_fees', [$this, 'convert_cart_fees'], 1000);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'reset_session_cart_item'], 1000, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'tag_order_currency'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'clean_order_line_item_meta'], 20);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_order_item_meta']);
    }

    public function filter_woocommerce_currency($currency) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || $this->is_payment_request()) {
            return $currency;
        }

        return $this->get_selected_currency();
    }

    public function filter_price_html($price_html, $product) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || is_cart() || is_checkout()) {
            return $price_html;
        }

        if (!$product instanceof WC_Product) {
            return $price_html;
        }

        return $this->get_product_price_html($product);
    }

    public function filter_available_variation($data, $product, $variation) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || !$variation instanceof WC_Product) {
            return $data;
        }

        $display_price = $this->convert_product_price($variation, $this->get_product_base_price($variation));
        $display_regular_price = $this->convert_product_price($variation, $this->get_product_base_price($variation, 'regular_price'));

        $data['display_price'] = $display_price;
        $data['display_regular_price'] = $display_regular_price;
        $data['price_html'] = $this->get_product_price_html($variation);

        return $data;
    }

    public function convert_cart_item_prices($cart) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || $this->is_payment_request() || $this->cart_converting || !$cart || $cart->is_empty()) {
            return;
        }

        $this->cart_converting = true;

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product)) {
                continue;
            }

            $product = $cart_item['data'];

            if (!empty($cart_item['superwoo_free_gift'])) {
                $product->set_price(0);
                continue;
            }

            $base_price = (float) $product->get_price('edit');
            $cart->cart_contents[$cart_item_key]['_superwoo_current_inr_price'] = $base_price;
            $product->set_price($this->convert_product_price($product, $base_price));
            $cart->cart_contents[$cart_item_key]['_superwoo_currency'] = $this->get_selected_currency();
        }

        $this->cart_converting = false;
    }

    public function restore_cart_item_prices($cart) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || $this->is_payment_request() || !$cart || $cart->is_empty()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['data']) || !($cart_item['data'] instanceof WC_Product) || !empty($cart_item['superwoo_free_gift'])) {
                continue;
            }

            $product = $cart_item['data'];

            // Always restore from the catalog product. Reusing the mutable
            // cart object's price can preserve a temporary payment/checkout
            // value (for example ₹1) as the next request's base price.
            $catalog_id = !empty($cart_item['variation_id'])
                ? absint($cart_item['variation_id'])
                : (!empty($cart_item['product_id']) ? absint($cart_item['product_id']) : $product->get_id());
            $catalog_product = $catalog_id ? wc_get_product($catalog_id) : false;
            $base_price = $catalog_product instanceof WC_Product
                ? (float) $catalog_product->get_price('edit')
                : (float) $product->get_price('edit');

            $cart->cart_contents[$cart_item_key]['_superwoo_base_inr_price'] = $base_price;
            $product->set_price($base_price);
            unset($cart->cart_contents[$cart_item_key]['_superwoo_currency']);
        }
    }

    public function convert_cart_fees($cart) {
        if (!$this->is_enabled() || !$this->is_runtime_context() || $this->is_payment_request() || !$cart) {
            return;
        }

        $fees_api = method_exists($cart, 'fees_api') ? $cart->fees_api() : null;
        if (!$fees_api || !method_exists($fees_api, 'get_fees')) {
            return;
        }

        foreach ($fees_api->get_fees() as $fee) {
            if (!is_object($fee) || !isset($fee->amount)) {
                continue;
            }

            if (!isset($fee->superwoo_base_inr_amount)) {
                $fee->superwoo_base_inr_amount = (float) $fee->amount;
            }

            $converted = $this->convert_amount((float) $fee->superwoo_base_inr_amount);
            $fee->amount = $converted;
            if (isset($fee->total)) {
                $fee->total = $converted;
            }
        }
    }

    public function tag_order_currency($order, $data) {
        if (!$this->is_enabled() || !$order instanceof WC_Order) {
            return;
        }

        $currency = $this->get_selected_currency();
        $order->set_currency($currency);
        $order->update_meta_data('_superwoo_selected_currency', $currency);
        $order->update_meta_data('_superwoo_base_currency', self::BASE_CURRENCY);
    }

    public function reset_session_cart_item($cart_item, $values) {
        unset($cart_item['_superwoo_base_inr_price'], $cart_item['_superwoo_current_inr_price'], $cart_item['_superwoo_currency']);
        return $cart_item;
    }

    public function clean_order_line_item_meta($item) {
        if (!$item instanceof WC_Order_Item_Product) {
            return;
        }

        $item->delete_meta_data('_superwoo_base_inr_price');
        $item->delete_meta_data('_superwoo_current_inr_price');
        $item->delete_meta_data('_superwoo_currency');
    }

    public function hide_order_item_meta($hidden) {
        $hidden[] = '_superwoo_base_inr_price';
        $hidden[] = '_superwoo_current_inr_price';
        $hidden[] = '_superwoo_currency';

        return array_values(array_unique($hidden));
    }

    public function capture_requested_currency() {
        if (!$this->is_enabled()) {
            return;
        }

        $enabled = $this->get_enabled_currencies();
        $requested = isset($_GET[self::COOKIE_NAME]) ? $this->sanitize_currency_code(wp_unslash($_GET[self::COOKIE_NAME])) : '';

        if (!$requested || !in_array($requested, $enabled, true)) {
            return;
        }

        $_COOKIE[self::COOKIE_NAME] = $requested;
        if (!headers_sent()) {
            wc_setcookie(self::COOKIE_NAME, $requested, time() + MONTH_IN_SECONDS);
        }
    }

    public function mark_currency_pages_uncacheable() {
        if (!$this->is_enabled() || is_admin()) {
            return;
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
    }

    public function is_enabled() {
        $settings = superwoo_get_settings();
        return !empty($settings['enable_multi_currency']) && count($this->get_enabled_currencies($settings)) > 1;
    }

    public function get_selected_currency() {
        $settings = superwoo_get_settings();
        $enabled = $this->get_enabled_currencies($settings);
        $default = $this->sanitize_currency_code($settings['default_currency'] ?? self::BASE_CURRENCY);

        if (!in_array($default, $enabled, true)) {
            $default = self::BASE_CURRENCY;
        }

        if ($this->is_auto_detect_enabled($settings)) {
            $detected = $this->detect_currency($enabled);
            if ($detected) {
                return $detected;
            }
        }

        return $default;
    }

    public function get_enabled_currencies($settings = null) {
        $settings = is_array($settings) ? $settings : superwoo_get_settings();
        $codes = $settings['enabled_currency_codes'] ?? [self::BASE_CURRENCY];

        if (is_string($codes)) {
            $codes = preg_split('/[\s,]+/', $codes);
        }

        $codes = is_array($codes) ? $codes : [];
        $codes[] = self::BASE_CURRENCY;

        $codes = array_values(array_unique(array_filter(array_map([$this, 'sanitize_currency_code'], $codes))));
        return $codes ?: [self::BASE_CURRENCY];
    }

    public function get_non_base_enabled_currencies($settings = null) {
        return array_values(array_diff($this->get_enabled_currencies($settings), [self::BASE_CURRENCY]));
    }

    public function convert_product_price($product, $base_price, $currency = null) {
        $base_price = (float) $base_price;
        $currency = $currency ? $this->sanitize_currency_code($currency) : $this->get_selected_currency();

        if ($currency === self::BASE_CURRENCY) {
            return round($base_price, wc_get_price_decimals());
        }

        $converted = $this->convert_amount($base_price, $currency);
        $extra = $product instanceof WC_Product ? $this->get_product_manual_extra($product, $currency) : 0.0;

        return round($converted + $extra, wc_get_price_decimals());
    }

    public function convert_amount($amount, $currency = null) {
        $currency = $currency ? $this->sanitize_currency_code($currency) : $this->get_selected_currency();
        $amount = (float) $amount;

        if ($currency === self::BASE_CURRENCY) {
            return $amount;
        }

        $rate = $this->get_exchange_rate($currency);
        return $amount * $rate;
    }

    public function format_amount($amount, $currency = null) {
        $currency = $currency ? $this->sanitize_currency_code($currency) : $this->get_selected_currency();

        return wc_price((float) $amount, ['currency' => $currency]);
    }

    public function get_product_price_html($product) {
        if ($product->is_type('variable')) {
            return $this->get_variable_price_html($product);
        }

        if ('' === $product->get_price('edit')) {
            return '';
        }

        $price = $this->convert_product_price($product, $this->get_product_base_price($product));
        $regular = $this->convert_product_price($product, $this->get_product_base_price($product, 'regular_price'));

        if ($regular > $price && $price > 0) {
            return wc_format_sale_price($this->format_amount($regular), $this->format_amount($price));
        }

        return $this->format_amount($price);
    }

    public function get_product_base_price($product, $field = 'price') {
        if (!$product instanceof WC_Product) {
            return 0.0;
        }

        if ('regular_price' === $field) {
            $price = $product->get_regular_price('edit');
            if ('' === $price) {
                $price = $product->get_price('edit');
            }
            return (float) $price;
        }

        return (float) $product->get_price('edit');
    }

    public function get_product_manual_extra($product, $currency = null) {
        $currency = $currency ? $this->sanitize_currency_code($currency) : $this->get_selected_currency();

        if (!$product instanceof WC_Product || $currency === self::BASE_CURRENCY) {
            return 0.0;
        }

        $product_id = $product->get_id();
        $parent_id = $product->get_parent_id();
        $extras = get_post_meta($product_id, '_superwoo_manual_currency_extras', true);

        if ((!is_array($extras) || !array_key_exists($currency, $extras)) && $parent_id) {
            $extras = get_post_meta($parent_id, '_superwoo_manual_currency_extras', true);
        }

        if (!is_array($extras) || !array_key_exists($currency, $extras)) {
            return 0.0;
        }

        return (float) $extras[$currency];
    }

    public function get_exchange_rate($currency) {
        $currency = $this->sanitize_currency_code($currency);

        if ($currency === self::BASE_CURRENCY) {
            return 1.0;
        }

        $rates = $this->get_exchange_rates();
        return isset($rates[$currency]) && (float) $rates[$currency] > 0 ? (float) $rates[$currency] : 1.0;
    }

    public function sanitize_currency_code($code) {
        if (!preg_match('/([A-Z]{3})/i', (string) $code, $matches)) {
            return '';
        }

        return strtoupper($matches[1]);
    }

    private function get_variable_price_html($product) {
        $children = $product->get_children();
        $prices = [];

        foreach ($children as $child_id) {
            $variation = wc_get_product($child_id);
            if (!$variation) {
                continue;
            }

            $price = $this->convert_product_price($variation, $this->get_product_base_price($variation));
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        if (empty($prices)) {
            return '';
        }

        $min = min($prices);
        $max = max($prices);

        if ($min !== $max) {
            return wc_format_price_range($this->format_amount($min), $this->format_amount($max));
        }

        return $this->format_amount($min);
    }

    private function get_exchange_rates() {
        $settings = superwoo_get_settings();
        $enabled = $this->get_enabled_currencies($settings);
        $rates = [self::BASE_CURRENCY => 1.0];
        $api_rates = $this->get_api_rates($settings, $enabled);
        $manual_rates = $this->sanitize_rates($settings['manual_exchange_rates'] ?? []);

        foreach ($enabled as $code) {
            if ($code === self::BASE_CURRENCY) {
                continue;
            }

            if (isset($api_rates[$code]) && (float) $api_rates[$code] > 0) {
                $rates[$code] = (float) $api_rates[$code];
            } elseif (isset($manual_rates[$code]) && (float) $manual_rates[$code] > 0) {
                $rates[$code] = (float) $manual_rates[$code];
            }
        }

        return $rates;
    }

    private function get_api_rates($settings, $enabled) {
        $api_url = isset($settings['exchange_rate_api_url']) ? trim((string) $settings['exchange_rate_api_url']) : '';
        if ('' === $api_url || !function_exists('wp_remote_get')) {
            return [];
        }

        $key = isset($settings['exchange_rate_api_key']) ? trim((string) $settings['exchange_rate_api_key']) : '';
        $cache_minutes = max(1, absint($settings['exchange_rate_cache_minutes'] ?? 360));
        $transient_key = 'superwoo_rates_' . md5($api_url . '|' . $key . '|' . implode(',', $enabled));
        $cached = get_transient($transient_key);

        if (is_array($cached)) {
            return $cached;
        }

        $url = $this->build_exchange_rate_url($api_url, $key, $enabled);
        $response = wp_remote_get($url, ['timeout' => 8]);
        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return [];
        }

        $rates = $this->extract_rates_from_response($body, $enabled);
        if (!empty($rates)) {
            set_transient($transient_key, $rates, $cache_minutes * MINUTE_IN_SECONDS);
        }

        return $rates;
    }

    private function build_exchange_rate_url($api_url, $key, $enabled) {
        $symbols = implode(',', array_diff($enabled, [self::BASE_CURRENCY]));
        $replace = [
            '{base}'     => self::BASE_CURRENCY,
            '{from}'     => self::BASE_CURRENCY,
            '{source}'   => self::BASE_CURRENCY,
            '{symbols}'  => $symbols,
            '{currencies}' => $symbols,
            '{currency}' => $symbols,
            '{key}'      => rawurlencode($key),
            '{api_key}'  => rawurlencode($key),
            'base=base'  => 'base=' . self::BASE_CURRENCY,
            'from=base'  => 'from=' . self::BASE_CURRENCY,
            'source=base' => 'source=' . self::BASE_CURRENCY,
            'symbols=symbols' => 'symbols=' . $symbols,
            'currencies=symbols' => 'currencies=' . $symbols,
            'quotes=symbols'  => 'quotes=' . $symbols,
            'access_key=api_key' => 'access_key=' . rawurlencode($key),
        ];

        $has_placeholders = (bool) preg_match('/\{(?:base|from|source|symbols|currencies|currency|key|api_key)\}|(?:base|from|source)=base|(?:symbols|currencies|quotes)=symbols|access_key=api_key/i', $api_url);
        $url = strtr($api_url, $replace);
        if (!$has_placeholders) {
            $args = [
                'source'     => self::BASE_CURRENCY,
                'currencies' => $symbols,
            ];

            if ($key) {
                $args['access_key'] = $key;
            }

            $url = add_query_arg($args, $url);
        }

        return esc_url_raw($url);
    }

    private function extract_rates_from_response($body, $enabled) {
        $source = [];

        if (!empty($body['quotes']) && is_array($body['quotes'])) {
            return $this->extract_currencylayer_quotes($body, $enabled);
        }

        if (!empty($body['rates']) && is_array($body['rates'])) {
            $source = $body['rates'];
        } elseif (!empty($body['conversion_rates']) && is_array($body['conversion_rates'])) {
            $source = $body['conversion_rates'];
        } elseif (!empty($body['data']) && is_array($body['data'])) {
            $source = $body['data'];
        }

        $rates = [];
        foreach ($enabled as $code) {
            if ($code === self::BASE_CURRENCY || !isset($source[$code])) {
                continue;
            }

            $value = is_array($source[$code]) && isset($source[$code]['value']) ? $source[$code]['value'] : $source[$code];
            $value = (float) $value;
            if ($value > 0) {
                $rates[$code] = $value;
            }
        }

        return $rates;
    }

    private function extract_currencylayer_quotes($body, $enabled) {
        $quotes = is_array($body['quotes'] ?? null) ? $body['quotes'] : [];
        $source_currency = $this->sanitize_currency_code($body['source'] ?? self::BASE_CURRENCY);
        $source_currency = $source_currency ?: self::BASE_CURRENCY;
        $rates = [];

        foreach ($enabled as $code) {
            if ($code === self::BASE_CURRENCY) {
                continue;
            }

            $direct_key = $source_currency . $code;
            if ($source_currency === self::BASE_CURRENCY && isset($quotes[$direct_key]) && (float) $quotes[$direct_key] > 0) {
                $rates[$code] = (float) $quotes[$direct_key];
                continue;
            }

            $base_key = $source_currency . self::BASE_CURRENCY;
            if (isset($quotes[$direct_key], $quotes[$base_key]) && (float) $quotes[$direct_key] > 0 && (float) $quotes[$base_key] > 0) {
                $rates[$code] = (float) $quotes[$direct_key] / (float) $quotes[$base_key];
            }
        }

        return $rates;
    }

    private function detect_currency($enabled) {
        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? $this->sanitize_currency_code(wp_unslash($_COOKIE[self::COOKIE_NAME])) : '';
        $requested = isset($_GET[self::COOKIE_NAME]) ? $this->sanitize_currency_code(wp_unslash($_GET[self::COOKIE_NAME])) : '';

        if ($requested && $cookie && in_array($cookie, $enabled, true)) {
            return $cookie;
        }

        $country = $this->detect_country();
        $map = $this->get_country_currency_map();
        $currency = $country && isset($map[$country]) ? $map[$country] : '';

        if ($currency && in_array($currency, $enabled, true)) {
            if ($cookie !== $currency && !headers_sent()) {
                wc_setcookie(self::COOKIE_NAME, $currency, time() + MONTH_IN_SECONDS);
            }
            return $currency;
        }

        if ($cookie && $cookie !== self::BASE_CURRENCY && !headers_sent()) {
            wc_setcookie(self::COOKIE_NAME, self::BASE_CURRENCY, time() - HOUR_IN_SECONDS);
        }

        return '';
    }

    private function detect_country() {
        $woocommerce = function_exists('WC') ? WC() : null;
        if ($woocommerce && !empty($woocommerce->customer)) {
            $country = $woocommerce->customer->get_shipping_country() ?: $woocommerce->customer->get_billing_country();
            if ($country) {
                return strtoupper($country);
            }
        }

        $header_country = $this->detect_country_from_headers();
        if ($header_country) {
            return $header_country;
        }

        if (class_exists('WC_Geolocation')) {
            $geo = WC_Geolocation::geolocate_ip('', true, true);
            if (!empty($geo['country'])) {
                return strtoupper($geo['country']);
            }
        }

        return '';
    }

    private function detect_country_from_headers() {
        $headers = [
            'HTTP_CF_IPCOUNTRY',
            'HTTP_X_COUNTRY_CODE',
            'HTTP_X_GEOIP_COUNTRY_CODE',
            'HTTP_GEOIP_COUNTRY_CODE',
            'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        ];

        foreach ($headers as $header) {
            if (empty($_SERVER[$header])) {
                continue;
            }

            $country = strtoupper(preg_replace('/[^A-Z]/i', '', sanitize_text_field(wp_unslash($_SERVER[$header]))));
            if (2 === strlen($country) && 'XX' !== $country) {
                return $country;
            }
        }

        return '';
    }

    private function is_auto_detect_enabled($settings = null) {
        $settings = is_array($settings) ? $settings : superwoo_get_settings();
        return !empty($settings['currency_auto_detect']);
    }

    private function is_runtime_context() {
        return !is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST);
    }

    /**
     * Razorpay 1CC calculates the payment amount from the WooCommerce cart.
     * Do not mutate cart prices while its order endpoint is running.
     */
    private function is_payment_request() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $rest_route = defined('REST_REQUEST') && REST_REQUEST && isset($_REQUEST['rest_route'])
            ? sanitize_text_field(wp_unslash($_REQUEST['rest_route']))
            : '';

        return false !== strpos($request_uri, '/1cc/v1/order/create') || false !== strpos($rest_route, '/1cc/v1/order/create');
    }

    private function sanitize_rates($rates) {
        if (is_string($rates)) {
            $parsed = [];
            foreach (preg_split('/\r\n|\r|\n/', $rates) as $line) {
                if (!preg_match('/^\s*([A-Z]{3})\s*[=:,]\s*([0-9.]+)\s*$/i', $line, $matches)) {
                    continue;
                }
                $parsed[strtoupper($matches[1])] = (float) $matches[2];
            }
            $rates = $parsed;
        }

        $clean = [];
        foreach (is_array($rates) ? $rates : [] as $code => $rate) {
            $code = $this->sanitize_currency_code($code);
            $rate = (float) $rate;
            if ($code && $code !== self::BASE_CURRENCY && $rate > 0) {
                $clean[$code] = $rate;
            }
        }

        return $clean;
    }

    private function get_country_currency_map() {
        $eur = ['AT', 'BE', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'SI', 'SK'];
        $map = [
            'IN' => 'INR',
            'US' => 'USD',
            'GB' => 'GBP',
            'AE' => 'AED',
            'AU' => 'AUD',
            'CA' => 'CAD',
            'SG' => 'SGD',
            'JP' => 'JPY',
            'CN' => 'CNY',
            'HK' => 'HKD',
            'NZ' => 'NZD',
            'CH' => 'CHF',
            'ZA' => 'ZAR',
        ];

        foreach ($eur as $country) {
            $map[$country] = 'EUR';
        }

        return $map;
    }
}

function superwoo_currency() {
    static $currency = null;

    if (null === $currency) {
        $currency = new SuperWoo_Currency();
    }

    return $currency;
}

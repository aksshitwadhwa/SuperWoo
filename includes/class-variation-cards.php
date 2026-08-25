<?php
defined('ABSPATH') || exit;

class SuperWoo_Variation_Cards {
    public function hooks() {
        $settings = superwoo_get_settings();
        if (empty($settings['enable_variation_cards'])) {
            return;
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('body_class', [$this, 'body_classes']);
    }

    public function enqueue_assets() {
        if (!is_singular('product')) {
            return;
        }

        wp_enqueue_style('superwoo-variation-cards', SUPERWOO_URL . 'public/css/variation-cards.css', [], SUPERWOO_VERSION);
        wp_enqueue_script('superwoo-variation-cards', SUPERWOO_URL . 'public/js/variation-cards.js', ['jquery'], SUPERWOO_VERSION, true);

        wp_localize_script('superwoo-variation-cards', 'SuperWooVariationCards', [
            'currencySymbol'    => get_woocommerce_currency_symbol(),
            'currencyPosition'  => get_option('woocommerce_currency_pos', 'left'),
            'priceDecimals'     => wc_get_price_decimals(),
            'decimalSeparator'  => wc_get_price_decimal_separator(),
            'thousandSeparator' => wc_get_price_thousand_separator(),
            'trimZeros'         => apply_filters('woocommerce_price_trim_zeros', false),
            'i18n'              => [
                'unavailable' => __('Unavailable', 'superwoo'),
                'from'        => __('From', 'superwoo'),
            ],
        ]);
    }

    public function body_classes($classes) {
        if (is_singular('product')) {
            $classes[] = 'superwoo-variation-cards-enabled';
        }

        return $classes;
    }
}

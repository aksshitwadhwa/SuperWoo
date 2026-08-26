<?php
defined('ABSPATH') || exit;

class SuperWoo_Discount_Percentage {
    public function hooks() {
        add_filter('woocommerce_get_price_html', [$this, 'append_percentage'], 1000, 2);
    }

    public function append_percentage($price_html, $product) {
        if (!$this->is_enabled() || !$product instanceof WC_Product || is_cart() || is_checkout() || !$product->is_on_sale()) {
            return $price_html;
        }

        $percentage = $product->is_type('variable') ? $this->variable_percentage($product) : $this->sale_percentage($product);
        return $percentage > 0 ? $price_html . ' <span class="superwoo-discount-percentage">-' . esc_html($percentage) . '%</span>' : $price_html;
    }

    private function sale_percentage($product) {
        $regular = (float) $product->get_regular_price('edit');
        $sale = (float) $product->get_sale_price('edit');
        return $regular > 0 && $sale >= 0 && $sale < $regular ? (int) round((($regular - $sale) / $regular) * 100) : 0;
    }

    private function variable_percentage($product) {
        $percentages = [];
        foreach ($product->get_children() as $child_id) {
            $variation = wc_get_product($child_id);
            if ($variation) {
                $percentage = $this->sale_percentage($variation);
                if ($percentage > 0) {
                    $percentages[] = $percentage;
                }
            }
        }
        return empty($percentages) ? 0 : max($percentages);
    }

    private function is_enabled() {
        return !empty(superwoo_get_settings()['show_discount_percentage']);
    }
}

<?php
defined('ABSPATH') || exit;

/** Adds administrator-confirmed catalog items to a payment-only recovery order. */
class SuperWoo_Order_Recovery_Items {
    public static function add($record, $rows, $source, $actor_id) {
        if (!current_user_can('manage_woocommerce')) { return new WP_Error('recovery_items_forbidden', __('You cannot review recovery items.', 'superwoo')); }
        if (!is_array($record) || empty($record['payment_id']) || empty($record['wc_order_id']) || !is_array($rows) || count($rows) > 10 || !function_exists('wc_get_order') || !function_exists('wc_get_product')) {
            return new WP_Error('recovery_items_invalid_request', __('The recovery item request is invalid.', 'superwoo'));
        }
        $claim = SuperWoo_Order_Recovery_Payment_Record::claim((string) $record['payment_id']);
        if (!is_array($claim) || 'created' !== ($claim['state'] ?? '') || empty($claim['order_id'])) { return new WP_Error('recovery_items_no_claim', __('The payment has no completed recovery order.', 'superwoo')); }
        $order = wc_get_order(absint($claim['order_id']));
        if (!$order instanceof WC_Order || $order->get_status() !== SuperWoo_Order_Recovery_Payment_Record::STATUS || $order->get_meta('_superwoo_recovery_payment_only', true) !== 'yes' || absint($order->get_meta('_superwoo_recovery_historical_order_id', true)) !== absint($record['wc_order_id']) || $order->get_transaction_id() !== (string) $record['payment_id'] || strtoupper($order->get_currency()) !== 'INR' || (int) round((float) $order->get_total() * 100) !== (int) ($record['gross_minor'] ?? -1)) {
            return new WP_Error('recovery_items_order_mismatch', __('The recovery order no longer matches the imported payment.', 'superwoo'));
        }
        if ($order->get_items('line_item') || $order->get_meta('_superwoo_recovery_items_missing', true) !== 'yes') { return new WP_Error('recovery_items_already_present', __('This order already has reviewed product lines.', 'superwoo')); }
        $source = substr(sanitize_text_field((string) $source), 0, 500);
        if (strlen($source) < 8) { return new WP_Error('recovery_items_source_required', __('Describe the checkout, customer, or order evidence used to identify these items.', 'superwoo')); }
        $valid = []; $seen = []; $sum_minor = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) { return new WP_Error('recovery_items_invalid_line', __('A product line is invalid.', 'superwoo')); }
            $id = isset($row['product_id']) && is_scalar($row['product_id']) && ctype_digit((string) $row['product_id']) ? absint($row['product_id']) : 0;
            $quantity = isset($row['quantity']) && is_scalar($row['quantity']) && ctype_digit((string) $row['quantity']) ? (int) $row['quantity'] : 0;
            $subtotal = self::minor($row['subtotal'] ?? null);
            $total = self::minor($row['total'] ?? null);
            if (!$id && !$quantity && null === $subtotal && null === $total) { continue; }
            if (!$id || isset($seen[$id]) || $quantity < 1 || $quantity > 100 || null === $subtotal || null === $total || $total > $subtotal) {
                return new WP_Error('recovery_items_invalid_line', __('Each item needs a unique product, quantity, subtotal and total.', 'superwoo'));
            }
            $product = wc_get_product($id);
            if (!$product instanceof WC_Product || $product->is_type('variable')) { return new WP_Error('recovery_items_product_missing', __('A selected product or exact variation was not found.', 'superwoo')); }
            $seen[$id] = true; $sum_minor += $total;
            $valid[] = ['product' => $product, 'quantity' => $quantity, 'subtotal' => $subtotal, 'total' => $total];
        }
        if (!$valid || $sum_minor !== (int) $record['gross_minor']) {
            return new WP_Error('recovery_items_total_mismatch', __('Product line totals must exactly equal the recorded payment. Shipping and tax amounts need their own evidence.', 'superwoo'));
        }
        foreach ($valid as $line) {
            $item = new WC_Order_Item_Product();
            $item->set_product($line['product']);
            $item->set_quantity($line['quantity']);
            $item->set_subtotal(number_format($line['subtotal'] / 100, 2, '.', ''));
            $item->set_total(number_format($line['total'] / 100, 2, '.', ''));
            $order->add_item($item);
        }
        $order->update_meta_data('_superwoo_recovery_items_missing', 'no');
        $order->update_meta_data('_superwoo_recovery_items_evidence', $source);
        $order->update_meta_data('_superwoo_recovery_items_approved_by', absint($actor_id));
        $order->update_meta_data('_superwoo_recovery_items_approved_at_utc', gmdate('Y-m-d H:i:s'));
        $saved = $order->save();
        return $saved ? absint($saved) : new WP_Error('recovery_items_save_failed', __('WooCommerce could not save the reviewed items.', 'superwoo'));
    }

    private static function minor($value) {
        if (!is_scalar($value) || !preg_match('/^\d+(?:\.\d{1,2})?$/D', trim((string) $value))) { return null; }
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');
        if (strlen($whole) > 10) { return null; }
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}

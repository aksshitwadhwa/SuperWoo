<?php
defined('ABSPATH') || exit;

/** Deterministic, read-only classification using WooCommerce CRUD evidence. */
class SuperWoo_Order_Recovery_Classifier {
    public static function find_existing_order($payment_id, $explicit_order_id, $explicit_order_number = '') {
        if ($payment_id && function_exists('wc_get_orders')) {
            $matches = self::find_orders_paginated(['transaction_id' => $payment_id], static function ($order) use ($payment_id) {
                return method_exists($order, 'get_transaction_id') && hash_equals((string) $payment_id, (string) $order->get_transaction_id());
            });
            if (is_wp_error($matches)) { return ['conflict' => true, 'match' => 'payment_id_lookup_incomplete']; }
            if (count($matches) === 1) { return ['order' => $matches[0], 'match' => 'payment_id']; }
            if (count($matches) > 1) { return ['ambiguous' => true, 'match' => 'payment_id']; }
        }
        if ($explicit_order_id && function_exists('wc_get_order')) {
            $order = wc_get_order(absint($explicit_order_id));
            if ($order) {
                $linked_payment_id = (string) $order->get_transaction_id();
                if ($payment_id && $linked_payment_id && !hash_equals($linked_payment_id, (string) $payment_id)) {
                    return ['conflict' => true, 'order' => $order, 'match' => 'order_id'];
                }
                return ['order' => $order, 'match' => 'order_id'];
            }
        }
        if ($explicit_order_number && function_exists('wc_get_orders')) {
            $matches = self::find_orders_paginated(['search' => $explicit_order_number], static function ($order) use ($explicit_order_number) {
                return method_exists($order, 'get_order_number') && hash_equals((string) $explicit_order_number, (string) $order->get_order_number());
            });
            if (is_wp_error($matches)) { return ['conflict' => true, 'match' => 'order_number_lookup_incomplete']; }
            if (count($matches) === 1) { return ['order' => $matches[0], 'match' => 'order_number']; }
            if (count($matches) > 1) { return ['ambiguous' => true, 'match' => 'order_number']; }
        }
        return null;
    }

    /** Walk every CRUD result page; lookup failures fail closed as conflicts. */
    private static function find_orders_paginated($query, $matches_order) {
        $matches = [];
        $page = 1;
        $per_page = 100;
        do {
            $result = wc_get_orders(array_merge($query, ['limit' => $per_page, 'page' => $page, 'paginate' => true, 'return' => 'objects', 'orderby' => 'ID', 'order' => 'ASC']));
            if (is_wp_error($result) || !is_object($result) || !isset($result->orders, $result->max_num_pages) || !is_array($result->orders)) {
                return new WP_Error('order_lookup_incomplete', 'WooCommerce order lookup did not return a complete paginated result.');
            }
            foreach ($result->orders as $order) {
                if (is_object($order) && $matches_order($order)) { $matches[] = $order; }
            }
            $max_pages = max(1, absint($result->max_num_pages));
            $page++;
        } while ($page <= $max_pages);
        return $matches;
    }

    public static function classify($row, $order_match = null, $rules = [], $duplicate_in_file = false) {
        if ($duplicate_in_file || !empty($order_match['ambiguous'])) {
            return ['classification' => 'duplicate_ambiguous', 'reason' => $duplicate_in_file ? 'Duplicate payment identifier occurs more than once in this CSV.' : 'More than one WooCommerce order has this payment identifier or order number.'];
        }
        if (!empty($order_match['conflict'])) {
            return ['classification' => 'manual_review', 'reason' => 'Explicit order reference conflicts with the order payment identifier.'];
        }
        if (!empty($order_match['order'])) {
            return ['classification' => 'existing_order', 'reason' => 'WooCommerce CRUD lookup matched by ' . sanitize_key($order_match['match']) . '.'];
        }
        if (self::is_explicit_workshop($row, $rules)) {
            return ['classification' => 'workshop_excluded', 'reason' => 'Matched a configured exact Workshop exclusion rule; no WooCommerce order was matched.'];
        }
        if (self::has_workshop_keyword($row)) {
            return ['classification' => 'manual_review', 'reason' => 'Workshop-related text found; generic keywords cannot exclude or recover a transaction.'];
        }
        $reported = strtolower((string) ($row['reported_status'] ?? ''));
        if (in_array($reported, ['captured', 'paid', 'payment_captured', 'success', 'succeeded'], true)) {
            if (!empty($row['payment_id']) || !empty($row['razorpay_order_id']) || !empty($row['receipt']) || !empty($row['wc_order_id'])) {
                return ['classification' => 'missing_order_candidate', 'reason' => 'CSV reports a captured-like status and no local order match; provider status remains unverified.'];
            }
            return ['classification' => 'manual_review', 'reason' => 'Captured-like status is reported but transaction identifiers are missing and status is unverified.'];
        }
        if (in_array($reported, ['failed', 'authorized', 'created', 'refunded', 'partially_refunded', 'partially refunded'], true)) {
            return ['classification' => 'manual_review', 'reason' => 'Payment state is reported by CSV only and has not been provider-verified.'];
        }
        if (!empty($row['payment_id']) && empty($row['wc_order_id']) && empty($row['razorpay_order_id']) && empty($row['receipt'])) {
            return ['classification' => 'manual_review', 'reason' => 'Payment identifier has no order or receipt reference; amount/customer data cannot establish a match.'];
        }
        return ['classification' => 'manual_review', 'reason' => 'Insufficient verified evidence for a stronger classification.'];
    }

    public static function add_order_reference($row, $order_match) {
        if (!empty($order_match['order']) && is_object($order_match['order']) && method_exists($order_match['order'], 'get_id')) {
            $row['wc_order_id'] = absint($order_match['order']->get_id());
        }
        return $row;
    }

    public static function attach_order_totals($row, $order) {
        $row['wc_total_minor'] = null;
        $row['reconciled_difference_minor'] = null;
        if (!$order || !is_object($order) || !method_exists($order, 'get_total') || !method_exists($order, 'get_currency')) { return $row; }
        $currency = strtoupper((string) $order->get_currency());
        if ($row['currency'] && $currency && $row['currency'] !== $currency) { return $row; }
        $precision = self::currency_precision($currency ?: $row['currency']);
        $row['wc_total_minor'] = (int) round((float) $order->get_total() * pow(10, $precision));
        if (null !== $row['gross_minor']) { $row['reconciled_difference_minor'] = (int) $row['gross_minor'] - (int) $row['wc_total_minor']; }
        return $row;
    }

    private static function currency_precision($currency) {
        if (in_array($currency, ['JPY','KRW','VND','CLP','XAF','XOF'], true)) { return 0; }
        if (in_array($currency, ['BHD','KWD','OMR','JOD','TND'], true)) { return 3; }
        if (in_array($currency, ['CLF','UYW'], true)) { return 4; }
        return 2;
    }

    private static function is_explicit_workshop($row, $rules) {
        $rules = is_array($rules) ? $rules : [];
        $receipt = strtolower((string) ($row['receipt'] ?? ''));
        foreach ((array) ($rules['receipt_prefixes'] ?? []) as $prefix) {
            $prefix = strtolower(trim((string) $prefix));
            if ($prefix !== '' && substr($receipt, 0, strlen($prefix)) === $prefix) { return true; }
        }
        $notes = (array) ($row['notes'] ?? []);
        foreach ((array) ($rules['metadata'] ?? []) as $key => $expected) {
            $key = sanitize_key($key);
            if (isset($notes[$key]) && is_scalar($expected) && hash_equals(strtolower(trim((string) $expected)), strtolower(trim((string) $notes[$key])))) { return true; }
        }
        foreach ((array) ($rules['checkout_sources'] ?? []) as $source) {
            if (isset($notes['checkout_source']) && hash_equals(strtolower(trim((string) $source)), strtolower(trim((string) $notes['checkout_source'])))) { return true; }
        }
        return false;
    }

    private static function has_workshop_keyword($row) {
        $text = strtolower((string) ($row['description'] ?? '') . ' ' . wp_json_encode($row['notes'] ?? []));
        return (bool) preg_match('/\bworkshop\b|\bmasterclass\b|\bwebinar\b/', $text);
    }
}

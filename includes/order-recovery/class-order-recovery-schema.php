<?php
defined('ABSPATH') || exit;

/** Versioned schema for read-only Razorpay import evidence. */
class SuperWoo_Order_Recovery_Schema {
    const VERSION = '4';
    const OPTION = 'superwoo_order_recovery_schema_version';

    public static function table($name) {
        global $wpdb;
        return $wpdb->prefix . 'superwoo_order_recovery_' . sanitize_key($name);
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $batches = self::table('batches');
        $transactions = self::table('transactions');
        $links = self::table('batch_transactions');
        dbDelta("CREATE TABLE {$batches} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_key char(64) NOT NULL,
            filename varchar(190) NOT NULL DEFAULT '',
            file_sha256 char(64) NOT NULL,
            row_count int(10) unsigned NOT NULL DEFAULT 0,
            inserted_count int(10) unsigned NOT NULL DEFAULT 0,
            updated_count int(10) unsigned NOT NULL DEFAULT 0,
            duplicate_count int(10) unsigned NOT NULL DEFAULT 0,
            error_count int(10) unsigned NOT NULL DEFAULT 0,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_key (batch_key),
            KEY created_at (created_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$transactions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            idempotency_key char(64) NOT NULL,
            payment_id varchar(190) NOT NULL DEFAULT '',
            razorpay_order_id varchar(190) NOT NULL DEFAULT '',
            wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            wc_order_number varchar(190) NOT NULL DEFAULT '',
            receipt varchar(190) NOT NULL DEFAULT '',
            description text NULL,
            notes_json longtext NULL,
            reported_status varchar(64) NOT NULL DEFAULT '',
            verification_state varchar(24) NOT NULL DEFAULT 'unverified',
            verified_at_utc datetime NULL,
            verification_error varchar(64) NOT NULL DEFAULT '',
            provider_status varchar(64) NOT NULL DEFAULT '',
            provider_currency char(3) NOT NULL DEFAULT '',
            provider_amount_minor bigint(20) NULL,
            provider_amount_refunded_minor bigint(20) NULL,
            provider_refund_status varchar(32) NOT NULL DEFAULT '',
            provider_refunds_complete tinyint(1) NOT NULL DEFAULT 0,
            provider_captured tinyint(1) NULL,
            provider_order_id varchar(190) NOT NULL DEFAULT '',
            provider_match_result varchar(190) NOT NULL DEFAULT '',
            provider_created_at_utc datetime NULL,
            provider_refunds_json longtext NULL,
            classification varchar(48) NOT NULL DEFAULT 'manual_review',
            classification_reason varchar(190) NOT NULL DEFAULT '',
            currency char(3) NOT NULL DEFAULT '',
            gross_minor bigint(20) NULL,
            wc_total_minor bigint(20) NULL,
            fee_minor bigint(20) NULL,
            fee_gst_minor bigint(20) NULL,
            refund_minor bigint(20) NULL,
            refund_reference varchar(500) NOT NULL DEFAULT '',
            refund_status varchar(64) NOT NULL DEFAULT '',
            refund_at_utc datetime NULL,
            reconciled_difference_minor bigint(20) NULL,
            paid_at_utc datetime NULL,
            customer_email varchar(190) NOT NULL DEFAULT '',
            customer_phone varchar(40) NOT NULL DEFAULT '',
            missing_fields text NULL,
            data_warnings text NULL,
            source_values_json longtext NULL,
            source_row_hash char(64) NOT NULL,
            recovered_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_batch_id bigint(20) unsigned NOT NULL,
            last_batch_id bigint(20) unsigned NOT NULL,
            duplicate_count int(10) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY payment_id (payment_id),
            KEY razorpay_order_id (razorpay_order_id),
            KEY wc_order_id (wc_order_id),
            KEY classification (classification),
            KEY reported_status (reported_status),
            KEY paid_at_utc (paid_at_utc),
            KEY currency (currency)
        ) {$charset};");
        dbDelta("CREATE TABLE {$links} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            transaction_id bigint(20) unsigned NOT NULL,
            source_row_number int(10) unsigned NOT NULL,
            source_row_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY batch_row (batch_id,source_row_number),
            KEY transaction_id (transaction_id),
            KEY batch_id (batch_id)
        ) {$charset};");
        update_option(self::OPTION, self::VERSION, false);
    }

    public static function maybe_install() {
        if (self::VERSION !== (string) get_option(self::OPTION, '')) {
            self::install();
        }
    }
}

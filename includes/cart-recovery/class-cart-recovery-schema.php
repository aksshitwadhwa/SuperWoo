<?php
defined('ABSPATH') || exit;

/** Installs and upgrades Cart Recovery's dedicated operational tables. */
class SuperWoo_Cart_Recovery_Schema {
    const VERSION = '1';
    const OPTION = 'superwoo_cart_recovery_schema_version';

    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'superwoo_recovery_';

        dbDelta("CREATE TABLE {$prefix}carts (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            session_hash char(64) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_name varchar(100) NOT NULL DEFAULT '', last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '', email_hash char(64) NOT NULL DEFAULT '',
            phone varchar(32) NOT NULL DEFAULT '', phone_hash char(64) NOT NULL DEFAULT '',
            currency varchar(12) NOT NULL DEFAULT '', cart_fingerprint char(64) NOT NULL DEFAULT '',
            subtotal decimal(20,6) NOT NULL DEFAULT 0, total decimal(20,6) NOT NULL DEFAULT 0,
            coupons longtext NULL, context longtext NULL,
            recovery_token_hash char(64) NOT NULL DEFAULT '', recovery_token_expires_at datetime NULL,
            recovered_at datetime NULL, abandoned_at datetime NULL, checkout_started_at datetime NULL,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0, recovery_channel varchar(32) NOT NULL DEFAULT '',
            recovery_workflow_id bigint(20) unsigned NOT NULL DEFAULT 0, recovery_step_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL, updated_at datetime NOT NULL, last_activity_at datetime NOT NULL, expires_at datetime NULL,
            PRIMARY KEY  (id), UNIQUE KEY public_id (public_id), UNIQUE KEY session_hash (session_hash),
            KEY status_activity (status,last_activity_at), KEY abandoned_at (abandoned_at), KEY customer_id (customer_id),
            KEY email_hash (email_hash), KEY phone_hash (phone_hash), KEY order_id (order_id), KEY recovery_token_hash (recovery_token_hash), KEY expires_at (expires_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}cart_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, cart_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL, variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_name text NOT NULL, variation_attributes longtext NULL, quantity int(11) unsigned NOT NULL DEFAULT 1,
            unit_price decimal(20,6) NOT NULL DEFAULT 0, line_subtotal decimal(20,6) NOT NULL DEFAULT 0, line_total decimal(20,6) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY cart_id (cart_id), KEY product_id (product_id), KEY variation_id (variation_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, cart_id bigint(20) unsigned NOT NULL,
            event_type varchar(64) NOT NULL, channel varchar(32) NOT NULL DEFAULT '', data longtext NULL,
            idempotency_key char(64) NOT NULL DEFAULT '', created_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY idempotency_key (idempotency_key), KEY cart_created (cart_id,created_at), KEY event_created (event_type,created_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}workflows (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, name varchar(190) NOT NULL, status varchar(20) NOT NULL DEFAULT 'draft', trigger_type varchar(40) NOT NULL DEFAULT 'cart_abandoned', conditions longtext NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY status_trigger (status,trigger_type)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}workflow_steps (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, workflow_id bigint(20) unsigned NOT NULL, step_order int(11) unsigned NOT NULL DEFAULT 0, action_type varchar(40) NOT NULL, configuration longtext NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), KEY workflow_order (workflow_id,step_order)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}executions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, cart_id bigint(20) unsigned NOT NULL, workflow_id bigint(20) unsigned NOT NULL, current_step_id bigint(20) unsigned NOT NULL DEFAULT 0, status varchar(32) NOT NULL DEFAULT 'pending', idempotency_key char(64) NOT NULL, attempts int(11) unsigned NOT NULL DEFAULT 0, next_run_at datetime NULL, locked_at datetime NULL, completed_at datetime NULL, last_error text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY cart_workflow (cart_id,workflow_id), UNIQUE KEY idempotency_key (idempotency_key), KEY status_next (status,next_run_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, cart_id bigint(20) unsigned NOT NULL, execution_id bigint(20) unsigned NOT NULL DEFAULT 0, workflow_step_id bigint(20) unsigned NOT NULL DEFAULT 0, channel varchar(32) NOT NULL, status varchar(32) NOT NULL DEFAULT 'queued', provider_message_id varchar(190) NOT NULL DEFAULT '', idempotency_key char(64) NOT NULL, subject text NULL, payload longtext NULL, error_message text NULL, sent_at datetime NULL, opened_at datetime NULL, clicked_at datetime NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY idempotency_key (idempotency_key), KEY cart_id (cart_id), KEY execution_id (execution_id), KEY provider_message (channel,provider_message_id), KEY status_created (status,created_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}offers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, cart_id bigint(20) unsigned NOT NULL, workflow_step_id bigint(20) unsigned NOT NULL DEFAULT 0, coupon_id bigint(20) unsigned NOT NULL DEFAULT 0, coupon_code_hash char(64) NOT NULL DEFAULT '', offer_type varchar(32) NOT NULL, amount decimal(20,6) NOT NULL DEFAULT 0, expires_at datetime NULL, redeemed_at datetime NULL, created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY cart_id (cart_id), KEY coupon_id (coupon_id), KEY expires_at (expires_at)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}connections (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, provider varchar(32) NOT NULL, status varchar(32) NOT NULL DEFAULT 'disconnected', account_name varchar(190) NOT NULL DEFAULT '', phone varchar(32) NOT NULL DEFAULT '', credentials longtext NULL, metadata longtext NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL,
            PRIMARY KEY (id), UNIQUE KEY provider (provider)
        ) {$charset};");
        dbDelta("CREATE TABLE {$prefix}suppressions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, channel varchar(32) NOT NULL, identifier_hash char(64) NOT NULL, reason varchar(100) NOT NULL DEFAULT '', created_at datetime NOT NULL, expires_at datetime NULL,
            PRIMARY KEY (id), UNIQUE KEY channel_identifier (channel,identifier_hash), KEY expires_at (expires_at)
        ) {$charset};");

        update_option(self::OPTION, self::VERSION, false);
    }

    public static function maybe_install() {
        if (self::VERSION !== get_option(self::OPTION)) {
            self::install();
        }
    }
}

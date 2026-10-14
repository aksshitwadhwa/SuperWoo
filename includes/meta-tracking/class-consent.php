<?php
defined('ABSPATH') || exit;

/** Consent resolver. No consent integration means unknown, which fails closed. */
class SuperWoo_Meta_Tracking_Consent {
    const GRANTED = 'granted';
    const DENIED = 'denied';
    const UNKNOWN = 'unknown';

    public static function state() {
        $state = apply_filters('superwoo_meta_marketing_consent_state', self::UNKNOWN);
        return in_array($state, [self::GRANTED, self::DENIED, self::UNKNOWN], true) ? $state : self::UNKNOWN;
    }

    public static function dispatch_allowed() {
        return self::GRANTED === self::state();
    }
}

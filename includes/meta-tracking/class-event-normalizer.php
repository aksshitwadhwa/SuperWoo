<?php
defined('ABSPATH') || exit;

/** Builds a minimal future event envelope; Phase 3A never dispatches it. */
class SuperWoo_Meta_Tracking_Event_Normalizer {
    public static function normalize($event) {
        $event = is_array($event) ? $event : [];
        $checkout_type = isset($event['checkout_type']) ? sanitize_key($event['checkout_type']) : 'unknown';
        if (!in_array($checkout_type, ['classic', 'blocks', 'unknown'], true)) {
            $checkout_type = 'unknown';
        }

        $source = isset($event['source']) ? sanitize_key($event['source']) : 'browser';
        if (!in_array($source, ['browser', 'server'], true)) {
            $source = 'browser';
        }

        $event_name = isset($event['event_name']) ? preg_replace('/[^A-Za-z0-9_]/', '', (string) $event['event_name']) : '';
        $event_id = isset($event['event_id']) ? preg_replace('/[^A-Za-z0-9_-]/', '', (string) $event['event_id']) : '';

        return [
            'event_name' => substr($event_name, 0, 64),
            'event_id' => substr($event_id, 0, 128),
            'timestamp' => isset($event['timestamp']) ? absint($event['timestamp']) : time(),
            'source' => $source,
            'checkout_type' => $checkout_type,
        ];
    }
}

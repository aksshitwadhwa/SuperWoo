<?php
defined('ABSPATH') || exit;

/** Native, deliberately small recovery email sender. Provider tracking is not claimed. */
class SuperWoo_Cart_Recovery_Email {
    private $repository;
    public function __construct($repository) { $this->repository = $repository; }
    public function send($cart, $url) {
        $email = sanitize_email($cart['email'] ?? '');
        if (!$email || $this->repository->is_suppressed('email', $email)) { return false; }
        $key = $this->repository->hash('email:' . $cart['id'] . ':' . gmdate('YmdH'));
        $subject = sprintf(__('Your cart is waiting at %s', 'superwoo'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $body = '<h2>' . esc_html__('You left items in your cart', 'superwoo') . '</h2><p>' . esc_html(sprintf(__('Your cart total is %s.', 'superwoo'), wc_price((float) $cart['total']))) . '</p><p><a href="' . esc_url($url) . '">' . esc_html__('Return to checkout', 'superwoo') . '</a></p>';
        $sent = wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        $this->repository->message(['cart_id' => (int) $cart['id'], 'channel' => 'email', 'status' => $sent ? 'sent' : 'failed', 'idempotency_key' => $key, 'subject' => $subject, 'payload' => wp_json_encode(['recovery_url' => $url]), 'sent_at' => $sent ? $this->repository->now() : null, 'error_message' => $sent ? '' : 'wp_mail returned false']);
        $this->repository->event((int) $cart['id'], $sent ? 'recovery_email_sent' : 'recovery_email_failed', [], 'email', 'email-event:' . $key);
        return $sent;
    }
}

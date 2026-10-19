# SuperWoo Order and Payment Isolation Audit

**Date:** 2026-10-19  
**Scope:** Source audit of SuperWoo runtime code for WooCommerce order-status and payment lifecycle hooks, gateway execution, and direct order/payment mutations. No live site, order, payment, or production setting was accessed or changed.

## Result

SuperWoo now registers **no WooCommerce order-status or payment lifecycle listeners**. The previous status/payment/checkout diagnostic callbacks were removed from the plugin composition root. Those callbacks only logged events, but they ran synchronously on WooCommerce lifecycle hooks and could be mistaken for order management or add unnecessary work to those paths.

Source search found no SuperWoo runtime calls to a gateway `process_payment()`/`process_refund()`, WooCommerce `payment_complete()`, `set_paid()`, or `wc_create_order()`. The Razorpay abandoned-cart receiver remains a separate receive-only endpoint: it authenticates, rate-limits, records a redacted receipt summary, and returns a response; it does not load, create, or update WooCommerce orders or payments.

## Remaining WooCommerce integrations

These integrations are outside order-status/payment lifecycle hooks and do not set order status, invoke a gateway, or mark a payment paid:

- Currency and bundle-offer modules intentionally affect displayed/cart pricing and totals when enabled. They can therefore affect the amount WooCommerce presents for payment, which is their product behavior; they do not authorize, capture, refund, or confirm payments.
- Currency and cart-drawer consent modules add their own metadata during order creation. Shoppable Videos adds attribution metadata to order line items.
- Cart Recovery observes checkout order creation to update its own recovery record and attach recovery metadata. Its recovery status is internal to SuperWoo, not WooCommerce order status. This hook does not handle payment confirmation.
- Cart drawer and product modules have cart mutation hooks for their own cart features; these are not payment gateway hooks.

These features were left intact to avoid changing pricing, consent, attribution, cart, or recovery behavior. This audit guarantees no SuperWoo callback is attached to WooCommerce order-status or payment-completion/failure hooks; it does not claim that SuperWoo has no WooCommerce integration at all.

## Changes

- Removed SuperWoo's checkout-validation, checkout-order-created, order-status-changed, payment-complete, and failed-status diagnostic listeners and their logging methods from `includes/class-plugin.php`.
- Added `tests/order-payment-isolation.test.php` as a regression guard against reintroducing order-status/payment hooks or direct gateway/payment operations.

## Verification

- PHP lint passed for the changed plugin file and regression test.
- Static boundary test passed: 70 assertions.
- Existing Meta observer Node suite passed: 28 tests.
- `git diff --check` passed.
- Full source search under runtime PHP/JS found no order-status/payment lifecycle hook registrations or direct gateway/payment operations.
- No checkout or payment runtime was exercised, and no production changes were made.

## Status

```text
ORDER_STATUS_HOOKS_REGISTERED=0
PAYMENT_LIFECYCLE_HOOKS_REGISTERED=0
DIRECT_GATEWAY_OPERATIONS=0
DIRECT_WOOCOMMERCE_PAYMENT_MUTATIONS=0
RAZORPAY_ABANDONED_RECEIVER_ORDER_PAYMENT_MUTATIONS=0
INTENTIONAL_CART_TOTAL_HOOKS=PRESENT — currency and bundle-offer features
ORDER_METADATA_HOOKS=PRESENT — currency, WhatsApp consent, cart recovery, video attribution
PRODUCTION_CHANGES=NO
LIVE_PAYMENT_TESTED=NO
```

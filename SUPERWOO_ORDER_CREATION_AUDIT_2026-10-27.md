# SuperWoo order creation audit — 2026-10-27

## Scope and conclusion

This is the requested read-only Stage A audit. The repository contains plugin source and mock tests, but no connected WooCommerce store, Razorpay transaction export, checkout snapshot, order database, or runtime logs. It cannot establish why a particular live payment has no order.

The immediate, provable reason **SuperWoo Order Recovery does not create an order** is that its admin workflow has no order writer or approval action. The screen explicitly says order creation is unavailable. A CSV import stores and classifies payment rows, provider verification reads Razorpay payment facts, and preview evaluates eligibility; none persists a WooCommerce order. This is an intentional safety gate because no authoritative checkout evidence adapter is connected.

For **new checkouts**, SuperWoo does not call a Razorpay payment handler or create an order. WooCommerce and the installed checkout/gateway extensions own those operations. A paid Razorpay transaction without a WooCommerce order therefore needs a transaction-level trace through the actual checkout and gateway installation. This source audit alone cannot assign the failure to SuperWoo, WooCommerce, the gateway, a webhook, or a server error.

## Source findings

| Area | Source evidence | Finding |
|---|---|---|
| Plugin boot | `superwoo.php`; `includes/class-plugin.php` | Loads the recovery admin and a receive-only Razorpay abandoned-cart endpoint. The runtime has no `wc_create_order()`, gateway `process_payment()`, `payment_complete()`, or order status writer. The cart recovery coordinator exists in source but is not wired into the plugin boot path. |
| Recovery admin | `includes/order-recovery/class-order-recovery-admin.php` | Provides CSV import, exact Workshop rules, read-only provider verification, previews and export. It advertises that order creation is unavailable. There is no approval or create-order request handler. |
| Recovery eligibility | `includes/order-recovery/class-order-recovery-preview.php` | Requires verified captured payment, complete refund evidence, absence of an existing order, and authoritative checkout/backup evidence with exact line items, billing, shipping, discounts, taxes, fees, currency and arithmetic. The admin passes `null` for checkout evidence, so an imported record cannot become ready for order creation. |
| Matching | `includes/order-recovery/class-order-recovery-classifier.php` | Searches WooCommerce through CRUD for transaction ID, explicit order ID and order number. Amount or customer identity does not establish an exact match. A configured Workshop identifier returns `workshop_excluded` **before** the existing-order match is classified; this can hide a mixed or already existing order from the recovery view. It does not delete or change that WooCommerce order. |
| Workshop boundary | Same classifier and admin | Receipt prefix, metadata key/value and checkout source rules can exclude an entire transaction. A generic “workshop” word sends it to manual review. No line-level distinction between Workshop and store products exists in recovery classification. |
| Free gifts at live cart | `includes/class-bundle-offers.php` | If offers are enabled and the selected `price_gift` rule qualifies, the plugin adds each configured gift product to the WooCommerce cart with `superwoo_free_gift`, quantity one, and a zero price. The selected offer is the first qualifying enabled rule by priority; a higher-priority discount can prevent the gift rule from being selected. Unavailable or non-purchasable gift products are skipped. There is no separate Workshop checkout integration in this module. |
| Currency | `includes/class-currency.php` | Also preserves a zero price for `superwoo_free_gift` cart lines when currency conversion runs. It does not create orders. |
| Provider endpoint | `includes/class-razorpay-abandoned-webhook.php` | Receives an abandoned-cart notification and records a redacted summary. It does not confirm payment or create WooCommerce orders. |

## Exact-order rule for the next stage

Create or repair only the WooCommerce order proven by an exact payment-to-checkout link. Preserve every proven checkout line, including a free product with product/variation ID, quantity and zero total. Do not add a free product based only on a current offer rule, a Workshop label, a payment amount, or a customer identity. Current rules may differ from those at the historical checkout.

Leave Workshop payment handling unchanged. A Workshop-only payment remains excluded from store-order recovery. A transaction showing both a Workshop and a free store item must go to manual review until its original checkout record proves the exact order composition and payment relationship. The current transaction-wide exclusion cannot safely decide such a case. If the original checkout was a normal WooCommerce cart, its gift line should be inspected in that order. If the Workshop used a separate checkout, the existence of a free item must be proven from that checkout's persisted record before proposing any WooCommerce item or order. Do not create a zero-value order solely from a Workshop payment.

## What is needed to find the live failure

1. A sanitized example of a successful Razorpay payment with no visible WooCommerce order: payment ID, Razorpay order ID, receipt, event time/timezone, payment status and any WooCommerce reference. Keep credentials and full customer details out of the repository.
2. The corresponding WooCommerce order search result, including Trash and all statuses, using the exact payment/reference identifiers; note whether HPOS is enabled.
3. The active checkout method (classic, Blocks, payment link, or separate Workshop flow), Razorpay gateway plugin/version, and gateway/webhook settings relevant to that checkout.
4. Sanitized WooCommerce/Razorpay/WordPress logs around the checkout time, plus a checkout/session snapshot if one exists. For a free item case, include the original cart/order line IDs, quantity and zero price, not just the present offer configuration.
5. One controlled test on a nonproduction copy: submit a cart with the same paid product and qualifying free item, observe cart lines immediately before checkout, order creation response, gateway result, WooCommerce order lines/metadata, and any fatal or webhook error. Run a separate Workshop-only test to verify exclusion.

These inputs distinguish checkout failure before WooCommerce order persistence from an order stored under another ID/status, gateway callback failure after persistence, and recovery-screen expectations. They also establish whether the gift was actually in the submitted cart. Do not use the payment amount alone to invent an order or gift.

## Implementation plan after Stage A

1. Validate exact identifiers and reconstruct the incident timeline in a nonproduction restore. Confirm HPOS/legacy storage through WooCommerce APIs.
2. Separate transaction-level Workshop payment exclusion from line-level mixed-checkout review. Check for an existing order before presenting an exclusion result, while never recovering a Workshop-only payment as a store order.
3. Connect a validated, immutable checkout/backup evidence adapter only if one exists. Include original free lines and provenance. Continue to fail closed when the payment-to-checkout join or any order line is ambiguous.
4. Preview one exact order with its complete monetary breakdown and verify against Razorpay gross without treating Razorpay processing fees as a discount. Then design an approved, idempotent WooCommerce CRUD writer with side-effect controls and an audit log. No writer should be enabled before real runtime validation.
5. Test paid store checkout, Workshop-only checkout, mixed Workshop/free checkout, missing gift, duplicate payment, repeated import, HPOS and legacy storage, gateway callback failure, and retries.

## Verification, changes and rollback

No plugin code, database schema, live order, payment or Workshop flow was changed. This audit file is the only new artifact. No production data, migration, provider call or deployment was used. Rollback is deletion of this report file.

Local mock tests passed: `order-recovery-import.test.php` (29 assertions), `order-recovery-preview.test.php` (21 checks), `free-gift-pricing.test.php` (zero-priced gift), and `order-payment-isolation.test.php` (86 assertions). These do not verify a live checkout, HPOS mode, gateway behavior, or actual missing order.

Security boundary: recovery must continue to require capability and nonce checks, exact identifiers, provider verification, complete checkout evidence, duplicate prevention and explicit approval before any future order write. Keep customer data and gateway credentials out of reports and tests.

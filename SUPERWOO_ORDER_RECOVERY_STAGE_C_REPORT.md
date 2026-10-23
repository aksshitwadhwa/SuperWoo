# SuperWoo Order Recovery - Stage C Report

**Date:** 2026-10-23  
**Scope:** Optional read-only Razorpay payment verification and evidence correlation. No production database or provider account was accessed.

## Delivered

- Added a manually triggered Razorpay verifier using HTTPS `GET` requests only: fetch one payment by its exact `pay_...` identifier, then fetch that payment's refund pages. Request timeout is 10 seconds, redirects are disabled, TLS validation stays enabled, and refund pagination is bounded to 300 records.
- Credentials are read only from `SUPERWOO_RAZORPAY_KEY_ID` and `SUPERWOO_RAZORPAY_KEY_SECRET` constants in protected site configuration (for example `wp-config.php`). They are not stored in plugin options, logs, or database records. No automatic or background provider calls are made.
- Added schema version 2 fields for verification time/state, safe error code, provider status, currency, amount/refund amounts, capture flag, Razorpay order reference, provider timestamp, normalized refund list, and correlation result. Raw API responses and customer fields are not retained.
- Added a capability and nonce protected admin action on an imported record. It compares provider payment ID, currency, amount, and Razorpay order ID with imported evidence, then uses WooCommerce CRUD through the existing classifier to look for an exact payment-ID match. Multiple local orders and conflicting references are recorded as conflicts. Amount, email, customer, and timestamp do not establish an order match.
- The detail view displays verification state/time/error, provider financial/status data, correlation result, and a manual read-only verify button. API failures remain error/unverified and never mean failed payment. Verification does not change WooCommerce orders or advance recovery classification.
- Left the existing abandoned-cart receiver and orphaned cart-recovery modules unchanged and unwired.

## Runtime evidence not available

The source audit and Stage B report establish that the active gateway/Magic Checkout implementation is outside this repository. No staging site, active plugin versions, gateway metadata contract, API credentials, sample historical CSV, cart/session records, WooCommerce orders, HPOS setting, or verified Workshop examples were available. Accordingly:

- No gateway-specific WooCommerce metadata keys or receipt-to-order rules have been guessed.
- Razorpay `order_id` is retained as provider evidence; it is not assumed to be a WooCommerce order ID. A Razorpay order reference without explicit checkout mapping is not a Woo order match.
- Existing WooCommerce order lookup is by exact provider payment ID using `wc_get_orders()`/`wc_get_order()` CRUD only. No legacy posts or HPOS tables are queried.
- Stage B Workshop exact rules remain configurable and generic keywords remain manual review. Stage C does not infer Workshop exclusions from provider notes.
- Reconstruction readiness remains unestablished. Verified payment evidence alone does not supply product, address, tax, shipping, coupon, or fulfillment details; no order creation exists in this stage.

## Tests and checks

Passed:

- `php tests/order-recovery-import.test.php` - 26 assertions.
- `php tests/order-recovery-provider.test.php` - 15 mocked checks for successful payment/refund normalization, bounded request parameters, authentication failure, rate limiting, timeout, provider ID mismatch, explicit provider failed status, missing credentials, amount/currency/order-reference mismatch, multiple WooCommerce matches, exact CRUD correlation, and conflicting imported Woo order references.
- `php tests/order-payment-isolation.test.php` - 84 assertions.
- `php tests/security-hardening.test.php` - 12 assertions.
- `php tests/razorpay-abandoned-webhook.test.php` - 32 assertions.
- `php -l` on the provider, admin, repository, schema, and bootstrap files.
- `git diff --check`.

No live Razorpay credentials were used. The new test mocks WordPress HTTP responses; staging behavior still requires verification against the actual WordPress HTTP stack and permitted API account.

## Security and limitations

Only GET endpoints are implemented; no capture/refund/create/update API calls exist. The verifier intentionally performs at most four provider HTTP requests (one payment and up to three refund pages), each bounded by a 10 second timeout. Non-2xx responses, malformed responses, transport errors, missing credentials and mismatched IDs are stored as safe error codes; provider error bodies are not logged or retained. Provider status is shown separately from CSV reported status. No automatic status changes, notifications, order writes, or customer messages occur.

Schema v2 is installed by the existing admin-page schema check using `dbDelta`; no production migration was run. The first authorized admin visit to Order Recovery on staging will apply the additive schema change.

## Staging verification steps

1. Back up the staging database and deploy this branch only to staging. Do not run production migrations.
2. Confirm WooCommerce and active Razorpay gateway/Magic Checkout names and versions, HPOS mode, and the documented payment/order metadata keys. Supply a sanitized sample covering captured, failed, authorized, refunded/partial-refund, duplicate, and Workshop cases.
3. Create a restricted staging Razorpay read-only key pair and set the two constants in protected staging configuration. Confirm no credentials appear in WordPress options, logs, database fields, or rendered pages.
4. Visit **SuperWoo > Order Recovery** to apply schema v2, import sanitized CSV evidence, and confirm identical re-imports remain idempotent.
5. On representative records, use the read-only verify action. Confirm exact payment-ID order correlation via WooCommerce CRUD; review provider order IDs, amount/currency mismatches, multiple Woo order conflicts, auth failure, 404, timeout, and 429 handling. Confirm all failures remain unverified and no order, stock, status, email, webhook, or customer notification changes occur.
6. Validate the actual gateway's checkout/session metadata and Workshop rules against authorized staging fixtures. Keep unsupported records in manual review; do not proceed to order reconstruction without a separate approval.

## Stage C status

```text
READ_ONLY_PROVIDER_CLIENT=IMPLEMENTED (optional, manually triggered)
PRODUCTION_PROVIDER_CALLS=NO
PRODUCTION_DATABASE_MIGRATION=NO
ORDER_OR_PAYMENT_MUTATION=NO
CHECKOUT_OR_WEBHOOK_CHANGE=NO
RUNTIME_GATEWAY_CONTRACT=UNAVAILABLE
RECONSTRUCTION=NOT_IMPLEMENTED
```



Full-suite note: all other PHP and JavaScript tests passed, but 	ests/updater-hardening.test.php and 	ests/updater-release-integration.test.php could not run because this PHP CLI lacks the Sodium extension (SODIUM_CRYPTO_SIGN_SEEDBYTES / sodium_crypto_sign_seed_keypair).

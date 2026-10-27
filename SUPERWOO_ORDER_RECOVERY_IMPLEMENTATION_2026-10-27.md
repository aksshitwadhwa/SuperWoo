# SuperWoo order recovery implementation — 2026-10-27

Local plugin version: **1.5.7** (`superwoo.php` header and runtime constant; `readme.txt` stable tag). The published update feed and package have not been changed or published by this local work.

## Implemented

- Recovery classification now checks duplicates, conflicts and exact existing WooCommerce matches before a configured Workshop exclusion. An already existing order is visible for review even if its payment has a Workshop receipt; the order and payment remain unchanged. An unmatched Workshop payment still stays excluded.
- A qualifying zero-priced SuperWoo gift in a normal WooCommerce cart is marked on the created order line with its exact gift product or variation ID. The hook does not add a new item or alter the line total.
- Reconstruction preview now requires the checkout evidence to contain the exact Razorpay payment ID. A gift line proved by that evidence remains in the proposed order at zero. A line marked as a gift with a nonzero subtotal, total or tax is rejected.
- The CSV importer now recognizes the supplied reconciliation and raw-payment header names, spreadsheet-style integer order IDs such as `8051.0`, and day-first dates. It keeps the existing warning that a timestamp without a timezone is interpreted as UTC.
- The payment importer rejects the mixed transactions ledger because it contains settlement and refund rows that are not captured checkout payments.
- An administrator can now create an individually confirmed **payment-only WooCommerce review order** for a referenced, missing, unrefunded captured payment. The action verifies Razorpay again, checks WooCommerce for an existing order, excludes Workshop-related records, and claims both the payment ID and historical ID in database-backed options before writing. Repeated or interrupted requests fail closed for manual reconciliation.
- The order uses the custom `Recovery review` status. It records the captured amount, Razorpay payment ID/order ID, currency, available email/phone, source fingerprint, imported fees as separate metadata, provider timestamp and historical WooCommerce ID. It has no product, gift, shipping or tax lines; no payment capture/refund or `payment_complete()` call occurs. The WooCommerce order screen labels this limitation.

## Data and write boundary

The owner provided the paths to payment and order exports. They were inspected read-only in place and were not copied into the repository. Provider payment data and the supplied order export do not establish the exact WooCommerce products, variations, quantities, free gifts, shipping, discounts or taxes for a missing order. The new writer therefore creates a clearly marked payment-only review order, never a complete product order. It is not triggered by CSV import; an administrator must open and confirm one record at a time. No historical or live production order was created, changed or deployed during this implementation.

Workshop-only payments remain outside store-order recovery. A claimed Workshop plus free-item transaction remains manual review until an original checkout record proves its complete order lines and exact payment link. The current code does not manufacture a free item from a present-day offer rule. The exact historical WooCommerce ID is saved as protected metadata; WooCommerce assigns a new internal ID through CRUD.

## Files and database

Changed `superwoo.php`, `includes/class-plugin.php`, `includes/class-bundle-offers.php`, `includes/order-recovery/class-order-recovery-admin.php`, `includes/order-recovery/class-order-recovery-classifier.php`, `includes/order-recovery/class-order-recovery-csv.php`, `includes/order-recovery/class-order-recovery-preview.php`; added `includes/order-recovery/class-order-recovery-payment-record.php` and its focused test. No recovery-table schema migration was added. Successful approvals create WooCommerce orders and two non-autoloaded WordPress option claims; no approval has been executed against production.

## Supplied export findings

The reconciliation file contains 535 distinct captured INR payment IDs. An independent exact ID comparison found 331 references in the 1,074-row WooCommerce export and 41 references absent from it. The other 163 payments have no WooCommerce reference. Of those, 157 have one of two exact descriptions, `Workshop Purchase` or `Workshop/Course Purchase`. The current classifier sends their Workshop text to manual review unless an exact exclusion rule is configured; no order creation path exists for them. The remaining 6 have no WooCommerce reference and require manual review. The 331 found references include 24 cancelled orders and 3 with a reported payment/order total difference; these are existing-order review cases, not new-order candidates.

All 41 absent references have an integer-valued WooCommerce ID in payment notes, but their notes contain no product, variation or line-item IDs. The 6 without a reference have empty notes. The supplied orders export includes status, amount and customer/address columns but no order-item, discount, shipping-line or tax-line data. The 41 IDs fall within the numerical ID range of the supplied export; this does not prove they are absent from the live store. Search live WooCommerce through CRUD across all statuses and Trash before calling any one of them missing.

The additional `Payment report.csv` has 751 payment rows: 535 captured, 212 failed and 4 refunded. Its notes contain no product, variation, gift or line-item identifiers. The `transactions - 24 Jul 26 - 22 Oct 26.csv` file has 627 mixed ledger rows (553 payments, 67 settlements and 7 refunds); it is supporting financial evidence, not a checkout-item source. Its notes also contain no item identifiers. Neither file can establish an order composition. Import the payment report or reconciliation report for payment review; a mixed settlement ledger must not be treated as a list of captured orders.

A read-only scan of the `globalgoodhealth_wp76.sql` backup generated on 22 October 2026 found HPOS `wc_orders`, legacy `posts`, and WooCommerce order-item tables. It found zero `wc_orders`, zero `posts`, and zero order items for the 41 referenced IDs. The scan was validated against an existing order ID that has one HPOS row, one legacy post and two order items. The backup also contains 201 CartFlows abandonment rows; none links to any of the 41 IDs. This supports absence from that dated snapshot, but it does not reveal what was in the submitted carts or prove the current live database still has the same state. The backup was neither restored nor modified.

Parser smoke checks on the actual files passed: 535/535 reconciliation rows and 47/47 raw-payment rows parsed; all have amounts and dates; 372 and 41 respectively retained WooCommerce IDs. The date strings have no timezone offset, so their historical timezone still needs confirmation before using a paid timestamp.

Source SHA-256 fingerprints: reconciliation `68c7a538c578570ad60ab49e132f13c01c72acd93251b5e2b8ffdc65696a72a8`; orders export `a3749e606a171ad4e6aa62f69a5bbd9c1bc57548bbc6457d32f706f2a36292e0`; 47-row file `329361d368577a23ccc0a1b33a6b0f58e92cb4958d841d4541b290c250f5e450`.

Additional SHA-256 fingerprints: database backup `3de2cc9434e01a19062341f970625850c4e2c3293c7c0c2f2cf00dd7069b8919`; payment report `fc81e08c4dfee5b0f23ed1dcc99e7d60d58b8ecbae9b3af70c90b9727713d80e`; transaction ledger `fc6b36d35e86721b6c444e602ff18ce49a22aa9a4725e01b5850a2614d81c671`.

## Verification

- `php tests/order-recovery-import.test.php`: PASS, 35 assertions.
- `php tests/order-recovery-preview.test.php`: PASS, 24 checks.
- `php tests/order-recovery-payment-record.test.php`: PASS, 14 checks, including unauthorized access, repeat approval and an interrupted save.
- `php tests/free-gift-pricing.test.php`: PASS.
- `php tests/order-payment-isolation.test.php`: PASS, 88 assertions.
- PHP lint on the changed runtime files: PASS.
- `git diff --check`: PASS.

The changed plugin was loaded in an isolated diagnostic WordPress/WooCommerce site. The custom status registered, and a test `WC_Order` in legacy storage saved with the status, exact payment total and transaction ID, no items, then was deleted. `CRUD_SMOKE=PASS`; `HPOS=no`. The database server was stopped afterward. This validates the basic legacy CRUD path, not a real provider verification, admin browser flow, HPOS runtime, third-party side effects, or production deployment. A controlled nonproduction approval with the actual gateway/extensions remains required before production use.

Even a review-status WooCommerce save can run third-party new-order hooks. The local diagnostic site did not include the production extension stack, so email, analytics, fulfillment and webhook behavior cannot be certified from this test. The review status and missing-item flag reduce accidental fulfillment; they are not a substitute for a production-like staging check.

## Admin use after deployment

In **SuperWoo → Order Recovery**, import `Payment report.csv` or `razorpay_captured_payments_reconciliation.csv`. Open a referenced missing-order candidate, verify it with Razorpay, then review the payment-only warning and select the individual confirmation checkbox. The action verifies Razorpay again before creating a `Recovery review` WooCommerce order. It requires `SUPERWOO_RAZORPAY_KEY_ID` and `SUPERWOO_RAZORPAY_KEY_SECRET` in protected site configuration. Importing a CSV alone never creates an order. The mixed `transactions - 24 Jul 26 - 22 Oct 26.csv` ledger is rejected by the payment importer.

Only the referenced store-payment group can reach the action. Workshop wording goes to manual review, unreferenced payments are blocked, existing/cancelled orders are not duplicated, and refunds or provider mismatches stop creation. If the action reports an existing claim after an interruption, inspect WooCommerce by payment ID and the claim before any retry. Do not remove claim options automatically.

## Rollback

Revert the changed PHP files and tests from this patch. The code adds no recovery-table migration. If a payment-only review order is ever created, reconcile its payment and historical-ID claim options before any manual order deletion or retry; do not clear claims automatically. Existing WooCommerce orders were untouched by this local work.

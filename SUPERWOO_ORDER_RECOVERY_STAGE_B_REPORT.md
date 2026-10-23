# SuperWoo Order Recovery — Stage B Report

**Date:** 2026-10-23  
**Scope:** Read-only Razorpay CSV import, evidence normalization, local order matching and transaction classification. No provider API integration or WooCommerce order mutation was implemented.

## Delivered

- Registered **SuperWoo → Order Recovery** in the existing plugin composition root, gated by `manage_woocommerce`.
- Added a versioned `dbDelta` schema, prepared-query repository, and admin UI for CSV import, summary counts, filters, pagination, record detail, source-batch history, and exact Workshop exclusion rules.
- Added UTF-8/BOM validation, comma/semicolon/tab delimiter detection, header aliases, dynamic Notes/Metadata columns, row/size limits, missing-field tracking, redaction, and stable source fingerprints. The upload is parsed from PHP's temporary upload path and is not copied into uploads/media or retained.
- Normalized provider/payment IDs, WooCommerce IDs and order numbers, Razorpay order IDs, receipt, description, notes/metadata, reported status, currency, integer minor-unit amounts, fees, fee GST, refunds and refund references/status/timestamps, payment timestamp, email and phone. Original recognized non-contact source values are retained alongside normalized values. Missing timezones are interpreted as UTC with a warning; amounts without currency remain null with a warning.
- Matched local WooCommerce orders through `wc_get_orders()` and `wc_get_order()` only, checking exact payment transaction ID, explicit WooCommerce order ID, and exact order number. No legacy posts or HPOS tables are queried.
- Classified imported records as `existing_order`, `missing_order_candidate`, `duplicate_ambiguous`, `workshop_excluded`, or `manual_review`. CSV-reported captured-like states create *unverified candidates* only; reported failed/authorized/refunded states remain manual review. Generic Workshop wording also remains manual review. Amount/customer evidence alone never matches an order.
- Kept captured gross, WooCommerce total, fee, fee GST, refunds and gross/order difference as separate values. No fee is netted from customer payment.
- Added file-hash batch idempotency and unique stable transaction keys for provider payment IDs, plus row source links and unique database constraints. Repeated identical CSV uploads do not create a second batch; repeated IDs inside a file are flagged ambiguous.
- Fixed an existing security test's path separator assumption so its assertion works on Windows.

## Files changed

- `superwoo.php`
- `includes/class-plugin.php`
- `includes/order-recovery/class-order-recovery-schema.php`
- `includes/order-recovery/class-order-recovery-repository.php`
- `includes/order-recovery/class-order-recovery-csv.php`
- `includes/order-recovery/class-order-recovery-classifier.php`
- `includes/order-recovery/class-order-recovery-admin.php`
- `tests/order-recovery-import.test.php`
- `tests/security-hardening.test.php` (Windows portability fix)
- `SUPERWOO_ORDER_RECOVERY_STAGE_B_REPORT.md`

The Stage A report remains the source audit and architecture baseline.

## Database changes

The code defines three dedicated tables using the site prefix `{$wpdb->prefix}superwoo_order_recovery_`:

- `batches`: file SHA-256, sanitized filename, actor, timestamp and row outcome counts. Unique batch key prevents the same file being imported twice.
- `transactions`: normalized source evidence, reported/unverified state, classification/reason, original recognized source values, source fingerprint, amounts as integer minor units, contact fields required for review, and WooCommerce total/difference where locally matched. Unique idempotency key prevents duplicate recovery records.
- `batch_transactions`: per-import row number and fingerprint linking transactions to every source batch. Unique `(batch_id, source_row_number)` prevents duplicate row links.

Schema version is `1` under `superwoo_order_recovery_schema_version`. `dbDelta` runs only when an authorized user opens the Order Recovery admin page and the installed schema version differs. No migration was executed in this workspace or against production.

## Verification

Passed:

- `tests/order-recovery-import.test.php` — 26 assertions.
- `tests/order-payment-isolation.test.php` — 82 assertions.
- `tests/razorpay-abandoned-webhook.test.php` — 32 assertions.
- `tests/security-hardening.test.php` — 12 assertions.
- `tests/settings-persistence.test.php` — 30 assertions.
- `tests/free-gift-pricing.test.php` and `tests/review-media-limits.test.php`.
- `node tests/meta-tracking.test.js` — 28 tests.
- PHP syntax checks for all changed PHP files.
- `git diff --check`.
- Source search confirmed the new order-recovery module contains no order mutation/payment operations, payment lifecycle hooks, or HTTP/Razorpay API calls.

Environment-limited failures:

- `tests/updater-hardening.test.php` cannot run because this PHP runtime lacks Sodium constants/functions (`SODIUM_CRYPTO_SIGN_SEEDBYTES`).
- `tests/updater-release-integration.test.php` cannot run because `sodium_crypto_sign_seed_keypair()` is unavailable.

These failures are unrelated to Order Recovery. The previously failing Windows-only path assertion in `security-hardening.test.php` was corrected and now passes.

## Security and safety

- Every admin screen and mutation uses `manage_woocommerce`; upload/rule writes require action nonces.
- Uploads are bounded at 10 MB / 50,000 rows, UTF-8 checked, rejected on binary NUL, invalid headers or excess columns, and parsed without moving them to a public directory. Only safe source values, source hashes and batch metadata are retained; notes and free text redact email/phone-like values. Needed contact fields remain in the restricted recovery table and are masked in the UI.
- Logs contain batch IDs/counts or sanitized error codes, not payment/customer values or source rows.
- WooCommerce reads use CRUD APIs. There are no order writes, checkout changes, payment API calls, captures, refunds, emails, or recovery-driven side effects.
- CSV states remain explicitly unverified. “Missing order candidate” is not a confirmed missing order or proof of capture.

## Limitations and known risks

- The actual historical Razorpay export was not available during implementation. Header aliases and amount-unit interpretation need validation against the exact store export. Generic decimal Amount/Fee fields are treated as major currency units; explicitly named paise/minor-unit columns are treated as minor units. The original recognized values are retained for review.
- Timezone-less dates are assumed UTC and visibly warned; the correct export timezone must be confirmed before drawing date-range conclusions.
- WooCommerce order matching and total comparisons were tested with CRUD stubs, not a live/staging WooCommerce installation. HPOS and legacy modes remain unverified at runtime.
- Order-number lookup uses WooCommerce search and accepts only an exact returned order-number match. Custom order-number plugins may need an adapter.
- Workshop rules apply during import/re-import; changing rules does not retroactively reclassify old rows automatically.
- Rows without stable payment IDs fall back to a normalized row fingerprint. Such records remain review-only; materially changed exports may not deduplicate them reliably.
- The schema has not been exercised against the site's actual MySQL/MariaDB version, prefix, collation, or existing recovery data.

## Staging verification steps

1. Back up staging and verify WooCommerce HPOS setting, compatibility mode, plugin versions, currency precision and Razorpay export format.
2. Open **SuperWoo → Order Recovery** as a `manage_woocommerce` user; verify the three tables and schema version are created on staging only.
3. Import a sanitized copy of the historical export. Confirm batch hash, row counts, missing fields, source links, date warnings, and that repeating the exact file creates no second batch.
4. Verify local order matches against known transaction IDs, explicit Woo order IDs and order numbers in both HPOS and legacy modes. Check ambiguous duplicate references and currency mismatch behavior.
5. Configure only confirmed Workshop identifiers; check exact rules exclude and generic Workshop terms remain manual review.
6. Verify fee, fee GST, refund, order total and reconciliation difference are displayed independently; specifically confirm no fee is subtracted from captured gross.
7. Confirm status labels always say CSV-reported/unverified, no customer emails or external calls occur, and WooCommerce orders/statuses/stock/analytics/fulfillment remain unchanged.
8. Test unauthorized access, invalid nonce, malformed/oversized/invalid-encoding CSV, no currency, duplicate IDs and concurrent uploads.

## Rollback

Disable or remove the Order Recovery submenu/module code on staging. Its feature tables are isolated and can be backed up/dropped independently after review. No WooCommerce order rollback is applicable because this stage performs no order writes. Do not run schema cleanup or migrations against production as part of this delivery.

## Stage B status

```text
CSV_IMPORT_AND_CLASSIFICATION=IMPLEMENTED
ORDER_WRITES=NONE
RAZORPAY_API_CALLS=NONE
PRODUCTION_DATABASE_ACCESSED=NO
STAGING_RUNTIME_VERIFIED=NO
PROVIDER_STATUS_VERIFIED=NO
ORDER_RECONSTRUCTION=NOT_IMPLEMENTED
```

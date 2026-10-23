# SuperWoo Order Recovery — Stage A Audit and Implementation Plan

**Date:** 2026-10-23  
**Scope:** Read-only source review of the SuperWoo repository for order creation, checkout, Razorpay touchpoints, recovery evidence, storage, security conventions and verification infrastructure. No recovery code was added, no database was accessed, and no live store, order, payment or credential was inspected.

## Executive summary

SuperWoo currently does not implement a WooCommerce payment gateway, create or update orders, or subscribe to order-status/payment-completion hooks. The Razorpay integration present in this repository is a receive-only abandoned-cart notification endpoint. It writes a small redacted receipt summary to an uploads log; it does not receive or retain transaction identifiers, payment status, amounts, line-item details, or customer contact values.

The actual Razorpay checkout/order behavior belongs to the active Razorpay WooCommerce gateway plugin and/or Razorpay Magic Checkout, neither of which is included in this source tree. Therefore, this repository alone cannot establish why historical orders went missing, determine the meaning of the ₹55.24 example, identify Workshop transactions, or verify payment records.

The existing cart recovery code is not wired into the current bootstrap: `superwoo.php` does not require the coordinator and `SuperWoo_Plugin` does not instantiate it. If enabled and wired in a future implementation, its snapshots could provide corroborating checkout evidence, but the snapshot does not preserve billing/shipping addresses, shipping methods, tax lines or a complete order reconstruction record. It must not be treated as sufficient proof for automatic reconstruction.

The WooCommerce storage mode (HPOS, legacy posts, or compatibility synchronization) cannot be determined from source code. It is a runtime setting on the store and must be checked in an authorized staging/store environment before implementation verification.

## Audit findings

### 1. Bootstrap and module architecture

- `superwoo.php` loads module files and starts `SuperWoo_Plugin` on `plugins_loaded`.
- `includes/class-plugin.php` is the composition root. Admin screens are registered with `admin_menu`; most admin capabilities use `manage_woocommerce`; mutating admin actions use `admin-post.php` and nonces.
- WooCommerce availability is checked through `SuperWoo_WooCommerce_Guard` before storefront WooCommerce features are started.
- No Composer/PHPUnit configuration was found at the repository root. Tests are standalone PHP and JavaScript scripts under `tests/`.
- Existing custom-table code uses `$wpdb`, prepared queries and `dbDelta` in feature-specific schema/repository classes. The settings option is used for configuration, not high-volume records.

### 2. Checkout and order lifecycle

- SuperWoo routes cart drawer checkout through the WooCommerce checkout URL or Razorpay Magic Checkout button when available. Source comments/history document that the Razorpay gateway owns its native Buy Now behavior.
- Existing hooks on order creation add selected-currency metadata, WhatsApp order-update consent, and (in Cart Recovery source) internal cart-recovery attribution. Shoppable Videos may attach line-item attribution.
- Static isolation guard `tests/order-payment-isolation.test.php` prevents SuperWoo code from registering WooCommerce order-status/payment lifecycle hooks or calling `wc_create_order()`, `payment_complete()`, `set_paid()`, `process_payment()` or `process_refund()`.
- No direct order insertion is present in the audited SuperWoo runtime. The plugin does not decide whether an order is paid, failed, processing, completed or refunded.

### 3. Razorpay integration and evidence

- `includes/class-razorpay-abandoned-webhook.php` registers two token-protected REST routes for abandoned-cart notices. It rate-limits requests, rejects malformed/oversized JSON, and records only a receipt ID, timestamp, payload hash and safe aggregate/presence fields.
- Raw payload fields, including customer/contact/address/cart values, are not persisted. No payment ID, Razorpay order ID, receipt, payment status, gross amount, fee, GST, refund or payment timestamp is extracted.
- The token is a bearer credential in the callback URL. Rotation requires `manage_woocommerce` and a nonce. Logs are written beneath uploads with directory protection.
- No authenticated Razorpay API client, API credential settings, transaction importer, CSV parser, payment reconciliation table, or order recovery workflow was found.
- The Razorpay gateway plugin's own configuration, metadata keys, webhook signatures, order notes and checkout session correlation remain unknown until inspected in the actual site or its documentation/configuration.

### 4. Existing cart recovery records

- Files under `includes/cart-recovery/` define cart/session snapshots, a custom schema, event history, workflow tables, and an admin UI. However, the current bootstrap neither requires `includes/class-cart-recovery.php` nor creates `SuperWoo_Cart_Recovery`; these files are presently orphaned from runtime execution.
- The snapshot design includes product and variation IDs, quantities, product names, variation attributes, observed prices/totals, coupons, currency, cart total/subtotal and limited customer identifiers. Session/email/phone lookup material is HMAC-hashed in key places; captured contact values are also present in the cart table by design.
- It does not preserve shipping address, billing address as an immutable checkout snapshot, shipping method/rate, tax breakdown, fees, full coupon discount allocation, or a durable WooCommerce cart/order object snapshot. Cart state is mutable and keyed primarily by a session hash. It can be stale, overwritten or absent.
- These tables therefore offer supporting evidence only. No existing recovery mechanism links Razorpay payments to reconstructed orders.

### 5. Totals, shipping, taxes, discounts and gateway fees

- WooCommerce remains the source of truth for checkout totals. SuperWoo's currency feature can convert product prices and cart fees and records selected/base currency on normal checkout orders.
- Bundle/offer features can affect cart pricing; WooCommerce calculates shipping and taxes in the wider store/gateway configuration. SuperWoo does not have a separate Razorpay fee calculation or reconciliation formula.
- Nothing in the repository proves that the example difference (`₹2,341.00 - ₹2,285.76 = ₹55.24`) is a gateway fee. Equality with a fee shown elsewhere is a clue, not a verified accounting relationship. Customer payment, WooCommerce total, fees, fee GST, refunds and reconciliation delta must be stored independently and compared against verified records.
- The historical Razorpay export referenced in the request is not present in the repository or attachment available to this audit. Column names, date formats, amount units, refund representation and Workshop identifiers cannot yet be inspected.

### 6. Storage compatibility, database and concurrency

- All order access for a future implementation should use WooCommerce CRUD (`wc_get_order`, `wc_get_orders`, order CRUD objects) and must not query legacy order posts or HPOS tables directly.
- HPOS status and compatibility synchronization are runtime facts and remain unverified. Both modes need staging verification through WooCommerce APIs.
- Existing repository conventions support a dedicated custom table with `dbDelta`, schema versioning, unique keys and a repository class. For recovery records, unique constraints should enforce idempotency for provider payment IDs and recovery attempts; claims/locks must be atomic to prevent two admins/jobs creating the same order.
- No recovery-specific cron job currently exists. The orphaned cart-recovery implementation has Action Scheduler-based automation, but it is not connected to this feature and should not be reused for payment recovery without review.

### 7. Security, notifications and side effects

- Existing admin screens generally check `manage_woocommerce`, escape output and nonce-protect mutations. Recovery must apply capability and nonce checks on every state-changing endpoint.
- CSV import must validate upload errors, size, extension/content and encoding; parse without trusting headers; avoid public temporary storage; and remove temporary files. Imported records should minimize PII and never contain credentials or full payment credentials.
- A future recovery flow must use WooCommerce CRUD with notifications suppressed and explicitly control status/payment metadata, stock reduction, and hooks that can dispatch shipment/fulfillment/analytics integrations. A normal WooCommerce order save may trigger extensions, so “no email/no stock/no conversion/no shipment” requires controlled hooks/options and verification against the active site's extension set, not just SuperWoo source review.
- Do not call capture/refund APIs. Authorized-but-uncaptured records remain review-only. Refunded payment recovery must never initiate another refund.
- Fail closed when product, customer, address, tax or original financial details are unverified. Amount-only matching cannot authorize order creation.

### 8. Tests and existing verification

- Existing focused tests cover Razorpay abandoned-webhook redaction/authentication and order/payment isolation, plus settings/security and other plugin behavior.
- There is no WooCommerce integration fixture or automated HPOS/legacy matrix in the repository. The current source tests cannot prove runtime behavior with the site's Razorpay plugin, shipping/tax setup, email extensions, inventory, analytics, or fulfillment integrations.
- Stage B onward will need new parser/classification/unit tests and a staging WooCommerce integration matrix. No tests were run for this read-only audit; no code changed.

## Recovery design recommendation

1. **Evidence-first records:** Import API/CSV observations into a dedicated recovery transaction table. Preserve a redacted raw-field map or normalized evidence snapshot, import source, provider IDs, amounts in integer minor units, currency, dates, verification state and classification. Avoid putting raw CSV files, API secrets or unnecessary PII into long-lived logs.
2. **Deterministic classification:** Resolve in the required order: verified payment ID already on an order; explicit order ID in verified notes; verified checkout/session correlation; Razorpay order ID and metadata; receipt/order-number evidence; customer/amount/time only as secondary hints. Never create from amount-only evidence. Store a reason code and evidence references for every result.
3. **Workshop exclusions:** Add explicit configurable identifiers and source metadata rules. Exclusion should require a verified identifier or multiple corroborating attributes; generic text/keyword matching may flag review but must not silently exclude or recover. Keep excluded records visible with reasons.
4. **Financial comparison:** Keep WooCommerce original total, captured amount, Razorpay fee, fee GST, refunds, currency and signed difference in separate fields. Show the arithmetic and evidence used. Do not net fees against customer payment or rewrite historical order totals automatically.
5. **Preview then approval:** Default to dry run. Render a per-record preview describing exact proposed customer, product, quantity, addresses, totals, status and side effects. Require manual approval; permit batch approval only for records meeting explicitly verified criteria. Use WooCommerce CRUD and a unique database idempotency key plus atomic claim before any order save.
6. **Controlled order save:** Reconstruct only from durable, authoritative checkout snapshots or independently verified records. Preserve old references as protected metadata and let WooCommerce allocate a new internal ID where necessary. Set historical paid date only when verified. Suppress emails and duplicate stock/analytics/fulfillment effects, then verify those controls in the active extension stack.
7. **API verification:** When credentials are configured, call authenticated Razorpay read endpoints with bounded timeouts, minimal scopes, secret storage via existing secure site config where possible, redacted errors, and no capture/refund operations. Timeouts must leave records unverified/manual review.
8. **Audit trail:** Record actor, timestamps, source/import batch, old/new order reference, decision, verification result and action outcome. Do not log secret values or full customer details.

## Staged implementation plan

### Stage A — complete

Read-only repository audit and design. This report is the deliverable. No recovery functionality or schema change has been made.

### Stage B — import and classification

Add the admin navigation/shell, dedicated recovery schema/repository, secure CSV upload and flexible header normalization, import idempotency, deterministic classification, exclusion settings, filters and a read-only record detail view. No WooCommerce order mutations and no Razorpay API calls in this stage.

### Stage C — provider verification and evidence correlation

Add optional read-only Razorpay API verification only after the active gateway/API credentials, account permissions, version and available endpoint contract are confirmed. Inspect the real checkout/gateway metadata and cart recovery availability. Map data provenance and identify Workshop rules from verified references. Keep failures/timeouts in manual review.

### Stage D — dry-run previews

Produce proposed order reconstructions and discrepancies without writes. Require enough evidence for every line, address and financial component; otherwise classify as manual review. Verify missing/existing, duplicate, failed, authorized, refunded and fee difference cases.

### Stage E — controlled approval and reconstruction

Only after staging validation, implement per-order approval and carefully bounded batch approval for verified unambiguous records. Use WooCommerce CRUD only, database-backed idempotency/locking, audit history, notification/stock/analytics/fulfillment controls, and retry-safe partial failure handling. Keep capture/refund impossible by design.

### Stage F — future discrepancy detection

Separate follow-on proposal for ongoing discrepancy detection. Do not enable automatic recovery. Any automatic action requires explicit owner approval after measured false-positive rates and side-effect verification.

## Required runtime evidence before Stage C/D

- The supplied Razorpay export, with secrets and customer data redacted where practical, plus its official export schema/sample.
- Staging access or exported sanitized WooCommerce order metadata for representative matched, missing, failed, authorized, refunded and Workshop payments.
- Active Razorpay gateway and Magic Checkout plugin names/versions and the site-specific payment/order metadata contract.
- HPOS status and compatibility mode, WooCommerce version, currency/precision, tax/shipping/coupon configuration, and any fee plugin/settings relevant to the historical date.
- Available checkout/session/cart snapshots, their retention period, and whether a trustworthy backup exists for historical product/address/tax reconstruction.
- Verified Workshop identifiers and examples, from Razorpay metadata or the separate Workshop system.
- Staging extension inventory for email, stock, analytics/conversion, shipment and fulfillment hooks that can run on order creation/status changes.

## Manual verification and rollback

- **For this stage:** Review the source paths cited above and compare this report against the staging site's active plugins/settings. No rollback is needed because no runtime code, settings, tables or orders were changed.
- **For later stages:** Deploy only to staging first. Export/backup the recovery tables and affected order IDs, run import/classification without order writes, compare every proposed order to source evidence, and verify HPOS and legacy mode through WooCommerce screens/APIs. Test retries, duplicate approvals, concurrent approvals, email suppression, stock quantities and downstream integrations. Roll back by disabling the feature and restoring only the dedicated recovery tables; do not delete or rewrite WooCommerce orders as a generic rollback.

## Stage A status

```text
SOURCE_AUDIT=COMPLETE
RECOVERY_CODE_ADDED=NO
DATABASE_SCHEMA_CHANGED=NO
LIVE_STORE_ACCESSED=NO
HPOS_MODE=UNKNOWN (requires runtime inspection)
RAZORPAY_API_VERIFIED=NO (no client/credentials in source)
HISTORICAL_CSV_INSPECTED=NO (not available in repository/attachments)
ORDER_OR_PAYMENT_MUTATED=NO
TESTS_RUN=NO (read-only audit)
```

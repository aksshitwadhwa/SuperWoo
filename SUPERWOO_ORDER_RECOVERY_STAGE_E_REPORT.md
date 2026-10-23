# SuperWoo Order Recovery — Stage E Report

**Status: controlled order creation remains blocked.** Safe supporting changes were implemented locally, but Stage E does not meet its final acceptance criteria because this repository has no validated authoritative historical checkout evidence source or adapter. No order-creation path was added. The admin now states that order creation is unavailable, and all current candidates remain review-only.

No live store connection, deployment, database migration, Razorpay API request, order mutation, payment mutation, notification, stock change, or fulfillment action was performed.

## Inspection and verified project conventions

Read the Stage A, B, C, D and D.1 reports, the existing recovery implementation/tests, and the plugin bootstrap/composition root. `SUPERWOO_ORDER_RECOVERY_SOURCE_REVIEW.md` is not present. Source headers declare WordPress 6.2+, PHP 7.4+, and WooCommerce 8.0+. Recovery UI is registered through `SuperWoo_Plugin` and currently uses `manage_woocommerce`, `admin-post.php`, WordPress nonces, `$wpdb`/`dbDelta` custom tables, standalone PHP tests, and WooCommerce CRUD for order reads.

The D.1 report establishes there is no staging runtime, sanitized store data, backup, historical checkout snapshot, or verified source adapter in this workspace. The orphaned cart-recovery code is not wired into the composition root and lacks complete immutable address/shipping/tax/fee evidence. Payment CSV/provider data cannot supply those missing fields. Consequently, no candidate can meet the required evidence gate.

## Changes made

- `includes/order-recovery/class-order-recovery-classifier.php`: replaced bounded exact payment-ID and order-number searches with complete paginated WooCommerce CRUD reads. Every candidate page is inspected and exact values are checked in memory. An invalid/incomplete page result returns a conflict so a lookup failure cannot be mistaken for order absence.
- `includes/order-recovery/class-order-recovery-provider.php`: refund evidence is complete only when pagination reaches a short terminal page within the supported bound, payment refund aggregate is present, refund records are structurally valid and tied to the requested payment, currencies agree, refund statuses are terminal (`processed` or `failed`), and processed refund sum agrees with the payment aggregate. A full page at the pagination ceiling or any unresolved/inconsistent refund evidence remains incomplete.
- `includes/order-recovery/class-order-recovery-schema.php`: schema version advanced from 2 to 3 with `provider_refunds_complete`, defaulting to incomplete. The existing `maybe_install()`/`dbDelta` convention applies the versioned change when the recovery admin page is visited by an authorized user; no migration was run here.
- `includes/order-recovery/class-order-recovery-repository.php`: changed imported source-row fingerprints clear all provider-derived fields, refund completeness, and verification timestamp/state. Re-imported evidence therefore cannot inherit a stale successful verification.
- `includes/order-recovery/class-order-recovery-preview.php`: a current captured preview requires complete refund evidence; incomplete refunds produce `incomplete_evidence` with `refund_evidence_incomplete`.
- `includes/order-recovery/class-order-recovery-admin.php`: preview rendering rechecks exact current WooCommerce matches and current Workshop rules; visible classification now reflects that fresh result. Added an admin notice stating order creation is unavailable without a validated authoritative evidence adapter.
- `tests/order-recovery-import.test.php`: pagination coverage for exact order-number search on a later page, duplicate exact transaction references across pages, and fail-closed behavior on incomplete query results.
- `tests/order-recovery-provider.test.php`: coverage for refund-list pagination ceiling, unresolved refund states, refund aggregate mismatch, and terminal-list completeness.
- `tests/order-recovery-preview.test.php`: coverage that incomplete refund evidence blocks a ready preview.
- `tests/order-recovery-repository.test.php`: new repository test confirms a changed source fingerprint invalidates previously saved provider and refund evidence.

The schema change is additive. No order/payment records or existing recovery rows were altered in this local environment.

## Approval and creation workflow

The existing admin workflow still imports CSV evidence, displays classifications/previews, and permits an administrator with `manage_woocommerce` to explicitly request the existing read-only provider verification. Current previews recheck order matches and Workshop rules. The admin explicitly reports that order creation is unavailable.

There is no approve or create action in this build. The required checkout evidence source, stable source record IDs, field-level provenance, exact payment linkage, and completeness/integrity guarantees have not been established. Implementing a callable order writer without those verified preconditions would violate the prompt’s “do not fake” requirement. No administrator can create an order through this Stage E code.

To unblock controlled approval, provide:

1. A sanitized immutable checkout/order snapshot or verified backup restored into an isolated local test fixture, with source identity, schema, capture coverage, retention and integrity documentation.
2. Evidence linking each record to the exact Razorpay payment ID, plus all required customer, catalog/product/variation, quantity, price, discount/coupon, fee, address, shipping, tax, currency and order-total fields with per-field provenance.
3. A WooCommerce integration test environment supporting the declared WooCommerce 8.0+ baseline and HPOS/legacy configurations, including the installed extensions whose order-save hooks may send mail, reduce stock, or trigger fulfillment.
4. A reviewed design for safe order status, notification/stock/fulfillment suppression and post-save verification against that installed extension stack.

Until those are supplied and verified, Stage E must remain incomplete and recovery candidates must not be reconstructed.

## Idempotency, stale evidence and duplicate detection

Existing CSV idempotency remains based on the unique provider payment identity (or source-row fingerprint when no payment ID exists). Updated source evidence now invalidates Stage C verification instead of retaining stale facts. Refund completeness is persisted and defaults to false, which keeps old or partially migrated records fail-closed. Exact order matching now paginates WooCommerce CRUD results to completion; lookup errors become conflicts. The admin evaluates current Workshop rules when rendering each preview.

No approval lock, recovery claim, created-order ID, append-only recovery-action audit table, retry/reconciliation record, or order-creation idempotency constraint was introduced because there is no safe order-writing path to attach them to. These are mandatory before a future implementation can create orders under retries/concurrency. A PHP request timeout must never be treated as permission to retry.

## Payment status and side effects

This stage calls no new provider operations; only the existing explicit Stage C read-only verification path remains. It does not call `payment_complete()`, `set_paid()`, `wc_create_order()`, gateway capture/refund methods, or order status methods. It causes no order save, stock reduction, email, webhook, fulfillment, or customer action. Therefore there is no selected WooCommerce order status behavior to claim. A future writer must prove side-effect suppression in the active extension stack before creating even one order.

## Test commands and results

All commands ran locally from `D:\SuperWoo\superwoo`; tests use repository mocks/stubs and do not validate live behavior.

| Command | Result |
|---|---|
| `php tests/order-recovery-import.test.php` | PASS — 29 assertions |
| `php tests/order-recovery-repository.test.php` | PASS — 3 assertions |
| `php tests/order-recovery-provider.test.php` | PASS — 19 checks |
| `php tests/order-recovery-preview.test.php` | PASS — 21 checks |
| `php tests/security-hardening.test.php` | PASS — 12 assertions |
| `php tests/razorpay-abandoned-webhook.test.php` | PASS — 32 assertions |
| `php tests/order-payment-isolation.test.php` | PASS — 86 assertions |
| `php -l` over PHP files under `includes`, `admin`, and `tests` | PASS — 69 files |
| `git diff --check` | PASS |

No WooCommerce integration, HPOS runtime, legacy runtime, database migration, provider endpoint, admin browser, or order-persistence test could run in this workspace. The order/payment isolation test passed and confirms there are no order/payment mutation methods introduced by the local changes.

## Known limitations and assumptions

- No authoritative checkout evidence source is currently connected or validated. Stage E acceptance is not met.
- The complete paginated lookup depends on WooCommerce’s `wc_get_orders()` paginated return contract in the supported version range; it was unit-tested with a local compatible stub, not an installed WooCommerce runtime.
- Refund completeness is deliberately conservative. Any pagination ceiling, unresolved refund status, absent aggregate, or mismatch blocks recovery; these conditions require operator review.
- This repository does not contain active site gateway configuration or extension inventory. It cannot establish mail, stock, analytics, webhooks, shipment or fulfillment effects of a future order save.
- The schema migration has not been executed. Verify the version-3 field on a restorable nonproduction copy before any release; do not run it against production as part of this task.
- Tests do not prove credentials are absent from an external live installation, and no live system was accessed.

## Local use

An administrator with `manage_woocommerce` can continue to use **SuperWoo → Order Recovery** to import a Razorpay CSV, inspect candidates and previews, update exact Workshop rules, export review results, and explicitly run read-only provider verification when configured. The interface warns that order creation is unavailable. No order can be approved or created in this build.

## Live deployment and recovery checklist

This is a checklist for a separately authorized future deployment, not deployment approval. Working directly on a live store carries operational risk that automated tests cannot remove.

1. Obtain explicit deployment approval and schedule a maintenance/change window.
2. Create and verify a restorable backup of files and database; confirm restore procedure before changing code/schema.
3. Validate the exact evidence adapter and all required field provenance in an isolated test environment first; this build has no such adapter and must not be used to reconstruct orders.
4. Confirm WooCommerce, gateway and extension versions, HPOS mode, order-save side effects, recovery permissions and the version-3 schema plan.
5. Deploy only the reviewed build. Start with the minimum administrator permissions and keep recovery creation disabled until its evidence and side-effect gates are independently reviewed.
6. If a future approved writer is introduced, recover one individually approved, low-risk record only; verify CRUD persistence, transaction ID, totals/taxes, status, audit trail, no duplicate, and no unintended mail/stock/fulfillment before considering another.
7. If anything is ambiguous, stop and reconcile from the backup and evidence. Do not retry a timed-out creation blindly.
8. Roll back code/configuration using the verified backup and reviewed procedure. Do not delete a potentially created order or recovery evidence automatically; reconcile it manually first.

## Stage E status

```text
SAFE SUPPORTING FIXES=IMPLEMENTED
AUTHORITATIVE CHECKOUT EVIDENCE=UNAVAILABLE
PER-ORDER APPROVAL=NOT IMPLEMENTED
ORDER CREATION=BLOCKED / NOT IMPLEMENTED
HPOS OR LEGACY RUNTIME VALIDATION=NOT RUN
LIVE STORE OR PROVIDER MUTATIONS=NONE
STAGE E ACCEPTANCE=NOT MET
```

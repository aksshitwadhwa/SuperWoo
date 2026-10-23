# SuperWoo Order Recovery - Stage D Report

**Date:** 2026-10-23  
**Scope:** Read-only reconstruction previews. No WooCommerce orders, payment data, or production database were changed or accessed.

## Delivered

- Added a deterministic dry-run evaluator with outcomes `ready_for_review`, `incomplete_evidence`, and `conflict`, plus reason codes and explicit missing fields.
- Added a preview outcome column for each imported record and a detailed preview in record details. It shows original references, Stage C verification/capture/refund evidence, imported WooCommerce totals/difference, provider gross, fees and fee GST independently, missing evidence, provenance, and side effects. It shows no proposed order when evidence is incomplete.
- Added an export for the current filtered page. It omits customer contact data and protects text cells against spreadsheet formula execution.
- Preview checks the local WooCommerce order through the existing CRUD classifier by exact payment ID, explicit Woo order ID and exact order number. An existing order, ambiguous match, conflicting reference, duplicate, Workshop exclusion, provider conflict, failed payment or refund becomes `conflict`. Missing Woo CRUD support fails closed. Unverified and authorized/uncaptured transactions remain incomplete; no paid-order proposal is produced.
- A `ready_for_review` representation requires a source-marked authoritative checkout snapshot or verified backup packet containing customer billing/shipping, products and variations, quantities, discounts, shipping methods, tax breakdowns, currency, fees and order total. Every proposed leaf field receives provenance. The evaluator checks discount, tax and order-total arithmetic without forcing amounts to reconcile. A difference between Razorpay gross and proposed WooCommerce total is displayed separately.
- No authoritative checkout/session/backup source is available in this repository or supplied runtime evidence. The admin path therefore passes no packet and currently leaves otherwise eligible records `incomplete_evidence` with `authoritative_checkout_evidence_unavailable`. CSV notes and Razorpay payment fields are not promoted to checkout authority. The orphaned cart-recovery module remains untouched and unwired.

## Changed files

- `superwoo.php` - loads the preview evaluator.
- `includes/order-recovery/class-order-recovery-preview.php` - dry-run validation, matching gates, reason codes, arithmetic, proposed representation and provenance.
- `includes/order-recovery/class-order-recovery-admin.php` - preview display, verification-state filter and current-page CSV export.
- `includes/order-recovery/class-order-recovery-repository.php` - prepared filter for verification state.
- `tests/order-recovery-preview.test.php` - reconstruction preview test cases.
- `SUPERWOO_ORDER_RECOVERY_STAGE_D_REPORT.md` - this report.

No schema change was needed; previews are computed on demand and are not persisted.

## Tests and checks

Passed:

- `php tests/order-recovery-preview.test.php` - 20 assertions, including missing lines/addresses, mismatched discount/order totals, duplicate references, existing and ambiguous orders, failed/authorized/refunded/partial-refund/unverified payments, Workshop exclusion, currency mismatch, nonauthoritative CSV data, and complete authoritative evidence.
- `php tests/order-recovery-import.test.php` - 26 assertions.
- `php tests/order-recovery-provider.test.php` - 15 mocked checks.
- `php tests/order-payment-isolation.test.php` - 86 assertions.
- `php tests/security-hardening.test.php` - 12 assertions.
- `php tests/razorpay-abandoned-webhook.test.php` - 32 assertions.
- PHP syntax checks on changed PHP files and `git diff --check`.

## Evidence requirements and limitations

A real staging source adapter is still needed to supply a durable, authorized checkout snapshot or verified backup with a stable evidence reference. That source must establish customer billing and any required shipping address, exact product/variation IDs and quantities, item prices, discount allocation, shipping methods/rates, tax rate breakdowns, currency, fees and the original WooCommerce total. The source must be validated against active gateway and WooCommerce behavior. No such adapter or packet exists here, so no current record can reach `ready_for_review` in the admin UI.

The evaluator checks current product/variation existence and exact parent relationship through `wc_get_product()` when evaluating a supplied authoritative packet. Historical deleted products or changed tax configuration need separate review. Provider gross can differ from WooCommerce total; the difference is never netted against fees or otherwise adjusted. Partial and full refunds are conflicts requiring manual review, not paid-order reconstruction proposals.

## Staging verification and rollback

1. Back up staging and deploy only to staging. Review the active gateway/plugin versions, HPOS setting and authorized checkout/backup evidence source.
2. Keep API verification manual as in Stage C. Import sanitized representative records and verify candidates using read-only provider verification only when explicitly invoked.
3. Confirm WooCommerce CRUD matches and no-match cases in both HPOS and legacy-compatible staging configurations; test existing, duplicate, ambiguous, failed, authorized, fully/partially refunded and Workshop records.
4. Connect no evidence adapter until its source and authority are documented. Validate every proposed field's provenance, product/variation link, addresses, discounts, shipping, tax, currency and arithmetic. Confirm gross, fee, fee GST, refund and Woo total remain separate.
5. Review filtered-page CSV output, escaping, nonce/capability handling and sensitive-data minimization. Confirm no order, stock, email, fulfillment, payment, checkout, webhook or customer state changes occur.
6. Roll back by disabling/removing the Stage D preview module and menu UI on staging. No preview data or WooCommerce rows need restoration because this stage is read-only and persists no previews.

## Stage D status

```text
DRY_RUN_PREVIEWS=IMPLEMENTED
AUTHORITATIVE_CHECKOUT_SOURCE=UNAVAILABLE
CURRENT_ADMIN_READY_FOR_REVIEW_RECORDS=NONE (evidence source unavailable)
ORDER_OR_PAYMENT_MUTATION=NO
PROVIDER_API_CALLS_FROM_PREVIEW=NO
DATABASE_SCHEMA_CHANGE=NO
STAGING_VERIFIED=NO
STAGE_E=NOT_STARTED
```

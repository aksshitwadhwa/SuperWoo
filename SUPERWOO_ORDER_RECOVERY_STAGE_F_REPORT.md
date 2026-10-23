# SuperWoo Order Recovery — Stage F Report

**Date:** 2026-10-23
**Outcome: historical checkout evidence unavailable in this local project.** No actual historical transaction, order, backup, checkout/session record, or runtime log was accessible to inspect. Therefore no record is eligible to proceed to an approval phase, no evidence adapter was implemented, and no code/schema change was warranted.

This was a local repository inventory only. No external account or system was searched. The live store was not accessed, and no migration, provider request, order/payment change, notification, inventory change, webhook action, or fulfillment action occurred.

## Sources inspected

The Stage A, B, C, D, D.1 and E reports were read, along with the Order Recovery importer, repository, schema, classifier, provider, preview and admin code; cart recovery tracker/schema/coordinator code; Razorpay abandoned-cart webhook; relevant settings/integration references; and existing Order Recovery tests.

Workspace inventory used `Get-ChildItem -Force`, a recursive filename inventory excluding `.git`, and:

```powershell
rg --files -g '*.sql' -g '*.sql.gz' -g '*.wpress' -g '*.bak' -g '*.backup' -g '*.csv' -g '*.tsv' -g '*.jsonl' -g '*.log' -g '*.zip' -g '*snapshot*' -g '*checkout*' -g '*session*' -g '*reconcil*' -g '*staging*' -g '*backup*'
```

The evidence-like files found were versioned SuperWoo plugin ZIP packages, the source file `public/js/meta-checkout-observer.js`, and source code matching recovery/checkout terms. The ZIP contents inspected for `SuperWoo-1.5.5.zip` are plugin source/distribution files, not a store backup. No WordPress runtime configuration, database dump, CSV/TSV payment export, order export, reconciliation artifact, runtime log, or staging data was found. No customer records or secrets were inspected.

### Inventory and classification

| Source / location | Actually available and coverage | Stable ID / exact payment linkage | Field coverage and trust | Assessment |
|---|---|---|---|---|
| SuperWoo release archives at repository root (for example `SuperWoo-1.5.5.zip`) | Available as software packages. They contain no store data; no incident-period coverage. | No historical transaction or order IDs. | Plugin source only. No customer, catalog snapshot, order lines, discounts, shipping, tax, address or totals from store checkouts. | Unsuitable for reconstruction. |
| Stage B recovery tables (`superwoo_order_recovery_*`) | Schema and repository source exist; no connected WordPress DB or rows are available. | Code defines imported payment IDs and batch/source-row fingerprints, but no actual stable row is present to inspect. | If populated, imported CSV rows hold gateway/export facts and limited reported references; they are not checkout snapshots. Actual values, export date range, retention and integrity cannot be verified here. | Unavailable data; provider corroboration only if an authorized, documented export is supplied. |
| Stage C verification fields | Schema and explicit read-only verifier exist; no actual verification record exists in this workspace. No provider call was made. | Code uses a payment ID, but there is no verified historical identifier/status record to examine. | Provider API result fields are intended to represent payment/refund facts, not products or checkout selections. | Unavailable; provider facts would not establish original WooCommerce order contents. |
| Cart Recovery tables/tracker (`includes/cart-recovery/`) | Schema/tracker code exists; no database/table/data/retention period is available. Stage A reports the coordinator is unwired in the current composition root. | Intended `public_id`, HMAC `session_hash`, optional `order_id`; no demonstrated payment-ID field/link. | Code would snapshot current product/variation IDs, names, variation attributes, quantity, unit/line prices, coupons, currency, cart subtotal/total, limited customer identity, and context. It does not preserve a complete immutable billing/shipping address, shipping method/rate, tax breakdown, full fee lines, historical product/catalog definition, or complete discount allocation. Cart data is mutable and may be stale. | Code-only, incomplete and unlinked. Do not enable/query it as historical checkout authority. At most corroboration if actual rows and exact linkage are later verified. |
| Razorpay abandoned-cart webhook (`includes/class-razorpay-abandoned-webhook.php`; intended uploads log `superwoo-logs/razorpay-abandoned-cart.log`) | Receive-only implementation exists; the runtime uploads directory/log is not present. Date coverage and retention unknown. | Generates its own receipt ID and payload hash; does not preserve a Razorpay payment ID or Woo order reference. | Redacted receipt summary and presence/aggregate indicators only; raw payload is not retained. No products, quantities, addresses, shipping, tax, discounts, payment status, refund or order total. | Unsuitable for reconstruction; cannot link a transaction. |
| WooCommerce database/order records and backups | None mounted or supplied. | No actual order IDs, transaction IDs or backup references inspectable. | No current or historical order fields can be compared. HPOS/legacy mode unknown. | Unavailable. A sanitized, consistent backup restored in an isolated WooCommerce environment could establish existing orders in that snapshot, but cannot be presumed to contain missing checkout submissions. |
| WordPress/SuperWoo/gateway logs | No runtime logs were found. The abandoned-cart log described above is only a code-defined location. | No actual event IDs or payment references available. | No historical checkout/order payloads available for field inspection. | Unavailable. |
| Razorpay CSV/export and reconciliation artifacts | No matching CSV, TSV, payment export, statement, or reconciliation file was found. | No actual provider payment IDs or row fingerprints. | No amounts/status/refunds/currency values available to compare. | Unavailable. |
| External integrations in project source | Source references exist for Razorpay abandoned-cart receipt intake, Meta Pixel checkout tracking, configurable exchange-rate retrieval, and the SuperWoo updater. Runtime credentials/options/configuration were not read. | No demonstrated link from these integrations to an immutable checkout source and exact payment ID. | Meta Pixel is an outbound analytics event, not a retained complete order record. Exchange-rate retrieval is not a historical checkout snapshot. Updater data describes software releases. The Razorpay receipt is incomplete as described above. | No suitable historical evidence source. |

## Field-level evidence matrix

This matrix distinguishes source code describing a possible field from an actual historical value. Since no historical record was accessible, there are no fields that can honestly be marked `verified` for a candidate. `Not available` means no record was inspected; `code-only` is not historical evidence.

| Required field | Actual historical evidence found | Cart Recovery code-only schema/tracker | Razorpay abandoned-cart log implementation | Current assessment |
|---|---|---|---|---|
| Stable source record ID | Not available | Defines public UUID and HMAC session hash; no actual record | Generates receipt UUID and body hash; no actual log | Missing from actual evidence |
| Exact Razorpay payment ID and verified status | Not available | No payment field/link shown | Not collected | Missing; payment cannot be linked |
| Product and variation IDs | Not available | Captured at cart observation time, not proven historical or immutable | Not collected | Missing actual evidence; code-only corroboration if rows existed |
| Quantity and historical unit price | Not available | Captures quantity/current unit price and cart line amounts at snapshot time | Not collected | No historical record/catalog validation |
| Discounts and coupon allocations | Not available | Coupon codes/cart state, not complete immutable per-line allocation | Not collected | Missing |
| Fee lines | Not available | No full fee-line snapshot | Not collected | Missing |
| Billing address | Not available | Only limited billing identity (name/email/phone) is tracked by the inspected code | Not collected | Required address fields missing |
| Shipping address | Not available | Not captured by the inspected tracker/schema | Not collected | Missing when shipping applies |
| Shipping method and cost | Not available | No method/rate snapshot | Not collected | Missing |
| Tax rates and amounts | Not available | No tax-line/rate breakdown | Not collected | Missing |
| Currency and exact order total | Not available | Cart currency/total at mutable cart snapshot time only | Not collected | No actual value; cart total is not established Woo order total |
| Refund/partial-refund information | Not available | Not collected | Not collected | Missing actual provider evidence |
| Per-field provenance and integrity | Not available | Session hash/fingerprint design is code-only; cart fingerprint is not a source integrity guarantee | Receipt hash applies to webhook body but raw body is discarded; no payment linkage | Insufficient for reconstruction |

No field is classified as conflicting because there are no actual values to compare. Provider-side payment evidence, if supplied later, must remain separate from checkout/order evidence and cannot fill product, customer, shipping, discount, tax, or order-total fields.

## Exact linkage and integrity

- No actual payment or checkout record was available, so there is no exact payment ID to compare with a stable checkout-source ID.
- The cart recovery design keys observations by session HMAC and replaces/refreshes mutable snapshots. A matching session hash, email, phone, amount, or timestamp alone would not prove that a snapshot belongs to a particular Razorpay payment.
- The abandoned-cart receipt ID is generated by SuperWoo for the webhook receipt. It is not a Razorpay payment ID and the webhook implementation does not extract one.
- Stage B row hashes and Stage C provider identifiers are code-defined importer/verifier identifiers. No actual imported or verified record is present here.
- Product IDs in cart code do not prove historical catalog state. Current products may have changed prices, variations, tax classes or availability since the event.
- No date range, retention policy, backup chain, immutability control, checksum for checkout records, or historical event coverage could be verified.

## Adapter status

No adapter was implemented. There is no genuinely available source whose contents, authority, exact payment linkage, completeness and integrity can be verified. Implementing an adapter against a schema alone would create a false claim of historical evidence and weaken the existing Stage E evidence gate.

If an authoritative source is later supplied, the narrow read-only contract should return an immutable `source_reference` (backup/snapshot identity plus record key, or immutable checkout record ID), content fingerprint, exact payment ID link, and field-level provenance for every value. It must validate field completeness and arithmetic, flag historical catalog uncertainty and all conflicts, and never infer fields from amount, identity or time. A restored historical backup should be read through WooCommerce CRUD in an isolated copy where possible; the adapter must not query legacy order posts or HPOS tables directly.

## Tests and validation

No functionality was added in Stage F, so no test was added. Existing Order Recovery tests were rerun locally; they use mocks/stubs and do not validate actual historical records or a WordPress/WooCommerce runtime.

| Command | Result |
|---|---|
| `php tests/order-recovery-import.test.php` | PASS — 29 assertions |
| `php tests/order-recovery-repository.test.php` | PASS — 3 assertions |
| `php tests/order-recovery-provider.test.php` | PASS — 19 checks |
| `php tests/order-recovery-preview.test.php` | PASS — 21 checks |
| `php -l` over PHP files under `includes`, `admin`, and `tests` | PASS — 69 files |
| `git diff --check` | PASS |

No live data, backup restore, HPOS/legacy configuration, provider verification, or integration environment was tested. The earlier Stage E security, webhook, and order/payment isolation results are recorded in its report and are not represented here as historical-evidence validation.

## Recommendation and owner actions

**No candidate is eligible to proceed to a future approval phase.** Keep the current Stage E creation gate blocked. Historical payment counts remain unconfirmed missing-order counts.

To continue, provide locally or in an explicitly authorized isolated environment:

1. A sanitized, dated WooCommerce database backup or immutable checkout snapshot covering the incident period, with its source, timestamp, retention/immutability details, and a stable record key.
2. The official Razorpay export/schema and sanitized rows for affected payment IDs, plus the existing Stage C verification result if one exists. Do not provide credentials.
3. A defensible exact join key from checkout evidence to the exact Razorpay payment ID (for example, a recorded payment ID or documented immutable checkout/session correlation). Amount, customer identity or timestamp alone is insufficient.
4. For each proposed record, product/variation IDs, quantities, historical prices, discount/coupon allocation, fee lines, applicable addresses, shipping method/cost, tax rates/amounts, currency, exact WooCommerce total, refund details, and field-level provenance.
5. Historical catalog/tax/shipping configuration or a consistent backup containing the relevant definitions if those have changed since checkout.

Do not send live credentials or unredacted customer data through source files, test fixtures, archives or reports. Once the source is supplied, validate its schema and sample records before writing an adapter, and keep the adapter read-only.

## Safe next steps

1. Obtain and verify a restorable backup before any live-store recovery planning; work from a sanitized isolated copy.
2. Identify the incident date range, active WooCommerce/Razorpay gateway versions, HPOS mode, and retention of backups/logs without exposing credentials.
3. Inspect the supplied source’s actual fields, stable IDs, linkage and integrity against sanitized known records.
4. Build and test a read-only evidence adapter only if it can prove every required field and exact payment linkage; otherwise continue to mark affected records incomplete/manual review.
5. Re-run Stage D previews against the validated adapter in an isolated environment. Do not implement or enable order approval/creation until a separately reviewed stage is explicitly authorized.

No deployment, production access, database migration, or order reconstruction occurred in Stage F.

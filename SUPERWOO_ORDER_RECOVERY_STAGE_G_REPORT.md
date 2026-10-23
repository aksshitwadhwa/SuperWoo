# SuperWoo Order Recovery — Stage G Report

**Date:** 2026-10-23
**Outcome: no owner-supplied historical backup or Razorpay export is available in this project.** No reconciliation adapter or utility was implemented because there is no real source file whose schema, field semantics, linkage, integrity or historical coverage can be inspected. No candidate was advanced or marked eligible.

This phase was limited to local file and source inspection. No live store, external account, credential, database, provider endpoint or order was accessed. No migration, deployment, import, order/payment change, email, stock, webhook or fulfillment action occurred.

## Files and sources inspected

Read the Stage A, B, C, D, D.1, E and F reports. Inspected the Order Recovery CSV parser, repository/schema, classifier, provider verifier, preview, admin code and tests; cart recovery schema/tracker/coordinator; Razorpay abandoned-cart webhook; plugin root, checkout observer and integration references.

The local inventory used:

```powershell
Get-ChildItem -Recurse -File | Where-Object {
    $_.FullName -notmatch '\\.git\\' -and
    $_.Extension -match '^\.(sql|gz|wpress|bak|backup|csv|tsv|jsonl|log|zip|xml|json)$'
}
rg --files --hidden -g 'wp-config*.php' -g '.env*' -g 'wp-cli.yml' -g '*staging*' -g '*backup*' -g '*razorpay*' -g '*reconcil*' -g '!*.zip'
```

Files matching evidence-like extensions were the versioned SuperWoo release ZIPs and `update.json`; no `.sql`, `.sql.gz`, `.wpress`, `.bak`, `.backup`, `.csv`, `.tsv`, `.jsonl`, runtime `.log`, or staging configuration was found. The ZIP files are plugin distribution packages, not WooCommerce store backups. `SuperWoo-1.5.5.zip` was listed and contains plugin source/distribution paths. No owner-provided historical data file was present.

## Inventory and evidence classification

| Source | Actual availability, format and coverage | Linkage, integrity and fields | Usefulness |
|---|---|---|---|
| WooCommerce backup/order database | No backup or database file; no date range or retention information. | No stable order record, payment ID, order contents or checksum available. HPOS/legacy mode is unknown. | Unavailable. A backup can prove an order exists in that snapshot; it does not alone prove the contents of a checkout that never produced an order. |
| Official Razorpay payment/refund export | No export file or export schema. No export date range or timezone supplied. | No payment ID, Razorpay order ID, receipt, status, captured flag, currency, amount, refund, fee or fee GST values available. | Unavailable. Provider facts could corroborate payment/refund state only; they cannot supply original WooCommerce checkout contents. |
| Stage B recovery import records | Importer/schema code exists, but no WordPress database or imported rows are available. | The schema can retain provider identifiers, import batch and source-row fingerprints, but actual record IDs, values and source CSVs are absent. | No candidate-level reconciliation possible. Existing code cannot be treated as a recovered historical dataset. |
| Stage C provider verification records | Verifier/schema code exists; no database records were available and no provider call was made. | No verified payment ID/status/refund result to link to checkout evidence. | No actual provider evidence. |
| Cart Recovery data | `includes/cart-recovery/` schema and tracker exist as code; no runtime table/data or retention policy is available. Stage A/F report that the module is not wired into the current plugin bootstrap. | Intended fields include a public ID, HMAC session hash, optional order ID, cart products/variations, quantity, cart prices, coupons, currency/cart total and limited identity. The snapshot is mutable and the code does not show a complete address, shipping, tax or fee breakdown. No exact payment ID link is defined. | Code-only and incomplete; not checkout authority. Do not enable it or assume historical rows exist. |
| Razorpay abandoned-cart webhook receipt log | The receiver code defines a log under the runtime uploads directory; no such log was present. | The implementation records a generated receipt ID and redacted receipt summary/hash. It does not retain a payment ID, order contents, amounts/status/refunds, addresses, shipping or taxes. | Unsuitable for order reconstruction and unavailable for reconciliation. |
| WooCommerce/SuperWoo/gateway logs and exports | No runtime logs, order exports or reconciliation artifacts were present. | No date range, identifiers, field coverage or retention could be verified. | Unavailable. |
| Project integrations | Source references exist for the abandoned-cart receiver, Meta checkout analytics observer, configurable exchange-rate lookup and updater. Runtime settings/accounts were not accessed. | None provides an available immutable historical checkout record linked to an exact payment ID in this repository. Analytics dispatch and current exchange-rate retrieval are not retained checkout evidence. | No suitable source. |

The local project contains source code and tests, not evidence records. No statement is made that the store has no backups or exports elsewhere; only that none was supplied or accessible within this workspace.

## Candidate classifications and review report

There are no actual recovery-candidate database rows in the local project, so there are no candidate IDs or individual matching results to report. The outcomes below describe what can and cannot be established from the supplied material:

| Required distinction | Stage G finding |
|---|---|
| Existing WooCommerce order found | Not evaluated against a store. The local classifier requires a WordPress/WooCommerce runtime and uses WooCommerce CRUD. |
| Exact payment matched to verified historical checkout record | None. No checkout record or payment export/provider result was supplied. |
| Payment with incomplete checkout evidence | Any payment candidate represented only by imported CSV/provider facts remains incomplete; actual candidates are unavailable here. |
| Ambiguous or conflicting match | Cannot evaluate without actual order and payment records. Do not resolve using amount, identity or timestamp. |
| Workshop exclusion | Rules and code exist, but no actual Workshop identifiers/records were supplied. Generic text is not an exclusion proof. |
| Refunded or partially refunded payment | No actual provider/refund records exist locally to classify. Existing Stage E code fails closed when refund evidence is incomplete. |

No candidate is eligible for future order approval or creation. Stage E's creation gate must stay blocked. Aggregate payment counts must not be represented as missing-order counts.

## Expected evidence formats for a later intake

These are intake requirements, not formats found or validated in this phase.

### WooCommerce database backup

Provide a dated, complete, consistent backup from the period needed for comparison, with its generation time, source, retention/chain-of-custody notes and checksum. A SQL dump (optionally compressed, such as `.sql` or `.sql.gz`) or a documented WordPress backup archive (for example a `.wpress` package) may be suitable for a controlled restore. A package-specific archive must be restored with its supported tool into an isolated local environment; it must not be parsed as if it were a plain SQL dump.

The restore must include the WooCommerce order data and the relevant product/variation, coupon, tax, shipping and settings history needed to interpret stored order fields. Record the originating WooCommerce version and HPOS/legacy mode if known. Do not provide production credentials. Treat a backup as sensitive: do not place it in this plugin repository or commit it. Use a restricted local evidence directory and an isolated restored site. Read order records through WooCommerce CRUD wherever runtime access is possible; do not write directly to HPOS or legacy order tables.

A backup can establish a stored order's values at its snapshot date. A backup that contains no order does not prove the original checkout contents or the reason no order was persisted. For that, a separate durable checkout/session record with a documented exact link to the payment is required.

### Official Razorpay payment and refund exports

Provide the unmodified official export file(s) obtained by the owner from the authorized Razorpay account, its export/report name and documented schema, selected date range/timezone, generation time and any export filters. A CSV is the preferred source for inspection, preserving the original header row and encoding; a spreadsheet or statement in another format requires separate schema review before parsing. Razorpay export column names, units and refund representation are not assumed by this report.

For payment facts, the source should identify the exact payment ID and, where present, Razorpay order ID/receipt, status/captured state, currency, amount, creation/capture timestamps, amount refunded, and separately reported fee/fee GST. Refund rows should retain refund ID, linked payment ID, currency, amount, status and timestamp. If fee details are in a settlement export rather than a payment export, supply that as a separate source with its own schema and identifiers. Keep original files and report checksums; create a sanitized analysis copy when needed. Do not include credentials or authorization data.

These exports can establish only the gateway facts their documented fields support. They cannot establish products, quantities, variations, customer checkout selections, discounts, shipping, tax lines, addresses or the original WooCommerce order total.

## Field-level evidence status

No source row was actually available, so no required field is verified, corroborated or conflicting for a candidate. `Missing` below means absent from supplied evidence, not necessarily absent from the live store.

| Field | Supplied historical value | Requirement before any candidate can be considered complete |
|---|---|---|
| Stable checkout-source record ID | Missing | Immutable snapshot ID or backup identity plus record key and source fingerprint. |
| Exact Razorpay payment ID and verified status | Missing | Exact identifier shared by the checkout evidence or an explicit immutable join; status verified by supplied official provider evidence. |
| Product and variation IDs | Missing | Historical IDs tied to the checkout record and validated against historical catalog evidence. |
| Quantity and historical unit/item price | Missing | Exact quantity and historical prices from the authoritative checkout snapshot/order record. |
| Discounts/coupon allocations | Missing | Coupon and per-line/order discount values and their arithmetic. |
| Fee lines | Missing | Checkout fee lines distinguished from provider processing fees and fee GST. |
| Billing/shipping addresses | Missing | Original values from the source where applicable; do not derive from current customer identity. |
| Shipping method and cost | Missing | Historical method/rate and exact amount. |
| Tax rates and amounts | Missing | Historical line, shipping and fee tax rate IDs/rates and amounts. |
| Currency and exact WooCommerce total | Missing | Original checkout/order currency and total from the authoritative source. Keep distinct from Razorpay gross. |
| Refund and partial-refund facts | Missing | Complete provider refund listing/aggregate with stable refund IDs and terminal/known states. |
| Field provenance and integrity | Missing | Per-field source location, source record reference, transformations and immutable fingerprint. |

No amounts, emails, customer names, timestamps, current catalog values or fuzzy references were used to infer order contents or matches.

## Adapter and utility status

No Stage G adapter, importer or reconciliation utility was implemented. The Stage B parser's existing header normalization is not sufficient reason to claim compatibility with an unseen official Razorpay export. The database schema describes recovery records but cannot substitute for an actual source or schema sample. Implementing a backup adapter against an unprovided HPOS/legacy dump would also risk misinterpreting storage/version differences.

After real evidence is supplied, first validate a sanitized sample and its schema, date range, stable identifiers, source integrity and exact payment linkage. Any future utility should be read-only, preserve source fingerprints/provenance, separate provider facts from checkout facts, require exact identifiers, report ambiguity/conflicts, and fail closed for missing fields or arithmetic mismatches. It must not query order tables directly or modify the evidence gate.

## Tests and validation

No tests were added because no new functionality was implemented. Existing tests were rerun locally; they use mocks/stubs and do not validate genuine historical records.

| Command | Result |
|---|---|
| `php tests/order-recovery-import.test.php` | PASS — 29 assertions |
| `php tests/order-recovery-repository.test.php` | PASS — 3 assertions |
| `php tests/order-recovery-provider.test.php` | PASS — 19 checks |
| `php tests/order-recovery-preview.test.php` | PASS — 21 checks |
| `php -l` over PHP files under `includes`, `admin`, and `tests` | PASS — 69 files |
| `git diff --check` | PASS |

No backup restore, real export parse, provider verification, HPOS/legacy runtime, or live order comparison was run.

## Owner actions and safe next steps

1. Provide a dated complete WooCommerce backup or immutable checkout snapshot covering the affected period, along with provenance, checksum, storage mode/version if known, retention details and a stable record key.
2. Provide the official Razorpay payment export and separate refund export where applicable, with the untouched header/schema, selected time range/timezone and generation date. Do not provide credentials.
3. Provide the exact join key connecting each checkout record to the Razorpay payment ID. If the source has no exact join, leave the candidate unresolved.
4. Keep full backup/customer data outside the repository in restricted storage. Prepare a minimized/sanitized local copy for review without removing the exact linkage or field structure being validated.
5. Once available, inspect format and representative records first; only then design a read-only adapter and add parser/matching tests for that proven format.
6. Keep order creation disabled. This Stage G process is evidence reconciliation only; any later approval/creation phase requires separate authorization and the complete authoritative evidence gate.

No deployment, migration, provider API call, external account access, or order reconstruction occurred.

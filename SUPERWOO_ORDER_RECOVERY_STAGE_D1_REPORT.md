# SuperWoo Order Recovery — Stage D.1 Report

**Status: staging validation blocked; local regression checks passed.**

## Scope and outcome

Stage D.1 was intended to validate the Stage B import, Stage C provider verification, and Stage D read-only reconstruction previews against sanitized staging data, and to identify an authoritative source for historical checkout evidence. The available workspace is a plugin source checkout. It contains no mounted WordPress staging runtime, staging database/configuration, sanitized staging export, or backup/snapshot from which the requested environment and behavior could be verified. Per the safety requirements, no staging/provider access was attempted and no validation result is claimed for staging.

No adapter was implemented: there is no verifiable authoritative historical checkout source or safe staging access contract in the supplied workspace. The source candidates and limitations below are based on repository code and reports only.

## Inspection performed

Read `SUPERWOO_ORDER_RECOVERY_STAGE_A_AUDIT.md`, `SUPERWOO_ORDER_RECOVERY_STAGE_B_REPORT.md`, `SUPERWOO_ORDER_RECOVERY_STAGE_C_REPORT.md`, and `SUPERWOO_ORDER_RECOVERY_STAGE_D_REPORT.md`; inspected the recovery implementation, tests, and cart-recovery/webhook source paths.

Workspace inspection used PowerShell `Get-ChildItem -Force` and:

```powershell
rg --files -g "wp-config.php" -g "wp-config-*.php" -g ".env*" -g "docker-compose*" -g "compose.y*" -g "wp-cli.yml" -g "*.sql" -g "*.sql.gz" -g "*staging*" -g "*backup*" -g "*.wpress" -g "*.zip" -g "*.csv" -g "*.json"
```

The search found plugin release archives and the repository's `update.json`, but no WordPress configuration, database export, staging artifact, backup, or payment CSV. The ZIP files are SuperWoo plugin releases, not store backups. No staging hostname, database credentials, gateway configuration, or sanitized staging records were available in the checkout.

## Candidate historical evidence sources

| Candidate | Authority and retention | Stable identifiers / fields | Gaps and current availability |
|---|---|---|---|
| Existing WooCommerce orders read through CRUD | Authoritative for the existence and current stored values of an order. Historical retention depends on the store/backup policy. | WooCommerce order ID/number; CRUD-accessible customer, line, address, totals, taxes, shipping and payment fields where actually present. | No running WooCommerce instance was available. An existing order is useful to detect a match/conflict, not to establish missing-order fields. |
| Stage B imported Razorpay CSV records | A provider export can support gateway-side transaction facts according to the export's documented fields; it is not authoritative for WooCommerce checkout composition. Retention depends on operator export/storage policy. | Stable provider payment identifier and explicit receipt/order reference if present; provider status, amount, currency, fee/refund fields and timestamps if exported. | No sanitized CSV was present. No export schema or retention policy could be confirmed. Payment evidence cannot establish product lines, quantities, addresses, taxes, shipping or discounts. |
| Stage C verification results | Provider verification may establish the payment facts returned by the explicitly triggered verification operation. It does not establish the missing WooCommerce order representation. | Provider payment ID and response/reference identifiers captured by the existing Stage C record, subject to the actual response fields. | No staging verification result or provider account access was available. No new provider call was made. |
| Orphaned Cart Recovery snapshot schema/tracker | Repository code describes intended live cart/session observations, not proof that historical records exist or were retained. Its captured values would be corroborating checkout evidence only unless completeness, integrity and linkage are demonstrated. | The code defines a public record ID and HMAC session hash; cart item product/variation IDs, names, attributes, quantity and cart prices; coupon data; limited customer name/email/phone; currency/totals/context/timestamps and optional order ID. | Module remains unwired according to Stage A and no table/data was available to inspect. The snapshot does not capture complete billing/shipping addresses, shipping rates, line/tax breakdowns, fee rows or full discount allocations. Session hash is not the raw session cookie and does not itself prove linkage to a payment. Do not enable or query it for this validation. |
| Razorpay abandoned-cart webhook log | The repository implementation logs a redacted receipt of abandoned-cart webhook events; it is not a payment or completed checkout source. Retention is runtime uploads/log retention and was not inspectable. | Timestamp, hashed/presence or aggregate indicators and limited receipt information as defined by the code. | No runtime log was mounted. It does not provide payment status/ID, authoritative order lines, address, taxes or checkout totals. |
| Store backups, historical snapshots, operational logs | A consistent, dated store backup restored in an isolated staging environment may provide authoritative WooCommerce order evidence for orders that exist in that snapshot. Checkout/session logs need a documented schema and integrity/retention guarantees before they can support field reconstruction. | Depends on the actual backup/log schema and snapshot identity. | No backup, snapshot, or historical operational log was available. No fields or retention can be asserted. |

## Staging environment validation

The following could not be inspected or validated because the workspace has no staging runtime or data:

- Active WooCommerce and Razorpay gateway/plugin versions.
- HPOS enabled/disabled state or legacy compatibility behavior.
- Staging configuration, database, sanitized customer/order/payment fixtures, and backup availability.
- CSV import idempotency and exact reference matching against real staging rows.
- Explicit provider verification outcomes, including refunds and partial refunds.
- Duplicate/ambiguous payments, Workshop rules, permissions, CSV export, and each preview outcome in the admin UI.
- Read behavior under HPOS and legacy storage configurations.

No order, payment, stock, fulfillment, email, or customer-facing action was performed. No production credential, production migration, or Razorpay API call was used. The existing Stage C verification workflow was not triggered.

## Local regression checks

The following existing repository tests were run locally. These use the test suite's stubs and are not staging validation:

| Command | Result |
|---|---|
| `php tests/order-recovery-import.test.php` | PASS — 26 assertions |
| `php tests/order-recovery-provider.test.php` | PASS — 15 checks |
| `php tests/order-recovery-preview.test.php` | PASS — 20 checks |
| `php tests/security-hardening.test.php` | PASS — 12 assertions |
| `php tests/razorpay-abandoned-webhook.test.php` | PASS — 32 assertions |
| `php tests/order-payment-isolation.test.php` | PASS — 86 assertions |
| `php -l` over PHP files under `includes`, `admin`, and `tests` | PASS — 68 files |
| `git diff --check` | PASS — no whitespace errors |

No regression test was added because no reproducible code defect was established from available evidence. A local passing suite cannot establish active gateway compatibility, runtime permissions, HPOS behavior, or actual evidence quality.

## Read-only adapter proposal (design only)

If staging operators identify a source, first provide a sanitized, immutable source sample and document its retention and integrity controls. A future adapter should be read-only and return a packet with:

- A stable source reference: immutable backup/snapshot identifier plus source record key, or an immutable evidence-record ID; include a content checksum where the source format supports it.
- Per-field provenance: source reference, source field/path, authority level (`verified`, `corroborating`, or `missing`), and any transformation applied.
- Exact provider payment ID and explicit WooCommerce order reference only when present in the source. No amount-only, email-only, customer-only or timestamp-only identity join.
- Customer fields, product and variation IDs, quantities, line values, discounts, shipping method/rate, tax details, currency, and total only when each field is actually recorded by the authoritative source.
- Separate Razorpay gross, gateway fee, fee GST, refunds and discrepancy values; never alter the proposed WooCommerce total to force reconciliation.
- A source completeness indicator, missing-field list, conflict list, and stable evidence reference that can be followed in a review audit.

For backups, the preferred validation path is restoring a sanitized copy into an isolated staging site and reading existing orders through WooCommerce CRUD. An adapter must not directly query legacy post/order tables or HPOS tables. A separate checkout/session source should not be treated as authoritative until its capture coverage, schema, linkage, immutability and retention are demonstrated.

## Blockers, limitations and remaining risks

- Staging access and sanitized records are prerequisites for the requested end-to-end validation and were not supplied.
- No authoritative historical checkout source was verifiable. The existing orphaned cart-recovery schema is incomplete and its runtime presence/data cannot be established from this repository.
- The admin preview currently supplies no authoritative evidence packet to the evaluator (as documented in Stage D); therefore this checkout alone cannot produce or validate a complete `ready_for_review` reconstruction from historical checkout evidence.
- Local stub tests do not cover deployed plugin versions, database state, capabilities in the real admin, export handling in the browser, or HPOS/legacy runtime behavior.
- Historical payment counts remain unconfirmed and must not be treated as missing-order counts.

## Staging verification instructions

Before resuming Stage D.1, an operator should provide a staging URL or controlled staging access, exact active WooCommerce/Razorpay plugin versions, HPOS status, and approved sanitized fixture records. Include (or identify in staging) a source backup/snapshot or checkout evidence with documented schema, stable IDs, timestamps, field coverage and retention. Then, in staging only:

1. Confirm the environment identity and backup restore point; verify no production credentials are configured.
2. Exercise re-import of the same sanitized CSV and confirm stable row/batch behavior without duplicate recovery records.
3. Verify exact payment ID and explicit order-reference matching, then test missing references, duplicate/ambiguous references, Workshop exclusions and manual-review flags.
4. Trigger only the existing explicitly authorized Stage C provider verification for approved fixture IDs; verify captured, authorized, failed, refunded and partial-refund paths.
5. Verify roles/capabilities, nonce failures, filtered pagination and CSV export with sanitized data.
6. Review each preview outcome and provenance, including conflicts, incomplete evidence and a fully evidenced review candidate. Confirm WooCommerce order tables/CRUD state, payment records, stock, mail, fulfillment and webhook configuration remain unchanged.
7. Repeat supported read scenarios with HPOS enabled and legacy compatibility configuration where the staging installation permits it. Record exact versions, fixture identifiers (redacted), commands/screenshots or logs, and observed results.

These are instructions for a later staging run, not a claim that it occurred.

## Rollback

This Stage D.1 work made no application-code, database, provider, or staging changes; there is no Stage D.1 runtime rollback action. The report is the only Stage D.1 artifact. If a future adapter is introduced, remove/disable that read-only integration and its nonproduction configuration to roll it back; do not drop or rewrite recovery evidence tables without a separately reviewed retention/migration plan.

## Changed files

- Added `SUPERWOO_ORDER_RECOVERY_STAGE_D1_REPORT.md`.

No source code, schema, or tests were changed in Stage D.1. Existing uncommitted Stage A–D implementation/report files were present in the checkout and are not attributed to this stage.

# SuperWoo Cart Recovery Implementation Plan

## Design decisions

- Add Cart Recovery as a native module: `includes/class-cart-recovery.php` plus focused services under `includes/cart-recovery/`.
- Keep WooCommerce and SuperWoo as the cart/order source of truth. Recovery tables are an auditable snapshot and workflow record, not a replacement cart engine.
- Use Action Scheduler when WooCommerce is active. Do not use WP-Cron for recovery execution.
- Use WooCommerce CRUD APIs for all order work and declare HPOS compatibility.
- Support WhatsApp only via a `Wahob` provider abstraction. Until Wahob publishes/provides contracts, the provider is disabled and does not make speculative HTTP requests.

## Proposed data model

All tables use `$wpdb->prefix . 'superwoo_recovery_*'`, UTC `datetime` values, `BIGINT UNSIGNED` primary keys, `dbDelta` migrations, and no foreign-key constraints (WordPress-compatible upgrades) while retaining indexed logical relationships.

| Table | Purpose and key indexes |
| --- | --- |
| `superwoo_recovery_carts` | One persistent SuperWoo cart/session; unique session hash, status/last activity, customer ID/email hash/phone hash, recovery token hash, order ID, attribution fields. Indexes: status+abandoned_at, customer ID, email hash, order ID, token hash, expires_at. |
| `superwoo_recovery_cart_items` | Snapshot items: product/variation, quantity, prices, product name, variation attributes. Indexes: cart ID, product ID, variation ID. |
| `superwoo_recovery_events` | Immutable cart/recovery timeline. Indexes: cart ID+created_at, event type+created_at, idempotency key. |
| `superwoo_recovery_workflows` | Automation definition and enabled state. |
| `superwoo_recovery_workflow_steps` | Ordered conditions/wait/action configuration. Indexes: workflow ID+step order. |
| `superwoo_recovery_executions` | One workflow execution per cart/workflow, with idempotency lock/state. Unique cart/workflow constraint; indexes status/next run. |
| `superwoo_recovery_messages` | Email/Wahob/manual delivery attempts and provider IDs/statuses. Indexes: cart ID, execution ID, channel+provider ID, idempotency key, created_at. |
| `superwoo_recovery_offers` | Recovery coupon generation metadata and WooCommerce coupon ID/code hash. Indexes cart ID, coupon ID, expiry. |
| `superwoo_recovery_connections` | Wahob connection metadata. Secrets stored encrypted, never rendered/logged. |
| `superwoo_recovery_suppressions` | Hashed identifiers, channel, reason, timestamps. Unique channel+identifier hash. |

## Status and attribution model

`ACTIVE → ABANDONED → RECOVERY_IN_PROGRESS → RECOVERED` is the normal path. `EXPIRED`, `UNSUBSCRIBED`, and `SUPPRESSED` stop communication. The configurable inactivity threshold defaults to 30 minutes.

Recovery attribution priority:

1. Valid recovery-token cart restored and checkout completed within the configured window (default 7 days).
2. Click event tied to a SuperWoo message followed by the matching cart/order inside the window.
3. No implicit attribution for unrelated later orders.

Recovered orders receive SuperWoo cart, workflow, step, channel, token/click, timestamp, original cart value, and recovered value metadata through WooCommerce order CRUD APIs.

## Required hooks and endpoint design

### Storefront and checkout hooks

- Cart add/remove/quantity/application changes: create or update a snapshot with write throttling and a cart-content fingerprint.
- `woocommerce_checkout_update_order_review`/classic checkout field events: capture permitted identity fields after debounced frontend requests.
- Checkout Blocks: add a dedicated integration using Store API/Blocks extension points only after target-store validation.
- `woocommerce_checkout_order_processed`, payment completion, and relevant paid-order statuses: locate the matching recovery cart and atomically mark it recovered/cancel pending jobs.
- Recovery URL rewrite/query handler: validate opaque token, restore currently valid items/coupons into WooCommerce cart, record click, redirect to checkout.

### Endpoints

- `wp_ajax_nopriv_superwoo_recovery_capture`: rate-limited, nonce-protected progressive checkout/cart context capture; accepts only allowlisted fields.
- Authenticated Cart Recovery admin actions for manual send, retry, cancel, workflow operations, export/delete requests, and Wahob test/sync operations.
- `rest_api_init` route reserved for `superwoo/v1/wahob/webhook`; disabled until Wahob signature/replay requirements are known. It must validate signature, timestamp, idempotency, and provider event IDs before modifying a message event.

## Implementation phases

1. **Foundation and migrations** — module loader, settings defaults, HPOS declaration, capability checks, versioned database installer, repository interface, Action Scheduler health check.
2. **Cart/session tracking** — persistent cookie/session identifier, snapshot service, throttled writes, logged-in/guest correlation, attribution capture.
3. **Progressive checkout capture** — classic checkout JavaScript and endpoint; privacy consent behavior; Blocks assessment and implementation.
4. **Abandonment and recovery links** — status processor, secure opaque token handler, cart restoration validation, expiry/revocation.
5. **Automation engine and email** — workflow/step model, Action Scheduler dispatch, idempotency, retries/cancellation, email templates, unsubscribe/suppression.
6. **Smart coupons and attribution** — WooCommerce coupon generation constraints, cleanup, order metadata, audit trail.
7. **Admin experience** — Cart Recovery overview, carts table/detail timeline, workflows, templates, settings, reports, product insights, manual recovery.
8. **Wahob adapter** — provider contract/config screen first; authentication/template/contact/message/webhook support only after documented Wahob API contracts are supplied.
9. **Reporting, retention, and hardening** — aggregate queries, retention cleanup, export/erase support, concurrency tests, operational health panel.
10. **Release validation** — migration upgrade test, HPOS/classic/Blocks matrix, Action Scheduler behavior, duplicate-send/recovery attribution regression tests, release documentation.

## Performance and migration strategy

- Snapshot only when the cart fingerprint or captured identity changes, with a short debounce; do not write on every frontend event.
- Use indexed tables and paginate/admin-query by date/status. Analytics should aggregate SQL data by selected range rather than loading all carts.
- Use Action Scheduler batches with claim/idempotency protections and a cancellation/recheck before every message.
- Store only configuration/schema versions in options. Migrations are additive, versioned, and rerunnable with `dbDelta`; destructive cleanup is opt-in and retention-based.
- Preserve existing SuperWoo/WooCommerce data. Deactivation cancels only Cart Recovery scheduled actions; uninstall behavior must be an explicit retention choice, never accidental data loss.

## Security, privacy, and operational acceptance criteria

- No payment data is collected or copied.
- PII is allowlisted, encrypted/protected where appropriate, not written to SuperWoo diagnostic logs, and subject to configurable retention and deletion/export controls.
- Recovery links are cryptographically random, stored hashed, expirable, revocable, and rate-limited.
- Every send has an idempotency key and all automation steps re-check cart state immediately before execution.
- Wahob is the only WhatsApp provider. No provider request is implemented until real Wahob endpoint/auth/webhook documentation is available.
- Production readiness cannot be claimed until critical guest/customer tracking, recovery restoration, duplicate-send prevention, workflow cancellation, HPOS, classic checkout, target-store Checkout Blocks, and Wahob contract tests pass.

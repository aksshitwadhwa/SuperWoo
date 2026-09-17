# SuperWoo Cart Recovery Audit

## Scope and audit boundary

This audit was completed before any Cart Recovery implementation code was added. The findings are based on the SuperWoo source tree at version `1.0.222`.

## Existing architecture

### Bootstrap and module loading

- `superwoo.php` is the bootstrap. It defines plugin constants, loads all module classes, installs activation/deactivation hooks, and bootstraps `SuperWoo_Plugin` on `plugins_loaded`.
- `includes/class-plugin.php` is the composition root. It registers the SuperWoo menu, settings save handler, diagnostics, and core WooCommerce diagnostics.
- On ordinary wp-admin requests it loads admin-relevant modules only. Storefront/AJAX requests load product, cart, reviews, variation, and carousel modules.
- Module classes use a `hooks()` method and are instantiated from the composition root. This is the correct pattern for Cart Recovery.

### Existing modules

- Product benefits and product meta.
- Bundle offers and offer notices.
- AJAX cart drawer.
- Product reviews with media support.
- Shop filters, variation cards, product carousel, currency, Elementor tags, shortcodes, and shoppable videos.
- Shoppable Videos is the only analytics-oriented module. It uses a custom post type plus one event table.

### Admin architecture and design system

- `SuperWoo → Settings`, `Health`, and `Logs` are registered in `SuperWoo_Plugin::register_settings_page()`.
- Bundle Offers and Shoppable Video Analytics add their own submenu pages.
- Server-rendered admin views live in `admin/views/`; shared styling is `public/css/admin.css`.
- Admin actions use `admin-post.php`, WordPress nonces, and `manage_woocommerce` capability checks.
- No generic admin routing, table, chart, or component framework exists. Cart Recovery should use server-rendered SuperWoo admin views and extend `admin.css`/a scoped Cart Recovery stylesheet.

### Settings and logging

- Global settings are stored in one `superwoo_settings` option through `superwoo_get_settings()` and `SuperWoo_Plugin::save_settings()`.
- This is appropriate for low-volume feature configuration only. It must not store carts, events, automation jobs, messages, or analytics rows.
- `superwoo_log()` writes sanitized diagnostic context to WooCommerce logging and an uploads-based SuperWoo log. It intentionally omits PII-like fields, secrets, payment values, tokens, emails, phones, and addresses.
- Cart Recovery should reuse this logger for technical diagnostics only; business events and auditable communication history belong in dedicated tables.

### WooCommerce, checkout, AJAX, and frontend conventions

- `SuperWoo_Cart_Drawer` is the primary cart integration. It supports normal admin AJAX, `wc_ajax` for cart mutations, WooCommerce cart fragments, and Store API cart refresh support.
- Cart Drawer JavaScript uses localized config, a single nonce, jQuery, and WooCommerce fragments. It already persists WooCommerce cart session data after mutations.
- Existing product/cart hook points include `woocommerce_add_to_cart`, `woocommerce_cart_item_removed`, `woocommerce_after_checkout_validation`, `woocommerce_checkout_order_processed`, `woocommerce_payment_complete`, and failed-order status handling.
- Existing AJAX endpoints are `wp_ajax_*`/`wp_ajax_nopriv_*`; no SuperWoo REST routes are registered.
- Classic checkout has integration points. Checkout Blocks are not explicitly implemented; Store API cart refresh is the only Blocks-adjacent behavior identified.

### Existing database and scheduled processing

- There is no shared database abstraction/repository layer.
- `SuperWoo_Shoppable_Videos::install()` creates `{$wpdb->prefix}superwoo_video_events` with `dbDelta`, a schema option, indexes, and a scheduled cleanup hook.
- Video analytics uses WP-Cron (`wp_schedule_event`) for daily cleanup. No Action Scheduler usage was found.
- There are no existing Cart Recovery tables, migration registry, relationships, or cleanup policies.

### Integrations, email, reporting, and customer tracking

- No Wahob code, credentials, documentation, or API contract is present.
- No direct email automation/template system is present. WooCommerce mail can be used as the transport behind a new native recovery email renderer.
- No customer/visitor tracking abstraction, abandoned-cart workflow, recovery attribution, or product-abandonment reporting exists.
- No chart library or build pipeline was found. Use lightweight server-provided datasets with WordPress-admin-compatible rendering, or introduce a scoped dependency only after approval.

### HPOS, tests, build, and conventions

- No HPOS compatibility declaration was found. The module must declare compatibility through WooCommerce's `FeaturesUtil` after the plugin bootstrap is available.
- Existing order diagnostics use `wc_get_order()`/`WC_Order` patterns and do not query `wp_posts` directly. Cart Recovery must keep that HPOS-safe approach.
- No PHPUnit, Composer, npm, or JavaScript build/test configuration was found. Current JavaScript is unbundled browser JavaScript and CSS is committed directly.
- PHP is written as WordPress class files guarded by `defined('ABSPATH') || exit;`; SQL should use `$wpdb->prepare()`, schema installs use `dbDelta`, and output is escaped.

## Reusable components

| Existing component | Reuse in Cart Recovery |
| --- | --- |
| `SuperWoo_Plugin` | Module registration, activation migration trigger, submenu registration, shared settings.
| `SuperWoo_Cart_Drawer` | Cart mutations/fragment lifecycle; add non-invasive tracking calls after confirmed cart state changes.
| `superwoo_get_cart()` | Safe retrieval of WooCommerce cart context.
| `superwoo_log()` | Sanitized technical diagnostics only.
| Shoppable Video installer | `dbDelta`, schema-version option, and cleanup pattern—improved with explicit versioned migrations and Action Scheduler.
| Existing AJAX conventions | Nonce/capability structure for authenticated admin operations; public tracking needs a distinct, limited nonce strategy.
| WooCommerce `WC_Order` APIs | HPOS-safe order attribution and reporting.

## New requirements

### Database

Dedicated tables are required for cart sessions, cart items, automation workflows/steps, executions, message events, recovery events, offers/coupons, Wahob connections, and structured logs. High-volume data must never be stored in `wp_options`.

### Hooks

Required hooks include cart add/update/remove/restore, checkout field changes, checkout start, order creation/status/payment, Action Scheduler actions, recovery-link request handling, and optional Wahob webhook processing. Exact hook registration is defined in the implementation plan.

### API and frontend work

New endpoints are required for debounced progressive checkout capture and Wahob webhooks. Admin actions can use authenticated `admin-post`/AJAX endpoints; public recovery must use opaque signed tokens and not expose internal IDs. Checkout Blocks need a separate Store API/Blocks integration assessment and test pass.

### Privacy and security

Recovery data contains PII. The module needs explicit opt-in/consent policy controls, suppression/unsubscribe support, retention controls, minimal collection, access control, redacted diagnostics, encryption-at-rest strategy for provider credentials, prepared SQL, nonce and capability enforcement, idempotency keys, token expiry/revocation, webhook verification, and rate limits.

### Architectural blockers

There is no technical blocker to implementation. Two prerequisites prevent a fully operational WhatsApp integration:

1. Wahob API documentation/credentials and webhook-signature contract are absent.
2. Checkout Blocks behavior must be tested on the target store; no Blocks-specific integration is currently present.

The Cart Recovery core, email automation, secure recovery links, admin UI, Action Scheduler processing, and a Wahob provider abstraction can proceed without inventing Wahob endpoints.


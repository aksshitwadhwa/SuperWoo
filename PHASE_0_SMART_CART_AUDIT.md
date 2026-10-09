# Phase 0: Universal Smart Cart audit

**Status:** Audit only. No implementation, deployment, database operation, or runtime setting change was performed.

**Scope:** Existing SuperWoo cart UI and integrations, with a proposal for an opt-in responsive cart drawer offering free-shipping progress, product recommendations, savings, merchant trust/delivery information, and a sticky checkout footer.

## Current architecture

SuperWoo initializes its frontend modules in `includes/class-plugin.php`. `SuperWoo_Cart_Drawer::hooks()` exits when `enable_cart_drawer` is off; otherwise it attaches the drawer to `wp_footer`, enqueues the drawer assets, adds WooCommerce cart fragments, and registers SuperWoo AJAX and `wc_ajax` handlers. The drawer is rendered only when the WooCommerce cart is initialized and the request is not a checkout page. This makes the current implementation a site-wide overlay, not a replacement for WooCommerce's native mini-cart block.

`templates/cart-drawer.php` supplies the shell, backdrop, drawer, mobile navigation/search, and product-page sticky add-to-cart area. `templates/cart-drawer-inner.php` renders the changing cart contents: notices, offer notices, line items and variation metadata, quantity/removal controls, cross-sells, totals, and checkout action. The footer is already positioned as a fixed/sticky region; `syncDrawerLayout()` in `public/js/cart-drawer.js` measures its height and reserves scroll space.

Cart changes use two paths. SuperWoo's AJAX/`wc_ajax` endpoints update, remove, or add through `WC()->cart` methods and recalculate totals. JavaScript also calls WooCommerce Store API cart endpoints for native cart mutations, captures Store API nonce tokens, then requests a read-only SuperWoo refresh. `superwoo_cart_drawer_fragments()` and `superwoo_cart_total_html()` provide the rendered fragments and totals. WooCommerce remains the calculation owner, although SuperWoo's endpoints also explicitly call `calculate_totals()` and persist session state.

The checkout action is deliberately conditional. `superwoo_cart_primary_button_html()` emits the Razorpay Magic Checkout button only when `superwoo_razorpay_magic_checkout_available()` finds the expected plugin functions and enabled state; otherwise it links to `wc_get_checkout_url()`. A Smart Cart must retain this exact handoff, including the current amount label and button identity, and must not add gateway calls or order/payment mutations.

The settings are stored in the `superwoo_settings` option. Defaults come from `superwoo_get_settings()` and the settings page is `admin/views/settings-page.php`; `SuperWoo_Plugin::save_settings()` reconstructs and saves a broad settings array. Existing relevant settings include `enable_cart_drawer`, `cart_auto_open`, `cart_drawer_crosssell`, and `cart_drawer_coupon`. Cart recovery is separately enabled by `enable_cart_recovery` and uses its own recovery tables, tracker, automation, and checkout hooks.

No PHPUnit, Composer, JavaScript package, Jest, or Playwright test configuration was found in the plugin tree. Existing audit/plan markdown files provide prior manual investigation context, but do not constitute automated regression coverage.

## Existing feature map and reuse

| Smart Cart capability | Existing implementation | Reuse / gap |
|---|---|---|
| Free-shipping progress | `SuperWoo_Cart_Drawer::get_cart_notice_message()` uses `superwoo_offer_free_delivery_threshold` and sums non-gift `line_subtotal` values. Bundle offer notices can take precedence. | Reuse its display container and offer-aware placement. This threshold is not WooCommerce's selected zone/rate/free-shipping method eligibility. An accurate progress feature needs a supported source of eligibility, with a clear unavailable state when destination/rates are not known. |
| Product recommendations | `get_cross_sell_products()` uses product cross-sell IDs, then related products; filters to purchasable, visible, in-stock simple products. `cart_drawer_crosssell` controls this. | Reuse WooCommerce cross-sell/related product selection and existing add endpoint for simple products. Filter out cart products and duplicates. Variable products need a product page/options selection instead of direct add. |
| Customer savings | Cart rows display catalog regular/sale price markup in `cart-drawer-inner.php`. Bundle offers may alter line prices during `woocommerce_before_calculate_totals`. | There is no authoritative cart-wide savings summary. Show savings only when WooCommerce supplies a verifiable coupon/discount amount or line discount; define treatment of sale pricing, coupons, tax, gift lines, and dynamic pricing before implementation. Do not compute by subtracting arbitrary catalog prices from mutable cart prices. |
| Merchant trust and delivery | Existing UI has offer/free-delivery notices, product variation details, and theme-driven colors. | No merchant trust or delivery promise data model was found. Add merchant-managed copy/date/rules as optional settings or render nothing; do not infer delivery promises from a generic shipping threshold. |
| Sticky checkout footer | `.superwoo-cart-drawer__footer` is outside the scrolling content and CSS fixes it to the drawer bottom; JS measures its height. | Reuse existing footer/checkout rendering. Any added summary must be height-measured and usable at small viewport heights and zoom. Preserve Razorpay Magic Checkout fallback logic. |

## WooCommerce calculation and compatibility boundaries

- **Native calculations:** Use the initialized `WC_Cart` and `WC_Product` APIs as read models. Display totals using WooCommerce formatted total/subtotal/shipping/tax values and current currency context. Never set product prices, fees, taxes, shipping, coupon state, cart contents, or order state to produce Smart Cart presentation.
- **Variations:** Existing cart rows preserve variation IDs and formatted cart item data. Recommendations are simple products only today. Any variable recommendation must link to product/options selection or use a fully validated WooCommerce variation add flow; never add a parent product without required attributes.
- **Coupons and taxes:** `superwoo_cart_total_html()` currently renders subtotal, shipping, and grand total, but does not provide a dedicated tax or discount breakdown. Use WooCommerce cart discount/tax getters and existing formatted HTML where a breakdown is enabled. Avoid a competing recomputation, and account for tax display configuration and inclusive/exclusive prices.
- **Shipping zones:** The configured SuperWoo free-delivery threshold is a merchant-entered global notice value, not a proxy for WooCommerce shipping zones, selected package rates, free-shipping minimums, coupon rules, or destination-dependent rates. A reliable bar must reflect the actual eligible package/rate or explicitly remain a separately labeled merchant offer. Multiple packages and unset customer addresses require defined behavior.
- **Multi-currency:** `SuperWoo_Currency` filters display currency and restores/converts cart product prices around WooCommerce total calculation. Third-party multi-currency extensions may also filter prices/totals. Render already-calculated WooCommerce values once; do not call SuperWoo conversion on values already converted by hooks, or append a second currency conversion. A currency integration matrix is needed before claiming support for named third-party plugins.
- **Classic and block themes:** The drawer attaches to `wp_footer` and uses WooCommerce classic fragments plus Store API cart requests. It should work where the theme emits `wp_footer`, but no native WooCommerce Mini-Cart/Cart/Checkout block integration was found. Site Editor templates, block cart state updates, fragment deprecation, caching/optimization, and themes that omit or delay footer output need explicit tests. The drawer should not replace or mutate a native block's own cart state.
- **Mobile accessibility:** The shell uses dialog semantics, `aria-hidden`, Escape close, focus-on-open, focus restoration, live updates, and touch scrolling. The inspected drawer script does not implement a complete keyboard focus trap or inert background management. Smart Cart acceptance should include keyboard containment, screen-reader state/count announcements, visible focus, reduced motion, safe-area insets, zoom, and short-screen/iOS viewport behavior.
- **Cart Recovery boundary:** `SuperWoo_Cart_Recovery` captures cart snapshots, customer identity, checkout-start events, and restoration data into its own dedicated schema. Smart Cart should not add analytics/identity capture, write recovery tables, change recovery email/link flows, or modify recovery cart restoration. Ordinary cart mutations may continue to be observed by existing WooCommerce hooks.

## Proposed files for a later Phase 1

These are proposed targets only; no file was changed in this audit.

| Proposed file | Purpose |
|---|---|
| `includes/class-universal-smart-cart.php` | New display-only Smart Cart coordinator/data provider. Register only behind the new opt-in master flag. Read existing `WC()->cart`; expose normalized, formatted presentation data without mutating cart state. |
| `superwoo.php` | Require the new class alongside cart modules. |
| `includes/class-plugin.php` | Instantiate/register the module and sanitize/save the new master and per-feature opt-in settings while preserving all existing settings. Existing broad settings serialization must be handled carefully so saving the settings page does not clear absent Smart Cart fields or alter older options. |
| `includes/functions.php` | Add defaults and narrowly scoped helpers for feature flags and any compatibility-safe WooCommerce presentation values; avoid duplicating cart calculations. |
| `admin/views/settings-page.php` | Add a Smart Cart settings panel for the master switch and five independent feature switches, plus merchant-controlled trust/delivery copy if approved. Keep all new controls opt-in and preserve existing Cart tab controls. |
| `includes/class-cart-drawer.php` | Integrate opt-in render data into the existing drawer only; keep endpoints, mutation logic, and checkout selection unchanged. Gate any new data/render branch. |
| `templates/cart-drawer-inner.php` | Render the optional progress, recommendation, savings, trust/delivery, and footer presentation from normalized view data. Keep old markup branch intact when the master switch is off. |
| `public/js/cart-drawer.js` | Only if necessary: synchronize visual progress/height and accessible announcements after existing fragment/Store API refresh. Do not create new cart mutation pathways. |
| `public/css/cart-drawer.css` | Responsive styles for opt-in elements and accessible states, scoped to the new feature classes. |
| `tests/` (new) | Introduce a reproducible test harness/fixtures before broad integration changes; likely PHPUnit/WooCommerce integration coverage plus browser tests. No harness currently exists. |

Possible future extraction of totals/progress into separate helpers should be decided after defining the exact meaning of “savings” and “free shipping.” Do not widen `includes/class-cart-drawer.php` into another calculation engine.

## Dependency map

```text
superwoo.php
  ├─ includes/functions.php ── settings defaults, cart access, fragment/total helpers
  ├─ includes/class-currency.php ── WooCommerce price/currency hooks
  ├─ includes/class-bundle-offers.php ── cart discounts, gift items, offer notices
  └─ includes/class-plugin.php
       └─ includes/class-cart-drawer.php [enable_cart_drawer]
            ├─ templates/cart-drawer.php
            ├─ templates/cart-drawer-inner.php
            ├─ public/js/cart-drawer.js ── classic AJAX + Store API refresh/mutations
            └─ public/css/cart-drawer.css

Future opt-in:
class-plugin/settings → class-universal-smart-cart (read-only view data)
                   → existing cart drawer/template/assets
                   → WooCommerce calculated cart state

Separate boundary: class-cart-recovery + includes/cart-recovery/* + recovery tables
Checkout boundary: existing Woo checkout URL or Razorpay Magic Checkout button
```

## Compatibility risks and blockers

1. **Shipping eligibility is unresolved.** The existing threshold may represent a merchant promotion; it is not necessarily the selected WooCommerce shipping method's rule. Product behavior must choose whether the bar reports WooCommerce free shipping, the SuperWoo offer threshold, or both distinctly. For accurate WooCommerce status, define how to handle no destination, multiple shipping packages, coupons that unlock free shipping, virtual-only carts, and selected rate changes.
2. **“Accurate customer savings” needs a definition.** Decide whether this means sale-vs-regular price, coupon discounts, dynamic/bundle discounts, tax-inclusive reductions, or a combined value. WooCommerce's cart discount and tax state should be the authority. Third-party dynamic pricing may not expose a comparable original price.
3. **Trust/delivery content has no existing source.** Merchant copy, delivery estimate source, and conditions need to be configured or supplied by an explicit integration. Do not show unsupported delivery-date promises.
4. **Theme and checkout integration matrix is not present.** No local staging/browser automation or plugin compatibility matrix is part of the repository. Block themes and Razorpay Magic Checkout need real staging verification before release.
5. **Multi-currency combinations are open-ended.** SuperWoo has its own conversion hooks; third-party converter behavior cannot be safely assumed. Start with WooCommerce-formatted current-cart values and test supported integrations by name before documenting compatibility.
6. **Working tree has pre-existing edits.** At audit time, `includes/class-plugin.php`, `includes/functions.php`, `readme.txt`, `superwoo.php`, and `update.json` were already modified. They were not changed by this audit. Any Phase 1 review must preserve and account for those unrelated/local changes.

These decisions are blockers to implementation semantics, not blockers to this Phase 0 report.

## Regression inventory

Phase 1 must verify existing behavior for:

- Empty cart, guest and logged-in sessions, cart count, header/shortcode/Elementor triggers, mobile bottom navigation, auto-open, close/backdrop/Escape, and focus restoration.
- Simple, variable, variation, sale, stock-limited, and non-purchasable items; formatted variation metadata; quantity updates and removals.
- Classic AJAX/`wc_ajax`, WooCommerce fragments, Store API add/update/remove, cross-sell addition, repeated add protection, and session persistence.
- Subtotal, shipping, tax, grand total, coupons, bundle discounts/free gifts, offer notices, free-delivery notice, and free-gift removal/requalification.
- Razorpay Magic Checkout activation conditions and button ID, regular WooCommerce checkout fallback, checkout page exclusion, and no payment/order side effects.
- SuperWoo multi-currency and a named third-party multi-currency integration; shipping zones, free-shipping methods/coupon conditions, multiple packages, and virtual carts.
- Classic theme, block theme with Cart/Checkout blocks, Elementor, mobile/tablet/desktop widths, WP Rocket/cache/minification, and theme CSS collisions.
- Cart Recovery capture, checkout-start tracking, restoration link, and recovered-order status remain unchanged.
- Keyboard-only operation, screen-reader dialog/live-region announcements, visible focus, reduced motion, 200% zoom, safe-area padding, and short mobile viewport scrolling.

## Testing strategy

1. **Static and PHP checks:** PHP lint all changed PHP files; WordPress coding/static checks if available. Validate settings sanitization and option backward compatibility, including partial/missing posted fields.
2. **Unit/helper tests:** Establish a test harness. Test feature-flag defaults, read-only view model behavior, recommendation de-duplication and purchasability, and savings/progress presentation states without cloning WooCommerce pricing algorithms.
3. **WooCommerce integration tests:** Use fixtures for simple/variable products, taxes, coupons, sale prices, shipping zones/rates/free shipping, virtual products, bundles/free gifts, and guest/session carts. Assert Smart Cart reads totals and causes no mutation to cart line data, coupons, fees, rates, totals, order, or payment state.
4. **Transport tests:** Exercise classic AJAX/fragment updates and Store API updates/removals, nonce handling, fragment refresh, session consistency, and empty-cart transitions.
5. **Browser/accessibility tests:** Add Playwright coverage for classic and block themes at desktop/mobile sizes, keyboard and screen-reader-visible state, sticky footer layout, reduced motion, and dynamic height changes after every cart refresh. Supplement automated checks with VoiceOver/NVDA and iOS Safari smoke tests.
6. **Staging compatibility:** Test supported Razorpay Magic Checkout and fallback; SuperWoo multi-currency and each explicitly supported third-party currency plugin; WP Rocket with cache/minification; and current production theme/page-builder combinations. Never use a live payment for test validation.

## Safe feature flag and rollback design

Add one `enable_universal_smart_cart` master setting defaulting to `false`, plus independent `smart_cart_free_shipping`, `smart_cart_recommendations`, `smart_cart_savings`, `smart_cart_trust_delivery`, and `smart_cart_sticky_footer` switches all defaulting to `false`. The master switch gates registration/rendering; individual switches gate only their own UI. Keep `enable_cart_drawer` and all existing settings semantics unchanged. With the master disabled, the current template output, AJAX handlers, Store API behavior, checkout button, and CSS/JS behavior should be byte-for-byte or behaviorally equivalent to current behavior.

Rollback is the master switch off; no schema migration or cart/session/order mutation should be required. If a release regression requires a full rollback, restore the previous plugin package after disabling the feature. Do not roll back or overwrite existing merchant WooCommerce shipping, tax, currency, offer, or payment settings.

Settings serialization needs special care: `save_settings()` reconstructs the option array. New flags must be included with safe defaults, while existing flags and posted values remain preserved; missing Smart Cart fields must never implicitly turn on a feature.

## Proposed implementation phases and acceptance criteria

### Phase 1 — contracts, settings, and read-only view model

- Define and document the free-shipping and savings semantics, supported currency integrations, trust/delivery source, and short-cart behavior.
- Add disabled-by-default master and per-feature flags without changing any existing option value or cart behavior.
- Implement a read-only Smart Cart view model based on initialized WooCommerce cart/rate/discount APIs; prove with tests that it does not mutate cart, session, checkout, order, or payment state.
- **Accept when:** existing cart markup/behavior is unchanged with the master off; settings survive save/reload and older stored options; tests cover no-cart/uninitialized-cart and feature flag combinations; no checkout or gateway code path changes.

### Phase 2 — opt-in presentation in existing drawer

- Render free-shipping progress, recommendations, savings, and trust/delivery content independently in the existing drawer; keep the existing sticky checkout control and Magic Checkout selection intact.
- Keep unavailable/ambiguous data hidden or clearly labeled rather than presenting a guessed amount/date.
- **Accept when:** each switch independently controls only its own section; values match WooCommerce under coupons/tax/shipping and supported currencies; variation recommendations cannot be incorrectly added; existing drawer endpoint payloads and checkout actions are unchanged.

### Phase 3 — responsive accessibility and classic/block compatibility

- Complete focus containment/inert behavior, announcements, visible focus, reduced-motion and safe-area support; ensure fragment and Store API refreshes update all enabled presentation without stale totals.
- Validate footer layout at minimum supported viewport sizes and on short/zoomed screens.
- **Accept when:** keyboard and screen-reader interaction meets the documented dialog behavior; mobile layouts have no overlapping/hidden checkout controls; classic and block theme tests pass; no duplicate cart state appears.

### Phase 4 — release gate and guarded rollout

- Run full regression and staging matrix, inspect logs and WooCommerce notices, package a release, and document supported integrations and rollback instructions.
- Enable only on a staging store first; merchant can opt in individual features after validating configuration.
- **Accept when:** automated checks and manual integration matrix pass, the feature-off path is verified against baseline, no payment/cart-total discrepancy is observed, and release artifacts contain no unrelated working-tree changes.

## Phase 0 disposition

The audit is complete. No code outside this report was modified, and no deployment or destructive operation was performed. Phase 1 should wait for approval and for the three product semantics in **Compatibility risks and blockers** (shipping eligibility, savings definition, and trust/delivery data source) to be settled.

# SuperWoo Smart Cart — Phase 0 Audit

**Date:** 2026-10-30  
**Scope:** Local repository review only. No production access or deployment.

## Existing architecture

- `SuperWoo_Cart_Drawer` owns the existing drawer, localized AJAX/Store API bridge, nonce checks, cart mutation endpoints, fragment rendering, and session persistence.
- WooCommerce `WC()->cart` remains the source for items, totals, coupons, shipping, and cart session. SuperWoo offer rules mutate cart prices through their existing WooCommerce hooks; the Smart Cart does not introduce a separate price calculator.
- `templates/cart-drawer-inner.php` renders cart rows, existing offer/delivery progress, WooCommerce cross-sells (with related-product fallback), privacy acknowledgment, and checkout CTA.
- `superwoo_cart_total_html()` summarizes WooCommerce cart totals. Cart Recovery and checkout/payment modules are separate; this work adds no order, payment, gateway, or checkout-submit hooks.
- JavaScript delegates to WooCommerce cart routes where available and the Store API for supported external cart mutations. The existing request queue, duplicate-add defenses, mutation generation, and drawer loading state are reused.
- Settings share the secured `superwoo_settings` persistence map and the existing Cart settings tab.

## Gaps addressed in this upgrade

- The coupon setting previously supported a checkout link or disabled state, with no drawer application. A native WooCommerce coupon route and optional inline UI have been added.
- Product rows called a quantity-based amount “Subtotal” and could compare a catalog total with a discounted cart line. They now show a unit price and a separately labeled payable line amount; sale strike-through is limited to WooCommerce sale products.
- The related-product fallback previously shared the cross-sell heading. The source is now reflected in the heading; merchant wording is used only for explicit WooCommerce cross-sells.
- Merchant-configured, opt-in trust content is being added with blank defaults so the plugin does not invent shipping, returns, security, or support promises.
- Quantity updates gain server-side sold-individually and maximum-purchase checks when the product exposes WooCommerce’s standard API.

## Reused behavior

- Existing drawer shell, loading overlay, sticky footer, consent control, cart item mutation pipeline, fragment refresh, offer engine, free delivery eligibility check, cross-sell AJAX add, cart total helpers, settings form, and persisted settings merge.
- Existing configured free-delivery amount and offer rules; no reference-image thresholds, products, or prices are copied.

## Risks and compatibility boundaries

- Store-specific shipping eligibility may depend on address, chosen package, extensions, coupons, and taxes. Existing progress logic suppresses a free-shipping promise until WooCommerce has calculated shipping and selected a real zero-cost method. Threshold messaging remains informational.
- Custom product types, decimal quantity plugins, custom cart stores, and third-party coupon interfaces require runtime coverage beyond this local source review.
- Variable recommendations are intentionally omitted from the quick-add cards because they require option selection; only eligible simple products are quick-added.
- Classic/Blocks integrations, HPOS, supported payment providers, and theme behavior have not been revalidated as a compatibility matrix in this turn.
- The current privacy acknowledgment is retained. Whether it is legally required is merchant/legal configuration, not something this UI change can determine.

## Proposed/changed files

- `includes/class-cart-drawer.php`: native coupon endpoints, recommendation source label, quantity validation.
- `includes/class-plugin.php`, `includes/functions.php`, `admin/views/settings-page.php`: validated coupon/trust/recommendation settings.
- `templates/cart-drawer-inner.php`: clearer row pricing, coupon form, opt-in trust strip, truthful recommendation heading.
- `public/js/cart-drawer.js`, `public/css/cart-drawer.css`: asynchronous coupon actions and presentation.
- `tests/cart-coupon-contract.test.php` plus existing cart/settings tests.
- Remaining reports under this directory.

**Phase 0 status: PASS for repository architecture review.** Store-specific checkout/shipping behavior remains unverified.

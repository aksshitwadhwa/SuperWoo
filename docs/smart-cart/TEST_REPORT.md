# Smart Cart Test Report

**Date:** 2026-10-30

> Current status is in the Phase 11 addendum below; Phase 10 browser-block statements are historical.

## Executed and passed

- All **21** standalone `tests/*.test.php` scripts pass, including cart coupon source contract, merchant trust sanitization, quantity AJAX, delivery progress, cart pricing, free gift pricing/lifecycle, sale discounts, settings persistence, and order/payment isolation.
- All **95** PHP files pass `php -l`.
- All **21** JavaScript files pass `node --check`.
- `git diff --check` passes.
- The existing Node Meta suite passes 28 tests.
- `tests/integration/offer-woocommerce.php` passes **129** checks against a fresh, isolated WordPress 7.1.3 / WooCommerce 11.2.1 database. It verifies the screenshot cart totals under the corrected offer calculation, decimal and zero-decimal rounding, quantity and repeated recalculation, gift eligibility, exact range boundaries, native coupon stacking/removal, session refresh, and that no order was created. No checkout or payment ran.
- A combined delivery/offer progress contract test confirms separate shipping and offer bars appear together inside the shared card; delivery is only represented when the selected WooCommerce shipping state supports the claim.

## Not executed / blocked

- Five Node browser suites (`buy-now-cart-browser`, `cart-add-performance-browser`, `cart-cross-sell-browser`, `cart-cross-sell-pointer-browser`, `cart-drawer-browser`) remain **BLOCKED** before page execution. The installed Chrome signature is invalid and launch aborts. Playwright-managed Chromium with sandbox enabled, Firefox, and WebKit also abort on this host. These are environment blocks, not assertion failures. Browser harnesses were made sandbox-preserving and now use isolated temporary profiles.
- No browser session was available for real UI interaction, screenshots, console capture, or the requested viewport matrix. No screenshot files were generated.
- Phase 10 fixed the historical fixture setup. Earlier runs loaded the current plugin class before including the archived pre-fix class, causing `Cannot redeclare class SuperWoo_Bundle_Offers`. The corrected isolated run deactivated SuperWoo before loading the old class and reproduced the original screenshot: base ₹3,745, total ₹3,035, savings ₹710. Current code returns ₹3,370.50 with ₹374.50 off. The baseline snapshot is now preserved at `tests/fixtures/offer-baseline/class-bundle-offers.php` (SHA-256 `553ada960742869d68c62376aedd2df6390169f56aab9a365ea108c554588685`). Its precise source commit is unknown, so use it as a reproducible regression fixture rather than a release-tagged historical artifact.
- No live WooCommerce coupon apply/remove, theme checkout, Blocks, HPOS, payment provider, or mobile safe-area test was run.

## Interpretation

Passing standalone tests and the isolated WooCommerce integration validate the covered cart, offer, coupon, gift, and order/payment isolation behavior. The historical comparison now confirms the previous double-discount result and the fixed result. Browser interaction and visual acceptance remain blocked. The integration deliberately did not create an order or enter checkout/payment.

## Phase 11 rerun — 2026-10-31

- Standalone PHP: **21 scripts passed**.
- Meta Node suite: **28 passed**.
- Sandbox-enabled Chromium browser suites: **5 passed**.
- PHP lint: **95 files passed**; JavaScript syntax: **21 files passed**.
- Staged and unstaged `git diff --check`: **passed**.
- The WP-CLI offer integration rerun was **stopped by its intended guard** because the available install was `superwoo_meta_test` / `http://localhost:8088`; it requires `superwoo_offer_audit` / `http://superwoo-offer-audit.test`. No fixture ran. The previous 129-check result remains Phase 10 evidence only.
- Local isolated site GET: home/product/cart 200; empty checkout 302 to cart; populated checkout 200 was observed in local browser smoke. No checkout submit/payment/order occurred.

See `PHASE_11_FINAL_QA.md` for current test details and release gate.

## Phase 12 final acceptance addendum — 2026-10-31

- Standalone PHP: **22 scripts passed**, including ten new static cart dialog accessibility guardrails.
- Guarded WooCommerce offer integration: **129 assertions passed earlier in Phase 12** against `superwoo_offer_audit` / `superwoo-offer-audit.test`; later rerun was blocked before the test body because `/tmp/superwoo-phase10/mysql.sock` did not accept a connection. The guard was not weakened and no alternate database was substituted.
- Meta Node suite: **28 passed**.
- PHP lint: **96 files passed**. JavaScript syntax: **passed** for all public JS and test JS. `git diff --check`: **passed**.
- Browser shopping journey: earlier Phase 12 run passed product/cart add, quantity up/down, coupon apply/remove, recommendation add, gift unlock, 10% offer totals, Store API parity, checkout navigation without submission, the eight requested widths, and landscape. Screenshots are in `evidence/phase-12/`.
- Later rerun of the five browser suites: **blocked before page execution**; every Chromium launch failed while initializing the macOS browser sandbox (`SIGTRAP`, `Input/output error (5)`). The sandbox was not disabled. No connected browser backend was available.
- The browser regression now asserts focus wrapping, Escape, and focus restoration. The new focus trap and reduced-motion CSS pass static guardrails, but their post-fix browser behavior is not verified due to the browser block.

See `PHASE_12_FINAL_ACCEPTANCE.md` for the complete evidence, coverage limits, and remaining release gates. The release candidate remains unpublished and `PRODUCTION_READY=NO`.

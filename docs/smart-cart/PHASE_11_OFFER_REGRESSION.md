# Phase 11 Offer Regression

**Date:** 2026-10-31  
**Result:** **Historical over-discount is a defect and remains fixed in local source.** No live promotional settings were changed.

## Policy used as specification

The merchant’s configured offer table and supplied cart screenshots describe mutually exclusive cart-value tiers: a free-product offer at ₹2,000–₹2,999, 10% at ₹3,000–₹7,000, 15% at ₹7,001–₹14,000, and 20% at ₹14,001 and above. The offer engine chooses one eligible rule by numeric priority. For the reported five-unit Hair Boost cart, the qualifying amount is five WooCommerce sale prices: `5 × ₹749 = ₹3,745`; the applicable tier is one 10% offer. No coupon was applied (merchant confirmed). The original sale reduction from ₹1,035 to ₹749 is product sale pricing, not a second offer discount.

Expected two-decimal result: **₹3,745.00 − ₹374.50 = ₹3,370.50**. A zero-decimal store rounds the final total according to WooCommerce’s configured precision (₹3,371 for this amount).

## Root cause and correction

The pre-fix offer callback removed an ineligible free-gift line from inside `woocommerce_before_calculate_totals`. WooCommerce recursively recalculated totals on removal. The nested pass discounted the mutable product price, then the outer pass discounted that already-discounted price again. At zero-decimal unit rounding the five-unit price sequence became `₹749 → ₹674 → ₹607`, producing ₹3,035 and ₹710 savings. This is not consistent with the configured single-tier rule.

The correction in `includes/class-bundle-offers.php` guards recursive gift reconciliation, bases eligibility and discounting on the saved pre-offer price, skips nested gift-sync callbacks, retains internal precision until WooCommerce totals rounding, and clears stale offer markers when hydrating a saved cart. Gift callbacks release their guard after exceptions.

## Regression coverage

- The prior real WooCommerce integration run passed 129 assertions on WP 7.1.3 / WC 11.2.1, including the exact five-unit screenshot case, 10/15/20% tiers, quantity up/down transitions, gifts, priority, scopes, coupon apply/remove, repeated/nested totals, stale session hydration, and no created order.
- Current standalone `tests/offer-lifecycle.test.php` passed 136 assertions; `tests/offer-sale-discount.test.php` passed the configured tiers, sale product, quantity change, session refresh, and zero-decimal rounding cases.
- The current Phase 11 attempt to rerun `tests/integration/offer-woocommerce.php` stopped at the test’s explicit guard: WP-CLI pointed at `superwoo_meta_test` / `http://localhost:8088`, not its required `superwoo_offer_audit` / `http://superwoo-offer-audit.test`. No fixture code ran and no data was changed. The prior 129-check result remains historical evidence, not a Phase 11 rerun.
- Browser observation on the isolated Phase 11 store showed five sale-price units at ₹3,745, a single ₹374.50 offer discount, and ₹3,370.50 total. A synthetic 5% WooCommerce coupon then independently stacked and the UI reported ₹543.02 aggregate savings and ₹3,201.98 total. The reported production cart had no coupon, so coupon stacking does not explain that report.

## Remaining pricing limits

- The configured tier limits have paise gaps (`₹2,999–₹3,000`, `₹7,000–₹7,001`, and `₹14,000–₹14,001`) because those exact values are in the merchant settings. The code respects the settings; changing them is a business-policy decision and was not done.
- Tax-inclusive/exclusive configurations, external tax extensions, shipping zones, and third-party discount plugins were not rerun in the Phase 11 environment. The cart summary/pricing standalone contract passed, but this is not an extension matrix.
- Coupon discounts are independent and may stack according to WooCommerce coupon rules. The summary discount can therefore exceed the tier percentage when a valid coupon is also applied.

# SuperWoo offer pricing audit and fix — 2026-10-30

## Finding and exact reproduction

The reported cart was reproduced with the unmodified offer module and real WooCommerce 11.2.1 on WordPress 7.1.3, PHP 8.5.11, in a new disposable `/tmp` site and socket-only MariaDB database. No production database, settings, checkout, order, or payment was used.

The test product has a regular price of INR 1,035 and sale price of INR 749. Four units cost INR 2,996 and qualify for the configured free gift. Raising the quantity to five crosses into the INR 3,000–7,000 10% tier and removes that gift.

WooCommerce's `WC_Cart` registers `calculate_totals()` on `woocommerce_cart_item_removed` and `woocommerce_add_to_cart`. SuperWoo was changing gift lines inside its `woocommerce_before_calculate_totals` callback. Removing the old gift therefore started a nested totals calculation: the inner callback applied 10%, then the outer callback used the already-discounted mutable product price and applied another 10%.

With zero-decimal unit rounding, the original sequence was `749 → 674 → 607`. Five units then cost **INR 3,035**, and the drawer correctly reported the resulting **INR 710** difference from INR 3,745. This is the screenshot's exact incorrect result. Changing only the displayed Discount row would conceal an actual pricing defect.

## Changes

- Guard offer calculation per cart against recursive gift callbacks, with `finally` cleanup after errors.
- Apply the percentage to an authoritative saved pre-offer base rather than a product object modified by a nested callback. Use that same base for tier qualification.
- Skip gift reconciliation callbacks while the owning offer calculation is already reconciling the cart.
- Retain WooCommerce internal unit precision until WooCommerce rounds line and cart totals. This prevents early whole-rupee rounding from magnifying savings across multiple units.
- Clear stale SuperWoo offer/currency pricing markers when hydrating a cart and invalidate its saved totals. The current catalog sale price and current rules are then reapplied.
- Release the gift-sync guard after callback exceptions so subsequent calculations can recover.

The fixed two-decimal cart is **INR 3,745 subtotal − INR 374.50 discount = INR 3,370.50 total**. A store configured for zero-decimal totals rounds that final total to **INR 3,371**. The sale-price reduction from INR 1,035 to INR 749 is not counted again as an offer.

The implementation change is confined to the offer module. No gateway implementation, checkout submission, payment authorization/capture, order status, or production configuration was modified for this fix.

## Wider audit and regression coverage

| Area | Result |
| --- | --- |
| Exact gift-to-10% screenshot transition | Reproduced before fix; correct after fix |
| 10%, 15%, 20% tiers, upward/downward quantity changes | Passed |
| Stale gifts with 10%, 15%, 20%, 100% discounts | Passed |
| Repeated and nested totals callbacks | Passed; no compounding |
| Multiple gifts and manually removed gifts | Passed; no duplicate/paid gifts |
| Existing sale prices and quantity multiplication | Passed |
| Whole-rupee and two-decimal stores | Passed; final rounding owned by WooCommerce |
| Product, variation, category scopes | Passed; unrelated items do not unlock scoped thresholds |
| Quantity/amount caps, priority, saved-order ties, disabled rules, legacy tiers | Passed |
| Old saved prices and cart totals | Passed on real WooCommerce hydration |
| Gift-add callback exception | Guards released; next calculation recovers |
| Coupon stacking/removal | Passed on real WooCommerce; coupon remains independent |
| Drawer summary, taxes, fees, shipping and currency | Existing summary regression suite passed |
| Browser cart quantity, failure/nonce recovery, recommendations and Buy Now fixtures | Passed in isolated Chrome profiles |

Two configuration/display considerations remain explicit:

1. The pictured ranges are exact numeric limits. Values between 2,999 and 3,000, between 7,000 and 7,001, and between 14,000 and 14,001 can fall between configured tiers when paise are used. Tests confirm these boundaries are honored. Merchant settings were not silently changed; continuous ranges require merchant-approved limits such as 2,999.99 / 7,000.99 / 14,000.99.
2. The drawer's Discount row aggregates cart savings, including a legitimate coupon or negative fee. A 10% coupon after the 10% offer legitimately gives a combined 19% reduction before rounding. The fix prevents repeated application of the offer; it does not suppress independent merchant discounts. The screenshot defect was reproduced without any coupon.

## Verification

- `php tests/offer-lifecycle.test.php`: **136 assertions passed**. Before the fix, its first screenshot assertion failed with expected 3,370.50 / actual 3,035.
- All **18** standalone PHP test files passed, including the broader order/payment isolation, free-gift, pricing, settings, webhook, recovery and updater suites.
- `tests/integration/offer-woocommerce.php`: **129 real WooCommerce checks passed** after the fix; separate baseline run reproduced the exact old cart. The script refuses execution unless WP-CLI is using the dedicated `superwoo_offer_audit` database and `http://superwoo-offer-audit.test` site.
- Node tests: **32 passed, 0 failed**, including all four browser test files. Chrome used isolated temporary profiles and the installed WordPress jQuery; no signed-in browser profile or production page was used.
- PHP lint: **89 files passed** (runtime and tests, including the plugin entry point and uninstall file). JavaScript syntax checks and `git diff --check` passed.
- No checkout was submitted, and no order, real payment, or fake Meta event was created.

Browser fixtures and the disposable WooCommerce runtime do not constitute a production compatibility test with every theme, tax setup, gateway, currency adapter, or third-party discount plugin.

## Release and repository state

The correction is packaged as **1.6.26**, superseding the immutable 1.6.25 release. The ZIP, plugin header, runtime version, readme stable tag, and update feed must agree; the public ZIP's size and SHA-256 are verified before switching the feed.

Publication completed to the existing Digtize update host. The package was uploaded first and verified over HTTPS, then the feed was replaced by an atomic FTP rename. The exact public feed and ZIP returned HTTP 200 with no redirects. The release is available for installation through WordPress; the production store's installed copy was not changed directly.

```text
RELEASE=1.6.26
PACKAGE=https://digtize.com/plugins/superwoo/SuperWoo-1.6.26.zip
PACKAGE_BYTES=312811
SHA256=bbb2538ddd1b2dc15ecf79baf62876edd2661830ec15a47d4efb1f449473d172
LIVE_FEED_AND_PACKAGE_VERIFIED=YES
PRODUCTION_ORDERS_OR_PAYMENTS_MODIFIED=NO
DISPOSABLE_DATABASE_STOPPED=YES
```

The repository still contains the previously resolved, uncommitted merge of the local recovery branch and remote cart branch. This task does not retry the previously rejected broad merge commit/push. The new source and tests are available in the local worktree; GitHub synchronization remains pending explicit approval for that merge.

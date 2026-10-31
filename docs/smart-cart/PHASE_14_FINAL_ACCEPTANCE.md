# SuperWoo Smart Cart — Phase 14 Final Acceptance

**Date:** 2026-10-31 (Asia/Kolkata)  
**Scope:** Local source, disposable WordPress/WooCommerce databases, loopback storefront, sandbox-enabled Chromium, and a local-only 1.6.32 candidate. No production configuration, customer data, order, payment, or published feed was changed.

## Result

Core cart and offer behavior is verified on the available disposable WooCommerce runtime, including a fresh 129-check integration run and a complete synthetic happy-path cart journey. A visible reward progress-label contrast defect was fixed and given a regression assertion. The next candidate, 1.6.32, installs and activates on the cloned disposable site, and its settings option survives upgrade from published 1.6.31.

The work does **not** close every acceptance request. The full screenshot-state matrix, manual screen-reader review, all merchant setting controls, virtual-keyboard/safe-area/RTL cases, full stock/variation journeys, supported-version/theme/extension matrix, and measured Core Web Vitals remain incomplete. Production readiness is **NO**. No candidate was deployed or published.

## Work completed

- Inspected current worktree, published version, updater, prior Phase 11/12 records, and available tests. No Phase 13 reports were present in this checkout.
- Verified the public feed reports 1.6.31. Downloaded its package; its public SHA-256 matched the local package byte-for-byte and its ZIP integrity check passed. The feed and package remain unsigned: the SHA-256 is only an integrity check against the same feed, not independent authenticity proof.
- Added `tests/qa/phase14-bootstrap.sh`. It validates the exact disposable database and site URL, checks MariaDB/WordPress/HTTP, keeps MariaDB network-disabled and HTTP loopback-only, captures a sandboxed Chromium smoke screenshot, and runs a browser smoke test. It completed with `QA_READY=YES`.
- Reran the guarded WooCommerce offer integration against `superwoo_offer_audit`: **129 checks passed**, with no checkout submit, order, or payment.
- Reran **22 standalone PHP test scripts** and **33 Node tests** (including five Chromium browser suites); all passed. PHP lint checked 96 repository PHP files; JavaScript syntax checked 21 files.
- Executed the local storefront journey: empty cart, eight portrait widths, landscape, add-to-cart, quantity up/down, coupon apply/remove, recommendation add, gift unlock, 10% offer, Store API parity, and navigation to populated checkout without submission.
- Ran a real loopback keyboard probe. Focus remained in the dialog, Tab wrapped, Escape closed and restored focus, and reduced-motion transition was `0.00001s`.
- Created a cloned disposable database/site, installed 1.6.31, upgraded to local candidate 1.6.32, verified WooCommerce and SuperWoo runtime classes, confirmed the settings serialization hash was unchanged, and confirmed an update transient is offered for a higher version. Test clone is under `/tmp/superwoo-phase14-release/`.
- Prepared a local 1.6.32 package and update metadata draft. No package was uploaded and the public `update.json` remains 1.6.31.

## Verified pricing case

The fresh integration suite and browser journey confirm the prior over-discount defect remains fixed. For five sale-price units at ₹749, the applicable 10% offer is calculated once: ₹3,745.00 subtotal, ₹374.50 offer discount, ₹3,370.50 before any additional coupon. The browser’s separate synthetic cart with recommendations produced ₹4,194.00 subtotal, ₹419.40 offer discount, and ₹3,774.60 total. Store API returned `377460` minor units, matching the displayed ₹3,774.60. A coupon is a separate WooCommerce discount and can stack under the store’s existing coupon rules.

## Fix made

Phase 14 changed `.superwoo-bundle-progress__labels`, current amount, separator, and target colors from white to dark text. This fixes the visibly low-contrast label over both the green progress fill and gray track. `tests/cart-delivery-progress.test.php` now checks the label color contract. Calculated contrast with the current default colors is 5.83:1 on the fill and 16.96:1 on the track.

## Test summary

| Area | Result | Evidence / limit |
|---|---|---|
| PHP standalone suite | PASS | 22 scripts, 0 failures |
| WooCommerce offer integration | PASS | 129 guarded checks on WP 7.1.4 / WC 11.2.1; no orders/payments |
| Node + browser suites | PASS | 33 tests, 0 failures; five browser suites |
| Real storefront cart journey | PASS for exercised path | Synthetic products; checkout navigation only |
| PHP lint | PASS | 96 files |
| JavaScript syntax | PASS | 21 files |
| QA bootstrap | PASS | `QA_READY=YES`; Chromium sandbox remained enabled |
| Browser console | PARTIAL | 11 `net::ERR_FAILED` resource messages corresponded to requests aborted by the explicit off-loopback network block; the harness did not separately enumerate those URLs |
| Cart setting persistence | PASS for covered persistence contract | 30 standalone assertions; cloned-site upgrade settings hash preserved |
| Full settings UI/asset matrix | PARTIAL | Not every control was toggled and reloaded in the admin UI |
| Visual matrix | PARTIAL | One-product view at eight portrait widths, one landscape view, and selected 390px states; see visual report |
| Accessibility | PARTIAL | Focus, Escape, focus restoration, reduced motion, progress semantics/source checks; no screen reader or RTL test |
| Performance | PARTIAL | Immediate-open mocked-latency browser test and asset byte counts; no Core Web Vitals or full network profile |
| Security | PARTIAL | Existing focused hardening tests/source review; not a comprehensive audit. Update metadata is unsigned |
| Compatibility | PARTIAL | One available WP/WC/PHP combination only |

## Required status

```text
SMART_CART_IMPLEMENTATION=PARTIAL
QA_INFRASTRUCTURE=PASS
BROWSER_RUNTIME=PASS — sandbox-enabled Chromium suites and local journey; in-app Browser connection unavailable
WOOCOMMERCE_INTEGRATION=PASS — 129 guarded checks
OFFER_REGRESSION=PASS — one-time tier discount and nested recalculation cases
BROWSER_FUNCTIONAL_QA=PARTIAL — core journey passes; negative/session/stock/variation matrix incomplete
VISUAL_ACCEPTANCE=PARTIAL
RESPONSIVE_QA=PARTIAL — eight portrait widths and one landscape; remaining device-state matrix incomplete
ACCESSIBILITY=PARTIAL
MERCHANT_SETTINGS=PARTIAL
REGRESSION_TESTS=PASS — 22 PHP scripts, 33 Node tests, 129 WooCommerce integration checks, lint/syntax/diff checks
PERFORMANCE_REVIEW=PARTIAL
SECURITY_REVIEW=PARTIAL — update feed is unsigned; no comprehensive security audit
NEXT_RELEASE_CANDIDATE=READY — local 1.6.32 candidate only, package and disposable upgrade verified
PRODUCTION_READY=NO
PRODUCTION_CHANGES=NONE
PRODUCTION_UPDATE_FEED=UNCHANGED — 1.6.31
REAL_ORDERS_OR_PAYMENTS=NONE
```

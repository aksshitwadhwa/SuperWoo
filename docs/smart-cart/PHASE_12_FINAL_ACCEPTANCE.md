# SuperWoo Smart Cart — Phase 12 Final Acceptance

**Date:** 2026-10-31  
**Scope:** Local source, disposable WooCommerce fixtures, and loopback-only browser journey. No production access, deployment, order, or payment.

## Decision

The Smart Cart is **partially accepted as a local release candidate, but not production-ready**. The core browser journey, exact historical offer case, WooCommerce offer integration, standalone regressions, and static checks have evidence. Two accessibility defects found during this phase were fixed in source and given regression coverage, but the browser could not be relaunched afterward to verify those fixes behaviorally. The current disposable MariaDB endpoint also became unavailable, so the successful 129-check integration run could not be repeated at the final checkpoint.

No production version was changed and no update was published during Phase 12. At that phase stop point, local source reported `1.6.26`; the later release-preparation task locally bumped the source/readme to `1.6.27` and created an unsigned candidate outside the repository. It remains unpublished and blocked by the missing production signing trust configuration.

## Completed verification

### WooCommerce integration and offers

- The guarded integration test ran in the isolated `superwoo_offer_audit` database/site and passed **129 real WooCommerce checks**. The identity guard was not changed or bypassed.
- Historical cart: five units × ₹749 = ₹3,745.00, one configured 10% reduction = ₹374.50, total ₹3,370.50. Repeated/nested recalculations, quantity transitions, exact tier boundaries, gift reconciliation, coupon add/remove, old-session hydration, and zero-order assertion were included in the suite.
- During the browser journey, a paid cart subtotal of ₹4,194.00 got one ₹419.40 discount and total ₹3,774.60. The cart Store API returned total `377460` minor units, matching the displayed amount.
- No checkout was submitted and no order or payment was created.
- A subsequent attempt to rerun the integration suite failed before its guard/test body because WordPress could not connect to `/tmp/superwoo-phase10/mysql.sock`. The MariaDB socket file exists, but the server was not responding. No other database was substituted.

### Browser journey and visual evidence

The earlier Phase 12 run used Chromium 147.0.7727.15 with sandboxing enabled, a loopback-only site, and routes blocked to all non-loopback network destinations. It passed:

- Product load and add-to-cart, drawer open, correct product details and representative synthetic image.
- Quantity increase/decrease, synthetic coupon apply/remove, recommendation add, gift unlock, and 10% offer transition.
- Store API parity and navigation to populated checkout without submission.
- One-product captures at 1440, 1280, 1024, 768, 430, 390, 375, and 320px, plus landscape 844×390px.

Screenshots are under `docs/smart-cart/evidence/phase-12/`. The artwork is a locally generated geometric placeholder, not product photography. Visual review found legible drawer content and no evident horizontal overflow in the inspected 320px screenshot. The design was not pixel-compared with the reference; several required transient/orientation states are not captured.

Synthetic fixtures also include a long-title product, a variable product with three variations, and an out-of-stock product. Those product selection/availability interactions were not exercised in a browser.

At a later checkpoint, standalone browser suites were rerun and all five failed **before page execution**. Chromium exited during macOS sandbox initialization with `FATAL:content/browser/sandbox_parameters_mac.mm:67 ... Input/output error (5)` / `SIGTRAP`. The in-app/connected browser discovery returned no available browser. The sandbox was not disabled to force a run.

### Accessibility correction

The Phase 12 probe initially found focus entering the dialog and returning to its opener on Escape, but Tab could leave the open cart. `prefers-reduced-motion: reduce` also left the drawer transition at 0.24 seconds. Source now traps Tab/Shift+Tab inside the open dialog, ignores hidden controls, retains focus when there are no tabbable controls, shortens cart/mobile UI transitions for reduced-motion users, and gives each cart quantity input a product-specific accessible name. A new 10-check static guardrail test passes, and the real browser harness now covers focus wrapping, Escape, and restoration. Because the browser launch later became unavailable, the changed focus trap and reduced-motion behavior are **not behaviorally verified yet**.

## Tests and checks

| Check | Result | Notes |
|---|---|---|
| Standalone PHP | PASS — 22 scripts | Includes pricing, recovery, security, settings, and ten accessibility source guardrails |
| WooCommerce offer integration | PASS — 129 checks in Phase 12; final rerun blocked | Earlier successful run used guarded disposable DB; latest rerun could not connect to its MariaDB socket |
| Meta Node suite | PASS — 28 tests | `node --test tests/meta-tracking.test.js` |
| Browser suites | BLOCKED — 0 passed, 5 failed before page execution | macOS Chromium sandbox setup; not assertion failures |
| PHP lint | PASS — 96 files | PHP 8.5.11 CLI |
| JavaScript syntax | PASS | All `public/js/*.js` and `tests/*.js` |
| `git diff --check` | PASS | At final source change checkpoint |

## Settings, responsiveness, security, and performance limits

- Settings persistence and safe defaults have standalone PHP coverage. A full browser settings save/reload matrix, every module toggle, and proof that disabled modules omit all frontend assets were not rerun in this phase.
- Responsive screenshots cover all eight requested widths for the single-product cart state and landscape 844×390. Combined reward/gift/coupon states were captured at 390px only. Safe-area, virtual keyboard, floating navigation overlap, RTL, and screen-reader behavior were not tested.
- Existing offer/security reviews remain focused source and contract checks; this is not a full plugin security audit. WooCommerce remains authoritative for cart pricing, shipping, taxes, fees, coupons, checkout, and payment processing. No payment/order hooks were added in Phase 12.
- Drawer behavior was previously shown to open before a mocked 400ms cart response, and cart update paths avoid a redundant Store API count request. No fresh performance profile, transfer-size measurement, or Core Web Vitals run was performed in Phase 12.
- Runtime compatibility coverage is limited to the documented disposable WordPress/WooCommerce environment and earlier integration environment (WP 7.1.x / WC 11.2.1). This is not a supported-version matrix.

## Machine-readable status

```text
SMART_CART_IMPLEMENTATION=PARTIAL
WOOCOMMERCE_INTEGRATION=PARTIAL — 129 checks passed in Phase 12; latest rerun blocked by unavailable disposable MariaDB
OFFER_REGRESSION=PARTIAL — one-time discount/tier cases pass; tax and shipping combination coverage remains incomplete
BROWSER_FUNCTIONAL_QA=PARTIAL — captured journey passed; removal/variation/OOS coverage and browser rerun remain incomplete
VISUAL_ACCEPTANCE=PARTIAL
RESPONSIVE_QA=PARTIAL
ACCESSIBILITY=PARTIAL — focus trap and reduced-motion fixes implemented; live browser recheck blocked
MERCHANT_SETTINGS=PARTIAL
REGRESSION_TESTS=PARTIAL — non-browser checks pass; browser suite execution blocked
RELEASE_CANDIDATE=NOT_READY
PRODUCTION_READY=NO
PRODUCTION_DEPLOYMENT=NONE
REAL_ORDERS_OR_PAYMENTS=NONE
```

## Remaining acceptance gates

1. Restore the isolated MariaDB/socket service and rerun the guarded integration test.
2. Restore a sandboxed browser and rerun all five browser suites plus the updated focus/reduced-motion checks.
3. Capture loading/error, shipping-only/unlocked, RTL, reduced-motion, safe-area, and keyboard states; inspect the full viewport matrix and compare against the approved reference.
4. Complete screen-reader and color-contrast review, settings save/reload/disabled-asset checks, variable/out-of-stock browser flows, and supported-version/theme/tax/shipping matrix.

Phase 12 stops here. No deployment or update publication is authorized by this report.

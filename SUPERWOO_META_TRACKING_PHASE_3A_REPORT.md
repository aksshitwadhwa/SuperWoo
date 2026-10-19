# SuperWoo Meta Tracking — Phase 3A Report

**Date:** 2026-10-13  
**Scope:** Local implementation of the disabled-by-default tracking foundation. No production deployment, payment, order, checkout submission, or configuration change was performed.

## Outcome

Phase 3A adds an isolated, read-only foundation for future Meta tracking. It does not initialize Meta Pixel, dispatch events, add CAPI, or register payment/order hooks. The feature is off for new and existing installations. Even when explicitly enabled, event dispatch remains hard-disabled and Razorpay Magic Checkout remains unsupported.

## Architecture and files

Added:

- `includes/class-meta-tracking.php` — bootstrap and checkout-only script enqueue, requiring both the master flag and inactive kill switch.
- `includes/meta-tracking/interface-observer.php` — read-only observer contract with start/stop only.
- `includes/meta-tracking/class-consent.php` — `granted`, `denied`, and `unknown` consent states. A documented WordPress filter is the integration point; invalid or absent values resolve to `unknown` and fail closed.
- `includes/meta-tracking/class-event-normalizer.php` — minimal event envelope limited to event name, event ID, timestamp, source, and checkout type. Unrecognized fields, including PII and payment data, are discarded.
- `public/js/meta-checkout-observer.js` — Classic and Blocks UI observers. They read checkout selection transitions and expose checkout type only; they never dispatch an event or change checkout state.
- `tests/meta-tracking.test.js` — runnable Node tests for flags, consent, observers, Pixel detection, Razorpay/unsupported checkout no-op behavior, and static payment-boundary assertions.
- `SUPERWOO_META_TRACKING_PHASE_3A_REPORT.md` — this report.

Modified:

- `superwoo.php` — loads the isolated module.
- `includes/functions.php` — adds false defaults for the feature, emergency switch, and optional diagnostics. Existing saved values continue to be merged with defaults.
- `includes/class-plugin.php` — registers the module’s frontend enqueue hook and saves the new settings through the existing capability- and nonce-protected settings form.
- `admin/views/settings-page.php` — adds the Meta Tracking settings tab and Phase 3A status details.

## Settings and behavior

- `enable_meta_tracking`: defaults to `false`.
- `meta_tracking_kill_switch`: defaults to `false`; when active, it prevents the observer asset from loading.
- `meta_tracking_diagnostics`: defaults to `false`; optional console diagnostics contain only booleans and fixed status values.
- No observer script is enqueued when the feature is off, the kill switch is active, or the request is not a checkout page (including the order-received page).
- Unknown or denied consent attaches no observer. No consent provider is bundled. WhatsApp consent is not used.
- Even when consent is granted and the foundation is enabled, `eventDispatchAllowed` is always `false` in Phase 3A.
- Razorpay status is `unsupported`; the code does not inspect or intercept its checkout UI.
- No Meta CAPI token field or storage was added.

## Diagnostics and data boundary

Optional browser diagnostics report whether a callable `fbq` runtime exists, consent state, checkout adapter state, dispatch state, kill-switch state, and the fixed Razorpay support status. SuperWoo never calls `fbq`, initializes a Pixel, writes diagnostics to persistent logs, or reads cookies, order keys, customer details, payment details, or full checkout URLs.

The normalized model is allow-listed. Classic and Blocks observers expose only `checkoutType`; the Blocks adapter uses only `wp.data.select('wc/store/payment').getActivePaymentMethod()` and `wp.data.subscribe()`. No observer method can submit checkout, create an order, change a gateway, or alter totals.

## Verification

`node --test tests/meta-tracking.test.js` passed all 11 tests. `node --check` passed for the observer and test file. `git diff --check` passed.

The tests cover disabled and killed states, denied/unknown consent, malformed/missing Pixel runtime, Classic and Blocks read-only observers, unsupported checkout/Razorpay no-op behavior, disabled event dispatch, settings defaults/save wiring, and absence of payment/order processing integrations in the new module.

**Payment invariance:** the new module has no WooCommerce payment, cart, order, gateway, or pricing hooks and no network dispatch. The source-level boundary checks passed. Actual WooCommerce integration tests comparing live cart totals, gateway lists, checkout payloads, order creation, payment results, or redirects were not run: this checkout has no WordPress/WooCommerce test harness, and PHP is unavailable in the current shell. Those runtime invariants remain unverified and must be exercised in a local/staging WordPress test environment before any later rollout.

**Existing SuperWoo tests:** no pre-existing automated test suite was present. No PHP lint or WordPress settings round-trip test could be executed in this environment. The settings check is static and confirms the new keys are included in defaults and the existing save form while existing settings remain represented.

## Remaining blockers

- Phase 2 live checkout, Pixel ownership, event inventory, and consent provider remain unverified, as documented in the Phase 2B report.
- No supported consent-provider adapter is configured; consent therefore defaults to unknown and fails closed.
- No WordPress/PHP integration harness was available to exercise actual settings persistence or WooCommerce payment invariance.
- Razorpay Magic Checkout has no supported selection adapter in this phase.

## Required status

```text
PHASE_3A_STATUS=COMPLETE — local foundation implemented; runtime WordPress verification remains outstanding
LOCAL_ONLY=YES
PRODUCTION_CHANGES=NONE

META_TRACKING_DEFAULT=DISABLED
ADDPAYMENTINFO_DISPATCH=DISABLED
CAPI_IMPLEMENTED=NO

PIXEL_INITIALIZATION_ADDED=NO
HARDCODED_PIXEL_ID=NO

RAZORPAY_CODE_MODIFIED=NO
RAZORPAY_OBSERVER_IMPLEMENTED=NO

PAYMENT_GATEWAY_CODE_MODIFIED=NO
PAYMENT_PROCESSING_HOOKS_ADDED=NO
CHECKOUT_SUBMISSION_MODIFIED=NO
CART_TOTAL_LOGIC_MODIFIED=NO
ORDER_PROCESSING_MODIFIED=NO

CONSENT_UNKNOWN_FAILS_CLOSED=YES
KILL_SWITCH_TESTED=YES — static gate and browser no-op tests

PAYMENT_INVARIANCE_TESTS=PASS for source boundary and observer behavior; WooCommerce runtime invariance UNVERIFIED
FAULT_INJECTION_TESTS=PASS — 11 automated Node tests
EXISTING_SUPERWOO_TESTS=NONE PRESENT; PHP/WordPress test runtime unavailable

PHASE_3B_READY=NO — live ownership/consent and WordPress/WooCommerce runtime validation remain blockers
```

**Stop point:** Phase 3A only. No AddPaymentInfo dispatch or deployment was performed.

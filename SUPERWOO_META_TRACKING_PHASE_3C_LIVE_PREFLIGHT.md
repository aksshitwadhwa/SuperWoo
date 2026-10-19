# SuperWoo Meta Tracking — Phase 3C Live Preflight

**Date:** 2026-10-13  
**Scope:** Read-only public MODU storefront preflight and public Razorpay documentation review. This phase did not log in to WordPress, inspect private configuration, create an order, submit checkout, send a Meta event, or change production.

## Decision

**Preflight is partial and the controlled live test is not cleared.** A passive fetch of the public [MODU homepage](https://modu.co.in/) exposed a Google Tag Manager iframe reference with container ID `GTM-KB25XZTB` and Facebook-linked image references, including one labeled `fbpx`. This does not establish which tags or pixels execute, who owns them, whether `AddPaymentInfo` already fires, or whether consent gates them.

The public cart, checkout, and WordPress REST endpoints could not be inspected with the available page-fetch tool. The connected interactive browser inventory was retried for this operator-assisted phase and again returned empty (`[]`); no authenticated WordPress admin or Meta Events Manager access was available. Checkout type, installed versions, gateways, Razorpay configuration, Meta ownership, consent, and checkout totals therefore remain unknown. No production test order or payment is justified by the evidence gathered so far.

## Findings

### Public storefront

The homepage fetch showed the MODU storefront and referenced GTM container `GTM-KB25XZTB`, plus Facebook-linked images. These are observations about public homepage markup only. They do not verify Pixel initialization or event delivery. The earlier Phase 2B report lists three candidate Pixel IDs as user-supplied; none has been confirmed on the live site, and this report does not select one.

Direct fetches of `https://modu.co.in/cart/`, `https://modu.co.in/checkout/`, `https://modu.co.in/wp-json/`, and the checkout page REST lookup were inaccessible through this tool. No interactive navigation, browser developer tools, network log, or storefront storage was available. Accordingly, checkout and payment behavior were not exercised.

### Razorpay public integration surface

The public [Razorpay WooCommerce plugin repository](https://github.com/razorpay/razorpay-woocommerce) documents the WooCommerce payment extension and Magic Checkout-related releases. The installed MODU plugin source/version was unavailable, so its enabled settings and runtime behavior were not verified. No supported external pre-payment payment-selection callback for the installed WooCommerce integration was verified in this preflight.

Razorpay’s public [Magic Checkout Shopify configuration documentation](https://github.com/razorpay/markdown-docs/blob/master/payments/magic-checkout/shopify/configuration.md) describes an “Add payment info” event when a customer selects a payment option in the Shopify integration. That documentation is Shopify-specific; it does not establish a public callback or event contract for a WooCommerce plugin or expose a signal SuperWoo can safely observe. Do not treat it as WooCommerce support.

### Test-order safety

No live gateway list or configuration was available, so no offline method is confirmed. Do not enable COD or BACS globally for this preflight. If a later authorized test is approved, first confirm an already-enabled offline gateway from read-only admin evidence; use only that method, with a designated test cart/order and merchant-provided fulfillment suppression. If no such method is already enabled, keep the test blocked until an isolated staging path is available. A real card/UPI charge is not required and remains out of scope.

## Required status

```text
LIVE_PREFLIGHT_STATUS=PARTIAL — public homepage and public docs reviewed; interactive/admin/Meta discovery unavailable

WORDPRESS_VERSION=UNKNOWN
WOOCOMMERCE_VERSION=UNKNOWN
SUPERWOO_PRODUCTION_VERSION=UNKNOWN — local source is 1.4.2; deployment not verified

LIVE_CHECKOUT_TYPE=UNKNOWN
CLASSIC_CHECKOUT_PRESENT=UNKNOWN
BLOCKS_CHECKOUT_PRESENT=UNKNOWN

RAZORPAY_ACTIVE=UNKNOWN
RAZORPAY_MAGIC_CHECKOUT_ACTIVE=UNKNOWN
STANDARD_WC_PAYMENT_INPUT_AVAILABLE=UNKNOWN
PAYMENT_SELECTION_OWNER=UNKNOWN

PIXELS_DETECTED=UNVERIFIED — homepage references GTM and Facebook-linked images; no Pixel ID confirmed
PIXEL_INITIALIZATION_SOURCES=GTM container reference GTM-KB25XZTB observed; tag contents/owners unknown; Facebook-linked image references observed
CANONICAL_PIXEL_CONFIRMED=NO

EXISTING_ADDPAYMENTINFO=UNKNOWN
EXISTING_ADDPAYMENTINFO_SOURCE=UNKNOWN
DUPLICATE_EVENT_RISK=HIGH/UNKNOWN — live event sources and ownership were not observable

CONSENT_PROVIDER=UNKNOWN
CONSENT_SIGNAL_AVAILABLE=UNKNOWN
SUPERWOO_CONSENT_ADAPTER_POSSIBLE=UNKNOWN — no live consent API or signal inspected

SAFE_TEST_ORDER_METHOD=NONE VERIFIED — only use an already-enabled offline method after admin confirmation; do not enable one globally
REAL_FINANCIAL_CHARGE_REQUIRED=NO

RAZORPAY_PUBLIC_PAYMENT_SELECTION_SIGNAL=NONE VERIFIED for WooCommerce; Shopify-specific Meta event documentation is not a WooCommerce callback contract

PHASE_3B_DIRECTLY_TESTABLE_ON_LIVE=NO — checkout type, gateway UI, canonical Pixel, existing event ownership, and consent are unverified
PRODUCTION_DEPLOYMENT_PERFORMED=NO
PRODUCTION_SETTINGS_CHANGED=NO
REAL_META_EVENT_SENT=NO
REAL_FINANCIAL_PAYMENT_ATTEMPTED=NO

NEXT_RECOMMENDED_TEST=Obtain read-only authenticated browser/admin and Meta Events Manager access; verify checkout architecture, installed versions, active gateways, consent signal, Pixel owner, and existing AddPaymentInfo before considering an isolated offline test
```

## Stop conditions

Do not deploy Phase 3B or run a live test order yet. The checkout architecture is unknown, the canonical Pixel is unconfirmed, existing `AddPaymentInfo` ownership is unknown, consent cannot be verified, and no safe offline gateway has been confirmed. Razorpay selection remains unsupported without a documented WooCommerce-facing signal. These are explicit preflight blockers, not permission to infer or work around production behavior.

```text
PRODUCTION_FILES_CHANGED=NO
PRODUCTION_DATABASE_CHANGED=NO
PRODUCTION_SETTINGS_CHANGED=NO
PAYMENT_SETTINGS_CHANGED=NO
CHECKOUT_SUBMITTED=NO
ORDER_CREATED=NO
PAYMENT_AUTHORIZATION_TESTED=NO
PAYMENT_CAPTURE_TESTED=NO
```

## Phase 3C-A authenticated discovery — operator evidence pending

No authenticated admin, browser, or Meta Events Manager evidence has been supplied for this addendum. The public preflight findings above remain unchanged; every live runtime/admin value below remains unverified. This section is a collection checklist, not a claim that Codex inspected the authenticated store.

```text
AUTHENTICATED_DISCOVERY_STATUS=BLOCKED — no connected signed-in browser or authenticated admin/Meta session is available to this agent

WORDPRESS_VERSION=UNKNOWN
WOOCOMMERCE_VERSION=UNKNOWN
SUPERWOO_PRODUCTION_VERSION=UNKNOWN

LIVE_CHECKOUT_TYPE=UNKNOWN
STANDARD_WC_PAYMENT_INPUT_COUNT=UNKNOWN
PAYMENT_SELECTION_OWNER=UNKNOWN

RAZORPAY_ACTIVE=UNKNOWN
RAZORPAY_PLUGIN_VERSION=UNKNOWN
RAZORPAY_MAGIC_CHECKOUT_ACTIVE=UNKNOWN

FBQ_RUNTIME_PRESENT=UNKNOWN
PIXELS_CONFIRMED=UNKNOWN
CANONICAL_PIXEL_ID=UNKNOWN
CANONICAL_PIXEL_CONFIRMED=NO

EXISTING_ADDPAYMENTINFO=UNKNOWN
EXISTING_ADDPAYMENTINFO_SOURCE=UNKNOWN
DUPLICATE_EVENT_RISK=UNKNOWN

CONSENT_PROVIDER=UNKNOWN
CONSENT_SIGNAL_AVAILABLE=UNKNOWN
SUPERWOO_CONSENT_ADAPTER_POSSIBLE=UNKNOWN

LIVE_PAYMENT_METHODS=UNKNOWN
SAFE_EXISTING_TEST_METHOD=UNKNOWN

RAZORPAY_PUBLIC_PAYMENT_SELECTION_SIGNAL=NONE_VERIFIED

PHASE_3B_LIVE_COMPATIBILITY=UNDETERMINED
CONTROLLED_LIVE_TEST_READY=NO — required evidence is missing

PRODUCTION_CHANGES=NONE
PAYMENT_SETTINGS_CHANGED=NO
REAL_PAYMENT_ATTEMPTED=NO
SUPERWOO_META_EVENT_SENT=NO
```

### Operator checklist

Please return screenshots or copied values for these read-only checks. Redact customer details, API keys, webhook secrets, and any payment credentials. Do not change settings, submit checkout, or make a payment.

1. **Versions and deployment:** In WordPress, capture Dashboard → Updates/Site Health, Plugins (WooCommerce, SuperWoo, Razorpay, consent plugins), and Appearance → Themes. Include the SuperWoo version and whether its deployed files/settings visibly include Meta Tracking. Do not assume the local source version matches production.
2. **Checkout and gateways:** Capture WooCommerce → Settings → Advanced showing the assigned Checkout page; inspect Pages → Checkout in the editor and identify shortcode, Checkout Block, or custom builder. Open the real checkout with a normal cart, but do not submit. In DevTools Console run:

   ```js
   document.querySelectorAll('input[name="payment_method"]').length
   Array.from(document.querySelectorAll('input[name="payment_method"]'), e => ({method: e.value, checked: e.checked, visible: !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length), context: e.closest('li')?.innerText.trim()}))
   ```

   Also capture the currently enabled methods from WooCommerce → Settings → Payments and the Razorpay settings page. Redact credentials. Note if selection is native WooCommerce UI, Razorpay UI, a modal, or an iframe.
3. **Pixel and existing events:** On the checkout page run `typeof window.fbq`. In DevTools Network filter `facebook`, `fbevents`, or `/tr`; share Pixel IDs and request initiators only, with other query parameters/customer data redacted. Capture whether `InitiateCheckout`, `AddPaymentInfo`, or `Purchase` appears naturally in Meta Events Manager/Test Events, including event source and dataset. Do not create or fabricate an event. If ordinary payment-method selection is needed to observe an existing event, do not submit checkout; report that selection was made and what appeared.
4. **Canonical dataset and consent:** From Meta Events Manager, provide the intended MODU dataset/Pixel ID and evidence it matches the checkout runtime ID. From the consent banner/provider, provide provider name, marketing category, current consent state, and the documented JS/API integration signal if one exists. Do not infer consent from `fbq` or unrelated cookies; do not share access tokens.
5. **Razorpay signal and safe method:** Provide the installed Razorpay version and any documented WooCommerce-facing payment-selection callback/hook, if its official docs or installed source name one. Also report which offline methods (COD/BACS/Cheque) are already enabled. Do not enable one for this discovery.

Label observations by source (`OPERATOR_AUTHENTICATED_ADMIN`, `OPERATOR_BROWSER_RUNTIME`, or `META_EVENTS_MANAGER`). Once this evidence is supplied, the report can be updated and the live-test gate recalculated. Until then, do not deploy Phase 3B or create a test order.

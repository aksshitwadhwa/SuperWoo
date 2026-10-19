# SuperWoo Meta Tracking — Phase 2B Runtime Report

**Date:** 2026-10-13  
**Scope:** Read-only MODU runtime verification. After approval to inspect production, the public homepage was fetched for passive inspection. A connected browser with developer tools was unavailable, so rendered DOM, browser network activity, cookies/storage, checkout/cart UI, Meta Events Manager, and admin screens could not be inspected. This report distinguishes public page observations from unverified runtime/admin facts.

## Decision

**Public live-page inspection is partial; Phase 3 is not ready.** The public MODU homepage was accessible through a read-only web page fetch. It exposes a Google Tag Manager iframe URL containing container ID `GTM-KB25XZTB` and an image labeled `fbpx` linking to Facebook, but the fetched page view does not expose the actual Facebook request parameters, Pixel ID, event stream, or ownership. The three Pixel IDs listed in the request remain user-supplied candidates; none was verified. No checkout was submitted, no order/payment flow started, no intentional event was generated, and no settings were changed.

The local repository confirms SuperWoo `1.4.2`, a custom cart drawer, and a conditional handoff to the Razorpay Magic Checkout mini-cart button. It does not identify the target store’s installed versions, checkout implementation, Pixel ownership, consent provider, or payment baseline. Razorpay selection remains unsupported absent a documented external signal.

## 1. Environment inventory

| Item | Repository evidence | MODU staging runtime |
|---|---|---|
| WordPress | SuperWoo requires at least `6.2` | Unknown |
| WooCommerce | SuperWoo requires at least `8.0` | Unknown |
| SuperWoo | Source version `1.4.2`, commit `edcd849` | Installed version unknown |
| Theme | Not included in plugin source | Unknown |
| Razorpay plugin | Not bundled; SuperWoo checks Razorpay functions | Version unknown |
| Meta for WooCommerce | Not bundled | Version unknown |
| WP Rocket | Not bundled | Version unknown |
| Consent plugin/provider | No general consent provider in SuperWoo | Unknown |
| Cart | SuperWoo can render its custom AJAX drawer if enabled | Runtime status and native cart coexistence unknown |
| Checkout | SuperWoo links to Woo checkout or conditionally renders Razorpay button `#btn-1cc-mini-cart` | Classic/Blocks/custom/Magic Checkout path unknown |
| Thank You / Order Received | No target site template in plugin repository | Unknown |
| Other gateways / express methods | Not knowable from this repository | Unknown |

## 2. Checkout architecture

Repository observations:

- `includes/class-cart-drawer.php` owns SuperWoo drawer rendering, cart fragments, and cart AJAX operations; it skips drawer rendering on checkout pages.
- `includes/functions.php::superwoo_razorpay_magic_checkout_available()` checks `is1ccEnabled()` and `isMiniCartCheckoutEnabled()` plus test-mode conditions.
- `includes/functions.php::superwoo_cart_primary_button_html()` renders the WooCommerce checkout URL unless those checks pass, when it renders `#btn-1cc-mini-cart`.
- `public/js/cart-drawer.js` leaves Buy Now/Razorpay controls to their owning integration.

These source paths establish a custom cart drawer and conditional handoff only. They do not establish the page’s actual checkout implementation or that Magic Checkout is enabled on staging.

**CHECKOUT_TYPE:** `UNKNOWN` on staging. Possible paths cannot be selected without visiting the intended staging pages.

## 3. Pixel inventory

No Meta Pixel initializer or Pixel ID was found in SuperWoo runtime source. A repository search cannot inventory theme code, active plugins, GTM, browser scripts, or server-side integrations.

### Live public homepage observation

The read-only page fetch for [modu.co.in](https://modu.co.in/) exposed a Google Tag Manager iframe resolving to `https://www.googletagmanager.com/ns.html?id=GTM-KB25XZTB` and a footer image labeled `fbpx` with a Facebook-domain link. This is evidence that the homepage references a GTM container and a Facebook-related image request; it does not establish which GTM tags fire, whether the image is an actual Meta Pixel request, any Pixel ID, source owner, consent state, browser/server events, or duplication. I did not open the Facebook image link because it may itself trigger a tracking request.

The brief names these IDs as previously observed, but none could be inspected in this session:

| Candidate Pixel/Dataset ID | Runtime-observed? | Owner | Pages/events |
|---|---|---|---|
| `1508382043627612` | No | Unknown | Unknown |
| `1506096780402858` | No | Unknown | Unknown |
| `1943849039517761` | No | Unknown | Unknown |

No claim is made that these are all the IDs or that any one is canonical. Do not infer primary ownership from order in source. Meta for WooCommerce, Razorpay Facebook Analytics, GTM, theme snippets, and other plugin ownership remain unverified.

## 4. Existing event inventory

Browser/Server, Pixel ID, event ID, parameters, owner, and duplicate risk require rendered-page/network or Meta Test Events access. Browser tools were unavailable. “No local SuperWoo sender found” does not mean the event is absent on the store.

| Event | Pixel/Dataset | Browser/Server | event_id | Owner | Duplicate risk |
|---|---|---|---|---|---|
| PageView | Unknown | Unknown | Unknown | Unknown | Unknown |
| ViewContent | Unknown | Unknown | Unknown | Unknown | Unknown |
| AddToCart | Unknown | Unknown | Unknown | Unknown | Unknown |
| InitiateCheckout | Unknown | Unknown | Unknown | Unknown | Unknown |
| AddPaymentInfo | Unknown | Unknown | Unknown | Unknown | Unknown |
| Purchase | Unknown | Unknown | Unknown | Unknown | Unknown |

No synthetic or fake events were generated. `magic_checkout_requested`, `saved_address_selected`, `payment_method_selected`, `pay_now_clicked`, and `payment_successful` were not found in SuperWoo source; their existence or behavior in the deployed Razorpay integration is unknown.

## 5. Meta for WooCommerce, CAPI, and Purchase

- Meta for WooCommerce installation/version and its Connected Business, Pixel/Dataset, catalog, CAPI, and Purchase settings are unknown. No settings were opened or changed.
- The SuperWoo repository contains no Pixel/CAPI sender and no Purchase event implementation.
- The deployed Purchase owner, browser/server delivery, matching event IDs, and deduplication status are unknown. SuperWoo must not own or modify Purchase in its initial tracking release.
- The canonical dataset cannot be established safely from this local checkout or the candidate IDs alone.

## 6. AddPaymentInfo status

No AddPaymentInfo sender was found in SuperWoo runtime source. Because the site’s rendered funnel and Meta Test Events could not be observed, the deployed event is **unknown**, not confirmed absent. Its trigger, Pixel, browser/server source, event ID, owner, parameters, and duplication are unknown.

## 7. Razorpay boundary

The local code only checks feature availability and renders the expected button; it does not contain a Razorpay payment-selection event adapter or payment callback. Runtime Magic Checkout status, Facebook Analytics toggle, test-mode configuration, and custom event stream were not accessible. No Razorpay code or private runtime was inspected.

Do not infer AddPaymentInfo from a button click, payment success, or webhook, and do not use private iframe messages, `postMessage` interception, or monkey-patching. Until a documented supported pre-payment method-selection interface is verified:

```text
RAZORPAY_ADDPAYMENTINFO_SUPPORT=BLOCKED
```

## 8. Consent

| Field | Staging finding |
|---|---|
| Consent provider | Unknown |
| Marketing consent granted/denied/undecided detectable | Unknown |
| Marketing category | Unknown |
| Programmatic API/event | Unknown |
| Default state / Pixel ordering | Unknown |

SuperWoo’s WhatsApp order-updates consent is purpose-specific and is not advertising consent. It was not reused or changed. No consent configuration was inspected or changed.

## 9. Payment baseline

No staging cart/checkout was available. No test or live transaction was performed. Therefore prices and payment behavior below remain uncaptured:

| Baseline item | Value |
|---|---|
| Product prices | Unknown |
| Subtotal | Unknown |
| Discount | Unknown |
| Shipping | Unknown |
| Tax | Unknown |
| Fees | Unknown |
| Total | Unknown |
| Available/selected payment methods | Unknown |
| Order creation/result/status | Unknown |
| Success/failure behavior | Unknown |
| Redirect / Thank You page | Unknown |

`PAYMENT_TEST_MODE_ONLY=YES` in the sense that no live payment was made; no test-mode payment was made either. Baseline is not captured.

## 10. Phase 3 path recommendation

**Current decision: neither Classic nor Blocks is runtime-cleared.** The source-level Phase 1 analysis suggests a non-blocking read-only observer could be evaluated for either standard WooCommerce checkout, but staging must first establish the active path, consent behavior, canonical Pixel, and existing AddPaymentInfo owner.

If those findings clear Phase 3, its first scope should remain browser-only, disabled by default, no CAPI, no Purchase, no Pixel initialization, no Razorpay adapter, no order/cart/session writes, no payment hooks, and no checkout submission hooks. Razorpay remains separately blocked without a supported selection callback.

## 11. Blockers and verification needed

1. No connected browser with developer tools is available. The live homepage was fetched passively, but cart/checkout navigation and network inspection could not be performed.
2. No read-only WordPress admin or Meta Events Manager/Test Events access was available for settings and event ownership.
3. The candidate Pixel IDs have not been matched to an owner or canonical business dataset.
4. Consent implementation and consent-before-Pixel behavior are unknown.
5. Razorpay version/configuration, Magic Checkout availability, and event support are unknown.
6. The payment baseline cannot be recorded without staging. No test checkout was attempted.

To finish the read-only runtime checks, connect a browser with developer tools and provide read-only WordPress admin/Meta Events Manager access where those admin-only facts are required. Do not implement Phase 3.

**Go/No-Go:** **NO-GO for Phase 3.** Limited public homepage observations do not identify the canonical Pixel, establish AddPaymentInfo or Purchase ownership, verify consent, or classify checkout. No changes were made.

## Final status

```text
PHASE_2B_STATUS=PARTIAL — public homepage inspected; browser/admin/Meta runtime checks unavailable
LIVE_READ_ONLY_DISCOVERY=PASS — public homepage fetch only; interactive/browser inspection unavailable
PIXEL_OWNERSHIP_VERIFIED=NO
ADDPAYMENTINFO_STATUS_VERIFIED=NO
CONSENT_STATUS_VERIFIED=NO
CHECKOUT_TYPE_VERIFIED=NO
RAZORPAY_FRONTEND_OBSERVED=NO — browser unavailable
DISPLAYED_TOTAL_BASELINE_CAPTURED=NO — cart/checkout UI unavailable

WORDPRESS_VERSION=UNKNOWN
WOOCOMMERCE_VERSION=UNKNOWN
SUPERWOO_VERSION=1.4.2 in local source; staging install UNKNOWN
RAZORPAY_VERSION=UNKNOWN
META_FOR_WOOCOMMERCE_VERSION=UNKNOWN

CHECKOUT_TYPE=UNKNOWN

PIXEL_IDS=UNVERIFIED candidates supplied: 1508382043627612, 1506096780402858, 1943849039517761; none confirmed on live page
PRIMARY_PIXEL_ID=UNKNOWN
PRIMARY_PIXEL_OWNER=UNKNOWN
GTM_CONTAINER_OBSERVED=GTM-KB25XZTB (public homepage reference; tag contents/owner unverified)

EXISTING_CAPI=UNKNOWN
PURCHASE_OWNER=UNKNOWN
PURCHASE_DEDUPLICATION=UNKNOWN

EXISTING_ADDPAYMENTINFO=UNKNOWN on staging; no sender found in SuperWoo source
ADDPAYMENTINFO_OWNER=UNKNOWN

RAZORPAY_CUSTOM_EVENTS=UNKNOWN; no interactive Razorpay frontend inspected, none found in SuperWoo source
RAZORPAY_ADDPAYMENTINFO_SUPPORT=BLOCKED

CONSENT_PROVIDER=UNKNOWN
MARKETING_CONSENT_DETECTABLE=UNKNOWN

PAYMENT_BASELINE_CAPTURED=NO — cart/checkout UI could not be inspected with available tools
PAYMENT_TEST_MODE_ONLY=YES — no live or test transaction performed

CLASSIC_OBSERVER_READY=NOT CLEARED — runtime checkout, owner, and consent unknown
BLOCKS_OBSERVER_READY=NOT CLEARED — runtime checkout, version, owner, and consent unknown

PHASE_3_RECOMMENDED_SCOPE=BROWSER ONLY after discovery; standard WooCommerce only; no CAPI/Purchase/Pixel initialization/Razorpay adapter
PHASE_3_READY=NO

PRODUCTION_CHANGES=NONE
ORDER_CREATION_TESTED=NO
PAYMENT_AUTHORIZATION_TESTED=NO
PAYMENT_CAPTURE_TESTED=NO
PAYMENT_FAILURE_TESTED=NO
PRODUCTION_FILES_CHANGED=NO
PRODUCTION_DATABASE_CHANGED=NO
PRODUCTION_SETTINGS_CHANGED=NO
PAYMENT_SETTINGS_CHANGED=NO
PAYMENT_CODE_MODIFIED=NO
RAZORPAY_MODIFIED=NO
CHECKOUT_MODIFIED=NO
ORDER_PROCESSING_MODIFIED=NO
```

# SuperWoo Meta Tracking — Phase 2 Discovery Report

**Date:** 2026-10-13  
**Scope:** Read-only discovery based on the local SuperWoo repository. The requested target staging/test store could not be inspected: no staging URL or connected authenticated browser was available in this session. This report separates repository facts from deployed-store unknowns and does not substitute production browsing for staging access.

## Decision

**Phase 2 is blocked for deployed-store discovery, and Phase 3 is not ready.** The local repository confirms SuperWoo `1.4.2` and its conditional Razorpay Magic Checkout button handoff, but cannot reveal the target store’s active theme, checkout implementation, payment extensions, Pixel IDs, Meta/CAPI event delivery, consent state, or payment baseline. No events or fake transactions were generated. No runtime or production files were changed.

For a future Phase 3, the safest initial scope remains browser-only and standard WooCommerce checkout observers only after site-level Pixel ownership and consent are verified. There is not enough evidence to approve either Classic or Blocks on the deployed store yet. Razorpay Magic Checkout remains excluded from `AddPaymentInfo` unless its owner supplies a supported pre-payment selection signal.

## 1. Environment inventory

| Item | Repository evidence | Target staging/test store |
|---|---|---|
| SuperWoo version | `1.4.2` in `superwoo.php`; commit `edcd849` | Not verified installed version |
| WordPress | Plugin requires at least `6.2` | Actual version unknown |
| WooCommerce | Plugin requires at least `8.0` | Actual version unknown |
| PHP | Plugin requires at least `7.4` | Actual version unknown |
| Active theme | Not represented in plugin repository | Unknown |
| Cart implementation | SuperWoo can render its custom AJAX cart drawer when enabled; native/theme cart is external | Unknown whether enabled or coexisting with Cart/Mini-Cart Blocks |
| Checkout implementation | SuperWoo cart button hands off conditionally to Woo checkout URL or Razorpay’s mini-cart button integration | Unknown: classic, Blocks, custom, Magic Checkout, or combination |
| Thank You / Order Received | No site template or runtime markup in repository | Unknown |
| Razorpay extension/version | No Razorpay plugin bundled; SuperWoo detects `is1ccEnabled()` and `isMiniCartCheckoutEnabled()` | Unknown |
| Razorpay Magic Checkout | SuperWoo conditionally renders `#btn-1cc-mini-cart` when function/config checks pass | Actual enablement and checkout flow unknown |
| Other payment gateways | Not discoverable from plugin repository | Unknown |
| Express checkout methods | Not discoverable from plugin repository | Unknown |

**Checkout classification:** `UNKNOWN` for the target store. The SuperWoo cart entry point is a custom drawer. It may hand off to Razorpay Magic Checkout conditionally, but the underlying WooCommerce Checkout page implementation and any accelerated methods are not established by local source.

## 2. Checkout architecture discovery

The local integration points are:

- `includes/class-cart-drawer.php` renders the drawer and its AJAX/cart-fragment behavior; it excludes rendering on checkout pages.
- `includes/functions.php::superwoo_cart_primary_button_html()` renders a normal WooCommerce checkout link unless Razorpay’s feature checks pass, in which case it outputs the button ID `btn-1cc-mini-cart`.
- `public/js/cart-drawer.js` treats Razorpay/Magic Checkout and other Buy Now controls as third-party-owned and leaves their click behavior alone.
- The local code does not identify whether the target checkout page is built with the classic shortcode, Checkout Block, a page-builder template, or a provider-specific custom checkout.

No staging page, checkout source, admin environment information, or authenticated browser state was available. Accordingly, this report cannot verify the Thank You page, selected gateway, guest/registered behavior, express methods, or any checkout lifecycle on the target.

## 3. Meta Pixel inventory and ownership

### Repository inventory

Search of the local plugin tree found no Meta Pixel initialization (`fbq`), Pixel/Dataset ID, CAPI client, `AddPaymentInfo`, or `Purchase` sender in SuperWoo runtime code. This is a repository-only result. It does not include the deployed theme, plugins, GTM container, snippets, or server-side tag infrastructure.

### Deployed inventory

No storefront/checkout pages or browser network requests could be inspected. No Pixel ID or owner can be safely assigned. Presence, count, duplicate initialization, page coverage, and browser/server delivery are all unknown. No claim is made that Meta for WooCommerce, PixelYourSite, GTM, Razorpay Facebook Analytics, or a custom plugin is or is not active.

| Field | Finding |
|---|---|
| Existing Pixel IDs observed | None observed; target could not be inspected |
| Existing Pixel count | Unknown |
| Pixel source/plugin/theme/GTM owner | Unknown |
| Pages where Pixel loads | Unknown |
| Browser events emitted | Unknown |
| CAPI appears active | Unknown |
| Duplicate Pixel initialization | Unknown |
| Primary dataset / Pixel | `UNKNOWN` |
| Primary owner | `UNKNOWN` |
| Confidence | None for deployed ownership; repository-only search has high confidence for SuperWoo source |

## 4. Existing event matrix

The request requires actual rendered-browser and Meta Test Events verification. That access was unavailable, so deployed event state is **unknown** for every row. “No local SuperWoo sender found” is not evidence that another plugin or server integration does not send the event.

| Event | Pixel | Browser | Server | Owner | Duplicate Risk |
|---|---|---|---|---|---|
| PageView | Unknown | Unknown | Unknown | Unknown | Unknown |
| ViewContent | Unknown | Unknown | Unknown | Unknown | Unknown |
| AddToCart | Unknown | Unknown | Unknown | Unknown | Unknown |
| InitiateCheckout | Unknown | Unknown | Unknown | Unknown | Unknown |
| AddPaymentInfo | Unknown | Unknown | Unknown | Unknown | Unknown |
| Purchase | Unknown | Unknown | Unknown | Unknown | Unknown |

For all rows, local SuperWoo source search found no Meta event sender, Pixel ID, or CAPI client. No event was generated for this audit.

### Event metadata and Razorpay events

`EVENT_ID_PRESENT`, browser/server source, trigger, event parameters, event owner, and deduplication behavior cannot be verified without browser network inspection and Meta Test Events. The names `magic_checkout_requested`, `payment_method_selected`, `pay_now_clicked`, and `payment_successful` were not found in SuperWoo source. No assertion is made about whether Razorpay emits these internally or externally on the target store.

## 5. CAPI and Purchase ownership

- **CAPI owner:** Unknown on target. No CAPI implementation exists in the SuperWoo repository.
- **Purchase owner:** Unknown on target. No Purchase sender exists in SuperWoo repository. This does not establish who else sends Purchase.
- **Purchase deduplication:** Unknown. Event IDs, browser/server copies, and Meta Events Manager receipt could not be checked.
- **SuperWoo scope recommendation:** SuperWoo should not own or modify Purchase in the initial implementation.
- **Meta for WooCommerce, GTM, PixelYourSite, custom snippets, Razorpay analytics, and other server-side providers:** all unverified on target.

## 6. Existing AddPaymentInfo check

No `AddPaymentInfo` sender was found in local SuperWoo runtime code. Existing implementations outside this repository, browser event delivery, Meta Test Events, trigger timing, Pixel ID, event ID, and duplication are unknown. Therefore SuperWoo must not add this event until a target-store inventory identifies an owner and confirms that no event already covers the intended trigger.

## 7. Consent discovery

| Required field | Finding |
|---|---|
| Consent provider | Unknown on target |
| Marketing category name | Unknown |
| Programmatic API available | Unknown |
| Consent enforced before Pixel initialization | Unknown |
| Consent change event | Unknown |
| Default state | Unknown |
| Marketing consent detectable by SuperWoo | No evidence available; not cleared |

SuperWoo stores a separate WhatsApp order-updates consent value in WooCommerce session/order metadata. That purpose-specific setting is not evidence of advertising/marketing consent and must not be reused for Meta. No consent configuration was inspected or changed.

## 8. Razorpay boundary

The Phase 1 conclusion is unchanged. SuperWoo detects the Razorpay Magic Checkout/minicart availability functions and outputs its expected button; it does not implement a payment-selection callback, gateway request, webhook, or payment state change. The target’s Razorpay plugin version and Magic Checkout settings could not be verified.

No documented public selection callback or sanctioned plugin event has been established by this discovery. Do not infer selection from the SuperWoo button click, payment success, or a post-payment webhook; do not inspect private iframe internals, intercept private `postMessage`, monkey-patch Razorpay, or change Razorpay code.

```text
RAZORPAY_ADDPAYMENTINFO_SUPPORT=BLOCKED
```

This does not block a future standard WooCommerce observer once the target checkout type, consent, and event owner are verified.

## 9. Payment invariance baseline

The brief asks for a staging checkout baseline. No staging checkout was accessible, so **no cart snapshot, checkout, payment attempt, or test transaction was performed**. All values below remain unmeasured:

| Baseline field | Value |
|---|---|
| Cart subtotal | Unknown |
| Discounts | Unknown |
| Shipping | Unknown |
| Taxes | Unknown |
| Fees | Unknown |
| Total | Unknown |
| Available gateways | Unknown |
| Selected gateway | Unknown |
| Order creation behavior | Unknown |
| Payment success behavior | Unknown |
| Payment failure behavior | Unknown |
| Redirect behavior | Unknown |
| Thank You page behavior | Unknown |

This is an access blocker, not a failed payment test. No production payment was attempted.

## 10. Duplicate-event risks

1. Pixel/CAPI owners and IDs on the deployed store are unknown, so installing any additional sender risks duplicate events.
2. Purchase may already be sent by Meta for WooCommerce, GTM, Razorpay-related analytics, custom code, or another integration; ownership and deduplication were not observable.
3. AddPaymentInfo may already be sent by another Pixel integration. Repository search alone cannot clear this event for use.
4. Consent ordering and categories are unknown; dispatching before marketing consent could violate the merchant’s intended policy.
5. Razorpay’s Magic Checkout selection is not exposed through the verified SuperWoo handoff. Button clicks would create a false positive if treated as selection.

## 11. Recommended Phase 3 architecture and scope

When blockers are cleared, keep the first implementation **browser-only**, disabled by default, and separate from cart, recovery, order, and payment modules:

- One explicit SuperWoo master opt-in and tracking-only kill switch; no automatic enablement for existing stores.
- Classic checkout observer only if the deployed checkout is confirmed classic and the merchant’s existing event inventory establishes ownership/consent. It should observe actual payment-method selection changes, bind once, and never prevent default or wait on Meta.
- Blocks observer only if the deployed checkout is confirmed Blocks and its supported read-only payment data-store API is present on the tested WooCommerce versions. It should only observe active method changes; it must not register a payment method or use payment setup/validation lifecycle hooks.
- Use an already-present, merchant-approved Pixel dispatcher only after the canonical Pixel ID/owner is documented. SuperWoo must not initialize a second Pixel.
- Gate dispatch on the verified marketing consent API and suppress events when consent is denied or unavailable.
- No CAPI, access token, queue, server event, Purchase, Razorpay adapter, order/session writes, or checkout endpoint calls in the initial scope.
- Isolate observer errors from checkout. Add regression checks for cart totals, payment methods, authorization/capture, order creation/status, redirects, and success/failure paths before pilot.

**Current recommendation:** neither Classic nor Blocks is cleared for implementation on this target until discovered. A source-level observer design remains technically plausible for either standard WooCommerce path, but that is not deployed-store approval.

## 12. Open blockers

1. Staging URL and authenticated read-only browser/admin access were not available. No connected browser was present in this session.
2. WordPress/WooCommerce/theme/payment plugin versions and active checkout blocks are unknown.
3. Meta Pixel IDs, canonical dataset, tag owners, browser events, CAPI, Purchase, and AddPaymentInfo are unknown.
4. Consent provider/API/category/default state and Pixel consent ordering are unknown.
5. Razorpay version/settings and any documented merchant-supported selection callback are unknown.
6. Payment invariance baseline was not captured because the staging checkout was inaccessible.

To clear Phase 3, run this read-only discovery on the intended staging store with browser access and Meta Events Manager/Test Events access (or provide a complete export of those observations). Do not infer the canonical Pixel solely from source order or first ID seen.

**Go/No-Go:** **NO-GO for Phase 3.** Phase 2 repository discovery is complete, but deployed-store validation is blocked by missing staging/browser and Meta/consent visibility. Razorpay remains blocked independently. No implementation or deployment was performed.

## Final status

```text
PHASE_2_STATUS=BLOCKED — repository discovery complete; deployed-store checks unavailable
PRODUCTION_CHANGES=NONE
PAYMENT_CODE_MODIFIED=NO
RAZORPAY_MODIFIED=NO
CHECKOUT_MODIFIED=NO
ORDER_PROCESSING_MODIFIED=NO

CHECKOUT_TYPE=UNKNOWN (SuperWoo custom drawer; underlying target checkout not inspected)
WOOCOMMERCE_VERSION=UNKNOWN on target; plugin declares minimum 8.0
RAZORPAY_VERSION=UNKNOWN

PRIMARY_META_DATASET=UNKNOWN
PRIMARY_PIXEL_ID=UNKNOWN
PRIMARY_PIXEL_OWNER=UNKNOWN

EXISTING_PIXEL_COUNT=UNKNOWN
EXISTING_CAPI=UNKNOWN
PURCHASE_OWNER=UNKNOWN
PURCHASE_DEDUPLICATION=UNKNOWN

EXISTING_ADDPAYMENTINFO=UNKNOWN on target; none found in SuperWoo repository
ADDPAYMENTINFO_OWNER=UNKNOWN

CONSENT_PROVIDER=UNKNOWN
MARKETING_CONSENT_DETECTABLE=NO EVIDENCE / NOT VERIFIED

CLASSIC_OBSERVER_READY=NOT CLEARED — target checkout and consent unknown
BLOCKS_OBSERVER_READY=NOT CLEARED — target checkout, version, and consent unknown
RAZORPAY_ADDPAYMENTINFO_SUPPORT=BLOCKED unless supported API verified

PAYMENT_BASELINE_CAPTURED=NO — staging checkout unavailable; no transaction attempted

PHASE_3_RECOMMENDED_SCOPE=BROWSER ONLY after site inventory; standard WooCommerce path to be selected after verification; no CAPI/Purchase/Razorpay adapter
PHASE_3_READY=NO — staging/tag/consent/baseline blockers remain
```

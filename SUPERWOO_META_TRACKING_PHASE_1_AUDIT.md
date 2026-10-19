# SuperWoo Meta Conversion Tracking — Phase 1 Audit

**Audit date:** 2026-10-13  
**Audited source:** SuperWoo `1.4.2` at commit `edcd849` (`main`, clean at audit start)  
**Scope:** Read-only architecture and payment-safety audit. This report is the only file created for this phase. No runtime code, gateway setting, order, payment, cart, or production state was changed.

## Executive decision

**Phase 2 is not ready to begin.** A future, opt-in Meta browser event observer appears feasible for standard classic WooCommerce checkout, and WooCommerce Blocks exposes a documented read-only active-payment-method selector. Both still need duplicate-pixel/consent discovery and supported-version integration tests. Razorpay Magic Checkout is **unsupported for `AddPaymentInfo` at this time**: SuperWoo’s source only checks whether Razorpay’s 1CC/minicart feature is enabled and renders its expected button; neither the installed SuperWoo source nor the Razorpay public documentation reviewed defines a supported external event/callback for observing payment selection inside Magic Checkout. Do not inspect private iframe messages or patch Razorpay internals to fill this gap.

The safe design is an independent, disabled-by-default observer that never joins checkout’s payment hooks or request/redirect flow. Browser-only tracking is the lowest-risk first milestone. CAPI, a queue, and deduplication are later optional layers and should not be included until the store’s existing Pixel/CAPI owner is known; blindly enabling them creates duplicate events and privacy risk.

## 1. Existing SuperWoo architecture

### Bootstrap and composition

- `superwoo.php` defines plugin constants, loads module classes, registers activation/deactivation, and boots `SuperWoo_Plugin` on `plugins_loaded`.
- `includes/class-plugin.php` is the composition root. Modules follow a `hooks()` registration pattern; plugin settings are read through `superwoo_get_settings()`.
- The plugin gates WooCommerce-dependent behavior with `SuperWoo_WooCommerce_Guard`. Regular admin requests take an early return after admin modules are registered; storefront and AJAX requests load the display/cart modules.
- There is no general-purpose module registry, service container, dependency injection layer, or module-specific feature-flag framework. A future tracking module should follow the existing class + `hooks()` convention, but isolate registration behind its own master option and avoid changing existing settings’ semantics.

### Settings and administration

- Settings live in the single `superwoo_settings` option. Defaults are merged in `superwoo_get_settings()` in `includes/functions.php`; `SuperWoo_Plugin::save_settings()` reconstructs/sanitizes the settings array, checks a nonce and `manage_woocommerce`, then updates the option.
- The server-rendered settings view is `admin/views/settings-page.php`; tabs are currently Product Page, Cart, Colors & Style, and Multi-Currency. There is no Meta/marketing settings area.
- Existing options include module toggles, cart settings, currency settings, appearance values, diagnostics, and `enable_logging`. A new option must default false for both new and existing installs. Saving the current settings form rebuilds the array, so any future setting must be added to defaults, form, and save handler together or it will be lost on a settings save.
- There is no existing Meta credential store. CAPI access tokens must never be exposed to browser JavaScript, placed in logs, or stored without an explicit secret-handling design.

### Marketing, analytics, and existing event sources

- There is no Meta Pixel initialization, Meta CAPI dispatcher, `AddPaymentInfo`, or `Purchase` event implementation in the current source tree. Repository search found no Meta/Facebook pixel identifiers or CAPI calls. This does **not** prove that the active theme, GTM, Meta for WooCommerce, PixelYourSite, another plugin, or a tag manager does not inject events on the merchant site.
- Shoppable Videos is the only analytics-like module. `includes/class-shoppable-videos.php` records its own pseudonymous video engagement events in a custom table, schedules daily cleanup, and attaches cart/order attribution. It is not a general analytics transport and is not a suitable place to send Meta conversion events.
- The existing logs in `includes/functions.php` are opt-in (`enable_logging` defaults false), sanitize sensitive-looking context keys, write under uploads and WooCommerce’s logger, and categorize order/payment/diagnostic/error events. `includes/class-plugin.php` observes WooCommerce checkout validation/order/payment-result hooks and documents those methods as diagnostics only. They are not checkout control points or a tracking queue.
- Cart Recovery is a distinct identity/automation boundary (`includes/class-cart-recovery.php`, `includes/cart-recovery/*`): it captures cart snapshots and customer identity, starts checkout tracking, schedules processing/email work, and may restore carts. Meta tracking must not reuse its customer data, session fields, database schema, recovery hooks, or automation pipeline. Its purpose and retention policy differ.

### WooCommerce and Razorpay touch points in SuperWoo

- `includes/class-cart-drawer.php` registers drawer rendering, fragments, custom add/update/remove AJAX actions, and cart quantity enforcement. It also stores the cart’s WhatsApp-order-updates consent on classic and Store API order creation. These actions mutate cart/session/order metadata and are not appropriate places for `AddPaymentInfo` observation.
- `includes/class-bundle-offers.php` adjusts discounts before WooCommerce calculates totals and synchronizes gift items. `includes/class-currency.php` filters currency/prices and participates in cart totals/session/order metadata. Those are business-logic integrations and must remain independent from tracking.
- `includes/functions.php::superwoo_razorpay_magic_checkout_available()` tests for Razorpay functions `is1ccEnabled()` and `isMiniCartCheckoutEnabled()` plus test-mode conditions. `superwoo_cart_primary_button_html()` renders `#btn-1cc-mini-cart` when those feature checks pass, otherwise links to the WooCommerce checkout URL. `public/js/cart-drawer.js` recognizes that ID as a Buy Now control and intentionally leaves the click to the owning payment integration. This is a handoff/availability integration, not a payment-selection observer.
- No Razorpay plugin files are bundled in this repository. No Razorpay payment API, transaction, webhook, order mutation, iframe message, or gateway callback is used by SuperWoo for this handoff.

## 2. Payment safety and likely interference points

The module must not be attached to code that affects gateway configuration or transactional state. In particular, avoid:

- WooCommerce filters/actions that return or modify available gateways, chosen payment method, fees, cart totals, checkout fields, Store API checkout payload, order status, payment result, or redirect URL.
- `woocommerce_checkout_order_processed`, `woocommerce_payment_complete`, `woocommerce_order_status_changed`, `woocommerce_rest_checkout_process_payment_with_context`, or payment gateway `process_payment()` for a pre-payment selection event. Existing SuperWoo diagnostics use some completion/status hooks strictly to log; a tracking observer should not add blocking network work or alter their behavior.
- `onPaymentSetup`, `onCheckoutValidation`, checkout submit handlers, Razorpay button click handlers, or any extension observer whose response or promise can hold up checkout. WooCommerce documents these as part of checkout/payment processing; using them for a nonessential analytics send creates an avoidable failure and latency path.
- Any code that writes `WC()->session`, payment/order metadata, cart state, fees, or checkout redirect state solely to carry tracking data.

An observer must be read-only and fire-and-forget. It must catch its own errors, have bounded memory, never prevent default behavior, never return a value that changes WooCommerce flow, and never wait for Meta before allowing the shopper to continue. If consent is required or unknown, do not dispatch. CAPI/queue failures must be contained outside the checkout request.

## 3. WooCommerce compatibility findings

### Classic checkout

- Standard checkout presents a selected gateway in the checkout form. A delegated listener on the user’s actual `change` event for the WooCommerce payment-method input can observe the selected ID without changing it. Treat this as a browser UI observation, not proof that payment data was entered or that a payment succeeded.
- WooCommerce’s classic checkout refreshes portions of the page during address, shipping, coupon, and payment-related updates. Repeated initialization or fragment replacement can cause duplicate tracking unless the observer is bound once and de-duplicates per checkout lifecycle (for example, only emit on a real transition into a supported selection, with a documented re-selection policy).
- Server-side hooks that receive posted checkout review/payment data are too close to checkout processing, may run repeatedly, and risk conflating recalculation with selection. They are not recommended for the first event.
- Guest and registered customers both use the same client-side payment selection surface in the classic form. No customer identity is needed for a browser `AddPaymentInfo` signal; do not collect email/phone to make it work.
- Express methods (wallet buttons and one-click flows) may initiate payment without a conventional radio-selection transition. They need their own documented integration contract. Do not infer selection from button text, clicks, or a completed order.

### Checkout Blocks

- WooCommerce’s documented `wc/store/payment` data store provides `getActivePaymentMethod()` and also distinguishes regular and express payment methods. This is a genuine read-only observation point when the Checkout Block and its supported WooCommerce data store are present. It is stronger than querying arbitrary DOM markup, but it must be version-guarded and tested against the project’s declared minimum WooCommerce 8.0 and current supported releases.
- The documented Blocks checkout event emitter APIs include validation, payment setup, and success/failure lifecycle events. Those are lifecycle/payment-processing integrations, not the ideal place to observe a shopper’s earlier selection. Do not subscribe to them merely to emit a marketing event.
- Selection is client state: WooCommerce documents that changing the payment method does not itself send a Store API request. Therefore a server-only observer cannot reliably see the selection before order submission. Do not create an `extensionCartUpdate`/Store API request to transmit it.
- Express payment methods have separate state and may not expose the same “selection” semantics as standard methods. The event policy needs to explicitly exclude unsupported express flows or separately implement a documented observer for them.
- SuperWoo currently has no registered Checkout Block payment extension or explicit Block checkout integration for this event. The cart drawer’s Store API use does not imply Checkout Block support.

### Cart/session/order lifecycle and order completion

- WooCommerce remains the authority for cart totals, taxes, fees, shipping, discounts, and the order/payment result. The tracking design should read only the selected method identifier and perhaps non-sensitive event metadata. Do not read or send order/payment secrets, card details, billing data, or session identifiers.
- `AddPaymentInfo` is a pre-purchase behavior event. Order completion and payment completion are different signals and do not establish that the shopper selected a payment method at the point requested. Avoid reusing order-status hooks to synthesize it.
- Guest vs registered checkout should not change event semantics. Ensure a guest event does not depend on a logged-in user ID, WooCommerce session writes, or recovery tracker identity.

## 4. Razorpay Magic Checkout findings

1. **Initialization:** SuperWoo checks whether Razorpay reports 1CC and minicart checkout enabled, then renders the expected mini-cart button ID. SuperWoo does not initialize Magic Checkout itself. Razorpay’s public integration/docs describe Magic Checkout as a supported WooCommerce integration and describe a shopper choosing a payment method within checkout.
2. **Selection signal:** No supported public callback/event for external listeners to observe Magic Checkout’s selected payment option was found in the Razorpay WooCommerce source overview or public Magic Checkout documentation reviewed for this audit.
3. **Meta `payment_method_selected`:** This is a Meta event name, not evidence of a Razorpay callback. No SuperWoo/Razorpay contract currently forwards Razorpay payment choice to Meta.
4. **Webhook:** A payment webhook reports payment/order lifecycle after an attempt; it is not a reliable pre-payment selection signal. A webhook cannot represent a user merely selecting a method and leaving before payment.
5. **Iframe/private runtime:** No documented external callback/message interface was verified. Do not inspect/intercept private iframe `postMessage`, poll Razorpay internal DOM, monkey-patch global functions, or modify Razorpay source.
6. **Before payment completion:** SuperWoo can know it handed the shopper to the 1CC flow, but the local integration does not prove which method the shopper selected within it. Firing `AddPaymentInfo` on button click would misstate the event and count users who never selected a method.

**Conclusion:** Razorpay Magic Checkout must be excluded from `AddPaymentInfo` until Razorpay supplies a documented callback, supported public event, or sanctioned plugin hook that exposes method selection before payment completion. Continue to let Razorpay own the checkout button and payment flow unchanged.

## 5. Existing Meta tracking conflicts and duplication risk

- **Repository finding:** No local Pixel, CAPI, `AddPaymentInfo`, or `Purchase` implementation was found in SuperWoo source.
- **Store finding:** The repository cannot inspect plugins active on the deployed store, theme snippets, tag manager containers, server-side gateways, consent manager, or the Pixel ID configuration. Existing event ownership and consent behavior are therefore **unknown**.
- **Risk:** A new `fbq` call could duplicate Meta for WooCommerce, PixelYourSite, GTM, a theme snippet, or another CAPI/Pixel provider. A second Pixel initializer must never be added. A pre-existing browser `AddPaymentInfo` could also duplicate the new observer.
- **Browser/server deduplication:** If a later phase adds both Pixel and CAPI, each corresponding browser/server event must use the same event name and matching event identifier according to Meta’s deduplication guidance. A random ID generated independently in each path will not deduplicate. For the first release, browser-only is simpler and safer; do not send CAPI until event ownership and deduplication have been verified in Meta Events Manager.
- **Consent:** There is no general marketing-consent abstraction in SuperWoo. Its WhatsApp order-update checkbox is separate purpose-specific consent and cannot be treated as advertising consent. A tracking module must integrate with a supported consent manager when present, and default to suppressing marketing events when consent is denied or cannot be resolved under the merchant’s chosen policy. The exact jurisdiction/policy remains a merchant/legal configuration decision.

**Duplication risk at repo level:** Low (no implementation exists).  
**Duplication risk on the actual store:** High/unknown until a staging and production tag inventory is completed.

## 6. Proposed reusable architecture and files

Use a distinct `includes/meta-tracking/` module rather than extending cart, recovery, or payment code. All module registration is gated by a master flag defaulting false. Initial release should be browser-only, use an already-present Pixel only, and never initialize a Pixel or store a Meta access token.

| Proposed file | Responsibility | Depends on |
|---|---|---|
| `includes/class-meta-tracking.php` | Feature bootstrap; read opt-in/test-mode/kill-switch flags; register only tracking hooks/assets | settings, WooCommerce guard |
| `includes/meta-tracking/interface-payment-observer.php` | Read-only adapter contract; normalized selection callback data | none |
| `includes/meta-tracking/class-classic-checkout-observer.php` | Bind once to classic checkout payment-method selection changes; no checkout submit interception | enqueued observer JS, normalized event service |
| `includes/meta-tracking/class-blocks-checkout-observer.php` | Version/availability-guarded subscription to documented Blocks payment store active-method state | Woo Blocks data store, event service |
| `public/js/meta-checkout-observer.js` | Non-blocking browser observers; feature/consent checks; duplicate-transition guard; catch all exceptions | localized non-secret settings, `fbq` if already present |
| `includes/meta-tracking/class-event-normalizer.php` | Map supported observed selection to Meta event name/limited parameters and stable event ID policy | observer adapter |
| `includes/meta-tracking/class-meta-pixel-dispatcher.php` | Dispatch event to an already-present Pixel only; no `init` call | normalizer, consent gate |
| `includes/meta-tracking/class-consent-gate.php` | Resolve explicit consent integration and fail closed where required | consent provider adapters |
| `includes/meta-tracking/class-diagnostics.php` | Redacted event counts/reasons and operational status; no payment identifiers or PII | existing `superwoo_log()` only if safe and enabled |
| `admin/views/meta-tracking-settings.php` | Explicit master opt-in, event switches, test mode, provider/consent status, kill switch | current admin view/settings pattern |
| `tests/meta-tracking/*` | Isolated adapter, duplication, consent, fault injection, and payment invariance tests | WordPress/Woo test harness |

**Later-only files (not Phase 2 scope):** a CAPI client, queue repository/worker, retry policy, and admin diagnostics may be proposed after the browser pilot. Do not piggyback on Cart Recovery tables/actions. CAPI tokens need server-only encrypted/WordPress secret configuration handling, strict capability/nonce controls, redaction, and endpoint timeouts. Queue processing must be asynchronous and never executed in a checkout response.

### Dependency map

`SuperWoo_Plugin composition root` → master flag and adapter availability → `classic observer` OR `Blocks observer` → consent gate → event normalizer/dedupe guard → existing `fbq` dispatcher.  
Future isolated path: normalized event → async queue → CAPI dispatcher. Queue is optional and not a dependency for successful checkout. Razorpay adapter is **not registered** unless an upstream supported selection contract is verified. Existing cart drawer, offers, currency, cart recovery, shoppable video analytics, order/payment status logging, and payment plugins remain outside this dependency graph.

## 7. Compatibility risks and required decisions

- **Meta ownership:** Unknown current Pixel IDs and event coverage may result in duplicate events. Inventory Meta for WooCommerce, PixelYourSite, GTM, theme snippets, CAPI gateways, and Meta Events Manager before pilot.
- **Consent API diversity:** CookieYes, Complianz, Cookiebot, OneTrust, and other consent managers expose different APIs and categories. Choose and document a supported adapter matrix; fail closed if state is not known.
- **Classic checkout/custom themes:** Some themes replace payment controls or update checkout fragments. Delegate event listeners, bind once, and test the site’s actual checkout implementation.
- **Checkout Blocks and WooCommerce versions:** The plugin declares WooCommerce 8.0 minimum. Verify the documented `wc/store/payment` store and selector availability/behavior across the supported range; absence must be a graceful no-op. The runtime environment/version set was not available in this repository audit.
- **Express wallets and accelerated checkout:** Selection can be a modal/button flow. Do not label a click as payment information. Explicitly mark unsupported unless the payment provider exposes a public supported selection signal.
- **Razorpay versions and Magic Checkout configuration:** SuperWoo only detects two Razorpay functions; no upstream version contract is pinned. 1CC can have COD and prepaid methods and a separate flow. Avoid inferring selected method, amount, or success from feature availability or the mini-cart button.
- **Performance/privacy:** Keep browser code tiny, no synchronous or blocking request, no payment/billing data, no ad-hoc fingerprinting, no session mutation, no unnecessary event persistence.
- **Existing purchase conversion:** SuperWoo currently does not send Purchase. That does not rule out an existing plugin’s Purchase Pixel/CAPI path. Do not add/alter Purchase as part of this event work.

## 8. Verification inventory and limitations

### Verified from repository

- Bootstrap/composition root and module registration pattern.
- Existing settings option/defaults/save flow and admin capability/nonce checks.
- Cart, offers, currency, cart-recovery, shoppable-video analytics, consent metadata, logs, and scheduled cleanup touch points.
- Razorpay Magic Checkout availability/button handoff in SuperWoo; no Razorpay SDK/API/webhook/event implementation in this repository.
- No local Meta Pixel/CAPI/event implementation found.
- Git was clean at audit start; current source version is 1.4.2.

### Not verified

- Active production WordPress/WooCommerce versions, theme, plugin inventory, gateway configuration, Razorpay plugin version/settings, Meta Pixel ID(s), GTM, server-side CAPI provider, consent manager, site network/security settings, actual checkout runtime, and browser/network event stream.
- Razorpay’s private/runtime client code or any merchant-only callback. None was inspected or inferred.
- No checkout, webhook, live/test payment, or deployment was run as part of this audit.

## 9. Required test plan before release

### Observer behavior

- Classic checkout: guest and logged-in; default method; changing between two methods; reselecting same method; checkout fragment refresh; method removed/disabled; validation error; failed payment retry; normal successful checkout. Assert one event per defined selection transition and no event on page load unless product semantics explicitly require it.
- Blocks: standard gateways; active method changes; saved token; express method state; Store API refresh; unavailable/incompatible payment methods; checkout retry; WooCommerce 8.0 minimum and current supported release. Assert observer only reads store state and does not dispatch checkout/payment actions.
- Razorpay 1CC: button rendered/absent, test/live mode, prepaid and COD options, customer exits before selection, selection, cancellation, payment failure, success. Expected current behavior: no SuperWoo `AddPaymentInfo` event for Magic Checkout, while Razorpay checkout remains unchanged.
- Consent: granted, denied, undecided, manager missing, consent changes mid-session, each supported consent-manager adapter. Assert no dispatch when disallowed/unknown.
- Duplicate-source checks: existing Pixel present/absent; existing Pixel initializer present; other plugin already emits AddPaymentInfo; Pixel + CAPI with matching IDs in later phase. Assert SuperWoo never initializes a Pixel and browser/server events deduplicate only when configured by one event owner.

### Payment invariance and fault injection

For enabled and disabled feature flag, compare before/after invariants for product/variation prices, cart subtotal, discounts, taxes, shipping, fees, total, available gateways, selected gateway, authorization/capture, order creation, order status transitions, checkout redirect, success/failure/cancel behavior, cart/session state, guest and registered checkout. Expected diff is only the consent-permitted Meta event and isolated diagnostics.

Inject Pixel absent, `fbq` throwing, malformed settings, blocked browser script, JavaScript exception, slow/unavailable Meta endpoint, invalid/future CAPI credentials, API timeout, queue unavailable, duplicate queue delivery, stale consent, malformed payload, and storage/log failure. In every case checkout proceeds with the same WooCommerce result; no retries run inline; no sensitive values appear in logs.

### Accessibility, delivery, and operations

- Confirm tracking UI is not added to checkout and creates no focus/keyboard changes.
- Confirm cache/minification/delay tools do not duplicate initialization or drop consent state.
- Confirm diagnostics are off by default, redact event payloads, have retention/clear behavior, and do not log Pixel IDs, access tokens, payment details, customer identifiers, or full URLs with query strings.
- Use Meta Test Events/Events Manager and browser developer tools on staging; never use a production payment to validate event tracking.

## 10. Feature flag, rollout, and rollback design

- Add a new independent master flag such as `enable_meta_tracking`, default `false` in code and on upgrade. Existing merchants remain opted out. Do not activate automatically based on existing Pixel presence or Razorpay settings.
- Separate `test_mode` from production dispatch. Test mode should use Meta’s test event code only when explicitly configured, show a clear admin status, and never alter checkout.
- Add a tracking-only kill switch evaluated at script enqueue and again immediately before dispatch. A disable must stop all SuperWoo browser/server tracking without disabling or changing the merchant’s other Pixel integrations.
- Pilot first on one staging store with the same theme, Woo version, payment plugins, and consent manager as production. Validate checkout invariants and event ownership before enabling at one production store.
- Rollback by disabling the master/kill switch and, if needed, removing only the tracking module assets/hooks in a new plugin release. No order/cart/payment rollback or data repair should ever be needed. The disabled state must leave current behavior unchanged.
- CAPI, persistent event storage, and automatic retries require separate opt-in/review because they expand privacy, secret-management, and operational scope.

## 11. Refined implementation phases and acceptance criteria

### Phase 2 — consent and event ownership discovery

Document store’s active Pixel/CAPI owners, Pixel IDs, existing AddPaymentInfo/Purchase sends, consent manager APIs/categories, and checkout implementation.  
**Accept when:** staging tag inventory and consent behavior are documented; one owner is designated for AddPaymentInfo; duplicate sources are either removed/configured by the merchant or SuperWoo is explicitly disabled for that event; no runtime code changes are included in this audit.

### Phase 3 — disabled-by-default browser observer foundation

Build the module shell, settings, kill switch, logging-safe diagnostics, and consent gate. Add classic and Blocks read-only observers for supported standard method transitions. Reuse an existing Pixel only; no CAPI or Razorpay adapter.  
**Accept when:** feature defaults off for new and existing installs; all existing settings survive save; observer unbinds/initializes once; browser event is never awaited; failures cannot affect checkout; no event is sent without opt-in and consent; no Pixel is initialized by SuperWoo.

### Phase 4 — compatibility, safety, and one-store pilot

Run the test matrix on staging for supported classic/Blocks versions and actual payment/theme/consent extensions; compare cart/order/payment invariants and Meta Test Events.  
**Accept when:** zero unexplained differences in totals, shipping, gateway state, order creation/status, authorization/capture, redirects, or session; event count/parameters match the documented event policy; site owner approves the recorded compatibility matrix.

### Phase 5 — optional CAPI evaluation (separate approval gate)

Only after Phase 4, decide whether server-side tracking is needed, who owns deduplication, and how to handle tokens, consent, queue, retries, retention, and privacy disclosures.  
**Accept when:** separate threat/privacy review; shared browser/server event ID verified in Meta Test Events; async delivery proven; kill switch/rollback and retention exercised; explicit approval before implementation.

### Razorpay adapter phase — blocked pending upstream contract

Do not schedule/implement an adapter based on reverse engineering or on checkout button clicks. Re-evaluate only when Razorpay publishes or supplies a supported external callback/event or sanctioned Woo plugin API that reports selected method before payment completion and does not require SuperWoo to handle payment flow.  
**Accept when:** documented API, supported version range, lifecycle timing, failure behavior, and test fixture are available; separate Razorpay staging test proves it is read-only and does not duplicate existing Meta integration.

## 12. Open blockers and go/no-go

1. **Merchant tag inventory is missing.** We cannot determine whether a live Meta Pixel/CAPI or AddPaymentInfo event already exists.
2. **Consent integration is unspecified.** The current WhatsApp consent is purpose-specific, not marketing consent.
3. **Razorpay selection callback is unverified/unavailable in public interfaces reviewed.** Magic Checkout is unsupported for this event.
4. **No staging checkout/browser test harness or supported integration matrix is present in the repository.** WooCommerce Blocks support must be confirmed on the merchant’s deployed versions.
5. **No event ownership/deduplication decision is recorded.** Do not emit browser and server copies by default.

**Go/no-go:** **NO-GO for implementation/rollout today.** Phase 2 may begin only after the owner approves this read-only audit and supplies/authorizes discovery of the deployed tag and consent setup. A separate explicit approval is required before runtime implementation. Razorpay remains excluded until a supported selection signal exists. This audit itself is complete.

## Source references

Repository references were inspected at the audited commit: `superwoo.php`, `includes/class-plugin.php`, `includes/functions.php`, `includes/class-cart-drawer.php`, `includes/class-bundle-offers.php`, `includes/class-currency.php`, `includes/class-cart-recovery.php`, `includes/cart-recovery/*`, `includes/class-shoppable-videos.php`, `admin/views/settings-page.php`, `public/js/cart-drawer.js`, and existing audit documents.

Primary documentation reviewed:

- [WooCommerce Checkout flow and events](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/checkout-flow-and-events/)
- [WooCommerce payment data store](https://developer.woocommerce.com/docs/block-development/reference/data-store/payment/)
- [WooCommerce data flow](https://developer.woocommerce.com/docs/block-development/reference/overview-of-data-flow/)
- [WooCommerce payment method integration](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/checkout-payment-methods/payment-method-integration)
- [WooCommerce Store API Checkout endpoint](https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/checkout/)
- [Razorpay Magic Checkout documentation](https://github.com/razorpay/markdown-docs/blob/master/payments/magic-checkout.md)
- [Razorpay WooCommerce payment extension source repository](https://github.com/razorpay/razorpay-woocommerce)
- [Meta Pixel standard events](https://developers.facebook.com/docs/meta-pixel/reference/standard-events/)
- [Meta Conversions API event deduplication](https://developers.facebook.com/docs/marketing-api/conversions-api/deduplicate-pixel-and-server-events/)

## Final status

```text
PHASE_1_STATUS=COMPLETE
PAYMENT_CODE_MODIFIED=NO
PAYMENT_GATEWAY_SETTINGS_MODIFIED=NO
CART_TOTAL_LOGIC_MODIFIED=NO
ORDER_PROCESSING_MODIFIED=NO
PRODUCTION_CHANGES=NONE
RAZORPAY_PAYMENT_SELECTION_SIGNAL=UNVERIFIED/UNAVAILABLE via supported public integration; unsupported
WOOCOMMERCE_CLASSIC_SUPPORT=FEASIBLE read-only browser observer; staging validation required
WOOCOMMERCE_BLOCKS_SUPPORT=DOCUMENTED read-only payment store selector; version/runtime validation required
META_DUPLICATION_RISK=LOW in repository; HIGH/UNKNOWN on deployed store pending tag inventory
PAYMENT_SAFETY_RISK=LOW with isolated non-blocking observer; unvalidated until payment invariance tests pass
PHASE_2_READY=NO — tag ownership, consent integration, and staging compatibility are blockers
```

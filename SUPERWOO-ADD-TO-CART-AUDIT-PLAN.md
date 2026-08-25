# SuperWoo Product Quantity Doubling: Audit and Remediation Plan

## Status

**Root cause identified and fixed.** The temporary diagnostic mode remains
available and off by default for verification.

## Reported behaviour

On a product page, selecting quantity `2` and pressing Add to Cart results in
quantity `4` in the cart. The report needs one clarification during testing:

- **Case A:** an empty cart receives four units from one click.
- **Case B:** the cart already has two units and a click at quantity two raises
  the line to four. This is standard WooCommerce additive-cart behaviour, but
  may still be different from the desired UX.

The plan distinguishes these cases before changing cart semantics.

## Read-only code audit findings

### SuperWoo add path

`public/js/cart-drawer.js` sends normal product submissions to the custom
WordPress AJAX action `superwoo_add_product_to_cart`.

`includes/class-cart-drawer.php::ajax_add_product_to_cart()` then:

1. reads the submitted `quantity`;
2. validates the product and variation;
3. calls `WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation)` once.

There is no arithmetic in this PHP path that changes `2` to `4`.

### Other cart mutations in this plugin

- Cart drawer cross-sells add one unit only from their separate endpoint.
- Bundle offers can add a separate item marked `superwoo_free_gift`; they do
  not add another paid copy of the requested product.
- Currency code alters prices only.
- Cart fragment refreshes recalculate/render the cart; they do not add products.

### Duplicate-request risk

SuperWoo currently intercepts both native add-button clicks and form submits,
then replaces them with its own AJAX request. A JavaScript in the active theme,
Elementor, WooCommerce extension, cache/minification layer, or a second copy of
the script can also react to the same interaction. Such a second request may
use either SuperWoo's endpoint or WooCommerce's native endpoint.

The existing SuperWoo action-ID guard only protects duplicate calls reaching
`superwoo_add_product_to_cart`. It cannot prevent a second request sent to a
different WooCommerce/theme endpoint. Therefore it is not enough evidence to
declare the issue fixed.

## Root-cause hypothesis

**Most likely, unconfirmed:** one user click produces two add-to-cart network
requests, each with quantity two. This exactly explains an empty cart receiving
four units.

**Alternative:** if the line already contains two units, WooCommerce is
correctly adding the selected two more units. This is expected WooCommerce cart
behaviour, not a duplicate request.

No repository-only audit can distinguish those alternatives. Browser Network
evidence from the affected product page is required.

## Confirmed root cause

The browser capture showed one request with `quantity=2` and one cart line at
quantity four. In SuperWoo's custom endpoint, the plugin manually called
`woocommerce_add_to_cart_validation` and then called `WC_Cart::add_to_cart()`.
WooCommerce runs that same validation filter internally, so validation callbacks
ran twice for every SuperWoo product-add request. A callback with cart side
effects can consequently add the requested quantity twice.

The redundant manual validation call was removed. WooCommerce now remains the
single owner of validation inside `WC_Cart::add_to_cart()`.

## Proposed diagnostic phase (no cart behaviour changes)

### How to run the implemented diagnostic

1. In **WooCommerce → SuperWoo → General → Cart Drawer**, enable
   **Temporarily log product Add to Cart diagnostics** and save.
2. Open the affected product in a private/incognito mobile-sized browser with
   an empty cart. Open DevTools **Network** (Preserve log) and **Console**.
3. Set quantity to two, click Add to Cart exactly once, then save the Network
   request list/HAR and the matching PHP error-log lines beginning with
   `SuperWoo add-to-cart diagnostic`.
4. Disable the setting immediately after the capture.

The diagnostic records no customer data. It logs only request ID, request path,
product/variation IDs, requested quantity, matching cart quantity, and stage
(`received`, `added`, or `duplicate_skipped`).

1. Add a temporary, opt-in diagnostic mode for SuperWoo product adds.
   It records a request ID, requested quantity, product/variation IDs, request
   URI, and cart quantities before/after the SuperWoo endpoint.
2. Add browser console diagnostics only when that mode is enabled. It records
   which SuperWoo handler ran and its request ID; it does not collect customer
   data.
3. Reproduce with an empty cart and quantity two while preserving the Network
   tab HAR or request list.
4. Classify each request:

   | Result | Meaning | Next action |
   | --- | --- | --- |
   | Two `superwoo_add_product_to_cart` requests | SuperWoo request duplication | Make one canonical SuperWoo submission path and retain server idempotency. |
   | One SuperWoo request plus Woo/theme `wc-ajax` or `?add-to-cart=` request | Handler conflict | Stop SuperWoo from intercepting the conflicting native path; retain one authoritative path. |
   | One request and pre-existing line quantity was two | Normal additive cart semantics | Confirm desired behaviour before changing semantics. |
   | One request, empty cart, quantity becomes four | External filter/plugin mutation | Capture WooCommerce hook/filter diagnostics and identify the responsible callback. |

## Proposed implementation after diagnosis

Only the branch proven by diagnostics will be applied.

### If duplicate requests are proven

- Make the product form use exactly one submission route.
- Keep the sticky bar as a proxy to that same route, never a second route.
- Retain short-lived server idempotency for retries/double taps.
- Remove redundant interception rather than attempting to mask duplicates by
  changing quantities.

### If normal additive behaviour is the issue

- Do not change WooCommerce by default.
- Obtain explicit approval for "set cart line to selected quantity" semantics,
  because it changes how repeated Add to Cart clicks behave site-wide.

### If an external callback mutates the cart

- Identify the exact callback and change only the integration point proven to
  cause the extra add. Do not modify WooCommerce core, Razorpay, or HPOS.

## Regression matrix for the approved fix

Run each with an empty cart and an existing cart line:

1. Simple product, quantities 1, 2, and 3.
2. Variable product after selecting a valid variation.
3. Original product-page button.
4. Mobile sticky Add to Cart bar.
5. Rapid double tap and slow repeated clicks.
6. Logged-out and logged-in sessions.
7. Add-to-cart AJAX response, cart drawer count, and cart line quantity.
8. Bundle-offer free gifts, ensuring paid-product quantity is unchanged.
9. Cart, checkout, and Razorpay checkout initiation.

## Scope guardrails

- No WooCommerce core, Razorpay, HPOS, order status, stock, or historic-order
  changes.
- No change to product-page design or unrelated cart functionality.
- Diagnostics are temporary/opt-in and will be removed after the root cause is
  verified.

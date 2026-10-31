# Phase 12 Browser Evidence

All storefront data and products in these captures are synthetic and came from the disposable local WooCommerce installation. Generated PNG product art is geometric placeholder artwork created locally for layout testing; it is not product photography. Captures are visual evidence, not a pixel-perfect comparison against the approved reference.

## Captures

- `empty-390.png` — empty drawer at 390px.
- `one-product-{1440,1280,1024,768,430,390,375,320}.png` — one product at the eight required widths. The browser journey waited for the drawer transform to finish before capture.
- `multiple-products-390.png` — main product plus a synthetic related product and recommendation cards.
- `gift-unlocked-390.png` — gift threshold reached; free shipping is also active under the disposable site's configured threshold.
- `offer-10pct-390.png` — ₹4,194 eligible paid subtotal, one 10% tier reduction of ₹419.40, total ₹3,774.60.
- `coupon-applied-390.png` — synthetic coupon applied before it was removed in the journey.
- `checkout-populated-390.png` — populated checkout page reached; no form submission or order was made.
- `one-product-landscape-844x390.png` — cart in landscape viewport.

The automated journey asserted product/cart state, quantity updates, coupon apply/remove, recommendation add, reward transitions, Store API total (`377460` minor units) and checkout navigation. The browser was loopback-only; all non-local requests were blocked. The browser run used the sandbox-enabled Chromium configuration earlier in this Phase 12 session. A later attempt to rerun the browser suites failed before page execution because Chromium could not initialize its macOS sandbox in the current tool environment.

## Not captured

No standalone shipping-only, shipping-unlocked-only, loading, error, RTL, reduced-motion, safe-area, or virtual-keyboard screenshots were captured. The latter transient states were not claimed as visually accepted.

# Smart Cart Architecture

## Data and request flow

1. The drawer is rendered from the initialized WooCommerce cart/session.
2. Quantity, remove, add, coupon, and refresh actions are sent to same-site WooCommerce AJAX endpoints, with the existing SuperWoo nonce and queued cart request pipeline.
3. Coupon codes are normalized and validated by WooCommerce `WC_Cart::apply_coupon()`; removal uses `WC_Cart::remove_coupon()`. The browser never submits a price or discount amount.
4. Mutations pass through the existing `send_fragments()` path, which calculates totals once, persists the WooCommerce cart session, then returns freshly rendered cart markup.
5. The existing offer engine remains responsible for promotional prices and gift items. Shipping remains WooCommerce-calculated; SuperWoo does not set a shipping price.
6. Recommendation quick-add is restricted to visible, purchasable, in-stock simple products. Variable products need a native option-selection step and are excluded from one-click add.

## Security and isolation

- Every state-changing cart endpoint calls `verify_ajax()` for WooCommerce availability and the cart nonce.
- Admin values are saved through the existing capability-protected settings handler, allowlisted values, sanitizers, and shared-option merge behavior.
- Trust copy is merchant entered and escaped on render. Empty default copy prevents unsupported commercial claims.
- This change adds no order lifecycle, gateway, payment-status, checkout submission, or payment processor hooks.

## Progress presentation

The shared reward card now presents shipping and offer progress independently. The shipping message and its amount bar use the configured threshold only when WooCommerce has calculated shipping and returned a chosen rate; an actual zero-cost selected rate is required before claiming free delivery is applied. The existing offer renderer supplies its own goal and the existing bundle-offer class continues to own discount/gift eligibility. This UI does not change shipping charges or add a second gift engine.

## Known design gaps

- A cart may show an eligible shipping goal and the offer engine's current goal together. It does not yet enumerate every future offer tier at once, nor has every store-specific shipping-zone/coupon combination been validated.
- Coupon application is implemented but should be tested against the exact WooCommerce release and coupon extensions before production use.
- Visual token inheritance still depends on the existing drawer CSS variables and theme overrides; cross-theme screenshots remain outstanding.

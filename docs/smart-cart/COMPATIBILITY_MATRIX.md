# Smart Cart Compatibility Matrix

| Environment / feature | Status | Evidence / limitation |
|---|---|---|
| WooCommerce cart/session | Source verified | Uses `WC()->cart`; writes through existing session persistence path. |
| Native coupon validation | Implemented, runtime unverified | New apply/remove routes call WooCommerce cart APIs and recalculate through shared fragments. |
| Native free shipping | Partial | Existing code requires calculated shipping and a real zero-cost chosen rate before stating delivery is free. |
| SuperWoo offer gifts/discounts | Reused, not redesigned | Existing offer lifecycle remains authoritative. |
| Combined shipping/offer progress | Verified in source and isolated runtime | Separate bars share one card; shipping claim is suppressed until WooCommerce has calculated a selected shipping rate. |
| Offer lifecycle integration | PASS on one disposable runtime | 129 checks on WP 7.1.3 / WC 11.2.1 / PHP 8.5.11; no orders or payments. Not a full version/extension matrix. |
| Simple product recommendations | Source verified | Visible, purchasable, in-stock simple items only. |
| Variable recommendation products | Unsupported in one-click card | Requires selection/validation UI; omitted rather than bypassing WooCommerce. |
| WooCommerce Blocks cart mutation | Partial | Existing Store API bridge remains; coupon cart behavior on Blocks/runtime not tested here. |
| Classic checkout | Preserved by link | No custom payment or checkout execution added. |
| HPOS | Not tested | This cart UI change does not read/write order storage. |
| Elementor and theme cart triggers | Existing adapters retained | No matrix rerun in this task. |
| Third-party coupon, product bundle, decimal quantity, custom cart extensions | Unverified | Requires per-extension staging tests. |
| RTL, keyboard, reduced motion | Partially inherited | Existing drawer behavior retained; new coupon/trust controls use native semantic elements; full accessibility audit not run. |

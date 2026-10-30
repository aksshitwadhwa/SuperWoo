# Cart drawer progress and responsiveness changes

## Implemented locally

- Delivery copy is no longer returned for the separate green top banner. It is rendered inside the existing offer progress card, preserving the gift/discount goal and its bar.
- If no offer bar exists, a delivery-only cart receives the same progress presentation. Delivery is described as free only when WooCommerce has selected an actual zero-cost shipping rate. This presentation never changes shipping rates or totals.
- Consent text is 10px; total rows have line-height 2; grand-total top padding is 3px; recommendation Add buttons are 15px / weight 600.

## Delay sources and changes

The old drawer opened on `added_to_cart`, after the add completed, and `openCart()` could wait for the page-load drawer refresh. Custom writes also queued behind that refresh. Successful adds then fetched a count that was already available in the returned fragments.

The drawer now opens on WooCommerce's `adding_to_cart` lifecycle, or when a validated SuperWoo add begins. A loading status covers unconfirmed content until WooCommerce returns the real cart. Product rows, quantities and prices are not invented optimistically. Checkout remains blocked during this pending state, and failures retain the last confirmed cart with an error. Closing the drawer while a request runs remains effective when its response arrives.

Full drawer content is refreshed on demand instead of issuing a competing page-load request for a closed drawer. Older read responses are rejected if another add has since started. Native archive adds continue using WooCommerce's own request/queue; SuperWoo does not submit a second native add. SuperWoo-owned writes remain serialized.

The existing SuperWoo add, recommendation and refresh handlers are also registered on the WooCommerce storefront AJAX route, with the existing nonce checks and admin-AJAX fallback. Own responses render the inner drawer once instead of separately rendering duplicate notices, footer totals and checkout markup. Recommendation adds no longer calculate/persist a second time immediately before the common response handler does that work. Counts already supplied by confirmed fragments do not trigger another Store API count request.

## Verification

- All **19 PHP test files passed**, including offer pricing and order/payment isolation.
- All **33 Node/browser tests passed**. A new isolated Chrome fixture uses a simulated **400 ms** add response and verifies immediate opening before completion, pending feedback, unchanged unconfirmed totals, checkout blocking, successful updates, network failure, close intent, on-demand refresh, rejection of a late stale read, storefront URLs, and no redundant plugin count/startup reads.
- The delivery rendering test covers a shared offer card, a delivery-only bar, actual free shipping, unknown shipping, disabled thresholds, and paid shipping above the promotional threshold. It rejects any attempt to recalculate shipping while rendering.
- PHP lint passed across **91 files** including runtime, tests, plugin entry point and uninstall file. JavaScript syntax checks and `git diff --check` passed.

These checks demonstrate frontend behavior under simulated latency and removal of specific redundant work. No live server response-time benchmark was performed; hosting, WooCommerce, theme and other extension latency can still affect confirmation time. No checkout, production cart, payment, order, database or hosting configuration was changed for this work.

## Release state

The changes are in the local worktree and documented under **Unreleased** in `readme.txt`. The published 1.6.26 package remains immutable and contains the earlier offer-pricing fix; it has not been replaced with these UI changes. These changes are ready for the next plugin release. The earlier merge/push approval block is not retried by this task.

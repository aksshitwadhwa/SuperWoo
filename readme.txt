=== SuperWoo ===
Contributors: Aksshit Wadhwa
Tags: woocommerce, cart drawer, product benefits, faq, bundle discounts
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.205
License: GPLv2 or later

SuperWoo adds WooCommerce product benefit icons, how-to content, product FAQs, shoppable videos, bundle offer rules, cart notices, and an AJAX cart drawer.

When Shoppable Videos is enabled, SuperWoo stores pseudonymous, on-site video engagement events (views, product clicks, add-to-cart actions, and completions) for 90 days. It does not send those events to an external service. If Multi-Currency location detection or an exchange-rate provider is enabled, the site administrator is responsible for reviewing that provider's privacy policy and informing visitors as required.

== Description ==

SuperWoo converts the current WPCode snippet features into a reusable plugin while preserving existing data keys:

* `product_benefit` taxonomy
* `benefit_icon` term meta
* `product_how_to_use` product meta
* `product_faqs` product meta
* `pbi_bundle_rules` option

Shortcodes:

* `[product_benefits]`
* `[product_benefits id="123"]`
* `[product_faqs]`
* `[product_faqs id="123"]`
* `[bundle_offers_notice]`
* `[superwoo_product_reviews]`
* `[superwoo_product_reviews id="123"]`
* `[superwoo_cart_button]`
* `[superwoo_shoppable_videos]`

Elementor:

* Dynamic Tag: `SuperWoo Cart Drawer Trigger`
* Use it in an Elementor link URL field, such as an Icon widget link, to open the cart drawer.
* Links using the trigger automatically receive a live cart-count badge.

== Installation ==

1. Upload the `superwoo` folder to `wp-content/plugins/`.
2. Activate `SuperWoo` from WordPress admin.
3. Disable the old WPCode snippet after confirming the plugin is active.
4. Configure settings under WooCommerce > SuperWoo.
5. Configure offer rules under WooCommerce > Offers.

== Changelog ==

= 1.0.205 =
* Avoid no-op quantity updates that caused third-party product-page hooks to add a second unit.

= 1.0.204 =
* Preserve the exact requested product-page quantity when third-party WooCommerce hooks mutate a newly added cart line.

= 1.0.203 =
* Prevent duplicate simple and variation product additions when frontend scripts or handlers execute more than once.

= 1.0.202 =
* Ensure the initial product-page Add to Cart action adds exactly one unit, including variation products with stale quantity inputs.

= 1.0.201 =
* Remove a cart line when decrementing quantity one and restore the product-page Add to Cart action.

= 1.0.200 =
* Protect variation cart lines from third-party quantity mutations so one Add to Cart action adds exactly the requested quantity.

= 1.0.199 =
* Normalize mini-cart checkout taps to Razorpay's button target so Magic Checkout opens reliably on mobile.

= 1.0.198 =
* Force Razorpay Magic Checkout to use the full mobile viewport and hide SuperWoo sticky navigation while checkout is open.

= 1.0.197 =
* Fix product-to-cart quantity matching by normalizing native and jQuery form references before cart-line lookup.

= 1.0.196 =
* Keep single-product and cart-drawer quantities synchronized using the confirmed WooCommerce cart value.

= 1.0.195 =
* Process each product quantity click once and reset quantity-button margins to zero.

= 1.0.194 =
* Normalize delayed theme quantity controls so product actions contain exactly one minus and one plus button.

= 1.0.193 =
* Add products from single-product pages through validated WooCommerce AJAX without refreshing the page.

= 1.0.192 =
* Preserve the established product-button design while showing quantity only after the real Add to Cart control is clicked.

= 1.0.191 =
* Present Add to Cart and Buy Now as equal-width product actions and replace Add to Cart with inline WooCommerce quantity controls after selection.

= 1.0.190 =
* Preserve product-link click targets by capturing the pointer only after a genuine carousel drag begins.

= 1.0.189 =
* Make carousel initialization reliable when scripts are delayed and exclude the essential carousel runtime from WP Rocket Delay JavaScript.

= 1.0.188 =
* Preserve product-card clicks in looping carousels by requiring a deliberate drag before suppressing navigation and keeping boundary slides pointer-accessible.

= 1.0.187 =
* Replace Shop Filter price fields with a WooCommerce-priced dual-handle range slider that works with automatic filtering.

= 1.0.186 =
* Make Shop Filter visibility settings apply globally to existing and new Elementor widgets as well as shortcodes.

= 1.0.185 =
* Add an accessible mobile filter toggle that expands all shop filters and remains open across automatic filter refreshes.

= 1.0.184 =
* Add automatic shop-filter application with debounced search and price inputs, immediate selection updates, and safe Elementor editor behavior.

= 1.0.183 =
* Ensure Slides to Show overrides Elementor Columns immediately in the initial Elementor editor preview as well as on the public page.

= 1.0.182 =
* Make Elementor carousel controls update in real time across the editor and preview iframe, with clean reinitialization and no duplicate generated UI or event handlers.

= 1.0.181 =
* Restore immediate Elementor editor preview updates for carousel heading, CTA, and interaction controls; improve horizontal drag responsiveness.

= 1.0.180 =
* Simplify carousel arrows to one vertically centered left/right placement; retain responsive pixel offsets for fine positioning.

= 1.0.179 =
* Harden the Elementor Products Carousel with accessible autoplay controls, off-screen focus management, seamless boundary looping, drag safety, RTL handling, live announcements, lazy initialization, and horizontal-only wheel navigation.
* Add optional carousel heading, subheading, accessibility label, and View All controls.

= 1.0.178 =
* Expand structured diagnostics for cart changes, cart-drawer AJAX failures, checkout validation, order creation, payment completion, and failed orders.
* Add safe context redaction, request IDs, log rotation, and a Logs screen that includes fatal errors.

= 1.0.177 =
* Add Shoppable Videos with native video management, collections, timed product cards, WooCommerce cart attribution, optional Quick Buy, product-page placement, analytics, shortcodes, and Elementor support.
* Add accessible fullscreen viewing, WordPress Media Library selection, SKU-aware product search, and explicit privacy/retention disclosure for local video engagement analytics.

= 1.0.176 =
* Add optional infinite mouse-wheel and trackpad scrolling for product carousels.

= 1.0.175 =
* Add global Shop Filter visibility options for search, category, price, attributes, availability, sale, rating, and sorting controls.

= 1.0.174 =
* Correct Elementor mobile and tablet carousel preview sizing by using the active preview device values.

= 1.0.173 =
* Add responsive left and right arrow pixel offsets for product carousels, including negative values.

= 1.0.172 =
* Update the Elementor carousel preview immediately when carousel controls and responsive settings change.
* Add per-filter visibility controls to the Shop Filters widget and star-rating filter choices.

= 1.0.171 =
* Register the SuperWoo Shop Filters Elementor widget in the Elementor editor widget library.

= 1.0.170 =
* Add reusable Shop Filters with shortcode and Elementor widget support for search, categories, price, attributes, stock, sale, rating, and sorting.
* Add carousel product-limit and arrow-icon controls, plus live Elementor editor preview for carousel settings.
* Hide native quantity-input spinner controls when SuperWoo minus/plus buttons are present.

= 1.0.169 =
* Keep the cart drawer checkout button text and icon visible on hover and keyboard focus.
* Include the latest product quantity-control styling updates.

= 1.0.168 =
* Show the calculated WooCommerce grand total on the cart drawer checkout button.
* Refine variation-card controls by removing the surrounding shaded panel, duplicate variation price, and reset link.
* Normalize plus/minus quantity control spacing across themes.

= 1.0.149 =
* Prepare the WordPress.org submission build with GPL metadata, privacy disclosure, and WordPress-managed updates.

= 1.0.145 =
* Improve diagnostics and resolve the previous GitHub updater issue in the development distribution.

= 1.0.144 =
* Remove SuperWoo's duplicate Razorpay Magic Checkout script, metadata and direct-order request so Woo Razorpay exclusively controls the native Buy Now flow.
* Route the cart drawer checkout button through the standard WooCommerce checkout URL.

= 1.0.143 =
* Restore raw WooCommerce catalog prices before and after Razorpay 1CC calculates payment totals, preventing temporary ₹1 values from reaching checkout.
* Isolate frontend cart and pricing modules from normal WordPress admin requests and record SuperWoo fatal activation errors.
* Close the cart drawer when customers click outside it.

= 1.0.115 =
Remove the SuperWoo percentage badge from single-product prices and variation price data, leaving native sale prices unchanged.

= 1.0.114 =
Hide theme discount spans only inside the open cart drawer while leaving page badges visible.

= 1.0.113 =
Set mobile variation cards to a consistent 200px width.

= 1.0.112 =
Keep mobile variation forms and selectors full width while limiting the 30/70 quantity and Add to Cart grid to the purchase controls.

= 1.0.111 =
Keep variation cards on one horizontally scrollable mobile row, contain page overflow, and hide the row scrollbar without disabling touch scrolling.

= 1.0.110 =
Remove SuperWoo's duplicate product-card sale flash and its obsolete badge-hiding cleanup so WooCommerce or the active theme owns the existing sale badge.

= 1.0.109 =
Consolidate cart-drawer.css: removed every duplicate rule and merged conflicting overrides into one declaration per selector (207 rules / 1942 lines down to 153 rules / 1639 lines), removed `!important` from same-origin overrides (kept only the theme-compat, Elementor, mobile-layout and badge force rules), compacted the drawer header/title/count and bundle-notice styling, removed the redundant dashboard inline style, guarded duplicate bundle-offers CSS enqueues, and restored the product-card discount badge fallback. See CSS-AUDIT.md.

= 1.0.106 =
Keep cart drawer cross-sell Add buttons from becoming overly rounded on hover while preserving the hover color change.

= 1.0.105 =
Refine cart drawer typography by keeping product title weight stable on hover and reducing the Your Cart heading and checkout button size/weight.

= 1.0.104 =
Force the single-product sale-price percentage discount badge to render as the green pill even in theme price widgets outside the standard summary markup.

= 1.0.103 =
Place the single-product percentage discount badge beside the sale price itself, including Elementor price widget fallbacks, using the existing green badge design.

= 1.0.102 =
Lock mobile inline product quantity and Add to Cart controls onto the same row and make custom minus/input/plus quantity controls fit inside the 30% column.

= 1.0.101 =
Stop mobile Buy Now layout code from forcing form.cart to display block and apply the 30/70 inline product purchase grid directly with inline priority.

= 1.0.100 =
Apply the mobile 30/70 product-page purchase layout to inner WooCommerce and Elementor add-to-cart wrappers so inline quantity, Add to Cart, and Buy Now align correctly.

= 1.0.99 =
Fix mobile sticky and inline product purchase layouts with 30/70 quantity and Add to Cart rows, and place Buy Now full width below the inline row.

= 1.0.98 =
Hide the cart drawer delivery notice unless an offer is active or an Offers free-delivery threshold is configured, and add the threshold setting to the Offers admin page.

= 1.0.97 =
Add a live fallback that inserts the percentage discount badge into single-product Elementor/theme price widgets and keeps it on the same row.

= 1.0.96 =
Use the requested green percentage discount badge styling, set badge font weight to 400, and show the percentage badge beside the main single-product price.

= 1.0.95 =
Replace generic WooCommerce Sale badges with green percentage discount badges and keep theme percentage discount badges visible.

= 1.0.94 =
Remove all remaining SuperWoo discount badge hiding logic and force product sale/discount badges back to visible on shop and product pages.

= 1.0.93 =
Show active offer-applied messages in the cart drawer's delivery notice strip and remove the separate offer popup notification.

= 1.0.92 =
Fix duplicate single-product add-to-cart handling, refresh cart drawer offer/product/checkout styling, keep sale badges visible outside the drawer, make product-card buttons full width, and restore the inline mobile Add to Cart button alongside the floating bar.

= 1.0.90 =
Merge product review photo and video uploads into one designed media upload field while preserving separate image/video display.

= 1.0.89 =
Add product review testimonial video uploads alongside photo uploads, save both as review media, and render playable videos in review cards.

= 1.0.88 =
Keep offer-applied popup notifications visible above the cart drawer and move them beside the open drawer on desktop.

= 1.0.87 =
Prevent offer logic from increasing paid product quantities, avoid optimistic cart-count doubling, and show clearer cart-updated offer popups with discount or free gift worth.

= 1.0.86 =
Move offer-applied notifications to the site bottom-right and send explicit AJAX offer events when discounts or free gifts unlock.

= 1.0.85 =
Keep cart drawer notices to one flat and one price-range offer when configured, and add right-side AJAX toasts when a discount unlocks or a free gift is actually added.

= 1.0.84 =
Show contextual cart-drawer offer notices by selecting the next quantity discount milestone and next price gift milestone instead of rendering every configured offer.

= 1.0.83 =
Harden Offers AJAX saving so Save button clicks and form submits use one reliable request path and refresh the list after saving.

= 1.0.82 =
Make Offers product search return selectable variations for variable products so admins choose the exact variant in the offer setup.

= 1.0.81 =
Show selectable product variations in Offers product search when variable products match, with clean variation names, thumbnails, and prices.

= 1.0.80 =
Keep Offers product-search prices readable on highlighted results and show only the product name in selected product chips.

= 1.0.79 =
Simplify Offers product search output to clean product name, thumbnail, and current price only.

= 1.0.78 =
Show product prices alongside thumbnails and names in Offers product search dropdowns and selected product chips.

= 1.0.77 =
Add inline editor delete, show product thumbnails in offer product selectors, and refresh the Offers list from AJAX responses after save, delete, and status changes.

= 1.0.76 =
Make Edit use the same inline AJAX Offers editor as Create, keeping the offer list visible while loading and saving edits.

= 1.0.75 =
Keep Create New Offer on the Offers list page with an inline AJAX editor, update the visible list after saving, and label the offer title field clearly.

= 1.0.74 =
Rework Offers admin into AJAX-powered list and dedicated create/edit pages, open Create New Offer in a new tab, and allow multiple free products in one price-range gift offer.

= 1.0.73 =
Add PHP, CSS, and JavaScript dashboard safeguards so the mobile bottom navigation is removed on /dashboard/ even if cached markup is present.

= 1.0.72 =
Keep the mobile bottom navigation hidden on dashboard URLs, including child dashboard paths and subdirectory installs.

= 1.0.71 =
Restore mobile bottom navigation clicks by fully hiding the search sheet layer when closed.

= 1.0.70 =
Allow mobile page scrolling while the product search panel is open.

= 1.0.69 =
Remove the dark page overlay behind the mobile product search panel.

= 1.0.68 =
Force bottom-nav Search to use the SuperWoo product search panel only, avoid review-search hijacking, and harden mobile search sheet layout.

= 1.0.67 =
Improve the mobile search sheet spacing and hide the product sticky Add to Cart bar while search is open.

= 1.0.66 =
Set the mobile bottom navigation active underline color to the site blue.

= 1.0.65 =
Restyle the mobile bottom navigation with a rounded floating layout, website color variables, active states, separators, and a stronger cart badge.

= 1.0.64 =
Hide the mobile bottom navigation on the dashboard page so it does not conflict with dashboard bottom controls.

= 1.0.63 =
Point the mobile bottom navigation Account icon to the site dashboard page.

= 1.0.62 =
Improve mobile bottom navigation spacing so the product sticky Add to Cart bar does not overlap icons or the cart count badge.

= 1.0.61 =
Add a mobile bottom navigation bar with Home, Search, Account, Wishlist, and Cart icons, plus a mobile search sheet and product sticky Add to Cart spacing above the nav.

= 1.0.60 =
Keep the desktop Add to Cart layout stable during AJAX by intercepting native theme click handlers and showing a spinner-only pending state.

= 1.0.59 =
Prevent desktop product Add to Cart from reflowing during AJAX submission by avoiding theme loading classes and locking the button dimensions until the request completes.

= 1.0.58 =
Force the Razorpay PDP Buy Now button and its form wrapper to full width on mobile when the sticky Add to Cart bar is active.

= 1.0.57 =
Make the mobile Buy Now button and its theme wrapper full width, with top spacing reset, while keeping the sticky quantity Add to Cart bar.

= 1.0.56 =
Audit mobile product buttons by behavior/text so Buy Now stays visible even when the theme gives it WooCommerce add-to-cart classes, while only the native Add to Cart button is hidden behind the sticky bar.

= 1.0.55 =
Keep mobile Buy Now visible, preserve the sticky quantity Add to Cart bar, and prevent initial product-page layout flash before SuperWoo enhancements load.

= 1.0.54 =
Package manual plugin updates.

= 1.0.53 =
Add mobile product sticky Add to Cart quantity bar, hide duplicate inline mobile cart buttons, and strengthen sale regular-price strikethrough styling.

= 1.0.52 =
Use IP/location-only currency auto-detection and fall back to INR when visitor location is unavailable.

= 1.0.51 =
Improve visitor currency detection by honoring currency query requests, CDN country headers, WooCommerce geolocation fallback, and uncacheable multi-currency pages.

= 1.0.50 =
Update multi-currency exchange-rate integration for Currencylayer live API, including access_key/source/currencies URL defaults and quotes parsing.

= 1.0.49 =
Restyle the Multi-Currency settings tab with a compact card layout, labeled currency defaults, and cache hours.

= 1.0.48 =
Split the SuperWoo settings screen into General and Multi-Currency tabs.

= 1.0.47 =
Add settings-driven multi-currency support with INR base prices, detected/default visitor currency, exchange-rate API/cache/manual fallback rates, per-product manual currency extras, converted checkout/cart totals, and Razorpay Magic Checkout currency context.

= 1.0.46 =
Hide inline mobile Buy Now controls when SuperWoo's sticky mobile Buy Now button is active.

= 1.0.45 =
Remove mobile variation card indentation and make the mobile sticky Buy Now button edge-to-edge.

= 1.0.44 =
Harden mobile variation card equal sizing and render the mobile sticky Buy Now button from the product page footer.

= 1.0.43 =
Normalize mobile variation card sizing and add a mobile sticky Buy Now button on product pages.

= 1.0.42 =
Stack variable product option cards in a mobile column while keeping the desktop one-row layout.

= 1.0.41 =
Keep variable product option cards in one horizontal row with responsive overflow when space is limited.

= 1.0.40 =
Add variable product option cards with title and price display, using WooCommerce variation data and site color styling.

= 1.0.39 =
Package current plugin state after manual changes.

= 1.0.38 =
Vertically center the empty cart drawer message and Continue Shopping button in the drawer body.

= 1.0.37 =
Clamp cart drawer cross-sell titles to two lines and center the empty-cart Continue Shopping button.

= 1.0.36 =
Center the external Elementor reviews View All button and make it load more reviews in place only when more reviews are available.

= 1.0.35 =
Add product photo upload support to the SuperWoo review form and display uploaded review images in the modern reviews layout.

= 1.0.34 =
Render the reviews View All button only when more reviews exist, keep it as an in-place load-more action, and center the Elementor-style button text span.

= 1.0.33 =
Center the reviews View All button and strengthen website font-family inheritance across the modern reviews section.

= 1.0.32 =
Refine the modern reviews section to match the reference layout more closely, keep neutral review styling inside Product Data Tabs, use site colors only for action buttons, and fix filter hover states.

= 1.0.31 =
Replace the WooCommerce product reviews tab content with a modern SuperWoo reviews layout, including summary bars, search, rating filters, sorting, review cards, image-meta support, and the standard WooCommerce review form.

= 1.0.30 =
Open Razorpay Magic Checkout from the SuperWoo cart drawer Pay Now button when Magic Checkout is active, and remove the drawer's order-pay redirect path.

= 1.0.29 =
Change drawer Pay Now from a checkout link to a direct payment starter that creates a pending order and redirects to the WooCommerce order payment URL.

= 1.0.28 =
Fix cart drawer full-fragment replacement so product rows render again when subtotal/count update.

= 1.0.27 =
Clamp cart drawer quantities to one, keep removal on the remove button only, always show Pay Now for non-empty carts, and force full drawer refresh after cross-sell adds.

= 1.0.26 =
Add realtime cart drawer offer-notice fragments and persist recalculated offer/gift cart state before rendering drawer updates.

= 1.0.25 =
Prevent duplicate single-product AJAX add submissions and avoid stale fragment races that can show incorrect drawer subtotals.

= 1.0.24 =
Add AJAX add-to-cart support for single product pages and open the cart drawer after successful add.

= 1.0.23 =
Adjust Elementor cart counter badge position to sit farther outside the icon.

= 1.0.22 =
Harden Elementor cart icon trigger clicks, open from `#superwoo-cart` hash loads, re-attach live counters after Elementor/header rerenders, and sync count through the WooCommerce Store API.

= 1.0.21 =
Add an automatic live cart-count badge to Elementor icon/link widgets using the SuperWoo cart drawer trigger.

= 1.0.20 =
Add an Elementor URL dynamic tag for triggering the SuperWoo cart drawer from Icon/link widgets.

= 1.0.19 =
Hide theme `<span class="discount">` sale labels only inside the SuperWoo cart drawer while keeping page product-card badges visible.

= 1.0.18 =
Audit persistent floating percentage badges with text-node detection, hide overlapping badge wrappers, and restore them after closing the drawer.

= 1.0.17 =
Improve floating percentage badge detection to hide small wrapper badges overlapping the drawer.

= 1.0.16 =
Hide only discount badges that overlap the cart drawer, while keeping normal product-card badges visible.

= 1.0.15 =
Make cart drawer buttons inherit site/theme primary and secondary colors.

= 1.0.14 =
Remove broad page-hiding badge CSS and add direct realtime subtotal/pay button fragments.

= 1.0.13 =
Audit cart drawer layout, remove visible backdrop, measure scroll offsets, and ensure one fixed checkout/pay button.

= 1.0.12 =
Rework cart drawer into fixed header, scrollable body, fixed footer, lighter backdrop, and stronger sale badge hiding.

= 1.0.11 =
Show Pay Now only for logged-in users, use checkout redirect for guests, and hide floating sale badges while drawer is open.

= 1.0.10 =
Fix cart drawer remove/quantity-zero behavior and avoid stale fragment refresh after removal.

= 1.0.9 =
Make cart drawer body scroll independently, keep Pay Now footer visible, and hide floating sale badges while cart is open.

= 1.0.8 =
Add offer scope controls for whole store, category, and specific products; improve admin product search UI.

= 1.0.7 =
Rebuild Offers admin UI and add two offer types: flat product discount and price-range free product.

= 1.0.6 =
Rename WooCommerce menu label from Bundle Offers to Offers.

= 1.0.5 =
Fix cart drawer scroll fallback and force product discount badges below the drawer layer.

= 1.0.4 =
Harden cart drawer quantity AJAX controls against theme script conflicts.

= 1.0.3 =
Fix drawer scrolling/title hover behavior and add bundle offer ranges with free product gifts.

= 1.0.2 =
Fix cart drawer vertical centering caused by aggressive theme layout styles.

= 1.0.1 =
Cart drawer theme-compatibility CSS hardening.

= 1.0.0 =
Initial plugin build.

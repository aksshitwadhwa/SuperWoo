# SuperWoo Smart Cart — Phase 14 Visual Report

**Date:** 2026-10-31  
**Evidence:** [Phase 14 screenshot gallery](evidence/phase-14/index.html)

## Capture environment

Screenshots were captured from the local loopback-only synthetic WooCommerce site using Chromium 147.0.7727.15 with its sandbox enabled. The generated placeholder product artwork is synthetic; it is not representative product photography. The approved reference image was not available as a local artifact, so no pixel-level reference match is claimed.

## Captured and inspected

- One-product drawer at 1440, 1280, 1024, 768, 430, 390, 375, and 320 CSS pixels.
- One-product landscape at 844 × 390.
- Empty cart, multiple products, gift threshold reached, 10% offer tier, coupon applied, checkout page opened without submission, and QA bootstrap smoke screenshot at 390px or the bootstrap viewport.
- Screenshots were inspected at 390px, 1440px, and the 10% cart state. No horizontal clipping was visible in the inspected 390px state. The 1440px capture showed the drawer fully open.

## Defect found and fixed

Progress values were white over both the green fill and pale gray track, making the text hard to read in the cart screenshot. The label, current value, separator, and target now use dark colors. For current default colors, calculated contrast is 5.83:1 against the fill (`#24a51f`) and 16.96:1 against the track (`#f1f3f1`). A regression assertion was added to `tests/cart-delivery-progress.test.php`.

## Remaining visual gaps

The state × viewport matrix is incomplete. Shipping-only, combined shipping plus gift, shipping unlocked alone, loading, error, invalid coupon, RTL, safe-area, and keyboard states do not all have dedicated screenshots at the requested widths. Tablet/phone landscape coverage beyond 844 × 390 was not captured. Product and recommendation placeholder art needs replacement with more representative synthetic packshots before final product-image visual acceptance.

The browser journey blocks all non-loopback network requests by design. Its console summary included 11 `net::ERR_FAILED` resource messages from that isolation policy; individual requested URLs were not recorded in the journey report. No interaction was performed against a live store.

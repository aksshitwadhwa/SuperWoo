# Smart Cart Visual Acceptance

## Reference adaptation

The current drawer already provides a right-side drawer, header, item list, progress card, recommendation carousel, order summary, and sticky footer. New content is inserted into that hierarchy. Store products, brand colors, shipping thresholds, and promotion values remain dynamic.

## Implemented visual changes

- Cart rows display current unit price and a distinct quantity/line-payable amount.
- Original pricing is shown only for an actual WooCommerce sale; the percentage badge also follows the existing merchant toggle.
- Inline coupons use a compact disclosure, input, action, and accessible status region.
- Trust information is compact, optional, and uses only merchant-provided wording.
- Recommendation headings distinguish explicit cross-sells from related-product fallback.

## Acceptance not yet verified

No screenshots were captured. Installed Chrome fails signature verification and aborts during startup. Playwright-managed Chromium with sandbox enabled, Firefox, and WebKit also abort before page execution. The in-app/Chrome browser connection inventory returned no connected browser. Therefore there are no valid screenshot paths to provide, and no screenshots from the supplied reference were misrepresented as test evidence. Details are in `PHASE_10_BROWSER_DIAGNOSTICS.md`.

The required 1440, 1280, 1024, 768, 430, 390, 375, and 320px viewports; empty/one/multiple-item, reward, coupon, recommendation, loading/error, and sticky-footer states; portrait/landscape; console inspection; keyboard walk-through; RTL; reduced-motion; and safe-area checks remain unverified. No visual fixes were made based on guesses without rendering evidence. Capture and inspect actual screenshots at these sizes when a working local browser is available. Do not treat the reference match as visually approved until those are exercised.

## Phase 11 visual addendum — 2026-10-31

The Phase 10 status above is historical. Phase 11 captured and inspected 12 screenshots in `evidence/phase-11/`: empty 390px; one product at all eight requested widths; gift/free-shipping reward; 10% offer; coupon applied. Mobile captures show the sticky summary and CTA at narrow widths without visible horizontal overflow. Visual acceptance remains **PARTIAL**: one desktop capture is mid-transition, synthetic product images are blank, and multiple-product, transient/reward, landscape, keyboard, RTL, reduced-motion, and safe-area states remain incomplete. The supplied reference is not saved locally, so no pixel-level comparison is claimed. See `PHASE_11_VISUAL_ACCEPTANCE.md`.

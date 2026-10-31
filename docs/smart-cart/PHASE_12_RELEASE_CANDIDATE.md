# SuperWoo Smart Cart — Phase 12 Release Candidate

**Phase 12 stop-point version:** SuperWoo `1.6.26`  
**Publication:** unpublished; no feed metadata, Git tag, push, or production update was created. A subsequent release-preparation task bumped local source to `1.6.27` and built `/tmp/superwoo-release-1.6.27/SuperWoo-1.6.27-CANDIDATE.zip`; that unsigned artifact was not published.  
**Decision:** **NOT READY for broad release** until the remaining verification gates in `PHASE_12_FINAL_ACCEPTANCE.md` pass.

## Contents of this candidate snapshot

The working tree includes the completed Smart Cart implementation through Phase 11 and the Phase 12 accessibility corrections:

- Keyboard focus remains within the open cart dialog; Escape continues to close it and restore focus to its opener.
- Cart drawer, mobile bottom navigation, and mobile search transitions honor `prefers-reduced-motion`.
- Regression coverage asserts the Tab/Shift+Tab boundary behavior, Escape restoration, and reduced-motion declarations.
- Existing cart, offers, coupons, recommendations, reward progress, order summary, consent, and checkout handoff code remains under WooCommerce's existing cart and checkout APIs.

## Files changed specifically in Phase 12 continuation

- `public/js/cart-drawer.js` — dialog keyboard focus containment and hidden-control exclusion.
- `public/css/cart-drawer.css` — reduced-motion styles for cart/mobile UI.
- `templates/cart-drawer-inner.php` — product-specific accessible name for quantity inputs.
- `tests/cart-drawer-browser.test.js` — browser assertions for focus wrapping, Escape, and focus restoration.
- `tests/cart-drawer-accessibility.test.php` — ten static accessibility guardrails.
- `docs/smart-cart/PHASE_12_FINAL_ACCEPTANCE.md` — acceptance evidence and gaps.
- `docs/smart-cart/PHASE_12_RELEASE_CANDIDATE.md` — this candidate record.
- `docs/smart-cart/evidence/phase-12/` — local synthetic screenshots and evidence README.

## Validation summary

- 22 standalone PHP test scripts pass, including ten accessibility static guardrails.
- 28 Meta Node tests pass.
- PHP lint passes for 96 files; JavaScript syntax checks pass; `git diff --check` passes.
- Guarded WooCommerce integration passed 129 checks earlier in Phase 12. A later rerun could not connect to its disposable MariaDB socket.
- The Phase 12 storefront journey passed and captured responsive screenshots earlier in this phase. A later browser-suite rerun was blocked before page execution by the macOS Chromium sandbox startup failure.
- Accessibility fixes have source guardrails and browser regression assertions, but no post-fix live browser behavior proof.

## Known limitations and release risks

- The final environment checkpoint cannot reproduce the WooCommerce or browser runs because the isolated MariaDB service and browser backend are unavailable.
- Visual coverage does not include every requested state, and reference-image pixel comparison was not performed.
- Accessibility coverage is incomplete: no manual screen-reader test, complete contrast measurement, RTL interaction, or post-fix browser validation.
- Merchant settings browser matrix and disabled-module asset behavior remain incomplete.
- No cross-version, theme, tax, shipping, currency, or third-party gateway matrix was completed.
- At the Phase 12 stop point, source version was `1.6.26`. The later release-preparation task changed local source/readme references to `1.6.27`; the update feed remains at the prior 1.6.26 metadata because signed production metadata is unavailable.

## Rollback

No production or version change exists to roll back. In local development, revert only the Phase 12 paths listed above if the focused accessibility change is rejected; preserve all other worktree changes. For a future separately approved deployment, retain the previous verified plugin ZIP and database backup, and roll back the plugin files to that version if the new release causes storefront regressions. Database rollback is not expected from the Phase 12 UI change, but must follow the site owner's backup/restore process if other release changes require it.

## Release decision

```text
RELEASE_CANDIDATE=NOT_READY
PRODUCTION_READY=NO
PUBLISHED=NO
DEPLOYED=NO
LOCAL_RELEASE_VERSION_PREPARED=1.6.27
```

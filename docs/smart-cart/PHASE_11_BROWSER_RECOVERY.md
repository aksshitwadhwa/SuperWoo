# Phase 11 Browser Recovery

**Date:** 2026-10-31  
**Scope:** Local development and disposable test installation only. No deployment, production access, order, or payment.

## Findings

- Host is Apple Silicon (`arm64`) on macOS 27.0.1. The installed Google Chrome app had an invalid signature during Phase 10 diagnostics and aborted in macOS application registration. This correlation does not prove the signature was the sole cause.
- A standalone Playwright Chromium headless shell, version 147.0.7727.15, successfully launched with Chromium sandboxing enabled. It loaded a local page, executed JavaScript, clicked a button, captured `/tmp/superwoo-phase11-browser-smoke.png`, and closed cleanly. No `--no-sandbox` flag, quarantine removal, or system security change was used.
- The supported in-app browser surface was unavailable in this session. Browser regression suites were run using the repository’s Chromium harness against isolated fixtures; the actual local storefront was also opened during the recovered browser run documented below. This does not constitute a supported external browser session.

## Disposable WordPress HTTP recovery

Phase 10’s apparent product mismatch came from a test webroot whose `wp-content` symlink resolved to the separate `superwoo-meta-test` install. The browser request and WP-CLI test configuration consequently used different databases. The WordPress options in the database used by the web request had `home` and `siteurl` set to `http://localhost:8088`, which caused the observed canonical redirect. This was test-environment cross-contamination, not a SuperWoo redirect.

Phase 11 uses `/tmp/superwoo-phase11/site` and the separate `superwoo_phase11` database. Its WordPress `home` and `siteurl` both point to `http://127.0.0.1:8765`; MariaDB is accessed through a Unix socket, and the HTTP server binds to `127.0.0.1` only. Runtime identity was confirmed as WordPress 7.1.4, WooCommerce 11.2.1, and SuperWoo 1.6.26.

Read-only GET checks returned 200 for the homepage, synthetic product, and cart. Empty-cart checkout returned WooCommerce’s expected 302 redirect to cart. The populated-cart checkout page returned 200 during the browser smoke journey. Product page and drawer assets loaded from the local site. No unexpected redirect was observed after isolation.

## Browser evidence

The five browser suites passed using the sandbox-enabled headless shell:

| Suite | Verified behavior |
|---|---|
| Buy Now cart isolation | Snapshot ordering, failure behavior, rapid click coalescing, nonce refresh |
| Add-to-cart performance | Immediate drawer open, truthful loading/totals, error recovery, consent gate, storefront add |
| Cross-sell carousel | Desktop/narrow layout, pointer target, three-card row, both directions, fragment replacement |
| Cross-sell pointer interaction | Real browser pointer movement at desktop and mobile widths |
| Drawer quantity | Increment/decrement, remove at one, server-confirmed totals, nonce retry, failure rollback, coalesced bursts |

Command:

```sh
SUPERWOO_TEST_CHROME=/tmp/superwoo-phase10-browsers/chromium_headless_shell-1217/chrome-headless-shell-mac-arm64/chrome-headless-shell \
SUPERWOO_TEST_JQUERY=/tmp/superwoo-phase11/site/wp-includes/js/jquery/jquery.min.js \
node --test --test-concurrency=1 tests/buy-now-cart-browser.test.js tests/cart-add-performance-browser.test.js tests/cart-cross-sell-browser.test.js tests/cart-cross-sell-pointer-browser.test.js tests/cart-drawer-browser.test.js
```

Result: **5 passed, 0 failed, 0 skipped**. These suites use browser-rendered fixtures and mocked requests; they are not end-to-end payment tests.

## Limits

The Phase 11 headless harness is not the installed Chrome app and does not validate every macOS GUI/browser integration. The in-app Browser connection was unavailable. Visual evidence is limited to the saved screenshots listed in `PHASE_11_VISUAL_ACCEPTANCE.md`. Do not treat browser recovery as production-browser, gateway, or payment certification.

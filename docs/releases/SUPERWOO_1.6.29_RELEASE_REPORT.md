# SuperWoo 1.6.29 Release Report

**Date:** 2026-10-31  
**Status:** Published to the existing Digtize WordPress update feed.

## Fix

The cart preservation script previously waited only on Razorpay's `#btn-1cc-pdp`. Theme or other plugin Buy Now controls could start a single-product checkout without saving the existing WooCommerce cart first. The script now recognizes common theme/gateway selectors and accessible Buy Now button labels, saves the current cart before allowing the original click to continue, and refreshes the snapshot for each new attempt. Normal add-to-cart controls are not intercepted.

Razorpay still builds its Buy Now order from the selected product only. The original cart snapshot is restored through the existing restoration path after checkout returns to the storefront. No order status, payment processing, gateway, or payment settings code was changed.

## Verification

- `tests/buy-now-cart-isolation.test.php`: PASS, including cart restoration, empty-cart behavior, and fresh snapshots after cart changes.
- `tests/updater-hardening.test.php`: PASS, 35 assertions.
- JavaScript syntax checks and PHP lint: PASS.
- Release ZIP integrity and packaged PHP lint: PASS, 72 PHP files.
- Public package and feed: HTTP 200, no redirects; uploaded ZIP matches the local artifact byte-for-byte and the feed's SHA-256 and size.
- `tests/buy-now-cart-browser.test.js` could not run to completion: the installed headless Chrome process aborted with SIGABRT before returning a test result. The browser click path is therefore not end-to-end verified in this environment.

## Release details

- Feed: `https://digtize.com/plugins/superwoo/update.json`
- ZIP: `https://digtize.com/plugins/superwoo/SuperWoo-1.6.29.zip`
- Size: 318,818 bytes
- SHA-256: `c6d5707feeed8f714f1e9500f58cdc8983b72ac7f764c6ae3483faf559aeaaeb`
- The previous 1.6.28 feed and ZIP were downloaded before publishing to `/tmp/superwoo-release-1.6.27/remote-update-1.6.28.json` and `/tmp/superwoo-release-1.6.27/remote-SuperWoo-1.6.28-pre-1.6.29.zip`. The 1.6.28 ZIP remains at its original server URL. Restore the prior feed to `/public_html/plugins/superwoo/update.json` to roll back update availability.
- This publication uses the existing unsigned JSON update format. No customer WordPress installation, settings, database, or payment configuration was changed.

```text
RELEASE_VERSION=1.6.29
UPDATE_SERVER_PUBLISHED=YES
PUBLIC_FEED_VERIFICATION=PASS
PUBLIC_PACKAGE_VERIFICATION=PASS
BUY_NOW_SERVER_REGRESSION=PASS
BUY_NOW_BROWSER_TEST=UNVERIFIED — Chrome aborted before execution completed
WORDPRESS_INSTALLATION_UPDATED=NO
```

# SuperWoo 1.6.30 Release Report

**Date:** 2026-10-31  
**Status:** Published to the existing Digtize WordPress update feed.

## Cart offer behavior

- Before free delivery is reached, show its simple remaining-amount message and a single free-delivery progress bar. Suppress the lower-priority offer text and bar.
- After WooCommerce confirms free shipping is applied, show the next eligible offer and its single progress bar.
- The 1.6.29 ZIP had been assembled from a stale staging copy that omitted the updated `templates/cart-progress.php`. The source change was present locally, but not in that package. Version 1.6.30 was built from a fresh copy of the runtime directories, and the exact template was verified inside both the local and publicly downloaded ZIP before switching the update feed.

## Verification and publication

- Delivery/progress regression test: PASS.
- Cart quantity/shipping notice regression test: PASS.
- Updater hardening tests: PASS, 35 assertions.
- PHP lint: PASS, 71 packaged PHP files.
- ZIP integrity: PASS; public ZIP matches local bytes.
- Public feed and ZIP return HTTP 200 without redirects; feed hash and size match the package.
- Feed: `https://digtize.com/plugins/superwoo/update.json`
- ZIP: `https://digtize.com/plugins/superwoo/SuperWoo-1.6.30.zip`
- ZIP size: 318,032 bytes
- SHA-256: `0f0074fb607ec371dd34920406bb88d9b54ba577ad61e4129cbf30affcf1c62f`
- The previous 1.6.29 feed and ZIP are backed up at `/tmp/superwoo-release-1.6.30/remote-update-1.6.29.json` and `/tmp/superwoo-release-1.6.30/remote-SuperWoo-1.6.29.zip`. The prior ZIP remains on the server.
- No customer WordPress installation was updated. This release uses the existing unsigned JSON update feed.

```text
RELEASE_VERSION=1.6.30
FRESH_RUNTIME_PACKAGE=YES
PRIORITY_TEMPLATE_IN_PUBLIC_ZIP=VERIFIED
UPDATE_SERVER_PUBLISHED=YES
DELIVERY_TESTS=PASS
WORDPRESS_INSTALLATION_UPDATED=NO
```

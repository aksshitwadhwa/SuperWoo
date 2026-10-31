# SuperWoo 1.6.28 Release Report

**Date:** 2026-10-31  
**Status:** Published to the existing Digtize WordPress update feed.

## Changes

- The cart progress card now prioritizes the free-delivery goal. While free delivery is pending, lower-priority offer notices are hidden and only the free-delivery progress bar is shown.
- After WooCommerce confirms a zero-cost shipping rate, the next applicable offer is shown. Only its progress bar is shown, avoiding the former two-bar card.
- Pending free-delivery progress remains visible before shipping rates are calculated; the success message still requires a real calculated free-shipping rate.
- Added 10px top spacing above the shared progress card.

## Release verification

- Focused delivery/progress regression test: PASS.
- Cart quantity/shipping notice regression test: PASS.
- PHP lint for changed PHP files: PASS.
- ZIP integrity: PASS; package root is `SuperWoo/`; packaged PHP files linted.
- Updater security suite: PASS (35 assertions).
- `git diff --check`: PASS.
- Published ZIP is 318,403 bytes, SHA-256 `a673979a6d9f2c05fa9527720ef4d63718aae2f5282ba971de1811d3c3617d36`.
- Uploaded over explicit FTPS. Public package and feed both return HTTP 200 without redirects. The public ZIP matches the local ZIP byte-for-byte; public `update.json` matches the local feed and its advertised hash and size.

## Feed and rollback

- Update URL: `https://digtize.com/plugins/superwoo/update.json`
- Package URL: `https://digtize.com/plugins/superwoo/SuperWoo-1.6.28.zip`
- Previous release: 1.6.27. Its feed and ZIP were downloaded before publication to `/tmp/superwoo-release-1.6.27/remote-update-1.6.27.json` and `/tmp/superwoo-release-1.6.27/remote-SuperWoo-1.6.27-pre-1.6.28.zip`. The previous ZIP remains on the server. Restore the prior feed to `/public_html/plugins/superwoo/update.json` to roll the update offer back.
- This uses the existing unsigned JSON feed, as directed. HTTPS and SHA-256/package-size checks remain active; SHA-256 does not authenticate the feed itself.
- No customer WordPress installation, database, settings, or payment configuration was changed. Dashboard update installation was not performed or verified.

```text
RELEASE_VERSION=1.6.28
UPDATE_SERVER_PUBLISHED=YES
PACKAGE_HTTPS_VERIFICATION=PASS
FEED_HTTPS_VERIFICATION=PASS
FOCUSED_DELIVERY_TEST=PASS
UPDATER_REGRESSION_TESTS=PASS (35)
WORDPRESS_INSTALLATION_TESTED=NO
SIGNATURE_AUTHENTICITY=NOT PROVIDED (existing unsigned feed)
```

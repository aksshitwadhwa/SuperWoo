# SuperWoo 1.6.27 Release Report

**Date:** 2026-10-31  
**Deployment status:** **PUBLISHED to the existing Digtize update feed**  
**Scope:** Published the approved 1.6.27 ZIP and existing unsigned `update.json` format to the plugin update directory over verified explicit FTPS. No WordPress installation, database, or settings were modified.

## Release identity

- Previous published release: `1.6.26`. Before publishing, the remote `update.json` and `SuperWoo-1.6.26.zip` were downloaded over explicit FTPS and backed up under `/tmp/superwoo-release-1.6.27/`.
- Candidate version: `1.6.27`, the next version after the locally tracked 1.6.26 release.
- Local version references updated: main plugin header, `SUPERWOO_VERSION`, readme stable tag, and changelog.
- No database migration/schema version change was needed.

## Candidate artifact

- Local candidate ZIP: `/tmp/superwoo-release-1.6.27/SuperWoo-1.6.27-CANDIDATE.zip`
- SHA-256: `7dc5c859845b08443d8c708e55c5156cda235811bff2a064108a082c6ac197e7`
- Size: `323649` bytes
- ZIP contains the plugin at `SuperWoo/`, including `superwoo.php`, admin, includes, public assets/fonts, templates, readme, and uninstall handler. Archive integrity passed and all 71 PHP files in the archive linted.
- Tests, local evidence/screenshots, docs, developer signing tools, previous release ZIPs, `update.json`, and secrets are excluded. The old local 1.6.26 ZIP and metadata snapshot are preserved at `/tmp/superwoo-release-1.6.27/backup/`.
- Published package: `https://digtize.com/plugins/superwoo/SuperWoo-1.6.27.zip`.
- The existing update protocol uses unsigned flat JSON. Publication was made at the owner's explicit direction to keep the existing `update.json` + ZIP workflow; no signing keys or signature checks were added or bypassed. The updater still enforces TLS, exact host/path, no redirects, advertised size/SHA-256, and ZIP layout. SHA-256 detects package mismatch but does not authenticate the feed against compromise of the hosting account/server.

## Changes included

- All current plugin runtime source, including the Smart Cart changes already present in the working tree.
- Final Phase 12 cart accessibility updates: keyboard focus containment, reduced-motion support, and product-specific accessible names for cart quantity controls.
- Updated 1.6.27 changelog with the cart UX/accessibility changes.

Excluded from the ZIP: test suites, browser evidence, audit/phase reports, local settings, test databases, release tooling, signing fixtures, and all previous ZIP archives. No production credentials were copied or used.

## Update infrastructure and security gate

- Configured metadata URL in local code: `https://digtize.com/plugins/superwoo/update.json`.
- Package URL pattern: `https://digtize.com/plugins/superwoo/SuperWoo-<version>.zip`.
- Plugin slug: `superwoo`; updater integration is the WordPress `Update URI` host filter for `digtize.com` plus update-transient and plugin-information filters.
- The local updater rejects redirects, verifies TLS, restricts package host/path, and checks response size, SHA-256, and ZIP layout. Its current implementation consumes **unsigned flat JSON**; its own tests explicitly state that the feed hash is not an authenticity signature.
- The checked-in `update.json` now matches the published 1.6.27 feed, including package URL, SHA-256, and byte size.
- The server directory was verified as `/public_html/plugins/superwoo/` on the authorized Digtize hosting account. The remote 1.6.26 feed and ZIP were downloaded as rollback copies before changes. The new ZIP was uploaded first and verified through the public HTTPS URL byte-for-byte before metadata was switched using a remote rename.
- Public HTTPS verification returned HTTP 200 without redirects for both update metadata and package. The public feed exactly matched local release metadata; package size was 323,649 bytes and SHA-256 was `7dc5c859845b08443d8c708e55c5156cda235811bff2a064108a082c6ac197e7`.
- The feed is intentionally unsigned under the existing owner-authorized workflow. The live update endpoint and package are verified, but WordPress dashboard detection/install and post-install smoke tests were not run. WordPress transient/CDN caches may delay visibility.

## Validation

| Check | Result |
|---|---|
| Candidate ZIP structure/integrity | PASS — `unzip -t`; expected `SuperWoo/` plugin root and main file present |
| Candidate version consistency | PASS — header, runtime constant, stable tag, and 1.6.27 changelog match |
| Candidate PHP lint | PASS — 71 packaged PHP files |
| Source PHP lint | PASS — 96 files |
| JavaScript syntax | PASS — all public JS and test JS |
| Standalone PHP tests | PASS — 22 scripts |
| Updater regression tests | PASS — 35 assertions; this suite covers HTTPS-only unsigned feed behavior and does not establish signed authenticity |
| Meta Node tests | PASS — 28 tests |
| WooCommerce offer integration | PASS — 129 checks earlier in Phase 12; a subsequent rerun could not connect to the disposable MariaDB socket |
| Browser suites | BLOCKED — sandboxed Chromium failed before page execution; no sandbox bypass used |
| Live update metadata/package | PASS — HTTPS 200, no redirects, remote ZIP matches local bytes and feed |
| Dashboard update discovery/install | NOT RUN — no WordPress installation was updated |
| Post-install smoke tests | NOT RUN — no WordPress installation was updated |
| `git diff --check` | PASS |

The known Smart Cart limitations remain: post-fix focus/reduced-motion behavior needs browser verification; visual coverage is incomplete; screen-reader, contrast, RTL, safe-area, and virtual-keyboard checks remain; settings save/reload and disabled-asset behavior are not fully verified; and WooCommerce/theme/tax/shipping compatibility coverage is limited. The local Phase 12 cart journey and offer integration passed earlier, with no real order/payment submitted.

## Backup and rollback

The prior local 1.6.26 artifact and metadata snapshot are in `/tmp/superwoo-release-1.6.27/backup/`; the exact prior remote feed and ZIP are in `/tmp/superwoo-release-1.6.27/remote-update.json` and `/tmp/superwoo-release-1.6.27/remote-SuperWoo-1.6.26.zip`. The old remote 1.6.26 ZIP remains on the update server. Rollback consists of restoring the backed-up `update.json` to `/public_html/plugins/superwoo/update.json`; the previous ZIP remains available at its original URL.

## Remaining rollout checks

1. For stronger supply-chain authenticity, approve a separate signed-metadata deployment project and publish a verifier-compatible signed feed; this release follows the currently used unsigned format by explicit direction.
2. Test update detection and installation on a disposable WordPress site starting from 1.6.26 and verify settings preservation before broad customer rollout.
3. Restore the disposable MariaDB and browser test environment to rerun blocked integration/browser checks, including post-fix focus and reduced-motion behavior.

## Required status

```text
RELEASE_PACKAGE=PASS — uploaded ZIP verified over public HTTPS
VERSION_UPDATED=YES — source/header/readme and local/remote update metadata are 1.6.27
UPDATE_METADATA=PASS — existing unsigned flat JSON format, matches package SHA-256 and size
UPDATE_SERVER_PUBLISHED=YES — `/public_html/plugins/superwoo/`; HTTPS metadata and package return 200 without redirects
WORDPRESS_UPDATE_DETECTED=NOT TESTED
WORDPRESS_UPDATE_INSTALLATION=NOT TESTED — no WordPress installation was updated
POST_UPDATE_SMOKE_TESTS=NOT RUN
ROLLBACK_READY=YES — previous remote feed/ZIP backed up locally; old remote ZIP remains available
SIGNATURE_AUTHENTICITY=NOT PROVIDED — current unsigned protocol cannot authenticate feed ownership
DEPLOYMENT_STATUS=PUBLISHED; dashboard/install verification pending
```

The authorized update distribution server now serves the 1.6.27 ZIP and feed. No deployed WordPress plugin installation, WordPress settings, customer database, or payment configuration was changed.

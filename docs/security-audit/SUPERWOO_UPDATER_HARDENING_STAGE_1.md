# SuperWoo Updater Hardening — Stage 1 Implementation

**Date:** 2026-10-23  
**Scope:** Local development implementation and fixture-backed verification only. No production keys, update server, plugin deployment, or live download was accessed or changed.

## Result

The updater now accepts only canonical signed release metadata and verifies SuperWoo ZIP packages before WordPress installs them. Production trust anchors are intentionally empty because Stage 2 signing setup is not approved. This disables updater offers from the existing unsigned feed, with no fallback to unsigned metadata or package downloads.

## Changes

- `includes/class-github-updater.php` now verifies the signed envelope, schema, Ed25519 signature, approved package URL, monotonic release sequence, package size, SHA-256, ZIP root layout, and file types. Metadata and package HTTP requests explicitly verify TLS and reject redirects. SuperWoo package failures return `WP_Error`; unrelated plugin package requests pass through unchanged.
- `tests/updater-hardening.test.php` uses a deterministic non-production test key and mocked WordPress HTTP/transient/upgrader APIs. It exercises valid update discovery, canonical/signature/schema/replay failures, URL allowlist cases, redirect/status failures, content-length and size checks, hash mismatch, malformed ZIP, package substitution, cleanup, and unrelated plugin behavior.
- `docs/security-audit/SUPERWOO_UPDATER_HARDENING_PLAN.md` records the implemented wire contract and remaining server requirements.

## Verification

PHP lint passed for all 66 PHP files. All seven standalone PHP test files passed, including 60 updater assertions. The complete Node suite passed 28/28 tests. `git diff --check` passed. Tests use mocked HTTP and a deterministic test-only Ed25519 key; no external requests were made.

The updater suite covers valid signed discovery and WordPress update fields; missing/bad/unknown signatures; manifest mutation, schema errors, duplicate keys and noncanonical JSON; monotonic sequence and downgrade rejection; malicious URL variants; TLS/transport failures, metadata/package redirects and HTTP errors; required response length, package size and SHA-256; ZIP structure; URL substitution and preempted download paths on a SuperWoo install (must return `WP_Error`); unchanged handling for unrelated plugin packages; and temporary file cleanup.

Stage 2B extends this wire contract with a required signed UTC `expires_at` field limited to a maximum 30-day validity window. The release signer and updater now share canonicalization and schema validation so emitted metadata matches what the client accepts.

## Remaining risks and limits

- Production update discovery is intentionally disabled until the empty production trust-anchor map is populated during Stage 2 and the server is ready. Do not deploy this Stage 1 client by itself as a user-facing update; it would stop receiving updates from the current unsigned feed.
- The allowlisted package host/path and 50 MiB compressed package ceiling are fixed in code. A different CDN, path, or larger release requires a reviewed client change.
- ZIP inspection requires PHP `ZipArchive`; if unavailable, SuperWoo updates fail closed while unrelated updater operations remain untouched.
- Verification was standalone on local PHP 8.5.11, not in a production WordPress upgrader session or across the supported WordPress/PHP matrix. Staging integration remains required after Stage 2.

## Production prerequisites

Stage 2 must approve and install a production public trust anchor, configure signing-key custody and rotation, publish canonical signed manifests matching the documented schema, and serve immutable ZIP packages from the allowlisted host/path with matching SHA-256, byte length, and no redirects. Until that is complete, update discovery is intentionally unavailable.

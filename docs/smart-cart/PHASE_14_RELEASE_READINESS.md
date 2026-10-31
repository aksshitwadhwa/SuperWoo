# SuperWoo Smart Cart — Phase 14 Release Readiness

**Date:** 2026-10-31  
**Candidate:** SuperWoo 1.6.32, local only  
**Decision:** Candidate package is technically installable in the tested disposable site; **not production-ready and not published**.

## Candidate artifact

- ZIP: `/tmp/superwoo-phase14-release/SuperWoo-1.6.32-CANDIDATE.zip`
- SHA-256: `116e44697f87c0645fbc2e6bc9ae2e5c26ebc4d96354bf3cffe26b045d674d0e`
- Size: 324,340 bytes
- ZIP integrity and PHP lint inside package: passed.
- Disposable install: 1.6.31 → 1.6.32 passed; SuperWoo/WooCommerce activated; settings option hash unchanged.
- Metadata draft: `/tmp/superwoo-phase14-release/update-1.6.32-draft.json`; not published.

## Published state

Read-only inspection found the public feed at `https://digtize.com/plugins/superwoo/update.json` still reporting 1.6.31. Its package checksum matched the corresponding local 1.6.31 ZIP. The feed is unsigned, so a feed-supplied SHA-256 does not provide independent authenticity. The public endpoint and files were not changed.

## Required before release

1. Close the documented remaining visual, accessibility, merchant-settings, performance, and compatibility gaps or explicitly accept them.
2. Complete the configured signed-update infrastructure and production trust-anchor/key custody. The current public feed is not signed; do not enable or ship a verifier without the production public trust anchor.
3. Validate the final source/package diff and release notes, and re-run the test suite against the exact release artifact.
4. Obtain separate release approval, then publish immutable ZIP and metadata atomically using the authorized release process. Verify the public feed and a disposable WordPress remote update after publication.
5. Keep the prior 1.6.31 package available for rollback; document rollback and cache invalidation with the update-server operator.

## Rollback outline (not executed)

Retain the known-good 1.6.31 ZIP and metadata. If release validation fails, restore the feed to the prior metadata/package, invalidate relevant CDN caches, and use a disposable WordPress installation to confirm update detection before instructing site owners. Do not downgrade live sites automatically; preserve their settings and take their normal backups first.

## Release gate

```text
LOCAL_CANDIDATE=1.6.32
PACKAGE_VALID=YES
DISPOSABLE_UPGRADE=PASS
PUBLISHED_FEED=1.6.31 (unchanged)
SIGNED_METADATA=NO
PRODUCTION_TRUST_ANCHOR=NOT_CONFIGURED
PUBLISHED=NO
PRODUCTION_READY=NO
```

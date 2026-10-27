# SuperWoo Stage 2B — Verification Results

**Date:** 2026-10-23  
**Scope:** Local signer, shared verifier, and in-process update-server fixture. No external network requests, production keys, live server, production WordPress, or deployment were used.

## Outcome

The release signer and Stage 1 updater now share manifest canonicalization, schema validation, Ed25519 signature handling, exact package URL validation, and signed expiry validation. Development integration tests produce a signed manifest with the CLI, feed it through a simulated Digtize endpoint, verify the package, and extract it into an isolated temporary directory. The local fixture demonstrates the intended protocol only; it is not an end-to-end test of Digtize or the WordPress upgrader.

## Cases verified

| Area | Result |
|---|---|
| Sign final fixture ZIP, include byte length and SHA-256 | Pass |
| Canonical envelope exactly matches verifier serialization | Pass |
| Ed25519 detached signature and key ID | Pass |
| Signed expiry in UTC; reject expired and over-30-day metadata | Pass |
| Exact `digtize.com/plugins/superwoo/*.zip` host/path policy | Pass; malicious host and path rejected |
| Valid simulated metadata and WordPress update offer | Pass |
| Valid package hash and isolated install extraction | Pass |
| Invalid signature, missing metadata, HTTP errors, and network failure | Pass; fail closed |
| Metadata and package redirects | Pass; fail closed |
| Modified ZIP and hash/size mismatch | Pass; fail closed |
| Version downgrade and sequence replay/substitution | Pass |
| Development key overlap and old-key retirement | Pass |
| Signer refuses output overwrite, wrong URL, expired metadata, unsafe key-file permissions | Pass |
| Production key lookup and unsigned fallback | Pass; trust map remains empty and legacy feed is not fetched |

## Test execution

- PHP CLI: **8.5.11**, sodium and ZipArchive extensions present.
- All standalone PHP suites: **8 passed**. This includes free-gift pricing, payment/order isolation (72 assertions), Razorpay webhook (32), review-upload limits, security hardening (12), settings persistence (30), updater hardening (66), and signed-release integration (29).
- Node: **28 passed, 0 failed, 0 skipped** (`node --test tests/*.test.js`).
- PHP lint: **70 PHP files checked, 0 syntax errors**.
- `git diff --check`: **passed**.
- No HTTP connection left the process; fixture assertions state network disabled.

The integration test's “installation” verifies the returned local ZIP and extracts it using PHP ZipArchive to a temporary directory, then confirms the main plugin version. It does not invoke `WP_Upgrader`, WordPress filesystem APIs, or a real site. A staging WordPress update is still required after server and production-key approval.

## Remaining compatibility and operational risks

- The live `digtize.com` endpoint, web server, CDN, TLS, MIME types, redirects, cache behavior, and ZIP bytes were not inspected; production compatibility is **unverified**.
- Production trust anchors are still empty. The current signed local fixture key is test-only and has no production trust value. Do not publish or deploy this checkout as a hardened production updater.
- The schema now requires `expires_at`, making this client incompatible with the old unsigned flat feed and any earlier signed envelope without that field. Bootstrap must be coordinated and cannot rely on an unsigned fallback.
- The key file CLI is the approved offline signing mechanism; production use requires the dedicated offline workstation, encrypted key custody, dual control, and a reviewed release workflow.
- The integration fixture does not test actual WordPress update-transient/plugin-directory behavior, supported PHP versions other than local 8.5.11, or sodium-compat behavior on older hosts.
- Site sequence state can be lost or restored with a database backup. Expiration limits replay time, but recovery needs release-ledger checks.

## Release gate

**Stage 2B local implementation: PASS. Production readiness: NO.** No production key was generated, no production trust anchor was added, no endpoint or package was published, no production file/database/setting was changed, and no plugin was deployed. Stage 2 production signing setup and server implementation require separate explicit approval.

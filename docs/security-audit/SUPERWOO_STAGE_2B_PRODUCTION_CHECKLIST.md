# SuperWoo Stage 2B — Production Readiness Checklist

**For the authorized update-server and WordPress administrators.** This is a preparation checklist; no production system was accessed or changed. Items marked **Unverified** require the relevant owner to inspect the live service.

## Update API and hosting

- [ ] **Unverified:** Confirm the live metadata endpoint currently configured for deployed clients: `https://digtize.com/plugins/superwoo/update.json`.
- [ ] **Unverified:** Identify the actual Digtize hosting platform, web server, CDN/proxy, filesystem, deployment owner, TLS terminator, and access/error log locations.
- [ ] **Unverified:** Confirm TLS certificate chain, hostname, renewal ownership, and HTTPS behavior. Metadata and ZIP requests must return directly without HTTP redirects.
- [ ] **Unverified:** Confirm whether the signed endpoint will use a versioned path (recommended during migration) or replace `update.json`; determine how legacy and hardened clients are distinguished.
- [ ] **Unverified:** Confirm package URL host/path can remain exactly `digtize.com/plugins/superwoo/<filename>.zip`. Any CDN or alternate hostname needs a separately reviewed client allowlist change before use.
- [ ] **Unverified:** Confirm deployed service supports atomic metadata replacement after immutable artifact upload and can retain old packages without overwriting a released version.

## Required metadata and HTTP behavior

- [ ] Serve an envelope with exactly `manifest` and `signature`; Ed25519 detached signature covers canonical JSON bytes of `manifest`.
- [ ] Include the exact schema fields: `schema_version`, `slug`, `version`, `requires`, `requires_php`, `package`, lowercase `sha256`, integer `package_size`, increasing integer `sequence`, `key_id`, and strict UTC `expires_at`.
- [ ] Keep expiration within 30 days and publish a fresh, higher-sequence manifest when extending or replacing a release. Do not mutate metadata while reusing its sequence.
- [ ] Return `Content-Type: application/json` (UTF-8 acceptable), HTTP 200, exact canonical response bytes with no whitespace wrapper, no redirect, no HTML error page, and a short cache lifetime (at most five minutes and never beyond metadata expiration). Purge stale CDN copies on publication.
- [ ] Return ZIP bytes from the exact allowlisted path with `Content-Type: application/zip` or `application/octet-stream`, correct `Content-Length`, no redirect, no content transformation/compression that changes the streamed bytes, and stable bytes matching signed size and SHA-256.
- [ ] Optional range requests must not alter the normal full-package response. Confirm server/CDN error paths never return HTTP 200 with an HTML body.
- [ ] Confirm the endpoint and package are reachable with standard WordPress safe HTTP requests and valid CA verification from a staging WordPress host. No external URL fetch was run for this stage.

## Production key custody and release operation

- [ ] Obtain explicit owner approval before generating the production Ed25519 signing key or changing the plugin's pinned public trust-anchor list.
- [ ] Generate/store the private key only on a dedicated offline signing workstation. Encrypt it at rest; prohibit web-server storage, source control, CI variables, shared drives, and support tickets. Name the custodians and document dual control, signing logs, encrypted backup, recovery, and signer access revocation.
- [ ] Pin only the public key and immutable key ID in a reviewed plugin release. Never fetch the public key from the same metadata host and never publish a hardened release with no production trust anchors.
- [ ] Maintain a release ledger: source commit, build environment, package filename, SHA-256, byte count, version, sequence, expiry, key ID, approvals, upload result, and post-publication verification.
- [ ] Build ZIP from reviewed source, verify root folder and `superwoo.php` header version, run PHP lint/full relevant tests, and freeze the final ZIP before hashing/signing.
- [ ] Upload ZIP first under a new immutable filename; independently fetch and verify its size/hash over HTTPS; publish signed metadata atomically afterward. Do not overwrite a versioned artifact.
- [ ] Test the exact package against a disposable WordPress installation using the approved public key before broad release. Verify plugin directory replacement, updater UI, expiry, redirects, modified bytes, and rollback-as-forward-release.
- [ ] Plan key overlap and retirement in advance. For compromise, stop signing and use an independent administrative recovery channel; the compromised key must not authorize its own replacement.

## Existing-installation bootstrap

- [ ] Approve a controlled bootstrap process for legacy installations. The preferred process is an authenticated administrator upload of the signed-capable package after checking its digest through an independent trusted channel.
- [ ] Confirm the first signed-capable package embeds the real approved public trust anchor and the expiry-aware verifier. Empty trust list is a release blocker.
- [ ] Approve whether the old unsigned feed remains available temporarily, the exact cutoff date, permitted package(s), monitoring, and final retirement. The hardened client must never fall back to it.
- [ ] Document coverage for old plugin versions, sites that miss the migration window, administrator recovery, and manual installation. Do not claim old versions will continue dashboard updates after legacy service retirement.

## Final release gates

- [ ] Production PHP/WordPress compatibility matrix and sodium verification fallback tested on supported hosts.
- [ ] Signed metadata and immutable package validated from staging, including actual CDN/cache response headers.
- [ ] New key rotation and emergency revocation exercise completed.
- [ ] Bootstrap pilot completed and observed before broad distribution.
- [ ] Named owners sign off on server changes, key custody, endpoint routing, release workflow, migration window, and rollback process.

**Production status:** Not verified. The local Stage 2B simulator does not establish compatibility with the actual Digtize server, hosting stack, CDN, TLS, or deployed WordPress sites.

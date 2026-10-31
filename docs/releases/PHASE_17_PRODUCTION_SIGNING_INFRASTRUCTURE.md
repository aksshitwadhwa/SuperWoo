# SuperWoo Phase 17 — Signing Infrastructure Discovery and Design

**Date:** 2026-10-31  
**Scope:** Local repository review and isolated loopback fixture only. No production server was changed, no production key was generated or activated, and no customer installation was contacted.

## Status

```text
PRODUCTION_INFRASTRUCTURE_AUDIT=PARTIAL — local configuration reviewed; live hosting state unavailable
SIGNED_ENDPOINT_IMPLEMENTATION=PARTIAL — isolated static endpoint fixture implemented; production endpoint not deployed
PRODUCTION_INFRASTRUCTURE_DEPLOYED=NO
PRODUCTION_ENDPOINT_VERIFIED=NO
LEGACY_FEED_CHANGED=NO
PRODUCTION_SIGNING_KEY_ACTIVE=NO
SUPERWOO_1_6_32_PUBLISHED=NO
```

## Existing architecture verified locally

- The plugin's legacy metadata URL is `https://digtize.com/plugins/superwoo/update.json`.
- The source uses a dedicated signed metadata URL, `https://digtize.com/plugins/superwoo/update-signed.json`, only after trust anchors are configured or signed mode has been latched.
- The package allowlist is exact host `digtize.com`, path `/plugins/superwoo/`, HTTPS, and versioned filename `SuperWoo-<version>.zip`.
- `update.json` in this checkout is an unsigned flat manifest for version `1.6.31`; this is repository content, not a current live observation.
- The Phase 16 verifier uses canonical JSON and detached Ed25519 signatures. It binds version, package URL, package SHA-256, byte size, sequence, key ID, and expiry. Package download redirects are rejected; TLS, package size, SHA-256, ZIP paths, and embedded plugin version are checked.
- The release tool writes canonical signed envelopes and refuses keys without exact mode `0600`. The private key is used locally by the signer; it need not be present on the web server.

The static signed endpoint model is intentional: the offline signer produces an immutable `update-signed.json`; the web host serves it as a static file next to the immutable ZIP. The endpoint does not sign dynamically and has no access to the private key.

## Production hosting audit boundary

The browsing tool could not retrieve the public update URLs, including both metadata paths and the package directory. This session has no authenticated hosting, SSH, FTP, deployment, or server-log connector. Therefore the following cannot be verified: current live feed/version and bytes; server vendor/platform; filesystem and ZIP location; TLS/redirect behavior; MIME/cache headers; authentication/ACLs; deployment process; access/error logs; backups; or rollback mechanism. The local `update.json` cannot be treated as proof of production state.

No credentials were requested or used. The previously shared FTP host was for the MODU WordPress site and has not been established as the Digtize update server. It was not reused for this deployment.

## Proposed production architecture

1. Keep the legacy `update.json` and existing versioned ZIP route unchanged during bootstrap.
2. Generate the production Ed25519 identity on an owner-controlled offline signing workstation only after separate approval. Keep the base64 private key in a mode-0600 file on encrypted offline storage, with controlled backup and two-person custody for recovery. Never copy it to the public host, repository, CI logs, or WordPress.
3. Provision the matching public key and key ID in the first approved signed-capable plugin release (or a separately reviewed, authenticated bootstrap procedure). Verify the packaged trust anchor before distribution.
4. Build and freeze the final ZIP; record its SHA-256 and size. Sign the exact manifest using `tools/sign-superwoo-release.php`; never mutate the ZIP after signing.
5. Publish the signed envelope as the separate static `update-signed.json` and its package under the exact allowlisted path. Serve over HTTPS without redirects, with JSON and ZIP MIME types, no directory listing, short metadata caching/revalidation, and immutable caching for versioned ZIPs.
6. Test from a disposable provisioned WordPress client before enabling the signed endpoint for any installation. Keep the legacy feed available for old clients.
7. Rotate by shipping overlapping public anchors in a reviewed client update, publishing with the new key ID and a strictly increasing sequence, verifying adoption, then removing the old anchor in a later release. Revocation of a lost private key requires an authenticated client trust-anchor update; removing a key from the server does not revoke it from installed clients.

## Risks and remaining approvals

- The current legacy bootstrap path is not cryptographically authenticated. A signed endpoint does not secure the initial delivery of verifier code or public trust anchors to an old unsigned client. Existing installations need a separately trusted bootstrap route or an explicitly accepted one-time legacy exposure; do not imply that a public-key-only update makes that first transition tamper-proof.
- The release tool only signs packages bound to the approved digtize.com URL. The actual host/path and hosting controls must be confirmed by the server operator before deployment.
- Production signing key generation/activation is explicitly not approved by this Phase 17 brief.
- No production deployment can safely occur until the exact hosting account, isolated target directory, verified deployment access, existing backup, and rollback procedure are supplied and independently confirmed.

## Local fixture

`tests/fixtures/signed-update-server/router.php` serves only two fixed static files, allows GET/HEAD, rejects other methods and unknown paths, emits explicit MIME/cache/security headers, and has no redirect route. It is solely a loopback test fixture; PHP's development server/router is not a production hosting design. The endpoint fixture test creates its own ephemeral signing key and synthetic ZIP in a temporary directory, then removes them.

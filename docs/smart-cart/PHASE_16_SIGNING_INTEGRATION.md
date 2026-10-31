# SuperWoo Phase 16 — Signed Update Integration

**Status:** Local implementation and end-to-end fixture verification passed. Production signing is not provisioned.

## Contract now implemented

- `includes/class-github-updater.php` loads `SuperWoo_Update_Manifest` and verifies a signed envelope before trusting release fields. The envelope is exactly `{ "manifest": ..., "signature": ... }`; the envelope JSON itself must be canonical. The detached Ed25519 signature is over the canonical manifest object.
- The manifest has an exact schema: schema version, slug, plugin version, WordPress/PHP requirements, package URL, SHA-256, byte size, monotonic sequence, key ID, and UTC expiry. Metadata expires within 30 days.
- Trust anchors are supplied by a site-level constant, for example:

  ```php
  define('SUPERWOO_UPDATE_TRUSTED_PUBLIC_KEYS', [
      'release-key-2026-a' => 'BASE64_ENCODED_32_BYTE_PUBLIC_KEY',
  ]);
  ```

  The value above is a template only. No production key was generated or inserted. Every configured key ID and public key must validate; an empty or malformed list rejects updates.
- Without a trust-anchor constant or prior signed-mode latch, clients use the existing `https://digtize.com/plugins/superwoo/update.json` legacy feed for compatibility. Once a trust anchor is provisioned, the client stores a signed-only mode latch and reads the separate fixed endpoint `https://digtize.com/plugins/superwoo/update-signed.json`. Removing the constant later does not re-enable legacy updates. An invalid signed envelope never triggers a legacy retry.
- Signed package URLs must use HTTPS on the exact `digtize.com` host and `/plugins/superwoo/` path, without credentials, query, fragment, encoded path, or redirect. The basename must be `SuperWoo-<manifest version>.zip`. TLS validation is on; redirects are disabled.
- Before WordPress receives the archive, the updater checks HTTP status, exact response size, SHA-256, safe ZIP paths, the main plugin file, and that its embedded version equals the signed manifest version.
- Signed release state is stored in a site option. Lower sequence numbers, same-sequence changed manifests, and version downgrades are rejected. A corrected artifact needs a new sequence. A recovery release must use a newer sequence and a non-lower version.
- Key rotation is supported by overlapping trusted key IDs during transition, then removing the old public key. Sequence numbering remains monotonic across key rotation. Removing all keys leaves signed mode latched and stops update offers.
- Ed25519 uses PHP Sodium, with the existing ParagonIE Sodium compatibility verifier as a fallback. No custom cryptographic primitive was introduced. The release signer still requires a mode-0600 private-key file and fails if Sodium is unavailable.

## Local evidence

`tests/updater-signed.test.php` passes 36 assertions covering signature and canonical-envelope checks, trust-key selection, expiry, host/path and version binding, sequence replay and downgrade rejection, persistence failure, package hash/size/header checks, redirect and network failure, cache verification, and overlapping-key rotation. Separate tests prove empty anchors fail closed and a stored signed-only latch remains active if the constant is removed. The legacy regression suite passes 36 assertions without a configured anchor.

The exact final 1.6.32 ZIP was signed using an ephemeral local Ed25519 identity, then passed through the real updater offer and `upgrader_pre_download` flow with mocked HTTPS transport. The updater selected the signed endpoint, offered 1.6.32, downloaded the exact candidate archive, and verified its bytes. The private test key was deleted after this check; test-only public key and envelope remain under `/tmp/superwoo-phase16-release/`.

## Compatibility and server boundary

Existing clients and new clients without a configured trust anchor continue reading the existing legacy `update.json` format. A provisioned client reads only `update-signed.json`; it does not fall back to the legacy document on missing, expired, malformed, or invalidly signed data. This keeps both feeds available during migration without presenting unsigned metadata to signed-only clients.

The new signed endpoint has not been created or inspected on digtize.com. The server operator must publish canonical signed envelopes there, keep package ZIPs immutable, serve the matching size/hash over HTTPS with no redirect, and preserve the legacy feed during the bootstrap window. Production deployment also needs an approved public trust anchor on client installations before the signed endpoint is enabled. Older 1.6.31 clients do not contain this verifier and cannot consume signed envelopes.

The current local candidate has no production trust anchor. It therefore remains in explicitly transitional legacy mode until an operator provisions one. This is a local release-candidate feature, not production update authenticity.

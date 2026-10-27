# SuperWoo Stage 2B — Signed Release Tooling

**Status:** Local tooling and fixture implementation complete; production keys, server, publication, and deployment untouched. **Not production-ready for signing or release.**

## Implemented wire contract

The updater and signer share `includes/class-superwoo-update-manifest.php`. The response body is a compact canonical JSON envelope with exactly `manifest` and `signature`. The detached signature is base64 Ed25519 over the canonical JSON bytes of `manifest`; the outer envelope is also emitted in canonical form because the updater requires the received body to match its canonical serialization byte for byte. The restricted manifest schema contains exactly:

```json
{
  "schema_version": 1,
  "slug": "superwoo",
  "version": "1.5.6",
  "requires": "6.2",
  "requires_php": "7.4",
  "package": "https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip",
  "sha256": "lowercase-64-character-sha256",
  "package_size": 123456,
  "sequence": 101,
  "key_id": "release-ed25519-2026-01",
  "expires_at": "2026-10-24T12:00:00Z"
}
```

Expiration is signed, must be strict UTC, must be in the future, and may be at most 30 days ahead. The release sequence is positive and monotonic; the client persists its high-water sequence and rejects lower sequence or same-sequence substitution. The updater additionally offers only versions greater than the installed version. The package URL must be HTTPS on the exact host `digtize.com`, path `/plugins/superwoo/`, with a simple `.zip` filename and no credentials, query, fragment, nonstandard port, encoded path, or redirect. The client hashes the downloaded bytes and checks the signed size, SHA-256, and ZIP layout before returning a local file to WordPress. Production trust anchors remain empty in this checkout; unsigned feeds remain rejected.

Canonicalization sorts object keys lexically, preserves list order, emits unescaped Unicode and slashes, and is valid for this fixed ASCII-key schema (which has no floating-point values). This is the specific canonical representation the current Stage 1 PHP client implements; it should not be described as a general-purpose RFC 8785 implementation. The shared code avoids independent signer/updater interpretations.

## Local signing command

The CLI tool is `tools/sign-superwoo-release.php`. It requires PHP sodium and ZipArchive, verifies the ZIP's root and main plugin header version, hashes the final archive, checks the URL/filename and manifest values, and writes the canonical envelope to a new file without overwriting an existing one. It refuses unsigned output and rejects private-key files readable by group or others. Use this file-based signer for production only on a dedicated offline signing workstation with the encrypted production key temporarily available to the authorized signer; never sign on the public web server or a general-purpose connected workstation.

Example using a **development-only** Ed25519 secret key already stored in a mode-0600 file:

```sh
php tools/sign-superwoo-release.php \
  --package=/path/to/SuperWoo-1.5.6.zip \
  --version=1.5.6 \
  --package-url=https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip \
  --sequence=101 \
  --key-id=dev-2026 \
  --expires-at=2026-10-24T12:00:00Z \
  --private-key-file=/secure/local/dev-ed25519-secret.b64 \
  --output=/secure/local/update.json
```

The tool does not generate keys, upload artifacts, contact Digtize, overwrite metadata, or install a plugin. Never pass a production private key to this tool outside the approved offline signing procedure. A production process must not copy its private key to the update host.

## Key lifecycle design

- Generate the production Ed25519 key on a dedicated offline signing workstation only after owner approval. Store the key encrypted at rest, limit access to named custodians, keep a separate encrypted recovery copy under dual control, and log every signing operation. Do not use a web host, CI secret, shared drive, source repository, or support ticket for private-key storage or transfer.
- Give each public key a unique, immutable `key_id`. Keep the public key in the plugin's trusted map. Do not fetch trust anchors from the metadata server.
- For planned rotation, ship a reviewed client build containing both old and new public keys before publishing with the new key. Publish with the old key until that client build is validated; then sign new manifests with the new key during an overlap period. Remove the old trust anchor only in a later reviewed client release after adoption is sufficient.
- For suspected compromise, stop signing with the key. A compromised signing key cannot securely authorize its own replacement. Recovery requires a separately authenticated trust-anchor package/update path, such as controlled administrator installation, then removal of the compromised key. Preserve evidence and the release ledger.
- Rehearse expiration, key overlap, retirement, database restore, sequence recovery, and recovery from loss of signing service before enabling production updates.

## Update-server publication requirements

The actual Digtize server was not reachable for this task; its implementation and configuration remain unverified. It must publish the exact signed envelope at the configured metadata endpoint and serve the exact immutable ZIP bytes at the signed package URL. Publish the package first, verify its served byte count and SHA-256, then atomically publish metadata. Do not replace a versioned ZIP. The metadata response must be canonical JSON with no added whitespace or HTML wrapper, no redirect, valid HTTPS, and a cache lifetime shorter than the remaining metadata lifetime. ZIP responses must not be rewritten, recompressed, or redirected to a non-allowlisted CDN. See the production checklist for HTTP requirements.

## Current stop boundary

Development fixtures and key material are generated only inside test temp directories and deleted by the tests. No production key exists or has been created. The production trust-anchor map is still empty, the current feed is unsigned, and the live endpoint has not been configured for signed releases. Do not publish this source as a production updater until the approved public key is pinned, the production server emits this schema, a bootstrap path is approved, and a staged WordPress update has passed.

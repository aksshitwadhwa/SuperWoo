# SuperWoo Phase 17 — Signed Endpoint Test Results

**Environment:** local macOS PHP CLI, isolated temporary directory, PHP built-in server bound only to `127.0.0.1`, ephemeral Ed25519 key. No external endpoint, production server, or customer site was contacted.

## Results

| Suite | Result | Coverage |
|---|---:|---|
| `php tests/signed-endpoint-http.test.php` | PASS — 13 assertions | HTTP metadata/package routes, exact signed body, signature verification, content types, cache headers, no redirects, immutable package bytes/hash, HEAD behavior, 404, and 405 |
| `php tests/updater-signed.test.php` | PASS — 36 assertions | Valid/invalid and unsigned metadata, missing/unknown key, expiry, tampering, URL host/path, sequence replay, downgrade, key overlap/rotation, package hash/size/ZIP checks, redirects, network failure, and persistence failures |
| `php tests/signing-workflow.test.php` | PASS — 9 assertions | CLI signer output, canonical schema, signature/hash agreement, tamper rejection, insecure key permissions and unapproved package host rejection |

The HTTP fixture signs only a synthetic local package. Its envelope names a package on the production allowlisted host because the production verifier deliberately hard-codes that host; the HTTP test fetches the same package bytes from loopback directly to validate static serving. This is not evidence that Digtize serves the same bytes or headers.

## Additional security properties

- No redirect is emitted by the fixture and the client-side updater separately requests metadata/packages with redirects disabled.
- The fixture exposes only explicit paths and does not implement directory listing, arbitrary file lookup, request-driven URL selection, dynamic signing, or secrets in responses.
- Metadata is short-cache (`max-age=60, must-revalidate`); the versioned ZIP is immutable-cache. Production cache configuration remains unverified.
- The ephemeral private key and package are removed by the test. No production key material exists in the test.

## Reproduction

```sh
php tests/signed-endpoint-http.test.php
php tests/updater-signed.test.php
php tests/signing-workflow.test.php
```

The HTTP integration test requires local loopback networking and PHP `sodium` plus `ZipArchive`. It passed with the sandbox configured to permit only this local process/test execution; it made no outbound requests.

## Not tested

No TLS certificate, public DNS, CDN/proxy behavior, real web-server MIME/caching, production error handling, production logs, deployment procedure, or live legacy-feed immutability was tested. Those require authorized hosting-admin access and an identified staging/production target.

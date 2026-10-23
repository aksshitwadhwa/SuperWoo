# SuperWoo Updater Hardening Plan

**Prepared:** 2026-10-23
**Status:** Stage 1 local client implementation complete; Stage 2 signing and update-server integration remain pending. No production key or server change was made.

## Current implementation

`includes/class-github-updater.php` integrates with WordPress through the `Update URI` host filter, update-transient filters, and `plugins_api`. It fetches `https://digtize.com/plugins/superwoo/update.json` using `wp_remote_get`, caches decoded JSON for five minutes, and passes `package` through `esc_url_raw()` into the WordPress update object. `update.json` currently names a ZIP on the same domain.

The source currently does not explicitly set `sslverify`, disable redirects, validate the metadata schema, constrain the package host/path, bind a package digest to release metadata, or verify a signature. URL escaping is not a host authorization check. No updater-specific regression tests were found. WordPress offers `upgrader_pre_download` to short-circuit the normal package fetch, which can preserve the standard plugin-updater UI while enforcing artifact verification.

## Proposed trust and release model

### Trust anchors and metadata

1. Pin the metadata origin to `https://digtize.com/plugins/superwoo/update.json` in plugin code. Require HTTPS, normal certificate/hostname validation, no user information or nonstandard port, and no redirects. A redirect is a verification failure unless a future reviewed design explicitly allowlists each hop.
2. The metadata must include a schema version, plugin slug, release version, WordPress/PHP requirements, package URL, package SHA-256, package byte length, release sequence, signing key ID, and detached signature. Validate types, bounds, slug, semantic version, URL host/path, and size before creating an update transient.
3. Sign deterministic UTF-8 metadata using the updater's restricted canonical JSON representation, excluding the detached `signature` field. Object keys are lexically sorted; arrays preserve order; schema keys are ASCII and values have no floating-point numbers. Require canonical serialization to match the signed representation so duplicate-key ambiguity is rejected. This is not a general-purpose RFC 8785 implementation.
4. Pin the public verification key in a plugin release. Use Ed25519; verify with the WordPress-bundled sodium compatibility API or native sodium, and fail closed if neither supported verifier is available. Do not download a replacement public key from the same update endpoint.
5. Keep the private signing key outside the web root, plugin, and public update server—prefer offline/HSM-backed or access-controlled release CI signing. Restrict key use to reviewed release artifacts and audit every signing operation.
6. Require a monotonically increasing signed release sequence and a version greater than the installed release to limit replay of old-but-valid release metadata. Rotate keys by shipping a plugin release containing both the old and new public trust anchors, then stop signing with the old key after adoption.

### Package download and integrity

1. Allow only the exact approved package host `digtize.com` and the `/plugins/superwoo/` package path. Any CDN or object-storage hostname must be reviewed and added as an exact host before use; do not allow arbitrary subdomains or metadata-supplied hosts.
2. Route SuperWoo package downloads through `upgrader_pre_download`. The handler should first verify that the request is for this plugin and that the package URL exactly matches the already verified metadata.
3. Fetch using WordPress’s safe HTTP API with TLS verification explicitly enabled, a bounded timeout, streaming to a temporary file, a conservative maximum size, and redirects disabled. Reject all 3xx responses; this prevents a permitted URL from silently redirecting to an unapproved host.
4. Verify response status, allowed content length (when present), actual file size, SHA-256 digest, ZIP structure, and the expected top-level plugin directory before returning the local package path to WordPress core. Remove temporary downloads after failure or completed installation using the upgrader lifecycle.
5. Return `WP_Error` for any SuperWoo verification failure. Do not return `false` on failure: `false` allows WordPress core to continue its ordinary, unverified download path. Return `false` only for packages that are not SuperWoo’s.
6. Preserve all existing filters and update object shape for WordPress compatibility. Leave other plugins/themes and WordPress core updater behavior untouched.

TLS verification protects the connection; the signature protects release metadata from an update-server compromise; the signed SHA-256 protects package bytes from substitution. These controls complement one another.

## Update-server changes required

The update service must:

- Publish the expanded signed metadata schema and maintain deterministic canonicalization.
- Publish a SHA-256 digest, exact byte length, and monotonic release sequence for each immutable ZIP.
- Store immutable, versioned package artifacts on an explicitly approved HTTPS host; prevent replacement of an already published version’s ZIP.
- Operate the signing key securely outside the public web server. The website should serve the manifest and artifact, but should not have access to the signing private key.
- Establish key custody, two-person release review, rotation/revocation procedure, signing audit logs, disaster recovery, and process for updating pinned public keys through plugin releases.
- Serve correct content length and ZIP bytes without redirects, or propose a specific CDN host for explicit allowlisting and review.
- Retain the previous signed metadata/package for rollback at the application level, but the updater must reject downgrade/replay versions unless an explicit signed rollback release increments the sequence and has a higher version.

No signature keys should be generated or committed as part of this planning phase. The server-side signing workflow and immutable artifact storage are prerequisites to enabling signature verification in the client.

## Regression test plan

Tests should use deterministic test keys and mocked WordPress HTTP/upgrader APIs; no test should contact production or download a real package.

- Accept a correctly signed, schema-valid release with an approved URL, expected version/requirements, digest, size, and monotonic sequence.
- Reject bad/missing signature, unknown key ID, mutated metadata, duplicate JSON keys/noncanonical encoding, malformed fields, invalid slug/version/requirements, stale sequence, and downgrade.
- Reject HTTP, credentials/userinfo, unapproved host/subdomain, unapproved port, path traversal/encoded path edge cases, fragments, IP literal, and metadata URL substitution.
- Reject TLS verification failure, redirect/3xx, wrong HTTP status, missing/contradictory length, oversize artifact, truncated ZIP, invalid ZIP/root directory, and SHA-256 mismatch.
- Assert invalid metadata never enters update transients or cached state; invalid package returns `WP_Error` and never falls through to WordPress’s ordinary download path.
- Assert legitimate WordPress update response fields are unchanged; `plugins_api` remains correct; unrelated plugin/theme/core packages return `false` from the SuperWoo-only download filter.
- Assert temporary file cleanup on success and failure and key-rotation overlap behavior.

## Implementation sequence after approval

1. Agree on exact metadata canonical form, schema, allowed package host(s), package size limit, and signing-key custody.
2. Implement pure validation/signature helpers and unit tests first.
3. Add verified metadata fetching and verified transient construction.
4. Add the SuperWoo-only `upgrader_pre_download` package verification path and lifecycle cleanup.
5. Integrate update server changes and publish a signed test release on a non-production test endpoint.
6. Verify WordPress plugin update discovery, details, download, checksum rejection, install, rollback, and unrelated updater behavior on supported WordPress/PHP versions.
7. Release the trust-anchor client only after server publishing and key custody are ready; never deploy a client that expects signatures before the server publishes them.

## Stage 1 implementation boundary (2026-10-23)

The approved local implementation is in `includes/class-github-updater.php` with network-free regression coverage in `tests/updater-hardening.test.php`. Its production trust-anchor map is deliberately empty. As a result, the legacy unsigned `update.json` feed is not accepted and no update is offered until Stage 2 installs a reviewed public key and the server publishes the signed format. There is no unsigned compatibility fallback.

The Stage 1 client contract is:

- The canonical metadata URL is the existing exact HTTPS endpoint, fetched with TLS verification and redirects disabled.
- The response is canonical UTF-8 JSON with exactly two top-level fields: `manifest` and base64 `signature`. Canonicalization uses the restricted ASCII-key representation described above; whitespace, duplicate keys, reordered/noncanonical encoding, and unknown fields are rejected.
- The detached Ed25519 signature covers canonical JSON bytes of `manifest`. The signature field is outside the signed manifest. Required manifest fields are `schema_version` (integer `1`), `slug` (`superwoo`), `version`, `requires`, `requires_php`, `package`, lowercase hex `sha256`, integer `package_size`, positive integer `sequence`, `key_id`, and UTC `expires_at` no more than 30 days in the future. Stage 2B's shared signer/verifier implementation supersedes the original field list.
- Package URLs must use HTTPS on exactly `digtize.com`, port 443/default, and path `/plugins/superwoo/<safe-name>.zip`; user information, query/fragment, escapes, traversal, and redirects are rejected. The package response must be HTTP 200, have a matching `Content-Length`, fit within 50 MiB, match the signed byte count and SHA-256, and be a valid ZIP rooted at `SuperWoo/` containing `SuperWoo/superwoo.php` with no symlink or traversal entries.
- Cached manifests are signature-checked again. A site option pins the highest accepted sequence and manifest hash; lower sequences and same-sequence substitutions are rejected. A rollback must therefore be published as a higher version with a higher signed sequence.
- If a SuperWoo package request fails verification, the updater returns `WP_Error`; it never returns `false` for that request, so WordPress cannot fall through to an ordinary unsigned fetch. Requests for unrelated plugins are left unchanged.

The production public-key map remains empty because Stage 2 is not approved. For tests only, the test subclass derives a deterministic Ed25519 keypair from a fixed fixture seed in process memory. The private fixture seed is not a production credential and is not installed as a runtime trust anchor.

The update server must implement this exact envelope and manifest contract, canonicalize/sign the manifest, publish immutable ZIPs with matching size and SHA-256, return a correct content length without redirects, and provide the approved production `key_id`/public key through a separately reviewed Stage 2 change. Signing-key custody, key rotation/recovery, artifact immutability, and an isolated signed staging endpoint remain production prerequisites.

## Approval required before implementation

- Approval to modify updater code and add updater tests.
- Approval of `digtize.com` as the pinned metadata and package host, or provision of the exact alternative CDN host(s).
- Update-server owner approval and implementation of signed metadata, immutable packages/digests, signing-key custody, release sequence, and key rotation.
- Approval of the public-key trust-anchor and recovery/rotation strategy.
- Approval of a staging endpoint and release test plan. No credential or production access is requested in this plan.

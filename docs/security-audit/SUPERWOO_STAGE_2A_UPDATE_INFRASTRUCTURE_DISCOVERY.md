# SuperWoo — Stage 2A Update Infrastructure Discovery

**Date:** 2026-10-23  
**Scope:** Read-only local repository, release-history, and public-endpoint discovery. The only created artifact is this report. No source, endpoint, package, key, server, or deployed installation was changed.

## Executive finding

The plugin source is hosted in the GitHub repository `aksshitwadhwa/SuperWoo`, but plugin update metadata and package URLs are configured on the custom `digtize.com` domain. The repository does not contain the update-server implementation, deployment credentials, CI/release workflow, signing keys, or hosting configuration. The actual hosting platform and current live endpoint contents therefore remain unverified.

The local tracked `update.json` is an unsigned, flat JSON document for version `1.5.5`; it points to `https://digtize.com/plugins/superwoo/SuperWoo-1.5.5.zip`. That format is incompatible with the completed Stage 1 verifier, which accepts only a canonical signed envelope and has an intentionally empty production trust-anchor map. There is no unsigned fallback. Consequently, the Stage 1 client currently offers no update until Stage 2 provisions a production public key and the server publishes the signed format.

## Existing architecture and evidence

| Area | Repository finding | Confidence / limitation |
|---|---|---|
| Source control | Git remote `origin` is `https://github.com/aksshitwadhwa/SuperWoo.git`; current local branch is `main`. | Confirmed locally. This does not establish the update-package host. |
| WordPress update identity | `superwoo.php` sets `Update URI: https://digtize.com/plugins/superwoo/`; the updater registers `update_plugins_digtize.com`. | Confirmed in the local checkout. |
| Metadata endpoint | `includes/class-github-updater.php` pins `https://digtize.com/plugins/superwoo/update.json`. | Confirmed in the local Stage 1 source. |
| Local metadata | `update.json` has `name`, `slug`, `version`, `requires`, `requires_php`, `package`, `homepage`, and `description`; current local values identify `superwoo` version `1.5.5`. | Confirmed in the tracked checkout; may differ from live server. |
| Package URL pattern | Local metadata uses `https://digtize.com/plugins/superwoo/SuperWoo-<version>.zip`. | Confirmed locally; current live response could not be fetched. |
| Updater response path | The updater reads JSON, builds the normal WordPress plugin update object, fills update transients, and serves `plugins_api` details. | Confirmed by source/history. The Stage 1 implementation now verifies before populating these fields. |
| Historical origin | Commit `57f30b7` (“Serve SuperWoo updates from Digtize”, 2026-08-30) changed the updater from GitHub Releases API metadata to the Digtize JSON endpoint and added `update.json`. | Confirmed by Git history. GitHub remains source control; it is no longer the configured update feed. |
| Release artifacts in Git | Versioned ZIP archives are tracked through `1.4.2`. Local `SuperWoo-1.5.4.zip` and `SuperWoo-1.5.5.zip` are untracked workspace files; the tracked `update.json` points to `1.5.5`. | Confirmed locally. This does not prove what is stored on the server. |
| ZIP layout | Older tracked archives include a top-level `SuperWoo/` or `superwoo/` directory; the local `1.5.5` archive has `SuperWoo/` and a `SuperWoo/superwoo.php` header at version `1.5.5`. | Confirmed for sampled local archives. Server copies and the live installed directory casing are unknown. |
| Automation | No GitHub Actions, GitLab/CircleCI workflows, deployment scripts, package scripts, Dockerfile, or release automation files are present in the tracked tree. Release commits update `superwoo.php`, `readme.txt`, and `update.json`; historical release commits sometimes add ZIPs. | Strong evidence there is no automation in this repository. An external/private workflow may still exist. |
| Hosting platform | The configured feed/package hostname is `digtize.com`; no update-server PHP source, WordPress plugin/theme source, VPS config, DNS config, or hosting metadata is in this checkout. | Unknown. A URL path or static JSON response alone cannot identify PHP, WordPress, shared hosting, VPS, CDN, or object storage. |
| Live endpoint | A public GET to the configured endpoint was attempted, but DNS resolution for `digtize.com` failed in this environment. No live response, headers, current package, or hosting platform were observed. | Unverified. No credentials or admin/hosting access were used. |

### Existing version and package handling

The local manifest version is maintained alongside the plugin header and `Stable tag`. Historical commits labeled as releases change the metadata version/package filename; package files are sometimes committed, while recent workspace ZIPs are not tracked. This points to a release process that has at least a manual repository-edit/package step. It does not establish how files are uploaded or promoted to the Digtize server, whether the server has a control panel or custom deployment job, or whether a separate external automation exists.

The current metadata is not cryptographically bound to its ZIP. It contains no signature, public key identifier, sequence number, package hash, or package byte length. The current legacy client accepts the JSON fields and passes the package URL to WordPress; it does not authenticate metadata or package bytes.

## Stage 1 compatibility

The Stage 1 client in `includes/class-github-updater.php` is designed for WordPress’s existing updater flow, but it intentionally rejects the current unsigned metadata. Its production trust map returns an empty array until Stage 2 is approved. It requires:

- Exact metadata endpoint configured in the client, HTTPS/TLS verification, HTTP 200, bounded canonical JSON, and no redirects.
- An envelope with exactly `manifest` and base64 `signature` fields. The Ed25519 signature covers the canonical JSON manifest.
- Manifest schema version `1`, slug `superwoo`, version and platform requirements, package URL, lowercase SHA-256, integer package byte length, increasing sequence, and key ID.
- HTTPS package URL on exactly `digtize.com`, under `/plugins/superwoo/`, with a safe `.zip` filename, no URL credentials/query/fragment/encoded traversal, and no redirects.
- A matching `Content-Length`, exact signed file size and SHA-256, and a readable ZIP rooted at `SuperWoo/` with the main plugin file and no symlinks/traversal entries. ZIP verification fails closed if `ZipArchive` is unavailable.
- A pinned trust anchor in plugin code; no key is fetched from the update server. Cached metadata is verified again and lower or same-sequence-substituted manifests are rejected.
- A `WP_Error` for every SuperWoo verification failure so WordPress cannot continue with its ordinary unverified package request. Other plugin packages pass through.

Compatibility gaps and items to test before release:

1. The existing flat `update.json` lacks all signature fields and will not be accepted. Server and client must switch in a coordinated migration; do not add an unsigned compatibility path to Stage 1.
2. The live endpoint and ZIP cannot currently be checked. Confirm actual server values and response headers from an authorized staging environment before publishing the trust-anchor client.
3. Sampled archives differ in top-level directory capitalization. Stage 1 accepts the expected folder name case-insensitively; verify the WordPress upgrader replaces the existing plugin directory correctly on a case-sensitive Linux filesystem and does not create a duplicate plugin directory.
4. Stage 1 validates ZIP root and bytes but does not inspect the PHP plugin header inside the ZIP to ensure its `Version` equals the signed manifest version. The release pipeline must enforce this invariant; adding client-side verification would require a separately reviewed code change.
5. Local tests are fixture-backed, not a live WordPress `Plugin_Upgrader` integration test. Test the `plugins_api`, update-transient, download, error, cleanup, and directory replacement paths against supported WordPress/PHP versions before production release.
6. The 50 MiB limit, package host/path, and mandatory exact `Content-Length` are fixed in code. Any CDN, path, response transformation, or larger package requires an approved client change and tests.

## Recommended signed-update architecture

Use an immutable, versioned ZIP and static signed metadata on the approved Digtize HTTPS host. Keep the serving tier unable to read the signing private key.

1. A release build creates the ZIP deterministically, checks the plugin header, `readme.txt` stable tag, archive root, and absence of unexpected files, then records the final ZIP’s SHA-256 and exact byte count.
2. A reviewed release job produces the manifest required by Stage 1, with a globally increasing release sequence, key ID, version, requirements, and exact immutable package URL.
3. The release signer signs only canonical manifest bytes with Ed25519. Publish the canonical `{ "manifest": ..., "signature": ... }` envelope and ZIP without redirects, and verify served byte count/hash against the signed manifest from a non-production test endpoint before promotion.
4. Pin only the public key in the client’s `trusted_public_keys()` map. The public key is not secret and may be committed in a reviewed plugin release. Never embed or transmit the private key in the plugin or web server.
5. Prefer a managed KMS/HSM asymmetric Ed25519 key where supported, with narrowly scoped signing identity, audited invocations, two-person release approval, and no exportable private-key material. If the selected KMS cannot sign Ed25519 directly, use an offline hardware-protected signer or an isolated, ephemeral signing job with a secret mounted only for the approved release job. Do not store the private key in the web root, plugin repository, site database, or general-purpose CI variables.
6. Maintain a signed release ledger and immutable artifact storage. Never replace an existing versioned ZIP. Record source commit, build inputs, artifact digest/size, manifest sequence, signer/key ID, approval, and publication result.

The simplest compatible server deployment is a static canonical metadata file plus a static ZIP under the already allowlisted path. Whether Digtize’s existing hosting supports this configuration is unknown. Do not choose a new CDN or package host without a client allowlist review.

### Rotation and rollback

- Planned rotation: ship a client release pinning both old and new public keys; continue signing with the old key while that client version reaches installations; switch signing to the new key only after adoption is confirmed; later remove the old key in another signed client release.
- Lost or suspected-compromised private key: stop signing with it. Because a compromised key can forge a higher sequence, do not rely on that key to authorize its own replacement. Distribute a trust-anchor recovery release through a separately authenticated administrator-controlled channel and document affected versions.
- Replay protection: sequence must be strictly increasing for every release and tracked durably by the client. Preserve the high-water sequence across normal updates. Rehearse behavior after database restore or migration, where the sequence option may be rolled back or absent.
- Version downgrade prevention: metadata version must compare greater than the installed version. To restore old code, rebuild that code as a new higher version and publish it at a higher sequence; never lower the signed version or overwrite an old package.

## Migration strategy for existing installations

There is a bootstrap boundary: current installations use an unsigned updater, while the Stage 1 client intentionally refuses unsigned metadata and currently contains no production key. A zero-unsigned-fallback client cannot securely receive its first trust anchor from the old unsigned feed. The transition must not hide this fact.

Recommended strict migration:

1. Build a Stage 2 client release with the approved public trust anchor and the new signed metadata contract. Prefer a separate signed endpoint such as `update-v2.json`; do not change the old endpoint in place while legacy clients still depend on its flat schema.
2. Publish and test the signed endpoint and immutable package on a non-production hostname or isolated staging path. Validate package bytes, canonical signature, redirects, HTTP headers, ZIP root, and actual WordPress update behavior.
3. Bootstrap existing production installations through an authenticated administrator-controlled installation of the reviewed Stage 2 ZIP, with its digest independently confirmed through an approved release channel. This is a manual migration step, not an unsigned updater fallback.
4. After installation, verify that each client discovers only the signed endpoint and that signature, package, sequence, and install tests pass. Track adoption/version counts without collecting customer data.
5. Retire or disable the old unsigned endpoint after all intended installations have migrated. Sites not migrated will need the same authenticated manual installation; they must not be left on a silently trusted unsigned path.
6. Keep the old package available for operator rollback, but publish rollback code as a higher plugin version and sequence. Preserve the prior signed manifest/artifact for audit.

If dashboard-only migration is required for every existing installation, that conflicts with the strict no-unsigned-fallback requirement for clients that have no pinned key. Any one-time unsigned bootstrap would be a separate, explicitly approved risk exception; it is not recommended by this report.

## Remaining risks

- Live metadata, current published package, hosting platform, server-side access controls, TLS behavior, and release automation are not verified because the public hostname did not resolve here and no infrastructure access was provided.
- A malicious or compromised release signer can authorize malicious packages; protect signing authority with HSM/KMS controls, review, audit, and emergency revocation procedures.
- A server/database restore may roll back the client’s sequence high-water state. Recovery and replay behavior need a tested operational procedure.
- No production trust key exists in the Stage 1 client yet, and the old production feed format is unsigned. Stage 1 must not be deployed as a standalone user-facing updater build.
- Build reproducibility and package/header version equality are not established in the repository.
- The current updater ZIP-root casing must be checked on the same filesystem and WordPress versions used in production.

## Exact approvals and prerequisites

Before Stage 2 implementation or deployment, obtain explicit approval for:

1. The production public key ID and base64 Ed25519 public key to pin in plugin source, including the rotation/revocation recovery plan.
2. The final metadata schema, endpoint URL, exact approved package host/path, 50 MiB size ceiling, and required response headers.
3. Digtize update-server owner participation to publish canonical signed metadata and immutable ZIPs, configure no-redirect serving, and keep signing private keys off the serving host.
4. Signing-key custody model (KMS/HSM or offline/isolated signer), named key custodians, release approval/audit process, backup/recovery, and emergency compromise response.
5. The staging endpoint/domain and WordPress/PHP compatibility matrix for integration tests.
6. The strict migration method: authenticated manual installation of the Stage 2 trust-anchor client for existing installations, plus the schedule for retiring the unsigned feed. A dashboard bootstrap over unsigned metadata would require a separate risk exception.
7. A production deployment/change window after staging verification. This Stage 2A discovery did not deploy anything.

## Final status

```text
STAGE_2A_STATUS=COMPLETE — local repository discovery and compatibility review
SOURCE_REPOSITORY=GitHub: aksshitwadhwa/SuperWoo
CONFIGURED_UPDATE_HOST=digtize.com
CONFIGURED_METADATA_URL=https://digtize.com/plugins/superwoo/update.json
LOCAL_METADATA_FORMAT=UNSIGNED flat JSON; version 1.5.5
LOCAL_PACKAGE_PATTERN=https://digtize.com/plugins/superwoo/SuperWoo-<version>.zip
LIVE_METADATA_AND_PACKAGE=UNVERIFIED — public DNS resolution failed in this environment
UPDATE_HOSTING_PLATFORM=UNKNOWN — no server-side configuration/source available
RELEASE_AUTOMATION=NOT FOUND IN REPOSITORY; external process unknown
STAGE_1_SIGNED_FORMAT_COMPATIBLE=NO with current flat unsigned metadata; requires coordinated server change
STAGE_1_PRODUCTION_TRUST_ANCHOR=EMPTY — updates intentionally fail closed
UNSIGNED_FALLBACK=NONE
PRODUCTION_INFRASTRUCTURE_CHANGED=NO
PRODUCTION_KEYS_CREATED_OR_CHANGED=NO
SOURCE_OR_ENDPOINT_CHANGED=NO
DEPLOYMENT_OR_PACKAGE_PUBLICATION=NO
```

# SuperWoo — Final Update Signing Report

**Result:** **PARTIAL — local signing workflow verified; production integration blocked**

## Existing components inspected

- `tools/sign-superwoo-release.php` creates release metadata from final ZIP bytes and requires PHP sodium.
- `includes/class-superwoo-update-manifest.php` implements restricted canonical JSON, exact schema, Ed25519 detached signing and verification, exact package URL validation, digest fields, key ID, sequence and expiry validation (maximum 30 days).
- The active `includes/class-github-updater.php` currently consumes flat unsigned JSON. It does not invoke the manifest signature verifier. Its package SHA-256 binds bytes only to the same feed.
- Read-only fetch in this run confirmed `https://digtize.com/plugins/superwoo/update.json` remains on 1.6.31 unsigned schema. Its public ZIP returned the exact advertised 318,112 bytes and SHA-256 and passed ZIP integrity. No public key is currently activated in runtime updater code for the production release path.

## Local verification

`tests/signing-workflow.test.php` invokes the actual CLI signer using a fresh ephemeral, non-production Ed25519 key. Nine assertions passed: protected key mode, manifest envelope, schema/expiry/host validation, SHA-256 equality, verifier acceptance, metadata tamper rejection, ZIP mutation detection, insecure key permission rejection and wrong-host rejection. The fixture key/package are temporary and are not committed.

The signing utility requires an immutable ZIP whose filename matches the allowlisted URL basename. The signature is Ed25519 over canonical manifest JSON. Expiry is UTC ISO form and limited to 30 days. Package host/path is exactly `digtize.com/plugins/superwoo/`. The tool fails closed if sodium, key, package, valid metadata, output path, or ZIP checks are unavailable.

## Production prerequisites

1. Owner approval of production key ID/public-key trust anchor and offline private-key custodian. No production private key or trust anchor was generated or installed.
2. Update runtime changed to verify the signed envelope before trusting digest/package fields; retain fail-closed behavior and no unsigned fallback.
3. Server publishes immutable ZIP plus matching signed envelope, returns correct content length, HTTPS without redirects, and follows exact host/path policy.
4. Staging endpoint and installed-version bootstrap/migration tested before production. Current older clients expect unsigned flat JSON while signed-verifier clients would reject the current unsigned feed; coordinated migration is required.
5. Key rotation/revocation, sequence monotonicity, expiry refresh, rollback as a higher signed sequence/version, and incident recovery rehearsed.

Until these requirements are met, do not publish signed metadata and do not claim update authenticity. Public update feed is unchanged.

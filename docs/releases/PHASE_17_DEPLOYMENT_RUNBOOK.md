# SuperWoo Phase 17 — Deployment Runbook (Not Executed)

This runbook is for a later owner-approved infrastructure operation. No production deployment has occurred.

## Preconditions

- Identify the exact Digtize update-host account, provider/platform, document root, staging path, production path, and authorized operator.
- Provide authenticated least-privilege deployment access through the approved channel. Do not send credentials in chat or commit them.
- Confirm the package host/path is `https://digtize.com/plugins/superwoo/` and the static signed metadata route maps to `update-signed.json`.
- Capture and checksum the current legacy `update.json`, current public versioned ZIP, relevant web-server/CDN configuration, and file permissions. Store backups outside the public document root.
- Prepare filesystem rollback commands and verify access to the hosting control panel/logs before making changes.
- Confirm an isolated staging host/path with HTTPS and the same routing/cache layer. Never use synthetic metadata on a customer-facing route.
- Obtain separate approval for production key generation/activation and for a bootstrap/trust-anchor distribution method. These are not authorized by this brief.

## Staging sequence

1. Create an isolated staging directory/virtual host inaccessible to public clients except the test operator; disable directory listing and prohibit script execution in the package directory.
2. Generate an ephemeral test key on a disposable workstation; create a test ZIP, calculate final size/hash, and sign canonical metadata using `tools/sign-superwoo-release.php`.
3. Publish only the test envelope and ZIP in the isolated route. Confirm TLS, status 200, exact MIME and cache headers, no redirect, correct content length, no directory listing, and no sensitive response fields.
4. Verify the signature and package with a disposable provisioned WordPress client. Run all negative cases from `PHASE_17_SIGNED_ENDPOINT_TESTS.md`.
5. Verify access/error logs contain no private key or customer data; remove test artifacts and test key after evidence is recorded.

## Production sequence after additional approvals

1. Reconfirm production target identity and backup hashes immediately before change.
2. Upload the immutable, versioned ZIP and signed envelope to a new isolated route; do not alter `update.json`, existing ZIPs, aliases, redirects, or CDN routing.
3. Verify read-only from outside the host: TLS chain, status/headers, no redirects, exact metadata bytes, valid signature using approved public key, package size/hash, and expected 404/405 behavior where applicable.
4. Compare live legacy feed and current ZIP hashes to pre-change snapshots. Confirm no customer-facing version changed.
5. If any check fails, remove only the newly added signed-route artifacts and restore the signed-route backup. Leave legacy files untouched. Record rollback and evidence.
6. Do not activate a production key, configure customer trust anchors, publish 1.6.32, or migrate customers until separately approved.

## Current execution result

Blocked before staging/deployment: this environment has no authenticated hosting connector or supplied staging target for Digtize, and public endpoint requests were inaccessible through the available browser tool. The earlier FTP details identify a MODU WordPress host, not a verified Digtize update host. Therefore no server files/configuration were changed and no claim of production endpoint verification is made.

# SuperWoo Phase 16 — Release Readiness

**Candidate:** SuperWoo 1.6.32, local only
**Decision:** **NOT READY FOR PRODUCTION PUBLICATION**

## Candidate artifact

- ZIP: `/tmp/superwoo-phase16-release/artifacts/SuperWoo-1.6.32.zip`
- Size: **325,619 bytes**
- SHA-256: `1c773a909bd70bb405f245de55a51a63d2ffa563cbcaf6131900a90d2fd21781`
- ZIP integrity: pass; 119 entries; 71 packaged PHP files lint clean.
- Upgrade: exact final archive installed from 1.6.31 in the disposable WordPress 7.1.4/WooCommerce 11.2.1 clone; SuperWoo and WooCommerce active; settings hash identical.
- Local synthetic signed envelope: `/tmp/superwoo-phase16-release/artifacts/update-signed-test-only.json`. It uses key ID `phase16-local-ephemeral-not-production`; the matching public key is in `/tmp/superwoo-phase16-release/phase16-ephemeral-public.key`. The corresponding private test key was deleted. This envelope and public key are test fixtures only and must never be published.

## Current distribution boundary

No production signing identity or production trust anchor exists in this candidate. If no trust-anchor constant has been provisioned, the updater intentionally continues the existing unsigned legacy feed for compatibility. HTTPS, host/path, size, ZIP, and SHA-256 checks do not authenticate that legacy metadata against compromise of the hosting account or server.

When an installation explicitly provisions `SUPERWOO_UPDATE_TRUSTED_PUBLIC_KEYS`, signed-only mode is latched in the site database and the updater reads `https://digtize.com/plugins/superwoo/update-signed.json`. That endpoint was not created, staged, or inspected. The live `update.json` and plugin packages were not changed or rechecked in this phase. The last previously recorded public-feed observation in the Phase 15 report was unsigned 1.6.31; that is historical evidence, not a current live check.

Older 1.6.31 clients do not contain the signature verifier and cannot parse the signed envelope. Keep the legacy feed available during bootstrap and use a separate signed feed for provisioned clients. Do not put an empty trust-anchor list in a release and do not enable the signed endpoint until the production public key is provisioned on the intended installations.

## Owner-controlled prerequisites

1. Approve a production key ID and public-key trust-anchor delivery method (reviewed plugin release or controlled `wp-config.php` provisioning). The private key must remain offline and must never be placed on the public server or committed to Git.
2. Generate the production Ed25519 identity on the approved offline workstation; document key custody, backup, rotation overlap, and emergency revocation. This was not done.
3. Configure and stage `update-signed.json` on the update host. Verify canonical JSON, immutable versioned ZIP, exact package path, matching byte count/SHA-256, TLS, and no redirects using a disposable signed client.
4. Bootstrap existing installations through the current legacy channel with a reviewed verifier release, then provision the approved public anchor. Keep the legacy feed serving older clients during transition.
5. Run a signed staging update through WordPress end to end, including failure and rollback rehearsal. The local mock does not prove live host behavior.
6. Obtain separate publication/deployment approval. Phase 16 did not publish, deploy, alter production configuration, or modify the public feed.

The Smart Cart remains partially accepted across a limited local compatibility matrix. See [Phase 16 final acceptance](PHASE_16_FINAL_ACCEPTANCE.md) for the remaining QA scope.

```text
LOCAL_1_6_32_CANDIDATE=READY
SIGNER_TO_RUNTIME_UPDATER=PASS — ephemeral key and mocked transport
DISPOSABLE_1_6_31_TO_1_6_32_UPGRADE=PASS — exact final ZIP
PRODUCTION_PUBLIC_KEY=NOT CREATED
PRODUCTION_TRUST_ANCHOR=NOT PROVISIONED
SIGNED_UPDATE_ENDPOINT=NOT CONFIGURED OR VERIFIED
PUBLIC_LEGACY_FEED=NOT MODIFIED; LAST PREVIOUS OBSERVATION 1.6.31
SMART_CART_ACCEPTANCE=PARTIAL
PRODUCTION_READY=NO
PUBLISHED=NO
```

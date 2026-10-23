# SuperWoo Stage 2B — Existing Installation Migration

**Status:** Preparation only. No bootstrap package, production key, endpoint, or release was created or published.

## Current compatibility state

The deployed/legacy updater format is understood from repository history as a flat unsigned JSON object at `https://digtize.com/plugins/superwoo/update.json`. The locally hardened Stage 1 client accepts only a canonical signed envelope and deliberately has an empty production trust-anchor map. Stage 2B adds required signed `expires_at`; therefore a Stage 1 client built before the expiry extension also cannot accept the Stage 2B schema. Production is not confirmed to run either local build. Actual live endpoint behavior is unverified.

There is no secure cryptographic path for an old unsigned client to bootstrap a new public key from the same unsigned feed. The transition must not pretend otherwise. Existing installations continue to run the code already installed, but their dashboard update path must be managed until they receive the approved trust-anchor client.

## Proposed controlled bootstrap

1. **Prepare production signing and service first.** Owner approves key custody and public-key pinning; Digtize operator implements signed metadata, immutable package delivery, expiry, sequence, and no-redirect behavior on an isolated endpoint. No public trust key should be empty in the first hardened update-capable package.
2. **Build the first signed-capable package.** It must contain the approved production public key(s), signed-manifest verifier including `expires_at`, and the production metadata endpoint. Build from a reviewed source commit; check ZIP root, plugin header version, digest, file size, PHP syntax, and full tests.
3. **Bootstrap legacy installations through an authenticated administrative path.** Deliver the package through a controlled channel and have an authorized site administrator upload/install it from WordPress or the host's authenticated file manager after independently comparing its SHA-256 from a separate trusted channel. Record site, installed version, package digest, time, and operator. This manual bootstrap is deliberately distinct from an unsigned fallback inside the hardened updater.
4. **Validate in a production-like pilot before broad distribution.** On a disposable WordPress site, install the exact bootstrap package, verify update discovery from the signed endpoint, then test a later signed update. Test expired/bad signatures, wrong host/path, redirects, package hash mismatch, version/sequence replay, key overlap, rollback-as-forward-release, and WordPress filesystem ownership. Confirm the update transient and actual plugin directory replacement.
5. **Roll out by a controlled cohort.** First update an owner-operated pilot, then a small monitored cohort. Verify installed version and update health through authorized inventory, not customer or checkout data. Expand only after clean verification and an agreed rollback window.
6. **Retire unsigned service after the migration window.** Keep the legacy endpoint/package route only as long as the owner explicitly approves for clients still running old code. It must be time-bounded, monitored, and never consumed as a fallback by the hardened updater. After the migration cutoff, stop publishing unsigned offers and retain archived packages for manual recovery.

The legacy endpoint cannot safely serve both incompatible schemas to the same URL unless the server can reliably distinguish old and new clients. Prefer a separately named signed endpoint for a transition period (for example, `/plugins/superwoo/update-v2.json`) and pin that URL in the bootstrap package. If the endpoint must remain `update.json`, cut over only after the bootstrap cohort is ready, with a documented manual recovery route for every legacy installation. Which routing model is viable requires live server inspection and owner approval.

## Older-version support and no-fallback rule

- Older installed builds remain functional as plugins; update availability is a separate concern. Do not assure automatic updates through the old feed once it is retired.
- A temporary legacy service may continue offering old unsigned updates only under explicit, time-limited migration approval. It stays isolated from the hardened updater, must not be reintroduced as a fallback, and should publish only the reviewed bootstrap package if that is the chosen transition method.
- The safer default is manual authenticated bootstrap from a pinned digest, with old update distribution frozen during the short bootstrap window. The owner must choose between temporary legacy update service and a manual bootstrap-only window based on fleet size and operational capacity.
- After bootstrap, unsigned metadata or package downloads always fail closed. There is no “try signed, then legacy” behavior.

## Rollback and recovery

- Normal rollback is a **forward release**: rebuild known-good code with a version greater than the currently installed version and a strictly greater signed sequence. Never lower the manifest version, reuse a sequence, or overwrite an old ZIP.
- Keep immutable prior artifacts and signed manifests in an access-controlled archive. Confirm their signatures against the active trust set before use.
- If a release is broken, stop promotion, publish a higher-version/higher-sequence forward fix, and use WordPress's normal rollback/backup only under the store's separately approved recovery procedure.
- If the production signing key is suspected compromised, stop publication and do not trust a replacement signed by that key. Use a separately authenticated administrator installation of a recovery plugin build containing a new trust anchor and revoking the old one.
- Database restore can roll back the client's sequence high-water option. Before restoring, preserve sequence state or compare it with the release ledger; a recovered database must not make old signed manifests valid again. Expiry reduces, but does not eliminate, replay risk.
- Keep at least one operator-verified package and tested file/database backup for every production cohort. No production rollback was performed for this stage.

## Decisions still required

1. Approve production signing-key custody model and authorized signers.
2. Approve the first production public-key identifier and pinning source in the reviewed plugin release. Production key generation is not approved here.
3. Approve whether the signed endpoint is versioned or replaces `update.json`, and whether legacy unsigned service gets a time-bounded grace window.
4. Approve manual administrative bootstrap and independent digest delivery for existing installations.
5. Approve the pilot sites, success criteria, migration cutoff, telemetry/inventory method, and rollback authority.
6. Approve production server changes, package publication workflow, cache policy, and ownership of the signing/release ledger.

Until these decisions and the production prerequisites are complete, Stage 2B artifacts are local fixtures only. Do not deploy or publish them.

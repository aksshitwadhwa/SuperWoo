# SuperWoo Phase 17 — Trust Migration Plan

## Current client generations

1. **Pre-verifier clients (including 1.6.31):** consume the legacy flat `update.json`; they cannot verify signed envelopes.
2. **Phase 16 verifier clients without a trust anchor:** remain on the legacy feed for compatibility.
3. **Verifier clients with trust anchors or the signed-mode latch:** fetch only `update-signed.json`; invalid/missing/expired signed metadata fails closed and never falls back to unsigned data.

The legacy feed must remain unchanged and available while pre-verifier and unprovisioned clients are supported. The signed endpoint is a separate path.

## Bootstrap security constraint

Shipping a public key in a plugin update obtained through the current unsigned feed does not authenticate that bootstrap package. An attacker able to replace the legacy feed/package could replace both the verifier and embedded public key. Therefore one of these controlled bootstrap methods is required before claiming authenticated updates:

- Distribute an approved verifier release through an already authenticated channel whose package integrity is independently checked by the operator (for example, a directly verified archive hash over a separate trusted channel); or
- Have the authorized administrator install the verifier release manually after verifying its out-of-band hash/signature, then provision the public anchor through a separately authenticated administrative procedure.

Do not set the signed-mode latch on an installation until the verified trust anchor is present and the signed endpoint has been staged and validated. Do not remove or overwrite the legacy feed as a migration shortcut.

## Controlled rollout

1. Owner approves production key ID and offline custody/recovery procedure. Production key generation is a separate approval and has not occurred.
2. Operator stages signed endpoint + immutable test package in an isolated directory and verifies the Phase 16 verifier with disposable keys and a staging client.
3. Build a bootstrap release with the approved public key embedded and tested; verify exact archive checksum using the trusted bootstrap method above.
4. Pilot on an owner-controlled disposable WordPress installation. Confirm signed endpoint selection, valid update visibility, package hash verification, rejection cases, rollback, and unchanged settings.
5. Manually provision a small approved pilot group only after explicit owner approval. Verify key ID and sequence state without exposing private material.
6. Broaden distribution in batches. Keep the legacy channel operational for older installations.
7. After adoption evidence and a separately approved deprecation window, decide whether old feed support can be removed in a future major release.

## Replay, downgrade, rotation, recovery

- `sequence` must increase for every published signed manifest, including a corrected package or recovery build. Never reuse a sequence for changed bytes/metadata.
- Client-persisted sequence and accepted version reject replay, same-sequence mutation, and downgrade. A rollback must be repackaged as a higher version with a higher sequence; do not lower the sequence to restore files.
- Rotation: overlap old and new public key IDs in a verified bootstrap release; sign subsequent release with the new key; confirm adoption; remove old key in a later release.
- Suspected private-key compromise: stop signing with it, prepare a separately authenticated emergency trust-anchor update, ship a new key, and use a higher sequence. Server-side removal alone cannot revoke a key already trusted by clients.
- If a public key is lost before deployment, keep clients on their current channel; generate/approve a replacement only under the owner's key-custody procedure. Never bypass signature verification.

## Rollback

Keep the last known-good ZIP and its signed manifest offline. To recover from a defective release, produce a corrected package at a non-lower version and higher sequence, sign with a currently trusted key, publish atomically, and verify from a pilot client. Keep the legacy feed unchanged throughout. Filesystem/server rollback must restore only the signed endpoint/package artifacts and must not alter the legacy feed or client options.

## Customer migration status

No customer installation has been contacted or migrated. Production public key, trust anchor, signed endpoint, and release are not active. The exact list of installed versions and their update source is unknown.

# SuperWoo 1.5.6 — Temporary HTTPS-Only Update Feed

**Decision date:** 2026-10-23
**Scope:** Temporary updater behavior requested to make the current plugin release available through WordPress without a production signing key.

The production updater reads a flat JSON release document over HTTPS. Metadata and package requests require valid TLS, reject redirects, and constrain the package to `digtize.com/plugins/superwoo/<filename>.zip`. Metadata and package sizes are bounded. The package must contain the expected SuperWoo ZIP layout and match the SHA-256 and byte length listed in the feed before the new updater installs it.

The feed is **unsigned**. The SHA-256 value is read from that same feed, so it detects transfer corruption but is not an independent authenticity check. An attacker who can change both the feed and ZIP on the update host can provide a matching hash. This is a temporary reduction from the prior signed-update design, not equivalent protection.

The 1.5.6 release aligned its plugin header, runtime constant, readme stable tag, update feed, and ZIP at packaging time. The package includes the tracked runtime plugin files, including order-recovery files introduced after the old 1.5.5 ZIP; it excludes repository tests, signing tools, and development reports. The merged source branch is now 1.5.7; the 1.5.6 feed and ZIP remain the prior release until a separate 1.5.7 package is prepared.

Before publication, verify the ZIP hash and byte size against `update.json`, upload the immutable ZIP first, then upload the JSON feed. After publication, fetch both URLs over HTTPS and confirm HTTP 200 without redirects, matching length/hash, valid JSON, and the 1.5.6 plugin header. Dashboard update checks may retain the previous feed briefly because of WordPress transients.

No production signing key is required for this interim mode. Do not publish an unsigned feed after restoring the signed updater. Re-enable signed verification only after the approved offline key’s public key is pinned in a reviewed release and the server publishes a matching signed envelope. Existing dashboard bootstrap behavior must be tested against the exact deployed version before changing the feed format again.

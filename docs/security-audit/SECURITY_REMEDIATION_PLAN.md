# SuperWoo Security Remediation Plan

**Audit date:** 2026-10-23. Recommendations only; no remediation or production change was performed.

## Phase 0 — Emergency Containment

No compromise is confirmed, and production runtime exposure is unknown, so this audit does not direct an immediate production shutdown. The local machine has pre-fix PHP versions, and the XAMPP Apache configuration loads a PHP module; do not expose that local XAMPP instance publicly while its runtime status is unresolved.

If the host confirms a vulnerable internet-facing production runtime that cannot be patched immediately, ask the hosting provider to apply its emergency containment guidance, including restricting public access until a fixed/backported runtime is available. If compromise indicators appear, preserve evidence and coordinate containment with the host/incident-response provider before cleanup. Do not run exploit payloads against production.

## Phase 1 — Critical Patching

1. Inventory each production and staging PHP web SAPI, exact version/build/vendor, and every PHP-FPM pool, Apache module, CGI/FastCGI service, container, and scheduled CLI runtime.
2. Upgrade to a supported fixed upstream branch: PHP 8.5.12+, 8.4.27+, 8.3.36+, or 8.2.35+. For distro/LTS builds, obtain written vendor confirmation that the package includes the CVE-2026-109618 and CVE-2026-109619 backports; version strings can differ.
3. Update the local Homebrew CLI 8.5.11 and replace/patch XAMPP PHP 8.2.4 if either is used. XAMPP is x86_64 on this ARM64 host; confirm operational compatibility before changing that developer toolchain.
4. Restart PHP workers as part of an approved maintenance window after the host applies the runtime update; verify the web SAPI itself, not just CLI. Consider WordPress/WooCommerce/plugin PHP compatibility on staging first.

The PHP project says no complete mitigation replaces patching. If a host cannot patch, ask the vendor for a temporary supported isolation/WAF measure and its risk limits.

## Phase 2 — Application Hardening

1. Strengthen updater supply-chain checks: require HTTPS, allowlist the exact package host, validate version/schema, publish an immutable SHA-256 or signature through an independently trusted channel, and verify it before installation. A signature private key must not be stored in the plugin.
2. Retain the new 20 MiB combined review-media limit and five media submissions per client/hour. Add CDN/web-server `client_max_body_size` or equivalent, PHP `post_max_size`, `upload_max_filesize`, and `max_file_uploads` controls. Test that rate limiting uses the real client IP behind the production proxy; transient counters are not atomic under concurrency.
3. Ensure uploads cannot execute PHP or other scripts at the web server. Keep the MIME allowlist and WordPress upload validation; consider stricter video types/limits if the product policy permits.
4. Keep webhook logs redacted and bounded. Treat the callback URL as a secret, rotate after exposure, restrict access logs where feasible, and avoid copying it into tickets/chat. Keep logs protected at the actual server layer, especially Nginx.
5. Continue reviewing authorization, nonce validation, output escaping, and safe HTTP boundaries as feature endpoints change. No missing high-confidence auth bypass was identified in this source review.

## Phase 3 — Infrastructure Hardening

1. Ask the host to confirm OS/package security patch state, PHP handler and pool versions, Apache/Nginx/LiteSpeed configuration, PHP upload/body limits, and whether PHP execution is blocked under uploads.
2. Ensure `wp-content/uploads/superwoo-logs/` and backup/config files are denied by the real server configuration; do not rely on `.htaccess` under Nginx.
3. Restrict admin access with MFA, least-privilege roles, unique credentials, and hosting panel access controls. Rotate exposed hosting credentials through the host if any were shared in an insecure channel; do not paste credentials into audit artifacts.
4. Maintain known-good backups and a tested restore path. Capture immutable logs and file-integrity baselines for incident response.
5. Keep local XAMPP services bound/restricted to intended interfaces. Confirm actual listeners/firewall rules through the authorized OS/host admin because this audit could not inspect processes or sockets.

## Phase 4 — Verification

1. On staging, verify web and CLI PHP versions, worker restart, WordPress/WooCommerce boot, plugin activation, settings round-trip, public review image/video upload boundaries, and update discovery/install behavior.
2. Confirm rejected review media does not alter payment, cart, or review text behavior unexpectedly; test per-file, aggregate, total-request, and hourly rate boundaries through normal fixtures, not exploit payloads.
3. Verify webhook GET/HEAD readiness and authorized JSON receipt using Razorpay’s supported test/verification method; confirm bad token, oversized body, and rate limit behavior without storing personal payloads.
4. Confirm uploads and diagnostic logs are inaccessible over HTTP from an authorized test path; verify actual Nginx/Apache policy.
5. Re-run plugin PHP lint and the existing suites. Current local evidence is: all 52 PHP files lint; security 12 assertions, webhook 32, order/payment isolation 70, settings 30, media limits, free-gift pricing, and Meta observer 28 pass.
6. After host approval, verify production PHP through the hosting dashboard/host attestation and monitor logs after patch. Avoid checkout/payment tests unless separately approved and performed in staging.

## Rollback and downtime

PHP runtime upgrades and worker restarts can interrupt requests and may expose incompatibilities. Schedule host changes with a tested snapshot/rollback package and a maintenance window; use a previous supported patched build if an application compatibility issue appears. Do not roll back to a vulnerable PHP build on a public host. For plugin hardening changes, stage and version the updater change; retain a tested prior plugin package, but do not roll back into an update-feed integrity weakness. No rollback is applicable to this audit because no infrastructure or production code was changed.

## Approval boundaries

This audit created local report files only. Production PHP changes, host scans, file/database changes, WordPress account/plugin changes, log collection, service restarts, and deployment require authorization through the site's hosting/operator workflow. No passwords, API keys, bearer tokens, or private keys should be shared in chat or committed to audit reports.

# SuperWoo Compromise Investigation

**Audit date:** 2026-10-23  
**Conclusion:** No compromise indicators were observed in the inspected repository source, but live compromise status is **not assessed** because production filesystem, hosting logs, WordPress database/admin, and processes were unavailable.

## Evidence sources inspected

- SuperWoo PHP source, templates, JavaScript assets, tests, plugin metadata, and local Git working-tree/history metadata.
- Read-only pattern searches for command execution, dynamic evaluation, obfuscation helpers, file inclusion, upload handlers, REST/AJAX handlers, and database writes.
- Local filesystem timestamps for files modified since 2026-10-17, plus recent Git commit summaries.
- Local PHP/XAMPP binary identity and selected Apache PHP module configuration.
- Local test suites and PHP syntax checks.

No production server, hosting account, WordPress database, access/error log, PHP-FPM journal, control panel, OS process list, SSH configuration, firewall, or backup system was accessed. The OS denied process-list inspection. The supplied scope did not authorize live-server access.

## Suspicious files and source patterns

- No `eval`, `exec`, `system`, `shell_exec`, `passthru`, `proc_open`, or `popen` usage was found in non-test plugin PHP.
- No `base64_decode`, `gzinflate`, `gzuncompress`, or `str_rot13` obfuscation chains were found in non-test plugin PHP.
- The only production `maybe_unserialize` use is for legacy review metadata and WordPress post-meta duplication; no raw request-controlled deserialize path was observed in the searched source.
- Review uploads use WordPress `wp_handle_upload` with an explicit MIME allowlist; files are attached to review/product records. Current local changes impose a 20 MiB combined per-review media budget and a five-media-submission-per-client/hour limit. Server execution policy for uploads is unknown.
- Files with post-2026-10-17 timestamps in the repository correspond to recent Git commits/review work and local package artifacts. The remaining uncommitted source change is the requested review-media cap. This timing is consistent with local development evidence, but does not establish live deployment history.
- The requested security-indicator directory names were not found under the locally inspected `/Applications/XAMPP/xamppfiles/htdocs/Mynexthost` path. They are indicators only and do not establish malware status on hosted sites.

## Logs and observed activity

No live server or WordPress logs were inspected. Existing user-supplied Razorpay/SuperWoo logs belong to earlier order troubleshooting and do not provide a system-wide compromise investigation or coverage of the full interval from 2026-10-17 onward. Do not treat absence of plugin-level errors as evidence that no PHP exploit or persistence occurred.

SuperWoo’s abandoned-cart webhook stores a bounded, redacted receipt: event label, receipt ID, payload SHA-256, field counts, currency, and boolean indicators of customer contact fields. It does not store raw cart/customer payloads or change orders/payment state. The endpoint authenticates using a URL bearer token, requires HTTPS, limits requests/body size, rate-limits by client address, and returns 404 for a bad token. Because there is no documented signature in the reviewed integration, token secrecy and upstream/web-server access-log handling remain important.

SuperWoo logs are stored under uploads with Apache `.htaccess` and IIS `web.config` protections. Nginx does not read `.htaccess`; the actual server must deny access to the log directory. The plugin itself displays this limitation in its logs screen.

## Persistence and infrastructure

The plugin source has no shell or scheduled-task persistence mechanism discovered by the searched patterns. A code review cannot establish the absence of unknown WordPress cron records, rogue administrator accounts, modified core/plugin/theme files, uploaded shells, altered `.htaccess`/Nginx config, startup services, SSH keys, or external persistence.

## Confidence

- **Repository source review:** Moderate confidence for the specific patterns and files searched; not a formal static-analysis certification.
- **Local compromise:** No indicators observed in the reviewed repository; local host state not fully assessed.
- **Production compromise:** Not assessed. There is no evidence either way from production systems.

## Recommended next investigation steps

1. Ask the hosting provider to preserve and review Apache/Nginx access/error logs, PHP-FPM logs, WAF events, and control-panel security events from 2026-10-17 onward, looking for abnormal multipart requests, memory-exhaustion/fatal patterns, unexpected PHP uploads, and suspicious admin activity.
2. Have an authorized administrator compare WordPress core, active plugins/themes, and SuperWoo files against known release hashes/backups; inspect uploads for executable PHP and unexpected recent files without executing them.
3. Review administrator accounts, application passwords, scheduled WP-Cron events, active plugins/themes, and hosting users/SSH keys for unauthorized additions. Preserve exports as evidence before cleanup.
4. Establish the actual PHP web runtime and patch/backport status for every host, pool, container, and CLI scheduler.
5. If any compromise indicator is found, preserve disk/log evidence and engage the host/incident-response provider before deleting files or rotating/cleaning in place.

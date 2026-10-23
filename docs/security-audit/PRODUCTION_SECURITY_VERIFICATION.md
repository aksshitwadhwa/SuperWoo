# Production Security Verification Checklist

**For:** Authorized hosting administrator
**Purpose:** Read-only PHP2Shell and compromise verification for the live WordPress/WooCommerce site. This checklist does not request credentials and does not authorize configuration or production changes.

## Safety and evidence handling

- [ ] Use the hosting control panel, host-provided inventory, and read-only log/file views. Do not paste passwords, private keys, database credentials, webhook bearer URLs, customer data, or raw environment files into chat or reports.
- [ ] Record UTC/local collection time, host/site identifier, source system, and the command or panel page used. Preserve original log exports and hashes before analysis.
- [ ] Do not run exploit payloads, high-volume scans, checkout/payment attempts, cleanup tools, or file deletion/quarantine during evidence collection.
- [ ] If evidence suggests active compromise, preserve snapshots/logs and contact the hosting provider or incident-response team before modifying the site.

## Runtime and PHP patch status

- [ ] Record the PHP version and vendor/package build that serves the site’s web requests, separately for each domain, pool, container, and handler. Do not substitute CLI PHP output for web-SAPI data.
- [ ] Identify SAPI/handler: Apache mod_php, PHP-FPM via Nginx/Apache FastCGI, CGI/FastCGI, LiteSpeed/LSAPI, FrankenPHP, or containerized runtime.
- [ ] For PHP-FPM, record pool names, executable/package versions, master/worker status, service restart time, and effective pool-level overrides. Redact any values that contain secrets.
- [ ] For Apache, record loaded PHP module/build/version, active vhosts, `Listen` bindings, and whether PHP is loaded as mod_php or proxied to FPM.
- [ ] Record the vendor advisory/package evidence that CVE-2026-109618 and CVE-2026-109619 are fixed/backported. Upstream fixed versions are 8.2.35, 8.3.36, 8.4.27, and 8.5.12; PHP 8.1 and older need migration or a vendor-confirmed backport. References: [CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w), [CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp).
- [ ] If the runtime is below a fixed version and there is no vendor backport evidence, classify production exposure as potentially vulnerable and arrange a host-approved patch window. Do not treat PHP settings/WAF rules as a replacement for patching.
- [ ] Record all PHP runtimes that serve HTTP and background jobs separately; verify every worker/pool/container was restarted after a patch.

## Network and web-server configuration

- [ ] Record public versus private listener interfaces/ports for the web server and PHP-FPM/FastCGI. Confirm FPM is not exposed directly to untrusted networks and only trusted frontends can connect to its socket/port.
- [ ] Record relevant firewall/security-group/WAF rules and load-balancer origin restrictions without changing them.
- [ ] Confirm effective upload/body-size and multipart request limits at CDN/WAF/reverse proxy/web server/PHP layers.
- [ ] Confirm PHP execution is denied in uploads and other writable directories; verify policy through configuration review, not by uploading executable test files.
- [ ] Confirm diagnostic/log directories and backups are denied at the actual web server. Nginx does not enforce `.htaccess` rules.
- [ ] Review effective error logging, log retention, PHP worker recycling, and any per-request PHP overrides relevant to the PHP advisories. Do not alter configuration during this checklist.

## Logs and suspicious request review (2026-10-17 onward)

- [ ] Preserve and review web access/error logs, PHP-FPM or Apache logs, reverse-proxy/CDN/WAF logs, control-panel audit logs, and relevant system journal entries from 2026-10-17 onward; include an earlier baseline if retained.
- [ ] Look for abnormal multipart/form-data traffic, unusual upload part/header counts, empty or nested file controls, request body inconsistencies, repeated OOM/fatal/worker-crash patterns, `Maximum execution time exceeded`, and unusual PHP errors around request startup/shutdown.
- [ ] Correlate source IPs, request paths, user agents, response codes, worker restarts, and timestamps across log sources. Do not assume absence of recorded errors proves no exploitation.
- [ ] Record log gaps, sampling, retention boundaries, and whether errors/deprecations were excluded.

## Files, WordPress, and persistence checks

- [ ] Compare WordPress core, active plugins/themes, and SuperWoo files against known-good release packages or integrity hashes. Record mismatches and timestamps; do not repair/delete files during collection.
- [ ] List recently created or modified PHP/script files in uploads, cache, temporary, and writable directories; inspect as text only and do not execute suspicious files.
- [ ] Review `.htaccess`, Nginx/site configuration, PHP `.user.ini`, `wp-config.php` metadata/permissions, and other routing/auto-prepend settings for unexpected changes. Never include secret values in the report.
- [ ] Review WordPress administrator and hosting-panel accounts, application passwords/API tokens, recent role changes, login/audit history, active plugins/themes, and WP-Cron events for unexpected additions.
- [ ] Review plugin/theme install, update, activation, deletion, and updater-feed history, including SuperWoo package/version timestamps and source. Preserve package hashes and update logs.
- [ ] Check the requested MyNextHost review-only indicators—`block-lab-pro`, `suspicious-plugin-cleaner`, `responsive-block-suite`, and `cgt-blocks`—if present. Their presence alone is not evidence of malware; verify source, version, owner, and deployment records.
- [ ] Review scheduled tasks, hosting cron, SSH keys, unexpected services/processes, and outbound network records where included in the host’s authorized audit scope.

## Outcome record

For each item, record `Verified`, `Not found`, `Unknown`, or `Not collected`, plus evidence reference and confidence. Summarize:

- Web runtime/version/SAPI and vendor patch proof.
- Any vulnerable or unverified runtime/pool/container.
- Exposure path and compensating controls, clearly marked as non-remediating.
- File, account, log, update, or persistence anomalies and their timestamps.
- Evidence gaps and recommended next read-only collection.
- Whether compromise is `Confirmed`, `Suspected`, `None observed in reviewed evidence`, or `Not assessed`.

No account credentials are needed for this checklist to be shared with the authorized host administrator. The site owner should request findings and evidence references, not access secrets.

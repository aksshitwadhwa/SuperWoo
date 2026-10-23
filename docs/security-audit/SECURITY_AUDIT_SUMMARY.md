# SuperWoo Security Audit Summary

**Audit date:** 2026-10-23  
**Scope:** Read-only source, local runtime, and local XAMPP configuration review. Production access was not performed.  
**Project:** SuperWoo WordPress/WooCommerce plugin, repository version 1.5.5.

## Overall assessment

**ACTION REQUIRED.** The local native CLI is PHP 8.5.11, below the fixed PHP2Shell release 8.5.12. XAMPP also contains PHP 8.2.4, below fixed 8.2.35, and its Apache configuration loads a PHP module. No running server process or web SAPI version could be verified, and no production PHP runtime was inspected. Treat the local XAMPP runtime as potentially vulnerable until its vendor patch status and actual use are confirmed. Production exposure remains unknown.

The plugin source itself has no observed `eval`, shell execution, unsafe dynamic code execution, or obfuscation chain. The source review and local tests did not reveal evidence of compromise. Server logs, production files, WordPress users/options, scheduled tasks, active plugins/themes, and outbound traffic were not available, so compromise is **not assessed for the live site**.

## Project and environment

- SuperWoo is a PHP plugin for WordPress and WooCommerce. It declares WordPress 6.2+, PHP 7.4+, and WooCommerce 8.0+ in `superwoo.php`.
- Local native CLI: PHP 8.5.11, CLI SAPI, Apple Silicon Homebrew build. It is not proof of the web runtime.
- Local XAMPP binary: PHP 8.2.4, x86_64 Mach-O. XAMPP Apache config includes `LoadModule php_module`; whether that Apache service is running could not be verified.
- No WordPress root or `wp-config.php` was found under the inspected XAMPP `htdocs` tree. The repository itself contains no WordPress runtime.
- No Composer/npm dependency manifests or lockfiles are present in the plugin repository.
- The plugin uses WordPress/WooCommerce APIs and contacts its custom update feed at `https://digtize.com/plugins/superwoo/update.json`; the currency endpoint is configurable and its source uses WordPress safe HTTP handling.

## Findings

| Severity | Finding | Status |
|---|---|---|
| High | Local PHP 8.5.11 CLI predates the PHP2Shell fix. XAMPP PHP 8.2.4 also predates the upstream fix; vendor backport and active web use are unverified. | Local installed runtime exposure verified by version; network exposure unknown |
| Medium | Custom plugin updater trusts an HTTPS JSON feed for the package URL but does not verify a package signature/hash or constrain the package host in plugin code. | Source finding; feed/host compromise not observed |
| Medium | Public review uploads have a per-submission 20 MiB aggregate cap and five media-bearing submissions per client/hour, but PHP receives request bodies before plugin checks; host-level request limits remain necessary. The transient limiter is not atomic and keys only `REMOTE_ADDR`, so concurrency and reverse-proxy address mapping affect its strength. | Local limit implemented and tested; host config unknown |
| Low/Medium | Webhook authentication is a bearer token in the URL because the reviewed Razorpay integration documents no signature. URL paths can be retained by upstream access logs. The endpoint bounds body size/rate, rejects bad tokens, and stores a redacted receipt rather than the payload. | Source reviewed; production log handling unknown |
| Low/Medium | SuperWoo log files live under uploads. Apache/IIS deny files are created, but Nginx does not honor `.htaccess`; source documentation correctly calls for a Nginx deny rule. | Runtime server configuration unknown |

The previously identified review-media storage exposure now has a server-side limit: maximum 20 MiB across media in one review and five media submissions per client per hour, subject to the proxy/rate-limit caveats above. This only limits future processing; PHP/web-server temporary upload capacity must also be bounded. :codex-annotation{index="1"}

## Test and source-review evidence

- PHP lint: all 65 PHP files passed.
- Local suites passed: media upload limits; security hardening (12 assertions); Razorpay webhook (32); order/payment isolation (70); settings persistence (30); free-gift pricing; Meta observer (28 tests).
- `git diff --check` passed.
- Source pattern review found no `eval`, shell command execution, or obfuscation call chains in non-test PHP files.
- Requested MyNextHost indicator directories (`block-lab-pro`, `suspicious-plugin-cleaner`, `responsive-block-suite`, `cgt-blocks`) were not found under the inspected local `Mynexthost` project path. This is not evidence about any hosted account.
- No live payment, webhook, exploit, network scan, or production change was performed.

## Critical unknowns

1. Actual PHP web runtime/SAPI and patch/backport status for production and staging.
2. Whether live hosting uses Apache mod_php, PHP-FPM, CGI/FastCGI, LiteSpeed, a container, or a vendor-maintained build.
3. Active production WordPress/WooCommerce/core/plugin/theme versions, configuration, file integrity, users, scheduled jobs, and logs since 2026-10-17.
4. Nginx or other web-server rules protecting `wp-content/uploads/superwoo-logs/`, upload/body limits, backup state, and host isolation.
5. Whether the updater's public feed and package delivery enforce immutable signed releases and transport protections outside this repository.

## Top remediation priorities

1. Have the hosting administrator verify and patch every PHP web SAPI to PHP 8.5.12+, 8.4.27+, 8.3.36+, or 8.2.35+, or a vendor-confirmed backport. Restart PHP workers after patching. Patch local Homebrew/XAMPP runtimes separately if they are used.
2. Verify production/staging PHP SAPI and version from the hosting control panel or host, then record package/vendor build evidence. Do not infer it from CLI.
3. Review web/PHP security logs and file-change/account history from 2026-10-17 onward for compromise indicators; preserve evidence before cleanup if anything is suspicious.
4. Add signed update artifacts or an independently trusted package hash and strict HTTPS host allowlisting to the updater release process.
5. Configure host-level maximum request/body sizes and PHP upload limits; verify uploads cannot execute as PHP and logs are denied at the actual web server.

## Production safety status

No production host, WordPress admin, database, file, setting, service, or payment configuration was accessed or changed. **Production runtime verified: NO.** No containment or patch was executed.

## References

- [PHP security advisory archive, 2026-10-21](https://www.php.net/archive/2026.php#2026-10-21-5)
- [PHP 8.5.12 release announcement](https://news-web.php.net/php.announce/513)
- [PHP 8 changelog, fixed versions](https://www.php.net/ChangeLog-8.php#8.5.12)
- [PHP-src GHSA-w8hw-w9fx-j79w / CVE-2026-109618](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
- [PHP-src GHSA-6wmf-4rv5-p3rp / CVE-2026-109619](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)
- [NHS England advisory CC-4875](https://digital.nhs.uk/cyber-alerts/2026/cc-4875)

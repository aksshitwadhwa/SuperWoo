# Local PHP Runtime Upgrade Plan

**Prepared:** 2026-10-23 (Asia/Kolkata)
**Mode:** Discovery and recommendations only. No PHP package, XAMPP file, Apache configuration, service, or process was changed or restarted.

## Findings

### Homebrew CLI

- `command -v php` resolves to `/opt/homebrew/bin/php`.
- That is a symlink to `../Cellar/php/8.5.11_1/bin/php`; `brew list --versions php` reports `php 8.5.11_1`. `brew --prefix` is `/opt/homebrew`, and `brew --prefix php` is `/opt/homebrew/opt/php`.
- `php -v` reports PHP 8.5.11, CLI SAPI, Apple Silicon arm64, loaded config `/opt/homebrew/etc/php/8.5/php.ini`. This establishes Homebrew as the installation source.
- `brew services list` reported PHP as `none`; no PHP-FPM process or listener was observed. This is a CLI installation, not a currently running PHP network server.
- The installed Homebrew formula metadata was 8.5.11_1. Current [Homebrew Formulae](https://formulae.brew.sh/formula/php) lists PHP 8.5.12 as stable. A `brew info --json` discovery call attempted to refresh Homebrew API cache, but the OS denied its cache write; cached formula data still identified 8.5.11_1. No formula install or upgrade ran.

### XAMPP

- XAMPP root is `/Applications/XAMPP`; its PHP CLI symlink is `/Applications/XAMPP/xamppfiles/bin/php -> php-8.2.4`. The bundled `php-8.2.4`, `php-cgi-8.2.4`, and `modules/libphp.so` are x86_64 Mach-O files. The PHP module file and executable are owned by `root:admin`.
- `/Applications/XAMPP/xamppfiles/etc/httpd.conf` includes `etc/extra/httpd-xampp.conf`. That file loads `modules/libphp.so`; Apache is configured to serve PHP through the module when started.
- Apache config contains `Listen 80`; the included SSL config contains `Listen 443`. These unqualified directives bind Apache to wildcard interfaces if it is launched. They do **not** prove that Apache is running or externally reachable.
- `launchctl`/Homebrew service inventories showed no Apache or PHP service; process inspection found no `httpd`, `apache`, `php-fpm`, or `php-cgi` process. `lsof` showed no listener on ports 80 or 443 and no PHP/FPM listener. Therefore, no local PHP web runtime was observed listening on a network interface at audit time.
- XAMPP’s `manager-osx.app` metadata was not present at the expected `XAMPP.app` path. The installed binaries/configuration are sufficient to identify its PHP bundle and Apache module setup.

### Separate local listener observation

`lsof` showed `mariadbd` listening on `*:3306` for IPv4 and IPv6. This is not a PHP listener, but it means MariaDB is bound to wildcard interfaces locally. Whether macOS firewall, router, or network policy makes it reachable from other machines was not established. Have the local administrator review this separately; no firewall or database configuration was changed.

## Advisory verification

The PHP-src maintainers published both PHP2Shell advisories on 2026-10-21. They rate the issues Critical, with CVSS 4.0 9.2. Affected upstream releases are below PHP 8.2.35, 8.3.36, 8.4.27, and 8.5.12. The PHP changelog lists fixes for both advisories in those branches. PHP 8.1 and older are upstream end-of-life; a distribution/vendor backport requires confirmation from that vendor. [CVE-2026-109618 advisory](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w), [CVE-2026-109619 advisory](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp), [PHP changelog](https://www.php.net/ChangeLog-8.php#8.5.12), [PHP 8.5.12 release](https://www.php.net/releases/8_5_12.php).

Both local upstream version numbers predate the relevant fix. The Homebrew CLI version is directly verified. XAMPP 8.2.4 is **potentially vulnerable**; no vendor backport evidence was found. Because neither Apache nor PHP-FPM was running, no HTTP exposure was observed from this Mac at the time of inspection. Do not infer anything about production from these local results.

## SuperWoo compatibility

- `superwoo.php` declares `Requires PHP: 7.4`; both 8.2.35 and 8.5.12 exceed that declared floor.
- The source feature-floor scan found no use of PHP features newer than the declared PHP 7.4 floor.
- All 65 PHP files linted under local PHP 8.5.11. Existing standalone suites also passed under 8.5.11. Since 8.5.12 is a patch release on the same minor branch, the plugin source is expected to remain compatible; the exact 8.5.12 binary was not installed or tested.
- Earlier disposable-site records document WordPress 7.1.3 and WooCommerce 11.2.1, but no WordPress root was found under the inspected XAMPP `htdocs` tree in this discovery. This does not prove compatibility of every production theme/plugin. Stage any web-runtime change against the actual site stack before production.

## Recommended upgrade paths and commands

### Homebrew CLI 8.5.11 → 8.5.12

Homebrew’s current formula lists 8.5.12. First review the exact pending changes and dependencies; Homebrew may also upgrade dependencies/dependents. Do this only when the owner approves the actual upgrade:

```sh
brew update
brew outdated php
brew upgrade --dry-run php
HOMEBREW_NO_INSTALL_CLEANUP=1 brew upgrade php
php -v
php --ini
```

Do not run `brew cleanup` until compatibility is accepted. Then run the SuperWoo lint and test suites. PHP CLI upgrading will not switch the XAMPP Apache module.

Rollback for the current shell, if the old keg remains present because cleanup was disabled:

```sh
export PATH="/opt/homebrew/Cellar/php/8.5.11_1/bin:$PATH"
php -v
```

This uses the retained old keg for commands in that shell; it does not modify service settings. If Homebrew removed that keg, rollback requires restoring PHP 8.5.11 from a trusted Homebrew historical formula/tap or a verified backup. Do not hand-edit Homebrew symlinks or mix binaries and extension directories.

### XAMPP PHP 8.2.4 → fixed supported build

Do not replace `libphp.so` or PHP executables individually: the Apache module, Apache ABI, PHP build flags, extensions, and thread-safety must match. No currently available XAMPP macOS installer containing PHP 8.2.35+ was verified in this audit. Safe options:

1. Obtain an official, vendor-supported XAMPP macOS package that explicitly bundles PHP 8.2.35+ (or a newer supported fixed branch), verify its published integrity/signature, and test with a copy of the local site first; or
2. Keep XAMPP Apache stopped and plan a separately approved migration to a maintained local Apache + supported PHP-FPM stack. That requires a deliberate Apache/FastCGI configuration migration and is not a drop-in module swap.

If the vendor supports an in-place XAMPP replacement and the administrator approves a maintenance window, stop services through XAMPP Manager first and preserve the complete current tree before the vendor installer runs:

```sh
sudo ditto /Applications/XAMPP /Applications/XAMPP.pre-php2shell
```

Install the vendor’s verified package using its documented installer, then confirm:

```sh
/Applications/XAMPP/xamppfiles/bin/php -v
file /Applications/XAMPP/xamppfiles/modules/libphp.so
```

Rollback if the installation replaced `/Applications/XAMPP` and the preserved tree is intact (after confirming XAMPP is stopped):

```sh
sudo mv /Applications/XAMPP /Applications/XAMPP.post-upgrade-failed-20261023
sudo ditto /Applications/XAMPP.pre-php2shell /Applications/XAMPP
/Applications/XAMPP/xamppfiles/bin/php -v
```

Keep the failed-upgrade copy for diagnosis. Confirm how the vendor installer handles MariaDB data before using it; restoring the entire tree also restores its prior local database and configuration. These commands are provided for a later approved operation and were **not run**.

## Approval needed

- Approve the Homebrew PHP CLI upgrade after reviewing `brew upgrade --dry-run php`; its commands and tests have not been run.
- Approve XAMPP replacement only after a verified vendor package and a local database/config backup plan exist. The bundled x86_64 module is not currently serving requests, so replacement is not urgent for network containment.
- Approve any local MariaDB bind/firewall change separately after reviewing what depends on the local server.
- Production PHP verification and patching must be carried out by the authorized hosting administrator; no production access or change is included here.

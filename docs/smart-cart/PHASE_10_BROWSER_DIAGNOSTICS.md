# Phase 10 Browser Diagnostics

**Date:** 2026-10-30  
**Scope:** Local browser recovery for SuperWoo Smart Cart acceptance. No production access, deployment, customer data, or payment activity.

## Result

Browser recovery is **BLOCKED before page execution**. I tried the installed Chrome, version-pinned Playwright Chromium, Playwright Firefox and WebKit, and the connected browser runtime. None produced a usable browser page. No sandbox controls were disabled to force a pass. The full browser suite and screenshot acceptance therefore remain unverified.

## Installed Chrome findings

- Executable: `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`
- Version: `156.0.8078.74`; universal x86_64 and arm64 binary on an arm64 host.
- `codesign --verify --deep --strict --verbose=4` reports `invalid signature (code or signature have been modified)` for the app bundle. The Info.plist parses successfully; this does not establish which component was changed.
- A prior headless launch with an isolated temporary profile and browser sandbox enabled exits 134 (`SIGABRT`) before the page loads. The crash report is `~/Library/Logs/DiagnosticReports/Google Chrome-2026-10-30-2118454.ips`. Its faulting stack goes through `abort`, `___RegisterApplication_block_invoke`, `_RegisterApplication`, `TransformProcessType`, and `ChromeMain` on macOS 27.0.1 build 26A434.
- This locates the abort in macOS application registration. The available evidence does **not** prove that the invalid signature alone caused the abort; treat signature failure and the registration crash as correlated findings, not a confirmed single root cause.
- Browser profiles were created in fresh temporary directories, so the observed launch failures did not come from reusing a logged-in profile. The process-list check was restricted by the host (`pgrep` returned a process-list permission error), so conflicting Chrome processes could not be ruled out conclusively. No missing-library error was reported before the abort.
- Test harnesses previously passed `--no-sandbox`. Phase 10 removed that argument and now supplies a unique temporary profile per run. No unchanged test command was repeatedly retried.

## Fallback browsers

| Route | Exact check | Result |
|---|---|---|
| Connected browser runtime | Browser list from the supported browser client | Empty; no connected in-app or Chrome browser |
| Playwright-managed Chromium | Playwright 1.59.1, Chrome for Testing 147.0.7727.15, arm64, downloaded to `/tmp` from the official Playwright CDN | Default Playwright launch arguments included `--no-sandbox`; that default smoke attempt failed before page load. Re-run with `chromiumSandbox: true` omitted that flag, then Chromium aborted with `FATAL:content/browser/sandbox_parameters_mac.mm:67 Check failed: . : Input/output error (5)` and `SIGTRAP`. No page loaded. |
| Playwright Firefox | Firefox 148.0.2 (Playwright build 1511), headless | Process exited with `SIGABRT` immediately after launch, before a page loaded. |
| Playwright WebKit | WebKit 26.4 (Playwright build 2272), headless | `pw_run.sh` child exited with `Abort trap: 6` / exit 134 before a page loaded. |
| Safari WebDriver | System `safaridriver` is present; `safaridriver --enable` displayed a macOS password prompt | Stopped without entering a password or changing system settings. Safari remote automation is not enabled. |
| Alternative browser/container | Firefox/Chromium system binaries, Docker/Podman/Colima, and simulator runtime | Not installed/available. |

An official Google Chrome installer downloaded for diagnosis failed `pkgutil --check-signature` with `Status: invalid signature`. It was not expanded or executed and the temporary package was removed.

## Phase 11 recovery addendum — 2026-10-31

The above records Phase 10 only. Phase 11 launched standalone Playwright Chromium 147.0.7727.15 with sandboxing enabled; no `--no-sandbox` flag or macOS security bypass was used. Its smoke page executed JavaScript, accepted a click, saved `/tmp/superwoo-phase11-browser-smoke.png`, and closed cleanly. Five browser suites then passed. The in-app Browser backend was unavailable in this session. A local site was isolated at `127.0.0.1:8765`, resolving the prior redirect/cross-database confusion. Chrome’s signature issue remains unresolved; the successful shell does not repair or validate the installed Chrome app. See `PHASE_11_BROWSER_RECOVERY.md`.

## What is needed to resume

Provide one supported browser surface that can open local pages with its normal sandbox intact. For example, connect the ChatGPT browser/Chrome control session, or have the workstation operator repair/reinstall Chrome from a vendor-signed package and confirm its signature. Safari is another option after the operator enables Safari remote automation locally. Do not share the macOS password.

The disposable database and WordPress files remain under `/tmp/superwoo-phase10`; MariaDB is isolated to a Unix socket with TCP networking disabled. The attempted loopback PHP server at `127.0.0.1:8765` returned a WordPress `301` to `http://127.0.0.1:8088/`. That redirect persisted after setting loopback `WP_HOME`/`WP_SITEURL`, flushing the disposable cache, and temporarily deactivating SuperWoo, so it is not currently attributed to SuperWoo. I removed the temporary URL constants, restored the database `home` and `siteurl` to `http://superwoo-offer-audit.test`, and stopped the HTTP server. Its redirect source remains unverified; a local browser URL/host mapping must be resolved before visual work. The site still contains synthetic data only. No checkout submission or payment should be made.

## Acceptance boundary

The following are not verified because no browser page could execute: cart drawer interactions, coupon and recommendation interactions, mobile touch/scroll/focus behavior, visual states, checkout navigation, rendered settings changes, browser console, and the eight requested viewport screenshots. PHP, Node, integration, lint, and diff results are recorded in `PHASE_10_ACCEPTANCE_REPORT.md` and `TEST_REPORT.md`.

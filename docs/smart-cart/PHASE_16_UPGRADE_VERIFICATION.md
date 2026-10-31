# SuperWoo Phase 16 — Upgrade Verification

**Result:** PASS for the final local 1.6.32 archive on the guarded disposable WordPress clone.

## WP-CLI database identity guard

The prior attempt parsed `wp config get DB_NAME` as an unfiltered command-substitution string. WP-CLI 2.12 emits a PHP 8.5 deprecation notice in that output, so the expected database name was not an exact match and the run stopped before installation.

`tests/qa/wp-cli-db-guard.sh` now accepts the expected identity only when it appears as exactly one complete output line. Warning/deprecation lines before or after it are ignored; substrings, duplicate identity lines, and wrong database names are rejected. The verification script also checks the exact site URL, database connectivity, required archives, active plugins, and settings hash before and after the upgrade.

`tests/qa/wp-cli-db-guard.test.sh` passed five parser regression cases. This retains the exact database name `superwoo_phase14_candidate` and exact loopback URL `http://127.0.0.1:8770`; the guard does not allow an alternate site or database.

## Exact final archive upgrade

The final archive—not the previous Phase 15 ZIP—was installed with WP-CLI into the disposable clone:

| Check | Result |
|---|---|
| Starting installed version | 1.6.32 (the script first force-installed the 1.6.31 source archive to establish the requested starting point) |
| Upgrade path exercised | 1.6.31 → exact final 1.6.32 ZIP |
| WordPress / WooCommerce | 7.1.4 / 11.2.1 |
| SuperWoo after install | 1.6.32, active |
| WooCommerce after install | Active |
| `superwoo_settings` SHA-256 before | `16708407865b0b032b31332e0bff89d7114b8d0f9c4fb0b342d88ed8a8c0fed4` |
| `superwoo_settings` SHA-256 after | `16708407865b0b032b31332e0bff89d7114b8d0f9c4fb0b342d88ed8a8c0fed4` |

The test used only the pre-existing disposable database and synthetic local content. It did not place an order, submit checkout, or use a payment method. The plugin was left at 1.6.32 in that disposable clone.

## Artifact verification

- Final ZIP: `/tmp/superwoo-phase16-release/artifacts/SuperWoo-1.6.32.zip`
- Size: **325,619 bytes**
- SHA-256: `1c773a909bd70bb405f245de55a51a63d2ffa563cbcaf6131900a90d2fd21781`
- Archive integrity: passed (`unzip -t`)
- Archive entries: 119
- PHP files inside candidate: 71 linted, zero failures
- Main plugin header and runtime version: 1.6.32
- Candidate signing and runtime updater verification: passed with an ephemeral test key; see [Phase 16 signing integration](PHASE_16_SIGNING_INTEGRATION.md).

The package contains runtime plugin files only. Tests, docs, release tooling, local update metadata, and private keys are excluded.

# SuperWoo Smart Cart — Final Compatibility Report

**Result:** **PARTIAL — one local runtime combination tested**

| Stack | Version | Verification |
|---|---:|---|
| PHP | 8.5.11 | 97 local PHP files lint clean |
| WordPress | 7.1.4 | Disposable local site; upgrade path exercised |
| WooCommerce | 11.2.1 | 129 guarded offer/integration checks plus storefront Store API journey |
| SuperWoo | 1.6.31 → 1.6.32 | Local ZIP upgrade, activation and settings-hash preservation passed |
| Chromium | 147.0.7727.15 | Sandboxed functional suites and loopback journey |

This does not establish a supported-version matrix. No second WordPress/WooCommerce version, classic/block theme comparison, live host, payment gateway, cache/minify plugin matrix, RTL, or broad variable/out-of-stock journey was run. Production PHP remains unknown. Do not infer compatibility from declared minimums alone.

# SuperWoo Smart Cart — Final Performance Report

**Result:** **PARTIAL** — controlled local sample only

## Method and sample

One headless Chromium 147.0.7727.15 run at a 390×844 mobile viewport against `http://127.0.0.1:8765` and the disposable WooCommerce site. Off-loopback requests were blocked. The browser sandbox stayed enabled. Timings are wall-clock samples from a single run; no throttling or production network was applied.

| Measure | Observed |
|---|---:|
| DOMContentLoaded | 513 ms |
| Click to drawer visible | 216 ms |
| Click to cart item reflected | 250 ms |
| Quantity click to updated quantity | 236 ms |
| Total document request count (page + cart journey) | 64 |
| Resource Timing JS/CSS entries | 46 |
| Cart drawer CSS transferSize | 74,271 bytes |
| Cart drawer JS transferSize | 84,787 bytes |

Browser Resource Timing `transferSize` can vary with cache/compression and the total request count includes the storefront's theme, WooCommerce and unrelated assets. The count is not a SuperWoo-only request count. No standalone recommendation query duration or isolated cart recalculation benchmark was captured. No CLS, LCP, INP, Lighthouse, warm-cache/cold-cache comparison, or representative mobile bandwidth test was run.

The browser functional test verifies immediate visual drawer opening under a deliberately delayed mocked add response, and server-confirmed totals thereafter. No optimization was made based only on the single local timing sample. Performance acceptance remains partial.

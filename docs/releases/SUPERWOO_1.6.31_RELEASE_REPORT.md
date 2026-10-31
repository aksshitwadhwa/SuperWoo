# SuperWoo 1.6.31 Release Report

**Date:** 2026-10-31

## Buy Now cart recovery fix

When the customer returns from or cancels the isolated Buy Now flow and opens the SuperWoo cart, the drawer refresh now restores the saved pre-Buy-Now cart and clears the temporary snapshot. Clearing it at this point prevents a later callback from restoring stale cart contents over subsequent shopper cart changes.

The change is limited to cart restoration. It does not add or change payment processing, gateway selection, order creation, or order status handling.

## Verification

- Buy Now cart isolation regression suite: passed.
- Updater hardening suite: passed (35 assertions).
- `git diff --check`: passed.
- Published feed reports version `1.6.31` and the expected package URL/hash.
- Downloaded public ZIP matches the tested local ZIP byte-for-byte; SHA-256: `2e97b04c68b3cc6de4d179518e44aa2c9b4e3ef103a32fc59d8d30457294f08e`.
- ZIP integrity check: passed.

The live checkout cancellation flow was not exercised in a browser. The regression is covered at the cart-isolation test level; confirm the customer-facing flow after installing the update.

## Availability

The 1.6.31 package and update metadata are published at:

- Feed: `https://digtize.com/plugins/superwoo/update.json`
- Package: `https://digtize.com/plugins/superwoo/SuperWoo-1.6.31.zip`

The update distribution is live. Existing WordPress sites still need to check for updates and install 1.6.31 from Dashboard → Updates. No site installation was remotely updated by this release.

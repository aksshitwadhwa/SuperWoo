# SuperWoo Offers: Audit and Implementation Plan

**Status:** Reimplemented after approval on clean `origin/main` at `dd2e93f`
**Scope:** The Offers feature under WooCommerce → SuperWoo → Offers
**Audited code:** `includes/class-bundle-offers.php`, `admin/views/bundle-rule-row.php`, `admin/views/bundle-offer-row.php`, `public/js/admin-bundle-offers.js`, and cart drawer offer-state integration.

## Requested outcome

1. Let an administrator configure a cart price range and a discount that applies while the eligible cart subtotal is in that range.
2. Apply no more than one SuperWoo offer to a cart at a time.

The existing “Price range free product” offer remains a separate offer type unless you request otherwise. “Price range discount” below means a percentage discount on eligible cart lines, following the existing discount calculation model.

## Current behavior

- Offers are stored in the WordPress option `pbi_bundle_rules`.
- The editor currently supports **Flat product discount** (minimum quantity plus percentage) and **Price range free product** (minimum and optional maximum eligible subtotal plus one or more free products).
- Both types have a scope: whole store, category, or selected products. For price-range gifts, the subtotal is calculated from non-gift cart lines in that scope. A maximum of zero means no upper bound.
- `apply_discounts()` collects every qualifying quantity discount and every qualifying gift offer. It syncs all matching gifts, then applies the greatest matching percentage discount to each eligible product. Consequently, a cart can receive a product discount and one or more free gifts together; separate items can also be discounted by separate rules.
- The “greatest discount” selection is per cart line, not a single offer selected for the entire cart. It does not suppress a qualifying gift rule.
- Existing legacy tier rules (rules with `tiers` and no `offer_type`) are still recognized by the cart calculation. The current notice state reports qualifying modern product discounts and gifts, so it is not a complete representation of all legacy discount applications.
- Cart notices and cart drawer offer events are derived separately from offer application. Changing selection behavior without updating those paths could show misleading notices or stale “offer applied” events.

## Audit findings and implementation risks

| Finding | Impact |
| --- | --- |
| The current price-range type grants free products; it does not discount the cart. | A new offer type and admin inputs are needed to configure a percentage and range. |
| Discount and gift matching are accumulated independently. | “Only one offer” must be enforced centrally before either prices or gifts are changed. |
| Current overlapping discounts are resolved per product using the largest percentage. | This must be replaced by one cart-level winning rule; leaving the old helper path active would allow stacking. |
| Rule scope determines both eligible subtotal and which products a discount can touch. | Range qualification and discount application should use the same scope, including variation handling. |
| `woocommerce_before_calculate_totals` can run repeatedly, while this feature mutates product prices and cart contents. | The chosen offer and its effects must recalculate cleanly when quantities, products, coupons, or cart contents change; prices must not compound across recalculations. |
| Free gifts are automatically inserted, zero-priced, quantity-limited, and reconciled after removals. | Gift synchronization must receive only the selected offer, and must remove gifts from offers that are no longer selected or qualified. |
| Contextual notices, cart offer state, and drawer events inspect rules separately. | They must use the same selected-offer result as pricing, or customers can see multiple offers advertised when only one is applied. |
| Existing persisted rules do not have priority metadata. | Backward compatibility needs a deterministic default for current offers and a way to configure priority if priority is the selection policy. |
| Amount comparisons and price display are currency-sensitive; the current subtotal helper has INR-specific cart-item metadata fallbacks. | Range boundaries and displayed values should use the existing cart subtotal/currency behavior; implementation must avoid mixing raw product prices and display-converted values. |

## Proposed behavior for approval

### New “Price range discount” offer

Add an offer type with:

- Minimum eligible subtotal (inclusive).
- Maximum eligible subtotal (inclusive; blank or zero means no upper bound, matching the existing gift range convention).
- Discount percentage, greater than 0 and at most 100.
- Existing “Applies to” scope: whole store, category, or selected products.

The percentage applies to all eligible, non-gift cart lines in the selected scope, using each line’s regular price and the existing WooCommerce price-decimal rounding behavior. The range is checked against the scoped, non-gift subtotal using the current cart price/currency calculation. Range values are cart subtotal thresholds, not individual product price filters.

### One offer per cart

Select one qualifying enabled rule for the whole cart before mutating prices or gifts. Only that selected rule may produce a discount or add a gift. Other matching rules, including legacy tier rules, must not affect prices or add gifts in that calculation.

**Recommended selection policy:** Add an explicit offer priority to each offer (lower number wins); when priorities tie, use the current saved rule order. The first qualifying rule by that order wins. This is predictable and lets the merchant choose whether a gift, quantity discount, or range discount takes precedence. Existing rules receive a stable default priority based on their current saved order so an upgrade does not silently reorder promotions.

The admin list/editor should make this priority visible and editable. If you prefer the most valuable discount to win automatically, or want a different precedence rule, that policy should be settled before implementation because gifts and percentage discounts cannot be compared consistently without defining gift value.

### Customer-facing behavior

- Only the selected offer is reported as applied in cart/cart-drawer offer state.
- A gift is present only when its own offer is the selected winner and still qualifies.
- If an offer stops qualifying, or a higher-priority qualifying offer takes precedence, remove its automatic gift and restore/recalculate customer-paid line prices.
- Upcoming-offer notices may still mention a next offer as promotional guidance, but must not describe it as applied. The active notice must identify only the selected offer.

## Implementation plan (after approval)

1. **Define rule data and compatibility.** Add the `price_discount` offer type fields (`min_amount`, `max_amount`, `discount`) and priority metadata. Normalize older rules at read time without changing their effective order. Validate malformed, negative, inverted, and out-of-range values server-side in AJAX saves.
2. **Update offer editor and list.** Add “Price range discount,” its threshold/percentage inputs and help text; show the type, scope, range/discount summary, and priority in the list. Update type-dependent field visibility and required-field validation. Preserve existing rule types and free-product selection.
3. **Centralize offer selection.** Implement one deterministic selector that evaluates enabled new and legacy rules against the current non-gift cart and returns a single winning offer. Ensure quantity discounts, range discounts, and gifts all pass through this selection before price/gift mutation.
4. **Apply the selected rule safely.** Apply a selected discount only to its eligible lines, or synchronize gifts only for the selected gift offer. Recalculate on subsequent WooCommerce totals passes and remove stale automatic gifts when the winner changes. Keep customer-added quantities intact.
5. **Align all offer messaging.** Derive cart offer state, notices, and drawer events from the same selected rule. Check behavior for a winner change, no winner, and legacy rules so the UI never claims that multiple offers applied.
6. **Review boundaries and WooCommerce integrations.** Confirm inclusive endpoints, blank/zero maximum semantics, variation/category membership, tax-inclusive display context, coupon interaction, multi-currency price metadata, empty cart, and repeated total calculations.
7. **Manual verification checklist.** Use a cart matrix covering below/at/inside/at upper/above range; scoped and out-of-scope products; overlapping rules of each type; tied priorities; old saved rules; gift replacement/removal; quantity changes; coupons; currency conversion; and cart drawer notices/events. No code or test changes are included in this audit.

## Acceptance criteria

- An administrator can save a valid price-range percentage offer and edit it later.
- The discount activates exactly at the inclusive configured boundaries, applies only to eligible non-gift lines in scope, and is absent outside the range.
- At most one enabled offer changes the cart at any time, including combinations of legacy tiers, quantity discounts, price-range discounts, and free-gift offers.
- The selected rule is determined by priority and saved order, with stable behavior for existing rules.
- Repeated totals calculations do not compound discounts or duplicate gifts; removing/changing cart contents recalculates the winner and cleans up stale gifts.
- Cart notices, offer state, and drawer events identify the applied winner consistently.
- Existing flat quantity discounts and price-range free-gift rules remain editable and retain their intended behavior when selected.

## Implementation note

The approved policy is **explicit priority (lower number wins), then saved rule order**; the price-range discount is a **percentage off eligible cart products based on the scoped cart subtotal**.

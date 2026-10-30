# Implementation Plan: promotion engine

**Branch**: `011-promotion-engine` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

A promotion is a WooCommerce coupon. Everything WooCommerce already does with coupons is used as it is: the three
rewards, minimum cart value, products, categories and brands with their exclusions, the usage limits and the expiry
date. Five settings are added to the coupon screen: apply automatically, start date, maximum discount, minimum item
count, and first order only. The conditions are checked where WooCommerce checks every coupon, so a refusal carries a
translated reason; the cap is applied inside WooCommerce's own discount calculation; and automatic promotions are kept
applied to the cart, one at a time, by the rule the owner confirmed (Q1).

## Technical Context

| | |
|---|---|
| Runs in | WordPress 7.1.4, WooCommerce 11.1.0 (block cart and checkout over the Store API), WPML 4.9.7, CoreX v0.47.0 |
| Storage | Coupon meta only: `_mizzey_automatic` (yes or no), `_mizzey_starts` (timestamp), `_mizzey_max_discount` (decimal), `_mizzey_min_items` (integer), `_mizzey_first_order` (yes or no) |
| Dependencies | None new |
| Testing | `t52-promotion-engine`: in-process on real carts (`WC()->cart`, `WC_Discounts`) and orders; over HTTP, the block cart's Store API shows an automatic promotion with its discount |

## Constitution Check

| Principle | Held? | How |
|---|---|---|
| M-1 scope traced | Yes | The seventeen PROMO rows of #281. Stacking and priority (PROMO-16, PROMO-17) are DEF; the combination setting and the launch rules are #290's |
| M-4 native first | Yes | WooCommerce coupons and core Brands carry every row they can; Mizzey adds only the five P-003 gaps |
| M-5 three lists | Yes | No safeguard added |
| M-7 tests | Yes | Every promotion path, money included, has an automated test |
| M-9 languages | Yes | Refusal messages translated into Arabic; the coupon screen is admin, English |

## Design

| Piece | Responsibility |
|---|---|
| `src/Promotions/PromotionSettings.php` | The five settings: reading them from a coupon, the coupon screen fields (General: automatic, start date, maximum discount; Usage restriction: minimum items, first order only), and saving them through `woocommerce_coupon_options_save` after WooCommerce's nonce check. A start date after the expiry date is refused with a notice and not saved |
| `src/Promotions/PromotionRules.php` | `woocommerce_coupon_is_valid` (throws, as WooCommerce's own Brands check does, so the customer sees the reason): not before the start date; the minimum item count, counting units of the items the promotion applies to (all units for a cart-wide promotion); first order only, against the account's orders or, for a guest, the billing email's, ignoring cancelled, failed, pending and draft orders and the order being placed. The Store API updates the customer from the checkout request before it validates the cart, so a guest is checked by email at checkout |
| `src/Promotions/DiscountCap.php` | When WooCommerce starts a capped promotion's calculation (`woocommerce_coupon_get_items_to_apply`, for a cart and for an order's coupon recalculation alike), it simulates WooCommerce 11.1.0's own percentage calculation on those items (its prices with sequential discounts and the item limit, what is left of each line, its leftover cents one per unit) and, only if the result would pass the cap, sets the largest percentage whose result stays within it, written to eight decimals and simulated as written; a fixed cart amount above the cap becomes the cap. The next amount read in that calculation (`woocommerce_coupon_get_amount`) receives it once, so WooCommerce's own allocation, rounding and leftover cents land on the cap. No cap on a fixed discount per product (refused on save) |
| `src/Promotions/AutomaticPromotions.php` | After the cart's totals are calculated (`woocommerce_after_calculate_totals`, with a re-entry guard), the published automatic promotions valid for the cart, and passing the checkout's own checks (the email list against the billing email; the per-customer limit counted over the account, its email and the billing email, and for a guest over any account the email belongs to, as the Store API counts it), are measured on a copy of WooCommerce's discount calculation. Free shipping is worth the cheapest paid rate the zone's own methods calculate, counted only where a coupon is what makes shipping free under the free shipping method's own threshold rules. The larger discount wins, then the earliest created (Q1); the cart is brought to that one without a notice, and the promotions this class applied are kept in the visitor's session, so one switched off is taken off a cart that holds it. A code the customer entered takes their place. The list is cached and cleared on every coupon create, update, trash and delete |

## Criterion to test map

| AC | `t52-promotion-engine` asserts |
|---|---|
| AC-1 | Percentage, fixed-cart and free-shipping promotions give their reward on a real cart |
| AC-2 | Minimum value, minimum item count (units of the eligible items), products, categories, brands (native) and first order only (account; guest by billing email; order context; over HTTP at checkout) each refuse and allow as set; the item-count, first-order and not-yet-active reasons in English and Arabic |
| AC-3 | An automatic promotion applies with no code when it qualifies and leaves when it stops qualifying; a customer's code takes its place and removing it brings it back; switching the flag off through WooCommerce takes effect at once; a guest whose email used it up gets the next one; free shipping worth 50 beats 20 off and stays chosen over repeated calculations, and is worth nothing without a coupon-accepting free shipping method; over HTTP the block cart lists the automatic promotion; with two qualifying the larger applies, on a tie the earlier (explicit creation times) |
| AC-4 | The total and per-customer limits stop the promotion |
| AC-5 | Before the start date and after the expiry the promotion is refused, also automatically; a start after the expiry is not saved |
| AC-6 | Excluded products and categories are not discounted |
| AC-7 | The cap holds exactly: across many items and odd prices, where WooCommerce would add a leftover cent, in the cart total, for a fixed cart amount, with the item limit, with sequential discounts, and on an order when its coupons are applied and recalculated; a cap is not saved on a fixed per-product discount |
| AC-8 | Every setting is saved through WooCommerce's own coupon screen save (`WC_Meta_Box_Coupon_Data::save`) and read back |

## Risks

| Risk | Mitigation |
|---|---|
| Applying a coupon inside the totals calculation loops | A re-entry guard, and the cart is changed only when the winner differs from what is applied |
| Many automatic promotions slow every cart | The list is cached; each is measured only when it is valid for the cart |
| A future WooCommerce changes when it reads the amount | Pinned 11.1.0; the cap checks in t52 fail if it does |

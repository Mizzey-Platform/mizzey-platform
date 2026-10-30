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
| `src/Promotions/PromotionRules.php` | `woocommerce_coupon_is_valid` (throws, as WooCommerce's own Brands check does, so the customer sees the reason): not before the start date; the minimum item count, counting units of the items the promotion applies to (all units for a cart-wide promotion); first order only, against the account's orders or, for a guest, the billing email's, ignoring cancelled, failed, pending and draft orders and the order being placed; a capped promotion is not applied to an existing order in the admin, because WooCommerce calculates order discounts without the filter the cap uses |
| `src/Promotions/DiscountCap.php` | `woocommerce_coupon_get_items_to_apply` starts a running total for the promotion each time WooCommerce begins its calculation; `woocommerce_coupon_get_discount_amount` (percent and per-product promotions, on the cart) gives each item at most what remains of the cap. A changed item discount turns off WooCommerce's leftover-cents top-up, so the cap holds. A fixed cart discount is its own cap |
| `src/Promotions/AutomaticPromotions.php` | After the cart's totals are calculated (`woocommerce_after_calculate_totals`, with a re-entry guard), the published automatic promotions that are valid for the cart are measured on a copy of WooCommerce's discount calculation (a free-shipping promotion is worth the cart's shipping), the larger discount wins, then the earliest created (Q1); the cart is brought to that one: others automatic ones removed, the winner applied without a notice. Codes a customer entered are left to WooCommerce's own rules. New automatic promotions are created individual-use. The list of automatic promotions is cached and cleared when a coupon is saved or trashed |

## Criterion to test map

| AC | `t52-promotion-engine` asserts |
|---|---|
| AC-1 | Percentage, fixed-cart and free-shipping promotions give their reward on a real cart |
| AC-2 | Minimum value, minimum item count (units of the eligible items), products, categories, brands (native) and first order only (account; guest by billing email; order context) each refuse and allow as set, with the reason in English and Arabic |
| AC-3 | An automatic promotion applies with no code when it qualifies and leaves when it stops qualifying; a coupon code applies when entered; over HTTP the block cart's Store API lists the automatic promotion and its discount; with two qualifying automatic promotions the larger applies, and on a tie the earlier |
| AC-4 | The total and per-customer limits stop the promotion |
| AC-5 | Before the start date and after the expiry the promotion is refused, also automatically; a start after the expiry is not saved |
| AC-6 | Excluded products and categories are not discounted |
| AC-7 | A capped percentage promotion never discounts more than the cap, across many items, including where WooCommerce would add leftover cents; a capped promotion is refused on an existing admin order |
| AC-8 | Every setting is saved through the coupon screen's own save path and read back |

## Risks

| Risk | Mitigation |
|---|---|
| Applying a coupon inside the totals calculation loops | A re-entry guard, and the cart is changed only when the winner differs from what is applied |
| Many automatic promotions slow every cart | The list is cached; each is measured only when it is valid for the cart |
| The cap filter runs only for carts | Capped promotions are refused on existing orders in the admin (documented); checkout orders carry the cart's capped discount |

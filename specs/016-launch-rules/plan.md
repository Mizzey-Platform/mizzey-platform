# Implementation Plan: the launch business rules

**Branch**: `016-launch-rules` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

Two pieces of code; the rest is WooCommerce and #281, proven. `WelcomeDiscount` issues each new customer their own
promotion from a template staff mark on the coupon screen, applied automatically for that customer only.
`Combination` is the store setting for whether promotions combine (off by default); `AutomaticPromotions::winners()`
applies it. BR-002, BR-006, BR-008, BR-009 and BR-010 are verified natively or by configuration; BR-003 and BR-007 wait
for PRE-09 behind the stock seam.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Promotions/WelcomeDiscount.php`, `Combination.php`; changes to `AutomaticPromotions.php`, `PromotionSettings.php`, `PromotionRules.php` |
| Testing | `t57-launch-rules`: in-process on the cart, shipping and refunds; BR-008 over HTTP |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | BR-003 and BR-007 pending PRE-09; no ERP mechanism chosen |
| M-4 | Native rules verified; the two gaps recorded |
| M-7 | Promotion, refund and pricing paths have automated tests and mutation checks |
| M-10 | OD-03 and OD-04 are working defaults; their criteria are `pending OD-nn` |

## Design

| Piece | What it does |
|---|---|
| `WelcomeDiscount::issue()` on `user_register` | For a Customer-role account, once: a coupon copied from the template, restricted to the customer's email, one use, first order, the template's days, automatic, owned by the customer |
| `WelcomeDiscount::codeFor()` | The customer's own code while usable, a candidate in `AutomaticPromotions::automaticCodes()` |
| `PromotionRules::valid()` | Refuses the template as a code |
| `Combination` | The setting in WooCommerce's general settings; `woocommerce_coupon_get_individual_use` true for every coupon when off |
| `AutomaticPromotions::winners()` | Off: #281's one winner, none beside an entered code. On: the best discount and the best free-shipping promotion, joining entered codes, an individual-use promotion alone |

## Criterion to test map

| AC | `t57-launch-rules` asserts |
|---|---|
| AC-1 | No template, no issue; the template saved on the screen; refused as a code; a customer's own copy with its email, one use, first order, 14 days, cap; 60 off 600, 200 off 3,000, none under 500; not another customer's; none for staff; none after use |
| AC-2 | Two units of two products ship free with an automatic free-shipping promotion of two items; one unit pays the flat rate |
| AC-4 | The setting in general settings, off by default; off: one automatic, the last of two codes; on: code, automatic discount and free shipping together; an individual-use code alone |
| AC-5 | 601 on a 600 order refused, also by line; 600 refunded; 1 more refused |
| AC-7 | A guest order linked to the account the order-received offer creates, over HTTP |
| AC-8 | With the free-shipping promotion only the free method is offered and shipping is 0 |
| AC-9 | 5,000 off a 100 order: 100 off, total not negative |
| AC-3, AC-6 | Pending PRE-09 |

## Risks

| Risk | Handling |
|---|---|
| Many customers' welcome coupons slow every cart | Each is a candidate for its own customer only, excluded from the store's automatic list |
| Combining on surprises staff | Off by default; the setting says what it does |

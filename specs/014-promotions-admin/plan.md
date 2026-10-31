# Implementation Plan: promotions managed from admin

**Branch**: `014-promotions-admin` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

The coupon screen (WooCommerce's, with #281's Mizzey settings) creates automatic promotions and codes with their
conditions, rewards, limits, exclusions, start and end. One setting is added: Paused, which keeps the coupon published
(so WooCommerce goes on recording and reversing its use) and is refused in WooCommerce's validity check, with "Paused"
shown in the coupon list. `t55-promotions-admin` proves each through the screen's own save handler, a real cart and a
real order.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `PromotionSettings` (the Paused box, its save, the list label), `PromotionRules` (the refusal) |
| Testing | `t55-promotions-admin`: in-process, `WC_Meta_Box_Coupon_Data::save()`, a real cart, `WC_Discounts` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | Every criterion met natively or by #281, except the pause, whose gap (Draft is not a pause) is recorded in Native coverage |
| M-7 | Promotion paths have an automated test |

## Criterion to test map

| AC | `t55-promotions-admin` asserts |
|---|---|
| AC-1, AC-2 | An automatic promotion with a minimum of two items, a cap and an exclusion, saved on the screen, applies with no code at two items, capped; a code with a minimum spend and a per-customer limit is valid. The combination setting is #290's |
| AC-3 | A future start and a past end both refused |
| AC-4 | A real order records a use; ticked Paused on the screen: published, refused with "This coupon is paused.", count and record kept, the placed order keeps its discount on recalculation, its cancellation reverses the use; unticked: applies again; a numeric code paused too; an automatic promotion leaves and returns to a cart that holds it |

## Risks

| Risk | Handling |
|---|---|
| Staff unpublish instead of pausing | The Paused box's description says what it does; the walkthrough (PRE-07) names it |

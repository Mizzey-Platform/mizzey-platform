# Implementation Plan: promotions managed from admin

**Branch**: `014-promotions-admin` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

No code. The coupon screen (WooCommerce's, with #281's Mizzey settings) creates automatic promotions and codes with
their conditions, rewards, limits, exclusions, start and end; WordPress's Draft and Publish statuses pause and
reactivate them, keeping WooCommerce's use count and per-customer record. `t55-promotions-admin` proves each through
the screen's own save handler and a real cart.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | None |
| Testing | `t55-promotions-admin`: in-process, `WC_Meta_Box_Coupon_Data::save()`, a real cart, `WC_Discounts` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | Every criterion met natively or by #281; nothing custom |
| M-7 | Promotion paths have an automated test |

## Criterion to test map

| AC | `t55-promotions-admin` asserts |
|---|---|
| AC-1, AC-2 | An automatic promotion with a minimum of two items, a cap and an exclusion, saved on the screen, applies with no code at two items, capped; a code with a minimum spend and a per-customer limit is valid. The combination setting is #290's |
| AC-3 | A future start and a past end both refused |
| AC-4 | Paused: the code refused, use count and record kept; reactivated: valid again, count unchanged, the customer who used it up still refused; an automatic promotion leaves and returns to a cart that holds it |

## Risks

| Risk | Handling |
|---|---|
| Staff look for a "Pause" button | The coupon list shows each coupon's status; the walkthrough (PRE-07) names Draft as pause |

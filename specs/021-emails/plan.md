# Implementation Plan: customer emails

**Branch**: `021-emails` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's own emails cover the welcome, order confirmed, delivered, refunded and failed payment events, and
WooCommerce Multilingual sends them in the order's language. Two additions and one new email close the gaps: the
welcome discount's details in the new account email, a pay-again link in the failed order email, and a Shipped email
with the carrier and tracking number for the status model's Shipped. The shared layout is PRE-03b's.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7, WCML 5.5.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Emails/`: `EmailAdditions`, `Shipped`, `ShippedEmail`, `OrderLanguage` |
| Extension points | `woocommerce_email_additional_content_customer_new_account` and `_customer_failed_order`; `woocommerce_email_classes`; `woocommerce_order_status_shipped`; `mizzey_order_shipment` (for #291) |
| Testing | `t62-emails`: in-process, through WooCommerce's mailer, mail captured |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce's emails first; each gap is in Native coverage |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every customer-facing line has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `EmailAdditions` | Appends the welcome code, what it gives and its last day to the new account email; appends the pay-again link to the failed order email while the order still needs payment |
| `Shipped`, `ShippedEmail` | On first entering Shipped, once per order under a lock: a WooCommerce email to the billing email with the carrier, tracking number and tracking page (order meta or `mizzey_order_shipment`), or a note that they follow |
| `OrderLanguage` | Runs the sending in the order's WPML language and WordPress locale, restored after |

## Criterion to test map

| AC | `t62-emails` asserts |
|---|---|
| AC-1 | The new account email names the discount and its code |
| AC-2, AC-7 | A placed order's confirmation, for a customer who declined marketing |
| AC-3 | The failed order email carries the pay-again link |
| AC-4, AC-8 | One Shipped email with carrier and tracking; none on a repeat; an Arabic order's in Arabic |
| AC-5 | The delivered confirmation (the invitation is t61's) |
| AC-6 | The refunded order email |
| AC-9 | Recorded: the sending service is #284's |

## Risks

| Risk | Handling |
|---|---|
| The carrier integration stores tracking elsewhere | `mizzey_order_shipment` lets #291 supply it |

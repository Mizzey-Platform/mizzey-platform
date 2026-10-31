# Implementation Plan: checkout

**Branch**: `019-checkout` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's block checkout and Store API already take shipping, payment, notes, coupons and terms, show the totals,
prefill a signed-in customer's address and keep each order item's price. Two pieces close the gaps: the Egyptian
address (the country locale and two address fields) and the stock safeguard (WooCommerce's order stock writers off;
each line reserved and released through the adapter). The designed checkout is PRE-03b's.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Checkout/`: `EgyptianAddress`, `OrderStock` |
| Extension points | `woocommerce_get_country_locale`; `woocommerce_register_additional_checkout_field` (location `address`); `woocommerce_store_api_checkout_order_processed` (before payment, a `RouteException` refuses it) and `woocommerce_checkout_order_processed` (classic); `woocommerce_order_status_cancelled` and `_failed`; the stock filters `woocommerce_hold_stock_for_checkout`, `woocommerce_can_reduce_order_stock`, `woocommerce_payment_complete_reduce_order_stock`, `woocommerce_can_restore_order_stock`, `woocommerce_can_restock_refunded_items`, `woocommerce_prevent_adjust_line_item_product_stock` |
| Testing | `t60-checkout`: in-process with a mock source, and over HTTP for the Store API checkout with the development fixture |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | No ERP mechanism is chosen: reservations go through the seam with an operation key per line |
| M-4 | Native first; each gap is in Native coverage |
| M-7 | Every criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| D-19 item 5 | No WooCommerce order path changes stock; every reservation and release goes through the adapter |
| FIX-04 | Every customer-facing message and label has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `EgyptianAddress` | Egypt's locale: governorate required, city, street and building; postcode and second line hidden. District (required) and landmark (optional) as WooCommerce address fields, stored with the order and the customer |
| `OrderStock` | The six stock filters off; at placement each line reserved through the gateway with `order-{id}-item-{item}-reserve-{attempt}`; a failure undoes the lines reserved, raises the attempt and refuses the placement (a `RouteException`, 409, on the Store API; an exception the classic checkout shows); cancelled or failed releases once |

## Criterion to test map

| AC | `t60-checkout` asserts |
|---|---|
| AC-1 | The locale's governorate, postcode and second line; district required and landmark optional as address fields; [HTTP] an order keeps its district |
| AC-3 | [HTTP] Shipping rates with costs, cash on delivery offered, the totals |
| AC-4 | [HTTP] The note kept on the order; coupon entry and totals are t59's |
| AC-6 | [HTTP] A missing district refused with a message naming it, in English and Arabic |
| AC-7 | WooCommerce's reduction and restoration change nothing and the other writers are off; reserved once per line, placing again reserves nothing; released once; a refused line undoes the others and a retry reserves; Cancelled releases; [HTTP] an order reserved with the fixture, WooCommerce's stock untouched; no source bound, the checkout refused |
| AC-8 | A later price change leaves the order's item price |
| AC-2, AC-5 | Native (WooCommerce's prefill and block checkout); the designed screens after PRE-03b |

## Risks

| Risk | Handling |
|---|---|
| A placement fails after its lines are reserved (payment refused) | The order goes to Failed or is cancelled as unpaid, and the reservation is released |
| The stock source does not answer a release | The gateway logs it; reconciliation is ERP-side (PRE-09) |

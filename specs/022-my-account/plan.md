# Implementation Plan: the account area

**Branch**: `022-my-account` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's My Account already has the dashboard, profile and password, and the order history and detail. Two
pieces close the gaps: an address book of up to ten Egyptian addresses with a default copied to WooCommerce's address,
and account pages for order tracking and the wishlist. The invoice download waits for #280.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Account/`: `AddressBook`, `AccountPages` |
| Extension points | REST `mizzey/v1/account/addresses`; `woocommerce_order_details_after_order_table`; `woocommerce_account_menu_items`, `woocommerce_get_endpoint_url` |
| Testing | `t63-my-account`: in-process, the REST routes through `rest_do_request()` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | WooCommerce's account pages first; each gap is in Native coverage |
| M-7 | Every criterion built here has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every customer-facing message has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `AddressBook` | Up to 10 addresses in user meta, each cleaned (required parts, the mobile number in its Egyptian form, an Egyptian governorate); the default copied to WooCommerce's billing and shipping address and district and landmark fields, its number to the shipping phone only; REST routes for the signed-in customer |
| `AccountPages` | Under an order's details on the account page: the shipment and the statuses entered with their dates; a Wishlist page after Orders in the account menu, its rewrite rules rebuilt once |

## Criterion to test map

| AC | `t63-my-account` asserts |
|---|---|
| AC-2 | Add, refuse three bad addresses, the first the default and copied with its district; set another default (the sign-in number untouched); edit, delete, another customer refused; the capacity; REST 401 for a visitor and 201 for the customer |
| AC-4 | The progress in order and the carrier and tracking number on the order view |
| AC-5 | The Wishlist page after Orders, listing the saved items |
| AC-1, AC-3 | Native (WooCommerce's account pages) |
| AC-6 | Built in specs/023-invoice: t64 downloads the invoice from the account's orders |

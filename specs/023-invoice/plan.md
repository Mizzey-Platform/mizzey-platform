# Implementation Plan: the invoice

**Branch**: `023-invoice` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce has no invoice. Our own code makes one: a number issued once per paid order, a document in the order's
language with the amounts charged, rendered to PDF by the bundled mPDF, and downloads for staff and for the order's
customer.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`); mPDF 8.3.1 (`mizzey-site/composer.lock`) |
| Code | `mizzey-site/src/Invoices/`: `InvoiceSettings`, `InvoiceNumber`, `InvoiceDocument`, `InvoicePdf`, `InvoiceRoutes` |
| Library | `mizzey-site/lib/`: mPDF and its dependencies, installed with `composer install --no-dev` from `mizzey-site/`, fonts trimmed to DejaVu Sans regular and bold (see `lib/README.md`) |
| Extension points | `woocommerce_general_settings`; `woocommerce_order_status_changed`; `template_redirect` (`?mizzey-invoice={id}`); `woocommerce_my_account_my_orders_actions`; `woocommerce_admin_order_actions`; `woocommerce_admin_order_data_after_order_details` |
| Testing | `t64-invoice`: in-process, and over HTTP for the account's download |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 (2.0.0) | WooCommerce has no invoice; our own code, no invoice plugin (D-24); each gap is in Native coverage |
| M-7 | Every criterion built here has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| M-8 | The library is pinned by `composer.lock` and recorded in `stack.lock.json` |
| FIX-04 | Every label has its Arabic |

## Design

| Piece | What it does |
|---|---|
| `InvoiceSettings` | The seller's legal name, address, tax registration and commercial register numbers, a footer note and the number prefix, as a section of WooCommerce's General settings |
| `InvoiceNumber` | The order's number and issue date, issued on a paid status or on first request, under a GET_LOCK, the counter read from the database inside it |
| `InvoiceDocument` | The HTML, in the order's language (specs/021's `OrderLanguage`) and direction, every value escaped |
| `InvoicePdf` | mPDF, loaded only here, DejaVu Sans with OpenType layout for Arabic; no remote resources |
| `InvoiceRoutes` | The download on the site's front (where the customer's sign-in cookie is read), with a nonce per order and a check of the user's right; the account, orders list and order screen links |

## Criterion to test map

| AC | `t64-invoice` asserts |
|---|---|
| AC-1 | Two paid orders get consecutive numbers, asking again keeps the number, an unpaid order gets none |
| AC-2 | The document's seller details, numbers, billing details, unit price, discount, shipping, total, refund and net; an Arabic order in Arabic, right to left; the PDF is made |
| AC-3 | Staff get the orders list action and the order screen's link; a customer does not |
| AC-4 | [HTTP] The account's orders link downloads a PDF; an unpaid order has no link; no nonce, or another customer, 403 |

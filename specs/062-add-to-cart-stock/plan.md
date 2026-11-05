# Implementation Plan: adding to the cart takes account of the stock source

**Branch**: `062-add-to-cart-stock` | **Date**: 2026-11-05 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Cart\AddToCartStock` on `woocommerce_add_to_cart_validation`: the quantity the cart would hold for the
stock item (its lines and language records, less the shopper's own held quantity) is asked of `StockGateway`, as
`CartStock` asks at the cart; a refusal is an error notice, which the Store API turns into the route's error. Three
new messages, with their Arabic. Scenarios that add to the cart over HTTP switch the stand-in fixture on for their run.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | ERP-03's wording, "adding to the cart ... take account of your ERP stock" |
| M-3 | P1-E: the store side of the seam only; every criterion provisional until PRE-09 |
| M-4 | WooCommerce's own validation hook and notices; only the stock question is ours |
| M-7 | Every criterion has a t102 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t102-add-to-cart-stock` asserts |
|---|---|
| AC-1 | 3 of 3 let in, 4 refused with the count; 2 in the cart and 2 more refused naming the 2; 1 more let in; none left is out of stock; over HTTP, the Store API with the fixture |
| AC-2 | Arabic-record lines count against the English record and the other way; a variation is its own item; over HTTP, the Arabic lamp refused after 3 English |
| AC-3 | A source that is down, one that does not know the item, and none bound all refuse; over HTTP, the Store API and a link add nothing with no source |
| AC-4 | 2 held by the unpaid order and 1 left: 1 more let in |
| AC-5 | The validation filter lets in, refuses with one notice, and stays quiet after an earlier refusal; a variation asked by its own record; the Store API and an add-to-cart link |
| AC-6 | Each of the four refusals reads in Arabic |

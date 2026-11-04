# Implementation Plan: what the product page states beside the product

**Branch**: `055-product-page` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\ProductPage`: a Store API extension (`mizzey-page`) with the saving, Buy Now, the delivery
estimate, the returns and authenticity statements and the share addresses; its settings section; the Buy Now redirect;
Open Graph tags on the product page.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0, WPML 4.9.7 (`stack.lock.json`) |
| Code | `mizzey-site/src/Storefront/ProductPage.php`, registered in `mizzey-site.php`; Arabic for the estimate |
| Testing | `t95-product-page`, over HTTP through the Store API and the product page |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | Every criterion is in US-04-02, US-04-04, US-04-06 or US-04-10 for the rows traced |
| M-4 | WooCommerce's prices, add-to-cart address, shipping zones and pages; only the gaps are ours |
| M-7 | Every criterion has a t95 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t95-product-page` asserts |
|---|---|
| AC-1 | 200 on sale at 150: 50 and 25%; a variable product's largest saving; none for a product not on sale |
| AC-2 | The Buy Now address with quantity 2: 302 to checkout, the cart holds the lamp twice |
| AC-3 | Default 2 to 5; the Cairo zone's 1 to 2; another state the default; the text, and in Arabic |
| AC-4 | The English and Arabic texts and their pages from the Store API in each language |
| AC-5 | The share addresses; the Open Graph tags on the product page |

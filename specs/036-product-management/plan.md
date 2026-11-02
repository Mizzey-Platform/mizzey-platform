# Implementation Plan: product management

**Branch**: `036-product-management` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

Most of #276 is WooCommerce's product screen. Two classes close the gaps: `ProductFields` (video, SEO, badges,
related products) and `PriceHistory` (ADM-50's price part). `wpml-config.xml` says which fields are the item's and
which each language's.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Catalogue/ProductFields.php`, `PriceHistory.php`; `mizzey-site/wpml-config.xml` |
| Extension points | `woocommerce_product_options_general_product_data`, `woocommerce_admin_process_product_object`, `gettext_woocommerce`, `woocommerce_related_products`, `pre_get_document_title`, `mizzey_meta_description`, `get_canonical_url`, `wp_robots`, Store API endpoint data; `woocommerce_before_/after_product(_variation)_object_save`, `add_meta_boxes` |
| Testing | `t76-product-management`: the product screen and the REST API over HTTP, the product pages in both languages, the importer; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | Stock only through the seam; stock criteria pending PRE-09 |
| M-4, D-24 | Native first; no SEO plugin; gaps recorded |
| ADR-0003 | One SEO store for products, read by the meta description filter specs/003 left for it |
| M-7 | Each criterion has an automated check |

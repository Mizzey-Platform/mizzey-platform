# Implementation Plan: stock in the admin

**Branch**: `026-admin-stock` | **Date**: 2026-10-31 | **Spec**: [spec.md](spec.md)

## Summary

One guard on WooCommerce's own save path and direct stock update, a REST refusal, and the adapter's figure shown in
the admin, with one way through for the ERP's projection.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 (`stack.lock.json`) |
| Code | `mizzey-site/src/Stock/StockWriteGuard.php` |
| Extension points | `woocommerce_before_product_object_save`, `woocommerce_before_product_variation_object_save`; `woocommerce_update_product_stock_query`; `woocommerce_rest_pre_insert_product_object` and its variation filter; `woocommerce_admin_stock_html`; `woocommerce_product_options_stock_status`; `woocommerce_variation_options_inventory`; `admin_head` |
| Testing | `t67-admin-stock`, in process, with the harness's permit taken away |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 (2.0.0) | WooCommerce has no stock authority outside itself; our own code (D-24) |
| M-7 | Each criterion has an automated check; deliberate defects are caught (`evidence/mutation.txt`) |
| FIX-04 | Every message has its Arabic |

## Criterion to test map

| AC | `t67-admin-stock` asserts |
|---|---|
| AC-1 | A product's and a variation's save keep stock, status, backorders and stock management while the rest of the edit is kept; the direct stock update writes nothing; a new product stores no figure; REST 403 for a product and a variation, 200 for a name; the projection writes |
| AC-2 | The list and the product and variation editors show the source's 7, not the stored 12, or not known; the fields are locked on the product screen |

# Implementation Plan: order management in admin

**Branch**: `030-order-management` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

WooCommerce's orders list and order screen already search by number, customer, phone and email, filter by status and
month, keep private notes and cancel; specs/010, specs/013 and specs/029 add the cancellation reason, the refund
permission and the refund reference. Three gaps remain: list filters by payment method, carrier and date range; the
shipment fields on the order screen and on the customer's order; and the packing slip.

## Technical Context

| Item | Value |
|---|---|
| Stack | WordPress 7.1.4, WooCommerce 11.1.0 with its own order tables (HPOS) |
| Code | `mizzey-site/src/Orders/OrderFilters.php`, `ShipmentBox.php`, `PackingSlip.php`; `Invoices/InvoicePdf::render()` shared by the invoice and the slip |
| Extension points | `woocommerce_order_list_table_restrict_manage_orders`, `woocommerce_order_list_table_prepare_items_query_args`, `woocommerce_admin_order_data_after_shipping_address`, `woocommerce_process_shop_order_meta`, `woocommerce_order_details_after_order_table`, `woocommerce_admin_order_actions`, `woocommerce_admin_order_data_after_order_details`, `admin_init` |
| Data | The order's `_mizzey_carrier`, `_mizzey_tracking_number`, `_mizzey_tracking_url` (already read by the Shipped email, specs/021, and written by #291) |
| Security | Shipment saved only with its nonce and `edit_shop_orders`; the slip needs `edit_shop_orders` and a per-order nonce; the tracking address must be http or https |
| Testing | `t70-order-management`, in process and over the real request for the slip's refusal; mutation evidence in `evidence/mutation.txt` |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | ADM-85, ADM-88 to ADM-91, ADM-93 only; ADM-156 bulk slips (DEF) not built |
| M-4 | Native search, notes, cancel and refund kept; the three gaps recorded; the slip is our own code (D-24) |
| M-7 | Each criterion has an automated check; deliberate defects are caught |
| FIX-04 | The slip and the customer's shipment lines have their Arabic; staff text is English (ADM-159) |

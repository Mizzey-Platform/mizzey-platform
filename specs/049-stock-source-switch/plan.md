# Implementation Plan: the stock source switch

**Branch**: `049-stock-source-switch` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

`StockGateway` asks a router, `RoutedStockSource`, which source holds each item: the ERP adapter bound before it, or
`StoreStockSource`, the store's own stock. `StockMode` decides by the master switch, the item's choice and the store
default. The store's own stock is WooCommerce's figure on the stock identity record, written only through the guard's
projection, with a ledger table that applies each change once per key and records it. `StockSourceAdmin` adds the
settings and the editor panel; `StockFeedRoute` adds `mizzey/v1/stock`.

## Technical Context

| Item | Value |
|---|---|
| Code | `mizzey-site/src/Stock/StockMode.php`, `StoreStockSource.php`, `RoutedStockSource.php`, `StockSourceAdmin.php`, `StockFeedRoute.php`; `StockGateway.php` routes per item |
| Storage | Options `mizzey_stock_default_source`, `mizzey_stock_erp_off`; meta `_mizzey_stock_source` on the stock identity record; table `{prefix}mizzey_stock_moves` (unique operation key, index by item and time), created by `dbDelta` under a schema version option |
| Concurrency | A MySQL named lock on the record holding the stock; the figure written and read back, then the ledger row, in one transaction; on failure the stock fields are put back from a snapshot, as MyISAM tables ignore the transaction |
| Extension points | `mizzey_stock_source` (the router binds last, at 1000), `woocommerce_get_settings_products`, `woocommerce_product_options_stock_status`, `woocommerce_variation_options_inventory`, `woocommerce_process_product_meta`, `woocommerce_save_product_variation`, `rest_api_init` |
| Permissions | Settings: `manage_woocommerce` (WooCommerce's screen); editor: `edit_product`; route: `mizzey_feed_stock` (Administrator, and the "Stock feed" role, which holds nothing else) |
| Testing | `t88-stock-source-switch`; the stock scenarios t53, t59, t60, t67 unchanged; mutation evidence |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-3 | No ERP mechanism is chosen: the router wraps whatever adapter PRE-09 binds; the route is offered, not chosen |
| M-4 | WooCommerce's own stock figure is the store's figure; only the missing operation key, ledger and routing are ours |
| M-5 | The switch, the route and the store's own stock are owner-approved safeguards (D-28), listed apart from the contractual criteria |
| M-7 | Every criterion and safeguard has an automated check |
| D-19 item 5 | Items on the ERP source are still never written locally; the store's figure is written only through the projection |

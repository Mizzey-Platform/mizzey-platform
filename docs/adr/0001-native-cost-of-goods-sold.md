# ADR-0001: Product cost uses WooCommerce's native Cost of Goods Sold

**Status:** Proposed
**Date:** 2026-09-22
**Traces:** ADM-27 (P1 key, S1), RPT-11 (P1 key, S1), MIG-13 (P1-L, S1)
**Refines:** Technical Design v1.2 §6 row "ADM-27 Product cost | New field on product and variation"

## Context

The Technical Design commits ADM-27 to a new field. Discovery found that WooCommerce provides one:

- P-016 (9 Sep 2026, WooCommerce 11.1.0): native Cost of Goods Sold, `WC_Product::get_cogs_value` and
  `set_cogs_value`. A cost survived save and reload. The feature is off by default
  (`woocommerce_feature_cost_of_goods_sold_enabled = no`).
- P-017 (9 Sep 2026): the cost is frozen onto the order during `calculate_totals`. An order for 2 units at cost 100
  still read 200 after the product cost was changed to 999.
- Source reading (22 Sep 2026): `CostOfGoodsSoldController` declares `is_experimental => false` and
  `enabled_by_default => false`. The CSV importer maps "Cost of goods" to `cogs_value`
  (`includes/admin/importers/mappings/default.php`), the CSV exporter and the admin product list carry it, and REST
  v3 products and variations expose it to users who can read private products. No `cogs` reference exists in
  `src/StoreApi`.

## Decision

Use the native feature, enabled by configuration before the first order is placed. Build no custom field, table,
service or CLI command. Add custom code only if a pilot contract test fails, or if the resolution of CX-01 (which
staff roles hold financial permission) requires narrower visibility than native gives.

## Depends on

- CX-01 (open) for staff visibility (US-16-05 AC2). This ADR takes no position on it.
- OD-12 (client) for the meaning of the value (purchase or landed cost). Not for the mechanism.
- The WooCommerce version recorded in `stack.lock.json`.

## Consequences

- No schema of our own to maintain or migrate.
- The feature must be enabled before the first live order. An order placed while it is off records no cost, and
  enabling it later does not backfill.
- Arabic and English product copies under WCML must both carry the cost. The pilot tests this. It is unverified
  today.
- Technical Design §6 should be corrected in its next version (Stage 1 review comment C-01).

## Alternatives rejected

A custom meta field. It duplicates a stable native feature and loses the order-line snapshot proved by P-017. It
would also need its own import, export and REST handling.

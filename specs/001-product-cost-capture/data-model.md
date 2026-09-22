# Data model: Product cost captured from day one

No new entities or tables. Everything below is native WooCommerce 11.1.0 storage.

| Entity | Where | Field | Rules |
|---|---|---|---|
| Product cost | Simple product, and variable-product parent | `_cogs_total_value` (post meta), read and written through `WC_Product::get_cogs_value()` and `set_cogs_value()` | Decimal EGP. A blank is "no cost", which differs from 0 (spec edge case). Basis per OD-12 |
| Variation cost | Product variation | `_cogs_total_value`, plus `_cogs_value_is_additive` | Additive flag: when true, the variation's value is added to the parent's cost. The default and its effect are verified in t03 |
| Order line cost | Order item (HPOS tables) | `_cogs_total_value` (item meta) | Set when order totals are calculated. Quantity times unit cost. Not changed by later product edits (AC-2) |
| Order cost total | Order | native order COGS total | Sum of line costs. Read only |

Relationships: order line to product or variation (native). Arabic and English products are linked by a WPML
translation group. Whether the cost follows across that link is research item R-4: it does not, for the channels that never fire `save_post`, which is why `MizzeySite\Catalogue\CostTranslationSync` exists.

State: the feature flag `woocommerce_feature_cost_of_goods_sold_enabled` (yes or no) gates all of the above. Orders
created while it is `no` carry no cost, and cannot gain one later.

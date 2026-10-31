<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * No WooCommerce order path changes stock on its own (D-19 item 5: only the ERP is authoritative, and no local writer
 * may alter effective stock). Switched off: the checkout hold (`woocommerce_hold_stock_for_checkout`), the reduction at
 * payment or placement (`woocommerce_can_reduce_order_stock`, `woocommerce_payment_complete_reduce_order_stock`), the
 * restoration on cancellation (`woocommerce_can_restore_order_stock`), refund restocking
 * (`woocommerce_can_restock_refunded_items`) and the adjustment when an order line is edited
 * (`woocommerce_prevent_adjust_line_item_product_stock`). Reservations and releases go through the adapter instead
 * (specs/019, OrderStock).
 *
 * The other local writers D-19 lists (the product editors, bulk and quick edit, the importer, REST, WP-CLI, the
 * carrier's webhook) are their own PBIs'.
 *
 * See specs/018-cart.
 */
final class LocalStockWriters
{
    public const OFF = ['woocommerce_hold_stock_for_checkout', 'woocommerce_can_reduce_order_stock', 'woocommerce_payment_complete_reduce_order_stock', 'woocommerce_can_restore_order_stock', 'woocommerce_can_restock_refunded_items'];

    public static function register(): void
    {
        foreach (self::OFF as $filter) {
            add_filter($filter, '__return_false', 999);
        }
        add_filter('woocommerce_prevent_adjust_line_item_product_stock', '__return_true', 999);
    }
}

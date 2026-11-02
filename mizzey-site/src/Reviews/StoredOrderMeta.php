<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

/**
 * An order's meta value as stored, read from the database and not from any cache, for a mark that another request may
 * have just written (a sent email). WooCommerce's order tables when they hold orders (HPOS), the post meta otherwise.
 *
 * See specs/020-reviews.
 */
final class StoredOrderMeta
{
    public static function get(int $orderId, string $key): string
    {
        global $wpdb;
        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            return (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta WHERE order_id = %d AND meta_key = %s ORDER BY id DESC LIMIT 1", $orderId, $key)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        return (string) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1", $orderId, $key)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}

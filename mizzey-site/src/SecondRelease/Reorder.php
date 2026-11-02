<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\SecondRelease;

use MizzeySite\Orders\StatusModel;

defined('ABSPATH') || exit;

/**
 * Reorder from the order history (JRN-11, ACCT-09): WooCommerce's own "Order again", which puts the order's items
 * back in the cart at today's prices and stock. Offered on a delivered order (Delivered, or COD Collected), only once
 * the second release is on.
 *
 * Register ids: JRN-11, ACCT-09. See specs/047-second-release.
 */
final class Reorder
{
    public static function register(): void
    {
        add_filter('woocommerce_valid_order_statuses_for_order_again', [self::class, 'statuses'], 20);
    }

    /**
     * @param mixed $statuses
     * @return mixed
     */
    public static function statuses($statuses)
    {
        if (! ReleaseSwitch::on()) {
            return [];
        }

        return array_values(array_unique(array_merge(is_array($statuses) ? $statuses : [], StatusModel::DELIVERED)));
    }
}

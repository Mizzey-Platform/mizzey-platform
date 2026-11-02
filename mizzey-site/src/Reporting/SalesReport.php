<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reporting;

defined('ABSPATH') || exit;

/**
 * What the sales report counts as a sale, set once in WooCommerce Analytics' own settings (RPT-01, specs/034 AC-4).
 *
 * - **Date type**: the order's date (`date_created`). Analytics' default is the paid date, which a cash on delivery
 *   order only receives when it reaches Delivered after the remittance (specs/010), so cash sales would appear late,
 *   on the day the remittance is recorded. Counted by the order's date, a cash order and a card order placed the same
 *   day fall in the same period; the cash position is SHIP-16's, not the report's.
 * - **Excluded statuses**: WooCommerce's (Pending Payment, Payment Failed, Cancelled) and Delivery Failed / Returned to
 *   Origin, which is not a sale.
 * - **Actionable statuses**: the statuses the dashboard counts as orders requiring action.
 *
 * Written once, through the native options, so the owner can change them later in Analytics, Settings; the dashboard
 * (specs/035) reads the same options. The owner's reading is still to be confirmed (provisional).
 *
 * Register ids: RPT-01. See specs/034-sales-report.
 */
final class SalesReport
{
    /** Set once the settings are written; autoloaded, so the check costs no query. */
    public const SETTLED    = 'mizzey_sales_report_settled';
    public const DATE_TYPE  = 'date_created';
    /** Statuses that are never sales: WooCommerce's defaults and Delivery Failed. */
    public const NOT_SALES  = ['pending', 'failed', 'cancelled', 'delivery-failed'];
    /** The settings' version: a site settled by an earlier one is settled again. */
    public const VERSION    = '2';

    public static function register(): void
    {
        add_action('init', [self::class, 'settle'], 20);
    }

    public static function settle(): void
    {
        if (get_option(self::SETTLED) === self::VERSION) {
            return;
        }
        update_option('woocommerce_date_type', self::DATE_TYPE);
        // Whatever the store excluded already is kept, and every status that is never a sale is added to it.
        $excluded = (array) get_option('woocommerce_excluded_report_order_statuses', []);
        update_option('woocommerce_excluded_report_order_statuses', array_values(array_unique(array_merge(array_map('strval', $excluded), self::NOT_SALES))));
        update_option('woocommerce_actionable_order_statuses', StoreDashboard::ACTION_STATUSES);
        if (class_exists(\Automattic\WooCommerce\Admin\API\Reports\Cache::class)) {
            \Automattic\WooCommerce\Admin\API\Reports\Cache::invalidate();
        }
        update_option(self::SETTLED, self::VERSION, true);
    }
}

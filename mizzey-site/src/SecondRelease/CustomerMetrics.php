<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\SecondRelease;

defined('ABSPATH') || exit;

/**
 * Customer figures for staff (ADM-101, ADM-05, ADM-07).
 *
 * - **On the customer's record** (specs/033): total spend, orders, average order value, refunded orders and coupons
 *   used, from WooCommerce's own order data.
 * - **On the dashboard** (specs/035): new and returning customers over the last 30 days, from WooCommerce Analytics'
 *   order statistics; the conversion rate is Google Analytics' (it needs the visit count only Analytics has), linked.
 *
 * Shown only once the second release is on.
 *
 * Register ids: ADM-101, ADM-05, ADM-07. See specs/047-second-release.
 */
final class CustomerMetrics
{
    public static function register(): void
    {
        add_action('mizzey_customer_record_panel', [self::class, 'record'], 10, 1);
        add_action('mizzey_dashboard_widget_end', [self::class, 'dashboard']);
    }

    /** @return array{spend:float,orders:int,average:float,refunded:int,coupons:int} */
    public static function of(int $userId): array
    {
        $ids = wc_get_orders(['customer_id' => $userId, 'limit' => -1, 'return' => 'ids', 'status' => array_keys(wc_get_order_statuses())]);
        $spend = 0.0;
        $paid  = 0;
        $refunded = 0;
        $coupons  = 0;
        foreach ((array) $ids as $id) {
            $order = wc_get_order((int) $id);
            if (! $order instanceof \WC_Order) {
                continue;
            }
            if ($order->is_paid() || $order->has_status(['completed', 'cod-collected'])) {
                $spend += (float) $order->get_total() - (float) $order->get_total_refunded();
                ++$paid;
            }
            $refunded += $order->get_total_refunded() > 0 ? 1 : 0;
            $coupons  += count($order->get_coupon_codes());
        }

        return ['spend' => round($spend, 2), 'orders' => $paid, 'average' => $paid > 0 ? round($spend / $paid, 2) : 0.0, 'refunded' => $refunded, 'coupons' => $coupons];
    }

    /** @param mixed $user */
    public static function record($user): void
    {
        if (! ReleaseSwitch::on() || ! $user instanceof \WP_User) {
            return;
        }
        $m = self::of($user->ID);
        printf(
            '<table class="widefat striped mizzey-customer-figures"><tbody><tr><th>%s</th><td>%s</td></tr><tr><th>%s</th><td>%d</td></tr><tr><th>%s</th><td>%s</td></tr><tr><th>%s</th><td>%d</td></tr><tr><th>%s</th><td>%d</td></tr></tbody></table>',
            esc_html__('Total spend (net of refunds)', 'mizzey-site'),
            wp_kses_post(wc_price($m['spend'])),
            esc_html__('Paid orders', 'mizzey-site'),
            $m['orders'],
            esc_html__('Average order value', 'mizzey-site'),
            wp_kses_post(wc_price($m['average'])),
            esc_html__('Orders with a refund', 'mizzey-site'),
            $m['refunded'],
            esc_html__('Coupons used', 'mizzey-site'),
            $m['coupons']
        );
    }

    /** @return array{new:int,returning:int} Customers with an order in the last 30 days, first-time or returning. */
    public static function newAndReturning(): array
    {
        global $wpdb;
        $since = gmdate(sprintf('%s %s', 'Y-m-d', 'H:i:s'), time() - 30 * DAY_IN_SECONDS);
        $rows  = $wpdb->get_results($wpdb->prepare("SELECT returning_customer, COUNT(DISTINCT customer_id) AS n FROM {$wpdb->prefix}wc_order_stats WHERE date_created_gmt >= %s AND parent_id = 0 AND status NOT IN ('wc-failed', 'wc-cancelled', 'wc-pending', 'wc-checkout-draft') GROUP BY returning_customer", $since)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $out = ['new' => 0, 'returning' => 0];
        foreach ((array) $rows as $row) {
            $out[(int) $row->returning_customer === 1 ? 'returning' : 'new'] = (int) $row->n;
        }

        return $out;
    }

    public static function dashboard(): void
    {
        if (! ReleaseSwitch::on()) {
            return;
        }
        $c = self::newAndReturning();
        printf(
            '<p class="mizzey-dashboard-customers">%s</p>',
            esc_html(sprintf(__('Last 30 days: %1$d new customers, %2$d returning. The conversion rate is in Google Analytics (Reports, Engagement).', 'mizzey-site'), $c['new'], $c['returning']))
        );
    }
}

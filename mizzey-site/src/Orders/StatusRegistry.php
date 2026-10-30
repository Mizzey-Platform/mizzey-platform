<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

defined('ABSPATH') || exit;

/**
 * Registers the model's statuses with WooCommerce, names them for the register, and counts the ones after payment
 * as paid. Uses WooCommerce's own status filters, which serve both HPOS and the legacy order storage.
 *
 * Register ids: ORD-01 to ORD-08, ORD-12, ORD-13, ORD-14. See specs/010-order-status-model, FR-001 and FR-011.
 */
final class StatusRegistry
{
    public static function register(): void
    {
        add_filter('woocommerce_register_shop_order_post_statuses', [self::class, 'postStatuses']);
        add_filter('wc_order_statuses', [self::class, 'labels']);
        add_filter('woocommerce_order_is_paid_statuses', [self::class, 'paid']);
        add_filter('woocommerce_reports_order_statuses', [self::class, 'reported']);
        add_filter('woocommerce_email_enabled_customer_completed_order', [self::class, 'completedEmail'], 10, 2);
    }

    /**
     * Whether the labels are being drawn for a customer: on the storefront, not in the admin, the REST API, cron or
     * WP-CLI. Filterable, so a test can draw the customer's view.
     */
    public static function customerView(): bool
    {
        $view = ! is_admin() && ! (defined('REST_REQUEST') && REST_REQUEST) && ! wp_doing_cron() && ! (defined('WP_CLI') && WP_CLI);

        return (bool) apply_filters('mizzey_order_status_customer_view', $view);
    }

    /**
     * The legacy sales reports and the dashboard widget count the statuses after payment too. WooCommerce Analytics
     * includes registered statuses unless excluded; which statuses the sales report excludes is #286's (RPT-01).
     *
     * @param mixed $statuses
     * @return mixed
     */
    public static function reported($statuses)
    {
        if (! is_array($statuses) || ! array_intersect(['completed', 'processing'], $statuses)) {
            return $statuses;
        }

        return array_values(array_unique([...$statuses, ...StatusModel::PAID]));
    }

    /**
     * A cash order already delivered (COD Collected) is not told "your order is complete" when the remittance is
     * recorded days later. The delivery email itself is #285's (NOTF-06).
     *
     * @param mixed $enabled
     * @param mixed $order
     */
    public static function completedEmail($enabled, $order): bool
    {
        if ($order instanceof \WC_Order && StatusModel::enteredAt($order, StatusModel::COD_COLLECTED) !== null) {
            return false;
        }

        return (bool) $enabled;
    }

    /**
     * @param array<string,array<string,mixed>> $statuses
     * @return array<string,array<string,mixed>>
     */
    public static function postStatuses(array $statuses): array
    {
        foreach (StatusModel::added() as $status => $label) {
            $statuses['wc-' . $status] = [
                'label'                     => $label,
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                // The admin's status list counts; the admin is English only (ADM-159), so the count is not translated.
                'label_count'               => [
                    'singular' => $label . ' <span class="count">(%s)</span>',
                    'plural'   => $label . ' <span class="count">(%s)</span>',
                    'context'  => null,
                    'domain'   => null,
                ],
            ];
        }

        return $statuses;
    }

    /**
     * The statuses in model order: Pending Payment, Confirmed, Preparing, Shipped, Out for Delivery, COD Collected,
     * Delivered, then WooCommerce's others, then Delivery Failed.
     *
     * @param array<string,string> $statuses
     * @return array<string,string>
     */
    public static function labels(array $statuses): array
    {
        foreach (StatusModel::renamed() as $status => $label) {
            if (isset($statuses['wc-' . $status])) {
                $statuses['wc-' . $status] = $label;
            }
        }
        $added   = StatusModel::added();
        $ordered = [];
        foreach ($statuses as $key => $label) {
            if ($key === 'wc-completed') {
                foreach ([StatusModel::PREPARING, StatusModel::SHIPPED, StatusModel::OUT_FOR_DELIVERY, StatusModel::COD_COLLECTED] as $status) {
                    $ordered['wc-' . $status] = $added[$status];
                }
            }
            $ordered[$key] = $label;
        }
        $ordered['wc-' . StatusModel::DELIVERY_FAILED] = $added[StatusModel::DELIVERY_FAILED];
        // The remittance is between the courier and the store: a customer whose cash order is COD Collected sees
        // Delivered.
        if (self::customerView()) {
            $ordered['wc-' . StatusModel::COD_COLLECTED] = StatusModel::renamed()['completed'];
        }

        return $ordered;
    }

    /**
     * @param list<string> $statuses
     * @return list<string>
     */
    public static function paid(array $statuses): array
    {
        return array_values(array_unique([...$statuses, ...StatusModel::PAID]));
    }
}

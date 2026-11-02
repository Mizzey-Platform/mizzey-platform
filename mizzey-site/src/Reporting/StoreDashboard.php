<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reporting;

use Automattic\WooCommerce\Utilities\OrderUtil;
use MizzeySite\Catalogue\CanonicalProduct;
use MizzeySite\Payments\PaymentRecord;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockIdentity;

defined('ABSPATH') || exit;

/**
 * The store at a glance, on the WordPress dashboard (ADM-01 to ADM-04, ADM-06, ADM-08 to ADM-13, ADM-15).
 *
 * - **Sales** for today, 7 or 30 days: revenue, orders, units, average order value and refunds, read from WooCommerce
 *   Analytics' own revenue figures, so the dashboard and the sales report (RPT-01, specs/034) always agree. Charts by
 *   day, week, month and a custom range are Analytics' own (ADM-08, P1-L), one link away.
 * - **Top products**: units sold in the period, counted per physical item, so an English and an Arabic record of
 *   one product are one line (P-019's split does not reach the dashboard).
 * - **Orders requiring action** and **pending refunds** (CX-05, D-12): counts, each linked to the standard filtered
 *   orders list. Pending refunds are the refund states the launch platform has: a paid order cancelled and not yet
 *   refunded, and an order the payment layer flagged for staff (specs/028). No returns queue (ADM-120, ADM-141).
 * - **Low and out of stock, as the ERP reports them** (ADM-10, ADM-11): each physical item asked through the stock
 *   seam (specs/012), one answer per item whatever its languages, kept for ten minutes; an item the source cannot
 *   answer for is counted as unknown, never as in stock. Final criteria wait for PRE-09 (M-3).
 * - **Quick actions** (ADM-15): add a product, create a coupon, view the orders waiting to be prepared.
 *
 * Register ids: ADM-01 to ADM-04, ADM-06, ADM-08 to ADM-13, ADM-15. See specs/035-admin-dashboard.
 */
final class StoreDashboard
{
    public const CAP        = 'view_woocommerce_reports';
    public const STOCK_KEY  = 'mizzey_dashboard_stock';
    public const STOCK_TTL  = 600;
    public const RUN_KEY    = 'mizzey_dashboard_stock_run';
    public const COUNT_HOOK = 'mizzey_dashboard_stock_count';
    public const BATCH      = 200;
    public const PERIODS    = ['today' => 0, '7d' => 6, '30d' => 29];

    /** The most orders a refund list holds, newest first; the count then reads "or more". */
    public const REFUND_LIMIT = 500;

    /** The statuses whose orders wait for staff, in the order work is done. */
    public const ACTION_STATUSES = ['on-hold', 'processing', 'preparing', 'delivery-failed'];

    public static function register(): void
    {
        // The sales report's settings, which these figures read (specs/034 AC-4).
        SalesReport::register();
        add_action('wp_dashboard_setup', [self::class, 'widget']);
        add_action(self::COUNT_HOOK, [self::class, 'countBatch'], 10, 1);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [self::class, 'listOnly'], 30);
    }

    public static function widget(): void
    {
        if (current_user_can(self::CAP)) {
            wp_add_dashboard_widget('mizzey_store_glance', __('The store at a glance', 'mizzey-site'), [self::class, 'render']);
        }
    }

    /** @return array{0:string,1:string} The period's first and last moments, in the site's time zone, as Analytics takes them. */
    public static function range(string $period): array
    {
        $days  = self::PERIODS[$period] ?? self::PERIODS['7d'];
        $today = new \DateTimeImmutable('today', wp_timezone());

        return [$today->sub(new \DateInterval('P' . $days . 'D'))->format('Y-m-d\T00:00:00'), $today->format('Y-m-d\T23:59:59')];
    }

    /**
     * Revenue, orders, units, average order value and refunds for the period: Analytics' revenue totals, as the sales
     * report reads them.
     *
     * @return array{revenue:float,net:float,orders:int,units:int,aov:float,refunds:float}
     */
    public static function sales(string $after, string $before): array
    {
        $request = new \WP_REST_Request('GET', '/wc-analytics/reports/revenue/stats');
        $request->set_query_params(['after' => $after, 'before' => $before, 'interval' => 'day']);
        $totals = (array) (rest_do_request($request)->get_data()['totals'] ?? []);

        return [
            'revenue' => (float) ($totals['total_sales'] ?? 0),
            'net'     => (float) ($totals['net_revenue'] ?? 0),
            'orders'  => (int) ($totals['orders_count'] ?? 0),
            'units'   => (int) ($totals['num_items_sold'] ?? 0),
            'aov'     => (float) ($totals['avg_order_value'] ?? 0),
            'refunds' => (float) ($totals['refunds'] ?? 0),
        ];
    }

    /** The order statuses Analytics leaves out of its reports, as it stores them (`wc-` prefixed, trash as is). */
    public static function excludedStatuses(): array
    {
        $excluded = array_merge(['auto-draft', 'trash', 'checkout-draft'], (array) get_option('woocommerce_excluded_report_order_statuses', ['pending', 'failed', 'cancelled']));
        $excluded = (array) apply_filters('woocommerce_analytics_excluded_order_statuses', $excluded);

        return array_values(array_unique(array_map(static fn ($s): string => (string) $s === 'trash' ? 'trash' : 'wc-' . preg_replace('/^wc-/', '', (string) $s), $excluded)));
    }

    /** The date column Analytics counts an order by (WooCommerce's "date type" setting): paid, created or completed. */
    public static function dateColumn(): string
    {
        $type = (string) get_option('woocommerce_date_type', 'date_paid');

        return in_array($type, ['date_created', 'date_paid', 'date_completed'], true) ? $type : 'date_paid';
    }

    /**
     * The best sellers of the period by units, one line per physical item (a product, or each of its variations), with
     * the period's refunds taken off, counted as Analytics counts orders.
     *
     * @return list<array{id:int,name:string,units:int}>
     */
    public static function topProducts(string $after, string $before, int $limit = 5): array
    {
        global $wpdb;
        $column   = self::dateColumn();
        $excluded = implode("','", array_map('esc_sql', self::excludedStatuses()));
        $after    = str_replace('T', ' ', $after);
        $before   = str_replace('T', ' ', $before);
        // The period's orders, found through the date column's index; a refund belongs to its parent order's period.
        $first = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(o.date_created) FROM {$wpdb->prefix}wc_order_stats o WHERE o.parent_id = 0 AND o.{$column} BETWEEN %s AND %s AND o.status NOT IN ('{$excluded}')",
            $after,
            $before
        )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($first === null) {
            return [];
        }
        // A refund is made after its order, so only refunds since the period's first order can belong to it.
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT l.product_id, l.variation_id, SUM(l.product_qty) AS qty FROM {$wpdb->prefix}wc_order_product_lookup l
             JOIN (
                 SELECT o.order_id FROM {$wpdb->prefix}wc_order_stats o
                 WHERE o.parent_id = 0 AND o.{$column} BETWEEN %s AND %s AND o.status NOT IN ('{$excluded}')
                 UNION ALL
                 SELECT r.order_id FROM {$wpdb->prefix}wc_order_stats r
                 JOIN {$wpdb->prefix}wc_order_stats o ON o.order_id = r.parent_id
                 WHERE r.date_created >= %s AND r.parent_id > 0 AND o.parent_id = 0 AND o.{$column} BETWEEN %s AND %s AND o.status NOT IN ('{$excluded}')
             ) ids ON ids.order_id = l.order_id
             GROUP BY l.product_id, l.variation_id",
            $after,
            $before,
            (string) $first,
            $after,
            $before
        )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $units = [];
        foreach ($rows as $row) {
            $item      = (int) $row->variation_id > 0 ? (int) $row->variation_id : (int) $row->product_id;
            $canonical = CanonicalProduct::of($item);
            $id        = $canonical instanceof \WC_Product ? $canonical->get_id() : $item;
            $units[$id] = ($units[$id] ?? 0) + (int) $row->qty;
        }
        $units = array_filter($units, static fn (int $qty): bool => $qty > 0);
        arsort($units);
        $out = [];
        foreach (array_slice($units, 0, $limit, true) as $id => $qty) {
            $product = wc_get_product($id);
            $out[]   = ['id' => (int) $id, 'name' => $product instanceof \WC_Product ? wp_strip_all_tags($product->get_formatted_name()) : '#' . $id, 'units' => (int) $qty];
        }

        return $out;
    }

    /** @return array<string,int> Orders waiting for staff, by status, counted by the database. */
    public static function needingAction(): array
    {
        $counts = [];
        foreach (self::ACTION_STATUSES as $status) {
            $found           = wc_get_orders(['status' => [$status], 'limit' => 1, 'paginate' => true, 'return' => 'ids', 'type' => 'shop_order']);
            $counts[$status] = is_object($found) && isset($found->total) ? (int) $found->total : 0;
        }

        return $counts;
    }

    /**
     * Orders with a refund still to make, newest first, at most the limit of each: paid orders cancelled and not fully
     * refunded; and orders the payment layer flagged since their last refund, unless they have since been delivered or
     * refunded. Each list is filtered first and limited after, so a resolved order never hides an open one.
     *
     * @return array{cancelled_paid:list<int>,flagged:list<int>}
     */
    public static function pendingRefunds(): array
    {
        return ['cancelled_paid' => self::cancelledPaid(), 'flagged' => self::flagged()];
    }

    /** The most orders a refund list holds (REFUND_LIMIT, or the `mizzey_dashboard_refund_limit` filter's). */
    public static function limit(): int
    {
        return max(1, (int) apply_filters('mizzey_dashboard_refund_limit', self::REFUND_LIMIT));
    }

    /**
     * Flagged orders still open, newest first, in one bounded query: the flag list is stored in order (PaymentRecord
     * appends), so its last `at` is the latest flag; an order counts while that is later than its latest refund. Times
     * are compared as seconds since 1970 in UTC, whatever the database session's time zone.
     *
     * @return list<int>
     */
    private static function flagged(): array
    {
        global $wpdb;
        $open = array_values(array_diff(array_keys(wc_get_order_statuses()), ['wc-completed', 'wc-cod-collected', 'wc-refunded']));
        $in   = implode("','", array_map('esc_sql', $open));
        // How PHP stores each flag's time inside the list: `s:2:"at";i:` and the number.
        $at = serialize('at') . 'i:'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
        $ids  = OrderUtil::custom_orders_table_usage_is_enabled()
            ? $wpdb->get_col($wpdb->prepare(
                "SELECT o.id FROM {$wpdb->prefix}wc_orders o
                 JOIN {$wpdb->prefix}wc_orders_meta m ON m.order_id = o.id AND m.meta_key = %s
                 WHERE o.type = 'shop_order' AND o.status IN ('{$in}')
                 AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.meta_value, %s, -1), ';', 1) AS UNSIGNED) > COALESCE((
                     SELECT TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', MAX(r.date_created_gmt)) FROM {$wpdb->prefix}wc_orders r
                     WHERE r.type = 'shop_order_refund' AND r.parent_order_id = o.id), 0)
                 ORDER BY o.date_created_gmt DESC, o.id DESC LIMIT %d",
                PaymentRecord::ATTENTION,
                $at,
                self::limit()
            )) // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            : $wpdb->get_col($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
                 WHERE p.post_type = 'shop_order' AND p.post_status IN ('{$in}')
                 AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.meta_value, %s, -1), ';', 1) AS UNSIGNED) > COALESCE((
                     SELECT TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', MAX(r.post_date_gmt)) FROM {$wpdb->posts} r
                     WHERE r.post_type = 'shop_order_refund' AND r.post_parent = p.ID), 0)
                 ORDER BY p.post_date_gmt DESC, p.ID DESC LIMIT %d",
                PaymentRecord::ATTENTION,
                $at,
                self::limit()
            )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array_values(array_unique(array_map('intval', (array) $ids)));
    }

    /**
     * Cancelled orders with a paid date whose refunds do not cover their total, summed as WooCommerce sums refunds in
     * the order storage in use.
     *
     * @return list<int>
     */
    private static function cancelledPaid(): array
    {
        global $wpdb;
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $orders = $wpdb->prefix . 'wc_orders';
            // A refund's total is stored negative, as WooCommerce's get_total_refunded() reads it.
            $sql = $wpdb->prepare(
                "SELECT o.id FROM {$orders} o
                 JOIN {$wpdb->prefix}wc_order_operational_data d ON d.order_id = o.id
                 WHERE o.type = 'shop_order' AND o.status = 'wc-cancelled' AND d.date_paid_gmt > '1970-01-01 00:00:00'
                 AND o.total_amount + COALESCE((SELECT SUM(r.total_amount) FROM {$orders} r WHERE r.type = 'shop_order_refund' AND r.parent_order_id = o.id), 0) > 0.005
                 ORDER BY o.id DESC LIMIT %d",
                self::limit()
            ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $sql = $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} paid ON paid.post_id = p.ID AND paid.meta_key = '_date_paid' AND paid.meta_value NOT IN ('', '0')
                 JOIN {$wpdb->postmeta} total ON total.post_id = p.ID AND total.meta_key = '_order_total'
                 WHERE p.post_type = 'shop_order' AND p.post_status = 'wc-cancelled'
                 AND CAST(total.meta_value AS DECIMAL(26,8)) - COALESCE((SELECT SUM(CAST(rm.meta_value AS DECIMAL(26,8))) FROM {$wpdb->posts} r
                     JOIN {$wpdb->postmeta} rm ON rm.post_id = r.ID AND rm.meta_key = '_refund_amount'
                     WHERE r.post_type = 'shop_order_refund' AND r.post_parent = p.ID), 0) > 0.005
                 ORDER BY p.ID DESC LIMIT %d",
                self::limit()
            );
        }

        return array_map('intval', (array) $wpdb->get_col($sql)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * A refund list's count, "or more" when the list reached its limit.
     *
     * @param list<int> $ids
     */
    private static function refundCount(array $ids): string
    {
        $count = number_format_i18n(count($ids));

        /* translators: %s: the number of orders, the most a list holds. */
        return count($ids) >= self::limit() ? sprintf(__('%s or more', 'mizzey-site'), $count) : $count;
    }

    /**
     * The orders list shows exactly the orders a count was made of, when the dashboard links to it.
     *
     * @param mixed $args
     * @return mixed
     */
    public static function listOnly($args)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
        $which = isset($_GET['mizzey_refunds']) ? sanitize_key(wp_unslash((string) $_GET['mizzey_refunds'])) : '';
        if (! is_array($args) || ! in_array($which, ['cancelled_paid', 'flagged'], true) || ! current_user_can(self::CAP)) {
            return $args;
        }
        $ids        = self::pendingRefunds()[$which];
        $args['id'] = $ids === [] ? [0] : $ids;
        unset($args['status']);

        return $args;
    }

    /**
     * Low and out of stock as the stock source reports them, one answer per physical item. The count is made in the
     * background, a batch at a time, so a large catalogue or a slow source never holds the dashboard; the page shows the
     * last complete count and when it was made.
     *
     * @return array{low:int,out:int,unknown:int,items:int,threshold:int,at:int}|null Null while the first count runs.
     */
    public static function stock(): ?array
    {
        $kept = get_transient(self::STOCK_KEY);
        if (! is_array($kept) || (int) ($kept['at'] ?? 0) < time() - self::STOCK_TTL) {
            self::queueCount();
        }

        return is_array($kept) ? $kept : null;
    }

    public static function queueCount(): void
    {
        if (function_exists('as_has_scheduled_action') && ! as_has_scheduled_action(self::COUNT_HOOK, null, 'mizzey')) {
            as_enqueue_async_action(self::COUNT_HOOK, [0], 'mizzey');
        }
    }

    /**
     * One batch of the count: the next items after `$after` (a product or variation id), then the next batch is queued,
     * or the count is kept when the catalogue is done.
     *
     * @param mixed $after
     */
    public static function countBatch($after = 0): void
    {
        global $wpdb;
        $after = (int) $after;
        $run   = get_option(self::RUN_KEY);
        if ($after === 0 || ! is_array($run)) {
            $run = ['low' => 0, 'out' => 0, 'unknown' => 0, 'items' => 0, 'threshold' => max(0, (int) get_option('woocommerce_notify_low_stock_amount', 2)), 'seen' => []];
        }
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status IN ('publish','private') AND ID > %d ORDER BY ID LIMIT %d",
            $after,
            self::BATCH
        ))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $gateway = StockGateway::quick();
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            if (! $product instanceof \WC_Product || $product->is_type(['variable', 'grouped', 'external'])) {
                continue;
            }
            $item = StockIdentity::resolve($id);
            if ($item === null) {
                // A record that resolves to no stock identity cannot be answered for: unknown, never in stock.
                $run['items']++;
                $run['unknown']++;
                continue;
            }
            if (isset($run['seen'][$item->kind . ':' . $item->id])) {
                continue;
            }
            $run['seen'][$item->kind . ':' . $item->id] = 1;
            $run['items']++;
            $answer = $gateway->level($item->id);
            if (! $answer->isKnown()) {
                $run['unknown']++;
            } elseif ((int) $answer->quantity <= 0) {
                $run['out']++;
            } elseif ((int) $answer->quantity <= (int) $run['threshold']) {
                $run['low']++;
            }
        }
        if (count($ids) === self::BATCH) {
            update_option(self::RUN_KEY, $run, false);
            as_enqueue_async_action(self::COUNT_HOOK, [(int) end($ids)], 'mizzey');
            return;
        }
        unset($run['seen']);
        $run['at'] = time();
        set_transient(self::STOCK_KEY, $run, DAY_IN_SECONDS);
        delete_option(self::RUN_KEY);
    }

    private static function ordersUrl(array $args): string
    {
        return add_query_arg($args, admin_url('admin.php?page=wc-orders'));
    }

    public static function render(): void
    {
        if (! current_user_can(self::CAP)) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only choice of period.
        $period = isset($_GET['mizzey_period']) && is_string($_GET['mizzey_period']) && isset(self::PERIODS[$_GET['mizzey_period']]) ? sanitize_key($_GET['mizzey_period']) : '7d';
        [$after, $before] = self::range($period);
        $sales = self::sales($after, $before);

        echo '<div class="mizzey-store-glance">';
        echo '<p>';
        foreach (['today' => __('Today', 'mizzey-site'), '7d' => __('7 days', 'mizzey-site'), '30d' => __('30 days', 'mizzey-site')] as $key => $label) {
            printf($key === $period ? '<strong>%2$s</strong> ' : '<a href="%1$s">%2$s</a> ', esc_url(add_query_arg('mizzey_period', $key, admin_url('index.php'))), esc_html($label));
        }
        echo '</p>';
        echo '<table class="widefat striped"><tbody>';
        foreach ([
            [__('Revenue', 'mizzey-site'), wc_price($sales['revenue'])],
            [__('Orders', 'mizzey-site'), esc_html(number_format_i18n($sales['orders']))],
            [__('Units sold', 'mizzey-site'), esc_html(number_format_i18n($sales['units']))],
            [__('Average order value', 'mizzey-site'), wc_price($sales['aov'])],
            [__('Refunds', 'mizzey-site'), wc_price($sales['refunds'])],
        ] as [$label, $value]) {
            printf('<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html($label), wp_kses_post($value));
        }
        echo '</tbody></table>';
        printf('<p><a href="%s">%s</a></p>', esc_url(admin_url('admin.php?page=wc-admin&path=/analytics/revenue')), esc_html__('Charts by day, week, month or any range', 'mizzey-site'));

        printf('<h3>%s</h3>', esc_html__('Top products', 'mizzey-site'));
        $top = self::topProducts($after, $before);
        if ($top === []) {
            printf('<p>%s</p>', esc_html__('No sales in this period.', 'mizzey-site'));
        } else {
            echo '<ol>';
            foreach ($top as $line) {
                printf('<li><a href="%s">%s</a>: %s</li>', esc_url((string) get_edit_post_link($line['id'])), esc_html($line['name']), esc_html(sprintf(_n('%s unit', '%s units', $line['units'], 'mizzey-site'), number_format_i18n($line['units']))));
            }
            echo '</ol>';
        }

        printf('<h3>%s</h3><ul>', esc_html__('Orders requiring action', 'mizzey-site'));
        foreach (self::needingAction() as $status => $count) {
            printf('<li><a href="%s">%s: %s</a></li>', esc_url(self::ordersUrl(['status' => 'wc-' . $status])), esc_html(wc_get_order_status_name($status)), esc_html(number_format_i18n($count)));
        }
        echo '</ul>';

        $pending = self::pendingRefunds();
        printf('<h3>%s</h3><ul>', esc_html__('Refunds to make', 'mizzey-site'));
        printf('<li><a href="%s">%s: %s</a></li>', esc_url(self::ordersUrl(['mizzey_refunds' => 'cancelled_paid'])), esc_html__('Paid orders cancelled and not refunded', 'mizzey-site'), esc_html(self::refundCount($pending['cancelled_paid'])));
        printf('<li><a href="%s">%s: %s</a></li>', esc_url(self::ordersUrl(['mizzey_refunds' => 'flagged'])), esc_html__('Orders the payment layer flagged', 'mizzey-site'), esc_html(self::refundCount($pending['flagged'])));
        echo '</ul>';

        $stock = self::stock();
        printf('<h3>%s</h3>', esc_html__('Stock, as the stock source reports it', 'mizzey-site'));
        if ($stock === null) {
            printf('<p>%s</p>', esc_html__('The stock is being counted. It appears here in a few minutes.', 'mizzey-site'));
            $stock = ['low' => 0, 'out' => 0, 'unknown' => 0, 'items' => 0, 'threshold' => 0, 'at' => 0];
        }
        printf(
            '<ul><li><a href="%s">%s: %s</a></li><li><a href="%s">%s: %s</a></li><li>%s: %s</li></ul><p class="description">%s</p>',
            esc_url(admin_url('admin.php?page=wc-admin&path=/analytics/stock&type=lowstock')),
            esc_html(sprintf(__('Low stock (%s or fewer)', 'mizzey-site'), number_format_i18n($stock['threshold']))),
            esc_html(number_format_i18n($stock['low'])),
            esc_url(admin_url('admin.php?page=wc-admin&path=/analytics/stock&type=outofstock')),
            esc_html__('Out of stock', 'mizzey-site'),
            esc_html(number_format_i18n($stock['out'])),
            esc_html__('No answer from the stock source', 'mizzey-site'),
            esc_html(number_format_i18n($stock['unknown'])),
            esc_html(sprintf(__('%1$s items, read at %2$s.', 'mizzey-site'), number_format_i18n($stock['items']), wp_date('H:i', $stock['at'])))
        );

        printf(
            '<p><a class="button button-primary" href="%s">%s</a> <a class="button" href="%s">%s</a> <a class="button" href="%s">%s</a></p>',
            esc_url(admin_url('post-new.php?post_type=product')),
            esc_html__('Add product', 'mizzey-site'),
            esc_url(admin_url('post-new.php?post_type=shop_coupon')),
            esc_html__('Create coupon', 'mizzey-site'),
            esc_url(self::ordersUrl(['status' => 'wc-processing'])),
            esc_html__('View pending orders', 'mizzey-site')
        );
        do_action('mizzey_dashboard_widget_end');
        echo '</div>';
    }
}

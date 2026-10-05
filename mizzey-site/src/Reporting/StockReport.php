<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reporting;

use Automattic\WooCommerce\Admin\API\Reports\Stock\Controller as StockController;
use Automattic\WooCommerce\Enums\ProductStockStatus;
use WP_Query;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Makes the standard stock report list physical items, one line each, in every admin language context.
 *
 * The measured faults, on WooCommerce 11.1.0 with WPML 4.9.7 and WooCommerce Multilingual 5.5.7. With one low
 * item in both languages, one in Arabic only and one in English only, the low-stock list showed two lines in an
 * English session (the Arabic-only item missing), two in an Arabic session (the English-only item missing) and
 * four in an all-languages session (the bilingual item twice), where three are owed.
 *
 * The correction is confined to the report's own query. `Reports\Stock\Controller::get_items()` registers its
 * clause filters, runs one WP_Query, and removes them, both for the screen and for the export, which calls the
 * same method. While those filters are registered, this class lifts the language filter from that query and adds
 * the representative-record condition, both from PhysicalItems. The report's stock filter, ordering, total and
 * paging then run on physical items, in SQL, in whatever kind of request the report is read: the screen's REST
 * request under either language's address, a scheduled export, wp-admin or WP-CLI.
 *
 * The summary under the report's table is a second set of figures, counted by WooCommerce with statements of its
 * own that no query filter reaches, and it counted language records. Each figure is replaced by the total of the
 * list it summarises, so the summary and the lists cannot disagree.
 *
 * It changes no stock, no synchronisation and no storefront behaviour, and it chooses nothing about where the
 * ERP's figures are stored or what "low" means: the report keeps reading the store's own figures and thresholds.
 *
 * Register ids: RPT-10. See specs/004-inventory-report-one-item-once.
 */
final class StockReport
{
    public static function register(): void
    {
        // Before the query is built, so WPML's own query filters find the flag when they run.
        add_action('parse_query', [self::class, 'everyLanguage']);
        // After the report's own clause filters at 10, which add the stock filter and the ordering.
        add_filter('posts_clauses', [self::class, 'onePerPhysicalItem'], 20, 2);
        add_filter('woocommerce_analytics_stock_stats_query', [self::class, 'summaryOfPhysicalItems']);
    }

    /**
     * Let the stock report's query see every language record.
     */
    public static function everyLanguage(WP_Query $query): void
    {
        if (self::isStockReportQuery($query)) {
            PhysicalItems::everyLanguage($query);
        }
    }

    /**
     * Keep one record per physical item in the stock report's query.
     *
     * @param array<string,string> $clauses
     * @return array<string,string>
     */
    public static function onePerPhysicalItem(array $clauses, WP_Query $query): array
    {
        if (self::isStockReportQuery($query)) {
            global $wpdb;
            $clauses['where'] .= PhysicalItems::representativeOnly($wpdb->posts);
        }

        return $clauses;
    }

    /**
     * Make each figure of the report's summary the total of the list it summarises.
     *
     * The figures are read live. WooCommerce keeps its own for thirty days; a summary that can lag behind the
     * list beside it is the fault being corrected, and the cost is measured in t25.
     *
     * @param mixed $totals The summary as WooCommerce counted it, keyed by figure.
     * @return mixed
     */
    public static function summaryOfPhysicalItems($totals)
    {
        if (!is_array($totals) || !PhysicalItems::available()) {
            return $totals;
        }

        $report = new StockController();
        // The lists the report offers: low stock, and one per stock status. Any other figure is left as it came.
        $lists = array_merge([ProductStockStatus::LOW_STOCK], array_keys(wc_get_product_stock_status_options()));
        foreach (array_keys($totals) as $figure) {
            $isWholeReport = 'products' === $figure;
            if ($isWholeReport || in_array($figure, $lists, true)) {
                $totals[$figure] = self::listTotal($report, $isWholeReport ? null : $figure) ?? $totals[$figure];
            }
        }

        return $totals;
    }

    /**
     * The number of physical items in one of the report's lists, as the list itself counts them.
     *
     * @param string|null $type The list, or null for the whole report.
     * @return int|null Null when the list answered with an error, which its contract allows.
     */
    private static function listTotal(StockController $report, ?string $type): ?int
    {
        $request = new WP_REST_Request('GET', '/wc-analytics/reports/stock');
        $request->set_param('page', 1);
        $request->set_param('per_page', 1);
        if (null !== $type) {
            $request->set_param('type', $type);
        }

        $response = rest_ensure_response($report->get_items($request));
        if (is_wp_error($response)) {
            return null;
        }

        return (int) ($response->get_headers()['X-WP-Total'] ?? 0);
    }

    /**
     * Whether this is the query the stock report runs.
     *
     * The controller's own clause filter is registered only while its get_items() is querying, so its presence,
     * together with the post types that method asks for, identifies the query exactly: no other product query
     * on the site is touched.
     */
    private static function isStockReportQuery(WP_Query $query): bool
    {
        if (!class_exists(StockController::class)) {
            return false;
        }
        if (false === has_filter('posts_where', [StockController::class, 'add_wp_query_filter'])) {
            return false;
        }

        $types = (array) $query->get('post_type');
        sort($types);

        return ['product', 'product_variation'] === $types;
    }
}

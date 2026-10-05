<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reporting;

use Automattic\WooCommerce\Admin\API\Reports\Stock\Controller as StockController;
use WP_Query;

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
 * same method. While those filters are registered, this class widens the language scope for that query and adds
 * the representative-record condition from PhysicalItems. The report's stock filter, ordering, total and paging
 * then run on physical items, in SQL.
 *
 * It changes no stock, no synchronisation and no storefront behaviour, and it chooses nothing about where the
 * ERP's figures are stored or what "low" means: the report keeps reading the store's own figures and thresholds.
 *
 * Register ids: RPT-10. See specs/004-inventory-report-one-item-once.
 */
final class StockReport
{
    /** The language to restore once the report's query has been built. */
    private static ?string $languageToRestore = null;

    public static function register(): void
    {
        // As early as possible, so the language scope is already wide when WPML's own query filters run.
        add_action('parse_query', [self::class, 'beforeQuery'], PHP_INT_MIN);
        // After the report's own clause filters at 10, which add the stock filter and the ordering.
        add_filter('posts_clauses', [self::class, 'onePerPhysicalItem'], 20, 2);
        // Once the SQL exists the language scope has done its work, whatever the query then returns.
        add_filter('posts_request', [self::class, 'afterQueryBuilt'], PHP_INT_MAX, 2);
    }

    /**
     * Widen the language scope for the stock report's query.
     */
    public static function beforeQuery(WP_Query $query): void
    {
        if (self::isStockReportQuery($query) && null === self::$languageToRestore) {
            self::$languageToRestore = PhysicalItems::widenLanguageScope();
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
     * Restore the language scope once the stock report's query has been built.
     */
    public static function afterQueryBuilt(string $request, WP_Query $query): string
    {
        if (self::isStockReportQuery($query)) {
            PhysicalItems::restoreLanguageScope(self::$languageToRestore);
            self::$languageToRestore = null;
        }

        return $request;
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

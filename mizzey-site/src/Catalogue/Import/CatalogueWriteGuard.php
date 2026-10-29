<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Stops any catalogue write while a preview or a validation run is active (AC-5).
 *
 * It sits on `query`, which runs before every database statement, so it also catches direct SQL that no WordPress
 * hook would see. A statement that changes a protected catalogue table (products and media, their metadata, terms and
 * their relationships, WooCommerce's product lookup tables, WPML's translation records) is refused before it runs.
 * Everything else is allowed on purpose: reading leaves housekeeping behind, such as WPML's `_icl_cache` and expired
 * transients removed on read, and refusing it would break reading. This permits no catalogue repair. It is installed
 * around one command and always removed.
 *
 * Register ids: MIG-03, MIG-06. See specs/006-catalogue-import-spec, FR-007 and plan.md (CatalogueWriteGuard).
 */
final class CatalogueWriteGuard
{
    private const MUTATION = '/^\s*(?:\/\*.*?\*\/\s*)?(INSERT|UPDATE|DELETE|REPLACE|TRUNCATE|ALTER|DROP|CREATE)\b/is';

    /** @var list<string>|null */
    private static ?array $tables = null;

    public static function install(): void
    {
        add_filter('query', [self::class, 'refuseCatalogueWrites'], PHP_INT_MAX);
    }

    public static function remove(): void
    {
        remove_filter('query', [self::class, 'refuseCatalogueWrites'], PHP_INT_MAX);
    }

    /**
     * @throws RuntimeException Naming the statement, before it runs.
     */
    public static function refuseCatalogueWrites(string $query): string
    {
        if (preg_match(self::MUTATION, $query) !== 1) {
            return $query;
        }
        foreach (self::tables() as $table) {
            if (preg_match('/[\s`.,(]' . preg_quote($table, '/') . '[\s`.,;()]|[\s`.,(]' . preg_quote($table, '/') . '$/i', ' ' . $query) === 1) {
                throw new CatalogueWriteRefused(sprintf(
                    /* translators: 1: a table, 2: the start of the statement. */
                    __('A catalogue write was attempted during a read-only run and refused (table %1$s): %2$s', 'mizzey-site'),
                    $table,
                    substr(preg_replace('/\s+/', ' ', $query) ?? '', 0, 160),
                ));
            }
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        global $wpdb;
        if (self::$tables === null) {
            self::$tables = [
                $wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta,
                $wpdb->prefix . 'wc_product_meta_lookup', $wpdb->prefix . 'wc_product_attributes_lookup',
                $wpdb->prefix . 'woocommerce_attribute_taxonomies', $wpdb->prefix . 'icl_translations',
            ];
        }

        return self::$tables;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reporting;

defined('ABSPATH') || exit;

/**
 * Resolves language records to physical items, in SQL, for any report that lists products.
 *
 * The measured root (P-020, and the audit of 5 October 2026). One sellable product or variation is stored as one
 * post per language, and WooCommerce Multilingual copies its stock onto every one of them, so each language
 * record holds its own `wc_product_meta_lookup` row with the full quantity. A report that lists product posts
 * therefore lists language records, not items.
 *
 * What this class supplies. A WHERE condition that keeps exactly one record per translation group: the
 * representative. Because it is part of the query, the report's own filtering, ordering, total and paging all
 * operate on physical items. Nothing is merged in PHP afterwards, which is the approach P-019 measured as wrong:
 * merging after the page is cut gives totals and page boundaries that count language records.
 *
 * The representative of a group is its source-language record, the one the translations were made from. Where a
 * group has no listable source-language record, for example because the original is in the bin, it is the
 * listable record with the lowest id. A record that belongs to no translation group represents itself.
 *
 * It reads the translation relationship and changes nothing: no stock, no synchronisation, no record.
 *
 * Shared on purpose. The decision of 9 September 2026 records that a second report needs the same resolution, so
 * it lives here once and each report asks for it.
 *
 * Register ids: RPT-10. See specs/004-inventory-report-one-item-once.
 */
final class PhysicalItems
{
    /**
     * The post statuses in which a record can stand for its item. A report run by staff lists published and
     * private records; a draft or binned original must not hide a sellable translation.
     */
    private const LISTABLE_STATUSES = ['publish', 'private'];

    /**
     * Whether a translation relationship exists to resolve against.
     */
    public static function available(): bool
    {
        return defined('ICL_SITEPRESS_VERSION');
    }

    /**
     * A WHERE fragment, starting with AND, that keeps only the representative record of each physical item.
     *
     * The record is suppressed when its translation group holds another listable record that outranks it: a
     * source-language record outranks a translation, and between two of the same kind the lower id does.
     *
     * @param string $postsTable The posts table or alias the outer query selects from.
     */
    public static function representativeOnly(string $postsTable): string
    {
        global $wpdb;

        // The table name is an identifier and cannot be a placeholder value, so anything but a plain identifier
        // is refused rather than escaped.
        if (!self::available() || 1 !== preg_match('/^[A-Za-z0-9_]+$/', $postsTable)) {
            return '';
        }

        $translations = $wpdb->prefix . 'icl_translations';
        $statuses     = implode(', ', array_fill(0, count(self::LISTABLE_STATUSES), '%s'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- identifiers only; every value is a placeholder.
        return $wpdb->prepare(" AND NOT EXISTS (
            SELECT 1
            FROM {$translations} AS mizzey_self
            INNER JOIN {$translations} AS mizzey_rival
                ON mizzey_rival.trid = mizzey_self.trid
                AND mizzey_rival.element_type = mizzey_self.element_type
                AND mizzey_rival.element_id <> mizzey_self.element_id
            INNER JOIN {$wpdb->posts} AS mizzey_rival_post
                ON mizzey_rival_post.ID = mizzey_rival.element_id
                AND mizzey_rival_post.post_status IN ({$statuses})
            WHERE mizzey_self.element_id = {$postsTable}.ID
                AND mizzey_self.element_type IN ('post_product', 'post_product_variation')
                AND (
                    (mizzey_rival.source_language_code IS NULL AND mizzey_self.source_language_code IS NOT NULL)
                    OR (
                        (mizzey_rival.source_language_code IS NULL) = (mizzey_self.source_language_code IS NULL)
                        AND mizzey_rival.element_id < mizzey_self.element_id
                    )
                )
        ) ", ...self::LISTABLE_STATUSES);
    }

    /**
     * Lift the language scope, so that the query that follows sees every language record.
     *
     * A report of physical items must not depend on the language the operator's session is in. WPML otherwise
     * narrows a product query to that language, which is how an item that exists only in the other language
     * drops out of the list.
     *
     * @return string|null The language to restore, or null when there is nothing to restore.
     */
    public static function widenLanguageScope(): ?string
    {
        if (!self::available()) {
            return null;
        }

        $current = apply_filters('wpml_current_language', null);
        if (!is_string($current) || 'all' === $current) {
            return null;
        }

        do_action('wpml_switch_language', 'all');

        return $current;
    }

    /**
     * Restore the language scope taken by widenLanguageScope().
     */
    public static function restoreLanguageScope(?string $language): void
    {
        if (null !== $language) {
            do_action('wpml_switch_language', $language);
        }
    }
}

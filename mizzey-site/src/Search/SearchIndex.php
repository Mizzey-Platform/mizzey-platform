<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Search;

defined('ABSPATH') || exit;

/**
 * What the search reads: each product's searchable text, normalised, per language record (SRCH-01, SRCH-02).
 *
 * One row per product record: its name; its short and full description; its SKU and its variations' SKUs; the names of
 * its brands and categories (with the parents of each category). Kept up to date on every product save (its language
 * record's own row), on a category or brand rename (its products again, in the background), and removed with the
 * product. `wp mizzey search reindex` rebuilds it whole. Products not published are indexed and left out by the
 * product query itself.
 *
 * Register ids: SRCH-01, SRCH-02. See specs/058-search.
 */
final class SearchIndex
{
    public const SCHEMA  = 'mizzey_search_schema';
    public const VERSION = '1';
    public const REINDEX = 'mizzey_search_reindex_term';
    public const GROUP   = 'mizzey-search';

    public static function register(): void
    {
        add_action('init', [self::class, 'install'], 1);
        add_action('woocommerce_after_product_object_save', [self::class, 'saved'], 30, 1);
        add_action('deleted_post', [self::class, 'deleted'], 10, 1);
        foreach (['product_cat', 'product_brand'] as $taxonomy) {
            add_action("edited_{$taxonomy}", [self::class, 'termChanged'], 20, 1);
        }
        add_action('set_object_terms', [self::class, 'termsSet'], 20, 4);
        add_action(self::REINDEX, [self::class, 'reindexTerm'], 10, 2);
    }

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'mizzey_search';
    }

    public static function ready(): bool
    {
        return get_option(self::SCHEMA) === self::VERSION;
    }

    public static function install(): void
    {
        if (self::ready()) {
            return;
        }
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'search_install', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        try {
            wp_cache_delete(self::SCHEMA, 'options');
            if (self::ready()) {
                return;
            }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = self::table();
            dbDelta("CREATE TABLE {$table} (
  product_id bigint(20) unsigned NOT NULL,
  language varchar(7) NOT NULL DEFAULT '',
  title text NOT NULL,
  body mediumtext NOT NULL,
  sku text NOT NULL,
  terms text NOT NULL,
  updated int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (product_id),
  KEY language (language)
) " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                update_option(self::SCHEMA, self::VERSION, true);
                if (function_exists('as_enqueue_async_action')) {
                    as_enqueue_async_action('mizzey_search_reindex_all', [], self::GROUP);
                }
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** @param mixed $product */
    public static function saved($product): void
    {
        if ($product instanceof \WC_Product_Variation) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent instanceof \WC_Product) {
                self::index($parent);
            }

            return;
        }
        if ($product instanceof \WC_Product) {
            self::index($product);
        }
    }

    /** Writes a product record's row. */
    public static function index(\WC_Product $product): void
    {
        if (! self::ready() || $product instanceof \WC_Product_Variation) {
            return;
        }
        global $wpdb;
        $id       = $product->get_id();
        $skus     = [$product->get_sku('edit')];
        foreach ($product->get_children() as $child) {
            $skus[] = (string) get_post_meta((int) $child, '_sku', true);
        }
        $terms = [];
        foreach (['product_cat', 'product_brand'] as $taxonomy) {
            if (! taxonomy_exists($taxonomy)) {
                continue;
            }
            $assigned = wp_get_object_terms($id, $taxonomy);
            foreach (is_array($assigned) ? $assigned : [] as $term) {
                $terms[] = $term->name;
                foreach (get_ancestors((int) $term->term_id, $taxonomy, 'taxonomy') as $ancestor) {
                    $parent = get_term((int) $ancestor, $taxonomy);
                    if ($parent instanceof \WP_Term) {
                        $terms[] = $parent->name;
                    }
                }
            }
        }
        $language = (string) apply_filters('wpml_element_language_code', null, ['element_id' => $id, 'element_type' => 'product']);
        $wpdb->replace(self::table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'product_id' => $id,
            'language'   => $language,
            'title'      => Normalise::text((string) get_post_field('post_title', $id)),
            'body'       => Normalise::text((string) get_post_field('post_excerpt', $id) . ' ' . (string) get_post_field('post_content', $id)),
            'sku'        => ' ' . implode(' ', array_filter(array_map(static fn (string $s): string => mb_strtolower(trim($s)), $skus), 'strlen')) . ' ',
            'terms'      => Normalise::text(implode(' ', array_unique($terms))),
            'updated'    => time(),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%d']);
    }

    /** @param mixed $postId */
    public static function deleted($postId): void
    {
        if (! self::ready()) {
            return;
        }
        global $wpdb;
        $wpdb->delete(self::table(), ['product_id' => (int) $postId], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /** A category or brand renamed: its products are indexed again, in the background. */
    public static function termChanged(int $termId): void
    {
        $term = get_term($termId);
        if ($term instanceof \WP_Term && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(self::REINDEX, [$termId, $term->taxonomy], self::GROUP);
        }
    }

    /**
     * Categories or brands set on a product outside a product save: the product's row follows.
     *
     * @param mixed $objectId
     * @param mixed $terms
     * @param mixed $ttIds
     * @param mixed $taxonomy
     */
    public static function termsSet($objectId, $terms, $ttIds, $taxonomy): void
    {
        if (in_array($taxonomy, ['product_cat', 'product_brand'], true) && get_post_type((int) $objectId) === 'product') {
            $product = wc_get_product((int) $objectId);
            if ($product instanceof \WC_Product) {
                self::index($product);
            }
        }
    }

    /**
     * @param mixed $termId
     * @param mixed $taxonomy
     */
    public static function reindexTerm($termId, $taxonomy): void
    {
        $ids = get_objects_in_term(array_merge([(int) $termId], get_term_children((int) $termId, (string) $taxonomy) ?: []), (string) $taxonomy);
        foreach (is_array($ids) ? $ids : [] as $id) {
            $product = wc_get_product((int) $id);
            if ($product instanceof \WC_Product) {
                self::index($product);
            }
        }
    }

    /** Every product record again. Returns how many. */
    public static function reindexAll(): int
    {
        global $wpdb;
        $ids   = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('auto-draft', 'trash')"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = 0;
        foreach ($ids as $id) {
            $product = wc_get_product((int) $id);
            if ($product instanceof \WC_Product) {
                self::index($product);
                ++$count;
            }
        }

        return $count;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

use MizzeySite\Catalogue\Collections;

/**
 * Manual product ordering within each category and each collection (MER-03).
 *
 * WooCommerce has one manual order for the whole catalogue; a category or a collection has none of its own. Here each
 * category's and collection's edit screen carries "Product order": its products, each with a position. The positions
 * are kept on the products (`_mizzey_pos_<term id>`, the main-language term's id), on each language record, so the
 * Arabic page orders the same. A category or collection page in WooCommerce's default sorting, and a Store API query
 * for one category or collection sorted by `menu_order`, list the positioned products first, by position, then the
 * rest in WooCommerce's own order. Any other sorting the shopper chooses (price, newest) is left as it is.
 *
 * Register ids: MER-03, ADM-55, ADM-57. See specs/061-manual-order.
 */
final class ManualOrder
{
    public const PREFIX      = '_mizzey_pos_';
    public const TAXONOMIES  = ['product_cat', Collections::TAXONOMY];
    private const NONCE      = 'mizzey_manual_order';
    private const SHOWN      = 200;

    public static function register(): void
    {
        foreach (self::TAXONOMIES as $taxonomy) {
            add_action("{$taxonomy}_edit_form", [self::class, 'box'], 20, 2);
            add_action("edited_{$taxonomy}", [self::class, 'save'], 20, 1);
        }
        add_filter('posts_clauses', [self::class, 'clauses'], 30, 2);
    }

    /** The main-language term of a category or collection, where its order is kept. */
    public static function original(int $termId, string $taxonomy): int
    {
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = $default !== '' ? (int) apply_filters('wpml_object_id', $termId, $taxonomy, true, $default) : 0;

        return $original > 0 ? $original : $termId;
    }

    /**
     * @param mixed $term
     * @param mixed $taxonomy
     */
    public static function box($term, $taxonomy): void
    {
        if (! $term instanceof \WP_Term || ! current_user_can('manage_product_terms')) {
            return;
        }
        $original = self::original((int) $term->term_id, (string) $taxonomy);
        $products = get_posts([
            'post_type'        => 'product',
            'post_status'      => ['publish', 'private', 'draft', 'future'],
            'numberposts'      => self::SHOWN,
            'tax_query'        => [['taxonomy' => (string) $taxonomy, 'field' => 'term_id', 'terms' => [(int) $term->term_id]]], // phpcs:ignore WordPress.DB.SlowDBQuery
            'orderby'          => 'title',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ]);
        printf('<h2>%s</h2><p>%s</p>', esc_html__('Product order', 'mizzey-site'), esc_html__('Give the products you want first a position (1 first); the others follow in the store\'s usual order. The order applies in both languages.', 'mizzey-site'));
        wp_nonce_field(self::NONCE, 'mizzey_manual_order_nonce');
        echo '<table class="widefat striped" style="max-width:640px"><tbody>';
        foreach ($products as $product) {
            $position = (string) get_post_meta($product->ID, self::PREFIX . $original, true);
            printf(
                '<tr><td>%s</td><td style="width:120px"><input type="number" min="1" step="1" name="mizzey_position[%d]" value="%s" style="width:100px"></td></tr>',
                esc_html(get_the_title($product)),
                (int) $product->ID,
                esc_attr($position),
            );
        }
        echo '</tbody></table>';
    }

    public static function save(int $termId): void
    {
        $nonce = isset($_POST['mizzey_manual_order_nonce']) ? sanitize_text_field(wp_unslash($_POST['mizzey_manual_order_nonce'])) : '';
        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE) === false || ! current_user_can('manage_product_terms')) {
            return;
        }
        $term = get_term($termId);
        if (! $term instanceof \WP_Term || ! in_array($term->taxonomy, self::TAXONOMIES, true)) {
            return;
        }
        $key       = self::PREFIX . self::original($termId, $term->taxonomy);
        $positions = isset($_POST['mizzey_position']) && is_array($_POST['mizzey_position']) ? wp_unslash($_POST['mizzey_position']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        foreach ($positions as $productId => $position) {
            $position = (int) $position;
            foreach (self::records((int) $productId) as $record) {
                $position > 0 ? update_post_meta($record, $key, $position) : delete_post_meta($record, $key);
            }
        }
    }

    /** @return list<int> A product and its language records. */
    private static function records(int $productId): array
    {
        if (get_post_type($productId) !== 'product') {
            return [];
        }
        $records      = [$productId];
        $translations = apply_filters('wpml_get_element_translations', null, apply_filters('wpml_element_trid', null, $productId, 'post_product'), 'post_product');
        foreach (is_array($translations) ? $translations : [] as $translation) {
            if (isset($translation->element_id)) {
                $records[] = (int) $translation->element_id;
            }
        }

        return array_values(array_unique(array_filter($records)));
    }

    /**
     * The single category or collection a product query lists in its default order, or null.
     *
     * @return array{0:string,1:int}|null
     */
    private static function orderedTerm(\WP_Query $query): ?array
    {
        $types = (array) $query->get('post_type');
        $isArchive = $query->is_main_query() && ($query->is_tax(self::TAXONOMIES));
        if (! $isArchive && ! in_array('product', $types, true)) {
            return null;
        }
        $orderby = (string) $query->get('orderby');
        if ($orderby !== '' && ! str_starts_with($orderby, 'menu_order')) {
            return null; // The shopper chose another order.
        }
        foreach (self::TAXONOMIES as $taxonomy) {
            if ($isArchive && $query->is_tax($taxonomy)) {
                $term = $query->get_queried_object();

                return $term instanceof \WP_Term ? [$taxonomy, (int) $term->term_id] : null;
            }
        }
        $tax = (array) $query->get('tax_query');
        $hit = null;
        foreach ($tax as $clause) {
            if (! is_array($clause) || ! in_array($clause['taxonomy'] ?? '', self::TAXONOMIES, true)) {
                continue;
            }
            $terms = (array) ($clause['terms'] ?? []);
            if (count($terms) !== 1 || $hit !== null) {
                return null; // More than one category or collection: no single manual order applies.
            }
            $field = (string) ($clause['field'] ?? 'term_id');
            $found = $field === 'slug' ? get_term_by('slug', (string) reset($terms), (string) $clause['taxonomy']) : get_term((int) reset($terms), (string) $clause['taxonomy']);
            $hit   = $found instanceof \WP_Term ? [(string) $clause['taxonomy'], (int) $found->term_id] : null;
        }

        return $hit;
    }

    /**
     * @param mixed $clauses
     * @param mixed $query
     * @return mixed
     */
    public static function clauses($clauses, $query)
    {
        if (! is_array($clauses) || ! $query instanceof \WP_Query || (is_admin() && ! wp_doing_ajax())) {
            return $clauses;
        }
        $term = self::orderedTerm($query);
        if ($term === null) {
            return $clauses;
        }
        global $wpdb;
        $key = self::PREFIX . self::original($term[1], $term[0]);
        $clauses['join']   .= $wpdb->prepare(' LEFT JOIN %i mzpos ON mzpos.post_id = %i.ID AND mzpos.meta_key = %s ', $wpdb->postmeta, $wpdb->posts, $key);
        $first              = sprintf('%s %s %s, %s(%s %s %s) %s', 'mzpos.meta_value', 'IS', 'NULL', 'CAST', 'mzpos.meta_value', 'AS', 'UNSIGNED', 'ASC');
        $clauses['orderby'] = $first . ((string) ($clauses['orderby'] ?? '') !== '' ? ', ' . $clauses['orderby'] : '');

        return $clauses;
    }
}

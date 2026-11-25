<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

use MizzeySite\Catalogue\Collections;
use MizzeySite\Storefront\HomeSections;
use MizzeySite\Storefront\ProductRail;

/**
 * The home page's product sections (HOME-02, HOME-03, HOME-07): "Trending now" and "New arrivals" as a rail under a
 * heading, and the deals panel ("Deals of the week"), a rail on the Indigo panel with its own line, as
 * C1-Home-Phone, C1-Home-Phone-Arabic and C1-Home-Desktop draw them.
 *
 * Which products a section shows is the editor's rule or curation, never code (specs/056): a badge (New, Trending,
 * Limited, Best Seller, through the `mizzey_badge` taxonomy specs/056 keeps), a category, a collection, the newest,
 * those on sale, or products picked by hand in the editor's order. The products are the visitor's language's; a
 * category or collection set on one language's page is read as its translation on the other. The cards are the
 * product rail of slice 4 (ProductRail), which renders nothing when no product is left, so a section with nothing to
 * show leaves no region at all (AC-3).
 *
 * Register ids: HOME-02, HOME-03, HOME-07, HOME-10. See specs/066-stage-2-shop-front, slice 5, and specs/056.
 */
final class ProductSections
{
    public const SOURCES  = ['badge', 'category', 'collection', 'newest', 'sale', 'products'];
    public const BADGES   = ['trending', 'new', 'limited', 'best-seller'];
    public const MAX      = 24;
    public const FALLBACK = 12;

    /** @param array<string,mixed> $attributes */
    public static function renderProducts(array $attributes): string
    {
        $ids = self::productIds($attributes);
        if ([] === $ids) {
            return '';
        }
        $heading = Section::plain($attributes['heading'] ?? '');
        $rail    = ProductRail::render($ids, ['heading' => $heading, 'link' => self::link($attributes), 'label' => $heading]);
        if ('' === $rail) {
            return '';
        }
        return sprintf('<div %s>%s</div>', Section::wrapper('products', ['mizzey-home-products']), $rail);
    }

    /** @param array<string,mixed> $attributes */
    public static function renderDeals(array $attributes): string
    {
        $ids     = self::productIds($attributes);
        $heading = Section::plain($attributes['heading'] ?? '');
        $rail    = [] === $ids ? '' : ProductRail::render($ids, ['label' => $heading]);
        if ('' === $rail) {
            return '';
        }
        $line = Section::plain($attributes['line'] ?? '');
        $link = esc_url(self::link($attributes));
        // The line sits beside the heading on a desktop and under it on a phone (style.css), so it is in the head row.
        $head = Section::el('div', ['class' => ['mizzey-home__head', 'mizzey-deals__head']],
            ('' !== $heading ? Section::el('h2', ['class' => 'mizzey-home__heading'], esc_html($heading)) : '')
            . ('' !== $line ? Section::el('p', ['class' => 'mizzey-deals__line'], esc_html($line)) : '')
            . ('' !== $link ? Section::el('a', ['class' => 'mizzey-home__all', 'href' => $link], esc_html__('View all', 'mizzey-site')) : ''));
        return sprintf(
            '<section %s>%s%s</section>',
            Section::wrapper('deals', ['mizzey-deals', 'mizzey-on-brand'], ['aria-label' => '' !== $heading ? $heading : null]),
            $head,
            $rail
        );
    }

    /** The "View all" address: the editor's, or the category or collection the section reads. */
    private static function link(array $attributes): string
    {
        $link = trim((string) ($attributes['link'] ?? ''));
        if ('' !== $link) {
            return $link;
        }
        $taxonomy = self::taxonomyOf((string) ($attributes['source'] ?? ''));
        $term     = '' === $taxonomy ? 0 : self::localTerm((int) ($attributes['term'] ?? 0), $taxonomy);
        if ($term > 0) {
            $url = get_term_link($term, $taxonomy);
            return is_wp_error($url) ? '' : (string) $url;
        }
        return '';
    }

    /**
     * The products a section's rule or curation selects, in the visitor's language, in the order shown.
     *
     * @param array<string,mixed> $attributes
     * @return list<int>
     */
    public static function productIds(array $attributes): array
    {
        $source = in_array($attributes['source'] ?? '', self::SOURCES, true) ? (string) $attributes['source'] : 'newest';
        $count  = (int) ($attributes['count'] ?? self::FALLBACK);
        $count  = $count > 0 ? min(self::MAX, $count) : self::FALLBACK;

        if ('products' === $source) {
            // The editor sets no count for a hand-picked list: every pick shows, up to the maximum its field allows.
            $picked = array_values(array_filter(array_map('intval', (array) ($attributes['products'] ?? []))));
            return array_slice($picked, 0, self::MAX);
        }

        $args = [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'fields'              => 'ids',
            'posts_per_page'      => $count,
            'orderby'             => ['date' => 'DESC', 'ID' => 'DESC'],
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'suppress_filters'    => false,
            'tax_query'           => [self::visible()], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        ];
        if ('badge' === $source) {
            $badge = in_array($attributes['badge'] ?? '', self::BADGES, true) ? (string) $attributes['badge'] : self::BADGES[0];
            $args['tax_query'][] = ['taxonomy' => HomeSections::BADGE_TAXONOMY, 'field' => 'slug', 'terms' => [$badge]];
        } elseif ('category' === $source || 'collection' === $source) {
            $taxonomy = self::taxonomyOf($source);
            $term     = self::localTerm((int) ($attributes['term'] ?? 0), $taxonomy);
            if ($term <= 0) {
                return [];
            }
            $args['tax_query'][] = ['taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => [$term]];
        } elseif ('sale' === $source) {
            $sale = array_values(array_filter(array_map('intval', wc_get_product_ids_on_sale())));
            if ([] === $sale) {
                return [];
            }
            $args['post__in'] = $sale;
        }
        $query = new \WP_Query($args);
        return array_values(array_map('intval', (array) $query->posts));
    }

    /** Products a visitor may find: not hidden from the catalogue. */
    private static function visible(): array
    {
        return ['taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => ['exclude-from-catalog'], 'operator' => sprintf('%s %s', 'NOT', 'IN')];
    }

    private static function taxonomyOf(string $source): string
    {
        if ('category' === $source) {
            return 'product_cat';
        }
        return 'collection' === $source && taxonomy_exists(Collections::TAXONOMY) ? Collections::TAXONOMY : '';
    }

    /** The term in the visitor's language, when the editor chose it on the other language's page. */
    private static function localTerm(int $term, string $taxonomy): int
    {
        if ($term <= 0 || '' === $taxonomy) {
            return 0;
        }
        $local = (int) apply_filters('wpml_object_id', $term, $taxonomy, true);
        return $local > 0 ? $local : $term;
    }
}

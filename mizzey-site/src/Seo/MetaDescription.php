<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

defined('ABSPATH') || exit;

/**
 * Emits a meta description, which WordPress core does not provide at all.
 *
 * The measured gap. Core has no meta description field and no SEO plugin is active on this site, so the
 * storefront emits none. NFR-03 names "meta" among its eight obligations.
 *
 * Output only, never a control, and no storage of its own. MKT-12 contracts "editable title, description and
 * canonical tag on every product, category and page" and SSC-27 contracts the per-product override; both belong
 * to other slices. So this class derives a description from the record WordPress already holds and emits it
 * through `mizzey_meta_description`. When MKT-12 or SSC-27 is built, its stored field returns through that filter
 * and becomes the source: no second metadata store appears, and nothing here is replaced.
 *
 * No cross-page fallback, deliberately. A page with nothing to describe emits no description rather than
 * repeating a neighbour's, because a site where every page shares one description is worse for NFR-03 than a site
 * where some pages have none.
 *
 * Register ids: NFR-03. See specs/003-information-architecture-urls.
 */
final class MetaDescription
{
    /**
     * The length search engines generally display. Longer text is cut on a word boundary rather than mid-word.
     */
    private const MAX_LENGTH = 160;

    public static function register(): void
    {
        add_action('wp_head', [self::class, 'emit'], 1);
    }

    public static function emit(): void
    {
        $description = self::resolve();

        /**
         * Filters the meta description this site emits for the current route.
         *
         * The documented override point. A later admin-owned control (MKT-12, SSC-27) returns its stored value
         * here, and this class continues to supply the default.
         *
         * @param string $description The derived description, or an empty string when the record has none.
         */
        $description = (string) apply_filters('mizzey_meta_description', $description);

        if ('' === $description) {
            return;
        }

        printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
    }

    /**
     * A description derived from the current record's own content. Never from another record.
     */
    private static function resolve(): string
    {
        if (is_singular()) {
            $post = get_queried_object();
            if (!$post instanceof \WP_Post) {
                return '';
            }
            // The excerpt is the author's own summary where one exists; the content is the fallback for the same
            // record. Both are this record's text, so no page can inherit another's description.
            $source = has_excerpt($post) ? $post->post_excerpt : $post->post_content;
            return self::shorten((string) $source);
        }

        if (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            return $term instanceof \WP_Term ? self::shorten((string) $term->description) : '';
        }

        if (is_post_type_archive()) {
            $type = get_query_var('post_type');
            $type = is_array($type) ? reset($type) : $type;
            $object = $type ? get_post_type_object((string) $type) : null;
            return $object ? self::shorten((string) $object->description) : '';
        }

        if (is_front_page() || is_home()) {
            return self::shorten((string) get_bloginfo('description'));
        }

        return '';
    }

    /**
     * Strip markup and shortcodes, collapse whitespace, and cut on a word boundary.
     */
    private static function shorten(string $text): string
    {
        $text = wp_strip_all_tags(strip_shortcodes($text), true);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ('' === $text) {
            return '';
        }
        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::MAX_LENGTH);
        $space = mb_strrpos($cut, ' ');
        return rtrim(false === $space ? $cut : mb_substr($cut, 0, $space));
    }
}

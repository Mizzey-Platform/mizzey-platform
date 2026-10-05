<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

defined('ABSPATH') || exit;

/**
 * Emits a canonical link on the routes WordPress core leaves without one.
 *
 * The measured gap. Core's `rel_canonical()` opens with `if ( ! is_singular() ) { return; }`, read from
 * wp-includes/link-template.php. So a singular page or product gets a canonical and an archive gets none:
 * measured on this runtime, `/sample-page/` emits one while `/`, `/ar/` and `/shop/` emit nothing. NFR-03 names
 * "canonical" without restricting it to singular URLs, and an archive is exactly where duplicate-URL risk lives,
 * because pagination and filtering multiply addresses for one listing.
 *
 * Output only, never a control. NFR-03 obliges the output. The editable controls over it are contracted
 * separately: ADM-41 "Canonical and robots override" and SSC-27 "Per-product SEO override: title, description,
 * slug, canonical", owned by other slices. So this class emits and does not store, and every value passes
 * through `mizzey_canonical_url` so one of those rows can later override it per record without this code
 * changing and without a second metadata store to reconcile.
 *
 * Register ids: NFR-03. See specs/003-information-architecture-urls.
 */
final class ArchiveCanonical
{
    public static function register(): void
    {
        // Priority 11: after core's own rel_canonical at 10, so a singular route keeps core's tag and never
        // receives a second one.
        add_action('wp_head', [self::class, 'emit'], 11);
    }

    /**
     * Print the canonical link for a non-singular route.
     */
    public static function emit(): void
    {
        if (is_singular() || is_404() || is_search()) {
            // Singular routes are core's. A 404 has no canonical form. A search results page is a query, not a
            // document, and canonicalising it would invite the duplicate indexing NFR-03 exists to avoid.
            return;
        }

        $url = self::resolve();

        /**
         * Filters the canonical URL this site emits for a non-singular route.
         *
         * The documented override point. A later admin-owned control (ADM-41, SSC-27) can return its stored
         * value here, and this class continues to supply the default.
         *
         * @param string $url The resolved canonical URL, or an empty string when none applies.
         */
        $url = (string) apply_filters('mizzey_canonical_url', $url);

        if ('' === $url) {
            return;
        }

        printf('<link rel="canonical" href="%s" />' . "\n", esc_url($url));
    }

    /**
     * The canonical form of the current non-singular route, derived from the resolved query rather than from the
     * request string, so a query parameter or a path variant cannot produce a different answer.
     */
    private static function resolve(): string
    {
        if (is_front_page()) {
            return (string) home_url('/');
        }

        if (is_home()) {
            $page_for_posts = (int) get_option('page_for_posts');
            return $page_for_posts > 0 ? (string) get_permalink($page_for_posts) : (string) home_url('/');
        }

        if (is_post_type_archive()) {
            $type = get_query_var('post_type');
            $type = is_array($type) ? reset($type) : $type;
            $link = $type ? get_post_type_archive_link((string) $type) : false;
            return is_string($link) ? $link : '';
        }

        if (is_tax() || is_category() || is_tag()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $link = get_term_link($term);
                return is_string($link) ? $link : '';
            }
        }

        return '';
    }
}

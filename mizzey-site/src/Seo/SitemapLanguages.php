<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

defined('ABSPATH') || exit;

/**
 * Adds the non-default languages to the core XML sitemap.
 *
 * Why this is code and not configuration, measured before anything was written. WPML ships no integration with
 * the core sitemap on this stack: `WPML_Sitemaps_Filter`, `WPML_Core_Sitemaps` and `WPML_Sitemap` do not exist,
 * and every `wp_sitemaps_*` filter has zero callbacks. The cause of the gap is that WPML scopes queries to the
 * current language, so core's provider, running in the default language, only ever sees English records.
 * Measured: `wp-sitemap-posts-page-1.xml` listed pages 2, 5, 6, 7 and 8 and omitted the Arabic 19 to 22.
 *
 * Why it reuses core's providers rather than querying itself. Switching language and asking the same provider
 * again produces exactly the entries core would have produced for that language, including the URL that WPML's
 * own permalink filters build. A hand-written query would duplicate core's logic and drift from it. Measured
 * detail that rules out the simpler fix: `suppress_filters` returns all languages for posts but **not** for
 * terms, so a single query argument cannot cover both providers.
 *
 * Register ids: NFR-03. See specs/003-information-architecture-urls.
 */
final class SitemapLanguages
{
    /**
     * Guards against re-entry: the provider is called again inside our own filter.
     */
    private static bool $running = false;

    public static function register(): void
    {
        add_filter('wp_sitemaps_posts_pre_url_list', [self::class, 'posts'], 10, 3);
        add_filter('wp_sitemaps_taxonomies_pre_url_list', [self::class, 'taxonomies'], 10, 3);
    }

    /**
     * @param array<int,array<string,mixed>>|null $url_list Core's short-circuit value, null by default.
     * @param string                              $post_type The subtype being listed.
     * @param int                                 $page_num  The sitemap page.
     * @return array<int,array<string,mixed>>|null
     */
    public static function posts($url_list, $post_type, $page_num)
    {
        return self::merge($url_list, 'posts', (string) $post_type, (int) $page_num);
    }

    /**
     * @param array<int,array<string,mixed>>|null $url_list Core's short-circuit value, null by default.
     * @param string                              $taxonomy The subtype being listed.
     * @param int                                 $page_num The sitemap page.
     * @return array<int,array<string,mixed>>|null
     */
    public static function taxonomies($url_list, $taxonomy, $page_num)
    {
        return self::merge($url_list, 'taxonomies', (string) $taxonomy, (int) $page_num);
    }

    /**
     * Ask the provider once per active language and merge, keeping each language's own URLs.
     *
     * @param array<int,array<string,mixed>>|null $url_list
     * @return array<int,array<string,mixed>>|null
     */
    private static function merge($url_list, string $provider_name, string $subtype, int $page_num)
    {
        if (self::$running || null !== $url_list) {
            // Either we are inside our own call, or something else already short-circuited the list and owns it.
            return $url_list;
        }

        $languages = self::languages();
        if (count($languages) < 2) {
            return $url_list;
        }

        $provider = wp_sitemaps_get_server()->registry->get_provider($provider_name);
        if (!$provider instanceof \WP_Sitemaps_Provider) {
            return $url_list;
        }

        $current = apply_filters('wpml_current_language', null);
        $merged = [];
        $seen = [];

        self::$running = true;
        try {
            foreach ($languages as $language) {
                do_action('wpml_switch_language', $language);
                foreach ($provider->get_url_list($page_num, $subtype) as $entry) {
                    $loc = isset($entry['loc']) ? (string) $entry['loc'] : '';
                    if ('' === $loc || isset($seen[$loc])) {
                        // One URL once. A record reachable from more than one language context must not be
                        // listed twice, which is the same duplicate-URL concern guardrail G-1 holds elsewhere.
                        continue;
                    }
                    $seen[$loc] = true;
                    $merged[] = $entry;
                }
            }
        } finally {
            self::$running = false;
            do_action('wpml_switch_language', $current);
        }

        return $merged ?: $url_list;
    }

    /**
     * The active language codes, default first so its URLs lead the list.
     *
     * @return array<int,string>
     */
    private static function languages(): array
    {
        $active = apply_filters('wpml_active_languages', null, 'skip_missing=0');
        if (!is_array($active) || !$active) {
            return [];
        }
        $codes = array_keys($active);
        $default = (string) apply_filters('wpml_default_language', null);
        if ('' !== $default && in_array($default, $codes, true)) {
            $codes = array_merge([$default], array_values(array_diff($codes, [$default])));
        }
        return $codes;
    }
}

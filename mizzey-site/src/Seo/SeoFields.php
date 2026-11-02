<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

use MizzeySite\Catalogue\ProductFields;

defined('ABSPATH') || exit;

/**
 * Editable title, description and canonical on every page and category (MKT-12), with the same four fields and the
 * same storage keys as a product's (specs/036, ADM-39 to ADM-41): one SEO store, on the post or the term (ADR-0003).
 * Pages keep them in post meta, product categories, collections and brands in term meta; each language record has
 * its own. A robots override keeps an item out of search results and out of the sitemap.
 *
 * Register ids: MKT-12. See specs/038-seo-controls.
 */
final class SeoFields
{
    public const NONCE      = 'mizzey_seo_fields';
    public const TAXONOMIES = ['product_cat', 'product_collection', 'product_brand'];

    public static function register(): void
    {
        add_action('add_meta_boxes_page', [self::class, 'box']);
        add_action('save_post_page', [self::class, 'savePage'], 10, 1);
        foreach (self::TAXONOMIES as $taxonomy) {
            add_action("{$taxonomy}_edit_form_fields", [self::class, 'termFields'], 20, 1);
            add_action("edited_{$taxonomy}", [self::class, 'saveTerm'], 10, 1);
        }
        add_filter('pre_get_document_title', [self::class, 'title'], 21);
        add_filter('mizzey_meta_description', [self::class, 'description'], 21);
        add_filter('get_canonical_url', [self::class, 'pageCanonical'], 21, 2);
        add_filter('mizzey_canonical_url', [self::class, 'termCanonical'], 21);
        add_filter('wp_robots', [self::class, 'robots'], 21);
        add_filter('wp_sitemaps_posts_query_args', [self::class, 'sitemapPosts'], 10, 1);
        add_filter('wp_sitemaps_taxonomies_query_args', [self::class, 'sitemapTerms'], 10, 1);
    }

    /** @return array{title:string,description:string,canonical:string,robots:string} */
    public static function of(string $kind, int $id): array
    {
        $get = $kind === 'term' ? static fn (string $k): string => (string) get_term_meta($id, $k, true) : static fn (string $k): string => (string) get_post_meta($id, $k, true);

        return ['title' => $get(ProductFields::SEO_TITLE), 'description' => $get(ProductFields::SEO_DESC), 'canonical' => $get(ProductFields::CANONICAL), 'robots' => $get(ProductFields::ROBOTS)];
    }

    /** @param array<string,string> $values */
    private static function store(string $kind, int $id, array $values): void
    {
        $set = $kind === 'term' ? static fn (string $k, string $v) => update_term_meta($id, $k, $v) : static fn (string $k, string $v) => update_post_meta($id, $k, $v);
        $set(ProductFields::SEO_TITLE, mb_substr(sanitize_text_field($values['title'] ?? ''), 0, 120));
        $set(ProductFields::SEO_DESC, mb_substr(sanitize_textarea_field($values['description'] ?? ''), 0, 320));
        $set(ProductFields::CANONICAL, esc_url_raw(trim($values['canonical'] ?? ''), ['http', 'https']));
        $robots = (string) ($values['robots'] ?? '');
        $set(ProductFields::ROBOTS, in_array($robots, ProductFields::ROBOT_CHOICES, true) ? $robots : '');
    }

    /** @return array<string,string>|null The submitted fields, when the form's nonce is valid. */
    private static function submitted(): ?array
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! isset($_POST['mizzey_seo']) || ! is_array($_POST['mizzey_seo'])) {
            return null;
        }
        $raw = wp_unslash($_POST['mizzey_seo']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised in store().

        return array_map(static fn ($v): string => is_string($v) ? $v : '', array_intersect_key((array) $raw, array_flip(['title', 'description', 'canonical', 'robots'])));
    }

    private static function form(array $v, bool $table): void
    {
        $rows = [
            ['title', __('SEO title', 'mizzey-site'), sprintf('<input type="text" name="mizzey_seo[title]" value="%s" maxlength="120" class="widefat" />', esc_attr($v['title']))],
            ['description', __('Meta description', 'mizzey-site'), sprintf('<textarea name="mizzey_seo[description]" maxlength="320" rows="3" class="widefat">%s</textarea>', esc_textarea($v['description']))],
            ['canonical', __('Canonical address (override)', 'mizzey-site'), sprintf('<input type="url" name="mizzey_seo[canonical]" value="%s" class="widefat" />', esc_attr($v['canonical']))],
        ];
        $select = '<select name="mizzey_seo[robots]">';
        foreach (['' => __('Index and follow (default)', 'mizzey-site'), 'noindex' => __('Do not index', 'mizzey-site'), 'nofollow' => __('Do not follow links', 'mizzey-site'), 'noindex,nofollow' => __('Do not index or follow', 'mizzey-site')] as $key => $label) {
            $select .= sprintf('<option value="%s"%s>%s</option>', esc_attr($key), selected($v['robots'], $key, false), esc_html($label));
        }
        $rows[] = ['robots', __('Search engines', 'mizzey-site'), $select . '</select>'];
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        foreach ($rows as [$key, $label, $control]) {
            if ($table) {
                printf('<tr class="form-field"><th scope="row">%s</th><td>%s</td></tr>', esc_html($label), $control); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
            } else {
                printf('<p><label><strong>%s</strong></label><br />%s</p>', esc_html($label), $control); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped above.
            }
        }
    }

    public static function box(): void
    {
        add_meta_box('mizzey-seo', __('Search engines', 'mizzey-site'), static function ($post): void {
            self::form(self::of('post', (int) $post->ID), false);
        }, 'page', 'normal', 'low');
    }

    /** @param mixed $postId */
    public static function savePage($postId): void
    {
        $values = self::submitted();
        if ($values === null || wp_is_post_revision((int) $postId) || ! current_user_can('edit_page', (int) $postId)) {
            return;
        }
        self::store('post', (int) $postId, $values);
    }

    /** @param mixed $term */
    public static function termFields($term): void
    {
        if ($term instanceof \WP_Term) {
            self::form(self::of('term', $term->term_id), true);
        }
    }

    /** @param mixed $termId */
    public static function saveTerm($termId): void
    {
        $values = self::submitted();
        if ($values === null || ! current_user_can('manage_product_terms')) {
            return;
        }
        self::store('term', (int) $termId, $values);
    }

    /** @return array{0:string,1:int}|null The current page or term this site has SEO fields for. */
    private static function current(): ?array
    {
        if (is_page()) {
            return ['post', (int) get_queried_object_id()];
        }
        $object = get_queried_object();
        if ($object instanceof \WP_Term && in_array($object->taxonomy, self::TAXONOMIES, true)) {
            return ['term', $object->term_id];
        }

        return null;
    }

    private static function field(string $name): string
    {
        $current = self::current();

        return $current === null ? '' : trim(self::of($current[0], $current[1])[$name]);
    }

    /**
     * @param mixed $title
     * @return mixed
     */
    public static function title($title)
    {
        $own = self::field('title');

        return $own !== '' ? $own : $title;
    }

    /**
     * @param mixed $description
     * @return mixed
     */
    public static function description($description)
    {
        $own = self::field('description');

        return $own !== '' ? $own : $description;
    }

    /**
     * @param mixed $url
     * @param mixed $post
     * @return mixed
     */
    public static function pageCanonical($url, $post = null)
    {
        $own = $post instanceof \WP_Post && $post->post_type === 'page' ? self::of('post', $post->ID)['canonical'] : '';

        return $own !== '' ? $own : $url;
    }

    /**
     * @param mixed $url
     * @return mixed
     */
    public static function termCanonical($url)
    {
        $own = is_page() ? '' : self::field('canonical');

        return $own !== '' ? $own : $url;
    }

    /**
     * @param mixed $robots
     * @return mixed
     */
    public static function robots($robots)
    {
        $own = self::field('robots');
        if (! is_array($robots) || $own === '') {
            return $robots;
        }
        foreach (explode(',', $own) as $directive) {
            $robots[$directive] = true;
        }

        return $robots;
    }

    /** @return array<string|int,mixed> The meta condition of an item search engines may index. */
    private static function indexable(): array
    {
        return [
            'relation' => 'OR',
            ['key' => ProductFields::ROBOTS, 'compare' => implode(' ', ['NOT', 'EXISTS'])],
            ['key' => ProductFields::ROBOTS, 'value' => 'noindex', 'compare' => implode(' ', ['NOT', 'LIKE'])],
        ];
    }

    /**
     * An item kept out of search results is kept out of the sitemap.
     *
     * @param mixed $args
     * @return mixed
     */
    public static function sitemapPosts($args)
    {
        if (! is_array($args)) {
            return $args;
        }
        $meta   = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $meta[] = self::indexable();
        $args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        return $args;
    }

    /**
     * @param mixed $args
     * @return mixed
     */
    public static function sitemapTerms($args)
    {
        if (! is_array($args)) {
            return $args;
        }
        $meta   = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $meta[] = self::indexable();
        $args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        return $args;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

use MizzeySite\Catalogue\Collections;
use MizzeySite\Catalogue\ProductFields;

/**
 * What the home page's sections draw from, set in the admin, never in code (HOME-01 to HOME-11).
 *
 * The home page is a page built from blocks (the block editor is the mechanism, D-14): its sections are blocks the
 * client adds, edits and reorders in the editor, in each language (HOME-10, HOME-11), and a product section is
 * WooCommerce's Product Collection block, which shows a "no results" state of its own when a rule matches nothing.
 * This class supplies what those blocks cannot reach on their own:
 *
 * - **Badges as a taxonomy** (HOME-02, HOME-03): the four manual badges of D-19 (New, Trending, Limited, Best Seller,
 *   specs/036) are mirrored into the taxonomy `mizzey_badge` on every save of a product, its Arabic record included,
 *   so a Product Collection block can show "Trending" or "New" by its own taxonomy filter. The badges stay set on the
 *   product screen; the taxonomy has no screen of its own and is never edited by hand.
 * - **On the home page** (HOME-04, HOME-05, HOME-06): product categories, brands and collections each carry "Show on
 *   the home page" and a position, set on their own admin screens on the main-language term, so one setting governs
 *   both languages. `GET mizzey/v1/home/terms?taxonomy=product_cat|product_brand|product_collection` lists them in
 *   the request's language, in that position: name, address, description, image and product count.
 *
 * The hero, offer banners, trust signals and the signup prompt (HOME-01, HOME-07, HOME-08, HOME-09) are blocks with
 * the client's own words and images in each language's page; the signup's consent is specs/017's.
 *
 * Register ids: HOME-02, HOME-03, HOME-04, HOME-05, HOME-06, HOME-10, HOME-11. See specs/056-home-sections.
 */
final class HomeSections
{
    public const BADGE_TAXONOMY = 'mizzey_badge';
    public const ON_HOME        = 'mizzey_on_home';
    public const POSITION       = 'mizzey_home_position';
    public const TAXONOMIES     = ['product_cat', 'product_brand', Collections::TAXONOMY];
    private const NONCE         = 'mizzey_on_home';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerTaxonomy'], 20);
        add_action('woocommerce_after_product_object_save', [self::class, 'mirrorBadges'], 20, 1);
        // WPML copies the badges to the Arabic record as post meta, not through a product save: mirrored then too.
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'badgesWritten'], 10, 3);
        }
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('template_redirect', [self::class, 'noBadgeArchive'], 1);
        foreach (self::TAXONOMIES as $taxonomy) {
            add_action("{$taxonomy}_add_form_fields", [self::class, 'addFields']);
            add_action("{$taxonomy}_edit_form_fields", [self::class, 'editFields']);
            add_action("created_{$taxonomy}", [self::class, 'saveFields'], 10, 1);
            add_action("edited_{$taxonomy}", [self::class, 'saveFields'], 10, 1);
        }
    }

    public static function registerTaxonomy(): void
    {
        register_taxonomy(self::BADGE_TAXONOMY, ['product'], [
            'labels'            => ['name' => __('Badges', 'mizzey-site'), 'singular_name' => __('Badge', 'mizzey-site')],
            'public'            => false,
            // Queryable, as WordPress's block query reads only a queryable taxonomy's filter; its archive is answered 404.
            'publicly_queryable' => true,
            'show_ui'           => false,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => false,
            'query_var'         => false,
        ]);
        if (get_option('mizzey_badge_terms') === '1') {
            return; // The four terms exist: made once, not looked up on every request.
        }
        foreach (self::badgeNames() as $slug => $name) {
            if (! term_exists($slug, self::BADGE_TAXONOMY)) {
                wp_insert_term($name, self::BADGE_TAXONOMY, ['slug' => $slug]);
            }
        }
        update_option('mizzey_badge_terms', '1', true);
    }

    /** A badge has no page of its own: its address answers 404, so no thin archive is served or indexed. */
    public static function noBadgeArchive(): void
    {
        if (is_tax(self::BADGE_TAXONOMY)) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    /** @return array<string,string> */
    private static function badgeNames(): array
    {
        return ['new' => __('New', 'mizzey-site'), 'trending' => __('Trending', 'mizzey-site'), 'limited' => __('Limited', 'mizzey-site'), 'best-seller' => __('Best Seller', 'mizzey-site')];
    }

    /**
     * The product's badges, as terms, after every save; a variation has none of its own.
     *
     * @param mixed $product
     */
    public static function mirrorBadges($product): void
    {
        if (! $product instanceof \WC_Product || $product instanceof \WC_Product_Variation || ! taxonomy_exists(self::BADGE_TAXONOMY)) {
            return;
        }
        $badges = array_values(array_intersect(ProductFields::BADGE_ORDER, (array) $product->get_meta(ProductFields::BADGES, true, 'edit')));
        $now    = wp_get_object_terms($product->get_id(), self::BADGE_TAXONOMY, ['fields' => 'slugs']);
        $now    = is_array($now) ? $now : [];
        sort($now);
        $want = $badges;
        sort($want);
        if ($now !== $want) {
            wp_set_object_terms($product->get_id(), $badges, self::BADGE_TAXONOMY);
        }
    }

    /**
     * @param mixed $metaId
     * @param mixed $postId
     * @param mixed $key
     */
    public static function badgesWritten($metaId, $postId, $key): void
    {
        if ($key !== ProductFields::BADGES || get_post_type((int) $postId) !== 'product') {
            return;
        }
        $product = wc_get_product((int) $postId);
        if ($product instanceof \WC_Product) {
            self::mirrorBadges($product);
        }
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/home/terms', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => ['taxonomy' => ['required' => true, 'type' => 'string', 'enum' => self::TAXONOMIES]],
            'callback'            => static fn (\WP_REST_Request $request): \WP_REST_Response => new \WP_REST_Response(['terms' => self::onHome((string) $request->get_param('taxonomy'))]),
        ]);
    }

    /**
     * The terms of a taxonomy shown on the home page, in their position, in the request's language.
     *
     * @return list<array<string,mixed>>
     */
    public static function onHome(string $taxonomy): array
    {
        if (! in_array($taxonomy, self::TAXONOMIES, true) || ! taxonomy_exists($taxonomy)) {
            return [];
        }
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $shown = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            if (! $term instanceof \WP_Term) {
                continue;
            }
            $original = self::original($term);
            if (get_term_meta($original, self::ON_HOME, true) !== 'yes') {
                continue;
            }
            $image   = (int) get_term_meta($original, 'thumbnail_id', true);
            $url     = get_term_link($term);
            $shown[] = [
                'position'    => (int) get_term_meta($original, self::POSITION, true),
                'id'          => (int) $term->term_id,
                'name'        => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url'         => is_wp_error($url) ? '' : (string) $url,
                'description' => wp_strip_all_tags((string) $term->description),
                'image'       => $image > 0 ? (string) wp_get_attachment_image_url($image, 'woocommerce_thumbnail') : '',
                'count'       => (int) $term->count,
            ];
        }
        usort($shown, static fn (array $a, array $b): int => [$a['position'], $a['name']] <=> [$b['position'], $b['name']]);

        return array_map(static function (array $row): array {
            unset($row['position']);

            return $row;
        }, $shown);
    }

    /** The term in the store's main language, where its home settings are kept. */
    private static function original(\WP_Term $term): int
    {
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = $default !== '' ? (int) apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, true, $default) : 0;

        return $original > 0 ? $original : (int) $term->term_id;
    }

    public static function addFields(): void
    {
        echo '<div class="form-field"><label><input type="checkbox" name="mizzey_on_home" value="1"> ' . esc_html__('Show on the home page', 'mizzey-site') . '</label></div>';
        echo '<div class="form-field"><label for="mizzey_home_position">' . esc_html__('Position on the home page', 'mizzey-site') . '</label><input type="number" min="0" step="1" name="mizzey_home_position" id="mizzey_home_position" value="0"></div>';
        wp_nonce_field(self::NONCE, 'mizzey_on_home_nonce');
    }

    /** @param mixed $term */
    public static function editFields($term): void
    {
        if (! $term instanceof \WP_Term) {
            return;
        }
        $original = self::original($term);
        printf(
            '<tr class="form-field"><th scope="row">%s</th><td><label><input type="checkbox" name="mizzey_on_home" value="1"%s> %s</label><p><label for="mizzey_home_position">%s</label> <input type="number" min="0" step="1" name="mizzey_home_position" id="mizzey_home_position" value="%s"></p><p class="description">%s</p>',
            esc_html__('Home page', 'mizzey-site'),
            checked(get_term_meta($original, self::ON_HOME, true) === 'yes', true, false), // phpcs:ignore WordPress.Security.EscapeOutput
            esc_html__('Show on the home page', 'mizzey-site'),
            esc_html__('Position on the home page', 'mizzey-site'),
            esc_attr((string) (int) get_term_meta($original, self::POSITION, true)),
            esc_html__('Set on the term in the main language; it applies to both languages.', 'mizzey-site'),
        );
        wp_nonce_field(self::NONCE, 'mizzey_on_home_nonce');
        echo '</td></tr>';
    }

    public static function saveFields(int $termId): void
    {
        $nonce = isset($_POST['mizzey_on_home_nonce']) ? sanitize_text_field(wp_unslash($_POST['mizzey_on_home_nonce'])) : '';
        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE) === false || ! current_user_can('manage_product_terms')) {
            return;
        }
        $term = get_term($termId);
        if (! $term instanceof \WP_Term || ! in_array($term->taxonomy, self::TAXONOMIES, true)) {
            return;
        }
        $original = self::original($term);
        empty($_POST['mizzey_on_home']) ? delete_term_meta($original, self::ON_HOME) : update_term_meta($original, self::ON_HOME, 'yes');
        update_term_meta($original, self::POSITION, max(0, (int) ($_POST['mizzey_home_position'] ?? 0)));
    }
}

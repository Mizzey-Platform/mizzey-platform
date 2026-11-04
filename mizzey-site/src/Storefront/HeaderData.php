<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

use MizzeySite\Wishlist\Wishlist;

/**
 * What the header and its navigation show, as data, for the designed header to read (PRE-03b builds the screens).
 *
 * - `GET mizzey/v1/header`: the cart's live item count, the wishlist's count, whether the visitor is signed in, and the
 *   addresses of the cart, the wishlist, the account and the search, in the request's language (NAV-03 to NAV-06,
 *   NAV-09). The cart count is WooCommerce's own (`get_cart_contents_count()`, what its mini cart shows), read from the
 *   visitor's session; nothing is cached.
 * - `GET mizzey/v1/navigation`: the category tree the mega menu and the mobile menu show (NAV-07, NAV-10), in the
 *   request's language: each category with its name, address, product count, image and children, three levels at
 *   most. It is driven by the admin, never by code: the categories themselves, WooCommerce's own drag-and-drop order
 *   in Products > Categories, and a "Show in the main menu" setting on each category, kept on the category in the
 *   store's main language so one setting governs both languages. Empty categories and WooCommerce's default category
 *   are left out.
 *
 * The logo is WordPress's site logo (NAV-01), and the menu links beside the categories are WordPress's navigation
 * menus: both admin-managed and native, so neither is repeated here.
 *
 * Register ids: NAV-03, NAV-04, NAV-05, NAV-06, NAV-07, NAV-09, NAV-10. See specs/054-header-navigation.
 */
final class HeaderData
{
    public const HIDDEN = 'mizzey_menu_hidden';
    private const DEPTH = 3;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('product_cat_add_form_fields', [self::class, 'addField']);
        add_action('product_cat_edit_form_fields', [self::class, 'editField']);
        add_action('created_product_cat', [self::class, 'saveField']);
        add_action('edited_product_cat', [self::class, 'saveField']);
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/header', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => static fn (): \WP_REST_Response => self::private(self::header()),
        ]);
        register_rest_route('mizzey/v1', '/navigation', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => static fn (): \WP_REST_Response => new \WP_REST_Response(['categories' => self::categories()]),
        ]);
    }

    private static function private(array $data): \WP_REST_Response
    {
        $response = new \WP_REST_Response($data);
        $response->header('Cache-Control', 'no-store,private');

        return $response;
    }

    /** @return array<string,mixed> */
    public static function header(): array
    {
        if (function_exists('wc_load_cart') && (! function_exists('WC') || WC()->cart === null)) {
            wc_load_cart();
        }
        $count = function_exists('WC') && WC()->cart !== null ? (int) WC()->cart->get_cart_contents_count() : 0;

        return [
            'cart'     => ['count' => $count, 'url' => (string) wc_get_cart_url()],
            'wishlist' => ['count' => count(Wishlist::ids()), 'url' => (string) wc_get_endpoint_url('wishlist', '', wc_get_page_permalink('myaccount'))],
            'account'  => ['signed_in' => is_user_logged_in(), 'url' => (string) wc_get_page_permalink('myaccount')],
            'search'   => ['url' => (string) home_url('/'), 'parameter' => 's', 'post_type' => 'product'],
        ];
    }

    /**
     * The category tree, in the request's language.
     *
     * @return list<array<string,mixed>>
     */
    public static function categories(int $parent = 0, int $level = 1): array
    {
        if ($level > self::DEPTH) {
            return [];
        }
        $default = (int) get_option('default_product_cat', 0);
        $terms   = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => $parent,
            'hide_empty' => true,
            'menu_order' => 'ASC',
        ]);
        $tree = [];
        foreach (is_array($terms) ? $terms : [] as $term) {
            if (! $term instanceof \WP_Term || self::isDefault($term, $default) || self::hidden($term)) {
                continue;
            }
            $image  = (int) get_term_meta(self::original($term), 'thumbnail_id', true);
            $url    = get_term_link($term);
            $tree[] = [
                'id'       => (int) $term->term_id,
                'name'     => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url'      => is_wp_error($url) ? '' : (string) $url,
                'count'    => (int) $term->count,
                'image'    => $image > 0 ? (string) wp_get_attachment_image_url($image, 'woocommerce_thumbnail') : '',
                'children' => self::categories((int) $term->term_id, $level + 1),
            ];
        }

        return $tree;
    }

    /** The category in the store's main language, where its settings are kept. */
    private static function original(\WP_Term $term): int
    {
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = $default !== '' ? (int) apply_filters('wpml_object_id', $term->term_id, 'product_cat', true, $default) : 0;

        return $original > 0 ? $original : (int) $term->term_id;
    }

    private static function isDefault(\WP_Term $term, int $default): bool
    {
        $main = get_term($default, 'product_cat');

        return $main instanceof \WP_Term && self::original($term) === self::original($main);
    }

    public static function hidden(\WP_Term $term): bool
    {
        return get_term_meta(self::original($term), self::HIDDEN, true) === 'yes';
    }

    public static function addField(): void
    {
        echo '<div class="form-field"><label><input type="checkbox" name="mizzey_menu_shown" value="1" checked> '
            . esc_html__('Show in the main menu', 'mizzey-site') . '</label>'
            . wp_nonce_field('mizzey_menu_shown', 'mizzey_menu_shown_nonce', true, false) . '</div>';
    }

    /** @param mixed $term */
    public static function editField($term): void
    {
        if (! $term instanceof \WP_Term) {
            return;
        }
        $shown = ! self::hidden($term);
        printf(
            '<tr class="form-field"><th scope="row">%s</th><td><label><input type="checkbox" name="mizzey_menu_shown" value="1"%s> %s</label>%s<p class="description">%s</p></td></tr>',
            esc_html__('Main menu', 'mizzey-site'),
            checked($shown, true, false), // phpcs:ignore WordPress.Security.EscapeOutput
            esc_html__('Show in the main menu', 'mizzey-site'),
            wp_nonce_field('mizzey_menu_shown', 'mizzey_menu_shown_nonce', true, false), // phpcs:ignore WordPress.Security.EscapeOutput
            esc_html__('Set on the category in the main language; it applies to both languages.', 'mizzey-site'),
        );
    }

    public static function saveField(int $termId): void
    {
        $nonce = isset($_POST['mizzey_menu_shown_nonce']) ? sanitize_text_field(wp_unslash($_POST['mizzey_menu_shown_nonce'])) : '';
        if ($nonce === '' || wp_verify_nonce($nonce, 'mizzey_menu_shown') === false || ! current_user_can('manage_product_terms')) {
            return;
        }
        $term = get_term($termId, 'product_cat');
        if (! $term instanceof \WP_Term) {
            return;
        }
        $original = self::original($term);
        empty($_POST['mizzey_menu_shown']) ? update_term_meta($original, self::HIDDEN, 'yes') : delete_term_meta($original, self::HIDDEN);
    }
}

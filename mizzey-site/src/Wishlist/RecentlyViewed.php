<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Wishlist;

use MizzeySite\Catalogue\CanonicalProduct;

defined('ABSPATH') || exit;

/**
 * Recently viewed products (WISH-07), keyed to the canonical product so English and Arabic views are one entry, most
 * recent first, at most 15 (WooCommerce's own number). A signed-in customer's list is kept on their account, so it
 * persists across sessions (US-11-07); a guest's in a browser-session cookie, as WooCommerce keeps its own. Shown in
 * the current language. WooCommerce tracks views only while its Recently Viewed widget is active, by the
 * language-specific id, for the session only (`wc_track_product_view()`, WooCommerce 11.1.0), which this does not use.
 *
 * A signed-in customer's view is recorded as the page is built. A guest's is recorded by the page itself, with a
 * short script that sends `POST mizzey/v1/recently-viewed` once the page has loaded, so the product page sets no
 * cookie and the same page can be served to every guest from the page cache (NFR-02, `PageCache`). A page that
 * shows a guest's list asks the page cache not to keep it (`products()`).
 *
 * Register ids: WISH-07. See specs/015-wishlist.
 */
final class RecentlyViewed
{
    public const META   = '_mizzey_recently_viewed';
    public const COOKIE = 'mizzey_recently_viewed';
    public const LIMIT  = 15;

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'track'], 25);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    /** On a single product page, records a customer's view now, and prints the script that records a guest's. */
    public static function track(): void
    {
        if (! is_singular('product')) {
            return;
        }
        if (is_user_logged_in()) {
            self::record((int) get_queried_object_id());

            return;
        }
        add_action('wp_footer', [self::class, 'viewScript'], 99);
    }

    /** A guest's view, sent by the product page once it has loaded. */
    public static function viewScript(): void
    {
        $file   = dirname(__DIR__, 2) . '/assets/recently-viewed.js';
        $source = is_readable($file) ? (string) file_get_contents($file) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
        $data   = wp_json_encode(['url' => rest_url(WishlistRoutes::NS . '/recently-viewed'), 'id' => (int) get_queried_object_id()]);
        if ($source === '' || ! is_string($data)) {
            return;
        }
        wp_print_inline_script_tag(str_replace('__DATA__', $data, $source), ['id' => 'mizzey-recently-viewed']);
    }

    public static function routes(): void
    {
        register_rest_route(WishlistRoutes::NS, '/recently-viewed', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'viewed'],
            'permission_callback' => '__return_true',
            'args'                => ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]],
        ]);
    }

    /** `POST mizzey/v1/recently-viewed` `{ "id": n }`: records a view of a published product the visitor can see. */
    public static function viewed(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $product = wc_get_product((int) $request['id']);
        if (! $product instanceof \WC_Product || $product->get_status() !== 'publish' || ! $product->is_visible()) {
            return new \WP_Error('mizzey_wishlist_no_product', __('This product could not be found.', 'mizzey-site'), ['status' => 404]);
        }
        self::record($product->get_id());

        return new \WP_REST_Response(['recorded' => true], 200);
    }

    public static function record(int $productId): void
    {
        $canonical = CanonicalProduct::of($productId);
        if ($canonical === null || $canonical->is_type('variation')) {
            return;
        }
        $list = array_values(array_diff(self::stored(get_current_user_id()), [$canonical->get_id()]));
        array_unshift($list, $canonical->get_id());
        self::store(get_current_user_id(), array_slice($list, 0, self::LIMIT));
    }

    /** @return list<\WC_Product> Visible products in the current language, most recent first, without `$except`. */
    public static function products(int $limit = self::LIMIT, int $except = 0): array
    {
        if (get_current_user_id() === 0 && self::stored(0) !== []) {
            // A guest's own list on the page: the page is theirs, and is not kept for another guest. An empty list is
            // the same for every guest, so that page may be kept (PageCacheRequest::PERSONAL_ON_PRODUCT).
            \MizzeySite\Performance\PageCache::doNotStore('recently-viewed');
        }
        $skip = $except > 0 ? CanonicalProduct::of($except)?->get_id() : null;
        $out  = [];
        foreach (self::stored(get_current_user_id()) as $id) {
            if ($id === $skip) {
                continue;
            }
            $shown = Wishlist::shown($id);
            if ($shown instanceof \WC_Product && $shown->is_visible()) {
                $out[] = $shown;
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** Adds a guest's views to the account's on sign-in or registration, most recent first, and clears the guest's. */
    public static function mergeGuestInto(int $userId): void
    {
        $guest = self::guest()->read();
        if ($userId <= 0 || $guest === []) {
            return;
        }
        $merged = array_values(array_unique(array_merge($guest, self::stored($userId))));
        self::store($userId, array_slice($merged, 0, self::LIMIT));
        self::guest()->write([]);
    }

    public static function guest(): GuestCookie
    {
        return new GuestCookie(self::COOKIE, 0);
    }

    /** @return list<int> */
    private static function stored(int $userId): array
    {
        if ($userId > 0) {
            $meta = get_user_meta($userId, self::META, true);

            return is_array($meta) ? array_values(array_filter(array_map('intval', $meta))) : [];
        }

        return self::guest()->read();
    }

    /** @param list<int> $ids */
    private static function store(int $userId, array $ids): void
    {
        if ($userId > 0) {
            update_user_meta($userId, self::META, $ids);

            return;
        }
        self::guest()->write($ids);
    }
}

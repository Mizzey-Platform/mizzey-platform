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
    }

    /** On a single product page, records the view. */
    public static function track(): void
    {
        if (is_singular('product')) {
            self::record((int) get_queried_object_id());
        }
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

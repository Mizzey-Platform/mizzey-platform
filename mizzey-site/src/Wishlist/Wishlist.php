<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Wishlist;

use MizzeySite\Catalogue\CanonicalProduct;

defined('ABSPATH') || exit;

/**
 * One minimal wishlist (B17): save, view, remove and move to cart, keyed to the canonical product or variation, so a
 * product saved in English and in Arabic is one entry. A customer's list is kept on their account (WISH-02), a guest's
 * in a signed cookie for the shopping session, WooCommerce's guest session lifetime (`wc_session_expiration`, two days
 * by default) (WISH-03). On sign-in or registration a guest's list is added to the account's, without duplicates, and
 * the guest's is cleared (US-11-06); doing it again changes nothing. No sharing, multiple lists or price alerts (P2).
 *
 * WooCommerce has no wishlist (P-015). Register ids: WISH-01 to WISH-04. See specs/015-wishlist.
 */
final class Wishlist
{
    public const META   = '_mizzey_wishlist';
    public const COOKIE = 'mizzey_wishlist';

    public static function register(): void
    {
        add_action('wp_login', [self::class, 'onLogin'], 10, 2);
        add_action('user_register', [self::class, 'onRegister'], 10, 1);
    }

    /** @return list<int> The visitor's canonical product ids, most recently saved first; only products they may see. */
    public static function ids(): array
    {
        return array_values(array_filter(self::stored(get_current_user_id()), static fn (int $id): bool => self::viewable(wc_get_product($id))));
    }

    /**
     * The record to show for a canonical product: its translation in the current language when the visitor may see
     * it, otherwise the canonical record itself, so a draft or private translation discloses nothing.
     */
    public static function shown(int $canonicalId): ?\WC_Product
    {
        $local = CanonicalProduct::inCurrentLanguage($canonicalId);
        if (self::viewable($local)) {
            return $local;
        }
        $canonical = wc_get_product($canonicalId);

        return self::viewable($canonical) ? $canonical : null;
    }

    /**
     * Whether the visitor may see the product: published (a variation, and its parent), or readable by them. A draft,
     * private or trashed product is neither saved nor shown, so the wishlist discloses nothing that is not on sale.
     *
     * @param mixed $product
     */
    public static function viewable($product): bool
    {
        if (! $product instanceof \WC_Product) {
            return false;
        }
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : null;
        foreach (array_filter([$product, $parent]) as $record) {
            if ($record->get_status() !== 'publish' && ! current_user_can('read_post', $record->get_id())) {
                return false;
            }
        }

        return true;
    }

    /** Saves a product or variation (any language record); the canonical id, or null for no such product. */
    public static function add(int $productId): ?int
    {
        $canonical = CanonicalProduct::of($productId);
        if ($canonical === null || ! self::viewable($canonical) || ! self::viewable(wc_get_product($productId))) {
            return null;
        }
        $id   = $canonical->get_id();
        $list = array_values(array_diff(self::stored(get_current_user_id()), [$id]));
        array_unshift($list, $id);
        self::store(get_current_user_id(), array_slice($list, 0, self::capacity()));

        return $id;
    }

    public static function remove(int $productId): bool
    {
        $canonical = CanonicalProduct::of($productId);
        $id        = $canonical ? $canonical->get_id() : $productId;
        $list      = self::stored(get_current_user_id());
        if (! in_array($id, $list, true)) {
            return false;
        }
        self::store(get_current_user_id(), array_values(array_diff($list, [$id])));

        return true;
    }

    /**
     * Moves a saved item into the cart in one action (WISH-04): added, then taken off the wishlist. An item out of
     * stock, or one the cart refuses, stays saved and the reason is given.
     */
    public static function moveToCart(int $productId): true|\WP_Error
    {
        $canonical = CanonicalProduct::of($productId);
        if ($canonical === null || ! in_array($canonical->get_id(), self::stored(get_current_user_id()), true)) {
            return new \WP_Error('mizzey_wishlist_not_saved', __('This item is not in your wishlist.', 'mizzey-site'), ['status' => 404]);
        }
        $shown = self::shown($canonical->get_id()) ?? $canonical;
        if (! $shown->is_in_stock()) {
            return new \WP_Error('mizzey_wishlist_out_of_stock', __('This item is out of stock, so it stays in your wishlist.', 'mizzey-site'), ['status' => 409]);
        }
        if (! WC()->cart && function_exists('wc_load_cart')) {
            wc_load_cart();
        }
        // Load the visitor's cart before adding, or WooCommerce's later lazy load from the session replaces the line just
        // added (a REST request starts with the cart unloaded).
        WC()->cart->get_cart();
        $variation = $shown->is_type('variation') ? $shown->get_id() : 0;
        $parent    = $variation ? $shown->get_parent_id() : $shown->get_id();
        $key       = WC()->cart->add_to_cart($parent, 1, $variation, $variation ? $shown->get_variation_attributes() : []);
        if (! $key) {
            wc_clear_notices();

            return new \WP_Error('mizzey_wishlist_not_added', __('This item could not be added to your cart, so it stays in your wishlist.', 'mizzey-site'), ['status' => 409]);
        }
        self::remove($canonical->get_id());

        return true;
    }

    /** Adds a guest's list to the account's, without duplicates, and clears the guest's. Idempotent. */
    public static function mergeGuestInto(int $userId): void
    {
        $guest = self::guest()->read();
        if ($userId <= 0 || $guest === []) {
            return;
        }
        $account = self::stored($userId);
        $merged  = array_values(array_unique(array_merge(array_diff($guest, $account), $account)));
        self::store($userId, array_slice($merged, 0, self::capacity()));
        self::guest()->write([]);
    }

    /**
     * @param mixed $login
     * @param mixed $user
     */
    public static function onLogin($login, $user): void
    {
        if ($user instanceof \WP_User) {
            self::mergeGuestInto($user->ID);
            RecentlyViewed::mergeGuestInto($user->ID);
        }
    }

    /** @param mixed $userId */
    public static function onRegister($userId): void
    {
        self::mergeGuestInto((int) $userId);
        RecentlyViewed::mergeGuestInto((int) $userId);
    }

    public static function capacity(): int
    {
        return max(1, (int) apply_filters('mizzey_wishlist_capacity', 100));
    }

    /** The guest cookie, for the lifetime of WooCommerce's guest session. */
    public static function guest(): GuestCookie
    {
        return new GuestCookie(self::COOKIE, (int) apply_filters('wc_session_expiration', 2 * DAY_IN_SECONDS));
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

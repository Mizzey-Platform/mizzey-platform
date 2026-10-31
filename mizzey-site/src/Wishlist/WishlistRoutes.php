<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Wishlist;

defined('ABSPATH') || exit;

/**
 * The wishlist and recently viewed lists over the REST API, for the storefront's screens (the designed screens wait for
 * PRE-03b). Each request acts on the visitor's own lists: a customer's when signed in (WordPress's REST cookie
 * authentication, with its nonce), a guest's otherwise (the signed cookie). Items come back in the request's language.
 *
 * - `GET  mizzey/v1/wishlist`                    the saved items
 * - `POST mizzey/v1/wishlist/items`              `{ "id": n }` saves a product or variation
 * - `DELETE mizzey/v1/wishlist/items/{id}`       removes one
 * - `POST mizzey/v1/wishlist/items/{id}/cart`    moves one to the cart, or says why it stays (409)
 * - `GET  mizzey/v1/recently-viewed`             recently viewed products
 *
 * Register ids: WISH-01 to WISH-04, WISH-07. See specs/015-wishlist.
 */
final class WishlistRoutes
{
    public const NS = 'mizzey/v1';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        $id = ['id' => ['type' => 'integer', 'required' => true, 'minimum' => 1]];
        register_rest_route(self::NS, '/wishlist', ['methods' => 'GET', 'callback' => [self::class, 'list'], 'permission_callback' => '__return_true']);
        register_rest_route(self::NS, '/wishlist/items', ['methods' => 'POST', 'callback' => [self::class, 'add'], 'permission_callback' => '__return_true', 'args' => $id]);
        register_rest_route(self::NS, '/wishlist/items/(?P<id>\d+)', ['methods' => 'DELETE', 'callback' => [self::class, 'remove'], 'permission_callback' => '__return_true', 'args' => $id]);
        register_rest_route(self::NS, '/wishlist/items/(?P<id>\d+)/cart', ['methods' => 'POST', 'callback' => [self::class, 'move'], 'permission_callback' => '__return_true', 'args' => $id]);
        register_rest_route(self::NS, '/recently-viewed', ['methods' => 'GET', 'callback' => [self::class, 'recent'], 'permission_callback' => '__return_true']);
    }

    public static function list(): \WP_REST_Response
    {
        return new \WP_REST_Response(['items' => self::items(Wishlist::ids())], 200);
    }

    public static function add(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (Wishlist::add((int) $request['id']) === null) {
            return new \WP_Error('mizzey_wishlist_no_product', __('This product could not be found.', 'mizzey-site'), ['status' => 404]);
        }

        return new \WP_REST_Response(['items' => self::items(Wishlist::ids())], 201);
    }

    public static function remove(\WP_REST_Request $request): \WP_REST_Response
    {
        Wishlist::remove((int) $request['id']);

        return new \WP_REST_Response(['items' => self::items(Wishlist::ids())], 200);
    }

    public static function move(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $moved = Wishlist::moveToCart((int) $request['id']);

        return $moved === true ? new \WP_REST_Response(['items' => self::items(Wishlist::ids())], 200) : $moved;
    }

    public static function recent(): \WP_REST_Response
    {
        $items = array_map(static fn (\WC_Product $p): array => self::item($p, (int) $p->get_id()), RecentlyViewed::products());

        return new \WP_REST_Response(['items' => $items], 200);
    }

    /**
     * @param list<int> $canonicalIds
     * @return list<array<string,mixed>>
     */
    private static function items(array $canonicalIds): array
    {
        $out = [];
        foreach ($canonicalIds as $id) {
            $shown = Wishlist::shown($id);
            if ($shown instanceof \WC_Product) {
                $out[] = self::item($shown, $id);
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private static function item(\WC_Product $shown, int $canonicalId): array
    {
        return [
            'id'        => $shown->get_id(),
            'canonical' => $canonicalId,
            'name'      => $shown->get_name(),
            'permalink' => $shown->get_permalink(),
            'in_stock'  => $shown->is_in_stock(),
        ];
    }
}

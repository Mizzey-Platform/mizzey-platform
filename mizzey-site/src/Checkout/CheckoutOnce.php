<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Checkout;

use Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils;

defined('ABSPATH') || exit;

/**
 * Place Order pressed twice makes one order (PAY-05).
 *
 * The block checkout's two requests for one cart, sent at the same moment, each found the cart full and each made an
 * order: WooCommerce reuses a draft order only for requests that come one after the other, and each request reads its
 * cart before it runs. Each Store API checkout of a cart now runs under a lock of its own (MySQL GET_LOCK, keyed by the
 * cart's session); a second one that finds the lock held waits for the first to finish and is then refused, since the
 * cart it read is the one just ordered. The lock is held until WooCommerce has written the session, so a checkout that
 * starts after it reads the cart as the first one left it.
 *
 * Found by the audit of 2 November 2026 (C M-3). See specs/028-payments AC-1.
 */
final class CheckoutOnce
{
    /** How long a second press waits for the first, in seconds. */
    private const WAIT = 30;

    private static ?string $held = null;

    public static function register(): void
    {
        add_filter('rest_request_before_callbacks', [self::class, 'before'], 5, 3);
        // After WooCommerce writes the session (shutdown, priority 20).
        add_action('shutdown', [self::class, 'release'], 30);
    }

    /**
     * @param mixed $response
     * @param mixed $handler
     * @return mixed
     */
    public static function before($response, $handler, \WP_REST_Request $request)
    {
        if (is_wp_error($response) || self::$held !== null || $request->get_method() !== 'POST' || ! self::isCheckout($request)) {
            return $response;
        }
        $key = self::cartKey($request);
        if ($key === '') {
            return $response;
        }
        global $wpdb;
        $name = substr('mz_checkout_' . md5($wpdb->prefix . $key), 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $name)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            // Another checkout of this cart is running. This request read the cart before it finished, so it would order
            // the same cart again: it waits for that one to end, then is refused. The customer sees the order placed.
            if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, self::WAIT)) === 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }

            return new \WP_Error('mizzey_checkout_busy', __('Your order is already being placed.', 'mizzey-site'), ['status' => 409]);
        }
        self::$held = $name;

        return $response;
    }

    public static function release(): void
    {
        if (self::$held === null) {
            return;
        }
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::$held)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        self::$held = null;
    }

    /** The Store API checkout route, not pay-for-order (`/checkout/{id}`), which pays one existing order. */
    private static function isCheckout(\WP_REST_Request $request): bool
    {
        return preg_match('#^/wc/store(/v\d+)?/checkout/?$#', (string) $request->get_route()) === 1;
    }

    /**
     * The visitor's cart session, one key however it is presented: the session id inside a valid cart token (Store API
     * clients; WooCommerce issues new tokens for one session as they near expiry), or the id in the visitor's valid
     * WooCommerce session cookie (the block checkout). An invalid token or cookie gives none, so a forged one cannot
     * hold another visitor's checkout.
     */
    private static function cartKey(\WP_REST_Request $request): string
    {
        $token = trim((string) $request->get_header('cart_token'));
        if ($token !== '' && class_exists(CartTokenUtils::class)) {
            $session = (string) (CartTokenUtils::get_cart_token_payload($token)['user_id'] ?? '');

            return $session !== '' ? 'session:' . $session : '';
        }
        if (class_exists(\WC_Session_Handler::class)) {
            $cookie = (new \WC_Session_Handler())->get_session_cookie();
            if (is_array($cookie) && (string) ($cookie[0] ?? '') !== '') {
                return 'session:' . (string) $cookie[0];
            }
        }

        return '';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Cart;

use MizzeySite\Stock\StockGateway;

defined('ABSPATH') || exit;

/**
 * A guest's cart survives registration and sign-in (CART-13, CART-14, CART-15) and merges predictably with the
 * account's saved cart (CART-18).
 *
 * WooCommerce carries the guest's session, cart included, to the account on the first request after sign-in
 * (`WC_Session_Handler::migrate_guest_session_to_user_session()`), and then adds the account's saved cart, but only
 * when `wp_login` fired (`_woocommerce_load_saved_cart_after_login`), and by replacing a saved line with the guest's
 * line of the same item rather than combining them (`WC_Cart_Session::get_cart_from_session()`, 11.1.0). So:
 *
 * - every sign-in marks the saved cart to merge, whatever signed the customer in (the account page, a sign-in link,
 *   a social sign-in plugin): the mark follows the logged-in cookie being set;
 * - the merge runs first (`woocommerce_load_cart_from_session`) and combines quantities of the same line, keeps every
 *   other line of both carts, and caps each item at what can be bought: one for an item sold individually, the
 *   product's purchase limit, and the stock the stock source confirms (an unanswered question caps nothing here; the
 *   cart check then says so, CartStock). Prices are WooCommerce's own: each line is priced from its product as the
 *   cart loads.
 *
 * A notice says when a quantity was lowered. Register ids: CART-13, CART-14, CART-15, CART-18. See specs/018-cart.
 */
final class CartMerge
{
    public const FLAG = '_woocommerce_load_saved_cart_after_login';

    public static function register(): void
    {
        add_action('set_logged_in_cookie', [self::class, 'mark'], 10, 4);
        add_action('woocommerce_load_cart_from_session', [self::class, 'merge'], 1);
    }

    /**
     * @param mixed $cookie
     * @param mixed $expire
     * @param mixed $expiration
     * @param mixed $userId
     */
    public static function mark($cookie, $expire, $expiration, $userId): void
    {
        if ((int) $userId > 0) {
            update_user_meta((int) $userId, self::FLAG, 1);
        }
    }

    public static function merge(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0 || ! function_exists('WC') || ! WC()->session || ! get_user_meta($userId, self::FLAG, true)) {
            return;
        }
        $meta   = '_woocommerce_persistent_cart_' . get_current_blog_id();
        $saved  = get_user_meta($userId, $meta, true);
        $saved  = is_array($saved) && is_array($saved['cart'] ?? null) ? $saved['cart'] : [];
        $guest  = WC()->session->get('cart', null);
        $merged = self::combine($saved, is_array($guest) ? $guest : [], StockGateway::make());
        WC()->session->set('cart', $merged['cart']);
        // The account's saved cart is the merged one at once, so another device signing in finds it (CART-09):
        // WooCommerce saves it only when the cart is next recalculated.
        update_user_meta($userId, $meta, ['cart' => $merged['cart']]);
        // WooCommerce's own merge would now replace lines instead of combining them.
        delete_user_meta($userId, self::FLAG);
        if ($merged['lowered'] !== []) {
            /* translators: %s: product names. */
            wc_add_notice(sprintf(__('We lowered the quantity of %s to what is available.', 'mizzey-site'), implode(', ', $merged['lowered'])), 'notice');
        }
    }

    /**
     * The saved cart and the guest cart as one: the same line's quantities added, each line capped at what can be
     * bought.
     *
     * @param array<string,array<string,mixed>> $saved
     * @param array<string,array<string,mixed>> $guest
     * @return array{cart:array<string,array<string,mixed>>,lowered:list<string>}
     */
    public static function combine(array $saved, array $guest, StockGateway $gateway): array
    {
        $cart = $saved;
        foreach ($guest as $key => $line) {
            if (isset($cart[$key])) {
                $cart[$key]['quantity'] = (int) $cart[$key]['quantity'] + (int) ($line['quantity'] ?? 0);
            } else {
                $cart[$key] = $line;
            }
        }
        $lowered = [];
        foreach ($cart as $key => $line) {
            $record  = (int) (($line['variation_id'] ?? 0) ?: ($line['product_id'] ?? 0));
            $product = $record > 0 ? wc_get_product($record) : null;
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $cap = self::cap($product, $record, $gateway);
            if ($cap !== null && (int) $line['quantity'] > $cap) {
                $lowered[] = $product->get_name();
                if ($cap <= 0) {
                    unset($cart[$key]);
                    continue;
                }
                $cart[$key]['quantity'] = $cap;
            }
        }

        return ['cart' => $cart, 'lowered' => $lowered];
    }

    /** The most of the item that can be bought, or null when nothing limits it. */
    private static function cap(\WC_Product $product, int $record, StockGateway $gateway): ?int
    {
        $caps = [];
        if ($product->is_sold_individually()) {
            $caps[] = 1;
        }
        $limit = (int) $product->get_max_purchase_quantity();
        if ($limit > 0) {
            $caps[] = $limit;
        }
        $answer = $gateway->level($record);
        if ($answer->isKnown()) {
            $caps[] = (int) $answer->quantity;
        }

        return $caps !== [] ? min($caps) : null;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Cart;

use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockIdentity;

defined('ABSPATH') || exit;

/**
 * A guest's cart survives registration and sign-in (CART-13, CART-14, CART-15) and merges predictably with the
 * account's saved cart (CART-18).
 *
 * On the first request after sign-in, WooCommerce moves the guest's session, cart included, to the account
 * (`WC_Session_Handler::migrate_guest_session_to_user_session()`, which fires `woocommerce_guest_session_to_user_id`),
 * and then adds the account's saved cart, but only when `wp_login` fired, and by replacing a saved line with the
 * guest's line of the same item rather than combining them (`WC_Cart_Session::get_cart_from_session()`, 11.1.0). So:
 *
 * - the merge runs only in the request where a guest session was moved to the account, whatever signed the customer
 *   in (the account page, registration, a sign-in link, a social sign-in plugin); a sign-in with no guest session
 *   (another device) restores the account's cart as WooCommerce does and adds nothing;
 * - it runs before WooCommerce's own merge (`woocommerce_load_cart_from_session`), combines quantities of the same
 *   line, keeps every other line of both carts, and caps them at what can be bought: one for an item sold one at a
 *   time, and the stock the stock source confirms, shared by every line of one stock item (an unanswered question caps
 *   nothing here; the cart check then says so, CartStock). WooCommerce's own stock field is not a limit (D-19). Prices
 *   are WooCommerce's own: each line is priced from its product as the cart loads;
 * - it clears WooCommerce's merge mark, saves the result as the account's cart and says when a quantity was lowered.
 *
 * Every sign-in still marks the saved cart for WooCommerce's own restore (`set_logged_in_cookie`), as `wp_login` would.
 *
 * Register ids: CART-13, CART-14, CART-15, CART-18. See specs/018-cart.
 */
final class CartMerge
{
    public const FLAG = '_woocommerce_load_saved_cart_after_login';

    /** The account a guest session was moved to in this request, if any. */
    private static ?int $migrated = null;

    public static function register(): void
    {
        add_action('set_logged_in_cookie', [self::class, 'mark'], 10, 4);
        add_action('woocommerce_guest_session_to_user_id', [self::class, 'migrated'], 10, 2);
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

    /**
     * @param mixed $guestId
     * @param mixed $userId
     */
    public static function migrated($guestId, $userId): void
    {
        self::$migrated = (int) $userId > 0 ? (int) $userId : null;
    }

    public static function merge(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0 || self::$migrated !== $userId || ! function_exists('WC') || ! WC()->session) {
            return;
        }
        // Once per move: a later load in this request, or another request, finds no guest session to add.
        self::$migrated = null;
        $meta   = '_woocommerce_persistent_cart_' . get_current_blog_id();
        $saved  = get_user_meta($userId, $meta, true);
        $saved  = is_array($saved) && is_array($saved['cart'] ?? null) ? $saved['cart'] : [];
        $guest  = WC()->session->get('cart', null);
        $merged = self::combine($saved, is_array($guest) ? $guest : [], StockGateway::make());
        WC()->session->set('cart', $merged['cart']);
        // The account's saved cart is the merged one at once, so another device signing in finds it (CART-09):
        // WooCommerce saves it only when the cart is next recalculated.
        update_user_meta($userId, $meta, ['cart' => $merged['cart']]);
        // WooCommerce's own merge would now add the saved cart again, replacing lines instead of combining them.
        delete_user_meta($userId, self::FLAG);
        if ($merged['lowered'] !== []) {
            /* translators: %s: product names. */
            wc_add_notice(sprintf(__('We lowered the quantity of %s to what is available.', 'mizzey-site'), implode(', ', $merged['lowered'])), 'notice');
        }
    }

    /**
     * The saved cart and the guest cart as one: the same line's quantities added, the saved lines first, each line
     * capped at what can be bought, and the lines of one stock item sharing its confirmed stock.
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
        $lowered   = [];
        $remaining = [];
        foreach ($cart as $key => $line) {
            $record  = (int) (($line['variation_id'] ?? 0) ?: ($line['product_id'] ?? 0));
            $product = $record > 0 ? wc_get_product($record) : null;
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $item     = StockIdentity::resolve($record);
            $identity = $item !== null ? $item->id : -$record;
            if (! array_key_exists($identity, $remaining)) {
                $answer               = $gateway->level($record);
                $remaining[$identity] = $answer->isKnown() ? (int) $answer->quantity : null;
            }
            $cap = $remaining[$identity];
            if ($product->is_sold_individually()) {
                $cap = $cap === null ? 1 : min($cap, 1);
            }
            $wanted = (int) $line['quantity'];
            $kept   = $cap === null ? $wanted : max(0, min($wanted, $cap));
            if ($remaining[$identity] !== null) {
                $remaining[$identity] -= $kept;
            }
            if ($kept < $wanted) {
                $lowered[] = $product->get_name();
            }
            if ($kept <= 0) {
                unset($cart[$key]);
                continue;
            }
            $cart[$key]['quantity'] = $kept;
        }

        return ['cart' => $cart, 'lowered' => array_values(array_unique($lowered))];
    }
}

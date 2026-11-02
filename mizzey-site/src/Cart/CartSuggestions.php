<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Cart;

defined('ABSPATH') || exit;

/**
 * Suggestions in the cart (CART-08) read the staff's related-products assignment, as D-19 item 3 rules: the products
 * in the cart's items' Upsells field (the Linked products tab), not the Cross-sells field, so staff keep one list.
 *
 * WooCommerce's cart takes its suggestions from `WC_Cart::get_cross_sells()`, which the classic cart's cross-sells and
 * the Store API cart's `cross_sells` (the cart block's suggestions) both read; its filter returns the assigned products
 * here, without those already in the cart, in the order assigned. A cart whose items have nothing assigned suggests
 * nothing: there is no automatic fallback (D-19).
 *
 * Register ids: CART-08. See specs/018-cart.
 */
final class CartSuggestions
{
    public static function register(): void
    {
        add_filter('woocommerce_cart_crosssell_ids', [self::class, 'assigned'], 10, 2);
    }

    /**
     * @param mixed $ids
     * @param mixed $cart
     * @return mixed
     */
    public static function assigned($ids, $cart)
    {
        if (! $cart instanceof \WC_Cart) {
            return $ids;
        }
        $inCart   = [];
        $assigned = [];
        foreach ($cart->get_cart() as $line) {
            $inCart[] = (int) $line['product_id'];
            $inCart[] = (int) $line['variation_id'];
            $product  = wc_get_product((int) $line['product_id']);
            if ($product instanceof \WC_Product) {
                $assigned = [...$assigned, ...array_map('intval', $product->get_upsell_ids())];
            }
        }

        return array_values(array_diff(array_unique($assigned), $inCart));
    }
}

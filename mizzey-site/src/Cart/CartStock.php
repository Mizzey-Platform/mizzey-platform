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
 * Stock re-validated through the adapter whenever the cart is checked (CART-10, D-19 item 5): every stock question
 * goes to the stock source through StockGateway, never to WooCommerce's own stock field, and fails closed when the
 * source does not answer.
 *
 * One hook covers both carts: WooCommerce runs `woocommerce_check_cart_items` on the classic cart and checkout pages,
 * and the Store API runs it when it reports the cart's errors and before it places an order, turning each error notice
 * into a cart error that blocks checkout. Lines are counted together by stock identity, so two lines of one item, or
 * the item's two language records, are asked about as one quantity. The adapter's own retries and logs apply
 * (specs/012). Asking the ERP when the cart is viewed is ERP-03's mechanism (E-ERP-4); this is the store side.
 *
 * Register ids: CART-10. See specs/018-cart.
 */
final class CartStock
{
    public static function register(): void
    {
        add_action('woocommerce_check_cart_items', [self::class, 'check'], 20);
    }

    public static function check(): void
    {
        $cart = function_exists('WC') ? WC()->cart : null;
        if (! $cart instanceof \WC_Cart) {
            return;
        }
        foreach (self::problems($cart, StockGateway::make()) as $message) {
            wc_add_notice($message, 'error');
        }
    }

    /**
     * The cart's stock problems, one message per stock item: unknown stock, or less stock than the cart holds.
     *
     * @return list<string>
     */
    public static function problems(\WC_Cart $cart, StockGateway $gateway): array
    {
        $wanted = [];
        $names  = [];
        foreach ($cart->get_cart() as $line) {
            $product = $line['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }
            $record = (int) ($line['variation_id'] ?: $line['product_id']);
            $item   = StockIdentity::resolve($record);
            $key    = $item !== null ? $item->id : -$record;
            $wanted[$key] = ($wanted[$key] ?? 0) + (int) $line['quantity'];
            $names[$key]  = [$record, $product->get_name()];
        }
        // What the visitor's own unpaid order already reserved counts as theirs, so a retried checkout is not refused for
        // it (`mizzey_cart_stock_held`, specs/019).
        $held = [];
        foreach ((array) apply_filters('mizzey_cart_stock_held', []) as $heldRecord => $heldQuantity) {
            $heldItem = StockIdentity::resolve((int) $heldRecord);
            $heldKey  = $heldItem !== null ? $heldItem->id : -(int) $heldRecord;
            $held[$heldKey] = ($held[$heldKey] ?? 0) + (int) $heldQuantity;
        }
        $problems = [];
        foreach ($wanted as $key => $quantity) {
            [$record, $name] = $names[$key];
            $answer = $gateway->level($record);
            $quantity = max(0, $quantity - ($held[$key] ?? 0));
            if (! $answer->isKnown()) {
                /* translators: %s: product name. */
                $problems[] = sprintf(__('We could not confirm that %s is in stock. Please try again in a few minutes.', 'mizzey-site'), $name);
            } elseif (! $answer->sellable($quantity)) {
                $problems[] = (int) $answer->quantity > 0
                    /* translators: 1: units available, 2: product name. */
                    ? sprintf(_n('Only %1$d of %2$s is available. Please change the quantity in your cart.', 'Only %1$d of %2$s are available. Please change the quantity in your cart.', (int) $answer->quantity, 'mizzey-site'), (int) $answer->quantity, $name)
                    /* translators: %s: product name. */
                    : sprintf(__('%s is out of stock. Please remove it from your cart.', 'mizzey-site'), $name);
            }
        }

        return $problems;
    }
}

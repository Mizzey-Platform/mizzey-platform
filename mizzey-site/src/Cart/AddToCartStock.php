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
 * Stock asked of the stock source when something is added to the cart (ERP-03): an item is added only when the source
 * confirms it holds what the cart would then hold, and it fails closed when the source does not answer. Viewing the
 * cart is CartStock's (CART-10); together they are ERP-03's "adding to the cart and viewing the cart take account of
 * your ERP stock".
 *
 * One filter covers every route that adds: `woocommerce_add_to_cart_validation` runs for the product page form, a link
 * such as buy now (`?add-to-cart=`), and the Store API's add-item route behind the block cart and quick add, which
 * turns the error notice into the route's error. WooCommerce's own `WC_Cart::add_to_cart()`, called by code, does not
 * run it: a merge at sign-in caps itself (CartMerge) and the cart check covers the rest.
 *
 * The quantity asked about is what the cart would hold for the stock item: what is already there, on any line and in
 * either language record, plus what is being added, less what the visitor's own unpaid order already holds
 * (`mizzey_cart_stock_held`, specs/019), counted as CartStock counts it.
 *
 * Register ids: ERP-03. See specs/062-add-to-cart-stock.
 */
final class AddToCartStock
{
    public static function register(): void
    {
        add_filter('woocommerce_add_to_cart_validation', [self::class, 'validate'], 20, 4);
    }

    /**
     * @param mixed $passed
     * @param mixed $productId
     * @param mixed $quantity
     * @param mixed $variationId
     */
    public static function validate($passed, $productId = 0, $quantity = 1, $variationId = 0): bool
    {
        if (! $passed) {
            return false;
        }
        $cart   = function_exists('WC') ? WC()->cart : null;
        $record = (int) $variationId ?: (int) $productId;
        $problem = self::problem($record, max(1, (int) $quantity), $cart instanceof \WC_Cart ? $cart : null, StockGateway::make());
        if ($problem === null) {
            return true;
        }
        wc_add_notice($problem, 'error');

        return false;
    }

    /**
     * Why `$quantity` more of `$record` cannot go into the cart, or null when the stock source holds enough.
     */
    public static function problem(int $record, int $quantity, ?\WC_Cart $cart, StockGateway $gateway): ?string
    {
        $product = wc_get_product($record);
        $name    = $product instanceof \WC_Product ? $product->get_name() : (string) $record;
        $item    = StockIdentity::resolve($record);
        $key     = $item !== null ? $item->id : -$record;

        $inCart = 0;
        foreach ($cart instanceof \WC_Cart ? $cart->get_cart() : [] as $line) {
            $lineRecord = (int) (($line['variation_id'] ?? 0) ?: ($line['product_id'] ?? 0));
            $lineItem   = StockIdentity::resolve($lineRecord);
            if (($lineItem !== null ? $lineItem->id : -$lineRecord) === $key) {
                $inCart += (int) ($line['quantity'] ?? 0);
            }
        }
        $held = 0;
        foreach ((array) apply_filters('mizzey_cart_stock_held', []) as $heldRecord => $heldQuantity) {
            $heldItem = StockIdentity::resolve((int) $heldRecord);
            if (($heldItem !== null ? $heldItem->id : -(int) $heldRecord) === $key) {
                $held += (int) $heldQuantity;
            }
        }
        $alreadyMine = max(0, $inCart - $held);

        $answer = $gateway->level($record);
        if (! $answer->isKnown()) {
            /* translators: %s: product name. */
            return sprintf(__('We could not confirm that %s is in stock. Please try again in a few minutes.', 'mizzey-site'), $name);
        }
        if ($answer->sellable($alreadyMine + $quantity)) {
            return null;
        }
        $available = max(0, (int) $answer->quantity);
        if ($available === 0) {
            /* translators: %s: product name. */
            return sprintf(__('%s is out of stock.', 'mizzey-site'), $name);
        }
        if ($alreadyMine === 0) {
            /* translators: 1: units available, 2: product name. */
            return sprintf(_n('Only %1$d of %2$s is available.', 'Only %1$d of %2$s are available.', $available, 'mizzey-site'), $available, $name);
        }

        /* translators: 1: units available, 2: product name, 3: units already in the cart. */
        return sprintf(_n('Only %1$d of %2$s is available, and your cart already holds %3$d.', 'Only %1$d of %2$s are available, and your cart already holds %3$d.', $available, 'mizzey-site'), $available, $name, $alreadyMine);
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The stock state a product card shows (PLP-03), read from the product's stock as WooCommerce holds it.
 *
 * Read only: it asks nothing of the stock source and writes nothing. WooCommerce's figure is the one each source keeps
 * current (the ERP's projection, or the store's own stock, D-28 and D-29), so a card shows the state of whichever source
 * the product is set to without choosing one. Adding to the cart is what asks the source (AddToCartStock, specs/062):
 * a card may still say "Only 3 left" while the source refuses, and the add then fails closed.
 *
 * - `out`: WooCommerce says the product cannot be bought from stock (out of stock, or a variable product with no
 *   variation in stock).
 * - `low`: a product whose stock WooCommerce counts holds no more than its low-stock amount (its own, else the
 *   store's), and more than none: the card reads "Only N left".
 * - `in`: anything else, including a product whose stock is not counted and a product on backorder.
 *
 * Register ids: PLP-03 (AC-6, provisional until PRE-09). See specs/066-stage-2-shop-front, slice 4.
 */
final class CardStock
{
    public const IN  = 'in';
    public const LOW = 'low';
    public const OUT = 'out';

    /** @return array{state:string,left:int|null} */
    public static function of(\WC_Product $product): array
    {
        if (! $product->is_in_stock()) {
            return ['state' => self::OUT, 'left' => null];
        }
        if ($product->is_type('variable') || ! $product->managing_stock()) {
            return ['state' => self::IN, 'left' => null];
        }
        $left = (int) $product->get_stock_quantity();
        if ($left > 0 && $left <= (int) wc_get_low_stock_amount($product)) {
            return ['state' => self::LOW, 'left' => $left];
        }

        return ['state' => self::IN, 'left' => null];
    }
}

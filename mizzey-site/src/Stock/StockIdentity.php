<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

use MizzeySite\Catalogue\CanonicalProduct;

defined('ABSPATH') || exit;

/**
 * Resolves a product or variation, in either language, to one stock identity: its canonical record, the translation
 * group's source-language record (ERP-01, D-10: one commercial item or variant is one ERP stock identity regardless of
 * language). A record with no canonical record has no identity: unknown stock is not sellable.
 *
 * See specs/012-erp-adapter-seam, FR-002, and CanonicalProduct.
 */
final class StockIdentity
{
    public static function resolve(int $id): ?StockItem
    {
        $record = CanonicalProduct::of($id);
        if ($record === null) {
            return null;
        }
        $kind = CanonicalProduct::kind($record) === CanonicalProduct::VARIATION ? StockItem::VARIATION : StockItem::PRODUCT;

        return new StockItem($record->get_id(), $kind, (string) $record->get_sku('edit'));
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * Where each stock item's stock comes from: the ERP adapter or the store's own stock (D-28).
 *
 * In order: the master switch ("ERP off") sends every item to the store's own stock; otherwise the item's own choice,
 * held on its stock identity record (the translation group's source-language product or variation, so both languages
 * agree); otherwise the store default. With nothing set the store default is the ERP, which is how the store behaved
 * before this switch existed.
 *
 * See specs/049-stock-source-switch, FR-001.
 */
final class StockMode
{
    public const ERP   = 'erp';
    public const STORE = 'store';

    /** The store default, `erp` or `store`. */
    public const DEFAULT_OPTION = 'mizzey_stock_default_source';
    /** `yes` while the ERP is switched off for every item. */
    public const ERP_OFF_OPTION = 'mizzey_stock_erp_off';
    /** An item's own choice, on its stock identity record: `erp`, `store`, or absent to follow the default. */
    public const ITEM_META = '_mizzey_stock_source';

    public static function storeDefault(): string
    {
        return get_option(self::DEFAULT_OPTION) === self::STORE ? self::STORE : self::ERP;
    }

    public static function erpOff(): bool
    {
        return get_option(self::ERP_OFF_OPTION) === 'yes';
    }

    /** The item's own choice, or '' when it follows the store default. */
    public static function choice(int $itemId): string
    {
        $value = (string) get_post_meta($itemId, self::ITEM_META, true);

        return in_array($value, [self::ERP, self::STORE], true) ? $value : '';
    }

    public static function sourceFor(StockItem $item): string
    {
        if (self::erpOff()) {
            return self::STORE;
        }
        $choice = self::choice($item->id);

        return $choice !== '' ? $choice : self::storeDefault();
    }

    /** Whether an item, in either language, reads the store's own stock now. */
    public static function onStore(int $productId): bool
    {
        $item = StockIdentity::resolve($productId);

        return $item !== null && self::sourceFor($item) === self::STORE;
    }

    /** Sets an item's own choice ('' follows the default). The caller has checked the user's right. */
    public static function choose(StockItem $item, string $choice): void
    {
        if ($choice === '') {
            delete_post_meta($item->id, self::ITEM_META);
        } elseif (in_array($choice, [self::ERP, self::STORE], true)) {
            update_post_meta($item->id, self::ITEM_META, $choice);
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * What the stock source is asked about: one commercial item, whatever language record the store was looking at.
 *
 * The id is the translation group's source-language record (StockIdentity). Whether the ERP keys its stock per product
 * or per variant, and by which external key, is the adapter's mapping (PRE-09, M16 and M17).
 *
 * Register ids: ERP-01. See specs/012-erp-adapter-seam, FR-002.
 */
final class StockItem
{
    public const PRODUCT   = 'product';
    public const VARIATION = 'variation';

    public function __construct(public readonly int $id, public readonly string $kind, public readonly string $sku)
    {
    }

    public function label(): string
    {
        return $this->kind . ' ' . $this->id . ($this->sku !== '' ? ' (' . $this->sku . ')' : '');
    }
}

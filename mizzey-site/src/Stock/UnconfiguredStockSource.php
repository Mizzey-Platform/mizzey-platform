<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The adapter the store has until PRE-09's adapter is bound: it refuses everything, so the gateway answers unknown
 * and nothing is sold on stock no source has confirmed (ERP-01; M-3, no ERP mechanism is chosen before PRE-09).
 */
final class UnconfiguredStockSource implements StockSource
{
    public function name(): string
    {
        return 'unconfigured';
    }

    public function level(StockItem $item): int
    {
        throw new StockSourceRefused('No stock source is configured.');
    }

    public function adjust(StockItem $item, int $delta, string $operationKey): int
    {
        throw new StockSourceRefused('No stock source is configured.');
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The adapter boundary to the stock source of truth (INT-16, ERP-01). The real adapter is written when PRE-09 is
 * approved; until then the store binds UnconfiguredStockSource, and tests bind a mock.
 *
 * The contract every adapter keeps:
 * - level() returns the quantity the source holds for the item now.
 * - adjust() applies a change of `$delta` units once per `$operationKey`: a key already applied returns the current
 *   level and changes nothing, so a retry after a timeout cannot change stock twice.
 * - A failure that may pass (timeout, connection, server error) throws StockSourceUnavailable, and the gateway retries.
 * - A failure that will not pass (unknown item, rejected change) throws StockSourceRefused, and is not retried.
 *
 * See specs/012-erp-adapter-seam, FR-003 and FR-005.
 */
interface StockSource
{
    /** A short name for the logs. */
    public function name(): string;

    /** @throws StockSourceUnavailable|StockSourceRefused */
    public function level(StockItem $item): int;

    /** @throws StockSourceUnavailable|StockSourceRefused */
    public function adjust(StockItem $item, int $delta, string $operationKey): int;
}

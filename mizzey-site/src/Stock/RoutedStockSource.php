<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * Sends each stock item to the ERP adapter or the store's own stock, as StockMode says (D-28).
 *
 * It is the store's stock source: bound last on the `mizzey_stock_source` filter, it takes whatever adapter was bound
 * before it (the ERP's once PRE-09 is approved, the fixture on development and staging, none on production until
 * then) as the ERP side. `StockGateway` asks it which source holds an item, so retries, logging and the source's name
 * in the logs stay per source. It never chooses for itself: an ERP that does not answer stays an outage (OD-41).
 *
 * See specs/049-stock-source-switch, FR-001 and FR-006.
 */
final class RoutedStockSource implements StockSource
{
    public function __construct(private readonly StockSource $erp, private readonly StockSource $store)
    {
    }

    public static function register(): void
    {
        add_filter(StockGateway::SOURCE_FILTER, [self::class, 'bind'], 1000);
    }

    /**
     * @param mixed $source
     * @return mixed
     */
    public static function bind($source)
    {
        if ($source instanceof self) {
            return $source;
        }

        return new self($source instanceof StockSource ? $source : new UnconfiguredStockSource(), new StoreStockSource());
    }

    public function erp(): StockSource
    {
        return $this->erp;
    }

    public function store(): StoreStockSource
    {
        return $this->store instanceof StoreStockSource ? $this->store : new StoreStockSource();
    }

    /** The source that holds this item now. */
    public function for(StockItem $item): StockSource
    {
        return StockMode::sourceFor($item) === StockMode::STORE ? $this->store : $this->erp;
    }

    public function name(): string
    {
        return 'routed';
    }

    public function level(StockItem $item): int
    {
        return $this->for($item)->level($item);
    }

    public function adjust(StockItem $item, int $delta, string $operationKey): int
    {
        return $this->for($item)->adjust($item, $delta, $operationKey);
    }
}

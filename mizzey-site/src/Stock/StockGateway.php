<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The one place the store asks about stock and changes it (ERP-01, INT-16). Callers name a product or variation in
 * either language; the gateway resolves it to its stock identity, asks the adapter, retries a failure a bounded
 * number of times with the same operation key, logs every attempt, and answers unknown when the source did not
 * answer. It never writes WooCommerce's own stock field: closing the local stock writers is #255's and #306's
 * (D-19 item 5).
 *
 * The adapter comes from the `mizzey_stock_source` filter; the default refuses (UnconfiguredStockSource) until
 * PRE-09's adapter is bound.
 *
 * Register ids: ERP-01, ERP-02, INT-16. See specs/012-erp-adapter-seam.
 */
final class StockGateway
{
    public const SOURCE_FILTER   = 'mizzey_stock_source';
    public const ATTEMPTS_FILTER = 'mizzey_stock_attempts';
    public const LOG_SOURCE      = 'mizzey-stock';

    public function __construct(
        private readonly StockSource $source,
        private readonly int $attempts = 3,
        private readonly ?\WC_Logger_Interface $logger = null,
        private readonly int $pauseMs = 100,
    ) {
    }

    /** The gateway with the store's adapter and settings. */
    public static function make(): self
    {
        $source = apply_filters(self::SOURCE_FILTER, null);

        return new self(
            $source instanceof StockSource ? $source : new UnconfiguredStockSource(),
            max(1, (int) apply_filters(self::ATTEMPTS_FILTER, 3)),
            function_exists('wc_get_logger') ? wc_get_logger() : null,
        );
    }

    public function level(int $productId): StockAnswer
    {
        $item = StockIdentity::resolve($productId);
        if ($item === null) {
            $this->log('warning', 'level', null, 0, 'no stock identity for record ' . $productId);

            return StockAnswer::unknown(null, 'no stock identity');
        }

        return $this->attempt('level', $item, '', fn (): int => $this->source->level($item));
    }

    /** Whether `$quantity` units can be sold: only when the source answered, and holds enough. */
    public function sellable(int $productId, int $quantity): bool
    {
        return $this->level($productId)->sellable($quantity);
    }

    /**
     * Changes stock by `$delta` units, once for `$operationKey`: the caller's name for this one change (an order line,
     * a cancellation), kept on every retry so the source applies it once (the StockSource contract).
     */
    public function adjust(int $productId, int $delta, string $operationKey): StockAnswer
    {
        $item = StockIdentity::resolve($productId);
        if ($item === null || $delta === 0 || trim($operationKey) === '') {
            $reason = $item === null ? 'no stock identity' : ($delta === 0 ? 'no change asked for' : 'no operation key');
            $this->log('warning', 'adjust', $item, 0, $reason . ' (record ' . $productId . ')');

            return StockAnswer::unknown($item, $reason);
        }

        return $this->attempt('adjust', $item, $operationKey, fn (): int => $this->source->adjust($item, $delta, $operationKey));
    }

    /** @param callable(): int $call */
    private function attempt(string $operation, StockItem $item, string $key, callable $call): StockAnswer
    {
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            try {
                $quantity = $call();
                $this->log($operation === 'adjust' ? 'info' : 'debug', $operation, $item, $attempt, 'answered ' . $quantity, $key);

                return StockAnswer::known($item, $quantity);
            } catch (StockSourceRefused $e) {
                $this->log('warning', $operation, $item, $attempt, 'refused: ' . $e->getMessage(), $key);

                return StockAnswer::unknown($item, 'refused');
            } catch (StockSourceUnavailable $e) {
                $this->log('warning', $operation, $item, $attempt, 'unavailable: ' . $e->getMessage(), $key);
                if ($attempt < $this->attempts && $this->pauseMs > 0) {
                    usleep($this->pauseMs * 1000);
                }
            } catch (\Throwable $e) {
                // An adapter that breaks its contract is not trusted to answer again: fail closed at once.
                $this->log('error', $operation, $item, $attempt, get_class($e) . ': ' . $e->getMessage(), $key);

                return StockAnswer::unknown($item, 'adapter error');
            }
        }

        return StockAnswer::unknown($item, 'exhausted');
    }

    /** Diagnostics never change the stock answer: a failing logger, or an adapter whose name() fails, is ignored. */
    private function log(string $level, string $operation, ?StockItem $item, int $attempt, string $outcome, string $key = ''): void
    {
        try {
            try {
                $name = $this->source->name();
            } catch (\Throwable) {
                $name = get_class($this->source);
            }
            $this->logger?->log($level, sprintf('stock %s %s, attempt %d, %s: %s', $operation, $item ? $item->label() : '-', $attempt, $name, $outcome), [
                'source'    => self::LOG_SOURCE,
                'operation' => $operation,
                'item'      => $item?->id,
                'attempt'   => $attempt,
                'key'       => $key,
            ]);
        } catch (\Throwable) {
            // The answer stands without its log line.
        }
    }
}

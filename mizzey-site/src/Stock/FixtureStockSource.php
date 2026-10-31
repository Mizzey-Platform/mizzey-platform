<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * A stand-in stock source for development and staging, until PRE-09's adapter exists (D-10: a fixture stands in for a
 * missing input; M-3: no ERP mechanism is chosen). It answers from the canonical record's WooCommerce stock fields, as
 * a fixture of what the ERP would hold, less the changes it was asked for. Changes are kept in its own ledger, once per
 * operation key as the StockSource contract asks, so an order can be placed and cancelled on staging; it never writes
 * WooCommerce's stock fields.
 *
 * It is bound only when the `mizzey_stock_fixture` option is `yes` and the site is not a production site
 * (`wp_get_environment_type()`); on production the store keeps UnconfiguredStockSource, and every stock question fails
 * closed until the real adapter is bound (D-19 item 5). A bound adapter always wins over it.
 *
 * See specs/018-cart.
 */
final class FixtureStockSource implements StockSource
{
    public const OPTION = 'mizzey_stock_fixture';
    /** The fixture's own ledger of changes: operation key => [item id, delta]. */
    public const LEDGER = 'mizzey_stock_fixture_ledger';
    /** What an item whose stock WooCommerce does not count is taken to hold. */
    public const UNCOUNTED = 9999;

    public static function register(): void
    {
        add_filter(StockGateway::SOURCE_FILTER, [self::class, 'bind'], 5);
    }

    public static function enabled(): bool
    {
        return wp_get_environment_type() !== 'production' && get_option(self::OPTION) === 'yes';
    }

    /**
     * @param mixed $source
     * @return mixed
     */
    public static function bind($source)
    {
        return $source === null && self::enabled() ? new self() : $source;
    }

    public function name(): string
    {
        return 'fixture';
    }

    public function level(StockItem $item): int
    {
        $product = wc_get_product($item->id);
        if (! $product instanceof \WC_Product) {
            throw new StockSourceRefused('unknown item');
        }
        $base = $product->managing_stock() ? max(0, (int) $product->get_stock_quantity()) : ($product->is_in_stock() ? self::UNCOUNTED : 0);
        foreach (self::ledger() as [$id, $delta]) {
            if ((int) $id === $item->id) {
                $base += (int) $delta;
            }
        }

        return $base;
    }

    /** Applies a change once per key; a change that would take stock below zero is refused. */
    public function adjust(StockItem $item, int $delta, string $operationKey): int
    {
        $ledger = self::ledger();
        if (! isset($ledger[$operationKey])) {
            if ($this->level($item) + $delta < 0) {
                throw new StockSourceRefused('not enough stock');
            }
            $ledger[$operationKey] = [$item->id, $delta];
            update_option(self::LEDGER, $ledger, false);
        }

        return $this->level($item);
    }

    /** @return array<string,array{0:int,1:int}> */
    private static function ledger(): array
    {
        $ledger = get_option(self::LEDGER, []);

        return is_array($ledger) ? $ledger : [];
    }
}

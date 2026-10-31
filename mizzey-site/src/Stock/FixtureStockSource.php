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
 * a fixture of what the ERP would hold, and refuses every change, so it never writes stock.
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
        if ($product->managing_stock()) {
            return max(0, (int) $product->get_stock_quantity());
        }

        return $product->is_in_stock() ? self::UNCOUNTED : 0;
    }

    public function adjust(StockItem $item, int $delta, string $operationKey): int
    {
        throw new StockSourceRefused('fixture is read-only');
    }
}

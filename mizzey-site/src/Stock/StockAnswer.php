<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * The gateway's answer: a known quantity, or unknown with the reason. Unknown is never sellable (ERP-01, D-10's fail
 * closed invariant).
 *
 * See specs/012-erp-adapter-seam, FR-004.
 */
final class StockAnswer
{
    private function __construct(public readonly ?StockItem $item, public readonly ?int $quantity, public readonly string $reason)
    {
    }

    public static function known(StockItem $item, int $quantity): self
    {
        return new self($item, $quantity, '');
    }

    public static function unknown(?StockItem $item, string $reason): self
    {
        return new self($item, null, $reason);
    }

    public function isKnown(): bool
    {
        return $this->quantity !== null;
    }

    public function sellable(int $quantity): bool
    {
        return $this->quantity !== null && $quantity > 0 && $this->quantity >= $quantity;
    }
}

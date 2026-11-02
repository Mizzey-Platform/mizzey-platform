<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

defined('ABSPATH') || exit;

/**
 * A carrier, as the store's shipping layer sees it (SHIP-08: the carrier's official integration wrapped in an
 * abstraction layer). The integration creates the shipment and receives the carrier's status; this tells the layer
 * which orders are the carrier's, their tracking number, and the carrier's latest status code and time.
 *
 * See specs/041-bosta.
 */
interface Carrier
{
    public function id(): string;

    public function name(): string;

    public function ownsOrder(\WC_Order $order): bool;

    public function tracking(\WC_Order $order): string;

    /** @return array{code:string,label:string,at:int}|null The latest status the integration recorded, or null. */
    public function status(\WC_Order $order): ?array;
}

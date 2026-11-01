<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

defined('ABSPATH') || exit;

/**
 * One payment event a provider reported and Mizzey verified: a payment, a refund or a void, with its outcome. It holds
 * the provider's reference and the money, never a card field (PAY-02, PAY-06).
 *
 * Register ids: PAY-06. See specs/028-payments.
 */
final class PaymentEvent
{
    public const PAYMENT = 'payment';
    public const REFUND  = 'refund';
    public const VOID    = 'void';
    /** A payment the provider now reports refunded: the amount is the total refunded so far. */
    public const REFUNDED = 'refunded';
    /** A payment the provider now reports voided. */
    public const VOIDED   = 'voided';

    public const SUCCESS = 'success';
    public const FAILED  = 'failed';
    public const PENDING = 'pending';

    public function __construct(
        public readonly string $provider,
        public readonly int $orderId,
        public readonly string $reference,
        public readonly string $kind,
        public readonly string $outcome,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $occurredAt,
        public readonly string $channel,
    ) {
    }

    /** The key that makes an event the same event when it arrives twice. */
    public function key(): string
    {
        return $this->provider . ':' . $this->kind . ':' . $this->reference;
    }

    /** @return array<string,int|string> */
    public function toArray(int $receivedAt): array
    {
        return [
            'provider'    => $this->provider,
            'reference'   => $this->reference,
            'kind'        => $this->kind,
            'outcome'     => $this->outcome,
            'amount'      => $this->amountCents,
            'currency'    => $this->currency,
            'occurred_at' => $this->occurredAt,
            'channel'     => $this->channel,
            'received_at' => $receivedAt,
        ];
    }
}

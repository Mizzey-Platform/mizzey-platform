<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

defined('ABSPATH') || exit;

/**
 * What a provider made of one request to its callback address: a verified event, or a refusal with its reason. The
 * reason is for the log and the order note only; it never carries the request's content.
 *
 * Register ids: PAY-04, PAY-16. See specs/028-payments.
 */
final class CallbackResult
{
    private function __construct(
        public readonly ?PaymentEvent $event,
        public readonly int $orderId,
        public readonly string $reason,
        public readonly string $channel,
    ) {
    }

    public static function verified(PaymentEvent $event): self
    {
        return new self($event, $event->orderId, '', $event->channel);
    }

    /** A request that is not the provider's word: unsigned, wrongly signed, or of a shape the store does not take. */
    public static function refused(string $reason, string $channel, int $orderId = 0): self
    {
        return new self(null, $orderId, $reason, $channel);
    }

    public function isVerified(): bool
    {
        return $this->event !== null;
    }
}

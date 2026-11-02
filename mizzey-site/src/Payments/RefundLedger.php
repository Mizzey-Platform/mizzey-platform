<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

defined('ABSPATH') || exit;

/**
 * Refunds made from the order screen, counted once. WooCommerce records the refund and asks the gateway to make it;
 * the provider then sends a notice of the same refund, which a gateway can record a second time. The refund takes the
 * order's payment lock, so its notice waits until the refund is saved; the provider's reference for it is kept from
 * the gateway's own order note; and the gate acknowledges that notice without passing it on.
 *
 * Register ids: PAY-05, PAY-07, PAY-08, PAY-17. See specs/028-payments.
 */
final class RefundLedger
{
    public const MADE_HERE = '_mizzey_refund_made_here';

    public static function register(): void
    {
        add_action('woocommerce_create_refund', [self::class, 'lockOrder'], 0, 2);
        add_filter('woocommerce_new_order_note_data', [self::class, 'captureReference'], 10, 2);
    }

    /**
     * @param mixed $refund
     * @param mixed $args
     * @throws \Exception When another payment request holds the order; `wc_create_refund()` returns it as its error.
     */
    public static function lockOrder($refund, $args): void
    {
        $order = is_array($args) ? wc_get_order((int) ($args['order_id'] ?? 0)) : null;
        if (! $order instanceof \WC_Order || PaymentGate::providerFor($order) === null) {
            return;
        }
        if (! PaymentGate::lock('o' . $order->get_id())) {
            throw new \Exception(esc_html__('A payment update for this order is in progress. Try the refund again in a moment.', 'mizzey-site'));
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public static function captureReference($data, $args)
    {
        $order = is_array($args) ? wc_get_order((int) ($args['order_id'] ?? 0)) : null;
        if (! is_array($data) || ! $order instanceof \WC_Order) {
            return $data;
        }
        $provider = PaymentGate::providerFor($order);
        $reference = $provider !== null ? $provider->refundReferenceInNote((string) ($data['comment_content'] ?? '')) : null;
        if ($reference !== null && ! self::madeHere($order, $reference)) {
            $order->add_meta_data(self::MADE_HERE, $reference, false);
            $order->save_meta_data();
        }
        if ($reference !== null) {
            \MizzeySite\Orders\RefundReference::fromGateway($order, $reference);
        }

        return $data;
    }

    /** Keep a refund the layer made itself, so the provider's notice of it is acknowledged and not recorded again. */
    public static function record(\WC_Order $order, string $reference): void
    {
        if (! self::madeHere($order, $reference)) {
            $order->add_meta_data(self::MADE_HERE, $reference, false);
            $order->save_meta_data();
        }
    }

    public static function madeHere(\WC_Order $order, string $reference): bool
    {
        foreach ($order->get_meta(self::MADE_HERE, false) as $meta) {
            if ((string) $meta->value === $reference) {
                return true;
            }
        }

        return false;
    }
}

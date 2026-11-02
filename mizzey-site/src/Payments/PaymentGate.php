<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

use MizzeySite\Payments\Paymob\PaymobProvider;

defined('ABSPATH') || exit;

/**
 * The payment layer's gate (PAY-01). Every request to a provider's callback address passes here first, ahead of the
 * provider's own gateway code, and reaches that code only when it is the provider's word, belongs to this order,
 * matches what the order asks for, and has not already been applied.
 *
 * In order: the provider verifies the signature (PAY-04, PAY-16); the order is locked, so two notices of one payment,
 * or a refund and its notice, run one after the other; a transaction already held by another order is refused; a
 * repeat is acknowledged and stops (PAY-05); the event is recorded (PAY-06); then a pending notice, a payment of
 * another amount or currency, a second payment of a paid order, a payment of a closed order, a void, and the notice of
 * a refund made from the order screen are recorded and stop, the money cases flagged for staff. What is left (a first
 * success or failure for an order awaiting payment, or a refund made in the provider's dashboard) goes on to the
 * gateway. The lock is held to the end of the request, so the gateway acts inside it.
 *
 * Register ids: PAY-01, PAY-04, PAY-05, PAY-06, PAY-16. See specs/028-payments.
 */
final class PaymentGate
{
    public const LOG_SOURCE = 'mizzey-payments';

    /** Statuses in which an order still waits for its payment. */
    public const AWAITING = ['pending', 'failed', 'on-hold'];

    /** Seconds a request waits for another to finish with the same order. */
    private const LOCK_WAIT = 10;

    /**
     * How a request ends when the gate stops it. Tests replace it to observe the end instead of exiting.
     *
     * @var callable(int,string,string):void|null
     */
    public static $finish = null;

    /** @var array<string,true> */
    private static array $held = [];

    public static function register(): void
    {
        foreach (self::providers() as $provider) {
            add_action('woocommerce_api_' . $provider->callbackAction(), static function () use ($provider): void {
                self::handle($provider, self::method(), wp_unslash($_GET), (string) file_get_contents('php://input'));
            }, 1);
        }
        add_action('shutdown', [self::class, 'releaseAll']);
    }

    /** @return list<PaymentProvider> */
    public static function providers(): array
    {
        $providers = apply_filters('mizzey_payment_providers', [new PaymobProvider()]);

        return array_values(array_filter(is_array($providers) ? $providers : [], static fn ($p) => $p instanceof PaymentProvider));
    }

    public static function providerFor(\WC_Order $order): ?PaymentProvider
    {
        foreach (self::providers() as $provider) {
            if ($provider->ownsOrder($order)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Decide one callback request. Returns when the gateway may act; otherwise ends the request.
     *
     * @param array<string,mixed> $query
     */
    public static function handle(PaymentProvider $provider, string $method, array $query, string $body): void
    {
        $result  = $provider->read($method, $query, $body);
        $channel = $result->channel;
        if (! $result->isVerified()) {
            self::stop($channel, 403, 'refused', $result->orderId, $result->reason);
            return;
        }
        $event = $result->event;
        $order = wc_get_order($event->orderId);
        if (! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund || ! $provider->ownsOrder($order)) {
            self::stop($channel, 403, 'refused', 0, 'unknown_order_' . $event->kind);
            return;
        }
        if (! self::lock('o' . $order->get_id()) || ! self::lock('r' . md5($event->key()))) {
            self::stop($channel, 503, 'busy', $order->get_id(), 'order_busy', $order);
            return;
        }
        $order = wc_get_order($order->get_id());
        if (! $order instanceof \WC_Order) {
            self::stop($channel, 403, 'refused', 0, 'order_vanished');
            return;
        }

        $owner = PaymentRecord::ownerOf($event->key());
        if ($owner !== 0 && $owner !== $order->get_id()) {
            self::stop($channel, 403, 'refused', $order->get_id(), 'held_by_order_' . $owner);
            return;
        }
        $awaiting = $order->has_status(self::AWAITING);
        if ($owner === $order->get_id() && ! ($event->kind === PaymentEvent::PAYMENT && $event->outcome === PaymentEvent::SUCCESS && $awaiting)) {
            // Already applied, or nothing to apply. A success recorded while the order still waits is let through
            // again, as the gateway did not finish it the first time.
            self::stop($channel, 200, 'already handled', $order->get_id(), '', $order);
            return;
        }
        PaymentRecord::add($order, $event);

        $amount = self::money($event->amountCents, $event->currency);
        switch ($event->kind) {
            case PaymentEvent::REFUND:
                // A refund's own notice never reaches the gateway, which would count it again: a refund made here is
                // already on the order, and one made in the provider's dashboard is recorded from the payment's
                // refunded total.
                self::stop($channel, 200, RefundLedger::madeHere($order, $event->reference) ? 'refund already recorded' : 'recorded', $order->get_id(), '', $order);
                return;
            case PaymentEvent::REFUNDED:
                self::reconcileRefunds($order, $event, $channel);
                return;
            case PaymentEvent::PAYMENT:
                break;
            default: // a void, or a payment reported voided
                if (! $order->has_status('refunded')) {
                    PaymentRecord::flag($order, sprintf(__('%1$s reported a void (reference %2$s). Check the order and its refunds.', 'mizzey-site'), $event->provider, $event->reference));
                }
                self::stop($channel, 200, 'recorded', $order->get_id(), '', $order);
                return;
        }

        if ($event->outcome === PaymentEvent::PENDING) {
            self::stop($channel, 200, 'pending recorded', $order->get_id(), '', $order);
            return;
        }
        if (! $awaiting) {
            if ($event->outcome === PaymentEvent::SUCCESS) {
                $other = PaymentRecord::otherPayment($order, $event->key());
                if ($other !== null) {
                    self::refundSecondPayment($provider, $order, $event, $other, $amount);
                } else {
                    PaymentRecord::flag($order, sprintf(__('A payment of %1$s (reference %2$s) arrived while the order is %3$s. It was not applied: refund it, or restore the order.', 'mizzey-site'), $amount, $event->reference, wc_get_order_status_name($order->get_status())));
                }
            }
            self::stop($channel, 200, 'recorded', $order->get_id(), '', $order);
            return;
        }
        if ($event->outcome === PaymentEvent::SUCCESS) {
            $expected = (int) round((float) $order->get_total('edit') * 100);
            if ($event->amountCents !== $expected || $event->currency !== strtoupper((string) $order->get_currency())) {
                PaymentRecord::flag($order, sprintf(__('%1$s approved %2$s (reference %3$s) but the order total is %4$s. The order was not marked paid: check the payment and refund or correct it.', 'mizzey-site'), $event->provider, $amount, $event->reference, self::money($expected, (string) $order->get_currency())));
                self::stop($channel, 200, 'amount mismatch recorded', $order->get_id(), 'amount_mismatch', $order);
                return;
            }
        }
        // A first success or failure for an order awaiting payment: the gateway applies it, inside the lock.
    }

    /**
     * A second verified payment of an order already paid by another: a duplicate charge (PAY-05). It is refunded in
     * full at once, through the provider, outside the order's own refunds, since the order never counted it. It runs
     * once: the payment was recorded before this call, so a repeat of its notice stops as already handled, and the
     * refund's own notice is acknowledged through the ledger. When the provider does not make the refund, staff are
     * flagged to make it.
     *
     * @param array<string,int|string> $other The payment that paid the order.
     */
    private static function refundSecondPayment(PaymentProvider $provider, \WC_Order $order, PaymentEvent $event, array $other, string $amount): void
    {
        $refund = $provider->refundTransaction($event->reference, $event->amountCents);
        if ($refund !== null) {
            RefundLedger::record($order, $refund);
            $order->add_order_note(sprintf(__('A second payment of %1$s (reference %2$s) arrived for an order already paid by reference %3$s. It was refunded in full automatically (refund reference %4$s).', 'mizzey-site'), $amount, $event->reference, $other['reference'], $refund));
            wc_get_logger()->info(sprintf('second payment refunded (order %d)', $order->get_id()), ['source' => self::LOG_SOURCE]);
            return;
        }
        PaymentRecord::flag($order, sprintf(__('A second payment of %1$s (reference %2$s) arrived for an order already paid by reference %3$s. The automatic refund did not go through: refund the second payment.', 'mizzey-site'), $amount, $event->reference, $other['reference']));
    }

    /**
     * The provider reports the total refunded on the payment so far. The order's own refunds are compared with it: equal
     * is the usual case (a refund made from the order screen); more at the provider is a refund made in its dashboard,
     * recorded on the order for the difference without asking the provider again (PAY-17); less at the provider is
     * flagged for staff. The order is locked, so a refund being made from the order screen is saved before this runs.
     */
    private static function reconcileRefunds(\WC_Order $order, PaymentEvent $event, string $channel): void
    {
        $atProvider = $event->amountCents;
        $onOrder    = (int) round((float) $order->get_total_refunded() * 100);
        if ($atProvider > $onOrder) {
            $refund = wc_create_refund([
                'order_id'       => $order->get_id(),
                'amount'         => ($atProvider - $onOrder) / 100,
                /* translators: 1: payment provider, 2: provider's payment reference. */
                'reason'         => sprintf(__('Refund made in the %1$s dashboard (payment %2$s)', 'mizzey-site'), $event->provider, $event->reference),
                'refund_payment' => false,
                'restock_items'  => false,
            ]);
            if (is_wp_error($refund)) {
                PaymentRecord::flag($order, sprintf(__('%1$s reports %2$s refunded but the order records %3$s, and the difference could not be recorded. Check the order\'s refunds.', 'mizzey-site'), $event->provider, self::money($atProvider, $event->currency), self::money($onOrder, $event->currency)));
            }
        } elseif ($atProvider < $onOrder) {
            PaymentRecord::flag($order, sprintf(__('%1$s reports %2$s refunded but the order records %3$s. Check the order\'s refunds.', 'mizzey-site'), $event->provider, self::money($atProvider, $event->currency), self::money($onOrder, $event->currency)));
        }
        self::stop($channel, 200, $atProvider === $onOrder ? 'refunds match' : 'refunds reconciled', $order->get_id(), '', $order);
    }

    /** Take a named lock for the rest of the request. */
    public static function lock(string $name): bool
    {
        if (isset(self::$held[$name])) {
            return true;
        }
        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', 'mizzey_pay_' . $name, self::LOCK_WAIT));
        if ((string) $got === '1') {
            self::$held[$name] = true;
            return true;
        }

        return false;
    }

    public static function releaseAll(): void
    {
        global $wpdb;
        foreach (array_keys(self::$held) as $name) {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'mizzey_pay_' . $name));
        }
        self::$held = [];
    }

    private static function method(): string
    {
        return isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' ? 'POST' : 'GET';
    }

    private static function money(int $cents, string $currency): string
    {
        return number_format($cents / 100, 2) . ' ' . strtoupper($currency);
    }

    /**
     * End the request. A notice gets a status and a word; a returning customer is sent on: to the order when it is
     * known and paid or being checked, otherwise back to the checkout with a message in their language.
     */
    private static function stop(string $channel, int $status, string $word, int $orderId, string $reason, ?\WC_Order $order = null): void
    {
        if ($reason !== '') {
            wc_get_logger()->warning(sprintf('%s %s: %s (order %d)', $channel, $word, $reason, $orderId), ['source' => self::LOG_SOURCE]);
        }
        $location = '';
        if ($channel === 'return') {
            if ($order instanceof \WC_Order && $status < 400) {
                $location = $order->get_checkout_order_received_url();
            } else {
                if (function_exists('wc_add_notice') && WC()->session) {
                    wc_add_notice(__('Your payment was not confirmed. If you paid, your order will be updated shortly; otherwise you can try again.', 'mizzey-site'), 'error');
                }
                $location = wc_get_checkout_url();
            }
        }
        if (is_callable(self::$finish)) {
            (self::$finish)($status, $word, $location);
            return;
        }
        if ($location !== '') {
            wp_safe_redirect($location);
        } else {
            status_header($status);
            nocache_headers();
            echo esc_html($word);
        }
        exit;
    }
}

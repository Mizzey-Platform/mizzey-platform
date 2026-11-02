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

    /** Refusals that stop every payment until someone acts: logged as errors. */
    private const URGENT = ['no_hmac_secret', 'bad_signature', 'amount_mismatch'];

    /** Whether the event being handled belongs to another provider order than the one that paid the order. */
    private static bool $foreign = false;

    /** The provider's own order id of the transaction that paid the order. */
    public const PROVIDER_ORDER = '_mizzey_provider_order';

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
        add_action('admin_notices', [self::class, 'keyNotice']);
    }

    /**
     * Test keys never reach production, and live keys stay on production (specs/028 FR-011): staff who manage the store
     * are told on every admin page while the stored Paymob keys do not match the site's environment.
     */
    public static function keyNotice(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        $mode = Paymob\PaymobProvider::keyMode();
        if ($mode === '') {
            return;
        }
        $production = wp_get_environment_type() === 'production';
        if ($production && $mode === 'test') {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html__('Paymob is set up with test keys on the live store: no real payment can be taken. Enter the live keys.', 'mizzey-site'));
        } elseif (! $production && $mode === 'live') {
            printf('<div class="notice notice-warning"><p>%s</p></div>', esc_html__('Paymob is set up with live keys on a site that is not the live store: real cards would be charged. Enter the test keys.', 'mizzey-site'));
        }
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
        self::$foreign = false;
        $result  = $provider->read($method, $query, $body);
        $channel = $result->channel;
        if (! $result->isVerified()) {
            self::stop($channel, 403, 'refused', $result->orderId, $result->reason);
            return;
        }
        $event = $result->event;
        $order = wc_get_order($event->orderId);
        if (! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund) {
            self::stop($channel, 403, 'refused', 0, 'unknown_order_' . $event->kind);
            return;
        }
        if (! $provider->ownsOrder($order)) {
            self::otherMethod($provider, $order, $event, $channel);
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

        // A signed fact that contradicts the order (another integration, a failure of another amount): never applied,
        // never sent on to the gateway.
        $binding = $provider->bindingProblem($order, $event);
        // Once the order is paid, a transaction of another provider order is a second payment (refunded below, D-25)
        // or someone else's transaction: either way its return never shows this order's confirmation page.
        $paidBy        = (string) $order->get_meta(self::PROVIDER_ORDER, true);
        self::$foreign = $paidBy !== '' && $paidBy !== (string) ($event->signed['provider_order'] ?? '');
        if ($binding !== null) {
            self::stop($channel, 403, 'refused', $order->get_id(), $binding, $order);
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
        if (in_array($event->kind, [PaymentEvent::REFUND, PaymentEvent::REFUNDED], true) && $channel !== 'notice') {
            // Refunds are taken from Paymob's server notice only, never from a customer's return; not even recorded, so the
            // notice that follows is not taken for a repeat.
            self::stop($channel, 200, 'ignored', $order->get_id(), '', $order);
            return;
        }
        PaymentRecord::add($order, $event);

        $amount = self::money($event->amountCents, $event->currency);
        switch ($event->kind) {
            case PaymentEvent::REFUND:
                // A refund's own notice never reaches the gateway, which would count it again. One made here is already
                // on the order; one made in the provider's dashboard is recorded from this notice, whose amount and
                // provider order the provider signs.
                if (RefundLedger::madeHere($order, $event->reference)) {
                    self::stop($channel, 200, 'refund already recorded', $order->get_id(), '', $order);
                    return;
                }
                self::dashboardRefund($order, $event, $channel);
                return;
            case PaymentEvent::REFUNDED:
                self::compareRefunds($order, $event, $channel);
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
                PaymentRecord::flag($order, sprintf(__('%1$s approved %2$s (reference %3$s) but the order total is %4$s. The order was not marked paid: check the payment and refund or correct it.', 'mizzey-site'), $event->provider, $amount, $event->reference, self::money($expected, (string) $order->get_currency())), 'mismatch' . '-' . $event->key());
                self::stop($channel, 200, 'amount mismatch recorded', $order->get_id(), 'amount_mismatch', $order);
                return;
            }
        }
        if ($event->outcome === PaymentEvent::SUCCESS && ($event->signed['provider_order'] ?? '') !== '') {
            // The provider order that pays this one: any later transaction must belong to it (bindingProblem above).
            $order->update_meta_data(self::PROVIDER_ORDER, (string) $event->signed['provider_order']);
            $order->save_meta_data();
        }
        // A first success or failure for an order awaiting payment: the gateway applies it, inside the lock.
    }

    /**
     * A verified payment for an order that now pays another way: the customer opened the provider's page, went back and
     * placed the order with another method (cash on delivery), then paid in the old tab. The money was taken, so it is
     * recorded on the order once and flagged for staff to refund it or take it as the payment; the gateway is never
     * asked to act on an order that is not its own. Failures and other events are only recorded.
     */
    private static function otherMethod(PaymentProvider $provider, \WC_Order $order, PaymentEvent $event, string $channel): void
    {
        if (! self::lock('o' . $order->get_id()) || ! self::lock('r' . md5($event->key()))) {
            self::stop($channel, 503, 'busy', $order->get_id(), 'order_busy');
            return;
        }
        $order = wc_get_order($order->get_id());
        if (! $order instanceof \WC_Order || $provider->bindingProblem($order, $event) !== null || PaymentRecord::ownerOf($event->key()) !== 0) {
            self::stop($channel, 403, 'refused', $order instanceof \WC_Order ? $order->get_id() : 0, 'other_method_' . $event->kind);
            return;
        }
        PaymentRecord::add($order, $event);
        if ($event->kind === PaymentEvent::PAYMENT && $event->outcome === PaymentEvent::SUCCESS) {
            PaymentRecord::flag($order, sprintf(
                /* translators: 1: provider, 2: amount, 3: reference, 4: the order's payment method. */
                __('%1$s took a payment of %2$s (reference %3$s) for this order, which is set to pay by %4$s. Refund the payment, or take it as the order\'s payment and tell the courier not to collect.', 'mizzey-site'),
                $event->provider,
                self::money($event->amountCents, $event->currency),
                $event->reference,
                (string) $order->get_payment_method_title()
            ));
            wc_get_logger()->error(sprintf('payment for an order paying another way (order %d)', $order->get_id()), ['source' => self::LOG_SOURCE]);
        }
        self::$foreign = true;
        self::stop($channel, 200, 'recorded', $order->get_id(), '', $order);
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
     * A refund made in the provider's dashboard (PAY-17), from the refund's own notice: its amount and its provider order
     * are signed. It is recorded on the order once (its event key), for its amount, only when it belongs to the provider
     * order that paid this one and fits what is left to refund; otherwise staff are told and nothing is written. An order
     * paid before the paying provider order was kept has none to match, so its dashboard refunds are flagged too.
     */
    private static function dashboardRefund(\WC_Order $order, PaymentEvent $event, string $channel): void
    {
        $paidBy    = (string) $order->get_meta(self::PROVIDER_ORDER, true);
        $remaining = (int) round(((float) $order->get_total() - (float) $order->get_total_refunded()) * 100);
        if ($paidBy === '' || $paidBy !== (string) ($event->signed['provider_order'] ?? '') || $event->amountCents <= 0 || $event->amountCents > $remaining) {
            PaymentRecord::flag($order, sprintf(
                /* translators: 1: payment provider, 2: amount, 3: provider's reference. */
                __('%1$s reported a refund of %2$s (reference %3$s) that could not be matched to this order\'s payment or does not fit what is left to refund. Nothing was recorded: check it in the %1$s dashboard.', 'mizzey-site'),
                $event->provider,
                self::money($event->amountCents, $event->currency),
                $event->reference
            ), 'refund' . '-' . $event->key());
            self::stop($channel, 200, 'recorded', $order->get_id(), 'refund_not_matched', $order);
            return;
        }
        $refund = wc_create_refund([
            'order_id'              => $order->get_id(),
            'amount'                => $event->amountCents / 100,
            /* translators: 1: payment provider, 2: provider's refund reference. */
            'reason'                => sprintf(__('Refund made in the %1$s dashboard (refund %2$s)', 'mizzey-site'), $event->provider, $event->reference),
            'refund_payment'        => false,
            'restock_items'         => false,
            // The refund was made at the provider: its record says so (specs/029).
            'mizzey_from_dashboard' => true,
        ]);
        if (is_wp_error($refund)) {
            PaymentRecord::flag($order, sprintf(__('%1$s reported a refund of %2$s (reference %3$s) that could not be recorded. Check the order\'s refunds.', 'mizzey-site'), $event->provider, self::money($event->amountCents, $event->currency), $event->reference));
            self::stop($channel, 200, 'recorded', $order->get_id(), 'refund_not_recorded', $order);
            return;
        }
        RefundLedger::record($order, $event->reference);
        self::stop($channel, 200, 'refund recorded', $order->get_id(), '', $order);
    }

    /**
     * The provider's running total of what was refunded on the payment, which it does not sign: compared with the
     * order's refunds, never written. Less at the provider than on the order is flagged for staff; more is a refund
     * whose own notice has not arrived yet. Only for the payment that paid the order.
     */
    private static function compareRefunds(\WC_Order $order, PaymentEvent $event, string $channel): void
    {
        $paidBy = (string) $order->get_meta(self::PROVIDER_ORDER, true);
        if (self::$foreign || $paidBy === '' || $paidBy !== (string) ($event->signed['provider_order'] ?? '')) {
            self::stop($channel, 200, 'recorded', $order->get_id(), '', $order);
            return;
        }
        $atProvider = $event->amountCents;
        $onOrder    = (int) round((float) $order->get_total_refunded() * 100);
        if ($atProvider < $onOrder) {
            PaymentRecord::flag($order, sprintf(__('%1$s reports %2$s refunded but the order records %3$s. Check the order\'s refunds.', 'mizzey-site'), $event->provider, self::money($atProvider, $event->currency), self::money($onOrder, $event->currency)), 'below' . '-' . $event->key());
        }
        self::stop($channel, 200, $atProvider === $onOrder ? 'refunds match' : ($atProvider > $onOrder ? 'refund awaited' : 'refunds differ'), $order->get_id(), '', $order);
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
            // A refusal that means every payment will be refused (no secret stored, or a signature that does not verify,
            // as after a secret is changed at Paymob) is an error, raised to operations by the monitor (NFR-10); the
            // others (an unsigned or altered request, a repeat) are warnings.
            $level = in_array($reason, self::URGENT, true) ? 'error' : 'warning';
            wc_get_logger()->log($level, sprintf('%s %s: %s (order %d)', $channel, $word, $reason, $orderId), ['source' => self::LOG_SOURCE]);
        }
        $location = '';
        if ($channel === 'return') {
            if ($order instanceof \WC_Order && $status < 400 && ! self::$foreign) {
                $location = $order->get_checkout_order_received_url();
            } elseif ($order instanceof \WC_Order && $status < 400) {
                $location = wc_get_page_permalink('myaccount');
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

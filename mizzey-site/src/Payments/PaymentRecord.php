<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments;

defined('ABSPATH') || exit;

/**
 * The payment record kept against an order (PAY-06): each verified event once, with the provider's reference, kind,
 * outcome, amount, currency and times, and the items staff must look at. Shown on the order screen, where it also says
 * that paid is not the same as received (PAY-19). Nothing here is a card field (PAY-02).
 *
 * Register ids: PAY-06, PAY-19. See specs/028-payments.
 */
final class PaymentRecord
{
    public const EVENTS    = '_mizzey_payment_events';
    public const ATTENTION = '_mizzey_payment_attention';
    /** One row per event key, so a provider reference can be looked up across every order. */
    public const REFERENCE = '_mizzey_payment_ref';

    public static function register(): void
    {
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'panel'], 30, 1);
    }

    /** @return list<array<string,int|string>> */
    public static function events(\WC_Order $order): array
    {
        $events = $order->get_meta(self::EVENTS, true);

        return is_array($events) ? array_values($events) : [];
    }

    /** @return list<array{at:int,message:string}> */
    public static function attention(\WC_Order $order): array
    {
        $items = $order->get_meta(self::ATTENTION, true);

        return is_array($items) ? array_values($items) : [];
    }

    public static function has(\WC_Order $order, string $key): bool
    {
        foreach (self::events($order) as $event) {
            if ($event['provider'] . ':' . $event['kind'] . ':' . $event['reference'] === $key) {
                return true;
            }
        }

        return false;
    }

    /** A successful payment recorded on the order other than the one named. */
    public static function otherPayment(\WC_Order $order, string $key): ?array
    {
        foreach (self::events($order) as $event) {
            $eventKey = $event['provider'] . ':' . $event['kind'] . ':' . $event['reference'];
            if ($event['kind'] === PaymentEvent::PAYMENT && $event['outcome'] === PaymentEvent::SUCCESS && $eventKey !== $key) {
                return $event;
            }
        }

        return null;
    }

    /** The order that already holds an event, or 0. */
    public static function ownerOf(string $key): int
    {
        $ids = wc_get_orders([
            'type'       => 'shop_order',
            'status'     => 'any',
            'limit'      => 1,
            'return'     => 'ids',
            'meta_query' => [['key' => self::REFERENCE, 'value' => $key]],
        ]);

        return is_array($ids) && $ids !== [] ? (int) $ids[0] : 0;
    }

    public static function add(\WC_Order $order, PaymentEvent $event): void
    {
        if (self::has($order, $event->key())) {
            return;
        }
        $events   = self::events($order);
        $events[] = $event->toArray(time());
        $order->update_meta_data(self::EVENTS, $events);
        $order->add_meta_data(self::REFERENCE, $event->key(), false);
        $order->save();
    }

    /** A flag for staff, once per `$key` when one is given (a repeated event is not flagged again). */
    public static function flag(\WC_Order $order, string $message, string $key = ''): void
    {
        $items = self::attention($order);
        if ($key !== '' && in_array($key, array_column($items, 'key'), true)) {
            return;
        }
        $items[] = ['at' => time(), 'message' => $message] + ($key !== '' ? ['key' => $key] : []);
        $order->update_meta_data(self::ATTENTION, $items);
        $order->update_meta_data(self::ATTENTION . '_at', time()); // The latest flag's time, for the dashboard's query.
        $order->save();
        $order->add_order_note($message);
    }

    /** The record on the order screen. Staff screens are in English (ADM-159). */
    public static function panel($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }
        $events    = self::events($order);
        $attention = self::attention($order);
        if ($events === [] && $attention === []) {
            return;
        }
        echo '<div class="mizzey-payment-record" style="clear:both;padding-top:12px">';
        echo '<h3>' . esc_html__('Payment record', 'mizzey-site') . '</h3>';
        foreach ($attention as $item) {
            echo '<p class="mizzey-payment-attention" style="border-left:4px solid #d63638;padding:4px 8px;background:#fcf0f1">'
                . esc_html($item['message']) . '</p>';
        }
        if ($events !== []) {
            echo '<table class="widefat striped"><thead><tr>';
            foreach ([__('Received', 'mizzey-site'), __('Event', 'mizzey-site'), __('Outcome', 'mizzey-site'), __('Amount', 'mizzey-site'), __('Reference', 'mizzey-site'), __('Via', 'mizzey-site')] as $heading) {
                echo '<th>' . esc_html($heading) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($events as $event) {
                echo '<tr><td>' . esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $event['received_at']))
                    . '</td><td>' . esc_html($event['provider'] . ' ' . $event['kind'])
                    . '</td><td>' . esc_html((string) $event['outcome'])
                    . '</td><td>' . esc_html(number_format(((int) $event['amount']) / 100, 2) . ' ' . $event['currency'])
                    . '</td><td>' . esc_html((string) $event['reference'])
                    . '</td><td>' . esc_html((string) $event['channel']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p class="description">' . esc_html__('Paid means the payment provider approved the payment. The money reaches the store\'s account when the provider settles it, not at this time (see the settlement note).', 'mizzey-site') . '</p>';
        echo '</div>';
    }
}

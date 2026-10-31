<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Checkout;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MizzeySite\Stock\StockAnswer;
use MizzeySite\Stock\StockGateway;

defined('ABSPATH') || exit;

/**
 * Stock at order placement goes through the adapter (CHK-12, D-19 item 5, the Stage 3 build plan's stock safeguard).
 * WooCommerce's own order stock writers are switched off by LocalStockWriters (specs/018).
 *
 * The order keeps a reservation record, one entry per line: the stock record and quantity reserved, the generation of
 * its operation keys, and its state (reserved, undone, released, refused, unsure when a reserve went unanswered, or
 * unsettled when an undo or a release went unanswered).
 * Releases use the record, not the order's current lines, so an order edited after placement releases what was taken.
 *
 * - Placement, on the block checkout, the classic checkout and pay-for-order: each line not already reserved is
 *   reserved with key `order-{id}-item-{item}-g{generation}-reserve`. A retried placement re-sends the same keys, so the
 *   source applies each once. If any line is refused or unanswered, the lines reserved in this attempt are undone, the
 *   placement is refused before payment (a 409 on the Store API; a message on the classic checkout and pay-for-order),
 *   and the coupons it held are released. A line undone, or released, moves to a new generation, so a later placement
 *   asks afresh; a line whose answer never came keeps its keys, so asking again cannot take its stock twice.
 * - An order that reaches a paid status without a reservation (a payment callback, staff) is reserved then; if the
 *   source refuses, the order is marked (`UNRESERVED`) with a note for staff, as a payment already taken cannot be
 *   refused here, and the status model (specs/010) decides where it may move next.
 * - A cancelled or failed order, and an unpaid order trashed or deleted, releases what it reserved, once.
 * - A line whose undo or release went unanswered stays marked unsettled for reconciliation with the source (PRE-09);
 *   a later placement asks for it with new keys.
 *
 * Register ids: CHK-12. See specs/019-checkout.
 */
final class OrderStock
{
    public const RECORD     = '_mizzey_stock_reservation';
    /** When a paid order could not be reserved, for staff to check before fulfilling it. */
    public const UNRESERVED = '_mizzey_stock_unreserved';

    /** Paid or fulfilled statuses: an order there should hold a reservation. */
    private const HOLDING = ['processing', 'on-hold', 'preparing', 'shipped', 'out-for-delivery', 'cod-collected', 'completed'];
    /** Unpaid statuses an order may be trashed or deleted from, releasing what it reserved. */
    private const UNPAID = ['pending', 'failed', 'checkout-draft', 'cancelled'];

    public static function register(): void
    {
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'placedThroughStoreApi'], 0, 1);
        add_action('woocommerce_checkout_order_processed', [self::class, 'placedThroughClassic'], 0, 3);
        add_action('woocommerce_before_pay_action', [self::class, 'beforePay'], 0, 1);
        add_action('woocommerce_order_status_changed', [self::class, 'statusChanged'], 20, 4);
        add_action('woocommerce_before_trash_order', [self::class, 'beforeRemoval'], 10, 1);
        add_action('woocommerce_before_delete_order', [self::class, 'beforeRemoval'], 10, 1);
        add_filter('mizzey_cart_stock_held', [self::class, 'heldByVisitor'], 10, 1);
    }

    public static function message(): string
    {
        return __('We could not reserve the stock for your order. Please try again in a few minutes.', 'mizzey-site');
    }

    /**
     * @param mixed $order
     * @throws RouteException When the stock cannot be reserved.
     */
    public static function placedThroughStoreApi($order): void
    {
        if ($order instanceof \WC_Order && ! self::reserve($order, StockGateway::make())) {
            wc_release_coupons_for_order($order);
            throw new RouteException('mizzey_stock_not_reserved', esc_html(self::message()), 409);
        }
    }

    /**
     * @param mixed $orderId
     * @param mixed $posted
     * @param mixed $order
     * @throws \Exception When the stock cannot be reserved; the classic checkout shows the message.
     */
    public static function placedThroughClassic($orderId, $posted, $order): void
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if ($order instanceof \WC_Order && ! self::reserve($order, StockGateway::make())) {
            wc_release_coupons_for_order($order);
            throw new \Exception(esc_html(self::message()));
        }
    }

    /**
     * Pay-for-order: the order is reserved before the gateway is asked to charge it.
     *
     * @param mixed $order
     */
    public static function beforePay($order): void
    {
        if ($order instanceof \WC_Order && ! self::reserve($order, StockGateway::make())) {
            wc_add_notice(self::message(), 'error');
            wp_safe_redirect($order->get_checkout_payment_url());
            exit;
        }
    }

    /**
     * @param mixed $orderId
     * @param mixed $from
     * @param mixed $to
     * @param mixed $order
     */
    public static function statusChanged($orderId, $from, $to, $order): void
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }
        $to = (string) $to;
        if (in_array($to, ['cancelled', 'failed'], true)) {
            self::release($order, StockGateway::make());

            return;
        }
        if (in_array($to, self::HOLDING, true) && ! self::active($order)) {
            if (self::reserve($order, StockGateway::make())) {
                $order->delete_meta_data(self::UNRESERVED);
                $order->save_meta_data();
            } elseif ($order->get_meta(self::UNRESERVED) === '') {
                $order->update_meta_data(self::UNRESERVED, (string) time());
                $order->save_meta_data();
                $order->add_order_note(__('The stock for this order could not be reserved with the stock source. Check it before fulfilling the order.', 'mizzey-site'));
            }
        }
    }

    /** @param mixed $orderId */
    public static function beforeRemoval($orderId): void
    {
        $order = wc_get_order((int) $orderId);
        if ($order instanceof \WC_Order && in_array($order->get_status(), self::UNPAID, true)) {
            self::release($order, StockGateway::make());
        }
    }

    /** Whether every line of the order holds a reservation now. */
    public static function active(\WC_Order $order): bool
    {
        $record = self::record($order);
        if ($record === []) {
            return false;
        }
        foreach ($record as $line) {
            if ($line['state'] !== 'reserved') {
                return false;
            }
        }

        return true;
    }

    /** Reserves every line not already reserved; on any failure, undoes this attempt's reservations and answers false. */
    public static function reserve(\WC_Order $order, StockGateway $gateway): bool
    {
        $record = self::plan($order);
        $taken  = [];
        $ok     = true;
        foreach ($record as $itemId => $line) {
            if ($line['state'] === 'reserved') {
                continue;
            }
            $answer = $gateway->adjust($line['record'], -$line['qty'], self::key($order, $itemId, $line['gen'], 'reserve'));
            if ($answer->isKnown()) {
                $record[$itemId]['state'] = 'reserved';
                $taken[]                  = $itemId;
                continue;
            }
            $record[$itemId]['state'] = self::refused($answer) ? 'refused' : 'unsure';
            $ok                       = false;
            break;
        }
        if (! $ok) {
            foreach ($taken as $itemId) {
                $line   = $record[$itemId];
                $undone = $gateway->adjust($line['record'], $line['qty'], self::key($order, $itemId, $line['gen'], 'undo'))->isKnown();
                $record[$itemId]['state'] = $undone ? 'undone' : 'unsettled';
            }
        }
        self::save($order, $record);

        return $ok;
    }

    /** Releases the reserved lines of the record once; answers whether nothing reserved remains. */
    public static function release(\WC_Order $order, StockGateway $gateway): bool
    {
        $record = self::record($order);
        $all    = true;
        foreach ($record as $itemId => $line) {
            if ($line['state'] !== 'reserved') {
                continue;
            }
            $released = $gateway->adjust($line['record'], $line['qty'], self::key($order, $itemId, $line['gen'], 'release'))->isKnown();
            $record[$itemId]['state'] = $released ? 'released' : 'unsettled';
            $all                      = $all && $released;
        }
        self::save($order, $record);

        return $all;
    }

    /**
     * What the visitor's own unpaid order holds, by stock record, so a retried checkout is not refused for the stock it
     * already reserved (CartStock adds it back).
     *
     * @param mixed $held
     * @return mixed
     */
    public static function heldByVisitor($held)
    {
        if (! is_array($held) || ! function_exists('WC') || ! WC()->session) {
            return $held;
        }
        foreach (['store_api_draft_order', 'order_awaiting_payment'] as $key) {
            $order = wc_get_order((int) WC()->session->get($key));
            if (! $order instanceof \WC_Order || ! in_array($order->get_status(), ['pending', 'failed', 'checkout-draft'], true)) {
                continue;
            }
            foreach (self::record($order) as $line) {
                if ($line['state'] === 'reserved') {
                    $held[$line['record']] = ($held[$line['record']] ?? 0) + $line['qty'];
                }
            }
        }

        return $held;
    }

    /**
     * The record for a placement: the order's current lines, each keeping its state and generation when it is the same
     * line with the same stock and quantity; a line undone or released before asks with a new generation.
     *
     * @return array<int,array{record:int,qty:int,gen:int,state:string}>
     */
    private static function plan(\WC_Order $order): array
    {
        $before = self::record($order);
        $plan   = [];
        foreach ($order->get_items() as $itemId => $item) {
            if (! $item instanceof \WC_Order_Item_Product || (int) $item->get_quantity() <= 0) {
                continue;
            }
            $record = (int) ($item->get_variation_id() ?: $item->get_product_id());
            $qty    = (int) $item->get_quantity();
            $old    = $before[(int) $itemId] ?? null;
            $same   = $old !== null && $old['record'] === $record && $old['qty'] === $qty;
            $gen    = $old !== null ? $old['gen'] : 0;
            $state  = 'new';
            if ($same && in_array($old['state'], ['reserved', 'unsure'], true)) {
                // Reserved stays reserved; unsure asks again with the same key, which the source applies once.
                $state = $old['state'] === 'reserved' ? 'reserved' : 'new';
            } elseif ($old !== null) {
                $gen++;
            }
            $plan[(int) $itemId] = ['record' => $record, 'qty' => $qty, 'gen' => $gen, 'state' => $state];
        }

        return $plan;
    }

    private static function refused(StockAnswer $answer): bool
    {
        return $answer->reason === 'refused';
    }

    /** @return array<int,array{record:int,qty:int,gen:int,state:string}> */
    public static function record(\WC_Order $order): array
    {
        $record = $order->get_meta(self::RECORD);

        return is_array($record) ? $record : [];
    }

    /** @param array<int,array{record:int,qty:int,gen:int,state:string}> $record */
    private static function save(\WC_Order $order, array $record): void
    {
        $order->update_meta_data(self::RECORD, $record);
        $order->save_meta_data();
    }

    private static function key(\WC_Order $order, int $itemId, int $gen, string $step): string
    {
        return 'order-' . $order->get_id() . '-item-' . $itemId . '-g' . $gen . '-' . $step;
    }
}

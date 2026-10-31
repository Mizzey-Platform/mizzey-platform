<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Checkout;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MizzeySite\Stock\StockGateway;

defined('ABSPATH') || exit;

/**
 * Stock at order placement goes through the adapter, and no WooCommerce order path changes stock on its own (CHK-12,
 * D-19 item 5, the Stage 3 build plan's stock safeguard).
 *
 * WooCommerce's own order stock writers are switched off: the checkout hold (`woocommerce_hold_stock_for_checkout`),
 * the reduction at payment or placement (`woocommerce_can_reduce_order_stock`,
 * `woocommerce_payment_complete_reduce_order_stock`), the restoration on cancellation
 * (`woocommerce_can_restore_order_stock`), refund restocking (`woocommerce_can_restock_refunded_items`) and the
 * adjustment when an order line is edited (`woocommerce_prevent_adjust_line_item_product_stock`).
 *
 * Instead, when an order is placed (block checkout and classic checkout), each line is reserved with the stock source
 * through the gateway, with an operation key per order line, so a retried placement reserves once. If any line cannot
 * be reserved (the source refuses, or does not answer), the lines already reserved are released and the placement is
 * refused before payment, with a message. A cancelled or failed order releases its reservation, once. How the ERP
 * holds and confirms a reservation is ERP-04's mechanism (PRE-09); a release the source does not answer is logged by the
 * gateway and stays for reconciliation.
 *
 * Register ids: CHK-12. See specs/019-checkout.
 */
final class OrderStock
{
    public const RESERVED = '_mizzey_stock_reserved';
    public const RELEASED = '_mizzey_stock_released';
    /** Raised after a refused placement is undone, so the next placement asks with new keys. */
    public const ATTEMPT  = '_mizzey_stock_attempt';

    public static function register(): void
    {
        foreach (['woocommerce_hold_stock_for_checkout', 'woocommerce_can_reduce_order_stock', 'woocommerce_payment_complete_reduce_order_stock', 'woocommerce_can_restore_order_stock', 'woocommerce_can_restock_refunded_items'] as $filter) {
            add_filter($filter, '__return_false', 999);
        }
        add_filter('woocommerce_prevent_adjust_line_item_product_stock', '__return_true', 999);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'placedThroughStoreApi'], 0, 1);
        add_action('woocommerce_checkout_order_processed', [self::class, 'placedThroughClassic'], 0, 3);
        add_action('woocommerce_order_status_cancelled', [self::class, 'released'], 10, 1);
        add_action('woocommerce_order_status_failed', [self::class, 'released'], 10, 1);
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
            throw new \Exception(esc_html(self::message()));
        }
    }

    /** Reserves every line once; on any failure, releases what it reserved and answers false. */
    public static function reserve(\WC_Order $order, StockGateway $gateway): bool
    {
        if ($order->get_meta(self::RESERVED) === 'yes') {
            return true;
        }
        // A placement interrupted midway asks again with the same keys, so nothing is reserved twice; one refused and
        // undone asks next time with new keys, since the source already holds the old ones as applied.
        $attempt = (int) $order->get_meta(self::ATTEMPT);
        $done    = [];
        foreach (self::lines($order) as $itemId => [$record, $quantity]) {
            if (! $gateway->adjust($record, -$quantity, self::key($order, $itemId, 'reserve-' . $attempt))->isKnown()) {
                foreach ($done as $doneId => [$doneRecord, $doneQuantity]) {
                    $gateway->adjust($doneRecord, $doneQuantity, self::key($order, $doneId, 'undo-' . $attempt));
                }
                $order->update_meta_data(self::ATTEMPT, (string) ($attempt + 1));
                $order->save_meta_data();

                return false;
            }
            $done[$itemId] = [$record, $quantity];
        }
        $order->update_meta_data(self::RESERVED, 'yes');
        $order->save_meta_data();

        return true;
    }

    /** @param mixed $orderId */
    public static function released($orderId): void
    {
        $order = wc_get_order((int) $orderId);
        if ($order instanceof \WC_Order) {
            self::release($order, StockGateway::make());
        }
    }

    /** Releases a reserved order's lines once; answers whether every line was released. */
    public static function release(\WC_Order $order, StockGateway $gateway): bool
    {
        if ($order->get_meta(self::RESERVED) !== 'yes' || $order->get_meta(self::RELEASED) === 'yes') {
            return true;
        }
        $all = true;
        foreach (self::lines($order) as $itemId => [$record, $quantity]) {
            $all = $gateway->adjust($record, $quantity, self::key($order, $itemId, 'release'))->isKnown() && $all;
        }
        if ($all) {
            $order->update_meta_data(self::RELEASED, 'yes');
            $order->save_meta_data();
        }

        return $all;
    }

    /** @return array<int,array{0:int,1:int}> Order item id => [product or variation record, quantity]. */
    private static function lines(\WC_Order $order): array
    {
        $lines = [];
        foreach ($order->get_items() as $itemId => $item) {
            if ($item instanceof \WC_Order_Item_Product && (int) $item->get_quantity() > 0) {
                $lines[(int) $itemId] = [(int) ($item->get_variation_id() ?: $item->get_product_id()), (int) $item->get_quantity()];
            }
        }

        return $lines;
    }

    private static function key(\WC_Order $order, int $itemId, string $step): string
    {
        return 'order-' . $order->get_id() . '-item-' . $itemId . '-' . $step;
    }
}

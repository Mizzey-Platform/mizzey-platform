<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

defined('ABSPATH') || exit;

/**
 * The contracted order status model: which statuses exist, what each is called, which moves are allowed, which
 * statuses mean delivered, and where the first entry into each status is recorded.
 *
 * Statuses are WooCommerce's keys without the `wc-` prefix. WordPress stores a status in a 20-character field with
 * that prefix, so a key is at most 17 characters.
 *
 * Register ids: ORD-01 to ORD-08, ORD-12, ORD-13, ORD-14, ADM-87. See specs/010-order-status-model.
 */
final class StatusModel
{
    public const PREPARING        = 'preparing';
    public const SHIPPED          = 'shipped';
    public const OUT_FOR_DELIVERY = 'out-for-delivery';
    public const COD_COLLECTED    = 'cod-collected';
    public const DELIVERY_FAILED  = 'delivery-failed';

    /** A new order, or a draft (the block checkout's or the admin's), before it has a real status. */
    public const STARTING = ['', 'new', 'auto-draft', 'draft', 'checkout-draft'];

    /** The statuses an order may start with: WooCommerce first stores a new order as Pending Payment. */
    public const INITIAL = ['pending', 'processing', 'on-hold', 'failed', 'cancelled'];

    /** Delivered (ORD-07) and COD Collected (ORD-14, entered only once delivery happened). */
    public const DELIVERED = ['completed', self::COD_COLLECTED];

    /** New statuses that follow payment, so WooCommerce counts them as paid. Delivery Failed is not one. */
    public const PAID = [self::PREPARING, self::SHIPPED, self::OUT_FOR_DELIVERY, self::COD_COLLECTED];

    public const ENTERED_PREFIX   = '_mizzey_status_entered_';
    public const REASON_META      = '_mizzey_cancellation_reason';
    public const CANCELLED_BY_META = '_mizzey_cancelled_by';

    /** Allowed moves, from => to. A full refund reaches Refunded from every paid status. */
    private const TRANSITIONS = [
        'pending'               => ['processing', 'on-hold', 'failed', 'cancelled'],
        'failed'                => ['pending', 'processing', 'on-hold', 'cancelled'],
        'on-hold'               => ['processing', 'failed', 'cancelled', 'refunded'],
        'processing'            => [self::PREPARING, 'cancelled', 'refunded'],
        self::PREPARING         => [self::SHIPPED, 'processing', 'cancelled', 'refunded'],
        self::SHIPPED           => [self::OUT_FOR_DELIVERY, 'completed', self::COD_COLLECTED, self::DELIVERY_FAILED, 'refunded'],
        self::OUT_FOR_DELIVERY  => ['completed', self::COD_COLLECTED, self::DELIVERY_FAILED, self::SHIPPED, 'refunded'],
        self::COD_COLLECTED     => ['completed', 'refunded'],
        'completed'             => ['refunded'],
        self::DELIVERY_FAILED   => ['refunded'],
        // A payment that arrives after cancellation revives the order, as WooCommerce's own payment flow does; a paid
        // cancelled order can still be refunded in full.
        'cancelled'             => ['processing', 'refunded'],
        'refunded'              => [],
    ];

    /**
     * The new statuses with their register names, in model order.
     *
     * @return array<string,string>
     */
    public static function added(): array
    {
        return [
            self::PREPARING        => __('Preparing', 'mizzey-site'),
            self::SHIPPED          => __('Shipped', 'mizzey-site'),
            self::OUT_FOR_DELIVERY => __('Out for Delivery', 'mizzey-site'),
            self::COD_COLLECTED    => __('COD Collected, Awaiting Remittance', 'mizzey-site'),
            self::DELIVERY_FAILED  => __('Delivery Failed / Returned to Origin', 'mizzey-site'),
        ];
    }

    /**
     * WooCommerce statuses named for the register: Processing is Confirmed (ORD-03), Completed is Delivered (ORD-07).
     *
     * @return array<string,string>
     */
    public static function renamed(): array
    {
        return [
            'pending'    => __('Pending Payment', 'mizzey-site'),
            'failed'     => __('Payment Failed', 'mizzey-site'),
            'processing' => __('Confirmed', 'mizzey-site'),
            'completed'  => __('Delivered', 'mizzey-site'),
        ];
    }

    /** Whether the order is paid in cash on delivery. */
    public static function isCash(\WC_Order $order): bool
    {
        return $order->get_payment_method() === 'cod';
    }

    /**
     * The owner's cash route (30 October 2026): a cash order reaches Delivered only through COD Collected, and COD
     * Collected is for cash orders only. Null when the move fits the route.
     */
    public static function routeProblem(\WC_Order $order, string $from, string $to): ?string
    {
        $from = self::bare($from);
        $to   = self::bare($to);
        if ($to === 'completed' && self::isCash($order) && $from !== self::COD_COLLECTED) {
            return __('Order status not changed: a cash on delivery order reaches Delivered through COD Collected, when the remittance is recorded.', 'mizzey-site');
        }
        if ($to === self::COD_COLLECTED && ! self::isCash($order)) {
            return __('Order status not changed: COD Collected is only for cash on delivery orders.', 'mizzey-site');
        }

        return null;
    }

    /** Whether the table allows the move. Into an unknown status: never. From an unknown status: to a known one. */
    public static function allowed(string $from, string $to): bool
    {
        $from = self::bare($from);
        $to   = self::bare($to);
        if ($from === $to) {
            return true;
        }
        if (in_array($from, self::STARTING, true)) {
            return in_array($to, self::INITIAL, true) || in_array($to, self::STARTING, true);
        }
        if (! array_key_exists($to, self::TRANSITIONS)) {
            return false;
        }
        if (! array_key_exists($from, self::TRANSITIONS)) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$from], true);
    }

    /**
     * Where the order may go from its status, for messages.
     *
     * @return list<string>
     */
    public static function next(string $from): array
    {
        $from = self::bare($from);
        if (in_array($from, self::STARTING, true)) {
            return self::INITIAL;
        }

        return self::TRANSITIONS[$from] ?? array_keys(self::TRANSITIONS);
    }

    public static function isDelivered(\WC_Order $order): bool
    {
        return in_array($order->get_status(), self::DELIVERED, true);
    }

    public static function metaKey(string $status): string
    {
        return self::ENTERED_PREFIX . self::bare($status);
    }

    /** When the order first entered the status, as a UTC timestamp, or null if it never did since this was recorded. */
    public static function enteredAt(\WC_Order $order, string $status): ?int
    {
        $value = $order->get_meta(self::metaKey($status), true);

        return is_numeric($value) ? (int) $value : null;
    }

    /** When delivery was first established: the earlier of entering COD Collected and entering Delivered. */
    public static function deliveredAt(\WC_Order $order): ?int
    {
        $times = array_filter(array_map(static fn (string $status): ?int => self::enteredAt($order, $status), self::DELIVERED));

        return $times === [] ? null : min($times);
    }

    /** The status's register name, for messages. */
    public static function name(string $status): string
    {
        return function_exists('wc_get_order_status_name') ? wc_get_order_status_name(self::bare($status)) : self::bare($status);
    }

    public static function bare(string $status): string
    {
        return str_starts_with($status, 'wc-') ? substr($status, 3) : $status;
    }
}

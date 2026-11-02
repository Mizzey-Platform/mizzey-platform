<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

defined('ABSPATH') || exit;

/**
 * A refund never exceeds the amount actually paid for that item or order after discounts (BR-006). WooCommerce refuses
 * a refund above what remains of the order (`wc_create_refund()`, P-014), but lets one line be refunded above what was
 * paid for it as long as the order total holds. Before WooCommerce makes the refund, each line it names is checked:
 * what it returns, with its tax, may not exceed the line's total after discounts, with its tax, less what earlier
 * refunds already returned for it.
 *
 * Register ids: BR-006. See specs/016-launch-rules.
 */
final class RefundIntegrity
{
    /** Half a cent of rounding between WooCommerce's stored totals and the amounts a refund names. */
    private const TOLERANCE = 0.005;

    public static function register(): void
    {
        add_action('woocommerce_create_refund', [self::class, 'check'], 1, 2);
    }

    /**
     * @param mixed $refund
     * @param mixed $args
     * @throws \Exception When a line would get back more than was paid for it; `wc_create_refund()` returns it as its error.
     */
    public static function check($refund, $args): void
    {
        $order = is_array($args) ? wc_get_order((int) ($args['order_id'] ?? 0)) : null;
        $lines = is_array($args) ? (array) ($args['line_items'] ?? []) : [];
        if (! $order instanceof \WC_Order || $lines === []) {
            return;
        }
        foreach ($lines as $itemId => $line) {
            $item = $order->get_item((int) $itemId);
            if (! $item instanceof \WC_Order_Item) {
                continue;
            }
            $asked = abs((float) ($line['refund_total'] ?? 0)) + array_sum(array_map(static fn ($t) => abs((float) $t), (array) ($line['refund_tax'] ?? [])));
            $paid  = (float) $item->get_total() + (float) $item->get_total_tax();
            $skip = $refund instanceof \WC_Order_Refund ? (int) $refund->get_id() : 0;
            if ($asked > $paid - self::refunded($order, (int) $itemId, $skip) + self::TOLERANCE) {
                throw new \Exception(esc_html__('A refund cannot exceed what was paid for an item.', 'mizzey-site'));
            }
        }
    }

    /**
     * What earlier refunds returned for one line of the order, with its tax. The refund being made is left out:
     * WooCommerce has saved it once by the time it asks (`calculate_totals()` inside `wc_create_refund()`).
     */
    private static function refunded(\WC_Order $order, int $itemId, int $skip): float
    {
        $back = 0.0;
        foreach ($order->get_refunds() as $refund) {
            if ($skip > 0 && (int) $refund->get_id() === $skip) {
                continue;
            }
            foreach ($refund->get_items(['line_item', 'shipping', 'fee']) as $refunded) {
                if ((int) $refunded->get_meta('_refunded_item_id', true) === $itemId) {
                    $back += abs((float) $refunded->get_total()) + abs((float) $refunded->get_total_tax());
                }
            }
        }

        return $back;
    }
}

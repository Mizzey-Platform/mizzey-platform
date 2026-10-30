<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

defined('ABSPATH') || exit;

/**
 * The cancellation reason on the order screen, and its line in the order timeline. The admin is English only
 * (ADM-159).
 *
 * Register id: ORD-08. See specs/010-order-status-model, FR-003.
 */
final class CancellationField
{
    public const FIELD = 'mizzey_cancellation_reason';

    public static function register(): void
    {
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'render']);
        add_action('woocommerce_order_status_cancelled', [self::class, 'note'], 10, 2);
    }

    public static function render(\WC_Order $order): void
    {
        $stored = (string) $order->get_meta(StatusModel::REASON_META, true);
        echo '<p class="form-field form-field-wide mizzey-cancellation-reason">';
        if ($order->get_status() === 'cancelled') {
            printf(
                '<strong>%s</strong> %s',
                esc_html__('Cancellation reason:', 'mizzey-site'),
                esc_html($stored)
            );
        } else {
            printf(
                '<label for="%1$s">%2$s</label><textarea id="%1$s" name="%1$s" rows="2" style="width:100%%"></textarea>',
                esc_attr(self::FIELD),
                esc_html__('Cancellation reason (required to cancel)', 'mizzey-site')
            );
        }
        echo '</p>';
    }

    /**
     * @param \WC_Order|null $order
     */
    public static function note(int $orderId, $order = null): void
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }
        $reason = (string) $order->get_meta(StatusModel::REASON_META, true);
        if ($reason === '') {
            return;
        }
        $byUser = (int) $order->get_meta(StatusModel::CANCELLED_BY_META, true) > 0;
        /* translators: %s: the reason given. */
        $order->add_order_note(sprintf(__('Cancellation reason: %s', 'mizzey-site'), $reason), 0, $byUser);
    }
}

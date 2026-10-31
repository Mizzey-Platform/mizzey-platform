<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Invoices;

use MizzeySite\Reviews\StoredOrderMeta;

defined('ABSPATH') || exit;

/**
 * An order's invoice number and date (ADM-92): issued once, the first time the order reaches a paid status, or the first
 * time its invoice is asked for if it was paid before invoices existed; never for an unpaid, failed or cancelled order.
 * Numbers run in sequence from one counter, under a database lock, so two orders paid at once never share a number;
 * the prefix is a setting (InvoiceSettings).
 *
 * See specs/023-invoice.
 */
final class InvoiceNumber
{
    public const NUMBER  = '_mizzey_invoice_number';
    public const ISSUED  = '_mizzey_invoice_issued';
    public const COUNTER = 'mizzey_invoice_next';

    public static function register(): void
    {
        add_action('woocommerce_order_status_changed', [self::class, 'statusChanged'], 30, 4);
    }

    /**
     * @param mixed $orderId
     * @param mixed $from
     * @param mixed $to
     * @param mixed $order
     */
    public static function statusChanged($orderId, $from, $to, $order): void
    {
        if (in_array((string) $to, self::paidStatuses(), true)) {
            self::issue((int) $orderId);
        }
    }

    /** Whether the order may have an invoice: it is paid, or it had one issued before (a refunded order keeps it). */
    public static function issuable(\WC_Order $order): bool
    {
        return in_array($order->get_status(), self::paidStatuses(), true) || self::of((int) $order->get_id()) !== '';
    }

    /** The order's invoice number, or '' when none was issued. */
    public static function of(int $orderId): string
    {
        return StoredOrderMeta::get($orderId, self::NUMBER);
    }

    /** Issues the order's invoice number once and answers it, or '' for an order that may not have one. */
    public static function issue(int $orderId): string
    {
        global $wpdb;
        $known = self::of($orderId);
        if ($known !== '') {
            return $known;
        }
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order || ! in_array($order->get_status(), self::paidStatuses(), true)) {
            return '';
        }
        $lock = substr('mz_' . $wpdb->prefix . 'invoice_number', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return '';
        }
        try {
            // Read again under the lock, from the database: another request may have issued it meanwhile.
            $known = self::of($orderId);
            if ($known !== '') {
                return $known;
            }
            $next   = max(1, (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::COUNTER))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $number = InvoiceSettings::prefix() . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            update_option(self::COUNTER, (string) ($next + 1), false);
            $order->update_meta_data(self::NUMBER, $number);
            $order->update_meta_data(self::ISSUED, (string) time());
            $order->save_meta_data();

            return $number;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** When the invoice was issued (a Unix time), or null. */
    public static function issuedAt(int $orderId): ?int
    {
        $at = StoredOrderMeta::get($orderId, self::ISSUED);

        return $at !== '' ? (int) $at : null;
    }

    /** @return list<string> WooCommerce's paid statuses, which the status model extends (specs/010). */
    private static function paidStatuses(): array
    {
        return array_values(array_map('strval', (array) wc_get_is_paid_statuses()));
    }
}

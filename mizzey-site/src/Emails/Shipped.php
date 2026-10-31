<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Emails;

defined('ABSPATH') || exit;

/**
 * Sends the Shipped email (NOTF-04) once per order, when it first enters Shipped, in the order's language: WPML's
 * order language and the WordPress locale are switched around the email and restored after. A MySQL named lock per
 * order keeps two requests from sending it twice.
 *
 * The shipment the email shows is the order's: `_mizzey_carrier`, `_mizzey_tracking_number` and `_mizzey_tracking_url`,
 * which the carrier integration (#291) writes, or supplies through the `mizzey_order_shipment` filter.
 *
 * Register ids: NOTF-04. See specs/021-emails.
 */
final class Shipped
{
    public const SENT     = '_mizzey_shipped_emailed';
    public const CARRIER  = '_mizzey_carrier';
    public const TRACKING = '_mizzey_tracking_number';
    public const URL      = '_mizzey_tracking_url';

    public static function register(): void
    {
        add_filter('woocommerce_email_classes', [self::class, 'email']);
        add_action('woocommerce_order_status_shipped', [self::class, 'shipped'], 20, 2);
    }

    /**
     * @param mixed $emails
     * @return mixed
     */
    public static function email($emails)
    {
        if (is_array($emails) && class_exists('\WC_Email')) {
            require_once __DIR__ . '/ShippedEmail.php';
            $emails['MizzeySite_Customer_Shipped'] = new ShippedEmail();
        }

        return $emails;
    }

    /** @return array{carrier:string,tracking:string,url:string} */
    public static function shipment(\WC_Order $order): array
    {
        $shipment = [
            'carrier'  => (string) $order->get_meta(self::CARRIER),
            'tracking' => (string) $order->get_meta(self::TRACKING),
            'url'      => (string) $order->get_meta(self::URL),
        ];
        $filtered = apply_filters('mizzey_order_shipment', $shipment, $order);

        return is_array($filtered) ? array_map('strval', array_replace($shipment, array_intersect_key($filtered, $shipment))) : $shipment;
    }

    /**
     * @param mixed $orderId
     * @param mixed $order
     */
    public static function shipped($orderId, $order = null): void
    {
        global $wpdb;
        $id   = $order instanceof \WC_Order ? $order->get_id() : (int) $orderId;
        $lock = substr('mz_' . $wpdb->prefix . 'shipped_email_' . $id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        try {
            self::send($id);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    private static function send(int $id): void
    {
        // The mark is read from the database under the lock, not from a cache: another request may have sent the email
        // while this one waited with the order already loaded.
        if (\MizzeySite\Reviews\StoredOrderMeta::get($id, self::SENT) !== '') {
            return;
        }
        $order = wc_get_order($id);
        $email = WC()->mailer()->get_emails()['MizzeySite_Customer_Shipped'] ?? null;
        if (! $order instanceof \WC_Order || $order->get_meta(self::SENT) !== '' || ! $email instanceof ShippedEmail) {
            return;
        }
        $sent = OrderLanguage::during($order, static fn (): bool => $email->trigger($order));
        if ($sent) {
            $order->update_meta_data(self::SENT, (string) time());
            $order->save_meta_data();
        }
    }
}

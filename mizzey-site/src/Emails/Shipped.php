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
 * When the order entered Shipped before its carrier and tracking number were known, the email says the details follow,
 * and the order is marked as owing them (`OWED`). The first time both are known (staff attach the shipment, or the
 * carrier's update fills it) while the order is still on its way, a short tracking email keeps that promise, once.
 *
 * Register ids: NOTF-04. See specs/021-emails.
 */
final class Shipped
{
    public const SENT     = '_mizzey_shipped_emailed';
    public const OWED     = '_mizzey_shipped_tracking_owed';
    public const CARRIER  = '_mizzey_carrier';
    public const TRACKING = '_mizzey_tracking_number';
    public const URL      = '_mizzey_tracking_url';

    private static bool $following = false;

    public static function register(): void
    {
        add_filter('woocommerce_email_classes', [self::class, 'email']);
        add_action('woocommerce_order_status_shipped', [self::class, 'shipped'], 20, 2);
        add_action('woocommerce_after_order_object_save', [self::class, 'followUp'], 30, 1);
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
            $shipment = self::shipment($order);
            if ($shipment['carrier'] === '' || $shipment['tracking'] === '') {
                $order->update_meta_data(self::OWED, (string) time());
            }
            $order->save_meta_data();
        }
    }

    /**
     * The tracking email the Shipped email promised, once the carrier and tracking number are known. Heard after any
     * save of the order (the carrier's update) and called by the shipment box when staff attach the shipment.
     *
     * @param mixed $order
     */
    public static function followUp($order): void
    {
        if (self::$following || ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund || (string) $order->get_meta(self::OWED) === '') {
            return;
        }
        $shipment = self::shipment($order);
        if ($shipment['carrier'] === '' || $shipment['tracking'] === '') {
            return;
        }
        global $wpdb;
        $id   = $order->get_id();
        $lock = substr('mz_' . $wpdb->prefix . 'shipped_email_' . $id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        self::$following = true;
        try {
            // Read under the lock, from the database: another request may have sent it meanwhile.
            if (\MizzeySite\Reviews\StoredOrderMeta::get($id, self::OWED) === '') {
                return;
            }
            $fresh = wc_get_order($id);
            $email = WC()->mailer()->get_emails()['MizzeySite_Customer_Shipped'] ?? null;
            if (! $fresh instanceof \WC_Order || ! $email instanceof ShippedEmail) {
                return;
            }
            // Once the order is delivered, or no longer on its way, the details are no use: the promise lapses.
            $onTheWay = $fresh->has_status([\MizzeySite\Orders\StatusModel::SHIPPED, \MizzeySite\Orders\StatusModel::OUT_FOR_DELIVERY]);
            $sent     = $onTheWay && OrderLanguage::during($fresh, static fn (): bool => $email->trigger($fresh, true));
            if ($sent || ! $onTheWay) {
                $fresh->delete_meta_data(self::OWED);
                $fresh->save_meta_data();
                $order->delete_meta_data(self::OWED);
            }
        } finally {
            self::$following = false;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

defined('ABSPATH') || exit;

/**
 * Bosta, through its official WooCommerce plugin (SHIP-08, INT-02; D-24 keeps the carrier's own integration). The
 * plugin pushes the order to Bosta, keeps the tracking number (`bosta_tracking_number`) and writes Bosta's state code
 * and description from Bosta's webhook (`bosta_state_code`, `bosta_status`). Its own status mapping is switched off
 * in the plugin (P-012), so Mizzey's layer maps the codes (`CarrierStatus`).
 *
 * See specs/041-bosta.
 */
final class BostaCarrier implements Carrier
{
    public const TRACKING = 'bosta_tracking_number';
    public const CODE     = 'bosta_state_code';
    public const LABEL    = 'bosta_status';
    /** Bosta's plugin's delivery time for a delivered parcel. */
    public const DELIVERED = 'bosta_delivery_date';
    /** How Bosta's plugin writes it (bosta_format_date()). */
    private const DELIVERED_FORMAT = 'l,' . ' ' . 'd/m/Y' . ' ' . 'h:ia'; // "Monday, 02/11/2026 03:45pm"

    public function id(): string
    {
        return 'bosta';
    }

    public function name(): string
    {
        return 'Bosta';
    }

    public function ownsOrder(\WC_Order $order): bool
    {
        return (string) $order->get_meta(self::TRACKING) !== '' || (string) $order->get_meta(self::CODE) !== '';
    }

    public function tracking(\WC_Order $order): string
    {
        return mb_substr(sanitize_text_field((string) $order->get_meta(self::TRACKING)), 0, 100);
    }

    public function status(\WC_Order $order): ?array
    {
        $code = trim((string) $order->get_meta(self::CODE));
        if ($code === '' || preg_match('/^\d{1,4}$/', $code) !== 1) {
            return null;
        }

        return ['code' => $code, 'label' => mb_substr(sanitize_text_field((string) $order->get_meta(self::LABEL)), 0, 120), 'at' => self::deliveredAt($order) ?? time()];
    }

    /**
     * Bosta's own delivery time, as its plugin stores it for a delivered parcel ("Monday, 02/11/2026 03:45pm", Cairo
     * time), so a cash collection is dated when the courier collected, not when the store read the code (ADR-0002).
     */
    public static function deliveredAt(\WC_Order $order): ?int
    {
        $text = trim((string) $order->get_meta(self::DELIVERED));
        if ($text === '') {
            return null;
        }
        $at = \DateTimeImmutable::createFromFormat(self::DELIVERED_FORMAT, $text, new \DateTimeZone('Africa/Cairo'));

        return $at instanceof \DateTimeImmutable && $at->getTimestamp() <= time() + HOUR_IN_SECONDS ? $at->getTimestamp() : null;
    }
}

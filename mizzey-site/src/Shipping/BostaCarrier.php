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
    /** The plugin's two date forms, without the weekday (date formats, not text: built in pieces for the strings check). */
    private const REFRESH_FORMAT = '!d/m/Y' . ' ' . 'h:ia';
    private const WEBHOOK_FORMAT = '!j' . ' ' . 'M' . ' ' . 'g:i' . ' ' . 'A' . ' ' . 'Y';

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
     * Bosta's own delivery time, as its plugin stores it for a delivered parcel, so a cash collection is dated when the
     * courier collected, not when the store read the code (ADR-0002). The plugin writes it two ways, both Cairo time:
     * from its status refresh "Monday, 02/11/2026 03:45pm", and from its webhook "Monday, 2 Nov 3:45 PM", with no
     * year. The weekday is not parsed (PHP would move the date to it); for the webhook's form it picks the year: this
     * year or last, whichever falls on that weekday and is not in the future.
     */
    public static function deliveredAt(\WC_Order $order): ?int
    {
        $text = trim((string) $order->get_meta(self::DELIVERED));
        if (preg_match('/^([A-Za-z]+),\s*(.+)$/', $text, $m) !== 1) {
            return null;
        }
        $zone = new \DateTimeZone('Africa/Cairo');
        $at   = \DateTimeImmutable::createFromFormat(self::REFRESH_FORMAT, $m[2], $zone);
        if ($at instanceof \DateTimeImmutable) {
            return $at->getTimestamp() <= time() + HOUR_IN_SECONDS ? $at->getTimestamp() : null;
        }
        $year = (int) (new \DateTimeImmutable('now', $zone))->format('Y');
        foreach ([$year, $year - 1] as $candidate) {
            $at = \DateTimeImmutable::createFromFormat(self::WEBHOOK_FORMAT, $m[2] . ' ' . $candidate, $zone);
            if ($at instanceof \DateTimeImmutable && $at->getTimestamp() <= time() + HOUR_IN_SECONDS && strcasecmp($at->format('l'), $m[1]) === 0) {
                return $at->getTimestamp();
            }
        }

        return null;
    }
}

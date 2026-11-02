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

        return ['code' => $code, 'label' => mb_substr(sanitize_text_field((string) $order->get_meta(self::LABEL)), 0, 120), 'at' => time()];
    }
}

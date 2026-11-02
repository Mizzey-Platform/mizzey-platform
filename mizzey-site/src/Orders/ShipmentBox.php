<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

use MizzeySite\Emails\Shipped;

defined('ABSPATH') || exit;

/**
 * The order's shipment, attached and corrected by staff on the order screen (ADM-89, US-18-04): the carrier, the
 * tracking number and the tracking address. They are the same fields the carrier integration (#291) writes when it
 * creates the shipment and the Shipped email reads (`Shipped::CARRIER`, `TRACKING`, `URL`), so there is one record.
 * Every change is written to the order's notes with who made it, and the customer sees the carrier and tracking
 * number on the order in their account.
 *
 * Register ids: ADM-89. See specs/030-order-management.
 */
final class ShipmentBox
{
    public const NONCE = 'mizzey_shipment';
    public const MAX   = 100;

    public static function register(): void
    {
        add_action('woocommerce_admin_order_data_after_shipping_address', [self::class, 'fields'], 20, 1);
        add_action('woocommerce_process_shop_order_meta', [self::class, 'save'], 20, 2);
        add_action('woocommerce_order_details_after_order_table', [self::class, 'customer'], 15, 1);
    }

    /** @param mixed $order */
    public static function fields($order): void
    {
        if (! $order instanceof \WC_Order || ! current_user_can('edit_shop_orders')) {
            return;
        }
        $s = Shipped::shipment($order);
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        echo '<div class="mizzey-shipment" style="clear:both;padding-top:8px">';
        printf('<h3>%s</h3>', esc_html__('Shipment', 'mizzey-site'));
        foreach ([
            'carrier'  => [__('Carrier', 'mizzey-site'), $s['carrier']],
            'tracking' => [__('Tracking number', 'mizzey-site'), $s['tracking']],
            'url'      => [__('Tracking address (optional)', 'mizzey-site'), $s['url']],
        ] as $key => [$label, $value]) {
            printf(
                '<p class="form-field form-field-wide"><label for="mizzey_shipment_%1$s">%2$s</label><input type="%3$s" id="mizzey_shipment_%1$s" name="mizzey_shipment[%1$s]" value="%4$s" maxlength="%5$d" /></p>',
                esc_attr($key),
                esc_html($label),
                $key === 'url' ? 'url' : 'text',
                esc_attr($value),
                $key === 'url' ? 500 : self::MAX
            );
        }
        echo '</div>';
    }

    /**
     * Saved with the order. A change is kept only with this form's nonce and for staff who edit orders.
     *
     * @param mixed $orderId
     * @param mixed $order
     */
    public static function save($orderId, $order = null): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! current_user_can('edit_shop_orders') || ! isset($_POST['mizzey_shipment']) || ! is_array($_POST['mizzey_shipment'])) {
            return;
        }
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }
        $raw = wp_unslash($_POST['mizzey_shipment']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised below.
        self::set($order, [
            'carrier'  => is_string($raw['carrier'] ?? null) ? $raw['carrier'] : '',
            'tracking' => is_string($raw['tracking'] ?? null) ? $raw['tracking'] : '',
            'url'      => is_string($raw['url'] ?? null) ? $raw['url'] : '',
        ]);
    }

    /**
     * Attach or correct the shipment, noting the change with the staff member who made it.
     *
     * @param array{carrier:string,tracking:string,url:string} $values
     */
    public static function set(\WC_Order $order, array $values): bool
    {
        $new = [
            'carrier'  => mb_substr(sanitize_text_field($values['carrier']), 0, self::MAX),
            'tracking' => mb_substr(sanitize_text_field($values['tracking']), 0, self::MAX),
            'url'      => self::url($values['url']),
        ];
        $old = [
            'carrier'  => (string) $order->get_meta(Shipped::CARRIER),
            'tracking' => (string) $order->get_meta(Shipped::TRACKING),
            'url'      => (string) $order->get_meta(Shipped::URL),
        ];
        if ($new === $old) {
            return false;
        }
        $order->update_meta_data(Shipped::CARRIER, $new['carrier']);
        $order->update_meta_data(Shipped::TRACKING, $new['tracking']);
        $order->update_meta_data(Shipped::URL, $new['url']);
        $order->save_meta_data();
        $user = wp_get_current_user();
        $who  = $user->exists() ? $user->user_login : __('the system', 'mizzey-site');
        $note = $old['carrier'] === '' && $old['tracking'] === ''
            ? sprintf(__('Shipment attached by %1$s: carrier %2$s, tracking number %3$s.', 'mizzey-site'), $who, $new['carrier'] ?: '-', $new['tracking'] ?: '-')
            : sprintf(__('Shipment corrected by %1$s: carrier %2$s to %3$s, tracking number %4$s to %5$s.', 'mizzey-site'), $who, $old['carrier'] ?: '-', $new['carrier'] ?: '-', $old['tracking'] ?: '-', $new['tracking'] ?: '-');
        $order->add_order_note($note, 0, false);
        // A Shipped email that went out without these details promised them: they are sent now (NOTF-04).
        Shipped::followUp($order);

        return true;
    }

    /** Only an http or https address is kept. */
    private static function url(string $url): string
    {
        $url  = trim($url);
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        $ok   = filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) && $host !== '' && str_contains($host, '.');

        return $ok && mb_strlen($url) <= 500 ? esc_url_raw($url, ['http', 'https']) : '';
    }

    /**
     * The shipment on the customer's order view, in their language.
     *
     * @param mixed $order
     */
    public static function customer($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }
        $s = Shipped::shipment($order);
        if ($s['tracking'] === '') {
            return;
        }
        echo '<section class="mizzey-order-shipment">';
        printf('<h2>%s</h2>', esc_html__('Shipment', 'mizzey-site'));
        if ($s['carrier'] !== '') {
            printf('<p>%s: %s</p>', esc_html__('Carrier', 'mizzey-site'), esc_html($s['carrier']));
        }
        $number = $s['url'] !== ''
            ? sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url($s['url']), esc_html($s['tracking']))
            : esc_html($s['tracking']);
        printf('<p>%s: %s</p>', esc_html__('Tracking number', 'mizzey-site'), $number); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
        echo '</section>';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

use MizzeySite\Emails\Shipped;
use MizzeySite\Orders\StatusModel;

defined('ABSPATH') || exit;

/**
 * The explicit carrier-status-to-order-status mapping (SHIP-13, SHIP-14, SHIP-17; ADR-0002 minimum, D-20 C9).
 *
 * - **The mapping** is a table staff keep in WooCommerce, Settings, Shipping, Carrier statuses: each carrier code to
 *   one Mizzey state. Every code the carrier has sent is listed there, mapped or not. A code with no mapping, or one
 *   whose move the order status model refuses, leaves the order where it is, puts it in "needs review" on the order
 *   and in the list, and notes why: it is never taken as delivered. Bosta's legend (I-08) fills the table; until then
 *   it is empty and every code is reviewed by staff.
 * - **Returned to origin** is its own state: the order moves to Delivery Failed / Returned to Origin (specs/010).
 * - **Repeats** change nothing: a code is applied once per order.
 * - **Cash on delivery** (SHIP-16): the first time a cash-on-delivery order reaches delivered, the amount collected and
 *   when are kept, once; staff record what Bosta remitted, when, and its reference on the order (`CodReconciliation`).
 * - **Tracking** (SHIP-03, SHIP-09): the carrier's tracking number fills the order's shipment when staff have not
 *   entered one (specs/030), so the customer's order view and the Shipped email show it.
 *
 * Register ids: SHIP-03, SHIP-08, SHIP-09, SHIP-13, SHIP-14, SHIP-17, INT-02. See specs/041-bosta.
 */
final class CarrierStatus
{
    public const MAP      = 'mizzey_carrier_status_map';
    public const SEEN     = 'mizzey_carrier_codes_seen';
    public const APPLIED  = '_mizzey_carrier_code_applied';
    public const REVIEW   = '_mizzey_carrier_needs_review';
    public const NONCE    = 'mizzey_carrier_map';

    /** The Mizzey states a code can map to, and the order status each one moves the order to (null: none). */
    public const STATES = [
        'in-transit'       => StatusModel::SHIPPED,
        'out-for-delivery' => StatusModel::OUT_FOR_DELIVERY,
        'delivered'        => 'completed',
        'returned'         => StatusModel::DELIVERY_FAILED,
        'informational'    => null,
    ];

    private static bool $applying = false;

    /** @return list<Carrier> */
    public static function carriers(): array
    {
        $carriers = apply_filters('mizzey_carriers', [new BostaCarrier()]);

        return array_values(array_filter((array) $carriers, static fn ($c): bool => $c instanceof Carrier));
    }

    public static function register(): void
    {
        add_action('woocommerce_after_order_object_save', [self::class, 'apply'], 20, 1);
        add_filter('mizzey_order_shipment', [self::class, 'shipment'], 10, 2);
        add_filter('woocommerce_get_sections_shipping', [self::class, 'section']);
        add_action('woocommerce_settings_shipping', [self::class, 'settings']);
        add_action('woocommerce_settings_save_shipping', [self::class, 'save']);
        add_action('woocommerce_admin_order_data_after_shipping_address', [self::class, 'orderPanel'], 30, 1);
    }

    /** @return array<string,string> code => state */
    public static function map(): array
    {
        $map = get_option(self::MAP, []);

        return is_array($map) ? array_filter(array_map('strval', $map), static fn (string $s): bool => array_key_exists($s, self::STATES)) : [];
    }

    /**
     * After any save of an order (the carrier plugin saves it when Bosta's webhook arrives): a new carrier code is
     * mapped and applied once.
     *
     * @param mixed $order
     */
    public static function apply($order): void
    {
        if (self::$applying || ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund) {
            return;
        }
        foreach (self::carriers() as $carrier) {
            if (! $carrier->ownsOrder($order)) {
                continue;
            }
            $status = $carrier->status($order);
            if ($status === null || (string) $order->get_meta(self::APPLIED) === $carrier->id() . ':' . $status['code']) {
                return;
            }
            self::$applying = true;
            try {
                self::remember($carrier, $status);
                self::move($order, $carrier, $status);
            } finally {
                self::$applying = false;
            }
            return;
        }
    }

    /** @param array{code:string,label:string,at:int} $status */
    private static function remember(Carrier $carrier, array $status): void
    {
        $seen = get_option(self::SEEN, []);
        $seen = is_array($seen) ? $seen : [];
        $seen[$carrier->id() . ':' . $status['code']] = ['carrier' => $carrier->name(), 'code' => $status['code'], 'label' => $status['label'], 'last' => time()];
        update_option(self::SEEN, $seen, false);
    }

    /** @param array{code:string,label:string,at:int} $status */
    private static function move(\WC_Order $order, Carrier $carrier, array $status): void
    {
        $order->update_meta_data(self::APPLIED, $carrier->id() . ':' . $status['code']);
        $state = self::map()[$status['code']] ?? null;
        $label = $status['label'] !== '' ? $status['label'] : '-';
        if ($state === null) {
            self::review($order, sprintf(__('%1$s sent status code %2$s (%3$s), which has no mapping yet. The order was not moved: map the code in Settings, Shipping, Carrier statuses.', 'mizzey-site'), $carrier->name(), $status['code'], $label));
            return;
        }
        $target = self::STATES[$state];
        if ($target === null || $order->has_status($target)) {
            $order->delete_meta_data(self::REVIEW);
            $order->save();
            $order->add_order_note(sprintf(__('%1$s status %2$s (%3$s): %4$s.', 'mizzey-site'), $carrier->name(), $status['code'], $label, $state), 0, false);
            return;
        }
        $from = (string) $order->get_status();
        if (! StatusModel::allowed($from, $target)) {
            self::review($order, sprintf(__('%1$s sent status code %2$s (%3$s), mapped to %4$s, but an order in %5$s cannot move there. The order was not moved.', 'mizzey-site'), $carrier->name(), $status['code'], $label, $state, wc_get_order_status_name($from)));
            return;
        }
        if ($state === 'delivered' && StatusModel::isCash($order)) {
            CodReconciliation::collected($order, $status['at']);
            $target = StatusModel::COD_COLLECTED;
        }
        $order->delete_meta_data(self::REVIEW);
        $order->save();
        $order->update_status($target, sprintf(__('%1$s status %2$s (%3$s).', 'mizzey-site'), $carrier->name(), $status['code'], $label));
    }

    private static function review(\WC_Order $order, string $why): void
    {
        $order->update_meta_data(self::REVIEW, $why);
        $order->save();
        $order->add_order_note($why, 0, false);
    }

    /**
     * The carrier's tracking number, when staff have not entered a shipment by hand.
     *
     * @param mixed $shipment
     * @param mixed $order
     * @return mixed
     */
    public static function shipment($shipment, $order)
    {
        if (! is_array($shipment) || ! $order instanceof \WC_Order || ($shipment['tracking'] ?? '') !== '') {
            return $shipment;
        }
        foreach (self::carriers() as $carrier) {
            if ($carrier->ownsOrder($order) && $carrier->tracking($order) !== '') {
                $shipment['carrier']  = $carrier->name();
                $shipment['tracking'] = $carrier->tracking($order);
                break;
            }
        }

        return $shipment;
    }

    /**
     * @param mixed $sections
     * @return mixed
     */
    public static function section($sections)
    {
        if (is_array($sections)) {
            $sections['mizzey_carrier_statuses'] = __('Carrier statuses', 'mizzey-site');
        }

        return $sections;
    }

    private static function onSection(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which settings section is shown.
        return isset($_GET['section']) && sanitize_key(wp_unslash((string) $_GET['section'])) === 'mizzey_carrier_statuses';
    }

    /** The mapping, visible and editable in the administration (SHIP-14). */
    public static function settings(): void
    {
        if (! self::onSection()) {
            return;
        }
        $map  = self::map();
        $seen = get_option(self::SEEN, []);
        $seen = is_array($seen) ? $seen : [];
        $codes = [];
        foreach ($seen as $row) {
            $codes[(string) $row['code']] = $row;
        }
        foreach (array_keys($map) as $code) {
            $codes[(string) $code] = $codes[(string) $code] ?? ['carrier' => 'Bosta', 'code' => (string) $code, 'label' => '', 'last' => 0];
        }
        ksort($codes, SORT_NATURAL);
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        printf('<h2>%s</h2><p>%s</p>', esc_html__('Carrier statuses', 'mizzey-site'), esc_html__('How each status code the carrier sends moves an order. A code with no mapping never moves an order: the order is marked for review. Returned to origin is its own state and is never taken as delivered.', 'mizzey-site'));
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Carrier', 'mizzey-site') . '</th><th>' . esc_html__('Code', 'mizzey-site') . '</th><th>' . esc_html__('Carrier description', 'mizzey-site') . '</th><th>' . esc_html__('Maps to', 'mizzey-site') . '</th></tr></thead><tbody>';
        $labels = self::labels();
        foreach ($codes as $code => $row) {
            echo '<tr><td>' . esc_html((string) $row['carrier']) . '</td><td>' . esc_html((string) $code) . '</td><td>' . esc_html((string) $row['label']) . '</td><td>';
            printf('<select name="mizzey_carrier_map[%s]"><option value="">%s</option>', esc_attr((string) $code), esc_html__('Needs review (no mapping)', 'mizzey-site'));
            foreach ($labels as $state => $label) {
                printf('<option value="%s"%s>%s</option>', esc_attr($state), selected($map[(string) $code] ?? '', $state, false), esc_html($label));
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
        printf('<p><label>%s <input type="text" name="mizzey_carrier_new_code" pattern="\d{1,4}" size="6" /></label></p>', esc_html__('Add a code from the carrier\'s legend:', 'mizzey-site'));
    }

    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            'in-transit'       => __('In transit (Shipped)', 'mizzey-site'),
            'out-for-delivery' => __('Out for delivery', 'mizzey-site'),
            'delivered'        => __('Delivered (COD: collected)', 'mizzey-site'),
            'returned'         => __('Delivery failed / returned to origin', 'mizzey-site'),
            'informational'    => __('Informational: no change', 'mizzey-site'),
        ];
    }

    public static function save(): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if (! self::onSection() || $nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! current_user_can('manage_woocommerce')) {
            return;
        }
        $posted = isset($_POST['mizzey_carrier_map']) && is_array($_POST['mizzey_carrier_map']) ? wp_unslash($_POST['mizzey_carrier_map']) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked below.
        $map    = [];
        foreach ((array) $posted as $code => $state) {
            $code  = (string) $code;
            $state = sanitize_key((string) $state);
            if (preg_match('/^\d{1,4}$/', $code) === 1 && array_key_exists($state, self::STATES)) {
                $map[$code] = $state;
            }
        }
        $new = isset($_POST['mizzey_carrier_new_code']) ? sanitize_text_field(wp_unslash((string) $_POST['mizzey_carrier_new_code'])) : '';
        if (preg_match('/^\d{1,4}$/', $new) === 1) {
            $seen = get_option(self::SEEN, []);
            $seen = is_array($seen) ? $seen : [];
            $key        = (new BostaCarrier())->id() . ':' . $new;
            $seen[$key] = $seen[$key] ?? ['carrier' => 'Bosta', 'code' => $new, 'label' => '', 'last' => 0];
            update_option(self::SEEN, $seen, false);
        }
        update_option(self::MAP, $map, false);
    }

    /** @param mixed $order */
    public static function orderPanel($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }
        $review = (string) $order->get_meta(self::REVIEW);
        if ($review !== '') {
            printf('<p class="form-field form-field-wide"><strong>%s</strong> %s</p>', esc_html__('Carrier status needs review:', 'mizzey-site'), esc_html($review));
        }
    }
}

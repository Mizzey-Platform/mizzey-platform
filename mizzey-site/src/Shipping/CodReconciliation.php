<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

use MizzeySite\Orders\StatusModel;

defined('ABSPATH') || exit;

/**
 * Cash-on-delivery reconciliation: collected versus remitted, per order (SHIP-16, ADR-0002 item 3).
 *
 * Collected: the order total and the time the carrier reported delivery, set once from the first delivered status; a
 * repeat changes nothing. Remitted: the amount, the date and the reference of the carrier's remittance, entered by
 * staff on the order (OD-20 decides later whether they are imported from a statement). Recording the remittance moves
 * the order from COD Collected to Delivered, the owner's cash route (specs/010). The orders list can show the cash
 * orders collected and not yet remitted.
 *
 * Register ids: SHIP-16. See specs/041-bosta.
 */
final class CodReconciliation
{
    public const COLLECTED    = '_mizzey_cod_collected';
    public const COLLECTED_AT = '_mizzey_cod_collected_at';
    public const REMITTED     = '_mizzey_cod_remitted';
    public const REMITTED_AT  = '_mizzey_cod_remitted_at';
    public const REFERENCE    = '_mizzey_cod_remittance_ref';
    public const NONCE        = 'mizzey_cod_remittance';

    public static function register(): void
    {
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'panel'], 40, 1);
        add_action('woocommerce_process_shop_order_meta', [self::class, 'save'], 30, 2);
        add_action('woocommerce_order_list_table_restrict_manage_orders', [self::class, 'filter'], 30, 2);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [self::class, 'query'], 30);
        add_action('woocommerce_order_status_' . StatusModel::COD_COLLECTED, [self::class, 'staffCollected'], 10, 2);
    }

    /** The first delivered status of a cash order: the amount and the time, once. */
    public static function collected(\WC_Order $order, int $at): void
    {
        if ((string) $order->get_meta(self::COLLECTED) !== '') {
            return;
        }
        $order->update_meta_data(self::COLLECTED, wc_format_decimal($order->get_total()));
        $order->update_meta_data(self::COLLECTED_AT, (string) $at);
    }

    /**
     * An order moved to COD Collected by staff, not by the carrier, still records what was collected and when.
     *
     * @param mixed $orderId
     * @param mixed $order
     */
    public static function staffCollected($orderId, $order = null): void
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if ($order instanceof \WC_Order && (string) $order->get_meta(self::COLLECTED) === '') {
            self::collected($order, time());
            $order->save_meta_data();
        }
    }

    /** @return array{collected:string,collected_at:int,remitted:string,remitted_at:string,reference:string} */
    public static function of(\WC_Order $order): array
    {
        return [
            'collected'    => (string) $order->get_meta(self::COLLECTED),
            'collected_at' => (int) $order->get_meta(self::COLLECTED_AT),
            'remitted'     => (string) $order->get_meta(self::REMITTED),
            'remitted_at'  => (string) $order->get_meta(self::REMITTED_AT),
            'reference'    => (string) $order->get_meta(self::REFERENCE),
        ];
    }

    /** Record the carrier's remittance for the order; a collected order then moves to Delivered. */
    public static function remit(\WC_Order $order, string $amount, string $date, string $reference): ?string
    {
        $reference = mb_substr(sanitize_text_field($reference), 0, 100);
        $amount    = trim($amount);
        $day       = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1 || (float) $amount <= 0 || ! $day) {
            return __('Give the amount remitted and its date.', 'mizzey-site');
        }
        if ((string) $order->get_meta(CarrierStatus::REVIEW) !== '') {
            return __('The carrier status of this order needs review first.', 'mizzey-site');
        }
        if ((string) $order->get_meta(self::COLLECTED) === '') {
            return __('The carrier has not reported this cash as collected yet.', 'mizzey-site');
        }
        $amount = wc_format_decimal($amount, 2);
        $order->update_meta_data(self::REMITTED, $amount);
        $order->update_meta_data(self::REMITTED_AT, $date);
        $order->update_meta_data(self::REFERENCE, $reference);
        $order->save();
        $user = wp_get_current_user();
        $order->add_order_note(sprintf(__('Cash remitted by the carrier: %1$s on %2$s, reference %3$s (recorded by %4$s).', 'mizzey-site'), $amount, $date, $reference !== '' ? $reference : '-', $user->exists() ? $user->user_login : '-'), 0, false);
        if ($order->has_status(StatusModel::COD_COLLECTED)) {
            $order->update_status('completed', __('The carrier remitted the cash.', 'mizzey-site'));
        }

        return null;
    }

    /** @param mixed $order */
    public static function panel($order): void
    {
        if (! $order instanceof \WC_Order || ! StatusModel::isCash($order) || ! current_user_can('edit_shop_orders')) {
            return;
        }
        $r = self::of($order);
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        echo '<div class="form-field form-field-wide mizzey-cod"><h3>' . esc_html__('Cash on delivery', 'mizzey-site') . '</h3>';
        printf(
            '<p>%s</p>',
            esc_html($r['collected'] !== ''
                ? sprintf(__('Collected by the carrier: %1$s on %2$s.', 'mizzey-site'), $r['collected'], wp_date(get_option('date_format'), $r['collected_at']))
                : __('Not collected yet.', 'mizzey-site'))
        );
        printf(
            '<p><label>%s <input type="text" name="mizzey_cod_remitted" value="%s" size="8" /></label> <label>%s <input type="date" name="mizzey_cod_remitted_at" value="%s" /></label> <label>%s <input type="text" name="mizzey_cod_reference" value="%s" maxlength="100" /></label></p>',
            esc_html__('Remitted', 'mizzey-site'),
            esc_attr($r['remitted']),
            esc_html__('on', 'mizzey-site'),
            esc_attr($r['remitted_at']),
            esc_html__('reference', 'mizzey-site'),
            esc_attr($r['reference'])
        );
        echo '</div>';
    }

    /**
     * @param mixed $orderId
     * @param mixed $order
     */
    public static function save($orderId, $order = null): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! current_user_can('edit_shop_orders') || empty($_POST['mizzey_cod_remitted'])) {
            return;
        }
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if (! $order instanceof \WC_Order || ! StatusModel::isCash($order)) {
            return;
        }
        $amount = sanitize_text_field(wp_unslash((string) $_POST['mizzey_cod_remitted']));
        $date   = isset($_POST['mizzey_cod_remitted_at']) ? sanitize_text_field(wp_unslash((string) $_POST['mizzey_cod_remitted_at'])) : '';
        $ref    = isset($_POST['mizzey_cod_reference']) ? sanitize_text_field(wp_unslash((string) $_POST['mizzey_cod_reference'])) : '';
        if ($amount === (string) $order->get_meta(self::REMITTED) && $date === (string) $order->get_meta(self::REMITTED_AT) && $ref === (string) $order->get_meta(self::REFERENCE)) {
            return;
        }
        $problem = self::remit($order, $amount, $date, $ref);
        if ($problem !== null && class_exists('\WC_Admin_Meta_Boxes')) {
            \WC_Admin_Meta_Boxes::add_error($problem);
        }
    }

    /**
     * @param mixed $type
     * @param mixed $which
     */
    public static function filter($type = 'shop_order', $which = 'top'): void
    {
        if ($type !== 'shop_order' || $which !== 'top') {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
        $on = isset($_GET['mizzey_unremitted']);
        printf('<label><input type="checkbox" name="mizzey_unremitted" value="1"%s /> %s</label>', checked($on, true, false), esc_html__('Cash collected, not remitted', 'mizzey-site'));
    }

    /**
     * @param mixed $args
     * @return mixed
     */
    public static function query($args)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only list filter.
        if (! is_array($args) || ! isset($_GET['mizzey_unremitted'])) {
            return $args;
        }
        $meta   = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $meta[] = ['key' => self::COLLECTED, 'compare' => 'EXISTS'];
        $meta[] = ['key' => self::REMITTED, 'compare' => implode(' ', ['NOT', 'EXISTS'])];
        $args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

        return $args;
    }
}

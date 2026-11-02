<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

defined('ABSPATH') || exit;

/**
 * Every refund carries its payment reference (ADM-124). WooCommerce keeps a refund's amount, reason and whether the
 * gateway made it; this adds the reference of the payment it returns (the order's transaction reference, PAY-06) and,
 * where there is one, the refund's own reference: the provider's, when the gateway made it; the one staff typed, when
 * it was made by hand; or a mark that it was made in the provider's dashboard and recorded from its report (PAY-17).
 * The references are shown on the refund line of the order screen and named in the standard refunded-order email.
 *
 * Register ids: RET-08, ADM-124. See specs/029-refunds.
 */
final class RefundReference
{
    public const PAYMENT   = '_mizzey_payment_reference';
    public const REFERENCE = '_mizzey_refund_reference';
    public const SOURCE    = '_mizzey_refund_reference_source';

    public const BY_GATEWAY   = 'gateway';
    public const BY_DASHBOARD = 'dashboard';
    public const BY_STAFF     = 'staff';

    /** The field the refund panel sends, and the most it keeps. */
    public const FIELD = 'mizzey_refund_reference';
    public const MAX   = 100;

    /** The refund being made in this request, until the gateway has answered. */
    private static ?\WC_Order_Refund $current = null;

    public static function register(): void
    {
        add_action('woocommerce_create_refund', [self::class, 'stamp'], 20, 2);
        add_action('woocommerce_after_order_refund_item_name', [self::class, 'line']);
        add_action('admin_enqueue_scripts', [self::class, 'field'], 20);
        add_action('woocommerce_email_after_order_table', [self::class, 'email'], 10, 4);
    }

    /**
     * Before the refund is saved: the payment's reference, and the staff's or the dashboard's reference.
     *
     * @param mixed $refund
     * @param mixed $args
     */
    public static function stamp($refund, $args): void
    {
        if (! $refund instanceof \WC_Order_Refund || ! is_array($args)) {
            return;
        }
        self::$current = $refund;
        $order   = wc_get_order((int) ($args['order_id'] ?? 0));
        $payment = $order instanceof \WC_Order ? trim((string) $order->get_transaction_id('edit')) : '';
        if ($payment !== '') {
            $refund->update_meta_data(self::PAYMENT, self::clean($payment));
        }
        if (! empty($args['mizzey_from_dashboard'])) {
            $refund->update_meta_data(self::SOURCE, self::BY_DASHBOARD);
            return;
        }
        if (! empty($args['refund_payment'])) {
            return;
        }
        $typed = self::typed();
        if ($typed !== '') {
            $refund->update_meta_data(self::REFERENCE, $typed);
            $refund->update_meta_data(self::SOURCE, self::BY_STAFF);
        }
    }

    /** The provider's reference for the refund the gateway is making now (`RefundLedger` reads it from its note). */
    public static function fromGateway(\WC_Order $order, string $reference): void
    {
        $refund = self::$current;
        if (! $refund instanceof \WC_Order_Refund || $refund->get_id() <= 0 || $refund->get_parent_id() !== $order->get_id()) {
            return;
        }
        $refund->update_meta_data(self::REFERENCE, self::clean($reference));
        $refund->update_meta_data(self::SOURCE, self::BY_GATEWAY);
        $refund->save_meta_data();
    }

    /**
     * The reference staff typed on the refund panel, read only from WooCommerce's own refund request, whose nonce and
     * capability WooCommerce and `RefundCapability` have already checked; checked again here.
     */
    private static function typed(): string
    {
        if (! wp_doing_ajax() || ($_POST['action'] ?? '') !== 'woocommerce_refund_line_items' || ! isset($_POST[self::FIELD])) {
            return '';
        }
        if (! check_ajax_referer('order-item', 'security', false) || ! current_user_can(\MizzeySite\Roles\RefundCapability::CAP)) {
            return '';
        }

        return self::clean(sanitize_text_field(wp_unslash((string) $_POST[self::FIELD])));
    }

    private static function clean(string $text): string
    {
        return mb_substr(trim(wp_strip_all_tags($text)), 0, self::MAX);
    }

    /** @return array{payment:string,reference:string,source:string} */
    public static function of(\WC_Order_Refund $refund): array
    {
        return [
            'payment'   => (string) $refund->get_meta(self::PAYMENT),
            'reference' => (string) $refund->get_meta(self::REFERENCE),
            'source'    => (string) $refund->get_meta(self::SOURCE),
        ];
    }

    /**
     * The refund line on the order screen (staff, English: ADM-159).
     *
     * @param mixed $refund
     */
    public static function line($refund): void
    {
        if (! $refund instanceof \WC_Order_Refund) {
            return;
        }
        $r     = self::of($refund);
        $parts = [];
        $parts[] = $r['payment'] !== ''
            ? sprintf(__('Payment reference: %s', 'mizzey-site'), $r['payment'])
            : __('Payment reference: none on the order', 'mizzey-site');
        if ($r['source'] === self::BY_GATEWAY) {
            $parts[] = sprintf(__('Refund reference: %s (made through the payment gateway)', 'mizzey-site'), $r['reference']);
        } elseif ($r['source'] === self::BY_STAFF) {
            $parts[] = sprintf(__('Refund reference: %s (typed by staff)', 'mizzey-site'), $r['reference']);
        } elseif ($r['source'] === self::BY_DASHBOARD) {
            $parts[] = __('Made in the payment provider\'s dashboard and recorded from its report', 'mizzey-site');
        } elseif ($refund->get_refunded_payment()) {
            $parts[] = __('Made through the payment gateway', 'mizzey-site');
        } else {
            $parts[] = __('Recorded by hand, no refund reference given', 'mizzey-site');
        }
        printf('<p class="description mizzey-refund-reference">%s</p>', esc_html(implode(' | ', $parts)));
    }

    /**
     * The reference field on the refund panel of the order screen, for a refund made by hand.
     *
     * @param mixed $hook
     */
    public static function field($hook): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen instanceof \WP_Screen || ! in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)
            || ! current_user_can(\MizzeySite\Roles\RefundCapability::CAP)) {
            return;
        }
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/admin-refund-reference.js';
        wp_enqueue_script('mizzey-admin-refund-reference', plugins_url('mizzey-site/assets/admin-refund-reference.js'), ['jquery', 'wc-admin-order-meta-boxes'], is_readable($file) ? (string) filemtime($file) : null, true);
        wp_localize_script('mizzey-admin-refund-reference', 'mizzeyRefundReference', [
            'field' => self::FIELD,
            'max'   => self::MAX,
            'label' => __('Refund reference (for a refund made by hand, optional):', 'mizzey-site'),
            'tip'   => __('The reference of the money returned, for example a transfer or a Paymob dashboard refund. Kept with the refund and named in the customer\'s refund email.', 'mizzey-site'),
        ]);
    }

    /**
     * The refund's reference in the standard refunded-order email, full or partial (WooCommerce gives the partial one
     * its own id), in the order's language (the email is already switched to it, specs/021).
     *
     * @param mixed $order
     * @param mixed $sentToAdmin
     * @param mixed $plainText
     * @param mixed $email
     */
    public static function email($order, $sentToAdmin, $plainText, $email = null): void
    {
        if ($sentToAdmin || ! $email instanceof \WC_Email || ! in_array($email->id, ['customer_refunded_order', 'customer_partially_refunded_order'], true) || ! $order instanceof \WC_Order) {
            return;
        }
        $refund = isset($email->refund) && $email->refund instanceof \WC_Order_Refund ? $email->refund : null;
        if ($refund === null) {
            $refunds = $order->get_refunds();
            $refund  = $refunds[0] ?? null;
        }
        if (! $refund instanceof \WC_Order_Refund) {
            return;
        }
        $r    = self::of($refund);
        $line = $r['reference'] !== ''
            ? sprintf(__('Refund reference: %s', 'mizzey-site'), $r['reference'])
            : ($r['payment'] !== '' ? sprintf(__('Payment reference: %s', 'mizzey-site'), $r['payment']) : '');
        if ($line === '') {
            return;
        }
        if ($plainText) {
            echo "\n" . esc_html($line) . "\n\n";
            return;
        }
        printf('<p class="mizzey-refund-reference">%s</p>', esc_html($line));
    }
}

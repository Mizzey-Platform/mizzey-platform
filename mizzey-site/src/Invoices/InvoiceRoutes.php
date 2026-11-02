<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Invoices;

defined('ABSPATH') || exit;

/**
 * Where an invoice is downloaded (ADM-92, ACCT-12): `?mizzey-invoice={id}&_wpnonce=...` on the site, for staff who edit
 * orders and for the order's own signed-in customer, with a nonce per order. A front-end address, as WordPress reads the
 * customer's sign-in cookie there and not in the admin. Staff find it on the
 * order's screen and in the orders list; the customer in the account's orders list, beside each paid order.
 *
 * See specs/023-invoice.
 */
final class InvoiceRoutes
{
    public const ACTION = 'mizzey_invoice';
    public const QUERY  = 'mizzey-invoice';

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'maybeDownload'], 1);
        add_filter('woocommerce_my_account_my_orders_actions', [self::class, 'accountAction'], 20, 2);
        add_filter('woocommerce_admin_order_actions', [self::class, 'listAction'], 20, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'orderScreen'], 20, 1);
    }

    public static function url(\WC_Order $order): string
    {
        return wp_nonce_url(add_query_arg(self::QUERY, $order->get_id(), home_url('/')), self::ACTION . '_' . $order->get_id());
    }

    /** Whether the signed-in user may have the order's invoice: staff who edit orders, or the order's customer. */
    public static function allowed(\WC_Order $order): bool
    {
        $user = get_current_user_id();

        return $user > 0 && (current_user_can('edit_shop_orders') || (int) $order->get_customer_id() === $user);
    }

    public static function maybeDownload(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below, per order.
        if (! isset($_GET[self::QUERY])) {
            return;
        }
        $id    = absint(wp_unslash($_GET[self::QUERY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below, per order.
        $order = $id > 0 ? wc_get_order($id) : null;
        if (! $order instanceof \WC_Order || ! check_admin_referer(self::ACTION . '_' . $id) || ! self::allowed($order) || ! InvoiceNumber::issuable($order)) {
            wp_die(esc_html__('This invoice is not available.', 'mizzey-site'), esc_html__('Invoice', 'mizzey-site'), ['response' => 403]);
        }
        InvoiceNumber::issue($id);
        $pdf = InvoicePdf::bytes(wc_get_order($id));
        if ($pdf === '') {
            wp_die(esc_html__('This invoice is not available.', 'mizzey-site'), esc_html__('Invoice', 'mizzey-site'), ['response' => 403]);
        }
        nocache_headers();
        header('Content-Type:application/pdf');
        header(sprintf('Content-Disposition:attachment;filename="%s"', sanitize_file_name(InvoiceNumber::of($id) . '.pdf')));
        header(sprintf('Content-Length:%d', strlen($pdf)));
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a PDF file.
        exit;
    }

    /**
     * @param mixed $actions
     * @param mixed $order
     * @return mixed
     */
    public static function accountAction($actions, $order)
    {
        if (is_array($actions) && $order instanceof \WC_Order && InvoiceNumber::issuable($order) && self::allowed($order)) {
            $actions['invoice'] = ['url' => self::url($order), 'name' => __('Invoice', 'mizzey-site')];
        }

        return $actions;
    }

    /**
     * @param mixed $actions
     * @param mixed $order
     * @return mixed
     */
    public static function listAction($actions, $order)
    {
        if (is_array($actions) && $order instanceof \WC_Order && InvoiceNumber::issuable($order) && current_user_can('edit_shop_orders')) {
            $actions['mizzey_invoice'] = ['url' => self::url($order), 'name' => __('Invoice', 'mizzey-site'), 'action' => 'mizzey-invoice'];
        }

        return $actions;
    }

    /** @param mixed $order */
    public static function orderScreen($order): void
    {
        if ($order instanceof \WC_Order && InvoiceNumber::issuable($order) && current_user_can('edit_shop_orders')) {
            printf('<p class="form-field form-field-wide"><a class="button" href="%s">%s</a></p>', esc_url(self::url($order)), esc_html__('Download invoice', 'mizzey-site'));
        }
    }
}

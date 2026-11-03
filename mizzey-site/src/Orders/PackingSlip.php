<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

use MizzeySite\Checkout\EgyptianAddress;
use MizzeySite\Emails\OrderLanguage;
use MizzeySite\Emails\Shipped;
use MizzeySite\Invoices\InvoiceDocument;
use MizzeySite\Invoices\InvoicePdf;
use MizzeySite\Invoices\InvoiceSettings;

defined('ABSPATH') || exit;

/**
 * The packing slip of one order (ADM-93, P1-L: per order; bulk printing, ADM-156, is DEF), printed or downloaded by
 * staff from the order screen and the orders list. It lists what goes in the parcel and where it goes, with no prices,
 * in the order's language, so it can travel in the parcel. Our own code with the invoice's PDF library (D-24).
 *
 * Register ids: ADM-93. See specs/030-order-management.
 */
final class PackingSlip
{
    public const ACTION = 'mizzey_packing_slip';
    public const QUERY  = 'mizzey-packing-slip';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybeDownload'], 1);
        add_filter('woocommerce_admin_order_actions', [self::class, 'listAction'], 21, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [self::class, 'orderScreen'], 21, 1);
    }

    /** A staff address in the admin: the slip is for staff only. */
    public static function url(\WC_Order $order): string
    {
        return wp_nonce_url(add_query_arg(self::QUERY, $order->get_id(), admin_url('admin.php')), self::ACTION . '_' . $order->get_id());
    }

    public static function maybeDownload(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below, per order.
        if (! isset($_GET[self::QUERY])) {
            return;
        }
        $id    = absint(wp_unslash($_GET[self::QUERY])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked below, per order.
        $order = $id > 0 ? wc_get_order($id) : null;
        if (! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund || ! check_admin_referer(self::ACTION . '_' . $id) || ! current_user_can('edit_shop_orders')) {
            wp_die(esc_html__('This packing slip is not available.', 'mizzey-site'), esc_html__('Packing slip', 'mizzey-site'), ['response' => 403]);
        }
        $pdf = self::pdf($order);
        nocache_headers();
        header('Content-Type:application/pdf');
        header(sprintf('Content-Disposition:inline;filename="%s"', sanitize_file_name('packing-slip-' . $order->get_order_number() . '.pdf')));
        header(sprintf('Content-Length:%d', strlen($pdf)));
        echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a PDF file.
        exit;
    }

    public static function pdf(\WC_Order $order): string
    {
        return InvoicePdf::render(self::html($order), sprintf(__('Packing slip %s', 'mizzey-site'), $order->get_order_number()), InvoiceDocument::rtl($order));
    }

    public static function html(\WC_Order $order): string
    {
        return (string) OrderLanguage::during($order, static fn (): string => self::render($order));
    }

    private static function render(\WC_Order $order): string
    {
        $dir  = InvoiceDocument::rtl($order) ? 'rtl' : 'ltr';
        $out  = sprintf('<div class="mizzey-packing-slip" dir="%s">', esc_attr($dir));
        $out .= sprintf('<h1>%s</h1>', esc_html__('Packing slip', 'mizzey-site'));

        $out .= '<table class="mizzey-invoice-parties" width="100%"><tr><td>';
        $seller = InvoiceSettings::seller();
        if ($seller['name'] !== '') {
            $out .= sprintf('<div><strong>%s</strong></div>', esc_html($seller['name']));
        }
        $out .= self::labelled(__('Order number', 'mizzey-site'), (string) $order->get_order_number());
        $created = $order->get_date_created();
        if ($created) {
            $out .= self::labelled(__('Order date', 'mizzey-site'), wp_date((string) get_option('date_format'), $created->getTimestamp()));
        }
        $shipment = Shipped::shipment($order);
        if ($shipment['carrier'] !== '') {
            $out .= self::labelled(__('Carrier', 'mizzey-site'), $shipment['carrier']);
        }
        if ($shipment['tracking'] !== '') {
            $out .= self::labelled(__('Tracking number', 'mizzey-site'), $shipment['tracking']);
        }
        $out .= '</td><td>';
        $out .= sprintf('<div><strong>%s</strong></div>', esc_html__('Ship to', 'mizzey-site'));
        foreach (self::recipient($order) as $line) {
            $out .= sprintf('<div>%s</div>', esc_html($line));
        }
        $out .= '</td></tr></table>';

        $out .= '<table class="mizzey-invoice-lines" width="100%"><thead><tr>';
        foreach ([__('Item', 'mizzey-site'), __('SKU', 'mizzey-site'), __('Quantity', 'mizzey-site')] as $heading) {
            $out .= sprintf('<th>%s</th>', esc_html($heading));
        }
        $out .= '</tr></thead><tbody>';
        foreach ($order->get_items() as $item_id => $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $left = (int) $item->get_quantity() + (int) $order->get_qty_refunded_for_item($item_id);
            if ($left <= 0) {
                continue;
            }
            $product = $item->get_product();
            $out    .= sprintf(
                '<tr><td>%s%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($item->get_name()),
                self::options($item),
                esc_html($product instanceof \WC_Product ? (string) $product->get_sku() : ''),
                esc_html((string) $left)
            );
        }
        $out .= '</tbody></table>';

        $note = trim((string) $order->get_customer_note());
        if ($note !== '') {
            $out .= sprintf('<h2>%s</h2><p>%s</p>', esc_html__('Customer note', 'mizzey-site'), nl2br(esc_html($note)));
        }

        return $out . '</div>';
    }

    /** The chosen options of a variation line, shown under its name. */
    private static function options(\WC_Order_Item_Product $item): string
    {
        $parts = [];
        foreach ($item->get_formatted_meta_data('_', true) as $meta) {
            $parts[] = wp_strip_all_tags((string) $meta->display_key) . ': ' . wp_strip_all_tags((string) $meta->display_value);
        }

        return $parts === [] ? '' : sprintf('<br><small>%s</small>', esc_html(implode(', ', $parts)));
    }

    /** @return list<string> The delivery name, address in the Egyptian structure, and phone; the billing ones when there is no shipping address. */
    private static function recipient(\WC_Order $order): array
    {
        $type     = $order->get_meta(EgyptianAddress::ONE_ADDRESS) !== 'yes' && $order->has_shipping_address() ? 'shipping' : 'billing';
        $get      = static fn (string $field): string => (string) $order->{"get_{$type}_{$field}"}();
        $district = (string) $order->get_meta('_wc_' . $type . '/' . EgyptianAddress::DISTRICT);
        $states   = (array) WC()->countries->get_states($get('country'));
        $state    = (string) ($states[$get('state')] ?? $get('state'));
        $phone    = $type === 'shipping' && $order->get_shipping_phone() !== '' ? $order->get_shipping_phone() : $order->get_billing_phone();

        return array_values(array_filter([
            trim($get('first_name') . ' ' . $get('last_name')),
            $get('company'),
            $get('address_1'),
            $get('address_2'),
            implode(', ', array_filter([$district, $get('city'), $state])),
            (string) $order->get_meta('_wc_' . $type . '/' . EgyptianAddress::LANDMARK),
            $phone,
        ], static fn ($line): bool => is_string($line) && trim($line) !== ''));
    }

    private static function labelled(string $label, string $value): string
    {
        return sprintf('<div><strong>%s:</strong> %s</div>', esc_html($label), esc_html($value));
    }

    /**
     * @param mixed $actions
     * @param mixed $order
     * @return mixed
     */
    public static function listAction($actions, $order)
    {
        if (is_array($actions) && $order instanceof \WC_Order && current_user_can('edit_shop_orders')) {
            $actions['mizzey_packing_slip'] = ['url' => self::url($order), 'name' => __('Packing slip', 'mizzey-site'), 'action' => 'mizzey-packing-slip'];
        }

        return $actions;
    }

    /** @param mixed $order */
    public static function orderScreen($order): void
    {
        if ($order instanceof \WC_Order && $order->get_id() > 0 && current_user_can('edit_shop_orders')) {
            printf('<p class="form-field form-field-wide"><a class="button" target="_blank" rel="noopener" href="%s">%s</a></p>', esc_url(self::url($order)), esc_html__('Print packing slip', 'mizzey-site'));
        }
    }
}

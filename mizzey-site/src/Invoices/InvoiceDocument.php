<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Invoices;

use MizzeySite\Checkout\EgyptianAddress;
use MizzeySite\Emails\OrderLanguage;

defined('ABSPATH') || exit;

/**
 * An order's invoice as a document (ADM-92, ACCT-12), in the order's language and direction: the seller's details, the
 * invoice and order numbers and dates, the customer's billing details in the Egyptian structure, each line with its
 * quantity and the price charged, the discounts, shipping, fees and taxes as WooCommerce recorded them on the order, the
 * total, and any refunds. The amounts are the order's own, the price snapshot of the sale (BR-005), never the
 * catalogue's current prices. Every value from the order or a setting is escaped.
 *
 * See specs/023-invoice.
 */
final class InvoiceDocument
{
    /** Whether the document is right to left: the order's language is Arabic. */
    public static function rtl(\WC_Order $order): bool
    {
        return str_starts_with((string) $order->get_meta('wpml_language'), 'ar');
    }

    /** The document's HTML, or '' when the order has no invoice number. */
    public static function html(\WC_Order $order): string
    {
        $number = InvoiceNumber::of((int) $order->get_id());
        if ($number === '') {
            return '';
        }

        return (string) OrderLanguage::during($order, static fn (): string => self::render($order, $number));
    }

    private static function render(\WC_Order $order, string $number): string
    {
        $seller = InvoiceSettings::seller();
        $dir    = self::rtl($order) ? 'rtl' : 'ltr';
        $issued = InvoiceNumber::issuedAt((int) $order->get_id());
        $out    = sprintf('<div class="mizzey-invoice" dir="%s">', esc_attr($dir));
        $out   .= sprintf('<h1>%s</h1>', esc_html__('Invoice', 'mizzey-site'));

        $out .= '<table class="mizzey-invoice-parties" width="100%"><tr><td>';
        foreach ([$seller['name'], $seller['address']] as $line) {
            if ($line !== '') {
                $out .= sprintf('<div>%s</div>', nl2br(esc_html($line)));
            }
        }
        if ($seller['tax'] !== '') {
            $out .= self::labelled(__('Tax registration number', 'mizzey-site'), $seller['tax']);
        }
        if ($seller['register'] !== '') {
            $out .= self::labelled(__('Commercial register number', 'mizzey-site'), $seller['register']);
        }
        $out .= '</td><td>';
        $out .= self::labelled(__('Invoice number', 'mizzey-site'), $number);
        $out .= self::labelled(__('Invoice date', 'mizzey-site'), wp_date((string) get_option('date_format'), $issued ?? time()));
        $out .= self::labelled(__('Order number', 'mizzey-site'), (string) $order->get_order_number());
        $created = $order->get_date_created();
        if ($created) {
            $out .= self::labelled(__('Order date', 'mizzey-site'), wp_date((string) get_option('date_format'), $created->getTimestamp()));
        }
        if ($order->get_payment_method_title() !== '') {
            $out .= self::labelled(__('Payment method', 'mizzey-site'), $order->get_payment_method_title());
        }
        $out .= '</td></tr></table>';

        $out .= sprintf('<h2>%s</h2>', esc_html__('Billed to', 'mizzey-site'));
        foreach (self::customer($order) as $line) {
            $out .= sprintf('<div>%s</div>', esc_html($line));
        }

        $out .= '<table class="mizzey-invoice-lines" width="100%"><thead><tr>';
        foreach ([__('Item', 'mizzey-site'), __('Quantity', 'mizzey-site'), __('Unit price', 'mizzey-site'), __('Total', 'mizzey-site')] as $heading) {
            $out .= sprintf('<th>%s</th>', esc_html($heading));
        }
        $out .= '</tr></thead><tbody>';
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $qty     = max(1, (int) $item->get_quantity());
            $product = $item->get_product();
            $sku     = $product instanceof \WC_Product ? (string) $product->get_sku() : '';
            $out    .= sprintf(
                '<tr><td>%s%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($item->get_name()),
                $sku !== '' ? sprintf('<br><small>%s</small>', esc_html($sku)) : '',
                esc_html((string) $item->get_quantity()),
                esc_html(self::money((float) $item->get_subtotal() / $qty, $order)),
                esc_html(self::money((float) $item->get_subtotal(), $order))
            );
        }
        $out .= '</tbody></table>';

        $out .= '<table class="mizzey-invoice-totals" width="100%">';
        // The lines' stored subtotals: WooCommerce's get_subtotal() rounds with the viewer's currency precision.
        $subtotal = array_sum(array_map(static fn ($item): float => $item instanceof \WC_Order_Item_Product ? (float) $item->get_subtotal() : 0.0, $order->get_items()));
        $out     .= self::total(__('Subtotal', 'mizzey-site'), $subtotal, $order);
        if ((float) $order->get_discount_total() > 0) {
            $out .= self::total(__('Discount', 'mizzey-site'), -(float) $order->get_discount_total(), $order);
        }
        if ((float) $order->get_shipping_total() > 0 || $order->get_shipping_method() !== '') {
            $out .= self::total(__('Shipping', 'mizzey-site'), (float) $order->get_shipping_total(), $order);
        }
        foreach ($order->get_fees() as $fee) {
            $out .= self::total($fee->get_name(), (float) $fee->get_total(), $order);
        }
        foreach ($order->get_tax_totals() as $tax) {
            $out .= self::total((string) $tax->label, (float) $tax->amount, $order);
        }
        $out .= self::total(__('Total', 'mizzey-site'), (float) $order->get_total(), $order, true);
        $refunded = (float) $order->get_total_refunded();
        if ($refunded > 0) {
            $out .= self::total(__('Refunded', 'mizzey-site'), -$refunded, $order);
            $out .= self::total(__('Net total', 'mizzey-site'), (float) $order->get_total() - $refunded, $order, true);
        }
        $out .= '</table>';

        if ($seller['note'] !== '') {
            $out .= sprintf('<p class="mizzey-invoice-note">%s</p>', nl2br(esc_html($seller['note'])));
        }

        return $out . '</div>';
    }

    /** @return list<string> The billing name, phone, email and address, in the Egyptian structure. */
    private static function customer(\WC_Order $order): array
    {
        $district = (string) $order->get_meta('_wc_billing/' . EgyptianAddress::DISTRICT);
        $states   = (array) WC()->countries->get_states($order->get_billing_country());
        $state    = (string) ($states[$order->get_billing_state()] ?? $order->get_billing_state());
        $place    = array_filter([$order->get_billing_address_1(), $district, $order->get_billing_city(), $state]);

        return array_values(array_filter([
            trim($order->get_formatted_billing_full_name()),
            $order->get_billing_company(),
            implode(', ', $place),
            $order->get_billing_phone(),
            $order->get_billing_email(),
        ], static fn ($line): bool => is_string($line) && trim($line) !== ''));
    }

    private static function labelled(string $label, string $value): string
    {
        return sprintf('<div><strong>%s:</strong> %s</div>', esc_html($label), esc_html($value));
    }

    private static function total(string $label, float $amount, \WC_Order $order, bool $strong = false): string
    {
        $cell = $strong ? '<strong>%s</strong>' : '%s';

        return sprintf('<tr><td>' . $cell . '</td><td class="mizzey-invoice-amount">' . $cell . '</td></tr>', esc_html($label), esc_html(self::money($amount, $order)));
    }

    /** An amount in the order's currency, as plain text, with that currency's number of decimals. */
    public static function money(float $amount, \WC_Order $order): string
    {
        return html_entity_decode(wp_strip_all_tags(wc_price($amount, ['currency' => $order->get_currency(), 'decimals' => self::decimals($order->get_currency())])), ENT_QUOTES, 'UTF-8');
    }

    /**
     * The decimals of a currency, read as stored and not through the viewer's currency (WooCommerce Multilingual
     * filters both wc_get_price_decimals() and the option): the store's own for its currency, WooCommerce
     * Multilingual's setting for another, else the store's own.
     */
    public static function decimals(string $currency): int
    {
        global $wpdb;
        $raw      = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'woocommerce_price_num_decimals')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $store    = is_numeric($raw) ? (int) $raw : 2;
        $base     = (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'woocommerce_currency')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $settings = maybe_unserialize((string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_wcml_settings'))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $other    = is_array($settings) ? ($settings['currency_options'][$currency]['num_decimals'] ?? null) : null;

        return max(0, $currency !== $base && is_numeric($other) ? (int) $other : $store);
    }
}

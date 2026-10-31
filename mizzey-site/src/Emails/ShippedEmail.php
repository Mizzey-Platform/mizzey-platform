<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Emails;

defined('ABSPATH') || exit;

/**
 * The Shipped email (NOTF-04): a WooCommerce email, listed with the store's other emails, sent to the order's billing
 * email when the order first enters Shipped (the status model of specs/010), in the order's language, with
 * the carrier and the tracking number. Those come from the order's shipment (`Shipped::shipment()`): the carrier
 * integration supplies them (#291, Stage 4); until it does, the email says the tracking details follow.
 *
 * Register ids: NOTF-04. See specs/021-emails.
 */
final class ShippedEmail extends \WC_Email
{
    public const ID = 'mizzey_customer_shipped';

    public function __construct()
    {
        $this->id             = self::ID;
        $this->customer_email = true;
        $this->title          = __('Order shipped', 'mizzey-site');
        $this->description    = __('Sent to the customer when the order is handed to the carrier, with the carrier and the tracking number.', 'mizzey-site');
        $this->placeholders   = ['{order_number}' => ''];
        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __('Your order #{order_number} is on its way', 'mizzey-site');
    }

    public function get_default_heading(): string
    {
        return __('Your order has shipped', 'mizzey-site');
    }

    /** A subject staff set goes through WooCommerce's own path; the default follows the language of the moment. */
    public function get_subject()
    {
        if (trim((string) ($this->settings['subject'] ?? '')) !== '') {
            return parent::get_subject();
        }

        return apply_filters('woocommerce_email_subject_' . $this->id, $this->format_string($this->get_default_subject()), $this->object, $this);
    }

    public function get_heading()
    {
        if (trim((string) ($this->settings['heading'] ?? '')) !== '') {
            return parent::get_heading();
        }

        return apply_filters('woocommerce_email_heading_' . $this->id, $this->format_string($this->get_default_heading()), $this->object, $this);
    }

    /** The language is the order's, set by Shipped before sending; not the site's, as WC_Email would set. */
    public function setup_locale()
    {
    }

    public function restore_locale()
    {
    }

    public function trigger(\WC_Order $order): bool
    {
        $this->object                         = $order;
        $this->recipient                      = $order->get_billing_email();
        $this->placeholders['{order_number}'] = $order->get_order_number();

        return $this->is_enabled() && $this->get_recipient() !== ''
            && (bool) $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
    }

    /** @return list<string> The lines of the message, in the language of the moment. */
    private function lines(): array
    {
        $order    = $this->object;
        $shipment = $order instanceof \WC_Order ? Shipped::shipment($order) : ['carrier' => '', 'tracking' => '', 'url' => ''];
        $lines    = [
            sprintf(
                /* translators: %s: customer first name. */
                __('Hello %s,', 'mizzey-site'),
                $order instanceof \WC_Order ? $order->get_billing_first_name() : ''
            ),
            __('Your order has been handed to the carrier.', 'mizzey-site'),
        ];
        if ($shipment['carrier'] !== '' && $shipment['tracking'] !== '') {
            /* translators: 1: carrier name, 2: tracking number. */
            $lines[] = sprintf(__('Carrier: %1$s. Tracking number: %2$s.', 'mizzey-site'), $shipment['carrier'], $shipment['tracking']);
            if ($shipment['url'] !== '') {
                /* translators: %s: the tracking page's address. */
                $lines[] = sprintf(__('Track it here: %s', 'mizzey-site'), $shipment['url']);
            }
        } else {
            $lines[] = __('We will send you the tracking details as soon as the carrier gives them to us.', 'mizzey-site');
        }

        return $lines;
    }

    public function get_content_html(): string
    {
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        foreach ($this->lines() as $line) {
            printf('<p>%s</p>', esc_html($line));
        }
        do_action('woocommerce_email_footer', $this);

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        return implode("\n\n", $this->lines());
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

defined('ABSPATH') || exit;

/**
 * The review invitation: a WooCommerce email, listed with the store's other emails (WooCommerce, Settings, Emails),
 * where staff switch it on or off and edit its subject and heading. Sent to the order's billing email once its
 * delivery is confirmed (ReviewInvitation), in the order's language, linking each product's reviews in that language.
 * The invitation makes no one eligible to review (D-17); the shared email layout is PRE-03b's.
 *
 * Register ids: REV-05. See specs/020-reviews.
 */
final class ReviewInvitationEmail extends \WC_Email
{
    public const ID = 'mizzey_review_invitation';

    public function __construct()
    {
        $this->id             = self::ID;
        $this->customer_email = true;
        $this->title          = __('Review invitation', 'mizzey-site');
        $this->description    = __('Sent to the customer once an order is delivered, inviting a review of its products.', 'mizzey-site');
        $this->placeholders   = ['{order_number}' => ''];
        parent::__construct();
    }

    public function get_default_subject(): string
    {
        return __('How was your order #{order_number}?', 'mizzey-site');
    }

    public function get_default_heading(): string
    {
        return __('Tell us what you think', 'mizzey-site');
    }

    /** The language is the order's, set by ReviewInvitation before sending; not the site's, as WC_Email would set. */
    public function setup_locale()
    {
    }

    public function restore_locale()
    {
    }

    /** Sends the invitation for the order; false when it is switched off or has no one to go to. */
    public function trigger(\WC_Order $order): bool
    {
        $this->setup_locale();
        $this->object                         = $order;
        $this->recipient                      = $order->get_billing_email();
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $sent = $this->is_enabled() && $this->get_recipient() !== '' && $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        $this->restore_locale();

        return (bool) $sent;
    }

    public function get_content_html(): string
    {
        $order = $this->object;
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        printf('<p>%s</p>', esc_html(sprintf(
            /* translators: %s: customer first name. */
            __('Hello %s,', 'mizzey-site'),
            $order instanceof \WC_Order ? $order->get_billing_first_name() : ''
        )));
        printf('<p>%s</p><ul>', esc_html__('Your order has been delivered. We would love to hear what you think of it:', 'mizzey-site'));
        foreach (self::links($order) as $name => $url) {
            printf('<li><a href="%1$s">%2$s</a></li>', esc_url($url), esc_html($name));
        }
        echo '</ul>';
        do_action('woocommerce_email_footer', $this);

        return (string) ob_get_clean();
    }

    public function get_content_plain(): string
    {
        $order = $this->object;
        $lines = [
            sprintf(
                /* translators: %s: customer first name. */
                __('Hello %s,', 'mizzey-site'),
                $order instanceof \WC_Order ? $order->get_billing_first_name() : ''
            ),
            __('Your order has been delivered. We would love to hear what you think of it:', 'mizzey-site'),
        ];
        foreach (self::links($order) as $name => $url) {
            $lines[] = $name . ': ' . $url;
        }

        return implode("\n\n", $lines);
    }

    /**
     * Each product of the order, by its name in the order's language, with the address of its reviews in that language.
     *
     * @param mixed $order
     * @return array<string,string>
     */
    public static function links($order): array
    {
        if (! $order instanceof \WC_Order) {
            return [];
        }
        $language = (string) $order->get_meta('wpml_language');
        $links    = [];
        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $id      = (int) apply_filters('wpml_object_id', (int) $item->get_product_id(), 'product', true, $language !== '' ? $language : null);
            $product = wc_get_product($id);
            if ($product instanceof \WC_Product && $product->get_status() === 'publish') {
                $url = (string) apply_filters('wpml_permalink', get_permalink($id), $language !== '' ? $language : null);
                $links[$product->get_name()] = $url . '#reviews';
            }
        }

        return $links;
    }
}

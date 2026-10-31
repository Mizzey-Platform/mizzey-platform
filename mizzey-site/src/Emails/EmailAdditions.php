<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Emails;

use MizzeySite\Promotions\WelcomeDiscount;

defined('ABSPATH') || exit;

/**
 * What the store's own WooCommerce emails lack for the contracted notifications, added through each email's additional
 * content (`woocommerce_email_additional_content_{id}`), which both the HTML and the plain text versions print:
 *
 * - NOTF-01, account created: the welcome discount's code, what it gives and until when, when the account has one
 *   (#290's WelcomeDiscount); the verification email itself is #305's.
 * - NOTF-03, payment failed: a link to pay for the order again, the retry prompt WooCommerce's text only describes.
 *
 * The other contracted emails are WooCommerce's own: order confirmed (processing and on-hold, NOTF-02), delivered
 * (completed, NOTF-06, with the review invitation of specs/020) and refunded (NOTF-08). Each goes out in the order's
 * language through WooCommerce Multilingual.
 *
 * Register ids: NOTF-01, NOTF-03. See specs/021-emails.
 */
final class EmailAdditions
{
    public static function register(): void
    {
        add_filter('woocommerce_email_additional_content_customer_new_account', [self::class, 'welcome'], 20, 3);
        add_filter('woocommerce_email_additional_content_customer_failed_order', [self::class, 'retry'], 20, 3);
    }

    /**
     * @param mixed $content
     * @param mixed $user
     * @param mixed $email
     * @return mixed
     */
    public static function welcome($content, $user, $email)
    {
        $userId = $user instanceof \WP_User ? $user->ID : (is_numeric($user) ? (int) $user : 0);
        $code   = $userId > 0 ? WelcomeDiscount::codeFor($userId) : null;
        $coupon = $code !== null ? new \WC_Coupon($code) : null;
        // Only a code the customer can still use: not expired, and not used up.
        $expired = $coupon instanceof \WC_Coupon && $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time();
        $usedUp  = $coupon instanceof \WC_Coupon && $coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit();
        if (! $coupon instanceof \WC_Coupon || ! $coupon->get_id() || $expired || $usedUp) {
            return $content;
        }
        $value   = $coupon->is_type('percent')
            /* translators: %s: a percentage, such as 10. */
            ? sprintf(__('%s%% off', 'mizzey-site'), wc_format_localized_decimal($coupon->get_amount()))
            : wp_strip_all_tags(wc_price((float) $coupon->get_amount()));
        $expires = $coupon->get_date_expires();
        $line    = $expires
            /* translators: 1: what the discount gives, 2: the code, 3: the last day it can be used. */
            ? sprintf(__('Your welcome gift: %1$s your first order with the code %2$s, until %3$s.', 'mizzey-site'), $value, $coupon->get_code(), wc_format_datetime($expires))
            /* translators: 1: what the discount gives, 2: the code. */
            : sprintf(__('Your welcome gift: %1$s your first order with the code %2$s.', 'mizzey-site'), $value, $coupon->get_code());

        return trim((string) $content . "\n\n" . $line);
    }

    /**
     * @param mixed $content
     * @param mixed $order
     * @param mixed $email
     * @return mixed
     */
    public static function retry($content, $order, $email)
    {
        if (! $order instanceof \WC_Order || ! $order->needs_payment()) {
            return $content;
        }
        // A link in the HTML email, whose text is the address itself, so the plain text email (tags removed) keeps it.
        $url  = $order->get_checkout_payment_url();
        /* translators: %s: the address to pay for the order again. */
        $line = sprintf(__('To try again, pay for your order here: %s', 'mizzey-site'), sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($url)));

        return trim((string) $content . "\n\n" . $line);
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Reviews;

use MizzeySite\Orders\StatusModel;

defined('ABSPATH') || exit;

/**
 * A delivered order triggers a review invitation (REV-05, NOTF-06's invitation): when an order first enters a status
 * of the Delivered set (StatusModel: Completed, COD Collected), the invitation email is sent once, in the order's
 * language (WPML's order language, switched for the email's text). A guest purchaser receives it too; it makes no one
 * eligible to review (D-17).
 *
 * Register ids: REV-05. See specs/020-reviews.
 */
final class ReviewInvitation
{
    public const SENT = '_mizzey_review_invited';

    public static function register(): void
    {
        add_filter('woocommerce_email_classes', [self::class, 'email']);
        foreach (StatusModel::DELIVERED as $status) {
            add_action('woocommerce_order_status_' . $status, [self::class, 'delivered'], 20, 2);
        }
    }

    /**
     * @param mixed $emails
     * @return mixed
     */
    public static function email($emails)
    {
        if (is_array($emails) && class_exists('\WC_Email')) {
            require_once __DIR__ . '/ReviewInvitationEmail.php';
            $emails['MizzeySite_Review_Invitation'] = new ReviewInvitationEmail();
        }

        return $emails;
    }

    /**
     * @param mixed $orderId
     * @param mixed $order
     */
    public static function delivered($orderId, $order = null): void
    {
        global $wpdb;
        $id = $order instanceof \WC_Order ? $order->get_id() : (int) $orderId;
        // One invitation even when two requests deliver the order at once: the check, the sending and the mark under a
        // MySQL named lock per order, the order read afresh inside it.
        $lock = substr('mz_' . $wpdb->prefix . 'review_invite_' . $id, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        try {
            self::send($id);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    private static function send(int $id): void
    {
        // Read from storage under the lock: another request may have sent it while this one waited with the order
        // already loaded.
        clean_post_cache($id);
        if (class_exists('\Automattic\WooCommerce\Caches\OrderCache')) {
            wc_get_container()->get(\Automattic\WooCommerce\Caches\OrderCache::class)->remove($id);
        }
        wp_cache_delete($id, 'orders');
        $order = wc_get_order($id);
        if (! $order instanceof \WC_Order || $order->get_meta(self::SENT) !== '') {
            return;
        }
        $email = WC()->mailer()->get_emails()['MizzeySite_Review_Invitation'] ?? null;
        if (! $email instanceof ReviewInvitationEmail) {
            return;
        }
        $language = (string) $order->get_meta('wpml_language');
        $current  = (string) apply_filters('wpml_current_language', null);
        $locale   = (string) (((array) apply_filters('wpml_active_languages', null))[$language]['default_locale'] ?? '');
        if ($language !== '') {
            do_action('wpml_switch_language', $language);
        }
        $switched = $locale !== '' && switch_to_locale($locale);
        try {
            $sent = $email->trigger($order);
        } finally {
            if ($switched) {
                restore_previous_locale();
            }
            if ($language !== '') {
                do_action('wpml_switch_language', $current !== '' ? $current : null);
            }
        }
        if ($sent) {
            $order->update_meta_data(self::SENT, (string) time());
            $order->save_meta_data();
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\SecondRelease;

use MizzeySite\Accounts\MarketingConsent;

defined('ABSPATH') || exit;

/**
 * Notification preferences in My Account (IA-23, ACCT-11). Order emails (confirmation, shipping, refunds) always go:
 * they are part of the order. The customer chooses the rest: the review invitation after delivery, and offers and news
 * (the marketing consent, specs/017, recorded with when and where it changed).
 *
 * Offered only once the second release is on.
 *
 * Register ids: IA-23, ACCT-11. See specs/047-second-release.
 */
final class NotificationPreferences
{
    public const NO_REVIEWS = '_mizzey_no_review_invitations';
    public const NONCE      = 'mizzey_notification_preferences';

    public static function register(): void
    {
        add_action('woocommerce_edit_account_form', [self::class, 'fields'], 20);
        add_action('woocommerce_save_account_details', [self::class, 'save'], 20, 1);
        add_filter('mizzey_review_invitation_allowed', [self::class, 'reviews'], 10, 2);
    }

    public static function fields(): void
    {
        if (! ReleaseSwitch::on() || ! is_user_logged_in()) {
            return;
        }
        $id = get_current_user_id();
        printf('<fieldset class="mizzey-preferences"><legend>%s</legend><p>%s</p>', esc_html__('Emails we send you', 'mizzey-site'), esc_html__('Emails about your orders always arrive. Choose the others:', 'mizzey-site'));
        printf('<p><label><input type="checkbox" name="mizzey_pref_reviews" value="yes"%s /> %s</label></p>', checked(get_user_meta($id, self::NO_REVIEWS, true) !== 'yes', true, false), esc_html__('Ask me to review a product after it arrives', 'mizzey-site'));
        printf('<p><label><input type="checkbox" name="mizzey_pref_offers" value="yes"%s /> %s</label></p>', checked(MarketingConsent::given($id), true, false), esc_html__('Offers and news', 'mizzey-site'));
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        echo '</fieldset>';
    }

    /** @param mixed $userId */
    public static function save($userId): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if (! ReleaseSwitch::on() || (int) $userId !== get_current_user_id() || ! wp_verify_nonce($nonce, self::NONCE)) {
            return;
        }
        $reviews = isset($_POST['mizzey_pref_reviews']) && sanitize_key(wp_unslash((string) $_POST['mizzey_pref_reviews'])) === 'yes';
        $offers  = isset($_POST['mizzey_pref_offers']) && sanitize_key(wp_unslash((string) $_POST['mizzey_pref_offers'])) === 'yes';
        $reviews ? delete_user_meta((int) $userId, self::NO_REVIEWS) : update_user_meta((int) $userId, self::NO_REVIEWS, 'yes');
        if ($offers !== MarketingConsent::given((int) $userId)) {
            MarketingConsent::record((int) $userId, $offers, 'account-preferences');
        }
    }

    /**
     * @param mixed $allowed
     * @param mixed $order
     * @return mixed
     */
    public static function reviews($allowed, $order)
    {
        if (! $order instanceof \WC_Order || (int) $order->get_customer_id() <= 0) {
            return $allowed;
        }

        return get_user_meta((int) $order->get_customer_id(), self::NO_REVIEWS, true) === 'yes' ? false : $allowed;
    }
}

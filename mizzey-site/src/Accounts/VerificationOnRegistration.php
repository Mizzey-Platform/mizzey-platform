<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

defined('ABSPATH') || exit;

/**
 * Email verification is sent when a customer account is created (AUTH-05).
 *
 * WooCommerce 11 verifies customer emails itself (`Internal\CustomerEmailVerification`): a one-time key bound to the
 * account email, valid a day, a "verify your email" prompt on the account page with a rate-limited resend, the
 * verified state on the account (`_wc_email_verified`), and, once verified, the customer's earlier guest orders linked
 * to the account (`wc_update_new_customer_past_orders`, B14: only after the email is verified). It sends the email only
 * when the customer asks from the account page; this sends it once at registration as well, through WooCommerce's own
 * controller, when its verification email is enabled. An account made from a guest's order confirmation is verified
 * by WooCommerce when its set-password link is used, so it is sent nothing here.
 *
 * Register ids: AUTH-05, AUTH-08. See specs/017-accounts.
 */
final class VerificationOnRegistration
{
    public static function register(): void
    {
        add_action('woocommerce_created_customer', [self::class, 'send'], 20, 1);
    }

    /** @param mixed $customerId */
    public static function send($customerId): void
    {
        $id   = (int) $customerId;
        $user = $id > 0 ? get_userdata($id) : false;
        if (! $user instanceof \WP_User || ! in_array('customer', (array) $user->roles, true) || ! self::enabled()) {
            return;
        }
        $controller = self::controller();
        if ($controller !== null && ! get_user_option('default_password_nag', $id)) {
            $controller->send_verification_email($id);
        }
    }

    public static function enabled(): bool
    {
        $setting = get_option('woocommerce_customer_verify_email_settings', []);

        return 'yes' === (is_array($setting) ? ($setting['enabled'] ?? 'yes') : 'yes');
    }

    /** Whether the account's email is verified, as WooCommerce records it. */
    public static function verified(int $userId): bool
    {
        $service = self::service();

        return $service !== null && $service->is_verified($userId);
    }

    private static function controller(): ?object
    {
        $class = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\VerificationController';

        return class_exists($class) && function_exists('wc_get_container') ? wc_get_container()->get($class) : null;
    }

    private static function service(): ?object
    {
        $class = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\EmailVerificationService';

        return class_exists($class) && function_exists('wc_get_container') ? wc_get_container()->get($class) : null;
    }
}

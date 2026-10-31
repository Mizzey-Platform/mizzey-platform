<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Accounts;

defined('ABSPATH') || exit;

/**
 * Registration with a phone number, at the reduced scope the ownership audit recorded for AUTH-02 (5 October 2026):
 * the phone number is captured at registration and can be used to sign in, with no SMS code (SMS is P2, INT-07).
 *
 * The registration form offers an optional mobile number; it is kept as the account's WooCommerce billing phone in the
 * Egyptian mobile form (`01` and nine digits; `+20` and `0020` prefixes accepted), and one number belongs to one account.
 * At sign-in, a mobile number given in place of the email or username signs in to the one account that holds it, with
 * that account's password; a number held by no account, or ambiguously, signs in to nothing, with WordPress's own
 * message.
 *
 * Register ids: AUTH-02. See specs/017-accounts.
 */
final class PhoneSignIn
{
    public const FIELD = 'billing_phone';

    public static function register(): void
    {
        add_action('woocommerce_register_form_start', [self::class, 'field'], 20);
        add_filter('woocommerce_registration_errors', [self::class, 'validate'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'save'], 10, 1);
        add_filter('authenticate', [self::class, 'authenticate'], 19, 3);
    }

    public static function field(): void
    {
        printf(
            '<p class="woocommerce-form-row form-row"><label for="reg_billing_phone">%1$s <span class="optional">%2$s</span></label><input type="tel" class="woocommerce-Input input-text" name="%3$s" id="reg_billing_phone" autocomplete="tel" dir="ltr" /></p>',
            esc_html__('Mobile number', 'mizzey-site'),
            esc_html__('(optional)', 'mizzey-site'),
            esc_attr(self::FIELD)
        );
    }

    /** The Egyptian mobile form of a number, or null when it is not one. */
    public static function normalise(string $phone): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', $phone) ?? '';
        if (str_starts_with($digits, '+20')) {
            $digits = '0' . substr($digits, 3);
        } elseif (str_starts_with($digits, '0020')) {
            $digits = '0' . substr($digits, 4);
        }

        return preg_match('/^01[0125][0-9]{8}$/', $digits) === 1 ? $digits : null;
    }

    /** @return list<int> The accounts holding the number. */
    public static function holders(string $phone): array
    {
        $normal = self::normalise($phone);
        if ($normal === null) {
            return [];
        }

        return array_map('intval', get_users(['meta_key' => self::FIELD, 'meta_value' => $normal, 'fields' => 'ID', 'number' => 2])); // phpcs:ignore WordPress.DB.SlowDBQuery
    }

    /**
     * @param mixed $errors
     * @param mixed $username
     * @param mixed $email
     * @return mixed
     */
    public static function validate($errors, $username, $email)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the registration form's nonce.
        $given = isset($_POST[self::FIELD]) ? trim(sanitize_text_field(wp_unslash((string) $_POST[self::FIELD]))) : '';
        if ($given === '' || ! $errors instanceof \WP_Error) {
            return $errors;
        }
        if (self::normalise($given) === null) {
            $errors->add('mizzey_phone_invalid', __('Please enter a mobile number such as 01012345678.', 'mizzey-site'));
        } elseif (self::holders($given) !== []) {
            $errors->add('mizzey_phone_taken', __('This mobile number is already used by another account.', 'mizzey-site'));
        }

        return $errors;
    }

    /** @param mixed $customerId */
    public static function save($customerId): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the registration form's nonce.
        $given  = isset($_POST[self::FIELD]) ? sanitize_text_field(wp_unslash((string) $_POST[self::FIELD])) : '';
        $normal = $given !== '' ? self::normalise($given) : null;
        if ((int) $customerId > 0 && $normal !== null) {
            update_user_meta((int) $customerId, self::FIELD, $normal);
        }
    }

    /**
     * A mobile number in place of the email or username: the one account holding it, with its password.
     *
     * @param mixed $user
     * @param mixed $username
     * @param mixed $password
     * @return mixed
     */
    public static function authenticate($user, $username, $password)
    {
        if ($user instanceof \WP_User || ! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            return $user;
        }
        if (self::normalise($username) === null || get_user_by('login', $username)) {
            return $user;
        }
        $holders = self::holders($username);
        if (count($holders) !== 1) {
            return $user;
        }
        $account = get_userdata($holders[0]);

        return $account instanceof \WP_User ? wp_authenticate_username_password(null, $account->user_login, $password) : $user;
    }
}

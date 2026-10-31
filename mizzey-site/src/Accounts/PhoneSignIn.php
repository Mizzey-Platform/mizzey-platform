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
 * Egyptian mobile form (`01` and nine digits; `+20` and `0020` prefixes accepted). One number signs in to one account:
 * the first account to hold it claims it, in one insert that a second account cannot also win, whatever wrote the
 * billing phone (registration, the account's address form, the admin, the REST API). An account whose number another
 * account already holds keeps the number for delivery and cannot sign in with it.
 *
 * At sign-in, a mobile number in any accepted spelling is replaced by its account's username before the password is
 * checked (`wp_authenticate`), so CoreX's lockout counts every spelling, and the username, as one identity. A number
 * no account holds is reduced to its one spelling, so its failures are counted together too. A mobile number wins over
 * a username spelled like one, and no new customer is given a username spelled like a mobile number.
 *
 * Register ids: AUTH-02. See specs/017-accounts.
 */
final class PhoneSignIn
{
    public const FIELD = 'billing_phone';
    /** The number this account holds for sign-in, when it holds the claim. */
    public const HELD = '_mizzey_phone';
    public const CLAIM = 'mizzey_phone';

    public static function register(): void
    {
        add_action('woocommerce_register_form_start', [self::class, 'field'], 20);
        add_filter('woocommerce_registration_errors', [self::class, 'validate'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'save'], 10, 1);
        add_action('added_user_meta', [self::class, 'changed'], 10, 4);
        add_action('updated_user_meta', [self::class, 'changed'], 10, 4);
        add_action('deleted_user_meta', [self::class, 'removed'], 10, 3);
        add_action('deleted_user', [self::class, 'release'], 10, 1);
        add_action('wp_authenticate', [self::class, 'identity'], 0, 1);
        add_filter('authenticate', [self::class, 'authenticate'], 19, 3);
        add_filter('woocommerce_new_customer_username', [self::class, 'username'], 10, 1);
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

    /** The account that signs in with the number, or null. */
    public static function holder(string $phone): ?int
    {
        $normal = self::normalise($phone);
        $userId = $normal !== null ? (int) get_option(self::claimName($normal), 0) : 0;

        return $userId > 0 && get_user_meta($userId, self::HELD, true) === $normal ? $userId : null;
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
        // As WooCommerce's own form says when an email has an account, this says when a number has one.
        if (self::normalise($given) === null) {
            $errors->add('mizzey_phone_invalid', __('Please enter a mobile number such as 01012345678.', 'mizzey-site'));
        } elseif (self::holder($given) !== null) {
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
     * Whatever wrote an account's billing phone: its claim follows the number.
     *
     * @param mixed $metaId
     * @param mixed $userId
     * @param mixed $key
     * @param mixed $value
     */
    public static function changed($metaId, $userId, $key, $value): void
    {
        if ($key !== self::FIELD) {
            return;
        }
        $userId = (int) $userId;
        $normal = is_string($value) ? self::normalise($value) : null;
        if ($normal !== null && get_user_meta($userId, self::HELD, true) === $normal) {
            return;
        }
        self::release($userId);
        if ($normal !== null && self::claim($normal, $userId)) {
            update_user_meta($userId, self::HELD, $normal);
        }
    }

    /**
     * @param mixed $metaIds
     * @param mixed $userId
     * @param mixed $key
     */
    public static function removed($metaIds, $userId, $key): void
    {
        if ($key === self::FIELD) {
            self::release((int) $userId);
        }
    }

    /** @param mixed $userId */
    public static function release($userId): void
    {
        $userId = (int) $userId;
        $held   = (string) get_user_meta($userId, self::HELD, true);
        if ($held !== '' && (int) get_option(self::claimName($held), 0) === $userId) {
            delete_option(self::claimName($held));
        }
        delete_user_meta($userId, self::HELD);
    }

    /** One insert that only one account can win; false when the number is already claimed. */
    private static function claim(string $normal, int $userId): bool
    {
        global $wpdb;
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::claimName($normal), (string) $userId)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete(self::claimName($normal), 'options');

        return $inserted === 1;
    }

    private static function claimName(string $normal): string
    {
        return self::CLAIM . '_' . $normal;
    }

    /**
     * The sign-in identity: a mobile number in any spelling becomes its account's username, or its one spelling when
     * no account holds it, before the password is checked and before CoreX counts a failure.
     *
     * @param mixed $login Passed by reference by `wp_signon()`.
     */
    public static function identity(&$login): void
    {
        if (is_string($login)) {
            $login = self::canonical($login);
        }
    }

    public static function canonical(string $login): string
    {
        $normal = self::normalise($login);
        if ($normal === null) {
            return $login;
        }
        $holder  = self::holder($normal);
        $account = $holder !== null ? get_userdata($holder) : false;

        return $account instanceof \WP_User ? $account->user_login : $normal;
    }

    /**
     * Sign-in through `wp_authenticate()` without `wp_signon()`: a mobile number signs in to the account holding it.
     *
     * @param mixed $user
     * @param mixed $username
     * @param mixed $password
     * @return mixed
     */
    public static function authenticate($user, $username, $password)
    {
        if ($user instanceof \WP_User || ! is_string($username) || ! is_string($password) || $password === '' || self::normalise($username) === null) {
            return $user;
        }
        $holder  = self::holder($username);
        $account = $holder !== null ? get_userdata($holder) : false;

        return $account instanceof \WP_User ? wp_authenticate_username_password(null, $account->user_login, $password) : $user;
    }

    /**
     * A generated username is never spelled like a mobile number.
     *
     * @param mixed $username
     * @return mixed
     */
    public static function username($username)
    {
        return is_string($username) && self::normalise($username) !== null ? $username . '-' . wc_strtolower(wp_generate_password(4, false)) : $username;
    }
}

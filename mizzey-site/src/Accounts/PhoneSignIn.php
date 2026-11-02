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
 * account already holds keeps the number for delivery and cannot sign in with it; registration does not say so, so the
 * form cannot be used to learn whether a number belongs to a customer. Numbers saved before this existed are claimed
 * once (`backfill()`), each by the one account holding it; a number several accounts hold is claimed by none.
 *
 * A number no account holds costs the same password work at sign-in as one that does, so the time taken does not say
 * which it is.
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
    public const BACKFILLED = 'mizzey_phone_claims_backfilled';
    /** The claim's value for a number several accounts held before claims existed: reserved, so nobody signs in with it. */
    public const RESERVED = '0';

    /** The username identity() resolved a number to in this request, which is not read as a number again. */
    private static ?string $resolved = null;

    public static function register(): void
    {
        add_action('woocommerce_register_form_start', [self::class, 'field'], 20);
        add_filter('woocommerce_registration_errors', [self::class, 'validate'], 10, 3);
        add_action('woocommerce_created_customer', [self::class, 'save'], 10, 1);
        add_action('added_user_meta', [self::class, 'changed'], 10, 4);
        add_action('updated_user_meta', [self::class, 'changed'], 10, 4);
        add_action('deleted_user_meta', [self::class, 'removed'], 10, 3);
        // Before the account's meta is deleted, so its claim can be found and released.
        add_action('delete_user', [self::class, 'release'], 10, 1);
        add_action('wp_authenticate', [self::class, 'identity'], 0, 1);
        add_filter('authenticate', [self::class, 'authenticate'], 19, 3);
        add_filter('woocommerce_new_customer_username', [self::class, 'username'], 10, 1);
        add_action('init', [self::class, 'backfill'], 20);
    }

    /** Claims the numbers saved before claims existed, once: each by its one holder, none held by several. */
    public static function backfill(): void
    {
        if (get_option(self::BACKFILLED) === 'yes') {
            return;
        }
        global $wpdb;
        $rows    = $wpdb->get_results($wpdb->prepare("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY user_id", self::FIELD)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $holders = [];
        foreach ((array) $rows as $row) {
            $normal = self::normalise((string) $row->meta_value);
            if ($normal !== null) {
                $holders[$normal][] = (int) $row->user_id;
            }
        }
        foreach ($holders as $normal => $users) {
            // claim() keeps a claim another account holds and takes over one whose owner no longer holds it. A number
            // several accounts hold is reserved for none until staff remove the reservation.
            if (count(array_unique($users)) > 1) {
                global $wpdb;
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::claimName($normal), self::RESERVED)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                wp_cache_delete(self::claimName($normal), 'options');
                wp_cache_delete('notoptions', 'options');
            } else {
                // The same locked path as any phone change, reading the account's phone as it is now.
                $userId = $users[0];
                self::locked('phone_user_' . $userId, static fn () => self::follow($userId));
            }
        }
        update_option(self::BACKFILLED, 'yes', false);
    }

    public static function field(): void
    {
        printf(
            '<p class="woocommerce-form-row form-row"><label for="reg_billing_phone">%s <span class="optional">%s</span></label><input type="tel" class="woocommerce-Input input-text" name="%s" id="reg_billing_phone" autocomplete="tel" dir="ltr" /></p>',
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

    /** The account that signs in with the number, or null: the claim's owner, while its saved phone is still the number. */
    public static function holder(string $phone): ?int
    {
        $normal = self::normalise($phone);
        $userId = $normal !== null ? (int) get_option(self::claimName($normal), 0) : 0;

        return $userId > 0 && get_user_meta($userId, self::HELD, true) === $normal
            && self::normalise((string) get_user_meta($userId, self::FIELD, true)) === $normal ? $userId : null;
    }

    /**
     * Runs `$work` holding a MySQL named lock, so two requests changing the same number or account take turns.
     * Without the lock (a timeout), the work still runs: holder() checks the saved phone, so a binding that loses a race
     * signs in to nobody rather than the wrong account.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    private static function locked(string $name, callable $work)
    {
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . $name, 0, 64);
        $held = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) === 1; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        try {
            return $work();
        } finally {
            if ($held) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            }
        }
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
        // A number another account holds is not refused here, which would say it belongs to a customer: the new
        // account keeps it for delivery and does not sign in with it (changed()).
        if (self::normalise($given) === null) {
            $errors->add('mizzey_phone_invalid', __('Please enter a mobile number such as 01012345678.', 'mizzey-site'));
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
        self::locked('phone_user_' . $userId, static fn () => self::follow($userId));
    }

    /** The account's claim follows its phone: the old number released, the new one claimed, under the account's lock. */
    private static function follow(int $userId): void
    {
        // The saved value, read under the lock, not the value this write carried, which a later write may have replaced.
        wp_cache_delete($userId, 'user_meta');
        $normal = self::normalise((string) get_user_meta($userId, self::FIELD, true));
        if ($normal !== null && get_user_meta($userId, self::HELD, true) === $normal) {
            return;
        }
        self::release($userId);
        if ($normal !== null && self::claim($normal, $userId)) {
            // Another write to this account's phone may have landed meanwhile: the claim follows only the saved value.
            wp_cache_delete($userId, 'user_meta');
            if (self::normalise((string) get_user_meta($userId, self::FIELD, true)) === $normal) {
                update_user_meta($userId, self::HELD, $normal);
            } else {
                global $wpdb;
                $wpdb->delete($wpdb->options, ['option_name' => self::claimName($normal), 'option_value' => (string) $userId]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                wp_cache_delete(self::claimName($normal), 'options');
            }
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
        global $wpdb;
        $userId = (int) $userId;
        $held   = (string) get_user_meta($userId, self::HELD, true);
        if ($held !== '') {
            // Only while this account still owns the claim: one delete naming both, so a release that overlaps another
            // account's new claim cannot remove it.
            $wpdb->delete($wpdb->options, ['option_name' => self::claimName($held), 'option_value' => (string) $userId]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            wp_cache_delete(self::claimName($held), 'options');
            wp_cache_delete('alloptions', 'options');
        }
        delete_user_meta($userId, self::HELD);
    }

    /**
     * One insert that only one account can win; false when another account holds the number. A claim whose owner no
     * longer holds the number (an account deleted or changed without its release) is taken over by one update naming
     * that owner, which only one account can win too.
     */
    private static function claim(string $normal, int $userId): bool
    {
        return self::locked('phone_num_' . $normal, static fn (): bool => self::claimUnlocked($normal, $userId));
    }

    private static function claimUnlocked(string $normal, int $userId): bool
    {
        global $wpdb;
        $name     = self::claimName($normal);
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) $userId)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $won      = $inserted === 1;
        if (! $won) {
            $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            // Stale when the owner's saved billing phone is no longer the number (or the account is gone): a claim still
            // being completed is not, because the owner's phone was saved before it claimed. A reservation never is.
            wp_cache_delete($owner, 'user_meta');
            $stale = $owner > 0 && $owner !== $userId && self::normalise((string) get_user_meta($owner, self::FIELD, true)) !== $normal;
            $won   = $owner === $userId || ($stale && $wpdb->update($wpdb->options, ['option_value' => (string) $userId], ['option_name' => $name, 'option_value' => (string) $owner]) === 1); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete($name, 'options');
        wp_cache_delete('alloptions', 'options');

        return $won;
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
            $login           = self::canonical($login);
            self::$resolved  = $login;
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
        // Resolved once: the username identity() gave is an account's, not a number to resolve again.
        if (self::$resolved !== null && $username === self::$resolved && get_user_by('login', $username)) {
            return $user;
        }
        $holder  = self::holder($username);
        $account = $holder !== null ? get_userdata($holder) : false;
        if (! $account instanceof \WP_User) {
            // The password work a held number costs, so the time does not say the number is unknown.
            wp_check_password($password, self::decoy());

            return $user;
        }

        return wp_authenticate_username_password(null, $account->user_login, $password);
    }

    /** A password hash no password matches, made once, for the work an unknown number costs. */
    private static function decoy(): string
    {
        $decoy = (string) get_option('mizzey_phone_decoy_hash', '');
        if ($decoy === '') {
            $decoy = wp_hash_password(wp_generate_password(32, true, true));
            update_option('mizzey_phone_decoy_hash', $decoy, true);
        }

        return $decoy;
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

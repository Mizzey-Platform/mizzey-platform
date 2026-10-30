<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Request;
use WP_User;

/**
 * The state of the security baseline, computed from the live hooks.
 *
 * It checks that each control's hook is registered and, where it can without side effects, that the policy behaves.
 * It does not establish every request path or catch every override by another plugin: the integration scenarios
 * verify the behaviour a visitor meets. The configuration facts the site depends on are reported beside them and never
 * judged here; CoreX login protection is AUTH-15's (#305), reported so its state is visible.
 *
 * Register ids: NFR-05, US-27-03 (the controls are listed for the handover). See specs/007-security-baseline, FR-008.
 */
final class BaselineReport
{
    /** The controls of this feature; the security check fails when one of them does not pass. */
    public const CONTROLS = [
        'generator',
        'xmlrpc',
        'account_discovery',
        'application_passwords',
        'sign_in_message',
        'recovery_limit',
        'comment_surface',
        'two_factor_active',
        'two_factor_roles',
        'two_factor_bypass',
        'two_factor_key',
        'sign_in_limit',
        'signup_check',
        'account_writers',
        'wordpress_registration',
    ];

    /** The WP 2FA release this integration was built and tested against (stack.lock.json). */
    public const TWO_FACTOR_VERSION = '4.2.1';

    /** The baseline roles of D-21. */
    public const TWO_FACTOR_ROLES = ['administrator', 'shop_manager'];

    /**
     * @return list<array{key:string,status:string,detail:string}>
     */
    public static function rows(): array
    {
        return [
            self::row('generator', self::generatorHidden(), __('No WordPress, WooCommerce, CoreX or WPML generator mark', 'mizzey-site')),
            self::row('xmlrpc', self::xmlRpcClosed(), __('XML-RPC refused before dispatch; no method registered', 'mizzey-site')),
            self::row('account_discovery', self::accountsHidden(), __('Visitors cannot list users or resolve ?author= numbers; no users sitemap', 'mizzey-site')),
            self::row('application_passwords', self::appPasswordsAdminOnly(), __('Application passwords for administrators only', 'mizzey-site')),
            self::row('sign_in_message', self::genericSignIn(), __('One sign-in failure message', 'mizzey-site')),
            self::row('recovery_limit', has_action('lostpassword_post', [RecoveryLimiter::class, 'limit']) !== false, self::limitsDetail()),
            self::row('comment_surface', self::commentsOnlyOnProducts(), __('Comments only on products; pings closed', 'mizzey-site')),
            self::fact('file_changes_disabled', defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS, 'DISALLOW_FILE_MODS'),
            self::fact('file_editor_disabled', defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT, 'DISALLOW_FILE_EDIT'),
            self::fact('debug_display_off', ! (defined('WP_DEBUG') && WP_DEBUG) || (defined('WP_DEBUG_DISPLAY') && ! WP_DEBUG_DISPLAY), __('Errors are not shown to visitors', 'mizzey-site')),
            self::row('two_factor_active', self::twoFactorActive(), sprintf(
                /* translators: %s: the WP 2FA version. */
                __('WP 2FA %s active, its sign-in challenge and the enrolment guard hooked', 'mizzey-site'),
                self::TWO_FACTOR_VERSION,
            )),
            self::row('two_factor_roles', self::twoFactorPolicyEnforced(), __('Two-factor required for administrators and shop managers, authenticator app and backup codes, no grace period, no exclusions', 'mizzey-site')),
            self::row('two_factor_bypass', ! (defined('DISABLE_2FA_LOGIN') && DISABLE_2FA_LOGIN), __('No emergency bypass (DISABLE_2FA_LOGIN)', 'mizzey-site')),
            self::row('two_factor_key', self::twoFactorKeyInConfiguration(), __('Encryption key provided by configuration; no copy in the database', 'mizzey-site')),
            self::pendingStaff(),
            self::signInLimit(),
            self::row('signup_check', SignupRoutes::attached() && SignupRoutes::selfTest(), __('Account creation checked on the account page, the checkout, the order-received offer and WordPress registration; the check works', 'mizzey-site')),
            self::accountWriters(),
            self::row('wordpress_registration', self::wordpressRegistrationGuarded(), get_option('users_can_register') ? __('WordPress registration is open and checked', 'mizzey-site') : __('WordPress registration is closed', 'mizzey-site')),
        ];
    }

    private static function twoFactorActive(): bool
    {
        return defined('WP_2FA_VERSION') && WP_2FA_VERSION === self::TWO_FACTOR_VERSION
            && has_action('wp_login', ['WP2FA\Authenticator\Login', 'wp_login']) !== false
            && has_filter('rest_authentication_errors', [TwoFactor::class, 'guardRest']) === 110
            && has_action('admin_init', [TwoFactor::class, 'guardAdministration']) === 0;
    }

    /**
     * The stored policy is the D-21 baseline: both roles, no exclusions, an authenticator app and backup codes, no email
     * codes or passkeys (the enrolment guard allows only the authenticator's set-up), no grace period, set-up at once.
     * Read from WP 2FA's own getter.
     */
    private static function twoFactorPolicyEnforced(): bool
    {
        if (! class_exists('\WP2FA\WP2FA')) {
            return false;
        }
        $policy = \WP2FA\WP2FA::get_policy_settings();
        if (! is_array($policy)) {
            return false;
        }
        $roles = array_values((array) ($policy['enforced_roles'] ?? []));

        return in_array($policy['enforcement-policy'] ?? '', ['certain-roles-only', 'all-users'], true)
            && ($policy['enforcement-policy'] === 'all-users' || array_diff(self::TWO_FACTOR_ROLES, $roles) === [])
            && (array) ($policy['excluded_roles'] ?? []) === []
            && (array) ($policy['excluded_users'] ?? []) === []
            && ($policy['enable_totp'] ?? '') === 'enable_totp'
            && empty($policy['enable_email'])
            && empty($policy['enable_passkeys'])
            && (array) ($policy['enable_passkeys_certain_roles'] ?? []) === []
            && ($policy['backup_codes_enabled'] ?? '') === 'yes'
            && ($policy['grace-policy'] ?? '') === 'no-grace-period'
            && ($policy['grace-policy-after-expire-action'] ?? '') === 'configure-right-away';
    }

    /**
     * The key is configuration's, and nothing of it is in the database: WP 2FA defines the constant itself when it falls
     * back to a stored key, so the constant alone proves nothing. Existence only; the value is never read.
     */
    private static function twoFactorKeyInConfiguration(): bool
    {
        global $wpdb;
        $stored = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s", 'wp_2fa_secret_key')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return defined('WP2FA_ENCRYPT_KEY') && is_string(WP2FA_ENCRYPT_KEY) && WP2FA_ENCRYPT_KEY !== ''
            && ! defined('WP2FA_SECRET_IS_IN_DB')
            && $stored === 0;
    }

    /**
     * The staff WP 2FA's policy in force requires to enrol who have not. Judged with WP 2FA's own reporting call, which
     * reads the live policy and writes nothing: the state WP 2FA keeps per user is refreshed for only the first user read
     * in a process, so after a policy change it is stale for the rest. Counted as pending when it cannot be told.
     *
     * @return array{key:string,status:string,detail:string}
     */
    private static function pendingStaff(): array
    {
        $pending = array_filter(
            get_users(['role__in' => self::TWO_FACTOR_ROLES, 'number' => 500]),
            static fn (\WP_User $user): bool => TwoFactor::active() && self::mustEnrol($user),
        );

        return ['key' => 'two_factor_pending_staff', 'status' => (string) count($pending), 'detail' => sprintf(
            /* translators: %s: the logins of the staff still to enrol, or none. */
            __('Staff still to set up two-factor sign-in: %s (reported, not judged here)', 'mizzey-site'),
            $pending === [] ? __('none', 'mizzey-site') : implode(', ', array_map(static fn (\WP_User $u): string => $u->user_login, $pending)),
        )];
    }

    private static function mustEnrol(\WP_User $user): bool
    {
        $helper = '\WP2FA\Admin\Helpers\User_Helper';
        if (! class_exists($helper)) {
            return true;
        }
        try {
            // Without a user object, WP 2FA answers from the policy and the user's enabled methods, as its reports do.
            return (bool) $helper::user_must_configure_2fa(null, (array) $user->roles, $user->user_login, $user->ID);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{key:string,status:string,detail:string}
     */
    private static function row(string $key, bool $ok, string $detail): array
    {
        return ['key' => $key, 'status' => $ok ? 'pass' : 'review', 'detail' => $detail];
    }

    /**
     * @return array{key:string,status:string,detail:string}
     */
    private static function fact(string $key, bool $on, string $detail): array
    {
        /* translators: %s: the configuration fact, such as a constant's name. */
        return ['key' => $key, 'status' => $on ? 'on' : 'off', 'detail' => sprintf(__('%s (reported, not judged here)', 'mizzey-site'), $detail)];
    }

    private static function generatorHidden(): bool
    {
        return apply_filters('the_generator', '<meta name="generator" />', 'xhtml') === ''
            && has_action('wp_head', 'wp_generator') === false
            && has_action('wp_head', [Disclosure::class, 'removeWpmlGenerator']) !== false
            && ! apply_filters('corex_generator_tag', true);
    }

    private static function xmlRpcClosed(): bool
    {
        return has_action('init', [XmlRpc::class, 'refuse']) === 0
            && apply_filters('xmlrpc_methods', ['pingback.ping' => 'this:pingback_ping']) === [];
    }

    private static function accountsHidden(): bool
    {
        $previous = get_current_user_id();
        try {
            wp_set_current_user(0);
            $status  = rest_do_request(new WP_REST_Request('GET', '/wp/v2/users'))->get_status();
            $vars    = AccountDiscovery::noNumericAuthor(['author' => '1']);
            $sitemap = apply_filters('wp_sitemaps_add_provider', new \WP_Sitemaps_Users(), 'users');

            return in_array($status, [401, 403], true)
                && has_filter('request', [AccountDiscovery::class, 'noNumericAuthor']) !== false
                && $vars === ['error' => '404']
                && $sitemap === false;
        } finally {
            wp_set_current_user($previous);
        }
    }

    private static function appPasswordsAdminOnly(): bool
    {
        $customer          = new WP_User();
        $customer->allcaps = ['read' => true];

        return ! apply_filters('wp_is_application_passwords_available_for_user', true, $customer);
    }

    private static function genericSignIn(): bool
    {
        $answers = array_map(
            static fn (string $code): string => (string) apply_filters('authenticate', new WP_Error($code, $code), '', '')->get_error_code(),
            ['invalid_username', 'incorrect_password'],
        );

        return count(array_unique($answers)) === 1 && ! in_array('incorrect_password', $answers, true);
    }

    private static function commentsOnlyOnProducts(): bool
    {
        return ! apply_filters('comments_open', true, 0) && ! apply_filters('pings_open', true, 0);
    }

    /**
     * CoreX's sign-in limit: on, its enforcer attached, and WordPress's default sign-in address served. The numbers
     * are reported, not judged: after the one-time baseline they are the owner's (specs/009-sign-in-protection, FR-007).
     *
     * @return array{key:string,status:string,detail:string}
     */
    private static function signInLimit(): array
    {
        $storeClass = '\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore';
        if (! class_exists($storeClass)) {
            return self::row('sign_in_limit', false, __('CoreX login protection is not available', 'mizzey-site'));
        }
        try {
            $settings = (new $storeClass())->current();
        } catch (\Throwable) {
            return self::row('sign_in_limit', false, __('CoreX login protection settings are not readable', 'mizzey-site'));
        }
        $enforced = false;
        foreach ((array) ($GLOBALS['wp_filter']['authenticate']->callbacks[30] ?? []) as $callback) {
            if (is_array($callback['function'] ?? null) && $callback['function'][0] instanceof \Corex\Config\Security\LoginProtection\LoginProtectionEnforcer) {
                $enforced = true;
            }
        }

        return self::row('sign_in_limit', $settings->enabled && $enforced && ! $settings->blockDefaultEndpoints, sprintf(
            /* translators: 1: failures, 2: window in seconds, 3: lockout in seconds. */
            __('Sign-in limit: %1$d failures in %2$d s lock that identity and address for %3$d s; default sign-in address served', 'mizzey-site'),
            $settings->threshold,
            $settings->windowSeconds,
            $settings->lockoutSeconds,
        ));
    }

    /**
     * @return array{key:string,status:string,detail:string}
     */
    private static function accountWriters(): array
    {
        $writers = SignupRoutes::writers();
        $list    = implode(', ', array_map(static fn (string $name, string $state): string => $name . ' ' . $state, array_keys($writers), $writers));

        return self::row('account_writers', ! in_array('unchecked', $writers, true), sprintf(
            /* translators: %s: each place accounts can be created, with its state. */
            __('Places accounts can be created: %s', 'mizzey-site'),
            $list,
        ));
    }

    private static function wordpressRegistrationGuarded(): bool
    {
        return ! get_option('users_can_register') || has_filter('registration_errors', [SignupRoutes::class, 'wordpress']) !== false;
    }

    private static function limitsDetail(): string
    {
        $limits = RecoveryLimiter::limits();

        return sprintf(
            /* translators: 1: requests per account, 2: requests per client address, 3: the window in minutes. */
            __('Recovery limited to %1$d per account and %2$d per address in %3$d minutes', 'mizzey-site'),
            $limits['account'],
            $limits['address'],
            (int) ($limits['window'] / 60),
        );
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use Throwable;
use WP_Error;
use WP_User;

/**
 * A staff member who must set up two-factor sign-in can do nothing privileged until they have.
 *
 * WP 2FA (the approved component for ADM-131) sends a staff member who must enrol to its set-up screen, but only for some
 * administration pages and only under some timing, and never for the REST API or background requests: the first-factor
 * session it leaves behind can still act there. This closes that gap. While WP 2FA says the signed-in user must enrol and
 * has not, the REST API, administration pages, administration actions and background requests allow only the user's own
 * enrolment steps and sign-out. WP 2FA's own checks stay in force on what is allowed.
 *
 * Inactive when WP 2FA is not active or its emergency bypass is on; the security check reports either. With WP 2FA
 * active and the bypass off, a user whose state cannot be evaluated is treated as pending: the guard fails closed.
 *
 * Register id: ADM-131. See specs/008-admin-two-factor, FR-003.
 */
final class TwoFactor
{
    /** WP 2FA's sign-in completion route, the only REST route a pending user needs. */
    private const REST_ALLOWED = ['/wp-2fa-methods/v1/login/validate'];

    /** WP 2FA's own enrolment and sign-out background actions (4.2.1), by exact name. */
    private const AJAX_ALLOWED = [
        'regenerate_authentication_key',
        'validate_authcode_via_ajax',
        'wp2fa_run_ajax_generate_json',
        'send_backup_codes_email',
        'wp2fa_dismiss_required_intro',
        'wp2fa_logout_account',
    ];

    /** The own-account enrolment screen. */
    private const SETUP_PAGE = 'profile.php';
    private const SETUP_SHOW = 'wp-2fa-setup';

    public static function register(): void
    {
        add_filter('rest_authentication_errors', [self::class, 'guardRest'], 110);
        add_action('admin_init', [self::class, 'guardAdministration'], 0);
    }

    /**
     * Whether WP 2FA is active with its enforcement on. The guard does nothing otherwise.
     */
    public static function active(): bool
    {
        return defined('WP_2FA_VERSION') && ! (defined('DISABLE_2FA_LOGIN') && DISABLE_2FA_LOGIN);
    }

    /**
     * Whether WP 2FA requires this user to enrol and they have not. Fails closed when it cannot be told.
     */
    public static function pending(WP_User $user): bool
    {
        $helper = '\WP2FA\Admin\Helpers\User_Helper';
        if (! class_exists($helper)) {
            return true;
        }
        try {
            return $helper::is_enforced($user, true) && ! $helper::is_user_using_two_factor($user);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param WP_Error|null|true $result An earlier authentication answer.
     *
     * @return WP_Error|null|true
     */
    public static function guardRest(mixed $result): mixed
    {
        if (is_wp_error($result) || ! self::active()) {
            return $result;
        }
        $user = wp_get_current_user();
        if (! $user->exists() || ! self::pending($user)) {
            return $result;
        }
        if (self::restRoute() !== null && in_array(self::restRoute(), self::REST_ALLOWED, true) && self::method() === 'POST') {
            return $result;
        }

        return new WP_Error('mizzey_two_factor_enrol_first', self::message(), ['status' => 403]);
    }

    public static function guardAdministration(): void
    {
        if (! self::active() || ! is_user_logged_in()) {
            return;
        }
        $user = wp_get_current_user();
        if (! self::pending($user)) {
            return;
        }

        if (wp_doing_ajax()) {
            $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            if (in_array($action, self::AJAX_ALLOWED, true)) {
                return;
            }
            wp_send_json_error(['message' => self::message()], 403);
        }

        global $pagenow;
        $isSetupScreen = $pagenow === self::SETUP_PAGE
            && self::method() === 'GET'
            && ($_GET['show'] ?? '') === self::SETUP_SHOW // phpcs:ignore WordPress.Security.NonceVerification
            && ! isset($_REQUEST['action']) // phpcs:ignore WordPress.Security.NonceVerification
            // `page` and `import` make wp-admin/admin.php dispatch another screen from profile.php.
            && ! isset($_GET['page']) // phpcs:ignore WordPress.Security.NonceVerification
            && ! isset($_GET['import']); // phpcs:ignore WordPress.Security.NonceVerification
        if ($isSetupScreen) {
            return;
        }
        if (self::method() !== 'GET' || $pagenow === 'admin-post.php') {
            wp_die(esc_html(self::message()), '', ['response' => 403]);
        }
        wp_safe_redirect(admin_url(self::SETUP_PAGE . '?show=' . self::SETUP_SHOW));
        exit;
    }

    private static function message(): string
    {
        return __('Set up two-factor sign-in before using this.', 'mizzey-site');
    }

    private static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * The route of this REST request, normalised: the same for `/wp-json/...` and `?rest_route=...`.
     */
    private static function restRoute(): ?string
    {
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
        if (! is_string($route) || $route === '') {
            return null;
        }

        return '/' . trim(untrailingslashit($route), '/');
    }
}

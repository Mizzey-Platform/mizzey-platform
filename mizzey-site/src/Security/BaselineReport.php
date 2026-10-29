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
 * Each control of this feature is judged by exercising its hook, not by asking whether a class was loaded: a control
 * switched off by another plugin reports "review". The configuration facts the site depends on are reported beside
 * them and never judged here; CoreX login protection is AUTH-15's (#305), reported so its state is visible.
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
    ];

    /**
     * @return list<array{key:string,status:string,detail:string}>
     */
    public static function rows(): array
    {
        return [
            self::row('generator', self::generatorHidden(), 'No WordPress, WooCommerce or CoreX generator mark'),
            self::row('xmlrpc', self::xmlRpcClosed(), 'XML-RPC refused before dispatch; no method registered'),
            self::row('account_discovery', self::accountsHidden(), 'Visitors cannot list users or resolve ?author= numbers; no users sitemap'),
            self::row('application_passwords', self::appPasswordsAdminOnly(), 'Application passwords for administrators only'),
            self::row('sign_in_message', self::genericSignIn(), 'One sign-in failure message'),
            self::row('recovery_limit', has_action('lostpassword_post', [RecoveryLimiter::class, 'limit']) !== false, self::limitsDetail()),
            self::row('comment_surface', self::commentsOnlyOnProducts(), 'Comments only on products; pings closed'),
            self::fact('file_changes_disabled', defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS, 'DISALLOW_FILE_MODS'),
            self::fact('file_editor_disabled', defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT, 'DISALLOW_FILE_EDIT'),
            self::fact('debug_display_off', ! (defined('WP_DEBUG') && WP_DEBUG) || (defined('WP_DEBUG_DISPLAY') && ! WP_DEBUG_DISPLAY), 'Errors are not shown to visitors'),
            self::fact('corex_login_protection', self::corexLoginProtectionOn(), 'CoreX login lockout (AUTH-15, #305)'),
        ];
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
        return ['key' => $key, 'status' => $on ? 'on' : 'off', 'detail' => $detail . ' (reported, not judged here)'];
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
        wp_set_current_user(0);
        $status = rest_do_request(new WP_REST_Request('GET', '/wp/v2/users'))->get_status();
        $vars   = apply_filters('request', ['author' => '1']);
        wp_set_current_user($previous);
        $sitemap = apply_filters('wp_sitemaps_add_provider', true, 'users');

        return in_array($status, [401, 403], true) && ! isset($vars['author']) && $sitemap === false;
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

    private static function corexLoginProtectionOn(): bool
    {
        $settings = get_option('corex_login_protection_settings');

        return is_array($settings) && ! empty($settings['enabled']);
    }

    private static function limitsDetail(): string
    {
        $limits = RecoveryLimiter::limits();

        return sprintf(
            'Recovery limited to %d per account and %d per address in %d minutes',
            $limits['account'],
            $limits['address'],
            (int) ($limits['window'] / 60),
        );
    }
}

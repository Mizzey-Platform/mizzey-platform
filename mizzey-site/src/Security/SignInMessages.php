<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_Error;

/**
 * One sign-in failure message, whether the account exists or not.
 *
 * WordPress says "the username is not registered" for one failure and "the password for the username is incorrect"
 * for the other, which confirms accounts to anyone guessing. WooCommerce's account page shows the same errors. The two
 * revealing codes become WordPress's own generic failure, whose Arabic translation ships with WordPress.
 *
 * It runs at priority 100, after CoreX's lockout check (30). CoreX's lockout keeps its code and gets a message Mizzey
 * translates, because CoreX ships none in Arabic. Every other error passes unchanged, and `wp_authenticate()` still
 * fires `wp_login_failed` for the failure, which CoreX counts.
 *
 * Register ids: NFR-05 (admin protection), AUTH-15 (the lockout message). See specs/007-security-baseline, FR-005, and
 * specs/009-sign-in-protection.
 */
final class SignInMessages
{
    private const REVEALING = ['invalid_username', 'invalid_email', 'incorrect_password'];

    /** CoreX's lockout error (LoginProtectionEnforcer). */
    public const LOCKED = 'corex_login_locked';

    public static function register(): void
    {
        add_filter('authenticate', [self::class, 'generic'], 100);
    }

    public static function generic(mixed $user): mixed
    {
        if (! $user instanceof WP_Error) {
            return $user;
        }
        if ($user->get_error_code() === self::LOCKED) {
            return new WP_Error(
                self::LOCKED,
                __('Too many failed sign-in attempts. Please wait a few minutes and try again.', 'mizzey-site'),
            );
        }
        if (! in_array($user->get_error_code(), self::REVEALING, true)) {
            return $user;
        }

        return new WP_Error(
            'authentication_failed',
            __('<strong>Error:</strong> Invalid username, email address or incorrect password.'),
        );
    }
}

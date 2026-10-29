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
 * It runs at priority 100, after CoreX's lockout check (30). Every other error passes unchanged, CoreX's lockout
 * message included, and `wp_authenticate()` still fires `wp_login_failed` for the failure, which CoreX counts.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-005.
 */
final class SignInMessages
{
    private const REVEALING = ['invalid_username', 'invalid_email', 'incorrect_password'];

    public static function register(): void
    {
        add_filter('authenticate', [self::class, 'generic'], 100);
    }

    public static function generic(mixed $user): mixed
    {
        if (! $user instanceof WP_Error || ! in_array($user->get_error_code(), self::REVEALING, true)) {
            return $user;
        }

        return new WP_Error(
            'authentication_failed',
            __('<strong>Error:</strong> Invalid username, email address or incorrect password.'),
        );
    }
}

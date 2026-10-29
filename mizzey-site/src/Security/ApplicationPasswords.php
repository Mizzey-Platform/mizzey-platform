<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_User;

/**
 * Application passwords for administrators only.
 *
 * An application password authenticates REST calls without passing through the sign-in form, so CoreX's lockout never
 * sees it. Customers and staff below administrator have no use for one. WooCommerce's own API keys, which the
 * integrations use, are a different mechanism and are untouched.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-004.
 */
final class ApplicationPasswords
{
    public static function register(): void
    {
        add_filter('wp_is_application_passwords_available_for_user', [self::class, 'onlyAdministrators'], 10, 2);
    }

    public static function onlyAdministrators(bool $available, WP_User $user): bool
    {
        return $available && user_can($user, 'manage_options');
    }
}

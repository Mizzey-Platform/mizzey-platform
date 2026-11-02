<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_User;

/**
 * Application passwords for administrators, and for the stock feed's integration account.
 *
 * An application password authenticates REST calls without passing through the sign-in form, so CoreX's lockout never
 * sees it. Customers and staff below administrator have no use for one. The one exception is an account holding the
 * stock feed permission (D-28, specs/049): an outside system that feeds the store's own stock signs its calls with
 * one, and that account holds nothing else. WooCommerce's own API keys are a different mechanism and are untouched.
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
        return $available && (user_can($user, 'manage_options') || user_can($user, \MizzeySite\Stock\StockFeedRoute::CAP));
    }
}

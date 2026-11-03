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
 * one, and that account holds nothing else. Such a password opens the stock feed route and nothing more: any other
 * request it signs (its own profile, its password, a batch) is refused, so a leaked one can change stock only.
 * WooCommerce's own API keys are a different mechanism and are untouched.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-004.
 */
final class ApplicationPasswords
{
    public static function register(): void
    {
        add_filter('wp_is_application_passwords_available_for_user', [self::class, 'onlyAdministrators'], 10, 2);
        add_filter('rest_pre_dispatch', [self::class, 'feedOnly'], 5, 3);
    }

    /**
     * A request signed with a non-administrator's application password reaches the stock feed route only.
     *
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function feedOnly($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || rest_get_authenticated_app_password() === null || current_user_can('manage_options')) {
            return $result;
        }
        if ($request->get_route() === \MizzeySite\Stock\StockFeedRoute::ROUTE) {
            return $result;
        }

        return new \WP_Error('mizzey_application_password_scope', __('This application password may only feed stock.', 'mizzey-site'), ['status' => 403]);
    }

    public static function onlyAdministrators(bool $available, WP_User $user): bool
    {
        return $available && (user_can($user, 'manage_options') || user_can($user, \MizzeySite\Stock\StockFeedRoute::CAP));
    }
}

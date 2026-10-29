<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use WP_Error;
use WP_REST_Request;

/**
 * A visitor cannot list or discover accounts.
 *
 * Three native routes give account names away: the REST users routes, a numeric `?author=` query (redirected to the
 * author's slug) and the users sitemap. Signed-in users who edit content keep the users routes, because the block
 * editor reads its author list there; any signed-in user keeps `/users/me`, which is their own record.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-003.
 */
final class AccountDiscovery
{
    public static function register(): void
    {
        add_filter('rest_pre_dispatch', [self::class, 'refuseUsersRoutes'], 10, 3);
        add_filter('request', [self::class, 'noNumericAuthor']);
        add_filter('wp_sitemaps_add_provider', [self::class, 'noUsersSitemap'], 10, 2);
    }

    /**
     * @param mixed $result A response already chosen by an earlier filter, or null.
     */
    public static function refuseUsersRoutes(mixed $result, mixed $server, WP_REST_Request $request): mixed
    {
        $route = $request->get_route();
        if ($result !== null || preg_match('#^/wp/v2/users(/|$)#', $route) !== 1) {
            return $result;
        }
        if (current_user_can('list_users') || current_user_can('edit_posts')) {
            return $result;
        }
        if (is_user_logged_in() && preg_match('#^/wp/v2/users/me/?$#', $route) === 1) {
            return $result;
        }

        return new WP_Error(
            'rest_forbidden',
            __('Sorry, you are not allowed to do that.'),
            ['status' => is_user_logged_in() ? 403 : 401],
        );
    }

    /**
     * A numeric author query from a visitor is answered as not found, so `redirect_canonical()` never reveals the
     * slug. Author archives by slug are untouched.
     *
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public static function noNumericAuthor(array $vars): array
    {
        if (is_admin() || ! isset($vars['author']) || current_user_can('edit_posts')) {
            return $vars;
        }
        if (! is_scalar($vars['author']) || preg_match('/^-?[0-9]+(,-?[0-9]+)*$/', (string) $vars['author']) !== 1) {
            return $vars;
        }
        unset($vars['author']);
        $vars['error'] = '404';

        return $vars;
    }

    public static function noUsersSitemap(mixed $provider, string $name): mixed
    {
        return $name === 'users' ? false : $provider;
    }
}

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
 * Native routes that give account names away: the REST users routes, a numeric `?author=` query (redirected to the
 * author's slug, or answered with the author archive), the users sitemap, the oEmbed answer (author name and author
 * archive address) and the feeds (the author's display name per item). Signed-in users who edit content keep the
 * users routes, because the block editor reads its author list there; any signed-in user keeps `/users/me`, which is
 * their own record.
 *
 * Every match mirrors how WordPress reads the request: REST routes are matched without regard to case, and the
 * `author` query var keeps only digits, commas and minus signs, so padding or letter case cannot slip past.
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
        add_filter('oembed_response_data', [self::class, 'noEmbedAuthor'], 20);
        add_filter('the_author', [self::class, 'noFeedAuthor'], 20);
    }

    /**
     * @param mixed $result A response already chosen by an earlier filter, or null.
     */
    public static function refuseUsersRoutes(mixed $result, mixed $server, WP_REST_Request $request): mixed
    {
        $route = $request->get_route();
        // Case-insensitive, as WP_REST_Server::match_request_to_handler() matches routes.
        if ($result !== null || preg_match('#^/wp/v2/users(/|$)#i', $route) !== 1) {
            return $result;
        }
        if (current_user_can('list_users') || current_user_can('edit_posts')) {
            return $result;
        }
        if (is_user_logged_in() && preg_match('#^/wp/v2/users/me/?$#i', $route) === 1) {
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
     * slug and the author archive never renders. Author archives by slug are untouched.
     *
     * WP_Query strips everything but digits, commas and minus signs from `author`, so `?author=%201` or
     * `?author=+1` reach the archive of account 1: any value that keeps a digit after that strip is refused.
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
        if (! is_scalar($vars['author'])) {
            // WP_Query turns a non-scalar author into an empty string: nothing to resolve.
            return $vars;
        }
        $normalised = (string) preg_replace('|[^0-9,-]|', '', (string) $vars['author']);
        if (preg_match('/[0-9]/', $normalised) !== 1) {
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

    /**
     * The oEmbed answer names the site, not the account that wrote the record: core's own answer when a post has no
     * author.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function noEmbedAuthor(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }
        $data['author_name'] = get_bloginfo('name');
        $data['author_url']  = get_home_url();

        return $data;
    }

    /**
     * A feed item names the site, not the account that wrote it. Pages keep the author, which the theme decides.
     */
    public static function noFeedAuthor(mixed $name): mixed
    {
        if (! is_feed()) {
            return $name;
        }

        return get_bloginfo('name');
    }
}

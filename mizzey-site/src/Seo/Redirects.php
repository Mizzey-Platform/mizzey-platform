<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

defined('ABSPATH') || exit;

/**
 * The redirect manager (MKT-13) and the log of missing pages (MKT-14), our own code (D-24: Redirection is not
 * installed). A redirect sends one path of this site, in either language, permanently (301) to another address;
 * staff add, change and remove them in Settings, Search engines. A request that ends in "page not found" is counted
 * by path, with the time it was last asked for and where it came from (no visitor address is kept), so staff can see
 * what is missing and redirect it.
 *
 * Register ids: MKT-13, MKT-14. See specs/038-seo-controls.
 */
final class Redirects
{
    public const OPTION  = 'mizzey_redirects';
    public const MISSING = 'mizzey_missing_pages';
    public const KEEP    = 500;

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'follow'], 0);
        add_action('template_redirect', [self::class, 'logMissing'], 99);
    }

    /** A path as it is compared: lower case, no query, one leading slash, no trailing slash. */
    public static function normalise(string $path): string
    {
        $path = (string) wp_parse_url($path, PHP_URL_PATH);
        $path = '/' . trim(strtolower(rawurldecode($path)), '/');

        return $path;
    }

    /** @return array<string,array{to:string,hits:int,created:int}> */
    public static function all(): array
    {
        $all = get_option(self::OPTION, []);

        return is_array($all) ? $all : [];
    }

    /** Add or change a redirect. Refuses a loop, an empty path and an address that is not http or https. */
    public static function save(string $from, string $to): ?string
    {
        $from = self::normalise($from);
        $to   = trim($to);
        if (str_starts_with($to, '/')) {
            $to = home_url($to);
        }
        $to = esc_url_raw($to, ['http', 'https']);
        if ($from === '/' || $to === '') {
            return __('Give the old path and a full new address.', 'mizzey-site');
        }
        $home = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        if ((string) wp_parse_url($to, PHP_URL_HOST) === $home && self::normalise($to) === $from) {
            return __('A page cannot be redirected to itself.', 'mizzey-site');
        }
        $all        = self::all();
        $all[$from] = ['to' => $to, 'hits' => (int) ($all[$from]['hits'] ?? 0), 'created' => (int) ($all[$from]['created'] ?? time())];
        update_option(self::OPTION, $all, false);
        $missing = self::missing();
        unset($missing[$from]);
        update_option(self::MISSING, $missing, false);

        return null;
    }

    public static function remove(string $from): void
    {
        $all = self::all();
        unset($all[self::normalise($from)]);
        update_option(self::OPTION, $all, false);
    }

    /** The redirect for the current request, before WordPress looks for a page. */
    public static function follow(): void
    {
        if (is_admin() || ! isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $path = self::normalise(wp_unslash((string) $_SERVER['REQUEST_URI'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared.
        $all  = self::all();
        if (! isset($all[$path])) {
            return;
        }
        $all[$path]['hits'] = (int) $all[$path]['hits'] + 1;
        update_option(self::OPTION, $all, false);
        if (wp_redirect($all[$path]['to'], 301, 'Mizzey')) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- staff choose the address, which may be another site.
            exit;
        }
    }

    /** @return array<string,array{hits:int,last:int,referrer:string}> */
    public static function missing(): array
    {
        $all = get_option(self::MISSING, []);

        return is_array($all) ? $all : [];
    }

    public static function logMissing(): void
    {
        if (! is_404() || is_admin() || ! isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $path = mb_substr(self::normalise(wp_unslash((string) $_SERVER['REQUEST_URI'])), 0, 300); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised and escaped on output.
        if (preg_match('#\.(php|env|git|sql|bak|ini|log)(/|$)#', $path) === 1) {
            return; // Probes for server files are not missing pages.
        }
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER'])) : '';
        $all      = self::missing();
        $all[$path] = ['hits' => (int) ($all[$path]['hits'] ?? 0) + 1, 'last' => time(), 'referrer' => mb_substr($referrer, 0, 300)];
        if (count($all) > self::KEEP) {
            uasort($all, static fn (array $a, array $b): int => $b['last'] <=> $a['last']);
            $all = array_slice($all, 0, self::KEEP, true);
        }
        update_option(self::MISSING, $all, false);
    }
}

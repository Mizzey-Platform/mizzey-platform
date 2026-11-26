<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

/**
 * Whether a request may be answered from the page cache, and under which key: decided from the request alone,
 * before WordPress loads (NFR-02).
 *
 * A stored page is only ever one that any visitor without an account, a cart or a list would have been sent, so it
 * is served only to such a visitor:
 *
 * - **Method.** GET or HEAD.
 * - **Cookies.** Every cookie the request carries must be one that changes nothing on the page: Cloudflare's
 *   (Access and bot management), the consent choice (the banner is decided in the browser), analytics and campaign
 *   cookies, WPML's language cookie, and the recently viewed list (no stored page shows it; see `PageCache`). The
 *   chosen language (`mizzey_language`) may be present when it is the language of the address; in the other language
 *   WordPress answers, and moves the visitor to their language. Any other cookie, a sign-in, a cart or session, a
 *   comment author, a wishlist, a password-protected post's, or one this list does not know, means WordPress answers.
 * - **Query string.** Campaign parameters (`utm_*`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `ttclid`,
 *   `_gl`) are ignored; any other parameter (a search, a filter, a sort, add to cart, a preview) means WordPress
 *   answers. A page is stored only from a request with no query string at all, so one visitor's campaign never
 *   appears in another's page.
 * - **Address.** Never the administration, the sign-in page, REST, the WooCommerce API, feeds, cron, XML-RPC, PHP
 *   files, the cart, the checkout or the account (by their English addresses here; in every language by WordPress's
 *   own checks before a page is stored, `PageCache::approve()`).
 *
 * The key is the scheme, the host, the path and the language of the address.
 */
final class PageCacheRequest
{
    /** Cookies that change nothing on a stored page. Matched against the cookie's name; `*` is any run of characters. */
    public const HARMLESS_COOKIES = [
        // Cloudflare: Access, bot management, load balancing.
        'CF_Authorization', 'CF_AppSession', 'CF_Binding', '__cf_bm', 'cf_clearance', '_cfuvid', '__cflb',
        // The consent choice: the banner is printed hidden and decided in the browser (MizzeySite\Marketing\ConsentBanner).
        'mizzey_consent',
        // Analytics and advertising: read by their scripts in the browser, never by the page.
        '_ga', '_ga_*', '_gid', '_gat*', '_gcl_*', '_fbp', '_fbc', '_clck', '_clsk', '_uetsid', '_uetvid', '_tt_*', '_ttp',
        '_hj*',
        // WooCommerce's order attribution (sourcebuster), set in the browser after consent.
        'sbjs_*',
        // WPML's language cookie, written by its script on every page; the address decides the language.
        'wp-wpml_current_language',
        // The guest's recently viewed list: only the product page shows it (`PERSONAL_ON_PRODUCT`).
        'mizzey_recently_viewed',
        // WordPress's own test cookie, and the sign-in screen's language.
        'wordpress_test_cookie', 'wp_lang',
    ];

    /**
     * Harmless cookies that a product page does show: the guest's recently viewed rail (spec 066 slice 10) is drawn
     * on the product page from it, so there WordPress answers a guest who carries it. A guest without it sees an
     * empty rail, the same for everyone, which may be stored (`RecentlyViewed::products()` vetoes any other).
     */
    public const PERSONAL_ON_PRODUCT = ['mizzey_recently_viewed'];

    /** The product address's base, as WooCommerce's permalink setting has it on this site (in every language). */
    public const PRODUCT_BASE = '/product/';

    /** Campaign parameters: ignored in the key, and never part of a stored page. */
    public const CAMPAIGN_PARAMETERS = ['utm_*', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', '_gl'];

    /** The chosen-language cookie (MizzeySite\I18n\LanguagePreference::COOKIE). */
    public const LANGUAGE_COOKIE = 'mizzey_language';

    /** Address prefixes WordPress always answers. */
    private const NEVER = ['/wp-admin', '/wp-login.php', '/wp-json', '/wp-cron.php', '/xmlrpc.php', '/wp-content/', '/wp-includes/',
        '/wc-api/', '/wp-signup.php', '/wp-activate.php', '/wp-comments-post.php', '/wp-trackback.php', '/cart', '/checkout', '/my-account'];

    private function __construct(
        public readonly bool $lookup,
        public readonly bool $store,
        public readonly string $reason,
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $path,
        public readonly string $language,
        public readonly bool $head,
    ) {
    }

    /**
     * @param array<string,mixed> $server  The request's server variables.
     * @param array<string,mixed> $cookies The request's cookies.
     * @param list<string>        $languages The storefront's languages, the default first; the others are the first
     *                                       segment of their addresses (`/ar/`).
     */
    public static function from(array $server, array $cookies, array $languages, bool $secure): self
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $uri    = (string) ($server['REQUEST_URI'] ?? '/');
        $query  = (string) ($server['QUERY_STRING'] ?? (string) parse_url($uri, PHP_URL_QUERY));
        $path   = (string) parse_url($uri, PHP_URL_PATH);
        $path   = $path === '' ? '/' : rawurldecode($path);
        $host   = strtolower((string) ($server['HTTP_HOST'] ?? ''));
        $lang   = self::languageOf($path, $languages);
        $make   = static fn (bool $lookup, string $reason, bool $store = false): self => new self(
            $lookup, $lookup && $store, $reason, $secure ? 'https' : 'http', $host, $path, $lang, $method === 'HEAD'
        );

        if (! in_array($method, ['GET', 'HEAD'], true)) {
            return $make(false, 'method');
        }
        if ($host === '' || str_contains($path, "\0")) {
            return $make(false, 'address');
        }
        if (isset($server['HTTP_AUTHORIZATION']) || isset($server['PHP_AUTH_USER'])) {
            return $make(false, 'authorization');
        }
        if (self::neverCached($path, $languages)) {
            return $make(false, 'address');
        }
        if ($query !== '') {
            foreach (explode('&', $query) as $pair) {
                if ($pair === '') {
                    continue;
                }
                $name = rawurldecode(explode('=', $pair, 2)[0]);
                if (! self::matches($name, self::CAMPAIGN_PARAMETERS)) {
                    return $make(false, 'query');
                }
            }
        }
        foreach ($cookies as $name => $value) {
            $name = (string) $name;
            if ($name === self::LANGUAGE_COOKIE) {
                if (is_string($value) && $value !== '' && $value !== $lang) {
                    return $make(false, 'language');
                }
                continue;
            }
            if (! self::matches($name, self::HARMLESS_COOKIES)) {
                return $make(false, 'cookie');
            }
            if (self::matches($name, self::PERSONAL_ON_PRODUCT) && self::isProduct($path, $languages)) {
                return $make(false, 'cookie');
            }
        }

        return $make(true, 'lookup', $query === '' && $method === 'GET');
    }

    /** The language of an address: a non-default language's code as its first segment, otherwise the default. */
    public static function languageOf(string $path, array $languages): string
    {
        $first = strtolower(explode('/', ltrim($path, '/'))[0] ?? '');
        foreach (array_slice($languages, 1) as $code) {
            if ($first === $code) {
                return $code;
            }
        }

        return (string) ($languages[0] ?? 'en');
    }

    /** The key of this request's page, without the site's prefix. */
    public function key(): string
    {
        return 'page' . ':' . hash('sha256', implode('|', [$this->scheme, $this->host, $this->path, $this->language]));
    }

    /** Whether the address is a product page, in any language. */
    private static function isProduct(string $path, array $languages): bool
    {
        $low = strtolower($path);
        foreach (array_slice($languages, 1) as $code) {
            if (str_starts_with($low, '/' . $code . '/')) {
                $low = substr($low, strlen($code) + 1);
                break;
            }
        }

        return str_starts_with($low, self::PRODUCT_BASE);
    }

    private static function neverCached(string $path, array $languages): bool
    {
        $low = strtolower($path);
        // The address without its language prefix, so /ar/wp-json and /ar/cart are judged as /wp-json and /cart.
        $bare = $low;
        foreach (array_slice($languages, 1) as $code) {
            if ($low === '/' . $code || str_starts_with($low, '/' . $code . '/')) {
                $bare = substr($low, strlen($code) + 1);
                $bare = $bare === '' ? '/' : $bare;
                break;
            }
        }
        foreach ([$low, $bare] as $candidate) {
            foreach (self::NEVER as $prefix) {
                if ($candidate === $prefix || str_starts_with($candidate, rtrim($prefix, '/') . '/') || str_starts_with($candidate, $prefix . '?')) {
                    return true;
                }
            }
            if (str_ends_with($candidate, '.php') || preg_match('#/(feed|rss|rss2|atom|rdf|trackback)(/|$)#', $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $patterns */
    private static function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $name || (str_contains($pattern, '*') && preg_match('/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/', $name) === 1)) {
                return true;
            }
        }

        return false;
    }
}

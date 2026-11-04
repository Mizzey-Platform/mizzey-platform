<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * The visitor's chosen language: kept, and followed on every storefront page.
 *
 * WPML takes the language from the address only (`/ar/` for Arabic) and never from a stored choice, so a visitor who
 * chose English and then opens an Arabic link (from an order, an email or a shared link) is moved to Arabic without
 * being asked. Here the choice is kept, and a storefront page in the other language is answered with a redirect to the
 * same page in the chosen one, when that page exists in it.
 *
 * - **Where the choice is kept.** On the device, in a cookie (NAV-02, US-01-02: the chosen language persists for the
 *   session and for a returning visitor on the same device). For a signed-in customer, also on the account (D-31, a
 *   recorded gift), so it follows them on every device. The account wins over the device.
 * - **How it is chosen.** Only by the visitor: a switcher link (it carries `?mizzey_language=<code>`, and for a
 *   signed-in customer a nonce; both are removed from the address by a redirect), the account page's language field,
 *   or the REST route
 *   `mizzey/v1/language`. Following an ordinary link in the other language never changes it. With no choice made, the
 *   address decides, as before; search engines, which keep no cookie, always get the page they asked for.
 * - **Cached pages.** A page served from a page cache never reaches PHP, so a short script in the head does the same
 *   for it, from the cookie and the page's own `hreflang` alternates. Signed-in pages are not cached.
 * - **The cart.** WooCommerce Multilingual's cart setting for a language switch is held at "keep the cart, each item in
 *   the new language" (`WCML_CART_SYNC`), so it cannot drift to emptying the cart (US-01-02).
 *
 * Only storefront pages are moved: never the administration (ADM-159, English only), REST or AJAX requests, feeds,
 * previews or the customiser.
 *
 * Register ids: NAV-02 (the account part is D-31, a gift). See specs/052-language-preference.
 */
final class LanguagePreference
{
    public const COOKIE   = 'mizzey_language';
    public const META     = 'mizzey_language';
    /** The query argument a switcher link carries: the visitor's own choice. */
    public const CHOICE   = 'mizzey_language';
    /** For a signed-in customer, the switcher link's nonce, so a link made elsewhere cannot change their account. */
    public const NONCE    = 'mizzey_language_nonce';
    public const LANGUAGES = ['en', 'ar'];
    private const DAYS    = 365;

    public static function register(): void
    {
        add_filter('option__wcml_settings', [self::class, 'keepCart'], 20);
        add_action('template_redirect', [self::class, 'route'], 0);
        add_filter('icl_ls_languages', [self::class, 'switcherLinks'], 20);
        add_action('wp_head', [self::class, 'cachedPageScript'], 1);
        add_action('wp_login', [self::class, 'signedIn'], 10, 2);
        add_action('user_register', [self::class, 'registered']);
        add_action('woocommerce_edit_account_form', [self::class, 'accountField']);
        add_action('woocommerce_save_account_details', [self::class, 'saveAccountField']);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    /** A known language code, or null. */
    public static function valid(mixed $code): ?string
    {
        return is_string($code) && in_array($code, self::LANGUAGES, true) ? $code : null;
    }

    /** The language the page is in. */
    public static function current(): string
    {
        $current = apply_filters('wpml_current_language', null);

        return self::valid($current) ?? 'en';
    }

    /** The visitor's choice: their account's for a signed-in customer, otherwise the device's, otherwise null. */
    public static function preferred(?int $userId = null): ?string
    {
        $userId ??= get_current_user_id();
        if ($userId > 0) {
            $kept = self::valid(get_user_meta($userId, self::META, true));
            if ($kept !== null) {
                return $kept;
            }
        }

        return self::valid(wp_unslash($_COOKIE[self::COOKIE] ?? null)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    /** Keeps a choice: on the device, and on the account when someone is signed in. */
    public static function choose(string $code, ?int $userId = null): void
    {
        $code = self::valid($code);
        if ($code === null) {
            return;
        }
        $userId ??= get_current_user_id();
        if ($userId > 0) {
            update_user_meta($userId, self::META, $code);
        }
        self::remember($code);
    }

    /** The device's cookie. Not HTTP-only: the cached-page script reads it, and it holds nothing private. */
    private static function remember(string $code): void
    {
        $_COOKIE[self::COOKIE] = $code;
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, $code, [
            'expires'  => time() + self::DAYS * DAY_IN_SECONDS,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function keepCart($settings)
    {
        if (is_array($settings) && defined('WCML_CART_SYNC')) {
            $settings['cart_sync']                = (array) ($settings['cart_sync'] ?? []);
            $settings['cart_sync']['lang_switch'] = WCML_CART_SYNC;
        }

        return $settings;
    }

    /** Whether this request is a storefront page a visitor asked for, which may be moved to their language. */
    private static function isStorefrontPage(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        return in_array($method, ['GET', 'HEAD'], true) && ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron()
            && ! (defined('REST_REQUEST') && REST_REQUEST) && ! (defined('WP_CLI') && WP_CLI)
            && ! is_feed() && ! is_robots() && ! is_preview() && ! is_customize_preview() && ! is_trackback();
    }

    /** The address of this request, in full. */
    private static function requestUrl(): string
    {
        return set_url_scheme(home_url(wp_unslash((string) ($_SERVER['REQUEST_URI'] ?? '/')))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    /**
     * The address of the page being served in another language, or null when the page has no translation there.
     * Its query string is kept (a search, a filter, a page number), without the choice argument.
     */
    public static function translationUrl(string $code): ?string
    {
        $url = self::accountEndpointUrl($code);
        if ($url === null) {
            // WPML's switcher data, without this class's own choice argument on its links.
            remove_filter('icl_ls_languages', [self::class, 'switcherLinks'], 20);
            $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 1]);
            add_filter('icl_ls_languages', [self::class, 'switcherLinks'], 20);
            $url = is_array($languages) && isset($languages[$code]['url']) && empty($languages[$code]['missing']) ? (string) $languages[$code]['url'] : '';
        }
        if ($url === '') {
            return null;
        }
        parse_str((string) wp_parse_url(self::requestUrl(), PHP_URL_QUERY), $query);
        unset($query[self::CHOICE], $query[self::NONCE], $query['lang']);

        return $query === [] ? $url : add_query_arg(array_map('rawurlencode', $query), $url);
    }

    /**
     * An account page with an endpoint (an order, the addresses, the account details) in another language: WPML's
     * switcher points at the account's home for these, so the same endpoint and value are built in that language.
     * Null when this is not such a page.
     */
    private static function accountEndpointUrl(string $code): ?string
    {
        if (! function_exists('is_account_page') || ! is_account_page() || ! WC()->query instanceof \WC_Query) {
            return null;
        }
        $endpoint = (string) WC()->query->get_current_endpoint();
        if ($endpoint === '') {
            return null;
        }
        $value   = (string) get_query_var($endpoint);
        $current = self::current();
        do_action('wpml_switch_language', $code);
        $account = (string) wc_get_page_permalink('myaccount');
        do_action('wpml_switch_language', $current);
        // The endpoint's own name is translated once per request, in the request's language; its name in the other
        // language is WooCommerce's setting (English) or that setting's WPML translation (WooCommerce Multilingual
        // keeps them in the "WP Endpoints" string context, by endpoint).
        $original = (string) get_option('woocommerce_myaccount_' . str_replace('-', '_', $endpoint) . '_endpoint', $endpoint);
        $original = $original !== '' ? $original : $endpoint;
        $slug     = (string) apply_filters('wpml_translate_single_string', $original, 'WP Endpoints', $endpoint, $code);
        $slug     = $slug !== '' ? $slug : $original;

        return user_trailingslashit(trailingslashit($account) . $slug . ($value !== '' ? '/' . rawurlencode($value) : ''));
    }

    /** The switcher link to a language, for the storefront's own switcher (the designed one is PRE-03b's). */
    public static function switchUrl(string $code): string
    {
        $url  = self::translationUrl($code) ?? (string) apply_filters('wpml_home_url', home_url('/'));
        $args = [self::CHOICE => $code] + (is_user_logged_in() ? [self::NONCE => wp_create_nonce(self::NONCE)] : []);

        return add_query_arg($args, $url);
    }

    /**
     * Keeps a choice a switcher link carried, and moves a page in the other language to the visitor's. A signed-in
     * customer's link must carry its nonce; one without is not their choice, and is only stripped from the address.
     */
    public static function route(): void
    {
        if (! self::isStorefrontPage()) {
            return;
        }
        $choice = self::valid(isset($_GET[self::CHOICE]) ? sanitize_key(wp_unslash($_GET[self::CHOICE])) : null); // phpcs:ignore WordPress.Security.NonceVerification
        if (isset($_GET[self::CHOICE])) { // phpcs:ignore WordPress.Security.NonceVerification
            $own = ! is_user_logged_in() || wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_GET[self::NONCE] ?? ''))), self::NONCE) !== false;
            $choice = $own ? $choice : null;
            if ($choice !== null) {
                self::choose($choice);
            }
            $clean = remove_query_arg([self::CHOICE, self::NONCE], self::requestUrl());
            $to    = $choice !== null && $choice !== self::current() ? (self::translationUrl($choice) ?? $clean) : $clean;
            self::redirect($to);
        }
        $preferred = self::preferred();
        if ($preferred === null || $preferred === self::current()) {
            return;
        }
        $to = self::translationUrl($preferred);
        if ($to !== null) {
            self::redirect($to);
        }
    }

    private static function redirect(string $to): void
    {
        nocache_headers();
        header('Vary: Cookie', false);
        if (wp_safe_redirect($to, 302, 'Mizzey')) {
            exit;
        }
    }

    /**
     * The language switcher's links carry the choice, so following one keeps it.
     *
     * @param mixed $languages
     * @return mixed
     */
    public static function switcherLinks($languages)
    {
        if (! is_array($languages) || is_admin()) {
            return $languages;
        }
        $args = is_user_logged_in() ? [self::NONCE => wp_create_nonce(self::NONCE)] : [];
        foreach ($languages as $code => $language) {
            if (is_array($language) && isset($language['url']) && self::valid($code) !== null) {
                $languages[$code]['url'] = add_query_arg([self::CHOICE => $code] + $args, (string) $language['url']);
            }
        }

        return $languages;
    }

    /** The same move for a page served from a page cache, done in the browser from the cookie and the alternates. */
    public static function cachedPageScript(): void
    {
        if (! self::isStorefrontPage() || is_user_logged_in()) {
            return;
        }
        $script = sprintf(
            '(function(){var m=document.cookie.match(/(?:^|; )%1$s=(en|ar)(?:;|$)/);if(!m)return;var c=m[1];var h=(document.documentElement.lang||"").slice(0,2);if(!h||h===c)return;var l=document.querySelector(\'link[rel="alternate"][hreflang^="\'+c+\'"]\');if(!l||!l.href)return;var u=new URL(l.href);var q=new URLSearchParams(location.search);q.delete("%1$s");q.delete("lang");q.forEach(function(v,k){u.searchParams.set(k,v);});if(u.href!==location.href)location.replace(u.href+location.hash);})();',
            self::COOKIE,
        );
        wp_print_inline_script_tag($script, ['id' => 'mizzey-language']);
    }

    /**
     * Signing in: the account's choice comes to this device; an account with no choice yet keeps the one this device
     * made.
     *
     * @param mixed $login
     * @param mixed $user
     */
    public static function signedIn($login, $user): void
    {
        if (! $user instanceof \WP_User) {
            return;
        }
        $kept = self::valid(get_user_meta($user->ID, self::META, true));
        if ($kept !== null) {
            self::remember($kept);

            return;
        }
        $device = self::valid(wp_unslash($_COOKIE[self::COOKIE] ?? null)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if ($device !== null) {
            update_user_meta($user->ID, self::META, $device);
        }
    }

    /** A new account starts with the device's choice, or the language it was opened in. */
    public static function registered(int $userId): void
    {
        if (is_admin() && ! wp_doing_ajax()) {
            return; // An account an administrator creates is not the customer's choice.
        }
        $device = self::valid(wp_unslash($_COOKIE[self::COOKIE] ?? null)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        update_user_meta($userId, self::META, $device ?? self::current());
    }

    /** The account page's language field. Each language is named in itself. */
    public static function accountField(): void
    {
        $kept = self::preferred() ?? self::current();
        echo '<p class="woocommerce-form-row form-row form-row-wide"><label for="mizzey_language">' . esc_html__('Language', 'mizzey-site') . '</label>';
        echo '<select name="mizzey_language" id="mizzey_language">';
        foreach (['en' => 'English', 'ar' => 'العربية'] as $code => $name) {
            printf('<option value="%1$s" lang="%1$s"%2$s>%3$s</option>', esc_attr($code), selected($kept, $code, false), esc_html($name));
        }
        echo '</select></p>';
    }

    public static function saveAccountField(int $userId): void
    {
        // WooCommerce checks the account form's nonce before this action runs.
        $code = self::valid(isset($_POST['mizzey_language']) ? sanitize_key(wp_unslash($_POST['mizzey_language'])) : null); // phpcs:ignore WordPress.Security.NonceVerification
        if ($code !== null) {
            self::choose($code, $userId);
        }
    }

    /** `GET` and `POST mizzey/v1/language`: the visitor's choice, for the storefront's own switcher. */
    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/language', [
            [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => static fn (): \WP_REST_Response => self::answer(),
            ],
            [
                'methods'             => 'POST',
                'permission_callback' => '__return_true',
                'args'                => ['language' => ['required' => true, 'type' => 'string', 'enum' => self::LANGUAGES]],
                'callback'            => static function (\WP_REST_Request $request): \WP_REST_Response {
                    self::choose((string) $request->get_param('language'));

                    return self::answer();
                },
            ],
        ]);
    }

    private static function answer(): \WP_REST_Response
    {
        $userId   = get_current_user_id();
        $account  = $userId > 0 ? self::valid(get_user_meta($userId, self::META, true)) : null;
        $response = new \WP_REST_Response([
            'language' => self::preferred(),
            'kept_on'  => $account !== null ? 'account' : (self::preferred() !== null ? 'device' : null),
        ]);
        $response->header('Cache-Control', 'no-store, private');

        return $response;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Marketing;

defined('ABSPATH') || exit;

/**
 * Consent management and the cookie banner (MKT-10, NFR-12).
 *
 * The banner asks once, in the visitor's language, and the choice is kept 180 days in the `mizzey_consent` cookie;
 * any link with `data-mizzey-consent-open` reopens it (the footer's "Cookie settings"). Until the visitor accepts,
 * Google's consent mode stays denied (`TagManager`) and WooCommerce's order attribution (MKT-09) sets no cookie.
 * Nothing that depends on the choice is decided on the server: the banner is printed hidden and shown by its script,
 * and order attribution is printed off and switched on in the browser from the cookie, so a cached page serves every
 * visitor correctly. Its look is neutral until the interface design (PRE-03b).
 *
 * Register ids: MKT-10. See specs/043-measurement-tools.
 */
final class ConsentBanner
{
    public const COOKIE = 'mizzey_consent';
    public const SCRIPT = 'mizzey-consent';

    public static function register(): void
    {
        add_action('wp_footer', [self::class, 'banner'], 5);
        add_action('wp_footer', [self::class, 'settingsLink'], 4);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_action('wp_enqueue_scripts', [self::class, 'attributionFromCookie'], 20);
        add_filter('wc_order_attribution_allow_tracking', [self::class, 'attribution']);
    }

    public static function granted(): bool
    {
        return isset($_COOKIE[self::COOKIE]) && sanitize_key(wp_unslash((string) $_COOKIE[self::COOKIE])) === 'granted';
    }

    /**
     * Campaign attribution on the order sets cookies: only with consent. The page is always printed with it off, so a
     * page cached for one visitor never switches it on for another; `attributionFromCookie()` switches it on in the
     * browser of a visitor who accepted.
     *
     * @param mixed $allow
     */
    public static function attribution($allow): bool
    {
        return false;
    }

    /** Before WooCommerce's attribution script starts: on when this browser's choice is "granted". */
    public static function attributionFromCookie(): void
    {
        if (is_admin() || ! wp_script_is('wc-order-attribution', 'enqueued')) {
            return;
        }
        wp_add_inline_script(
            'wc-order-attribution',
            'if(window.wc_order_attribution&&window.wc_order_attribution.params&&/(?:^|; )mizzey_consent=granted(?:;|$)/.test(document.cookie)){window.wc_order_attribution.params.allowTracking=true;}',
            'before'
        );
    }

    public static function enqueue(): void
    {
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/consent.js';
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/consent.js'), [], is_readable($file) ? (string) filemtime($file) : null, true);
    }

    /** The footer's "Cookie settings", which reopens the banner. Placed in the footer design with PRE-03b. */
    public static function settingsLink(): void
    {
        if (is_admin()) {
            return;
        }
        printf('<p class="mizzey-cookie-settings"><a href="#" data-mizzey-consent-open="1">%s</a></p>', esc_html__('Cookie settings', 'mizzey-site'));
    }

    public static function banner(): void
    {
        if (is_admin()) {
            return;
        }
        printf(
            '<div id="mizzey-consent" class="mizzey-consent" role="dialog" aria-live="polite" aria-label="%s" hidden><p>%s</p><p><button type="button" data-mizzey-consent="granted">%s</button><button type="button" data-mizzey-consent="denied">%s</button></p></div>',
            esc_attr__('Cookie choices', 'mizzey-site'),
            esc_html__('We use cookies to measure visits and to show you relevant offers on other sites. Essential cookies keep the store working and are always on.', 'mizzey-site'),
            esc_html__('Accept all', 'mizzey-site'),
            esc_html__('Essential only', 'mizzey-site')
        );
    }
}

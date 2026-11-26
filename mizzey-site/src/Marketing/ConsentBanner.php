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
 * visitor correctly.
 *
 * The banner is printed first in the page (`wp_body_open`), so it is the first thing the keyboard reaches; its look,
 * the cookie preferences it links to and the room it keeps at the foot of the page are the theme's (spec 066, slice 3,
 * mizzey-theme/inc/consent.php). The designed footer carries the link that reopens it; this class prints its own
 * "Cookie settings" link only on a page without that footer.
 *
 * Register ids: MKT-10. See specs/043-measurement-tools.
 */
final class ConsentBanner
{
    public const COOKIE = 'mizzey_consent';
    public const SCRIPT = 'mizzey-consent';

    public static function register(): void
    {
        add_action('wp_body_open', [self::class, 'banner'], 1);
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
            'if(window.wc_order_attribution&&window.wc_order_attribution.params&&/(?:^|;\s*)mizzey_consent=granted(?:;|$)/.test(document.cookie)){window.wc_order_attribution.params.allowTracking=true;}',
            'before'
        );
    }

    public static function enqueue(): void
    {
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/consent.js';
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/consent.js'), [], is_readable($file) ? (string) filemtime($file) : null, true);
    }

    /** The "Cookie settings" link that reopens the banner, on a page whose designed footer does not carry one. */
    public static function settingsLink(): void
    {
        if (is_admin() || did_action('mizzey_footer_consent_link')) {
            return;
        }
        printf('<p class="mizzey-cookie-settings"><a href="#" data-mizzey-consent-open="1">%s</a></p>', esc_html__('Cookie settings', 'mizzey-site'));
    }

    /** The banner, once: first in the page where the theme opens the body, otherwise after it. */
    public static function banner(): void
    {
        static $printed = false;
        if ($printed || is_admin()) {
            return;
        }
        $printed = true;
        printf(
            '<div id="mizzey-consent" class="mizzey-consent" role="dialog" aria-live="polite" aria-label="%s" hidden><p class="mizzey-consent__text">%s <button type="button" class="mizzey-consent__preferences" data-wp-interactive="mizzey/ui" data-wp-context="{&quot;target&quot;:&quot;mizzey-consent-preferences&quot;}" data-wp-on--click="actions.open" aria-haspopup="dialog" aria-controls="mizzey-consent-preferences">%s</button></p><p class="mizzey-consent__choices"><button type="button" class="mizzey-button mizzey-button--secondary mizzey-consent__choice" data-mizzey-consent="denied">%s</button><button type="button" class="mizzey-button mizzey-button--primary mizzey-consent__choice" data-mizzey-consent="granted">%s</button></p></div>',
            esc_attr__('Cookie choices', 'mizzey-site'),
            esc_html__('We use cookies to keep your cart, see how the store is used and show relevant ads.', 'mizzey-site'),
            esc_html__('Cookie preferences', 'mizzey-site'),
            esc_html__('Decline', 'mizzey-site'),
            esc_html__('Accept all', 'mizzey-site')
        );
    }
}

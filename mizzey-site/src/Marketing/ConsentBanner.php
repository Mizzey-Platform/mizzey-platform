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
 * The banner is printed hidden and shown by its script, so a cached page serves every visitor correctly. Its look is
 * neutral until the interface design (PRE-03b).
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
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('wc_order_attribution_allow_tracking', [self::class, 'attribution']);
    }

    public static function granted(): bool
    {
        return isset($_COOKIE[self::COOKIE]) && sanitize_key(wp_unslash((string) $_COOKIE[self::COOKIE])) === 'granted';
    }

    /**
     * Campaign attribution on the order sets cookies: only with consent.
     *
     * @param mixed $allow
     * @return mixed
     */
    public static function attribution($allow)
    {
        return self::granted() ? $allow : false;
    }

    public static function enqueue(): void
    {
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/consent.js';
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/consent.js'), [], is_readable($file) ? (string) filemtime($file) : null, true);
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

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * No generator mark and no XML-RPC discovery in what a visitor receives.
 *
 * Four products print a generator mark: WordPress in the head and in every feed, WooCommerce appended to
 * WordPress's, CoreX (v0.47.0) its own tag, and WPML its version and languages. Each is switched off through its own
 * hook, so nothing is filtered out of the page afterwards. The pingback header and the RSD link advertise XML-RPC,
 * which `XmlRpc` refuses.
 *
 * Register id: NFR-05 (admin protection). See specs/007-security-baseline, FR-001 and FR-002.
 */
final class Disclosure
{
    public static function register(): void
    {
        add_filter('corex_generator_tag', '__return_false');
        add_filter('the_generator', [self::class, 'noGenerator']);
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        add_filter('wp_headers', [self::class, 'withoutPingbackHeader']);
        add_action('wp_head', [self::class, 'removeWpmlGenerator'], 0);
    }

    /**
     * WPML hooks its tag on its own object at priority 10, so it is removed through that object, from earlier in
     * the same hook.
     */
    public static function removeWpmlGenerator(): void
    {
        global $sitepress;
        if (is_object($sitepress)) {
            remove_action('wp_head', [$sitepress, 'meta_generator_tag']);
        }
    }

    /**
     * `the_generator` serves the head and every feed type, and WooCommerce's mark rides on it.
     */
    public static function noGenerator(): string
    {
        return '';
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    public static function withoutPingbackHeader(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }
}

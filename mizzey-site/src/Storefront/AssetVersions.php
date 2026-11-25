<?php

/**
 * Every style and script of the site plugin and the theme is printed with a version that changes when its file
 * changes, so a browser or the edge cache never keeps an old copy after a release (spec 066, K-8).
 *
 * The measured defect (26 November 2026): blocks registered from block.json without a "version" (the product card's
 * style, the hero's style and view script, the sign-up's view script) were printed with `?ver=` WordPress's own
 * version, which stays the same between our releases. The card's adding loader fix was live on staging while a phone
 * and Cloudflare still served the old stylesheet.
 *
 * WordPress's own source filters for styles, scripts and script modules are used: a source whose file lives in the
 * site plugin or the active theme gets the file's modification time as its version, whatever it was registered with.
 * Any other source (WordPress, WooCommerce, WPML) is left as it is.
 *
 * Register ids: NFR-04 (D-21). See specs/066-stage-2-shop-front, the checks every slice passes (K-8).
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

final class AssetVersions
{
    public static function register(): void
    {
        add_filter('style_loader_src', [self::class, 'source'], 20);
        add_filter('script_loader_src', [self::class, 'source'], 20);
        add_filter('script_module_loader_src', [self::class, 'source'], 20);
    }

    /**
     * @param mixed $src The source WordPress is about to print.
     *
     * @return mixed
     */
    public static function source(mixed $src): mixed
    {
        if (! is_string($src) || '' === $src) {
            return $src;
        }
        $file = self::file($src);
        if (null === $file) {
            return $src;
        }
        $time = filemtime($file);

        return false === $time ? $src : add_query_arg('ver', (string) $time, $src);
    }

    /** The file of ours a source points at, or null when it is not ours or does not exist. */
    private static function file(string $src): ?string
    {
        $path = (string) wp_parse_url($src, PHP_URL_PATH);
        if ('' === $path || ! preg_match('/\.(css|js|mjs)$/i', $path)) {
            return null;
        }
        $plugin = wp_normalize_path((string) realpath(dirname(__DIR__, 2)));
        $roots  = [
            [(string) wp_parse_url(plugins_url('', $plugin . '/mizzey-site.php'), PHP_URL_PATH), $plugin],
            [(string) wp_parse_url(get_stylesheet_directory_uri(), PHP_URL_PATH), wp_normalize_path((string) realpath(get_stylesheet_directory()))],
        ];
        foreach ($roots as [$prefix, $dir]) {
            if ('' === $prefix || '' === $dir || 0 !== strpos($path, rtrim($prefix, '/') . '/')) {
                continue;
            }
            $file = realpath($dir . substr($path, strlen(rtrim($prefix, '/'))));
            if (false === $file) {
                return null;
            }
            $file = wp_normalize_path($file);

            // Only a file inside the root it was asked for.
            return 0 === strpos($file, $dir . '/') && is_file($file) ? $file : null;
        }

        return null;
    }
}

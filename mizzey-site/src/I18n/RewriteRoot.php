<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * The web server's rewrite rules always name the site's root, never a language's.
 *
 * The measured defect. WordPress writes `.htaccess` from `home_url()`, which WPML gives a language prefix while a
 * page is shown in that language. WooCommerce flushes the rewrite rules on the next admin page after it queues a
 * flush (a product type, a permalink or an attribute change), so when that page is open in Arabic WordPress writes
 * `RewriteBase /ar/` and `RewriteRule . /ar/index.php`, and every Arabic address then loops on an Apache host
 * (500, "exceeded the limit of 10 internal redirects"). Seen on the local runtime on 2 November 2026; any production
 * host that reads `.htaccess` would break the same way.
 *
 * The rules are written with the site's stored home path, the one WordPress itself keeps in the `home` option.
 *
 * Register ids: RPT-01 (its Arabic report screen). See specs/034-sales-report.
 */
final class RewriteRoot
{
    public static function register(): void
    {
        add_filter('mod_rewrite_rules', [self::class, 'root']);
    }

    /** The site's own path, from the stored home address, with a trailing slash. */
    public static function base(): string
    {
        $path = (string) wp_parse_url((string) get_option('home'), PHP_URL_PATH);

        return '/' . ltrim(trailingslashit($path), '/');
    }

    /** The root WordPress used while writing: the home address of the current language, as WPML filters it. */
    public static function written(): string
    {
        $path = (string) wp_parse_url(home_url(), PHP_URL_PATH);

        return '/' . ltrim(trailingslashit($path), '/');
    }

    /**
     * Only WordPress's own language-prefixed root is replaced: its RewriteBase line and every front-controller target
     * that starts with it (the catch-all, and each rule when verbose rules are on). Another block's base and targets
     * are left alone, and nothing changes when the page writing the rules is in the default language.
     *
     * @param mixed $rules
     * @return mixed
     */
    public static function root($rules)
    {
        if (! is_string($rules) || $rules === '') {
            return $rules;
        }
        $base    = self::base();
        $written = self::written();
        if ($written === $base) {
            return $rules;
        }
        $rules = (string) preg_replace('/^RewriteBase ' . preg_quote($written, '/') . '$/m', 'RewriteBase ' . $base, $rules);

        return str_replace(' ' . $written . 'index.php', ' ' . $base . 'index.php', $rules);
    }
}

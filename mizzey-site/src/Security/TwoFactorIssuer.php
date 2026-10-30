<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * The name a staff member's authenticator app shows for this site: the site's title, not its address.
 *
 * WP 2FA 4.2.1 names the authenticator entry after `site_url()` with the scheme removed ("staging.mizzey.com"), in two
 * places only (`TOTP::get_qr_code()` and `TOTP::regenerate_authentication_key()`), and offers no setting or hook for
 * it. This answers the bare `site_url()` call made by those two methods, and only that call, with the site's title as
 * stored ("Mizzey" on the live site, "Mizzey (staging)" on staging). Every other `site_url()` result is untouched. The
 * title is read as stored, not through translation filters, so an entry keeps one name whatever language the set-up
 * page is in. Colons are removed, because the entry's label is "name:login". An empty title leaves WP 2FA's own name.
 *
 * An addition the owner asked for on 30 October 2026, not a register obligation. See specs/008-admin-two-factor, FR-008.
 */
final class TwoFactorIssuer
{
    private const BUILDERS = ['get_qr_code', 'regenerate_authentication_key'];
    private const TOTP     = 'WP2FA\Methods\TOTP';

    public static function register(): void
    {
        add_filter('site_url', [self::class, 'issuer'], 10, 3);
    }

    public static function issuer(mixed $url, mixed $path, mixed $scheme): mixed
    {
        if ($path !== '' || $scheme !== null || ! self::calledByTotp()) {
            return $url;
        }
        $name = self::name();

        return $name === '' ? $url : $name;
    }

    /**
     * The site's title as stored, one line, without colons.
     */
    public static function name(): string
    {
        $stored = wp_load_alloptions()['blogname'] ?? null;
        if ($stored === null) {
            // Not autoloaded: read the stored value directly, not through the translation filters.
            global $wpdb;
            $stored = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", 'blogname')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
        $name   = wp_specialchars_decode(is_string($stored) ? $stored : '', ENT_QUOTES);
        $name   = str_replace(':', ' ', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Whether the `site_url()` call being answered was made directly by one of WP 2FA's two name builders, not by a
     * callback running inside them. Each pinned builder makes exactly one bare call.
     */
    private static function calledByTotp(): bool
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $index => $frame) {
            if (($frame['function'] ?? '') !== 'site_url' || isset($frame['class'])) {
                continue;
            }
            $caller = $trace[$index + 1] ?? [];

            return ($caller['class'] ?? '') === self::TOTP && in_array($caller['function'] ?? '', self::BUILDERS, true);
        }

        return false;
    }
}

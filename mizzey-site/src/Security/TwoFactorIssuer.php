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
        $stored = wp_load_alloptions()['blogname'] ?? '';
        $name   = wp_specialchars_decode(is_string($stored) ? $stored : '', ENT_QUOTES);
        $name   = str_replace(':', ' ', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private static function calledByTotp(): bool
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10) as $frame) {
            if (($frame['class'] ?? '') === self::TOTP && in_array($frame['function'] ?? '', self::BUILDERS, true)) {
                return true;
            }
        }

        return false;
    }
}

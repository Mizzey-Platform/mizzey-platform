<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Wishlist;

defined('ABSPATH') || exit;

/**
 * A guest's list of product ids, kept in a signed cookie: the ids, their expiry, and an HMAC over both with the site's
 * own salt, so a visitor cannot plant ids or stretch the lifetime. A cookie that is altered, expired or unreadable is
 * an empty list. The cookie is HTTP-only and SameSite=Lax (WooCommerce's own cookie defaults through `wc_setcookie()`).
 *
 * See specs/015-wishlist.
 */
final class GuestCookie
{
    public function __construct(private readonly string $name, private readonly int $lifetime)
    {
    }

    /** @return list<int> */
    public function read(): array
    {
        $raw   = isset($_COOKIE[$this->name]) ? (string) wp_unslash($_COOKIE[$this->name]) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $parts = explode('|', $raw);
        if (count($parts) !== 3 || ! ctype_digit($parts[1]) || (int) $parts[1] < time()) {
            return [];
        }
        if (! hash_equals(self::sign($parts[0], $parts[1]), $parts[2])) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $parts[0]))));
    }

    /** @param list<int> $ids An empty list removes the cookie. */
    public function write(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            unset($_COOKIE[$this->name]);
            if (function_exists('wc_setcookie')) {
                wc_setcookie($this->name, '', time() - YEAR_IN_SECONDS, is_ssl(), true);
            }

            return;
        }
        $list    = implode(',', $ids);
        $expires = (string) ($this->lifetime > 0 ? time() + $this->lifetime : time() + DAY_IN_SECONDS);
        $value   = $list . '|' . $expires . '|' . self::sign($list, $expires);
        $_COOKIE[$this->name] = $value;
        if (function_exists('wc_setcookie')) {
            wc_setcookie($this->name, $value, $this->lifetime > 0 ? (int) $expires : 0, is_ssl(), true);
        }
    }

    private static function sign(string $list, string $expires): string
    {
        return hash_hmac('sha256', $list . '|' . $expires, wp_salt('auth'));
    }
}

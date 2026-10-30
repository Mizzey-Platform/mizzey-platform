<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * The check against scripted account creation: a signed, single-use token issued with the page, and a trap field.
 *
 * A token names one route, carries the server time it was issued and a random nonce, and is signed with HMAC-SHA256
 * over WordPress's salts. It is accepted from 3 to 7,200 seconds after issue, once: its nonce is claimed with one
 * atomic database insert, never through a cache, and the claim is kept for the token's whole validity. The trap field
 * must arrive empty. This proves the server issued the token for that route at a human pace; it does not prove a person
 * is present (specs/009-sign-in-protection, "The account-creation check, and what it does not do").
 *
 * No external service, no third-party script, no cookie.
 *
 * Register id: AUTH-15. See specs/009-sign-in-protection, FR-005.
 */
final class SignupCheck
{
    public const FIELD_TOKEN = 'mizzey_signup_token';
    public const FIELD_TRAP  = 'mizzey_signup_extra';
    public const ROUTES      = ['account', 'checkout', 'order-received', 'wordpress'];
    public const MIN_AGE     = 3;
    public const MAX_AGE     = 7200;

    private const VERSION     = 'v1';
    private const PURPOSE     = 'mizzey-signup';
    private const MAX_LENGTH  = 512;
    private const CLAIM       = 'mizzey_signup_claim_';
    private const CLEANUP_MAX = 50;

    /**
     * A token for a route, issued now unless a time is given.
     */
    public static function issue(string $route, ?int $at = null): string
    {
        $payload = self::encode((string) wp_json_encode([
            'p' => self::PURPOSE,
            'r' => $route,
            't' => $at ?? time(),
            'n' => bin2hex(random_bytes(16)),
        ]));

        return self::VERSION . '.' . $payload . '.' . self::encode(self::sign($payload));
    }

    /**
     * The token's nonce and issue time if it is well formed, correctly signed, for this route, of an acceptable age,
     * and the trap is empty; null otherwise. Claims nothing.
     *
     * @return array{nonce:string,issued:int}|null
     */
    public static function inspect(string $route, mixed $token, mixed $trap, ?int $now = null): ?array
    {
        if (! is_string($token) || $token === '' || strlen($token) > self::MAX_LENGTH) {
            return null;
        }
        if ($trap !== null && (! is_string($trap) || $trap !== '')) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return null;
        }
        $signature = self::decode($parts[2]);
        if ($signature === null || ! hash_equals(self::sign($parts[1]), $signature)) {
            return null;
        }
        $json = self::decode($parts[1]);
        $data = $json === null ? null : json_decode($json, true, 4);
        if (! is_array($data)
            || ($data['p'] ?? null) !== self::PURPOSE
            || ! is_string($data['r'] ?? null) || $data['r'] !== $route
            || ! is_int($data['t'] ?? null)
            || ! is_string($data['n'] ?? null) || preg_match('/^[0-9a-f]{32}$/', $data['n']) !== 1
        ) {
            return null;
        }
        $age = ($now ?? time()) - $data['t'];
        if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
            return null;
        }

        return ['nonce' => $data['n'], 'issued' => $data['t']];
    }

    /**
     * Inspect the token and, if it passes, claim its nonce. True only for the one request that claims it.
     */
    public static function verify(string $route, mixed $token, mixed $trap): bool
    {
        $valid = self::inspect($route, $token, $trap);

        return $valid !== null && self::claim($valid['nonce'], $valid['issued']);
    }

    /**
     * The hidden fields a form carries: the token and the trap, which people never see or fill.
     */
    public static function fields(string $route): string
    {
        return sprintf(
            '<input type="hidden" name="%1$s" value="%2$s" />'
            . '<span class="mizzey-signup-extra" aria-hidden="true" style="display:none">'
            . '<label for="%3$s">%4$s</label>'
            . '<input type="text" id="%3$s" name="%3$s" value="" tabindex="-1" autocomplete="off" />'
            . '</span>',
            esc_attr(self::FIELD_TOKEN),
            esc_attr(self::issue($route)),
            esc_attr(self::FIELD_TRAP),
            esc_html__('Leave this field empty', 'mizzey-site'),
        );
    }

    /**
     * Remove one claim. The security check's self-test uses it so its probe leaves nothing behind.
     */
    public static function forget(string $nonce): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->options, ['option_name' => self::CLAIM . $nonce]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    public static function message(): string
    {
        return __('We could not create your account. Please reload the page and try again.', 'mizzey-site');
    }

    /**
     * Claim a nonce with one atomic insert. An existing claim, or a storage failure, refuses.
     */
    private static function claim(string $nonce, int $issued): bool
    {
        global $wpdb;
        self::cleanup();
        $expires  = $issued + self::MAX_AGE + 60;
        $inserted = $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
            self::CLAIM . $nonce,
            (string) $expires,
        ));

        return $inserted === 1;
    }

    /**
     * Remove expired claims, a bounded number at a time.
     */
    private static function cleanup(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT %d",
            $wpdb->esc_like(self::CLAIM) . '%',
            time(),
            self::CLEANUP_MAX,
        ));
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', self::VERSION . '.' . $payload, wp_salt('nonce'), true);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        if ($text === '' || preg_match('/^[A-Za-z0-9_-]+$/', $text) !== 1) {
            return null;
        }
        $bytes = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);

        return $bytes === false ? null : $bytes;
    }
}

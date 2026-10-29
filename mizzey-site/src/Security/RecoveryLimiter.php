<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

use Corex\Boot;
use Corex\Http\ClientAddress;
use Throwable;
use WP_Error;
use WP_User;

/**
 * Password-recovery requests are rate limited, per account and per client address.
 *
 * Neither WordPress, WooCommerce nor CoreX limits recovery requests: CoreX's limiter wraps only its own controllers.
 * WordPress's `retrieve_password()` and WooCommerce's account page both fire `lostpassword_post` before any email is
 * sent and abort on an error added there, so one hook covers both pages in every language.
 *
 * The client address comes from CoreX's `ClientAddress` service, the one its login lockout uses, so a visitor behind
 * the proxy is identified the same way by both. An unknown login counts against the address only, so the limit
 * cannot be used to tell whether an account exists. Each counter is a fixed window from its first request; a refused
 * request does not count and does not extend the window.
 *
 * Register id: NFR-05 (rate limiting). See specs/007-security-baseline, FR-006.
 */
final class RecoveryLimiter
{
    public const ERROR = 'mizzey_recovery_limited';

    /** @var array{account:int,address:int,window:int} */
    private const DEFAULTS = ['account' => 3, 'address' => 10, 'window' => 900];

    public static function register(): void
    {
        add_action('lostpassword_post', [self::class, 'limit'], 10, 2);
    }

    /**
     * The documented settings, filterable through `mizzey_recovery_limits`. Each value is at least 1.
     *
     * @return array{account:int,address:int,window:int}
     */
    public static function limits(): array
    {
        $filtered = apply_filters('mizzey_recovery_limits', self::DEFAULTS);
        $limits   = self::DEFAULTS;
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (is_array($filtered) && isset($filtered[$key]) && is_numeric($filtered[$key])) {
                $limits[$key] = max(1, (int) $filtered[$key]);
            }
        }

        return $limits;
    }

    /**
     * @param WP_User|false $user The account asked for, or false when no account matches.
     */
    public static function limit(WP_Error $errors, mixed $user): void
    {
        $limits   = self::limits();
        $counters = [];
        if ($user instanceof WP_User) {
            $counters[] = [self::key('account', (string) $user->ID), $limits['account']];
        }
        $address = self::address();
        if ($address !== '') {
            $counters[] = [self::key('address', $address), $limits['address']];
        }

        $now = time();
        foreach ($counters as [$key, $max]) {
            if (self::read($key, $now, $limits['window'])['count'] >= $max) {
                $errors->add(
                    self::ERROR,
                    __('Too many password reset requests. Please wait a few minutes and try again.', 'mizzey-site'),
                );

                return;
            }
        }
        foreach ($counters as [$key]) {
            $counter = self::read($key, $now, $limits['window']);
            set_transient($key, ['count' => $counter['count'] + 1, 'until' => $counter['until']], max(1, $counter['until'] - $now));
        }
    }

    /**
     * Remove the counters for an account and an address. The security check and the scenarios use it to leave
     * the runtime as they found it.
     */
    public static function forget(int $userId, string $address): void
    {
        delete_transient(self::key('account', (string) $userId));
        delete_transient(self::key('address', $address));
    }

    /**
     * @return array{count:int,until:int}
     */
    private static function read(string $key, int $now, int $window): array
    {
        $stored = get_transient($key);
        if (! is_array($stored) || ! isset($stored['count'], $stored['until']) || (int) $stored['until'] <= $now) {
            return ['count' => 0, 'until' => $now + $window];
        }

        return ['count' => (int) $stored['count'], 'until' => (int) $stored['until']];
    }

    private static function key(string $kind, string $value): string
    {
        return 'mizzey_recovery_' . substr(wp_hash($kind . '|' . $value), 0, 32);
    }

    /**
     * Resolved when a request arrives, never earlier: CoreX boots on `plugins_loaded`.
     */
    private static function address(): string
    {
        if (class_exists(Boot::class) && Boot::booted()) {
            try {
                return (string) Boot::app()->container()->make(ClientAddress::class)->current();
            } catch (Throwable) {
                // Fall through to the connection address: a limit on the wrong address beats no limit.
            }
        }
        $address = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

        return $address === false ? '' : (string) $address;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Security;

defined('ABSPATH') || exit;

/**
 * A fixed-window counter kept in the database, for the limits that protect the store: the password-recovery limits
 * (RecoveryLimiter) and the home sign-up's limits per network address and per email (Signups\SignupService).
 *
 * Kept in the options table and read and written with SQL, never through the options API or a transient, so no object
 * cache can hold it: a persistent cache that is flushed, evicts, or falls back to a request-only cache when its
 * server is unreachable would otherwise start every count again from nothing (NFR-02 review, 26 November 2026). The
 * row is `mizzey_counter_<key>` = `<count>:<until>`, not autoloaded. Rows whose window has passed are removed as new
 * ones are written. The callers serialise reading and raising a counter under their own database lock.
 */
final class WindowCounter
{
    public const PREFIX = 'mizzey_counter_';
    /** Expired rows removed per write, at most. */
    private const SWEEP = 100;

    /**
     * The count in the current window, and when that window ends; a counter never seen, or whose window has passed,
     * is zero with a window starting now.
     *
     * @return array{count:int,until:int}
     */
    public static function read(string $key, int $now, int $window): array
    {
        global $wpdb;
        $stored = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::PREFIX . $key)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if (is_string($stored) && preg_match('/^(\d+):(\d+)$/', $stored, $m) === 1 && (int) $m[2] > $now) {
            return ['count' => (int) $m[1], 'until' => (int) $m[2]];
        }

        return ['count' => 0, 'until' => $now + $window];
    }

    /** Stores a count and the end of its window, and removes some counters whose window has passed. */
    public static function write(string $key, int $count, int $until): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
            self::PREFIX . $key,
            $count . ':' . $until
        ));
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(SUBSTRING_INDEX(option_value, ':', -1) AS UNSIGNED) <= %d LIMIT %d",
            $wpdb->esc_like(self::PREFIX) . '%',
            time(),
            self::SWEEP
        ));
    }

    /** Raises a counter by one within its window. */
    public static function raise(string $key, int $now, int $window): void
    {
        $counter = self::read($key, $now, $window);
        self::write($key, $counter['count'] + 1, $counter['until']);
    }

    public static function forget(string $key): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->options, ['option_name' => self::PREFIX . $key]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

/**
 * Where Redis is and how this site uses it: one place for the object cache and the page cache (NFR-02).
 *
 * Read from the environment the hosting stack gives the PHP container (`REDIS_HOST`, `REDIS_PASSWORD`, and optionally
 * `REDIS_PORT` and `REDIS_DATABASE`), unless wp-config already defines the Redis Object Cache constants
 * (`WP_REDIS_HOST` and the rest), which then win. Nothing is cached when no host is set.
 *
 * - **One site, one key space.** Every key starts with the site's prefix: `WP_REDIS_PREFIX` when defined, otherwise
 *   `WP_CACHE_KEY_SALT`, otherwise one made from the database name and table prefix, so two sites on one Redis never
 *   read each other's entries. A database index (`REDIS_DATABASE`, default 0) separates them further when wanted.
 * - **Switches.** `MIZZEY_OBJECT_CACHE=off` or `MIZZEY_PAGE_CACHE=off` in the environment (or as constants) turns
 *   either cache off without a release.
 *
 * Loaded by the two drop-ins before WordPress has loaded its plugins, so it uses no WordPress function.
 */
final class RedisSettings
{
    /**
     * Groups kept for the request only, never in Redis. WordPress's own, and the groups WooCommerce, WPML and
     * WooCommerce Multilingual declare non-persistent themselves (`wp_cache_add_non_persistent_groups()`, which the
     * drop-in honours when they call it); listed here as well so they are request-only from the first line of a
     * request, before the class that declares them has loaded.
     */
    public const REQUEST_ONLY_GROUPS = [
        // WordPress (wp-includes/load.php).
        'counts', 'plugins', 'theme_json',
        // WooCommerce.
        'product_objects', 'woocommerce_payment_gateway_details', 'woocommerce_payments_providers',
        // WPML.
        'WPML_Cookie', 'element_language_details_misses', 'ls_languages', 'urls-to-ids-cache-key', 'wpml-settings-kept',
        'wpml_term_translation', 'get_source_language_by_trid', 'WPML_ST_Package_Factory',
        // WooCommerce Multilingual.
        'WCML\\MultiCurrency\\Geolocation', 'wpml-all-meta-product-variation', 'wpml-endpoints', 'WCML_Endpoints',
        'is_variable_product', 'converted_shipping_cost',
    ];

    /** The longest an object cache entry lives, so nothing stays in Redis for ever. */
    public const MAX_TTL = 86400;

    /** How long the object cache waits for Redis to accept a connection before it runs without it. */
    public const PROBE_SECONDS = 0.05;

    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $password,
        public readonly int $database,
        public readonly string $prefix,
        public readonly float $timeout = 1.0,
    ) {
    }

    /** The settings of this site, or null when no Redis host is configured. */
    public static function fromEnvironment(): ?self
    {
        $host = self::constant('WP_REDIS_HOST') ?? self::env('REDIS_HOST');
        if ($host === null || $host === '') {
            return null;
        }
        $port     = (int) (self::constant('WP_REDIS_PORT') ?? self::env('REDIS_PORT') ?? 6379);
        $password = (string) (self::constant('WP_REDIS_PASSWORD') ?? self::env('REDIS_PASSWORD') ?? '');
        $database = (int) (self::constant('WP_REDIS_DATABASE') ?? self::env('REDIS_DATABASE') ?? 0);
        $timeout  = (float) (self::constant('WP_REDIS_TIMEOUT') ?? 1.0);

        return new self((string) $host, $port > 0 ? $port : 6379, $password, max(0, $database), self::sitePrefix(), $timeout > 0 ? $timeout : 1.0);
    }

    /** The prefix every key of this site starts with. */
    public static function sitePrefix(): string
    {
        foreach (['WP_REDIS_PREFIX', 'WP_CACHE_KEY_SALT'] as $name) {
            $value = self::constant($name);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        $env = self::env('WP_REDIS_PREFIX');
        if ($env !== null && trim($env) !== '') {
            return trim($env);
        }
        $db    = defined('DB_NAME') ? (string) DB_NAME : '';
        $table = isset($GLOBALS['table_prefix']) && is_string($GLOBALS['table_prefix']) ? $GLOBALS['table_prefix'] : '';

        return 'mizzey-' . substr(hash('sha256', $db . '|' . $table), 0, 12) . ':';
    }

    /** Whether a cache is switched off (`MIZZEY_OBJECT_CACHE` or `MIZZEY_PAGE_CACHE` set to off, 0, false or no). */
    public static function switchedOff(string $name): bool
    {
        $value = self::constant($name) ?? self::env($name);
        if (is_bool($value)) {
            return ! $value;
        }

        return $value !== null && in_array(strtolower(trim((string) $value)), ['off', '0', 'false', 'no'], true);
    }

    /**
     * Prepares the Redis Object Cache drop-in (wp-content/object-cache.php): defines the constants it reads from this
     * site's settings, unless wp-config defined them first. Returns whether the drop-in should load: there is a host,
     * the cache is not switched off, the PhpRedis extension is there to reach it, and Redis answers a connection within
     * PROBE_SECONDS. Otherwise WordPress keeps its own cache, as it does with no drop-in, and with it its transients in
     * the database. The drop-in's own fallback for an unreachable Redis is never used: it keeps every value, transients
     * included, for the request only, while WordPress still believes an external cache holds them, so anything counted
     * in a transient would start again from nothing on every request.
     */
    public static function prepareObjectCache(): bool
    {
        $settings = self::fromEnvironment();
        if ($settings === null || self::switchedOff('MIZZEY_OBJECT_CACHE') || (! class_exists('Redis') && ! defined('WP_REDIS_CLIENT'))) {
            return false;
        }
        if (! self::reachable($settings)) {
            self::noteUnreachable();

            return false;
        }
        $define = static function (string $name, mixed $value): void {
            if (! defined($name)) {
                define($name, $value);
            }
        };
        $define('WP_REDIS_HOST', $settings->host);
        $define('WP_REDIS_PORT', $settings->port);
        if ($settings->password !== '') {
            $define('WP_REDIS_PASSWORD', $settings->password);
        }
        $define('WP_REDIS_DATABASE', $settings->database);
        $define('WP_REDIS_PREFIX', $settings->prefix);
        // A flush empties this site's keys only, never another site's on the same Redis.
        $define('WP_REDIS_SELECTIVE_FLUSH', true);
        // Redis unreachable: the request carries on with the request-only cache and logs why, never a fatal error.
        $define('WP_REDIS_GRACEFUL', true);
        $define('WP_REDIS_TIMEOUT', $settings->timeout);
        $define('WP_REDIS_READ_TIMEOUT', $settings->timeout);
        $define('WP_REDIS_MAXTTL', self::MAX_TTL);
        $define('WP_REDIS_IGNORED_GROUPS', self::REQUEST_ONLY_GROUPS);

        return true;
    }

    /** Whether Redis accepts a connection within PROBE_SECONDS; asked once per request. */
    public static function reachable(self $settings): bool
    {
        static $answers = [];
        $where = $settings->host . ':' . $settings->port;
        if (! array_key_exists($where, $answers)) {
            $errno  = 0;
            $errstr = '';
            $socket = @stream_socket_client('tcp://' . $where, $errno, $errstr, self::PROBE_SECONDS); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            $answers[$where] = is_resource($socket);
            if (is_resource($socket)) {
                fclose($socket); // phpcs:ignore WordPress.WP.AlternativeFunctions
            }
        }

        return $answers[$where];
    }

    /**
     * Says once a minute at most that the cache runs without Redis, so an outage is seen without flooding the log.
     * Names no address: the log may be read by people who should not learn where Redis lives.
     */
    public static function noteUnreachable(): void
    {
        $marker = rtrim(sys_get_temp_dir(), '/\\') . '/mizzey-redis-down-' . substr(hash('sha256', self::sitePrefix()), 0, 12);
        $last   = @filemtime($marker); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        if ($last !== false && time() - $last < 60) {
            return;
        }
        @touch($marker); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        error_log('Mizzey object cache: Redis is unreachable; this site runs on its database cache until it answers.'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    }

    private static function constant(string $name): mixed
    {
        return defined($name) ? constant($name) : null;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = $_SERVER[$name] ?? $_ENV[$name] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        }

        return is_string($value) ? $value : null;
    }
}

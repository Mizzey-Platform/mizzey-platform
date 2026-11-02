<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

use MizzeySite\Catalogue\ProductFields;

defined('ABSPATH') || exit;

/**
 * The redirect manager (MKT-13) and the log of missing pages (MKT-14), our own code (D-24: Redirection is not
 * installed). A redirect sends one path of this site, in either language, permanently (301) to another address, with
 * the visitor's query string (campaign parameters) when the target has none; staff add, change and remove them in
 * Settings, Search engines. A request that ends in "page not found" is counted by path, with the time it was last asked
 * for and where it came from (no visitor address is kept), so staff can see what is missing and redirect it.
 *
 * The redirects are staff's, in an option. The counts are written by visitors, so they live in a small table of their
 * own (`{prefix}mizzey_seo_log`), each one a single atomic row update: no visitor request rewrites an option. New
 * missing paths are taken at most `NEW_PER_MINUTE` a minute, and the log keeps `KEEP` paths, dropping the least asked
 * first, so a scanner cannot push out the pages staff need.
 *
 * Register ids: MKT-13, MKT-14. See specs/038-seo-controls.
 */
final class Redirects
{
    public const OPTION  = 'mizzey_redirects';
    public const MISSING = 'mizzey_missing_pages'; // The log before it moved to the table; read once, then removed.
    public const KEEP    = 500;
    public const NEW_PER_MINUTE = 30;
    public const SCHEMA  = 'mizzey_seo_log_schema';
    public const VERSION = '1';

    /** A redirect's use and a missing page, as the table names their kind. */
    private const REDIRECT = 'r';
    private const MISS     = 'm';
    private const ADMITTED = 'n'; // One row per minute: how many new missing paths were admitted in it.

    public static function register(): void
    {
        add_action('init', [self::class, 'install'], 1);
        add_action('template_redirect', [self::class, 'follow'], 0);
        add_action('template_redirect', [self::class, 'logMissing'], 99);
    }

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'mizzey_seo_log';
    }

    /** True once the table exists. Until then nothing is counted. */
    public static function ready(): bool
    {
        return get_option(self::SCHEMA) === self::VERSION;
    }

    /** Creates the table once (idempotent), and moves the old option log into it. */
    public static function install(): void
    {
        if (self::ready()) {
            return;
        }
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'seo_log_install', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return; // Another request is installing it.
        }
        try {
            wp_cache_delete(self::SCHEMA, 'options');
            if (self::ready()) {
                return;
            }
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $table = self::table();
            dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  kind char(1) NOT NULL,
  path_hash char(32) NOT NULL,
  path varchar(300) NOT NULL,
  hits int(10) unsigned NOT NULL DEFAULT 0,
  first_seen int(10) unsigned NOT NULL DEFAULT 0,
  last_seen int(10) unsigned NOT NULL DEFAULT 0,
  referrer varchar(300) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY kind_path (kind,path_hash),
  KEY kind_first (kind,first_seen)
) " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                return;
            }
            $old = get_option(self::MISSING, []);
            foreach (array_slice(is_array($old) ? $old : [], 0, self::KEEP, true) as $path => $m) {
                $path = mb_substr((string) $path, 0, 300);
                $last = (int) ($m['last'] ?? time());
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (kind, path_hash, path, hits, first_seen, last_seen, referrer) VALUES (%s, %s, %s, %d, %d, %d, %s)", self::MISS, md5($path), $path, max(1, (int) ($m['hits'] ?? 1)), $last, $last, mb_substr((string) ($m['referrer'] ?? ''), 0, 300))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            delete_option(self::MISSING);
            update_option(self::SCHEMA, self::VERSION, true);
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** A path as it is compared: lower case, no query, one leading slash, no trailing slash. */
    public static function normalise(string $path): string
    {
        $path = (string) wp_parse_url($path, PHP_URL_PATH);
        $path = '/' . trim(strtolower(rawurldecode($path)), '/');

        return $path;
    }

    /** @return array<string,array{to:string,hits:int,created:int}> The redirects as staff set them. */
    private static function rules(): array
    {
        $all = get_option(self::OPTION, []);

        return is_array($all) ? $all : [];
    }

    /** @return array<string,array{to:string,hits:int,created:int}> The redirects, each with the times it was used. */
    public static function all(): array
    {
        $all = self::rules();
        if ($all === [] || ! self::ready()) {
            return $all;
        }
        global $wpdb;
        $table = self::table();
        $rows  = (array) $wpdb->get_results($wpdb->prepare("SELECT path_hash, hits FROM {$table} WHERE kind = %s", self::REDIRECT), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $hits  = array_column($rows, 'hits', 'path_hash');
        foreach ($all as $from => $r) {
            // A count kept in the option before the table existed is added to the table's.
            $all[$from]['hits'] = (int) ($r['hits'] ?? 0) + (int) ($hits[md5((string) $from)] ?? 0);
        }

        return $all;
    }

    /** Add or change a redirect. Refuses a loop, an empty path and an address that is not http or https. */
    public static function save(string $from, string $to): ?string
    {
        $from = self::normalise($from);
        $to   = trim($to);
        if (str_starts_with($to, '/') && ! str_starts_with($to, '//')) {
            // The site's own root, not home_url(): WPML rewrites that to the admin's language and drops "/ar/".
            $to = untrailingslashit((string) get_option('home')) . $to;
        }
        // A full address with a host only: a bare "?query" or "#part" would send the old path back to itself.
        $to = ProductFields::webAddress($to);
        if ($from === '/' || $to === '') {
            return __('Give the old path and a full new address.', 'mizzey-site');
        }
        $all = self::rules();
        if (self::loops($from, $to, $all)) {
            return __('A page cannot be redirected to itself, directly or through other redirects.', 'mizzey-site');
        }
        $all[$from] = ['to' => $to, 'hits' => (int) ($all[$from]['hits'] ?? 0), 'created' => (int) ($all[$from]['created'] ?? time())];
        update_option(self::OPTION, $all, false);
        self::forget(self::MISS, $from);

        return null;
    }

    /**
     * True when the target leads back to `$from`, at once or by following the redirects already saved on this site.
     *
     * @param array<string,array{to:string}> $all
     */
    private static function loops(string $from, string $to, array $all): bool
    {
        $home = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $seen = [];
        for ($step = 0; $step < 50; $step++) {
            if (strtolower((string) wp_parse_url($to, PHP_URL_HOST)) !== $home) {
                return false; // Another site: the chain ends there.
            }
            $path = self::normalise($to);
            if ($path === $from) {
                return true;
            }
            if (isset($seen[$path]) || ! isset($all[$path]['to'])) {
                return false;
            }
            $seen[$path] = true;
            $to          = (string) $all[$path]['to'];
        }

        return true; // A chain this long is refused too.
    }

    public static function remove(string $from): void
    {
        $from = self::normalise($from);
        $all  = self::rules();
        unset($all[$from]);
        update_option(self::OPTION, $all, false);
        self::forget(self::REDIRECT, $from);
    }

    /** The redirect for the current request, before WordPress looks for a page. */
    public static function follow(): void
    {
        if (is_admin() || ! isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $uri  = wp_unslash((string) $_SERVER['REQUEST_URI']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only compared; the query goes through wp_redirect()'s own cleaning.
        $path = self::normalise($uri);
        $all  = self::rules();
        if (! isset($all[$path]['to'])) {
            return;
        }
        self::count(self::REDIRECT, $path, '');
        $to = self::withQuery((string) $all[$path]['to'], (string) wp_parse_url($uri, PHP_URL_QUERY));
        if (wp_redirect($to, 301, 'Mizzey')) { // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- staff choose the address, which may be another site.
            exit;
        }
    }

    /** The visitor's query string (campaign parameters) carried to a target that has none of its own. */
    public static function withQuery(string $to, string $query): string
    {
        if ($query === '' || strlen($query) > 2000 || str_contains($to, '?')) {
            return $to;
        }
        $parts = explode('#', $to, 2);

        return $parts[0] . '?' . $query . (isset($parts[1]) ? '#' . $parts[1] : '');
    }

    /** @return array<string,array{hits:int,last:int,referrer:string}> The missing pages, the latest asked first. */
    public static function missing(): array
    {
        if (! self::ready()) {
            return [];
        }
        global $wpdb;
        $table = self::table();
        $rows  = (array) $wpdb->get_results($wpdb->prepare("SELECT path, hits, last_seen, referrer FROM {$table} WHERE kind = %s ORDER BY last_seen DESC LIMIT %d", self::MISS, self::KEEP), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out   = [];
        foreach ($rows as $row) {
            $out[(string) $row['path']] = ['hits' => (int) $row['hits'], 'last' => (int) $row['last_seen'], 'referrer' => (string) $row['referrer']];
        }

        return $out;
    }

    /** Empties the missing-page log (staff's "Clear the list"). */
    public static function clearMissing(): void
    {
        if (self::ready()) {
            global $wpdb;
            $table = self::table();
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE kind = %s", self::MISS)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public static function logMissing(): void
    {
        if (! is_404() || is_admin() || ! isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $path = mb_substr(self::normalise(wp_unslash((string) $_SERVER['REQUEST_URI'])), 0, 300); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised and escaped on output.
        if (preg_match('#\.(php|env|git|sql|bak|ini|log)(/|$)#', $path) === 1) {
            return; // Probes for server files are not missing pages.
        }
        $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER'])) : '';
        self::count(self::MISS, $path, mb_substr($referrer, 0, 300));
    }

    /** The named lock that admits new missing paths one at a time. */
    public static function admitLock(): string
    {
        global $wpdb;

        return substr('mz_' . $wpdb->prefix . 'seo_log_admit', 0, 64);
    }

    /**
     * One more use of a path, as a single row update. A new missing path is admitted under a named lock, one request at
     * a time: only while fewer than `NEW_PER_MINUTE` were admitted this minute (counted as admissions, in a counter
     * row, so paths trimmed again at once still count), and the log is then trimmed to `KEEP` paths, the least asked
     * and the oldest leaving first. A request that cannot take the lock within a second admits nothing. A redirect's
     * path is always counted: staff set those, so they are few.
     */
    private static function count(string $kind, string $path, string $referrer): void
    {
        if (! self::ready()) {
            return;
        }
        global $wpdb;
        $table = self::table();
        $now   = time();
        $hash  = md5($path);
        $update = static fn (): int => (int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET hits = hits + 1, last_seen = %d, referrer = IF(%s = '', referrer, %s) WHERE kind = %s AND path_hash = %s", $now, $referrer, $referrer, $kind, $hash)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($update() > 0) {
            return;
        }
        $insert = static fn () => $wpdb->query($wpdb->prepare("INSERT INTO {$table} (kind, path_hash, path, hits, first_seen, last_seen, referrer) VALUES (%s, %s, %s, 1, %d, %d, %s) ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = %d", $kind, $hash, mb_substr($path, 0, 300), $now, $now, $referrer, $now)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($kind !== self::MISS) {
            $insert();
            return;
        }
        $lock = self::admitLock();
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
        }
        try {
            // Logged by another request while this one waited for the lock: one more hit, not a new admission.
            if ($update() > 0) {
                return;
            }
            $minute = 'minute-' . intdiv($now, MINUTE_IN_SECONDS);
            $taken  = (int) $wpdb->get_var($wpdb->prepare("SELECT hits FROM {$table} WHERE kind = %s AND path_hash = %s", self::ADMITTED, md5($minute))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($taken >= self::NEW_PER_MINUTE) {
                return;
            }
            $insert();
            $wpdb->query($wpdb->prepare("INSERT INTO {$table} (kind, path_hash, path, hits, first_seen, last_seen, referrer) VALUES (%s, %s, %s, 1, %d, %d, '') ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = %d", self::ADMITTED, md5($minute), $minute, $now, $now, $now)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE kind = %s AND first_seen < %d", self::ADMITTED, $now - 2 * MINUTE_IN_SECONDS)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // Counted and trimmed by one request at a time, so two requests never both trim for the same excess.
            $over = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE kind = %s", $kind)) - self::KEEP; // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($over > 0) {
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE kind = %s ORDER BY hits ASC, last_seen ASC LIMIT %d", $kind, $over)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** Takes one path's count off the table: a missing page once redirected, a redirect once removed. */
    private static function forget(string $kind, string $path): void
    {
        if (self::ready()) {
            global $wpdb;
            $table = self::table();
            $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE kind = %s AND path_hash = %s", $kind, md5($path))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }
}

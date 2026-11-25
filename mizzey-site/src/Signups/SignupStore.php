<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

/**
 * The home page's sign-up list: one small table of its own (`{prefix}mizzey_signups`), created once on `init` the
 * way the plugin's other small stores are (Seo\Redirects), and the only code that reads or writes it.
 *
 * One row per address: the address in lower case (unique), the language it signed up in, the source ("home"), the
 * status (subscribed or left), the marketing consent with the version of the words it was given to and when, a random
 * token for leaving, and the dates. No network address and no account is kept. Nothing here sends anything: there is
 * no campaign engine (DECISIONS A10).
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class SignupStore
{
    public const SCHEMA  = 'mizzey_signups_schema';
    public const VERSION = '1';

    public const SUBSCRIBED = 'subscribed';
    public const LEFT       = 'left';

    public static function register(): void
    {
        add_action('init', [self::class, 'install'], 1);
    }

    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'mizzey_signups';
    }

    public static function ready(): bool
    {
        return get_option(self::SCHEMA) === self::VERSION;
    }

    /** Creates the table once (idempotent); one request installs it while others wait for the next. */
    public static function install(): void
    {
        if (self::ready()) {
            return;
        }
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'signups_install', 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return;
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
  email varchar(191) NOT NULL,
  language varchar(10) NOT NULL DEFAULT '',
  source varchar(20) NOT NULL DEFAULT 'home',
  status varchar(12) NOT NULL DEFAULT 'subscribed',
  consent tinyint(1) unsigned NOT NULL DEFAULT 0,
  consent_version varchar(20) NOT NULL DEFAULT '',
  consent_at datetime NULL DEFAULT NULL,
  token char(40) NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  left_at datetime NULL DEFAULT NULL,
  welcomed_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  UNIQUE KEY token (token),
  KEY status (status)
) " . $wpdb->get_charset_collate() . ';');
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                update_option(self::SCHEMA, self::VERSION, true);
            }
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /**
     * @return array<string,mixed>|null
     * @throws SignupStoreError
     */
    public static function find(string $email): ?array
    {
        return self::row('email', $email);
    }

    /** @return array<string,mixed>|null */
    public static function byToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{40}$/', $token) === 1 ? self::row('token', $token) : null;
    }

    /** @return array<string,mixed>|null */
    public static function byId(int $id): ?array
    {
        return $id > 0 ? self::row('id', $id) : null;
    }

    /**
     * A new record's id; 0 when nothing was inserted (the address is already stored: a parallel request inserted it
     * first).
     *
     * @param array{email:string,language:string,consent:bool,consent_version:string} $data
     * @throws SignupStoreError When the database refuses the write.
     */
    public static function insert(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $given = $data['consent'];
        // INSERT IGNORE: the unique address decides between two parallel first sign-ups, without a database error.
        $ok = self::quiet(static fn () => $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            'INSERT IGNORE INTO ' . self::table() . ' (email, language, source, status, consent, consent_version, consent_at, token, created_at, updated_at) VALUES (%s, %s, %s, %s, %d, %s, IF(%d = 1, %s, NULL), %s, %s, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $data['email'],
            $data['language'],
            'home',
            self::SUBSCRIBED,
            $given ? 1 : 0,
            $given ? $data['consent_version'] : '',
            $given ? 1 : 0,
            $now,
            self::newToken(),
            $now,
            $now
        )));

        if ($ok === false) {
            throw new SignupStoreError((string) $wpdb->last_error);
        }

        return $ok === 1 ? (int) $wpdb->insert_id : 0;
    }

    /**
     * @param array<string,string|int|null> $fields
     * @throws SignupStoreError When the database refuses the write.
     */
    public static function update(int $id, array $fields): void
    {
        global $wpdb;
        $fields['updated_at'] = current_time('mysql', true);
        if (self::quiet(static fn () => $wpdb->update(self::table(), $fields, ['id' => $id])) === false) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            throw new SignupStoreError((string) $wpdb->last_error);
        }
    }

    /**
     * Records whose address contains the words searched for, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public static function search(string $words, int $page, int $perPage): array
    {
        global $wpdb;
        $where = self::where($words);

        return (array) self::quiet(static fn () => $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . " {$where} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $perPage, max(0, ($page - 1) * $perPage)), ARRAY_A)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /** How many welcomes were sent since a time (a Unix timestamp), for the hourly ceiling. */
    public static function welcomedSince(int $since): int
    {
        global $wpdb;

        return (int) self::quiet(static fn () => $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE welcomed_at >= %s', gmdate('Y-m-d H:i:s', $since)))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
    }

    /** How many records match the search, and how many of them are subscribed. @return array{all:int,subscribed:int} */
    public static function count(string $words = ''): array
    {
        global $wpdb;
        $where = self::where($words);
        $row   = self::quiet(static fn () => $wpdb->get_row($wpdb->prepare('SELECT COUNT(*) AS n, COALESCE(SUM(status = %s), 0) AS s FROM ' . self::table() . " {$where}", self::SUBSCRIBED), ARRAY_A)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return ['all' => (int) ($row['n'] ?? 0), 'subscribed' => (int) ($row['s'] ?? 0)];
    }

    /** The search as a prepared WHERE clause, or nothing. */
    private static function where(string $words): string
    {
        global $wpdb;
        $words = trim($words);

        return $words === '' ? '' : (string) $wpdb->prepare('WHERE email LIKE %s', '%' . $wpdb->esc_like(strtolower($words)) . '%');
    }

    /** @return array<string,mixed>|null */
    /**
     * @return array<string,mixed>|null Null when no record matches.
     * @throws SignupStoreError When the store is not installed or the database refuses the read.
     */
    private static function row(string $column, string|int $value): ?array
    {
        global $wpdb;
        if (! self::ready()) {
            throw new SignupStoreError('the sign-up table is not installed');
        }
        $wpdb->last_error = '';
        $row = self::quiet(static fn () => $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE {$column} = " . (is_int($value) ? '%d' : '%s'), $value), ARRAY_A)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->last_error !== '') {
            throw new SignupStoreError((string) $wpdb->last_error);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * Runs a query with wpdb's error printing off, then puts the setting back as it was. A failing query would
     * otherwise be written whole to the PHP error log, with the address or the leave token in it; the error is still
     * in `$wpdb->last_error` for the caller, which raises SignupStoreError, logged without the address.
     *
     * @template T
     * @param callable(): T $query
     * @return T
     */
    private static function quiet(callable $query)
    {
        global $wpdb;
        $was = $wpdb->suppress_errors(true);
        try {
            return $query();
        } finally {
            $wpdb->suppress_errors($was);
        }
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(20));
    }
}

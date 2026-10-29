<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * Stops any catalogue write while a preview or a validation run is active (AC-5).
 *
 * It sits on `query`, which runs before every database statement, so it also catches direct SQL that no WordPress
 * hook would see. Each statement is tokenised: quoted values and comments are set aside (MySQL's executable `/*!`
 * comments are read as code), so a statement is judged by its own keywords and identifiers, never by text inside a
 * value. A statement that changes a protected catalogue table (products and media, their metadata, terms and their
 * relationships, WooCommerce's product lookup tables, WPML's translation records) is refused before it runs.
 * Everything else is allowed on purpose: reading leaves housekeeping behind, such as WPML's `_icl_cache` and expired
 * transients removed on read, and refusing it would break reading. This permits no catalogue repair.
 *
 * The plugin installs it as WordPress loads when a catalogue command is the one being run, so the bootstrap is covered
 * too, and the command installs it again around its own work; it is installed once either way.
 *
 * Register ids: MIG-03, MIG-06. See specs/006-catalogue-import-spec, FR-007 and plan.md (CatalogueWriteGuard).
 */
final class CatalogueWriteGuard
{
    private const MUTATIONS = ['INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE', 'ALTER', 'DROP', 'CREATE', 'RENAME', 'LOAD'];
    private const READS     = ['SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'DESC', 'SET', 'DO', 'CHECKSUM'];

    private const TOKENS = <<<'PATTERN'
~'(?:\\.|''|[^'\\])*'|"(?:\\.|""|[^"\\])*"|`(?:``|[^`])*`|/\*.*?\*/|--[ \t][^\r\n]*|#[^\r\n]*|[A-Za-z0-9_$]+|[();.,]~s
PATTERN;

    /** The exit code a refusal ends a run with (contracts/cli.md). */
    public const EXIT = 3;

    /** @var list<string>|null */
    private static ?array $tables = null;

    /** Whether a command is running and will record a refusal itself; before that, the guard ends the process. */
    private static bool $commandActive = false;

    /**
     * Installed by the plugin as WordPress loads. A refusal now has no command to record it, so it stops the process
     * with its reason and exit 3, rather than surfacing as a generic fatal error.
     */
    public static function installForBootstrap(): void
    {
        self::add();
    }

    /** Installed by the command around its own work; a refusal is thrown for the command to record. */
    public static function install(): void
    {
        self::$commandActive = true;
        self::add();
    }

    public static function remove(): void
    {
        self::$commandActive = false;
        remove_filter('query', [self::class, 'refuseCatalogueWrites'], PHP_INT_MAX);
    }

    private static function add(): void
    {
        if (has_filter('query', [self::class, 'refuseCatalogueWrites']) === false) {
            add_filter('query', [self::class, 'refuseCatalogueWrites'], PHP_INT_MAX);
        }
    }

    /**
     * @throws CatalogueWriteRefused Naming the statement, before it runs.
     */
    public static function refuseCatalogueWrites(string $query): string
    {
        $protected = array_map('strtolower', self::tables());
        foreach (self::statements($query) as $tokens) {
            if (! self::mutates($tokens)) {
                continue;
            }
            foreach ($tokens as $token) {
                $identifier = strtolower(str_replace('``', '`', trim($token, '`')));
                if (in_array($identifier, $protected, true)) {
                    $message = sprintf(
                        /* translators: 1: a table, 2: the start of the statement. */
                        __('A catalogue write was attempted during a read-only run and refused (table %1$s): %2$s', 'mizzey-site'),
                        $identifier,
                        substr(preg_replace('/\s+/', ' ', $query) ?? '', 0, 160),
                    );
                    if (! self::$commandActive && class_exists(\WP_CLI::class)) {
                        \WP_CLI::warning($message);
                        \WP_CLI::halt(self::EXIT);
                    }
                    throw new CatalogueWriteRefused($message);
                }
            }
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public static function tables(): array
    {
        global $wpdb;
        if (self::$tables === null) {
            self::$tables = [
                $wpdb->posts, $wpdb->postmeta, $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta,
                $wpdb->prefix . 'wc_product_meta_lookup', $wpdb->prefix . 'wc_product_attributes_lookup',
                $wpdb->prefix . 'woocommerce_attribute_taxonomies', $wpdb->prefix . 'icl_translations',
            ];
        }

        return self::$tables;
    }

    /**
     * The statements of a query, each as its code tokens: values and comments set aside, executable comments read.
     *
     * @return list<list<string>>
     */
    private static function statements(string $query): array
    {
        $query = preg_replace('~/\*M?!\d*\s*(.*?)\*/~s', ' $1 ', $query) ?? $query;
        preg_match_all(self::TOKENS, $query, $matches);
        $statements = [[]];
        foreach ($matches[0] as $token) {
            $first = $token[0];
            if ($first === "'" || $first === '"' || str_starts_with($token, '/*') || str_starts_with($token, '--') || $first === '#') {
                continue;
            }
            if ($token === ';') {
                $statements[] = [];
                continue;
            }
            $statements[array_key_last($statements)][] = $token;
        }

        return $statements;
    }

    /**
     * Whether a statement changes data: the operation keyword, read after any common table expressions, is a mutation.
     * A CTE's name is an identifier, never the operation (`WITH checksum AS (...) UPDATE ...` is an update). A `WITH`
     * clause that cannot be read is treated as a mutation: the guard fails closed.
     *
     * @param list<string> $tokens
     */
    private static function mutates(array $tokens): bool
    {
        $position = 0;
        if (strtoupper($tokens[$position] ?? '') === 'WITH') {
            ++$position;
            if (strtoupper($tokens[$position] ?? '') === 'RECURSIVE') {
                ++$position;
            }
            while (true) {
                if (! isset($tokens[$position])) {
                    return true;
                }
                ++$position;
                if (($tokens[$position] ?? '') === '(' && ! self::skipGroup($tokens, $position)) {
                    return true;
                }
                if (strtoupper($tokens[$position] ?? '') !== 'AS') {
                    return true;
                }
                ++$position;
                if (! self::skipGroup($tokens, $position)) {
                    return true;
                }
                if (($tokens[$position] ?? '') !== ',') {
                    break;
                }
                ++$position;
            }
        }
        $keyword = strtoupper($tokens[$position] ?? '');
        if (in_array($keyword, self::READS, true)) {
            return false;
        }

        return in_array($keyword, self::MUTATIONS, true);
    }

    /**
     * Moves past one parenthesised group starting at `$position`.
     *
     * @param list<string> $tokens
     */
    private static function skipGroup(array $tokens, int &$position): bool
    {
        if (($tokens[$position] ?? '') !== '(') {
            return false;
        }
        $depth = 0;
        do {
            $token = $tokens[$position++];
            if ($token === '(') {
                ++$depth;
            } elseif ($token === ')') {
                --$depth;
            }
        } while ($depth > 0 && isset($tokens[$position]));

        return $depth === 0;
    }
}

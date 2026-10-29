<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import\Cli;

defined('ABSPATH') || exit;

use InvalidArgumentException;
use MizzeySite\Catalogue\Import\CatalogueWriteGuard;
use MizzeySite\Catalogue\Import\CatalogueWriteRefused;
use MizzeySite\Catalogue\Import\ExportReader;
use MizzeySite\Catalogue\Import\FileImportRunRecorder;
use MizzeySite\Catalogue\Import\HeaderCheck;
use MizzeySite\Catalogue\Import\Mapping;
use MizzeySite\Catalogue\Import\ReportWriter;
use MizzeySite\Catalogue\Import\RowInterpreter;
use MizzeySite\Catalogue\Import\ValidationPass;
use RuntimeException;
use WP_CLI;

/**
 * Previews and validates the agreed catalogue export, writing nothing to the catalogue.
 *
 * Developer-operated only: there is no screen (FR-011). Exit codes: 0 done, 1 file-level failure, 2 usage error
 * (unreadable file, unusable mapping, output that cannot be written), 3 a catalogue write was attempted and refused.
 *
 * Register ids: MIG-01, MIG-03, MIG-04, MIG-06, MIG-19. See specs/006-catalogue-import-spec, contracts/cli.md.
 */
final class CatalogueCommand
{
    private const EXIT_FILE_FAILURE = 1;
    private const EXIT_USAGE        = 2;
    private const EXIT_GUARD        = 3;

    /**
     * Shows the first rows of the export as the store would read them under the mapping.
     *
     * ## OPTIONS
     *
     * <file>
     * : The agreed export.
     *
     * [--rows=<n>]
     * : How many data rows to show.
     * ---
     * default: 10
     * ---
     *
     * [--mapping=<path>]
     * : The import specification. Default: the plugin's import/catalogue-mapping.json.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function preview(array $args, array $assoc): void
    {
        $mapping = $this->mapping($assoc);
        $file    = $this->file($args);
        CatalogueWriteGuard::install();
        try {
            $read   = ExportReader::read($file, $mapping->source(), max(1, (int) ($assoc['rows'] ?? 10)));
            $header = HeaderCheck::check($read['header'], $mapping);
            WP_CLI::log(sprintf(
                /* translators: 1: mapping version, 2: rules version, 3: format, 4: encoding. */
                __('Mapping %1$s (initial-load rules %2$d), format %3$s, encoding %4$s', 'mizzey-site'),
                $mapping->version(),
                $mapping->rulesVersion(),
                $mapping->source()['format'],
                $mapping->source()['encoding'],
            ));
            $this->printHeader($header);
            if ($header['failures'] !== []) {
                WP_CLI::halt(self::EXIT_FILE_FAILURE);
            }
            foreach ($read['rows'] as $raw) {
                $row = RowInterpreter::interpret($raw, $read['header'], $mapping);
                WP_CLI::log('');
                WP_CLI::log(sprintf(
                    /* translators: 1: row number, 2: row type. */
                    __('Row %1$d (%2$s)', 'mizzey-site'),
                    $row['row'],
                    $row['type'] ?? $row['type_source'],
                ));
                $fields = ['sku' => $row['sku_source'], 'parent_sku' => $row['parent_sku_source'], 'regular_price' => (string) $row['price']]
                    + $row['fields'];
                foreach ($row['attributes'] as $slug => $value) {
                    $fields['attribute_' . $slug] = $value;
                }
                foreach ($fields as $field => $value) {
                    WP_CLI::log(sprintf('  %-16s %s', $field, $value));
                }
                foreach ($row['issues'] as $issue) {
                    WP_CLI::log(sprintf('  ! %s %s', $issue['code'], $issue['problem']));
                }
            }
        } catch (CatalogueWriteRefused $refused) {
            WP_CLI::warning($refused->getMessage());
            WP_CLI::halt(self::EXIT_GUARD);
        } catch (RuntimeException $failure) {
            WP_CLI::warning($failure->getMessage());
            WP_CLI::halt(self::EXIT_USAGE);
        } finally {
            CatalogueWriteGuard::remove();
        }
    }

    /**
     * Validates the whole export and writes the report, the Client issue list and the run record.
     *
     * ## OPTIONS
     *
     * <file>
     * : The agreed export.
     *
     * --out=<dir>
     * : Where validation-report.csv, client-issues.csv and run.json are written.
     *
     * [--mapping=<path>]
     * : The import specification. Default: the plugin's import/catalogue-mapping.json.
     *
     * @param list<string>          $args
     * @param array<string, string> $assoc
     */
    public function validate(array $args, array $assoc): void
    {
        $mapping = $this->mapping($assoc);
        $file    = $this->file($args);
        $out     = rtrim((string) ($assoc['out'] ?? ''), '/\\');
        if ($out === '' || ! wp_mkdir_p($out) || ! is_writable($out)) {
            WP_CLI::warning(__('--out must name a directory that can be written.', 'mizzey-site'));
            WP_CLI::halt(self::EXIT_USAGE);
        }

        $recorder = new FileImportRunRecorder($out);
        $payload  = [
            'file'            => ['name' => basename($file), 'reference' => (string) realpath($file)],
            'mapping_used'    => $this->mappingReference($mapping),
            'mapping_version' => $mapping->version(),
            'rules_version'   => $mapping->rulesVersion(),
            'actor'           => $this->actor(),
            'started_at'      => gmdate('c'),
        ];
        $exit = 0;
        CatalogueWriteGuard::install();
        try {
            $read   = ExportReader::read($file, $mapping->source());
            $header = HeaderCheck::check($read['header'], $mapping);
            $payload['header'] = $header;
            $this->printHeader($header);
            if ($header['failures'] !== []) {
                $payload += ['outcome' => 'file-level-failure', 'reason' => implode(' ', array_column($header['failures'], 'detail')), 'counts' => null, 'reports' => []];
                $exit = self::EXIT_FILE_FAILURE;
            } else {
                $result  = ValidationPass::run($mapping, $read);
                $reports = ReportWriter::write($out, $result['lines'], $result['client_issues']);
                $payload += ['outcome' => 'completed', 'reason' => null, 'counts' => $result['counts'], 'reports' => $reports];
                foreach ($result['counts'] as $name => $count) {
                    WP_CLI::log(sprintf('%-18s %d', $name, $count));
                }
                WP_CLI::log(sprintf('%-18s %s', __('peak memory', 'mizzey-site'), size_format(memory_get_peak_usage(true), 1)));
            }
        } catch (CatalogueWriteRefused $refused) {
            $payload += ['outcome' => 'guard-refused', 'reason' => $refused->getMessage(), 'counts' => null, 'reports' => []];
            $exit = self::EXIT_GUARD;
        } catch (RuntimeException $failure) {
            $payload += ['outcome' => 'output-failure', 'reason' => $failure->getMessage(), 'counts' => null, 'reports' => []];
            $exit = self::EXIT_USAGE;
        } finally {
            CatalogueWriteGuard::remove();
        }

        $payload['finished_at'] = gmdate('c');
        try {
            $payload['error_reference'] = $exit === 0 ? null : $payload['outcome'];
            WP_CLI::log(sprintf(
                /* translators: %s: a file path. */
                __('Run recorded: %s', 'mizzey-site'),
                $recorder->record($payload),
            ));
        } catch (RuntimeException $failure) {
            WP_CLI::warning($failure->getMessage());
            $exit = $exit === 0 ? self::EXIT_USAGE : $exit;
        }
        if ($exit !== 0) {
            WP_CLI::warning((string) $payload['reason']);
            WP_CLI::halt($exit);
        }
        WP_CLI::success(__('Validation completed. Review client-issues.csv before it is sent (MIG-19).', 'mizzey-site'));
    }

    /**
     * @param array<string, string> $assoc
     */
    private function mapping(array $assoc): Mapping
    {
        $path = (string) ($assoc['mapping'] ?? self::defaultMapping());
        try {
            return Mapping::load($path);
        } catch (InvalidArgumentException $invalid) {
            WP_CLI::warning(__('The mapping cannot be used:', 'mizzey-site') . "\n" . $invalid->getMessage());
            WP_CLI::halt(self::EXIT_USAGE);
        }
    }

    /**
     * @param list<string> $args
     */
    private function file(array $args): string
    {
        $file = (string) ($args[0] ?? '');
        if ($file === '' || ! is_readable($file)) {
            WP_CLI::warning(__('Give the path of a readable export file.', 'mizzey-site'));
            WP_CLI::halt(self::EXIT_USAGE);
        }

        return $file;
    }

    /**
     * @param array{failures:list<array{code:string,detail:string}>,warnings:list<array{code:string,detail:string}>,complete:bool} $header
     */
    private function printHeader(array $header): void
    {
        foreach (array_merge($header['failures'], $header['warnings']) as $finding) {
            WP_CLI::log(sprintf('%s %s', $finding['code'], $finding['detail']));
        }
        WP_CLI::log($header['complete']
            ? __('Header: every column is mapped or recorded as not imported.', 'mizzey-site')
            : __('Header: the mapping is not complete for this file.', 'mizzey-site'));
    }

    private function mappingReference(Mapping $mapping): string
    {
        $plugin = wp_normalize_path(dirname(__DIR__, 4)) . '/';
        $path   = wp_normalize_path((string) realpath($mapping->path));

        return str_starts_with($path, $plugin) ? 'mizzey-site/' . substr($path, strlen($plugin)) : $path;
    }

    private function actor(): string
    {
        $user = wp_get_current_user();

        return $user->exists() ? $user->user_login : 'wp-cli';
    }

    public static function defaultMapping(): string
    {
        return dirname(__DIR__, 4) . '/import/catalogue-mapping.json';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Writes the validation report and the Client issue list as CSV for Excel.
 *
 * UTF-8 with a byte-order mark, so Excel shows Arabic correctly, and English headings (ADM-159). The Client list is
 * what the Developer reviews and sends (MIG-19); writing it is not the review.
 *
 * Register ids: MIG-06, MIG-19. See specs/006-catalogue-import-spec, research R-8.
 */
final class ReportWriter
{
    public const REPORT  = 'validation-report.csv';
    public const ISSUES  = 'client-issues.csv';
    private const REPORT_COLUMNS = ['row', 'sku', 'type', 'outcome', 'rule', 'reason', 'resolved_id', 'language_role', 'translations', 'issues', 'near_matches'];
    private const ISSUE_COLUMNS  = ['row', 'sku', 'field', 'problem', 'correction', 'issue_code'];

    /**
     * @param list<array<string,string>> $lines
     * @param list<array<string,string>> $clientIssues
     *
     * @return list<string> The paths written.
     *
     * @throws RuntimeException When a file cannot be written.
     */
    public static function write(string $directory, array $lines, array $clientIssues): array
    {
        return [
            self::csv($directory . '/' . self::REPORT, self::REPORT_COLUMNS, $lines),
            self::csv($directory . '/' . self::ISSUES, self::ISSUE_COLUMNS, $clientIssues),
        ];
    }

    /**
     * @param list<string>               $columns
     * @param list<array<string,string>> $rows
     */
    private static function csv(string $path, array $columns, array $rows): string
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                /* translators: %s: a file path. */
                __('The report cannot be written: %s', 'mizzey-site'),
                $path,
            ));
        }
        try {
            $complete = fwrite($handle, "\xEF\xBB\xBF") === 3 && self::writeRow($handle, $columns);
            foreach ($rows as $row) {
                $complete = $complete && self::writeRow($handle, array_map(static fn (string $column): string => $row[$column] ?? '', $columns));
            }
            if (! $complete || ! fflush($handle)) {
                throw new RuntimeException(sprintf(
                    /* translators: %s: a file path. */
                    __('The report was not written completely: %s', 'mizzey-site'),
                    $path,
                ));
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * Encodes one row in memory, then writes it and checks every byte arrived: `fputcsv()` on the file itself would
     * report a short write as success.
     *
     * @param resource     $handle
     * @param list<string> $values
     */
    private static function writeRow($handle, array $values): bool
    {
        $buffer = fopen('php://memory', 'w+b');
        if ($buffer === false) {
            return false;
        }
        try {
            $length = fputcsv($buffer, $values, ',', '"', '');
            if ($length === false || ! rewind($buffer)) {
                return false;
            }
            $csv = stream_get_contents($buffer);

            return is_string($csv) && strlen($csv) === $length && fwrite($handle, $csv) === $length;
        } finally {
            fclose($buffer);
        }
    }
}

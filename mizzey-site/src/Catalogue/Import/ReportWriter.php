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
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns, ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($handle, array_map(static fn (string $column): string => $row[$column] ?? '', $columns), ',', '"', '');
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Reads the agreed export: its header and its data rows, nothing interpreted.
 *
 * The agreed format is delimited text (tab-delimited, as Amazon's seller reports download; CSV where needed). A
 * workbook reader is deliberately absent until the agreed export needs one (research R-3). A byte-order mark is
 * stripped; a declared encoding other than UTF-8 is converted to UTF-8 as documented, never detected. Blank lines
 * are skipped. Row numbers count the header as row 1, so a report line points at the line a person sees.
 *
 * Register ids: MIG-01, MIG-03. See specs/006-catalogue-import-spec, FR-001.
 */
final class ExportReader
{
    private const DELIMITERS = ['tsv' => "\t", 'csv' => ','];

    /**
     * @param array{format:string,encoding:string} $source The mapping's `source` section, already validated.
     * @param int|null                              $limit  Read at most this many data rows (the preview).
     *
     * @return array{header:list<string>,rows:list<array{row:int,cells:list<string>}>}
     *
     * @throws RuntimeException When the file cannot be opened.
     */
    public static function read(string $path, array $source, ?int $limit = null): array
    {
        $handle = is_readable($path) ? fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                /* translators: %s: the file path. */
                __('The file cannot be read: %s', 'mizzey-site'),
                $path,
            ));
        }

        $delimiter = self::DELIMITERS[$source['format']];
        $encoding  = strtoupper($source['encoding']);
        $header    = null;
        $rows      = [];
        $line      = 0;

        try {
            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                ++$line;
                if ($cells === [null]) {
                    continue;
                }
                $cells = array_map(static fn (?string $cell): string => self::decode((string) $cell, $encoding), $cells);
                if ($header === null) {
                    $cells[0] = self::withoutByteOrderMark($cells[0]);
                    $header   = $cells;
                    continue;
                }
                $rows[] = ['row' => $line, 'cells' => $cells];
                if ($limit !== null && count($rows) >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return ['header' => $header ?? [], 'rows' => $rows];
    }

    private static function decode(string $cell, string $encoding): string
    {
        if ($encoding === 'UTF-8') {
            return $cell;
        }
        $converted = mb_convert_encoding($cell, 'UTF-8', $encoding);

        return $converted === false ? $cell : $converted;
    }

    private static function withoutByteOrderMark(string $cell): string
    {
        return str_starts_with($cell, "\xEF\xBB\xBF") ? substr($cell, 3) : $cell;
    }
}

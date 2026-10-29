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
 * workbook reader is deliberately absent until the agreed export needs one (research R-3). A declared encoding other
 * than UTF-8 is converted to UTF-8 before the file is parsed, so its delimiters and line ends are read correctly
 * (UTF-16 included); it is never detected, and text that does not convert stops the read. A byte-order mark is removed
 * before parsing, so a quoted first header still reads. Quoted cells may span lines. Blank lines are skipped. Row
 * numbers count the header as row 1, so a report line points at the line a person sees.
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
     * @throws RuntimeException When the file cannot be opened or decoded.
     */
    public static function read(string $path, array $source, ?int $limit = null): array
    {
        $handle    = self::open($path, strtoupper($source['encoding']));
        $delimiter = self::DELIMITERS[$source['format']];
        $header    = null;
        $rows      = [];
        $line      = 1;

        try {
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            while (true) {
                $start = $line;
                $cells = fgetcsv($handle, 0, $delimiter, '"', '');
                if ($cells === false) {
                    break;
                }
                $line = $start + 1 + self::lineBreaksIn($cells);
                if ($cells === [null]) {
                    continue;
                }
                $cells = array_map(static fn (?string $cell): string => (string) $cell, $cells);
                if ($header === null) {
                    $header = $cells;
                    continue;
                }
                $rows[] = ['row' => $start, 'cells' => $cells];
                if ($limit !== null && count($rows) >= $limit) {
                    break;
                }
            }
        } finally {
            fclose($handle);
        }

        return ['header' => $header ?? [], 'rows' => $rows];
    }

    /**
     * @return resource A stream of UTF-8 text.
     *
     * @throws RuntimeException
     */
    private static function open(string $path, string $encoding)
    {
        $handle = is_readable($path) ? fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                /* translators: %s: the file path. */
                __('The file cannot be read: %s', 'mizzey-site'),
                $path,
            ));
        }
        if ($encoding === 'UTF-8') {
            return $handle;
        }

        $raw = stream_get_contents($handle);
        fclose($handle);
        // Checked before converting: conversion substitutes invalid bytes silently, which would read as other text.
        $text = $raw === false || ! mb_check_encoding($raw, $encoding) ? false : mb_convert_encoding($raw, 'UTF-8', $encoding);
        if (! is_string($text) || ! mb_check_encoding($text, 'UTF-8') || ($raw !== '' && $text === '')) {
            throw new RuntimeException(sprintf(
                /* translators: %s: the declared encoding. */
                __('The file cannot be decoded from the declared encoding %s.', 'mizzey-site'),
                $encoding,
            ));
        }
        $converted = fopen('php://temp', 'w+b');
        if ($converted === false || fwrite($converted, $text) !== strlen($text) || ! rewind($converted)) {
            throw new RuntimeException(__('The decoded file cannot be buffered.', 'mizzey-site'));
        }

        return $converted;
    }

    /**
     * @param list<string|null> $cells
     */
    private static function lineBreaksIn(array $cells): int
    {
        $breaks = 0;
        foreach ($cells as $cell) {
            $breaks += substr_count((string) $cell, "\n");
        }

        return $breaks;
    }
}

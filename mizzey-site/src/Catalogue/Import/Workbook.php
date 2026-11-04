<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use RuntimeException;
use ZipArchive;

/**
 * Reads a workbook (`.xlsx`), as Google Sheets and Excel download one, as one sheet: every tab, in the workbook's
 * order, one after another.
 *
 * The client fills the product sheet by hand and may keep a tab per category. The first tab that has a header row sets
 * the header; every other tab with rows must carry the same column names (in any order), or the read stops and names
 * the tab and the difference, so no column is misread. An empty tab, or one with only the header, is skipped. Each
 * row keeps its tab's name and its own line number in that tab, so a report line points at what a person sees.
 *
 * Cells are read as their text: shared and inline strings, formula results, booleans as TRUE or FALSE, and numbers as
 * the shortest text of their value (`349`, `12.3`), so a price typed as 349.00 reads as `349`. A blank line is skipped.
 * Only PHP's own zip and XML readers are used: no library, nothing downloaded.
 *
 * Register ids: MIG-01, MIG-03. See specs/053-sheet-arabic-tabs.
 */
final class Workbook
{
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG  = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return array{header:list<string>,rows:list<array{row:int,tab:string,cells:list<string>}>}
     *
     * @throws RuntimeException When the workbook cannot be read, or a tab's columns differ from the first tab's.
     */
    public static function read(string $path, ?int $limit = null): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('This server cannot read a workbook (PHP has no zip support); save the sheet as CSV.', 'mizzey-site'));
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(sprintf(
                /* translators: %s: the file path. */
                __('The workbook cannot be opened: %s', 'mizzey-site'),
                $path,
            ));
        }
        try {
            $strings = self::sharedStrings($zip);
            $header  = null;
            $rows    = [];
            foreach (self::tabs($zip) as $name => $file) {
                $lines = self::lines($zip, $file, $strings);
                if ($lines === []) {
                    continue;
                }
                $first  = array_key_first($lines);
                $names  = array_map(static fn (string $cell): string => trim($cell), $lines[$first]);
                while ($names !== [] && end($names) === '') {
                    array_pop($names);
                }
                unset($lines[$first]);
                if ($names === []) {
                    continue;
                }
                if ($header === null) {
                    $header = $names;
                } elseif ($names !== $header) {
                    $missing = array_diff($header, $names);
                    $extra   = array_diff($names, $header);
                    if ($missing !== [] || $extra !== [] || count($names) !== count($header)) {
                        throw new RuntimeException(sprintf(
                            /* translators: 1: a tab's name, 2: the columns it lacks, 3: the columns it adds. */
                            __('The tab "%1$s" does not have the same columns as the first tab. Missing: %2$s. Not in the first tab: %3$s. Give every tab the same header row.', 'mizzey-site'),
                            $name,
                            $missing === [] ? '-' : implode(', ', $missing),
                            $extra === [] ? '-' : implode(', ', $extra),
                        ));
                    }
                }
                $order = array_flip($names);
                foreach ($lines as $number => $cells) {
                    $byName = [];
                    foreach ($header as $column) {
                        $byName[] = (string) ($cells[$order[$column]] ?? '');
                    }
                    if (implode('', array_map('trim', $byName)) === '') {
                        continue;
                    }
                    $rows[] = ['row' => $number, 'tab' => (string) $name, 'cells' => $byName];
                    if ($limit !== null && count($rows) >= $limit) {
                        break 2;
                    }
                }
            }
        } finally {
            $zip->close();
        }

        return ['header' => $header ?? [], 'rows' => $rows];
    }

    /** @return list<string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $xml = self::xml($zip, 'xl/sharedStrings.xml', false);
        if ($xml === null) {
            return [];
        }
        $strings = [];
        foreach ($xml->children(self::MAIN)->si as $si) {
            $strings[] = self::text($si);
        }

        return $strings;
    }

    /** The text of a string item: its own `t`, or its runs' `t` joined. */
    private static function text(\SimpleXMLElement $item): string
    {
        $node = $item->children(self::MAIN);
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->children(self::MAIN)->t;
        }

        return $text;
    }

    /**
     * The tabs in the workbook's order, each name to its part.
     *
     * @return array<string,string>
     */
    private static function tabs(ZipArchive $zip): array
    {
        $book = self::xml($zip, 'xl/workbook.xml', true);
        $rels = self::xml($zip, 'xl/_rels/workbook.xml.rels', true);
        $targets = [];
        foreach ($rels->children(self::PKG)->Relationship as $rel) {
            $target = (string) $rel->attributes()['Target'];
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            $targets[(string) $rel->attributes()['Id']] = $target;
        }
        $tabs = [];
        foreach ($book->children(self::MAIN)->sheets->children(self::MAIN)->sheet as $sheet) {
            $id = (string) $sheet->attributes(self::RELS)['id'];
            if (isset($targets[$id])) {
                $tabs[(string) $sheet->attributes()['name']] = $targets[$id];
            }
        }

        return $tabs;
    }

    /**
     * A tab's rows, each line number to its cells by column position.
     *
     * @param list<string> $strings
     * @return array<int,list<string>>
     */
    private static function lines(ZipArchive $zip, string $part, array $strings): array
    {
        $sheet = self::xml($zip, $part, true);
        $lines = [];
        $data  = $sheet->children(self::MAIN)->sheetData;
        if (! $data instanceof \SimpleXMLElement) {
            return [];
        }
        $next = 1;
        foreach ($data->children(self::MAIN)->row as $row) {
            $number = isset($row->attributes()['r']) ? (int) $row->attributes()['r'] : $next;
            $next   = $number + 1;
            $cells  = [];
            $column = 0;
            foreach ($row->children(self::MAIN)->c as $cell) {
                $column = isset($cell->attributes()['r']) ? self::column((string) $cell->attributes()['r']) : $column + 1;
                $cells[$column - 1] = self::value($cell, $strings);
            }
            if ($cells === []) {
                continue;
            }
            $filled = [];
            for ($i = 0, $last = max(array_keys($cells)); $i <= $last; ++$i) {
                $filled[] = $cells[$i] ?? '';
            }
            $lines[$number] = $filled;
        }

        return $lines;
    }

    /** `C7` is column 3. */
    private static function column(string $reference): int
    {
        $letters = (string) preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $number  = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + (ord($letter) - 64);
        }

        return max(1, $number);
    }

    /** @param list<string> $strings */
    private static function value(\SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) $cell->attributes()['t']; // Read as no-namespace attributes: the elements came through children($ns).
        $node = $cell->children(self::MAIN);
        if ($type === 'inlineStr') {
            return isset($node->is) ? self::text($node->is) : '';
        }
        $raw = isset($node->v) ? (string) $node->v : '';
        if ($type === 's') {
            return $strings[(int) $raw] ?? '';
        }
        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : ($raw === '0' ? 'FALSE' : $raw);
        }
        if (($type === '' || $type === 'n') && is_numeric($raw)) {
            // The shortest text of the number: 349.00 is stored as 349, and 12.300000000000001 reads as 12.3.
            return preg_match('/^-?\d+$/', $raw) === 1 ? $raw : rtrim(rtrim(sprintf('%.10F', (float) $raw), '0'), '.');
        }

        return $raw;
    }

    /** @throws RuntimeException When a required part is missing or is not XML. */
    private static function xml(ZipArchive $zip, string $part, bool $required): ?\SimpleXMLElement
    {
        $content = $zip->getFromName($part);
        if ($content === false) {
            if ($required) {
                throw new RuntimeException(sprintf(
                    /* translators: %s: a part of the workbook. */
                    __('The workbook is incomplete: %s is missing.', 'mizzey-site'),
                    $part,
                ));
            }

            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $xml      = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (! $xml instanceof \SimpleXMLElement) {
            throw new RuntimeException(sprintf(
                /* translators: %s: a part of the workbook. */
                __('The workbook cannot be read: %s is not valid.', 'mizzey-site'),
                $part,
            ));
        }

        return $xml;
    }
}

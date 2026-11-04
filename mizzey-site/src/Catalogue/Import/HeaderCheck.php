<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * Compares the file's header with the mapping, by name, never by position.
 *
 * A mapped column missing from the file, a header repeated in the file, or no header at all stops the run before
 * any row is interpreted (F1, F2, F3): a report built on a misapplied mapping would look right and be wrong. A
 * column the mapping does not list is reported (W2) and prevents a claim that the mapping is complete. A column the
 * mapping records as not imported may be absent without failing. A column with one of the mapping's prefixes that the
 * mapping could not take (`Mapping::forHeader()`), as its name is not usable or names an option another column fills,
 * stops the run too (F4): its values would otherwise be lost.
 *
 * Register ids: MIG-04, MIG-06, MIG-10. See specs/006-catalogue-import-spec, FR-008, and specs/051-product-sheet-import.
 */
final class HeaderCheck
{
    /**
     * @param list<string> $header
     *
     * @return array{failures:list<array{code:string,detail:string}>,warnings:list<array{code:string,detail:string}>,complete:bool,absent_ignored:list<string>}
     */
    public static function check(array $header, Mapping $mapping): array
    {
        $failures = [];
        $warnings = [];
        if ($header === [] || $header === ['']) {
            return [
                'failures'       => [['code' => 'F3', 'detail' => __('The file has no header row.', 'mizzey-site')]],
                'warnings'       => [],
                'complete'       => false,
                'absent_ignored' => [],
            ];
        }

        $counts = array_count_values($header);
        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $failures[] = ['code' => 'F2', 'detail' => sprintf(
                    /* translators: 1: a header, 2: how many times it appears. */
                    __('The header "%1$s" appears %2$d times.', 'mizzey-site'),
                    $name,
                    $count,
                )];
            }
        }

        $absentIgnored = [];
        foreach ($mapping->columns() as $name => $entry) {
            if (isset($counts[$name])) {
                continue;
            }
            if (isset($entry['to']) && ! $mapping->isOptional((string) $name)) {
                $failures[] = ['code' => 'F1', 'detail' => sprintf(
                    /* translators: 1: a header, 2: the store field it fills. */
                    __('The mapped column "%1$s" (fills %2$s) is missing from the file.', 'mizzey-site'),
                    $name,
                    $entry['to'],
                )];
            } else {
                $absentIgnored[] = (string) $name;
            }
        }

        $extra = array_values(array_diff(array_map('strval', array_keys($counts)), array_map('strval', array_keys($mapping->columns()))));
        foreach ($extra as $i => $name) {
            if (! $mapping->isPrefixed($name)) {
                continue;
            }
            // An option column the mapping could not take: its name is not usable, or another column fills it.
            $failures[] = ['code' => 'F4', 'detail' => sprintf(
                /* translators: %s: a header. */
                __('The option column "%s" cannot be read: after the prefix, give the option a name of its own in English letters, digits, "_" or "-" (for example option_size), used by one column only; an Arabic column (option_size_ar) needs the column of its option beside it.', 'mizzey-site'),
                $name,
            )];
            unset($extra[$i]);
        }
        $extra = array_values($extra);
        foreach ($extra as $name) {
            $warnings[] = ['code' => 'W2', 'detail' => sprintf(
                /* translators: %s: a header. */
                __('The column "%s" is in the file but not in the mapping: map it or record why it is not imported.', 'mizzey-site'),
                $name,
            )];
        }

        return [
            'failures'       => $failures,
            'warnings'       => $warnings,
            'complete'       => $failures === [] && $extra === [],
            'absent_ignored' => $absentIgnored,
        ];
    }
}

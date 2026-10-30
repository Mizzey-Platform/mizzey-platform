<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * The import specification as data: the one mapping for the initial migration, versioned.
 *
 * It is a file kept with the plugin (`mizzey-site/import/catalogue-mapping.json`), not a saved profile (MIG-05 is
 * deferred) and not a screen. Loading validates its shape and refuses an unusable mapping with every problem named,
 * so a wrong mapping can never produce a report that looks right. The shape is documented in the specification's
 * `data-model.md`.
 *
 * Register ids: MIG-04. See specs/006-catalogue-import-spec, FR-002 and FR-003.
 */
final class Mapping
{
    /**
     * Store fields a column can fill. `attribute_<slug>` fills a variation attribute, as WooCommerce names them.
     * `source_asin` keeps Amazon's identifier as a hidden source reference, never shown or searched (owner,
     * 30 October 2026).
     */
    public const FIELDS = ['sku', 'name', 'description', 'regular_price', 'parent_sku', 'row_type', 'name_ar', 'description_ar', 'source_asin'];

    /** Row types the rules know. */
    public const ROW_TYPES = ['simple', 'variable', 'variation'];

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(public readonly string $path, private readonly array $data)
    {
    }

    /**
     * @throws InvalidArgumentException With every problem found, one per line.
     */
    public static function load(string $path): self
    {
        $json = is_readable($path) ? file_get_contents($path) : false;
        $data = $json === false ? null : json_decode($json, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf(
                /* translators: %s: the mapping file path. */
                __('The mapping is not readable JSON: %s', 'mizzey-site'),
                $path,
            ));
        }
        $problems = self::problems($data);
        if ($problems !== []) {
            throw new InvalidArgumentException(implode("\n", $problems));
        }

        return new self($path, $data);
    }

    public function version(): string
    {
        return (string) $this->data['version'];
    }

    public function rulesVersion(): int
    {
        return (int) $this->data['rules_version'];
    }

    /**
     * @return array{format:string,encoding:string}
     */
    public function source(): array
    {
        return ['format' => (string) $this->data['source']['format'], 'encoding' => (string) ($this->data['source']['encoding'] ?? 'UTF-8')];
    }

    /**
     * @return array<string, array{to?:string,ignore?:string}>
     */
    public function columns(): array
    {
        return $this->data['columns'];
    }

    /** The header that fills a field, or null when the mapping does not fill it. */
    public function columnFor(string $field): ?string
    {
        foreach ($this->columns() as $header => $entry) {
            if (($entry['to'] ?? null) === $field) {
                return (string) $header;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> Attribute slug to header.
     */
    public function attributeColumns(): array
    {
        $attributes = [];
        foreach ($this->columns() as $header => $entry) {
            $to = $entry['to'] ?? '';
            if (str_starts_with($to, 'attribute_')) {
                $attributes[substr($to, strlen('attribute_'))] = (string) $header;
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, string> A source value of the row-type column to a row type.
     */
    public function rowTypeValues(): array
    {
        return $this->data['row_type']['values'];
    }

    /**
     * @return list<string> The Arabic fields the mapping fills.
     */
    public function arabicFields(): array
    {
        return array_values(array_filter(['name_ar', 'description_ar'], fn (string $field): bool => $this->columnFor($field) !== null));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function problems(array $data): array
    {
        $problems = [];
        if (! isset($data['version']) || ! is_string($data['version']) || trim($data['version']) === '') {
            $problems[] = __('version: a non-empty string is required', 'mizzey-site');
        }
        if (($data['rules_version'] ?? null) !== LoadRules::VERSION) {
            $problems[] = sprintf(
                /* translators: %d: the rules version this code applies. */
                __('rules_version: this tool applies initial-load rules version %d', 'mizzey-site'),
                LoadRules::VERSION,
            );
        }
        $format = $data['source']['format'] ?? null;
        if (! in_array($format, ['tsv', 'csv'], true)) {
            $problems[] = __('source.format: tsv or csv (another format needs agreement first)', 'mizzey-site');
        }
        $encoding = $data['source']['encoding'] ?? 'UTF-8';
        if (! is_string($encoding) || ! in_array(strtoupper($encoding), array_map('strtoupper', mb_list_encodings()), true)) {
            $problems[] = __('source.encoding: a known encoding name is required', 'mizzey-site');
        }

        $columns = $data['columns'] ?? null;
        if (! is_array($columns) || $columns === [] || array_is_list($columns)) {
            $problems[] = __('columns: an object of source headers is required', 'mizzey-site');

            return $problems;
        }
        $targets = [];
        foreach ($columns as $header => $entry) {
            $to     = is_array($entry) ? ($entry['to'] ?? null) : null;
            $ignore = is_array($entry) ? ($entry['ignore'] ?? null) : null;
            if (($to === null) === ($ignore === null)) {
                $problems[] = sprintf(
                    /* translators: %s: a source header. */
                    __('columns.%s: exactly one of "to" or "ignore" (with its reason) is required', 'mizzey-site'),
                    $header,
                );
                continue;
            }
            if ($ignore !== null && (! is_string($ignore) || trim($ignore) === '')) {
                $problems[] = sprintf(
                    /* translators: %s: a source header. */
                    __('columns.%s: "ignore" needs the reason the column is not imported', 'mizzey-site'),
                    $header,
                );
            }
            if ($to !== null) {
                if (! is_string($to) || (! in_array($to, self::FIELDS, true) && preg_match('/^attribute_[a-z0-9_-]+$/', $to) !== 1)) {
                    $problems[] = sprintf(
                        /* translators: 1: a source header, 2: the field it names. */
                        __('columns.%1$s: "%2$s" is not a store field this tool knows', 'mizzey-site'),
                        $header,
                        is_string($to) ? $to : '?',
                    );
                } elseif (in_array($to, $targets, true)) {
                    $problems[] = sprintf(
                        /* translators: %s: a store field. */
                        __('columns: two columns fill "%s"', 'mizzey-site'),
                        $to,
                    );
                }
                $targets[] = $to;
            }
        }
        foreach (['sku', 'name', 'regular_price', 'row_type'] as $required) {
            if (! in_array($required, $targets, true)) {
                $problems[] = sprintf(
                    /* translators: %s: a store field. */
                    __('columns: no column fills "%s"', 'mizzey-site'),
                    $required,
                );
            }
        }

        $values = $data['row_type']['values'] ?? null;
        if (! is_array($values) || $values === []) {
            $problems[] = __('row_type.values: the source values of the row-type column are required', 'mizzey-site');
        } else {
            foreach ($values as $value => $type) {
                if (! in_array($type, self::ROW_TYPES, true)) {
                    $problems[] = sprintf(
                        /* translators: 1: a source value, 2: the row type it names. */
                        __('row_type.values: "%1$s" names "%2$s", which is not simple, variable or variation', 'mizzey-site'),
                        $value,
                        is_string($type) ? $type : '?',
                    );
                }
            }
            if (in_array('variation', $values, true) && ! in_array('parent_sku', $targets, true)) {
                $problems[] = __('columns: variation rows need a column that fills "parent_sku"', 'mizzey-site');
            }
        }

        return $problems;
    }
}

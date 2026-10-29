<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue\Import;

defined('ABSPATH') || exit;

/**
 * Applies the mapping to one raw row, without coercing anything silently.
 *
 * The source SKU and parent SKU are kept exactly as written; their matching keys come from the store collation (`SkuIndex::key()`).
 * Every other field is trimmed. A price must read as a plain decimal with a dot and at most two places; anything else
 * is an issue, never a guess. An unknown row type, a row wider or narrower than the header, and text that is not
 * valid in the declared encoding are issues too. Whether an issue rejects the row is the rules' decision, not this
 * class's.
 *
 * Register ids: MIG-03, MIG-04. See specs/006-catalogue-import-spec, plan.md (RowInterpreter).
 */
final class RowInterpreter
{
    /**
     * @param array{row:int,cells:list<string>} $raw
     * @param list<string>                      $header
     *
     * @return array{row:int,sku_source:string,sku:string,parent_sku_source:string,parent_sku:string,type:?string,type_source:string,fields:array<string,string>,attributes:array<string,string>,price:?string,issues:list<array{code:string,field:string,problem:string,correction:string,owner?:string}>}
     */
    public static function interpret(array $raw, array $header, Mapping $mapping): array
    {
        $issues = [];
        $cells  = $raw['cells'];
        if (count($cells) !== count($header)) {
            $issues[] = LoadRules::issue('R8', '', sprintf(
                /* translators: 1: cells in the row, 2: columns in the header. */
                __('The row has %1$d cells and the header has %2$d columns.', 'mizzey-site'),
                count($cells),
                count($header),
            ), __('Re-export the row so every column has one cell.', 'mizzey-site'));
        }
        $byHeader = [];
        foreach ($header as $index => $name) {
            $byHeader[$name] = $cells[$index] ?? '';
        }

        $fields = [];
        foreach ($mapping->columns() as $name => $entry) {
            if (! isset($entry['to']) || str_starts_with($entry['to'], 'attribute_')) {
                continue;
            }
            $value = $byHeader[$name] ?? '';
            if (! mb_check_encoding($value, 'UTF-8')) {
                $issues[] = LoadRules::issue('R6', $name, __('The text is not valid in the declared encoding.', 'mizzey-site'), __('Re-export the file in the encoding the mapping declares.', 'mizzey-site'));
                $value = '';
            }
            $fields[$entry['to']] = $value;
        }

        $attributes = [];
        foreach ($mapping->attributeColumns() as $slug => $name) {
            $value = $byHeader[$name] ?? '';
            if (! mb_check_encoding($value, 'UTF-8')) {
                $issues[] = LoadRules::issue('R6', $name, __('The text is not valid in the declared encoding.', 'mizzey-site'), __('Re-export the file in the encoding the mapping declares.', 'mizzey-site'));
                $value = '';
            }
            $value = trim($value);
            if ($value !== '') {
                $attributes[$slug] = $value;
            }
        }

        $typeSource = trim($fields['row_type'] ?? '');
        $type       = $mapping->rowTypeValues()[$typeSource] ?? null;
        if ($type === null) {
            $issues[] = LoadRules::issue('R7', (string) $mapping->columnFor('row_type'), sprintf(
                /* translators: %s: the value found. */
                __('The row type "%s" is not one the mapping knows.', 'mizzey-site'),
                $typeSource,
            ), __('Use one of the row-type values the mapping lists.', 'mizzey-site'));
        }

        $price = trim($fields['regular_price'] ?? '');
        if ($price !== '' && preg_match('/^\d+(\.\d{1,2})?$/', $price) !== 1) {
            $issues[] = LoadRules::issue('R6', (string) $mapping->columnFor('regular_price'), sprintf(
                /* translators: %s: the value found. */
                __('The price "%s" is not a number.', 'mizzey-site'),
                $price,
            ), __('Write the price as digits with a dot for decimals, for example 249.50, with no currency or thousands separator.', 'mizzey-site'));
        }

        $skuSource       = $fields['sku'] ?? '';
        $parentSkuSource = $fields['parent_sku'] ?? '';
        unset($fields['sku'], $fields['parent_sku'], $fields['row_type'], $fields['regular_price']);

        return [
            'row'               => $raw['row'],
            'sku_source'        => $skuSource,
            'sku'               => trim($skuSource),
            'parent_sku_source' => $parentSkuSource,
            'parent_sku'        => trim($parentSkuSource),
            'type'              => $type,
            'type_source'       => $typeSource,
            'fields'            => array_map('trim', $fields),
            'attributes'        => $attributes,
            'price'             => $price === '' ? null : $price,
            'issues'            => $issues,
        ];
    }
}

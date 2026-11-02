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
            // Several columns may name images (a main image and the others): they are kept together, in column order.
            if ($entry['to'] === 'images' && ($fields['images'] ?? '') !== '' && trim($value) !== '') {
                $value = $fields['images'] . '|' . $value;
            } elseif ($entry['to'] === 'images' && trim($value) === '' && isset($fields['images'])) {
                $value = $fields['images'];
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
        if ($price !== '' && ! self::money($price)) {
            $issues[] = LoadRules::issue('R6', (string) $mapping->columnFor('regular_price'), sprintf(
                /* translators: %s: the value found. */
                __('The price "%s" is not a number.', 'mizzey-site'),
                $price,
            ), __('Write the price as digits with a dot for decimals, for example 249.50, with no currency or thousands separator.', 'mizzey-site'));
        }

        // The values the load writes besides the price are checked here too, so the client is asked before the load
        // (MIG-06, MIG-19). Each is a warning: the row still loads, without the value that cannot be used.
        $sale = trim($fields['sale_price'] ?? '');
        if ($sale !== '' && ! self::money($sale)) {
            $issues[] = LoadRules::issue('W5', (string) $mapping->columnFor('sale_price'), sprintf(
                /* translators: %s: the value found. */
                __('The sale price "%s" is not a number; it will not be written.', 'mizzey-site'),
                $sale,
            ), __('Write the sale price as digits with a dot for decimals, for example 199.50.', 'mizzey-site'));
        } elseif ($sale !== '' && self::money($price) && (float) $sale >= (float) $price) {
            $issues[] = LoadRules::issue('W5', (string) $mapping->columnFor('sale_price'), sprintf(
                /* translators: 1: the sale price, 2: the price. */
                __('The sale price %1$s is not below the price %2$s, so it is not shown as a saving.', 'mizzey-site'),
                $sale,
                $price,
            ), __('Give a sale price below the price, or leave it empty.', 'mizzey-site'));
        }
        $cost = trim($fields['cost'] ?? '');
        if ($cost !== '' && ! self::money($cost)) {
            $issues[] = LoadRules::issue('W6', (string) $mapping->columnFor('cost'), sprintf(
                /* translators: %s: the value found. */
                __('The cost "%s" is not a number; it will not be written.', 'mizzey-site'),
                $cost,
            ), __('Write the cost as digits with a dot for decimals, with no currency.', 'mizzey-site'));
        }
        $images = array_values(array_unique(array_filter(array_map('trim', explode('|', (string) ($fields['images'] ?? ''))), 'strlen')));
        foreach ($images as $address) {
            // The load's own check (CatalogueLoad::attach): an address it would refuse is reported here first.
            if (wp_http_validate_url($address) === false) {
                $issues[] = LoadRules::issue('W7', (string) $mapping->columnFor('images'), sprintf(
                    /* translators: %s: the value found. */
                    __('The image address "%s" is not a public web address; the image will not be downloaded.', 'mizzey-site'),
                    $address,
                ), __('Give the full public http or https address of the image.', 'mizzey-site'));
            }
        }
        if (count($images) > CatalogueLoad::IMAGES_MAX) {
            $issues[] = LoadRules::issue('W7', (string) $mapping->columnFor('images'), sprintf(
                /* translators: 1: images given, 2: images kept. */
                __('The row gives %1$d images; only the first %2$d are kept.', 'mizzey-site'),
                count($images),
                CatalogueLoad::IMAGES_MAX,
            ), __('Keep the images that matter most first, or remove the extra ones.', 'mizzey-site'));
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

    /** A plain decimal with a dot and at most two places, as the load writes a price. */
    private static function money(string $value): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $value) === 1;
    }
}

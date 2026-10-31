<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Stock;

defined('ABSPATH') || exit;

/**
 * Resolves a product or variation, in either language, to one stock identity: its translation group's source-language
 * record (ERP-01, D-10: one commercial item or variant is one ERP stock identity regardless of language).
 *
 * The group is read from its own rows (`wpml_element_trid`, then `wpml_get_element_translations`), not from
 * `wpml_original_element_id`, whose separately cached answer was observed naming another product inside one long
 * process (CostTranslationSync, and its verification record). A record the group does not list, while WPML is active,
 * has no identity rather than a guess: unknown stock is not sellable.
 *
 * See specs/012-erp-adapter-seam, FR-002.
 */
final class StockIdentity
{
    public static function resolve(int $id): ?StockItem
    {
        $product = $id > 0 ? wc_get_product($id) : null;
        if (! $product instanceof \WC_Product) {
            return null;
        }
        $kind = $product->is_type('variation') ? StockItem::VARIATION : StockItem::PRODUCT;
        if (! defined('ICL_SITEPRESS_VERSION')) {
            return new StockItem($id, $kind, (string) $product->get_sku('edit'));
        }
        $type   = $kind === StockItem::VARIATION ? 'post_product_variation' : 'post_product';
        $trid   = apply_filters('wpml_element_trid', null, $id, $type);
        $rows   = $trid ? (array) apply_filters('wpml_get_element_translations', null, $trid, $type, false, true) : [];
        $source = 0;
        $listed = false;
        foreach ($rows as $row) {
            $row     = (object) $row;
            $element = (int) ($row->element_id ?? 0);
            $listed  = $listed || $element === $id;
            if ($element > 0 && self::isSource($row)) {
                $source = $element;
            }
        }
        if (! $listed || $source === 0) {
            return null;
        }
        $record = $source === $id ? $product : wc_get_product($source);

        return $record instanceof \WC_Product ? new StockItem($source, $kind, (string) $record->get_sku('edit')) : null;
    }

    private static function isSource(object $row): bool
    {
        return isset($row->original) ? (bool) (int) $row->original : empty($row->source_language_code);
    }
}

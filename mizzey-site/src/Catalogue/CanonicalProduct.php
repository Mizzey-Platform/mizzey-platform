<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * The one record that stands for a product or variation across its languages: its translation group's source-language
 * record (D-10: one commercial item is one identity regardless of language; B17 for the wishlist).
 *
 * The group is read from its own rows (`wpml_element_trid`, then `wpml_get_element_translations`), not from
 * `wpml_original_element_id`, whose separately cached answer was observed naming another product inside one long
 * process (CostTranslationSync, and its verification record). A record the group does not list, while WPML is active,
 * has no canonical record rather than a guess. Without WPML each record is its own.
 *
 * See specs/012-erp-adapter-seam (FR-002) and specs/015-wishlist.
 */
final class CanonicalProduct
{
    public const PRODUCT   = 'product';
    public const VARIATION = 'variation';

    /** The canonical product or variation for any of its language records, or null. */
    public static function of(int $id): ?\WC_Product
    {
        $product = $id > 0 ? wc_get_product($id) : null;
        if (! $product instanceof \WC_Product) {
            return null;
        }
        if (! defined('ICL_SITEPRESS_VERSION')) {
            return $product;
        }
        $type   = $product->is_type('variation') ? 'post_product_variation' : 'post_product';
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

        return $record instanceof \WC_Product ? $record : null;
    }

    public static function kind(\WC_Product $product): string
    {
        return $product->is_type('variation') ? self::VARIATION : self::PRODUCT;
    }

    /** The record of a canonical product shown in the current language: its translation when there is one. */
    public static function inCurrentLanguage(int $canonicalId): ?\WC_Product
    {
        $product = wc_get_product($canonicalId);
        if (! $product instanceof \WC_Product) {
            return null;
        }
        $type  = $product->is_type('variation') ? 'product_variation' : 'product';
        $local = (int) apply_filters('wpml_object_id', $canonicalId, $type, true);
        $shown = $local > 0 && $local !== $canonicalId ? wc_get_product($local) : null;

        return $shown instanceof \WC_Product ? $shown : $product;
    }

    private static function isSource(object $row): bool
    {
        return isset($row->original) ? (bool) (int) $row->original : empty($row->source_language_code);
    }
}

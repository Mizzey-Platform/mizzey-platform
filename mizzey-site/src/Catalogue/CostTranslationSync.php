<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use Throwable;
use WC_Product;
use WC_Product_Variation;

/**
 * Keeps the product cost (ADM-27, RPT-11) equal across a product's language versions.
 *
 * Why this exists. WooCommerce stores cost natively and WPML copies it to translations when a product is saved
 * through wp-admin or the wp-admin importer. It does not when the cost is the only thing that changed and the save
 * comes from the REST API, WP-CLI or any other code: WooCommerce skips wp_update_post() in that case, so save_post
 * never fires and WPML and WCML never run. The Arabic copy then keeps a stale cost, or none, and an order for the
 * Arabic product records the wrong cost with nothing on screen to show it. Evidence and the full workflow matrix
 * are in specs/001-product-cost-capture (scenario t11) and its verification record.
 *
 * What it does. After WooCommerce has saved a product or variation, the original (source language) copies its cost
 * to its translations, through WooCommerce CRUD so the product lookup table stays correct. It compares first and
 * writes only on a difference, so running it again changes nothing and a translation missed by an earlier failure
 * is repaired by the next save.
 *
 * What it deliberately does not do: it stores no cost of its own, adds no field, and syncs nothing but cost.
 */
final class CostTranslationSync
{
    /** The post types this class syncs, mapped to the element types WPML records translations under. */
    private const ELEMENT_TYPES = [
        'product' => 'post_product',
        'product_variation' => 'post_product_variation',
    ];

    /** Guard against re-entry: saving a translation below must not start another sync pass. */
    private static bool $running = false;

    public static function register(): void
    {
        add_action('woocommerce_update_product', [self::class, 'sync'], 20, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'sync'], 20, 1);
    }

    /**
     * Copy the cost of the original product or variation onto its translations.
     *
     * @param int $productId The product WooCommerce has just saved.
     */
    public static function sync(int $productId): void
    {
        if (self::$running || $productId <= 0 || !self::costCaptureIsOn()) {
            return;
        }

        $elementType = self::ELEMENT_TYPES[get_post_type($productId)] ?? null;
        if (null === $elementType || !self::isOriginal($productId, $elementType)) {
            return;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            return;
        }

        self::$running = true;

        try {
            foreach (self::translationIds($productId, $elementType) as $translationId) {
                self::copyTo($product, $translationId);
            }
        } finally {
            self::$running = false;
        }
    }

    /**
     * Cost of Goods Sold is off by default and can be switched off again. While it is off, WooCommerce ignores
     * cost writes, so there is nothing to copy.
     */
    private static function costCaptureIsOn(): bool
    {
        if (!class_exists(FeaturesController::class) || !function_exists('wc_get_container')) {
            return false;
        }

        return wc_get_container()->get(FeaturesController::class)->feature_is_enabled('cost_of_goods_sold');
    }

    /**
     * True when this post is the source-language original. Translations never push cost back: the original is the
     * one copy an editor maintains, and WPML locks the field on the others.
     */
    private static function isOriginal(int $productId, string $elementType): bool
    {
        $original = apply_filters('wpml_original_element_id', null, $productId, $elementType);

        return null !== $original && (int) $original === $productId;
    }

    /**
     * @return int[] The other language versions of this product or variation.
     */
    private static function translationIds(int $productId, string $elementType): array
    {
        $trid = apply_filters('wpml_element_trid', null, $productId, $elementType);
        if (!$trid) {
            return [];
        }

        $ids = [];
        foreach ((array) apply_filters('wpml_get_element_translations', null, $trid, $elementType) as $translation) {
            $id = (int) ($translation->element_id ?? 0);
            if ($id > 0 && $id !== $productId) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Compare two costs as WooCommerce stores them, to the store's price precision. Floats that came from the same
     * decimal column are not safely compared with ==.
     */
    private static function sameAmount(?float $a, ?float $b): bool
    {
        if (null === $a || null === $b) {
            return $a === $b;
        }

        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return wc_format_decimal($a, $decimals) === wc_format_decimal($b, $decimals);
    }

    /**
     * Write the original's cost onto one translation, when it differs. A translation that cannot be saved is
     * logged and the remaining translations still run: a partial failure leaves a recorded gap, not a silent one,
     * and the next save of the original repairs it.
     */
    private static function copyTo(WC_Product $original, int $translationId): void
    {
        try {
            $translation = wc_get_product($translationId);
            if (!$translation instanceof WC_Product) {
                return;
            }

            $cost = $original->get_cogs_value();
            $additive = $original instanceof WC_Product_Variation && $translation instanceof WC_Product_Variation
                ? $original->get_cogs_value_is_additive()
                : null;

            $additiveMatches = null === $additive || $additive === $translation->get_cogs_value_is_additive();
            if (self::sameAmount($cost, $translation->get_cogs_value()) && $additiveMatches) {
                return;
            }

            $translation->set_cogs_value($cost);
            if (null !== $additive) {
                $translation->set_cogs_value_is_additive($additive);
            }
            $translation->save();
        } catch (Throwable $e) {
            wc_get_logger()->error(
                sprintf(
                    'Could not copy the cost of product %d to its translation %d: %s',
                    $original->get_id(),
                    $translationId,
                    $e->getMessage()
                ),
                ['source' => 'mizzey-cost-sync']
            );
        }
    }
}

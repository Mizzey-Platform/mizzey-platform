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
 * What it deliberately does not do: it stores no cost of its own, adds no field, and syncs nothing but cost. It
 * also does not act while a product is being created (see noteCreation()). The cost a new translation starts with
 * is carried by WPML and WooCommerce Multilingual, on the setting this plugin declares in wpml-config.xml.
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

    /** True while WooCommerce is saving a product for the first time. See noteCreation(). */
    private static bool $creating = false;

    public static function register(): void
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'noteCreation'], 1, 1);
        add_action('woocommerce_update_product', [self::class, 'sync'], 20, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'sync'], 20, 1);
        add_filter('woocommerce_save_product_cogs_value', [self::class, 'keepOriginalCost'], 20, 2);
        add_filter('woocommerce_save_product_cogs_is_additive_flag', [self::class, 'keepOriginalAdditiveFlag'], 20, 2);
    }

    /**
     * Record whether this save is creating the product, because nothing here may act on the identity of a post
     * that is being created.
     *
     * WPML registers a new post as it is inserted, using whatever language is current, and that first answer can
     * be wrong: a product created while the current language is Arabic is registered as an Arabic translation of
     * an unrelated product, and only becomes itself once its language is set. A rule that writes a cost on the
     * strength of that answer copies a stranger's cost into a new product. Observed in this project, which is why
     * both the copy outwards and the ownership rule stand down until a product exists.
     *
     * @param WC_Product $product The product about to be saved, with no id yet when it is being created.
     */
    public static function noteCreation($product): void
    {
        self::$creating = $product instanceof WC_Product && 0 === $product->get_id();
    }

    /**
     * The original owns the cost, in every channel.
     *
     * wp-admin already works this way: a cost typed onto a translated product is replaced by the original's cost
     * when WooCommerce Multilingual runs on save_post. Code does not go through save_post (see sync() above), so a
     * cost written straight onto a translation through REST, WP-CLI or a front-end request used to stick, leaving
     * the two language versions disagreeing and an Arabic order recording a cost the original never had.
     *
     * This closes that at WooCommerce's own write point. Whatever value reaches the data store for a translation,
     * the original's value is what gets stored, so the two cannot diverge. It is not bidirectional: a translation
     * never becomes the source. Nothing is saved here, so nothing recurses, and no other field is touched.
     *
     * @param float|null|false $value   The cost WooCommerce is about to store, or false if something suppressed it.
     * @param WC_Product       $product The product being saved.
     * @return float|null|false
     */
    public static function keepOriginalCost($value, $product)
    {
        // false means another extension has taken over storing the cost. That is its decision to make, not ours.
        if (false === $value) {
            return $value;
        }

        $original = self::originalOf($product);
        if (!$original instanceof WC_Product) {
            return $value;
        }

        $owned = $original->get_cogs_value();
        $given = is_numeric($value) ? (float) $value : null;
        if (!self::sameAmount($given, $owned)) {
            self::log(sprintf(
                'Cost %s was written to product %d, which is a translation of %d. The original owns the cost, so %s was stored instead.',
                null === $given ? 'none' : (string) $given,
                $product->get_id(),
                $original->get_id(),
                null === $owned ? 'none' : (string) $owned
            ));
        }

        return $owned;
    }

    /**
     * The same rule for a variation's additive flag, which is part of how its cost is defined.
     *
     * @param bool|null        $flag    The flag WooCommerce is about to store.
     * @param WC_Product       $product The variation being saved.
     * @return bool|null
     */
    public static function keepOriginalAdditiveFlag($flag, $product)
    {
        $original = self::originalOf($product);

        return $original instanceof WC_Product_Variation && $product instanceof WC_Product_Variation
            ? $original->get_cogs_value_is_additive()
            : $flag;
    }

    /**
     * The source-language original of a product that is a translation, or null when this product is the original
     * itself, is not translated, cannot be resolved, or when there is nothing to own because cost capture is off.
     */
    private static function originalOf($product): ?WC_Product
    {
        // Inside sync() the value being written is already the original's, so there is nothing to correct.
        if (self::$running || self::$creating || !$product instanceof WC_Product || !self::costCaptureIsOn()) {
            return null;
        }

        $productId = $product->get_id();
        $elementType = $productId > 0 ? (self::ELEMENT_TYPES[get_post_type($productId)] ?? null) : null;
        if (null === $elementType) {
            return null;
        }

        $group = self::translationGroup($productId, $elementType);

        // Only correct a post WPML actually lists in its own translation group, and lists as a translation.
        // A post being created is not registered yet, and asking about it can return another group's rows
        // altogether, so anything less certain than "WPML says this is a translation of that" is left alone.
        if (false !== $group['self'] || !$group['original'] || $group['original'] === $productId) {
            return null;
        }

        $original = wc_get_product($group['original']);

        return $original instanceof WC_Product ? $original : null;
    }

    /**
     * Copy the cost of the original product or variation onto its translations.
     *
     * @param int $productId The product WooCommerce has just saved.
     */
    public static function sync(int $productId): void
    {
        if (self::$running || self::$creating || $productId <= 0 || !self::costCaptureIsOn()) {
            return;
        }

        $elementType = self::ELEMENT_TYPES[get_post_type($productId)] ?? null;
        if (null === $elementType) {
            return;
        }

        $group = self::translationGroup($productId, $elementType);
        if (true !== $group['self'] || $productId !== $group['original'] || !$group['others']) {
            return;
        }

        $product = wc_get_product($productId);
        if (!$product instanceof WC_Product) {
            return;
        }

        self::$running = true;

        try {
            foreach ($group['others'] as $translationId) {
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
     * Which element of this translation group WPML records as the source-language original, and the other language
     * versions. Only the original copies its cost outwards: it is the copy an editor maintains, and WPML locks the
     * field on the others.
     *
     * Both answers come from one call to wpml_get_element_translations, deliberately. WPML also offers
     * wpml_original_element_id, but that answer is cached separately (SitePress::get_original_element_translation,
     * cache group original_element), and inside one long-running process that had just created a translation it was
     * observed naming another product entirely, while the rows read here were correct at that same moment (see the
     * verification record). Reading both facts from the same rows means they cannot disagree.
     *
     * `self` says what the group has to say about the saved post itself: true when it is the original, false when
     * it is one of the translations, and null when the group does not mention it at all. That last case is not
     * theoretical. A post being saved for the first time is not registered yet, and WPML can answer with a
     * neighbouring group's rows, so a caller that intends to write must insist on a definite answer.
     *
     * @return array{original:int,others:int[],self:bool|null}
     */
    private static function translationGroup(int $productId, string $elementType): array
    {
        $trid = apply_filters('wpml_element_trid', null, $productId, $elementType);
        if (!$trid) {
            return ['original' => 0, 'others' => [], 'self' => null];
        }

        $original = 0;
        $others = [];
        $self = null;
        foreach ((array) apply_filters('wpml_get_element_translations', null, $trid, $elementType) as $row) {
            $row = (object) $row; // WPML returns objects. A filter that returns arrays must not break a product save.
            $id = (int) ($row->element_id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $isOriginal = self::isSourceLanguage($row);
            if ($id === $productId) {
                $self = $isOriginal;
            }
            if ($isOriginal) {
                $original = $id;
            } elseif ($id !== $productId) {
                $others[] = $id;
            }
        }

        return ['original' => $original, 'others' => $others, 'self' => $self];
    }

    /** WPML marks the source-language element of a group: it is flagged, and it has no source language of its own. */
    private static function isSourceLanguage(object $row): bool
    {
        if (isset($row->original)) {
            return (bool) (int) $row->original;
        }

        return empty($row->source_language_code);
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
            self::log(sprintf(
                'Could not copy the cost of product %d to its translation %d: %s',
                $original->get_id(),
                $translationId,
                $e->getMessage()
            ));
        }
    }

    /** One place for anything worth investigating later, under a source an administrator can filter the log by. */
    private static function log(string $message): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, ['source' => 'mizzey-cost-sync']);
        }
    }
}

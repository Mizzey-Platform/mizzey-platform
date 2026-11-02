<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

use Throwable;
use WC_Product_Variation;

/**
 * Carries a product's copied fields to its language versions when code, not a screen, saved it.
 *
 * Why this exists. WPML copies the fields it is told to copy (price, sale price and its dates, SKU, weight and
 * dimensions, stock settings, cost) from save_post. A WooCommerce CRUD save calls wp_update_post() only when a post
 * field changed, and runs it before the product's fields are written, so after a save from the REST API, WP-CLI, the
 * catalogue load or any other code the Arabic record keeps the old price, and an Arabic order charges it. Measured in
 * t20 (REST, CRUD under WP-CLI and in a front-end request: stale Arabic price) and t18 (nine of fourteen fields).
 * D-07 sets the rule this applies: code that writes a product price synchronises the translation explicitly.
 *
 * What it does. After WooCommerce has saved a source-language product or variation outside save_post, WPML's own copy
 * (SitePress::copy_custom_fields, the call WPML makes on save_post) runs onto each translation, then the translation's
 * lookup row and caches are refreshed. Inside save_post (the product screen, quick and bulk edit) it stands aside:
 * WPML and WooCommerce Multilingual run after it there, on the finished save.
 *
 * product() is the full synchronisation WooCommerce Multilingual runs when a product is saved on its screen: the copy
 * above plus attributes, taxonomies, images and the variations, creating the Arabic variation of a new one. The
 * catalogue load calls it in its translation phase (#247); it is not run on every save, because it creates posts.
 *
 * It stores nothing of its own and changes nothing on the source record. Register ids: MIG-02, MIG-13, SSC-21,
 * ADM-25, ADM-26. See specs/036-product-management and specs/046-catalogue-load.
 */
final class TranslationSync
{
    private const ELEMENT_TYPES = [
        'product'           => 'post_product',
        'product_variation' => 'post_product_variation',
    ];

    /** WCML's in-process caches of a product's and a variation's terms (its Taxonomies components' groups). */
    private const TERM_CACHES = [
        'WCML\Synchronization\Component\Taxonomies',
        'WCML\Synchronization\Component\VariationTaxonomies',
    ];

    private static bool $running = false;

    public static function register(): void
    {
        add_action('woocommerce_update_product', [self::class, 'afterSave'], 30, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'afterSave'], 30, 1);
    }

    /** @param mixed $productId */
    public static function afterSave($productId): void
    {
        // Inside save_post WPML and WooCommerce Multilingual run after this save, on its finished fields.
        if (self::$running || doing_action('save_post')) {
            return;
        }
        self::copyFields((int) $productId);
    }

    /**
     * WPML's copy of the copied fields from a source-language product or variation onto its translations.
     *
     * @return list<int> The translations written to.
     */
    public static function copyFields(int $id): array
    {
        global $sitepress;
        $translations = self::translationsOf($id);
        if ($translations === [] || ! is_object($sitepress) || ! method_exists($sitepress, 'copy_custom_fields')) {
            return [];
        }
        $was           = self::$running;
        self::$running = true;
        try {
            foreach (array_keys($translations) as $translationId) {
                try {
                    $sitepress->copy_custom_fields($id, $translationId);
                    self::refresh($translationId);
                } catch (Throwable $e) {
                    self::log(sprintf('Could not copy the fields of product %d to its translation %d: %s', $id, $translationId, $e->getMessage()));
                }
            }
        } finally {
            self::$running = $was;
        }

        return array_keys($translations);
    }

    /**
     * The whole synchronisation of a source-language product, as WooCommerce Multilingual runs it on the product
     * screen: copied fields, attributes, taxonomies, images, and the variations (a new one gets its translation).
     *
     * @return list<int> The translations synchronised.
     */
    public static function product(int $id): array
    {
        if (get_post_type($id) !== 'product') {
            return [];
        }
        $translations = self::copyFields($id);
        $post         = get_post($id);
        if ($translations === [] || ! $post instanceof \WP_Post || ! has_action('wcml_synchronize_product_translations')) {
            return $translations;
        }
        self::$running = true;
        try {
            // WCML keeps each product's terms for the rest of the process once read (on an earlier save_post of this
            // product, before its new category was written): forget them, so the terms synchronised are today's.
            if (class_exists('\WPML_Non_Persistent_Cache')) {
                \WPML_Non_Persistent_Cache::flush_group(self::TERM_CACHES);
            }
            do_action('wcml_synchronize_product_translations', $post, [], []);
            // The variations' own copied fields, and each translated parent's price range from its variations.
            foreach (get_children(['post_parent' => $id, 'post_type' => 'product_variation', 'fields' => 'ids', 'post_status' => ['publish', 'private']]) as $variation) {
                self::copyFields((int) $variation);
            }
            foreach ($translations as $translationId) {
                if (wc_get_product($translationId) instanceof \WC_Product_Variable) {
                    \WC_Product_Variable::sync($translationId);
                }
                self::refresh($translationId);
            }
        } finally {
            self::$running = false;
        }

        return $translations;
    }

    /**
     * The other language versions of a source-language product or variation, by id with their language. Empty when the
     * item is a translation itself, is not translated, or WPML cannot say: only a definite original is copied from.
     *
     * @return array<int,string>
     */
    public static function translationsOf(int $id): array
    {
        $type = $id > 0 ? (self::ELEMENT_TYPES[get_post_type($id)] ?? null) : null;
        if ($type === null) {
            return [];
        }
        $trid = apply_filters('wpml_element_trid', null, $id, $type);
        if (! $trid) {
            return [];
        }
        $self   = null;
        $others = [];
        foreach ((array) apply_filters('wpml_get_element_translations', null, $trid, $type) as $row) {
            $row       = (object) $row;
            $elementId = (int) ($row->element_id ?? 0);
            if ($elementId <= 0) {
                continue;
            }
            $original = isset($row->original) ? (bool) (int) $row->original : empty($row->source_language_code);
            if ($elementId === $id) {
                $self = $original;
            } elseif (! $original) {
                $others[$elementId] = (string) ($row->language_code ?? '');
            }
        }

        return $self === true ? $others : [];
    }

    /** The translation's lookup row, transients and object cache, which a direct field copy does not touch. */
    private static function refresh(int $translationId): void
    {
        wp_cache_delete($translationId, 'post_meta');
        wp_cache_delete('product-' . $translationId, 'products');
        if (class_exists('\WC_Cache_Helper')) {
            \WC_Cache_Helper::invalidate_cache_group('product_' . $translationId);
        }
        if (function_exists('wcml_product_data_store_cpt') && method_exists(wcml_product_data_store_cpt(), 'update_lookup_table_data')) {
            wcml_product_data_store_cpt()->update_lookup_table_data($translationId);
        }
        wc_delete_product_transients($translationId);
        $product = wc_get_product($translationId);
        if ($product instanceof WC_Product_Variation && $product->get_parent_id() > 0) {
            wc_deferred_product_sync($product->get_parent_id()); // The translated parent's price range.
        }
    }

    private static function log(string $message): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, ['source' => 'mizzey-translation-sync']);
        }
    }
}

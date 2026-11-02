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
 * What it does. Before WooCommerce saves a source-language product or variation, it notes the fields the save changes
 * (its changed properties, mapped to their stored fields, and its changed custom fields). After the save, each of them
 * that WPML is set to copy is copied onto each translation with WPML's own per-field copy (the one save_post uses),
 * then the translation's lookup row and caches are refreshed. Only the fields of the change are written: a field the
 * save did not touch (WooCommerce's sales counter, say) stays as each record has it. The cost is left to
 * CostTranslationSync, which owns it. It runs inside save_post too (the product screen, quick and bulk edit), where
 * WPML copies the same values again afterwards; the copy compares first, so nothing changes twice.
 *
 * The translation group is read from WPML's table, not through its in-process caches, so a translation made earlier in
 * the same process (the translation editor, then a save by code) is always seen; WPML's field settings are read from
 * its stored settings for the same reason. (t76 found the Arabic record not following in such a process.)
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

    /**
     * Every stored price field. A change to any price property copies them all: WooCommerce may change another one
     * while it saves (a lower regular price clears a sale price that is no longer below it), after the change was noted.
     */
    private const PRICE_FIELDS = ['_regular_price', '_sale_price', '_price', '_sale_price_dates_from', '_sale_price_dates_to'];

    /** Product properties and the stored fields a change to each one writes. */
    private const PROPERTY_FIELDS = [
        'regular_price'     => self::PRICE_FIELDS,
        'sale_price'        => self::PRICE_FIELDS,
        'date_on_sale_from' => self::PRICE_FIELDS,
        'date_on_sale_to'   => self::PRICE_FIELDS,
        'sku'               => ['_sku'],
        'global_unique_id'  => ['_global_unique_id'],
        'weight'            => ['_weight'],
        'length'            => ['_length'],
        'width'             => ['_width'],
        'height'            => ['_height'],
        'tax_status'        => ['_tax_status'],
        'tax_class'         => ['_tax_class'],
        'manage_stock'      => ['_manage_stock'],
        'backorders'        => ['_backorders'],
        'low_stock_amount'  => ['_low_stock_amount'],
        'stock_status'      => ['_stock_status'],
        'sold_individually' => ['_sold_individually'],
        'virtual'           => ['_virtual'],
        'downloadable'      => ['_downloadable'],
        'download_limit'    => ['_download_limit'],
        'download_expiry'   => ['_download_expiry'],
        'total_sales'       => ['total_sales'],
    ];

    /** Copied fields another class owns: the cost is CostTranslationSync's, written through WooCommerce. */
    private const OWNED_ELSEWHERE = ['_cogs_total_value'];

    private static bool $running = false;

    /** @var array<int,list<string>> The fields each save in progress changes, by product id. */
    private static array $changed = [];

    public static function register(): void
    {
        add_action('woocommerce_before_product_object_save', [self::class, 'noteChanges'], 99, 1);
        add_action('woocommerce_before_product_variation_object_save', [self::class, 'noteChanges'], 99, 1);
        add_action('woocommerce_update_product', [self::class, 'afterSave'], 30, 1);
        add_action('woocommerce_update_product_variation', [self::class, 'afterSave'], 30, 1);
    }

    /** @param mixed $product The product about to be saved. */
    public static function noteChanges($product): void
    {
        if (! $product instanceof \WC_Product || $product->get_id() <= 0) {
            return;
        }
        $fields = [];
        foreach (array_keys($product->get_changes()) as $property) {
            $fields = array_merge($fields, self::PROPERTY_FIELDS[$property] ?? []);
        }
        foreach ($product->get_meta_data() as $meta) {
            if ($meta instanceof \WC_Meta_Data && (empty($meta->id) || $meta->get_changes() !== [])) {
                $fields[] = (string) $meta->key;
            }
        }
        // A field being deleted: WooCommerce keeps it in its pending meta with a null value, which get_meta_data() hides.
        $pending = new \ReflectionProperty(\WC_Data::class, 'meta_data');
        $pending->setAccessible(true);
        foreach ((array) $pending->getValue($product) as $meta) {
            if ($meta instanceof \WC_Meta_Data && $meta->value === null && ! empty($meta->id)) {
                $fields[] = (string) $meta->key;
            }
        }
        $id                 = $product->get_id();
        self::$changed[$id] = array_values(array_unique(array_merge(self::$changed[$id] ?? [], $fields)));
    }

    /** @param mixed $productId */
    public static function afterSave($productId): void
    {
        $id     = (int) $productId;
        $fields = self::$changed[$id] ?? [];
        unset(self::$changed[$id]);
        if (self::$running || $fields === []) {
            return;
        }
        self::copyFields($id, $fields);
    }

    /**
     * WPML's per-field copy, from a source-language product or variation onto its translations, of the given fields
     * WPML is set to copy, or of all of them.
     *
     * @param list<string>|null $fields Null for every copied field.
     * @return list<int> The translations written to.
     */
    public static function copyFields(int $id, ?array $fields = null): array
    {
        global $sitepress;
        $translations = self::translationsOf($id);
        if ($translations === [] || ! is_object($sitepress) || ! method_exists($sitepress, 'sync_custom_field')) {
            return [];
        }
        $copied = array_diff(self::copiedFields(), self::OWNED_ELSEWHERE);
        $fields = $fields === null ? $copied : array_intersect($fields, $copied);
        if ($fields === []) {
            return array_keys($translations);
        }
        $was           = self::$running;
        self::$running = true;
        try {
            foreach (array_keys($translations) as $translationId) {
                try {
                    foreach ($fields as $field) {
                        $sitepress->sync_custom_field($id, $translationId, $field);
                    }
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

    /** The custom fields WPML is set to copy, from its stored settings. @return list<string> */
    private static function copiedFields(): array
    {
        $settings = get_option('icl_sitepress_settings');
        $fields   = is_array($settings) ? (array) ($settings['translation-management']['custom_fields_translation'] ?? []) : [];
        $copy     = defined('WPML_COPY_CUSTOM_FIELD') ? (int) WPML_COPY_CUSTOM_FIELD : 1;

        return array_values(array_map('strval', array_keys(array_filter($fields, static fn ($mode): bool => (int) $mode === $copy))));
    }

    /**
     * The other language versions of a source-language product or variation, by id with their language. Empty when the
     * item is a translation itself, is not translated, or WPML is not running: only a definite original is copied from.
     *
     * @return array<int,string>
     */
    public static function translationsOf(int $id): array
    {
        global $wpdb;
        $type = $id > 0 ? (self::ELEMENT_TYPES[get_post_type($id)] ?? null) : null;
        if ($type === null || ! defined('ICL_SITEPRESS_VERSION')) {
            return [];
        }
        $rows = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT t.element_id, t.language_code, t.source_language_code FROM {$wpdb->prefix}icl_translations s JOIN {$wpdb->prefix}icl_translations t ON t.trid = s.trid AND t.element_type = s.element_type WHERE s.element_id = %d AND s.element_type = %s",
            $id,
            $type,
        ));
        $self   = null;
        $others = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $elementId = (int) $row->element_id;
            $original  = $row->source_language_code === null || $row->source_language_code === '';
            if ($elementId === $id) {
                $self = $original;
            } elseif (! $original && $elementId > 0) {
                $others[$elementId] = (string) $row->language_code;
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

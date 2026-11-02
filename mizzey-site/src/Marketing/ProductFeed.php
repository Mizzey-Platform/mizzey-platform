<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Marketing;

defined('ABSPATH') || exit;

/**
 * The product feed for Google Merchant Center (MKT-06) and the Meta catalogue (MKT-05).
 *
 * One feed per language at `?mizzey_feed=products` on each storefront's home address (`/` and `/ar/`), in Google's
 * product RSS format, which Meta's catalogue also reads. Each published, visible product is one item; a variable
 * product gives one item per variation, grouped by the product's SKU. The item id is the SKU, the same on both
 * language records and the same as the tracking plan's `item_id` (specs/042), so the feeds, the pixels and Analytics
 * name one item one way. Availability is the store's stock status. The feed is built at most once an hour per
 * language and rebuilt after any product is saved: one request builds it, under a lock, while others are served the
 * last good copy. Every value is cleaned of the control characters XML does not allow, so one stray character pasted
 * into a product cannot make the whole feed invalid.
 *
 * Register ids: MKT-05, MKT-06. See specs/043-measurement-tools.
 */
final class ProductFeed
{
    public const QUERY = 'mizzey_feed';
    public const CACHE = 'mizzey_feed_products_';
    public const LAST  = 'mizzey_feed_last_';
    public const MAX   = 500;

    /** Attributes Google reads to tell variants apart, by the store's attribute name. */
    public const VARIANT_FIELDS = ['size' => 'g:size', 'color' => 'g:color', 'colour' => 'g:color', 'shade' => 'g:color', 'material' => 'g:material', 'pattern' => 'g:pattern'];

    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'serve'], 0);
        add_action('woocommerce_update_product', [self::class, 'forget']);
        add_action('woocommerce_new_product', [self::class, 'forget']);
        add_action('woocommerce_delete_product', [self::class, 'forget']);
    }

    public static function url(string $lang): string
    {
        $home = $lang === 'ar' ? (string) apply_filters('wpml_permalink', home_url('/'), 'ar') : (string) apply_filters('wpml_permalink', home_url('/'), 'en');

        return add_query_arg(self::QUERY, 'products', $home);
    }

    /** After a product changes: the next request rebuilds; the last good copy is kept for the others meanwhile. */
    public static function forget(): void
    {
        delete_transient(self::CACHE . 'en');
        delete_transient(self::CACHE . 'ar');
    }

    /** Both feeds and their last good copies. */
    public static function purge(): void
    {
        self::forget();
        delete_option(self::LAST . 'en');
        delete_option(self::LAST . 'ar');
    }

    public static function serve(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only feed.
        if (! isset($_GET[self::QUERY]) || sanitize_key(wp_unslash((string) $_GET[self::QUERY])) !== 'products') {
            return;
        }
        $lang = TagManager::language();
        $xml  = get_transient(self::CACHE . $lang);
        if (! is_string($xml) || $xml === '') {
            $xml = self::fresh($lang);
        }
        nocache_headers();
        if ($xml === '') {
            status_header(503);
            header(sprintf('%s: %s', 'Retry-After', '60'));
            exit;
        }
        header(sprintf('%s: %s', 'Content-Type', 'application/xml;charset=UTF-8'));
        header(sprintf('%s: %s', 'X-Robots-Tag', 'noindex'));
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by XMLWriter, which escapes.
        exit;
    }

    /**
     * The feed when the hourly copy is gone: built by one request at a time. While another request builds it, the last
     * good copy is served; with none yet, this request waits for the build, and gets nothing if it takes too long.
     */
    private static function fresh(string $lang): string
    {
        global $wpdb;
        $last = get_option(self::LAST . $lang, '');
        $last = is_string($last) ? $last : '';
        $lock = substr('mz_' . $wpdb->prefix . 'feed_' . $lang, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, $last !== '' ? 0 : 30)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return $last;
        }
        try {
            self::uncache($lang);
            $xml = get_transient(self::CACHE . $lang); // Built by the request that held the lock before.
            if (is_string($xml) && $xml !== '') {
                return $xml;
            }
            $xml = self::build($lang);
            set_transient(self::CACHE . $lang, $xml, HOUR_IN_SECONDS);
            update_option(self::LAST . $lang, $xml, false);

            return $xml;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /**
     * Forgets what this request remembers of the feed's transient, so the next read goes to the database: a request
     * that waited for the lock may hold a "not there" from before the other request stored it. Transients live in the
     * options table here (no persistent object cache), so the options caches are cleared too.
     */
    private static function uncache(string $lang): void
    {
        foreach (['_transient_', '_transient_timeout_'] as $prefix) {
            wp_cache_delete($prefix . self::CACHE . $lang, 'options');
        }
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete(self::CACHE . $lang, 'transient');
    }

    /** A value as XML 1.0 allows it: valid UTF-8, with no control character but tab and line breaks. */
    public static function text(string $value): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', wp_check_invalid_utf8($value, true));
    }

    private static function put(\XMLWriter $w, string $name, string $value): void
    {
        $w->writeElement($name, self::text($value));
    }

    public static function build(string $lang): string
    {
        $w = new \XMLWriter();
        $w->openMemory();
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('rss');
        $w->writeAttribute('version', '2.0');
        $w->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $w->startElement('channel');
        self::put($w, 'title', wp_strip_all_tags((string) get_bloginfo('name')));
        self::put($w, 'link', self::url($lang));
        self::put($w, 'description', wp_strip_all_tags((string) get_bloginfo('description')));
        $ids = [];
        for ($page = 1; $page <= 200; $page++) {
            $batch = wc_get_products(['status' => 'publish', 'visibility' => 'visible', 'type' => ['simple', 'variable'], 'limit' => self::MAX, 'page' => $page, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
            $ids   = array_merge($ids, (array) $batch);
            if (count((array) $batch) < self::MAX) {
                break;
            }
        }
        foreach ($ids as $id) {
            $product = wc_get_product((int) $id);
            if (! $product instanceof \WC_Product || post_password_required($product->get_id()) || (string) get_post_field('post_password', $product->get_id()) !== '') {
                continue;
            }
            if ($product instanceof \WC_Product_Variable) {
                foreach ($product->get_children() as $child) {
                    $variation = wc_get_product((int) $child);
                    if ($variation instanceof \WC_Product_Variation && $variation->get_status() === 'publish') {
                        self::item($w, $variation, $product);
                    }
                }
                continue;
            }
            self::item($w, $product, null);
        }
        $w->endElement();
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    /** One product or variation, as Google's product RSS names its fields. */
    private static function item(\XMLWriter $w, \WC_Product $product, ?\WC_Product $parent): void
    {
        $price = (string) $product->get_regular_price();
        if ($price === '' || (float) $price <= 0) {
            return;
        }
        $base     = $parent ?? $product;
        $currency = get_woocommerce_currency();
        $w->startElement('item');
        self::put($w, 'g:id', self::id($product));
        self::put($w, 'g:title', wp_strip_all_tags($product->get_name()));
        $description = wp_strip_all_tags((string) ($base->get_short_description() !== '' ? $base->get_short_description() : $base->get_description()));
        self::put($w, 'g:description', mb_substr(trim((string) preg_replace('/\s+/', ' ', $description)), 0, 5000));
        self::put($w, 'g:link', (string) $product->get_permalink());
        $images = array_values(array_filter(array_merge([(int) $product->get_image_id() ?: (int) $base->get_image_id()], array_map('intval', $base->get_gallery_image_ids()))));
        foreach (array_slice($images, 0, 11) as $i => $image) {
            $src = wp_get_attachment_image_url($image, 'full');
            if (is_string($src) && $src !== '') {
                self::put($w, $i === 0 ? 'g:image_link' : 'g:additional_image_link', $src);
            }
        }
        self::put($w, 'g:availability', $product->is_in_stock() ? 'in_stock' : 'out_of_stock');
        $taxed = static fn (string $amount): string => wc_format_decimal(wc_get_price_including_tax($product, ['price' => (float) $amount]), wc_get_price_decimals());
        self::put($w, 'g:price', sprintf('%s %s', $taxed($price), $currency));
        $sale = (string) $product->get_sale_price();
        if ($sale !== '' && $product->is_on_sale() && (float) $sale < (float) $price) {
            self::put($w, 'g:sale_price', sprintf('%s %s', $taxed($sale), $currency));
        }
        self::put($w, 'g:condition', 'new');
        $brands = get_the_terms($base->get_id(), 'product_brand');
        $brand  = is_array($brands) && $brands !== [] ? $brands[0]->name : wp_strip_all_tags((string) get_bloginfo('name'));
        self::put($w, 'g:brand', $brand);
        $gtin = (string) $product->get_global_unique_id();
        if ($gtin !== '') {
            self::put($w, 'g:gtin', $gtin);
        } else {
            self::put($w, 'g:identifier_exists', 'no');
        }
        if ($parent instanceof \WC_Product) {
            self::put($w, 'g:item_group_id', self::id($parent));
            foreach ($product->get_attributes() as $name => $value) {
                $field = self::VARIANT_FIELDS[(string) preg_replace('/^pa_/', '', (string) $name)] ?? null;
                if ($field !== null && (string) $value !== '') {
                    $term = taxonomy_exists((string) $name) ? get_term_by('slug', (string) $value, (string) $name) : null;
                    self::put($w, $field, $term instanceof \WP_Term ? $term->name : (string) $value);
                }
            }
        }
        $cats = get_the_terms($base->get_id(), 'product_cat');
        if (is_array($cats) && $cats !== []) {
            self::put($w, 'g:product_type', implode(' > ', array_reverse(array_merge([$cats[0]->name], array_map(static fn (int $t): string => ($term = get_term($t)) instanceof \WP_Term ? $term->name : '', get_ancestors($cats[0]->term_id, 'product_cat'))))));
        }
        $w->endElement();
    }

    /** The SKU, which both language records share; the record's id when there is none. */
    public static function id(\WC_Product $product): string
    {
        $sku = trim((string) $product->get_sku('edit')); // A variation's own SKU, never its parent's.
        if ($sku !== '') {
            return $sku;
        }
        $canonical = \MizzeySite\Catalogue\CanonicalProduct::of($product->get_id());

        return (string) ($canonical instanceof \WC_Product ? $canonical->get_id() : $product->get_id());
    }
}

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
 * language and rebuilt after any product is saved.
 *
 * Register ids: MKT-05, MKT-06. See specs/043-measurement-tools.
 */
final class ProductFeed
{
    public const QUERY = 'mizzey_feed';
    public const CACHE = 'mizzey_feed_products_';
    public const MAX   = 5000;

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

    public static function forget(): void
    {
        delete_transient(self::CACHE . 'en');
        delete_transient(self::CACHE . 'ar');
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
            $xml = self::build($lang);
            set_transient(self::CACHE . $lang, $xml, HOUR_IN_SECONDS);
        }
        nocache_headers();
        header(sprintf('%s: %s', 'Content-Type', 'application/xml;charset=UTF-8'));
        header(sprintf('%s: %s', 'X-Robots-Tag', 'noindex'));
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by XMLWriter, which escapes.
        exit;
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
        $w->writeElement('title', wp_strip_all_tags((string) get_bloginfo('name')));
        $w->writeElement('link', self::url($lang));
        $w->writeElement('description', wp_strip_all_tags((string) get_bloginfo('description')));
        $ids = wc_get_products(['status' => 'publish', 'visibility' => 'visible', 'type' => ['simple', 'variable'], 'limit' => self::MAX, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
        foreach ((array) $ids as $id) {
            $product = wc_get_product((int) $id);
            if (! $product instanceof \WC_Product) {
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
        $w->writeElement('g:id', self::id($product));
        $w->writeElement('g:title', wp_strip_all_tags($product->get_name()));
        $description = wp_strip_all_tags((string) ($base->get_short_description() !== '' ? $base->get_short_description() : $base->get_description()));
        $w->writeElement('g:description', mb_substr(trim((string) preg_replace('/\s+/', ' ', $description)), 0, 5000));
        $w->writeElement('g:link', (string) $product->get_permalink());
        $images = array_values(array_filter(array_merge([(int) $product->get_image_id() ?: (int) $base->get_image_id()], array_map('intval', $base->get_gallery_image_ids()))));
        foreach (array_slice($images, 0, 11) as $i => $image) {
            $src = wp_get_attachment_image_url($image, 'full');
            if (is_string($src) && $src !== '') {
                $w->writeElement($i === 0 ? 'g:image_link' : 'g:additional_image_link', $src);
            }
        }
        $w->writeElement('g:availability', $product->is_in_stock() ? 'in_stock' : 'out_of_stock');
        $w->writeElement('g:price', sprintf('%s %s', wc_format_decimal($price, wc_get_price_decimals()), $currency));
        $sale = (string) $product->get_sale_price();
        if ($sale !== '' && (float) $sale < (float) $price) {
            $w->writeElement('g:sale_price', sprintf('%s %s', wc_format_decimal($sale, wc_get_price_decimals()), $currency));
        }
        $w->writeElement('g:condition', 'new');
        $brands = get_the_terms($base->get_id(), 'product_brand');
        $brand  = is_array($brands) && $brands !== [] ? $brands[0]->name : wp_strip_all_tags((string) get_bloginfo('name'));
        $w->writeElement('g:brand', $brand);
        $gtin = (string) $product->get_global_unique_id();
        if ($gtin !== '') {
            $w->writeElement('g:gtin', $gtin);
        } else {
            $w->writeElement('g:identifier_exists', 'no');
        }
        if ($parent instanceof \WC_Product) {
            $w->writeElement('g:item_group_id', self::id($parent));
        }
        $cats = get_the_terms($base->get_id(), 'product_cat');
        if (is_array($cats) && $cats !== []) {
            $w->writeElement('g:product_type', implode(' > ', array_reverse(array_merge([$cats[0]->name], array_map(static fn (int $t): string => (string) get_term($t)->name, get_ancestors($cats[0]->term_id, 'product_cat'))))));
        }
        $w->endElement();
    }

    /** The SKU, which both language records share; the record's id when there is none. */
    public static function id(\WC_Product $product): string
    {
        $sku = trim((string) $product->get_sku());

        return $sku !== '' ? $sku : (string) $product->get_id();
    }
}

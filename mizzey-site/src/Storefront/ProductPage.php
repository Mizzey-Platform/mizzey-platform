<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

/**
 * What the product page shows beside WooCommerce's own product, as data for the designed page (PRE-03b builds it).
 *
 * Through the Store API (`extensions.mizzey-page` on each product):
 *
 * - **The saving** (PDP-04): on a product on sale, the amount saved, in the Store API's minor units, and the percent.
 * - **Buy Now** (PDP-09): an address that adds the product (or the variation) and its quantity to the cart and goes to
 *   checkout. WooCommerce's own add-to-cart address, with `mizzey_buy_now=1`, which sends it to checkout.
 * - **The delivery estimate** (PDP-14): the working days the admin sets for the shipping zone of the customer's
 *   address, or the default when the address is not known, stated as an estimate, never a date.
 * - **The returns summary** (PDP-15) and **the authenticity statement** (PDP-16): the admin's words in each language,
 *   with the address of the policy page each links to.
 * - **Share** (PDP-21): the product's address and the sharing addresses for WhatsApp, Facebook, X and email; the
 *   product page carries Open Graph tags, so a shared link shows its title, image and price.
 *
 * The settings are in WooCommerce > Settings > Products > Product page. The gallery, video, name, brand, price,
 * options, stock, quantity, wishlist, descriptions, attributes as the specifications table, reviews, related products,
 * recently viewed and structured data are WooCommerce's own or earlier slices'; see specs/055-product-page.
 *
 * Register ids: PDP-04, PDP-09, PDP-14, PDP-15, PDP-16, PDP-21. See specs/055-product-page.
 */
final class ProductPage
{
    public const SECTION        = 'mizzey_product_page';
    public const RETURNS        = 'mizzey_returns_summary_';
    public const RETURNS_PAGE   = 'mizzey_returns_page';
    public const AUTHENTIC      = 'mizzey_authenticity_';
    public const AUTHENTIC_PAGE = 'mizzey_authenticity_page';
    public const DAYS_MIN       = 'mizzey_delivery_min';
    public const DAYS_MAX       = 'mizzey_delivery_max';
    /** Per shipping zone: `mizzey_delivery_zone_<id>`, "min-max" in working days. */
    public const ZONE           = 'mizzey_delivery_zone_';
    public const BUY_NOW        = 'mizzey_buy_now';

    public static function register(): void
    {
        add_filter('woocommerce_get_sections_products', [self::class, 'sections']);
        add_filter('woocommerce_get_settings_products', [self::class, 'settings'], 20, 2);
        add_action('woocommerce_blocks_loaded', [self::class, 'extend']);
        add_filter('woocommerce_add_to_cart_redirect', [self::class, 'buyNowRedirect'], 20, 1);
        add_action('wp_head', [self::class, 'openGraph'], 5);
    }

    /**
     * @param mixed $sections
     * @return mixed
     */
    public static function sections($sections)
    {
        if (is_array($sections)) {
            $sections[self::SECTION] = __('Product page', 'mizzey-site');
        }

        return $sections;
    }

    /**
     * @param mixed $settings
     * @param mixed $section
     * @return mixed
     */
    public static function settings($settings, $section)
    {
        if ($section !== self::SECTION) {
            return $settings;
        }
        $fields   = [['type' => 'title', 'id' => self::SECTION, 'title' => __('Product page', 'mizzey-site'), 'desc' => __('What the product page states beside the product. Each text is written in English and in Arabic.', 'mizzey-site')]];
        $fields[] = ['type' => 'textarea', 'id' => self::RETURNS . 'en', 'title' => __('Returns summary (English)', 'mizzey-site'), 'desc_tip' => __('A short summary, consistent with the published return policy and its return window.', 'mizzey-site'), 'css' => 'min-height:60px'];
        $fields[] = ['type' => 'textarea', 'id' => self::RETURNS . 'ar', 'title' => __('Returns summary (Arabic)', 'mizzey-site'), 'css' => 'min-height:60px', 'custom_attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
        $fields[] = ['type' => 'single_select_page', 'id' => self::RETURNS_PAGE, 'title' => __('Return policy page', 'mizzey-site'), 'class' => 'wc-page-search', 'args' => ['exclude' => []]];
        $fields[] = ['type' => 'textarea', 'id' => self::AUTHENTIC . 'en', 'title' => __('Authenticity statement (English)', 'mizzey-site'), 'css' => 'min-height:60px'];
        $fields[] = ['type' => 'textarea', 'id' => self::AUTHENTIC . 'ar', 'title' => __('Authenticity statement (Arabic)', 'mizzey-site'), 'css' => 'min-height:60px', 'custom_attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
        $fields[] = ['type' => 'single_select_page', 'id' => self::AUTHENTIC_PAGE, 'title' => __('Authenticity page', 'mizzey-site'), 'class' => 'wc-page-search', 'args' => ['exclude' => []]];
        $fields[] = ['type' => 'number', 'id' => self::DAYS_MIN, 'title' => __('Delivery estimate, from (working days)', 'mizzey-site'), 'desc_tip' => __('Used where the customer\'s address is not known, or its zone has no estimate of its own.', 'mizzey-site'), 'default' => '2', 'custom_attributes' => ['min' => '0', 'step' => '1']];
        $fields[] = ['type' => 'number', 'id' => self::DAYS_MAX, 'title' => __('Delivery estimate, to (working days)', 'mizzey-site'), 'default' => '5', 'custom_attributes' => ['min' => '0', 'step' => '1']];
        foreach (self::zones() as $id => $name) {
            $fields[] = ['type' => 'text', 'id' => self::ZONE . $id, 'title' => sprintf(
                /* translators: %s: a shipping zone's name. */
                __('Delivery estimate for %s', 'mizzey-site'),
                $name,
            ), 'desc_tip' => __('Working days, from and to, for example 1-3. Empty uses the default.', 'mizzey-site'), 'placeholder' => '1-3'];
        }
        $fields[] = ['type' => 'sectionend', 'id' => self::SECTION];

        return $fields;
    }

    /** @return array<int,string> The shipping zones, by id. */
    private static function zones(): array
    {
        if (! class_exists(\WC_Shipping_Zones::class)) {
            return [];
        }
        $zones = [];
        foreach (\WC_Shipping_Zones::get_zones() as $zone) {
            $zones[(int) $zone['id']] = (string) $zone['zone_name'];
        }

        return $zones;
    }

    public static function extend(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => ProductSchema::IDENTIFIER,
            'namespace'       => 'mizzey-page',
            'data_callback'   => static fn ($product): array => $product instanceof \WC_Product ? self::data($product) : [],
            'schema_callback' => static fn (): array => [
                'saving'       => ['description' => __('The saving on a product on sale: amount in minor units, and percent.', 'mizzey-site'), 'type' => ['object', 'null'], 'context' => ['view', 'edit'], 'readonly' => true],
                'buy_now'      => ['description' => __('The address that adds the product to the cart and goes to checkout.', 'mizzey-site'), 'type' => 'string', 'context' => ['view', 'edit'], 'readonly' => true],
                'delivery'     => ['description' => __('The delivery estimate, in working days.', 'mizzey-site'), 'type' => ['object', 'null'], 'context' => ['view', 'edit'], 'readonly' => true],
                'returns'      => ['description' => __('The returns summary and the policy page.', 'mizzey-site'), 'type' => 'object', 'context' => ['view', 'edit'], 'readonly' => true],
                'authenticity' => ['description' => __('The authenticity statement and its page.', 'mizzey-site'), 'type' => 'object', 'context' => ['view', 'edit'], 'readonly' => true],
                'share'        => ['description' => __('The product address and its sharing addresses.', 'mizzey-site'), 'type' => 'object', 'context' => ['view', 'edit'], 'readonly' => true],
            ],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /** @return array<string,mixed> */
    public static function data(\WC_Product $product): array
    {
        $language = self::language();

        return [
            'saving'       => self::saving($product),
            'buy_now'      => self::buyNowUrl($product),
            'delivery'     => self::delivery(self::destination()),
            'returns'      => ['summary' => self::text(self::RETURNS, $language), 'url' => self::page(self::RETURNS_PAGE, $language)],
            'authenticity' => ['statement' => self::text(self::AUTHENTIC, $language), 'url' => self::page(self::AUTHENTIC_PAGE, $language)],
            'share'        => self::share($product),
        ];
    }

    private static function language(): string
    {
        $current = (string) apply_filters('wpml_current_language', null);

        return $current === 'ar' ? 'ar' : 'en';
    }

    /**
     * The saving on a product on sale, or null. A variable product's is its largest variation saving.
     *
     * @return array{amount:string,percent:int}|null
     */
    public static function saving(\WC_Product $product): ?array
    {
        $pairs = [];
        if ($product instanceof \WC_Product_Variable) {
            foreach ($product->get_children() as $child) {
                $variation = wc_get_product($child);
                if ($variation instanceof \WC_Product && $variation->is_on_sale()) {
                    $pairs[] = [(float) $variation->get_regular_price(), (float) $variation->get_sale_price()];
                }
            }
        } elseif ($product->is_on_sale()) {
            $pairs[] = [(float) $product->get_regular_price(), (float) $product->get_sale_price()];
        }
        $best = null;
        foreach ($pairs as [$regular, $sale]) {
            if ($regular > 0 && $sale < $regular && ($best === null || $regular - $sale > $best[0] - $best[1])) {
                $best = [$regular, $sale];
            }
        }
        if ($best === null) {
            return null;
        }
        $decimals = wc_get_price_decimals();

        return [
            'amount'  => (string) (int) round(($best[0] - $best[1]) * (10 ** $decimals)),
            'percent' => (int) floor(($best[0] - $best[1]) / $best[0] * 100),
        ];
    }

    /** WooCommerce's add-to-cart address with the Buy Now flag; a variable product's needs its variation chosen. */
    public static function buyNowUrl(\WC_Product $product, int $quantity = 1): string
    {
        return add_query_arg(['add-to-cart' => $product->get_id(), 'quantity' => max(1, $quantity), self::BUY_NOW => 1], (string) get_permalink($product->get_id()));
    }

    /**
     * Buy Now goes to checkout once the item is in the cart.
     *
     * @param mixed $url
     * @return mixed
     */
    public static function buyNowRedirect($url)
    {
        return isset($_REQUEST[self::BUY_NOW]) && (string) wp_unslash($_REQUEST[self::BUY_NOW]) === '1' && wc_notice_count('error') === 0 ? wc_get_checkout_url() : $url; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
    }

    /** @return array{country:string,state:string,postcode:string}|null The customer's address, if known. */
    private static function destination(): ?array
    {
        if (! function_exists('WC') || WC()->customer === null) {
            return null;
        }
        $country = (string) WC()->customer->get_shipping_country();

        return $country === '' ? null : ['country' => $country, 'state' => (string) WC()->customer->get_shipping_state(), 'postcode' => (string) WC()->customer->get_shipping_postcode()];
    }

    /**
     * The estimate for an address's shipping zone, or the default when there is no address or the zone has none.
     *
     * @param array{country:string,state:string,postcode:string}|null $destination
     * @return array{min:int,max:int,zone:string,text:string}|null
     */
    public static function delivery(?array $destination): ?array
    {
        $min  = (int) get_option(self::DAYS_MIN, 2);
        $max  = (int) get_option(self::DAYS_MAX, 5);
        $zone = '';
        if ($destination !== null && class_exists(\WC_Shipping_Zones::class)) {
            $matched = \WC_Shipping_Zones::get_zone_matching_package(['destination' => $destination + ['city' => '', 'address' => '']]);
            if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', (string) get_option(self::ZONE . $matched->get_id(), ''), $m) === 1) {
                [$min, $max] = [(int) $m[1], (int) $m[2]];
                $zone        = (string) $matched->get_zone_name();
            }
        }
        if ($max <= 0) {
            return null;
        }
        $min = min($min, $max);

        return [
            'min'  => $min,
            'max'  => $max,
            'zone' => $zone,
            'text' => $min === $max
                /* translators: %d: working days. */
                ? sprintf(_n('Estimated delivery: %d working day', 'Estimated delivery: %d working days', $max, 'mizzey-site'), $max)
                /* translators: 1: fewest working days, 2: most working days. */
                : sprintf(__('Estimated delivery: %1$d to %2$d working days', 'mizzey-site'), $min, $max),
        ];
    }

    private static function text(string $prefix, string $language): string
    {
        $text = trim((string) get_option($prefix . $language, ''));

        return $text !== '' ? $text : trim((string) get_option($prefix . 'en', ''));
    }

    private static function page(string $option, string $language): string
    {
        $id = (int) get_option($option, 0);
        if ($id <= 0) {
            return '';
        }
        $local = (int) apply_filters('wpml_object_id', $id, 'page', true, $language);

        return (string) get_permalink($local > 0 ? $local : $id);
    }

    /** @return array{url:string,links:array<string,string>} */
    public static function share(\WC_Product $product): array
    {
        $url   = (string) get_permalink($product->get_id());
        $title = wp_strip_all_tags($product->get_name());

        return [
            'url'   => $url,
            'links' => [
                'whatsapp' => 'https://wa.me/?text=' . rawurlencode($title . ' ' . $url),
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
                'x'        => 'https://x.com/intent/post?url=' . rawurlencode($url) . '&text=' . rawurlencode($title),
                'email'    => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url),
            ],
        ];
    }

    /** The product page's Open Graph tags, so a shared link shows its title, image and price. */
    public static function openGraph(): void
    {
        if (! function_exists('is_product') || ! is_product()) {
            return;
        }
        $product = wc_get_product((int) get_queried_object_id());
        if (! $product instanceof \WC_Product) {
            return;
        }
        $image = (int) $product->get_image_id();
        $tags  = [
            'og:type'                => 'product',
            'og:title'               => wp_strip_all_tags($product->get_name()),
            'og:description'         => wp_trim_words(wp_strip_all_tags((string) ($product->get_short_description() ?: $product->get_description())), 30, '...'),
            'og:url'                 => (string) get_permalink($product->get_id()),
            'og:image'               => $image > 0 ? (string) wp_get_attachment_image_url($image, 'large') : '',
            'og:locale'              => self::language() === 'ar' ? 'ar_EG' : 'en_US',
            'product:price:amount'   => (string) wc_get_price_to_display($product),
            'product:price:currency' => get_woocommerce_currency(),
        ];
        foreach ($tags as $property => $content) {
            if ($content !== '') {
                printf('<meta property="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($content));
            }
        }
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    }
}

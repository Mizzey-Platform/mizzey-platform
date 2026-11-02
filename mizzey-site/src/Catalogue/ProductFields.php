<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

defined('ABSPATH') || exit;

/**
 * The contracted product fields WooCommerce does not have, on its own product screen (#276):
 *
 * - **Video** (ADM-22): one video per product, a web address (an uploaded video's or a YouTube or Vimeo page).
 * - **SEO** (ADM-39 to ADM-41): meta title, meta description, a canonical address override and a robots override, per
 *   language record (each language has its own words and its own address), emitted on the product page.
 * - **Badges** (ADM-42, MER-01): the four manual flags of D-19, New, Trending, Limited and Best Seller, set by staff,
 *   never calculated and never expiring; the card shows one by D-19's precedence. A property of the item, so the
 *   Arabic record carries the English one's (WPML `copy`).
 * - **Related products** (ADM-45): D-19 item 3 and D-20 C3: WooCommerce's Upsells field, labelled "Related products";
 *   WooCommerce's automatic related rail is not used.
 *
 * Video and badges reach the storefront through the Store API (`extensions.mizzey`), for the designed screens after
 * PRE-03b (D-27).
 *
 * Register ids: ADM-22, ADM-39, ADM-40, ADM-41, ADM-42, ADM-45. See specs/036-product-management.
 */
final class ProductFields
{
    public const VIDEO       = '_mizzey_product_video';
    public const SEO_TITLE   = '_mizzey_seo_title';
    public const SEO_DESC    = '_mizzey_seo_description';
    public const CANONICAL   = '_mizzey_seo_canonical';
    public const ROBOTS      = '_mizzey_seo_robots';
    public const BADGES      = '_mizzey_badges';
    public const NONCE       = 'mizzey_product_fields';

    /** D-19's badges, in the order one is chosen for a card: Limited, then New, then Best Seller, then Trending. */
    public const BADGE_ORDER = ['limited', 'new', 'best-seller', 'trending'];
    public const ROBOT_CHOICES = ['', 'noindex', 'nofollow', 'noindex,nofollow'];

    public static function register(): void
    {
        add_action('woocommerce_product_options_general_product_data', [self::class, 'fields']);
        add_action('woocommerce_admin_process_product_object', [self::class, 'save']);
        add_filter('gettext_woocommerce', [self::class, 'relabel'], 10, 2);
        add_filter('woocommerce_related_products', [self::class, 'noAutomaticRelated'], 10, 1);
        add_filter('pre_get_document_title', [self::class, 'title'], 20);
        add_filter('mizzey_meta_description', [self::class, 'description'], 20);
        add_filter('get_canonical_url', [self::class, 'canonical'], 20, 2);
        add_filter('wp_robots', [self::class, 'robots'], 20);
        add_action('woocommerce_init', [self::class, 'extend']);
    }

    /** @return array<string,string> */
    public static function badges(): array
    {
        return [
            'new'         => __('New', 'mizzey-site'),
            'trending'    => __('Trending', 'mizzey-site'),
            'limited'     => __('Limited', 'mizzey-site'),
            'best-seller' => __('Best Seller', 'mizzey-site'),
        ];
    }

    /** @return list<string> A product's badges, in D-19's precedence. */
    public static function badgesOf(\WC_Product $product): array
    {
        $set = (array) $product->get_meta(self::BADGES);

        return array_values(array_filter(self::BADGE_ORDER, static fn (string $b): bool => in_array($b, $set, true)));
    }

    public static function fields(): void
    {
        global $product_object;
        $p = $product_object instanceof \WC_Product ? $product_object : null;
        if ($p === null) {
            return;
        }
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        echo '<div class="options_group mizzey-product-fields">';
        woocommerce_wp_text_input(['id' => 'mizzey_video', 'label' => __('Video address', 'mizzey-site'), 'type' => 'url', 'value' => (string) $p->get_meta(self::VIDEO), 'desc_tip' => true, 'description' => __('An uploaded video, or a YouTube or Vimeo page.', 'mizzey-site')]);
        echo '<p class="form-field"><label>' . esc_html__('Badges', 'mizzey-site') . '</label>';
        $set = (array) $p->get_meta(self::BADGES);
        foreach (self::badges() as $key => $label) {
            printf('<label style="margin-inline-end:12px"><input type="checkbox" name="mizzey_badges[]" value="%s"%s /> %s</label>', esc_attr($key), checked(in_array($key, $set, true), true, false), esc_html($label));
        }
        echo '</p></div><div class="options_group mizzey-product-seo">';
        woocommerce_wp_text_input(['id' => 'mizzey_seo_title', 'label' => __('SEO title', 'mizzey-site'), 'value' => (string) $p->get_meta(self::SEO_TITLE), 'custom_attributes' => ['maxlength' => '120']]);
        woocommerce_wp_textarea_input(['id' => 'mizzey_seo_description', 'label' => __('Meta description', 'mizzey-site'), 'value' => (string) $p->get_meta(self::SEO_DESC), 'custom_attributes' => ['maxlength' => '320']]);
        woocommerce_wp_text_input(['id' => 'mizzey_seo_canonical', 'label' => __('Canonical address (override)', 'mizzey-site'), 'type' => 'url', 'value' => (string) $p->get_meta(self::CANONICAL)]);
        woocommerce_wp_select(['id' => 'mizzey_seo_robots', 'label' => __('Search engines', 'mizzey-site'), 'value' => (string) $p->get_meta(self::ROBOTS), 'options' => [
            ''                 => __('Index and follow (default)', 'mizzey-site'),
            'noindex'          => __('Do not index', 'mizzey-site'),
            'nofollow'         => __('Do not follow links', 'mizzey-site'),
            'noindex,nofollow' => __('Do not index or follow', 'mizzey-site'),
        ]]);
        echo '</div>';
    }

    /** @param mixed $product */
    public static function save($product): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if (! $product instanceof \WC_Product || $nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! current_user_can('edit_product', $product->get_id())) {
            return;
        }
        $text = static fn (string $key, int $max): string => isset($_POST[$key]) ? mb_substr(sanitize_text_field(wp_unslash((string) $_POST[$key])), 0, $max) : '';
        $url  = static fn (string $key): string => isset($_POST[$key]) ? esc_url_raw(trim(wp_unslash((string) $_POST[$key])), ['http', 'https']) : '';
        $product->update_meta_data(self::VIDEO, $url('mizzey_video'));
        $product->update_meta_data(self::SEO_TITLE, $text('mizzey_seo_title', 120));
        $product->update_meta_data(self::SEO_DESC, isset($_POST['mizzey_seo_description']) ? mb_substr(sanitize_textarea_field(wp_unslash((string) $_POST['mizzey_seo_description'])), 0, 320) : '');
        $product->update_meta_data(self::CANONICAL, $url('mizzey_seo_canonical'));
        $robots = $text('mizzey_seo_robots', 20);
        $product->update_meta_data(self::ROBOTS, in_array($robots, self::ROBOT_CHOICES, true) ? $robots : '');
        $badges = isset($_POST['mizzey_badges']) && is_array($_POST['mizzey_badges']) ? array_map('sanitize_key', wp_unslash($_POST['mizzey_badges'])) : [];
        $product->update_meta_data(self::BADGES, array_values(array_intersect(self::BADGE_ORDER, $badges)));
    }

    /**
     * "Upsells" is shown to staff as "Related products" (D-20 C3), on the product screen only.
     *
     * @param mixed $translation
     * @param mixed $text
     * @return mixed
     */
    public static function relabel($translation, $text)
    {
        if (is_admin() && $text === 'Upsells') {
            return __('Related products', 'mizzey-site');
        }

        return $translation;
    }

    /**
     * WooCommerce's automatic related products are not used (D-19 item 3): the related products are the staff's
     * Upsells, shown through that field, so the automatic rail is always empty.
     *
     * @param mixed $related
     * @return array<int>
     */
    public static function noAutomaticRelated($related): array
    {
        return [];
    }

    private static function current(): ?\WC_Product
    {
        if (! function_exists('is_product') || ! is_product()) {
            return null;
        }
        $product = wc_get_product((int) get_queried_object_id());

        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * @param mixed $title
     * @return mixed
     */
    public static function title($title)
    {
        $p = self::current();
        $own = $p ? trim((string) $p->get_meta(self::SEO_TITLE)) : '';

        return $own !== '' ? $own : $title;
    }

    /**
     * @param mixed $description
     * @return mixed
     */
    public static function description($description)
    {
        $p = self::current();
        $own = $p ? trim((string) $p->get_meta(self::SEO_DESC)) : '';

        return $own !== '' ? $own : $description;
    }

    /**
     * @param mixed $url
     * @param mixed $post
     * @return mixed
     */
    public static function canonical($url, $post = null)
    {
        $product = $post instanceof \WP_Post && $post->post_type === 'product' ? wc_get_product($post->ID) : null;
        $own     = $product instanceof \WC_Product ? (string) $product->get_meta(self::CANONICAL) : '';

        return $own !== '' ? $own : $url;
    }

    /**
     * @param mixed $robots
     * @return mixed
     */
    public static function robots($robots)
    {
        $p = self::current();
        $own = $p ? (string) $p->get_meta(self::ROBOTS) : '';
        if (! is_array($robots) || $own === '') {
            return $robots;
        }
        foreach (explode(',', $own) as $directive) {
            $robots[$directive] = true;
            unset($robots[$directive === 'noindex' ? 'index' : 'follow']);
        }

        return $robots;
    }

    public static function extend(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => ProductSchema::IDENTIFIER,
            'namespace'       => 'mizzey',
            'data_callback'   => static function ($product): array {
                return $product instanceof \WC_Product
                    ? ['video' => (string) $product->get_meta(self::VIDEO), 'badges' => self::badgesOf($product)]
                    : ['video' => '', 'badges' => []];
            },
            'schema_callback' => static fn (): array => [
                'video'  => ['description' => __('The product video address, if any.', 'mizzey-site'), 'type' => 'string', 'context' => ['view', 'edit'], 'readonly' => true],
                'badges' => ['description' => __('The manual badges, in the order a card shows them.', 'mizzey-site'), 'type' => 'array', 'items' => ['type' => 'string'], 'context' => ['view', 'edit'], 'readonly' => true],
            ],
            'schema_type'     => ARRAY_A,
        ]);
    }
}

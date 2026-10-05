<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * Keeps the brand archive's address word the same in every language.
 *
 * The measured fault, on WooCommerce 11.1.0 with its Arabic language pack. When no brand address has been saved
 * in the permalink settings, `WC_Brands::init_taxonomy()` takes the word from `__( 'brand', 'woocommerce' )`, a
 * translatable string, in the language of the request it happens to run in. The routing rules are built once and
 * stored, so they know one spelling. In an Arabic request the taxonomy is registered under the Arabic word, and
 * every link built from it, the canonical link, the language links and the feed link of a brand archive, carries
 * that word and leads to a 404, while the documented address `/ar/brand/{term}/` goes on answering. Had the rules
 * been rebuilt inside an Arabic request, the English addresses would have been the ones to break.
 *
 * WooCommerce avoids this for categories, tags and products by reading their default words in the site language
 * (`wc_get_permalink_structure()`). The brand taxonomy does not go through that function.
 *
 * The correction supplies the documented word, `brand`, as the default, through the filter WooCommerce applies
 * to the taxonomy's arguments. A word saved in the permalink settings is a stored literal, the same in every
 * language, and is left alone: the setting keeps working.
 *
 * Register ids: IA-05, NFR-03. See specs/003-information-architecture-urls.
 */
final class Brands
{
    /** The address word of the URL map: `/brand/{term}/` and `/ar/brand/{term}/`. */
    private const SLUG = 'brand';

    public static function register(): void
    {
        add_filter('register_taxonomy_product_brand', [self::class, 'sameAddressWordInEveryLanguage']);
    }

    /**
     * Replace the translatable default address word with the documented one.
     *
     * @param mixed $args The taxonomy's registration arguments.
     * @return mixed
     */
    public static function sameAddressWordInEveryLanguage($args)
    {
        if (!is_array($args) || '' !== (string) get_option('woocommerce_brand_permalink', '')) {
            return $args;
        }

        $args['rewrite'] = array_merge((array) ($args['rewrite'] ?? []), ['slug' => self::SLUG]);

        return $args;
    }
}

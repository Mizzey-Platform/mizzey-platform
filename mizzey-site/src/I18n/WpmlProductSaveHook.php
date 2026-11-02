<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Takes out WPML 4.9.7's two product-save handlers, which do nothing but log two deprecations on every product save.
 *
 * WPML hooks `WpmlSavePostHooks::on_product_save` and WPML Media's
 * `WPML_Media_Attachments_Duplication::woocommerce_extract_media_ids_from_post_content_and_meta` to
 * `woocommerce_after_product_object_save`, and each passes the product object to `get_post()`. WordPress then sanitises
 * it as a post, creating the dynamic properties `ID` and `filter` on the product (deprecated since PHP 8.2), and builds
 * a post whose ID is 0, which each handler's own check rejects, so both stop there. Removing them changes nothing but
 * the log: WPML still handles a product saved through the editor on `wp_after_insert_post`. Pinned to the version this
 * was read in: under any other WPML version the handlers stay, so an update that fixes them is not silenced.
 *
 * See specs/048-clean-log.
 */
final class WpmlProductSaveHook
{
    public const VERSION = '4.9.7';

    private const HOOK = 'woocommerce_after_product_object_save';

    /** class => method, as WPML 4.9.7 registers them. */
    private const HANDLERS = [
        'WPML\Hooks\WpmlSavePostHooks'      => 'on_product_save',
        'WPML_Media_Attachments_Duplication' => 'woocommerce_extract_media_ids_from_post_content_and_meta',
    ];

    public static function register(): void
    {
        add_action('init', [self::class, 'remove'], 1);
    }

    /** Remove WPML's handlers when the known version is running. Returns how many were removed. */
    public static function remove(): int
    {
        global $wp_filter;
        if (! defined('ICL_SITEPRESS_VERSION') || ICL_SITEPRESS_VERSION !== self::VERSION || ! isset($wp_filter[self::HOOK])) {
            return 0;
        }
        $removed = 0;
        foreach ($wp_filter[self::HOOK]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $fn = $callback['function'];
                if (! is_array($fn) || ! is_object($fn[0]) || ! is_string($fn[1])) {
                    continue;
                }
                foreach (self::HANDLERS as $class => $method) {
                    if ($fn[0] instanceof $class && $fn[1] === $method && remove_action(self::HOOK, $fn, (int) $priority)) {
                        $removed++;
                    }
                }
            }
        }

        return $removed;
    }
}

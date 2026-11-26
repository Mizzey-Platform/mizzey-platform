<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Performance;

defined('ABSPATH') || exit;

/**
 * Drops every stored page (both languages) when something a page shows changes (NFR-02, `PageCache`).
 *
 * What counts as a change: a product, a variation, its price or its stock (through WooCommerce, or written straight to
 * the product's meta, as WooCommerce Multilingual does for the Arabic product); any post, page or block the
 * storefront is built from (the home page's sections are its blocks, the header and footer are template parts and
 * navigation menus); a category, brand or collection and their settings; a classic menu; a review; a setting.
 * Transients, cron's schedule and the other options WordPress and its plugins write while serving a page are not
 * settings and do not purge.
 *
 * Purging is one command: the page generation is replaced when a change is seen and again as the request ends, so a
 * page built while the change was being saved is never sent. When Redis cannot be reached nothing
 * is being served from it either; the failure is logged.
 */
final class PageCachePurge
{
    /** Post types whose changes no stored page shows. */
    private const QUIET_POST_TYPES = ['revision', 'oembed_cache', 'customize_changeset', 'user_request', 'scheduled-action',
        'shop_order', 'shop_order_refund', 'shop_order_placehold', 'wp_font_family', 'wp_font_face'];
    /** Product meta that changes what a page shows. */
    private const PRODUCT_META = ['_price', '_regular_price', '_sale_price', '_stock', '_stock_status', '_sale_price_dates_from', '_sale_price_dates_to', '_visibility', '_featured'];
    /** Options written while serving or maintaining the site, not settings anyone changes. Prefixes and exact names. */
    private const QUIET_OPTIONS = ['_transient_', '_site_transient_', 'cron', 'action_scheduler_', 'woocommerce_admin_', 'wc_admin_',
        'wc_remote_inbox_', '_wc_', 'woocommerce_tracker_', 'woocommerce_marketplace_', 'jetpack_', 'recently_activated', 'auto_updater',
        'can_compress_scripts', 'rewrite_rules', 'icl_admin_messages', 'wpml_notices', '_wpml_', 'wpml_dependencies', 'wp_2fa_',
        'mizzey_staff_activity', 'mizzey_monitoring', 'db_upgraded', 'fresh_site', 'theme_switched'];

    private static bool $purged = false;
    private static float $last = 0.0;

    public static function register(): void
    {
        if (RedisSettings::fromEnvironment() === null) {
            return;
        }
        foreach (['save_post', 'deleted_post', 'trashed_post', 'untrashed_post'] as $hook) {
            add_action($hook, [self::class, 'post']);
        }
        foreach (['woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_set_stock_status',
            'woocommerce_variation_set_stock_status', 'woocommerce_update_product', 'woocommerce_update_product_variation',
            'woocommerce_new_product', 'woocommerce_new_product_variation', 'woocommerce_delete_product',
            'woocommerce_delete_product_variation', 'woocommerce_trash_product'] as $hook) {
            add_action($hook, [self::class, 'purge']);
        }
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'meta'], 10, 3);
        }
        foreach (['created_term', 'edited_term', 'delete_term', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta',
            'wp_update_nav_menu', 'wp_delete_nav_menu', 'comment_post', 'edit_comment', 'wp_set_comment_status', 'deleted_comment',
            'customize_save_after', 'switch_theme'] as $hook) {
            add_action($hook, [self::class, 'purge']);
        }
        foreach (['added_option', 'updated_option', 'deleted_option'] as $hook) {
            add_action($hook, [self::class, 'option']);
        }
    }

    /** @param mixed $postId */
    public static function post($postId): void
    {
        $type = get_post_type((int) $postId);
        if ($type !== false && ! in_array($type, self::QUIET_POST_TYPES, true) && ! wp_is_post_autosave((int) $postId)) {
            self::purge();
        }
    }

    /**
     * @param mixed $metaId
     * @param mixed $objectId
     * @param mixed $key
     */
    public static function meta($metaId, $objectId, $key): void
    {
        if (is_string($key) && in_array($key, self::PRODUCT_META, true) && in_array(get_post_type((int) $objectId), ['product', 'product_variation'], true)) {
            self::purge();
        }
    }

    /** @param mixed $name */
    public static function option($name): void
    {
        if (! is_string($name)) {
            return;
        }
        foreach (self::QUIET_OPTIONS as $quiet) {
            if ($name === $quiet || str_starts_with($name, $quiet)) {
                return;
            }
        }
        self::purge();
    }

    /**
     * Drops every stored page now, and again when the request ends. A request that changes many things (an import, a
     * stock run) purges at most once a second while it works, and once more at its end.
     */
    public static function purge(): void
    {
        $now = microtime(true);
        if (! self::$purged) {
            self::$purged = true;
            add_action('shutdown', [self::class, 'replaceGeneration'], PHP_INT_MAX);
        } elseif ($now - self::$last < 1.0) {
            return;
        }
        self::$last = $now;
        self::replaceGeneration();
    }

    /** Replaces the page generation. Returns whether Redis took it. */
    public static function replaceGeneration(): bool
    {
        $settings = RedisSettings::fromEnvironment();
        if ($settings === null) {
            return false;
        }
        try {
            $link = RedisLink::open($settings);
            $link->command('SET', $settings->prefix . PageCache::GENERATION, PageCache::newGeneration());
            $link->close();

            return true;
        } catch (RedisFailure $failure) {
            PageCache::note($failure);

            return false;
        }
    }

    /** For tests: the next change in this request purges at once. */
    public static function reset(): void
    {
        self::$purged = false;
        self::$last   = 0.0;
        remove_action('shutdown', [self::class, 'replaceGeneration'], PHP_INT_MAX);
    }
}

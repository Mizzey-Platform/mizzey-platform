<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * Every store attribute (`pa_size`, `pa_colour` and any the catalogue load creates) is translatable, so each value has
 * its Arabic term and the Arabic store shows it (specs/053).
 *
 * WooCommerce Multilingual treats an attribute as translatable unless staff turn that off, but it records the choice
 * only when the attribute is created from its admin screen; an attribute created by code is left out of WPML's
 * settings, and WPML then serves its English terms on the Arabic store. The default is supplied as WPML's settings
 * are read: nothing is written, and a choice saved for an attribute (on or off) always wins.
 *
 * Register ids: MIG-10, SSC-21. See specs/053-sheet-arabic-tabs.
 */
final class StoreAttributes
{
    public static function register(): void
    {
        add_filter('option_icl_sitepress_settings', [self::class, 'translatable']);
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function translatable($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }
        $sync = (array) ($settings['taxonomies_sync_option'] ?? []);
        foreach (self::names() as $name) {
            $taxonomy = 'pa_' . $name;
            if (! array_key_exists($taxonomy, $sync)) {
                $sync[$taxonomy] = 1;
            }
        }
        $settings['taxonomies_sync_option'] = $sync;

        return $settings;
    }

    /** @var list<string>|null */
    private static ?array $names = null;

    /**
     * The attributes' names, read from WooCommerce's own table: WPML may read its settings before WooCommerce has loaded.
     *
     * @return list<string>
     */
    private static function names(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return self::$names = [];
        }

        return self::$names = array_map('strval', (array) $wpdb->get_col($wpdb->prepare('SELECT attribute_name FROM %i', $table))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    /** Forgets the names read, after an attribute is added in this request. */
    public static function forget(): void
    {
        self::$names = null;
        wp_cache_delete('icl_sitepress_settings', 'options');
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Fills WooCommerce Multilingual settings its own installer would have written, when the stored settings lack them.
 *
 * `products_sync_date` (translations keep the original's publish date) is set by WooCommerce Multilingual's installer
 * and settings screen, defaulting to on. This store's settings were never saved from that screen, so the key is
 * missing and every translation save logs an undefined-key warning. The default is supplied as the option is read;
 * nothing is written, and a value saved from the settings screen always wins.
 *
 * See specs/048-clean-log.
 */
final class WcmlSettingsDefaults
{
    /** @var array<string,int> WooCommerce Multilingual's own defaults (class-wcml-install.php). */
    private const DEFAULTS = ['products_sync_date' => 1];

    public static function register(): void
    {
        add_filter('option__wcml_settings', [self::class, 'fill']);
    }

    /**
     * @param mixed $settings
     *
     * @return mixed
     */
    public static function fill($settings)
    {
        return is_array($settings) ? $settings + self::DEFAULTS : $settings;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Applies `mizzey-site/wpml-config.xml` as soon as it changes.
 *
 * WPML reads the configuration files of plugins only when staff open a few admin pages (Plugins, Themes, its own
 * settings), so a release that declares a new field (which product fields are copied to the Arabic record and which
 * are each language's own, specs/036) would not take effect until someone happened to open one. This runs WPML's own
 * loader once after the file changes, in the administration or on the command line, and remembers the file it applied.
 *
 * See specs/036-product-management.
 */
final class WpmlConfigSync
{
    public const OPTION = 'mizzey_wpml_config_applied';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'sync'], 99);
        if (defined('WP_CLI') && WP_CLI) {
            add_action('wp_loaded', [self::class, 'sync'], 99);
        }
    }

    public static function file(): string
    {
        return dirname(__DIR__, 2) . '/wpml-config.xml';
    }

    /** Run WPML's loader when the file differs from the one last applied. Returns whether it ran. */
    public static function sync(): bool
    {
        if (! class_exists('\WPML_Config') || ! is_readable(self::file())) {
            return false;
        }
        $hash = (string) md5_file(self::file());
        if (get_option(self::OPTION) === $hash) {
            return false;
        }
        \WPML_Config::load_config_run();
        update_option(self::OPTION, $hash, false);

        return true;
    }
}

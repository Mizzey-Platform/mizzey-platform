<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * The administration is in English for every staff member (ADM-159; an Arabic administration is ADM-159a, P2).
 *
 * The measured gap. WordPress lets each user choose their own language, and WPML its own admin language, so a staff
 * member who picks Arabic gets an Arabic, right-to-left administration (t77). Admin screens are fixed to English;
 * nothing a customer sees is touched: the storefront, the Store API and the REST API keep the visitor's language, and
 * so does an admin-ajax request the storefront makes.
 *
 * Register ids: ADM-159. See specs/037-everyday-admin.
 */
final class AdminEnglish
{
    public const LOCALE = 'en_US';

    public static function register(): void
    {
        add_filter('determine_locale', [self::class, 'locale'], 99);
        // WPML gives the administration the staff member's admin language through the site locale.
        add_filter('locale', [self::class, 'locale'], 99);
        // And its reading direction: English reads left to right, whatever the staff member's admin language.
        add_action('admin_init', [self::class, 'direction'], 0);
        add_action('set_current_user', [self::class, 'direction'], 99);
    }

    public static function direction(): void
    {
        global $wp_locale;
        if (self::adminScreen() && $wp_locale instanceof \WP_Locale) {
            $wp_locale->text_direction = 'ltr';
        }
    }

    /** Whether this request is an administration screen (not a REST, ajax or storefront request). */
    public static function adminScreen(): bool
    {
        return is_admin() && ! wp_doing_ajax() && ! (defined('REST_REQUEST') && REST_REQUEST) && ! wp_doing_cron();
    }

    /**
     * @param mixed $locale
     * @return mixed
     */
    public static function locale($locale)
    {
        return self::adminScreen() ? self::LOCALE : $locale;
    }
}

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
        // WPML 5.3 sets the direction from the staff member's profile language on `admin_init` and on
        // `admin_print_styles` (its rtl_fix, at 10): English is set again after it, before the styles are printed (20).
        add_action('admin_init', [self::class, 'direction'], 99);
        add_action('admin_print_styles', [self::class, 'direction'], 15);
        // And WPML 5.3 writes the profile language into the page's `lang`: the administration's page says English.
        add_filter('language_attributes', [self::class, 'attributes'], 99);
    }

    public static function direction(): void
    {
        global $wp_locale, $wp_styles;
        if (! self::adminScreen()) {
            return;
        }
        if ($wp_locale instanceof \WP_Locale) {
            $wp_locale->text_direction = 'ltr';
        }
        // The style queue's own direction chooses the right-to-left stylesheets (common-rtl.css and the rest).
        if ($wp_styles instanceof \WP_Styles) {
            $wp_styles->text_direction = 'ltr';
        }
        $GLOBALS['text_direction'] = 'ltr';
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
        // An explicit switch (an order's language for its email, invoice or packing slip) always wins.
        // WordPress asks for the locale before its switcher exists; is_locale_switched() would then fail.
        if (($GLOBALS['wp_locale_switcher'] ?? null) instanceof \WP_Locale_Switcher && is_locale_switched()) {
            return $locale;
        }

        return self::adminScreen() ? self::LOCALE : $locale;
    }

    /**
     * The page's `lang` and direction on an admin screen: English, left to right.
     *
     * @param mixed $attributes
     * @return mixed
     */
    public static function attributes($attributes)
    {
        if (! self::adminScreen() || ! is_string($attributes) || (($GLOBALS['wp_locale_switcher'] ?? null) instanceof \WP_Locale_Switcher && is_locale_switched())) {
            return $attributes;
        }
        $attributes = (string) preg_replace('/\s*dir="[^"]*"/', '', $attributes);

        return (string) preg_replace('/lang="[^"]*"/', 'lang="en-US"', $attributes);
    }
}

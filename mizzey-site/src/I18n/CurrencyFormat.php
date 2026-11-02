<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * The currency is written the Arabic way on Arabic pages: the amount, a space, then the Arabic symbol.
 *
 * The measured gap. WooCommerce keeps one symbol (`EGP`) and one position (`woocommerce_currency_pos`) for every
 * language, so the Arabic shop printed `EGP91.00`, the same as the English one (audit of 2 November 2026).
 * WooCommerce's own locale data gives the Egyptian pound in Arabic as the amount, a space and the symbol
 * (`i18n/locale-info.php`, EG, `right_space`), and every reviewed Arabic wireframe draws the amount followed by the
 * Arabic abbreviation of the pound.
 *
 * The position is changed through the option itself, because WooCommerce reads it in three places: `wc_price()`, the
 * Store API's currency fields and the blocks' settings. A filter on the price format alone would leave the cart and
 * checkout blocks in the English order. The symbol is a translation, so an Arabic email or invoice, which switches
 * to the order's language, writes it too. English keeps the store's setting, and the administration is English
 * (ADM-159), so staff see the stored values in the settings screen. Digits stay Western 0 to 9 (D-20 B2).
 *
 * Register id: NFR-04b. See specs/032-image-handling, AC-4.
 */
final class CurrencyFormat
{
    /** The amount, a space, then the symbol. */
    public const ARABIC_POSITION = 'right_space';

    public static function register(): void
    {
        add_filter('option_woocommerce_currency_pos', [self::class, 'position'], 20);
        add_filter('woocommerce_currency_symbol', [self::class, 'symbol'], 20, 2);
    }

    /**
     * @param mixed $position The stored position.
     *
     * @return mixed
     */
    public static function position(mixed $position): mixed
    {
        return self::arabic() ? self::ARABIC_POSITION : $position;
    }

    /**
     * @param mixed $symbol   WooCommerce's symbol.
     * @param mixed $currency The currency code.
     *
     * @return mixed
     */
    public static function symbol(mixed $symbol, mixed $currency): mixed
    {
        if ($currency !== 'EGP' || ! self::arabic()) {
            return $symbol;
        }

        /* translators: The Egyptian pound's symbol, written beside a price. */
        return __('EGP', 'mizzey-site');
    }

    /**
     * Whether text is being written in Arabic: an Arabic page, or an Arabic order's email or invoice. An explicit locale
     * switch (an order's email or invoice) decides first; otherwise WPML's current language, since WPML answers the
     * site locale with it and `get_locale()` alone can still read English while WPML serves Arabic.
     */
    private static function arabic(): bool
    {
        if (function_exists('is_locale_switched') && is_locale_switched()) {
            $switcher = $GLOBALS['wp_locale_switcher'] ?? null;
            $switched = $switcher instanceof \WP_Locale_Switcher ? (string) $switcher->get_switched_locale() : '';

            return str_starts_with(strtolower($switched), 'ar');
        }
        $language = (string) apply_filters('wpml_current_language', null);
        if ($language !== '') {
            return $language === 'ar';
        }

        return str_starts_with(strtolower((string) get_locale()), 'ar');
    }
}

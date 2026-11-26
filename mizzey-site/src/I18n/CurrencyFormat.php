<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Prices read as the boards draw them, in each language (owner ruling of 25 November 2026, specs/066).
 *
 * English: `EGP`, a non-breaking space, the amount with its thousands separator, and no `.00` on a whole pound
 * (`EGP 5,099`); piasters stay when a price has them (`EGP 99.50`, never `EGP 99.5`). Arabic, as C1-Home-Phone-Arabic
 * and C1-Arabic-Stage-2 draw it: the amount the same way, a non-breaking space, then the Arabic pound (`5,099 ج.م`).
 * Digits stay Western 0 to 9 (D-20 B2).
 *
 * The measured gap. WooCommerce keeps one symbol (`EGP`) and one position (`woocommerce_currency_pos`) for every
 * language, so the Arabic shop printed `EGP91.00`, the same as the English one (audit of 2 November 2026), and the
 * store's settings gave `EGP5,099.00` in English. WooCommerce's own locale data gives the Egyptian pound in Arabic as
 * the amount, a space and the symbol (`i18n/locale-info.php`, EG, `right_space`).
 *
 * The position is changed through the option itself, because WooCommerce reads it in three places: `wc_price()`, the
 * Store API's currency fields and the blocks' settings. The separators are set through WooCommerce's own filters,
 * which the same three read, and the whole-pound `.00` through `woocommerce_price_trim_zeros` (WooCommerce takes the
 * decimals off only when they are all zeros). All of it holds for the store's own currency whatever the settings
 * screen says, so a staff change there does not change what customers read; the settings screen shows the ruled
 * values. The number of decimals, the stored prices and every total are left alone: this is display only.
 *
 * In Arabic the non-breaking space and the pound are wrapped together in `.mizzey-price-unit`
 * (`woocommerce_price_format`), so the theme sets them in Tajawal as the boards draw them while the amount stays
 * Inter; the words read the same (`5,099 ج.م`).
 *
 * WooCommerce writes the space of the Store API's prefix and suffix as a plain one, so it is made non-breaking there.
 * Its blocks (cart, checkout, mini cart) always pad the decimals and have no setting for it, so
 * `assets/price-display.js` takes the `.00` off the whole amounts they draw. The symbol is a translation, so an Arabic
 * email or invoice, which switches to the order's language, writes it too.
 *
 * Register ids: NFR-04b (specs/032-image-handling, AC-4); PLP-01, PLP-02, PDP-04 (specs/066).
 */
final class CurrencyFormat
{
    /** The amount, a space, then the symbol. */
    public const ARABIC_POSITION = 'right_space';

    /** The symbol, a space, then the amount. */
    public const ENGLISH_POSITION = 'left_space';

    /** The currency the ruled form is for: the store's own. */
    public const CURRENCY = 'EGP';

    public const THOUSANDS = ',';

    public const DECIMAL = '.';

    public const SCRIPT = 'mizzey-price-display';

    private const NBSP = "\u{00A0}";

    public static function register(): void
    {
        add_filter('option_woocommerce_currency_pos', [self::class, 'position'], 20);
        add_filter('woocommerce_currency_symbol', [self::class, 'symbol'], 20, 2);
        add_filter('woocommerce_price_format', [self::class, 'format'], 20, 2);
        add_filter('wc_get_price_thousand_separator', [self::class, 'thousands'], 20);
        add_filter('wc_get_price_decimal_separator', [self::class, 'decimal'], 20);
        add_filter('woocommerce_price_trim_zeros', [self::class, 'trimZeros'], 20);
        add_filter('rest_request_after_callbacks', [self::class, 'storeApi'], 20, 3);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * After the amount in Arabic and before it in English, with a space either way.
     *
     * @param mixed $position The stored position.
     *
     * @return mixed
     */
    public static function position(mixed $position): mixed
    {
        if (self::arabic()) {
            return self::ARABIC_POSITION;
        }

        return self::ours() ? self::ENGLISH_POSITION : $position;
    }

    /**
     * @param mixed $symbol   WooCommerce's symbol.
     * @param mixed $currency The currency code.
     *
     * @return mixed
     */
    public static function symbol(mixed $symbol, mixed $currency): mixed
    {
        if ($currency !== self::CURRENCY || ! self::arabic()) {
            return $symbol;
        }

        /* translators: The Egyptian pound's symbol, written beside a price. */
        return __('EGP', 'mizzey-site');
    }

    /**
     * Arabic: the amount, then the non-breaking space and the pound in one span, so both can be set in the Arabic
     * face (the amount stays in Inter). `%1$s` is the symbol, `%2$s` the amount, as in WooCommerce's own formats.
     *
     * @param mixed $format   WooCommerce's format for the position.
     * @param mixed $position The currency position.
     *
     * @return mixed
     */
    public static function format(mixed $format, mixed $position): mixed
    {
        if (self::ARABIC_POSITION !== $position || ! self::arabic()) {
            return $format;
        }

        return '%2$s<span class="mizzey-price-unit">' . self::NBSP . '%1$s</span>';
    }

    /**
     * @param mixed $separator The stored separator.
     *
     * @return mixed
     */
    public static function thousands(mixed $separator): mixed
    {
        return self::ours() ? self::THOUSANDS : $separator;
    }

    /**
     * @param mixed $separator The stored separator.
     *
     * @return mixed
     */
    public static function decimal(mixed $separator): mixed
    {
        return self::ours() ? self::DECIMAL : $separator;
    }

    /**
     * No `.00` on a whole pound; `99.50` keeps its two places.
     *
     * @param mixed $trim WooCommerce's choice.
     *
     * @return mixed
     */
    public static function trimZeros(mixed $trim): mixed
    {
        return self::ours() ? true : $trim;
    }

    /**
     * The Store API's money objects carry a non-breaking space between the symbol and the amount, as `wc_price()`
     * writes it. Only the prefix and the suffix change: the amounts and their minor unit stay as WooCommerce gives them.
     *
     * @param mixed $response The response.
     * @param mixed $handler  The route's handler.
     * @param mixed $request  The request.
     *
     * @return mixed
     */
    public static function storeApi(mixed $response, mixed $handler, mixed $request): mixed
    {
        if (! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request || ! str_starts_with($request->get_route(), '/wc/store/')) {
            return $response;
        }
        $response->set_data(self::spaces($response->get_data()));

        return $response;
    }

    /**
     * The prefix and suffix of every money object in a Store API answer, with a non-breaking space.
     *
     * @param mixed $data A Store API answer, or part of one.
     *
     * @return mixed
     */
    public static function spaces(mixed $data): mixed
    {
        if ($data instanceof \stdClass) {
            foreach (get_object_vars($data) as $key => $value) {
                $data->{$key} = self::field((string) $key, $value);
            }

            return $data;
        }
        if (! is_array($data)) {
            return $data;
        }
        foreach ($data as $key => $value) {
            $data[$key] = self::field((string) $key, $value);
        }

        return $data;
    }

    /** The script that takes `.00` off the whole amounts WooCommerce's blocks draw. */
    public static function enqueue(): void
    {
        if (! self::ours()) {
            return;
        }
        $file = WP_PLUGIN_DIR . '/mizzey-site/assets/price-display.js';
        wp_enqueue_script(self::SCRIPT, plugins_url('mizzey-site/assets/price-display.js'), [], is_readable($file) ? (string) filemtime($file) : null, true);
    }

    private static function field(string $key, mixed $value): mixed
    {
        if (($key === 'currency_prefix' || $key === 'currency_suffix') && is_string($value)) {
            return str_replace(' ', self::NBSP, $value);
        }

        return is_array($value) || $value instanceof \stdClass ? self::spaces($value) : $value;
    }

    /** Whether the store sells in the currency the ruled form is for. */
    private static function ours(): bool
    {
        return get_option('woocommerce_currency') === self::CURRENCY;
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

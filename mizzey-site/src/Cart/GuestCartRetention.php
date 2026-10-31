<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Cart;

defined('ABSPATH') || exit;

/**
 * A guest's cart survives closing the browser and coming back within a set window (CART-16).
 *
 * WooCommerce keeps a guest's cart in its session, whose cookie and stored row last two days by default
 * (`WC_Session_Handler::set_session_expiration()`, 11.1.0). The window is a setting under WooCommerce, Settings,
 * General, "Keep a guest's cart for (days)": 30 by default, a proposed working default for the owner to confirm (the
 * Stage 3 build plan). It sets the guest session's lifetime, renewed a day before it ends; a signed-in customer's
 * cart is kept with the account (CART-09) and is not changed. The guest wishlist lives as long (specs/015).
 *
 * Register ids: CART-16. See specs/018-cart.
 */
final class GuestCartRetention
{
    public const OPTION  = 'mizzey_guest_cart_days';
    public const DEFAULT = 30;

    public static function register(): void
    {
        add_filter('wc_session_expiration', [self::class, 'expiration'], 10, 1);
        add_filter('wc_session_expiring', [self::class, 'expiring'], 10, 1);
        add_filter('woocommerce_general_settings', [self::class, 'setting'], 10, 1);
    }

    public static function days(): int
    {
        $days = filter_var(get_option(self::OPTION, (string) self::DEFAULT), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);

        return $days === false ? self::DEFAULT : (int) $days;
    }

    /**
     * @param mixed $seconds
     * @return mixed
     */
    public static function expiration($seconds)
    {
        return is_user_logged_in() ? $seconds : self::days() * DAY_IN_SECONDS;
    }

    /**
     * @param mixed $seconds
     * @return mixed
     */
    public static function expiring($seconds)
    {
        return is_user_logged_in() ? $seconds : max(DAY_IN_SECONDS, self::days() * DAY_IN_SECONDS - DAY_IN_SECONDS);
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function setting($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }
        $field = [
            'title'             => __("Keep a guest's cart for (days)", 'mizzey-site'),
            'desc'              => __('How long a visitor who has not signed in finds their cart again after closing the browser.', 'mizzey-site'),
            'id'                => self::OPTION,
            'type'              => 'number',
            'default'           => (string) self::DEFAULT,
            'custom_attributes' => ['min' => 1, 'max' => 365, 'step' => 1],
            'desc_tip'          => true,
        ];
        $out = [];
        foreach ($settings as $row) {
            $out[] = $row;
            if (($row['id'] ?? '') === 'woocommerce_calc_discounts_sequentially') {
                $out[] = $field;
            }
        }

        return count($out) > count($settings) ? $out : [...$settings, $field];
    }
}

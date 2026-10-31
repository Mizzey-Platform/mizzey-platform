<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * Whether promotions combine is a setting staff control, not a hidden behaviour (BR-004). It sits with WooCommerce's
 * own coupon settings (WooCommerce > Settings > General), and is off by default: one promotion per order (Part One
 * 1.6, "One promotion applies per order unless separately configured").
 *
 * Off, every promotion behaves as individual-use, whatever its own box says, so a code the customer enters replaces any
 * other, and one automatic promotion applies (#281). On, each coupon's own "Individual use only" box decides: the best
 * automatic discount and the best automatic free shipping can both apply, beside a code the customer enters, as far as
 * none of them is individual-use (AutomaticPromotions::settle()). The welcome discount (BR-001) is an automatic
 * promotion of its customer's and follows the same rule; its template's box carries OD-03's "not combined".
 *
 * Register ids: BR-004. See specs/016-launch-rules.
 */
final class Combination
{
    public const OPTION = 'mizzey_promotions_combine';

    public static function register(): void
    {
        add_filter('woocommerce_general_settings', [self::class, 'setting']);
        add_filter('woocommerce_coupon_get_individual_use', [self::class, 'individualUse'], 10, 2);
    }

    public static function allowed(): bool
    {
        return get_option(self::OPTION, 'no') === 'yes';
    }

    /**
     * The checkbox, after WooCommerce's "Calculate coupon discounts sequentially".
     *
     * @param mixed $settings
     * @return mixed
     */
    public static function setting($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }
        $field = [
            'title'    => __('Combine promotions', 'mizzey-site'),
            'desc'     => __('Allow promotions to combine on one order: a coupon code, the welcome discount, an automatic discount and free shipping. When off, one promotion applies per order.', 'mizzey-site'),
            'id'       => self::OPTION,
            'default'  => 'no',
            'type'     => 'checkbox',
            'autoload' => true,
        ];
        $out = [];
        $placed = false;
        foreach ($settings as $setting) {
            $out[] = $setting;
            if (! $placed && is_array($setting) && ($setting['id'] ?? '') === 'woocommerce_calc_discounts_sequentially') {
                $out[]  = $field;
                $placed = true;
            }
        }
        if (! $placed) {
            $out[] = $field;
        }

        return $out;
    }

    /**
     * The individual-use flag WooCommerce reads when it applies coupons (the view context only; the coupon screen
     * still shows and saves the coupon's own box).
     *
     * @param mixed $value
     * @param mixed $coupon
     */
    public static function individualUse($value, $coupon): bool
    {
        return self::allowed() ? (bool) $value : true;
    }
}

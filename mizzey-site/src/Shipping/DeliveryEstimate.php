<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

defined('ABSPATH') || exit;

/**
 * A delivery estimate shown to the customer where available (SHIP-04, P1-L). Staff write it on each shipping rate of
 * a zone (WooCommerce, Settings, Shipping), in English and in Arabic, for example "2 to 4 working days"; the rate the
 * customer sees carries the estimate of their language (the Store API's rate meta data), for the designed checkout
 * after PRE-03b. A rate with no estimate shows none.
 *
 * Register ids: SHIP-04. See specs/041-bosta.
 */
final class DeliveryEstimate
{
    public const META = 'mizzey_delivery_estimate';
    public const METHODS = ['flat_rate', 'free_shipping', 'local_pickup'];

    public static function register(): void
    {
        foreach (self::METHODS as $method) {
            add_filter("woocommerce_shipping_instance_form_fields_{$method}", [self::class, 'fields']);
        }
        add_filter('woocommerce_package_rates', [self::class, 'rates'], 20, 1);
        add_filter('woocommerce_cart_shipping_packages', [self::class, 'packages'], 20, 1);
    }

    /**
     * WooCommerce keeps calculated rates in the session by a hash of the package, which does not include the language:
     * the language is added, so a cart seen in English and then in Arabic gets its estimate in each.
     *
     * @param mixed $packages
     * @return mixed
     */
    public static function packages($packages)
    {
        if (! is_array($packages)) {
            return $packages;
        }
        $lang = self::language();
        foreach ($packages as $i => $package) {
            if (is_array($package)) {
                $packages[$i]['mizzey_language'] = $lang;
            }
        }

        return $packages;
    }

    private static function language(): string
    {
        return (string) apply_filters('wpml_current_language', null) === 'ar' ? 'ar' : 'en';
    }

    /**
     * @param mixed $fields
     * @return mixed
     */
    public static function fields($fields)
    {
        if (! is_array($fields)) {
            return $fields;
        }
        $fields['mizzey_estimate_en'] = ['title' => __('Delivery estimate (English)', 'mizzey-site'), 'type' => 'text', 'default' => '', 'desc_tip' => true, 'description' => __('Shown to the customer with this rate, for example: 2 to 4 working days.', 'mizzey-site')];
        $fields['mizzey_estimate_ar'] = ['title' => __('Delivery estimate (Arabic)', 'mizzey-site'), 'type' => 'text', 'default' => ''];

        return $fields;
    }

    /**
     * @param mixed $rates
     * @return mixed
     */
    public static function rates($rates)
    {
        if (! is_array($rates)) {
            return $rates;
        }
        $lang = self::language();
        foreach ($rates as $rate) {
            if (! $rate instanceof \WC_Shipping_Rate) {
                continue;
            }
            $settings = get_option('woocommerce_' . $rate->get_method_id() . '_' . $rate->get_instance_id() . '_settings', []);
            $estimate = is_array($settings) ? trim((string) ($settings['mizzey_estimate_' . $lang] ?? '')) : '';
            if ($estimate !== '') {
                $rate->add_meta_data(self::META, mb_substr(sanitize_text_field($estimate), 0, 80));
            }
        }

        return $rates;
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Checkout;

defined('ABSPATH') || exit;

/**
 * The checkout address in the Egyptian structure (CHK-01): governorate, city, district, street, landmark.
 *
 * WooCommerce has Egypt's governorates as its states and no address format for Egypt. Its country locale for Egypt is
 * set here (`woocommerce_get_country_locale`), which both checkouts and the account's address forms read: the
 * governorate (the state field) required, the city, the street and building (the first address line), the second line
 * and the postcode hidden, as Egyptian addresses do not use them. The district (required) and a landmark (optional)
 * are WooCommerce additional checkout fields in the address (`woocommerce_register_additional_checkout_field`), kept
 * with the order and the customer's address. They show on the block checkout, which the storefront uses; the designed
 * form is PRE-03b's.
 *
 * Register ids: CHK-01. See specs/019-checkout.
 */
final class EgyptianAddress
{
    public const DISTRICT = 'mizzey/district';
    public const LANDMARK = 'mizzey/landmark';

    public static function register(): void
    {
        add_filter('woocommerce_get_country_locale', [self::class, 'locale'], 10, 1);
        add_action('woocommerce_init', [self::class, 'fields']);
    }

    /**
     * @param mixed $locale
     * @return mixed
     */
    public static function locale($locale)
    {
        if (! is_array($locale)) {
            return $locale;
        }
        $locale['EG'] = array_replace_recursive((array) ($locale['EG'] ?? []), [
            'state'     => ['label' => __('Governorate', 'mizzey-site'), 'required' => true, 'hidden' => false],
            'city'      => ['label' => __('City', 'mizzey-site'), 'required' => true],
            'address_1' => ['label' => __('Street and building', 'mizzey-site'), 'required' => true],
            'address_2' => ['required' => false, 'hidden' => true],
            'postcode'  => ['required' => false, 'hidden' => true],
        ]);

        return $locale;
    }

    public static function fields(): void
    {
        if (! function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }
        woocommerce_register_additional_checkout_field([
            'id'         => self::DISTRICT,
            'label'      => __('District', 'mizzey-site'),
            'location'   => 'address',
            'type'       => 'text',
            'required'   => true,
            'attributes' => ['autocomplete' => 'address-level3'],
        ]);
        woocommerce_register_additional_checkout_field([
            'id'            => self::LANDMARK,
            'label'         => __('Nearest landmark', 'mizzey-site'),
            'optionalLabel' => __('Nearest landmark (optional)', 'mizzey-site'),
            'location'      => 'address',
            'type'          => 'text',
            'required'      => false,
        ]);
    }
}

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
 * are WooCommerce additional checkout fields in the address (`woocommerce_register_additional_checkout_field`), for an
 * Egyptian address only, kept with the order and the customer's address. They show on the block checkout, which the
 * storefront uses; the designed form is PRE-03b's.
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
        add_action('woocommerce_checkout_validate_order_before_payment', [self::class, 'beforePayment'], 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'payingOrder'], 10, 2);
    }

    /**
     * Paying an existing order through the Store API (`/checkout/{id}`): WooCommerce sets the address it is sent on the
     * order but not the address's additional fields, so the district and landmark sent with it are kept here, on the
     * order WooCommerce then saves and checks.
     *
     * @param mixed $order
     * @param mixed $request
     */
    public static function payingOrder($order, $request): void
    {
        if (! $order instanceof \WC_Order || ! $request instanceof \WP_REST_Request || preg_match('#^/wc/store(/v\d+)?/checkout/\d+$#', $request->get_route()) !== 1) {
            return;
        }
        $fields = \Automattic\WooCommerce\Blocks\Package::container()->get(\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class);
        foreach (['billing', 'shipping'] as $type) {
            $address = $request->get_param($type . '_address') ?? ($type === 'shipping' ? $request->get_param('billing_address') : null);
            foreach ([self::DISTRICT, self::LANDMARK] as $key) {
                if (is_array($address) && isset($address[$key]) && is_scalar($address[$key])) {
                    $fields->persist_field_for_order($key, sanitize_text_field((string) $address[$key]), $order, $type, false);
                }
            }
        }
    }

    /**
     * Before any Store API payment of an order, paying an existing order included: an Egyptian address has its
     * district.
     *
     * @param mixed $order
     * @param mixed $errors
     */
    public static function beforePayment($order, $errors): void
    {
        if (! $order instanceof \WC_Order || ! $errors instanceof \WP_Error) {
            return;
        }
        foreach (['billing', 'shipping'] as $type) {
            $country = $type === 'billing' ? $order->get_billing_country() : $order->get_shipping_country();
            if ($country === 'EG' && trim((string) $order->get_meta("_wc_{$type}/" . self::DISTRICT)) === '' && ($type === 'billing' || $order->needs_shipping_address())) {
                /* translators: %s: the field's name. */
                $errors->add('mizzey_district_missing', sprintf(__('%s is required.', 'mizzey-site'), __('District', 'mizzey-site')));

                return;
            }
        }
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
        // For an Egyptian address only: required and shown when the address being entered is in Egypt, hidden otherwise
        // (WooCommerce's conditional field rules, on the checkout document's `customer.address`).
        $egypt = ['customer' => ['properties' => ['address' => ['properties' => ['country' => ['const' => 'EG']]]]]];
        $other = ['customer' => ['properties' => ['address' => ['properties' => ['country' => ['not' => ['const' => 'EG']]]]]]];
        woocommerce_register_additional_checkout_field([
            'id'         => self::DISTRICT,
            'label'      => __('District', 'mizzey-site'),
            'location'   => 'address',
            'type'       => 'text',
            'required'   => $egypt,
            'hidden'     => $other,
            'attributes' => ['autocomplete' => 'address-level3'],
        ]);
        woocommerce_register_additional_checkout_field([
            'id'            => self::LANDMARK,
            'label'         => __('Nearest landmark', 'mizzey-site'),
            'optionalLabel' => __('Nearest landmark (optional)', 'mizzey-site'),
            'location'      => 'address',
            'type'          => 'text',
            'required'      => false,
            'hidden'        => $other,
        ]);
    }
}

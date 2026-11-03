<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Checkout;

use MizzeySite\Accounts\CustomerKey;
use MizzeySite\Emails\OrderLanguage;

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
    public const DISTRICT   = 'mizzey/district';
    public const LANDMARK   = 'mizzey/landmark';
    public const PLACE      = 'mizzey/place';
    public const BUILDING   = 'mizzey/building';
    public const FLOOR      = 'mizzey/floor';
    public const APARTMENT  = 'mizzey/apartment';
    public const APARTMENTS = 'apartment';
    public const VILLA      = 'villa';
    public const OFFICE     = 'office';
    /** The address's own fields, in the order a person reads an Egyptian address: largest place first. */
    public const FIELDS = [self::DISTRICT, self::PLACE, self::BUILDING, self::FLOOR, self::APARTMENT, self::LANDMARK];

    public static function register(): void
    {
        add_filter('woocommerce_get_country_locale', [self::class, 'locale'], 10, 1);
        add_action('woocommerce_init', [self::class, 'fields']);
        // One address: the order is delivered to the address the customer gives, as Egyptian stores ask for it.
        add_filter('pre_option_woocommerce_ship_to_destination', [self::class, 'oneAddress']);
        add_action('woocommerce_before_order_object_save', [self::class, 'composeOrder'], 10, 1);
        add_action('woocommerce_checkout_validate_order_before_payment', [self::class, 'beforePayment'], 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'payingOrder'], 10, 2);
        add_filter('woocommerce_validate_phone', [self::class, 'validPhone'], 10, 3);
    }

    /**
     * WooCommerce's phone check, which both checkouts and the account's address form run with the address's country:
     * for Egypt, an Egyptian mobile number, typed in any spelling (Arabic digits included); elsewhere, also a number in
     * Arabic digits.
     *
     * @param mixed $valid
     * @param mixed $phone
     * @param mixed $country
     */
    public static function validPhone($valid, $phone, $country = null): bool
    {
        $mobile = is_string($phone) && CustomerKey::phone($phone) !== null;
        if ($country === 'EG') {
            return $mobile;
        }

        return (bool) $valid || $mobile;
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
            foreach (self::FIELDS as $key) {
                if (is_array($address) && isset($address[$key]) && is_scalar($address[$key])) {
                    $fields->persist_field_for_order($key, sanitize_text_field((string) $address[$key]), $order, $type, false);
                }
            }
        }
    }

    /**
     * Before any Store API payment of an order, paying an existing order included: an Egyptian address has its
     * district, and a phone given with it is an Egyptian mobile number (kept on the order in its `01` form, CustomerKey).
     *
     * @param mixed $order
     * @param mixed $errors
     */
    public static function beforePayment($order, $errors): void
    {
        if (! $order instanceof \WC_Order || ! $errors instanceof \WP_Error) {
            return;
        }
        // A phone given with an Egyptian address is a mobile number the carrier can call (CHK-11), in any spelling.
        foreach (['billing', 'shipping'] as $type) {
            $country = $type === 'billing' ? $order->get_billing_country() : $order->get_shipping_country();
            $phone   = trim((string) ($type === 'billing' ? $order->get_billing_phone() : $order->get_shipping_phone()));
            if ($country === 'EG' && $phone !== '' && CustomerKey::phone($phone) === null) {
                $errors->add('mizzey_phone_invalid', __('Please enter a mobile number such as 01012345678.', 'mizzey-site'));
                break;
            }
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

    /** One address for the order: WooCommerce's setting that delivers to the billing address, with no second form. */
    public static function oneAddress(): string
    {
        return 'billing_only';
    }

    /**
     * The Egyptian address in reading order, largest place first: governorate, city, district, the kind of place, the
     * street or compound, building, floor, apartment, landmark. The core fields' order is set here; the address's own
     * fields carry theirs in `fields()`.
     *
     * @param mixed $locale
     * @return mixed
     */
    public static function locale($locale)
    {
        if (! is_array($locale)) {
            return $locale;
        }
        $locale['EG'] = array_replace_recursive((array) ($locale['EG'] ?? []), [
            'state'     => ['label' => __('Governorate', 'mizzey-site'), 'required' => true, 'hidden' => false, 'priority' => 41, 'index' => 41],
            'city'      => ['label' => __('City or area', 'mizzey-site'), 'required' => true, 'priority' => 42, 'index' => 42],
            'address_1' => ['label' => __('Street or compound', 'mizzey-site'), 'required' => true, 'priority' => 45, 'index' => 45],
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
        // For an Egyptian address only (WooCommerce's conditional field rules, on the checkout document's
        // `customer.address`): shown when the address is in Egypt, hidden otherwise. A floor and an apartment are asked
        // of a flat or an office, not of a villa.
        $egypt      = ['customer' => ['properties' => ['address' => ['properties' => ['country' => ['const' => 'EG']]]]]];
        $other      = ['customer' => ['properties' => ['address' => ['properties' => ['country' => ['not' => ['const' => 'EG']]]]]]];
        // A rule's top-level keys are the document's properties, so the condition on two address fields sits on the
        // address itself: in Egypt, and a flat or an office.
        $isStacked  = [
            'properties' => ['country' => ['const' => 'EG'], self::PLACE => ['enum' => [self::APARTMENTS, self::OFFICE]]],
            'required'   => [self::PLACE],
        ];
        $stacked    = ['customer' => ['properties' => ['address' => $isStacked]]];
        $notStacked = ['customer' => ['properties' => ['address' => ['not' => $isStacked]]]];
        woocommerce_register_additional_checkout_field([
            'id'         => self::DISTRICT,
            'label'      => __('District or neighbourhood', 'mizzey-site'),
            'location'   => 'address',
            'type'       => 'text',
            'required'   => $egypt,
            'hidden'     => $other,
            'index'      => 43,
            'attributes' => ['autocomplete' => 'address-level3'],
        ]);
        woocommerce_register_additional_checkout_field([
            'id'       => self::PLACE,
            'label'    => __('Type of place', 'mizzey-site'),
            'location' => 'address',
            'type'     => 'select',
            'options'  => [
                ['value' => self::APARTMENTS, 'label' => __('Apartment', 'mizzey-site')],
                ['value' => self::VILLA, 'label' => __('Villa or house', 'mizzey-site')],
                ['value' => self::OFFICE, 'label' => __('Office', 'mizzey-site')],
            ],
            'required' => $egypt,
            'hidden'   => $other,
            'index'    => 44,
        ]);
        woocommerce_register_additional_checkout_field([
            'id'                         => self::BUILDING,
            'label'                      => __('Building or villa number', 'mizzey-site'),
            'location'                   => 'address',
            'type'                       => 'text',
            'required'                   => $egypt,
            'hidden'                     => $other,
            'index'                      => 46,
            'show_in_order_confirmation' => false,
        ]);
        woocommerce_register_additional_checkout_field([
            'id'                         => self::FLOOR,
            'label'                      => __('Floor', 'mizzey-site'),
            'location'                   => 'address',
            'type'                       => 'text',
            'required'                   => $stacked,
            'hidden'                     => $notStacked,
            'index'                      => 47,
            'show_in_order_confirmation' => false,
        ]);
        woocommerce_register_additional_checkout_field([
            'id'                         => self::APARTMENT,
            'label'                      => __('Apartment or office number', 'mizzey-site'),
            'location'                   => 'address',
            'type'                       => 'text',
            'required'                   => $stacked,
            'hidden'                     => $notStacked,
            'index'                      => 48,
            'show_in_order_confirmation' => false,
        ]);
        woocommerce_register_additional_checkout_field([
            'id'            => self::LANDMARK,
            'label'         => __('Nearest landmark', 'mizzey-site'),
            'optionalLabel' => __('Nearest landmark (optional)', 'mizzey-site'),
            'location'      => 'address',
            'type'          => 'text',
            'required'      => false,
            'hidden'        => $other,
            'index'         => 49,
        ]);
    }

    /**
     * The building, floor and apartment as one line, in the order's language, kept as the address's second line, which
     * the order screens, the emails, the packing slip and the courier (Bosta sends it as `secondLine`) already show.
     * Written on every save, so a change made by staff on the order screen is carried too. An address without them (an
     * older order, another country) keeps its second line.
     *
     * @param mixed $order
     */
    public static function composeOrder($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }
        foreach (['billing', 'shipping'] as $type) {
            $country = $type === 'billing' ? $order->get_billing_country('edit') : $order->get_shipping_country('edit');
            if ($country !== 'EG') {
                continue;
            }
            $value = static fn (string $key): string => trim((string) $order->get_meta("_wc_{$type}/{$key}", true, 'edit'));
            $line  = OrderLanguage::during($order, static fn (): string => self::line($value(self::PLACE), $value(self::BUILDING), $value(self::FLOOR), $value(self::APARTMENT)));
            if ($line === '') {
                continue;
            }
            $current = $type === 'billing' ? $order->get_billing_address_2('edit') : $order->get_shipping_address_2('edit');
            if ($current !== $line) {
                $type === 'billing' ? $order->set_billing_address_2($line) : $order->set_shipping_address_2($line);
            }
        }
    }

    /** "Building 5, floor 4, apartment 18"; "Villa 12"; "Building 3, floor 2, office 7". Empty when nothing is given. */
    public static function line(string $place, string $building, string $floor, string $apartment): string
    {
        $parts = [];
        if ($building !== '') {
            /* translators: %s: the building's or villa's number. */
            $parts[] = sprintf($place === self::VILLA ? __('Villa %s', 'mizzey-site') : __('Building %s', 'mizzey-site'), $building);
        }
        if ($place !== self::VILLA) {
            if ($floor !== '') {
                /* translators: %s: the floor. */
                $parts[] = sprintf(__('floor %s', 'mizzey-site'), $floor);
            }
            if ($apartment !== '') {
                /* translators: %s: the apartment's or office's number. */
                $parts[] = sprintf($place === self::OFFICE ? __('office %s', 'mizzey-site') : __('apartment %s', 'mizzey-site'), $apartment);
            }
        }

        /* translators: separates the parts of an address line, as in "Building 5, floor 4". */
        return implode(_x(', ', 'address parts', 'mizzey-site'), $parts);
    }
}

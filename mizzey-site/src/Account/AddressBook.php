<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Account;

use MizzeySite\Accounts\PhoneSignIn;
use MizzeySite\Checkout\EgyptianAddress;

defined('ABSPATH') || exit;

/**
 * The customer's address book (ACCT-04): add, edit, delete, set default.
 *
 * WooCommerce keeps one billing and one shipping address on the account. The book keeps up to ten addresses on the
 * account (`_mizzey_addresses`), each in the Egyptian structure of specs/019 and specs/050 (name, mobile, governorate, city,
 * district, the kind of place, street or compound, building, floor, apartment, landmark). Setting one as the default copies it into WooCommerce's own billing and shipping
 * address, which the checkout prefills (CHK-02); its mobile number goes to the shipping phone only, as the billing phone
 * is the number the customer signs in with (specs/017). Deleting the default leaves WooCommerce's copy as it is. The REST
 * routes act on the signed-in customer's own book only (WordPress's REST cookie sign-in, with its nonce); the designed
 * screen is PRE-03b's.
 *
 * - `GET    mizzey/v1/account/addresses`            the book
 * - `POST   mizzey/v1/account/addresses`            add one
 * - `PUT    mizzey/v1/account/addresses/{id}`       edit one
 * - `DELETE mizzey/v1/account/addresses/{id}`       delete one
 * - `POST   mizzey/v1/account/addresses/{id}/default` make it the default
 *
 * Register ids: ACCT-04. See specs/022-my-account.
 */
final class AddressBook
{
    public const META     = '_mizzey_addresses';
    public const CAPACITY = 10;
    public const FIELDS   = ['first_name', 'last_name', 'phone', 'state', 'city', 'district', 'place', 'address_1', 'building', 'floor', 'apartment', 'landmark'];
    private const REQUIRED = ['first_name', 'last_name', 'phone', 'state', 'city', 'district', 'place', 'address_1', 'building'];
    /** Asked of a flat or an office, not of a villa. */
    private const STACKED = ['floor', 'apartment'];
    /** The longest each part is kept. */
    public const LENGTHS = ['first_name' => 60, 'last_name' => 60, 'phone' => 32, 'state' => 10, 'city' => 80, 'district' => 80, 'place' => 10, 'address_1' => 200, 'building' => 40, 'floor' => 20, 'apartment' => 20, 'landmark' => 120];
    /** The book's part and the checkout's field it is kept in. */
    private const CHECKOUT = ['district' => EgyptianAddress::DISTRICT, 'place' => EgyptianAddress::PLACE, 'building' => EgyptianAddress::BUILDING, 'floor' => EgyptianAddress::FLOOR, 'apartment' => EgyptianAddress::APARTMENT, 'landmark' => EgyptianAddress::LANDMARK];

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        $own = static fn (): bool => is_user_logged_in();
        $id  = ['id' => ['type' => 'string', 'required' => true, 'pattern' => '^[a-z0-9]{12}$']];
        register_rest_route('mizzey/v1', '/account/addresses', [
            ['methods' => 'GET', 'callback' => [self::class, 'listRoute'], 'permission_callback' => $own],
            ['methods' => 'POST', 'callback' => [self::class, 'addRoute'], 'permission_callback' => $own],
        ]);
        register_rest_route('mizzey/v1', '/account/addresses/(?P<id>[a-z0-9]{12})', [
            ['methods' => 'PUT', 'callback' => [self::class, 'editRoute'], 'permission_callback' => $own, 'args' => $id],
            ['methods' => 'DELETE', 'callback' => [self::class, 'deleteRoute'], 'permission_callback' => $own, 'args' => $id],
        ]);
        register_rest_route('mizzey/v1', '/account/addresses/(?P<id>[a-z0-9]{12})/default', [
            ['methods' => 'POST', 'callback' => [self::class, 'defaultRoute'], 'permission_callback' => $own, 'args' => $id],
        ]);
    }

    /**
     * Runs a change of the customer's book under a MySQL named lock, so two requests at once cannot overwrite each
     * other's change; the book is read afresh inside it.
     *
     * @template T
     * @param callable(): T $work
     * @return T|\WP_Error
     */
    private static function locked(int $userId, callable $work)
    {
        global $wpdb;
        $lock = substr('mz_' . $wpdb->prefix . 'address_book_' . $userId, 0, 64);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            return new \WP_Error('mizzey_address_busy', __('Please try again in a moment.', 'mizzey-site'), ['status' => 409]);
        }
        try {
            wp_cache_delete($userId, 'user_meta');

            return $work();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }
    }

    /** @return list<array<string,mixed>> */
    public static function all(int $userId): array
    {
        $book = get_user_meta($userId, self::META, true);

        return is_array($book) ? array_values($book) : [];
    }

    /**
     * Adds an address; its id, or the reason it was refused.
     *
     * @param array<string,mixed> $input
     */
    public static function add(int $userId, array $input): string|\WP_Error
    {
        return self::locked($userId, static fn () => self::addUnlocked($userId, $input));
    }

    /** @param array<string,mixed> $input */
    private static function addUnlocked(int $userId, array $input): string|\WP_Error
    {
        $book = self::all($userId);
        if (count($book) >= self::CAPACITY) {
            /* translators: %d: the most addresses a book holds. */
            return new \WP_Error('mizzey_address_full', sprintf(__('Your address book holds up to %d addresses.', 'mizzey-site'), self::CAPACITY), ['status' => 409]);
        }
        $address = self::clean($input);
        if ($address instanceof \WP_Error) {
            return $address;
        }
        $address['id']      = strtolower(wp_generate_password(12, false, false));
        $address['default'] = $book === [];
        $book[]             = $address;
        self::store($userId, $book);
        if ($address['default']) {
            self::copyToWooCommerce($userId, $address);
        }

        return $address['id'];
    }

    /** @param array<string,mixed> $input */
    public static function edit(int $userId, string $id, array $input): true|\WP_Error
    {
        return self::locked($userId, static fn () => self::editUnlocked($userId, $id, $input));
    }

    /** @param array<string,mixed> $input */
    private static function editUnlocked(int $userId, string $id, array $input): true|\WP_Error
    {
        $book  = self::all($userId);
        $index = self::find($book, $id);
        if ($index === null) {
            return self::missing();
        }
        $address = self::clean($input);
        if ($address instanceof \WP_Error) {
            return $address;
        }
        $book[$index] = array_merge($address, ['id' => $id, 'default' => (bool) $book[$index]['default']]);
        self::store($userId, $book);
        if ($book[$index]['default']) {
            self::copyToWooCommerce($userId, $book[$index]);
        }

        return true;
    }

    public static function delete(int $userId, string $id): true|\WP_Error
    {
        return self::locked($userId, static function () use ($userId, $id) {
            $book  = self::all($userId);
            $index = self::find($book, $id);
            if ($index === null) {
                return self::missing();
            }
            $wasDefault = (bool) $book[$index]['default'];
            array_splice($book, $index, 1);
            // One default while the book holds an address: the first left takes over, and the checkout's copy with it.
            if ($wasDefault && $book !== []) {
                $book[0]['default'] = true;
                self::copyToWooCommerce($userId, $book[0]);
            }
            self::store($userId, $book);

            return true;
        });
    }

    public static function makeDefault(int $userId, string $id): true|\WP_Error
    {
        return self::locked($userId, static fn () => self::makeDefaultUnlocked($userId, $id));
    }

    private static function makeDefaultUnlocked(int $userId, string $id): true|\WP_Error
    {
        $book  = self::all($userId);
        $index = self::find($book, $id);
        if ($index === null) {
            return self::missing();
        }
        foreach ($book as $i => $address) {
            $book[$i]['default'] = $i === $index;
        }
        self::store($userId, $book);
        self::copyToWooCommerce($userId, $book[$index]);

        return true;
    }

    public static function listRoute(): \WP_REST_Response
    {
        return new \WP_REST_Response(['addresses' => self::all(get_current_user_id())], 200);
    }

    public static function addRoute(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $id = self::add(get_current_user_id(), (array) $request->get_json_params() + (array) $request->get_body_params());

        return $id instanceof \WP_Error ? $id : new \WP_REST_Response(['id' => $id, 'addresses' => self::all(get_current_user_id())], 201);
    }

    public static function editRoute(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $done = self::edit(get_current_user_id(), (string) $request['id'], (array) $request->get_json_params() + (array) $request->get_body_params());

        return $done instanceof \WP_Error ? $done : new \WP_REST_Response(['addresses' => self::all(get_current_user_id())], 200);
    }

    public static function deleteRoute(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $done = self::delete(get_current_user_id(), (string) $request['id']);

        return $done instanceof \WP_Error ? $done : new \WP_REST_Response(['addresses' => self::all(get_current_user_id())], 200);
    }

    public static function defaultRoute(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $done = self::makeDefault(get_current_user_id(), (string) $request['id']);

        return $done instanceof \WP_Error ? $done : new \WP_REST_Response(['addresses' => self::all(get_current_user_id())], 200);
    }

    /**
     * An address with only its known fields, trimmed, the mobile number in its Egyptian form and the governorate one of
     * Egypt's; or what is wrong with it.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>|\WP_Error
     */
    private static function clean(array $input): array|\WP_Error
    {
        $address = [];
        foreach (self::FIELDS as $field) {
            // Text only, each part cut to its length (a list or an object is an empty part).
            $value           = isset($input[$field]) && is_scalar($input[$field]) ? sanitize_text_field((string) $input[$field]) : '';
            $address[$field] = mb_substr($value, 0, self::LENGTHS[$field] ?? 80);
        }
        if (! in_array($address['place'], [EgyptianAddress::APARTMENTS, EgyptianAddress::VILLA, EgyptianAddress::OFFICE], true)) {
            $address['place'] = '';
        }
        $required = $address['place'] === EgyptianAddress::VILLA ? self::REQUIRED : array_merge(self::REQUIRED, self::STACKED);
        $address['floor'] = EgyptianAddress::floor($address['floor']);
        if ($address['place'] === EgyptianAddress::VILLA) {
            $address['floor']     = '';
            $address['apartment'] = '';
        }
        foreach ($required as $field) {
            if ($address[$field] === '') {
                return new \WP_Error('mizzey_address_incomplete', __('Please fill in every part of the address; only the landmark is optional.', 'mizzey-site'), ['status' => 400, 'field' => $field]);
            }
        }
        $phone = PhoneSignIn::normalise($address['phone']);
        if ($phone === null) {
            return new \WP_Error('mizzey_address_phone', __('Please enter a mobile number such as 01012345678.', 'mizzey-site'), ['status' => 400, 'field' => 'phone']);
        }
        $address['phone'] = $phone;
        if (! array_key_exists($address['state'], (array) WC()->countries->get_states('EG'))) {
            return new \WP_Error('mizzey_address_governorate', __('Please choose a governorate.', 'mizzey-site'), ['status' => 400, 'field' => 'state']);
        }
        $address['country'] = 'EG';

        return $address;
    }

    /** @param array<string,mixed> $address */
    private static function copyToWooCommerce(int $userId, array $address): void
    {
        $customer = new \WC_Customer($userId);
        foreach (['billing', 'shipping'] as $type) {
            $fields = $type === 'shipping' ? ['first_name', 'last_name', 'phone', 'state', 'city', 'address_1', 'country'] : ['first_name', 'last_name', 'state', 'city', 'address_1', 'country'];
            foreach ($fields as $field) {
                $setter = "set_{$type}_{$field}";
                if (method_exists($customer, $setter)) {
                    $customer->{$setter}($address[$field] ?? '');
                }
            }
            foreach (self::CHECKOUT as $part => $key) {
                $customer->update_meta_data("_wc_{$type}/{$key}", (string) ($address[$part] ?? ''));
            }
        }
        // The building, floor and apartment as the second line, as an order carries them (specs/050).
        $line = EgyptianAddress::line((string) ($address['place'] ?? ''), (string) ($address['building'] ?? ''), (string) ($address['floor'] ?? ''), (string) ($address['apartment'] ?? ''));
        $customer->set_billing_address_2($line);
        $customer->set_shipping_address_2($line);
        $customer->save();
    }

    /** @param list<array<string,mixed>> $book */
    private static function find(array $book, string $id): ?int
    {
        foreach ($book as $i => $address) {
            if (($address['id'] ?? '') === $id) {
                return $i;
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $book */
    private static function store(int $userId, array $book): void
    {
        update_user_meta($userId, self::META, array_values($book));
    }

    private static function missing(): \WP_Error
    {
        return new \WP_Error('mizzey_address_missing', __('This address is not in your address book.', 'mizzey-site'), ['status' => 404]);
    }
}

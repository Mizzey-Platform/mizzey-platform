<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Cart;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\PromotionRules;
use MizzeySite\Promotions\PromotionSettings;

defined('ABSPATH') || exit;

/**
 * What the cart still needs for free shipping, for the cart's progress prompt (CART-07: "add one more item to
 * qualify"). The prompt itself is the designed cart's (PRE-03b); this is its data.
 *
 * Two things make shipping free at launch: an automatic free-shipping promotion with an item minimum (BR-002, two
 * units by the recorded example, specs/016) and a free shipping method's minimum amount. For each, the remaining need
 * is measured as WooCommerce measures it: units as the promotion counts them (`PromotionRules::units()`), only for a
 * promotion the cart would otherwise qualify for (its item minimum is the one condition left); the amount as WooCommerce's
 * free shipping method compares it (shown subtotal, less the discount unless the method ignores discounts). The data is
 * on the Store API cart as `extensions.mizzey.free_shipping`.
 *
 * Register ids: CART-07. See specs/018-cart.
 */
final class FreeShippingProgress
{
    public static function register(): void
    {
        add_action('woocommerce_blocks_loaded', [self::class, 'extend']);
    }

    public static function extend(): void
    {
        if (! function_exists('woocommerce_store_api_register_endpoint_data')) {
            return;
        }
        woocommerce_store_api_register_endpoint_data([
            'endpoint'        => CartSchema::IDENTIFIER,
            'namespace'       => 'mizzey',
            'data_callback'   => static fn (): array => ['free_shipping' => self::of(WC()->cart), 'wishlist_nonce' => \MizzeySite\Wishlist\WishlistRoutes::nonce()],
            'schema_callback' => static fn (): array => [
                'free_shipping' => [
                    'description' => 'What the cart still needs for free shipping.',
                    'type'        => 'object',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                    'properties'  => [
                        'free'          => ['type' => 'boolean'],
                        'items_needed'  => ['type' => ['integer', 'null']],
                        'amount_needed' => ['type' => ['string', 'null']],
                    ],
                ],
                'wishlist_nonce' => [
                    'description' => 'The nonce for moving a cart line to the wishlist (X-Mizzey-Nonce).',
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
            ],
            'schema_type'     => ARRAY_A,
        ]);
    }

    /**
     * @return array{free:bool,items_needed:?int,amount_needed:?string}
     */
    public static function of(?\WC_Cart $cart): array
    {
        $none = ['free' => false, 'items_needed' => null, 'amount_needed' => null];
        if (! $cart instanceof \WC_Cart || $cart->is_empty() || ! $cart->needs_shipping()) {
            return $none;
        }
        if (self::free()) {
            return ['free' => true, 'items_needed' => null, 'amount_needed' => null];
        }
        $amount = self::amountNeeded($cart);

        return [
            'free'          => false,
            'items_needed'  => self::itemsNeeded($cart),
            'amount_needed' => $amount !== null ? wc_format_decimal($amount, wc_get_price_decimals()) : null,
        ];
    }

    /** Whether what the customer is charged for shipping is nothing: the chosen rate of every package costs nothing. */
    private static function free(): bool
    {
        $packages = WC()->shipping()->get_packages();
        $chosen   = (array) (WC()->session ? WC()->session->get('chosen_shipping_methods', []) : []);
        foreach ($packages as $index => $package) {
            $rate = ((array) ($package['rates'] ?? []))[$chosen[$index] ?? ''] ?? null;
            if (! $rate instanceof \WC_Shipping_Rate || (float) $rate->get_cost() + array_sum(array_map('floatval', (array) $rate->get_taxes())) > 0) {
                return false;
            }
        }

        return $packages !== [];
    }

    /**
     * Whether a free-shipping promotion would make shipping free here: every package's zone has a free shipping method
     * that a coupon satisfies (requires a coupon, or a coupon or the minimum, or both with the minimum met).
     */
    private static function couponsShipFree(\WC_Cart $cart): bool
    {
        $packages = WC()->shipping()->get_packages();
        foreach ($packages as $package) {
            $takes = false;
            foreach (\WC_Shipping_Zones::get_zone_matching_package($package)->get_shipping_methods(true) as $method) {
                $requires = $method->id === 'free_shipping' ? (string) $method->get_option('requires') : '';
                $takes    = $takes || in_array($requires, ['coupon', 'either'], true)
                    || ($requires === 'both' && round(self::compared($cart, $method), wc_get_price_decimals()) >= (float) $method->get_option('min_amount'));
            }
            if (! $takes) {
                return false;
            }
        }

        return $packages !== [];
    }

    /** The amount a free shipping method compares with its minimum, as WC_Shipping_Free_Shipping::is_available() does (11.1.0). */
    private static function compared(\WC_Cart $cart, \WC_Shipping_Method $method): float
    {
        $compared = (float) $cart->get_displayed_subtotal();
        if ($method->get_option('ignore_discounts') !== 'yes') {
            $compared -= (float) $cart->get_discount_total() + ($cart->display_prices_including_tax() ? (float) $cart->get_discount_tax() : 0.0);
        }

        return $compared;
    }

    /** The fewest more units an automatic free-shipping promotion needs, or null. */
    private static function itemsNeeded(\WC_Cart $cart): ?int
    {
        if (! self::couponsShipFree($cart)) {
            return null;
        }
        // An automatic promotion does not stand beside a code the customer entered when promotions do not combine, nor
        // beside an entered individual-use code; an individual-use promotion stands beside no entered code
        // (AutomaticPromotions::winners(), specs/016).
        $automatic = AutomaticPromotions::automaticCodes();
        $entered   = array_values(array_diff(array_map('wc_format_coupon_code', $cart->get_applied_coupons()), $automatic));
        $blocked   = $entered !== [] && (! \MizzeySite\Promotions\Combination::allowed()
            || array_filter($entered, static fn (string $code): bool => (new \WC_Coupon($code))->get_individual_use()) !== []);
        // With promotions not combining, one automatic promotion applies, the one worth most: while the store runs
        // another automatic promotion, it may be the one chosen at the target instead, so no item target is promised.
        $others = array_filter(AutomaticPromotions::codes(), static fn (string $code): bool => ! (new \WC_Coupon($code))->get_free_shipping());
        if ($blocked || (! \MizzeySite\Promotions\Combination::allowed() && $others !== [])) {
            return null;
        }
        $needed = null;
        foreach (AutomaticPromotions::codes() as $code) {
            $coupon  = new \WC_Coupon($code);
            $minimum = PromotionSettings::minItems($coupon);
            if (! $coupon->get_free_shipping() || $minimum <= 0 || ($entered !== [] && $coupon->get_individual_use())) {
                continue;
            }
            // The promotion as it would stand with its item minimum met: valid then, the units are what is missing.
            $probe = new \WC_Coupon($code);
            $probe->update_meta_data(PromotionSettings::MIN_ITEMS, '0');
            $discounts = new \WC_Discounts($cart);
            if ($discounts->is_coupon_valid($probe) !== true) {
                continue;
            }
            $short = $minimum - PromotionRules::units($coupon, $discounts);
            if ($short > 0 && ($needed === null || $short < $needed)) {
                $needed = $short;
            }
        }

        return $needed;
    }

    /** The least more amount a free shipping method's minimum needs, or null. */
    private static function amountNeeded(\WC_Cart $cart): ?float
    {
        $needed = null;
        foreach (WC()->shipping()->get_packages() as $package) {
            foreach (\WC_Shipping_Zones::get_zone_matching_package($package)->get_shipping_methods(true) as $method) {
                if ($method->id !== 'free_shipping' || ! in_array($method->get_option('requires'), ['min_amount', 'either'], true)) {
                    continue;
                }
                $short = (float) $method->get_option('min_amount') - round(self::compared($cart, $method), wc_get_price_decimals());
                if ($short > 0 && ($needed === null || $short < $needed)) {
                    $needed = $short;
                }
            }
        }

        return $needed;
    }
}

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
            'data_callback'   => static fn (): array => ['free_shipping' => self::of(WC()->cart)],
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

    /** Whether every package already has a free rate. */
    private static function free(): bool
    {
        $packages = WC()->shipping()->get_packages();
        foreach ($packages as $package) {
            $rates = (array) ($package['rates'] ?? []);
            $free  = array_filter($rates, static fn ($rate): bool => $rate instanceof \WC_Shipping_Rate && ($rate->get_method_id() === 'free_shipping' || (float) $rate->get_cost() <= 0));
            if ($free === []) {
                return false;
            }
        }

        return $packages !== [];
    }

    /** The fewest more units an automatic free-shipping promotion needs, or null. */
    private static function itemsNeeded(\WC_Cart $cart): ?int
    {
        $needed = null;
        foreach (AutomaticPromotions::codes() as $code) {
            $coupon  = new \WC_Coupon($code);
            $minimum = PromotionSettings::minItems($coupon);
            if (! $coupon->get_free_shipping() || $minimum <= 0) {
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
                // As WC_Shipping_Free_Shipping::is_available() compares it (11.1.0): the discount, with its tax when
                // prices are shown with tax, comes off unless the method ignores discounts.
                $compared = (float) $cart->get_displayed_subtotal();
                if ($method->get_option('ignore_discounts') !== 'yes') {
                    $compared -= (float) $cart->get_discount_total() + ($cart->display_prices_including_tax() ? (float) $cart->get_discount_tax() : 0.0);
                }
                $short = (float) $method->get_option('min_amount') - round($compared, wc_get_price_decimals());
                if ($short > 0 && ($needed === null || $short < $needed)) {
                    $needed = $short;
                }
            }
        }

        return $needed;
    }
}

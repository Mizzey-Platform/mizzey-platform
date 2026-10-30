<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * The maximum discount (PROMO-19), applied by lowering the promotion's effective amount for one calculation, so that
 * WooCommerce's own allocation, rounding and leftover cents land exactly on the cap.
 *
 * WooCommerce starts each promotion's calculation, for a cart and when an order's coupons are recalculated alike, by
 * asking which items it applies to (`woocommerce_coupon_get_items_to_apply`). For a capped percentage promotion this
 * measures the amount WooCommerce is about to discount (the same price it will use: sequential discounts and the item
 * limit included) and, when the percentage would exceed the cap, sets the percentage that gives exactly the cap. For a
 * capped fixed cart promotion the amount becomes the cap. The next read of the amount in that calculation
 * (`woocommerce_coupon_get_amount`) receives it, once. A cap is not offered for a fixed per-product discount, whose
 * allocation could not hold it exactly; the coupon screen refuses that combination.
 *
 * Register id: PROMO-19. See specs/011-promotion-engine, FR-006.
 */
final class DiscountCap
{
    /** @var array<int,float> The effective amount for the next amount read, by coupon object. */
    private static array $effective = [];

    public static function register(): void
    {
        add_filter('woocommerce_coupon_get_items_to_apply', [self::class, 'start'], 999, 3);
        add_filter('woocommerce_coupon_get_amount', [self::class, 'amount'], 999, 2);
    }

    /**
     * @param mixed $items
     * @param mixed $discounts
     * @return mixed
     */
    public static function start($items, \WC_Coupon $coupon, $discounts)
    {
        unset(self::$effective[spl_object_id($coupon)]);
        $cap = PromotionSettings::maxDiscount($coupon);
        if ($cap === null || ! is_array($items) || ! $discounts instanceof \WC_Discounts) {
            return $items;
        }
        $amount   = (float) $coupon->get_amount('edit');
        $capCents = wc_add_number_precision($cap);
        if ($coupon->is_type('fixed_cart')) {
            if (wc_add_number_precision($amount) > $capCents) {
                self::$effective[spl_object_id($coupon)] = $cap;
            }

            return $items;
        }
        if (! $coupon->is_type('percent')) {
            return $items;
        }
        $base = self::percentBase($items, $coupon, $discounts);
        if ($base > 0 && $base * $amount / 100 > $capCents) {
            self::$effective[spl_object_id($coupon)] = $capCents / $base * 100;
        }

        return $items;
    }

    /**
     * @param mixed $value
     * @param mixed $coupon
     * @return mixed
     */
    public static function amount($value, $coupon)
    {
        if (! $coupon instanceof \WC_Coupon || ! isset(self::$effective[spl_object_id($coupon)])) {
            return $value;
        }
        $effective = self::$effective[spl_object_id($coupon)];
        unset(self::$effective[spl_object_id($coupon)]);

        return (string) $effective;
    }

    /**
     * The price, in cents, that WooCommerce's percentage calculation discounts: the same per-item price and quantity
     * it uses (`WC_Discounts::apply_coupon_percent()` in WooCommerce 11.1.0).
     *
     * @param array<int|string,object> $items
     */
    public static function percentBase(array $items, \WC_Coupon $coupon, \WC_Discounts $discounts): float
    {
        $limit      = (int) ($coupon->get_limit_usage_to_x_items() ?? 0);
        $applied    = 0;
        $sequential = get_option('woocommerce_calc_discounts_sequentially', 'no') === 'yes';
        $base       = 0.0;
        foreach ($items as $item) {
            if (empty($item->quantity)) {
                continue;
            }
            $price    = $sequential ? (float) $discounts->get_discounted_price_in_cents($item) : (float) round((float) $item->price);
            $quantity = $limit && ($limit - $applied) < $item->quantity ? $limit - $applied : $item->quantity;
            $quantity = max(0, (int) apply_filters('woocommerce_coupon_get_apply_quantity', $quantity, $item, $coupon, $discounts));
            $base    += ($price / $item->quantity) * $quantity;
            $applied += $quantity;
        }

        return $base;
    }
}

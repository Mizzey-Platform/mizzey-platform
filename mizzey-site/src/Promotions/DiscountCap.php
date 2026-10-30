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
 * simulates WooCommerce's own calculation on those items (its prices, what is left of each line after earlier
 * promotions, and its leftover cents); only when the result would exceed the cap does it set the largest percentage whose
 * result stays within it. For a capped fixed cart promotion the amount becomes the cap. The next read of the amount in that calculation
 * (`woocommerce_coupon_get_amount`) receives it, once. A cap is not offered for a fixed per-product discount, whose
 * allocation could not hold it exactly; the coupon screen refuses that combination.
 *
 * Register id: PROMO-19. See specs/011-promotion-engine, FR-006.
 */
final class DiscountCap
{
    /** @var array<int,float|string> The effective amount for the next amount read, by coupon object. */
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
        $lines = self::percentLines($items, $coupon, $discounts);
        if (self::simulate($lines, $amount) <= $capCents) {
            return $items;
        }
        // The largest percentage whose result stays within the cap; the result never decreases as the percentage grows.
        $low  = 0.0;
        $high = $amount;
        for ($step = 0; $step < 60; $step++) {
            $middle = ($low + $high) / 2;
            if (self::simulate($lines, $middle) <= $capCents) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }
        // WooCommerce reads the amount as text; eight decimals, rounded down and simulated as written, keep the two equal.
        $written = sprintf('%.8F', floor($low * 1e8) / 1e8);
        while ((float) $written > 0 && self::simulate($lines, (float) $written) > $capCents) {
            $written = sprintf('%.8F', (float) $written - 1e-8);
        }
        self::$effective[spl_object_id($coupon)] = $written;

        return $items;
    }

    /**
     * What WooCommerce 11.1.0's percentage calculation (`WC_Discounts::apply_coupon_percent()` and
     * `apply_coupon_remainder()`) gives at a percentage, in cents: each line's discount on its price, clipped to what is
     * left of it, then the leftover cents up to the rounded total, one per unit while the unit has room.
     *
     * @param list<array{price:float,left:float,units:int}> $lines
     */
    public static function simulate(array $lines, float $percent): float
    {
        $total = 0.0;
        $base  = 0.0;
        $room  = 0.0;
        foreach ($lines as $line) {
            $discount = wc_round_discount(min($line['left'], floor($line['price'] * ($percent / 100))), 0);
            $total   += $discount;
            $base    += $line['price'];
            $room    += min($line['units'], max(0, $line['left'] - $discount));
        }
        $target = wc_round_discount($base * ($percent / 100), 0);

        return $total + ($total < $target ? min($target - $total, $room) : 0);
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
     * The lines WooCommerce's percentage calculation works on: the price it discounts (sequential discounts and the
     * item limit included), what is left of each line, and its units.
     *
     * @param array<int|string,object> $items
     * @return list<array{price:float,left:float,units:int}>
     */
    public static function percentLines(array $items, \WC_Coupon $coupon, \WC_Discounts $discounts): array
    {
        $limit      = (int) ($coupon->get_limit_usage_to_x_items() ?? 0);
        $applied    = 0;
        $sequential = get_option('woocommerce_calc_discounts_sequentially', 'no') === 'yes';
        $lines      = [];
        foreach ($items as $item) {
            if (empty($item->quantity)) {
                continue;
            }
            $left     = (float) $discounts->get_discounted_price_in_cents($item);
            $price    = $sequential ? $left : (float) round((float) $item->price);
            $quantity = $limit && ($limit - $applied) < $item->quantity ? $limit - $applied : $item->quantity;
            $quantity = max(0, (int) apply_filters('woocommerce_coupon_get_apply_quantity', $quantity, $item, $coupon, $discounts));
            $lines[]  = ['price' => ($price / $item->quantity) * $quantity, 'left' => $left, 'units' => (int) ceil((float) $item->quantity)];
            $applied += $quantity;
        }

        return $lines;
    }
}

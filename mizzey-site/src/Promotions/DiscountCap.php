<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * The maximum discount (PROMO-19), applied inside WooCommerce's own cart discount calculation.
 *
 * WooCommerce starts each promotion's calculation by asking which items it applies to
 * (`woocommerce_coupon_get_items_to_apply`); that starts a running total for the promotion. Each item's discount then
 * passes `woocommerce_coupon_get_discount_amount` (percentage and per-product promotions, on a cart), which gives the
 * item at most what remains of the cap. A changed item discount switches off WooCommerce's leftover-cents top-up, so
 * the total stays within the cap. A fixed cart discount is its own cap. Amounts are kept in cents, as WooCommerce does.
 *
 * Register id: PROMO-19. See specs/011-promotion-engine, FR-006.
 */
final class DiscountCap
{
    /** @var array<string,int> Cents given so far by each capped promotion in the calculation running now. */
    private static array $given = [];

    public static function register(): void
    {
        add_filter('woocommerce_coupon_get_items_to_apply', [self::class, 'start'], 10, 2);
        add_filter('woocommerce_coupon_get_discount_amount', [self::class, 'cap'], 999, 5);
    }

    /**
     * @param mixed $items
     * @return mixed
     */
    public static function start($items, \WC_Coupon $coupon)
    {
        if (PromotionSettings::maxDiscount($coupon) !== null) {
            self::$given[$coupon->get_code()] = 0;
        }

        return $items;
    }

    /**
     * @param mixed $discount           The item's discount, in currency units.
     * @param mixed $discountingAmount
     * @param mixed $cartItem
     * @param mixed $single
     * @return mixed
     */
    public static function cap($discount, $discountingAmount, $cartItem, $single, $coupon)
    {
        if (! $coupon instanceof \WC_Coupon) {
            return $discount;
        }
        $max = PromotionSettings::maxDiscount($coupon);
        if ($max === null) {
            return $discount;
        }
        $code      = $coupon->get_code();
        $given     = self::$given[$code] ?? 0;
        $remaining = max(0, (int) round(wc_add_number_precision($max)) - $given);
        $cents     = (int) round(wc_add_number_precision((float) $discount));
        $allowed   = min($cents, $remaining);
        self::$given[$code] = $given + $allowed;

        return wc_remove_number_precision($allowed);
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * The promotion conditions WooCommerce's coupons lack, checked where WooCommerce checks every coupon
 * (`woocommerce_coupon_is_valid`). A failed condition throws, as WooCommerce's own Brands check does, so the customer
 * sees the reason in their language. The Store API updates the customer from the checkout request before it validates
 * the cart, so a guest's billing email is known when "first order only" is checked at checkout.
 *
 * Register ids: PROMO-05, PROMO-09, PROMO-15. See specs/011-promotion-engine, FR-002, FR-003, FR-005, FR-008.
 */
final class PromotionRules
{
    /** Orders that do not make a customer a returning one. */
    private const NOT_AN_ORDER = ['wc-cancelled', 'wc-failed', 'wc-pending', 'wc-checkout-draft'];

    public static function register(): void
    {
        add_filter('woocommerce_coupon_is_valid', [self::class, 'valid'], 10, 3);
    }

    /**
     * @param mixed $valid
     * @param mixed $discounts
     * @throws \Exception When a condition fails; WooCommerce turns it into the customer's error message.
     */
    public static function valid($valid, \WC_Coupon $coupon, $discounts = null): bool
    {
        if (! $valid) {
            return false;
        }
        if (PromotionSettings::paused($coupon)) {
            throw new \Exception(esc_html__('This coupon is paused.', 'mizzey-site'), 113);
        }
        $starts = PromotionSettings::starts($coupon);
        if ($starts !== null && time() < $starts) {
            throw new \Exception(esc_html__('This coupon is not active yet.', 'mizzey-site'), 109);
        }
        if (! $discounts instanceof \WC_Discounts) {
            return true;
        }
        $object = $discounts->get_object();
        $minItems = PromotionSettings::minItems($coupon);
        if ($minItems > 0 && self::units($coupon, $discounts) < $minItems) {
            throw new \Exception(esc_html(sprintf(
                /* translators: %d: the minimum number of items. */
                _n('This coupon needs at least %d item in your cart.', 'This coupon needs at least %d items in your cart.', $minItems, 'mizzey-site'),
                $minItems
            )), 111);
        }
        if (PromotionSettings::firstOrder($coupon) && self::hasEarlierOrder($object)) {
            throw new \Exception(esc_html__('This coupon is for a first order only.', 'mizzey-site'), 112);
        }

        return true;
    }

    /** Units in the cart or order that the promotion applies to; every unit for a cart-wide promotion. */
    public static function units(\WC_Coupon $coupon, \WC_Discounts $discounts): int
    {
        $productTypes = $coupon->is_type(wc_get_product_coupon_types());
        $units        = 0;
        foreach ($discounts->get_items_to_validate() as $item) {
            if (! $productTypes || ($item->product && $coupon->is_valid_for_product($item->product, $item->object))) {
                $units += (int) $item->quantity;
            }
        }

        return $units;
    }

    /**
     * Whether the customer already has an order, by account and by billing email, ignoring the order being placed.
     *
     * @param mixed $object The cart or order the coupon is checked against.
     */
    public static function hasEarlierOrder($object): bool
    {
        $exclude = [];
        if ($object instanceof \WC_Order) {
            $userId    = $object->get_customer_id();
            $email     = $object->get_billing_email();
            $exclude[] = $object->get_id();
        } else {
            $userId = get_current_user_id();
            $email  = function_exists('WC') && WC()->customer ? WC()->customer->get_billing_email() : '';
            if ($email === '' && $userId) {
                $email = (string) get_userdata($userId)->user_email;
            }
        }
        $statuses = array_values(array_diff(array_keys(wc_get_order_statuses()), self::NOT_AN_ORDER));
        foreach (array_filter([$userId ?: null, $email !== '' ? $email : null]) as $customer) {
            $found = wc_get_orders([
                'customer' => $customer,
                'status'   => $statuses,
                'exclude'  => $exclude,
                'limit'    => 1,
                'return'   => 'ids',
                'type'     => 'shop_order',
            ]);
            if ($found !== []) {
                return true;
            }
        }

        return false;
    }
}

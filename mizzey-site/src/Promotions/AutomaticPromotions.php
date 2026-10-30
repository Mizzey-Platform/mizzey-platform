<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * Applies automatic promotions (PROMO-11) to the cart, with no code, whenever they qualify.
 *
 * After the cart's totals are calculated, each published automatic promotion that is valid for the cart is measured
 * on a copy of WooCommerce's discount calculation (a free-shipping promotion is worth the cart's shipping). One applies,
 * because one promotion applies per order unless separately configured (Part One 1.6): the larger discount, then the
 * one created first (the owner's ruling Q1, 31 October 2026). A code the customer entered is their choice and takes
 * the place of automatic promotions; removing it brings them back. The combination setting is #290's (BR-004).
 *
 * The cart works the same for the block cart and checkout (Store API) and the classic pages, so both are covered.
 *
 * Register ids: PROMO-11, PROMO-23. See specs/011-promotion-engine, FR-004.
 */
final class AutomaticPromotions
{
    private const CACHE = 'mizzey_automatic_promotions';

    private static bool $busy = false;

    public static function register(): void
    {
        add_action('woocommerce_after_calculate_totals', [self::class, 'settle'], 20, 1);
        add_filter('woocommerce_apply_with_individual_use_coupon', [self::class, 'stepAside'], 10, 3);
        add_filter('woocommerce_coupon_message', [self::class, 'quiet'], 10, 3);
        add_action('save_post_shop_coupon', [self::class, 'forget']);
        add_action('woocommerce_new_coupon', [self::class, 'forget']);
        add_action('woocommerce_update_coupon', [self::class, 'forget']);
        add_action('woocommerce_delete_coupon', [self::class, 'forget']);
        add_action('woocommerce_trash_coupon', [self::class, 'forget']);
        add_action('trashed_post', [self::class, 'forgetPost']);
        add_action('untrashed_post', [self::class, 'forgetPost']);
        add_action('deleted_post', [self::class, 'forgetPost']);
    }

    /**
     * Brings the cart to the right automatic promotion, or none.
     *
     * @param mixed $cart
     */
    public static function settle($cart): void
    {
        if (self::$busy || ! $cart instanceof \WC_Cart) {
            return;
        }
        $automatic = self::codes();
        $applied   = array_map('wc_format_coupon_code', $cart->get_applied_coupons());
        $entered   = array_diff($applied, $automatic);
        $winner    = ($entered === [] && ! $cart->is_empty()) ? self::winner($cart, $automatic) : null;
        $remove    = array_values(array_diff(array_intersect($applied, $automatic), $winner !== null ? [$winner] : []));
        $add       = $winner !== null && ! in_array($winner, $applied, true);
        if ($remove === [] && ! $add) {
            return;
        }
        self::$busy = true;
        try {
            foreach ($remove as $code) {
                $cart->remove_coupon($code);
            }
            if ($add) {
                $cart->apply_coupon($winner);
            }
            $cart->calculate_totals();
        } finally {
            self::$busy = false;
        }
    }

    /**
     * The automatic promotion that applies to the cart: the larger discount, then the earliest created.
     *
     * @param list<string> $codes
     */
    public static function winner(\WC_Cart $cart, array $codes): ?string
    {
        $best = null;
        foreach ($codes as $code) {
            $coupon = new \WC_Coupon($code);
            if (! $coupon->get_id() || $coupon->get_status() !== 'publish' || ! PromotionSettings::automatic($coupon)
                || (new \WC_Discounts($cart))->is_coupon_valid($coupon) !== true || ! self::passesCheckout($coupon)) {
                continue;
            }
            $created   = $coupon->get_date_created();
            $candidate = [
                'code'    => $code,
                'value'   => self::value($cart, $coupon),
                'created' => $created ? $created->getTimestamp() : PHP_INT_MAX,
                'id'      => $coupon->get_id(),
            ];
            if ($best === null || self::better($candidate, $best)) {
                $best = $candidate;
            }
        }

        return $best['code'] ?? null;
    }

    /**
     * What the promotion is worth on this cart, in cents. Free shipping is worth the cheapest paid rate it replaces,
     * measured the same way whichever promotion is applied, and only where a free shipping method accepts a coupon.
     */
    public static function value(\WC_Cart $cart, \WC_Coupon $coupon): int
    {
        $discounts = new \WC_Discounts($cart);
        $discounts->apply_coupon($coupon, false);
        $cents = (int) round(array_sum($discounts->get_discounts_by_coupon(true)));
        if ($coupon->get_free_shipping()) {
            $cents += self::shippingValue($cart);
        }

        return $cents;
    }

    /**
     * The checks WooCommerce's checkout makes and its cart check does not, for a guest: the email restriction and the
     * per-customer limit by billing email. Without them an automatic promotion the checkout removes would come back on
     * the next calculation.
     */
    public static function passesCheckout(\WC_Coupon $coupon): bool
    {
        if (get_current_user_id() || ! function_exists('WC') || ! WC()->customer) {
            return true;
        }
        $email = (string) WC()->customer->get_billing_email();
        if ($email === '') {
            return true;
        }
        $restrictions = $coupon->get_email_restrictions();
        if ($restrictions && WC()->cart && ! WC()->cart->is_coupon_emails_allowed([$email], $restrictions)) {
            return false;
        }
        $limit = (int) $coupon->get_usage_limit_per_user();
        $store = $coupon->get_data_store();

        // The data store is a proxy that forwards through __call, so method_exists() cannot see its methods.
        return ! ($limit > 0 && $store && $store->has_callable('get_usage_by_email') && $store->get_usage_by_email($coupon, $email) >= $limit);
    }

    /** The cheapest paid rate of each package, summed, in cents; nothing where no free shipping method takes a coupon. */
    public static function shippingValue(\WC_Cart $cart): int
    {
        $cents = 0;
        foreach (WC()->shipping()->get_packages() as $package) {
            $zone     = \WC_Shipping_Zones::get_zone_matching_package($package);
            $unlocks  = false;
            foreach ($zone->get_shipping_methods(true) as $method) {
                $requires = $method->id === 'free_shipping' ? (string) $method->get_option('requires') : '';
                $minimum  = (float) $method->get_option('min_amount');
                if (in_array($requires, ['coupon', 'either'], true) || ($requires === 'both' && (float) $cart->get_displayed_subtotal() >= $minimum)) {
                    $unlocks = true;
                }
            }
            $paid = array_filter((array) ($package['rates'] ?? []), static fn ($rate) => $rate instanceof \WC_Shipping_Rate && $rate->get_method_id() !== 'free_shipping');
            if ($unlocks && $paid) {
                $cents += (int) round(wc_add_number_precision(min(array_map(static fn ($rate) => (float) $rate->get_cost(), $paid))));
            }
        }

        return $cents;
    }

    /**
     * Lets a customer's own code be added while an automatic promotion marked individual-use is applied; the automatic
     * one then steps aside in settle().
     *
     * @param mixed $allow
     * @param mixed $theCoupon
     * @param mixed $applied
     */
    public static function stepAside($allow, $theCoupon, $applied): bool
    {
        return $applied instanceof \WC_Coupon && in_array(wc_format_coupon_code($applied->get_code()), self::codes(), true) ? true : (bool) $allow;
    }

    /**
     * No "coupon applied" notice for an automatic promotion this class applies.
     *
     * @param mixed $message
     * @param mixed $code
     * @param mixed $coupon
     * @return mixed
     */
    public static function quiet($message, $code, $coupon)
    {
        return self::$busy && $coupon instanceof \WC_Coupon && PromotionSettings::automatic($coupon) ? '' : $message;
    }

    /** @return list<string> The codes of the published automatic promotions. */
    public static function codes(): array
    {
        $cached = get_transient(self::CACHE);
        if (is_array($cached)) {
            return $cached;
        }
        $ids   = get_posts([
            'post_type'        => 'shop_coupon',
            'post_status'      => 'publish',
            'posts_per_page'   => -1,
            'fields'           => 'ids',
            'meta_key'         => PromotionSettings::AUTOMATIC, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'       => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'suppress_filters' => true,
        ]);
        $codes = array_values(array_filter(array_map(static fn ($id) => wc_format_coupon_code((string) get_post_field('post_title', (int) $id, 'raw')), $ids)));
        set_transient(self::CACHE, $codes, DAY_IN_SECONDS);

        return $codes;
    }

    public static function forget(): void
    {
        delete_transient(self::CACHE);
    }

    /** @param mixed $postId */
    public static function forgetPost($postId): void
    {
        if (get_post_type((int) $postId) === 'shop_coupon') {
            self::forget();
        }
    }

    /**
     * @param array{value:int,created:int,id:int} $a
     * @param array{value:int,created:int,id:int} $b
     */
    private static function better(array $a, array $b): bool
    {
        if ($a['value'] !== $b['value']) {
            return $a['value'] > $b['value'];
        }
        if ($a['created'] !== $b['created']) {
            return $a['created'] < $b['created'];
        }

        return $a['id'] < $b['id'];
    }
}

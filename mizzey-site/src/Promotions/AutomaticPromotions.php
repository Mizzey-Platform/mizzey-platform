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

    /** The automatic promotions this class applied to the visitor's cart, kept in their session. */
    private const APPLIED = 'mizzey_automatic_applied';

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
        // What this class applied stays automatic even after its flag is switched off, so it can be taken away.
        $automatic = array_values(array_unique([...self::codes(), ...self::appliedHere()]));
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
            $now = array_map('wc_format_coupon_code', $cart->get_applied_coupons());
            self::remember($winner !== null && in_array($winner, $now, true) ? [$winner] : []);
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
            // A promotion that saves the customer nothing, or makes them pay more, is not applied.
            if ($candidate['value'] <= 0) {
                continue;
            }
            if ($best === null || self::better($candidate, $best)) {
                $best = $candidate;
            }
        }

        return $best['code'] ?? null;
    }

    /**
     * What the promotion saves the customer on this cart, in cents: its discount, plus the shipping it saves. Each
     * candidate is measured on its own, against the cart with no automatic promotion, so the promotion applied at the
     * moment never changes the comparison: what the customer would pay for shipping without any automatic promotion,
     * less what they would pay with this one (its discount can move the cart below a free shipping minimum; its free
     * shipping can make shipping free).
     */
    public static function value(\WC_Cart $cart, \WC_Coupon $coupon): int
    {
        $discounts = new \WC_Discounts($cart);
        $discounts->apply_coupon($coupon, false);
        $cents   = (int) round(array_sum($discounts->get_discounts_by_coupon(true)));
        $shown   = self::shownDiscount($cart, (array) $discounts->get_discounts_by_item(true));

        return $cents + self::shippingCost($cart, 0, false) - self::shippingCost($cart, $shown, $coupon->get_free_shipping());
    }

    /**
     * The discount as the free shipping minimum sees it, in cents: with each line's own tax when prices are shown with
     * tax and entered without it, or without it in the opposite case, so a cart mixing tax rates is measured exactly.
     *
     * @param array<string,float|int> $byItem Cents of discount by cart item key.
     */
    public static function shownDiscount(\WC_Cart $cart, array $byItem): int
    {
        $entered = wc_prices_include_tax();
        $shown   = $cart->display_prices_including_tax();
        $total   = 0.0;
        foreach ($byItem as $key => $cents) {
            $line  = $cart->get_cart_item((string) $key);
            $sub   = (float) ($line['line_subtotal'] ?? 0);
            $rate  = $sub > 0 ? (float) ($line['line_subtotal_tax'] ?? 0) / $sub : 0.0;
            $factor = (! $entered && $shown) ? 1 + $rate : (($entered && ! $shown) ? 1 / (1 + $rate) : 1.0);
            $total += (float) $cents * $factor;
        }

        return (int) round($total);
    }

    /**
     * The checks WooCommerce's checkout makes and its cart check does not: the email list against the billing email,
     * and the per-customer limit counted the way the Store API counts it (`OrderController::validate_coupon_usage_limit()`
     * in WooCommerce 11.1.0): over the account, its email and the billing email for a customer; for a guest, over the
     * billing email and any account that email belongs to. Without them an automatic promotion the checkout removes
     * would come back on the next calculation.
     */
    public static function passesCheckout(\WC_Coupon $coupon): bool
    {
        if (! function_exists('WC') || ! WC()->customer) {
            return true;
        }
        $email        = (string) WC()->customer->get_billing_email();
        $restrictions = $coupon->get_email_restrictions();
        // WooCommerce accepts a match on the billing email or the account's email.
        $emails = array_values(array_filter(array_map('wc_strtolower', [$email, get_current_user_id() ? (string) get_userdata(get_current_user_id())->user_email : ''])));
        $restrictions = array_map('wc_strtolower', $restrictions);
        if ($emails !== [] && $restrictions && WC()->cart && ! WC()->cart->is_coupon_emails_allowed($emails, $restrictions)) {
            return false;
        }
        $limit = (int) $coupon->get_usage_limit_per_user();
        if ($limit === 0) {
            return true;
        }
        $userId = get_current_user_id();
        if ($userId) {
            $aliases = [$email, (string) $userId, (string) get_userdata($userId)->user_email];
        } elseif ($email !== '') {
            $search  = wc_get_container()->get(\Automattic\WooCommerce\Internal\Customers\SearchService::class);
            $aliases = [$email];
            foreach ($search->find_user_ids_by_billing_email_for_coupons_usage_lookup([$email]) as $id) {
                $aliases[] = (string) $id;
                $aliases[] = (string) get_userdata((int) $id)->user_email;
            }
            $user = get_user_by('email', $email);
            if ($user) {
                $aliases[] = (string) $user->ID;
                $aliases[] = (string) $user->user_email;
                $aliases[] = (string) get_user_meta($user->ID, 'billing_email', true);
            }
        } else {
            return true;
        }

        return self::usage($coupon, $aliases) < $limit;
    }

    /**
     * A coupon's uses by any of the aliases, held ones included, as the Store API counts them.
     *
     * @param list<string> $aliases
     */
    public static function usage(\WC_Coupon $coupon, array $aliases): int
    {
        global $wpdb;
        $aliases = array_values(array_unique(array_filter($aliases)));
        if ($aliases === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($aliases), '%s'));
        $used         = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_used_by' AND meta_value IN ($placeholders)", $coupon->get_id(), ...$aliases)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $store        = $coupon->get_data_store();

        // The data store is a proxy that forwards through __call, so method_exists() cannot see its methods.
        return $used + ($store && $store->has_callable('get_tentative_usages_for_user') ? (int) $store->get_tentative_usages_for_user($coupon->get_id(), $aliases) : 0);
    }

    /**
     * What the customer would pay for shipping, in cents, with a given discount and with or without a free shipping
     * coupon: per package, nothing when a free shipping method of the zone would be available under its own rules
     * (`WC_Shipping_Free_Shipping::is_available()`, WooCommerce 11.1.0), otherwise the cheapest paid rate the zone's
     * methods calculate. Calculated from the zone's methods, so neither the promotion applied now nor WooCommerce hiding
     * paid rates once shipping is free changes it. Paid rates are taken as the zone calculates them for the cart: the
     * store's rates are fixed per governorate (the carrier's rate card), so a promotion does not change them. A rate that
     * depends on the cart's discounted value is not modelled (recorded in the specification).
     */
    public static function shippingCost(\WC_Cart $cart, int $discountCents, bool $freeCoupon): int
    {
        $cents = 0;
        foreach (WC()->shipping()->get_packages() as $package) {
            $methods = \WC_Shipping_Zones::get_zone_matching_package($package)->get_shipping_methods(true);
            $free    = false;
            $costs   = [];
            foreach ($methods as $method) {
                if ($method->id === 'free_shipping') {
                    $met      = self::minimumMet($cart, $method, $discountCents);
                    $requires = (string) $method->get_option('requires');
                    $free     = $free || match ($requires) {
                        'coupon'     => $freeCoupon,
                        'min_amount' => $met,
                        'either'     => $met || $freeCoupon,
                        'both'       => $met && $freeCoupon,
                        default      => true,
                    };
                    continue;
                }
                foreach ((array) $method->get_rates_for_package($package) as $rate) {
                    $costs[] = (float) $rate->get_cost();
                }
            }
            if (! $free && $costs !== []) {
                $cents += (int) round(wc_add_number_precision(min($costs)));
            }
        }

        return $cents;
    }

    /**
     * The free shipping method's minimum, checked for a given discount rather than the cart's current one. As
     * WooCommerce does, the discount counts only when the method does not ignore discounts; shown with tax, it counts
     * with its tax.
     */
    private static function minimumMet(\WC_Cart $cart, \WC_Shipping_Method $method, int $discountCents): bool
    {
        $total = (float) $cart->get_displayed_subtotal();
        if ($method->get_option('ignore_discounts') !== 'yes') {
            // The discount arrives already as shown (shownDiscount()).
            $total -= wc_remove_number_precision($discountCents);
        }

        return round($total, wc_get_price_decimals()) >= (float) $method->get_option('min_amount');
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

    /** @return list<string> */
    private static function appliedHere(): array
    {
        $codes = function_exists('WC') && WC()->session ? WC()->session->get(self::APPLIED) : [];

        return is_array($codes) ? array_values(array_map('strval', $codes)) : [];
    }

    /** @param list<string> $codes */
    private static function remember(array $codes): void
    {
        if (function_exists('WC') && WC()->session) {
            WC()->session->set(self::APPLIED, $codes);
        }
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

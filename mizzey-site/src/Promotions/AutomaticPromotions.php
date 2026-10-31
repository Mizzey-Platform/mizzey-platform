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
     * Brings the cart to the right automatic promotions, or none. With combining off (BR-004, the default), one
     * automatic promotion applies, and none beside a code the customer entered. With it on, the best automatic discount
     * and the best automatic free shipping can join the entered codes, an individual-use promotion standing alone.
     *
     * @param mixed $cart
     */
    public static function settle($cart): void
    {
        if (self::$busy || ! $cart instanceof \WC_Cart) {
            return;
        }
        // What this class applied stays automatic even after its flag is switched off, so it can be taken away.
        $automatic = self::automaticCodes();
        $applied   = array_map('wc_format_coupon_code', $cart->get_applied_coupons());
        $entered   = array_values(array_diff($applied, $automatic));
        // With combining off, or when one of the entered codes is individual-use, the cart holds one code: the latest
        // entered (BR-004), also when the setting or a coupon's box changed while the cart held more. WooCommerce
        // re-validates applied coupons only on the cart and checkout pages.
        $solo  = array_filter($entered, static fn (string $code): bool => (new \WC_Coupon($code))->get_individual_use());
        $extra = count($entered) > 1 && (! Combination::allowed() || $solo !== []) ? array_slice($entered, 0, -1) : [];
        if ($extra !== []) {
            $entered = array_values(array_diff($entered, $extra));
            $applied = array_values(array_diff($applied, $extra));
        }
        $winners   = $cart->is_empty() ? [] : self::winners($cart, $automatic, $entered);
        $remove    = [...$extra, ...array_values(array_diff(array_intersect($applied, $automatic), $winners))];
        $add       = array_values(array_diff($winners, $applied));
        if ($remove === [] && $add === []) {
            return;
        }
        self::$busy = true;
        try {
            foreach ($remove as $code) {
                $cart->remove_coupon($code);
            }
            foreach ($add as $code) {
                $cart->apply_coupon($code);
            }
            $now = array_map('wc_format_coupon_code', $cart->get_applied_coupons());
            self::remember(array_values(array_intersect($winners, $now)));
            $cart->calculate_totals();
        } finally {
            self::$busy = false;
        }
    }

    /**
     * The automatic promotions the cart should hold, given the codes the customer entered.
     *
     * @param list<string> $automatic
     * @param list<string> $entered
     * @return list<string>
     */
    public static function winners(\WC_Cart $cart, array $automatic, array $entered): array
    {
        if (! Combination::allowed()) {
            $winner = $entered === [] ? self::winner($cart, $automatic) : null;

            return $winner !== null ? [$winner] : [];
        }
        $held = array_map(static fn (string $code): \WC_Coupon => new \WC_Coupon($code), $entered);
        foreach ($held as $coupon) {
            if ($coupon->get_individual_use()) {
                return [];
            }
        }
        $individual = static fn (string $code): bool => (new \WC_Coupon($code))->get_individual_use();
        // An individual-use promotion never stands beside an entered code, so it is not a candidate then; left in, it
        // could outrank a compatible one and leave neither applied.
        $candidates = $entered !== [] ? array_values(array_filter($automatic, static fn (string $code): bool => ! $individual($code))) : $automatic;
        $shipping   = array_values(array_filter($candidates, static fn (string $code): bool => self::shippingOnly(new \WC_Coupon($code))));
        $discount   = array_values(array_diff($candidates, $shipping));
        $joinable   = array_values(array_filter($shipping, static fn (string $code): bool => ! $individual($code)));
        // Measured after the entered codes, simulated with each candidate (measure()).
        // Every qualifying discount, also one that loses money alone: free shipping beside it may make up for it.
        $discounts = self::candidates($cart, $discount, $entered, false);
        $saving    = array_values(array_filter($discounts, static fn (array $each): bool => $each['value'] > 0));
        // Each promotion alone, valued after the entered codes only, when it saves something.
        $options = [];
        foreach ([$saving[0] ?? null, self::best($cart, $shipping, $entered)] as $alone) {
            if ($alone !== null) {
                $options[] = ['codes' => [$alone['code']], 'value' => $alone['value'], 'created' => $alone['created'], 'id' => $alone['id']];
            }
        }
        // Each joinable discount with the free shipping that can join it, valued after that discount, which can take
        // the cart under a free shipping minimum: a discount that loses alone can win with free shipping beside it.
        foreach ($discounts as $each) {
            $ship = $individual($each['code']) ? null : self::best($cart, $joinable, [...$entered, $each['code']]);
            if ($ship !== null && $each['value'] + $ship['value'] > 0) {
                $options[] = ['codes' => [$each['code'], $ship['code']], 'value' => $each['value'] + $ship['value'], 'created' => $each['created'], 'id' => $each['id']];
            }
        }
        usort($options, static fn (array $a, array $b): int => self::better($a, $b) ? -1 : 1);

        return $options !== [] ? $options[0]['codes'] : [];
    }

    /**
     * The discount, in cents by cart item, of the coupons applied together in WooCommerce's order.
     *
     * @param list<\WC_Coupon> $coupons
     * @return array<string,float|int>
     */
    private static function discountByItem(\WC_Cart $cart, array $coupons): array
    {
        $discounts = new \WC_Discounts($cart);
        $discounts->set_items(self::items($cart));
        foreach (self::inWooCommerceOrder($coupons) as $each) {
            $discounts->apply_coupon($each, false);
        }

        return (array) $discounts->get_discounts_by_item(true);
    }

    /**
     * Coupons in the order WooCommerce's totals apply them, which matters with sequential discounts: fixed per product,
     * then percentage, then fixed cart (`woocommerce_coupon_sort` may change it), then a smaller item limit, then a
     * smaller amount, then the newer (`WC_Cart_Totals::get_coupons_from_cart()`, `sort_coupons_callback()`, 11.1.0).
     *
     * @param list<\WC_Coupon> $coupons
     * @return list<\WC_Coupon>
     */
    private static function inWooCommerceOrder(array $coupons): array
    {
        $rank = static fn (\WC_Coupon $c): int => (int) apply_filters('woocommerce_coupon_sort', ['fixed_product' => 1, 'percent' => 2, 'fixed_cart' => 3][$c->get_discount_type()] ?? 0, $c);
        usort($coupons, static function (\WC_Coupon $a, \WC_Coupon $b) use ($rank): int {
            return [$rank($a), $a->get_limit_usage_to_x_items(), (float) $a->get_amount(), -$a->get_id()]
                <=> [$rank($b), $b->get_limit_usage_to_x_items(), (float) $b->get_amount(), -$b->get_id()];
        });

        return $coupons;
    }

    /** A promotion whose only reward is free shipping. */
    private static function shippingOnly(\WC_Coupon $coupon): bool
    {
        return $coupon->get_free_shipping() && (float) $coupon->get_amount() <= 0;
    }

    /**
     * The automatic promotion that applies to the cart: the larger discount, then the earliest created.
     *
     * @param list<string> $codes
     */
    public static function winner(\WC_Cart $cart, array $codes): ?string
    {
        return self::best($cart, $codes)['code'] ?? null;
    }

    /**
     * The best qualifying automatic promotion among `$codes`, with its value, or null.
     *
     * @param list<string> $codes
     * @param list<string> $before
     * @return array{code:string,value:int,created:int,id:int}|null
     */
    private static function best(\WC_Cart $cart, array $codes, array $before = []): ?array
    {
        return self::candidates($cart, $codes, $before)[0] ?? null;
    }

    /**
     * Every qualifying automatic promotion among `$codes` with its value, best first: only those that save the
     * customer something, unless `$savingOnly` is false.
     *
     * @param list<string> $codes
     * @param list<string> $before
     * @return list<array{code:string,value:int,created:int,id:int}>
     */
    private static function candidates(\WC_Cart $cart, array $codes, array $before, bool $savingOnly = true): array
    {
        $found = [];
        foreach ($codes as $code) {
            $coupon = new \WC_Coupon($code);
            if (! $coupon->get_id() || $coupon->get_status() !== 'publish' || ! PromotionSettings::automatic($coupon)
                || (new \WC_Discounts($cart))->is_coupon_valid($coupon) !== true || ! self::passesCheckout($coupon)) {
                continue;
            }
            $created   = $coupon->get_date_created();
            $candidate = [
                'code'    => $code,
                'value'   => self::measure($cart, $coupon, $before),
                'created' => $created ? $created->getTimestamp() : PHP_INT_MAX,
                'id'      => $coupon->get_id(),
            ];
            // A promotion that saves the customer nothing, or makes them pay more, is not applied.
            if (! $savingOnly || $candidate['value'] > 0) {
                $found[] = $candidate;
            }
        }
        usort($found, static fn (array $a, array $b): int => self::better($a, $b) ? -1 : 1);

        return $found;
    }

    /**
     * What the promotion saves the customer on this cart, in cents: the cut in what they pay (the discount with the
     * tax it removes, whatever the display setting), plus the shipping it saves. Each candidate is measured on its
     * own, against the cart with no automatic promotion, so the promotion applied at the moment never changes the
     * comparison: what the customer would pay for shipping without any automatic promotion, less what they would pay
     * with this one (its discount can move the cart below a free shipping minimum; its free shipping can make shipping
     * free).
     *
     * `$before` are the codes the customer entered (with combining on): their discount comes off first, and off a free
     * shipping minimum.
     *
     * @param list<string> $before
     */
    public static function value(\WC_Cart $cart, \WC_Coupon $coupon, array $before = []): int
    {
        return self::measure($cart, $coupon, $before);
    }

    /**
     * The saving of the coupon, in cents, applied after the codes in `$before` in one discount
     * calculation, so what they already take off a line is not counted again.
     *
     * @param list<string> $before
     */
    private static function measure(\WC_Cart $cart, \WC_Coupon $coupon, array $before): int
    {
        // What the promotion adds: the discount with it, less the discount without it, line by line, both applied in
        // WooCommerce's order, so a line the entered codes already take to nothing gains nothing from it.
        $held    = array_map(static fn (string $code): \WC_Coupon => new \WC_Coupon($code), $before);
        $with    = self::discountByItem($cart, [...$held, $coupon]);
        $without = $held !== [] ? self::discountByItem($cart, $held) : [];
        // The paid saving is what the customer pays without it less what they pay with it, each with its own tax
        // rounding, so the rounding of the entered codes' discount is not credited to the candidate.
        $paid = self::paidSaving($cart, $with) - self::paidSaving($cart, $without);
        // The free shipping minimum is compared with the simulated discounts, not the cart's current ones, which an
        // applied automatic promotion changes under sequential discounts; an entered code's free shipping counts on
        // both sides.
        $heldFree    = array_filter($held, static fn (\WC_Coupon $c): bool => $c->get_free_shipping()) !== [];
        $costWithout = self::shippingCost($cart, self::shownDiscount($cart, $without), $heldFree);
        $costWith    = self::shippingCost($cart, self::shownDiscount($cart, $with), $heldFree || $coupon->get_free_shipping());

        return $paid + $costWithout - $costWith;
    }

    /**
     * The discount as the free shipping minimum sees it, in cents: with tax when prices are shown with tax, without it
     * otherwise, each line at its own rate, so a cart mixing tax rates is measured exactly.
     *
     * @param array<string,float|int> $byItem Cents of discount by cart item key.
     */
    public static function shownDiscount(\WC_Cart $cart, array $byItem): float
    {
        return self::discountWithTax($cart, $byItem, $cart->display_prices_including_tax());
    }

    /**
     * The cart's lines priced as WooCommerce's own totals discount them (`WC_Cart_Totals::calculate_discounts()`,
     * WooCommerce 11.1.0): after its tax adjustments, so a tax-exempt customer's price entered with tax is discounted
     * without it, as is a price adjusted for a customer outside the base location. Taken from the lines' subtotals,
     * which hold those adjusted prices once the totals are calculated.
     *
     * @return array<string,\stdClass>
     */
    private static function items(\WC_Cart $cart): array
    {
        $entered = wc_prices_include_tax();
        $items   = [];
        foreach ($cart->get_cart() as $key => $line) {
            $item           = new \stdClass();
            $item->key      = $key;
            $item->object   = $line;
            $item->product  = $line['data'];
            $item->quantity = $line['quantity'];
            $item->price    = wc_add_number_precision_deep((float) ($line['line_subtotal'] ?? 0) + ($entered ? self::subtotalTax($line) : 0.0));
            $items[$key]    = $item;
        }

        return $items;
    }

    /**
     * A line's subtotal tax as WooCommerce calculated it, before rounding (`line_tax_data`), so a rebuilt price equals
     * the one WooCommerce discounts and sorts by; the rounded `line_subtotal_tax` when the data is missing.
     *
     * @param array<string,mixed> $line
     */
    private static function subtotalTax(array $line): float
    {
        $taxes = $line['line_tax_data']['subtotal'] ?? null;

        return is_array($taxes) ? (float) array_sum(array_map('floatval', $taxes)) : (float) ($line['line_subtotal_tax'] ?? 0);
    }

    /**
     * A discount, in cents, with or without its tax, each line at its own tax rate. The discount is on the price as
     * entered (items()), so tax is added when prices are entered without it, and taken out when they are entered with
     * it.
     *
     * @param array<string,float|int> $byItem Cents of discount by cart item key.
     */
    private static function discountWithTax(\WC_Cart $cart, array $byItem, bool $withTax): float
    {
        $entered = wc_prices_include_tax();
        $total   = 0.0;
        foreach ($byItem as $key => $cents) {
            $line   = $cart->get_cart_item((string) $key);
            $sub    = (float) ($line['line_subtotal'] ?? 0);
            $rate   = $sub > 0 ? self::subtotalTax((array) $line) / $sub : 0.0;
            $factor = (! $entered && $withTax) ? 1 + $rate : (($entered && ! $withTax) ? 1 / (1 + $rate) : 1.0);
            $total += (float) $cents * $factor;
        }

        // Unrounded: WooCommerce rounds only the remaining total it compares with a free shipping minimum.
        return $total;
    }

    /**
     * The cut in what the customer pays for the items, in cents. When prices are entered without tax, the tax the
     * discount removes is rounded as WooCommerce rounds tax (`WC_Cart_Totals`, WooCommerce 11.1.0): per line, the
     * line's rounded tax before the discount less its rounded tax after it; at subtotal, the cart's rounded tax before
     * less its rounded tax after. Otherwise the discount already is the cut (prices entered with tax).
     *
     * @param array<string,float|int> $byItem Cents of discount by cart item key.
     */
    private static function paidSaving(\WC_Cart $cart, array $byItem): int
    {
        if (wc_prices_include_tax() || ! wc_tax_enabled()) {
            return (int) round(self::discountWithTax($cart, $byItem, true));
        }
        $perLine  = get_option('woocommerce_tax_round_at_subtotal') !== 'yes';
        $discount = 0.0;
        $removed  = 0.0;
        foreach ($byItem as $key => $cents) {
            $line      = (array) $cart->get_cart_item((string) $key);
            $sub       = (float) ($line['line_subtotal'] ?? 0);
            $tax       = self::subtotalTax($line);
            $discount += (float) $cents;
            if ($sub <= 0 || $tax <= 0) {
                continue;
            }
            if ($perLine) {
                $rates    = \WC_Tax::get_rates($line['data']->get_tax_class(), $cart->get_customer());
                $net      = (float) wc_add_number_precision($sub);
                $removed += self::lineTax($net, $rates) - self::lineTax($net - (float) $cents, $rates);
            } else {
                $removed += (float) $cents * $tax / $sub;
            }
        }
        if (! $perLine) {
            $before = 0.0;
            foreach ($cart->get_cart() as $line) {
                $before += (float) wc_add_number_precision(self::subtotalTax((array) $line));
            }
            $removed = (float) wc_round_tax_total($before, 0) - (float) wc_round_tax_total($before - $removed, 0);
        }

        return (int) round($discount + $removed);
    }

    /**
     * A line's tax in cents on a net amount in cents, each rate rounded to the cent as WooCommerce rounds line tax.
     *
     * @param array<int|string,array<string,mixed>> $rates
     */
    private static function lineTax(float $netCents, array $rates): float
    {
        return (float) array_sum(array_map(static fn ($tax) => (float) wc_round_tax_total((float) $tax, 0), \WC_Tax::calc_tax($netCents, $rates, false)));
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
        // WooCommerce 11.1 deprecates `WC_Cart::is_coupon_emails_allowed()` for this utility, which it now calls itself.
        if ($emails !== [] && $restrictions && ! \Automattic\WooCommerce\Utilities\DiscountsUtil::is_coupon_emails_allowed($emails, $restrictions)) {
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
    public static function shippingCost(\WC_Cart $cart, float $discountCents, bool $freeCoupon): int
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
                // What the customer pays for the rate: its cost and its tax, as the discount counts its tax (value()).
                foreach ((array) $method->get_rates_for_package($package) as $rate) {
                    $costs[] = (float) $rate->get_cost() + array_sum(array_map('floatval', (array) $rate->get_taxes()));
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
    private static function minimumMet(\WC_Cart $cart, \WC_Shipping_Method $method, float $discountCents): bool
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
        return $applied instanceof \WC_Coupon && in_array(wc_format_coupon_code($applied->get_code()), self::automaticCodes(), true) ? true : (bool) $allow;
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

    /**
     * The automatic promotions for this visitor: the store's, the signed-in customer's own welcome discount, and what
     * this class applied before (so one switched off can be taken away).
     *
     * @return list<string>
     */
    public static function automaticCodes(): array
    {
        $own = WelcomeDiscount::codeFor(get_current_user_id());

        return array_values(array_unique(array_filter([...self::codes(), ...($own !== null ? [$own] : []), ...self::appliedHere()])));
    }

    /** @return list<string> The codes of the store's published automatic promotions (not a customer's own). */
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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'       => [
                ['key' => PromotionSettings::AUTOMATIC, 'value' => 'yes'],
                // A customer's own welcome discount is a candidate for that customer only (automaticCodes()).
                ['key' => WelcomeDiscount::OWNER, 'compare' => 'NOT EXISTS'],
            ],
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

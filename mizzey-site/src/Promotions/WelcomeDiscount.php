<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * The welcome discount on account creation (BR-001): value, cap, expiry and eligibility all admin-configurable.
 *
 * Staff set it up as one coupon marked "Welcome discount template" on the coupon screen: its discount type and amount,
 * maximum discount, minimum spend, item and product conditions and "Individual use only" box are the welcome discount's,
 * and "Valid for (days)" sets its expiry. The template itself is never accepted as a code. When a customer account is
 * created, the customer gets their own promotion copied from it: restricted to their email, one use, first order only
 * (new customers only), expiring after the set days, and applied automatically for them alone (AutomaticPromotions), so
 * no code has to reach them. Without a published, unpaused template no welcome discount is issued.
 *
 * OD-03's example (10 percent off the first order, capped at 200 EGP, minimum order 500 EGP, valid 14 days, one use, new
 * customers only, not combined) is a working default the client replaces; it is the template staff are asked to set up.
 *
 * Register ids: BR-001. See specs/016-launch-rules.
 */
final class WelcomeDiscount
{
    public const TEMPLATE  = '_mizzey_welcome_template';
    public const DAYS      = '_mizzey_welcome_days';
    public const OWNER     = '_mizzey_welcome_for';
    public const USER_META = '_mizzey_welcome_coupon';

    /** The prefix of each email's claim to its welcome discount, kept when the account is deleted: one per email, ever. */
    public const ISSUED = 'mizzey_welcome_issued';

    public static function register(): void
    {
        add_action('user_register', [self::class, 'issue'], 20, 1);
    }

    public static function isTemplate(\WC_Coupon $coupon): bool
    {
        return $coupon->get_meta(self::TEMPLATE, true) === 'yes';
    }

    /** The customer a welcome discount belongs to, or 0 for any other coupon. */
    public static function owner(\WC_Coupon $coupon): int
    {
        return (int) $coupon->get_meta(self::OWNER, true);
    }

    public static function days(\WC_Coupon $coupon): int
    {
        return max(0, (int) $coupon->get_meta(self::DAYS, true));
    }

    /** The published, unpaused template, the most recently made if staff marked more than one. */
    public static function template(): ?\WC_Coupon
    {
        $ids = get_posts([
            'post_type'        => 'shop_coupon',
            'post_status'      => 'publish',
            'posts_per_page'   => 1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'fields'           => 'ids',
            'meta_key'         => self::TEMPLATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
            'meta_value'       => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
            'suppress_filters' => true,
        ]);
        $coupon = $ids ? new \WC_Coupon((int) $ids[0]) : null;

        return $coupon && $coupon->get_id() && ! PromotionSettings::paused($coupon) ? $coupon : null;
    }

    /**
     * Issues a new customer's welcome discount, once.
     *
     * @param mixed $userId
     */
    public static function issue($userId): void
    {
        $user = get_userdata((int) $userId);
        if (! $user instanceof \WP_User || ! in_array('customer', (array) $user->roles, true) || get_user_meta($user->ID, self::USER_META, true)) {
            return;
        }
        $template = self::template();
        if ($template === null || ! is_email($user->user_email)) {
            return;
        }
        // One claim per email, made atomically: an insert that does nothing when the email's claim row exists, so two
        // registrations at once cannot both pass (add_option() upserts, so it cannot tell them apart).
        if (! self::claim($user->user_email)) {
            return;
        }
        $coupon = new \WC_Coupon();
        $coupon->set_code('welcome-' . strtolower(wp_generate_password(10, false, false)));
        $coupon->set_props([
            'description'                 => sprintf('Welcome discount for customer #%d, from template #%d', $user->ID, $template->get_id()),
            'discount_type'               => $template->get_discount_type(),
            'amount'                      => $template->get_amount(),
            'individual_use'              => $template->get_individual_use('edit'),
            'free_shipping'               => $template->get_free_shipping(),
            'minimum_amount'              => $template->get_minimum_amount(),
            'maximum_amount'              => $template->get_maximum_amount(),
            'product_ids'                 => $template->get_product_ids(),
            'excluded_product_ids'        => $template->get_excluded_product_ids(),
            'product_categories'          => $template->get_product_categories(),
            'excluded_product_categories' => $template->get_excluded_product_categories(),
            'exclude_sale_items'          => $template->get_exclude_sale_items(),
            'limit_usage_to_x_items'      => $template->get_limit_usage_to_x_items(),
            'email_restrictions'          => [$user->user_email],
            'usage_limit'                 => 1,
            'usage_limit_per_user'        => 1,
            'date_expires'                => self::days($template) > 0 ? time() + self::days($template) * DAY_IN_SECONDS : null,
        ]);
        foreach ([PromotionSettings::MAX_DISCOUNT, PromotionSettings::MIN_ITEMS] as $key) {
            $coupon->update_meta_data($key, (string) $template->get_meta($key, true));
        }
        $coupon->update_meta_data(PromotionSettings::AUTOMATIC, 'yes');
        $coupon->update_meta_data(PromotionSettings::FIRST_ORDER, 'yes');
        $coupon->update_meta_data(self::OWNER, (string) $user->ID);
        $coupon->save();
        if ($coupon->get_id()) {
            // WooCommerce keeps a coupon's brand conditions as post meta of their own (`WC_Brands_Coupons`), lists of
            // term ids, not as coupon props.
            foreach (['product_brands', 'exclude_product_brands'] as $key) {
                $brands = get_post_meta($template->get_id(), $key, true);
                if ($brands !== '' && $brands !== []) {
                    update_post_meta($coupon->get_id(), $key, $brands);
                }
            }
            update_user_meta($user->ID, self::USER_META, $coupon->get_id());
        }
    }

    /** Claims the email's one welcome discount; false when it was claimed before, by this or any account. */
    public static function claim(string $email): bool
    {
        global $wpdb;
        $name     = self::ISSUED . '_' . hash('sha256', wc_strtolower($email));
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, '1')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        wp_cache_delete('notoptions', 'options');

        return $inserted === 1;
    }

    /** The customer's own welcome discount code while it can still be used, or null. */
    public static function codeFor(int $userId): ?string
    {
        $id = $userId > 0 ? (int) get_user_meta($userId, self::USER_META, true) : 0;
        if ($id <= 0 || get_post_status($id) !== 'publish') {
            return null;
        }
        $coupon = new \WC_Coupon($id);
        $limit  = $coupon->get_usage_limit();

        return $coupon->get_id() && ($limit === 0 || $coupon->get_usage_count() < $limit) ? wc_format_coupon_code($coupon->get_code()) : null;
    }
}

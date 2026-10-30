<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Promotions;

defined('ABSPATH') || exit;

/**
 * The five promotion settings WooCommerce's coupons lack (P-003), stored on the coupon and edited on its screen:
 * apply automatically, start date, maximum discount, minimum item count, and first order only. The coupon screen is
 * admin, so English only (ADM-159).
 *
 * Register ids: PROMO-05, PROMO-09, PROMO-11, PROMO-15, PROMO-19, PROMO-23. See specs/011-promotion-engine, FR-007.
 */
final class PromotionSettings
{
    public const AUTOMATIC    = '_mizzey_automatic';
    public const STARTS       = '_mizzey_starts';
    public const MAX_DISCOUNT = '_mizzey_max_discount';
    public const MIN_ITEMS    = '_mizzey_min_items';
    public const FIRST_ORDER  = '_mizzey_first_order';

    public static function register(): void
    {
        add_action('woocommerce_coupon_options', [self::class, 'generalFields'], 10, 2);
        add_action('woocommerce_coupon_options_usage_restriction', [self::class, 'restrictionFields'], 10, 2);
        add_action('woocommerce_coupon_options_save', [self::class, 'save'], 10, 2);
    }

    public static function automatic(\WC_Coupon $coupon): bool
    {
        return $coupon->get_meta(self::AUTOMATIC, true) === 'yes';
    }

    public static function starts(\WC_Coupon $coupon): ?int
    {
        $value = $coupon->get_meta(self::STARTS, true);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public static function maxDiscount(\WC_Coupon $coupon): ?float
    {
        $value = $coupon->get_meta(self::MAX_DISCOUNT, true);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    public static function minItems(\WC_Coupon $coupon): int
    {
        return max(0, (int) $coupon->get_meta(self::MIN_ITEMS, true));
    }

    public static function firstOrder(\WC_Coupon $coupon): bool
    {
        return $coupon->get_meta(self::FIRST_ORDER, true) === 'yes';
    }

    /**
     * The General tab: apply automatically, start date, maximum discount.
     *
     * @param mixed $couponId
     */
    public static function generalFields($couponId, \WC_Coupon $coupon): void
    {
        woocommerce_wp_checkbox([
            'id'          => 'mizzey_automatic',
            'label'       => __('Apply automatically', 'mizzey-site'),
            'description' => __('Applies without a code whenever its conditions hold. One automatic promotion applies per order: the larger discount, then the one created first.', 'mizzey-site'),
            'value'       => self::automatic($coupon) ? 'yes' : 'no',
        ]);
        $starts = self::starts($coupon);
        woocommerce_wp_text_input([
            'id'                => 'mizzey_starts',
            'label'             => __('Promotion starts', 'mizzey-site'),
            'placeholder'       => 'YYYY-MM-DD',
            'description'       => __('The promotion applies from the start of this day, store time. Leave empty to start at once.', 'mizzey-site'),
            'desc_tip'          => true,
            'type'              => 'date',
            'value'             => $starts !== null ? wp_date('Y-m-d', $starts) : '',
            'custom_attributes' => ['pattern' => '[0-9]{4}-[0-9]{2}-[0-9]{2}'],
        ]);
        $max = self::maxDiscount($coupon);
        woocommerce_wp_text_input([
            'id'          => 'mizzey_max_discount',
            'label'       => __('Maximum discount', 'mizzey-site'),
            'description' => __('The most this promotion takes off one order. Leave empty for no cap.', 'mizzey-site'),
            'desc_tip'    => true,
            'data_type'   => 'price',
            'value'       => $max !== null ? wc_format_localized_price((string) $max) : '',
        ]);
    }

    /**
     * The Usage restriction tab: minimum item count, first order only.
     *
     * @param mixed $couponId
     */
    public static function restrictionFields($couponId, \WC_Coupon $coupon): void
    {
        woocommerce_wp_text_input([
            'id'                => 'mizzey_min_items',
            'label'             => __('Minimum items', 'mizzey-site'),
            'description'       => __('Units in the cart that this promotion applies to. Leave empty for no minimum.', 'mizzey-site'),
            'desc_tip'          => true,
            'type'              => 'number',
            'value'             => self::minItems($coupon) ?: '',
            'custom_attributes' => ['min' => '0', 'step' => '1'],
        ]);
        woocommerce_wp_checkbox([
            'id'          => 'mizzey_first_order',
            'label'       => __('First order only', 'mizzey-site'),
            'description' => __('Only for a customer with no earlier order, by account or, for a guest, by billing email.', 'mizzey-site'),
            'value'       => self::firstOrder($coupon) ? 'yes' : 'no',
        ]);
    }

    /**
     * Saves the settings. Runs after WooCommerce's own nonce check and coupon save.
     *
     * @param mixed $couponId
     */
    public static function save($couponId, \WC_Coupon $coupon): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified woocommerce_meta_nonce before this action.
        $wasAutomatic = self::automatic($coupon);
        $automatic    = isset($_POST['mizzey_automatic']) ? 'yes' : 'no';
        $startsInput  = sanitize_text_field(wp_unslash((string) ($_POST['mizzey_starts'] ?? '')));
        $maxInput     = wc_format_decimal(sanitize_text_field(wp_unslash((string) ($_POST['mizzey_max_discount'] ?? ''))));
        $minItems     = absint(wp_unslash((string) ($_POST['mizzey_min_items'] ?? '0')));
        $firstOrder   = isset($_POST['mizzey_first_order']) ? 'yes' : 'no';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $coupon->update_meta_data(self::AUTOMATIC, $automatic);
        if ($automatic === 'yes' && ! $wasAutomatic) {
            // One promotion per order unless separately configured (Part One 1.6).
            $coupon->set_individual_use(true);
        }
        $starts = self::parseDate($startsInput);
        $expiry = $coupon->get_date_expires();
        if ($starts !== null && $expiry !== null && $starts > $expiry->getTimestamp()) {
            self::error(__('Promotion start not saved: it is after the expiry date.', 'mizzey-site'));
        } elseif ($startsInput === '' || $starts !== null) {
            $coupon->update_meta_data(self::STARTS, $starts !== null ? (string) $starts : '');
        } else {
            self::error(__('Promotion start not saved: use the YYYY-MM-DD format.', 'mizzey-site'));
        }
        $coupon->update_meta_data(self::MAX_DISCOUNT, $maxInput !== '' && (float) $maxInput > 0 ? (string) $maxInput : '');
        $coupon->update_meta_data(self::MIN_ITEMS, $minItems > 0 ? (string) $minItems : '');
        $coupon->update_meta_data(self::FIRST_ORDER, $firstOrder);
        $coupon->save();
        AutomaticPromotions::forget();
    }

    /** The start of the day, store time, as a timestamp; null when empty or not a date. */
    public static function parseDate(string $date): ?int
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());

        return $parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === $date ? $parsed->getTimestamp() : null;
    }

    private static function error(string $message): void
    {
        if (class_exists(\WC_Admin_Meta_Boxes::class)) {
            \WC_Admin_Meta_Boxes::add_error($message);
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Shipping;

defined('ABSPATH') || exit;

/**
 * The delivery estimate as dates on the product page (PDP-14; the owner's design ruling of 26 November 2026): "Order
 * now, get it by Thu 3 Dec", or "Order now, get it between Mon 30 Nov and Thu 3 Dec".
 *
 * The working days come from the estimate of specs/055 (the customer's zone, or the default; 2 to 5 today). They are
 * counted in Cairo time whatever the server's or the site's time zone, on the working days of the week staff set
 * (Saturday to Thursday by default), skipping the holidays staff list; an order after the cut-off time (14:00 by
 * default), or on a day off, counts from the next working day. In exact mode the date is the estimate's last day, so
 * the page never promises more than the estimate.
 *
 * The settings sit with the product page's other settings: WooCommerce > Settings > Products > Product page.
 *
 * Register ids: PDP-14. See specs/066-stage-2-shop-front, slice 9.
 */
final class DeliveryDates
{
    public const MODE     = 'mizzey_delivery_mode';
    public const DAYS     = 'mizzey_delivery_days';
    public const CUTOFF   = 'mizzey_delivery_cutoff';
    public const HOLIDAYS = 'mizzey_delivery_holidays';
    public const ZONE     = 'Africa/Cairo';
    /** Saturday to Thursday, as PHP numbers the days (0 is Sunday). */
    public const DEFAULT_DAYS = ['6', '0', '1', '2', '3', '4'];

    public static function register(): void
    {
        add_filter('woocommerce_get_settings_products', [self::class, 'settings'], 30, 2);
    }

    /**
     * The four settings, before the end of the product page section.
     *
     * @param mixed $settings
     * @param mixed $section
     * @return mixed
     */
    public static function settings($settings, $section)
    {
        if ($section !== \MizzeySite\Storefront\ProductPage::SECTION || ! is_array($settings)) {
            return $settings;
        }
        $days   = [
            '6' => __('Saturday', 'mizzey-site'),
            '0' => __('Sunday', 'mizzey-site'),
            '1' => __('Monday', 'mizzey-site'),
            '2' => __('Tuesday', 'mizzey-site'),
            '3' => __('Wednesday', 'mizzey-site'),
            '4' => __('Thursday', 'mizzey-site'),
            '5' => __('Friday', 'mizzey-site'),
        ];
        $fields = [
            ['type' => 'select', 'id' => self::MODE, 'title' => __('Delivery date shown', 'mizzey-site'), 'default' => 'range', 'options' => [
                'range' => __('A range: "get it between Mon 30 Nov and Thu 3 Dec"', 'mizzey-site'),
                'exact' => __('One date, the latest: "get it by Thu 3 Dec"', 'mizzey-site'),
            ], 'desc_tip' => __('The dates come from the delivery estimate above, in working days.', 'mizzey-site')],
            ['type' => 'multiselect', 'id' => self::DAYS, 'title' => __('Delivery working days', 'mizzey-site'), 'class' => 'wc-enhanced-select', 'default' => self::DEFAULT_DAYS, 'options' => $days],
            ['type' => 'text', 'id' => self::CUTOFF, 'title' => __('Order cut-off time (Cairo)', 'mizzey-site'), 'default' => '14:00', 'placeholder' => '14:00', 'desc_tip' => __('An order after this time, 24-hour clock, counts from the next working day.', 'mizzey-site')],
            ['type' => 'textarea', 'id' => self::HOLIDAYS, 'title' => __('Holidays (no delivery)', 'mizzey-site'), 'desc_tip' => __('One date a line, as 2026-12-25.', 'mizzey-site'), 'css' => 'min-height:80px'],
        ];
        $out = [];
        foreach ($settings as $field) {
            if (is_array($field) && ($field['type'] ?? '') === 'sectionend' && ($field['id'] ?? '') === \MizzeySite\Storefront\ProductPage::SECTION) {
                array_push($out, ...$fields);
            }
            $out[] = $field;
        }

        return $out;
    }

    public static function mode(): string
    {
        return get_option(self::MODE, 'range') === 'exact' ? 'exact' : 'range';
    }

    /** @return list<int> The working days of the week, 0 (Sunday) to 6. */
    public static function days(): array
    {
        $set  = get_option(self::DAYS, self::DEFAULT_DAYS);
        $days = array_values(array_unique(array_filter(array_map('intval', is_array($set) ? $set : self::DEFAULT_DAYS), static fn (int $d): bool => $d >= 0 && $d <= 6)));

        return $days !== [] ? $days : array_map('intval', self::DEFAULT_DAYS);
    }

    /** @return array{0:int,1:int} The cut-off time, hours and minutes; 14:00 when what is set is not a time. */
    public static function cutoff(): array
    {
        return preg_match('/^\s*([01]?\d|2[0-3]):([0-5]\d)\s*$/', (string) get_option(self::CUTOFF, '14:00'), $m) === 1 ? [(int) $m[1], (int) $m[2]] : [14, 0];
    }

    /** @return list<string> The holidays, as Y-m-d. */
    public static function holidays(): array
    {
        preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/', (string) get_option(self::HOLIDAYS, ''), $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * The first and last delivery days for an estimate of $min to $max working days, for an order placed $now.
     *
     * @return array{from:\DateTimeImmutable,to:\DateTimeImmutable}
     */
    public static function dates(int $min, int $max, ?\DateTimeImmutable $now = null): array
    {
        $zone     = new \DateTimeZone(self::ZONE);
        $now      = ($now ?? new \DateTimeImmutable('now'))->setTimezone($zone);
        $days     = self::days();
        $holidays = self::holidays();
        $working  = static fn (\DateTimeImmutable $d): bool => in_array((int) $d->format('w'), $days, true) && ! in_array($d->format('Y-m-d'), $holidays, true);
        [$hour, $minute] = self::cutoff();

        // The day the order is handled: today before the cut-off on a working day, otherwise the next working day.
        $day = $now->setTime(0, 0);
        if (! $working($day) || $now >= $now->setTime($hour, $minute)) {
            do {
                $day = $day->add(new \DateInterval('P1D'));
            } while (! $working($day));
        }
        $add = static function (\DateTimeImmutable $from, int $count) use ($working): \DateTimeImmutable {
            $at = $from;
            for ($i = 0; $i < max(0, $count); $i++) {
                do {
                    $at = $at->add(new \DateInterval('P1D'));
                } while (! $working($at));
            }

            return $at;
        };

        return ['from' => $add($day, min($min, $max)), 'to' => $add($day, max($min, $max))];
    }

    /** A date as the boards write it: "Thu 3 Dec", in Arabic "الخميس 3 ديسمبر", in Cairo time. */
    public static function format(\DateTimeImmutable $date): string
    {
        /* translators: a date format (PHP date()) for a delivery date, as in "Thu 3 Dec". */
        return (string) wp_date(_x('D j M', 'delivery date', 'mizzey-site'), $date->getTimestamp(), new \DateTimeZone(self::ZONE));
    }

    /**
     * The sentence for an estimate, its dates already in <strong>, escaped.
     *
     * @param array{min:int,max:int} $estimate
     */
    public static function sentence(array $estimate, ?\DateTimeImmutable $now = null): string
    {
        $dates = self::dates((int) $estimate['min'], (int) $estimate['max'], $now);
        $mark  = static fn (\DateTimeImmutable $d): string => '<strong class="mizzey-buy__date">' . esc_html(self::format($d)) . '</strong>';
        if (self::mode() === 'exact' || $dates['from'] == $dates['to']) {
            /* translators: %s: the latest delivery date, as in "Thu 3 Dec". */
            return sprintf(esc_html__('Order now, get it by %s', 'mizzey-site'), $mark($dates['to']));
        }

        /* translators: 1: the first delivery date, 2: the last, as in "Mon 30 Nov" and "Thu 3 Dec". */
        return sprintf(esc_html__('Order now, get it between %1$s and %2$s', 'mizzey-site'), $mark($dates['from']), $mark($dates['to']));
    }
}

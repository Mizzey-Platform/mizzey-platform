<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

use MizzeySite\Emails\Shipped;

defined('ABSPATH') || exit;

/**
 * The filters WooCommerce's orders list lacks for ADM-85: payment method, carrier and a date range. WooCommerce already
 * searches by order number, customer, phone and email (its "All" search) and filters by status, month and registered
 * customer; these combine with them. The store runs WooCommerce's own order tables (HPOS), whose list these extend.
 *
 * Register ids: ADM-85. See specs/030-order-management.
 */
final class OrderFilters
{
    public static function register(): void
    {
        add_action('woocommerce_order_list_table_restrict_manage_orders', [self::class, 'render'], 20, 2);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [self::class, 'query']);
    }

    /** @return array{payment:string,carrier:string,from:string,to:string} The filters in the request, sanitised. */
    public static function current(): array
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only list filter, like WooCommerce's own.
        $get = static fn (string $key): string => isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
        $values = [
            'payment' => sanitize_key($get('mizzey_payment')),
            'carrier' => $get('mizzey_carrier'),
            'from'    => self::date($get('mizzey_from')),
            'to'      => self::date($get('mizzey_to')),
        ];
        // phpcs:enable

        return $values;
    }

    private static function date(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : '';
    }

    /**
     * @param mixed $type
     * @param mixed $which
     */
    public static function render($type = 'shop_order', $which = 'top'): void
    {
        if ($type !== 'shop_order' || $which !== 'top' || ! current_user_can('edit_shop_orders')) {
            return;
        }
        $c = self::current();

        echo '<select name="mizzey_payment" id="mizzey-filter-payment">';
        printf('<option value="">%s</option>', esc_html__('All payment methods', 'mizzey-site'));
        foreach (WC()->payment_gateways()->payment_gateways() as $id => $gateway) {
            $title = $gateway->get_method_title() !== '' ? $gateway->get_method_title() : $gateway->get_title();
            printf('<option value="%s"%s>%s</option>', esc_attr((string) $id), selected($c['payment'], (string) $id, false), esc_html(wp_strip_all_tags((string) $title)));
        }
        echo '</select>';

        echo '<select name="mizzey_carrier" id="mizzey-filter-carrier">';
        printf('<option value="">%s</option>', esc_html__('All carriers', 'mizzey-site'));
        foreach (self::carriers() as $carrier) {
            printf('<option value="%s"%s>%s</option>', esc_attr($carrier), selected($c['carrier'], $carrier, false), esc_html($carrier));
        }
        echo '</select>';

        printf(
            '<label class="screen-reader-text" for="mizzey-filter-from">%1$s</label><input type="date" name="mizzey_from" id="mizzey-filter-from" value="%2$s" title="%1$s" />',
            esc_attr__('Placed from', 'mizzey-site'),
            esc_attr($c['from'])
        );
        printf(
            '<label class="screen-reader-text" for="mizzey-filter-to">%1$s</label><input type="date" name="mizzey_to" id="mizzey-filter-to" value="%2$s" title="%1$s" />',
            esc_attr__('Placed to', 'mizzey-site'),
            esc_attr($c['to'])
        );
    }

    /** @return list<string> The carriers on the store's orders, for the filter. */
    public static function carriers(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_orders_meta';
        $rows  = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_value FROM {$table} WHERE meta_key = %s AND meta_value <> '' ORDER BY meta_value LIMIT 50", Shipped::CARRIER)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return array_values(array_map('strval', (array) $rows));
    }

    /**
     * @param mixed $args
     * @return mixed
     */
    public static function query($args)
    {
        if (! is_array($args)) {
            return $args;
        }
        $c = self::current();
        if ($c['payment'] !== '' && array_key_exists($c['payment'], WC()->payment_gateways()->payment_gateways())) {
            $args['payment_method'] = $c['payment'];
        }
        if ($c['carrier'] !== '') {
            $meta                 = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
            $meta[]               = ['key' => Shipped::CARRIER, 'value' => $c['carrier'], 'compare' => '='];
            $args['meta_query']   = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
        }
        if ($c['from'] !== '' || $c['to'] !== '') {
            $tz   = wp_timezone();
            $from = $c['from'] !== '' ? (new \DateTimeImmutable($c['from'] . ' 00:00:00', $tz))->getTimestamp() : null;
            $to   = $c['to'] !== '' ? (new \DateTimeImmutable($c['to'] . ' 23:59:59', $tz))->getTimestamp() : null;
            // WooCommerce's month filter, when chosen too, narrows the same range: both apply.
            [$monthFrom, $monthTo] = self::range($args['date_created'] ?? null, $tz);
            $from = max(array_filter([$from, $monthFrom], 'is_int') ?: [null]);
            $to   = min(array_filter([$to, $monthTo], 'is_int') ?: [PHP_INT_MAX]);
            $to   = $to === PHP_INT_MAX ? null : $to;
            if ($from !== null && $to !== null) {
                $args['date_created'] = $from <= $to ? $from . '...' . $to : '1...1'; // Ranges that do not meet: nothing matches.
            } elseif ($from !== null) {
                $args['date_created'] = '>=' . $from;
            } else {
                $args['date_created'] = '<=' . $to;
            }
        }

        return $args;
    }

    /**
     * The first and last moments of a "start...end" range WooCommerce's month filter sets, as timestamps.
     *
     * @param mixed $range
     * @return array{0:?int,1:?int}
     */
    private static function range($range, \DateTimeZone $tz): array
    {
        if (! is_string($range) || substr_count($range, '...') !== 1) {
            return [null, null];
        }
        [$a, $b] = explode('...', $range);
        $stamp   = static function (string $v, bool $end) use ($tz): ?int {
            if (ctype_digit($v)) {
                return (int) $v;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
                return (new \DateTimeImmutable($v . ($end ? ' 23:59:59' : ' 00:00:00'), $tz))->getTimestamp();
            }

            return null;
        };

        return [$stamp($a, false), $stamp($b, true)];
    }
}

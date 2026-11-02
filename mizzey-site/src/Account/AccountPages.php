<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Account;

use MizzeySite\Emails\Shipped;
use MizzeySite\Orders\StatusModel;
use MizzeySite\Wishlist\Wishlist;

defined('ABSPATH') || exit;

/**
 * What WooCommerce's My Account lacks for the contracted account area, on its own pages and hooks:
 *
 * - Order tracking (ACCT-07): under an order's details, the shipment (carrier, tracking number and page, specs/021) and
 *   the order's progress, each status it has entered with its date (the status model's history, specs/010).
 * - Saved wishlist (ACCT-08): a Wishlist item in the account menu, after Orders, opening the storefront's one Wishlist
 *   page (IA-11, `/wishlist/` and `/ar/wishlist/`), so the wishlist has one route (G-1 of specs/003). The page's screen
 *   is specs/015's, after PRE-03b.
 *
 * The dashboard, profile and password, order history and order detail (ACCT-01, ACCT-02, ACCT-03, ACCT-05, ACCT-06) are
 * WooCommerce's own account pages; the address book is AddressBook. The designed screens are PRE-03b's.
 *
 * Register ids: ACCT-07, ACCT-08. See specs/022-my-account.
 */
final class AccountPages
{
    /** The account menu item's key. */
    public const WISHLIST = 'wishlist';


    public static function register(): void
    {
        add_action('woocommerce_order_details_after_order_table', [self::class, 'tracking'], 20, 1);
        add_filter('woocommerce_account_menu_items', [self::class, 'menu'], 20, 1);
        add_filter('woocommerce_get_endpoint_url', [self::class, 'wishlistUrl'], 10, 2);
    }

    /**
     * The account menu's Wishlist item opens the storefront's Wishlist page, in the visitor's language.
     *
     * @param mixed $url
     * @param mixed $endpoint
     * @return mixed
     */
    public static function wishlistUrl($url, $endpoint)
    {
        if ($endpoint !== self::WISHLIST) {
            return $url;
        }
        $page = get_page_by_path('wishlist');
        if (! $page instanceof \WP_Post) {
            return $url;
        }

        return get_permalink((int) apply_filters('wpml_object_id', $page->ID, 'page', true)) ?: $url;
    }


    /**
     * The order's progress: each status it entered, with when.
     *
     * @return list<array{status:string,name:string,at:int}>
     */
    public static function progress(\WC_Order $order): array
    {
        // Every status the order recorded entering (specs/010), the unhappy ones included, in the order entered.
        $steps    = [];
        $statuses = array_map(static fn (string $key): string => str_starts_with($key, 'wc-') ? substr($key, 3) : $key, array_keys(wc_get_order_statuses()));
        foreach ($statuses as $status) {
            $at = StatusModel::enteredAt($order, $status);
            if ($at !== null) {
                $steps[] = ['status' => $status, 'name' => StatusModel::name($status), 'at' => $at];
            }
        }
        usort($steps, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $steps;
    }

    /** @param mixed $order */
    public static function tracking($order): void
    {
        if (! $order instanceof \WC_Order || ! is_account_page()) {
            return;
        }
        $shipment = class_exists(Shipped::class) ? Shipped::shipment($order) : ['carrier' => '', 'tracking' => '', 'url' => ''];
        printf('<section class="mizzey-order-tracking"><h2>%s</h2>', esc_html__('Tracking', 'mizzey-site'));
        if ($shipment['carrier'] !== '' && $shipment['tracking'] !== '') {
            printf(
                '<p>%s</p>',
                esc_html(sprintf(
                    /* translators: 1: carrier name, 2: tracking number. */
                    __('Carrier: %1$s. Tracking number: %2$s.', 'mizzey-site'),
                    $shipment['carrier'],
                    $shipment['tracking']
                ))
            );
            if ($shipment['url'] !== '') {
                printf('<p><a href="%s">%s</a></p>', esc_url($shipment['url']), esc_html__('Track your shipment', 'mizzey-site'));
            }
        }
        echo '<ol class="mizzey-order-progress">';
        foreach (self::progress($order) as $step) {
            printf('<li class="mizzey-order-progress-%s">%s <time>%s</time></li>', esc_attr($step['status']), esc_html($step['name']), esc_html((string) wp_date(wc_date_format(), $step['at'])));
        }
        echo '</ol></section>';
    }


    /**
     * @param mixed $items
     * @return mixed
     */
    public static function menu($items)
    {
        if (! is_array($items)) {
            return $items;
        }
        $out = [];
        foreach ($items as $key => $label) {
            $out[$key] = $label;
            if ($key === 'orders') {
                $out[self::WISHLIST] = __('Wishlist', 'mizzey-site');
            }
        }

        return isset($out[self::WISHLIST]) ? $out : $out + [self::WISHLIST => __('Wishlist', 'mizzey-site')];
    }

}

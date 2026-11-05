<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Admin;

defined('ABSPATH') || exit;

/**
 * Two third-party plugins restyle WordPress's own admin far beyond their screens (ADM-159: the administration presented
 * consistently across the standard store screens). Paymob's `admin.css` turns every status filter row (`.subsubsub`)
 * into a card with pills; it is loaded on the order screens for Paymob's panel on a single order, and the order list
 * gets the card. Bosta's `main.css` is loaded on every admin screen: every page title 30px, every bulk-action button
 * a large green one, every list screen's filters stacked one per row (`.tablenav .actions` at full width), every table
 * header and the search box resized. Neither plugin is edited: this keeps each sheet to the screens it is for and
 * puts WordPress's own look back where the sheet must stay.
 *
 * - Paymob's sheet is removed from the order list; its own screens and the single order keep it.
 * - Bosta's sheets stay on its own screens (its menu pages and its section of the shipping settings) and the order
 *   screens, where its buttons, filters and order preview are; elsewhere they are removed. On the order screens a short sheet of ours, printed after Bosta's, gives the title and bulk actions back
 *   to WordPress, sets Bosta's buttons and filters in WordPress's button and field style on one row, and keeps our own
 *   "Cash collected, not remitted" box the size of a checkbox.
 *
 * Register ids: ADM-159. See specs/063-admin-plugin-styles.
 */
final class PluginAdminStyles
{
    public const HANDLE = 'mizzey-admin-plugin-styles';

    /** Paymob's admin sheet. */
    public const PAYMOB = 'paymob-admin-css';

    /** Bosta's admin sheets, as its plugin names them. */
    public const BOSTA = ['myCSS', 'pickupsCSS', 'flexshipCSS', 'fulfillmentCSS'];

    public static function register(): void
    {
        // Bosta enqueues on `admin_print_styles` at 10 and WordPress prints at 20: in between, everything is enqueued.
        add_action('admin_print_styles', [self::class, 'scope'], 15);
    }

    public static function scope(): void
    {
        $screen = self::screen();
        if ($screen === 'order-list') {
            wp_dequeue_style(self::PAYMOB);
        }
        if ($screen === 'other') {
            foreach (self::BOSTA as $handle) {
                wp_dequeue_style($handle);
            }
            return;
        }
        if ($screen === 'bosta') {
            return;
        }
        $deps = wp_style_is('myCSS', 'enqueued') ? ['myCSS'] : [];
        wp_register_style(self::HANDLE, false, $deps, '1');
        wp_enqueue_style(self::HANDLE);
        wp_add_inline_style(self::HANDLE, self::css());
    }

    /** `order-list`, `order-edit`, `bosta` (Bosta's own screens) or `other`. */
    public static function screen(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which screen this is.
        $page   = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $action  = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
        $section = isset($_GET['section']) ? sanitize_key((string) wp_unslash($_GET['section'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $current = function_exists('get_current_screen') ? get_current_screen() : null;
        $id      = $current instanceof \WP_Screen ? $current->id : '';
        if (str_starts_with($page, 'bosta') || ($page === 'wc-settings' && str_starts_with($section, 'bosta'))) {
            return 'bosta';
        }
        if ($page === 'wc-orders' || in_array($id, ['woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order'], true)) {
            return in_array($action, ['edit', 'new'], true) || $id === 'shop_order' ? 'order-edit' : 'order-list';
        }

        return 'other';
    }

    /** WordPress's own button: its admin colour, outlined, as the toolbar's other buttons are. */
    private static function css(): string
    {
        $button = 'font-size:13px;font-weight:500;line-height:30px;min-height:32px;height:auto;padding:0 12px;'
            . 'border:1px solid var(--wp-admin-theme-color,#2271b1);border-radius:2px;'
            . 'background:transparent;color:var(--wp-admin-theme-color,#2271b1);box-shadow:none;';

        return '/* mizzey: ADM-159, specs/063 */'
            . '.wrap .wp-heading-inline{font-size:23px;font-weight:400;line-height:1.3;color:inherit}'
            . '.wp-core-ui .wrap .bulkactions .button{' . $button . '}'
            . '.wrap .tablenav .actions{float:left;width:auto;margin:0;padding:0 8px 0 0}'
            . '.wrap .tablenav .no-pages{display:none!important}'
            . '.wrap .widefat thead tr th,.wrap .widefat tfoot tr th{color:inherit;font-size:14px;font-weight:400}'
            . '.search-box input[name="s"]{min-height:32px}'
            . '.wp-core-ui .search-box .button{height:auto;min-height:32px;font-size:13px}'
            . '.wrap .tablenav .bosta_custom_buttons_div{justify-content:flex-start;gap:8px;margin:8px 0;width:100%;clear:both}'
            . '.wrap .tablenav .bosta_custom_buttons_div .rightDiv,.wrap .tablenav .bosta_custom_buttons_div .leftDiv{gap:8px}'
            . '.wrap .tablenav .bosta_custom_button.orders-button,.wrap .tablenav .bosta_custom_button.danger-button,'
            . '.wrap .tablenav .bosta-filter-button,.wrap .tablenav .bosta-clear-button{' . $button . 'cursor:pointer}'
            . '.wrap .tablenav .bosta_custom_buttons_div .refreshIcon{display:none}'
            . '.wrap .tablenav .bosta_custom_p{font-size:13px;font-weight:400;line-height:32px;margin:0 6px 0 0}'
            . '.wrap .tablenav .bosta_status_search_tags{width:auto;margin:0 12px 8px 0;font-weight:400}'
            . '.wrap .tablenav .bosta-filter-select{min-height:32px;padding:0 24px 0 8px;border-radius:2px;font-size:13px}'
            . '.wrap .tablenav .mizzey-unremitted{display:inline-flex;align-items:center;gap:4px;margin:0 6px 0 0;line-height:32px}'
            . '.wrap .tablenav .mizzey-unremitted input[type=checkbox]{height:16px;min-height:0;width:16px;margin:0}';
    }
}

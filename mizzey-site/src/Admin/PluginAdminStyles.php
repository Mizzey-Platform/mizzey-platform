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
        $file = dirname(__DIR__, 2) . '/assets/admin-plugin-styles.css';
        wp_enqueue_style(self::HANDLE, plugins_url('mizzey-site/assets/admin-plugin-styles.css'), $deps, is_readable($file) ? (string) filemtime($file) : '1');
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
}

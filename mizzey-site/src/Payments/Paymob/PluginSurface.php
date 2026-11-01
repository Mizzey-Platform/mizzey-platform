<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Payments\Paymob;

defined('ABSPATH') || exit;

/**
 * Only what the store uses of Paymob's plugin is reachable. The store takes card payments on Paymob's hosted page
 * (PAY-03). The plugin also answers anonymous requests for features the store does not run (the embedded "pixel"
 * card form and its discounts, a buy-now-pay-later widget, its own add-to-cart), and staff requests for its
 * settings. The first are closed to everyone; the second need staff who manage the store. Both are answered before the
 * plugin's own code runs, on WordPress's `admin_init`, which `admin-ajax.php` fires before any action's handler.
 *
 * Register ids: PAY-02, PAY-03, PAY-13 (D-21 security). See specs/028-payments.
 */
final class PluginSurface
{
    /** Features the store does not run: closed to everyone. */
    public const CLOSED = [
        'create_order', 'update_pixel_data', 'get_order_id_from_session', 'paymob_apply_discount',
        'paymob_clear_discount', 'paymob_sync_pixel_intention', 'paymob_aw_select_plan', 'paymob_aw_clear_plan',
        'paymob_aw_cart_amount', 'add_to_cart',
    ];

    /** The plugin's settings: only staff who manage the store. */
    public const STAFF = [
        'connect_paymob_account', 'manual_setup_save_keys', 'disconnect_save_keys', 'change_mode_save',
        'reset_paymob_gateways', 'save_paymob_gateway_order', 'delete_gateway', 'webhook_url', 'toggle_gateway',
        'paymob_log_error',
    ];

    /**
     * How a refused request ends. Tests replace it to observe the refusal instead of exiting.
     *
     * @var callable(string):void|null
     */
    public static $refuse = null;

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'guard'], 0);
    }

    public static function guard(): void
    {
        if (! wp_doing_ajax()) {
            return;
        }
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
        if (in_array($action, self::CLOSED, true)) {
            self::deny($action);
        } elseif (in_array($action, self::STAFF, true) && ! current_user_can('manage_woocommerce')) {
            self::deny($action);
        }
    }

    private static function deny(string $action): void
    {
        wc_get_logger()->notice(sprintf('plugin action refused: %s (user %d)', $action, get_current_user_id()), ['source' => 'mizzey-payments']);
        if (is_callable(self::$refuse)) {
            (self::$refuse)($action);
            return;
        }
        wp_send_json_error(['code' => 'not_available'], 403);
    }
}

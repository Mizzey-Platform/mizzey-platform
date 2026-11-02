<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Roles;

defined('ABSPATH') || exit;

/**
 * Refunding is a capability of its own (D-10, D-11 item 7). WooCommerce allows a refund to anyone who may edit orders
 * (`edit_shop_orders`, in `WC_AJAX::refund_line_items()` and `delete_refund()` and the REST refunds controllers,
 * WooCommerce 11.1.0); this class asks for `mizzey_refund_orders` first, on every staff route, so the owner can take
 * refunds away from a role without rebuilding it.
 *
 * The capability is given once to the Administrator (Owner / Super Admin, ROLE-08) and the Shop manager (Admin,
 * ROLE-07, "refunds where the launch refund flow permits"); a later removal by the owner is kept. Refunds the store's
 * own code makes (a payment provider's callback, a cancellation) do not pass through these routes and are unaffected.
 *
 * Register ids: ROLE-07, ROLE-08. See specs/013-launch-roles, FR-001 to FR-003.
 */
final class RefundCapability
{
    public const CAP = 'mizzey_refund_orders';

    /** Set once the capability has been given, so it is never given again after the owner removes it. */
    public const GRANTED = 'mizzey_refund_capability_granted';

    /** WooCommerce's REST routes that make or delete a refund (v1 to v3); the preview makes none. */
    private const REST_ROUTE = '#^/wc/v[0-9]+/orders/[0-9]+/refunds(/[0-9]+)?$#';

    public static function register(): void
    {
        add_action('init', [self::class, 'grant'], 5);
        add_action('wp_ajax_woocommerce_refund_line_items', [self::class, 'guardAjax'], 0);
        add_action('wp_ajax_woocommerce_delete_refund', [self::class, 'guardAjax'], 0);
        add_filter('rest_pre_dispatch', [self::class, 'guardRest'], 10, 3);
    }

    public static function grant(): void
    {
        if (get_option(self::GRANTED) === '1') {
            return;
        }
        foreach (['administrator', 'shop_manager'] as $name) {
            get_role($name)?->add_cap(self::CAP);
        }
        update_option(self::GRANTED, '1', false);
    }

    /** The order screen's refund and delete-refund actions, before WooCommerce's own handlers. */
    public static function guardAjax(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_send_json_error(['error' => self::refusal()], 403);
        }
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function guardRest($result, $server, $request)
    {
        if ($result !== null || ! $request instanceof \WP_REST_Request || ! is_user_logged_in()) {
            return $result;
        }
        $changes = in_array($request->get_method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($changes && preg_match(self::REST_ROUTE, $request->get_route()) === 1 && ! current_user_can(self::CAP)) {
            return new \WP_Error('mizzey_refund_forbidden', self::refusal(), ['status' => rest_authorization_required_code()]);
        }

        return $result;
    }

    private static function refusal(): string
    {
        return __('You are not allowed to make or delete refunds.', 'mizzey-site');
    }
}

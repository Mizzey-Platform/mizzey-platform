<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Orders;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined('ABSPATH') || exit;

/**
 * Validates every order status change against the model, records when an order first entered each status, and keeps
 * the reason for a cancellation.
 *
 * Every staff entry point is checked before WooCommerce sets the status, so a refusal is a plain message and nothing
 * else in the request is lost: the order screen, the list's status buttons, bulk actions and the REST API. The guard
 * before every order save catches any other code path. It validates against the status in storage, not the copy the
 * object was loaded with, so an out-of-date object cannot overwrite a newer status. A refused move gets its stored
 * status and dates back and WooCommerce's pending transition is cleared, so no status hook, transition note or email
 * follows and the rest of the save goes ahead. (`woocommerce_order_edit_status`, which WooCommerce fires inside a
 * manual `set_status()` before saving, cannot be recalled; the staff entry points are checked first for that reason.)
 * WooCommerce 11.1.0 offers no public way to drop a pending transition, so `WC_Order::$status_transition` is cleared by
 * reflection; t51 fails if that stops working.
 *
 * Register ids: ADM-86, ADM-87, ORD-08. See specs/010-order-status-model, FR-002, FR-003, FR-007, FR-008.
 */
final class TransitionGuard
{
    private const NOTICES = 'mizzey_order_notices_';

    /** @var array<int,string> Reasons given on the order screen or over REST, by order id. */
    private static array $reasons = [];

    /** @var \WeakMap<\WC_Order,string>|null Reasons given for an order REST is creating, which has no id yet. */
    private static ?\WeakMap $newReasons = null;

    public static function register(): void
    {
        add_action('woocommerce_process_shop_order_meta', [self::class, 'screen'], 1, 1);
        add_action('wp_ajax_woocommerce_mark_order_status', [self::class, 'listButton'], 1);
        add_filter('woocommerce_bulk_action_ids', [self::class, 'bulk'], 10, 3);
        add_filter('woocommerce_rest_pre_insert_shop_order_object', [self::class, 'rest'], 10, 3);
        add_action('woocommerce_before_order_object_save', [self::class, 'beforeSave'], PHP_INT_MAX, 1);
        add_filter('woocommerce_payment_complete_order_status', [self::class, 'paymentStatus'], 20, 3);
        add_action('admin_notices', [self::class, 'notices']);
    }

    /**
     * The order screen. Runs after WooCommerce's own nonce check and before its order data save (priority 40).
     */
    public static function screen(int $orderId): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified woocommerce_meta_nonce before this action.
        if (! isset($_POST['order_status'])) {
            return;
        }
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }
        $from   = self::storedStatus($order);
        $to     = StatusModel::bare(wc_clean(wp_unslash((string) $_POST['order_status'])));
        $reason = sanitize_textarea_field(wp_unslash((string) ($_POST[CancellationField::FIELD] ?? '')));
        if ($from === $to) {
            return;
        }
        $problem = self::check($order, $from, $to, $reason, true);
        if ($problem !== null) {
            $_POST['order_status'] = 'wc-' . ($from !== '' ? $from : 'pending');
            self::notice($problem);

            return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ($to === 'cancelled') {
            self::$reasons[$orderId] = $reason;
        }
    }

    /**
     * The status buttons in the order list (`woocommerce_mark_order_status`). Checked before WooCommerce's own handler
     * runs, with the same nonce and capability; a refused move goes back to the list with the reason.
     */
    public static function listButton(): void
    {
        if (! current_user_can('edit_shop_orders') || ! isset($_GET['status'], $_GET['order_id'])) {
            return;
        }
        check_admin_referer('woocommerce-mark-order-status');
        $problem = self::listButtonProblem(absint(wp_unslash($_GET['order_id'])), sanitize_text_field(wp_unslash($_GET['status'])));
        if ($problem === null) {
            return;
        }
        self::notice($problem);
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=wc-orders'));
        exit;
    }

    public static function listButtonProblem(int $orderId, string $status): ?string
    {
        $order = wc_get_order($orderId);

        return $order instanceof \WC_Order ? self::check($order, self::storedStatus($order), StatusModel::bare($status), '', true) : null;
    }

    /**
     * Bulk "Change status to" in the order list. A cancellation is never made in bulk, because no reason can be given.
     *
     * @param array<int|string> $ids
     * @return array<int>
     */
    public static function bulk(array $ids, string $action, string $type): array
    {
        $ids = array_map('absint', $ids);
        if ($type !== 'order' || ! str_starts_with($action, 'mark_')) {
            return $ids;
        }
        $to      = StatusModel::bare(substr($action, 5));
        $kept    = [];
        $refused = [];
        foreach ($ids as $id) {
            $order = wc_get_order($id);
            if ($order instanceof \WC_Order && self::check($order, self::storedStatus($order), $to, '', true) !== null) {
                $refused[] = $id;
                continue;
            }
            $kept[] = $id;
        }
        if ($refused !== []) {
            self::notice(sprintf(
                /* translators: 1: the target status, 2: order numbers. */
                __('Not changed to %1$s, because the move is not allowed or a cancellation needs its reason on the order: %2$s.', 'mizzey-site'),
                StatusModel::name($to),
                implode(', ', $refused)
            ));
        }

        return $kept;
    }

    /**
     * The REST API, for updates and for creation. A forbidden move, or a staff cancellation without
     * `cancellation_reason`, is a 400 before anything is saved. A staff change is marked manual, so the timeline names
     * the person.
     *
     * @param \WC_Order|\WP_Error $order
     * @return \WC_Order|\WP_Error
     */
    public static function rest($order, \WP_REST_Request $request, bool $creating)
    {
        if (! $order instanceof \WC_Order || empty($request['status'])) {
            return $order;
        }
        $from = $creating ? '' : self::storedStatus($order);
        $to   = StatusModel::bare((string) $request['status']);
        if ($from === $to) {
            return $order;
        }
        $staff   = current_user_can('edit_shop_orders');
        $reason  = sanitize_textarea_field((string) ($request['cancellation_reason'] ?? ''));
        $problem = self::check($order, $from, $to, $reason, $staff);
        if ($problem !== null) {
            return new \WP_Error('mizzey_order_status_refused', $problem, ['status' => 400]);
        }
        if ($staff) {
            $request->set_param('manual_update', true);
        }
        if ($to === 'cancelled') {
            if ($creating) {
                self::newReasons()[$order] = $reason;
            } else {
                self::$reasons[$order->get_id()] = $reason;
            }
        }

        return $order;
    }

    /**
     * Before every order save: refuse a forbidden move without side effects, store a cancellation's reason, complete
     * the timeline when WooCommerce leaves out the transition note, and record the first entry into the new status.
     */
    public static function beforeSave(\WC_Order $order): void
    {
        $changes = $order->get_changes();
        $isNew   = ! $order->get_id();
        $to      = array_key_exists('status', $changes) ? (string) $changes['status'] : ($isNew ? $order->get_status() : null);
        if ($to === null) {
            return;
        }
        $data = $order->get_data();
        $from = $isNew ? '' : self::storedStatus($order);
        // An out-of-date copy (loaded before another change was saved) may not change the status at all: its pending
        // transition would describe a move that never happened.
        $loaded = (string) $data['status'] !== '' ? (string) $data['status'] : 'pending';
        if (! $isNew && $loaded !== $from) {
            self::refuse($order, $from, $to, $data, __('Status change refused: the order changed since this copy was loaded. Reload it and try again.', 'mizzey-site'));

            return;
        }
        if ($from === $to) {
            return;
        }
        $draft = in_array($from, StatusModel::STARTING, true);
        if (in_array($to, StatusModel::STARTING, true)) {
            // Only a new order or a draft may be a draft; an order with a real status never goes back to one.
            if (! $draft) {
                self::refuse($order, $from, $to, $data);
            }

            return;
        }
        if (! $draft && ! StatusModel::allowed($from, $to)) {
            self::refuse($order, $from, $to, $data);

            return;
        }
        $route = StatusModel::routeProblem($order, $from, $to);
        if ($route !== null) {
            self::refuse($order, $from, $to, $data, $route);

            return;
        }
        $transition = self::transition($order);
        if ($to === 'cancelled' && ! self::recordCancellation($order, $from, $to, $data, $transition)) {
            return;
        }
        if (! $draft && is_array($transition) && $transition['note'] === false) {
            // WooCommerce's payment completion passes no transition note; the timeline still records the change.
            $order->add_order_note(sprintf(
                /* translators: 1: old status, 2: new status. */
                __('Order status changed from %1$s to %2$s.', 'mizzey-site'),
                StatusModel::name($from),
                StatusModel::name($to)
            ));
        }
        if ($order->get_meta(StatusModel::metaKey($to), true) === '') {
            $order->update_meta_data(StatusModel::metaKey($to), (string) time());
        }
    }

    public static function notices(): void
    {
        if (! current_user_can('edit_shop_orders')) {
            return;
        }
        $key      = self::NOTICES . get_current_user_id();
        $messages = get_transient($key);
        if (! is_array($messages) || $messages === []) {
            return;
        }
        delete_transient($key);
        foreach ($messages as $message) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html((string) $message));
        }
    }

    /** Why the move is refused for this order, or null when it is allowed. */
    public static function check(\WC_Order $order, string $from, string $to, string $reason, bool $staff): ?string
    {
        $problem = self::problem($from, $to, $reason, $staff);

        return $problem ?? StatusModel::routeProblem($order, $from, $to);
    }

    /** Why the move is refused by the table or for a missing reason, or null when it is allowed. */
    public static function problem(string $from, string $to, string $reason, bool $staff): ?string
    {
        if (! StatusModel::allowed($from, $to)) {
            return sprintf(
                /* translators: 1: current status, 2: requested status, 3: allowed statuses. */
                __('Order status not changed: %1$s cannot move to %2$s. Allowed next: %3$s.', 'mizzey-site'),
                StatusModel::name($from),
                StatusModel::name($to),
                implode(', ', array_map([StatusModel::class, 'name'], StatusModel::next($from))) ?: __('none', 'mizzey-site')
            );
        }
        if ($to === 'cancelled' && $staff && trim($reason) === '') {
            return __('Order status not changed: give the reason for the cancellation.', 'mizzey-site');
        }

        return null;
    }

    /**
     * The status in storage, not the one the object was loaded with. An order created without an explicit status is
     * stored as Pending Payment.
     */
    public static function storedStatus(\WC_Order $order): string
    {
        if (! $order->get_id()) {
            return '';
        }
        global $wpdb;
        $stored = OrderUtil::custom_orders_table_usage_is_enabled()
            ? $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}wc_orders WHERE id = %d", $order->get_id())) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            : get_post_status($order->get_id());
        $stored = is_string($stored) && $stored !== '' ? StatusModel::bare($stored) : (string) $order->get_data()['status'];

        return $stored !== '' ? $stored : 'pending';
    }

    /**
     * Stores the reason for a cancellation. A manual cancellation without a reason is refused; any other records the
     * change's own note, or that the system made it.
     *
     * @param array<string,mixed>                                $data       The order as loaded.
     * @param array{from:string,to:string,note:mixed,manual:bool}|false $transition
     */
    private static function recordCancellation(\WC_Order $order, string $from, string $to, array $data, $transition): bool
    {
        $manual = is_array($transition) && ! empty($transition['manual']);
        // REST creation saves the new order once, giving it an id, before it sets the status: look in both places.
        $reason = self::$reasons[$order->get_id()] ?? (self::newReasons()[$order] ?? '');
        unset(self::$reasons[$order->get_id()], self::newReasons()[$order]);
        if ($reason === '' && $manual) {
            self::refuse($order, $from, $to, $data, __('Cancellation refused: give the reason for the cancellation.', 'mizzey-site'));

            return false;
        }
        if ($reason === '') {
            $note   = is_array($transition) && is_string($transition['note'] ?? null) ? trim(wp_strip_all_tags($transition['note'])) : '';
            $reason = $note !== '' ? $note : __('Cancelled by the system.', 'mizzey-site');
        }
        $order->update_meta_data(StatusModel::REASON_META, $reason);
        $order->update_meta_data(StatusModel::CANCELLED_BY_META, (string) ($manual ? get_current_user_id() : 0));

        return true;
    }

    /**
     * Messages for the person's next admin page.
     */
    private static function notice(string $message): void
    {
        $key        = self::NOTICES . get_current_user_id();
        $messages   = get_transient($key);
        $messages   = is_array($messages) ? $messages : [];
        $messages[] = $message;
        set_transient($key, array_values(array_unique($messages)), 5 * MINUTE_IN_SECONDS);
    }

    /**
     * Puts back the stored status and the dates WooCommerce sets on a status change, drops the pending transition,
     * and notes the refusal on the order.
     *
     * @param array<string,mixed> $data The order as loaded.
     */
    private static function refuse(\WC_Order $order, string $from, string $to, array $data, ?string $why = null): void
    {
        $dates = self::storedDates($order, $data);
        $order->set_status($from !== '' ? $from : 'pending');
        $order->set_date_paid($dates['date_paid']);
        $order->set_date_completed($dates['date_completed']);
        self::clearTransition($order);
        $message = $why ?? sprintf(
            /* translators: 1: current status, 2: requested status. */
            __('Status change from %1$s to %2$s refused: not an allowed move.', 'mizzey-site'),
            StatusModel::name($from),
            StatusModel::name($to)
        );
        if ($order->get_id()) {
            $order->add_order_note($message);
        }
        wc_get_logger()->warning($message, ['source' => 'mizzey-order-status', 'order_id' => $order->get_id()]);
    }

    /**
     * WooCommerce's cash-on-delivery gateway asks for Delivered when a payment is completed. A cash order is
     * Confirmed at that point; it reaches Delivered through COD Collected.
     *
     * @param mixed $status
     * @param mixed $orderId
     * @param mixed $order
     * @return mixed
     */
    public static function paymentStatus($status, $orderId = 0, $order = null)
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order((int) $orderId);
        if ($status === 'completed' && $order instanceof \WC_Order && StatusModel::isCash($order)) {
            return 'processing';
        }

        return $status;
    }

    /**
     * The paid and completed dates as stored, so a refused move never overwrites a date another change saved.
     *
     * @param array<string,mixed> $data The order as loaded, used for a new order.
     * @return array{date_paid:int|\WC_DateTime|null,date_completed:int|\WC_DateTime|null}
     */
    private static function storedDates(\WC_Order $order, array $data): array
    {
        if (! $order->get_id()) {
            return ['date_paid' => $data['date_paid'] ?? null, 'date_completed' => $data['date_completed'] ?? null];
        }
        global $wpdb;
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT date_paid_gmt, date_completed_gmt FROM {$wpdb->prefix}wc_order_operational_data WHERE order_id = %d", $order->get_id()), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $paid      = is_array($row) && ! empty($row['date_paid_gmt']) ? strtotime($row['date_paid_gmt'] . ' UTC') : null;
            $completed = is_array($row) && ! empty($row['date_completed_gmt']) ? strtotime($row['date_completed_gmt'] . ' UTC') : null;
        } else {
            $paid      = get_post_meta($order->get_id(), '_date_paid', true);
            $completed = get_post_meta($order->get_id(), '_date_completed', true);
            $paid      = is_numeric($paid) ? (int) $paid : null;
            $completed = is_numeric($completed) ? (int) $completed : null;
        }

        return ['date_paid' => $paid ?: null, 'date_completed' => $completed ?: null];
    }

    /** @return \WeakMap<\WC_Order,string> */
    private static function newReasons(): \WeakMap
    {
        return self::$newReasons ??= new \WeakMap();
    }

    /** @return array{from:string,to:string,note:mixed,manual:bool}|false */
    private static function transition(\WC_Order $order)
    {
        $property = new \ReflectionProperty(\WC_Order::class, 'status_transition');

        return $property->getValue($order);
    }

    private static function clearTransition(\WC_Order $order): void
    {
        $property = new \ReflectionProperty(\WC_Order::class, 'status_transition');
        $property->setValue($order, false);
    }
}

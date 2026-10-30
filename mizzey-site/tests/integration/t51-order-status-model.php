<?php
/**
 * t51, the order status model (specs/010-order-status-model, #248).
 *
 * In-process, on the runtime's order storage (HPOS), through WooCommerce's real handlers: the order data meta box save
 * preceded by Mizzey's priority-1 check, the bulk-action id filter, `rest_do_request` on `/wc/v3/orders`, and
 * `update_status`, `set_status` plus `save`, `payment_complete` and refunds. Every order, product and user it makes is
 * removed when it finishes; settings are not changed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Orders\StatusModel;
use MizzeySite\Orders\TransitionGuard;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';

/** The order as stored, not an in-memory copy. */
function t51_fresh( int $id ): \WC_Order {
	wp_cache_flush();
	$order = wc_get_order( $id );
	if ( ! $order instanceof \WC_Order ) {
		throw new \RuntimeException( "order {$id} is gone" );
	}
	return $order;
}

/** @return list<object> The order's notes, oldest first. */
function t51_notes( int $id ): array {
	return array_reverse( wc_get_order_notes( array( 'order_id' => $id ) ) );
}

/** An order for one unit of the product, moved to $status through the allowed path. */
function t51_order( Scenario $s, \WC_Product $product, string $status = 'processing', string $method = 'bacs' ): \WC_Order {
	$order = wc_create_order();
	$order->add_product( $product, 1 );
	$order->set_payment_method( $method );
	$order->calculate_totals();
	$order->save();
	$s->track_order( $order->get_id() );
	$path = array( 'processing', StatusModel::PREPARING, StatusModel::SHIPPED, StatusModel::OUT_FOR_DELIVERY );
	foreach ( $path as $step ) {
		if ( 'pending' === $status ) {
			break;
		}
		$order->update_status( $step );
		if ( $step === $status ) {
			break;
		}
	}
	return t51_fresh( $order->get_id() );
}

/** Save the order screen the way WooCommerce does: Mizzey's priority-1 check, then WooCommerce's own data save. */
function t51_screen_save( \WC_Order $order, string $to, string $reason = '' ): void {
	require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-order-data.php';
	$_POST = array(
		'order_status'                                  => 'wc-' . $to,
		'_payment_method'                               => $order->get_payment_method(),
		'customer_user'                                 => (string) $order->get_customer_id(),
		'order_date'                                    => $order->get_date_created()->date( 'Y-m-d' ),
		'order_date_hour'                               => $order->get_date_created()->date( 'H' ),
		'order_date_minute'                             => $order->get_date_created()->date( 'i' ),
		'order_date_second'                             => $order->get_date_created()->date( 's' ),
		\MizzeySite\Orders\CancellationField::FIELD     => $reason,
	);
	do_action( 'woocommerce_process_shop_order_meta', $order->get_id(), $order );
	\WC_Meta_Box_Order_Data::save( $order->get_id() );
	$_POST = array();
}

/** @return array{status:int,code:string} */
function t51_rest( int $id, array $body ): array {
	$request = new \WP_REST_Request( 'PUT', '/wc/v3/orders/' . $id );
	$request->set_body_params( $body );
	$response = rest_do_request( $request );
	$data     = $response->get_data();
	return array( 'status' => $response->get_status(), 'code' => is_array( $data ) ? (string) ( $data['code'] ?? '' ) : '' );
}

run(
	new Scenario( 't51-order-status-model', 'ORD-01 to ORD-14, ADM-86, ADM-87: the order status model' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous_user = get_current_user_id();
		$s->on_finish( static function () use ( $previous_user ) {
			wp_set_current_user( $previous_user );
			delete_transient( 'mizzey_order_notices_' . get_current_user_id() );
		} );
		$s->note( 'Order storage: ' . ( 'yes' === get_option( 'woocommerce_custom_orders_table_enabled' ) ? 'HPOS' : 'legacy' ) );

		$product = new \WC_Product_Simple();
		$product->set_name( 't51 product' );
		$product->set_regular_price( '100' );
		$product->set_status( 'publish' );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 10 );
		$product->save();
		$s->track_post( $product->get_id() );

		list( $staff ) = two_factor_user( $s, 'shop_manager' );
		wp_set_current_user( $staff->ID );
		$notices = static fn (): array => (array) get_transient( 'mizzey_order_notices_' . $staff->ID );
		delete_transient( 'mizzey_order_notices_' . $staff->ID );

		// AC-1: the eleven states, their register names in both languages, and which count as paid.
		$statuses = wc_get_order_statuses();
		$names    = array(
			'pending'          => 'Pending Payment',
			'failed'           => 'Payment Failed',
			'processing'       => 'Confirmed',
			'preparing'        => 'Preparing',
			'shipped'          => 'Shipped',
			'out-for-delivery' => 'Out for Delivery',
			'cod-collected'    => 'COD Collected, Awaiting Remittance',
			'completed'        => 'Delivered',
			'cancelled'        => 'Cancelled',
			'refunded'         => 'Refunded',
			'delivery-failed'  => 'Delivery Failed / Returned to Origin',
		);
		foreach ( $names as $key => $name ) {
			$check( ( $statuses[ 'wc-' . $key ] ?? '' ) === $name, "AC-1 {$key} is named \"{$name}\"" );
		}
		$check( null !== get_post_status_object( 'wc-delivery-failed' ) && null !== get_post_status_object( 'wc-cod-collected' ), 'AC-1 the new statuses are registered with WordPress' );
		switch_to_locale( 'ar' );
		$arabic = array_map( 'wc_get_order_status_name', array( 'pending', 'failed', 'processing', 'preparing', 'completed', 'delivery-failed' ) );
		restore_previous_locale();
		$check( array( 'بانتظار الدفع', 'فشل الدفع', 'تم التأكيد', 'قيد التجهيز', 'تم التوصيل', 'تعذّر التوصيل / أُعيد إلى المصدر' ) === $arabic, 'AC-1 the names are translated into Arabic (' . implode( ' | ', $arabic ) . ')' );
		$customer = static fn () => true;
		add_filter( 'mizzey_order_status_customer_view', $customer );
		$customer_label = wc_get_order_status_name( 'cod-collected' );
		remove_filter( 'mizzey_order_status_customer_view', $customer );
		$check( 'Delivered' === $customer_label, "AC-5 a customer sees COD Collected as Delivered ({$customer_label})" );
		$paid = wc_get_is_paid_statuses();
		$check( array() === array_diff( StatusModel::PAID, $paid ) && ! in_array( StatusModel::DELIVERY_FAILED, $paid, true ), 'FR-011 Preparing, Shipped, Out for Delivery and COD Collected are paid; Delivery Failed is not' );
		$reported = apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold' ) );
		$check( array() === array_diff( StatusModel::PAID, $reported ), 'FR-011 the legacy sales reports count them too' );

		// AC-2, AC-7, AC-8, FR-010: a prepaid order walked to Delivered by staff: attributed, timed, stock untouched.
		$walk   = t51_order( $s, $product, 'processing' );
		$stock  = (int) Scenario::fresh_product( $product->get_id() )->get_stock_quantity();
		$timely = true;
		foreach ( array( StatusModel::PREPARING, StatusModel::SHIPPED, StatusModel::OUT_FOR_DELIVERY, 'completed' ) as $to ) {
			$before = time();
			$check( true === $walk->update_status( $to, '', true ) && $to === t51_fresh( $walk->get_id() )->get_status(), "AC-2 staff move to {$to} accepted" );
			$after  = time();
			$walk   = t51_fresh( $walk->get_id() );
			$at     = StatusModel::enteredAt( $walk, $to );
			$timely = $timely && null !== $at && $at >= $before && $at <= $after;
		}
		$check( $timely, 'AC-8 the first entry into each status is recorded at the time of the move' );
		$check( $stock === (int) Scenario::fresh_product( $product->get_id() )->get_stock_quantity(), "FR-010 stock unchanged across the new statuses (stays {$stock})" );
		$staff_notes = array_filter( t51_notes( $walk->get_id() ), static fn ( $n ) => str_contains( $n->content, 'Order status changed' ) && $n->added_by === $staff->display_name );
		$check( count( $staff_notes ) >= 4, 'AC-7 each staff move is in the timeline under the staff member\'s name (' . count( $staff_notes ) . ' notes)' );
		$found = wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'meta_query' => array( array( 'key' => StatusModel::metaKey( 'completed' ), 'compare' => 'EXISTS' ) ) ) );
		$check( in_array( $walk->get_id(), $found, true ), 'AC-8 orders are found by when they were delivered, from order data' );
		$check( StatusModel::isDelivered( $walk ) && null !== StatusModel::deliveredAt( $walk ), 'FR-009 a Delivered order is in the delivered set with its delivery time' );

		// AC-8: re-entering a status keeps its first time.
		$back  = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY );
		$first = (int) StatusModel::enteredAt( $back, StatusModel::OUT_FOR_DELIVERY ) - 100;
		$back->update_meta_data( StatusModel::metaKey( StatusModel::OUT_FOR_DELIVERY ), (string) $first );
		$back->save();
		$back->update_status( StatusModel::SHIPPED );
		$back = t51_fresh( $back->get_id() );
		$back->update_status( StatusModel::OUT_FOR_DELIVERY );
		$check( $first === StatusModel::enteredAt( t51_fresh( $back->get_id() ), StatusModel::OUT_FOR_DELIVERY ), 'AC-8 going out for delivery again keeps the first time' );

		// AC-2, code path: a forbidden move is undone with no hook, no transition note, no date, and a refusal note.
		$code    = t51_order( $s, $product, 'processing' );
		$changed = 0;
		$count   = static function () use ( &$changed ) { ++$changed; };
		add_action( 'woocommerce_order_status_changed', $count );
		$code->set_status( 'completed' );
		$code->set_customer_note( 't51 kept' );
		$code->save();
		remove_action( 'woocommerce_order_status_changed', $count );
		$stored = t51_fresh( $code->get_id() );
		$notes  = wp_list_pluck( t51_notes( $code->get_id() ), 'content' );
		$check( 'processing' === $stored->get_status() && null === $stored->get_date_completed(), 'AC-2 [code] Confirmed to Delivered refused: status and completed date unchanged in storage' );
		$check( 0 === $changed && ! preg_grep( '/to Delivered\./', $notes ), 'AC-2 [code] no status-change hook and no transition note followed the refusal' );
		$check( (bool) preg_grep( '/refused: not an allowed move/', $notes ) && 't51 kept' === $stored->get_customer_note(), 'AC-2 [code] the refusal is noted on the order and the rest of the save went ahead' );

		// AC-2: an out-of-date copy cannot change the status, fire hooks, or erase a date another change saved.
		$stale = t51_order( $s, $product, 'processing' );
		foreach ( array( StatusModel::PREPARING, StatusModel::SHIPPED, 'completed' ) as $to ) {
			t51_fresh( $stale->get_id() )->update_status( $to );
		}
		$completed_at = t51_fresh( $stale->get_id() )->get_date_completed();
		$changed      = 0;
		add_action( 'woocommerce_order_status_changed', $count );
		$stale->set_status( StatusModel::PREPARING );
		$stale->save();
		remove_action( 'woocommerce_order_status_changed', $count );
		$after = t51_fresh( $stale->get_id() );
		$check( 'completed' === $after->get_status() && 0 === $changed, 'AC-2 [code] a stale copy cannot move a Delivered order back to Preparing, and no hook fires' );
		$check( null !== $completed_at && null !== $after->get_date_completed() && $completed_at->getTimestamp() === $after->get_date_completed()->getTimestamp(), 'AC-2 [code] the refused stale copy does not erase the completed date' );
		$twin  = t51_order( $s, $product, 'processing' );
		$other = t51_fresh( $twin->get_id() );
		$other->update_status( StatusModel::PREPARING );
		$changed = 0;
		add_action( 'woocommerce_order_status_changed', $count );
		$twin->set_status( StatusModel::PREPARING );
		$twin->save();
		remove_action( 'woocommerce_order_status_changed', $count );
		$check( 0 === $changed && StatusModel::PREPARING === t51_fresh( $twin->get_id() )->get_status(), 'AC-2 [code] a stale copy asking for the status already stored fires no second transition' );
		$new_cash = new \WC_Order();
		$new_cash->add_product( $product, 1 );
		$new_cash->set_payment_method( 'cod' );
		$new_cash->set_status( 'completed' );
		$new_cash->save();
		$s->track_order( $new_cash->get_id() );
		$check( 'completed' !== t51_fresh( $new_cash->get_id() )->get_status(), 'AC-5 a new cash order cannot be created as Delivered' );
		$check( ! StatusModel::allowed( 'auto-draft', StatusModel::SHIPPED ) && StatusModel::allowed( 'auto-draft', 'processing' ) && StatusModel::allowed( 'checkout-draft', 'pending' ), 'AC-2 a new or draft order starts as Pending Payment, Confirmed, On hold, Payment Failed or Cancelled' );
		$direct = new \WC_Order();
		$direct->add_product( $product, 1 );
		$direct->set_status( StatusModel::SHIPPED );
		$direct->save();
		$s->track_order( $direct->get_id() );
		$check( 'pending' === t51_fresh( $direct->get_id() )->get_status(), 'AC-2 [code] a new order saved directly as Shipped is kept as Pending Payment' );
		$check( null !== TransitionGuard::problem( '', StatusModel::SHIPPED, '', true ) && str_contains( (string) TransitionGuard::problem( '', StatusModel::SHIPPED, '', true ), 'Allowed next: Pending Payment, Confirmed, On hold, Payment Failed, Cancelled.' ), 'AC-2 the message for a new order lists the starting statuses' );
		$cod_confirmed = t51_order( $s, $product, 'processing', 'cod' );
		$check( null === $cod_confirmed->get_date_paid(), 'AC-5 an ordinary cash order is not marked paid when it is confirmed' );
		$cod_confirmed->update_status( StatusModel::PREPARING );
		$cod_confirmed = t51_fresh( $cod_confirmed->get_id() );
		$cod_confirmed->payment_complete( 't51-repeat' );
		t51_fresh( $cod_confirmed->get_id() )->update_status( 'processing' );
		$check( null === t51_fresh( $cod_confirmed->get_id() )->get_date_paid(), 'AC-5 a payment completion on a cash order past payment leaves nothing behind that marks it paid later' );
		$aborted = t51_order( $s, $product, 'pending', 'cod' );
		$abort   = static function () { throw new \Exception( 't51 aborted payment' ); };
		add_action( 'woocommerce_pre_payment_complete', $abort, 99 );
		$aborted->payment_complete( 't51-aborted' );
		remove_action( 'woocommerce_pre_payment_complete', $abort, 99 );
		t51_fresh( $aborted->get_id() )->update_status( 'processing' );
		$check( null === t51_fresh( $aborted->get_id() )->get_date_paid(), 'AC-5 an aborted payment completion leaves nothing behind that marks a cash order paid when it is confirmed' );

		// AC-2: an order with a real status never goes back to a draft.
		$draft = t51_order( $s, $product, 'processing' );
		$draft->set_status( 'checkout-draft' );
		$draft->save();
		$draft = t51_fresh( $draft->get_id() );
		$draft->set_status( 'completed' );
		$draft->save();
		$check( 'processing' === t51_fresh( $draft->get_id() )->get_status(), 'AC-2 [code] a Confirmed order cannot become a draft, so it cannot skip to Delivered through one' );

		// Payment flows are not blocked, and the timeline records them.
		$pay = t51_order( $s, $product, 'pending' );
		$pay->payment_complete( 't51-tx' );
		$pay_notes = wp_list_pluck( t51_notes( $pay->get_id() ), 'content' );
		$check( 'processing' === t51_fresh( $pay->get_id() )->get_status(), 'AC-2 payment_complete moves Pending Payment to Confirmed' );
		$check( (bool) preg_grep( '/^Order status changed from Pending Payment to Confirmed\.$/', $pay_notes ), 'AC-7 the payment-driven change is in the timeline' );
		$cod_paid = t51_order( $s, $product, 'pending', 'cod' );
		$cod_paid->payment_complete( 't51-cod' );
		$cod_paid = t51_fresh( $cod_paid->get_id() );
		$check( 'processing' === $cod_paid->get_status() && null === $cod_paid->get_date_completed(), 'AC-5 completing the payment of a cash order confirms it, instead of the WooCommerce jump to Delivered' );
		$unsaved = wc_create_order();
		$unsaved->add_product( $product, 1 );
		$unsaved->save();
		$s->track_order( $unsaved->get_id() );
		$unsaved->set_status( 'completed' );
		$unsaved->save();
		$check( 'pending' === t51_fresh( $unsaved->get_id() )->get_status(), 'AC-2 [code] a just-created order in memory cannot jump to Delivered' );
		$failed = t51_order( $s, $product, 'pending' );
		$failed->update_status( 'failed' );
		$failed->update_status( 'on-hold' );
		$check( 'on-hold' === t51_fresh( $failed->get_id() )->get_status(), 'AC-2 a failed payment, then on hold, are allowed' );
		$late = t51_order( $s, $product, 'pending' );
		$late->update_status( 'cancelled', __( 'Unpaid order cancelled - time limit reached.', 'woocommerce' ) );
		$late = t51_fresh( $late->get_id() );
		$late->payment_complete( 't51-late' );
		$check( 'processing' === t51_fresh( $late->get_id() )->get_status(), 'AC-2 a payment arriving after cancellation revives the order as Confirmed' );

		// AC-2 and AC-3, order screen.
		$screen = t51_order( $s, $product, 'processing' );
		t51_screen_save( $screen, 'completed' );
		$check( 'processing' === t51_fresh( $screen->get_id() )->get_status(), 'AC-2 [screen] Confirmed to Delivered refused' );
		$check( (bool) preg_grep( '/cannot move to Delivered\. Allowed next: Preparing, Cancelled, Refunded\./', $notices() ), 'AC-2 [screen] the person is told which moves are allowed' );
		t51_screen_save( t51_fresh( $screen->get_id() ), 'cancelled' );
		$check( 'processing' === t51_fresh( $screen->get_id() )->get_status(), 'AC-3 [screen] a cancellation without a reason is refused' );
		t51_screen_save( t51_fresh( $screen->get_id() ), StatusModel::PREPARING );
		$check( StatusModel::PREPARING === t51_fresh( $screen->get_id() )->get_status(), 'AC-2 [screen] Confirmed to Preparing accepted' );
		t51_screen_save( t51_fresh( $screen->get_id() ), 'cancelled', 'Customer asked by phone' );
		$cancelled   = t51_fresh( $screen->get_id() );
		$reason_note = array_filter( t51_notes( $screen->get_id() ), static fn ( $n ) => 'Cancellation reason: Customer asked by phone' === $n->content && $n->added_by === $staff->display_name );
		$check( 'cancelled' === $cancelled->get_status() && 'Customer asked by phone' === $cancelled->get_meta( StatusModel::REASON_META ) && (int) $cancelled->get_meta( StatusModel::CANCELLED_BY_META ) === $staff->ID, 'AC-3 [screen] with a reason: cancelled, the reason and the person stored' );
		$check( count( $reason_note ) === 1, 'AC-3, AC-7 the reason is in the timeline under the staff member\'s name' );
		wc_create_refund( array( 'order_id' => $cancelled->get_id(), 'amount' => $cancelled->get_total(), 'reason' => 't51 refund after cancel' ) );
		$check( 'refunded' === t51_fresh( $cancelled->get_id() )->get_status(), 'AC-6 a paid order that was cancelled can still be refunded in full' );
		delete_transient( 'mizzey_order_notices_' . $staff->ID );

		// AC-2, the order list's status buttons: checked before WooCommerce's handler.
		$check( 1 === has_action( 'wp_ajax_woocommerce_mark_order_status', array( TransitionGuard::class, 'listButton' ) ), 'AC-2 [list button] the check runs before WooCommerce\'s handler' );
		$button = t51_order( $s, $product, 'processing' );
		$check( null !== TransitionGuard::listButtonProblem( $button->get_id(), 'completed' ) && null === TransitionGuard::listButtonProblem( $button->get_id(), StatusModel::PREPARING ), 'AC-2 [list button] Confirmed to Delivered refused, Confirmed to Preparing allowed' );

		// AC-2 and AC-3, bulk: filtered, then applied the way WooCommerce applies it.
		$bulk_ok  = t51_order( $s, $product, 'processing' );
		$bulk_bad = t51_order( $s, $product, StatusModel::PREPARING );
		$kept     = apply_filters( 'woocommerce_bulk_action_ids', array( (string) $bulk_ok->get_id(), (string) $walk->get_id() ), 'mark_' . StatusModel::PREPARING, 'order' );
		foreach ( $kept as $id ) {
			wc_get_order( $id )->update_status( StatusModel::PREPARING, 'Order status changed by bulk edit.', true );
		}
		$check( array( $bulk_ok->get_id() ) === array_values( $kept ) && StatusModel::PREPARING === t51_fresh( $bulk_ok->get_id() )->get_status() && 'completed' === t51_fresh( $walk->get_id() )->get_status(), 'AC-2 [bulk] the Confirmed order moves to Preparing, the Delivered one is left out and unchanged' );
		$kept = apply_filters( 'woocommerce_bulk_action_ids', array( $bulk_bad->get_id() ), 'mark_cancelled', 'order' );
		$check( array() === $kept && StatusModel::PREPARING === t51_fresh( $bulk_bad->get_id() )->get_status(), 'AC-3 [bulk] no cancellation in bulk, because no reason can be given' );
		$check( (bool) preg_grep( '/^Not changed to /', $notices() ), 'AC-2 [bulk] the refused orders are reported' );

		// AC-2, AC-3 and AC-7, REST, for updates and creation.
		$rest = t51_order( $s, $product, 'processing' );
		$r    = t51_rest( $rest->get_id(), array( 'status' => 'completed' ) );
		$check( 400 === $r['status'] && 'mizzey_order_status_refused' === $r['code'] && 'processing' === t51_fresh( $rest->get_id() )->get_status(), "AC-2 [REST] Confirmed to Delivered is a 400 and the order is unchanged (status {$r['status']})" );
		$r = t51_rest( $rest->get_id(), array( 'status' => 'cancelled' ) );
		$check( 400 === $r['status'] && 'processing' === t51_fresh( $rest->get_id() )->get_status(), "AC-3 [REST] a staff cancellation without a reason is a 400 (status {$r['status']})" );
		$r          = t51_rest( $rest->get_id(), array( 'status' => StatusModel::PREPARING ) );
		$rest_notes = array_filter( t51_notes( $rest->get_id() ), static fn ( $n ) => str_contains( $n->content, 'to Preparing.' ) && $n->added_by === $staff->display_name );
		$check( 200 === $r['status'] && count( $rest_notes ) === 1, "AC-7 [REST] an allowed staff move is attributed to the person (status {$r['status']})" );
		$r = t51_rest( $rest->get_id(), array( 'status' => 'cancelled', 'cancellation_reason' => 'Out of stock at the warehouse' ) );
		$check( 200 === $r['status'] && 'Out of stock at the warehouse' === t51_fresh( $rest->get_id() )->get_meta( StatusModel::REASON_META ), "AC-3 [REST] with a reason the cancellation is stored (status {$r['status']})" );
		$create  = new \WP_REST_Request( 'POST', '/wc/v3/orders' );
		$create->set_body_params( array( 'status' => 'cancelled', 'line_items' => array( array( 'product_id' => $product->get_id(), 'quantity' => 1 ) ) ) );
		$created = rest_do_request( $create );
		if ( isset( $created->get_data()['id'] ) ) {
			$s->track_order( (int) $created->get_data()['id'] );
		}
		$check( 400 === $created->get_status(), 'AC-3 [REST] creating an order as Cancelled without a reason is a 400 (status ' . $created->get_status() . ')' );
		$create->set_param( 'cancellation_reason', 'Duplicate order entered by staff' );
		$created = rest_do_request( $create );
		$new_id  = (int) ( $created->get_data()['id'] ?? 0 );
		if ( $new_id ) {
			$s->track_order( $new_id );
		}
		$check( 201 === $created->get_status() && $new_id && 'cancelled' === t51_fresh( $new_id )->get_status() && 'Duplicate order entered by staff' === t51_fresh( $new_id )->get_meta( StatusModel::REASON_META ), 'AC-3 [REST] creating an order as Cancelled with a reason stores it (status ' . $created->get_status() . ')' );
		$shipped_new = new \WP_REST_Request( 'POST', '/wc/v3/orders' );
		$shipped_new->set_body_params( array( 'status' => StatusModel::SHIPPED, 'line_items' => array( array( 'product_id' => $product->get_id(), 'quantity' => 1 ) ) ) );
		$made = rest_do_request( $shipped_new );
		if ( isset( $made->get_data()['id'] ) ) {
			$s->track_order( (int) $made->get_data()['id'] );
		}
		$check( 400 === $made->get_status(), 'AC-2 [REST] creating an order directly as Shipped is a 400, not a 201 the guard then undoes (status ' . $made->get_status() . ')' );

		// AC-3: system and customer cancellations record their own reason.
		$unpaid = t51_order( $s, $product, 'pending' );
		wp_set_current_user( 0 );
		$unpaid->update_status( 'cancelled', __( 'Unpaid order cancelled - time limit reached.', 'woocommerce' ) );
		$by_customer = t51_order( $s, $product, 'pending' );
		$by_customer->update_status( 'cancelled', __( 'Order cancelled by customer.', 'woocommerce' ) );
		wp_set_current_user( $staff->ID );
		$check( 'Unpaid order cancelled - time limit reached.' === t51_fresh( $unpaid->get_id() )->get_meta( StatusModel::REASON_META ), 'AC-3 the unpaid-order timeout records its reason' );
		$check( 'Order cancelled by customer.' === t51_fresh( $by_customer->get_id() )->get_meta( StatusModel::REASON_META ) && 0 === (int) t51_fresh( $by_customer->get_id() )->get_meta( StatusModel::CANCELLED_BY_META ), 'AC-3 a customer\'s own cancellation records its reason' );
		$check( null !== TransitionGuard::problem( StatusModel::SHIPPED, 'cancelled', 'reason', true ), 'AC-2 a Shipped order cannot be cancelled' );

		// AC-4: failed delivery.
		$failed_delivery = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY );
		$failed_delivery->update_status( StatusModel::DELIVERY_FAILED, '', true );
		$failed_delivery = t51_fresh( $failed_delivery->get_id() );
		$failed_delivery->update_status( 'completed', '', true );
		$failed_delivery = t51_fresh( $failed_delivery->get_id() );
		$check( StatusModel::DELIVERY_FAILED === $failed_delivery->get_status() && ! StatusModel::isDelivered( $failed_delivery ) && null === StatusModel::deliveredAt( $failed_delivery ), 'AC-4 Delivery Failed is not delivered and cannot become Delivered' );
		$check( ! StatusModel::allowed( StatusModel::DELIVERY_FAILED, 'cancelled' ), 'AC-4 Delivery Failed cannot be cancelled (stock restoration belongs to the ERP)' );

		// AC-5: the cash route.
		$cod = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY, 'cod' );
		$cod->update_status( 'completed', '', true );
		$check( StatusModel::OUT_FOR_DELIVERY === t51_fresh( $cod->get_id() )->get_status(), 'AC-5 a cash order cannot skip COD Collected on its way to Delivered' );
		$cod = t51_fresh( $cod->get_id() );
		$cod->update_status( StatusModel::COD_COLLECTED, '', true );
		$cod = t51_fresh( $cod->get_id() );
		$check( StatusModel::isDelivered( $cod ) && null !== StatusModel::deliveredAt( $cod ), 'AC-5 COD Collected is delivered, with the time delivery was established' );
		$mail = WC()->mailer()->get_emails()['WC_Email_Customer_Completed_Order'];
		$mail->object = $walk;
		$prepaid_mail = $mail->is_enabled();
		$cod->update_status( 'completed', '', true );
		$mail->object = t51_fresh( $cod->get_id() );
		$check( $prepaid_mail && ! $mail->is_enabled(), 'AC-5 the "order complete" email stays for a prepaid order and is not sent when a cash order\'s remittance is recorded' );
		$check( 'completed' === t51_fresh( $cod->get_id() )->get_status() && StatusModel::deliveredAt( t51_fresh( $cod->get_id() ) ) === StatusModel::enteredAt( t51_fresh( $cod->get_id() ), StatusModel::COD_COLLECTED ), 'AC-5 COD Collected leads to Delivered, and delivery dates from collection' );
		$early = t51_order( $s, $product, 'processing', 'cod' );
		$early->update_status( StatusModel::COD_COLLECTED, '', true );
		$check( 'processing' === t51_fresh( $early->get_id() )->get_status(), 'AC-5 COD Collected is refused from Confirmed' );
		$prepaid = t51_order( $s, $product, StatusModel::SHIPPED );
		$prepaid->update_status( StatusModel::COD_COLLECTED, '', true );
		$check( StatusModel::SHIPPED === t51_fresh( $prepaid->get_id() )->get_status(), 'AC-5 COD Collected is refused for a prepaid order' );

		// AC-6: refunds.
		$partial = t51_order( $s, $product, 'processing' );
		$refund  = wc_create_refund( array( 'order_id' => $partial->get_id(), 'amount' => '10', 'reason' => 't51 partial' ) );
		$check( $refund instanceof \WC_Order_Refund && 10.0 === (float) t51_fresh( $partial->get_id() )->get_total_refunded() && 'processing' === t51_fresh( $partial->get_id() )->get_status(), 'AC-6 a partial refund is recorded and leaves the status' );
		$full = t51_order( $s, $product, StatusModel::SHIPPED );
		wc_create_refund( array( 'order_id' => $full->get_id(), 'amount' => $full->get_total(), 'reason' => 't51 full' ) );
		$check( 'refunded' === t51_fresh( $full->get_id() )->get_status(), 'AC-6 a full refund sets Refunded, from Shipped too' );

		// FR-013 (D6) and FR-012: the language an order is placed in is captured as order data, natively by WooCommerce
		// Multilingual, and status moves keep it.
		$current = (string) apply_filters( 'wpml_current_language', null );
		do_action( 'wpml_switch_language', 'ar' );
		$lang_order = t51_order( $s, $product, 'processing' );
		do_action( 'wpml_switch_language', $current );
		$captured = (string) $lang_order->get_meta( 'wpml_language' );
		$lang_order->update_status( StatusModel::PREPARING, '', true );
		$check( 'ar' === $captured, "FR-013 (D6) an order placed in Arabic stores its language as order data ({$captured})" );
		$check( 'ar' === t51_fresh( $lang_order->get_id() )->get_meta( 'wpml_language' ), 'FR-012 the order\'s language stays as stored after a status move' );

		return $ok;
	}
);

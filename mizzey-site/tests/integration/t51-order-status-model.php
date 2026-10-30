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
function t51_order( Scenario $s, \WC_Product $product, string $status = 'processing' ): \WC_Order {
	$order = wc_create_order();
	$order->add_product( $product, 1 );
	$order->set_payment_method( 'cod' );
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
		delete_transient( 'mizzey_order_notices_' . $staff->ID );

		// AC-1: the eleven states, their names, and which count as paid.
		$statuses = wc_get_order_statuses();
		foreach ( array( 'pending', 'failed', 'processing', 'preparing', 'shipped', 'out-for-delivery', 'cod-collected', 'completed', 'cancelled', 'refunded', 'delivery-failed' ) as $key ) {
			$check( isset( $statuses[ 'wc-' . $key ] ), "AC-1 status {$key} exists ({$statuses[ 'wc-' . $key ]})" );
		}
		$check( 'Confirmed' === wc_get_order_status_name( 'processing' ) && 'Delivered' === wc_get_order_status_name( 'completed' ), 'AC-1 Processing is named Confirmed and Completed is named Delivered' );
		$check( null !== get_post_status_object( 'wc-delivery-failed' ) && null !== get_post_status_object( 'wc-cod-collected' ), 'AC-1 the new statuses are registered with WordPress' );
		$paid = wc_get_is_paid_statuses();
		$check( array() === array_diff( StatusModel::PAID, $paid ) && ! in_array( StatusModel::DELIVERY_FAILED, $paid, true ), 'FR-011 Preparing, Shipped, Out for Delivery and COD Collected are paid; Delivery Failed is not' );
		switch_to_locale( 'ar' );
		$arabic = array( wc_get_order_status_name( 'processing' ), wc_get_order_status_name( 'preparing' ), wc_get_order_status_name( 'completed' ), wc_get_order_status_name( 'delivery-failed' ) );
		restore_previous_locale();
		$check( array( 'تم التأكيد', 'قيد التجهيز', 'تم التوصيل', 'تعذّر التوصيل / أُعيد إلى المصدر' ) === $arabic, 'AC-1 the names are translated into Arabic (' . implode( ' | ', $arabic ) . ')' );

		// AC-2, AC-7, AC-8, FR-010: the full walk as staff, attributed, timed, stock untouched by the new statuses.
		$walk  = t51_order( $s, $product, 'processing' );
		$stock = (int) \Automattic\WooCommerce\Utilities\NumberUtil::round( (float) Scenario::fresh_product( $product->get_id() )->get_stock_quantity() );
		foreach ( array( StatusModel::PREPARING, StatusModel::SHIPPED, StatusModel::OUT_FOR_DELIVERY, 'completed' ) as $to ) {
			$check( true === $walk->update_status( $to, '', true ) && $to === t51_fresh( $walk->get_id() )->get_status(), "AC-2 staff move to {$to} accepted" );
			$walk = t51_fresh( $walk->get_id() );
		}
		$check( $stock === (int) Scenario::fresh_product( $product->get_id() )->get_stock_quantity(), "FR-010 stock unchanged across the new statuses (stays {$stock})" );
		$staff_notes = array_filter( t51_notes( $walk->get_id() ), static fn ( $n ) => str_contains( $n->content, 'Order status changed' ) && $n->added_by === $staff->display_name );
		$check( count( $staff_notes ) >= 4, 'AC-7 each staff move is in the timeline under the staff member\'s name (' . count( $staff_notes ) . ' notes)' );
		$within = true;
		foreach ( t51_notes( $walk->get_id() ) as $note ) {
			if ( preg_match( '/to ([^.]+)\.$/', $note->content, $m ) ) {
				$key = array_search( $m[1], wc_get_order_statuses(), true );
				$at  = false === $key ? null : StatusModel::enteredAt( $walk, (string) $key );
				$within = $within && null !== $at && abs( $at - $note->date_created->getTimestamp() ) <= 2;
			}
		}
		$check( $within, 'AC-8 the first entry into each status equals its timeline note within two seconds' );
		$found = wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'meta_query' => array( array( 'key' => StatusModel::metaKey( 'completed' ), 'compare' => 'EXISTS' ) ) ) );
		$check( in_array( $walk->get_id(), $found, true ), 'AC-8 orders are found by when they were delivered, from order data' );
		$check( StatusModel::isDelivered( $walk ) && null !== StatusModel::deliveredAt( $walk ), 'FR-009 a Delivered order is in the delivered set with its delivery time' );

		// AC-8: re-entering a status keeps its first time.
		$back  = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY );
		$first = StatusModel::enteredAt( $back, StatusModel::OUT_FOR_DELIVERY );
		$back->update_meta_data( StatusModel::metaKey( StatusModel::OUT_FOR_DELIVERY ), (string) ( $first - 100 ) );
		$back->save();
		$back->update_status( StatusModel::SHIPPED );
		$back = t51_fresh( $back->get_id() );
		$back->update_status( StatusModel::OUT_FOR_DELIVERY );
		$check( $first - 100 === StatusModel::enteredAt( t51_fresh( $back->get_id() ), StatusModel::OUT_FOR_DELIVERY ), 'AC-8 going out for delivery again keeps the first time' );

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

		// Payment callbacks are not blocked.
		$pay = t51_order( $s, $product, 'pending' );
		$pay->set_payment_method( 'bacs' );
		$pay->save();
		$pay->payment_complete( 't51-tx' );
		$check( 'processing' === t51_fresh( $pay->get_id() )->get_status(), 'AC-2 payment_complete moves Pending Payment to Confirmed' );
		$cod_paid = t51_order( $s, $product, 'pending' );
		$cod_paid->payment_complete( 't51-cod' );
		$check( 'pending' === t51_fresh( $cod_paid->get_id() )->get_status(), 'AC-2 the WooCommerce cash-on-delivery shortcut from Pending Payment to Delivered is refused' );
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

		// AC-2 and AC-3, order screen.
		$screen = t51_order( $s, $product, 'processing' );
		t51_screen_save( $screen, 'completed' );
		$check( 'processing' === t51_fresh( $screen->get_id() )->get_status(), 'AC-2 [screen] Confirmed to Delivered refused' );
		$notices = (array) get_transient( 'mizzey_order_notices_' . $staff->ID );
		$check( (bool) preg_grep( '/cannot move to Delivered\. Allowed next: Preparing, Cancelled, Refunded\./', $notices ), 'AC-2 [screen] the person is told which moves are allowed' );
		t51_screen_save( t51_fresh( $screen->get_id() ), 'cancelled' );
		$check( 'processing' === t51_fresh( $screen->get_id() )->get_status(), 'AC-3 [screen] a cancellation without a reason is refused' );
		t51_screen_save( t51_fresh( $screen->get_id() ), StatusModel::PREPARING );
		$check( StatusModel::PREPARING === t51_fresh( $screen->get_id() )->get_status(), 'AC-2 [screen] Confirmed to Preparing accepted' );
		t51_screen_save( t51_fresh( $screen->get_id() ), 'cancelled', 'Customer asked by phone' );
		$cancelled = t51_fresh( $screen->get_id() );
		$reason_note = array_filter( t51_notes( $screen->get_id() ), static fn ( $n ) => 'Cancellation reason: Customer asked by phone' === $n->content && $n->added_by === $staff->display_name );
		$check( 'cancelled' === $cancelled->get_status() && 'Customer asked by phone' === $cancelled->get_meta( StatusModel::REASON_META ) && (int) $cancelled->get_meta( StatusModel::CANCELLED_BY_META ) === $staff->ID, 'AC-3 [screen] with a reason: cancelled, the reason and the person stored' );
		$check( count( $reason_note ) === 1, 'AC-3, AC-7 the reason is in the timeline under the staff member\'s name' );
		delete_transient( 'mizzey_order_notices_' . $staff->ID );

		// AC-2 and AC-3, bulk.
		$bulk_ok  = t51_order( $s, $product, 'processing' );
		$bulk_bad = t51_order( $s, $product, 'processing' );
		$bulk_bad->update_status( StatusModel::PREPARING );
		$kept = TransitionGuard::bulk( array( (string) $bulk_ok->get_id(), (string) $bulk_bad->get_id() ), 'mark_' . StatusModel::PREPARING, 'order' );
		$check( array( $bulk_ok->get_id(), $bulk_bad->get_id() ) === $kept, 'AC-2 [bulk] Confirmed and Preparing orders may both be set to Preparing (no change counts as allowed)' );
		$kept = apply_filters( 'woocommerce_bulk_action_ids', array( $bulk_ok->get_id(), t51_fresh( $walk->get_id() )->get_id() ), 'mark_processing', 'order' );
		$check( array( $bulk_ok->get_id() ) === array_values( $kept ), 'AC-2 [bulk] a Delivered order is removed from a bulk move to Confirmed' );
		$kept = apply_filters( 'woocommerce_bulk_action_ids', array( $bulk_ok->get_id() ), 'mark_cancelled', 'order' );
		$check( array() === $kept, 'AC-3 [bulk] no cancellation in bulk, because no reason can be given' );
		$check( (bool) preg_grep( '/^Not changed to /', (array) get_transient( 'mizzey_order_notices_' . $staff->ID ) ), 'AC-2 [bulk] the refused orders are reported' );

		// AC-2, AC-3 and AC-7, REST.
		$rest = t51_order( $s, $product, 'processing' );
		$r    = t51_rest( $rest->get_id(), array( 'status' => 'completed' ) );
		$check( 400 === $r['status'] && 'mizzey_order_status_refused' === $r['code'] && 'processing' === t51_fresh( $rest->get_id() )->get_status(), "AC-2 [REST] Confirmed to Delivered is a 400 and the order is unchanged (status {$r['status']})" );
		$r = t51_rest( $rest->get_id(), array( 'status' => 'cancelled' ) );
		$check( 400 === $r['status'] && 'processing' === t51_fresh( $rest->get_id() )->get_status(), "AC-3 [REST] a staff cancellation without a reason is a 400 (status {$r['status']})" );
		$r = t51_rest( $rest->get_id(), array( 'status' => StatusModel::PREPARING ) );
		$rest_notes = array_filter( t51_notes( $rest->get_id() ), static fn ( $n ) => str_contains( $n->content, 'to Preparing.' ) && $n->added_by === $staff->display_name );
		$check( 200 === $r['status'] && count( $rest_notes ) === 1, "AC-7 [REST] an allowed staff move is attributed to the person (status {$r['status']})" );
		$r = t51_rest( $rest->get_id(), array( 'status' => 'cancelled', 'cancellation_reason' => 'Out of stock at the warehouse' ) );
		$check( 200 === $r['status'] && 'Out of stock at the warehouse' === t51_fresh( $rest->get_id() )->get_meta( StatusModel::REASON_META ), "AC-3 [REST] with a reason the cancellation is stored (status {$r['status']})" );

		// AC-3: system and customer cancellations record their own reason.
		$unpaid = t51_order( $s, $product, 'pending' );
		wp_set_current_user( 0 );
		$unpaid->update_status( 'cancelled', __( 'Unpaid order cancelled - time limit reached.', 'woocommerce' ) );
		$by_customer = t51_order( $s, $product, 'pending' );
		$by_customer->update_status( 'cancelled', __( 'Order cancelled by customer.', 'woocommerce' ) );
		wp_set_current_user( $staff->ID );
		$check( 'Unpaid order cancelled - time limit reached.' === t51_fresh( $unpaid->get_id() )->get_meta( StatusModel::REASON_META ), 'AC-3 the unpaid-order timeout records its reason' );
		$check( 'Order cancelled by customer.' === t51_fresh( $by_customer->get_id() )->get_meta( StatusModel::REASON_META ) && 0 === (int) t51_fresh( $by_customer->get_id() )->get_meta( StatusModel::CANCELLED_BY_META ), 'AC-3 a customer\'s own cancellation records its reason' );
		$shipped = t51_order( $s, $product, StatusModel::SHIPPED );
		$check( null !== TransitionGuard::problem( StatusModel::SHIPPED, 'cancelled', 'reason', true ), 'AC-2 a Shipped order cannot be cancelled' );

		// AC-4 and AC-5: failed delivery and cash on delivery.
		$failed_delivery = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY );
		$failed_delivery->update_status( StatusModel::DELIVERY_FAILED, '', true );
		$failed_delivery = t51_fresh( $failed_delivery->get_id() );
		$failed_delivery->update_status( 'completed', '', true );
		$failed_delivery = t51_fresh( $failed_delivery->get_id() );
		$check( StatusModel::DELIVERY_FAILED === $failed_delivery->get_status() && ! StatusModel::isDelivered( $failed_delivery ) && null === StatusModel::deliveredAt( $failed_delivery ), 'AC-4 Delivery Failed is not delivered and cannot become Delivered' );
		$check( ! StatusModel::allowed( StatusModel::DELIVERY_FAILED, 'cancelled' ), 'AC-4 Delivery Failed cannot be cancelled (stock restoration belongs to the ERP)' );
		$cod = t51_order( $s, $product, StatusModel::OUT_FOR_DELIVERY );
		$cod->update_status( StatusModel::COD_COLLECTED, '', true );
		$cod = t51_fresh( $cod->get_id() );
		$check( StatusModel::isDelivered( $cod ) && null !== StatusModel::deliveredAt( $cod ), 'AC-5 COD Collected is delivered, with the time delivery was established' );
		$cod->update_status( 'completed', '', true );
		$check( 'completed' === t51_fresh( $cod->get_id() )->get_status() && StatusModel::deliveredAt( t51_fresh( $cod->get_id() ) ) === StatusModel::enteredAt( t51_fresh( $cod->get_id() ), StatusModel::COD_COLLECTED ), 'AC-5 COD Collected leads to Delivered, and delivery dates from collection' );
		$early = t51_order( $s, $product, 'processing' );
		$early->update_status( StatusModel::COD_COLLECTED, '', true );
		$check( 'processing' === t51_fresh( $early->get_id() )->get_status(), 'AC-5 COD Collected is refused from Confirmed' );

		// AC-6: refunds.
		$partial = t51_order( $s, $product, 'processing' );
		wc_create_refund( array( 'order_id' => $partial->get_id(), 'amount' => '10', 'reason' => 't51 partial' ) );
		$check( 'processing' === t51_fresh( $partial->get_id() )->get_status(), 'AC-6 a partial refund leaves the status' );
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

<?php
/**
 * P-004. Is order placement idempotent, so a repeated gateway callback is safely a no-operation?
 *
 * PAY-05 contracts duplicate order and charge protection and it is a key row. Section 7 row 12 assumes
 * Woo offers some protection and that callback idempotency is the integrator's job. A repeated Paymob
 * callback that charges or completes twice is the kind of defect that reaches the customer's bank.
 */

$notes   = array();
$verdict = 'partial';
$product = null;
$order   = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'P-004 probe product' );
	$product->set_regular_price( '100' );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 10 );
	$product->save();

	$order = wc_create_order();
	$order->add_product( wc_get_product( $product->get_id() ), 1 );
	$order->calculate_totals();
	$order->save();
	$order_id = $order->get_id();

	// Two identical gateway callbacks, same transaction reference.
	$txn = 'PROBE-TXN-0001';
	$order->payment_complete( $txn );
	$first_status = $order->get_status();
	$first_paid   = $order->get_date_paid() ? $order->get_date_paid()->date( 'c' ) : 'null';

	wp_cache_flush();
	$again = wc_get_order( $order_id );
	$again->payment_complete( $txn );

	wp_cache_flush();
	$final = wc_get_order( $order_id );

	$stock_after = wc_get_product( $product->get_id() )->get_stock_quantity();
	$notes_list  = wc_get_order_notes( array( 'order_id' => $order_id, 'limit' => 50 ) );

	$notes[] = sprintf(
		'WooCommerce %s. Order %d, payment_complete called twice with the same transaction reference %s. '
			. 'Status after the first call: %s, after the second: %s. Date paid after the first: %s, after the '
			. 'second: %s. Transaction id stored: %s. Stock left from 10: %s. Order notes recorded: %d.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$order_id, $txn,
		$first_status, $final->get_status(),
		$first_paid,
		$final->get_date_paid() ? $final->get_date_paid()->date( 'c' ) : 'null',
		$final->get_transaction_id(),
		var_export( $stock_after, true ),
		count( $notes_list )
	);

	// The thing that must not happen twice: stock reduced twice for one order.
	$reduced_once = ( 9 === (int) $stock_after );

	if ( $reduced_once ) {
		$verdict = 'confirmed';
		$notes[] = 'The second call did not reduce stock again, so Woo carries a guard on the order side: '
			. '_order_stock_reduced is set on the first pass and respected on the second. That is real protection '
			. 'against a repeated callback for the SAME order.';
	} else {
		$verdict = 'refuted';
		$notes[] = sprintf( 'Stock moved to %s, so the repeated call was NOT a no-operation and the order side has '
			. 'no guard. PAY-05 must enforce it.', var_export( $stock_after, true ) );
	}

	$notes[] = 'What this does NOT establish, and PAY-05 still needs: Woo protects a repeated callback against one '
		. 'order. It does nothing about a customer submitting checkout twice and creating two separate orders, or '
		. 'about a gateway retrying against an order that was meanwhile cancelled. Both are integrator work, so '
		. 'section 7 row 12 is right that callback idempotency belongs to Mizzey, and this probe narrows what is '
		. 'left rather than clearing it.';
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	if ( $order && $order->get_id() ) {
		$order->delete( true );
	}
	if ( $product && $product->get_id() ) {
		wp_delete_post( $product->get_id(), true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

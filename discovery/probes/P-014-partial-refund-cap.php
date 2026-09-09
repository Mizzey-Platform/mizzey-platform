<?php
/**
 * P-014. Does the native refund API enforce a ceiling at the amount actually paid?
 *
 * Section 6 states BR-006 is already guaranteed by Woo for full and partial refunds. If it is not,
 * the store can refund more than it took, which is the kind of defect that costs real money.
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
	$product->set_name( 'P-014 probe product' );
	$product->set_regular_price( '100' );
	$product->save();

	$order = wc_create_order();
	$order->add_product( wc_get_product( $product->get_id() ), 1 );
	$order->calculate_totals();
	$order->set_status( 'completed' );
	$order->save();
	$order_id = $order->get_id();
	$total    = (float) $order->get_total();

	$notes[] = sprintf( 'WooCommerce %s. Order %d total %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown', $order_id, var_export( $total, true ) );

	// 1. A legitimate partial refund.
	$partial = wc_create_refund( array( 'order_id' => $order_id, 'amount' => 30 ) );
	$partial_ok = ! is_wp_error( $partial );
	$notes[] = $partial_ok
		? 'A 30 partial refund was accepted.'
		: 'A 30 partial refund was REJECTED: ' . $partial->get_error_message();

	// 2. An over-refund: 200 remaining capacity is only 70.
	$over = wc_create_refund( array( 'order_id' => $order_id, 'amount' => 200 ) );
	$over_blocked = is_wp_error( $over );
	$notes[] = $over_blocked
		? 'A further refund of 200 against 70 remaining was REJECTED: ' . $over->get_error_message()
		: 'A further refund of 200 against 70 remaining was ACCEPTED, which exceeds the amount paid.';

	wp_cache_flush();
	$reloaded  = wc_get_order( $order_id );
	$refunded  = (float) $reloaded->get_total_refunded();
	$notes[]   = sprintf( 'Total refunded now stands at %s against a paid total of %s.',
		var_export( $refunded, true ), var_export( $total, true ) );

	if ( $partial_ok && $over_blocked && $refunded <= $total + 0.001 ) {
		$verdict = 'confirmed';
		$notes[] = 'The ceiling holds: partials are allowed and the total cannot exceed what was paid. BR-006 is '
			. 'native and section 6 is right that this one is already solved.';
	} elseif ( ! $over_blocked ) {
		$verdict = 'refuted';
		$notes[] = 'The API allowed a refund beyond the amount paid. BR-006 is NOT guaranteed and must be enforced '
			. 'by Mizzey, and the gateway side needs the same check.';
	} else {
		$verdict = 'partial';
		$notes[] = 'The ceiling held but the partial behaved unexpectedly; read the messages above before relying on it.';
	}

	$notes[] = 'Scope note: this tests the Woo bookkeeping ceiling only. Whether Paymob enforces the same limit on '
		. 'the gateway side is a separate question and is not tested here.';
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

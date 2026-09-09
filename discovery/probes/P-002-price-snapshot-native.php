<?php
/**
 * P-002. Is the BR-005 price snapshot genuinely native, with the price frozen onto the order line?
 *
 * Section 6 classifies this Native and calls BR-005 the highest risk business rule in the register,
 * one the platform already guarantees. If that is wrong, every historical order total is unstable.
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
	$product->set_name( 'P-002 probe product' );
	$product->set_regular_price( '100' );
	$product->save();

	$order = wc_create_order();
	$order->add_product( wc_get_product( $product->get_id() ), 3 );
	$order->calculate_totals();
	$order->save();
	$order_id = $order->get_id();

	$at_sale_total = (float) $order->get_total();
	$items         = $order->get_items();
	$item          = reset( $items );
	$at_sale_line  = $item ? (float) $item->get_total() : null;

	$notes[] = sprintf(
		'WooCommerce %s. Order %d for 3 units at 100: order total %s, line total %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$order_id,
		var_export( $at_sale_total, true ),
		var_export( $at_sale_line, true )
	);

	// Raise the price after the sale. A snapshot means the order does not move.
	$product->set_regular_price( '250' );
	$product->save();
	wp_cache_flush();

	$reloaded    = wc_get_order( $order_id );
	$after_total = (float) $reloaded->get_total();
	$after_items = $reloaded->get_items();
	$after_item  = reset( $after_items );
	$after_line  = $after_item ? (float) $after_item->get_total() : null;

	$notes[] = sprintf(
		'Product price then raised to 250. Same order re-read: order total %s, line total %s.',
		var_export( $after_total, true ),
		var_export( $after_line, true )
	);

	$frozen = abs( $after_total - $at_sale_total ) < 0.001
		&& null !== $after_line
		&& abs( (float) $after_line - (float) $at_sale_line ) < 0.001
		&& abs( $at_sale_total - 300.0 ) < 0.001;

	if ( $frozen ) {
		$verdict = 'confirmed';
		$notes[] = 'The price is frozen onto the order line at sale. BR-005 holds natively and section 6 is right '
			. 'to call it already solved. Note this is the price only: cost is a separate promise, tested in P-017.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'The order moved with the product price. BR-005, the highest risk rule in the register, is NOT '
			. 'guaranteed by the platform and needs building.';
	}
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

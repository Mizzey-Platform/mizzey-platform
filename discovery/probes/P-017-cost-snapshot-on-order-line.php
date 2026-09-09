<?php
/**
 * P-017. Is the product cost frozen onto the order at sale the way the price is?
 *
 * ENT-08 records the price snapshot as native and says nothing about cost. ADM-27 promises margin
 * reporting "on accurate history". If the order reads a mutable current cost, editing a product cost
 * rewrites every historical margin and no later work recovers what the cost was on the day.
 *
 * Method: place an order, record its cost, then change the product's cost and read the order again.
 *
 * Run: wp eval-file discovery/probes/P-017-cost-snapshot-on-order-line.php
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

use Automattic\WooCommerce\Internal\Features\FeaturesController;

$notes   = array();
$verdict = 'partial';
$restore = null;
$product = null;
$order   = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}
	if ( ! method_exists( 'WC_Product', 'set_cogs_value' ) ) {
		$notes[] = sprintf(
			'WooCommerce %s has no product cost API, so there is nothing to snapshot. See P-016.',
			defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown'
		);
		$verdict = 'confirmed';
		throw new LogicException( 'no-api' );
	}

	$features = wc_get_container()->get( FeaturesController::class );
	if ( ! $features->feature_is_enabled( 'cost_of_goods_sold' ) ) {
		$features->change_feature_enable( 'cost_of_goods_sold', true );
		$restore = false;
		$notes[] = 'Cost of Goods Sold was off, enabled for the test and restored afterwards.';
	}

	// A product costing 100, sold at 250, two units.
	$product = new WC_Product_Simple();
	$product->set_name( 'P-017 probe product' );
	$product->set_regular_price( '250' );
	$product->set_cogs_value( 100.0 );
	$product->save();

	$order = wc_create_order();
	$order->add_product( wc_get_product( $product->get_id() ), 2 );
	$order->calculate_totals();
	$order->set_status( 'completed' );
	$order->save();
	$order_id = $order->get_id();

	$at_sale = (float) $order->get_cogs_total_value();
	$notes[] = sprintf( 'Order %d placed with 2 units at cost 100. Order cost at sale: %s.', $order_id, var_export( $at_sale, true ) );

	// Now the thing that matters: change the product's cost after the sale.
	$product->set_cogs_value( 999.0 );
	$product->save();

	// Read the order fresh, bypassing any in-memory copy.
	wp_cache_flush();
	$reloaded = wc_get_order( $order_id );
	$after    = (float) $reloaded->get_cogs_total_value();

	$notes[] = sprintf( 'Product cost then changed to 999. Same order re-read from storage: %s.', var_export( $after, true ) );

	if ( abs( $after - $at_sale ) < 0.001 && abs( $at_sale - 200.0 ) < 0.001 ) {
		$verdict = 'refuted';
		$notes[] = 'The order kept the cost that applied on the day. WooCommerce snapshots cost onto the order '
			. 'during calculate_totals, so ADM-27 accurate history holds without Mizzey adding anything, '
			. 'provided the feature is enabled before the first order.';
	} elseif ( abs( $after - $at_sale ) >= 0.001 ) {
		$verdict = 'confirmed';
		$notes[] = 'The order cost moved with the product. Historical margins are rewritten by a cost edit, '
			. 'so a Mizzey-held snapshot on the order line is required.';
	} else {
		$verdict = 'partial';
		$notes[] = sprintf( 'Cost held steady but did not equal the expected 200 for 2 units at 100. '
			. 'Frozen, but the value needs explaining before it is relied on. Expected 200, saw %s.', var_export( $at_sale, true ) );
	}
} catch ( LogicException $e ) {
	// verdict already set
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

// Clean up: no probe order, no probe product, feature flag as we found it.
try {
	if ( $order && $order->get_id() ) {
		$order->delete( true );
	}
	if ( $product && $product->get_id() ) {
		wp_delete_post( $product->get_id(), true );
	}
	if ( false === $restore ) {
		wc_get_container()->get( FeaturesController::class )->change_feature_enable( 'cost_of_goods_sold', false );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode(
	array(
		'observed' => implode( ' ', $notes ),
		'verdict'  => $verdict,
	)
);

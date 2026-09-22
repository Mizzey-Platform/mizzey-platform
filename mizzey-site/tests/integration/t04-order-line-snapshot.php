<?php
/**
 * t04, AC-2: an order keeps the cost that applied when it was placed, after the product's cost changes.
 * Port of discovery probe P-017, as a contract test.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't04-order-line-snapshot', 'AC-2' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$product = $s->simple_product( 't04 product', '250', 100.0 );
		$order   = $s->order_for( $product, 2 );
		$at_sale = $order->get_cogs_total_value();
		$items   = $order->get_items();
		$line    = reset( $items );
		$s->note( 'Order cost at sale: ' . var_export( $at_sale, true ) . '; line cost ' . var_export( $line->get_cogs_value(), true ) );

		$product->set_cogs_value( 999.0 );
		$product->save();
		wp_cache_flush();
		$reread = wc_get_order( $order->get_id() );
		$items  = $reread->get_items();
		$line   = reset( $items );
		$s->note( 'After product cost changed to 999: order ' . var_export( $reread->get_cogs_total_value(), true ) . '; line ' . var_export( $line->get_cogs_value(), true ) );

		return 200.0 === $at_sale && 200.0 === $reread->get_cogs_total_value() && 200.0 === $line->get_cogs_value();
	}
);

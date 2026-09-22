<?php
/**
 * t02, AC-1: a simple product's cost saves and reads back unchanged. Also records, as a fact, that WooCommerce
 * stores a zero cost as null (no cost), the same as a blank.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't02-simple-cost', 'AC-1' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$priced = $s->simple_product( 't02 cost 137.50', '300', 137.5 );
		$zero   = $s->simple_product( 't02 cost zero', '300', 0.0 );
		$blank  = $s->simple_product( 't02 cost blank', '300', null );

		$read_priced = Scenario::fresh_product( $priced->get_id() )->get_cogs_value();
		$read_zero   = Scenario::fresh_product( $zero->get_id() )->get_cogs_value();
		$read_blank  = Scenario::fresh_product( $blank->get_id() )->get_cogs_value();

		$s->note( 'Set 137.5, read back ' . var_export( $read_priced, true ) );
		$s->note( 'Set 0, read back ' . var_export( $read_zero, true ) );
		$s->note( 'Set blank (null), read back ' . var_export( $read_blank, true ) );

		if ( null === $read_zero ) {
			$s->note( 'Fact: zero is stored as no cost (native zero-to-null conversion). Not a contract criterion.' );
		}
		return 137.5 === $read_priced;
	}
);

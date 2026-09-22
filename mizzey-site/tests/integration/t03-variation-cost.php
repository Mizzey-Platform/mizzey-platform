<?php
/**
 * t03, AC-1: each variation of a variable product keeps its own cost. Also records the default additive flag and
 * what a variation without its own cost reports (edge cases in the spec).
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't03-variation-cost', 'AC-1' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();

		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( '50ml', '100ml', '200ml' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new \WC_Product_Variable();
		$parent->set_name( 't03 variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
		$s->track_post( $parent->get_id() );

		$costs = array( '50ml' => 40.0, '100ml' => 70.0, '200ml' => null );
		$ids   = array();
		foreach ( $costs as $size => $cost ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_attributes( array( 'size' => $size ) );
			$variation->set_regular_price( '200' );
			$variation->set_cogs_value( $cost );
			$variation->save();
			$s->track_post( $variation->get_id() );
			$ids[ $size ] = $variation->get_id();
		}

		$ok = true;
		foreach ( $ids as $size => $id ) {
			$v = Scenario::fresh_product( $id );
			$s->note( sprintf( '%s: set %s, read %s, additive %s, effective %s', $size, var_export( $costs[ $size ], true ),
				var_export( $v->get_cogs_value(), true ), var_export( $v->get_cogs_value_is_additive(), true ),
				var_export( $v->get_cogs_effective_value(), true ) ) );
			if ( null !== $costs[ $size ] && $costs[ $size ] !== $v->get_cogs_value() ) {
				$ok = false;
			}
		}
		return $ok;
	}
);

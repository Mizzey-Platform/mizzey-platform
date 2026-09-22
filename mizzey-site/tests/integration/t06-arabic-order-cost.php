<?php
/**
 * t06, AC-3: an order for the Arabic version of a product, placed in the Arabic language context, records the cost.
 * Both creation paths from t05: a WPML duplicate, and a separately authored linked translation.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't06-arabic-order-cost', 'AC-3' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		if ( ! $sitepress ) {
			$s->note( 'WPML is not active.' );
			return false;
		}
		$s->enable_cogs();

		$en_dup = $s->simple_product( 't06 EN (dup)', '300', 110.0 );
		$ar_dup = (int) $sitepress->make_duplicate( $en_dup->get_id(), 'ar' );
		$s->track_post( $ar_dup );

		$en_sep = $s->simple_product( 't06 EN (separate)', '300', 90.0 );
		$ar     = new \WC_Product_Simple();
		$ar->set_name( 't06 AR عطر' );
		$ar->set_regular_price( '300' );
		$ar->set_status( 'publish' );
		$ar->save();
		$ar_sep = $ar->get_id();
		$s->track_post( $ar_sep );
		do_action( 'wpml_set_element_language_details', array(
			'element_id'           => $ar_sep,
			'element_type'         => 'post_product',
			'trid'                 => apply_filters( 'wpml_element_trid', null, $en_sep->get_id(), 'post_product' ),
			'language_code'        => 'ar',
			'source_language_code' => 'en',
		) );
		// The English product is saved again after linking, as it is when an editor next saves it in wp-admin
		// (save_post). That is when WPML copies the cost across.
		wp_update_post( array( 'ID' => $en_sep->get_id() ) );

		do_action( 'wpml_switch_language', 'ar' );
		$s->note( 'Current language for the orders: ' . apply_filters( 'wpml_current_language', null ) );

		$results = array();
		foreach ( array( 'duplicate' => array( $ar_dup, 220.0 ), 'separate' => array( $ar_sep, 180.0 ) ) as $path => $case ) {
			list( $id, $expected ) = $case;
			$order = $s->order_for( wc_get_product( $id ), 2 );
			$cost  = $order->get_cogs_total_value();
			$s->note( sprintf( '%s: order for 2 of the Arabic product (id %d) records cost %s; expected %s', $path, $id, var_export( $cost, true ), var_export( $expected, true ) ) );
			$results[ $path ] = abs( $cost - $expected ) < 0.001;
		}
		do_action( 'wpml_switch_language', 'en' );
		return ! in_array( false, $results, true );
	}
);

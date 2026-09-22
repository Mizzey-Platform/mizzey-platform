<?php
/**
 * t08, AC-5: product cost never reaches a visitor or a customer (contracts/cost-exposure.md).
 *
 * A simple product and a variation get distinctive costs, then:
 *  - as a visitor, over real HTTP: the product page, the Store API product and list, REST v3 product and variations;
 *  - as a logged-in customer, dispatched in process: Store API, REST v3 product and variations;
 *  - the customer's own order, rendered as My Account shows it.
 * The cost values must appear in none of them. Order emails are not sent locally and are not tested here.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

const COST_SIMPLE    = 4321.87;
const COST_VARIATION = 3876.19;

function leaks( string $body ): array {
	$found = array();
	foreach ( array( COST_SIMPLE, COST_VARIATION ) as $cost ) {
		foreach ( array( (string) $cost, number_format( $cost, 2 ), number_format( $cost, 2, ',', '.' ), number_format( $cost, 2, '.', '' ) ) as $form ) {
			if ( false !== strpos( $body, $form ) ) {
				$found[] = $form;
			}
		}
	}
	$found = array_unique( $found );
	if ( false !== stripos( $body, 'cost_of_goods' ) || false !== stripos( $body, 'cogs' ) ) {
		$found[] = 'cogs key';
	}
	return array_values( $found );
}

function dispatch( string $method, string $route ): array {
	$response = rest_do_request( new \WP_REST_Request( $method, $route ) );
	return array( $response->get_status(), wp_json_encode( $response->get_data() ) );
}

run(
	new Scenario( 't08-no-public-exposure', 'AC-5' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$simple = $s->simple_product( 't08 simple', '9000', COST_SIMPLE );

		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Size' );
		$attr->set_options( array( '50ml' ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 't08 variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attr ) );
		$parent->save();
		$s->track_post( $parent->get_id() );
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => '50ml' ) );
		$variation->set_regular_price( '8000' );
		$variation->set_cogs_value( COST_VARIATION );
		$variation->save();
		$s->track_post( $variation->get_id() );

		$ok   = true;
		$base = home_url( '/' );
		$sid  = $simple->get_id();
		$pid  = $parent->get_id();

		// Visitor, over HTTP.
		$visitor = array(
			'product page (simple)'     => add_query_arg( array( 'post_type' => 'product', 'p' => $sid ), $base ),
			'product page (variable)'   => add_query_arg( array( 'post_type' => 'product', 'p' => $pid ), $base ),
			'Store API product'         => add_query_arg( 'rest_route', "/wc/store/v1/products/$sid", $base ),
			'Store API variable'        => add_query_arg( 'rest_route', "/wc/store/v1/products/$pid", $base ),
			'Store API list'            => add_query_arg( 'rest_route', '/wc/store/v1/products', $base ),
			'REST v3 product'           => add_query_arg( 'rest_route', "/wc/v3/products/$sid", $base ),
			'REST v3 variations'        => add_query_arg( 'rest_route', "/wc/v3/products/$pid/variations", $base ),
		);
		foreach ( $visitor as $label => $url ) {
			$r      = wp_remote_get( $url, array( 'timeout' => 20 ) );
			$code   = is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r );
			$found  = is_wp_error( $r ) ? array( 'request failed' ) : leaks( wp_remote_retrieve_body( $r ) );
			$reached = ! is_wp_error( $r ) && in_array( (int) $code, array( 200, 401, 403 ), true );
			$ok     = $ok && $reached && ! $found;
			$s->note( sprintf( 'visitor %s: HTTP %s, cost %s', $label, $code, $found ? 'FOUND ' . implode( ', ', $found ) : 'absent' ) );
		}

		// Customer, in process.
		$customer_id = wp_insert_user( array( 'user_login' => 't08_customer_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'customer', 'user_email' => 't08-' . wp_rand() . '@example.invalid' ) );
		$s->track_user( $customer_id );
		wp_set_current_user( $customer_id );
		foreach ( array( "/wc/store/v1/products/$sid", "/wc/store/v1/products/$pid", "/wc/v3/products/$sid", "/wc/v3/products/$pid/variations" ) as $route ) {
			list( $code, $body ) = dispatch( 'GET', $route );
			$found = leaks( $body );
			$ok    = $ok && ! $found;
			$s->note( sprintf( 'customer %s: status %d, cost %s', $route, $code, $found ? 'FOUND ' . implode( ', ', $found ) : 'absent' ) );
		}

		// The customer's own order, as My Account renders it.
		$order = $s->order_for( $simple, 1 );
		$order->set_customer_id( $customer_id );
		$order->save();
		ob_start();
		wc_get_template( 'order/order-details.php', array( 'order_id' => $order->get_id() ) );
		$html  = ob_get_clean();
		$found = leaks( $html );
		$ok    = $ok && ! $found && '' !== trim( $html );
		$s->note( sprintf( 'customer My Account order view (%d bytes): cost %s', strlen( $html ), $found ? 'FOUND ' . implode( ', ', $found ) : 'absent' ) );
		$s->note( 'Not tested: order emails (not sent in the local runtime).' );
		wp_set_current_user( 0 );
		return $ok;
	}
);

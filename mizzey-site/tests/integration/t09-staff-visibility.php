<?php
/**
 * t09, AC-6 (pending CX-01): facts only. For each standard WordPress and WooCommerce role, can the user edit
 * products (and so see cost on the product screen), and does REST v3 return the cost to them? No verdict: which
 * roles should see cost is the open CX-01 question.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't09-staff-visibility', 'AC-6 (pending CX-01)' ),
	function ( Scenario $s ): ?bool {
		$s->enable_cogs();
		$product = $s->simple_product( 't09 product', '500', 222.33 );
		foreach ( array( 'administrator', 'shop_manager', 'editor', 'author', 'contributor', 'subscriber', 'customer' ) as $role ) {
			$uid = wp_insert_user( array( 'user_login' => 't09_' . $role . '_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => $role, 'user_email' => 't09-' . $role . '-' . wp_rand() . '@example.invalid' ) );
			if ( is_wp_error( $uid ) ) {
				$s->note( $role . ': could not create user: ' . $uid->get_error_message() );
				continue;
			}
			$s->track_user( $uid );
			wp_set_current_user( $uid );
			$response = rest_do_request( new \WP_REST_Request( 'GET', '/wc/v3/products/' . $product->get_id() ) );
			$data     = $response->get_data();
			$cost     = is_array( $data ) && isset( $data['cost_of_goods_sold'] ) ? wp_json_encode( $data['cost_of_goods_sold'] ) : 'not returned';
			$s->note( sprintf( '%s: edit_products=%s, REST v3 status %d, cost %s', $role, current_user_can( 'edit_products' ) ? 'yes' : 'no', $response->get_status(), $cost ) );
		}
		wp_set_current_user( 0 );
		return null;
	}
);

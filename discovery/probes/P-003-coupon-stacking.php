<?php
/**
 * P-003. Do Woo coupons stack freely, with no priority and no stacking policy?
 *
 * BR-004 and PROMO-16/17 need a stacking policy. If Woo applies every valid coupon in the order they
 * were added and offers no priority field, the policy is entirely Mizzey work, and ENT-11's
 * "promotion rules table for what the coupon schema cannot hold" is justified.
 */

$notes   = array();
$verdict = 'partial';
$made    = array();
$product = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}

	// Is there a priority or stacking field on the coupon schema at all?
	$coupon_probe = new WC_Coupon();
	$data_keys    = array_keys( $coupon_probe->get_data() );
	$policy_keys  = array_values( array_filter( $data_keys, static function ( $k ) {
		return false !== stripos( $k, 'priority' ) || false !== stripos( $k, 'stack' ) || false !== stripos( $k, 'order' );
	} ) );

	$notes[] = sprintf(
		'WooCommerce %s coupon schema exposes %d fields: %s. Priority or stacking fields: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		count( $data_keys ),
		implode( ', ', $data_keys ),
		$policy_keys ? implode( ', ', $policy_keys ) : 'none'
	);
	$notes[] = 'individual_use is the only exclusivity control: all or nothing, not a priority.';

	// Two percentage coupons, both valid, applied together.
	foreach ( array( 'p003a' => 10, 'p003b' => 20 ) as $code => $amount ) {
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $amount );
		$coupon->save();
		$made[] = $coupon->get_id();
	}

	$product = new WC_Product_Simple();
	$product->set_name( 'P-003 probe product' );
	$product->set_regular_price( '100' );
	$product->save();

	if ( null === WC()->cart ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $product->get_id(), 1 );
	$applied_a = WC()->cart->apply_coupon( 'p003a' );
	$applied_b = WC()->cart->apply_coupon( 'p003b' );
	WC()->cart->calculate_totals();

	$applied = WC()->cart->get_applied_coupons();
	$discount = (float) WC()->cart->get_discount_total();

	$notes[] = sprintf(
		'Two percent coupons (10%% and 20%%) on a 100 item: apply calls returned %s and %s, cart holds [%s], '
			. 'total discount %s.',
		$applied_a ? 'true' : 'false',
		$applied_b ? 'true' : 'false',
		implode( ', ', $applied ),
		var_export( $discount, true )
	);

	if ( count( $applied ) >= 2 && ! $policy_keys ) {
		$verdict = 'confirmed';
		$notes[] = 'Both coupons applied together and the schema carries no priority or stacking field. The order of '
			. 'application is the order they were added, which is not a policy. BR-004 and PROMO-16/17 are Mizzey '
			. 'work, and the ENT-11 promotion rules table is justified.';
	} elseif ( count( $applied ) < 2 ) {
		$verdict = 'refuted';
		$notes[] = 'Woo did not stack them, so some native policy exists and BR-004 may be partly configurable.';
	} else {
		$verdict = 'partial';
		$notes[] = 'Coupons stacked but a policy-shaped field exists; read it before concluding.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	if ( null !== WC()->cart ) {
		WC()->cart->empty_cart();
	}
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
	if ( $product && $product->get_id() ) {
		wp_delete_post( $product->get_id(), true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

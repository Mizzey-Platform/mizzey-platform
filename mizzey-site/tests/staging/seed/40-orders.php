<?php
/**
 * Staging seed, step 4: orders, in every state a review has to show. Invented customers only.
 *
 * Names are "Test Customer", addresses are invented, email addresses end in example.invalid, which can never
 * be delivered, and the phone numbers are a made-up run written in the four formats an Egyptian number is
 * commonly typed in. That last point is deliberate: order search by phone (ADM-157) has to be tried against a
 * number typed differently from how it was stored.
 *
 * Two orders are placed in Arabic and hold the Arabic product records, because an order line stores the record
 * of the language it was ordered in.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

if ( wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) ) ) {
	echo wp_json_encode( array( 'orders' => 'already seeded' ) ), "\n";
	return;
}

global $sitepress;
$sitepress->switch_lang( 'en', true );

$by_sku = static fn ( string $sku ): int => (int) wc_get_product_id_by_sku( $sku );
$arabic = static function ( int $id ): int {
	$type = 'product_variation' === get_post_type( $id ) ? 'product_variation' : 'product';
	return (int) apply_filters( 'wpml_object_id', $id, $type, true, 'ar' );
};

$customer = get_user_by( 'login', 'demo_customer' );
$cities   = array( 'Cairo', 'Giza', 'Alexandria', 'Mansoura' );
$phones   = array( '01000000101', '+201000000102', '00201000000103', '010 0000 0104', '01000000105', '+20 100 000 0106' );

// status, language, payment method, lines as SKU => quantity, days ago, customer account or guest.
$orders = array(
	array( 'pending', 'en', 'mizzey_staging_test', array( 'DEMO-S-001' => 1 ), 0, false ),
	array( 'processing', 'en', 'cod', array( 'DEMO-S-002' => 2, 'DEMO-S-009' => 1 ), 0, true ),
	array( 'processing', 'ar', 'cod', array( 'DEMO-S-005' => 1 ), 1, false ),
	array( 'processing', 'en', 'mizzey_staging_test', array( 'DEMO-V-002-S' => 2 ), 1, true ),
	array( 'on-hold', 'en', 'cod', array( 'DEMO-S-006' => 3 ), 2, false ),
	array( 'completed', 'en', 'mizzey_staging_test', array( 'DEMO-S-001' => 2, 'DEMO-S-011' => 1 ), 4, true ),
	array( 'completed', 'ar', 'mizzey_staging_test', array( 'DEMO-S-009' => 4, 'DEMO-V-002-M' => 1 ), 5, false ),
	array( 'completed', 'en', 'cod', array( 'DEMO-S-010' => 1 ), 6, false ),
	array( 'completed', 'en', 'mizzey_staging_test', array( 'DEMO-S-006' => 2 ), 7, true ),
	array( 'cancelled', 'en', 'cod', array( 'DEMO-S-002' => 1 ), 3, false ),
	array( 'failed', 'en', 'mizzey_staging_test', array( 'DEMO-S-005' => 1 ), 2, false ),
	array( 'refunded', 'en', 'mizzey_staging_test', array( 'DEMO-S-011' => 2 ), 8, false ),
);

$titles = array(
	'cod'                 => 'Cash on delivery',
	'mizzey_staging_test' => 'Test payment (staging, no money moves)',
);
$made   = array();
foreach ( $orders as $i => list( $status, $lang, $method, $lines, $days_ago, $account ) ) {
	$n     = $i + 1;
	$order = wc_create_order( array( 'customer_id' => $account && $customer ? $customer->ID : 0 ) );
	foreach ( $lines as $sku => $quantity ) {
		$id = $by_sku( $sku );
		if ( ! $id ) {
			WP_CLI::error( "seed order {$n}: no product with SKU {$sku}" );
		}
		$order->add_product( wc_get_product( 'ar' === $lang ? $arabic( $id ) : $id ), $quantity );
	}
	$address = array(
		'first_name' => 'Test',
		'last_name'  => sprintf( 'Customer %02d', $n ),
		'email'      => sprintf( 'customer%02d@example.invalid', $n ),
		'phone'      => $phones[ $i % count( $phones ) ],
		'address_1'  => sprintf( '%d Sample Street', 10 + $n ),
		'city'       => $cities[ $i % count( $cities ) ],
		'state'      => 'C',
		'postcode'   => '11511',
		'country'    => 'EG',
	);
	$order->set_address( $address, 'billing' );
	$order->set_address( $address, 'shipping' );
	$order->set_payment_method( $method );
	$order->set_payment_method_title( $titles[ $method ] );
	$order->set_created_via( 'staging-seed' );
	$order->set_date_created( time() - $days_ago * DAY_IN_SECONDS - $n * 600 );
	$order->update_meta_data( 'wpml_language', $lang );
	$order->calculate_totals();
	$order->save();

	if ( 'refunded' === $status ) {
		$order->update_status( 'completed', 'Staging seed.' );
		wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $order->get_total(),
				'reason'         => 'Staging seed: full refund.',
				'refund_payment' => false,
				'restock_items'  => false,
			)
		);
		$order->update_status( 'refunded', 'Staging seed.' );
	} else {
		$order->update_status( $status, 'Staging seed.' );
	}
	$made[ $status ] = ( $made[ $status ] ?? 0 ) + 1;

	// One completed order carries a partial refund, so a refund is visible that is not a whole order.
	if ( 6 === $n ) {
		wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => '88',
				'reason'         => 'Staging seed: partial refund of one item.',
				'refund_payment' => false,
				'restock_items'  => false,
			)
		);
	}
}

echo wp_json_encode( array( 'orders' => $made, 'total' => array_sum( $made ) ) ), "\n";

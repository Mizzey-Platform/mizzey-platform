<?php
/**
 * Staging seed: the store settings a checkout needs before an order can be placed at all.
 *
 * Every value here is invented for the staging copy and is not a proposal for the live store. The shipping
 * price, the zone and the methods offered are the client's decisions (the carrier rate card, the launch payment
 * methods) and stay theirs.
 *
 *  - cash on delivery switched on: the native capability, with no value ceiling and no fee (D-11);
 *  - one shipping zone for Egypt with one flat price, so the checkout has a shipping option to offer;
 *  - guest checkout allowed;
 *  - the "coming soon" screen off, so a reviewer sees the store and not a placeholder.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

$cod            = (array) get_option( 'woocommerce_cod_settings', array() );
$cod['enabled'] = 'yes';
$cod['title']   = $cod['title'] ?? 'Cash on delivery';
update_option( 'woocommerce_cod_settings', $cod );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );

// A new WooCommerce install hides every store page from visitors behind a "coming soon" screen. Staging exists
// to be looked at, and it is already closed to the public by the tunnel sign-in and kept out of search engines,
// so the screen is switched off here. The first browser pass ran against that screen and proved nothing.
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_manage_stock', 'yes' );
update_option( 'woocommerce_notify_low_stock_amount', 3 );

$zone_name = 'Egypt (staging, invented price)';
$existing  = array_filter( WC_Shipping_Zones::get_zones(), static fn ( array $z ): bool => $zone_name === $z['zone_name'] );
if ( ! $existing ) {
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( $zone_name );
	$zone->add_location( 'EG', 'country' );
	$zone->save();
	$instance = $zone->add_shipping_method( 'flat_rate' );
	update_option(
		'woocommerce_flat_rate_' . $instance . '_settings',
		array(
			'title'      => 'Standard delivery (staging)',
			'tax_status' => 'none',
			'cost'       => '50',
		)
	);
}
WC_Cache_Helper::get_transient_version( 'shipping', true );

echo wp_json_encode(
	array(
		'cod'           => get_option( 'woocommerce_cod_settings' )['enabled'],
		'shipping_zone' => $zone_name,
		'guest'         => get_option( 'woocommerce_enable_guest_checkout' ),
		'coming_soon'   => get_option( 'woocommerce_coming_soon' ),
	)
), "\n";

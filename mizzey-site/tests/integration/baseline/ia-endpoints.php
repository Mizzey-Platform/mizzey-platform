<?php
/**
 * Arabic slugs for the WooCommerce account and checkout endpoints (#242: IA-10, IA-16, IA-18, IA-19, IA-20).
 *
 * Runs after admin-visit.php, and that ordering is the point. WooCommerce registers its endpoint slugs as
 * translatable strings under context "WP Endpoints" when it runs in an admin context, so during setup.php they
 * do not exist yet: the first attempt there found no string id for any of them and translated nothing.
 *
 * No pages are created for these rows. They are endpoints on the account and checkout pages, and a page would
 * give one screen two managed routes, which guardrail G-1 forbids.
 *
 * The Arabic values are deliberate placeholders, suffixed rather than translated: the URLs must differ per
 * language and stay stable for the scenarios, while the final Arabic wording is a client content input.
 *
 * @package MizzeySite\Tests\Integration
 */

$endpoints = array(
	'order-received'  => 'order-received-ar',
	'orders'          => 'orders-ar',
	'view-order'      => 'view-order-ar',
	'edit-address'    => 'edit-address-ar',
	'lost-password'   => 'lost-password-ar',
	'edit-account'    => 'edit-account-ar',
	'payment-methods' => 'payment-methods-ar',
	'customer-logout' => 'customer-logout-ar',
	'downloads'       => 'downloads-ar',
	'order-pay'       => 'order-pay-ar',
);

$done = array();
$missing = array();
foreach ( $endpoints as $slug => $arabic ) {
	$string_id = function_exists( 'icl_get_string_id' ) ? icl_get_string_id( $slug, 'WP Endpoints' ) : 0;
	if ( ! $string_id ) {
		$missing[] = $slug;
		continue;
	}
	icl_add_string_translation( $string_id, 'ar', $arabic, ICL_TM_COMPLETE );
	$done[ $slug ] = $arabic;
}

flush_rewrite_rules( false );

echo wp_json_encode(
	array(
		'ia_endpoints_ar'      => $done,
		'ia_endpoints_missing' => $missing,
	)
), "\n";

<?php
/**
 * P-013. Does Woo ship only Shop Manager and Customer, with no path to six granular staff roles?
 *
 * ROLE-03 to ROLE-09 contract six staff roles, and the client's reporting document adds a matrix of
 * per-report, per-field access on top. If Woo offers two coarse roles, all of that is custom.
 */

$notes   = array();
$verdict = 'partial';

try {
	if ( ! function_exists( 'wp_roles' ) ) {
		throw new RuntimeException( 'Roles API not available.' );
	}

	$all       = array_keys( wp_roles()->roles );
	$wp_core   = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
	$woo_added = array_values( array_diff( $all, $wp_core ) );

	$notes[] = sprintf(
		'Roles on the site: %s. Beyond WordPress core, WooCommerce contributes: %s.',
		implode( ', ', $all ),
		$woo_added ? implode( ', ', $woo_added ) : 'none'
	);

	// How coarse is shop_manager? Count its capabilities, and check for anything report-scoped.
	$manager = get_role( 'shop_manager' );
	if ( $manager ) {
		$caps        = array_keys( array_filter( $manager->capabilities ) );
		$report_caps = array_values( array_filter( $caps, static function ( $c ) {
			return false !== stripos( $c, 'report' );
		} ) );
		$notes[] = sprintf(
			'shop_manager carries %d capabilities. Report-related ones: %s.',
			count( $caps ),
			$report_caps ? implode( ', ', $report_caps ) : 'none'
		);
		$notes[] = $report_caps
			? 'Report access is a single all-or-nothing capability, so hiding product cost or margin from one role '
				. 'while showing the rest of a report is not expressible in the capability model and is custom work.'
			: 'No report capability at all.';
	}

	$expected_two = count( $woo_added ) <= 2
		&& in_array( 'shop_manager', $woo_added, true )
		&& in_array( 'customer', $woo_added, true );

	if ( $expected_two ) {
		$verdict = 'confirmed';
		$notes[] = 'Shop Manager and Customer only. The six contracted staff roles and the reporting access matrix '
			. 'are entirely Mizzey work, as section 7 row 15 assumes.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'Woo contributes roles beyond Shop Manager and Customer, so the starting point is better than '
			. 'section 7 row 15 assumes and the role work may be smaller.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

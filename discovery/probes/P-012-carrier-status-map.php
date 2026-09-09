<?php
/**
 * P-012. Does Woo lack a Returned to Origin status, and can an unrecognised carrier status reach Delivered?
 *
 * SHIP-17 is P1 and key: returned-to-origin must be a distinct state, never silently recorded as
 * delivered. Two halves. The first is testable here. The second needs the Bosta integration, which is
 * not installed, so it is reported as untested rather than guessed at.
 */

$notes   = array();
$verdict = 'partial';

try {
	if ( ! function_exists( 'wc_get_order_statuses' ) ) {
		throw new RuntimeException( 'WooCommerce order status API not available.' );
	}

	$statuses = wc_get_order_statuses();
	$slugs    = array_keys( $statuses );

	$rto = array_filter(
		$slugs,
		static function ( $s ) {
			return false !== stripos( $s, 'rto' )
				|| false !== stripos( $s, 'returned' )
				|| false !== stripos( $s, 'origin' );
		}
	);

	$notes[] = sprintf(
		'WooCommerce %s ships %d order statuses: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		count( $slugs ),
		implode( ', ', $slugs )
	);

	if ( $rto ) {
		$notes[]  = 'A returned-to-origin status already exists: ' . implode( ', ', $rto ) . '.';
		$verdict  = 'refuted';
	} else {
		$notes[] = 'There is no returned-to-origin status. SHIP-17 needs a registered custom status, and every '
			. 'report, filter and role view that enumerates statuses has to know about it.';

		// The nearest wrong answer: is "completed" what an unmapped status would fall back to?
		$notes[] = sprintf(
			'The closest existing terminal states are %s. Nothing in core distinguishes a parcel that came back '
				. 'from one that arrived, so an unmapped carrier status has no correct home and the mapping in '
				. 'SHIP-14 is what prevents it landing on completed.',
			implode( ' and ', array_slice( array_values( array_filter( $slugs, static function ( $s ) {
				return in_array( $s, array( 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed' ), true );
			} ) ), 0, 4 ) )
		);

		// Second half: not testable without the carrier integration.
		$notes[] = 'The second half of the question, whether the Bosta integration has mapped a returned parcel to '
			. 'Delivered, is NOT tested here: the carrier plugin is not installed on this runtime. It needs '
			. 're-running against the integration before SHIP-14 is signed off.';
		$verdict = 'partial';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

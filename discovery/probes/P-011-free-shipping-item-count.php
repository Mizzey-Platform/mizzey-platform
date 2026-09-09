<?php
/**
 * P-011. Does Woo free shipping support only a minimum amount or coupon, with no item count condition?
 *
 * BR-002 is the client's "free shipping on two items" rule, and OD-04 still asks whether two items
 * means two units or two distinct products. Either reading needs a count condition. If Woo has none,
 * the rule is custom work whichever way OD-04 is answered.
 */

$notes   = array();
$verdict = 'partial';

try {
	if ( ! class_exists( 'WC_Shipping_Free_Shipping' ) ) {
		throw new RuntimeException( 'WC_Shipping_Free_Shipping does not exist.' );
	}

	$method = new WC_Shipping_Free_Shipping();
	$fields = $method->get_instance_form_fields();

	$requires = isset( $fields['requires']['options'] ) ? $fields['requires']['options'] : array();
	$keys     = array_keys( $requires );

	$notes[] = sprintf(
		'WC_Shipping_Free_Shipping offers %d condition(s): %s. Instance fields: %s.',
		count( $requires ),
		$requires ? implode( '; ', array_map(
			static function ( $k, $v ) {
				return $k . ' = ' . wp_strip_all_tags( (string) $v );
			},
			$keys,
			$requires
		) ) : 'none',
		implode( ', ', array_keys( $fields ) )
	);

	// Is any condition about a count of items rather than an amount or a coupon?
	$count_condition = array_filter(
		$keys,
		static function ( $k ) {
			return false !== stripos( $k, 'count' ) || false !== stripos( $k, 'quantity' ) || false !== stripos( $k, 'item' );
		}
	);
	$has_amount_field = isset( $fields['min_amount'] );

	if ( ! $count_condition ) {
		$verdict = 'confirmed';
		$notes[] = sprintf(
			'No item count or quantity condition exists. The only threshold field is %s. BR-002 is custom work '
				. 'under either reading of OD-04, so the OD-04 answer changes the rule but not the fact that it must be written.',
			$has_amount_field ? 'min_amount, an order total' : 'absent entirely'
		);
	} else {
		$verdict = 'refuted';
		$notes[] = 'A count-based condition exists: ' . implode( ', ', $count_condition )
			. '. BR-002 may be configurable rather than custom, which would reduce the estimate for that rule.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

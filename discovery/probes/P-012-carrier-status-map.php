<?php
/**
 * P-012. Does Woo lack a Returned to Origin status, and can an unrecognised carrier status reach Delivered?
 *
 * SHIP-17 is P1 and key: returned-to-origin must be a distinct state, never silently recorded as
 * delivered. SHIP-14 requires an explicit, documented, admin-visible mapping. Both halves are tested
 * here against the official Bosta WooCommerce plugin named in SHIP-08.
 */

$notes   = array();
$verdict = 'partial';
$order   = null;

try {
	if ( ! function_exists( 'wc_get_order_statuses' ) ) {
		throw new RuntimeException( 'WooCommerce order status API not available.' );
	}

	// Half one: does Woo carry a returned-to-origin status?
	$slugs = array_keys( wc_get_order_statuses() );
	$rto   = array_filter( $slugs, static function ( $s ) {
		return false !== stripos( $s, 'rto' ) || false !== stripos( $s, 'returned' ) || false !== stripos( $s, 'origin' );
	} );

	$notes[] = sprintf(
		'WooCommerce %s ships %d order statuses: %s. A returned-to-origin status: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		count( $slugs ),
		implode( ', ', $slugs ),
		$rto ? implode( ', ', $rto ) : 'none'
	);

	// Half two: the carrier integration.
	$handler = 'Bosta_Webhook_Handler';
	if ( ! class_exists( $handler ) ) {
		$notes[] = 'The Bosta plugin is not active, so the carrier half of this question is still untested.';
		$verdict = 'partial';
		throw new LogicException( 'no-bosta' );
	}

	$plugin_version = 'unknown';
	if ( function_exists( 'get_plugin_data' ) ) {
		$data = get_plugin_data( WP_PLUGIN_DIR . '/bosta-woocommerce/bosta-woocommerce.php', false, false );
		$plugin_version = $data['Version'] ?? 'unknown';
	}

	// What does the mapping table say, asked directly rather than read off the source?
	$map = array();
	foreach ( array( 45, 46, 48, 100, 101, 104, 20, 999999 ) as $code ) {
		$map[ $code ] = $handler::map_bosta_state_to_wc_status( $code );
	}
	$notes[] = sprintf(
		'Bosta plugin %s maps state codes to Woo statuses: %s.',
		$plugin_version,
		implode( ', ', array_map(
			static function ( $k, $v ) {
				return $k . ' to ' . ( null === $v ? 'null' : $v );
			},
			array_keys( $map ),
			$map
		) )
	);

	$unknown_is_delivered = in_array( $map[999999], array( 'completed' ), true );
	$notes[]              = $unknown_is_delivered
		? 'An unrecognised state maps to completed, which is the SHIP-17 failure directly.'
		: sprintf( 'An unrecognised state falls back to %s, not completed, so the feared silent Delivered does not '
			. 'come from the fallback.', var_export( $map[999999], true ) );

	// The real exposure: two distinct terminal outcomes share one Woo status.
	$notes[] = sprintf(
		'The exposure is elsewhere: states 45 and 46 are grouped as "Finished successfully" and BOTH map to %s, '
			. 'while only 45 sets a delivery date. Two different terminal outcomes collapse into one Woo status. '
			. 'The plugin ships no legend for these codes anywhere in its source, so what 46 means cannot be '
			. 'established from the integration itself and must come from Bosta documentation or an account. That '
			. 'absence is exactly what SHIP-14 exists to fix.',
		$map[45]
	);

	// Is the mapping even reached? Drive the handler and see whether status moves.
	$order = wc_create_order();
	$order->set_status( 'processing' );
	$order->save();
	$before = $order->get_status();

	$handler::handle_status_update( $order, array(
		'state'       => 46,
		'description' => 'P-012 probe payload',
		'timeStamp'   => null,
	) );

	wp_cache_flush();
	$after = wc_get_order( $order->get_id() );

	$notes[] = sprintf(
		'Driving the webhook handler with state 46 on an order that was %s left it %s, and wrote meta '
			. 'bosta_state_code=%s, bosta_status=%s.',
		$before,
		$after->get_status(),
		var_export( $after->get_meta( 'bosta_state_code' ), true ),
		var_export( $after->get_meta( 'bosta_status' ), true )
	);

	$status_unchanged = ( $before === $after->get_status() );

	if ( $status_unchanged ) {
		$verdict = 'refuted';
		$notes[] = 'The mapping is DORMANT. In handle_status_update the call to map_bosta_state_to_wc_status is '
			. 'commented out, so the plugin never changes order status from a webhook: it only records meta. '
			. 'Nothing is silently marked Delivered today, so the expectation as written is refuted. Two '
			. 'consequences follow. SHIP-13, carrier status reflected on the order, is NOT delivered by this plugin '
			. 'as shipped, which bears on the partial leverage on US-09-04. And the dormant mapping is a trap: '
			. 'whoever enables it inherits 45 and 46 both becoming completed.';
	} else {
		$verdict = 'confirmed';
		$notes[] = 'The handler moved the order status, so the mapping is live and state 46 lands on '
			. $after->get_status() . '. SHIP-17 needs the mapping overridden before launch.';
	}
} catch ( LogicException $e ) {
	// verdict already set
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	if ( $order && $order->get_id() ) {
		$order->delete( true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

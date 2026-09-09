<?php
/**
 * P-021. Does WooCommerce capture campaign attribution onto the order natively?
 *
 * MKT-09 contracts campaign attribution through to the order record, and PL-06 registers it as a
 * plugin need. Technical Design section 7 row 18 groups feeds and attribution as Plugin plus Extend,
 * and section 11 does not list campaign attribution in the cost table at all. Before a plugin is
 * chosen and a licence figure is put in front of the client, it is worth asking whether WooCommerce
 * already does this.
 *
 * Method. Establish that the mechanism ships and is on, list the fields it records, then prove the
 * storage half end to end: write attribution onto a real order, re-read it from storage, and query
 * orders by it. A figure that cannot be queried is not attribution, it is a note.
 *
 * What this cannot establish, stated so it does not inflate the verdict: the capture itself is
 * browser side, so a CLI probe cannot show that a real visitor's utm_source survives the journey,
 * and it cannot answer US-25-03 acceptance criterion 3, whether attribution survives a sign in
 * during checkout. That one needs a browser test and is recorded as an open question, not answered
 * here.
 *
 * Run: MIZZEY_WP=C:/wamp64/www/mizzey/app/wp python -m discovery.run_probes P-021
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

$notes   = array();
$verdict = 'partial';
$order   = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}

	$controller = 'Automattic\WooCommerce\Internal\Orders\OrderAttributionController';
	$has_class  = class_exists( $controller );
	$enabled    = get_option( 'woocommerce_feature_order_attribution_enabled', '(unset)' );

	// Which fields does it actually record? Read them from the shipped source rather than assuming.
	$fields    = array();
	$trait     = WC_ABSPATH . 'src/Internal/Traits/OrderAttributionMeta.php';
	if ( file_exists( $trait ) ) {
		preg_match_all(
			"/'([a-z_]*utm_[a-z_]*|source_type|referrer|session_[a-z_]+|device_type)'/",
			file_get_contents( $trait ),
			$m
		);
		$fields = array_values( array_unique( $m[1] ) );
	}

	$notes[] = sprintf(
		'WooCommerce %s. Order attribution controller present: %s. Feature flag '
			. 'woocommerce_feature_order_attribution_enabled: %s. Fields recorded by the shipped source (%d): %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$has_class ? 'yes' : 'no',
		var_export( $enabled, true ),
		count( $fields ),
		$fields ? implode( ', ', $fields ) : 'none found'
	);

	if ( ! $has_class ) {
		$verdict = 'confirmed';
		$notes[] = 'No native attribution mechanism exists in this version, so MKT-09 needs something on top and '
			. 'the PL-06 plugin need stands as registered.';
		throw new LogicException( 'no-native' );
	}

	// The storage half, end to end. Write attribution onto a real order the way the controller does.
	$order = wc_create_order();
	$order->set_status( 'completed' );
	$order->update_meta_data( '_wc_order_attribution_source_type', 'utm' );
	$order->update_meta_data( '_wc_order_attribution_utm_source', 'p021-source' );
	$order->update_meta_data( '_wc_order_attribution_utm_medium', 'cpc' );
	$order->update_meta_data( '_wc_order_attribution_utm_campaign', 'p021-campaign' );
	$order->save();
	$order_id = $order->get_id();

	wp_cache_flush();
	$reloaded = wc_get_order( $order_id );
	$read_back = $reloaded ? $reloaded->get_meta( '_wc_order_attribution_utm_campaign' ) : null;

	// Queryable, not just stored: can a report select orders by campaign?
	$found = wc_get_orders( array(
		'limit'      => 5,
		'status'     => 'any',
		'return'     => 'ids',
		'meta_query' => array( array(
			'key'   => '_wc_order_attribution_utm_campaign',
			'value' => 'p021-campaign',
		) ),
	) );
	$queryable = in_array( $order_id, array_map( 'intval', (array) $found ), true );

	$notes[] = sprintf(
		'Order %d written with utm_source, utm_medium and utm_campaign. Re-read from storage: campaign=%s. '
			. 'Selecting orders by campaign returned %d order(s), including this one: %s.',
		$order_id,
		var_export( $read_back, true ),
		count( (array) $found ),
		$queryable ? 'yes' : 'no'
	);

	$persists = ( 'p021-campaign' === $read_back );

	if ( $persists && $queryable && 'yes' === $enabled ) {
		$verdict = 'refuted';
		$notes[] = 'Campaign attribution to the order record is native, on by default, and queryable. '
			. 'US-25-03 acceptance criteria 1 and 2 need no plugin, so the PL-06 plugin need does not survive '
			. 'this and the register should record no licence for it. Section 7 row 18 groups feeds and '
			. 'attribution together and classes the pair Plugin plus Extend; the feeds half still needs a '
			. 'plugin, the attribution half does not, so the row is right only about its first half. Section 11 '
			. 'never listed attribution as a cost, so nothing the client has been told changes. What remains for '
			. 'US-25-03 is acceptance criterion 3 and the reporting surface, which is why the story stays extend '
			. 'rather than native.';
	} elseif ( $persists && $queryable ) {
		$verdict = 'partial';
		$notes[] = sprintf(
			'The mechanism works but the feature flag reads %s rather than yes, so it is not on by default on '
				. 'this install and enabling it is a deliberate build step, the same shape as the cost field in '
				. 'P-016. Native capability, not native behaviour.',
			var_export( $enabled, true )
		);
	} else {
		$verdict = 'partial';
		$notes[] = 'The controller exists but the attribution did not survive a write and re-read, or could not '
			. 'be selected on. Read the values above before concluding: stored but unqueryable would mean the '
			. 'data is there and no report can reach it, which is not what MKT-09 asks for.';
	}
} catch ( LogicException $e ) {
	// verdict already set
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

// Clean up: no probe order left behind.
try {
	if ( $order && $order->get_id() ) {
		$order->delete( true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

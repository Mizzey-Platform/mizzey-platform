<?php
/**
 * P-018. Does WooCommerce keep a structured, queryable record of when an order entered each status?
 *
 * ADM-86 contracts an order detail "timeline" and US-18-02 classifies it native. SHIP-13 reflects
 * carrier status onto the order. If Woo timestamps each transition in a queryable form, then
 * dispatched-at and delivered-at are derivable and no shipment history table is needed. If the record
 * is prose in order notes, then durations can only be recovered by parsing English sentences, which
 * is not a foundation for a delivery-time figure.
 *
 * Run: wp eval-file discovery/probes/P-018-order-status-transition-record.php
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

global $wpdb;

$notes   = array();
$verdict = 'partial';
$order   = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}
	$woo = defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown';

	$order = wc_create_order();
	$order->save();
	$order_id = $order->get_id();

	// Walk a realistic lifecycle. Each call is a transition Woo could record.
	foreach ( array( 'processing', 'on-hold', 'completed' ) as $status ) {
		$order->set_status( $status );
		$order->save();
	}

	// 1. Structured date columns on the order itself.
	$fresh     = wc_get_order( $order_id );
	$structured = array();
	foreach ( array( 'date_created', 'date_modified', 'date_paid', 'date_completed' ) as $field ) {
		$getter = 'get_' . $field;
		$value  = method_exists( $fresh, $getter ) ? $fresh->$getter() : null;
		$structured[ $field ] = $value ? $value->date( 'c' ) : 'null';
	}
	$notes[] = sprintf(
		'WooCommerce %s. After pending to processing to on-hold to completed, the order carries: %s.',
		$woo,
		implode( ', ', array_map(
			static function ( $k, $v ) {
				return $k . '=' . $v;
			},
			array_keys( $structured ),
			$structured
		) )
	);

	// 2. Is there a table that records transitions as rows?
	$candidates = array( 'wc_order_status_history', 'wc_order_status_transitions', 'wc_order_history' );
	$found_table = null;
	foreach ( $candidates as $table ) {
		$name = $wpdb->prefix . $table;
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) === $name ) {
			$found_table = $name;
			break;
		}
	}
	$notes[] = $found_table
		? sprintf( 'A structured status-history table exists: %s.', $found_table )
		: 'No status-history table exists. Checked ' . implode( ', ', $candidates ) . '.';

	// 3. What the order notes hold, which is where the admin timeline reads from.
	$order_notes = wc_get_order_notes( array( 'order_id' => $order_id, 'limit' => 50 ) );
	$status_notes = array();
	foreach ( $order_notes as $note ) {
		if ( stripos( $note->content, 'status changed' ) !== false ) {
			$status_notes[] = $note->content;
		}
	}
	$notes[] = sprintf(
		'%d order note(s) recorded, of which %d describe a status change. Example: %s',
		count( $order_notes ),
		count( $status_notes ),
		$status_notes ? '"' . trim( $status_notes[0] ) . '"' : 'none'
	);

	// 4. The verdict question: can a duration be computed without parsing prose?
	$has_transition_rows = (bool) $found_table;
	$notes_are_prose     = count( $status_notes ) > 0;

	if ( $has_transition_rows ) {
		$verdict = 'confirmed';
		$notes[] = 'Transitions are queryable as rows, so the ADM-86 timeline is native and delivery '
			. 'durations are derivable without new storage.';
	} elseif ( $notes_are_prose ) {
		$verdict = 'refuted';
		$notes[] = 'The only per-transition record is an English sentence in an order note, timestamped '
			. 'but not structured. date_paid and date_completed are the only queryable milestones, and '
			. 'neither is dispatch or delivery. A delivery-time or RTO-rate figure would have to parse '
			. 'note prose, which is not a foundation for a reported number. US-18-02 native is right for '
			. 'displaying a timeline and wrong for measuring one.';
	} else {
		$verdict = 'partial';
		$notes[] = 'Neither transition rows nor status notes were found, which is unexpected and needs '
			. 'a second look before anything is concluded.';
	}
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

echo wp_json_encode(
	array(
		'observed' => implode( ' ', $notes ),
		'verdict'  => $verdict,
	)
);

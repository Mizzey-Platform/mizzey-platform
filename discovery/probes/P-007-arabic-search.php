<?php
/**
 * P-007. Does native WordPress search fail for Arabic and Franco-Arabic terms?
 *
 * SRCH-06 and SRCH-07 contract a curated Arabic synonym and correction list. That is only worth
 * building if native search genuinely cannot reach an Arabic product by a near-miss spelling.
 *
 * The question is always "does this term find THE ARABIC PRODUCT", never "how many rows came back".
 * Counting rows is how the first run of this probe fooled itself: a Latin decoy product containing
 * the Franco-Arabic string matched, the count was non-zero, and it read as a success.
 */

$notes   = array();
$verdict = 'partial';
$made    = array();

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}

	// Arabic for "the king's perfume". The decoy is a different product, deliberately Latin, whose
	// name contains the Franco-Arabic rendering a shopper might type for the Arabic one.
	$arabic = "\u{0639}\u{0637}\u{0631} \u{0627}\u{0644}\u{0645}\u{0644}\u{0643}";
	$decoy  = 'Attar Bottle';

	$ids = array();
	foreach ( array( 'arabic' => $arabic, 'decoy' => $decoy ) as $key => $name ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name );
		$p->set_regular_price( '100' );
		$p->set_status( 'publish' );
		$p->save();
		$ids[ $key ] = $p->get_id();
		$made[]      = $p->get_id();
	}

	$finds_arabic = static function ( $term ) use ( $ids ) {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $term,
			'fields'         => 'ids',
			'posts_per_page' => 20,
		) );
		return in_array( $ids['arabic'], array_map( 'intval', $q->posts ), true );
	};

	$exact    = $finds_arabic( $arabic );
	$one_word = $finds_arabic( "\u{0639}\u{0637}\u{0631}" );
	// Same first word with ta marbuta typed as ha, the single commonest Arabic near miss.
	$variant  = $finds_arabic( "\u{0639}\u{0637}\u{0631} \u{0627}\u{0644}\u{0645}\u{0644}\u{0643}\u{0647}" );
	$franco   = $finds_arabic( 'attar' );

	$notes[] = sprintf(
		'WordPress %s, WooCommerce %s. Two products seeded: one named in Arabic, one Latin decoy named "%s" that '
			. 'contains the Franco-Arabic string. Asking only whether each term reaches THE ARABIC product: '
			. 'exact Arabic name %s, first Arabic word alone %s, orthographic variant %s, Franco-Arabic "attar" %s.',
		get_bloginfo( 'version' ),
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$decoy,
		$exact ? 'FOUND' : 'not found',
		$one_word ? 'FOUND' : 'not found',
		$variant ? 'FOUND' : 'not found',
		$franco ? 'FOUND' : 'not found'
	);

	if ( $exact && $one_word && ! $variant && ! $franco ) {
		$verdict = 'confirmed';
		$notes[] = 'Native search is substring matching and nothing more. It reaches the Arabic product only when the '
			. 'query is literally contained in the name, and fails both the orthographic variant and the '
			. 'Franco-Arabic rendering. There is no relevance to tune, which is exactly why SRCH-06 and SRCH-07 '
			. 'specify a curated synonym and correction list rather than a scoring change.';
		$notes[] = 'Note on the first run of this probe: searching "attar" does return a row, because the Latin decoy '
			. 'contains that string. Counting rows would report success where an Arabic shopper finds nothing.';
	} elseif ( ! $exact ) {
		$verdict = 'partial';
		$notes[] = 'Even the exact Arabic string did not reach the product, which points at collation or indexing '
			. 'rather than relevance and needs looking at before SRCH-06 is designed.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'A near miss reached the Arabic product, so native search does more than substring matching and '
			. 'the synonym list may be smaller than assumed.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

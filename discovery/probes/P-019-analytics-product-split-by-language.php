<?php
/**
 * P-019. When one product sells in both languages, does the product report still report one product?
 *
 * WPML gives every product its own post per language, and wc_order_product_lookup records the id that
 * was actually ordered. So the same shoe bought in Arabic and in English can reach the analytics layer
 * as two rows. RPT-03 best sellers and RPT-10 stock are product-level figures, and E24 is classified
 * partial on the assumption that WooCommerce Analytics supplies a sound base.
 *
 * WooCommerce Multilingual does ship a merge for this, in classes/Reports/Products/Query.php. This
 * probe does not take its word for it. It seeds the split, reads the raw report, then runs WCML's own
 * public filter over the result and measures three separate things: whether the totals come back
 * together, whether the ranking survives, and whether a paginated page tells the truth.
 *
 * Fixture. One product sold 3 in English and 5 in Arabic, total 8, against a control product sold 6.
 * Aggregated, the translated pair is the best seller. Split, the control tops the list and neither
 * half beats it. That gap is what makes a wrong ranking visible rather than arguable.
 *
 * Method note. The seeded row says wp-cli and the question is about the data layer, so it is answered
 * there. WCML registers its merge only on wc-analytics REST requests, which cannot exist in CLI, so
 * the probe calls the same public methods that filter calls, on real rows from the real data store.
 * What that cannot show is anything the REST controller does after the filter, which is recorded in
 * the observation rather than assumed away.
 *
 * Run: python -m discovery.run_probes P-019
 * Prints one JSON object: {"observed": "...", "verdict": "confirmed|refuted|partial"}
 */

use Automattic\WooCommerce\Admin\API\Reports\Cache;
use Automattic\WooCommerce\Admin\API\Reports\Products\DataStore as ProductsDataStore;
use Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler;

$notes     = array();
$verdict   = 'partial';
$made      = array();
$order_ids = array();

/** A real copy of a results object, so a filter cannot mutate the original. */
$copy_results = function ( $r ) {
	return (object) array(
		'data'    => $r->data,
		'total'   => isset( $r->total ) ? $r->total : 0,
		'pages'   => isset( $r->pages ) ? $r->pages : 0,
		'page_no' => isset( $r->page_no ) ? $r->page_no : 1,
	);
};

/** Compact one result set into "name=qty, name=qty" for the observation. */
$describe = function ( $rows ) {
	$parts = array();
	foreach ( $rows as $row ) {
		$parts[] = sprintf(
			'%s=%d',
			isset( $row['extended_info']['name'] ) ? $row['extended_info']['name'] : ( 'id ' . $row['product_id'] ),
			(int) $row['items_sold']
		);
	}
	return $parts ? implode( ', ', $parts ) : 'nothing';
};

try {
	global $wpdb, $sitepress;

	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active on this site.' );
	}
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		throw new RuntimeException( 'WPML core is not active.' );
	}
	if ( ! class_exists( 'woocommerce_wpml' ) ) {
		throw new RuntimeException( 'WooCommerce Multilingual is not active.' );
	}
	if ( ! class_exists( 'WCML\Reports\Products\Query' ) ) {
		throw new RuntimeException( 'WCML Reports Products Query is missing, so there is no merge to test.' );
	}
	$langs = array_keys( (array) apply_filters( 'wpml_active_languages', null, array() ) );
	if ( ! in_array( 'ar', $langs, true ) ) {
		throw new RuntimeException( 'Arabic is not an active language; configure it before running this probe.' );
	}

	// Deterministic: the merge decides what counts as a translation by comparing with the current language.
	$sitepress->switch_lang( 'en', true );

	$notes[] = sprintf(
		'WooCommerce %s, WPML %s, WooCommerce Multilingual %s. Active languages: %s. Current language: en.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		ICL_SITEPRESS_VERSION,
		defined( 'WCML_VERSION' ) ? WCML_VERSION : 'unknown',
		implode( ', ', $langs )
	);

	// The product that exists in two languages.
	$en = new WC_Product_Simple();
	$en->set_name( 'P-019 probe product' );
	$en->set_regular_price( '100' );
	$en->set_status( 'publish' );
	$en->save();
	$en_id  = $en->get_id();
	$made[] = $en_id;

	$ar_id = $sitepress->make_duplicate( $en_id, 'ar' );
	if ( ! $ar_id ) {
		throw new RuntimeException( 'WPML returned no Arabic product id, so the split cannot be created. See P-006.' );
	}
	$made[] = $ar_id;

	// The control, which exists once and outsells either half of the pair but not their sum.
	$control = new WC_Product_Simple();
	$control->set_name( 'P-019 control product' );
	$control->set_regular_price( '100' );
	$control->set_status( 'publish' );
	$control->save();
	$control_id = $control->get_id();
	$made[]     = $control_id;

	foreach ( array( array( $en_id, 3 ), array( $ar_id, 5 ), array( $control_id, 6 ) ) as $sale ) {
		list( $pid, $qty ) = $sale;
		$order = wc_create_order();
		$order->add_product( wc_get_product( $pid ), $qty );
		$order->calculate_totals();
		$order->set_status( 'completed' );
		$order->set_date_paid( time() );
		$order->save();
		$order_ids[] = $order->get_id();
		OrdersScheduler::import( $order->get_id() );
	}

	$notes[] = sprintf(
		'Seeded English product %d sold 3, its Arabic translation %d sold 5, and single-language control %d sold 6. '
			. 'The pair totals 8, so aggregated it is the best seller and split neither half beats the control.',
		$en_id,
		$ar_id,
		$control_id
	);

	// Fact one, independent of any plugin: what did the lookup table actually record?
	$lookup = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT product_id, SUM(product_qty) AS qty FROM {$wpdb->prefix}wc_order_product_lookup
			 WHERE product_id IN (%d, %d) GROUP BY product_id",
			$en_id,
			$ar_id
		),
		ARRAY_A
	);
	if ( ! $lookup ) {
		throw new RuntimeException( 'No rows reached wc_order_product_lookup, so the analytics layer never saw these orders.' );
	}
	$lookup_parts = array();
	foreach ( $lookup as $row ) {
		$lookup_parts[] = sprintf( '%d=%d', $row['product_id'], $row['qty'] );
	}
	$lookup_splits = count( $lookup ) > 1;
	$notes[]       = sprintf(
		'wc_order_product_lookup holds %d row(s) for the pair (%s), so the split %s at the storage layer.',
		count( $lookup ),
		implode( ', ', $lookup_parts ),
		$lookup_splits ? 'is real' : 'does not exist'
	);

	$store = new ProductsDataStore();
	$base  = array(
		'after'         => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
		'before'        => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
		'orderby'       => 'items_sold',
		'order'         => 'desc',
		'page'          => 1,
		'extended_info' => true,
	);

	// Fact two: the full page, unmerged, then merged the way the REST filter merges it.
	Cache::invalidate();
	$raw_full = $store->get_data( array_merge( $base, array( 'per_page' => 100 ) ) );
	if ( is_wp_error( $raw_full ) ) {
		throw new RuntimeException( 'Products report error: ' . $raw_full->get_error_message() );
	}
	$notes[] = sprintf( 'Report unmerged, one page of 100, ordered by units sold: %s.', $describe( $raw_full->data ) );

	$merger      = new WCML\Reports\Products\Query();
	$merged_full = $merger->joinProductTranslations( $copy_results( $raw_full ) );
	$merged_full = $merger->translateProductTitles( $merged_full );
	$notes[]     = sprintf(
		'The same page through the WCML merge: %s, reported total %s.',
		$describe( $merged_full->data ),
		var_export( $merged_full->total, true )
	);

	// Did the two halves come back together as one row carrying 8, and where does it sit?
	$pair_rows  = 0;
	$pair_units = 0;
	$pair_pos   = 0;
	$position   = 0;
	$top_units  = null;
	foreach ( $merged_full->data as $row ) {
		$position ++;
		if ( 1 === $position ) {
			$top_units = (int) $row['items_sold'];
		}
		if ( in_array( (int) $row['product_id'], array( $en_id, $ar_id ), true ) ) {
			$pair_rows ++;
			$pair_units += (int) $row['items_sold'];
			$pair_pos    = $pair_pos ? $pair_pos : $position;
		}
	}
	$merge_totals_ok = ( 1 === $pair_rows && 8 === $pair_units );
	$rank_ok         = ( 1 === $pair_pos );

	$notes[] = sprintf(
		'After the merge the pair occupies %d row(s) carrying %d unit(s), at position %d of %d, and the row at '
			. 'position 1 carries %s unit(s). Totals %s. Ranking %s.',
		$pair_rows,
		$pair_units,
		$pair_pos,
		count( $merged_full->data ),
		var_export( $top_units, true ),
		$merge_totals_ok ? 'recovered' : 'did not recover',
		$rank_ok ? 'survived' : 'did not survive, because the ORDER BY ran in SQL before the merge ran in PHP'
	);

	// Fact three: a paginated request, which is what a real best-sellers screen makes.
	Cache::invalidate();
	$raw_page = $store->get_data( array_merge( $base, array( 'per_page' => 2 ) ) );
	if ( is_wp_error( $raw_page ) ) {
		throw new RuntimeException( 'Paged products report error: ' . $raw_page->get_error_message() );
	}
	$merged_page = $merger->joinProductTranslations( $copy_results( $raw_page ) );
	$merged_page = $merger->translateProductTitles( $merged_page );

	$page_ok    = true;
	$page_wrong = '';
	foreach ( $merged_page->data as $row ) {
		if ( (int) $row['product_id'] === $en_id && 8 !== (int) $row['items_sold'] ) {
			$page_ok    = false;
			$page_wrong = sprintf(
				'"%s" showing %d unit(s) instead of 8',
				isset( $row['extended_info']['name'] ) ? $row['extended_info']['name'] : ( 'id ' . $en_id ),
				(int) $row['items_sold']
			);
		}
	}
	$notes[] = sprintf(
		'Page 1 at 2 per page, merged the same way: %s. %s',
		$describe( $merged_page->data ),
		$page_ok
			? 'No half-figure is presented under the original name.'
			: 'The English original is on page 2, so the Arabic half never finds it and is relabelled with the '
				. 'original title and left at its own number: ' . $page_wrong . '. The figure is wrong and looks legitimate.'
	);

	if ( ! $lookup_splits ) {
		$verdict = 'confirmed';
		$notes[] = 'The lookup table recorded one product, so there is nothing here to correct and the reporting '
			. 'stories can be built on the analytics base as classified.';
	} elseif ( ! $page_ok ) {
		$verdict = 'refuted';
		$notes[] = 'A paginated product report presents a wrong number under the right product name. The merge is '
			. 'applied after the SQL has already ordered and cut the result set, so it can only join rows that '
			. 'happen to land on the same page, and it fires only on wc-analytics REST requests, which means any '
			. 'query Mizzey writes against the lookup tables gets none of it. RPT-03 and RPT-10 cannot be built on '
			. 'this base as it stands. The correction belongs in SQL: resolve product_id to its translation group '
			. 'before ordering and paginating, by joining icl_translations and grouping on trid. Unlike the cost '
			. 'field in RPT-11, nothing is lost by deciding this later: icl_translations carries the language '
			. 'mapping whenever the order was placed, so historical figures recompute.';
	} elseif ( $merge_totals_ok && ! $rank_ok ) {
		$verdict = 'partial';
		$notes[] = 'The totals come back together but the ordering does not, so a best-sellers list ranks products '
			. 'on split figures and then displays merged ones. Any consumer of this report has to re-sort after '
			. 'the merge, which is a Mizzey correction rather than something the licence delivers.';
	} elseif ( $merge_totals_ok && $rank_ok ) {
		$verdict = 'confirmed';
		$notes[] = 'The split exists at the storage layer but the delivered merge recovers a single correctly '
			. 'ranked row at both page sizes tested. The analytics base holds for RPT-03 and RPT-10 on the REST '
			. 'path. A report Mizzey queries directly would still need the same correction.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'The two halves did not come back together as one row carrying 8. Read the counts above before '
			. 'concluding, but on this evidence the product report does not report a product.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

// Clean up: no probe orders, no probe products, no rows left in the analytics tables.
try {
	global $wpdb;
	foreach ( $order_ids as $oid ) {
		$wpdb->delete( $wpdb->prefix . 'wc_order_product_lookup', array( 'order_id' => $oid ) );
		$wpdb->delete( $wpdb->prefix . 'wc_order_stats', array( 'order_id' => $oid ) );
		$wpdb->delete( $wpdb->prefix . 'wc_order_tax_lookup', array( 'order_id' => $oid ) );
		$order = wc_get_order( $oid );
		if ( $order ) {
			$order->delete( true );
		}
	}
	foreach ( array_unique( $made ) as $id ) {
		wp_delete_post( $id, true );
	}
	if ( class_exists( 'Automattic\WooCommerce\Admin\API\Reports\Cache' ) ) {
		Cache::invalidate();
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

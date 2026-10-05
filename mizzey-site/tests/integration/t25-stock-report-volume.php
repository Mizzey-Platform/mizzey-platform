<?php
/**
 * t25, #246 AC-246-06 at the sizing baseline: with 5,000 physical items, each in two languages, the low-stock
 * report's total is 5,000 and a page holds distinct items. The query time and its plan are reported as facts.
 *
 * Why it exists. The correction is a condition inside the report's query, so its cost grows with the catalogue.
 * D-10 records 5,000 products as the starting sizing baseline (OD-15, an owner working decision). A correction
 * that is right on six fixtures and wrong or unusable at that size would not meet the row.
 *
 * Fixture. 10,000 product records written straight to the tables: 5,000 English originals and their 5,000 Arabic
 * translations, every one low. Written with SQL and not through the product API on purpose: the scenario measures
 * the report's query, and ten thousand API saves would measure something else and take many minutes. Nothing but
 * the three tables the query reads is touched, and every row is removed on finish.
 *
 * Verdict. Pass or fail on correctness only: the total, and one page of distinct originals. The elapsed time is a
 * note, not a threshold: this runtime is a developer machine, and a number measured here is evidence for this
 * machine alone.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

const T25_ITEMS = 5000;

run(
	new Scenario( 't25-stock-report-volume', 'AC-246-06 at the 5,000 item sizing baseline' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$translations = $wpdb->prefix . 'icl_translations';
		$lookup       = $wpdb->prefix . 'wc_product_meta_lookup';
		$admins       = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( (int) $admins[0] );

		$s->on_finish( function () use ( $wpdb, $translations, $lookup ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE 'T25-VOL %'" );
			foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
				$in = implode( ',', array_map( 'intval', $chunk ) );
				$wpdb->query( "DELETE FROM {$translations} WHERE element_type = 'post_product' AND element_id IN ({$in})" );
				$wpdb->query( "DELETE FROM {$lookup} WHERE product_id IN ({$in})" );
				$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$in})" );
			}
			wp_cache_flush();
		} );

		$before_total = (int) ( rest_do_request( t25_request( 1 ) )->get_headers()['X-WP-Total'] ?? 0 );

		// ---- Seed ---------------------------------------------------------------------------------------
		$now = current_time( 'mysql' );
		foreach ( array( 'en', 'ar' ) as $lang ) {
			for ( $from = 1; $from <= T25_ITEMS; $from += 1000 ) {
				$values = array();
				for ( $n = $from; $n < $from + 1000 && $n <= T25_ITEMS; $n++ ) {
					$values[] = $wpdb->prepare( "(%s, %s, 'publish', 'product', %s, %s, %s, %s, '', '', '', '')", sprintf( 'T25-VOL %s %05d', $lang, $n ), sprintf( 't25-vol-%s-%05d', $lang, $n ), $now, $now, $now, $now );
				}
				$wpdb->query( "INSERT INTO {$wpdb->posts} (post_title, post_name, post_status, post_type, post_date, post_date_gmt, post_modified, post_modified_gmt, post_content, post_excerpt, to_ping, pinged) VALUES " . implode( ',', $values ) );
			}
		}
		$ids = array( 'en' => array(), 'ar' => array() );
		foreach ( $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_title LIKE 'T25-VOL %'" ) as $row ) {
			list( , $lang, $n )          = explode( ' ', $row->post_title );
			$ids[ $lang ][ (int) $n ] = (int) $row->ID;
		}
		if ( T25_ITEMS !== count( $ids['en'] ) || T25_ITEMS !== count( $ids['ar'] ) ) {
			$s->note( sprintf( 'Fixture: expected %d records per language, found %d and %d.', T25_ITEMS, count( $ids['en'] ), count( $ids['ar'] ) ) );
			return false;
		}
		$trid = (int) $wpdb->get_var( "SELECT COALESCE(MAX(trid), 0) FROM {$translations}" );
		for ( $from = 1; $from <= T25_ITEMS; $from += 1000 ) {
			$stock = array();
			$group = array();
			for ( $n = $from; $n < $from + 1000 && $n <= T25_ITEMS; $n++ ) {
				$sku     = sprintf( 'T25-VOL-%05d', $n );
				$stock[] = $wpdb->prepare( "(%d, %s, 1, 'instock'), (%d, %s, 1, 'instock')", $ids['en'][ $n ], $sku, $ids['ar'][ $n ], $sku );
				$group[] = $wpdb->prepare( "('post_product', %d, %d, 'en', NULL), ('post_product', %d, %d, 'ar', 'en')", $ids['en'][ $n ], $trid + $n, $ids['ar'][ $n ], $trid + $n );
			}
			$wpdb->query( "INSERT INTO {$lookup} (product_id, sku, stock_quantity, stock_status) VALUES " . implode( ',', $stock ) );
			$wpdb->query( "INSERT INTO {$translations} (element_type, element_id, trid, language_code, source_language_code) VALUES " . implode( ',', $group ) );
		}
		wp_cache_flush();
		$s->note( sprintf( 'Seeded %d physical items as %d product records, each language record holding its own stock row of 1.', T25_ITEMS, 2 * T25_ITEMS ) );

		// ---- Measure ------------------------------------------------------------------------------------
		$sql = '';
		$capture = function ( $request ) use ( &$sql ) {
			if ( false !== strpos( $request, 'mizzey_self' ) ) {
				$sql = $request;
			}
			return $request;
		};
		add_filter( 'posts_request', $capture, PHP_INT_MAX );

		$ok = true;
		foreach ( array( 'en', 'ar', 'all' ) as $lang ) {
			do_action( 'wpml_switch_language', $lang );
			$started  = microtime( true );
			$response = rest_do_request( t25_request( 25 ) );
			$elapsed  = ( microtime( true ) - $started ) * 1000;
			$total    = (int) ( $response->get_headers()['X-WP-Total'] ?? -1 );
			$page     = array_map( fn( $row ) => (int) $row['id'], (array) $response->get_data() );
			$originals = count( array_intersect( $page, $ids['en'] ) );
			$right     = $total === $before_total + T25_ITEMS && 25 === count( array_unique( $page ) ) && 25 === $originals;
			$ok        = $ok && $right;
			$s->note( sprintf( '%s  [%s] total %d (expected %d), first page %d distinct lines of which %d are source-language records, %.0f ms', $right ? 'PASS' : 'FAIL', $lang, $total, $before_total + T25_ITEMS, count( array_unique( $page ) ), $originals, $elapsed ) );
		}
		remove_filter( 'posts_request', $capture, PHP_INT_MAX );

		// Last page: the boundary a merge-after-paging approach gets wrong.
		do_action( 'wpml_switch_language', 'en' );
		$last_page = (int) ceil( ( $before_total + T25_ITEMS ) / 25 );
		$request   = t25_request( 25 );
		$request->set_param( 'page', $last_page );
		$last      = rest_do_request( $request );
		$last_rows = count( (array) $last->get_data() );
		$expected  = ( $before_total + T25_ITEMS ) % 25 ?: 25;
		$ok        = $ok && $last_rows === $expected && (int) ( $last->get_headers()['X-WP-TotalPages'] ?? -1 ) === $last_page;
		$s->note( sprintf( '%s  the last page, %d, holds %d lines (expected %d)', $last_rows === $expected ? 'PASS' : 'FAIL', $last_page, $last_rows, $expected ) );

		// The plan, as a fact. Which index serves the representative lookup is what decides the cost.
		if ( '' !== $sql ) {
			foreach ( (array) $wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A ) as $row ) {
				$s->note( sprintf( 'PLAN  %s %s: type %s, key %s, rows %s', $row['select_type'], $row['table'], $row['type'], $row['key'] ?? 'none', $row['rows'] ) );
			}
		}

		return $ok;
	}
);

/** The low-stock report request. */
function t25_request( int $per_page ): \WP_REST_Request {
	$request = new \WP_REST_Request( 'GET', '/wc-analytics/reports/stock' );
	$request->set_param( 'type', 'lowstock' );
	$request->set_param( 'per_page', $per_page );
	return $request;
}

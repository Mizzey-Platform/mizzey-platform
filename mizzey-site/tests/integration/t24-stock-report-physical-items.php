<?php
/**
 * t24, #246 AC-246-01 to AC-246-08: the standard stock report lists one line per physical item, with one stock
 * figure, in every admin language context, and its total, paging, sorting and export follow the same list.
 *
 * The report is read through its own endpoint, `wc-analytics/reports/stock`, dispatched as an administrator, so
 * the real controller, its WP_Query and every filter registered against it run as written. The export is read
 * through WooCommerce's own exporter, which calls the controller's get_items() directly rather than through a
 * REST dispatch: that path is exercised separately because it is a different entry into the same query.
 *
 * Language context. Each list is read three times, with the session language set to English, to Arabic and to
 * all languages, inside this one WP-CLI process. That is NOT the whole input, as staging showed on 5 October 2026:
 * the multilingual plugin treats a WP-CLI process differently from the REST request the screen makes, and this
 * scenario passed while the screen was wrong. It is kept for what it does measure, the controller and its query
 * in process. The report as it is actually read, over HTTP, signed in, in each admin language context, is t26.
 *
 * Fixtures, six physical items:
 *   pair      a simple product in both languages, low
 *   arabic    a simple product that exists only in Arabic, low
 *   english   a simple product that exists only in English, low
 *   size      one low variation of a translated variable product, whose other variation is not low
 *   orphan    a translated product whose English original is no longer listable, low
 *   out       a simple product in both languages, out of stock
 * Five low physical items and one out-of-stock physical item are owed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use Automattic\WooCommerce\Admin\ReportCSVExporter;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Set a record's managed stock, and return its id. */
function t24_stock( int $id, int $quantity ): int {
	$product = wc_get_product( $id );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $quantity );
	$product->save();
	return $id;
}

/**
 * Read one page of the stock report through its endpoint.
 *
 * @return array{ids:int[],quantities:array<int,int|null>,total:int,pages:int,error:string}
 */
function t24_report( array $params ): array {
	$request = new \WP_REST_Request( 'GET', '/wc-analytics/reports/stock' );
	foreach ( $params + array( 'per_page' => 100 ) as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		return array( 'ids' => array(), 'quantities' => array(), 'total' => -1, 'pages' => -1, 'error' => $response->as_error()->get_error_message() );
	}
	$ids        = array();
	$quantities = array();
	foreach ( (array) $response->get_data() as $row ) {
		$ids[]                         = (int) $row['id'];
		$quantities[ (int) $row['id'] ] = isset( $row['stock_quantity'] ) ? (int) $row['stock_quantity'] : null;
	}
	$headers = $response->get_headers();
	return array(
		'ids'        => $ids,
		'quantities' => $quantities,
		'total'      => (int) ( $headers['X-WP-Total'] ?? -1 ),
		'pages'      => (int) ( $headers['X-WP-TotalPages'] ?? -1 ),
		'error'      => '',
	);
}

/** Every stock value stored for a set of records, so a change made by reading the report would show. */
function t24_stored( array $ids ): array {
	global $wpdb;
	wp_cache_flush();
	$out = array();
	foreach ( $ids as $id ) {
		$out[ $id ] = array(
			'meta'   => get_post_meta( $id, '_stock', true ),
			'status' => get_post_meta( $id, '_stock_status', true ),
			'lookup' => $wpdb->get_row( $wpdb->prepare( "SELECT stock_quantity, stock_status FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d", $id ), ARRAY_A ),
		);
	}
	return $out;
}

run(
	new Scenario( 't24-stock-report-physical-items', 'AC-246-01 to AC-246-08' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok = true;
		$w  = new Workflows( $s );
		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		wp_set_current_user( (int) $admins[0] );

		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$sorted = function ( array $ids ): array {
			sort( $ids );
			return array_values( $ids );
		};

		// ---- Fixtures ------------------------------------------------------------------------------------
		// Sources first, translations second: A11's invariant.
		$pair_en    = t24_stock( $w->simple( 'T24-PAIR', null ), 2 );
		$english    = t24_stock( $w->simple( 'T24-ENGLISH', null ), 2 );
		$arabic     = t24_stock( $w->simple( 'T24-ARABIC', null ), 1 );
		$orphan_en  = t24_stock( $w->simple( 'T24-ORPHAN', null ), 1 );
		$out_en     = t24_stock( $w->simple( 'T24-OUT', null ), 0 );
		list( $variable_en, $variations ) = $w->variable( 'T24-VAR', array( 'S' => null, 'M' => null ) );
		$size_en    = t24_stock( $variations[0], 1 );
		$roomy_en   = t24_stock( $variations[1], 50 );

		// The Arabic-only item: its one record is moved to Arabic, in a translation group of its own.
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $arabic, 'element_type' => 'post_product', 'trid' => null, 'language_code' => 'ar', 'source_language_code' => null ) );

		$pair_ar     = $w->translate( 'duplicate', $pair_en );
		$orphan_ar   = $w->translate( 'duplicate', $orphan_en );
		$out_ar      = $w->translate( 'duplicate', $out_en );
		$variable_ar = $w->translate( 'duplicate', $variable_en );
		$size_ar     = Workflows::ar_of( $size_en );
		$roomy_ar    = Workflows::ar_of( $roomy_en );
		if ( ! $pair_ar || ! $orphan_ar || ! $out_ar || ! $variable_ar || ! $size_ar ) {
			$s->note( 'Fixture: a translation could not be created, so nothing below is meaningful.' );
			return false;
		}

		// The orphan: its English original stops being listable, as a draft is not. Written to the row directly,
		// so that no save hook copies the status to the translation and the fixture is exactly what it says.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $orphan_en ) );
		clean_post_cache( $orphan_en );

		$records = array( $pair_en, $pair_ar, $english, $arabic, $orphan_en, $orphan_ar, $out_en, $out_ar, $variable_en, $variable_ar, $size_en, $size_ar, $roomy_en, $roomy_ar );
		$name    = array(
			$pair_en => 'pair(en)', $pair_ar => 'pair(ar)', $english => 'english', $arabic => 'arabic', $orphan_en => 'orphan(en,draft)',
			$orphan_ar => 'orphan(ar)', $out_en => 'out(en)', $out_ar => 'out(ar)', $size_en => 'size(en)', $size_ar => 'size(ar)',
			$roomy_en => 'roomy(en)', $roomy_ar => 'roomy(ar)',
			$variable_en => 'parent(en)', $variable_ar => 'parent(ar)',
		);
		$named   = fn( array $ids ) => implode( ', ', array_map( fn( $id ) => $name[ $id ] ?? "#$id", $ids ) );

		// Only the fixtures are compared, so a product left by another scenario cannot change a verdict.
		$mine = fn( array $ids ) => array_values( array_intersect( $ids, $records ) );

		$expected_low = $sorted( array( $pair_en, $english, $arabic, $orphan_ar, $size_en ) );
		$expected_out = $sorted( array( $out_en ) );
		$before       = t24_stored( $records );

		$s->note( sprintf( 'Stored state: the pair holds lookup stock %s (en) and %s (ar): two records, one item.', $before[ $pair_en ]['lookup']['stock_quantity'] ?? '?', $before[ $pair_ar ]['lookup']['stock_quantity'] ?? '?' ) );

		// ---- AC-246-01 to AC-246-07: the list, in three language contexts ---------------------------------
		$baseline_total = array();
		foreach ( array( 'en', 'ar', 'all' ) as $lang ) {
			do_action( 'wpml_switch_language', $lang );
			$low = t24_report( array( 'type' => 'lowstock' ) );
			$out = t24_report( array( 'type' => 'outofstock' ) );
			$after_language = apply_filters( 'wpml_current_language', null );

			$check( '' === $low['error'] && '' === $out['error'], "[$lang] the report answers" );
			$check( $sorted( $mine( $low['ids'] ) ) === $expected_low, "[$lang] AC-246-01, 03, 07 low-stock lists each physical item once: " . $named( $mine( $low['ids'] ) ) );
			$check( $sorted( $mine( $out['ids'] ) ) === $expected_out, "[$lang] AC-246-02 out-of-stock lists each physical item once: " . $named( $mine( $out['ids'] ) ) );
			$check( count( $low['ids'] ) === count( array_unique( $low['ids'] ) ), "[$lang] no line is repeated" );

			$quantities_ok = ( $low['quantities'][ $pair_en ] ?? null ) === 2 && ( $low['quantities'][ $english ] ?? null ) === 2
				&& ( $low['quantities'][ $arabic ] ?? null ) === 1 && ( $low['quantities'][ $orphan_ar ] ?? null ) === 1 && ( $low['quantities'][ $size_en ] ?? null ) === 1;
			$check( $quantities_ok, "[$lang] AC-246-05 each line shows the item's one figure, the pair 2 and not 4" );

			$check( $low['total'] === count( $low['ids'] ) && $out['total'] === count( $out['ids'] ), sprintf( '[%s] AC-246-06 the total equals the lines: low %d of %d, out %d of %d', $lang, $low['total'], count( $low['ids'] ), $out['total'], count( $out['ids'] ) ) );
			$check( $after_language === $lang, "[$lang] the session language is unchanged after the report: " . var_export( $after_language, true ) );
			$baseline_total[ $lang ] = array( $low['total'], $out['total'], $sorted( $low['ids'] ) );
		}
		// AC-246-04 over the whole list, not only the fixtures: the three contexts must agree exactly.
		$check( $baseline_total['en'] === $baseline_total['ar'] && $baseline_total['ar'] === $baseline_total['all'], 'AC-246-04 the three language contexts return the same list and the same totals' );

		// ---- AC-246-06: paging counts physical items -----------------------------------------------------
		do_action( 'wpml_switch_language', 'en' );
		$total    = $baseline_total['en'][0];
		$seen     = array();
		$pages    = 0;
		$page_one = t24_report( array( 'type' => 'lowstock', 'per_page' => 2, 'page' => 1 ) );
		for ( $page = 1; $page <= max( 1, $page_one['pages'] ); $page++ ) {
			$slice = t24_report( array( 'type' => 'lowstock', 'per_page' => 2, 'page' => $page ) );
			$seen  = array_merge( $seen, $slice['ids'] );
			$pages++;
		}
		$check( $page_one['pages'] === (int) ceil( $total / 2 ), sprintf( 'AC-246-06 two per page gives %d pages for %d items', $page_one['pages'], $total ) );
		$check( count( $seen ) === $total && count( array_unique( $seen ) ) === $total, sprintf( 'AC-246-06 walking every page yields %d distinct lines for a total of %d', count( array_unique( $seen ) ), $total ) );
		$check( $sorted( $mine( $seen ) ) === $expected_low, 'AC-246-06 no fixture is lost or repeated at a page boundary' );

		// ---- Sorting keeps one line per item -------------------------------------------------------------
		foreach ( array( array( 'stock_quantity', 'asc' ), array( 'stock_quantity', 'desc' ), array( 'sku', 'asc' ), array( 'stock_status', 'asc' ) ) as $sort ) {
			$list  = t24_report( array( 'type' => 'lowstock', 'orderby' => $sort[0], 'order' => $sort[1] ) );
			$order = array_values( array_filter( array_map( fn( $id ) => $list['quantities'][ $id ] ?? null, $list['ids'] ), fn( $q ) => null !== $q ) );
			$wanted = $order;
			'asc' === $sort[1] ? sort( $wanted ) : rsort( $wanted );
			$in_order = 'stock_quantity' !== $sort[0] || $order === $wanted;
			$check( $sorted( $mine( $list['ids'] ) ) === $expected_low && $list['total'] === $total && $in_order, sprintf( 'sorted by %s %s: one line per item, total %d, order kept', $sort[0], $sort[1], $list['total'] ) );
		}

		// ---- AC-246-08: the export is the same list -------------------------------------------------------
		foreach ( array( 'en', 'ar' ) as $lang ) {
			do_action( 'wpml_switch_language', $lang );
			$exporter = new ReportCSVExporter( 'stock', array( 'type' => 'lowstock' ) );
			$exporter->prepare_data_to_export();
			$rows = \Closure::bind( fn() => $this->row_data, $exporter, ReportCSVExporter::class )();
			$skus = array_map( fn( $row ) => (string) ( $row['sku'] ?? '' ), (array) $rows );
			$mine_skus = array_values( array_filter( $skus, fn( $sku ) => 0 === strpos( $sku, 'T24-' ) ) );
			sort( $mine_skus );
			$check( $mine_skus === array( 'T24-ARABIC', 'T24-ENGLISH', 'T24-ORPHAN', 'T24-PAIR', 'T24-VAR-S' ), "[$lang] AC-246-08 the export holds each physical item once: " . implode( ', ', $mine_skus ) );
			$check( (int) $exporter->get_total_rows() === $total, sprintf( '[%s] AC-246-08 the export total is %d, the screen total %d', $lang, (int) $exporter->get_total_rows(), $total ) );
		}

		// ---- The whole report, with no stock filter, follows the same rule ---------------------------------
		do_action( 'wpml_switch_language', 'en' );
		$all_types = t24_report( array() );
		$check( $sorted( $mine( $all_types['ids'] ) ) === $sorted( array( $pair_en, $english, $arabic, $orphan_ar, $out_en, $variable_en, $size_en, $roomy_en ) ), 'with no stock filter, each fixture item is still one line: ' . $named( $mine( $all_types['ids'] ) ) );

		// ---- FR-010: nothing is changed, and nothing outside the report is touched ------------------------
		$check( t24_stored( $records ) === $before, 'FR-010 reading the report changed no stored stock value on any record' );

		do_action( 'wpml_switch_language', 'en' );
		$plain = new \WP_Query( array( 'post_type' => 'product', 'post__in' => array( $pair_en, $pair_ar, $arabic, $english ), 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
		$check( $sorted( array_map( 'intval', $plain->posts ) ) === $sorted( array( $pair_en, $english ) ), 'FR-010 an ordinary product query in an English session is still scoped to English: ' . $named( array_map( 'intval', $plain->posts ) ) );

		return $ok;
	}
);

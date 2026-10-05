<?php
/**
 * t26, #246 AC-246-01 to AC-246-08 through the request the screen makes: the stock report, its summary and its
 * export, read over HTTP as a signed-in administrator in the English, Arabic and all-languages admin contexts.
 *
 * Why it exists. t24 reads the report by dispatching its endpoint inside one WP-CLI process, and passed while the
 * screen on staging showed AC-246-03 and AC-246-04 failing (docs/2026-10-05-staging-verification.md). The cause
 * was the context, not the fixtures: the multilingual plugin accepts the "all languages" session language only
 * inside wp-admin or WP-CLI, and the report's own request is a REST request, which is neither. A correction that
 * leaned on that switch was right in the one place t24 could look and wrong where the report is used.
 *
 * So this scenario does what the browser does, and nothing shorter:
 *   1. opens the Analytics Stock screen over HTTP, signed in, with the admin language set as the language
 *      switcher sets it;
 *   2. reads from that page the REST address and the nonce it hands the browser. In the Arabic context that
 *      address carries the Arabic prefix, and it is the page, not this scenario, that says so;
 *   3. sends the requests the screen sends, to that address, with that nonce and the cookies the page set.
 * The in-process dispatch t24 uses is run beside each HTTP reading and the two are compared, so a future gap
 * between the two paths fails here instead of on staging.
 *
 * The export. When every row is on the page, the screen builds the download in the browser from the rows it
 * already holds, which the list checks cover. Otherwise WooCommerce's exporter builds it in a scheduled job, and
 * such a job can run in a request that is neither wp-admin nor WP-CLI. A temporary endpoint in the disposable
 * runtime runs that exporter in such a request, under each language prefix.
 *
 * Fixtures, seven physical items:
 *   pair         a simple product in both languages, low
 *   arabic       a simple product that exists only in Arabic, low
 *   english      a simple product that exists only in English, low
 *   size         one low variation of a translated variable product, whose other variation is not low
 *   orphan       a translated product whose English original is no longer listable, low
 *   out          a simple product in both languages, out of stock
 *   out-arabic   a simple product that exists only in Arabic, out of stock
 * Five low physical items and two out-of-stock physical items are owed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Set a record's managed stock, and return its id. */
function t26_stock( int $id, int $quantity ): int {
	$product = wc_get_product( $id );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( $quantity );
	$product->save();
	return $id;
}

/**
 * One page of the stock report, asked for as the screen asks.
 *
 * @return array{ids:int[],quantities:array<int,int|null>,total:int,pages:int,error:string}
 */
function t26_page( Workflows $w, array $screen, array $params ): array {
	$r   = $w->screen_get( $screen, 'wc-analytics/reports/stock', $params + array( 'orderby' => 'stock_status', 'order' => 'asc', 'page' => 1, 'per_page' => 100 ) );
	$out = array( 'ids' => array(), 'quantities' => array(), 'total' => -1, 'pages' => -1, 'error' => '' );
	if ( 200 !== $r['status'] ) {
		$out['error'] = "HTTP {$r['status']} " . substr( $r['body'], 0, 160 );
		return $out;
	}
	foreach ( (array) json_decode( $r['body'], true ) as $row ) {
		$out['ids'][]                           = (int) $row['id'];
		$out['quantities'][ (int) $row['id'] ] = isset( $row['stock_quantity'] ) ? (int) $row['stock_quantity'] : null;
	}
	$out['total'] = (int) ( $r['headers']['x-wp-total'] ?? -1 );
	$out['pages'] = (int) ( $r['headers']['x-wp-totalpages'] ?? -1 );
	return $out;
}

/** Every page of one list, walked as an operator would page through it. */
function t26_list( Workflows $w, array $screen, array $params ): array {
	$list = t26_page( $w, $screen, $params );
	for ( $page = 2; $page <= $list['pages'] && '' === $list['error']; $page++ ) {
		$next               = t26_page( $w, $screen, $params + array( 'page' => $page ) );
		$list['ids']        = array_merge( $list['ids'], $next['ids'] );
		$list['quantities'] = $list['quantities'] + $next['quantities'];
		$list['error']      = $next['error'];
	}
	return $list;
}

/**
 * The summary the screen shows under the table.
 *
 * @return array<string,int>
 */
function t26_summary( Workflows $w, array $screen ): array {
	$r      = $w->screen_get( $screen, 'wc-analytics/reports/stock/stats' );
	$totals = 200 === $r['status'] ? ( json_decode( $r['body'], true )['totals'] ?? array() ) : array();
	return array_map( 'intval', (array) $totals );
}

/** The same list read the way t24 reads it: the endpoint dispatched inside this process. */
function t26_in_process( string $lang, string $type ): array {
	do_action( 'wpml_switch_language', $lang );
	$request = new \WP_REST_Request( 'GET', '/wc-analytics/reports/stock' );
	$request->set_param( 'type', $type );
	$request->set_param( 'per_page', 100 );
	$response = rest_do_request( $request );
	$ids      = $response->is_error() ? array() : array_map( fn( $row ) => (int) $row['id'], (array) $response->get_data() );
	do_action( 'wpml_switch_language', 'en' );
	return $ids;
}

run(
	new Scenario( 't26-stock-report-real-request', 'AC-246-01 to AC-246-08, through the signed-in HTTP request the screen makes' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok = true;
		$w  = new Workflows( $s );
		// For the in-process comparison only. The HTTP requests carry their own signed-in session.
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
		$pair_en    = t26_stock( $w->simple( 'T26-PAIR', null ), 2 );
		$english    = t26_stock( $w->simple( 'T26-ENGLISH', null ), 2 );
		$arabic     = t26_stock( $w->simple( 'T26-ARABIC', null ), 1 );
		$orphan_en  = t26_stock( $w->simple( 'T26-ORPHAN', null ), 1 );
		$out_en     = t26_stock( $w->simple( 'T26-OUT', null ), 0 );
		$out_arabic = t26_stock( $w->simple( 'T26-OUT-ARABIC', null ), 0 );
		list( $variable_en, $variations ) = $w->variable( 'T26-VAR', array( 'S' => null, 'M' => null ) );
		$size_en  = t26_stock( $variations[0], 1 );
		$roomy_en = t26_stock( $variations[1], 50 );

		// The Arabic-only items: each one record, moved to Arabic, in a translation group of its own.
		foreach ( array( $arabic, $out_arabic ) as $id ) {
			do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_product', 'trid' => null, 'language_code' => 'ar', 'source_language_code' => null ) );
		}

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

		// The orphan: its English original stops being listable. Written to the row directly, so that no save
		// hook copies the status to the translation.
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $orphan_en ) );
		clean_post_cache( $orphan_en );

		$records = array( $pair_en, $pair_ar, $english, $arabic, $orphan_en, $orphan_ar, $out_en, $out_ar, $out_arabic, $variable_en, $variable_ar, $size_en, $size_ar, $roomy_en, $roomy_ar );
		$name    = array(
			$pair_en => 'pair(en)', $pair_ar => 'pair(ar)', $english => 'english', $arabic => 'arabic', $orphan_en => 'orphan(en,draft)',
			$orphan_ar => 'orphan(ar)', $out_en => 'out(en)', $out_ar => 'out(ar)', $out_arabic => 'out-arabic', $size_en => 'size(en)',
			$size_ar => 'size(ar)', $roomy_en => 'roomy(en)', $roomy_ar => 'roomy(ar)', $variable_en => 'parent(en)', $variable_ar => 'parent(ar)',
		);
		$named = fn( array $ids ) => implode( ', ', array_map( fn( $id ) => $name[ $id ] ?? "#$id", $ids ) ) ?: 'nothing';
		// Only the fixtures are compared, so a product left by another scenario cannot change a verdict.
		$mine = fn( array $ids ) => array_values( array_intersect( $ids, $records ) );

		$expected_low = $sorted( array( $pair_en, $english, $arabic, $orphan_ar, $size_en ) );
		$expected_out = $sorted( array( $out_en, $out_arabic ) );

		// ---- The screen, in three admin language contexts --------------------------------------------------
		$screens = array();
		$seen    = array();
		foreach ( array( 'en', 'ar', 'all' ) as $lang ) {
			$screen           = $w->analytics_screen( 'stock', $lang );
			$screens[ $lang ] = $screen;
			$usable           = 200 === $screen['status'] && '' !== $screen['root'] && '' !== $screen['nonce'];
			$check( $usable, sprintf( '[%s] the signed-in Analytics Stock screen answers %d and hands the browser the REST address %s', $lang, $screen['status'], $screen['root'] ?: 'none' ) );
			if ( ! $usable ) {
				continue;
			}

			$low     = t26_list( $w, $screen, array( 'type' => 'lowstock' ) );
			$out     = t26_list( $w, $screen, array( 'type' => 'outofstock' ) );
			$summary = t26_summary( $w, $screen );

			$check( '' === $low['error'] && '' === $out['error'], "[$lang] the report answers over HTTP" . ( $low['error'] . $out['error'] ? ': ' . $low['error'] . ' ' . $out['error'] : '' ) );
			$check( $sorted( $mine( $low['ids'] ) ) === $expected_low, "[$lang] AC-246-01, 03, 07 low-stock lists each physical item once: " . $named( $mine( $low['ids'] ) ) );
			$check( $sorted( $mine( $out['ids'] ) ) === $expected_out, "[$lang] AC-246-02, 03 out-of-stock lists each physical item once: " . $named( $mine( $out['ids'] ) ) );
			$check( count( $low['ids'] ) === count( array_unique( $low['ids'] ) ) && count( $out['ids'] ) === count( array_unique( $out['ids'] ) ), "[$lang] no line is repeated" );

			$quantities_ok = ( $low['quantities'][ $pair_en ] ?? null ) === 2 && ( $low['quantities'][ $english ] ?? null ) === 2
				&& ( $low['quantities'][ $arabic ] ?? null ) === 1 && ( $low['quantities'][ $orphan_ar ] ?? null ) === 1 && ( $low['quantities'][ $size_en ] ?? null ) === 1;
			$check( $quantities_ok, "[$lang] AC-246-05 each line shows the item's one figure, the pair 2 and not 4" );
			$check( $low['total'] === count( $low['ids'] ) && $out['total'] === count( $out['ids'] ), sprintf( '[%s] AC-246-06 the total equals the lines: low %d of %d, out %d of %d', $lang, $low['total'], count( $low['ids'] ), $out['total'], count( $out['ids'] ) ) );

			// The summary under the table is part of the same screen, and must count what the lists hold.
			$every    = t26_page( $w, $screen, array( 'per_page' => 1 ) );
			$instock  = t26_page( $w, $screen, array( 'type' => 'instock', 'per_page' => 1 ) );
			$on_order = t26_page( $w, $screen, array( 'type' => 'onbackorder', 'per_page' => 1 ) );
			$lists    = array( 'products' => $every['total'], 'lowstock' => $low['total'], 'outofstock' => $out['total'], 'instock' => $instock['total'], 'onbackorder' => $on_order['total'] );
			$agrees   = true;
			foreach ( $lists as $figure => $total ) {
				$agrees = $agrees && ( $summary[ $figure ] ?? null ) === $total;
			}
			$check( $agrees, sprintf( '[%s] AC-246-06 the summary under the table counts physical items: summary %s, lists %s', $lang, wp_json_encode( array_intersect_key( $summary, $lists ) ), wp_json_encode( $lists ) ) );

			// The path t24 takes, beside the path the screen takes.
			$inside = $sorted( $mine( t26_in_process( $lang, 'lowstock' ) ) );
			$check( $inside === $sorted( $mine( $low['ids'] ) ), sprintf( '[%s] the in-process dispatch and the HTTP request return the same low-stock list: in process %s; over HTTP %s', $lang, $named( $inside ), $named( $sorted( $mine( $low['ids'] ) ) ) ) );

			$seen[ $lang ] = array( $sorted( $low['ids'] ), $sorted( $out['ids'] ), $low['total'], $out['total'], $summary );
		}
		// AC-246-04 over the whole lists and the summary, not only the fixtures: the three contexts must agree.
		$check( 3 === count( $seen ) && $seen['en'] === $seen['ar'] && $seen['ar'] === $seen['all'], 'AC-246-04 the three admin language contexts return the same lists, the same totals and the same summary' );
		if ( 3 !== count( $seen ) ) {
			return false;
		}

		// ---- AC-246-06: paging counts physical items, in the context that failed on staging ----------------
		$arabic_screen = $screens['ar'];
		$total         = $seen['ar'][2];
		$first         = t26_page( $w, $arabic_screen, array( 'type' => 'lowstock', 'per_page' => 2 ) );
		$walked        = t26_list( $w, $arabic_screen, array( 'type' => 'lowstock', 'per_page' => 2 ) );
		$check( $first['pages'] === (int) ceil( $total / 2 ), sprintf( '[ar] AC-246-06 two per page gives %d pages for %d items', $first['pages'], $total ) );
		$check( count( $walked['ids'] ) === $total && count( array_unique( $walked['ids'] ) ) === $total, sprintf( '[ar] AC-246-06 walking every page yields %d distinct lines for a total of %d', count( array_unique( $walked['ids'] ) ), $total ) );
		$check( $sorted( $mine( $walked['ids'] ) ) === $expected_low, '[ar] AC-246-06 no fixture is lost or repeated at a page boundary' );

		// ---- Sorting keeps one line per item, and the order asked for --------------------------------------
		foreach ( array( array( 'stock_quantity', 'asc' ), array( 'stock_quantity', 'desc' ), array( 'sku', 'asc' ) ) as $sort ) {
			$list   = t26_list( $w, $arabic_screen, array( 'type' => 'lowstock', 'orderby' => $sort[0], 'order' => $sort[1] ) );
			$order  = array_values( array_filter( array_map( fn( $id ) => $list['quantities'][ $id ] ?? null, $list['ids'] ), fn( $q ) => null !== $q ) );
			$wanted = $order;
			'asc' === $sort[1] ? sort( $wanted ) : rsort( $wanted );
			$in_order = 'stock_quantity' !== $sort[0] || $order === $wanted;
			$check( $sorted( $mine( $list['ids'] ) ) === $expected_low && $list['total'] === $total && $in_order, sprintf( '[ar] sorted by %s %s: one line per item, total %d, order kept', $sort[0], $sort[1], $list['total'] ) );
		}

		// ---- AC-246-08: the exporter, in a request that is neither wp-admin nor WP-CLI ----------------------
		$secret = wp_generate_password( 24, false );
		update_option( 't26_front_probe_secret', $secret, false );
		wp_mkdir_p( WPMU_PLUGIN_DIR );
		copy( __DIR__ . '/fixtures/t26-front-probe.php.txt', WPMU_PLUGIN_DIR . '/t26-front-probe.php' );
		$s->on_finish( function () {
			@unlink( WPMU_PLUGIN_DIR . '/t26-front-probe.php' );
			delete_option( 't26_front_probe_secret' );
		} );
		$plain_ids = array( $pair_en, $pair_ar, $english, $arabic );
		foreach ( array( 'en' => array( $pair_en, $english ), 'ar' => array( $pair_ar, $arabic ) ) as $lang => $own_language ) {
			$home  = (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
			$r     = wp_remote_get( add_query_arg( array( 't26_probe' => $secret, 'ids' => implode( ',', $plain_ids ) ), $home ), array( 'timeout' => 120, 'redirection' => 0, 'cookies' => array() ) );
			$probe = is_wp_error( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
			if ( ! is_array( $probe ) ) {
				$check( false, "[$lang] the front-end probe did not answer: " . substr( is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_body( $r ), 0, 160 ) );
				continue;
			}
			$check( false === $probe['admin'] && false === $probe['cli'] && $lang === $probe['language'], sprintf( '[%s] the probe ran in a front-end request: wp-admin %s, WP-CLI %s, language %s', $lang, var_export( $probe['admin'], true ), var_export( $probe['cli'], true ), $probe['language'] ) );
			$skus = array_values( array_filter( $probe['skus'], fn( $sku ) => 0 === strpos( $sku, 'T26-' ) ) );
			sort( $skus );
			$check( array( 'T26-ARABIC', 'T26-ENGLISH', 'T26-ORPHAN', 'T26-PAIR', 'T26-VAR-S' ) === $skus, "[$lang] AC-246-08 the export holds each physical item once: " . ( implode( ', ', $skus ) ?: 'nothing' ) );
			$check( $probe['total'] === $total, sprintf( '[%s] AC-246-08 the export total is %d, the screen total %d', $lang, $probe['total'], $total ) );
			// FR-010: the correction reaches the report's query and no other.
			$check( $sorted( $probe['plain'] ) === $sorted( $own_language ), "[$lang] FR-010 an ordinary product query in the same request is still scoped to its language: " . $named( $probe['plain'] ) );
		}

		return $ok;
	}
);

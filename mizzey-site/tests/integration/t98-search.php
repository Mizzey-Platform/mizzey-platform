<?php
/**
 * t98: the store's own product search (specs/058-search).
 *
 * Over HTTP through the Store API search WooCommerce's blocks use, the suggestion route and the results page.
 *
 * AC-1: a search finds products by name, description, SKU (an exact SKU first), brand and category, every word of the
 *       query matched, in English and Arabic.
 * AC-2: Arabic is matched as it is written in its several equal ways (hamza forms, taa marbuta, alef maqsura, short
 *       vowels, Arabic-Indic digits).
 * AC-3: a term on the synonym list, in any of its forms (Arabic, English, Franco-Arabic, a misspelling, several words),
 *       finds what the others find; a misspelling the list does not name finds nothing (no automatic typo tolerance).
 * AC-4: the list is managed in the admin by staff, and a change applies at once.
 * AC-5: suggestions as one types: products and categories, from two characters, and the results address; a search with
 *       no result offers the most stocked categories and the best sellers.
 * AC-6: every search is recorded with its term and result count, a search with no result told apart; suggestions are
 *       not recorded; nothing about the person is.
 * AC-7: the results page is the product archive with its filters.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Search\Normalise;
use MizzeySite\Search\ProductSearch;
use MizzeySite\Search\SearchIndex;
use MizzeySite\Search\Synonyms;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't98-search', 'SRCH-01 to SRCH-08, SRCH-06a, PLP-15: the store\'s own product search' ),
	function ( Scenario $s ): bool {
		global $sitepress, $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'Q' . wp_rand( 100000, 999999 ); // A word no other product has, so each search can be narrowed to this run.
		SearchIndex::install();
		ProductSearch::installLog();
		$kept = get_option( Synonyms::OPTION, null );
		$terms = array();
		$s->on_finish(
			static function () use ( $kept, &$terms, $tag ): void {
				null === $kept ? delete_option( Synonyms::OPTION ) : update_option( Synonyms::OPTION, $kept, false );
				foreach ( array_reverse( $terms ) as $t ) {
					wp_delete_term( $t[0], $t[1] );
				}
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ProductSearch::logTable() . ' WHERE term LIKE %s', '%' . $wpdb->esc_like( strtolower( $tag ) ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ProductSearch::logTable() . ' WHERE term LIKE %s', '%' . $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		);
		update_option( Synonyms::OPTION, '', false );
		Synonyms::forget();
		do_action( 'wpml_switch_language', 'en' );
		$cat      = wp_insert_term( "{$tag} Haircare", 'product_cat' );
		$terms[]  = array( (int) $cat['term_id'], 'product_cat' );
		$brand_id = 0;
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brand    = wp_insert_term( "{$tag} Lumina", 'product_brand' );
			$brand_id = (int) $brand['term_id'];
			$terms[]  = array( $brand_id, 'product_brand' );
		}
		$make = static function ( string $name, string $sku, string $description ) use ( $s, $cat, $brand_id ): \WC_Product {
			$p = $s->simple_product( $name, '100.00', null );
			$p->set_sku( $sku );
			$p->set_description( $description );
			$p->save();
			wp_set_object_terms( $p->get_id(), array( (int) $cat['term_id'] ), 'product_cat' );
			if ( $brand_id > 0 ) {
				wp_set_object_terms( $p->get_id(), array( $brand_id ), 'product_brand' );
			}
			return $p;
		};
		$dryer    = $make( "{$tag} Ionic Hair Dryer", "{$tag}-HD1", 'Dries hair fast with less heat.' );
		$lipstick = $make( "{$tag} Matte Lipstick", "{$tag}-LS1", 'A long-lasting colour.' );
		$serum    = $make( "{$tag} Night Serum", "{$tag}-SR1", 'For the face, applied before sleep.' );
		// The Arabic records, with Arabic names as the client writes them.
		$arabic = array();
		foreach ( array( array( $dryer, "{$tag} مُجفف شعر أيوني" ), array( $lipstick, "{$tag} أحمر شفاه مطفي" ), array( $serum, "{$tag} سيروم ليلي ١٢ ساعة" ) ) as list( $p, $name ) ) {
			$sitepress->make_duplicate( $p->get_id(), 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $p->get_id(), 'product', false, 'ar' );
			$s->track_post( $ar );
			wp_update_post( array( 'ID' => $ar, 'post_title' => $name ) );
			delete_post_meta( $ar, '_icl_lang_duplicate_of' );
			SearchIndex::index( wc_get_product( $ar ) );
			$arabic[ $p->get_id() ] = $ar;
		}
		wp_cache_flush();
		$find = static function ( string $q, string $lang = 'en' ) use ( $tag ): array {
			$base = 'ar' === $lang ? home_url( '/ar/wp-json/wc/store/v1/products' ) : rest_url( 'wc/store/v1/products' );
			$r    = sign_in_json( 'GET', $base . '?' . http_build_query( array( 'search' => $q, 'per_page' => 50 ) ), null );
			return array_values( array_filter( array_map( static fn ( $p ) => (int) $p['id'], (array) ( $r['data'] ?? array() ) ) ) );
		};
		$only = static fn ( array $found, array $want ): bool => array() === array_diff( $want, $found ) && array() === array_diff( $found, $want );

		// ---- AC-1: the fields ------------------------------------------------------------------------------------------
		$check( $only( $find( "{$tag} ionic" ), array( $dryer->get_id() ) ), 'AC-1 SRCH-01 by name' );
		$check( $only( $find( "{$tag} sleep" ), array( $serum->get_id() ) ), 'AC-1 SRCH-02 by description' );
		$check( $only( $find( "{$tag}-ls1" ), array( $lipstick->get_id() ) ), 'AC-1 SRCH-02 by SKU, whatever its case' );
		$check( 3 === count( $find( "{$tag} haircare" ) ) && ( 0 === $brand_id || 3 === count( $find( "{$tag} lumina" ) ) ), 'AC-1 SRCH-02 by category and by brand' );
		$check( array() === $find( "{$tag} ionic lipstick" ), 'AC-1 every word of the query must be found' );
		$check( $only( $find( "{$tag} مجفف", 'ar' ), array( $arabic[ $dryer->get_id() ] ) ), 'AC-1 SRCH-06a in Arabic, on the Arabic store, by the Arabic name' );

		// ---- AC-2: Arabic written its several ways ---------------------------------------------------------------------
		$check( 'احمر شفاه مطفي' === Normalise::text( 'أحمرُ شِفاهٍ مُطفي' ) && 'زجاجه' === Normalise::text( 'زجاجة' ) && 'مستشفي' === Normalise::text( 'مستشفى' ) && '12' === Normalise::text( '١٢' ) && 'creme' === Normalise::text( 'Crème' ), 'AC-2 the normaliser: hamza forms, short vowels, taa marbuta, alef maqsura, Arabic-Indic digits, accents' );
		$check( $only( $find( "{$tag} احمر شفاه", 'ar' ), array( $arabic[ $lipstick->get_id() ] ) ) && $only( $find( "{$tag} سيروم 12", 'ar' ), array( $arabic[ $serum->get_id() ] ) ) && $only( $find( "{$tag} مجفف شعر ايوني", 'ar' ), array( $arabic[ $dryer->get_id() ] ) ), 'AC-2 SRCH-06a an Arabic search finds the product however the alef, the digits and the marks are written' );

		// ---- AC-3 and AC-4: the synonym list ---------------------------------------------------------------------------
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		$groups = Synonyms::store( "lipstick, lip stick, روج, احمر شفاه, ahmar shafayef, lipstik\nhair dryer, سشوار, seshwar\n\nlonely" );
		$check( 2 === $groups && 2 === count( Synonyms::groups() ), 'AC-4 SRCH-07 the list keeps a group per line and drops a line with a single form (' . $groups . ')' );
		$check( $only( $find( "{$tag} rouge" ), array() ) && $only( $find( "{$tag} روج" ), array( $lipstick->get_id() ) ) && $only( $find( "{$tag} ahmar shafayef" ), array( $lipstick->get_id() ) ) && $only( $find( "{$tag} lipstik" ), array( $lipstick->get_id() ) ) && $only( $find( "{$tag} lip stick" ), array( $lipstick->get_id() ) ), 'AC-3 SRCH-06 on the English store, any listed form finds the lipstick (Arabic, Franco-Arabic, a misspelling, two words); an unlisted word finds nothing' );
		$check( $only( $find( "{$tag} روج", 'ar' ), array( $arabic[ $lipstick->get_id() ] ) ) && $only( $find( "{$tag} سشوار" ), array( $dryer->get_id() ) ) && $only( $find( "{$tag} seshwar" ), array( $dryer->get_id() ) ), 'AC-3 on the Arabic store too, and a form standing for several words (hair dryer) finds by them' );
		$check( array() === $find( "{$tag} lipstck" ) && array() === $find( "{$tag} serun" ), 'AC-3 a misspelling the list does not name finds nothing: no automatic typo tolerance' );
		// The admin screen's save: staff only, with its nonce.
		wp_set_current_user( $admin );
		ob_start();
		Synonyms::screen();
		$screen = (string) ob_get_clean();
		wp_set_current_user( 0 );
		$check( str_contains( $screen, 'name="mizzey_search_synonyms"' ) && str_contains( $screen, 'lipstick, lip stick' ) && str_contains( $screen, 'no automatic typo correction' ), 'AC-4 the admin screen shows the list for staff to change, and says what it does not do' );
		Synonyms::store( "lipstick, lip stick, روج, احمر شفاه, ahmar shafayef, lipstik\nhair dryer, سشوار, seshwar\nserum, serun" );
		$check( $only( $find( "{$tag} serun" ), array( $serum->get_id() ) ), 'AC-4 a form added in the admin applies at once' );

		// ---- AC-1 order: an exact SKU first ----------------------------------------------------------------------------
		$sku_first = $make( "{$tag} Hair Oil {$tag}-SR1X", "{$tag}-SR1X", 'An oil.' );
		wp_cache_flush();
		$ordered = new \WP_Query( array( 'post_type' => 'product', 's' => "{$tag}-sr1", 'orderby' => 'relevance', 'posts_per_page' => 10, 'fields' => 'ids', 'suppress_filters' => false ) );
		$check( array( $serum->get_id(), $sku_first->get_id() ) === array_map( 'intval', $ordered->posts ), 'AC-1 PLP-15 an exact SKU comes first in the relevance order, before a name that contains it (' . wp_json_encode( $ordered->posts ) . ')' );

		// ---- AC-5: suggestions ----------------------------------------------------------------------------------------
		$suggest = static fn ( string $q, string $lang = 'en' ): array => (array) ( sign_in_json( 'GET', ( 'ar' === $lang ? home_url( '/ar/wp-json/mizzey/v1/search/suggest' ) : rest_url( 'mizzey/v1/search/suggest' ) ) . '?' . http_build_query( array( 'q' => $q ) ), null )['data'] ?? array() );
		$typed  = $suggest( "{$tag} hai" );
		$short  = $suggest( 'h' );
		$ar_s   = $suggest( "{$tag} مجف", 'ar' );
		$check( in_array( $dryer->get_id(), array_column( (array) ( $typed['products'] ?? array() ), 'id' ), true ) && in_array( "{$tag} Haircare", array_column( (array) ( $typed['terms'] ?? array() ), 'name' ), true ) && str_contains( (string) ( $typed['url'] ?? '' ), 's=' ) && array() === ( $short['products'] ?? null ), 'AC-5 SRCH-03 typing offers products and categories, and the results address; one letter offers nothing (' . wp_json_encode( array( array_column( (array) ( $typed['products'] ?? array() ), 'id' ), array_column( (array) ( $typed['terms'] ?? array() ), 'name' ), $typed['url'] ?? null, $short['products'] ?? null ) ) . ')' );
		$check( in_array( $arabic[ $dryer->get_id() ], array_column( (array) ( $ar_s['products'] ?? array() ), 'id' ), true ), 'AC-5 suggestions on the Arabic store are the Arabic records' );
		$none = $suggest( "{$tag} zzzz" );
		$check( array() === $none['products'] && is_array( $none['alternatives'] ?? null ) && array() !== ( $none['alternatives']['categories'] ?? array() ) && array() !== ( $none['alternatives']['products'] ?? array() ), 'AC-5 SRCH-05 no result offers the most stocked categories and the best sellers instead' );

		// ---- AC-6: the log --------------------------------------------------------------------------------------------
		$logged = static fn ( string $term ): array => array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT results FROM ' . ProductSearch::logTable() . ' WHERE term = %s', $term ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . ProductSearch::logTable() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$check( array( 1 ) === $logged( "{$tag} ionic" ) && array( 0 ) === $logged( "{$tag} lipstck" ) && array() === $logged( "{$tag} hai" ) && array( 'id', 'term', 'normalised', 'language', 'results', 'searched_at' ) === $columns, 'AC-6 SRCH-08 each search is recorded once with its result count, a search with none told apart by its 0; suggestions are not recorded; nothing about the person is (' . wp_json_encode( array( $logged( "{$tag} ionic" ), $logged( "{$tag} lipstck" ) ) ) . ')' );

		// ---- AC-7: the results page ------------------------------------------------------------------------------------
		$url  = add_query_arg( array( 's' => "{$tag} haircare", 'post_type' => 'product' ), home_url( '/' ) );
		$page = wp_remote_get( $url, array( 'timeout' => 60 ) );
		$body = (string) wp_remote_retrieve_body( $page );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $page ), $body );
		$check( 200 === (int) wp_remote_retrieve_response_code( $page ) && storefront_shows( $body, "{$tag} Ionic Hair Dryer" ) && storefront_shows( $body, "{$tag} Night Serum" ) && 1 === preg_match( '/woocommerce-result-count|wc-block-product-results-count|woocommerce-ordering/i', $body ), 'AC-7 SRCH-04 the results page lists the matches as the product archive, with its count and ordering' );
		$check( array( 3, 4 ) === $logged( "{$tag} haircare" ), 'AC-6 a search made on the results page is recorded too, with its count (the hair oil joined the category since) (' . wp_json_encode( $logged( "{$tag} haircare" ) ) . ')' );

		return $ok;
	}
);

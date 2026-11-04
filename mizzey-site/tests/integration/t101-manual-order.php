<?php
/**
 * t101: manual product ordering within a category and a collection (specs/061-manual-order).
 *
 * AC-1: positions set on a category's edit screen order its page: the positioned products first, by position, then the
 *       rest; the Arabic page in the same order.
 * AC-2: the Store API, for that category sorted by the store's order, answers the same order; a shopper's other sort
 *       (price) is left as it is.
 * AC-3: a collection is ordered the same way, and a change on its screen applies at once.
 * AC-4: positions are saved only with the screen's nonce and the permission.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Collections;
use MizzeySite\Storefront\ManualOrder;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't101-manual-order', 'MER-03, ADM-55, ADM-57: manual product ordering within categories and collections' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag   = 'T101' . wp_rand( 1000, 9999 );
		$terms = array();
		$s->on_finish( static function () use ( &$terms ): void { foreach ( $terms as $t ) { wp_delete_term( $t[0], $t[1] ); } } );
		do_action( 'wpml_switch_language', 'en' );
		$cat  = wp_insert_term( "{$tag} Kitchen", 'product_cat' );
		$coll = wp_insert_term( "{$tag} Summer", Collections::TAXONOMY );
		$terms[] = array( (int) $cat['term_id'], 'product_cat' );
		$terms[] = array( (int) $coll['term_id'], Collections::TAXONOMY );
		$names = array( 'Alpha', 'Bravo', 'Charlie', 'Delta' );
		$ids   = array();
		foreach ( $names as $i => $name ) {
			$p = $s->simple_product( "{$tag} {$name}", (string) ( 100 + $i * 10 ) . '.00', null );
			wp_set_object_terms( $p->get_id(), array( (int) $cat['term_id'] ), 'product_cat' );
			wp_set_object_terms( $p->get_id(), array( (int) $coll['term_id'] ), Collections::TAXONOMY );
			$ids[ $name ] = $p->get_id();
		}
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		$order = static function ( int $term, array $positions, ?string $nonce = null ) use ( $admin, $ids ): void {
			wp_set_current_user( $admin );
			$_POST = array( 'mizzey_manual_order_nonce' => $nonce ?? wp_create_nonce( 'mizzey_manual_order' ), 'mizzey_position' => array() );
			foreach ( $ids as $name => $id ) {
				$_POST['mizzey_position'][ $id ] = (string) ( $positions[ $name ] ?? '' );
			}
			ManualOrder::save( $term );
			$_POST = array();
			wp_set_current_user( 0 );
		};
		// Charlie first, then Alpha; Bravo and Delta follow in the store's order.
		$order( (int) $cat['term_id'], array( 'Charlie' => 1, 'Alpha' => 2 ) );
		wp_cache_flush();
		$listed = static function ( string $body ) use ( $tag, $names ): array {
			$pos = array();
			foreach ( $names as $name ) {
				$at = strpos( $body, "{$tag} {$name}" );
				if ( false !== $at ) {
					$pos[ $name ] = $at;
				}
			}
			asort( $pos );
			return array_keys( $pos );
		};
		$page = static function ( string $url ): string {
			$r = wp_remote_get( $url, array( 'timeout' => 60 ) );
			$b = (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), $b );
			$b = (string) preg_replace( '#<head\b.*?</head>#is', '', $b ); // The page's own list, not its head.
			return $b;
		};

		// ---- AC-1: the category page ------------------------------------------------------------------------------------
		$en = $listed( $page( (string) get_term_link( (int) $cat['term_id'], 'product_cat' ) ) );
		$check( array( 'Charlie', 'Alpha' ) === array_slice( $en, 0, 2 ) && 4 === count( $en ), 'AC-1 MER-03 the category page lists the positioned products first, by position, then the rest (' . implode( ', ', $en ) . ')' );

		// ---- AC-2: the Store API --------------------------------------------------------------------------------------
		$api = static function ( array $args ) use ( $tag ): array {
			$r = sign_in_json( 'GET', rest_url( 'wc/store/v1/products' ) . '?' . http_build_query( $args + array( 'per_page' => 50 ) ), null );
			return array_values( array_map( static fn ( $p ) => trim( str_replace( "{$tag} ", '', (string) $p['name'] ) ), (array) ( $r['data'] ?? array() ) ) );
		};
		$store = $api( array( 'category' => (string) $cat['term_id'], 'orderby' => 'menu_order', 'order' => 'asc' ) );
		$price = $api( array( 'category' => (string) $cat['term_id'], 'orderby' => 'price', 'order' => 'desc' ) );
		$check( array( 'Charlie', 'Alpha' ) === array_slice( $store, 0, 2 ) && array( 'Delta', 'Charlie', 'Bravo', 'Alpha' ) === $price, 'AC-2 the Store API answers the same order for the store\'s sorting, and leaves the price sorting as it is (' . wp_json_encode( array( $store, $price ) ) . ')' );

		// ---- AC-3: a collection, changed at once ------------------------------------------------------------------------
		$order( (int) $coll['term_id'], array( 'Delta' => 1, 'Bravo' => 2 ) );
		wp_cache_flush();
		$c1 = $listed( $page( (string) get_term_link( (int) $coll['term_id'], Collections::TAXONOMY ) ) );
		$order( (int) $coll['term_id'], array( 'Bravo' => 1, 'Delta' => 2 ) );
		wp_cache_flush();
		$c2 = $listed( $page( (string) get_term_link( (int) $coll['term_id'], Collections::TAXONOMY ) ) );
		$check( array( 'Delta', 'Bravo' ) === array_slice( $c1, 0, 2 ) && array( 'Bravo', 'Delta' ) === array_slice( $c2, 0, 2 ), 'AC-3 MER-03 a collection is ordered by its own positions, independently of the category, and a change applies at once (' . wp_json_encode( array( $c1, $c2 ) ) . ')' );

		// ---- AC-4: the nonce --------------------------------------------------------------------------------------------
		$order( (int) $cat['term_id'], array( 'Delta' => 1 ), 'wrong' );
		$check( '' === (string) get_post_meta( $ids['Delta'], ManualOrder::PREFIX . (int) $cat['term_id'], true ) && '1' === (string) get_post_meta( $ids['Charlie'], ManualOrder::PREFIX . (int) $cat['term_id'], true ), 'AC-4 positions are saved only with the screen\'s nonce' );

		// ---- AC-1 in Arabic -----------------------------------------------------------------------------------------------
		$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( (int) $cat['term_id'], 'product_cat' )->term_taxonomy_id, 'tax_product_cat' );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_cat = wp_insert_term( "{$tag} المطبخ", 'product_cat', array( 'slug' => strtolower( $tag ) . '-kitchen-ar' ) );
		do_action( 'wpml_switch_language', 'en' );
		$terms[] = array( (int) $ar_cat['term_id'], 'product_cat' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( (int) $ar_cat['term_id'], 'product_cat' )->term_taxonomy_id, 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		foreach ( $ids as $id ) {
			$sitepress->make_duplicate( $id, 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ar' );
			if ( $ar > 0 ) {
				$s->track_post( $ar );
				wp_set_object_terms( $ar, array( (int) $ar_cat['term_id'] ), 'product_cat' );
			}
		}
		$order( (int) $cat['term_id'], array( 'Charlie' => 1, 'Alpha' => 2 ) ); // Saved again, so the Arabic records carry it.
		wp_cache_flush();
		do_action( 'wpml_switch_language', 'ar' );
		$ar_url = (string) get_term_link( (int) $ar_cat['term_id'], 'product_cat' );
		do_action( 'wpml_switch_language', 'en' );
		$ar_list = $listed( $page( $ar_url ) );
		$check( array( 'Charlie', 'Alpha' ) === array_slice( $ar_list, 0, 2 ), 'AC-1 SSC-12 the Arabic category page is in the same order (' . implode( ', ', $ar_list ) . ')' );

		return $ok;
	}
);

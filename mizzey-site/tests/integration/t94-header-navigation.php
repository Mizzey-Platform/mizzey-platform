<?php
/**
 * t94: what the header and its navigation show, as data (specs/054-header-navigation).
 *
 * AC-1: the header's data: the cart's live item count, the wishlist's count, whether the visitor is signed in, and the
 *       addresses, in the request's language, never cached.
 * AC-2: the category navigation is the admin's: the categories in WooCommerce's own order, with their children, in
 *       the request's language; empty categories left out.
 * AC-3: changed in the admin (a new order, a category taken out of the main menu), the navigation follows, in both
 *       languages, with no code change.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\HeaderData;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** The tagged nodes of a navigation tree, by name, with their children's names. */
function t94_nodes( array $tree, string $tag ): array {
	$out = array();
	foreach ( $tree as $node ) {
		if ( str_contains( (string) $node['name'], $tag ) ) {
			$out[] = array( $node['name'], array_map( static fn ( $c ) => $c['name'], (array) $node['children'] ), $node['url'] );
		}
	}
	return $out;
}

run(
	new Scenario( 't94-header-navigation', 'NAV-03 to NAV-07, NAV-09, NAV-10: the header and its navigation as data' ),
	function ( Scenario $s ): bool {
		// Adding to the cart asks the stock source (ERP-03, specs/062): the stand-in answers for this run.
		$fixture_before = get_option( \MizzeySite\Stock\FixtureStockSource::OPTION );
		$s->on_finish( static function () use ( $fixture_before ) { update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, $fixture_before ); } );
		update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, 'yes' );
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag   = 'T94' . wp_rand( 1000, 9999 );
		$terms = array();
		$s->on_finish(
			static function () use ( &$terms ): void {
				foreach ( array_reverse( $terms ) as $term ) {
					wp_delete_term( $term, 'product_cat' );
				}
			}
		);
		// A category and its Arabic translation, made as the admin makes them.
		$category = static function ( string $en, string $ar, int $parent = 0, int $ar_parent = 0 ) use ( &$terms ): array {
			do_action( 'wpml_switch_language', 'en' );
			$made = wp_insert_term( $en, 'product_cat', array( 'parent' => $parent ) );
			$id   = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
			$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( $id, 'product_cat' )->term_taxonomy_id, 'tax_product_cat' );
			do_action( 'wpml_switch_language', 'ar' );
			$arm  = wp_insert_term( $ar, 'product_cat', array( 'parent' => $ar_parent ) );
			do_action( 'wpml_switch_language', 'en' );
			$ar_id = is_wp_error( $arm ) ? 0 : (int) $arm['term_id'];
			do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( $ar_id, 'product_cat' )->term_taxonomy_id, 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = $id;
			$terms[] = $ar_id;
			return array( $id, $ar_id );
		};
		list( $home, $home_ar )   = $category( "{$tag} Home", "{$tag} المنزل" );
		list( $lamps, $lamps_ar ) = $category( "{$tag} Lamps", "{$tag} مصابيح", $home, $home_ar );
		list( $beauty, $beauty_ar ) = $category( "{$tag} Beauty", "{$tag} الجمال" );
		list( $empty ) = $category( "{$tag} Empty", "{$tag} فارغ" );
		update_term_meta( $home, 'order', 2 );
		update_term_meta( $beauty, 'order', 1 );
		foreach ( array( array( "{$tag} Lamp", $lamps ), array( "{$tag} Cream", $beauty ) ) as list( $name, $cat ) ) {
			$product = $s->simple_product( $name, '100.00', null );
			wp_set_object_terms( $product->get_id(), array( $cat ), 'product_cat' );
			$sitepress->make_duplicate( $product->get_id(), 'ar' );
			$dup = (int) apply_filters( 'wpml_object_id', $product->get_id(), 'product', false, 'ar' );
			if ( $dup > 0 ) {
				$s->track_post( $dup );
			}
		}
		foreach ( array( $home, $lamps, $beauty, $home_ar, $lamps_ar, $beauty_ar ) as $t ) {
			wp_update_term_count_now( array( (int) get_term( $t, 'product_cat' )->term_taxonomy_id ), 'product_cat' );
		}
		wp_cache_flush();
		$nav = static fn ( string $lang ): array => (array) ( sign_in_json( 'GET', 'ar' === $lang ? home_url( '/ar/wp-json/mizzey/v1/navigation' ) : rest_url( 'mizzey/v1/navigation' ), null )['data']['categories'] ?? array() );

		// ---- AC-2: the admin's categories, in order, in each language -------------------------------------------
		$en = t94_nodes( $nav( 'en' ), $tag );
		$ar = t94_nodes( $nav( 'ar' ), $tag );
		$check( array( "{$tag} Beauty", "{$tag} Home" ) === array_column( $en, 0 ) && array( "{$tag} Lamps" ) === ( $en[1][1] ?? null ), 'AC-2 NAV-07 the navigation lists the categories in the admin\'s order, with their children, and leaves the empty one out (' . wp_json_encode( $en ) . ')' );
		$check( array( "{$tag} الجمال", "{$tag} المنزل" ) === array_column( $ar, 0 ) && array( "{$tag} مصابيح" ) === ( $ar[1][1] ?? null ) && str_contains( (string) ( $ar[0][2] ?? '' ), '/ar/' ), 'AC-2 the Arabic navigation names the same categories in Arabic, with Arabic addresses (' . wp_json_encode( $ar, JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-3: changed in the admin -----------------------------------------------------------------------------
		update_term_meta( $home, 'order', 0 );
		wp_cache_flush();
		$reordered = array_column( t94_nodes( $nav( 'en' ), $tag ), 0 );
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		$_POST = array( 'mizzey_menu_shown_nonce' => wp_create_nonce( 'mizzey_menu_shown' ) ); // The box left unticked.
		HeaderData::saveField( $beauty );
		$_POST = array();
		ob_start();
		HeaderData::editField( get_term( $beauty_ar, 'product_cat' ) );
		$field = (string) ob_get_clean();
		wp_set_current_user( 0 );
		wp_cache_flush();
		$after_en = array_column( t94_nodes( $nav( 'en' ), $tag ), 0 );
		$after_ar = array_column( t94_nodes( $nav( 'ar' ), $tag ), 0 );
		$check( array( "{$tag} Home", "{$tag} Beauty" ) === $reordered, 'AC-3 a new order set in the admin is the navigation\'s order (' . wp_json_encode( $reordered ) . ')' );
		$check( array( "{$tag} Home" ) === $after_en && array( "{$tag} المنزل" ) === $after_ar && str_contains( $field, 'name="mizzey_menu_shown"' ) && ! str_contains( $field, 'checked' ), 'AC-3 a category taken out of the main menu in the admin leaves it in both languages, and its Arabic edit screen shows the same setting (' . wp_json_encode( array( $after_en, $after_ar ), JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-1: the header's data ----------------------------------------------------------------------------------
		$product = $s->simple_product( "{$tag} Mug", '50.00', null );
		$cart    = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$before  = sign_in_json( 'GET', rest_url( 'mizzey/v1/header' ), null, array(), $cart['cookies'] );
		$added   = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $product->get_id(), 'quantity' => 2 ), array( 'Nonce' => sign_in_header( $cart, 'nonce' ) ), $cart['cookies'] );
		$jar     = sign_in_jar( $added['cookies'] );
		$wished  = sign_in_json( 'POST', rest_url( 'mizzey/v1/wishlist/items' ), array( 'id' => $product->get_id() ), array(), $jar );
		$jar     = sign_in_jar( $wished['cookies'] );
		$now     = sign_in_json( 'GET', rest_url( 'mizzey/v1/header' ), null, array(), $jar );
		$ar_now  = sign_in_json( 'GET', home_url( '/ar/wp-json/mizzey/v1/header' ), null, array(), $jar );
		$check( 0 === ( $before['data']['cart']['count'] ?? -1 ) && 2 === ( $now['data']['cart']['count'] ?? 0 ) && 1 === ( $now['data']['wishlist']['count'] ?? 0 ) && false === ( $now['data']['account']['signed_in'] ?? null ), 'AC-1 NAV-05, NAV-06 the header\'s data carries the cart\'s live count (0, then 2) and the wishlist\'s (1), for a guest (' . wp_json_encode( array( $before['data']['cart'] ?? null, $now['data']['cart'] ?? null, $now['data']['wishlist'] ?? null, $wished['status'] ) ) . ')' );
		$check( str_contains( (string) ( $ar_now['data']['cart']['url'] ?? '' ), '/ar/' ) && str_contains( (string) ( $ar_now['data']['account']['url'] ?? '' ), '/ar/' ) && str_contains( sign_in_header( $now, 'cache-control' ), 'no-store' ), 'AC-1 NAV-03, NAV-04 its addresses are in the request\'s language, and it is never cached (' . ( $ar_now['data']['cart']['url'] ?? '' ) . ')' );

		return $ok;
	}
);

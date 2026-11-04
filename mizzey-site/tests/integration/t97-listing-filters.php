<?php
/**
 * t97: the listing's filters, sorting and paging are WooCommerce's own, and hold in both languages
 * (specs/057-listing-filters).
 *
 * Over HTTP through the Store API, which WooCommerce's product collection and filter blocks read.
 *
 * AC-1: filters by category, brand, price range and availability return the matching products only, in English and
 *       Arabic (the attribute filter is t93's, on the options the load makes store attributes).
 * AC-2: sorting by newest, price ascending and descending, and best selling, kept while a filter changes.
 * AC-3: paging with the result count.
 * AC-4: a category's listing page carries its description, breadcrumb and result count, and its filters and sort
 *       live in the address, so returning to it keeps them.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't97-listing-filters', 'PLP-06 to PLP-10, PLP-12 to PLP-14, PLP-16 to PLP-18, PLP-20, PLP-21: listing filters and sorting' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag   = 'T97' . wp_rand( 1000, 9999 );
		$terms = array();
		$s->on_finish( static function () use ( &$terms ): void { foreach ( array_reverse( $terms ) as $t ) { wp_delete_term( $t[0], $t[1] ); } } );
		do_action( 'wpml_switch_language', 'en' );
		$term = static function ( string $name, string $taxonomy, string $description = '' ) use ( &$terms ): \WP_Term {
			$made    = wp_insert_term( $name, $taxonomy, array( 'description' => $description ) );
			$terms[] = array( (int) $made['term_id'], $taxonomy );
			return get_term( (int) $made['term_id'], $taxonomy );
		};
		$kitchen = $term( "{$tag} Kitchen", 'product_cat', "{$tag} Everything for the kitchen." );
		$beauty  = $term( "{$tag} Beauty", 'product_cat' );
		$brands  = taxonomy_exists( 'product_brand' );
		$lumen   = $brands ? $term( "{$tag} Lumen", 'product_brand' ) : null;
		$nova    = $brands ? $term( "{$tag} Nova", 'product_brand' ) : null;
		$make    = static function ( string $name, string $price, \WP_Term $cat, ?\WP_Term $brand, int $sales, string $stock, string $date ) use ( $s ): \WC_Product {
			$p = $s->simple_product( $name, $price, null );
			$p->set_total_sales( $sales );
			$p->set_stock_status( $stock );
			$p->set_date_created( $date );
			$p->save();
			wp_set_object_terms( $p->get_id(), array( $cat->term_id ), 'product_cat' );
			if ( $brand ) {
				wp_set_object_terms( $p->get_id(), array( $brand->term_id ), 'product_brand' );
			}
			return $p;
		};
		$a = $make( "{$tag} Alpha", '100.00', $kitchen, $lumen, 5, 'instock', '2026-01-01 10:00:00' );
		$b = $make( "{$tag} Bravo", '300.00', $kitchen, $nova, 50, 'outofstock', '2026-02-01 10:00:00' );
		$c = $make( "{$tag} Charlie", '200.00', $beauty, $lumen, 1, 'instock', '2026-03-01 10:00:00' );
		$sitepress->make_duplicate( $a->get_id(), 'ar' );
		$a_ar = (int) apply_filters( 'wpml_object_id', $a->get_id(), 'product', false, 'ar' );
		$s->track_post( $a_ar );
		wp_cache_flush();
		$minor = 10 ** wc_get_price_decimals();
		$ids   = array( $a->get_id(), $b->get_id(), $c->get_id() );
		$query = static function ( array $args, string $base = '' ) use ( $ids ): array {
			$url = ( '' !== $base ? $base : rest_url( 'wc/store/v1/products' ) ) . '?' . http_build_query( $args + array( 'include' => implode( ',', $ids ), 'per_page' => 100 ) );
			$r   = sign_in_json( 'GET', $url, null );
			return array( array_map( static fn ( $p ) => (string) $p['name'], (array) ( $r['data'] ?? array() ) ), $r );
		};
		$names = static fn ( array $args ): array => $query( $args )[0];
		$sorted = static function ( array $list ): array {
			sort( $list );
			return $list;
		};

		// ---- AC-1: filters --------------------------------------------------------------------------------------------
		$check( array( "{$tag} Alpha", "{$tag} Bravo" ) === $sorted( $names( array( 'category' => (string) $kitchen->term_id ) ) ), 'AC-1 PLP-06 by category: the kitchen products only' );
		if ( $brands ) {
			$check( array( "{$tag} Alpha", "{$tag} Charlie" ) === $sorted( $names( array( 'brand' => (string) $lumen->term_id ) ) ), 'AC-1 PLP-07 by brand: the Lumen products only' );
		}
		$check( array( "{$tag} Alpha", "{$tag} Charlie" ) === $sorted( $names( array( 'min_price' => (string) ( 100 * $minor ), 'max_price' => (string) ( 250 * $minor ) ) ) ), 'AC-1 PLP-08 by price range 100 to 250' );
		$check( array( "{$tag} Alpha", "{$tag} Charlie" ) === $sorted( $names( array( 'stock_status' => array( 'instock' ) ) ) ), 'AC-1 PLP-10 by availability: in stock only' );
		$ar_names = $query( array( 'include' => (string) $a_ar, 'min_price' => (string) ( 50 * $minor ), 'max_price' => (string) ( 150 * $minor ), 'stock_status' => array( 'instock' ) ), home_url( '/ar/wp-json/wc/store/v1/products' ) )[0];
		$ar_none  = $query( array( 'include' => (string) $a_ar, 'min_price' => (string) ( 250 * $minor ) ), home_url( '/ar/wp-json/wc/store/v1/products' ) )[0];
		$check( array( (string) get_post_field( 'post_title', $a_ar ) ) === $ar_names && array() === $ar_none, 'AC-1 the filters answer on the Arabic store with the Arabic record (' . wp_json_encode( array( $ar_names, $ar_none ), JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-2: sorting --------------------------------------------------------------------------------------------
		$check( array( "{$tag} Charlie", "{$tag} Bravo", "{$tag} Alpha" ) === $names( array( 'orderby' => 'date', 'order' => 'desc' ) ), 'AC-2 PLP-12 newest first' );
		$check( array( "{$tag} Alpha", "{$tag} Charlie", "{$tag} Bravo" ) === $names( array( 'orderby' => 'price', 'order' => 'asc' ) ) && array( "{$tag} Bravo", "{$tag} Charlie", "{$tag} Alpha" ) === $names( array( 'orderby' => 'price', 'order' => 'desc' ) ), 'AC-2 PLP-13 by price, ascending and descending' );
		$check( array( "{$tag} Bravo", "{$tag} Alpha", "{$tag} Charlie" ) === $names( array( 'orderby' => 'popularity', 'order' => 'desc' ) ), 'AC-2 PLP-14 best selling first' );
		$check( array( "{$tag} Charlie", "{$tag} Alpha" ) === $names( array( 'orderby' => 'price', 'order' => 'desc', 'stock_status' => array( 'instock' ) ) ), 'AC-2 the sort is kept while a filter is added' );

		// ---- AC-3: paging and the count -------------------------------------------------------------------------------
		list( $page1, $r1 ) = $query( array( 'orderby' => 'price', 'order' => 'asc', 'per_page' => 2, 'page' => 1 ) );
		list( $page2 )      = $query( array( 'orderby' => 'price', 'order' => 'asc', 'per_page' => 2, 'page' => 2 ) );
		$check( array( "{$tag} Alpha", "{$tag} Charlie" ) === $page1 && array( "{$tag} Bravo" ) === $page2 && '3' === sign_in_header( $r1, 'x-wp-total' ) && '2' === sign_in_header( $r1, 'x-wp-totalpages' ), 'AC-3 PLP-16, PLP-17 two pages of two, with the result count (' . sign_in_header( $r1, 'x-wp-total' ) . ')' );

		// ---- AC-4: the category page ----------------------------------------------------------------------------------
		$url  = add_query_arg( array( 'orderby' => 'price-desc', 'min_price' => '100', 'max_price' => '250' ), (string) get_term_link( $kitchen ) );
		$page = wp_remote_get( $url, array( 'timeout' => 60 ) );
		$body = (string) wp_remote_retrieve_body( $page );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $page ), $body );
		$check( 200 === (int) wp_remote_retrieve_response_code( $page ) && storefront_shows( $body, "{$tag} Everything for the kitchen." ) && storefront_shows( $body, "{$tag} Alpha" ) && ! storefront_shows( $body, "{$tag} Bravo" ), 'AC-4 PLP-20, PLP-18 the category page carries its description, and the filter and sort in its address (price 100 to 250) apply, so returning to that address keeps them' );
		$check( 1 === preg_match( '/woocommerce-breadcrumb|wp-block-woocommerce-breadcrumbs|aria-label="Breadcrumb"/i', $body ) && 1 === preg_match( '/woocommerce-result-count|wc-block-product-results-count/i', $body ), 'AC-4 PLP-21, PLP-17 the page carries the breadcrumb and the result count' );

		return $ok;
	}
);

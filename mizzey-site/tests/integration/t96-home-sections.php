<?php
/**
 * t96: what the home page's sections draw from (specs/056-home-sections).
 *
 * AC-1: a product's badges are mirrored into the badge taxonomy on every save, its Arabic record included.
 * AC-2: a Product Collection block on a page, filtered by a badge, shows the products flagged with it and only those,
 *       and follows a change of flag with no code change; a rule that matches nothing still renders the page.
 * AC-3: categories, brands and collections marked "Show on the home page" are listed in their position, in the
 *       request's language; the setting is the main-language term's, for both languages.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Storefront\HomeSections;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

/** A Product Collection block showing the products of one badge, by the block's own taxonomy filter. */
function t96_collection( int $badge ): string {
	$attrs = array(
		'queryId' => 1,
		'query'   => array( 'perPage' => 20, 'pages' => 0, 'offset' => 0, 'postType' => 'product', 'order' => 'asc', 'orderBy' => 'title', 'search' => '', 'exclude' => array(), 'inherit' => false, 'taxQuery' => array( HomeSections::BADGE_TAXONOMY => array( $badge ) ), 'isProductCollectionBlock' => true, 'woocommerceOnSale' => false, 'woocommerceStockStatus' => array( 'instock', 'outofstock', 'onbackorder' ), 'woocommerceAttributes' => array(), 'woocommerceHandPickedProducts' => array() ),
		'tagName' => 'div',
		'collection' => 'woocommerce/product-collection/product-catalog',
	);
	return '<!-- wp:woocommerce/product-collection ' . wp_json_encode( $attrs ) . ' --><div class="wp-block-woocommerce-product-collection"><!-- wp:woocommerce/product-template --><!-- wp:post-title /--><!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection -->';
}

run(
	new Scenario( 't96-home-sections', 'HOME-02 to HOME-06, HOME-10, HOME-11: what the home page sections draw from' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T96' . wp_rand( 1000, 9999 );
		do_action( 'wpml_switch_language', 'en' );

		// ---- AC-1: the badges as terms ------------------------------------------------------------------------------
		$trend = $s->simple_product( "{$tag} Trend Lamp", '100.00', null );
		$trend->update_meta_data( ProductFields::BADGES, array( 'trending', 'new' ) );
		$trend->save();
		$plain = $s->simple_product( "{$tag} Plain Mug", '50.00', null );
		$sitepress->make_duplicate( $trend->get_id(), 'ar' );
		$trend_ar = (int) apply_filters( 'wpml_object_id', $trend->get_id(), 'product', false, 'ar' );
		$s->track_post( $trend_ar );
		$slugs = static fn ( int $id ): array => (array) wp_get_object_terms( $id, HomeSections::BADGE_TAXONOMY, array( 'fields' => 'slugs', 'orderby' => 'slug' ) );
		$check( array( 'new', 'trending' ) === $slugs( $trend->get_id() ) && array() === $slugs( $plain->get_id() ) && array( 'new', 'trending' ) === $slugs( $trend_ar ), 'AC-1 the badges set on the product are its badge terms, on its Arabic record too; a product with none has none (' . wp_json_encode( array( $slugs( $trend->get_id() ), $slugs( $trend_ar ) ) ) . ')' );

		// ---- AC-2: a section by a badge -------------------------------------------------------------------------------
		$trending = get_term_by( 'slug', 'trending', HomeSections::BADGE_TAXONOMY );
		$limited  = get_term_by( 'slug', 'limited', HomeSections::BADGE_TAXONOMY );
		$page     = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Home", 'post_content' => t96_collection( (int) $trending->term_id ) ) );
		$empty    = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Empty", 'post_content' => t96_collection( (int) $limited->term_id ) . '<!-- wp:paragraph --><p>' . $tag . ' after</p><!-- /wp:paragraph -->' ) );
		$s->on_finish( static function () use ( $page, $empty ): void { wp_delete_post( $page, true ); wp_delete_post( $empty, true ); } );
		$fetch = static function ( int $id ): string {
			$url = (string) get_permalink( $id );
			$r   = wp_remote_get( $url, array( 'timeout' => 60 ) );
			$b   = (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), $b );
			return 200 === (int) wp_remote_retrieve_response_code( $r ) ? $b : '';
		};
		$first = $fetch( $page );
		$check( storefront_shows( $first, "{$tag} Trend Lamp" ) && ! storefront_shows( $first, "{$tag} Plain Mug" ), 'AC-2 HOME-02 a Product Collection block filtered by "Trending" shows the trending product and not the other' );
		$plain->update_meta_data( ProductFields::BADGES, array( 'trending' ) );
		$plain->save();
		$trend = wc_get_product( $trend->get_id() );
		$trend->update_meta_data( ProductFields::BADGES, array( 'trending' ) );
		$trend->save();
		wp_cache_flush();
		$check( array( 'trending' ) === $slugs( $trend_ar ), 'AC-1 a later change of badges reaches the Arabic record\'s terms (' . wp_json_encode( $slugs( $trend_ar ) ) . ')' );
		$rest_tax = sign_in_json( 'GET', rest_url( 'wp/v2/taxonomies/' . HomeSections::BADGE_TAXONOMY ) . '?context=view', null );
		$check( 200 === $rest_tax['status'] && in_array( 'product', (array) ( $rest_tax['data']['types'] ?? array() ), true ), 'AC-2 the editor can list the badges for a product section\'s filter: the taxonomy is in the REST API (' . $rest_tax['status'] . ')' );
		wp_cache_flush();
		$second = $fetch( $page );
		$none   = $fetch( $empty );
		$check( storefront_shows( $second, "{$tag} Plain Mug" ) && storefront_shows( $second, "{$tag} Trend Lamp" ), 'AC-2 HOME-10 flagging another product in the admin adds it to the section, with no code change' );
		$check( '' !== $none && storefront_shows( $none, "{$tag} after" ), 'AC-2 a section whose rule matches nothing leaves the page whole' );
		$archive = wp_remote_get( add_query_arg( array( 'taxonomy' => HomeSections::BADGE_TAXONOMY, 'term' => 'trending' ), home_url( '/' ) ), array( 'timeout' => 60, 'redirection' => 0 ) ); // storefront-guard: exempt, a 404 is the answer.
		$check( 404 === (int) wp_remote_retrieve_response_code( $archive ), 'AC-2 a badge has no page of its own: its archive address answers 404 (' . wp_remote_retrieve_response_code( $archive ) . ')' );

		// ---- AC-3: terms on the home page ----------------------------------------------------------------------------
		$terms = array();
		$s->on_finish( static function () use ( &$terms ): void { foreach ( $terms as $t ) { wp_delete_term( $t[0], $t[1] ); } } );
		$make = static function ( string $taxonomy, string $en, string $ar ) use ( &$terms ): int {
			do_action( 'wpml_switch_language', 'en' );
			$m    = wp_insert_term( $en, $taxonomy );
			$id   = (int) $m['term_id'];
			$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( $id, $taxonomy )->term_taxonomy_id, 'tax_' . $taxonomy );
			do_action( 'wpml_switch_language', 'ar' );
			$a = wp_insert_term( $ar, $taxonomy );
			do_action( 'wpml_switch_language', 'en' );
			do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( (int) $a['term_id'], $taxonomy )->term_taxonomy_id, 'element_type' => 'tax_' . $taxonomy, 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = array( $id, $taxonomy );
			$terms[] = array( (int) $a['term_id'], $taxonomy );
			return $id;
		};
		$kitchen = $make( 'product_cat', "{$tag} Kitchen", "{$tag} المطبخ" );
		$beauty  = $make( 'product_cat', "{$tag} Beauty", "{$tag} الجمال" );
		$garden  = $make( 'product_cat', "{$tag} Garden", "{$tag} الحديقة" );
		$admin   = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		foreach ( array( array( $kitchen, 1 ), array( $beauty, 2 ) ) as list( $term, $position ) ) {
			$_POST = array( 'mizzey_on_home_nonce' => wp_create_nonce( 'mizzey_on_home' ), 'mizzey_on_home' => '1', 'mizzey_home_position' => (string) $position );
			HomeSections::saveFields( $term );
		}
		$_POST = array();
		wp_set_current_user( 0 );
		$list = static function ( string $lang ) use ( $tag ): array {
			$r = sign_in_json( 'GET', ( 'ar' === $lang ? home_url( '/ar/wp-json/mizzey/v1/home/terms' ) : rest_url( 'mizzey/v1/home/terms' ) ) . '?taxonomy=product_cat', null );
			return array_values( array_filter( array_column( (array) ( $r['data']['terms'] ?? array() ), 'name' ), static fn ( $n ) => str_contains( (string) $n, $tag ) ) );
		};
		$en = $list( 'en' );
		$ar = $list( 'ar' );
		$bad = sign_in_json( 'GET', rest_url( 'mizzey/v1/home/terms' ) . '?taxonomy=category', null );
		$check( array( "{$tag} Kitchen", "{$tag} Beauty" ) === $en && array( "{$tag} المطبخ", "{$tag} الجمال" ) === $ar && 400 === $bad['status'], 'AC-3 HOME-05 the categories shown on the home page, in their position, in each language; one not shown is not listed; another taxonomy is refused (' . wp_json_encode( array( $en, $ar ), JSON_UNESCAPED_UNICODE ) . ')' );
		$brands = taxonomy_exists( 'product_brand' );
		if ( $brands ) {
			$brand = $make( 'product_brand', "{$tag} Lumen", "{$tag} لومن" );
			wp_set_current_user( $admin );
			$_POST = array( 'mizzey_on_home_nonce' => wp_create_nonce( 'mizzey_on_home' ), 'mizzey_on_home' => '1', 'mizzey_home_position' => '0' );
			HomeSections::saveFields( $brand );
			$_POST = array();
			wp_set_current_user( 0 );
		}
		$coll = $make( 'product_collection', "{$tag} Summer", "{$tag} الصيف" );
		wp_set_current_user( $admin );
		$_POST = array( 'mizzey_on_home_nonce' => wp_create_nonce( 'mizzey_on_home' ), 'mizzey_on_home' => '1', 'mizzey_home_position' => '0' );
		HomeSections::saveFields( $coll );
		$_POST = array();
		wp_set_current_user( 0 );
		$names = static fn ( string $taxonomy ): array => array_values( array_filter( array_column( HomeSections::onHome( $taxonomy ), 'name' ), static fn ( $n ) => str_contains( (string) $n, $tag ) ) );
		$check( ( ! $brands || array( "{$tag} Lumen" ) === $names( 'product_brand' ) ) && array( "{$tag} Summer" ) === $names( 'product_collection' ), 'AC-3 HOME-04, HOME-06 brands and collections shown on the home page are listed too (' . wp_json_encode( array( $brands ? $names( 'product_brand' ) : null, $names( 'product_collection' ) ), JSON_UNESCAPED_UNICODE ) . ')' );

		return $ok;
	}
);

<?php
/**
 * t100: promotional banners, and each category's, brand's and collection's page content (specs/060-store-content).
 *
 * AC-1: a promotional banner shows only within its dates and on its targets (every page, the home page, a page, a
 *       category), in the page's language; removed, it is gone.
 * AC-2: its dates and targets are set by staff on its screen, with the nonce and the permission.
 * AC-3: a category, brand or collection carries a banner (image, alternative text, link) set on its screen, shown in
 *       both languages, and the Arabic term may set its own; a collection has an image.
 * AC-4: a block hidden in the editor is not shown, and its content is kept (SSC-03, WordPress's own).
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Collections;
use MizzeySite\Storefront\Promotions;
use MizzeySite\Storefront\TermContent;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't100-store-content', 'SSC-03 to SSC-06, SSC-09, SSC-12 to SSC-14: promotional banners and term page content' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag   = 'T100' . wp_rand( 1000, 9999 );
		$posts = array();
		$terms = array();
		$s->on_finish( static function () use ( &$posts, &$terms ): void {
			foreach ( $posts as $p ) { wp_delete_post( $p, true ); }
			foreach ( $terms as $t ) { wp_delete_term( $t[0], $t[1] ); }
		} );
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		do_action( 'wpml_switch_language', 'en' );

		// ---- AC-2: a banner, its dates and targets set on its screen ----------------------------------------------------
		$page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Offers", 'post_content' => '<!-- wp:mizzey/promo-banners /--><!-- wp:paragraph --><p>' . $tag . ' page body</p><!-- /wp:paragraph -->' ) );
		$other = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Other", 'post_content' => '<!-- wp:mizzey/promo-banners /--><!-- wp:paragraph --><p>' . $tag . ' other body</p><!-- /wp:paragraph -->' ) );
		$posts = array_merge( $posts, array( $page, $other ) );
		$promo = static function ( string $title, string $start, string $end, array $targets, string $pages = '' ) use ( &$posts, $admin, $tag ): int {
			$id      = (int) wp_insert_post( array( 'post_type' => Promotions::TYPE, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<!-- wp:paragraph --><p>' . $title . ' banner</p><!-- /wp:paragraph -->' ) );
			$posts[] = $id;
			wp_set_current_user( $admin );
			$_POST = array( 'mizzey_promo_nonce' => wp_create_nonce( 'mizzey_promo' ), 'mizzey_promo_start' => $start, 'mizzey_promo_end' => $end, 'mizzey_promo_targets' => $targets, 'mizzey_promo_pages' => $pages, 'mizzey_promo_categories' => '' );
			Promotions::save( $id );
			$_POST = array();
			wp_set_current_user( 0 );
			return $id;
		};
		$fmt     = static fn ( int $t ): string => wp_date( 'Y-m-d\TH:i', $t );
		$now_on  = $promo( "{$tag} Now", $fmt( time() - HOUR_IN_SECONDS ), $fmt( time() + DAY_IN_SECONDS ), array(), (string) $page );
		$later   = $promo( "{$tag} Later", $fmt( time() + DAY_IN_SECONDS ), '', array(), (string) $page );
		$ended   = $promo( "{$tag} Ended", '', $fmt( time() - HOUR_IN_SECONDS ), array( 'all' ) );
		$every   = $promo( "{$tag} Everywhere", '', '', array( 'all' ) );
		$check( array( 'page:' . $page ) === get_post_meta( $now_on, Promotions::TARGETS, true ) && '' !== get_post_meta( $now_on, Promotions::START, true ) && array( 'all' ) === get_post_meta( $every, Promotions::TARGETS, true ), 'AC-2 SSC-09 the dates and targets are kept as staff set them on the banner\'s screen' );

		// ---- AC-1: shown within its dates, on its targets -----------------------------------------------------------------
		$fetch = static function ( int $id ): string {
			$url = (string) get_permalink( $id );
			$r   = wp_remote_get( $url, array( 'timeout' => 60 ) );
			$b   = (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), $b );
			return $b;
		};
		$on   = $fetch( $page );
		$off  = $fetch( $other );
		$check( storefront_shows( $on, "{$tag} Now banner" ) && storefront_shows( $on, "{$tag} Everywhere banner" ) && ! storefront_shows( $on, "{$tag} Later banner" ) && ! storefront_shows( $on, "{$tag} Ended banner" ) && storefront_shows( $on, "{$tag} page body" ), 'AC-1 SSC-09 the targeted page shows the banner in its dates and the one for every page; not the one not yet started, nor the one ended' );
		$check( ! storefront_shows( $off, "{$tag} Now banner" ) && storefront_shows( $off, "{$tag} Everywhere banner" ), 'AC-1 another page shows only the banner for every page' );
		$rest = sign_in_json( 'GET', rest_url( 'mizzey/v1/promos' ) . '?context=page:' . $page, null );
		$check( array( "{$tag} Everywhere", "{$tag} Now" ) === array_values( array_filter( array_map( static fn ( $p ) => (string) $p['title'], (array) ( $rest['data']['promos'] ?? array() ) ), static fn ( $t ) => str_contains( $t, $tag ) ) ), 'AC-1 the REST route answers the same for that page' );
		wp_trash_post( $every );
		$gone = $fetch( $other );
		$check( ! storefront_shows( $gone, "{$tag} Everywhere banner" ), 'AC-1 a banner moved to the bin is gone' );
		// In Arabic: the banner's Arabic version, with the original's dates and targets.
		$sitepress->make_duplicate( $now_on, 'ar' );
		$sitepress->make_duplicate( $page, 'ar' );
		$promo_ar = (int) apply_filters( 'wpml_object_id', $now_on, Promotions::TYPE, false, 'ar' );
		$page_ar  = (int) apply_filters( 'wpml_object_id', $page, 'page', false, 'ar' );
		$posts    = array_merge( $posts, array( $promo_ar, $page_ar ) );
		wp_update_post( array( 'ID' => $promo_ar, 'post_content' => '<!-- wp:paragraph --><p>' . $tag . ' عرض اليوم</p><!-- /wp:paragraph -->' ) );
		delete_post_meta( $promo_ar, '_icl_lang_duplicate_of' );
		$ar = $page_ar > 0 ? $fetch( $page_ar ) : '';
		$check( storefront_shows( $ar, "{$tag} عرض اليوم" ) && ! storefront_shows( $ar, "{$tag} Now banner" ), 'AC-1 SSC-12 the Arabic page shows the banner\'s Arabic version, on the original\'s dates and targets' );

		// ---- AC-3: term banners --------------------------------------------------------------------------------------
		$image = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => "{$tag} banner", 'post_status' => 'inherit' ), ABSPATH . 'wp-includes/images/w-logo-blue.png' );
		update_post_meta( $image, '_wp_attachment_image_alt', "{$tag} Summer sale" );
		wp_update_attachment_metadata( $image, array( 'width' => 80, 'height' => 80, 'file' => 'w-logo-blue.png' ) );
		$s->on_finish( static function () use ( $image ): void { wp_delete_post( $image, true ); } );
		$cat  = wp_insert_term( "{$tag} Kitchen", 'product_cat', array( 'description' => "{$tag} All for the kitchen." ) );
		$coll = wp_insert_term( "{$tag} Summer", Collections::TAXONOMY );
		$terms[] = array( (int) $cat['term_id'], 'product_cat' );
		$terms[] = array( (int) $coll['term_id'], Collections::TAXONOMY );
		wp_set_current_user( $admin );
		$_POST = array( 'mizzey_term_content_nonce' => wp_create_nonce( 'mizzey_term_content' ), 'mizzey_banner' => (string) $image, 'mizzey_banner_link' => home_url( '/offers/' ) );
		TermContent::save( (int) $cat['term_id'] );
		$_POST = array( 'mizzey_term_content_nonce' => wp_create_nonce( 'mizzey_term_content' ), 'mizzey_banner' => '', 'mizzey_banner_link' => '', 'mizzey_term_image' => (string) $image );
		TermContent::save( (int) $coll['term_id'] );
		$_POST = array( 'mizzey_term_content_nonce' => 'wrong', 'mizzey_banner' => '' );
		TermContent::save( (int) $cat['term_id'] );
		$_POST = array();
		wp_set_current_user( 0 );
		$content = TermContent::content( get_term( (int) $cat['term_id'], 'product_cat' ) );
		$ccoll   = TermContent::content( get_term( (int) $coll['term_id'], Collections::TAXONOMY ) );
		$check( is_array( $content['banner'] ?? null ) && "{$tag} Summer sale" === $content['banner']['alt'] && home_url( '/offers/' ) === $content['banner']['link'] && "{$tag} All for the kitchen." === $content['description'] && '' !== ( $ccoll['image'] ?? '' ) && null === ( $ccoll['banner'] ?? 'x' ), 'AC-3 SSC-04, SSC-06 a category\'s banner (image, alternative text, link) and description, and a collection\'s image, set on their screens; a request without the nonce changes nothing' );
		// The Arabic category shows the banner too, unless it sets its own.
		$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( (int) $cat['term_id'], 'product_cat' )->term_taxonomy_id, 'tax_product_cat' );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_cat = wp_insert_term( "{$tag} المطبخ", 'product_cat', array( 'description' => "{$tag} كل ما يلزم المطبخ." ) );
		do_action( 'wpml_switch_language', 'en' );
		$terms[] = array( (int) $ar_cat['term_id'], 'product_cat' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( (int) $ar_cat['term_id'], 'product_cat' )->term_taxonomy_id, 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$api = sign_in_json( 'GET', home_url( '/ar/wp-json/mizzey/v1/term-content' ) . '?taxonomy=product_cat&id=' . (int) $cat['term_id'], null );
		$check( "{$tag} المطبخ" === ( $api['data']['name'] ?? '' ) && "{$tag} كل ما يلزم المطبخ." === ( $api['data']['description'] ?? '' ) && "{$tag} Summer sale" === ( $api['data']['banner']['alt'] ?? '' ), 'AC-3 SSC-12 the Arabic category answers with its own name and description and the shared banner (' . wp_json_encode( $api['data'] ?? null, JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-4: a hidden block --------------------------------------------------------------------------------------
		$hidden = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Hidden", 'post_content' => '<!-- wp:paragraph {"metadata":{"blockVisibility":false}} --><p>' . $tag . ' hidden section</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>' . $tag . ' shown section</p><!-- /wp:paragraph -->' ) );
		$posts[] = $hidden;
		$hb = $fetch( $hidden );
		$check( storefront_shows( $hb, "{$tag} shown section" ) && ! storefront_shows( $hb, "{$tag} hidden section" ) && str_contains( (string) get_post_field( 'post_content', $hidden ), "{$tag} hidden section" ), 'AC-4 SSC-03 a section hidden in the editor is not shown, and its content is kept' );

		return $ok;
	}
);

<?php
/**
 * t111, spec 066 slice 6: the designed category listing (AC-7; PLP-06 to PLP-10, PLP-12 to PLP-22, SSC-04).
 *
 * Over a fixture named "s6-" and removed when the scenario ends: a category with a description and a banner image, two
 * child categories, two brands, a store attribute ("Form") with two values, fourteen products across them (two out of
 * stock, each with its own price, sales and date), each with its Arabic translation and the Arabic terms, and an empty
 * category.
 *
 * On the server, through the requests a browser makes:
 *
 * - the category page is the designed listing (fails on WooCommerce's native template), and keeps the breadcrumb and
 *   the result count t97 reads;
 * - the count a filters drawer asks for is the server's answer for every chosen filter together;
 * - a filters form sent without JavaScript (a value list per group) is answered with the clean address, whose filters
 *   then apply.
 *
 * In Chromium (mizzey-site/tests/browser/category-listing.cjs), at 390 and 1440 wide, in English and Arabic: each filter
 * (category, brand, the price by dragging and by typing, the attribute, availability), each sort, removing one active
 * filter, paging, back from a product keeps the state, the drawer's pinned button and its single count with the i-dot
 * loader while it waits, the loading state, the empty states, the page without JavaScript, and the board's numbers.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/** Run the browser script on a plan. @return array<string,mixed> */
function t111_browser( array $plan ): array {
	$file = wp_tempnam( 't111-plan' );
	file_put_contents( $file, wp_json_encode( $plan, JSON_UNESCAPED_UNICODE ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/category-listing.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/** An image drawn here, so nothing is downloaded. */
function t111_image( Scenario $s, string $name ): int {
	$image = imagecreatetruecolor( 400, 400 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 255, 255, 255 ) );
	imagefilledellipse( $image, 200, 200, 220, 260, imagecolorallocate( $image, 255, 106, 74 ) );
	ob_start();
	imagejpeg( $image, null, 80 );
	$file = wp_upload_bits( $name . '.jpg', null, (string) ob_get_clean() );
	if ( ! empty( $file['error'] ) ) {
		throw new \RuntimeException( 'The fixture image could not be written: ' . $file['error'] );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = (int) wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $file['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $name );
	$s->on_finish( static fn () => wp_delete_attachment( $id, true ) );
	return $id;
}

/** GET a storefront address as a visitor; the status, the body and the Location header. @return array{0:int,1:string,2:string} */
function t111_get( string $url ): array {
	$r    = wp_remote_get( $url, array( 'timeout' => 90, 'redirection' => 0 ) );
	$code = (int) wp_remote_retrieve_response_code( $r );
	$body = (string) wp_remote_retrieve_body( $r );
	if ( 200 === $code ) {
		storefront_refuse_shell( $url, $code, $body );
	}
	return array( $code, $body, (string) wp_remote_retrieve_header( $r, 'location' ) );
}

run(
	new Scenario( 't111-category-listing', 'AC-7 (slice 6): the designed category listing, its filters, sorts, paging, drawer and empty states, in both languages' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		// Every record this run makes is named s6-..., beside other builders on the same database.
		$tag   = 's6-' . strtolower( wp_generate_password( 5, false, false ) );
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		do_action( 'wpml_switch_language', 'en' );

		// ---- Terms, each with its Arabic translation ------------------------------------------------------------------
		$terms = array();
		$s->on_finish( static function () use ( &$terms ): void { foreach ( array_reverse( $terms ) as $t ) { wp_delete_term( $t[0], $t[1] ); } } );
		$make_term = static function ( string $taxonomy, string $en, string $ar, int $parent_en = 0, int $parent_ar = 0, string $desc_en = '', string $desc_ar = '' ) use ( &$terms ): array {
			do_action( 'wpml_switch_language', 'en' );
			$m    = wp_insert_term( $en, $taxonomy, array( 'slug' => sanitize_title( $en ), 'parent' => $parent_en, 'description' => $desc_en ) );
			if ( is_wp_error( $m ) ) {
				throw new \RuntimeException( "The term {$en} could not be made: " . $m->get_error_message() );
			}
			$id   = (int) $m['term_id'];
			$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( $id, $taxonomy )->term_taxonomy_id, 'tax_' . $taxonomy );
			do_action( 'wpml_switch_language', 'ar' );
			$a = wp_insert_term( $ar, $taxonomy, array( 'slug' => sanitize_title( $en ) . '-ar', 'parent' => $parent_ar, 'description' => $desc_ar ) );
			if ( is_wp_error( $a ) ) {
				throw new \RuntimeException( "The term {$ar} could not be made: " . $a->get_error_message() );
			}
			do_action( 'wpml_switch_language', 'en' );
			do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( (int) $a['term_id'], $taxonomy )->term_taxonomy_id, 'element_type' => 'tax_' . $taxonomy, 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = array( $id, $taxonomy );
			$terms[] = array( (int) $a['term_id'], $taxonomy );
			return array( 'en' => $id, 'ar' => (int) $a['term_id'], 'slug' => array( 'en' => sanitize_title( $en ), 'ar' => sanitize_title( $en ) . '-ar' ), 'name' => array( 'en' => $en, 'ar' => $ar ) );
		};
		$cat     = $make_term( 'product_cat', "{$tag} Vitamins", "{$tag} فيتامينات", 0, 0, "{$tag} Daily essentials from brands you know.", "{$tag} أساسيات يومية من علامات تعرفها." );
		$minerals = $make_term( 'product_cat', "{$tag} Minerals", "{$tag} معادن", $cat['en'], $cat['ar'] );
		$omega    = $make_term( 'product_cat', "{$tag} Omega", "{$tag} أوميغا", $cat['en'], $cat['ar'] );
		$empty    = $make_term( 'product_cat', "{$tag} Sun care", "{$tag} العناية من الشمس", 0, 0, "{$tag} Nothing here yet.", "{$tag} لا شيء هنا بعد." );
		$brands   = taxonomy_exists( 'product_brand' );
		$lumen    = $brands ? $make_term( 'product_brand', "{$tag} Lumen", "{$tag} لومن" ) : null;
		$nova     = $brands ? $make_term( 'product_brand', "{$tag} Nova", "{$tag} نوفا" ) : null;
		$banner   = t111_image( $s, "{$tag}-banner" );
		update_term_meta( $cat['en'], \MizzeySite\Storefront\TermContent::BANNER, $banner );

		// ---- The store attribute "Form", translatable (specs/053) -------------------------------------------------------
		$attr_slug = 's6form' . substr( $tag, 3 );
		$attr_id   = wc_create_attribute( array( 'name' => "{$tag} Form", 'slug' => $attr_slug, 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ) );
		if ( is_wp_error( $attr_id ) ) {
			throw new \RuntimeException( 'The attribute could not be made: ' . $attr_id->get_error_message() );
		}
		$s->on_finish( static fn () => wc_delete_attribute( (int) $attr_id ) );
		$taxonomy = wc_attribute_taxonomy_name( $attr_slug );
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
		// Translatable, as every store attribute is (specs/053); in this process's memory only, nothing is saved.
		$sitepress->set_setting( 'taxonomies_sync_option', array_merge( (array) $sitepress->get_setting( 'taxonomies_sync_option', array() ), array( $taxonomy => 1 ) ), false );
		$capsules = $make_term( $taxonomy, "{$tag} Capsules", "{$tag} كبسولات" );
		$powder   = $make_term( $taxonomy, "{$tag} Powder", "{$tag} مسحوق" );

		// ---- Fourteen products --------------------------------------------------------------------------------------
		$rows = array(
			array( 'Alpha', 100, $minerals, $lumen, $capsules, 'instock', 5 ),
			array( 'Bravo', 220, $minerals, $nova, $powder, 'outofstock', 50 ),
			array( 'Charlie', 340, $minerals, $lumen, $capsules, 'instock', 1 ),
			array( 'Delta', 460, $minerals, $nova, $capsules, 'instock', 30 ),
			array( 'Echo', 580, $minerals, $lumen, $powder, 'instock', 2 ),
			array( 'Foxtrot', 700, $omega, $nova, $capsules, 'instock', 12 ),
			array( 'Golf', 820, $omega, $lumen, $powder, 'instock', 8 ),
			array( 'Hotel', 940, $omega, $nova, $capsules, 'outofstock', 3 ),
			array( 'India', 1060, $omega, $lumen, $capsules, 'instock', 40 ),
			array( 'Juliet', 1180, $cat, $lumen, $powder, 'instock', 6 ),
			array( 'Kilo', 1300, $cat, $nova, $capsules, 'instock', 9 ),
			array( 'Lima', 1420, $cat, null, $capsules, 'instock', 4 ),
			array( 'Mike', 1540, $cat, null, $powder, 'instock', 7 ),
			array( 'November', 1660, $cat, $lumen, null, 'instock', 11 ),
		);
		$products = array();
		$attribute = static function ( ?array $term, string $lang ) use ( $taxonomy, $attr_id ): array {
			if ( null === $term ) {
				return array();
			}
			$a = new \WC_Product_Attribute();
			$a->set_id( (int) $attr_id );
			$a->set_name( $taxonomy );
			$a->set_options( array( (int) $term[ $lang ] ) );
			$a->set_visible( true );
			$a->set_variation( false );
			return array( $a );
		};
		foreach ( $rows as $i => list( $word, $price, $category, $brand, $form, $stock, $sales ) ) {
			do_action( 'wpml_switch_language', 'en' );
			$p = $s->simple_product( "{$tag} {$word}", number_format( $price, 2, '.', '' ), null );
			$p->set_category_ids( array( $category['en'] ) );
			$p->set_stock_status( $stock );
			$p->set_total_sales( $sales );
			$p->set_date_created( gmdate( 'Y-m-d H:i:s', strtotime( '2026-01-01 10:00:00' ) + $i * DAY_IN_SECONDS ) );
			$p->set_attributes( $attribute( $form, 'en' ) );
			if ( 'Kilo' === $word ) {
				$p->set_regular_price( '1350.00' );
				$p->set_sale_price( number_format( $price, 2, '.', '' ) );
			}
			$p->save();
			if ( $brand ) {
				wp_set_object_terms( $p->get_id(), array( $brand['en'] ), 'product_brand' );
			}
			if ( $form ) {
				wp_set_object_terms( $p->get_id(), array( (int) $form['en'] ), $taxonomy );
			}
			$sitepress->make_duplicate( $p->get_id(), 'ar' );
			$ar_id = (int) apply_filters( 'wpml_object_id', $p->get_id(), 'product', false, 'ar' );
			$s->track_post( $ar_id );
			do_action( 'wpml_switch_language', 'ar' );
			$q = wc_get_product( $ar_id );
			if ( $q ) {
				$q->set_category_ids( array( $category['ar'] ) );
				$q->set_stock_status( $stock );
				$q->set_total_sales( $sales );
				$q->set_date_created( $p->get_date_created() );
				$q->set_attributes( $attribute( $form, 'ar' ) );
				if ( 'Kilo' === $word ) {
					$q->set_regular_price( '1350.00' );
					$q->set_sale_price( number_format( $price, 2, '.', '' ) );
				}
				$q->save();
				wp_set_object_terms( $ar_id, $brand ? array( $brand['ar'] ) : array(), 'product_brand' );
				wp_set_object_terms( $ar_id, $form ? array( (int) $form['ar'] ) : array(), $taxonomy );
			}
			do_action( 'wpml_switch_language', 'en' );
			$products[] = array(
				'name'     => "{$tag} {$word}",
				'price'    => $price,
				'child'    => $category === $cat ? '' : $category['slug']['en'],
				'brand'    => $brand ? $brand['slug']['en'] : '',
				'form'     => $form ? $form['slug']['en'] : '',
				'instock'  => 'instock' === $stock,
				'sales'    => $sales,
				'sale'     => 'Kilo' === $word,
				'order'    => $i,
				'url'      => (string) get_permalink( $p->get_id() ),
			);
		}
		// The lookup tables and the term counts the listing reads.
		wc_delete_product_transients();
		foreach ( array( 'product_cat', 'product_brand', $taxonomy ) as $tx ) {
			if ( taxonomy_exists( $tx ) ) {
				wc_change_term_counts( get_terms( array( 'taxonomy' => $tx, 'hide_empty' => false, 'fields' => 'ids' ) ), $tx );
			}
		}
		_wc_term_recount( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ), get_taxonomy( 'product_cat' ), true, false );
		wp_cache_flush();

		// Each address in its own language: WPML gives a term's link in the current language.
		$link = static function ( int $term, string $lang ): string {
			do_action( 'wpml_switch_language', $lang );
			$out = (string) get_term_link( $term, 'product_cat' );
			do_action( 'wpml_switch_language', 'en' );
			return $out;
		};
		$url = array(
			'en'    => $link( $cat['en'], 'en' ),
			'ar'    => $link( $cat['ar'], 'ar' ),
			'empty' => array( 'en' => $link( $empty['en'], 'en' ), 'ar' => $link( $empty['ar'], 'ar' ) ),
		);
		$s->note( '      ' . wp_json_encode( $url, JSON_UNESCAPED_UNICODE ) );

		// ---- On the server --------------------------------------------------------------------------------------------
		list( $code, $body ) = t111_get( $url['en'] );
		$check( 200 === $code && str_contains( $body, 'mizzey-listing' ) && storefront_shows( $body, "{$tag} Daily essentials from brands you know." ), 'PLP-20 the category page is the designed listing with its description (status ' . $code . ')' );
		$check( 1 === preg_match( '/woocommerce-breadcrumb/', $body ) && 1 === preg_match( '/woocommerce-result-count/', $body ), 'PLP-21, PLP-17 the designed page keeps the breadcrumb and the result count t97 reads' );
		$lumen_slug = $lumen ? $lumen['slug']['en'] : '';
		list( $code, $json ) = t111_get( add_query_arg( array( 'brands' => $lumen_slug, 'categories' => $minerals['slug']['en'], 'filter_stock_status' => 'instock', 'mizzey_count' => '1' ), $url['en'] ) );
		$count = json_decode( $json, true );
		$check( 200 === $code && 3 === (int) ( $count['count'] ?? -1 ), 'PLP-22 the drawer\'s count is the server\'s answer for every chosen filter together: Lumen, Minerals, in stock = 3 (' . substr( $json, 0, 200 ) . ')' );
		$nojs = $url['en'] . '?' . 'brands%5B%5D=' . rawurlencode( $lumen_slug ) . '&brands%5B%5D=' . rawurlencode( $nova ? $nova['slug']['en'] : '' ) . '&min_price=100&max_price=1700&orderby=price&mizzey_filters=1';
		list( $code, , $location ) = t111_get( $nojs );
		$check( in_array( $code, array( 301, 302, 303 ), true ) && str_contains( $location, 'brands=' . rawurlencode( $lumen_slug . ',' . ( $nova ? $nova['slug']['en'] : '' ) ) ) && ! str_contains( $location, 'mizzey_filters' ) && ! str_contains( $location, '%5B' ), 'PLP-18 a filters form sent without JavaScript is answered with the clean address (' . $code . ' ' . $location . ')' );
		// The form, as a browser without JavaScript sends it: two values of the attribute, and the untouched price
		// slider at the bounds the form was drawn with.
		$form = $url['en'] . '?' . 'filter_' . $attr_slug . '%5B%5D=' . rawurlencode( $capsules['slug']['en'] ) . '&filter_' . $attr_slug . '%5B%5D=' . rawurlencode( $powder['slug']['en'] ) . '&min_price=100&max_price=1700&' . \MizzeySite\Storefront\Listing::LOW . '=100&' . \MizzeySite\Storefront\Listing::HIGH . '=1700&mizzey_filters=1';
		list( $code2, , $location2 ) = t111_get( $form );
		$check( in_array( $code2, array( 301, 302, 303 ), true ) && str_contains( $location2, 'query_type_' . $attr_slug . '=or' ), 'PLP-09 two values of one attribute sent without JavaScript read as either, not both (' . $location2 . ')' );
		$check( '' !== $location2 && ! str_contains( $location2, 'min_price' ) && ! str_contains( $location2, 'max_price' ) && ! str_contains( $location2, \MizzeySite\Storefront\Listing::LOW ), 'PLP-08 an untouched price slider sent without JavaScript applies no price (' . $location2 . ')' );
		$narrow = $url['en'] . '?min_price=300&max_price=1700&' . \MizzeySite\Storefront\Listing::LOW . '=100&' . \MizzeySite\Storefront\Listing::HIGH . '=1700&mizzey_filters=1';
		list( , , $location3 ) = t111_get( $narrow );
		$check( str_contains( $location3, 'min_price=300' ) && ! str_contains( $location3, 'max_price' ), 'PLP-08 a moved handle is kept, the untouched one dropped (' . $location3 . ')' );
		if ( '' !== $location ) {
			list( $code, $body ) = t111_get( $location );
			$check( 200 === $code && storefront_shows( $body, "{$tag} Alpha" ) && ! storefront_shows( $body, "{$tag} Lima" ), 'PLP-07 that address lists the two brands\' products and not the others' );
		}

		// ---- In the browser -------------------------------------------------------------------------------------------
		$plan   = array(
			'tag'       => $tag,
			'url'       => $url,
			'products'  => $products,
			'attribute' => 'filter_' . $attr_slug,
			'slugs'     => array(
				'minerals' => $minerals['slug'],
				'omega'    => $omega['slug'],
				'lumen'    => $lumen ? $lumen['slug'] : null,
				'nova'     => $nova ? $nova['slug'] : null,
				'capsules' => $capsules['slug'],
				'powder'   => $powder['slug'],
			),
			'names'     => array( 'category' => $cat['name'], 'empty' => $empty['name'] ),
			'shots'     => getenv( 'MIZZEY_SHOTS' ) ? (string) getenv( 'MIZZEY_SHOTS' ) : '',
		);
		$result = t111_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 2000 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 120, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );

		return $ok;
	}
);

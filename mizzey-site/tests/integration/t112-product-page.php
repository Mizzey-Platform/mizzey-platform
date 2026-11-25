<?php
/**
 * t112, spec 066 slice 9: the top of the product page, the gallery and the buying area (AC-6, AC-11; PDP-01 to
 * PDP-11, PDP-14 to PDP-16, PDP-21, PDP-22), in a real browser over a fixture of four products.
 *
 * The fixture, every record named "s9-" and removed when the scenario ends:
 *
 * - a simple product with a 200 character name, a brand, a rating, a sale, three photos of far-apart shapes (2.27 wide,
 *   0.29 tall, square), key features in its short description, and no video;
 * - a variable product whose options are not size and colour: Pack (1 tub, 2 tubs) and Flavour (Vanilla, Chocolate,
 *   Unflavoured). Chocolate is sold out in every pack, 1 tub Unflavoured is on sale with 3 left, 2 tubs Unflavoured is
 *   not made, Vanilla has its own photo, 1 tub Vanilla is the default; it has a video;
 * - a simple product that is sold out;
 * - a simple product with no image.
 *
 * The simple and the variable product are translated into Arabic (WPML's duplicate, its own title). The returns,
 * authenticity and delivery settings of specs/055 are set for the run and put back.
 *
 * In Chromium (mizzey-site/tests/browser/product-page.cjs), at 1440 and 390 wide in English and Arabic, the page is
 * measured against C1-Product-Desktop, C1-Product-Phone and C1-Arabic-Stage-2, and used: options chosen, price, stock
 * and image following them without a shift, add to cart through the Store API (which asks the stock source, specs/062)
 * with the count changing only after the server answers, Buy Now, the wishlist heart, share, gallery zoom and video,
 * and the phone's buy bar in its states.
 *
 * Then, on the server: the cart holds what the browser added, and the wishlist what it saved.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Storefront\ProductPage;

require_once __DIR__ . '/_bootstrap.php';

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t112_english( int $id, string $type ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/** An image attachment of the given shape, drawn here, so no file is downloaded. */
function t112_image( Scenario $s, string $name, int $width, int $height, array $rgb ): int {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 255, 255, 255 ) );
	imagefilledrectangle( $image, (int) ( $width * 0.1 ), (int) ( $height * 0.1 ), (int) ( $width * 0.9 ), (int) ( $height * 0.9 ), imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] ) );
	// Grain, so the photo is not a flat block: the browser does not count a near-empty image as the largest paint.
	mt_srand( $width * $height );
	for ( $y = (int) ( $height * 0.1 ); $y < (int) ( $height * 0.9 ); $y += 2 ) {
		for ( $x = (int) ( $width * 0.1 ); $x < (int) ( $width * 0.9 ); $x += 2 ) {
			$shade = mt_rand( -40, 40 );
			imagesetpixel( $image, $x, $y, imagecolorallocate( $image, max( 0, min( 255, $rgb[0] + $shade ) ), max( 0, min( 255, $rgb[1] + $shade ) ), max( 0, min( 255, $rgb[2] + $shade ) ) ) );
		}
	}
	ob_start();
	imagepng( $image );
	$bits = (string) ob_get_clean();
	$file = wp_upload_bits( $name . '.png', null, $bits );
	if ( ! empty( $file['error'] ) ) {
		throw new \RuntimeException( 'The fixture image could not be written: ' . $file['error'] );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = (int) wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $file['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $name . ' photo' );
	$s->on_finish( static fn () => wp_delete_attachment( $id, true ) );
	return $id;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t112_browser( array $plan ): array {
	$file = wp_tempnam( 't112-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/product-page.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't112-product-page', 'AC-6, AC-11 (slice 9): the product page gallery and buying area, in both languages, at 390 and 1440' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 's9-' . strtolower( wp_generate_password( 5, false, false ) );
		do_action( 'wpml_switch_language', 'en' );

		// ---- The stock source: the development stand-in answers from WooCommerce's figure ---------------------------
		$fixture_was = get_option( FixtureStockSource::OPTION );
		$s->on_finish( static function () use ( $fixture_was ): void {
			false === $fixture_was ? delete_option( FixtureStockSource::OPTION ) : update_option( FixtureStockSource::OPTION, $fixture_was );
		} );
		update_option( FixtureStockSource::OPTION, 'yes' );

		// ---- The product page settings of specs/055, kept and put back ---------------------------------------------
		$options = array( ProductPage::RETURNS . 'en', ProductPage::RETURNS . 'ar', ProductPage::RETURNS_PAGE, ProductPage::AUTHENTIC . 'en', ProductPage::AUTHENTIC . 'ar', ProductPage::AUTHENTIC_PAGE, ProductPage::DAYS_MIN, ProductPage::DAYS_MAX );
		$kept    = array();
		foreach ( $options as $option ) {
			$kept[ $option ] = get_option( $option, null );
		}
		$s->on_finish( static function () use ( $kept ): void {
			foreach ( $kept as $option => $value ) {
				null === $value ? delete_option( $option ) : update_option( $option, $value );
			}
		} );
		$page = static function ( string $title, string $ar_title ) use ( $s, $sitepress ): array {
			do_action( 'wpml_switch_language', 'en' );
			$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title ) );
			$sitepress->make_duplicate( $id, 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $id, 'page', false, 'ar' );
			wp_update_post( array( 'ID' => $ar, 'post_title' => $ar_title ) );
			delete_post_meta( $ar, '_icl_lang_duplicate_of' );
			$s->on_finish( static function () use ( $id, $ar ): void { wp_delete_post( $ar, true ); wp_delete_post( $id, true ); } );
			return array( $id, $ar );
		};
		list( $returns ) = $page( "{$tag} Returns", "{$tag} الاسترجاع" );
		list( $genuine ) = $page( "{$tag} Authenticity", "{$tag} الأصالة" );
		update_option( ProductPage::RETURNS . 'en', 'Easy returns within 14 days.' );
		update_option( ProductPage::RETURNS . 'ar', 'استرجاع سهل خلال 14 يوما.' );
		update_option( ProductPage::RETURNS_PAGE, $returns );
		update_option( ProductPage::AUTHENTIC . 'en', 'Authentic, guaranteed' );
		update_option( ProductPage::AUTHENTIC . 'ar', 'أصلي، بضمان ميزي' );
		update_option( ProductPage::AUTHENTIC_PAGE, $genuine );
		update_option( ProductPage::DAYS_MIN, 2 );
		update_option( ProductPage::DAYS_MAX, 5 );

		// ---- The brand ------------------------------------------------------------------------------------------
		$brand = taxonomy_exists( 'product_brand' ) ? wp_insert_term( "{$tag} Vital", 'product_brand' ) : null;
		$brand = is_array( $brand ) ? (int) $brand['term_id'] : 0;
		if ( $brand > 0 ) {
			$s->on_finish( static fn () => wp_delete_term( $brand, 'product_brand' ) );
		}

		// ---- The photos: far-apart shapes, and the Vanilla photo -----------------------------------------------------
		$wide    = t112_image( $s, $tag . '-wide', 908, 400, array( 38, 59, 115 ) );
		$tall    = t112_image( $s, $tag . '-tall', 290, 1000, array( 255, 106, 74 ) );
		$square  = t112_image( $s, $tag . '-square', 800, 800, array( 36, 123, 71 ) );
		$vanilla = t112_image( $s, $tag . '-vanilla', 800, 800, array( 243, 241, 233 ) );

		$features = "<ul>\n<li>Collagen peptides, single ingredient</li>\n<li>Pasture raised and grass fed</li>\n<li>Paleo friendly and gluten free</li>\n</ul>";

		// ---- The simple product: a 200 character name, a sale, three photos, no video --------------------------------
		$name   = mb_substr( $tag . ' Aquaphor Healing Balm Stick, Skin Protectant with Avocado Oil and Shea Butter, a long name that keeps going to test how the product page holds two hundred characters of name without', 0, 200 );
		$simple = $s->simple_product( $name, '1875', null );
		$simple->set_sale_price( '1500' );
		$simple->set_image_id( $wide );
		$simple->set_gallery_image_ids( array( $tall, $square ) );
		$simple->set_short_description( $features );
		$simple->set_manage_stock( true );
		$simple->set_stock_quantity( 20 );
		$simple->save();
		update_post_meta( $simple->get_id(), '_wc_average_rating', '4.8' );
		update_post_meta( $simple->get_id(), '_wc_review_count', 96 );
		update_post_meta( $simple->get_id(), '_wcml_average_rating', '4.8' );
		update_post_meta( $simple->get_id(), '_wcml_review_count', 96 );
		if ( $brand > 0 ) {
			wp_set_object_terms( $simple->get_id(), array( $brand ), 'product_brand' );
		}
		t112_english( $simple->get_id(), 'product' );

		// ---- The variable product: Pack and Flavour ---------------------------------------------------------------
		$attributes = array();
		foreach ( array( 'Pack' => array( '1 tub', '2 tubs' ), 'Flavour' => array( 'Vanilla', 'Chocolate', 'Unflavoured' ) ) as $attr_name => $values ) {
			$a = new \WC_Product_Attribute();
			$a->set_name( $attr_name );
			$a->set_options( $values );
			$a->set_visible( true );
			$a->set_variation( true );
			$attributes[] = $a;
		}
		$var = new \WC_Product_Variable();
		$var->set_name( "{$tag} Collagen Peptides" );
		$var->set_status( 'publish' );
		$var->set_attributes( $attributes );
		$var->set_image_id( $square );
		$var->set_gallery_image_ids( array( $wide ) );
		$var->set_short_description( $features );
		$var->set_default_attributes( array( 'pack' => '1 tub', 'flavour' => 'Vanilla' ) );
		$var->update_meta_data( ProductFields::VIDEO, home_url( '/wp-content/uploads/' . $tag . '-sample.mp4' ) );
		$var->save();
		if ( $brand > 0 ) {
			wp_set_object_terms( $var->get_id(), array( $brand ), 'product_brand' );
		}
		t112_english( $var->get_id(), 'product' );
		$s->track_post( $var->get_id() );
		$variations = array();
		// Pack, Flavour, regular, sale, stock, photo. 2 tubs Unflavoured is not made.
		$made = array(
			array( '1 tub', 'Vanilla', '2600', '', 5, $vanilla ),
			array( '1 tub', 'Chocolate', '2600', '', 0, 0 ),
			array( '1 tub', 'Unflavoured', '2450', '2200', 3, 0 ),
			array( '2 tubs', 'Vanilla', '4800', '', 5, $vanilla ),
			// Its photo is already in the product's gallery (the wide one): the set lists it once.
			array( '2 tubs', 'Chocolate', '4800', '', 0, $wide ),
		);
		foreach ( $made as list( $pack, $flavour, $regular, $sale, $qty, $photo ) ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var->get_id() );
			$v->set_attributes( array( 'pack' => $pack, 'flavour' => $flavour ) );
			$v->set_regular_price( $regular );
			if ( '' !== $sale ) {
				$v->set_sale_price( $sale );
			}
			$v->set_manage_stock( true );
			$v->set_stock_quantity( $qty );
			$v->set_low_stock_amount( 4 );
			if ( $photo ) {
				$v->set_image_id( $photo );
			}
			$v->save();
			t112_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
			$variations[ $pack . '/' . $flavour ] = $v->get_id();
		}
		\WC_Product_Variable::sync( $var->get_id() );

		// ---- The sold-out product and the product with no image --------------------------------------------------
		$sold = $s->simple_product( "{$tag} Sold out balm", '900', null );
		$sold->set_manage_stock( true );
		$sold->set_stock_quantity( 0 );
		$sold->set_image_id( $square );
		$sold->set_short_description( $features );
		$sold->save();
		t112_english( $sold->get_id(), 'product' );
		$bare = $s->simple_product( "{$tag} No photo yet", '450', null );
		t112_english( $bare->get_id(), 'product' );

		// ---- A variable product sold out in every variation, with no default chosen ------------------------------
		$a = new \WC_Product_Attribute();
		$a->set_name( 'Scent' );
		$a->set_options( array( 'Cedar', 'Rose' ) );
		$a->set_visible( true );
		$a->set_variation( true );
		$allout = new \WC_Product_Variable();
		$allout->set_name( "{$tag} Sold out in every scent" );
		$allout->set_status( 'publish' );
		$allout->set_attributes( array( $a ) );
		$allout->set_image_id( $square );
		$allout->save();
		t112_english( $allout->get_id(), 'product' );
		$s->track_post( $allout->get_id() );
		foreach ( array( 'Cedar', 'Rose' ) as $scent ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $allout->get_id() );
			$v->set_attributes( array( 'scent' => $scent ) );
			$v->set_regular_price( '700' );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( 0 );
			$v->save();
			t112_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
		}
		\WC_Product_Variable::sync( $allout->get_id() );

		// The server's first answer, before any script: the stock line's state is the chosen variant's, and empty when
		// nothing is chosen (the parent's would say otherwise).
		$first = static function ( string $url ): string {
			$html = (string) wp_remote_retrieve_body( wp_remote_get( $url, array( 'timeout' => 60 ) ) ); // storefront-guard: exempt, the raw markup is what is read.
			return preg_match( '/class="mizzey-buy__stock" data-state="([^"]*)"/', $html, $m ) ? $m[1] : 'missing';
		};
		$check( '' === $first( get_permalink( $allout->get_id() ) ), 'PDP-06 the stock line of a variable product with nothing chosen has no state of its own, not the parent ones ("' . $first( get_permalink( $allout->get_id() ) ) . '")' );
		$check( 'in' === $first( get_permalink( $var->get_id() ) ), 'PDP-06 the stock line of the default variant, 1 tub Vanilla, carries its state ("' . $first( get_permalink( $var->get_id() ) ) . '")' );

		// ---- Swatches: the colour staff give an attribute's value on its edit screen --------------------------------
		$swatch_attr = wc_create_attribute( array( 'name' => "{$tag} Colour", 'slug' => substr( str_replace( '-', '', $tag ), 0, 12 ) . 'col', 'type' => 'select' ) );
		$check( ! is_wp_error( $swatch_attr ), 'setup: a global colour attribute' );
		if ( ! is_wp_error( $swatch_attr ) ) {
			$s->on_finish( static fn () => wc_delete_attribute( $swatch_attr ) );
			$taxonomy = wc_attribute_taxonomy_name_by_id( (int) $swatch_attr );
			register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false ) );
			// What admin_init does for the field (the whole admin_init is not run from the command line).
			\MizzeySite\Catalogue\AttributeSwatch::hook();
			$black = wp_insert_term( 'Black', $taxonomy );
			$blue  = wp_insert_term( 'Blue', $taxonomy );
			$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
			wp_set_current_user( $admin );
			ob_start();
			do_action( "{$taxonomy}_edit_form_fields", get_term( $black['term_id'], $taxonomy ), $taxonomy );
			$field = (string) ob_get_clean();
			$check( str_contains( $field, 'name="mizzey_swatch"' ) && str_contains( $field, 'type="color"' ), 'PDP-05 the edit screen of a value has a colour field' );
			$save = static function ( int $term_id, string $value ) use ( $taxonomy ): void {
				$_POST['mizzey_swatch']       = $value;
				$_POST['mizzey_swatch_nonce'] = wp_create_nonce( 'mizzey_swatch' );
				do_action( "edited_{$taxonomy}", $term_id, 0, array() );
				unset( $_POST['mizzey_swatch'], $_POST['mizzey_swatch_nonce'] );
			};
			$save( (int) $black['term_id'], '#1e1e1e' );
			$save( (int) $blue['term_id'], '#2f5fb3' );
			$check( '#1e1e1e' === get_term_meta( $black['term_id'], 'mizzey_swatch', true ), 'PDP-05 the colour is saved as a hex code' );
			$save( (int) $blue['term_id'], 'blue; background:url(x)' );
			$check( '#2f5fb3' === get_term_meta( $blue['term_id'], 'mizzey_swatch', true ), 'PDP-05 a value that is not a hex colour is refused and the saved one kept' );
			wp_set_current_user( 0 );
			$pa = new \WC_Product_Attribute();
			$pa->set_id( (int) $swatch_attr );
			$pa->set_name( $taxonomy );
			$pa->set_options( array( (int) $black['term_id'], (int) $blue['term_id'] ) );
			$pa->set_visible( true );
			$pa->set_variation( true );
			$sw = new \WC_Product_Variable();
			$sw->set_name( "{$tag} Knee strap" );
			$sw->set_status( 'publish' );
			$sw->set_attributes( array( $pa ) );
			$sw->save();
			$s->track_post( $sw->get_id() );
			foreach ( array( 'black', 'blue' ) as $slug ) {
				$v = new \WC_Product_Variation();
				$v->set_parent_id( $sw->get_id() );
				$v->set_attributes( array( $taxonomy => $slug ) );
				$v->set_regular_price( '2300' );
				$v->save();
				$s->track_post( $v->get_id() );
			}
			\WC_Product_Variable::sync( $sw->get_id() );
			$html = \MizzeySite\Storefront\ProductBuy::render( wc_get_product( $sw->get_id() ) );
			$check( 2 === substr_count( $html, 'mizzey-buy__option--swatch' ) && str_contains( $html, '--mizzey-swatch:#1e1e1e' ) && str_contains( $html, '--mizzey-swatch:#2f5fb3' ), 'PDP-05 the values with a colour show as swatches, as C1-Product-Phone frame 7 draws' );
		}

		// ---- Arabic: the simple and the variable product, with their own titles ----------------------------------
		$arabic = array();
		foreach ( array( 'simple' => array( $simple->get_id(), "{$tag} بلسم أكوافور الشافي، واقي للبشرة بزيت الأفوكادو وزبدة الشيا" ), 'var' => array( $var->get_id(), "{$tag} ببتيدات الكولاجين" ) ) as $key => list( $id, $title ) ) {
			$sitepress->make_duplicate( $id, 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ar' );
			if ( $ar > 0 ) {
				wp_update_post( array( 'ID' => $ar, 'post_title' => $title ) );
				delete_post_meta( $ar, '_icl_lang_duplicate_of' );
				$s->track_post( $ar );
				$product = wc_get_product( $ar );
				if ( $product instanceof \WC_Product_Variable ) {
					foreach ( $product->get_children() as $child ) {
						$s->track_post( (int) $child );
					}
				}
			}
			$arabic[ $key ] = $ar;
		}
		$check( $arabic['simple'] > 0 && $arabic['var'] > 0, 'setup: the simple and the variable product have their Arabic records' );

		$ar_url = static fn ( int $id ): string => (string) apply_filters( 'wpml_permalink', get_permalink( $id ), 'ar' );
		$plan   = array(
			'tag'        => $tag,
			'store'      => rest_url( 'wc/store/v1/' ),
			'urls'       => array(
				'simple'    => get_permalink( $simple->get_id() ),
				'var'       => get_permalink( $var->get_id() ),
				'sold'      => get_permalink( $sold->get_id() ),
				'bare'      => get_permalink( $bare->get_id() ),
				'allout'    => get_permalink( $allout->get_id() ),
				'simple_ar' => $arabic['simple'] > 0 ? $ar_url( $arabic['simple'] ) : '',
				'var_ar'    => $arabic['var'] > 0 ? $ar_url( $arabic['var'] ) : '',
			),
			'ids'        => array(
				'simple' => $simple->get_id(),
				'var'    => $var->get_id(),
				'sold'   => $sold->get_id(),
				'bare'   => $bare->get_id(),
			),
			'variations' => $variations,
			'name'       => $name,
			'returns'    => get_permalink( $returns ),
			'checkout'   => wc_get_checkout_url(),
			'video'      => home_url( '/wp-content/uploads/' . $tag . '-sample.mp4' ),
			'shots'      => get_temp_dir(),
		);
		$result = t112_browser( $plan );
		wp_cache_delete( 'alloptions', 'options' );
		$check( 'yes' === get_option( FixtureStockSource::OPTION ), 'setup: the stock source stand-in stayed on through the browser run' );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 80, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $result['shots'] ?? array() ) as $shot ) {
			$s->note( '      screenshot: ' . $shot );
		}

		// ---- What the server holds -------------------------------------------------------------------------------
		$lines = array();
		foreach ( (array) ( $result['cart']['items'] ?? array() ) as $item ) {
			$lines[ (int) $item['id'] ] = (int) $item['quantity'];
		}
		$s->note( '      the browser\'s cart, as the Store API answered: ' . wp_json_encode( $lines ) );
		$check( 1 === ( $lines[ $simple->get_id() ] ?? 0 ), 'PDP-08 Add to Cart put one of the simple product in the cart' );
		$check( 2 === ( $lines[ $variations['1 tub/Unflavoured'] ] ?? 0 ), 'PDP-05, PDP-07, PDP-08 the chosen option, 1 tub Unflavoured, went in with its quantity, 2' );
		$check( ! isset( $lines[ $variations['1 tub/Chocolate'] ] ) && ! isset( $lines[ $sold->get_id() ] ), 'PDP-22 nothing sold out reached the cart' );
		$check( in_array( $simple->get_id(), (array) ( $result['wishlist'] ?? array() ), true ), 'PDP-10 the wishlist holds the product its heart saved (' . wp_json_encode( $result['wishlist'] ?? null ) . ')' );

		return $ok;
	}
);

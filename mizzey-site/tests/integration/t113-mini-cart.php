<?php
/**
 * t113, spec 066 slice 12: the mini cart in a real browser (AC-13, AC-14; CART-12, NFR-11; its free-delivery line
 * reads CART-07's data of specs/018).
 *
 * The runtime is prepared here and the browser part is mizzey-site/tests/browser/mini-cart.cjs:
 *
 * - a simple product with a photo and a variable product with none, whose options are flavours, each with its Arabic
 *   translation (an Arabic name and, for the variable product, Arabic option values);
 * - a shipping zone for the store's country whose only method is free shipping from EGP 1,000, so the free-delivery
 *   line has a rule to read (specs/018 measures it as WooCommerce does);
 * - the development stock source answering for the run, as the cart asks it (specs/062).
 *
 * The browser checks, in English and in Arabic, at 1440 and at 390: the drawer and the sheet out of the header's cart
 * control, the empty and loading states, each line with its option and price, a quantity change and a removal that
 * show the server's answer only, focus kept inside and returned, and the subtotal against the cart page's.
 * Everything made here is prefixed "s12-" and removed; the options it sets are restored.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Stock\FixtureStockSource;

require_once __DIR__ . '/_bootstrap.php';

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t113_english( int $id, string $type ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/** Run something in a language and come back. */
function t113_in( string $lang, callable $fn ) {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$out = $fn();
	do_action( 'wpml_switch_language', $current );
	return $out;
}

/** A square product photo drawn here, so no file is downloaded. */
function t113_image( Scenario $s, string $name ): int {
	$image = imagecreatetruecolor( 600, 600 );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 255, 255, 255 ) );
	imagefilledrectangle( $image, 200, 120, 400, 480, imagecolorallocate( $image, 38, 59, 115 ) );
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
	$s->on_finish(
		static function () use ( $id ): void {
			// WPML's media translation gives the photo an Arabic copy when the product is translated: it goes too.
			$copy = (int) apply_filters( 'wpml_object_id', $id, 'attachment', false, 'ar' );
			if ( $copy > 0 && $copy !== $id ) {
				wp_delete_attachment( $copy, true );
			}
			wp_delete_attachment( $id, true );
		}
	);
	return $id;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t113_browser( array $plan ): array {
	$file = wp_tempnam( 't113-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/mini-cart.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't113-mini-cart', 'AC-13, AC-14 (slice 12): the mini cart from the header, a drawer at 1440 and a bottom sheet at 390, in both languages, with the cart page\'s lines and subtotal' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 's12-' . strtolower( wp_generate_password( 5, false, false ) );
		do_action( 'wpml_switch_language', 'en' );

		// ---- The stock source answers for this run (the cart asks it, specs/062) ---------------------------------------
		$fixture_was = get_option( FixtureStockSource::OPTION );
		$s->on_finish(
			static function () use ( $fixture_was ): void {
				false === $fixture_was ? delete_option( FixtureStockSource::OPTION ) : update_option( FixtureStockSource::OPTION, $fixture_was );
			}
		);
		update_option( FixtureStockSource::OPTION, 'yes' );

		// ---- Free delivery from EGP 1,000 in the store's country ------------------------------------------------------
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "{$tag} Egypt" );
		$zone->set_zone_order( 0 );
		$zone->add_location( (string) WC()->countries->get_base_country(), 'country' );
		$zone->save();
		$instance = $zone->add_shipping_method( 'free_shipping' );
		$s->on_finish(
			static function () use ( $zone ): void {
				$zone->delete();
				\WC_Cache_Helper::get_transient_version( 'shipping', true );
			}
		);
		update_option( "woocommerce_free_shipping_{$instance}_settings", array( 'title' => 'Free delivery', 'requires' => 'min_amount', 'min_amount' => '1000', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		// ---- A simple product and a variable product, each in both languages ------------------------------------------
		$simple = $s->simple_product( "{$tag} Serum", '300.00', null );
		// A photo on the simple product; the variable product has none, the tile's no-photo case.
		$simple->set_image_id( t113_image( $s, "{$tag}-photo" ) );
		$simple->save();
		t113_english( $simple->get_id(), 'product' );
		$sitepress->make_duplicate( $simple->get_id(), 'ar' );
		$simple_ar = (int) apply_filters( 'wpml_object_id', $simple->get_id(), 'product', false, 'ar' );
		if ( $simple_ar > 0 ) {
			$s->track_post( $simple_ar );
			wp_update_post( array( 'ID' => $simple_ar, 'post_title' => "{$tag} سيروم" ) );
		}

		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Flavour' );
		$attr->set_options( array( 'Vanilla', 'Chocolate' ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$var = new \WC_Product_Variable();
		$var->set_name( "{$tag} Protein" );
		$var->set_status( 'publish' );
		$var->set_attributes( array( $attr ) );
		$var->save();
		t113_english( $var->get_id(), 'product' );
		$s->track_post( $var->get_id() );
		$variations = array();
		foreach ( array( 'Vanilla', 'Chocolate' ) as $flavour ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var->get_id() );
			$v->set_attributes( array( 'flavour' => $flavour ) );
			$v->set_regular_price( '400' );
			$v->save();
			t113_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
			$variations[ $flavour ] = $v->get_id();
		}
		\WC_Product_Variable::sync( $var->get_id() );

		// Its Arabic translation, made as a translator makes it: an Arabic name and Arabic option values, linked to the
		// English product and variations (a duplicate would keep the English values).
		$arabic  = array( 'Vanilla' => 'فانيليا', 'Chocolate' => 'شوكولاتة' );
		$link    = static function ( int $id, int $original, string $type ): void {
			$trid = apply_filters( 'wpml_element_trid', null, $original, 'post_' . $type );
			do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		};
		$attr_ar = new \WC_Product_Attribute();
		$attr_ar->set_name( 'Flavour' );
		$attr_ar->set_options( array_values( $arabic ) );
		$attr_ar->set_visible( true );
		$attr_ar->set_variation( true );
		$product_ar = new \WC_Product_Variable();
		$product_ar->set_name( "{$tag} بروتين" );
		$product_ar->set_status( 'publish' );
		$product_ar->set_attributes( array( $attr_ar ) );
		$product_ar->save();
		$var_ar = $product_ar->get_id();
		$s->track_post( $var_ar );
		$link( $var_ar, $var->get_id(), 'product' );
		$vanilla_ar = 0;
		foreach ( $variations as $flavour => $id ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var_ar );
			$v->set_attributes( array( 'flavour' => $arabic[ $flavour ] ) );
			$v->set_regular_price( '400' );
			$v->save();
			$s->track_post( $v->get_id() );
			$link( $v->get_id(), $id, 'product_variation' );
			if ( 'Vanilla' === $flavour ) {
				$vanilla_ar = $v->get_id();
			}
		}
		\WC_Product_Variable::sync( $var_ar );
		wc_delete_product_transients( $var->get_id() );
		wc_delete_product_transients( $var_ar );
		wp_cache_flush();

		$check( $simple_ar > 0 && $var_ar > 0 && $vanilla_ar > 0, 'the fixture: each product has its Arabic translation' );
		$vanilla_product = $vanilla_ar > 0 ? wc_get_product( $vanilla_ar ) : null;
		$check( $vanilla_product instanceof \WC_Product_Variation && 'فانيليا' === (string) $vanilla_product->get_attribute( 'flavour' ), 'the fixture: the Arabic variation carries its Arabic option (' . ( $vanilla_ar > 0 ? get_post_type( $vanilla_ar ) . ' ' . get_post_status( $vanilla_ar ) : 'none' ) . ')' );

		$language = static function ( string $lang ) use ( $simple, $simple_ar, $var, $var_ar, $variations, $vanilla_ar, $tag ): array {
			$ar = 'ar' === $lang;
			return array(
				'home'      => (string) t113_in( $lang, static fn () => apply_filters( 'wpml_home_url', home_url( '/' ) ) ),
				'cart'      => (string) t113_in( $lang, static fn () => wc_get_cart_url() ),
				'rest'      => (string) t113_in( $lang, static fn () => rest_url( 'wc/store/v1/' ) ),
				'simple'    => array( 'id' => $ar ? $simple_ar : $simple->get_id(), 'name' => $ar ? "{$tag} سيروم" : "{$tag} Serum", 'minor' => 30000 ),
				'variation' => array(
					'id'         => $ar ? $vanilla_ar : $variations['Vanilla'],
					'name'       => $ar ? "{$tag} بروتين" : "{$tag} Protein",
					'option'     => $ar ? 'فانيليا' : 'Vanilla',
					'attributes' => array( array( 'attribute' => 'Flavour', 'value' => $ar ? 'فانيليا' : 'Vanilla' ) ),
					'minor'      => 40000,
				),
			);
		};

		$plan   = array(
			'tag'       => $tag,
			'en'        => $language( 'en' ),
			'ar'        => $language( 'ar' ),
			'freeMinor' => 100000,
			'shots'     => getenv( 'MIZZEY_SHOTS' ) ? (string) getenv( 'MIZZEY_SHOTS' ) : get_temp_dir(),
		);
		$s->note( '      plan: ' . wp_json_encode( array( 'en' => $plan['en'], 'ar' => $plan['ar'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$result = t113_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 50, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		if ( ! empty( $result['measured'] ) ) {
			$s->note( '      measured: ' . wp_json_encode( $result['measured'] ) );
		}

		return $ok;
	}
);

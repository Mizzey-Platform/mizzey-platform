<?php
/**
 * t107, spec 066 slice 4: the product card and the product rail (AC-5, AC-6; PLP-01 to PLP-05, MER-01, MER-07,
 * REV-04), in a real browser over a fixture of edge products.
 *
 * The fixture, every record named "s4-" and removed when the scenario ends: a 200 character name, a product with no
 * image and no rating, a product on sale, a sold-out product that is also on sale, a product with 3 left under its
 * own low-stock amount, a product with two labels (New and Trending), a tall and a wide image, and a variable product
 * whose options are flavours (not sizes), one of them sold out. A page in English and its Arabic translation carry
 * the products as a rail and as a grid; the page is a fixture too, removed at the end.
 *
 * In Chromium (mizzey-site/tests/browser/product-card.cjs), at 390 and 1440 wide in English and 390 in Arabic, each
 * card state renders as C1-Components 06 and C1-Options (g, chosen) draw it; quick add, quick add with options and
 * the wishlist heart work against the server: the add goes through WooCommerce's Store API, which asks the stock
 * source (specs/062), and a product the stock source refuses is not added even though WooCommerce still lists 3.
 *
 * Then, on the server: the cart holds what the browser added, and the wishlist what it saved. The stock fixture
 * switch and its ledger entry are put back.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Stock\FixtureStockSource;

require_once __DIR__ . '/_bootstrap.php';

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t107_english( int $id, string $type ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/** An image attachment of the given shape, drawn here, so no file is downloaded. */
function t107_image( Scenario $s, string $name, int $width, int $height ): int {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 255, 255, 255 ) );
	imagefilledrectangle( $image, (int) ( $width * 0.1 ), (int) ( $height * 0.1 ), (int) ( $width * 0.9 ), (int) ( $height * 0.9 ), imagecolorallocate( $image, 38, 59, 115 ) );
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
	$s->on_finish( static fn () => wp_delete_attachment( $id, true ) );
	return $id;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t107_browser( array $plan, string $script = 'product-card.cjs' ): array {
	$file = wp_tempnam( 't107-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/' . $script ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't107-product-card', 'AC-5, AC-6 (slice 4): the product card in every state and the product rail, with quick add through the stock source and the wishlist heart' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 's4-' . strtolower( wp_generate_password( 5, false, false ) );
		do_action( 'wpml_switch_language', 'en' );

		// ---- The stock source: the development fixture answers from WooCommerce's figure, less its own ledger -------
		$fixture_was = get_option( FixtureStockSource::OPTION );
		$ledger_key  = $tag . '-refuse';
		$s->on_finish(
			static function () use ( $fixture_was, $ledger_key ): void {
				false === $fixture_was ? delete_option( FixtureStockSource::OPTION ) : update_option( FixtureStockSource::OPTION, $fixture_was );
				$ledger = get_option( FixtureStockSource::LEDGER, array() );
				if ( is_array( $ledger ) && isset( $ledger[ $ledger_key ] ) ) {
					unset( $ledger[ $ledger_key ] );
					update_option( FixtureStockSource::LEDGER, $ledger, false );
				}
			}
		);
		update_option( FixtureStockSource::OPTION, 'yes' );

		// ---- The fixture --------------------------------------------------------------------------------------------
		$make = function ( string $name, string $price, array $set = array() ) use ( $s ): \WC_Product_Simple {
			$p = $s->simple_product( $name, $price, null );
			foreach ( $set as $method => $value ) {
				$p->{$method}( $value );
			}
			$p->save();
			// WooCommerce keeps the average and the count in post meta, recalculated from approved reviews; the fixture
			// stands in for reviews by writing them where they are read.
			if ( isset( $set['set_average_rating'] ) ) {
				update_post_meta( $p->get_id(), '_wc_average_rating', $set['set_average_rating'] );
				update_post_meta( $p->get_id(), '_wc_review_count', $set['set_review_count'] ?? 1 );
				// WooCommerce Multilingual answers both with its own totals across the languages.
				update_post_meta( $p->get_id(), '_wcml_average_rating', $set['set_average_rating'] );
				update_post_meta( $p->get_id(), '_wcml_review_count', $set['set_review_count'] ?? 1 );
				wc_delete_product_transients( $p->get_id() );
			}
			t107_english( $p->get_id(), 'product' );
			return $p;
		};
		$long_name = $tag . ' ' . str_repeat( 'Long name to test the two line clamp with an ', 5 );
		$long_name = mb_substr( $long_name, 0, 200 );
		// The extreme shapes of the real catalogue (a lip balm 0.29, a wide set 2.27).
		$wide_img  = t107_image( $s, $tag . '-wide', 908, 400 );
		$tall_img  = t107_image( $s, $tag . '-tall', 290, 1000 );

		$long = $make( $long_name, '470', array( 'set_image_id' => $wide_img, 'set_average_rating' => '4.7', 'set_review_count' => 305 ) );
		$bare = $make( $tag . ' no image, no rating', '1180' );
		$sale = $make( $tag . ' on sale', '2750', array( 'set_sale_price' => '2350', 'set_image_id' => $tall_img, 'set_average_rating' => '4.5', 'set_review_count' => 12 ) );
		$sold = $make( $tag . ' sold out', '2900', array( 'set_sale_price' => '2500', 'set_manage_stock' => true, 'set_stock_quantity' => 0, 'set_image_id' => $tall_img ) );
		$low  = $make( $tag . ' three left', '3100', array( 'set_manage_stock' => true, 'set_stock_quantity' => 3, 'set_low_stock_amount' => 5, 'set_image_id' => $wide_img ) );
		$two  = $make( $tag . ' two labels', '890', array( 'set_image_id' => $wide_img ) );
		$two->update_meta_data( ProductFields::BADGES, array( 'trending', 'new' ) );
		$two->save();
		// A five-digit sale: the longest price the card must hold whole, on one line or stacked.
		$big = $make( $tag . ' five-digit sale', '14999', array( 'set_sale_price' => '12499', 'set_image_id' => $wide_img ) );
		// The longest price the store sells, on sale: EGP 99,999.99, struck, beside the "+" on every card width.
		$top = $make( $tag . ' top price', '99999.99', array( 'set_sale_price' => '89999.99', 'set_image_id' => $wide_img ) );
		$refused = $make( $tag . ' refused by the stock source', '650', array( 'set_manage_stock' => true, 'set_stock_quantity' => 3, 'set_low_stock_amount' => 5 ) );

		// A variable product whose options are flavours: Vanilla, Chocolate (sold out), Unflavoured.
		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Flavour' );
		$attr->set_options( array( 'Vanilla', 'Chocolate', 'Unflavoured' ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$var = new \WC_Product_Variable();
		$var->set_name( $tag . ' flavours' );
		$var->set_status( 'publish' );
		$var->set_attributes( array( $attr ) );
		$var->set_image_id( $tall_img );
		$var->save();
		t107_english( $var->get_id(), 'product' );
		$s->track_post( $var->get_id() );
		$flavours = array();
		foreach ( array( 'Vanilla' => 5, 'Chocolate' => 0, 'Unflavoured' => 5 ) as $flavour => $qty ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var->get_id() );
			$v->set_attributes( array( 'flavour' => $flavour ) );
			$v->set_regular_price( 'Unflavoured' === $flavour ? '2450' : '2600' );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( $qty );
			$v->save();
			t107_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
			$flavours[ $flavour ] = $v->get_id();
		}
		\WC_Product_Variable::sync( $var->get_id() );

		// Two options at once, Colour and Size: only Blue/L and Red/M are in stock, so each choice rules out the other.
		$pair_attrs = array();
		foreach ( array( 'Colour' => array( 'Blue', 'Red' ), 'Size' => array( 'M', 'L' ) ) as $attr_name => $values ) {
			$a = new \WC_Product_Attribute();
			$a->set_name( $attr_name );
			$a->set_options( $values );
			$a->set_visible( true );
			$a->set_variation( true );
			$pair_attrs[] = $a;
		}
		$pair = new \WC_Product_Variable();
		$pair->set_name( $tag . ' colour and size' );
		$pair->set_status( 'publish' );
		$pair->set_attributes( $pair_attrs );
		$pair->save();
		t107_english( $pair->get_id(), 'product' );
		$s->track_post( $pair->get_id() );
		foreach ( array( array( 'Blue', 'L', 5 ), array( 'Red', 'M', 5 ), array( 'Blue', 'M', 0 ), array( 'Red', 'L', 0 ) ) as [ $colour, $size, $qty ] ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $pair->get_id() );
			$v->set_attributes( array( 'colour' => $colour, 'size' => $size ) );
			$v->set_regular_price( '700' );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( $qty );
			$v->save();
			t107_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
		}
		\WC_Product_Variable::sync( $pair->get_id() );

		// The stock source holds none of the "refused" product, while WooCommerce still lists 3 (the card says so).
		$ledger                = get_option( FixtureStockSource::LEDGER, array() );
		$ledger                = is_array( $ledger ) ? $ledger : array();
		$ledger[ $ledger_key ] = array( $refused->get_id(), -3 );
		update_option( FixtureStockSource::LEDGER, $ledger, false );

		// The sale product in Arabic too, as every product of the store is: the Arabic page shows that twin, and an add
		// from it reaches the cart (a product with no Arabic version is not kept in an Arabic cart).
		do_action( 'wpml_admin_make_post_duplicates', $sale->get_id() );
		$sale_ar = (int) apply_filters( 'wpml_object_id', $sale->get_id(), 'product', false, 'ar' );
		if ( $sale_ar > 0 && $sale_ar !== $sale->get_id() ) {
			$s->track_post( $sale_ar );
		}

		$order = array( $long->get_id(), $sale->get_id(), $sold->get_id(), $low->get_id(), $var->get_id(), $two->get_id(), $bare->get_id(), $refused->get_id(), $big->get_id(), $pair->get_id(), $top->get_id() );

		// ---- The page, in English and in Arabic: a rail of the eight, then the same eight as a grid ----------------
		$content = sprintf(
			"<!-- wp:mizzey/product-rail %s /-->\n<!-- wp:mizzey/product-rail %s /-->\n<!-- wp:mizzey/product-card %s /-->",
			wp_json_encode( array( 'products' => $order, 'heading' => $tag . ' rail', 'link' => home_url( '/shop/' ) ) ),
			wp_json_encode( array( 'products' => $order, 'layout' => 'grid' ) ),
			wp_json_encode( array( 'skeleton' => true ) )
		);
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		$en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' cards', 'post_content' => $content ) );
		do_action( 'wpml_switch_language', 'ar' );
		$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' بطاقات', 'post_content' => $content ) );
		do_action( 'wpml_switch_language', 'en' );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$s->on_finish(
			static function () use ( $en, $ar ): void {
				wp_delete_post( $ar, true );
				wp_delete_post( $en, true );
			}
		);
		wp_set_current_user( 0 );

		$plan = array(
			'tag'      => $tag,
			'en'       => get_permalink( $en ),
			'ar'       => (string) apply_filters( 'wpml_permalink', get_permalink( $ar ), 'ar' ),
			'store'    => rest_url( 'wc/store/v1/' ),
			'wishlist' => rest_url( 'mizzey/v1/wishlist' ),
			'ids'      => array(
				'long'    => $long->get_id(),
				'bare'    => $bare->get_id(),
				'sale'    => $sale->get_id(),
				'sold'    => $sold->get_id(),
				'low'     => $low->get_id(),
				'two'     => $two->get_id(),
				'var'     => $var->get_id(),
				'refused' => $refused->get_id(),
				'big'     => $big->get_id(),
				'pair'    => $pair->get_id(),
				'top'     => $top->get_id(),
			),
			'variations' => $flavours,
			'arIds'    => array( 'sale' => $sale_ar ),
			'shots'    => get_temp_dir(),
		);
		$result = t107_browser( $plan );
		// The database is shared: a run that another process switched the stand-in off under says so, rather than failing quietly.
		wp_cache_delete( 'alloptions', 'options' );
		$check( 'yes' === get_option( FixtureStockSource::OPTION ), 'setup: the stock source stand-in stayed on through the browser run' );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 40, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $result['shots'] ?? array() ) as $shot ) {
			$s->note( '      screenshot: ' . $shot );
		}

		// ---- The add sequence, the count on the card, the toast on a phone, a tap: Chromium and WebKit ----------
		$motion = t107_browser( $plan, 'card-motion.cjs' );
		$check( empty( $motion['error'] ), 'the motion run ran' . ( empty( $motion['error'] ) ? '' : ': ' . substr( (string) $motion['error'], 0, 1500 ) ) );
		foreach ( (array) ( $motion['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $motion['checks'] ?? array() ) ) >= 150, 'the motion run made every check (' . count( (array) ( $motion['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $motion['shots'] ?? array() ) as $shot ) {
			$s->note( '      screenshot: ' . $shot );
		}

		// ---- What the server holds ----------------------------------------------------------------------------------
		$cart = (array) ( $result['cart'] ?? array() );
		$s->note( '      the browser\'s cart, as the Store API answered: ' . wp_json_encode( array_map( static fn ( $i ) => array( $i['id'] ?? 0, $i['quantity'] ?? 0 ), (array) ( $cart['items'] ?? array() ) ) ) );
		$lines = array();
		foreach ( (array) ( $cart['items'] ?? array() ) as $item ) {
			$lines[ (int) $item['id'] ] = (int) $item['quantity'];
		}
		$check( 1 === ( $lines[ $long->get_id() ] ?? 0 ), 'PLP-04 the quick add put one of the long-named product in the cart' );
		$check( 1 === ( $lines[ $flavours['Unflavoured'] ] ?? 0 ) || 1 === ( $lines[ $flavours['Vanilla'] ] ?? 0 ), 'PLP-04 the quick add with options put the chosen flavour in the cart' );
		$check( ! isset( $lines[ $refused->get_id() ] ), 'ERP-03 the product the stock source refused is not in the cart' );
		$check( ! isset( $lines[ $flavours['Chocolate'] ] ), 'PLP-03 the sold-out flavour could not be added' );

		return $ok;
	}
);

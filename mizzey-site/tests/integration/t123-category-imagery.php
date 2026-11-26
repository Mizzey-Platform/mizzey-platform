<?php
/**
 * t123, spec 066: category pictures in every place they are shown, as the closed design draws them (the owner's
 * comments 7 and 8 of 26 November 2026; AC-1, AC-3, AC-7, AC-15; NAV-07, NAV-08, NAV-10, HOME-05, PLP-06, MER-06).
 *
 * The runtime is prepared here and the browser part is mizzey-site/tests/browser/category-imagery.cjs:
 *
 * - four top categories in both languages, each with a product so none is empty: Beauty (a child, and a wide picture,
 *   1536 x 1024), Home (a child, no picture), Gifts (no child, a tall picture, 1400 x 2100) and Pets (no child, no
 *   picture); the pictures are drawn here, so nothing is downloaded;
 * - each on the home page's category shortcuts, first; a page in each language carrying the shortcuts section (the
 *   front page is shared, and stays as it is).
 *
 * Before the browser: the helper's own answers (the box, the fit, `sizes` after the fit, the placeholder) and every
 * place's data carrying the picture's id. The browser then checks each place at 1440 and 390, at 1x to 3x, in
 * Chromium and WebKit, in English and Arabic. Everything made here is prefixed "ci-" and removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\CategoryImage;
use MizzeySite\Storefront\HeaderData;
use MizzeySite\Storefront\HomeSections;

require_once __DIR__ . '/_bootstrap.php';

/** A category's own term_taxonomy_id (as t108). */
function t123_ttid( int $term_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'product_cat'", $term_id ) );
}

/** Run something in a language and come back. */
function t123_in( string $lang, callable $fn ) {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$out = $fn();
	do_action( 'wpml_switch_language', $current );
	return $out;
}

/** A picture drawn here, so nothing is downloaded: a frame and a disc, to see a crop. */
function t123_image( Scenario $s, string $name, int $width, int $height ): int {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 233, 238, 249 ) );
	imagefilledellipse( $image, (int) ( $width / 2 ), (int) ( $height / 2 ), (int) ( $width * 0.6 ), (int) ( $height * 0.8 ), imagecolorallocate( $image, 255, 106, 74 ) );
	imagerectangle( $image, 2, 2, $width - 3, $height - 3, imagecolorallocate( $image, 38, 59, 115 ) );
	ob_start();
	imagejpeg( $image, null, 80 );
	$file = wp_upload_bits( $name . '.jpg', null, (string) ob_get_clean() );
	if ( ! empty( $file['error'] ) ) {
		throw new \RuntimeException( 'The fixture image could not be written: ' . $file['error'] );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = (int) wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ), $file['file'] );
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
	$s->on_finish( static fn () => wp_delete_attachment( $id, true ) );
	return $id;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t123_browser( array $plan ): array {
	$file = wp_tempnam( 't123-plan' );
	file_put_contents( $file, wp_json_encode( $plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/category-imagery.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't123-category-imagery', 'Owner comments 7 and 8 (spec 066): category pictures as drawn in every place, never upscaled, and the placeholder for a category with none' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'ci-' . strtolower( wp_generate_password( 4, false, false ) );

		// ---- Categories in both languages ------------------------------------------------------------------------
		$terms = array();
		$s->on_finish(
			static function () use ( &$terms ): void {
				foreach ( array_reverse( $terms ) as $term ) {
					wp_delete_term( $term, 'product_cat' );
				}
			}
		);
		$category = static function ( string $en, string $ar, int $parent = 0, int $ar_parent = 0 ) use ( &$terms ): array {
			do_action( 'wpml_switch_language', 'en' );
			$made = wp_insert_term( $en, 'product_cat', array( 'parent' => $parent ) );
			$id   = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
			$trid = apply_filters( 'wpml_element_trid', null, t123_ttid( $id ), 'tax_product_cat' );
			do_action( 'wpml_switch_language', 'ar' );
			$arm = wp_insert_term( $ar, 'product_cat', array( 'parent' => $ar_parent ) );
			do_action( 'wpml_switch_language', 'en' );
			$ar_id = is_wp_error( $arm ) ? 0 : (int) $arm['term_id'];
			do_action( 'wpml_set_element_language_details', array( 'element_id' => t123_ttid( $ar_id ), 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = $id;
			$terms[] = $ar_id;
			return array( $id, $ar_id );
		};
		$names = array(
			'en' => array( 'beauty' => "{$tag} Beauty", 'home' => "{$tag} Home", 'gifts' => "{$tag} Gifts", 'pets' => "{$tag} Pets" ),
			'ar' => array( 'beauty' => "{$tag} الجمال", 'home' => "{$tag} المنزل", 'gifts' => "{$tag} الهدايا", 'pets' => "{$tag} الحيوانات" ),
		);
		$ids = array();
		foreach ( array_keys( $names['en'] ) as $key ) {
			$ids[ $key ] = $category( $names['en'][ $key ], $names['ar'][ $key ] );
		}
		$skin  = $category( "{$tag} Skin care", "{$tag} العناية بالبشرة", $ids['beauty'][0], $ids['beauty'][1] );
		$lamps = $category( "{$tag} Lamps", "{$tag} مصابيح", $ids['home'][0], $ids['home'][1] );
		$wide  = t123_image( $s, "{$tag}-wide", 1536, 1024 );
		$tall  = t123_image( $s, "{$tag}-tall", 1400, 2100 );
		update_term_meta( $ids['beauty'][0], 'thumbnail_id', $wide );
		update_term_meta( $ids['gifts'][0], 'thumbnail_id', $tall );
		$position = -40;
		foreach ( $ids as $pair ) {
			update_term_meta( $pair[0], HomeSections::ON_HOME, 'yes' );
			update_term_meta( $pair[0], HomeSections::POSITION, $position );
			update_term_meta( $pair[0], 'order', $position );
			$position += 10;
		}
		foreach ( array( $skin[0], $lamps[0], $ids['gifts'][0], $ids['pets'][0] ) as $n => $cat ) {
			$product = $s->simple_product( "{$tag} Product {$n}", '120.00', null );
			wp_set_object_terms( $product->get_id(), array( $cat ), 'product_cat' );
			$sitepress->make_duplicate( $product->get_id(), 'ar' );
			$dup = (int) apply_filters( 'wpml_object_id', $product->get_id(), 'product', false, 'ar' );
			if ( $dup > 0 ) {
				$s->track_post( $dup );
			}
		}
		foreach ( $terms as $t ) {
			wp_update_term_count_now( array( t123_ttid( $t ) ), 'product_cat' );
		}
		wp_cache_flush();

		// ---- A page in each language with the category shortcuts ------------------------------------------------
		$pages = array();
		$s->on_finish(
			static function () use ( &$pages ): void {
				foreach ( $pages as $page ) {
					wp_delete_post( $page, true );
				}
			}
		);
		$shortcuts = array();
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$shortcuts[ $lang ] = (string) t123_in(
				$lang,
				static function () use ( $tag, $lang, &$pages ): string {
					$page    = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} shortcuts {$lang}", 'post_content' => '<!-- wp:mizzey/category-shortcuts {"heading":"' . $tag . '"} /-->' ) );
					$pages[] = $page;
					return (string) get_permalink( $page );
				}
			);
		}

		// ---- The helper's own answers -----------------------------------------------------------------------------
		$check( $wide === CategoryImage::id( $ids['beauty'][0] ) && $wide === CategoryImage::id( $ids['beauty'][1] ) && 0 === CategoryImage::id( $ids['pets'][0] ), 'the picture is the one kept on the category in the main language, in both languages; none is 0' );
		$check( 450.0 === CategoryImage::paint( 'mega', array( 408, 300 ), 1.5 ) && 640.0 === CategoryImage::paint( 'mega-wide', array( 640, 200 ), 2 / 3 ) && abs( CategoryImage::paint( 'row', array( 32, 32 ), 2 / 3 ) - 21.33 ) < 0.01, 'the paint after the fit: a wide picture covering 408 x 300 is painted 450 wide, a tall one covering 640 x 200 keeps 640, a tall one contained in 32 x 32 is 21.3 wide' );
		$files = array( array( 'url' => 'a-300', 'w' => 300, 'h' => 200 ), array( 'url' => 'a-768', 'w' => 768, 'h' => 512 ), array( 'url' => 'a-1024', 'w' => 1024, 'h' => 683 ), array( 'url' => 'a-1536', 'w' => 1536, 'h' => 1024 ) );
		$picked = array_map( static fn ( $f ) => $f['url'] . '@' . $f['x'], CategoryImage::densities( $files, 114 ) );
		$big    = array_map( static fn ( $f ) => $f['url'] . '@' . $f['x'], CategoryImage::densities( $files, 640 ) );
		$check( array( 'a-300@1', 'a-768@3' ) === $picked && array( 'a-768@1', 'a-1536@2' ) === $big, 'each density gets the smallest file at least as wide as it needs, listed once; past the largest file, the largest (' . implode( ' ', $picked ) . ' | ' . implode( ' ', $big ) . ')' );
		$html = CategoryImage::html( $wide, 'mega' );
		$check( str_contains( $html, 'srcset="' ) && str_contains( $html, ' 2x' ) && str_contains( $html, ' 3x' ) && ! str_contains( $html, '-150x150' ) && ! str_contains( $html, '-300x300' ) && str_contains( $html, 'data-place="mega"' ) && str_contains( $html, 'alt=""' ), 'a picture comes per density from the uncropped sizes, never a square crop of a wide picture: ' . substr( $html, 0, 400 ) );
		$tile = CategoryImage::html( $wide, 'shortcut' );
		$check( str_starts_with( $tile, '<picture' ) && str_contains( $tile, '<source media="(min-width:1024px)"' ) && str_contains( $tile, '<source media="(min-width:600px)"' ), 'a place whose box changes with the screen (the shortcuts: 80 to 235) gives each range its own set: ' . substr( $tile, 0, 300 ) );
		// The glyph comes from the theme's icon set, which a command-line run does not load by itself.
		if ( ! function_exists( 'MizzeySite\Theme\Icons\icon' ) && is_file( get_theme_file_path( 'inc/icons.php' ) ) ) {
			require_once get_theme_file_path( 'inc/icons.php' );
		}
		$empty = CategoryImage::html( 0, 'row' );
		$check( str_contains( $empty, 'is-empty' ) && str_contains( $empty, 'aria-hidden="true"' ) && str_contains( $empty, '<svg' ) && str_contains( $empty, 'width="20"' ), 'no picture: the placeholder with the image glyph, 20 in a 32 row: ' . $empty );

		// ---- Every place's data carries the picture's id ----------------------------------------------------------
		$tree = array_column( array_filter( (array) t123_in( 'ar', static fn () => HeaderData::categories() ), static fn ( $n ) => str_contains( (string) $n['name'], $tag ) ), 'image_id', 'name' );
		$check( 4 === count( $tree ) && $wide === (int) ( $tree[ $names['ar']['beauty'] ] ?? -1 ) && 0 === (int) ( $tree[ $names['ar']['home'] ] ?? -1 ) && $tall === (int) ( $tree[ $names['ar']['gifts'] ] ?? -1 ), 'the menus\' data (specs/054) carries each category\'s picture id in Arabic too' );
		$home = array_values( array_filter( HomeSections::onHome( 'product_cat' ), static fn ( $t ) => str_contains( (string) $t['name'], $tag ) ) );
		$check( 4 === count( $home ) && $wide === (int) $home[0]['image_id'], 'the shortcuts\' data (specs/056) carries the picture id' );

		// ---- The browser -----------------------------------------------------------------------------------------
		$links = static fn ( string $lang ): array => array(
			'beauty' => (string) get_term_link( $ids['beauty'][ 'ar' === $lang ? 1 : 0 ], 'product_cat' ),
			'pets'   => (string) get_term_link( $ids['pets'][ 'ar' === $lang ? 1 : 0 ], 'product_cat' ),
		);
		$plan   = array(
			'shots' => get_temp_dir(),
			'en'    => array( 'home' => home_url( '/' ), 'shortcuts' => $shortcuts['en'], 'names' => $names['en'], 'listing' => t123_in( 'en', static fn () => $links( 'en' ) ) ),
			'ar'    => array( 'home' => (string) apply_filters( 'wpml_permalink', home_url( '/' ), 'ar' ), 'shortcuts' => $shortcuts['ar'], 'names' => $names['ar'], 'listing' => t123_in( 'ar', static fn () => $links( 'ar' ) ) ),
		);
		$result = t123_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 400, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		$s->note( '      screenshots: ' . implode( ', ', (array) ( $result['shots'] ?? array() ) ) );

		return $ok;
	}
);

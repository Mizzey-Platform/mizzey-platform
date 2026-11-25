<?php
/**
 * t110, spec 066 slice 5: the home page sections (AC-3, AC-4; HOME-01 to HOME-11, SSC-02, SSC-03, SSC-12, SSC-13).
 *
 * On the server, over a fixture named "s5-" and removed when the scenario ends (products with badges, a category, a
 * collection and a brand shown on the home page, each with its Arabic translation, an empty category, two images):
 *
 * - each product section reads the rule or curation of specs/056: a badge, a category, a collection, the newest, on
 *   sale, and products picked by hand in the editor's order; a section whose rule matches nothing renders nothing;
 * - the hero renders as a single banner or as a slider, its first image never lazy, with its dimensions and a high
 *   fetch priority, and its preload is printed for the front page when the hero is the first shown section;
 * - a section without its words or terms renders nothing.
 *
 * In Chromium (mizzey-site/tests/browser/home-sections.cjs), signed in as an editor account, on the front page in
 * English and its Arabic translation: every section is added through the inserter and edited (words in place, the
 * settings in the sidebar, an image from the media library), saved, and the storefront shows the sections in the
 * editor's order with their content; one is moved up, previewed (the preview has the new order, the live page the old
 * one), then published; one is hidden and keeps its content; the Arabic page has its own sections and words. Then the
 * storefront at 390 and 1440 wide in both languages: the page never scrolls sideways, the hero's first image is
 * preloaded and is not lazy, the slider waits at least six seconds a slide, pauses while hovered, and stays still
 * under reduced motion.
 *
 * Then, on the server: the stored front page holds the sections in the new order, the hidden one with its content.
 * The front page setting, the pages, the fixture and the account are restored or removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Blocks\Banners;
use MizzeySite\Blocks\Hero;
use MizzeySite\Blocks\ProductSections;
use MizzeySite\Blocks\TermSections;
use MizzeySite\Blocks\TrustSignals;
use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Storefront\HomeSections;

require_once __DIR__ . '/_bootstrap.php';

/** The storefront's home in a language, as WPML links it. */
function t110_home( string $lang ): string {
	return (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
}

/** Run the browser script on a plan. @return array<string,mixed> */
function t110_browser( array $plan ): array {
	$file = wp_tempnam( 't110-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/home-sections.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/** An image drawn here, so nothing is downloaded. */
function t110_image( Scenario $s, string $name, int $width, int $height ): int {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 233, 238, 249 ) );
	imagefilledellipse( $image, (int) ( $width * 0.7 ), (int) ( $height * 0.5 ), (int) ( $width * 0.4 ), (int) ( $height * 0.7 ), imagecolorallocate( $image, 255, 106, 74 ) );
	ob_start();
	imagejpeg( $image, null, 80 );
	$bits = (string) ob_get_clean();
	$file = wp_upload_bits( $name . '.jpg', null, $bits );
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
 * The library sections of a stored page, in order: their type, a word that tells them apart, and Show on the site.
 *
 * @return list<array{type:string,word:string,visible:bool}>
 */
function t110_sections( int $post_id ): array {
	clean_post_cache( $post_id );
	$out = array();
	foreach ( parse_blocks( (string) get_post_field( 'post_content', $post_id, 'raw' ) ) as $block ) {
		if ( ! is_string( $block['blockName'] ) || ! str_starts_with( $block['blockName'], 'mizzey/' ) ) {
			continue;
		}
		$a     = (array) $block['attrs'];
		$word  = (string) ( $a['heading'] ?? ( $a['slides'][0]['title'] ?? '' ) );
		$out[] = array(
			'type'    => substr( $block['blockName'], 7 ),
			'word'    => wp_strip_all_tags( html_entity_decode( $word ) ),
			'visible' => false !== ( $a['visible'] ?? true ),
		);
	}
	return $out;
}

run(
	new Scenario( 't110-home-sections', 'AC-3, AC-4 (slice 5): every home section from the library, fed from the admin, added, edited, hidden, reordered and previewed by an editor in each language' ),
	function ( Scenario $s ): bool {
		global $sitepress, $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		// Every record this run makes is named s5-..., beside other builders on the same database.
		$tag   = 's5-' . strtolower( wp_generate_password( 5, false, false ) );
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		do_action( 'wpml_switch_language', 'en' );

		// ---- Terms on the home page, each with its Arabic translation (specs/056) ------------------------------------
		$terms = array();
		$s->on_finish( static function () use ( &$terms ): void { foreach ( $terms as $t ) { wp_delete_term( $t[0], $t[1] ); } } );
		$make_term = static function ( string $taxonomy, string $en, string $ar, ?int $position ) use ( &$terms, $admin ): int {
			do_action( 'wpml_switch_language', 'en' );
			$m    = wp_insert_term( $en, $taxonomy, array( 'slug' => sanitize_title( $en ) ) );
			$id   = (int) $m['term_id'];
			$trid = apply_filters( 'wpml_element_trid', null, (int) get_term( $id, $taxonomy )->term_taxonomy_id, 'tax_' . $taxonomy );
			do_action( 'wpml_switch_language', 'ar' );
			$a = wp_insert_term( $ar, $taxonomy, array( 'slug' => sanitize_title( $en ) . '-ar' ) );
			do_action( 'wpml_switch_language', 'en' );
			do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( (int) $a['term_id'], $taxonomy )->term_taxonomy_id, 'element_type' => 'tax_' . $taxonomy, 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = array( $id, $taxonomy );
			$terms[] = array( (int) $a['term_id'], $taxonomy );
			if ( null !== $position ) {
				wp_set_current_user( $admin );
				$_POST = array( 'mizzey_on_home_nonce' => wp_create_nonce( 'mizzey_on_home' ), 'mizzey_on_home' => '1', 'mizzey_home_position' => (string) $position );
				HomeSections::saveFields( $id );
				$_POST = array();
			}
			return $id;
		};
		$category   = $make_term( 'product_cat', "{$tag} Beauty", "{$tag} الجمال", 0 );
		$empty_cat  = $make_term( 'product_cat', "{$tag} Empty", "{$tag} فارغ", null );
		$collection = $make_term( 'product_collection', "{$tag} Gym bag", "{$tag} حقيبة الرياضة", 0 );
		$brand      = taxonomy_exists( 'product_brand' ) ? $make_term( 'product_brand', "{$tag} Lumen", "{$tag} لومن", 0 ) : 0;
		$brand_two  = $brand ? $make_term( 'product_brand', "{$tag} Mira", "{$tag} ميرا", 1 ) : 0;

		// ---- Products: trending, new, on sale, in the category and the collection, each with its Arabic record -------
		$product = function ( string $name, string $price, array $badges, array $more = array() ) use ( $s, $sitepress, $category, $collection ): int {
			do_action( 'wpml_switch_language', 'en' );
			$p = $s->simple_product( $name, $price, null );
			foreach ( $more as $method => $value ) {
				$p->{$method}( $value );
			}
			$p->update_meta_data( ProductFields::BADGES, $badges );
			$p->set_category_ids( array( $category ) );
			$p->save();
			wp_set_object_terms( $p->get_id(), array( $collection ), 'product_collection' );
			$sitepress->make_duplicate( $p->get_id(), 'ar' );
			$s->track_post( (int) apply_filters( 'wpml_object_id', $p->get_id(), 'product', false, 'ar' ) );
			return $p->get_id();
		};
		$trend_a = $product( "{$tag} Trend Lamp", '500.00', array( 'trending' ) );
		$trend_b = $product( "{$tag} Trend Mug", '300.00', array( 'trending', 'new' ) );
		$sale    = $product( "{$tag} Sale Balm", '200.00', array(), array( 'set_sale_price' => '150.00' ) );
		wp_cache_flush();

		// ---- AC-3: each product section reads its rule or curation -----------------------------------------------------
		$names = static function ( string $html ) use ( $tag ): array {
			preg_match_all( '/' . preg_quote( $tag, '/' ) . ' [A-Za-z]+ [A-Za-z]+/', $html, $m );
			return array_values( array_unique( $m[0] ) );
		};
		$render = static fn ( array $attrs ): string => ProductSections::renderProducts( $attrs + array( 'heading' => "{$tag} heading" ) );
		$by_badge = $names( $render( array( 'source' => 'badge', 'badge' => 'trending', 'count' => 24 ) ) );
		$check( in_array( "{$tag} Trend Lamp", $by_badge, true ) && in_array( "{$tag} Trend Mug", $by_badge, true ) && ! in_array( "{$tag} Sale Balm", $by_badge, true ), 'HOME-02 a section by the Trending badge shows the trending products and not the others (' . implode( ', ', $by_badge ) . ')' );
		$new = $names( $render( array( 'source' => 'badge', 'badge' => 'new', 'count' => 24 ) ) );
		$check( in_array( "{$tag} Trend Mug", $new, true ) && ! in_array( "{$tag} Trend Lamp", $new, true ), 'HOME-03 a section by the New badge shows only the new product (' . implode( ', ', $new ) . ')' );
		$in_cat = $names( $render( array( 'source' => 'category', 'term' => $category, 'count' => 24 ) ) );
		$check( 3 === count( $in_cat ), 'HOME-10 a section by a category shows the category\'s products (' . implode( ', ', $in_cat ) . ')' );
		$in_coll = $names( $render( array( 'source' => 'collection', 'term' => $collection, 'count' => 24 ) ) );
		$check( 3 === count( $in_coll ), 'HOME-10 a section by a collection shows the collection\'s products (' . implode( ', ', $in_coll ) . ')' );
		$on_sale = $names( $render( array( 'source' => 'sale', 'count' => 24 ) ) );
		$check( in_array( "{$tag} Sale Balm", $on_sale, true ) && ! in_array( "{$tag} Trend Lamp", $on_sale, true ), 'HOME-07 a section of products on sale shows the sale product (' . implode( ', ', $on_sale ) . ')' );
		$picked = $names( $render( array( 'source' => 'products', 'products' => array( $sale, $trend_a ) ) ) );
		$check( array( "{$tag} Sale Balm", "{$tag} Trend Lamp" ) === $picked, 'HOME-10 products picked by hand show in the editor\'s order (' . implode( ', ', $picked ) . ')' );
		$newest = ProductSections::productIds( array( 'source' => 'newest', 'count' => 3 ) );
		$check( array( $sale, $trend_b, $trend_a ) === $newest, 'HOME-03 a section of the newest shows the latest products first (' . implode( ',', $newest ) . ')' );
		$check( '' === $render( array( 'source' => 'category', 'term' => $empty_cat ) ) && '' === ProductSections::renderDeals( array( 'heading' => 'x', 'source' => 'category', 'term' => $empty_cat ) ), 'AC-3 a product section whose rule matches nothing renders nothing, no empty frame' );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_names = $names( $render( array( 'source' => 'badge', 'badge' => 'trending', 'count' => 24 ) ) );
		$ar_ids   = ProductSections::productIds( array( 'source' => 'category', 'term' => $category, 'count' => 24 ) );
		do_action( 'wpml_switch_language', 'en' );
		$ar_lang = array_unique( array_map( static fn ( $id ) => (string) apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_product' ) ), $ar_ids ) );
		$check( 2 === count( $ar_names ) && array( 'ar' ) === array_values( $ar_lang ) && 3 === count( $ar_ids ), 'SSC-12 in Arabic the same rules read the Arabic products, the category chosen in English included (' . wp_json_encode( $ar_lang ) . ')' );

		// ---- AC-3: the term sections read what is shown on the home page ---------------------------------------------
		$cats = TermSections::renderCategories( array( 'heading' => "{$tag} Shop by category" ) );
		$check( str_contains( $cats, "{$tag} Beauty" ) && ! str_contains( $cats, "{$tag} Empty" ) && str_contains( $cats, 'mizzey-shortcuts__item--all' ), 'HOME-05 the category shortcuts show the category marked for the home page, not the other, and the all-categories tile' );
		$colls = TermSections::renderCollections( array( 'heading' => "{$tag} Featured" ) );
		$check( str_contains( $colls, "{$tag} Gym bag" ), 'HOME-04 the featured collections show the collection marked for the home page' );
		$check( 0 === $brand || str_contains( TermSections::renderBrands( array( 'heading' => 'Brands' ) ), "{$tag} Lumen" ), 'HOME-06 the brand highlights show the brand marked for the home page' );

		// ---- HOME-01: the hero, single and slider; its first image is the page's LCP --------------------------------
		$wide  = t110_image( $s, "{$tag}-hero-1", 1600, 560 );
		$wide2 = t110_image( $s, "{$tag}-hero-2", 1600, 560 );
		$slides = array(
			array( 'imageId' => $wide, 'imageAlt' => '', 'tone' => 'light', 'title' => "{$tag} One<br>line two", 'line' => 'Line <strong>25%</strong>', 'ctaLabel' => 'Go', 'ctaUrl' => home_url( '/shop/' ) ),
			array( 'imageId' => $wide2, 'tone' => 'dark', 'title' => "{$tag} Two", 'line' => '', 'ctaLabel' => 'Go', 'ctaUrl' => home_url( '/shop/' ) ),
		);
		$single = Hero::render( array( 'variant' => 'single', 'slides' => $slides ) );
		$slider = Hero::render( array( 'variant' => 'slider', 'slides' => $slides, 'interval' => 3 ) );
		$check( 1 === substr_count( $single, 'mizzey-hero__slide ' ) && ! str_contains( $single, 'mizzey-hero__controls' ), 'HOME-01 the single banner shows one banner, no dots and no progress line (C1-Options)' );
		$check( 2 === substr_count( $slider, 'mizzey-hero__slide ' ) && str_contains( $slider, 'mizzey-hero__controls' ) && str_contains( $slider, 'data-interval="6"' ), 'HOME-01 the slider shows every slide and its controls, at least six seconds a slide even when set lower' );
		preg_match_all( '/<img [^>]*class="mizzey-hero__image[^"]*"[^>]*>/', $slider, $imgs );
		$first = $imgs[0][0] ?? '';
		$check( '' !== $first && str_contains( $first, 'fetchpriority="high"' ) && ! str_contains( $first, 'loading="lazy"' ) && preg_match( '/ width="\d+"/', $first ) && preg_match( '/ height="\d+"/', $first ), 'K-6 the first slide\'s image is not lazy, has a high fetch priority and its dimensions: ' . substr( $first, 0, 160 ) );
		$check( str_contains( $imgs[0][1] ?? '', 'loading="lazy"' ), 'K-6 the later slides\' images wait until needed' );
		$check( str_contains( $slider, 'line two' ) && str_contains( $slider, '<br' ) && str_contains( $slider, '<strong>25%</strong>' ), 'HOME-01 a headline keeps its line break and a line its bold figure, as the boards draw them' );
		$check( '' === Hero::render( array( 'slides' => array( array( 'title' => '' ) ) ) ), 'AC-3 a hero with no headline and no image renders nothing' );

		// ---- HOME-10: products picked by hand are never cut without a word --------------------------------------------
		// The editor offers no count for a hand-picked list, so a stale count (12 by default) must not drop a pick; the
		// list stops only at the section's stated maximum, which the editor's field enforces too (maxCount).
		$picks = range( 900001, 900013 );
		$check( $picks === ProductSections::productIds( array( 'source' => 'products', 'products' => $picks, 'count' => 12 ) ) && $picks === ProductSections::productIds( array( 'source' => 'products', 'products' => $picks ) ), 'HOME-10 thirteen products picked by hand show all thirteen, in order, whatever the count setting holds' );
		$lots = range( 900001, 900000 + ProductSections::MAX + 6 );
		$check( array_slice( $lots, 0, ProductSections::MAX ) === ProductSections::productIds( array( 'source' => 'products', 'products' => $lots ) ), 'HOME-10 a hand-picked list stops at the section\'s stated maximum (' . ProductSections::MAX . '), the limit the editor\'s field sets' );

		// ---- A plain text field keeps every character the editor typed ------------------------------------------------
		// A text field (not written in place) stores its words raw: "<" is a character there, never the start of a tag.
		$lt_a     = 'Under <EGP 500';
		$lt_b     = 'Save <50% today';
		$survives = static fn ( string $html, string $words ): bool => str_contains( $html, esc_html( $words ) );
		$fields   = array(
			'products heading'              => static fn ( string $w ): string => ProductSections::renderProducts( array( 'source' => 'products', 'products' => array( $sale ), 'heading' => $w ) ),
			'deals heading'                 => static fn ( string $w ): string => ProductSections::renderDeals( array( 'source' => 'products', 'products' => array( $sale ), 'heading' => $w ) ),
			'deals line'                    => static fn ( string $w ): string => ProductSections::renderDeals( array( 'source' => 'products', 'products' => array( $sale ), 'heading' => 'x', 'line' => $w ) ),
			'category shortcuts heading'    => static fn ( string $w ): string => TermSections::renderCategories( array( 'heading' => $w ) ),
			'category shortcuts all label'  => static fn ( string $w ): string => TermSections::renderCategories( array( 'heading' => 'x', 'allLabel' => $w ) ),
			'featured collections heading'  => static fn ( string $w ): string => TermSections::renderCollections( array( 'heading' => $w ) ),
			'brand highlights heading'      => static fn ( string $w ): string => 0 === $brand ? esc_html( $w ) : TermSections::renderBrands( array( 'heading' => $w ) ),
			'brand highlights all label'    => static fn ( string $w ): string => 0 === $brand ? esc_html( $w ) : TermSections::renderBrands( array( 'heading' => 'x', 'allLabel' => $w, 'allUrl' => home_url( '/brands/' ) ) ),
			'hero name for screen readers'  => static fn ( string $w ): string => Hero::render( array( 'variant' => 'slider', 'slides' => $slides, 'label' => $w ) ),
			'hero slide image description'  => static fn ( string $w ): string => Hero::render( array( 'variant' => 'single', 'slides' => array( array( 'imageAlt' => $w ) + $slides[0] ) ) ),
			'offer banner image description' => static fn ( string $w ): string => Banners::renderOffer( array( 'heading' => 'x', 'imageId' => $wide, 'imageAlt' => $w ) ),
			'trust signals name for screen readers' => static fn ( string $w ): string => TrustSignals::render( array( 'label' => $w, 'items' => array( array( 'title' => 'x' ) ) ) ),
		);
		foreach ( $fields as $field => $render_field ) {
			$check( $survives( $render_field( $lt_a ), $lt_a ) && $survives( $render_field( $lt_b ), $lt_b ), "SSC-02 the {$field}, a text field, keeps \"{$lt_a}\" and \"{$lt_b}\" whole" );
		}
		// A field written in place stores its text as entities, and still reads whole.
		$check( $survives( Banners::renderOffer( array( 'heading' => esc_html( $lt_a ), 'text' => esc_html( $lt_b ) ) ), $lt_a ) && $survives( Banners::renderOffer( array( 'heading' => 'x', 'text' => esc_html( $lt_b ) ) ), $lt_b ), 'SSC-02 the offer banner\'s words written in place keep "<" too' );

		// ---- The front page in each language, and the editor account --------------------------------------------------
		do_action( 'wpml_switch_language', 'en' );
		$en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} home", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'ar' );
		$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} الرئيسية", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'en' );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$was = array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ) );
		$s->on_finish(
			static function () use ( $was, $en, $ar ): void {
				update_option( 'show_on_front', $was['show_on_front'] );
				update_option( 'page_on_front', $was['page_on_front'] );
				wp_delete_post( $ar, true );
				wp_delete_post( $en, true );
			}
		);
		// The front page is a site-wide setting other runs share: put back even if this process dies on a fatal error
		// before the finishers run (a finisher that already ran makes this a no-op).
		register_shutdown_function(
			static function () use ( $was, $en ): void {
				if ( (int) get_option( 'page_on_front' ) === $en ) {
					update_option( 'show_on_front', $was['show_on_front'] );
					update_option( 'page_on_front', $was['page_on_front'] );
				}
			}
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $en );

		wp_update_post( array( 'ID' => $en, 'post_content' => wp_slash( serialize_blocks( array( array( 'blockName' => 'mizzey/hero', 'attrs' => array( 'slides' => $slides ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ) ) ) ) );
		$preload = Hero::preloadTag( $en );
		$check( str_contains( $preload, 'rel="preload" as="image"' ) && str_contains( $preload, (string) wp_get_attachment_image_url( $wide, 'full' ) ) && str_contains( $preload, 'fetchpriority="high"' ), 'K-6 the front page preloads the hero\'s first image from its head: ' . substr( $preload, 0, 140 ) );
		wp_update_post( array( 'ID' => $en, 'post_content' => serialize_blocks( array(
			array( 'blockName' => 'mizzey/signup', 'attrs' => array( 'heading' => 'x' ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
			array( 'blockName' => 'mizzey/hero', 'attrs' => array( 'slides' => $slides ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ),
		) ) ) );
		$check( '' === Hero::preloadTag( $en ), 'K-6 a hero lower on the page is not preloaded ahead of the section above it' );
		wp_update_post( array( 'ID' => $en, 'post_content' => '' ) );

		$user_id = wp_insert_user( array( 'user_login' => $tag . '-editor', 'user_email' => $tag . '-editor@example.com', 'user_pass' => wp_generate_password( 24 ), 'role' => 'editor', 'locale' => 'en_US' ) );
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( 'The editor account could not be made: ' . $user_id->get_error_message() );
		}
		$s->track_user( (int) $user_id );
		update_user_meta(
			(int) $user_id,
			$wpdb->get_blog_prefix() . 'persisted_preferences',
			array(
				'core/edit-post' => array( 'welcomeGuide' => false, 'fullscreenMode' => false ),
				'core'           => array( 'enableChoosePatternModal' => false, 'editorMode' => 'visual', 'isComplementaryAreaVisible' => true ),
				'_modified'      => gmdate( 'c' ),
			)
		);
		$expire  = time() + 4 * HOUR_IN_SECONDS;
		$cookies = array(
			array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user_id, $expire, 'auth' ) ),
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user_id, $expire, 'logged_in' ) ),
		);

		$plan   = array(
			'tag'      => $tag,
			'base'     => home_url( '/' ),
			'cookies'  => $cookies,
			'library'  => \MizzeySite\Blocks\SectionLibrary::names(),
			'link'     => home_url( '/shop/' ),
			'image'    => "{$tag}-hero-1",
			'products' => array( "{$tag} Trend Lamp", "{$tag} Trend Mug" ),
			'sale'     => "{$tag} Sale Balm",
			'category' => array( 'en' => "{$tag} Beauty", 'ar' => "{$tag} الجمال" ),
			'en'       => array( 'edit' => admin_url( "post.php?post={$en}&action=edit&lang=en" ), 'view' => t110_home( 'en' ) ),
			'ar'       => array( 'edit' => admin_url( "post.php?post={$ar}&action=edit&lang=ar" ), 'view' => t110_home( 'ar' ) ),
			'shots'    => get_temp_dir(),
		);
		$result = t110_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 100, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );

		// ---- What is stored -------------------------------------------------------------------------------------------
		$stored = t110_sections( $en );
		$s->note( '      stored English sections: ' . wp_json_encode( $stored, JSON_UNESCAPED_UNICODE ) );
		$order = array_column( $stored, 'type' );
		$check( array( 'hero', 'offer-banner', 'products', 'category-shortcuts', 'featured-collections', 'deals', 'brand-highlights', 'signup' ) === $order, 'SSC-02 the stored front page holds the sections in the editor\'s new order (' . implode( ', ', $order ) . ')' );
		$deals = array_values( array_filter( $stored, static fn ( $x ) => 'deals' === $x['type'] ) )[0] ?? array();
		$check( isset( $deals['visible'] ) && false === $deals['visible'] && "{$tag} Deals" === $deals['word'], 'SSC-03 the hidden section is stored with its content and Show on the site off' );
		$stored_ar = t110_sections( $ar );
		$s->note( '      stored Arabic sections: ' . wp_json_encode( $stored_ar, JSON_UNESCAPED_UNICODE ) );
		$check( 3 === count( $stored_ar ) && "{$tag} الأصلي" === ( $stored_ar[0]['word'] ?? '' ), 'SSC-12 the Arabic front page holds its own sections and words' );

		return $ok;
	}
);

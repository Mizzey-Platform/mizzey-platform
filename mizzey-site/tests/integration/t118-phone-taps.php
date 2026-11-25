<?php
/**
 * t118, spec 066 (K-5, K-6; NFR-04, NFR-11): on a phone every control acts on the first tap, the drawers move in
 * and out, and the sliders stand still under a finger.
 *
 * The runtime is prepared here and the browser part is mizzey-site/tests/browser/phone-taps.cjs:
 *
 * - a simple product and a variable product (two flavours) with stock, the stock source stand-in on;
 * - a page in each language with a rail of the two, a hero slider of two banners (headlines only, no image) and an
 *   announcement strip slider of three messages, as staff place them in the editor.
 *
 * The browser checks, at 390 wide with touch, CPU and network slowed: the menu, the search (on the product page) and a
 * card's add tapped the moment each is visible, before the page's scripts have started, act once; the same controls
 * act on a touch the browser sends no click for, and once on a full tap; the phone menu slides in and out over
 * several frames in English and in Arabic (from the right), by Escape too, and fades under reduced motion; the hero
 * and the strip do not move while a finger is down or the page is hidden. Everything made here is prefixed "tap-" and
 * removed; the options it sets are restored.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Stock\FixtureStockSource;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t118_english( int $id, string $type ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/**
 * Run the browser script on a plan, in one engine.
 *
 * @return array<string,mixed>
 */
function t118_browser( array $plan ): array {
	$file = wp_tempnam( 't118-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/phone-taps.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't118-phone-taps', 'K-5, K-6 (spec 066): on a phone every control acts on the first tap, the drawers move in and out, and the sliders hold under a finger' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'tap-' . strtolower( wp_generate_password( 5, false, false ) );
		do_action( 'wpml_switch_language', 'en' );

		// The cart asks the stock source (specs/062): the development stand-in answers for this run.
		$fixture_was = get_option( FixtureStockSource::OPTION );
		$s->on_finish(
			static function () use ( $fixture_was ): void {
				false === $fixture_was ? delete_option( FixtureStockSource::OPTION ) : update_option( FixtureStockSource::OPTION, $fixture_was );
			}
		);
		update_option( FixtureStockSource::OPTION, 'yes' );

		// ---- The products -------------------------------------------------------------------------------------------
		$simple = $s->simple_product( $tag . ' simple', '450', null );
		$simple->set_manage_stock( true );
		$simple->set_stock_quantity( 20 );
		$simple->save();
		t118_english( $simple->get_id(), 'product' );

		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Flavour' );
		$attr->set_options( array( 'Vanilla', 'Unflavoured' ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$var = new \WC_Product_Variable();
		$var->set_name( $tag . ' flavours' );
		$var->set_status( 'publish' );
		$var->set_attributes( array( $attr ) );
		$var->save();
		t118_english( $var->get_id(), 'product' );
		$s->track_post( $var->get_id() );
		foreach ( array( 'Vanilla', 'Unflavoured' ) as $flavour ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var->get_id() );
			$v->set_attributes( array( 'flavour' => $flavour ) );
			$v->set_regular_price( '600' );
			$v->set_manage_stock( true );
			$v->set_stock_quantity( 9 );
			$v->save();
			t118_english( $v->get_id(), 'product_variation' );
			$s->track_post( $v->get_id() );
		}
		\WC_Product_Variable::sync( $var->get_id() );

		// ---- The page, in English and in Arabic -------------------------------------------------------------------
		$message = static fn ( string $words ): array => array( 'text' => $words, 'label' => '', 'url' => '' );
		$blocks  = array(
			array(
				'blockName'    => 'mizzey/announcement-strip',
				'attrs'        => array(
					'variant'  => 'slider',
					'messages' => array(
						'en' => array( $message( "{$tag} first" ), $message( "{$tag} second" ), $message( "{$tag} third" ) ),
						'ar' => array( $message( "{$tag} الأولى" ), $message( "{$tag} الثانية" ), $message( "{$tag} الثالثة" ) ),
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'blockName'    => 'mizzey/hero',
				'attrs'        => array(
					'variant' => 'slider',
					'slides'  => array(
						array( 'imageId' => 0, 'imageAlt' => '', 'tone' => 'light', 'title' => "{$tag} banner one", 'line' => '', 'ctaLabel' => '', 'ctaUrl' => '' ),
						array( 'imageId' => 0, 'imageAlt' => '', 'tone' => 'light', 'title' => "{$tag} banner two", 'line' => '', 'ctaLabel' => '', 'ctaUrl' => '' ),
					),
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array(
				'blockName'    => 'mizzey/product-rail',
				'attrs'        => array( 'products' => array( $var->get_id(), $simple->get_id() ), 'heading' => $tag . ' rail' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);
		$content = serialize_blocks( $blocks );
		$admin   = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		$en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' taps', 'post_content' => wp_slash( $content ) ) );
		do_action( 'wpml_switch_language', 'ar' );
		$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' لمسات', 'post_content' => wp_slash( $content ) ) );
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
			'tag'     => $tag,
			'en'      => get_permalink( $en ),
			'ar'      => (string) apply_filters( 'wpml_permalink', get_permalink( $ar ), 'ar' ),
			'product' => get_permalink( $simple->get_id() ),
			'simple'  => $simple->get_id(),
			'var'     => $var->get_id(),
		);
		$answer = wp_remote_get( $plan['en'], array( 'timeout' => 60 ) );
		$page   = (string) wp_remote_retrieve_body( $answer );
		storefront_refuse_shell( $plan['en'], (int) wp_remote_retrieve_response_code( $answer ), $page );
		$check(
			3 === substr_count( $page, 'mizzey-announcement__message' ) && str_contains( $page, 'mizzey-hero--slider' ) && str_contains( $page, 'data-product-id="' . $var->get_id() . '"' ),
			'setup: the page carries the strip slider, the hero slider and the rail (' . substr_count( $page, 'mizzey-announcement__message' ) . ' messages, ' . strlen( $page ) . ' bytes)'
		);
		foreach ( array( 'chromium', 'webkit' ) as $engine ) {
			$result = t118_browser( $plan + array( 'engine' => $engine ) );
			if ( ! empty( $result['skipped'] ) ) {
				$s->note( "      {$engine}: not run, " . $result['skipped'] );
				$check( 'webkit' === $engine, "{$engine}: the browser is installed" );
				continue;
			}
			$check( empty( $result['error'] ), "{$engine}: the browser ran" . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
			foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
				$check( true === $row['ok'], "{$engine}: " . (string) $row['what'] );
			}
			$check( count( (array) ( $result['checks'] ?? array() ) ) >= 20, "{$engine}: the browser made every check (" . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		}
		wp_cache_delete( 'alloptions', 'options' );
		$check( 'yes' === get_option( FixtureStockSource::OPTION ), 'setup: the stock source stand-in stayed on through the browser runs' );

		return $ok;
	}
);

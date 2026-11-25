<?php
/**
 * t120, spec 066: prices display as the boards draw them (owner ruling of 25 November 2026; AC-5, AC-11, NFR-04b).
 *
 * English: "EGP", a non-breaking space, the amount with its thousands separator, and no ".00" on a whole pound
 * ("EGP 5,099"); piasters stay when a price has them ("EGP 99.50", never "EGP 99.5"). Arabic, as C1-Home-Phone-Arabic
 * and C1-Arabic-Stage-2 draw it: the amount in Western digits with the same separator, a non-breaking space, then
 * "ج.م" ("5,099 ج.م"). Display only: the stored price, the decimals and the totals do not change.
 *
 * In process: wc_price() in each language, the same after a staff member changes the position and the separators in
 * the settings, an Arabic email's switched locale, and the Store API's currency fields and amounts. In a real browser
 * (mizzey-site/tests/browser/price-display.cjs, Chromium and WebKit): a whole-pound price, a price with piasters and a
 * sale price on the card and on the product page, and the cart total of the cart block for a whole-pound cart and a
 * cart with piasters, each in English and in Arabic, with the Store API's cart response read in the same session.
 *
 * Every record is named "pf-" and removed when the scenario ends.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

const T111_NBSP = "\u{00A0}";
const T111_AR   = "\u{062C}.\u{0645}";

/** A price as text, the way a visitor reads it. */
function t120_text( string $html ): string {
	return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
}

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t120_english( int $id, string $type ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t120_browser( array $plan ): array {
	$file = wp_tempnam( 't120-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/price-display.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/**
 * The Store API's answer for a product, in the current language.
 *
 * @return array<string,mixed>
 */
function t120_store_product( int $id ): array {
	$response = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/products/' . $id ) );
	return (array) rest_get_server()->response_to_data( $response, false );
}

run(
	new Scenario( 't120-price-display', 'Owner ruling 25 Nov 2026, AC-5, AC-11, NFR-04b (spec 066): prices display as the boards draw them, in English and Arabic, on the card, the product page and the cart' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'pf-' . strtolower( wp_generate_password( 5, false, false ) );
		$sp  = T111_NBSP;
		$ar  = T111_AR;
		do_action( 'wpml_switch_language', 'en' );

		// ---- wc_price(), in each language ---------------------------------------------------------------------------
		$en_whole = t120_text( wc_price( 5099 ) );
		$en_pt    = t120_text( wc_price( 99.5 ) );
		$en_big   = t120_text( wc_price( 1234567.05 ) );
		$check( "EGP{$sp}5,099" === $en_whole, "ruling: English whole pounds read EGP, a non-breaking space, 5,099, with no .00 ({$en_whole})" );
		$check( "EGP{$sp}99.50" === $en_pt, "ruling: English piasters stay, two places (EGP 99.50, never 99.5) ({$en_pt})" );
		$check( "EGP{$sp}1,234,567.05" === $en_big, "ruling: every thousands group is separated ({$en_big})" );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_whole = t120_text( wc_price( 5099 ) );
		$ar_pt    = t120_text( wc_price( 99.5 ) );
		do_action( 'wpml_switch_language', 'en' );
		$check( "5,099{$sp}{$ar}" === $ar_whole, "ruling: Arabic as the Arabic boards draw it, 5,099, a non-breaking space, then the Arabic pound ({$ar_whole})" );
		$check( "99.50{$sp}{$ar}" === $ar_pt, "ruling: Arabic piasters stay, two places, Western digits ({$ar_pt})" );

		// ---- A staff member changes the settings: the display holds -------------------------------------------------
		$staff = array(
			'option_woocommerce_currency_pos'       => static fn () => 'left',
			'option_woocommerce_price_thousand_sep' => static fn () => '.',
			'option_woocommerce_price_decimal_sep'  => static fn () => ',',
		);
		foreach ( $staff as $hook => $fn ) {
			add_filter( $hook, $fn, 5 );
		}
		$staff_en = t120_text( wc_price( 5099.5 ) );
		do_action( 'wpml_switch_language', 'ar' );
		$staff_ar = t120_text( wc_price( 5099.5 ) );
		do_action( 'wpml_switch_language', 'en' );
		foreach ( $staff as $hook => $fn ) {
			remove_filter( $hook, $fn, 5 );
		}
		$check( "EGP{$sp}5,099.50" === $staff_en && "5,099.50{$sp}{$ar}" === $staff_ar, "ruling: a staff change to the position or the separators in the settings does not change what customers read ({$staff_en} | {$staff_ar})" );

		// ---- An Arabic customer email or invoice switches to the order's locale -------------------------------------
		switch_to_locale( 'ar' );
		$email_whole = t120_text( wc_price( 10 ) );
		$email_pt    = t120_text( wc_price( 10.25 ) );
		restore_previous_locale();
		$check( "10{$sp}{$ar}" === $email_whole && "10.25{$sp}{$ar}" === $email_pt, "ruling: an Arabic email or invoice writes the Arabic form, whole and with piasters ({$email_whole} | {$email_pt})" );

		// ---- The fixture: a whole-pound price, a price with piasters, a sale ----------------------------------------
		$make = function ( string $name, string $regular, ?string $sale = null ) use ( $s ): \WC_Product_Simple {
			$p = $s->simple_product( $name, $regular, null );
			if ( null !== $sale ) {
				$p->set_sale_price( $sale );
				$p->save();
			}
			t120_english( $p->get_id(), 'product' );
			return $p;
		};
		$whole = $make( $tag . ' whole pounds', '5099' );
		$pt    = $make( $tag . ' with piasters', '99.50' );
		$sale  = $make( $tag . ' on sale', '6000', '5099' );
		$ar_ids = array();
		foreach ( array( 'whole' => $whole, 'pt' => $pt, 'sale' => $sale ) as $key => $p ) {
			$ar_ids[ $key ] = catalogue_duplicate( $s, $p->get_id() );
		}
		$check( ! in_array( 0, $ar_ids, true ), 'setup: each fixture product has its Arabic record (' . implode( ', ', $ar_ids ) . ')' );

		$sale_html = t120_text( wc_get_product( $sale->get_id() )->get_price_html() );
		$check( str_contains( $sale_html, "EGP{$sp}6,000" ) && str_contains( $sale_html, "EGP{$sp}5,099" ) && ! str_contains( $sale_html, '.00' ), "ruling: a sale price and its struck original both read the ruled form ({$sale_html})" );

		// ---- The Store API: the currency fields change, the amounts do not ------------------------------------------
		$en_api = t120_store_product( $pt->get_id() );
		$en_cur = (array) ( $en_api['prices'] ?? array() );
		$check(
			"EGP{$sp}" === ( $en_cur['currency_prefix'] ?? null ) && '' === ( $en_cur['currency_suffix'] ?? null ) && ',' === ( $en_cur['currency_thousand_separator'] ?? null ) && '.' === ( $en_cur['currency_decimal_separator'] ?? null ),
			'ruling: the Store API gives the blocks the English form: prefix "EGP" and a non-breaking space, no suffix, "," and "." (' . wp_json_encode( array_intersect_key( $en_cur, array_flip( array( 'currency_prefix', 'currency_suffix', 'currency_thousand_separator', 'currency_decimal_separator' ) ) ), JSON_UNESCAPED_UNICODE ) . ')'
		);
		$check( 2 === ( $en_cur['currency_minor_unit'] ?? null ) && '9950' === ( $en_cur['price'] ?? null ), 'ruling, display only: the Store API amount and its minor unit are unchanged (9950 at 2 places: ' . ( $en_cur['price'] ?? '-' ) . ' at ' . ( $en_cur['currency_minor_unit'] ?? '-' ) . ')' );
		// The Arabic answer is read by the browser, from the Arabic cart: WPML sets a REST request's language from the request.
		$check( '99.50' === (string) get_post_meta( $pt->get_id(), '_price', true ) && 2 === wc_get_price_decimals(), 'ruling, display only: the stored price and the store\'s decimals are unchanged (' . get_post_meta( $pt->get_id(), '_price', true ) . ', ' . wc_get_price_decimals() . ')' );

		// ---- The pages the browser reads: the three as a rail, in English and in Arabic -----------------------------
		$content = sprintf( '<!-- wp:mizzey/product-rail %s /-->', wp_json_encode( array( 'products' => array( $whole->get_id(), $pt->get_id(), $sale->get_id() ), 'heading' => $tag . ' rail' ) ) );
		$admin   = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		$en_page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' prices', 'post_content' => $content ) );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $tag . ' أسعار', 'post_content' => $content ) );
		do_action( 'wpml_switch_language', 'en' );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en_page, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar_page, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$s->on_finish(
			static function () use ( $en_page, $ar_page ): void {
				wp_delete_post( $ar_page, true );
				wp_delete_post( $en_page, true );
			}
		);
		wp_set_current_user( 0 );

		$cart_id = wc_get_page_id( 'cart' );
		$ar_link = static fn ( int $id ): string => (string) apply_filters( 'wpml_permalink', get_permalink( $id ), 'ar' );
		$plan    = array(
			'tag'   => $tag,
			'store' => rest_url( 'wc/store/v1/' ),
			'ids'   => array(
				'en' => array( 'whole' => $whole->get_id(), 'pt' => $pt->get_id(), 'sale' => $sale->get_id() ),
				'ar' => $ar_ids,
			),
			'en'    => array(
				'cards'   => get_permalink( $en_page ),
				'whole'   => get_permalink( $whole->get_id() ),
				'pt'      => get_permalink( $pt->get_id() ),
				'sale'    => get_permalink( $sale->get_id() ),
				'cart'    => get_permalink( $cart_id ),
			),
			'ar'    => array(
				'cards'   => $ar_link( $en_page ),
				'whole'   => $ar_link( $whole->get_id() ),
				'pt'      => $ar_link( $pt->get_id() ),
				'sale'    => $ar_link( $sale->get_id() ),
				'cart'    => $ar_link( $cart_id ),
			),
			'shots' => get_temp_dir(),
		);
		$result = t120_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 30, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $result['shots'] ?? array() ) as $shot ) {
			$s->note( '      screenshot: ' . $shot );
		}

		return $ok;
	}
);

<?php
/**
 * t108, spec 066 slice 2: the designed header and its menus in a real browser (AC-1, AC-14; NAV-01 to NAV-10, SSC-07,
 * NFR-11; the signed-in switcher is D-31, a gift).
 *
 * The runtime is prepared here and the browser part is mizzey-site/tests/browser/site-header.cjs:
 *
 * - categories in both languages, two levels and a third under one of them, each with a product so none is empty, and
 *   a photo on one (an image already in the media library; nothing is uploaded);
 * - the header's navigation menu in each language, made as staff make it in the Site Editor: an offer link (the
 *   "Offer" style), a category link and a plain link;
 * - a page with WooCommerce's own add to cart button (the products shortcode) for the count after an add;
 * - a customer account with a first name, signed in by cookie.
 *
 * The browser checks the entry points, both menus, the three phone forms, the count after an add without a reload,
 * the language switch keeping the page and the cart, focus moving in, staying in and returning, and the signed-in
 * state. Then a category is taken out of the main menu in the admin, and the browser reopens both menus after a
 * reload. Everything made here is prefixed "s2-" and removed; the options it sets are restored.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\HeaderData;

require_once __DIR__ . '/_bootstrap.php';

/** A category's own term_taxonomy_id (as t94: get_term() would give the term in the current language). */
function t108_ttid( int $term_id ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'product_cat'", $term_id ) );
}

/** Run something in a language and come back. */
function t108_in( string $lang, callable $fn ) {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$out = $fn();
	do_action( 'wpml_switch_language', $current );
	return $out;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t108_browser( array $plan ): array {
	$file = wp_tempnam( 't108-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/site-header.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/**
 * Run the boards' checks (mizzey-site/tests/browser/_board.cjs) on a plan: { part, urls }.
 *
 * @return array<string,mixed>
 */
function t108_board( array $plan ): array {
	$file = wp_tempnam( 't108-board' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out = array();
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/_board.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/** A navigation menu's markup, as the Site Editor saves it. */
function t108_menu( array $links ): string {
	$out = '';
	foreach ( $links as $link ) {
		$out .= '<!-- wp:navigation-link ' . wp_json_encode( $link, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
	return $out;
}

run(
	new Scenario( 't108-site-header', 'AC-1, AC-14 (slice 2): the designed header, the mega menu and the phone menu, in both languages at 1440 and 390' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 's2-' . strtolower( wp_generate_password( 5, false, false ) );

		// The cart asks the stock source (specs/062): the development stand-in answers for this run.
		$fixture = get_option( \MizzeySite\Stock\FixtureStockSource::OPTION );
		$ajax    = get_option( 'woocommerce_enable_ajax_add_to_cart' );
		$s->on_finish(
			static function () use ( $fixture, $ajax ): void {
				update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, $fixture );
				update_option( 'woocommerce_enable_ajax_add_to_cart', $ajax );
			}
		);
		update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, 'yes' );
		update_option( 'woocommerce_enable_ajax_add_to_cart', 'yes' );

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
			$trid = apply_filters( 'wpml_element_trid', null, t108_ttid( $id ), 'tax_product_cat' );
			do_action( 'wpml_switch_language', 'ar' );
			$arm = wp_insert_term( $ar, 'product_cat', array( 'parent' => $ar_parent ) );
			do_action( 'wpml_switch_language', 'en' );
			$ar_id = is_wp_error( $arm ) ? 0 : (int) $arm['term_id'];
			do_action( 'wpml_set_element_language_details', array( 'element_id' => t108_ttid( $ar_id ), 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
			$terms[] = $id;
			$terms[] = $ar_id;
			return array( $id, $ar_id );
		};
		list( $beauty, $beauty_ar ) = $category( "{$tag} Beauty", "{$tag} الجمال" );
		list( $skin, $skin_ar )     = $category( "{$tag} Skin care", "{$tag} العناية بالبشرة", $beauty, $beauty_ar );
		list( $hair, $hair_ar )     = $category( "{$tag} Hair care", "{$tag} العناية بالشعر", $beauty, $beauty_ar );
		list( $home, $home_ar )     = $category( "{$tag} Home", "{$tag} المنزل" );
		list( $lamps, $lamps_ar )   = $category( "{$tag} Lamps", "{$tag} مصابيح", $home, $home_ar );
		list( $desk, $desk_ar )     = $category( "{$tag} Desk lamps", "{$tag} مصابيح مكتب", $lamps, $lamps_ar );
		update_term_meta( $beauty, 'order', 1 );
		update_term_meta( $home, 'order', 2 );
		update_term_meta( $skin, 'order', 1 );
		update_term_meta( $hair, 'order', 2 );
		// A photo on Beauty: WooCommerce's own placeholder image, already in the media library (nothing is uploaded).
		$photo = (int) get_option( 'woocommerce_placeholder_image', 0 );
		if ( $photo > 0 ) {
			update_term_meta( $beauty, 'thumbnail_id', $photo );
		}
		$products = array();
		foreach ( array( array( "{$tag} Serum", $skin ), array( "{$tag} Shampoo", $hair ), array( "{$tag} Desk lamp", $desk ) ) as list( $name, $cat ) ) {
			$product = $s->simple_product( $name, '120.00', null );
			wp_set_object_terms( $product->get_id(), array( $cat ), 'product_cat' );
			$sitepress->make_duplicate( $product->get_id(), 'ar' );
			$dup = (int) apply_filters( 'wpml_object_id', $product->get_id(), 'product', false, 'ar' );
			if ( $dup > 0 ) {
				$s->track_post( $dup );
			}
			$products[] = $product;
		}
		foreach ( array( $beauty, $skin, $hair, $home, $lamps, $desk, $beauty_ar, $skin_ar, $hair_ar, $home_ar, $lamps_ar, $desk_ar ) as $t ) {
			wp_update_term_count_now( array( t108_ttid( $t ) ), 'product_cat' );
		}
		wp_cache_flush();

		// ---- The header's navigation menu in each language --------------------------------------------------------
		$menus = array();
		$s->on_finish(
			static function () use ( &$menus ): void {
				foreach ( $menus as $id ) {
					wp_delete_post( $id, true );
				}
			}
		);
		$link = static fn ( string $lang, int $term ): string => (string) t108_in( $lang, static fn () => get_term_link( $term, 'product_cat' ) );
		do_action( 'wpml_switch_language', 'en' );
		$menus[] = $menu_en = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => "{$tag} header",
				// The header's menu is the one named for it (HeaderMenu::SLUG), never just the newest menu.
				'post_name'    => \MizzeySite\Storefront\HeaderMenu::SLUG,
				'post_content' => t108_menu(
					array(
						array( 'label' => "{$tag} Offers", 'url' => home_url( '/?s=offer' ), 'kind' => 'custom', 'className' => 'is-style-offer' ),
						array( 'label' => "{$tag} Beauty", 'url' => $link( 'en', $beauty ), 'kind' => 'taxonomy', 'type' => 'product_cat', 'id' => $beauty ),
						array( 'label' => "{$tag} Brands", 'url' => home_url( '/brands/' ), 'kind' => 'custom' ),
					)
				),
			)
		);
		do_action( 'wpml_switch_language', 'ar' );
		$menus[] = $menu_ar = (int) wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => "{$tag} header ar",
				'post_content' => t108_menu(
					array(
						array( 'label' => "{$tag} العروض", 'url' => home_url( '/ar/?s=offer' ), 'kind' => 'custom', 'className' => 'is-style-offer' ),
						array( 'label' => "{$tag} الجمال", 'url' => $link( 'ar', $beauty_ar ), 'kind' => 'taxonomy', 'type' => 'product_cat', 'id' => $beauty_ar ),
						array( 'label' => "{$tag} الماركات", 'url' => home_url( '/ar/brands/' ), 'kind' => 'custom' ),
					)
				),
			)
		);
		do_action( 'wpml_switch_language', 'en' );
		$trid = apply_filters( 'wpml_element_trid', null, $menu_en, 'post_wp_navigation' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $menu_ar, 'element_type' => 'post_wp_navigation', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );

		$check( \MizzeySite\Storefront\HeaderMenu::SLUG === get_post_field( 'post_name', $menu_en ), 'the menu staff name for the header carries that name (no other menu on this runtime holds it)' );

		// ---- A footer menu published after the header's, in both languages: the header must not take it -----------
		$footer = static function ( string $lang, string $label ) use ( $tag ): int {
			do_action( 'wpml_switch_language', $lang );
			$id = (int) wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => "{$tag} footer {$lang}", 'post_content' => t108_menu( array( array( 'label' => $label, 'url' => home_url( '/about/' ), 'kind' => 'custom' ) ) ) ) );
			do_action( 'wpml_switch_language', 'en' );
			return $id;
		};
		$menus[] = $footer_en = $footer( 'en', "{$tag} Footer link" );
		$menus[] = $footer_ar = $footer( 'ar', "{$tag} رابط التذييل" );
		$ftrid = apply_filters( 'wpml_element_trid', null, $footer_en, 'post_wp_navigation' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $footer_ar, 'element_type' => 'post_wp_navigation', 'trid' => $ftrid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		foreach ( array( 'en' => array( "{$tag} Offers", "{$tag} Beauty", "{$tag} Brands" ), 'ar' => array( "{$tag} العروض", "{$tag} الجمال", "{$tag} الماركات" ) ) as $lang => $labels ) {
			$links = array_column( (array) t108_in( $lang, static fn () => \MizzeySite\Storefront\HeaderMenu::links() ), 'label' );
			$check( $labels === $links, "SSC-07 [{$lang}] a footer menu published after the header's leaves the header's links unchanged (" . wp_json_encode( $links, JSON_UNESCAPED_UNICODE ) . ')' );
		}

		// ---- A page with WooCommerce's own add to cart button ----------------------------------------------------
		$page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} add", 'post_content' => '[products ids="' . $products[0]->get_id() . '" columns="1"]' . "
<!-- wp:mizzey/product-card {\"productId\":" . $products[1]->get_id() . '} /-->' ) );
		$s->on_finish( static function () use ( $page ): void { wp_delete_post( $page, true ); } );

		// ---- A customer with a first name ------------------------------------------------------------------------
		$user = wp_insert_user( array( 'user_login' => "{$tag}-customer", 'user_email' => "{$tag}-customer@example.com", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer', 'first_name' => 'Samira' ) );
		if ( is_wp_error( $user ) ) {
			throw new \RuntimeException( 'The customer could not be made: ' . $user->get_error_message() );
		}
		$s->track_user( (int) $user );
		$expire  = time() + 2 * HOUR_IN_SECONDS;
		$cookies = array(
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user, $expire, 'logged_in' ) ),
			array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user, $expire, 'auth' ) ),
		);

		// ---- What each language should show ----------------------------------------------------------------------
		$expected = static function ( string $lang ) use ( $tag ): array {
			$tree = (array) t108_in( $lang, static fn () => HeaderData::categories() );
			$top  = array_values( array_filter( $tree, static fn ( $n ) => str_contains( (string) $n['name'], $tag ) ) );
			$parent = $top[0] ?? array( 'name' => '', 'url' => '', 'children' => array() );
			return array(
				'top'       => array_column( $top, 'name' ),
				'parent'    => $parent['name'],
				'parentUrl' => $parent['url'],
				'children'  => array_column( (array) $parent['children'], 'name' ),
				'photo'     => '' !== (string) ( $parent['image'] ?? '' ),
				'offer'     => 'ar' === $lang ? "{$tag} العروض" : "{$tag} Offers",
				'row'       => 'ar' === $lang ? array( "{$tag} العروض", "{$tag} الجمال", "{$tag} الماركات" ) : array( "{$tag} Offers", "{$tag} Beauty", "{$tag} Brands" ),
				'after'     => array_values( array_diff( array_column( $top, 'name' ), array( 'ar' === $lang ? "{$tag} المنزل" : "{$tag} Home" ) ) ),
			);
		};
		$language = static function ( string $lang ) use ( $link, $beauty, $beauty_ar, $products, $expected ): array {
			$data = (array) t108_in( $lang, static fn () => HeaderData::header() );
			return array(
				'home'     => (string) t108_in( $lang, static fn () => apply_filters( 'wpml_home_url', home_url( '/' ) ) ),
				'search'   => (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang ),
				'account'  => (string) $data['account']['url'],
				'wishlist' => (string) $data['wishlist']['url'],
				'cart'     => (string) $data['cart']['url'],
				'category' => $link( $lang, 'ar' === $lang ? $beauty_ar : $beauty ),
				'product'  => (string) get_permalink( (int) apply_filters( 'wpml_object_id', $products[0]->get_id(), 'product', true, $lang ) ),
				'other'    => 'ar' === $lang ? array( 'code' => 'en', 'name' => 'English' ) : array( 'code' => 'ar', 'name' => 'العربية' ),
				'expected' => $expected( $lang ),
			);
		};
		$en = $language( 'en' );
		$ar = $language( 'ar' );
		$s->note( '      expected: ' . wp_json_encode( array( 'en' => $en['expected'], 'ar' => $ar['expected'] ), JSON_UNESCAPED_UNICODE ) );
		$check( array( "{$tag} Beauty", "{$tag} Home" ) === $en['expected']['top'] && array( "{$tag} Skin care", "{$tag} Hair care" ) === $en['expected']['children'] && $en['expected']['photo'] && $ar['expected']['photo'], 'the fixture: two categories in order, the first with two children and a photo (specs/054 serves them)' );

		$plan = array(
			'part'        => 'main',
			'tag'         => $tag,
			'en'          => $en,
			'ar'          => $ar,
			'addPage'     => (string) get_permalink( $page ),
			'addSelector' => '.add_to_cart_button.ajax_add_to_cart[data-product_id="' . $products[0]->get_id() . '"]',
			'cardSelector' => '.mizzey-card[data-product-id="' . $products[1]->get_id() . '"] .mizzey-card__add',
			'customer'    => array( 'cookies' => $cookies, 'first' => 'Samira' ),
			'shots'       => get_temp_dir(),
		);
		$result = t108_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 60, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );

		// ---- SSC-07: the admin takes Home out of the main menu; both menus follow after a reload --------------------
		update_term_meta( $home, HeaderData::HIDDEN, 'yes' );
		wp_cache_flush();
		$after = t108_browser( array( 'part' => 'after' ) + $plan );
		$check( empty( $after['error'] ), 'the browser ran after the change' . ( empty( $after['error'] ) ? '' : ': ' . substr( (string) $after['error'], 0, 1500 ) ) );
		foreach ( (array) ( $after['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( 4 === count( (array) ( $after['checks'] ?? array() ) ), 'the browser made every check after the change' );

		// ---- AC-14: no sideways scroll on a phone, with no logo file and a long store name (as on staging) ---------
		$logo = array( 'mod' => get_theme_mod( 'custom_logo' ), 'option' => get_option( 'site_logo' ), 'name' => get_option( 'blogname' ) );
		$s->on_finish(
			static function () use ( $logo ): void {
				false === $logo['mod'] ? remove_theme_mod( 'custom_logo' ) : set_theme_mod( 'custom_logo', $logo['mod'] );
				false === $logo['option'] ? delete_option( 'site_logo' ) : update_option( 'site_logo', $logo['option'] );
				update_option( 'blogname', $logo['name'] );
			}
		);
		remove_theme_mod( 'custom_logo' );
		delete_option( 'site_logo' );
		update_option( 'blogname', "{$tag} Mizzey (staging) demonstration store of the long name" );
		$wide = t108_browser( array( 'part' => 'overflow' ) + $plan );
		$check( empty( $wide['error'] ), 'the browser ran the phone widths' . ( empty( $wide['error'] ) ? '' : ': ' . substr( (string) $wide['error'], 0, 1500 ) ) );
		foreach ( (array) ( $wide['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( 32 === count( (array) ( $wide['checks'] ?? array() ) ), 'the browser made every phone-width check (' . count( (array) ( $wide['checks'] ?? array() ) ) . ')' );

		// ---- The boards' numbers (staging review, 25 November 2026) ---------------------------------------------------
		// The header's menu as C1-Home-Desktop draws it: six categories, then Offers and Brands, which fit the row at
		// 1440 and overflow into "More" at 1100. Checked in Chromium and WebKit at 390, 1100, 1280 and 1440, in both
		// languages: the search field, the tab bar, the tooltips, the mega menu, "More", the sticky header, the press on
		// touch and the page change (tests/browser/_board.cjs).
		// Its Beauty is the run's Beauty category, so the row's mark of the page's section shows (C1-Category-Desktop).
		$board_menu = static fn ( string $lang ): string => t108_menu(
			array_map(
				static fn ( string $label ): array => ( in_array( $label, array( 'Beauty', 'الجمال' ), true )
					? array( 'label' => $label, 'url' => $link( $lang, 'ar' === $lang ? $beauty_ar : $beauty ), 'kind' => 'taxonomy', 'type' => 'product_cat', 'id' => 'ar' === $lang ? $beauty_ar : $beauty )
					: array( 'label' => $label, 'url' => home_url( '/?s=' . rawurlencode( $label ) ), 'kind' => 'custom' ) ) + ( in_array( $label, array( 'Offers', 'العروض' ), true ) ? array( 'className' => 'is-style-offer' ) : array() ),
				'ar' === $lang
					? array( 'الجمال', 'الصحة والمنزل', 'الفيتامينات والمكملات', 'الرياضة والهواء الطلق', 'المنزل والمطبخ', 'البقالة', 'العروض', 'الماركات' )
					: array( 'Beauty', 'Health & Household', 'Vitamins & Supplements', 'Sport & Outdoor', 'Home & Kitchen', 'Grocery', 'Offers', 'Brands' )
			)
		);
		wp_update_post( array( 'ID' => $menu_en, 'post_content' => wp_slash( $board_menu( 'en' ) ) ) );
		wp_update_post( array( 'ID' => $menu_ar, 'post_content' => wp_slash( $board_menu( 'ar' ) ) ) );
		wp_cache_flush();
		// One address per page type (the owner's report, 26 November 2026): the header and footer are the shared ones.
		$pages = static fn ( string $lang ): array => (array) t108_in(
			$lang,
			static function () use ( $lang, $products, $beauty, $beauty_ar, $page, $link ): array {
				$wc   = static fn ( string $name ): string => (string) get_permalink( (int) apply_filters( 'wpml_object_id', wc_get_page_id( $name ), 'page', true, $lang ) );
				$home  = (string) apply_filters( 'wpml_home_url', home_url( '/' ) );
				$pages = array(
					'product'  => (string) get_permalink( (int) apply_filters( 'wpml_object_id', $products[0]->get_id(), 'product', true, $lang ) ),
					'category' => $link( $lang, 'ar' === $lang ? $beauty_ar : $beauty ),
					'shop'     => $wc( 'shop' ),
					'cart'     => $wc( 'cart' ),
					'checkout' => $wc( 'checkout' ),
					'account'  => $wc( 'myaccount' ),
					// The run's page exists in English only: an Arabic visit to it would be the English page.
					'page'     => 'ar' === $lang ? '' : (string) get_permalink( $page ),
					'search'   => add_query_arg( array( 's' => 'serum', 'post_type' => 'product' ), $home ),
					'404'      => trailingslashit( $home ) . 's2-no-such-page/',
				);
				return array_filter( $pages );
			}
		);
		$numbers = t108_board( array( 'part' => 'header', 'urls' => array( 'en' => $en['home'], 'ar' => $ar['home'], 'consent' => array( 'name' => 'mizzey_consent', 'value' => 'denied' ), 'pages' => array( 'en' => $pages( 'en' ), 'ar' => $pages( 'ar' ) ) ) ) );
		$check( empty( $numbers['error'] ), 'board: the browser ran' . ( empty( $numbers['error'] ) ? '' : ': ' . substr( (string) $numbers['error'], 0, 1500 ) ) );
		foreach ( (array) ( $numbers['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], 'board: ' . (string) $row['what'] );
		}
		$check( count( (array) ( $numbers['checks'] ?? array() ) ) >= 200, 'board: the browser made every check (' . count( (array) ( $numbers['checks'] ?? array() ) ) . ')' );

		return $ok;
	}
);

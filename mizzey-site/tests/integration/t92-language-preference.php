<?php
/**
 * t92: the chosen language is kept and followed (specs/052-language-preference).
 *
 * Over HTTP, as a browser makes the requests, with the cookies it keeps.
 *
 * AC-1: with no choice made, the address decides: each language's page answers in its own language.
 * AC-2: a switcher link keeps the choice on the device and lands on the same page in that language.
 * AC-3: with a choice kept, a page in the other language is answered with the same page in the chosen one (its query
 *       kept); a page with no translation is served as it is; the administration, REST and JSON are never moved.
 * AC-4: the cart is unchanged when the language is switched: the same items and quantities, named in the new language.
 * AC-5 (D-31): a signed-in customer's choice is kept on the account: it follows them on a new device, wins over a
 *       device's older choice, and a link made elsewhere cannot change it.
 * AC-6 (D-31): an order placed in Arabic, opened by a customer who chose English, opens in English with its items in
 *       English.
 * AC-7: a page served from a cache does the same in the browser; the cart setting cannot drift to emptying the cart.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\I18n\LanguagePreference;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';
require_once __DIR__ . '/_storefront.php';

/** A storefront page as a browser asks for it, redirects not followed. */
function t92_page( string $url, array $cookies = array() ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 60, 'redirection' => 0, 'cookies' => $cookies ) );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'body' => '', 'location' => '', 'cookies' => $cookies );
	}
	$answer = array(
		'status'   => (int) wp_remote_retrieve_response_code( $r ),
		'body'     => (string) wp_remote_retrieve_body( $r ),
		'location' => (string) wp_remote_retrieve_header( $r, 'location' ),
		'cookies'  => sign_in_jar( array_merge( $cookies, wp_remote_retrieve_cookies( $r ) ) ),
	);
	if ( 200 === $answer['status'] ) {
		storefront_refuse_shell( $url, $answer['status'], $answer['body'] );
	}
	return $answer;
}

/** A device that chose a language: its cookie. */
function t92_device( string $code ): array {
	return array( new \WP_Http_Cookie( array( 'name' => LanguagePreference::COOKIE, 'value' => $code ) ) );
}

/** The value a cookie list holds for a name, or null. */
function t92_cookie( array $cookies, string $name ): ?string {
	foreach ( array_reverse( $cookies ) as $cookie ) {
		if ( $cookie->name === $name ) {
			return (string) $cookie->value;
		}
	}
	return null;
}

/** An address without its trailing slash, for comparing redirect targets. */
function t92_same( string $a, string $b ): bool {
	return untrailingslashit( $a ) === untrailingslashit( $b );
}

run(
	new Scenario( 't92-language-preference', 'NAV-02 (and D-31): the chosen language is kept and followed, the cart unchanged' ),
	function ( Scenario $s ): bool {
		// Adding to the cart asks the stock source (ERP-03, specs/062): the stand-in answers for this run.
		$fixture_before = get_option( \MizzeySite\Stock\FixtureStockSource::OPTION );
		$s->on_finish( static function () use ( $fixture_before ) { update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, $fixture_before ); } );
		update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, 'yes' );
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T92' . wp_rand( 1000, 9999 );

		// A product in both languages, and one in English only.
		do_action( 'wpml_switch_language', 'en' );
		$lamp = $s->simple_product( "{$tag} Desk Lamp", '150.00', null );
		$sitepress->make_duplicate( $lamp->get_id(), 'ar' );
		$lamp_ar = (int) apply_filters( 'wpml_object_id', $lamp->get_id(), 'product', false, 'ar' );
		if ( $lamp_ar > 0 ) {
			$s->track_post( $lamp_ar );
			wp_update_post( array( 'ID' => $lamp_ar, 'post_title' => "مصباح مكتب {$tag}", 'post_name' => strtolower( "{$tag}-misbah" ) ) );
			delete_post_meta( $lamp_ar, '_icl_lang_duplicate_of' ); // Its own translation, not a duplicate kept in step.
		}
		$solo    = $s->simple_product( "{$tag} English Only", '80.00', null );
		$en_url  = (string) get_permalink( $lamp->get_id() );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_url  = (string) get_permalink( $lamp_ar );
		do_action( 'wpml_switch_language', 'en' );
		$solo_url = (string) get_permalink( $solo->get_id() );
		wp_cache_flush();

		// ---- AC-1: no choice, the address decides -----------------------------------------------------------------
		$plain_ar = t92_page( $ar_url );
		$plain_en = t92_page( $en_url );
		$check( 200 === $plain_ar['status'] && storefront_shows( $plain_ar['body'], "مصباح مكتب {$tag}" ) && 200 === $plain_en['status'] && storefront_shows( $plain_en['body'], "{$tag} Desk Lamp" ), 'AC-1 with no choice made, each language\'s page answers in its own language (' . $plain_ar['status'] . ', ' . $plain_en['status'] . ')' );

		// ---- AC-2: a switcher link keeps the choice ------------------------------------------------------------------
		$switch = t92_page( add_query_arg( LanguagePreference::CHOICE, 'ar', $en_url ) );
		$check( 302 === $switch['status'] && t92_same( $switch['location'], $ar_url ) && 'ar' === t92_cookie( $switch['cookies'], LanguagePreference::COOKIE ), 'AC-2 a switcher link to Arabic keeps the choice on the device and lands on the same product in Arabic (' . $switch['status'] . ' ' . $switch['location'] . ')' );
		$sw_ar = (string) apply_filters( 'wpml_ls_language_url', $ar_url, array( 'code' => 'ar' ) );
		$sw_en = (string) apply_filters( 'wpml_ls_language_url', $en_url, array( 'code' => 'en' ) );
		$alts  = (array) apply_filters( 'icl_ls_languages', array( 'ar' => array( 'url' => $ar_url ) ) );
		$check( str_contains( $sw_ar, LanguagePreference::CHOICE . '=ar' ) && str_contains( $sw_en, LanguagePreference::CHOICE . '=en' ) && ! str_contains( (string) ( $alts['ar']['url'] ?? '' ), LanguagePreference::CHOICE ), 'AC-2 the language switcher\'s links carry the choice; the alternate-language (hreflang) links do not' );
		$head = (string) $plain_en['body'];
		$check( 1 === preg_match( '/hreflang="ar"[^>]*>/', $head ) && ! str_contains( $head, 'hreflang="ar" href="' . esc_url( add_query_arg( LanguagePreference::CHOICE, 'ar', $ar_url ) ) ) && 0 === preg_match( '/<link[^>]+hreflang[^>]+' . LanguagePreference::CHOICE . '/', $head ), 'AC-1 the page\'s alternate-language links are the plain addresses, as search engines need them' );

		// ---- AC-3: the choice is followed ----------------------------------------------------------------------------
		$to_ar   = t92_page( $en_url, t92_device( 'ar' ) );
		$to_en   = t92_page( $ar_url, t92_device( 'en' ) );
		$query   = t92_page( add_query_arg( 'utm_source', 'email', $en_url ), t92_device( 'ar' ) );
		$stay    = t92_page( $solo_url, t92_device( 'ar' ) );
		$check( 302 === $to_ar['status'] && t92_same( $to_ar['location'], $ar_url ) && 302 === $to_en['status'] && t92_same( $to_en['location'], $en_url ), 'AC-3 a device that chose Arabic opening the English page lands on the Arabic one, and the other way round (' . $to_ar['location'] . ' | ' . $to_en['location'] . ')' );
		$check( 302 === $query['status'] && str_contains( $query['location'], 'utm_source=email' ), 'AC-3 the page\'s query is kept (' . $query['location'] . ')' );
		$check( 200 === $stay['status'] && storefront_shows( $stay['body'], "{$tag} English Only" ), 'AC-3 a page with no Arabic translation is served as it is (' . $stay['status'] . ')' );
		$json  = sign_in_json( 'GET', rest_url( 'wc/store/v1/products/' . $lamp->get_id() ), null, array(), t92_device( 'ar' ) );
		$admin = two_factor_http( 'GET', admin_url( 'index.php' ), array(), t92_device( 'ar' ) );
		$check( 200 === $json['status'] && "{$tag} Desk Lamp" === ( $json['data']['name'] ?? '' ) && ! str_contains( $admin['location'], '/ar/' ), 'AC-3 REST and the administration are never moved by the choice (' . $json['status'] . ', ' . $admin['status'] . ' ' . $admin['location'] . ')' );

		// ---- AC-4: the cart is unchanged on a switch ----------------------------------------------------------------
		$cart  = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$nonce = sign_in_header( $cart, 'nonce' );
		$added = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp->get_id(), 'quantity' => 2 ), array( 'Nonce' => $nonce ), $cart['cookies'] );
		$jar   = sign_in_jar( $added['cookies'] );
		$moved = t92_page( add_query_arg( LanguagePreference::CHOICE, 'ar', $en_url ), $jar );
		$jar   = $moved['cookies'];
		t92_page( $ar_url, $jar ); // The Arabic page, as the browser lands on it.
		$ar_cart = sign_in_json( 'GET', home_url( '/ar/wp-json/wc/store/v1/cart' ), null, array(), $jar );
		$back    = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $ar_cart['cookies'] );
		$items   = static fn ( array $answer ): array => array_map( static fn ( $i ) => array( (string) ( $i['name'] ?? '' ), (int) ( $i['quantity'] ?? 0 ) ), (array) ( $answer['data']['items'] ?? array() ) );
		$check( 1 === count( $items( $added ) ) && array( array( "مصباح مكتب {$tag}", 2 ) ) === $items( $ar_cart ) && array( array( "{$tag} Desk Lamp", 2 ) ) === $items( $back ), 'AC-4 NAV-02 the cart is unchanged when the language is switched: the same item and quantity, named in the new language, and back again (' . wp_json_encode( array( $items( $added ), $items( $ar_cart ), $items( $back ) ), JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-5 (D-31): the account keeps the choice ---------------------------------------------------------------
		$customer = wc_create_new_customer( "{$tag}-c@example.invalid", "{$tag}c", 'Pw-' . wp_generate_password( 18, false ) );
		$customer = is_wp_error( $customer ) ? 0 : (int) $customer;
		$s->track_user( $customer );
		$password = 'Pw-' . wp_generate_password( 18, false );
		wp_set_password( $password, $customer );
		$_POST['mizzey_language'] = 'ar';
		LanguagePreference::saveAccountField( $customer );
		unset( $_POST['mizzey_language'] );
		wp_set_current_user( $customer );
		ob_start();
		LanguagePreference::accountField();
		$field = (string) ob_get_clean();
		wp_set_current_user( 0 );
		$check( 'ar' === get_user_meta( $customer, LanguagePreference::META, true ) && str_contains( $field, 'name="mizzey_language"' ) && str_contains( $field, 'value="ar" lang="ar" selected' ), 'AC-5 the account page\'s language field keeps the choice on the account' );
		$signed = sign_in_account( 'en', "{$tag}c", $password ); // A new device, signing in on the English account page.
		$fresh  = t92_page( $en_url, $signed['cookies'] );
		$check( sign_in_has_session( $signed ) && 'ar' === t92_cookie( $signed['cookies'], LanguagePreference::COOKIE ) && 302 === $fresh['status'] && t92_same( $fresh['location'], $ar_url ), 'AC-5 D-31 on a new device, signing in brings the account\'s choice, and the English page opens in Arabic (' . $fresh['status'] . ')' );
		update_user_meta( $customer, LanguagePreference::META, 'en' );
		$older = t92_page( $ar_url, sign_in_jar( array_merge( $signed['cookies'], t92_device( 'ar' ) ) ) );
		$check( 302 === $older['status'] && t92_same( $older['location'], $en_url ), 'AC-5 D-31 the account\'s choice wins over a device\'s older one (' . $older['status'] . ')' );
		$crafted = t92_page( add_query_arg( LanguagePreference::CHOICE, 'ar', $en_url ), $signed['cookies'] );
		wp_cache_delete( $customer, 'user_meta' );
		$check( 'en' === get_user_meta( $customer, LanguagePreference::META, true ) && 302 === $crafted['status'] && t92_same( $crafted['location'], $en_url ), 'AC-5 D-31 a link made elsewhere, without the customer\'s nonce, changes nothing: the address is cleaned and the account stays English (' . $crafted['location'] . ')' );
		// The switcher's own link carries the customer's nonce: it is made for their session.
		$logged = t92_cookie( $signed['cookies'], LOGGED_IN_COOKIE );
		$_COOKIE[ LOGGED_IN_COOKIE ] = (string) $logged;
		wp_set_current_user( $customer );
		$own_url = add_query_arg( array( LanguagePreference::CHOICE => 'ar', LanguagePreference::NONCE => wp_create_nonce( LanguagePreference::NONCE ) ), $en_url );
		wp_set_current_user( 0 );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		$own = t92_page( $own_url, $signed['cookies'] );
		wp_cache_delete( $customer, 'user_meta' );
		$check( 302 === $own['status'] && t92_same( $own['location'], $ar_url ) && 'ar' === get_user_meta( $customer, LanguagePreference::META, true ), 'AC-5 D-31 the switcher\'s own link, with the nonce, changes the account\'s choice (' . $own['location'] . ')' );

		// ---- AC-6 (D-31): an order placed in Arabic, opened in English -------------------------------------------------
		update_user_meta( $customer, LanguagePreference::META, 'en' );
		$order = wc_create_order( array( 'customer_id' => $customer ) );
		$order->add_product( wc_get_product( $lamp_ar ), 1 );
		$order->update_meta_data( 'wpml_language', 'ar' );
		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->save();
		$s->track_order( $order->get_id() );
		do_action( 'wpml_switch_language', 'ar' );
		$view_ar = (string) $order->get_view_order_url();
		do_action( 'wpml_switch_language', 'en' );
		$view_en = (string) $order->get_view_order_url();
		$open    = t92_page( $view_ar, $signed['cookies'] );
		$shown   = t92_page( $view_en, $signed['cookies'] );
		$check( str_contains( $view_ar, '/ar/' ) && 302 === $open['status'] && t92_same( $open['location'], $view_en ) && 200 === $shown['status'] && storefront_shows( $shown['body'], "{$tag} Desk Lamp" ) && ! storefront_shows( $shown['body'], "مصباح مكتب {$tag}" ), 'AC-6 D-31 an order placed in Arabic, opened from its Arabic link by a customer who chose English, opens in English with its item in English (' . $open['status'] . ' ' . $open['location'] . ', ' . $shown['status'] . ')' );
		sign_in_forget( array( "{$tag}c" ) );

		// ---- AC-7: cached pages, the cart setting, the REST route ------------------------------------------------------
		$check( str_contains( $plain_en['body'], 'id="mizzey-language"' ) && str_contains( $plain_en['body'], LanguagePreference::COOKIE . '=' ), 'AC-7 a guest\'s page carries the script that does the same in the browser when the page comes from a cache' );
		$settings = LanguagePreference::keepCart( array( 'cart_sync' => array( 'lang_switch' => defined( 'WCML_CART_CLEAR' ) ? WCML_CART_CLEAR : 0, 'currency_switch' => 1 ) ) );
		$check( defined( 'WCML_CART_SYNC' ) && WCML_CART_SYNC === ( $settings['cart_sync']['lang_switch'] ?? null ) && WCML_CART_SYNC === ( get_option( '_wcml_settings' )['cart_sync']['lang_switch'] ?? null ), 'AC-7 the cart setting for a language switch is held at keeping the cart, even if it is saved as emptying it' );
		$read  = sign_in_json( 'GET', rest_url( 'mizzey/v1/language' ), null, array(), t92_device( 'ar' ) );
		$write = sign_in_json( 'POST', rest_url( 'mizzey/v1/language' ), array( 'language' => 'en' ), array(), array() );
		$bad   = sign_in_json( 'POST', rest_url( 'mizzey/v1/language' ), array( 'language' => 'fr' ), array(), array() );
		$check( 'ar' === ( $read['data']['language'] ?? '' ) && 'device' === ( $read['data']['kept_on'] ?? '' ) && 'en' === ( $write['data']['language'] ?? '' ) && 'en' === t92_cookie( $write['cookies'], LanguagePreference::COOKIE ) && 400 === $bad['status'], 'AC-2 the REST route reads and keeps a guest\'s choice, and refuses a language the store does not have (' . $read['status'] . ', ' . $write['status'] . ', ' . $bad['status'] . ')' );

		return $ok;
	}
);

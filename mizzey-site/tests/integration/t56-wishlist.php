<?php
/**
 * t56, the wishlist and recently viewed (specs/015-wishlist, #307).
 *
 * In-process: one entry per canonical product across languages, the guest's signed cookie (tampering, lifetime,
 * capacity), the customer's list on the account across devices, the merge on sign-in and registration (no duplicates,
 * idempotent), moving to the cart (a product, an Arabic variation, an out-of-stock item refused with its reason), and
 * recently viewed (one entry per product, most recent first, at most 15, shown in the current language, kept on the
 * account). Over HTTP: the REST routes as a guest, in English and in Arabic. Every product and user it makes is
 * removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Wishlist\GuestCookie;
use MizzeySite\Wishlist\RecentlyViewed;
use MizzeySite\Wishlist\Wishlist;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

/** A fresh visitor: nobody signed in, no wishlist or recently viewed cookie. */
function t56_guest(): void {
	wp_set_current_user( 0 );
	unset( $_COOKIE[ Wishlist::COOKIE ], $_COOKIE[ RecentlyViewed::COOKIE ] );
}

/** Sign in afresh as a user, on "another device": none of this browser's cookies. */
function t56_as( int $id ): void {
	t56_guest();
	wp_set_current_user( $id );
}

/** @return array{status:int,body:array,cookies:array} */
function t56_http( string $method, string $url, array $cookies, array $body = array() ): array {
	$response = wp_remote_request( $url, array( 'method' => $method, 'timeout' => 30, 'cookies' => $cookies, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => $body ? wp_json_encode( $body ) : null ) ); // storefront-guard: exempt, the wishlist routes answer JSON, not a storefront page.
	if ( is_wp_error( $response ) ) {
		return array( 'status' => 0, 'body' => array(), 'cookies' => $cookies );
	}
	$kept = array();
	foreach ( array_merge( $cookies, wp_remote_retrieve_cookies( $response ) ) as $cookie ) {
		$kept[ $cookie->name ] = $cookie;
	}
	foreach ( $kept as $name => $cookie ) {
		if ( '' === (string) $cookie->value || ( $cookie->expires && $cookie->expires < time() ) ) {
			unset( $kept[ $name ] );
		}
	}
	return array( 'status' => (int) wp_remote_retrieve_response_code( $response ), 'body' => (array) json_decode( wp_remote_retrieve_body( $response ), true ), 'cookies' => array_values( $kept ) );
}

run(
	new Scenario( 't56-wishlist', 'WISH-01 to WISH-04, WISH-07: the wishlist and recently viewed' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) {
			WC()->cart->empty_cart();
			t56_guest();
			wp_set_current_user( $previous );
			do_action( 'wpml_switch_language', 'en' );
		} );
		$tag     = 't56' . wp_rand( 1000, 9999 );
		$en      = catalogue_simple( $s, "{$tag}-A", 't56 serum' );
		$ar      = catalogue_duplicate( $s, $en );
		$b       = catalogue_simple( $s, "{$tag}-B", 't56 cream' );
		$c       = catalogue_simple( $s, "{$tag}-C", 't56 balm' );
		$gone    = catalogue_simple( $s, "{$tag}-D", 't56 sold out' );
		$product = wc_get_product( $gone );
		$product->set_stock_status( 'outofstock' );
		$product->save();
		$variable    = catalogue_variable( $s, "{$tag}-V", array( "{$tag}-V-S", "{$tag}-V-L" ) );
		$var_en      = (int) wc_get_product( $variable )->get_children()[0];
		$variable_ar = catalogue_duplicate( $s, $variable );
		$var_ar      = (int) apply_filters( 'wpml_object_id', $var_en, 'product_variation', false, 'ar' );
		if ( $var_ar > 0 ) {
			$s->track_post( $var_ar );
		}
		foreach ( array( $en, $b, $c, $variable ) as $stocked ) {
			$p = wc_get_product( $stocked );
			$p->set_stock_status( 'instock' );
			$p->save();
		}

		// The customer exists before any guest activity: creating an account merges the guest's lists into it.
		t56_guest();
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}-c", 'user_email' => "{$tag}-c@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
		$s->track_user( $customer );

		// WISH-01, B17: one entry per canonical product, saved and removed by either language record.
		t56_guest();
		$first  = Wishlist::add( $en );
		$second = Wishlist::add( $ar );
		$check( $ar > 0 && $en === $first && $en === $second && array( $en ) === Wishlist::ids(), "WISH-01, B17 saving the English ({$en}) and the Arabic ({$ar}) record of one product gives one entry" );
		Wishlist::remove( $ar );
		$check( array() === Wishlist::ids() && ! isset( $_COOKIE[ Wishlist::COOKIE ] ), 'WISH-01 removing it by its Arabic record empties the list and removes the cookie' );
		$check( null === Wishlist::add( 999999999 ), 'WISH-01 a product that does not exist is not saved' );

		// WISH-03: the guest's signed cookie, its lifetime and its size.
		Wishlist::add( $en );
		Wishlist::add( $b );
		$raw   = (string) ( $_COOKIE[ Wishlist::COOKIE ] ?? '' );
		$parts = explode( '|', $raw );
		$life  = (int) apply_filters( 'wc_session_expiration', 2 * DAY_IN_SECONDS );
		$check( array( $b, $en ) === Wishlist::ids() && 3 === count( $parts ) && abs( (int) $parts[1] - ( time() + $life ) ) < 60, 'WISH-03 a guest\'s list is kept in a signed cookie for WooCommerce\'s guest session (' . round( $life / HOUR_IN_SECONDS ) . ' hours), most recent first' );
		$_COOKIE[ Wishlist::COOKIE ] = $c . ',' . $parts[0] . '|' . $parts[1] . '|' . $parts[2];
		$planted                     = Wishlist::ids();
		$_COOKIE[ Wishlist::COOKIE ] = $parts[0] . '|' . ( (int) $parts[1] + YEAR_IN_SECONDS ) . '|' . $parts[2];
		$stretched                   = Wishlist::ids();
		$_COOKIE[ Wishlist::COOKIE ] = $raw;
		$check( array() === $planted && array() === $stretched && array( $b, $en ) === Wishlist::ids(), 'WISH-03 a cookie with an id planted, or its lifetime stretched, is ignored' );
		$expired = new GuestCookie( 't56_probe', 1 );
		$expired->write( array( $en ) );
		$probe                = explode( '|', (string) $_COOKIE['t56_probe'] );
		$_COOKIE['t56_probe'] = $probe[0] . '|' . ( time() - 1 ) . '|' . $probe[2];
		$check( array() === $expired->read(), 'WISH-03 an expired cookie is an empty list' );
		unset( $_COOKIE['t56_probe'] );
		$cap = static fn () => 2;
		add_filter( 'mizzey_wishlist_capacity', $cap );
		Wishlist::add( $c );
		remove_filter( 'mizzey_wishlist_capacity', $cap );
		$check( array( $c, $b ) === Wishlist::ids(), 'WISH-03 the list holds at most its capacity, the oldest dropped' );

		// US-11-06: the guest's list joins the account's on sign-in, without duplicates, and the guest's is cleared.
		update_user_meta( $customer, Wishlist::META, array( $b, $en ) );
		$user = get_user_by( 'id', $customer );
		do_action( 'wp_login', $user->user_login, $user );
		$merged = get_user_meta( $customer, Wishlist::META, true );
		$check( array( $c, $b, $en ) === $merged && ! isset( $_COOKIE[ Wishlist::COOKIE ] ), 'US-11-06 signing in adds the guest\'s items to the account\'s (' . implode( ',', (array) $merged ) . '), no duplicate, and clears the guest\'s list' );
		t56_guest();
		Wishlist::add( $ar );
		Wishlist::mergeGuestInto( $customer );
		Wishlist::mergeGuestInto( $customer );
		$check( array( $c, $b, $en ) === get_user_meta( $customer, Wishlist::META, true ), 'US-11-06 merging an item the account already has, twice, changes nothing' );
		t56_guest();
		Wishlist::add( $b );
		$newcomer = (int) wp_insert_user( array( 'user_login' => "{$tag}-n", 'user_email' => "{$tag}-n@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
		$s->track_user( $newcomer );
		$check( array( $b ) === get_user_meta( $newcomer, Wishlist::META, true ), 'US-11-06 registering keeps the guest\'s list on the new account' );

		// WISH-02: a customer's list is on the account, on any device.
		t56_as( $customer );
		Wishlist::add( $variable );
		t56_as( $customer );
		$check( array( $variable, $c, $b, $en ) === Wishlist::ids() && ! isset( $_COOKIE[ Wishlist::COOKIE ] ), 'WISH-02 a customer\'s list is kept on the account and is there on another device' );

		// WISH-04: one action to the cart; an out-of-stock item stays, with the reason.
		t56_guest();
		WC()->cart->empty_cart();
		Wishlist::add( $en );
		Wishlist::add( $var_ar );
		Wishlist::add( $gone );
		$simple_moved = Wishlist::moveToCart( $ar );
		do_action( 'wpml_switch_language', 'ar' );
		$var_moved = Wishlist::moveToCart( $var_ar );
		do_action( 'wpml_switch_language', 'en' );
		$in_cart = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$in_cart[] = (int) ( $line['variation_id'] ?: $line['product_id'] );
		}
		$check( true === $simple_moved && true === $var_moved && 2 === count( $in_cart ) && array( $gone ) === Wishlist::ids(), 'WISH-04 a product and an Arabic variation move to the cart in one action and leave the wishlist (' . implode( ',', $in_cart ) . ')' );
		$refused = Wishlist::moveToCart( $gone );
		$check( is_wp_error( $refused ) && 'mizzey_wishlist_out_of_stock' === $refused->get_error_code() && 409 === ( $refused->get_error_data()['status'] ?? 0 ) && array( $gone ) === Wishlist::ids(), 'WISH-04 an out-of-stock item is not moved, stays saved, and the reason is given (' . ( is_wp_error( $refused ) ? $refused->get_error_message() : 'moved' ) . ')' );
		$not_saved = Wishlist::moveToCart( $c );
		$check( is_wp_error( $not_saved ) && 404 === ( $not_saved->get_error_data()['status'] ?? 0 ), 'WISH-04 an item that is not saved is not moved' );
		WC()->cart->empty_cart();

		// WISH-07: recently viewed.
		t56_guest();
		RecentlyViewed::record( $en );
		RecentlyViewed::record( $b );
		RecentlyViewed::record( $ar );
		do_action( 'wpml_switch_language', 'ar' );
		$shown_ar = array_map( static fn ( $p ) => $p->get_id(), RecentlyViewed::products() );
		do_action( 'wpml_switch_language', 'en' );
		$shown_en = array_map( static fn ( $p ) => $p->get_id(), RecentlyViewed::products( 15, $b ) );
		$check( $ar === ( $shown_ar[0] ?? 0 ) && 2 === count( $shown_ar ) && array( $en ) === $shown_en, 'WISH-07 the English and Arabic views of one product are one entry, most recent first, shown in Arabic on an Arabic page; the product being viewed can be left out' );
		$ids = array();
		for ( $i = 0; $i < 17; $i++ ) {
			$ids[] = catalogue_simple( $s, "{$tag}-R{$i}", "t56 view {$i}" );
		}
		foreach ( $ids as $id ) {
			RecentlyViewed::record( $id );
		}
		$listed = array_map( static fn ( $p ) => $p->get_id(), RecentlyViewed::products() );
		$check( RecentlyViewed::LIMIT === count( $listed ) && end( $ids ) === $listed[0], 'WISH-07 at most 15, the latest first (' . count( $listed ) . ')' );
		t56_as( $customer );
		RecentlyViewed::record( $c );
		t56_as( $customer );
		$check( array( $c ) === array_map( static fn ( $p ) => $p->get_id(), RecentlyViewed::products() ), 'WISH-07 a customer\'s recently viewed list persists across sessions, on the account' );

		// Over HTTP, as a guest, in English and Arabic.
		t56_guest();
		$base   = rest_url( 'mizzey/v1/' );
		$ar_url = str_replace( home_url( '/' ), home_url( '/ar/' ), $base );
		$empty  = t56_http( 'GET', $base . 'wishlist', array() );
		$added  = t56_http( 'POST', $base . 'wishlist/items', $empty['cookies'], array( 'id' => $en ) );
		$again  = t56_http( 'GET', $base . 'wishlist', $added['cookies'] );
		$arabic = t56_http( 'GET', $ar_url . 'wishlist', $added['cookies'] );
		$check( 200 === $empty['status'] && array() === $empty['body']['items'] && 201 === $added['status'] && $en === ( $again['body']['items'][0]['canonical'] ?? 0 ), "[HTTP] a guest saves a product and finds it on the next request ({$added['status']})" );
		$check( $ar === ( $arabic['body']['items'][0]['id'] ?? 0 ), '[HTTP] on the Arabic address the saved product comes back as its Arabic record' );
		$moved   = t56_http( 'POST', $base . "wishlist/items/{$en}/cart", $added['cookies'] );
		$sold    = t56_http( 'POST', $base . 'wishlist/items', $moved['cookies'], array( 'id' => $gone ) );
		$refusal = t56_http( 'POST', $base . "wishlist/items/{$gone}/cart", $sold['cookies'] );
		$removed = t56_http( 'DELETE', $base . "wishlist/items/{$gone}", $refusal['cookies'] );
		$check( 200 === $moved['status'] && array() === $moved['body']['items'] && 409 === $refusal['status'] && 'mizzey_wishlist_out_of_stock' === ( $refusal['body']['code'] ?? '' ) && array() === $removed['body']['items'], "[HTTP] moving to the cart ({$moved['status']}), an out-of-stock refusal ({$refusal['status']}) and a removal" );

		return $ok;
	}
);

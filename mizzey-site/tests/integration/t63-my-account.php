<?php
/**
 * t63, the account area (specs/022-my-account, #308): the address book (add, edit, delete, default, its limits, its
 * checks and its REST routes), the default copied to WooCommerce's address without touching the sign-in number, order
 * tracking on the order view, and the wishlist page. In-process. Every product, order and user it makes is removed when
 * it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Account\AccountPages;
use MizzeySite\Account\AddressBook;
use MizzeySite\Emails\Shipped;
use MizzeySite\Wishlist\Wishlist;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A customer for the scenario, tracked for removal; 0 when WordPress refuses it. */
function t63_customer( Scenario $s, string $login ): int {
	$id = wp_insert_user( array( 'user_login' => $login, 'user_email' => "{$login}@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
	if ( is_wp_error( $id ) || (int) $id <= 1 ) {
		return 0;
	}
	$s->track_user( (int) $id );
	return (int) $id;
}

/** An address in the Egyptian structure. */
function t63_address( array $change = array() ): array {
	return $change + array( 'first_name' => 'Laila', 'last_name' => 'Fawzy', 'phone' => '+20 100 555 1234', 'state' => 'EG-01', 'city' => 'Cairo', 'district' => 'Zamalek', 'address_1' => '12 Brazil Street', 'landmark' => 'Near the club' );
}

run(
	new Scenario( 't63-my-account', 'ACCT-01 to ACCT-08: the account area' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		$tag   = 't63' . wp_rand( 1000, 9999 );
		$laila = t63_customer( $s, "{$tag}-laila" );
		$other = t63_customer( $s, "{$tag}-other" );
		update_user_meta( $laila, 'billing_phone', '01011112222' );

		// ACCT-04: add, check, default.
		$first   = AddressBook::add( $laila, t63_address() );
		$second  = AddressBook::add( $laila, t63_address( array( 'city' => 'Giza', 'state' => 'EG-12', 'address_1' => '3 Pyramids Road' ) ) );
		$missing = AddressBook::add( $laila, t63_address( array( 'district' => '' ) ) );
		$phone   = AddressBook::add( $laila, t63_address( array( 'phone' => '12345' ) ) );
		$place   = AddressBook::add( $laila, t63_address( array( 'state' => 'XX-99' ) ) );
		$book    = AddressBook::all( $laila );
		$customer = new \WC_Customer( $laila );
		$check( is_string( $first ) && is_string( $second ) && 2 === count( $book ) && true === $book[0]['default'] && false === $book[1]['default'] && '01005551234' === $book[0]['phone'] && is_wp_error( $missing ) && is_wp_error( $phone ) && is_wp_error( $place ) && 'Cairo' === $customer->get_billing_city() && 'Zamalek' === $customer->get_meta( '_wc_billing/mizzey/district' ), 'ACCT-04 two addresses added, the first the default and copied to WooCommerce\'s address with its district; one without a district, a malformed mobile number or an unknown governorate is refused' );

		$made_default = AddressBook::makeDefault( $laila, (string) $second );
		$customer     = new \WC_Customer( $laila );
		$check( true === $made_default && 'Giza' === $customer->get_billing_city() && '01005551234' === $customer->get_shipping_phone() && '01011112222' === get_user_meta( $laila, 'billing_phone', true ) && true === AddressBook::all( $laila )[1]['default'] && false === AddressBook::all( $laila )[0]['default'], 'ACCT-04 setting a default copies it to WooCommerce\'s billing and shipping address, its number to the shipping phone, and leaves the sign-in number alone' );

		$edited  = AddressBook::edit( $laila, (string) $first, t63_address( array( 'landmark' => 'Opposite the bakery' ) ) );
		$foreign = AddressBook::edit( $other, (string) $first, t63_address() );
		$deleted = AddressBook::delete( $laila, (string) $first );
		$check( true === $edited && is_wp_error( $foreign ) && true === $deleted && 1 === count( AddressBook::all( $laila ) ) && (string) $second === AddressBook::all( $laila )[0]['id'], 'ACCT-04 an address is edited and deleted; another customer cannot touch it' );

		$third = AddressBook::add( $laila, t63_address( array( 'city' => 'Tanta', 'state' => 'EG-03', 'address_1' => '8 Station Road' ) ) );
		AddressBook::delete( $laila, (string) $second );
		$after_delete = AddressBook::all( $laila );
		$customer     = new \WC_Customer( $laila );
		$check( 1 === count( $after_delete ) && (string) $third === $after_delete[0]['id'] && true === $after_delete[0]['default'] && 'Tanta' === $customer->get_billing_city(), 'ACCT-04 deleting the default makes the address left the default, and the checkout copy follows' );
		$second = $third;
		for ( $i = 0; $i < AddressBook::CAPACITY; $i++ ) {
			AddressBook::add( $other, t63_address() );
		}
		$full = AddressBook::add( $other, t63_address() );
		$check( is_wp_error( $full ) && AddressBook::CAPACITY === count( AddressBook::all( $other ) ), 'ACCT-04 the book holds up to ' . AddressBook::CAPACITY . ' addresses' );

		// The REST routes: the signed-in customer's own book only.
		wp_set_current_user( 0 );
		$guest = rest_do_request( new \WP_REST_Request( 'GET', '/mizzey/v1/account/addresses' ) );
		wp_set_current_user( $laila );
		$post = new \WP_REST_Request( 'POST', '/mizzey/v1/account/addresses' );
		$post->set_header( 'Content-Type', 'application/json' );
		$post->set_body( (string) wp_json_encode( t63_address( array( 'city' => 'Alexandria', 'state' => 'EG-02' ) ) ) );
		$added = rest_do_request( $post );
		$list  = rest_do_request( new \WP_REST_Request( 'GET', '/mizzey/v1/account/addresses' ) );
		wp_set_current_user( 0 );
		$password = 'Pw-' . wp_generate_password( 18, false );
		wp_set_password( $password, $laila );
		$signed   = sign_in_account( 'en', (string) get_userdata( $laila )->user_login, $password );
		$forged   = sign_in_json( 'POST', rest_url( 'mizzey/v1/account/addresses' ), t63_address(), array(), $signed['cookies'] );
		sign_in_forget( array( (string) get_userdata( $laila )->user_login ) );
		$check( sign_in_has_session( $signed ) && 401 === $forged['status'], '[HTTP] ACCT-04 a request carrying the sign-in cookies of the customer but not the REST nonce of WordPress is refused, so another site cannot change the book (' . $forged['status'] . ')' );
		$check( 401 === $guest->get_status() && 201 === $added->get_status() && 2 === count( (array) ( $list->get_data()['addresses'] ?? array() ) ), 'ACCT-04 the REST routes answer a visitor 401 and act on the signed-in customer\'s own book (' . $guest->get_status() . ', ' . $added->get_status() . ')' );

		// ACCT-07: order tracking, the shipment and the progress.
		$product = $s->simple_product( 't63 cream', '90', null );
		$order   = wc_create_order( array( 'customer_id' => $laila ) );
		$order->add_product( $product, 1 );
		$order->set_billing_email( (string) get_userdata( $laila )->user_email );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		foreach ( array( 'processing', 'preparing', 'shipped' ) as $step ) {
			$order = wc_get_order( $order->get_id() );
			$order->update_status( $step );
		}
		$order = wc_get_order( $order->get_id() );
		$order->update_meta_data( Shipped::CARRIER, 'Bosta' );
		$order->update_meta_data( Shipped::TRACKING, 'BST-55210' );
		$order->save();
		$steps = array_column( AccountPages::progress( $order ), 'status' );
		$failed_order = wc_create_order( array( 'customer_id' => $laila ) );
		$failed_order->add_product( $product, 1 );
		$failed_order->calculate_totals();
		$failed_order->save();
		$s->track_order( $failed_order->get_id() );
		foreach ( array( 'processing', 'preparing', 'shipped', 'delivery-failed' ) as $step ) {
			$failed_order = wc_get_order( $failed_order->get_id() );
			$failed_order->update_status( $step );
		}
		$failed_steps = array_column( AccountPages::progress( wc_get_order( $failed_order->get_id() ) ), 'status' );
		$check( 'delivery-failed' === end( $failed_steps ), 'ACCT-07 an order whose delivery failed shows that step too (' . implode( ',', $failed_steps ) . ')' );
		$on_account = static fn () => true;
		add_filter( 'woocommerce_is_account_page', $on_account );
		ob_start();
		do_action( 'woocommerce_order_details_after_order_table', $order );
		$html = (string) ob_get_clean();
		remove_filter( 'woocommerce_is_account_page', $on_account );
		$check( array( 'pending', 'processing', 'preparing', 'shipped' ) === $steps && false !== strpos( $html, 'BST-55210' ) && false !== strpos( $html, 'Bosta' ), 'ACCT-07 the order view shows the carrier and tracking number and each status the order entered, in order (' . implode( ',', $steps ) . ')' );

		// ACCT-08: the account menu opens the storefront's one Wishlist page (IA-11), not a second route under the account.
		$menu     = array_keys( (array) apply_filters( 'woocommerce_account_menu_items', array( 'dashboard' => 'Dashboard', 'orders' => 'Orders', 'customer-logout' => 'Log out' ) ) );
		$wish_url = wc_get_account_endpoint_url( AccountPages::WISHLIST );
		$vars     = (array) apply_filters( 'woocommerce_get_query_vars', array() );
		$check( array( 'dashboard', 'orders', AccountPages::WISHLIST, 'customer-logout' ) === $menu && untrailingslashit( home_url( '/wishlist' ) ) === untrailingslashit( $wish_url ) && ! isset( $vars[ AccountPages::WISHLIST ] ), 'ACCT-08 the account menu has a Wishlist item after Orders, opening the storefront\'s Wishlist page (' . $wish_url . ')' );

		return $ok;
	}
);

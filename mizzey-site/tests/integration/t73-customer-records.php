<?php
/**
 * t73: the customer's record in admin (ADM-100, ADM-103, ADM-104). See specs/033-customer-records.
 *
 * AC-1: the customer's profile shows contacts, addresses (the address book included) and order history, refunds
 *       visible on it.
 * AC-2: internal notes are added, each with its author and time, kept, and never shown to the customer.
 * AC-3: an account is suspended with a reason: sessions end, every sign-in is refused, the reason and who did it are
 *       kept; reinstating restores sign-in. Staff accounts and one's own cannot be suspended.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Account\AddressBook;
use MizzeySite\Account\CustomerRecords;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';
require_once __DIR__ . '/_sign_in.php';

run(
	new Scenario( 't73-customer-records', 'ADM-100, ADM-103, ADM-104: the customer record' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$s->on_finish( static function (): void { $_POST = array(); wp_set_current_user( 0 ); } );
		$tag  = 't73' . wp_rand( 1000, 9999 );
		$pass = 'Pw-' . wp_generate_password( 20, false );
		$mk   = function ( string $login, string $role ) use ( $s, $tag, $pass ): int {
			$id = (int) wp_insert_user( array( 'user_login' => "{$tag}{$login}", 'user_pass' => $pass, 'user_email' => "{$tag}{$login}@example.invalid", 'role' => $role ) );
			$s->track_user( $id );
			return $id;
		};
		$customer = $mk( 'cust', 'customer' );
		$manager  = $mk( 'mgr', 'shop_manager' );
		$other    = $mk( 'mgr2', 'shop_manager' );
		update_user_meta( $customer, 'billing_phone', '01009998877' );
		update_user_meta( $customer, 'billing_address_1', '5 Nile Corniche' );
		$product = $s->simple_product( 't73 cream', '80.00', null );
		$orders  = array();
		foreach ( array( 1, 2 ) as $n ) {
			$o = wc_create_order( array( 'customer_id' => $customer ) );
			$o->add_product( $product, $n );
			$o->calculate_totals();
			$o->set_date_created( time() - ( 3 - $n ) * HOUR_IN_SECONDS );
			$o->save();
			$s->track_order( $o->get_id() );
			$orders[] = $o->get_id();
		}
		wp_set_current_user( $manager );
		$panel = static function ( int $id ): string {
			ob_start();
			CustomerRecords::panel( get_user_by( 'id', $id ) );
			return (string) ob_get_clean();
		};
		$post = static function ( array $fields ): void {
			$_POST = array_merge( array( CustomerRecords::NONCE . '_nonce' => wp_create_nonce( CustomerRecords::NONCE ) ), $fields );
		};

		// ---- AC-1: contacts, addresses, orders -------------------------------------------------------------
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-profile.php';
		$fields = ( new \WC_Admin_Profile() )->get_customer_meta_fields();
		$check( isset( $fields['billing']['fields']['billing_phone'], $fields['billing']['fields']['billing_email'], $fields['billing']['fields']['billing_address_1'], $fields['shipping']['fields']['shipping_address_1'] ), 'AC-1 the profile carries WooCommerce\'s contact and address fields (billing and shipping)' );
		$html = $panel( $customer );
		$check( str_contains( $html, 'All 2 orders of this customer' ) && str_contains( $html, '_customer_user=' . $customer ), 'AC-1 the profile links to all of the customer\'s orders in the orders list' );
		$check( str_contains( $html, '#' . $orders[0] ) && str_contains( $html, '#' . $orders[1] ) && strpos( $html, '#' . $orders[1] ) < strpos( $html, '#' . $orders[0] ), 'AC-1 the profile lists the customer\'s orders, newest first, linked to each order' );
		$saved = AddressBook::add( $customer, array( 'first_name' => 'Rana', 'last_name' => 'Kamel', 'phone' => '01001234567', 'state' => 'EG-01', 'city' => 'Cairo', 'district' => "{$tag} Heliopolis", 'address_1' => '9 Baghdad Street', 'landmark' => '' ) );
		$extra = AddressBook::add( $customer, array( 'first_name' => 'Rana', 'last_name' => 'Kamel', 'phone' => '01001234567', 'state' => 'EG-02', 'city' => 'Alexandria', 'district' => "{$tag} Smouha", 'address_1' => '3 Victor Emmanuel Street', 'landmark' => '' ) );
		wc_get_order( $orders[1] )->update_status( 'processing' );
		$refund = wc_create_refund( array( 'order_id' => $orders[1], 'amount' => '10.00', 'reason' => 't73' ) );
		$html   = $panel( $customer );
		$check( is_string( $saved ) && is_string( $extra ) && str_contains( $html, "{$tag} Heliopolis" ) && str_contains( $html, "{$tag} Smouha" ) && str_contains( $html, '3 Victor Emmanuel Street' ), 'AC-1 the profile lists the customer\'s address book, a saved address that is not the default included' );
		$check( ! is_wp_error( $refund ) && str_contains( $html, '<del' ), 'AC-1, US-19-01 a refund against the customer shows on the profile (the order\'s total struck through with what is left)' );

		// ---- AC-2: internal notes ------------------------------------------------------------------------
		update_user_meta( $customer, CustomerRecords::NOTES, "{$tag} an older single note" );
		$post( array( 'mizzey_customer_note' => "{$tag} prefers delivery after 6 pm" ) );
		CustomerRecords::save( $customer );
		$_POST = array();
		wp_set_current_user( $other );
		$post( array( 'mizzey_customer_note' => "{$tag} refused delivery twice" ) );
		CustomerRecords::save( $customer );
		$_POST = array();
		wp_set_current_user( $manager );
		$notes      = CustomerRecords::notes( $customer );
		$notes_html = $panel( $customer );
		$check( 3 === count( $notes ) && "{$tag} an older single note" === $notes[0]['text'] && "{$tag} prefers delivery after 6 pm" === $notes[1]['text'] && "{$tag}mgr" === $notes[1]['by'] && "{$tag} refused delivery twice" === $notes[2]['text'] && "{$tag}mgr2" === $notes[2]['by'] && abs( $notes[2]['at'] - time() ) < 60, 'AC-2, US-19-02 notes are added, each with its author and time; a later note keeps the earlier ones, and a note saved before notes were entries is kept as the first' );
		$check( str_contains( $notes_html, 'prefers delivery after 6 pm' ) && str_contains( $notes_html, "by {$tag}mgr2" ) && strpos( $notes_html, 'refused delivery twice' ) < strpos( $notes_html, 'prefers delivery after 6 pm' ), 'AC-2 the profile shows each note with its author, newest first' );
		// Another request holding the customer's note lock (a second database connection) past the wait: nothing written.
		$rival = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$taken = 1 === (int) $rival->get_var( $rival->prepare( 'SELECT GET_LOCK(%s, 0)', CustomerRecords::notesLock( $customer ) ) );
		$post( array( 'mizzey_customer_note' => "{$tag} written while locked" ) );
		CustomerRecords::save( $customer );
		$_POST       = array();
		$busy_errors = new \WP_Error();
		do_action_ref_array( 'user_profile_update_errors', array( &$busy_errors, true, get_user_by( 'id', $customer ) ) );
		$rival->get_var( $rival->prepare( 'SELECT RELEASE_LOCK(%s)', CustomerRecords::notesLock( $customer ) ) );
		$rival->close();
		$check( $taken && 3 === count( CustomerRecords::notes( $customer ) ) && in_array( 'mizzey_customer_note_busy', $busy_errors->get_error_codes(), true ), 'AC-2 a note whose turn does not come (another save holds the customer\'s notes) is not written, and staff are told to add it again' );
		wp_set_current_user( $customer );
		$me = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/users/me' ) );
		$me->set_status( $me->get_status() );
		$store = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/cart' ) );
		ob_start();
		do_action( 'woocommerce_account_dashboard' );
		do_action( 'woocommerce_account_edit-account_endpoint' );
		$account = (string) ob_get_clean();
		$check( ! str_contains( wp_json_encode( $me->get_data() ) . wp_json_encode( $store->get_data() ) . $account, 'prefers delivery' ) && '' === $panel( $customer ), 'AC-2 the note is in none of what the customer can read: their user data, the store API, their account pages' );
		wp_set_current_user( $manager );
		$post( array( 'mizzey_customer_note' => 'forged' ) );
		$_POST[ CustomerRecords::NONCE . '_nonce' ] = 'bad';
		CustomerRecords::save( $customer );
		$_POST = array();
		$check( 3 === count( CustomerRecords::notes( $customer ) ), 'AC-2 without the form\'s nonce nothing changes' );

		// Who WordPress takes the visitor to be when the browser holds a valid signed-in cookie for the account, as a
		// Google or sign-in-link session would leave it.
		$cookie_user = static function ( int $id ): int {
			$keep                          = $_COOKIE;
			$token                         = \WP_Session_Tokens::get_instance( $id )->create( time() + HOUR_IN_SECONDS );
			$_COOKIE[ LOGGED_IN_COOKIE ]   = wp_generate_auth_cookie( $id, time() + HOUR_IN_SECONDS, 'logged_in', $token );
			$who                           = (int) apply_filters( 'determine_current_user', false );
			$_COOKIE                       = $keep;
			\WP_Session_Tokens::get_instance( $id )->destroy( $token );
			return $who;
		};
		$check( $customer === $cookie_user( $customer ), 'fixture: a valid signed-in cookie makes the customer the visitor' );

		// ---- AC-3: suspension ----------------------------------------------------------------------------
		$session_token = \WP_Session_Tokens::get_instance( $customer )->create( time() + HOUR_IN_SECONDS );
		$signed_in     = array( new \WP_Http_Cookie( array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $customer, time() + HOUR_IN_SECONDS, 'logged_in', $session_token ) ) ) );
		$cart          = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $signed_in );
		$cart_token    = sign_in_header( $cart, 'cart-token' );
		$check( '' !== $cart_token && 200 === $cart['status'], 'fixture: the customer holds a Store API cart token' );
		$token = \WP_Session_Tokens::get_instance( $customer )->create( time() + HOUR_IN_SECONDS );
		$post( array( 'mizzey_suspended' => 'yes', 'mizzey_suspension_reason' => '' ) );
		CustomerRecords::save( $customer );
		$errors = new \WP_Error();
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, get_user_by( 'id', $customer ) ) );
		$_POST = array();
		$check( ! CustomerRecords::isSuspended( $customer ) && $errors->has_errors(), 'AC-3 a suspension without a reason is refused with a message' );
		$post( array( 'mizzey_suspended' => 'yes', 'mizzey_suspension_reason' => 'Repeated refused deliveries' ) );
		CustomerRecords::save( $customer );
		$_POST = array();
		$check( CustomerRecords::isSuspended( $customer ) && 'Repeated refused deliveries' === get_user_meta( $customer, CustomerRecords::REASON, true ) && 0 === count( \WP_Session_Tokens::get_instance( $customer )->get_all() ) && null === \WP_Session_Tokens::get_instance( $customer )->get( $token ), 'AC-3 the account is suspended with its reason and its sessions end' );
		$history = get_user_meta( $customer, CustomerRecords::HISTORY, true );
		$check( is_array( $history ) && 'suspended' === ( end( $history )['action'] ?? '' ) && "{$tag}mgr" === ( end( $history )['by'] ?? '' ) && str_contains( $panel( $customer ), 'Repeated refused deliveries' ), 'AC-3 who suspended it, when and why are kept and shown' );
		wp_set_current_user( 0 );
		$signon = wp_authenticate( "{$tag}cust", $pass );
		$check( is_wp_error( $signon ) && 'mizzey_suspended' === $signon->get_error_code(), 'AC-3 a password sign-in is refused, saying the account is suspended' );
		$check( 0 === $cookie_user( $customer ), 'AC-3 a valid signed-in cookie, as Google or a sign-in link would set, does not make it the signed-in user' );
		$with_token = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array( 'Cart-Token' => $cart_token ) );
		$check( 403 === $with_token['status'], 'AC-3 the cart token issued before the suspension is refused (' . $with_token['status'] . ')' );
		$died  = null;
		$catch = static function () use ( &$died ) {
			return static function ( $m, $t = '', $a = array() ) use ( &$died ) {
				$died = (int) ( is_array( $a ) ? ( $a['response'] ?? 0 ) : 0 );
				throw new \RuntimeException( 'died' );
			};
		};
		add_filter( 'wp_die_handler', $catch );
		$after_login = 0;
		$watch       = static function () use ( &$after_login ) { $after_login++; };
		add_action( 'wp_login', $watch, 10 );
		wp_set_current_user( $customer );
		try {
			do_action( 'wp_login', "{$tag}cust", get_user_by( 'id', $customer ) );
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		remove_action( 'wp_login', $watch, 10 );
		remove_filter( 'wp_die_handler', $catch );
		$check( 403 === $died && 0 === $after_login && 0 === get_current_user_id(), 'AC-3 a sign-in any other route completes (Google, a sign-in link) is undone before anything else acts on it' );
		wp_set_current_user( 0 );
		$http = sign_in_account( 'en', "{$tag}cust", $pass );
		$check( ! sign_in_has_session( $http ) && str_contains( $http['body'], 'suspended' ), 'AC-3 over the real request, the account page refuses the sign-in and says why (' . $http['status'] . ')' );
		$http_ar = sign_in_account( 'ar', "{$tag}cust", $pass );
		$check( ! sign_in_has_session( $http_ar ) && str_contains( $http_ar['body'], 'هذا الحساب موقوف' ), 'AC-3 on the Arabic account page the message is in Arabic' );
		wp_set_current_user( $manager );
		$post( array( 'mizzey_suspension_reason' => 'Agreed by phone' ) );
		CustomerRecords::save( $customer );
		$_POST = array();
		wp_set_current_user( 0 );
		$check( ! CustomerRecords::isSuspended( $customer ) && wp_authenticate( "{$tag}cust", $pass ) instanceof \WP_User && $customer === $cookie_user( $customer ), 'AC-3 reinstated, the customer can sign in again, by password or cookie' );
		wp_set_current_user( $manager );
		$post( array( 'mizzey_suspended' => 'yes', 'mizzey_suspension_reason' => 'x' ) );
		CustomerRecords::save( $other );
		CustomerRecords::save( $manager );
		$_POST = array();
		$check( ! CustomerRecords::isSuspended( $other ) && ! CustomerRecords::isSuspended( $manager ) && '' === $panel( $other ), 'AC-3 a staff account, and one\'s own, cannot be suspended here' );
		$owner = $mk( 'owner', 'administrator' );
		wp_set_current_user( $owner );
		$post( array( 'mizzey_suspended' => 'yes', 'mizzey_suspension_reason' => 'x' ) );
		CustomerRecords::save( $other );
		$_POST = array();
		$check( current_user_can( 'edit_user', $other ) && ! CustomerRecords::isSuspended( $other ), 'AC-3 even an administrator, who may edit a shop manager, cannot suspend a staff account here' );

		return $ok;
	}
);

<?php
/**
 * t47, AUTH-15 (AC-2, AC-4; FR-003 to FR-005, FR-007): the check against scripted account creation.
 *
 * With each account-creating route opened for the scenario, requests the page did not produce create no account:
 * no token, a malformed, array, oversized, tampered, future-dated, wrong-route or spent token, a token too fresh, a
 * filled trap field. A valid token creates the account once. The block checkout rejects before order processing: no
 * account and no new order, and an existing draft order is unchanged; guest and signed-in checkout need no token. The
 * classic checkout's handlers, still registered, create no account. WordPress's registration, opened for one case,
 * applies the same check. Staff, WooCommerce REST with `create_customers` and WP-CLI keep their native behaviour; a
 * customer cannot create accounts in-process. Verifier boundaries, the atomic claim, the permit and the block wrapper
 * are checked in-process. The security check reports each row. Settings are restored and created accounts removed.
 *
 * Real-browser submission of the three forms is t49's.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\SignupCheck;
use MizzeySite\Security\SignupRoutes;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** The status the check gives a row, from its table output. */
function t47_row( string $out, string $key ): string {
	return 1 === preg_match( '/^' . preg_quote( $key, '/' ) . '\t(\S+)/m', $out, $m ) ? $m[1] : '';
}

/**
 * Post the account page's registration form.
 *
 * @param array<string,mixed> $extra Signup fields to send, or to override.
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function t47_register( string $lang, string $email, array $extra, ?string $nonce = null ): array {
	$account = sign_in_account_url( $lang );
	$form    = two_factor_http( 'GET', $account );
	$fields  = array(
		'email'                      => $email,
		'password'                   => 'Pw-' . wp_generate_password( 18, false ),
		'woocommerce-register-nonce' => $nonce ?? two_factor_field( $form['body'], 'woocommerce-register-nonce' ),
		'_wp_http_referer'           => wp_parse_url( $account, PHP_URL_PATH ),
		'register'                   => 'Register',
	);
	return two_factor_http( 'POST', $account, array_merge( $fields, $extra ), $form['cookies'] );
}

/** A token the server would have issued for a route, the given number of seconds ago. */
function t47_token( string $route, int $age ): string {
	return SignupCheck::issue( $route, time() - $age );
}

/**
 * A Store API cart holding one unit of a product.
 *
 * @return array{headers:array,cookies:array}
 */
function t47_cart( int $product_id, array $cookies = array() ): array {
	$cart = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $cookies );
	$auth = array( 'Cart-Token' => sign_in_header( $cart, 'cart-token' ), 'Nonce' => sign_in_header( $cart, 'nonce' ) );
	$add  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $product_id, 'quantity' => 1 ), $auth, $cart['cookies'] );
	return array( 'headers' => $auth, 'cookies' => $add['cookies'] );
}

/** A Store API checkout body for an email. */
function t47_checkout_body( string $email, ?bool $create, ?array $signup ): array {
	// An Egyptian address carries its district (specs/019).
	$address = array( 'first_name' => 'Test', 'last_name' => 'Buyer', 'address_1' => '1 Test Street', 'city' => 'Cairo', 'postcode' => '11511', 'country' => 'EG', 'state' => 'EG-01', 'email' => $email, 'phone' => '01000000000', 'mizzey/district' => 'Downtown' );
	$body    = array( 'billing_address' => $address, 'shipping_address' => $address, 'payment_method' => 'cod' );
	if ( null !== $create ) {
		$body['create_account']    = $create;
		$body['customer_password'] = 'Pw-' . wp_generate_password( 18, false );
	}
	if ( null !== $signup ) {
		$body['extensions'] = array( 'mizzey-signup' => $signup );
	}
	return $body;
}

/** The newest order id of any status, drafts included. */
function t47_latest_order(): int {
	$statuses = array_unique( array_merge( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ) );
	$ids      = wc_get_orders( array( 'limit' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'status' => $statuses, 'return' => 'ids' ) );
	return (int) ( $ids[0] ?? 0 );
}

run(
	new Scenario( 't47-signup-check', 'AUTH-15: every route that creates an account applies a check that a scripted submission fails' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$emails = array();
		$s->on_finish(
			static function () use ( &$emails ) {
				foreach ( $emails as $email ) {
					sign_in_remove_account( $email );
					foreach ( sign_in_orders_for( $email ) as $order_id ) {
						wc_get_order( $order_id )?->delete( true );
					}
				}
			}
		);
		$fresh = function ( string $tag ) use ( &$emails ): string {
			$email    = sign_in_email( $tag );
			$emails[] = $email;
			return $email;
		};
		$message_en = 'We could not create your account. Please reload the page and try again.';
		$message_ar = 'تعذر إنشاء حسابك. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.';

		$cod = get_option( 'woocommerce_cod_settings', array() );
		sign_in_options(
			$s,
			array(
				'woocommerce_enable_myaccount_registration'           => 'yes',
				'woocommerce_registration_generate_username'          => 'yes',
				'woocommerce_registration_generate_password'          => 'no',
				'woocommerce_enable_delayed_account_creation'         => 'yes',
				'woocommerce_enable_signup_and_login_from_checkout'   => 'yes',
				'woocommerce_enable_guest_checkout'                   => 'yes',
				'woocommerce_cod_settings'                            => array_merge( is_array( $cod ) ? $cod : array(), array( 'enabled' => 'yes', 'enable_for_virtual' => 'yes' ) ),
				// The cart asks the stock source and fails closed (specs/018): the development fixture stands in.
				\MizzeySite\Stock\FixtureStockSource::OPTION          => 'yes',
			)
		);

		// ---- AC-2, the account page --------------------------------------------------------------------------
		$form   = two_factor_http( 'GET', sign_in_account_url( 'en' ) );
		$issued = sign_in_page_token( $form['body'] );
		$check( '' !== $issued['token'] && 'present' === $issued['trap'], 'AC-2 [account] the registration form carries a token and a trap field' );
		$refusals = array(
			'no token'          => array(),
			'a malformed token' => array( 'mizzey_signup_token' => 'not-a-token', 'mizzey_signup_extra' => '' ),
			'an array token'    => array( 'mizzey_signup_token' => array( 'x' ), 'mizzey_signup_extra' => '' ),
			'an oversized token' => array( 'mizzey_signup_token' => str_repeat( 'a', 600 ), 'mizzey_signup_extra' => '' ),
			'a tampered token'  => array( 'mizzey_signup_token' => substr( t47_token( 'account', 10 ), 0, -2 ) . 'xx', 'mizzey_signup_extra' => '' ),
			'a future token'    => array( 'mizzey_signup_token' => SignupCheck::issue( 'account', time() + 120 ), 'mizzey_signup_extra' => '' ),
			'another route\'s token' => array( 'mizzey_signup_token' => t47_token( 'checkout', 10 ), 'mizzey_signup_extra' => '' ),
			'an expired token'  => array( 'mizzey_signup_token' => t47_token( 'account', 7300 ), 'mizzey_signup_extra' => '' ),
			'a filled trap'     => array( 'mizzey_signup_token' => t47_token( 'account', 10 ), 'mizzey_signup_extra' => 'https://spam.example' ),
		);
		foreach ( $refusals as $label => $extra ) {
			$email = $fresh( 'acct' );
			$r     = t47_register( 'en', $email, $extra );
			$check( ! sign_in_account_exists( $email ) && false !== strpos( $r['body'], $message_en ), "AC-2 [account] {$label} creates no account and shows the message" );
		}
		// A token used as soon as the page issued it: fetched and posted with no pause.
		$email   = $fresh( 'acct-fresh' );
		$account = sign_in_account_url( 'en' );
		$page    = two_factor_http( 'GET', $account );
		$r       = two_factor_http( 'POST', $account, array( 'email' => $email, 'password' => 'Pw-' . wp_generate_password( 18, false ), 'woocommerce-register-nonce' => two_factor_field( $page['body'], 'woocommerce-register-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'register' => 'Register', 'mizzey_signup_token' => sign_in_page_token( $page['body'] )['token'], 'mizzey_signup_extra' => '' ), $page['cookies'] );
		$check( ! sign_in_account_exists( $email ) && false !== strpos( $r['body'], $message_en ), 'AC-2 [account] a token used straight away (under 3 seconds) creates no account' );
		$email = $fresh( 'acct-nonce' );
		$r     = t47_register( 'en', $email, array( 'mizzey_signup_token' => t47_token( 'account', 10 ), 'mizzey_signup_extra' => '' ), 'bad-nonce' );
		$check( ! sign_in_account_exists( $email ) && false === strpos( $r['body'], $message_en ), 'AC-2 [account] a failed native nonce is WooCommerce\'s refusal, not the signup check\'s' );
		$token = t47_token( 'account', 10 );
		$email = $fresh( 'acct-ok' );
		t47_register( 'en', $email, array( 'mizzey_signup_token' => $token, 'mizzey_signup_extra' => '' ) );
		$check( sign_in_account_exists( $email ), 'AC-2 [account] a valid token creates the account' );
		$email = $fresh( 'acct-spent' );
		$r     = t47_register( 'en', $email, array( 'mizzey_signup_token' => $token, 'mizzey_signup_extra' => '' ) );
		$check( ! sign_in_account_exists( $email ) && false !== strpos( $r['body'], $message_en ), 'AC-2 [account] the same token a second time creates nothing' );
		$email = $fresh( 'acct-ar' );
		$r     = t47_register( 'ar', $email, array() );
		$check( ! sign_in_account_exists( $email ) && false !== strpos( $r['body'], $message_ar ), 'AC-2 [account, ar] no token creates no account and shows the Arabic message' );
		$email = $fresh( 'acct-ar-ok' );
		t47_register( 'ar', $email, array( 'mizzey_signup_token' => t47_token( 'account', 10 ), 'mizzey_signup_extra' => '' ) );
		$check( sign_in_account_exists( $email ), 'AC-2 [account, ar] a valid token creates the account' );

		// One token, two concurrent requests with different emails: at most one account.
		$token   = t47_token( 'account', 10 );
		$account = sign_in_account_url( 'en' );
		$form    = two_factor_http( 'GET', $account );
		$pair    = array( $fresh( 'race-a' ), $fresh( 'race-b' ) );
		$jar     = array();
		foreach ( $form['cookies'] as $cookie ) {
			$jar[ $cookie->name ] = $cookie->value;
		}
		$requests = array();
		foreach ( $pair as $email ) {
			$requests[] = array(
				'url'     => $account,
				'type'    => 'POST',
				'data'    => array( 'email' => $email, 'password' => 'Pw-' . wp_generate_password( 18, false ), 'woocommerce-register-nonce' => two_factor_field( $form['body'], 'woocommerce-register-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'register' => 'Register', 'mizzey_signup_token' => $token, 'mizzey_signup_extra' => '' ),
				'cookies' => $jar,
			);
		}
		\WpOrg\Requests\Requests::request_multiple( $requests, array( 'timeout' => 60, 'follow_redirects' => false ) );
		$created = count( array_filter( $pair, 'MizzeySite\Tests\Integration\sign_in_account_exists' ) );
		$check( $created <= 1, "AC-2 [account] two concurrent requests with one token create at most one account ({$created})" );

		// ---- AC-2, the block checkout (Store API) ------------------------------------------------------------
		$product = $s->simple_product( 't47 virtual item', '50', null );
		$product->set_virtual( true );
		$product->save();
		$checkout = rest_url( 'wc/store/v1/checkout' );

		$email  = $fresh( 'co-none' );
		$cart   = t47_cart( $product->get_id() );
		$before = t47_latest_order();
		$r      = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, true, null ), $cart['headers'], $cart['cookies'] );
		$check( 400 === $r['status'] && ! sign_in_account_exists( $email ) && t47_latest_order() === $before, "AC-2 [checkout] account creation without the check is refused before any order (status {$r['status']})" );

		$email = $fresh( 'co-ok' );
		$cart  = t47_cart( $product->get_id() );
		$r     = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, true, array( 'token' => t47_token( 'checkout', 10 ), 'trap' => '' ) ), $cart['headers'], $cart['cookies'] );
		$check( 200 === $r['status'] && sign_in_account_exists( $email ), "AC-2 [checkout] with a valid check the order is placed and the account created (status {$r['status']})" );

		$email = $fresh( 'co-trap' );
		$cart  = t47_cart( $product->get_id() );
		$r     = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, true, array( 'token' => t47_token( 'checkout', 10 ), 'trap' => 'x' ) ), $cart['headers'], $cart['cookies'] );
		$check( 400 === $r['status'] && ! sign_in_account_exists( $email ), 'AC-2 [checkout] a filled trap is refused' );

		$email = $fresh( 'co-guest' );
		$cart  = t47_cart( $product->get_id() );
		$r     = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, null, null ), $cart['headers'], $cart['cookies'] );
		$check( 200 === $r['status'] && ! sign_in_account_exists( $email ) && array() !== sign_in_orders_for( $email ), "AC-2 [checkout] a guest order without an account needs no check (status {$r['status']})" );

		// A refused request never reaches the checkout handler, so no order it would create or update (a new one, or a
		// pending or failed order kept in the session for a retry) can change. Dispatched in-process to watch the
		// handler's first order-processing hook.
		$ran   = 0;
		$count = static function () use ( &$ran ): void {
			$ran++;
		};
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', $count );
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( t47_checkout_body( $fresh( 'co-inprocess' ), true, null ) ) );
		$answer = rest_do_request( $request );
		remove_action( 'woocommerce_store_api_checkout_update_order_from_request', $count );
		$code = $answer->as_error() ? $answer->as_error()->get_error_code() : '';
		$check( 400 === $answer->get_status() && 'mizzey_signup_check' === $code && 0 === $ran, "AC-2 [checkout] a refused request is answered before the checkout handler runs (status {$answer->get_status()}, {$code}, handler hooks {$ran})" );

		// A draft order kept in the visitor's session for a retry is left unchanged by a refused request, and is the order
		// the same session then places with a valid check: proof the fixture is the order the Store API reuses.
		$email   = $fresh( 'co-retry' );
		$cart    = t47_cart( $product->get_id() );
		$payload = json_decode( (string) base64_decode( strtr( explode( '.', $cart['headers']['Cart-Token'] )[1] ?? '', '-_', '+/' ) ), true );
		$session = (string) ( $payload['user_id'] ?? '' );
		$draft   = wc_create_order( array( 'status' => 'checkout-draft', 'created_via' => 'store-api' ) );
		$draft->add_product( $product, 1 );
		$draft->calculate_totals();
		$draft->save();
		$s->track_order( $draft->get_id() );
		$sessions = $wpdb->prefix . 'woocommerce_sessions';
		$stored   = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT session_value FROM {$sessions} WHERE session_key = %s", $session ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$placed   = false;
		if ( is_array( $stored ) ) {
			$stored['store_api_draft_order'] = $draft->get_id();
			$placed = false !== $wpdb->update( $sessions, array( 'session_value' => maybe_serialize( $stored ) ), array( 'session_key' => $session ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$stamp  = static fn ( \WC_Order $o ): array => array( $o->get_status(), (string) $o->get_date_modified(), $o->get_billing_email(), $o->get_total(), $o->get_customer_id() );
		$before = $stamp( wc_get_order( $draft->get_id() ) );
		$r      = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, true, null ), $cart['headers'], $cart['cookies'] );
		wp_cache_flush();
		$check( $placed && 400 === $r['status'] && $before === $stamp( wc_get_order( $draft->get_id() ) ), "AC-2 [checkout] a refused request leaves the session's retry order unchanged (draft {$draft->get_id()})" );
		$r = sign_in_json( 'POST', $checkout, t47_checkout_body( $email, true, array( 'token' => t47_token( 'checkout', 10 ), 'trap' => '' ) ), $cart['headers'], $cart['cookies'] );
		$check( 200 === $r['status'] && (int) ( $r['data']['order_id'] ?? 0 ) === $draft->get_id(), 'AC-2 [checkout] and with a valid check that same order is placed (the fixture is the retry order)' );

		// Registration required: create_account omitted still creates an account, so the check applies.
		update_option( 'woocommerce_enable_guest_checkout', 'no' );
		$email = $fresh( 'co-req' );
		$cart  = t47_cart( $product->get_id() );
		$body  = t47_checkout_body( $email, null, null );
		$body['customer_password'] = 'Pw-' . wp_generate_password( 18, false );
		$r = sign_in_json( 'POST', $checkout, $body, $cart['headers'], $cart['cookies'] );
		$check( 400 === $r['status'] && ! sign_in_account_exists( $email ), "AC-2 [checkout, required] with create_account omitted and no check, refused (status {$r['status']})" );
		$email = $fresh( 'co-req-ok' );
		$cart  = t47_cart( $product->get_id() );
		$body  = t47_checkout_body( $email, null, array( 'token' => t47_token( 'checkout', 10 ), 'trap' => '' ) );
		$body['customer_password'] = 'Pw-' . wp_generate_password( 18, false );
		$r = sign_in_json( 'POST', $checkout, $body, $cart['headers'], $cart['cookies'] );
		$check( 200 === $r['status'] && sign_in_account_exists( $email ), "AC-2 [checkout, required] with a valid check the account is created (status {$r['status']})" );
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );

		// A signed-in customer needs no check.
		list( $customer, $customer_pass ) = two_factor_user( $s, 'customer' );
		$signed = sign_in_account( 'en', $customer->user_login, $customer_pass );
		$cart   = t47_cart( $product->get_id(), $signed['cookies'] );
		$r      = sign_in_json( 'POST', $checkout, t47_checkout_body( $customer->user_email, null, null ), $cart['headers'], $cart['cookies'] );
		foreach ( sign_in_orders_for( $customer->user_email ) as $order_id ) {
			$s->track_order( $order_id );
		}
		$check( 200 === $r['status'], "AC-2 [checkout] a signed-in customer's checkout needs no check (status {$r['status']})" );

		// The predicate follows WooCommerce's filtered checkout settings, not the raw options.
		add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );
		$off = SignupRoutes::checkoutCreatesAccount( array( 'create_account' => true ) );
		remove_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
		add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
		add_filter( 'woocommerce_checkout_registration_required', '__return_true' );
		$on = SignupRoutes::checkoutCreatesAccount( array() );
		remove_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );
		remove_filter( 'woocommerce_checkout_registration_required', '__return_true' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
		$check( false === $off && true === $on, 'AC-2 [checkout] the predicate follows the effective registration filters against contrary options' );

		// ---- AC-2, the order-received account offer ------------------------------------------------------------
		$offer = function ( string $email, array $extra ) use ( $s, $product ): array {
			$order = wc_create_order();
			$order->add_product( $product, 1 );
			$order->set_billing_email( $email );
			$order->set_billing_first_name( 'Test' );
			$order->calculate_totals();
			$order->set_status( 'processing' );
			$order->save();
			$s->track_order( $order->get_id() );
			$url = $order->get_checkout_order_received_url();
			return two_factor_http( 'POST', $url, array_merge( array( 'create-account' => '1', 'email' => $email, 'password' => 'Pw-' . wp_generate_password( 18, false ), '_wpnonce' => wp_create_nonce( 'wc_create_account' ) ), $extra ) );
		};
		$email = $fresh( 'or-none' );
		$offer( $email, array() );
		$check( ! sign_in_account_exists( $email ), 'AC-2 [order-received] without the check no account is created' );
		$email = $fresh( 'or-route' );
		$offer( $email, array( 'mizzey_signup_token' => t47_token( 'account', 10 ), 'mizzey_signup_extra' => '' ) );
		$check( ! sign_in_account_exists( $email ), 'AC-2 [order-received] another route\'s token is refused' );
		$email = $fresh( 'or-ok' );
		$offer( $email, array( 'mizzey_signup_token' => t47_token( 'order-received', 10 ), 'mizzey_signup_extra' => '' ) );
		$check( sign_in_account_exists( $email ), 'AC-2 [order-received] with a valid check the account is created' );

		// ---- AC-2, the classic checkout's handlers stay closed to account creation -----------------------------
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 't47 classic checkout', 'post_content' => '[woocommerce_checkout]' ) );
		$s->on_finish( static function () use ( $page ): void { wp_delete_post( $page, true ); } );
		$jar  = two_factor_http( 'GET', add_query_arg( 'add-to-cart', $product->get_id(), home_url( '/' ) ) );
		$form = two_factor_http( 'GET', get_permalink( $page ), array(), $jar['cookies'] );
		$email = $fresh( 'classic' );
		$classic = two_factor_http(
			'POST',
			add_query_arg( 'wc-ajax', 'checkout', home_url( '/' ) ),
			array( 'billing_first_name' => 'Test', 'billing_last_name' => 'Buyer', 'billing_country' => 'EG', 'billing_address_1' => '1 Test Street', 'billing_city' => 'Cairo', 'billing_state' => 'EG-01', 'billing_postcode' => '11511', 'billing_phone' => '01000000000', 'billing_email' => $email, 'createaccount' => '1', 'account_password' => 'Pw-' . wp_generate_password( 18, false ), 'payment_method' => 'cod', 'woocommerce-process-checkout-nonce' => two_factor_field( $form['body'], 'woocommerce-process-checkout-nonce' ), 'terms' => '1' ),
			$form['cookies']
		);
		$check( '' !== two_factor_field( $form['body'], 'woocommerce-process-checkout-nonce' ) && ! sign_in_account_exists( $email ) && array() === sign_in_orders_for( $email ), 'AC-2 [classic checkout] with a valid native nonce, no account and no order' );

		// ---- AC-2, WordPress's own registration, opened for one case --------------------------------------
		sign_in_options( $s, array( 'users_can_register' => '1' ) );
		$wp_form = two_factor_http( 'GET', add_query_arg( 'action', 'register', site_url( 'wp-login.php', 'login' ) ) );
		$login   = 't4x_wpreg_' . wp_rand();
		$email   = $fresh( 'wpreg' );
		two_factor_http( 'POST', add_query_arg( 'action', 'register', site_url( 'wp-login.php', 'login_post' ) ), array( 'user_login' => $login, 'user_email' => $email ), $wp_form['cookies'] );
		$check( 'present' === sign_in_page_token( $wp_form['body'] )['trap'] && ! sign_in_account_exists( $email ), 'AC-2 [wordpress] the form carries the check, and without it no account is created' );
		$email = $fresh( 'wpreg-ok' );
		two_factor_http( 'POST', add_query_arg( 'action', 'register', site_url( 'wp-login.php', 'login_post' ) ), array( 'user_login' => $login . 'b', 'user_email' => $email, 'mizzey_signup_token' => t47_token( 'wordpress', 10 ), 'mizzey_signup_extra' => '' ), $wp_form['cookies'] );
		$check( sign_in_account_exists( $email ), 'AC-2 [wordpress] with a valid check the account is created' );
		update_option( 'users_can_register', '0' );

		// ---- The verifier, in-process --------------------------------------------------------------------------
		$now = time();
		$at  = static fn ( int $age ): string => SignupCheck::issue( 'account', $now - $age );
		$check( null === SignupCheck::inspect( 'account', $at( 2 ), '', $now ) && null !== SignupCheck::inspect( 'account', $at( 3 ), '', $now ), 'FR-005 the minimum age is 3 seconds, inclusive' );
		$check( null !== SignupCheck::inspect( 'account', $at( 7200 ), '', $now ) && null === SignupCheck::inspect( 'account', $at( 7201 ), '', $now ), 'FR-005 the maximum age is 7,200 seconds, inclusive' );
		$claimed = $at( 10 );
		$first   = SignupCheck::verify( 'account', $claimed, '' );
		wp_cache_flush();
		$check( $first && ! SignupCheck::verify( 'account', $claimed, '' ), 'FR-005 a token is single-use, and clearing caches does not restore it' );
		$break = static fn ( string $q ): string => ( str_contains( $q, 'mizzey_signup_claim_' ) && 0 === stripos( ltrim( $q ), 'INSERT' ) ) ? 'INSERT INTO mizzey_no_such_table VALUES (1)' : $q;
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );
		$stored   = SignupCheck::verify( 'account', $at( 10 ), '' );
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );
		$check( ! $stored, 'FR-005 when the claim cannot be stored, the check refuses' );

		// ---- The guard and its permits, in-process ----------------------------------------------------------
		add_filter( 'mizzey_signup_trust_cli', '__return_false' );
		wp_set_current_user( 0 );
		$plain = wc_create_new_customer( $fresh( 'guard-anon' ), '', 'Pw-' . wp_generate_password( 18, false ) );
		$check( is_wp_error( $plain ), 'FR-003 an anonymous insertion with no verified route is refused' );
		$permit_email = $fresh( 'guard-permit' );
		SignupRoutes::grant( 'account', $permit_email );
		$one   = wc_create_new_customer( $permit_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		$two   = wc_create_new_customer( $fresh( 'guard-second' ), '', 'Pw-' . wp_generate_password( 18, false ) );
		SignupRoutes::grant( 'account', $fresh( 'guard-expected' ) );
		$other = wc_create_new_customer( $fresh( 'guard-other' ), '', 'Pw-' . wp_generate_password( 18, false ) );
		SignupRoutes::clearPermits();
		$check( ! is_wp_error( $one ) && is_wp_error( $two ) && is_wp_error( $other ), 'FR-003 one permit allows one insertion, for its own email only' );
		$thrown = false;
		try {
			SignupRoutes::insideOrderReceived( static function () { throw new \RuntimeException( 'render failed' ); } );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}
		$after_email = $fresh( 'guard-after-throw' );
		$_POST       = array( 'create-account' => '1', 'email' => $after_email, 'mizzey_signup_token' => t47_token( 'order-received', 10 ), 'mizzey_signup_extra' => '' );
		$after       = wc_create_new_customer( $after_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		$_POST = array();
		$check( $thrown && is_wp_error( $after ), 'FR-003 a render callback that throws leaves no order-received authorisation behind' );
		// An account-page permit does not survive a later refusal in the same registration.
		if ( null === WC()->session ) {
			WC()->initialize_session();
		}
		$later_email = $fresh( 'guard-later' );
		$later       = static function ( \WP_Error $error ): \WP_Error {
			$error->add( 't47_later', 'refused by a later check' );
			return $error;
		};
		add_filter( 'woocommerce_process_registration_errors', $later, 20 );
		$_POST = array( 'register' => 'Register', 'email' => $later_email, 'password' => 'Pw-' . wp_generate_password( 18, false ), 'woocommerce-register-nonce' => wp_create_nonce( 'woocommerce-register' ), 'mizzey_signup_token' => t47_token( 'account', 10 ), 'mizzey_signup_extra' => '' );
		SignupRoutes::processAccountRegistration();
		$_POST = array();
		remove_filter( 'woocommerce_process_registration_errors', $later, 20 );
		wc_clear_notices();
		$reused = wc_create_new_customer( $later_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		$check( ! sign_in_account_exists( $later_email ) || is_wp_error( $reused ), 'FR-003 after a later refusal, the account permit cannot be used by another operation' );
		$check( is_wp_error( $reused ), 'FR-003 and the second attempt is refused' );

		// A checkout permit does not outlive the checkout handler, and a nested request cannot use its parent's.
		$co_email = $fresh( 'guard-checkout' );
		$request  = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( t47_checkout_body( $co_email, true, array( 'token' => t47_token( 'checkout', 10 ), 'trap' => '' ) ) ) );
		rest_do_request( $request );
		$open   = SignupRoutes::openFrames();
		$leaked = wc_create_new_customer( $co_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		$check( 0 === $open['frames'] && is_wp_error( $leaked ), 'FR-003 after the checkout handler ends, no frame or permit remains (frames ' . $open['frames'] . ')' );
		$parent       = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$parent_email = $fresh( 'guard-parent' );
		$parent->set_param( 'create_account', true );
		$parent->set_param( 'billing_address', array( 'email' => $parent_email ) );
		$parent->set_param( 'extensions', array( 'mizzey-signup' => array( 'token' => t47_token( 'checkout', 10 ), 'trap' => '' ) ) );
		$checkout_handler = null;
		foreach ( rest_get_server()->get_routes()['/wc/store/v1/checkout'] ?? array() as $candidate ) {
			if ( isset( $candidate['methods']['POST'] ) ) {
				$checkout_handler = $candidate;
			}
		}
		SignupRoutes::restBefore( null, (array) $checkout_handler, $parent );
		$child = new \WP_REST_Request( 'GET', '/wp/v2/types' );
		SignupRoutes::restBefore( null, array( 'callback' => '__return_true' ), $child );
		$from_child = wc_create_new_customer( $parent_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		SignupRoutes::restAfter( null, array(), $child );
		$from_parent = wc_create_new_customer( $parent_email, '', 'Pw-' . wp_generate_password( 18, false ) );
		SignupRoutes::restAfter( null, array(), $parent );
		$check( null !== $checkout_handler && is_wp_error( $from_child ) && ! is_wp_error( $from_parent ), 'FR-003 a nested request cannot use its parent\'s permit; the parent still can after the child ends' );

		list( $plain_customer ) = two_factor_user( $s, 'customer' );
		wp_set_current_user( $plain_customer->ID );
		$by_customer = wc_create_new_customer( $fresh( 'guard-customer' ), '', 'Pw-' . wp_generate_password( 18, false ) );
		list( $manager ) = two_factor_user( $s, 'shop_manager' );
		wp_set_current_user( $manager->ID );
		$by_manager = wc_create_new_customer( $fresh( 'guard-manager' ), '', 'Pw-' . wp_generate_password( 18, false ) );
		wp_set_current_user( 0 );
		remove_filter( 'mizzey_signup_trust_cli', '__return_false' );
		$check( is_wp_error( $by_customer ) && ! is_wp_error( $by_manager ), 'FR-003 a signed-in customer cannot create accounts; a shop manager (create_customers) can' );
		$cli_email = $fresh( 'guard-cli' );
		$cli       = sign_in_wp( 'eval ' . escapeshellarg( "echo is_wp_error( wc_create_new_customer( '" . $cli_email . "', '', 'Pw-cli-" . wp_rand() . "' ) ) ? 'refused' : 'created';" ) );
		$check( false !== strpos( $cli['out'], 'created' ), 'FR-003 WP-CLI keeps its native behaviour' );

		// ---- AC-4: the security check ------------------------------------------------------------------------
		$report = sign_in_wp( 'mizzey security check' );
		foreach ( array( 'signup_check', 'account_writers', 'wordpress_registration' ) as $row ) {
			$check( 'pass' === t47_row( $report['out'], $row ), "AC-4 {$row} passes" );
		}
		$unhooked = sign_in_wp( 'eval ' . escapeshellarg( "remove_all_filters( 'woocommerce_process_registration_errors' ); WP_CLI::run_command( array( 'mizzey', 'security', 'check' ) );" ) );
		$check( 'review' === t47_row( $unhooked['out'], 'signup_check' ), 'AC-4 with the account adapter removed, signup_check says review' );
		sign_in_options( $s, array( 'woocommerce_customer_stock_notifications_allow_signups' => 'yes', 'woocommerce_customer_stock_notifications_create_account_on_signup' => 'yes' ) );
		$check( 'review' === t47_row( sign_in_wp( 'mizzey security check' )['out'], 'account_writers' ), 'AC-4 with stock-notification account creation on, account_writers says review' );
		update_option( 'woocommerce_customer_stock_notifications_create_account_on_signup', 'no' );
		update_option( 'users_can_register', '1' );
		$open = sign_in_wp( 'eval ' . escapeshellarg( "remove_all_filters( 'registration_errors' ); WP_CLI::run_command( array( 'mizzey', 'security', 'check' ) );" ) );
		$check( 'review' === t47_row( $open['out'], 'wordpress_registration' ), 'AC-4 with WordPress registration open and unchecked, wordpress_registration says review' );
		update_option( 'users_can_register', '0' );

		return $ok;
	}
);

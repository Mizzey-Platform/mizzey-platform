<?php
/**
 * t49, AUTH-15 (AC-2 in a browser; FR-002): the forms people use, and the sign-in flows with the limit on.
 *
 * In Chromium (mizzey-site/tests/browser/sign-in-flows.cjs): the account page's registration in English and Arabic,
 * the block checkout with account creation, and the order-received account offer each create the account when a
 * person submits them, carrying the check (the checkout's Store API request has `extensions.mizzey-signup`) with the
 * trap left empty and hidden. Then, with CoreX's limit on: every sign-in link the home, account and checkout pages
 * print resolves; the account page's sign-in, sign-out, lost password and the reset link work in both languages;
 * wp-login.php and CoreX's address sign in; WP 2FA's challenge completes with a computed code. Settings, accounts and
 * orders are restored or removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** A page's URL in a language. */
function t49_in( string $lang, callable $url ): string {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$out = (string) $url();
	do_action( 'wpml_switch_language', $current );
	return $out;
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t49_browser( array $plan ): array {
	$file = wp_tempnam( 't49-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/sign-in-flows.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", $out ) );
}

run(
	new Scenario( 't49-sign-in-browser', 'AUTH-15: the account forms and the sign-in flows work in a real browser in both languages' ),
	function ( Scenario $s ): bool {
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
		$cod = get_option( 'woocommerce_cod_settings', array() );
		sign_in_options(
			$s,
			array(
				'woocommerce_enable_myaccount_registration'         => 'yes',
				'woocommerce_registration_generate_username'        => 'yes',
				'woocommerce_registration_generate_password'        => 'no',
				'woocommerce_enable_delayed_account_creation'       => 'yes',
				'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
				'woocommerce_enable_guest_checkout'                 => 'yes',
				'woocommerce_cod_settings'                          => array_merge( is_array( $cod ) ? $cod : array(), array( 'enabled' => 'yes', 'enable_for_virtual' => 'yes' ) ),
			)
		);
		$product = $s->simple_product( 't49 virtual item', '50', null );
		$product->set_virtual( true );
		$product->save();
		$order_email = $fresh( 'br-offer' );
		$order       = wc_create_order();
		$order->add_product( $product, 1 );
		$order->set_billing_email( $order_email );
		$order->set_billing_first_name( 'Test' );
		$order->calculate_totals();
		$order->set_status( 'processing' );
		$order->save();
		$s->track_order( $order->get_id() );

		// ---- AC-2: the three forms, as a person submits them ---------------------------------------------------
		$forms = t49_browser(
			array(
				'part'          => 'forms',
				'password'      => 'Pw-' . wp_generate_password( 18, false ),
				'account'       => array(
					'en' => array( 'url' => sign_in_account_url( 'en' ), 'email' => $fresh( 'br-acct-en' ) ),
					'ar' => array( 'url' => sign_in_account_url( 'ar' ), 'email' => $fresh( 'br-acct-ar' ) ),
				),
				'checkout'      => array(
					'addToCart' => add_query_arg( 'add-to-cart', $product->get_id(), home_url( '/' ) ),
					'url'       => wc_get_checkout_url(),
					'email'     => $fresh( 'br-checkout' ),
					'state'     => 'EG-01',
					'billing'   => array( 'first_name' => 'Test', 'last_name' => 'Buyer', 'address_1' => '1 Test Street', 'city' => 'Cairo', 'postcode' => '11511', 'phone' => '01000000000' ),
				),
				'screenshotDir' => get_temp_dir(),
				'orderReceived' => array( 'url' => $order->get_checkout_order_received_url(), 'email' => $order_email ),
			)
		);
		$check( empty( $forms['error'] ), 'the browser ran the forms' . ( empty( $forms['error'] ) ? '' : ': ' . substr( (string) $forms['error'], 0, 400 ) ) );
		foreach ( (array) ( $forms['forms'] ?? array() ) as $row ) {
			$where = $row['route'] . ( isset( $row['lang'] ) ? ', ' . $row['lang'] : '' );
			$s->note( '      ' . wp_json_encode( $row ) );
			if ( 'account' === $row['route'] ) {
				$check( 1 === (int) $row['tokenField'] && 1 === (int) $row['trapField'] && false === $row['trapVisible'], "AC-2 [{$where}] the form carries the check, its trap hidden from people" );
			}
			if ( 'checkout' === $row['route'] ) {
				$check( true === ( $row['payloadHasCheck'] ?? null ) && true === ( $row['payloadTrapEmpty'] ?? null ) && 200 === ( $row['checkoutStatus'] ?? 0 ), "AC-2 [{$where}] the Store API request carries the check and succeeds" );
			}
			if ( 'order-received' === $row['route'] ) {
				$check( (int) $row['formFound'] > 0 && (int) $row['tokenField'] > 0, "AC-2 [{$where}] the rendered form carries the check" );
			}
			$check( sign_in_account_exists( (string) $row['email'] ), "AC-2 [{$where}] a person's submission creates the account" );
		}

		// ---- FR-002: the sign-in flows with the limit on --------------------------------------------------------
		sign_in_baseline( $s );
		two_factor_baseline( $s );
		list( $staff, $staff_pass ) = two_factor_user( $s, 'administrator' );
		two_factor_enrol( $staff );
		$secret     = (string) \WP2FA\Methods\TOTP::get_totp_decrypted( $staff );
		$identities = array( $staff->user_login );
		$customers  = array();
		foreach ( array( 'en', 'ar' ) as $lang ) {
			list( $c, $c_pass ) = two_factor_user( $s, 'customer' );
			$identities[]       = $c->user_login;
			$customers[ $lang ] = array( 'user' => $c, 'password' => $c_pass );
		}
		list( $wp_customer, $wp_pass ) = two_factor_user( $s, 'customer' );
		$identities[] = $wp_customer->user_login;
		$s->on_finish( static function () use ( $identities ) { sign_in_forget( $identities ); } );
		$sign_in = array( 'entries' => array() );
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$who            = $customers[ $lang ]['user'];
			$account        = sign_in_account_url( $lang );
			$sign_in[ $lang ] = array(
				'pages'        => array( t49_in( $lang, static fn () => home_url( '/' ) ), $account, t49_in( $lang, static fn () => wc_get_checkout_url() ) ),
				'account'      => $account,
				'lostPassword' => t49_in( $lang, static fn () => wc_lostpassword_url() ),
				'resetLink'    => add_query_arg( array( 'key' => get_password_reset_key( $who ), 'id' => $who->ID ), t49_in( $lang, static fn () => wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) ) ) ),
				'customer'     => array( 'login' => $who->user_login, 'password' => $customers[ $lang ]['password'] ),
			);
		}
		$sign_in['entries'][] = array( 'name' => 'wp-login.php', 'url' => home_url( '/wp-login.php' ), 'login' => $wp_customer->user_login, 'password' => $wp_pass, 'check' => sign_in_account_url( 'en' ), 'staff' => false );
		$sign_in['entries'][] = array( 'name' => 'CoreX address', 'url' => home_url( '/corex-login/' ), 'login' => $wp_customer->user_login, 'password' => $wp_pass, 'check' => sign_in_account_url( 'en' ), 'staff' => false );
		$sign_in['entries'][] = array( 'name' => 'two-factor', 'url' => home_url( '/wp-login.php' ), 'login' => $staff->user_login, 'password' => $staff_pass, 'secret' => $secret, 'check' => admin_url( 'profile.php' ), 'staff' => true );

		$flows = t49_browser( array( 'part' => 'flows', 'signIn' => $sign_in ) );
		$check( empty( $flows['error'] ), 'the browser ran the flows' . ( empty( $flows['error'] ) ? '' : ': ' . substr( (string) $flows['error'], 0, 400 ) ) );
		$broken = array();
		foreach ( (array) ( $flows['flows'] ?? array() ) as $row ) {
			if ( 'printed link' === $row['flow'] ) {
				if ( 200 !== (int) $row['status'] ) {
					$broken[] = $row['href'] . ' -> ' . $row['status'];
				}
				continue;
			}
			$s->note( '      ' . wp_json_encode( $row ) );
			$lang = $row['lang'] ?? '';
			$lang_ok = '' === $lang || ( 'ar' === $lang ? str_starts_with( (string) ( $row['htmlLang'] ?? '' ), 'ar' ) : ! str_starts_with( (string) ( $row['htmlLang'] ?? '' ), 'ar' ) );
			switch ( $row['flow'] ) {
				case 'account sign-in':
					$check( true === $row['signedIn'] && $lang_ok, "FR-002 [{$lang}] the account page's sign-in works, in its language" );
					break;
				case 'sign-out':
					$check( true === $row['signedOut'] && $lang_ok, "FR-002 [{$lang}] sign-out works, in its language" );
					break;
				case 'lost password':
					$check( true === $row['confirmed'] && $lang_ok, "FR-002 [{$lang}] lost password sends the link, in its language" );
					break;
				case 'reset link':
					$check( true === $row['form'] && $lang_ok, "FR-002 [{$lang}] the reset link opens the reset form, in its language" );
					break;
				case 'wp sign-in':
					$expect_challenge = 'two-factor' === $row['entry'];
					$check( true === $row['signedIn'] && ( ! $expect_challenge || true === $row['challenge'] ), "FR-002 [{$row['entry']}] signs in" . ( $expect_challenge ? ' through the challenge' : '' ) );
					break;
			}
		}
		$check( array() === $broken, 'FR-002 every sign-in link the pages print resolves' . ( $broken ? ': ' . implode( '; ', $broken ) : '' ) );

		return $ok;
	}
);

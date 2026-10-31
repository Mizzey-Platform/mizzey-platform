<?php
/**
 * t65: Google sign-in (AUTH-10) and one account per email (AUTH-13). See specs/024-google-sign-in.
 *
 * Google's token endpoint is mocked in this process (`pre_http_request`); no request reaches Google. Over HTTP only the
 * start, which never contacts Google, and a refused callback.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Accounts\GoogleSignIn;
use MizzeySite\Accounts\VerificationOnRegistration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** An unsigned ID token carrying the claims (the flow trusts the token endpoint's TLS, not a signature). */
function t65_token( array $claims ): string {
	$part = static fn ( array $data ): string => rtrim( strtr( base64_encode( (string) wp_json_encode( $data ) ), '+/', '-_' ), '=' );
	return $part( array( 'alg' => 'RS256' ) ) . '.' . $part( $claims ) . '.sig';
}

function t65_user( Scenario $s, string $email, string $role, string $password ): int {
	$id = wp_insert_user( array( 'user_login' => sanitize_user( strtok( $email, '@' ) ), 'user_email' => $email, 'user_pass' => $password, 'role' => $role ) );
	if ( is_wp_error( $id ) || (int) $id <= 1 ) {
		return 0;
	}
	$s->track_user( (int) $id );
	return (int) $id;
}

run(
	new Scenario( 't65-google-sign-in', 'AUTH-10, AUTH-13: Google sign-in, one account per email' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't65' . wp_rand( 1000, 9999 );
		sign_in_options( $s, array( GoogleSignIn::CLIENT_ID => '', GoogleSignIn::CLIENT_SECRET => '' ) );

		// Off until configured.
		$off = rest_do_request( new \WP_REST_Request( 'GET', '/mizzey/v1/google/start' ) );
		ob_start();
		GoogleSignIn::button();
		$no_button = (string) ob_get_clean();
		$check( ! GoogleSignIn::enabled() && 302 === $off->get_status() && false !== strpos( (string) ( $off->get_headers()['Location'] ?? '' ), 'mizzey-google=unavailable' ) && false === strpos( $no_button, 'google/start' ), 'AUTH-10 Google sign-in is off, and offers no button, until the client ID and secret are set' );

		update_option( GoogleSignIn::CLIENT_ID, "{$tag}-client.apps.googleusercontent.com" );
		update_option( GoogleSignIn::CLIENT_SECRET, "{$tag}-secret" );
		$client = "{$tag}-client.apps.googleusercontent.com";

		// [HTTP] The start sends the visitor to Google with a state bound to a cookie, a nonce and PKCE.
		$start = two_factor_http( 'GET', add_query_arg( 'redirect_to', rawurlencode( home_url( '/shop/' ) ), rest_url( 'mizzey/v1/google/start' ) ) );
		parse_str( (string) wp_parse_url( (string) $start['location'], PHP_URL_QUERY ), $asked );
		$bound = array_values( array_filter( sign_in_jar( $start['cookies'] ), static fn ( $c ) => GoogleSignIn::COOKIE === $c->name ) );
		$check( 302 === $start['status'] && str_starts_with( (string) $start['location'], GoogleSignIn::AUTHORIZE ) && $client === ( $asked['client_id'] ?? '' ) && GoogleSignIn::redirectUri() === ( $asked['redirect_uri'] ?? '' ) && 'S256' === ( $asked['code_challenge_method'] ?? '' ) && 43 <= strlen( (string) ( $asked['code_challenge'] ?? '' ) ) && '' !== ( $asked['nonce'] ?? '' ) && '' !== ( $asked['state'] ?? '' ) && isset( $bound[0] ) && $bound[0]->value === $asked['state'], '[HTTP] AUTH-10 the start sends the visitor to Google with the client, the return address, a state bound to a cookie, a nonce and a PKCE challenge (' . $start['status'] . ')' );

		// [HTTP] A callback without the state's cookie is refused, and the state cannot be used again.
		$forged = two_factor_http( 'GET', add_query_arg( array( 'state' => $asked['state'] ?? '', 'code' => 'x' ), GoogleSignIn::redirectUri() ) );
		$check( 302 === $forged['status'] && false !== strpos( (string) $forged['location'], 'mizzey-google=refused' ) && false === get_transient( 'mizzey_google_' . hash( 'sha256', (string) ( $asked['state'] ?? '' ) ) ), '[HTTP] AUTH-10 a callback without the browser\'s state cookie is refused, and its state is spent (' . $forged['status'] . ')' );

		// The ID token's checks.
		$good    = array( 'iss' => 'https://accounts.google.com', 'aud' => $client, 'exp' => time() + 300, 'nonce' => 'n-1', 'sub' => "{$tag}-sub-1", 'email' => "{$tag}-new@example.invalid", 'email_verified' => true, 'hd' => 'example.invalid', 'given_name' => 'Hana', 'family_name' => 'Adel' );
		$valid   = GoogleSignIn::claims( t65_token( $good ), 'n-1' );
		$refused = array(
			'issuer'   => GoogleSignIn::claims( t65_token( array( 'iss' => 'https://evil.example' ) + $good ), 'n-1' ),
			'audience' => GoogleSignIn::claims( t65_token( array( 'aud' => 'other-client' ) + $good ), 'n-1' ),
			'expired'  => GoogleSignIn::claims( t65_token( array( 'exp' => time() - 5 ) + $good ), 'n-1' ),
			'nonce'    => GoogleSignIn::claims( t65_token( $good ), 'n-2' ),
			'azp'      => GoogleSignIn::claims( t65_token( array( 'azp' => 'other-client' ) + $good ), 'n-1' ),
			'garbage'  => GoogleSignIn::claims( 'not-a-token', 'n-1' ),
		);
		$passed = array_keys( array_filter( $refused, static fn ( $r ) => ! is_wp_error( $r ) ) );
		$check( is_array( $valid ) && array() === $passed, 'AUTH-10 an ID token is taken only with Google\'s issuer, this client as audience and as the party it was issued to, an expiry ahead and the nonce sent' . ( array() === $passed ? '' : ' (passed: ' . implode( ', ', $passed ) . ')' ) );

		// The code exchange, against a mocked token endpoint.
		$sent = array();
		$mock = static function ( $pre, $args, $url ) use ( &$sent, &$good ) {
			if ( GoogleSignIn::TOKEN !== $url ) {
				return $pre;
			}
			$sent = (array) ( $args['body'] ?? array() );
			$ok   = 'good-code' === ( $sent['code'] ?? '' );
			return array( 'response' => array( 'code' => $ok ? 200 : 400, 'message' => '' ), 'body' => (string) wp_json_encode( $ok ? array( 'id_token' => t65_token( $good ) ) : array( 'error' => 'invalid_grant' ) ), 'headers' => array(), 'cookies' => array() );
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		$s->on_finish( static function () use ( $mock ) { remove_filter( 'pre_http_request', $mock, 10 ); } );
		$exchanged = GoogleSignIn::exchange( 'good-code', 'v-1', 'n-1' );
		$bad_code  = GoogleSignIn::exchange( 'bad-code', 'v-1', 'n-1' );
		$check( is_array( $exchanged ) && "{$tag}-sub-1" === ( $exchanged['sub'] ?? '' ) && is_wp_error( $bad_code ) && 'v-1' === ( $sent['code_verifier'] ?? '' ) && 'authorization_code' === ( $sent['grant_type'] ?? '' ), 'AUTH-10 the code is exchanged server to server with the PKCE verifier; a refused code signs no one in' );

		// AUTH-13: one account per email.
		$password = 'Pw-' . wp_generate_password( 18, false );
		$guest    = wc_create_order();
		$guest->set_billing_email( "{$tag}-new@example.invalid" );
		$guest->save();
		$s->track_order( $guest->get_id() );
		$created  = GoogleSignIn::account( $good );
		$again    = GoogleSignIn::account( array( 'email' => "{$tag}-changed@example.invalid" ) + $good );
		if ( $created instanceof \WP_User ) {
			$s->track_user( $created->ID );
		}
		$check( $created instanceof \WP_User && array( 'customer' ) === array_values( $created->roles ) && "{$tag}-new@example.invalid" === $created->user_email && "{$tag}-sub-1" === get_user_meta( $created->ID, GoogleSignIn::SUBJECT, true ) && VerificationOnRegistration::verified( $created->ID ) && 'Hana' === get_user_meta( $created->ID, 'first_name', true ) && $again instanceof \WP_User && $again->ID === $created->ID && $created->ID === (int) wc_get_order( $guest->get_id() )->get_customer_id(), 'AUTH-10, AUTH-13 a new Google user becomes one customer account, its email verified and its earlier guest order linked; signing in again, even with a changed Google email, is the same account' );

		$known_id  = t65_user( $s, "{$tag}-known@example.invalid", 'customer', $password );
		$class     = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\EmailVerificationService';
		wc_get_container()->get( $class )->mark_verified( $known_id );
		$known     = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-2", 'email' => "{$tag}-known@example.invalid" ) + $good );
		$kept      = $known instanceof \WP_User && wp_check_password( $password, get_userdata( $known_id )->user_pass, $known_id );
		$squat_id  = t65_user( $s, "{$tag}-squat@example.invalid", 'customer', $password );
		$squat_token = \WP_Session_Tokens::get_instance( $squat_id )->create( time() + HOUR_IN_SECONDS );
		$squatted  = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-3", 'email' => "{$tag}-squat@example.invalid" ) + $good );
		clean_user_cache( $squat_id );
		$replaced  = $squatted instanceof \WP_User && $squatted->ID === $squat_id && ! wp_check_password( $password, get_userdata( $squat_id )->user_pass, $squat_id ) && VerificationOnRegistration::verified( $squat_id ) && ! \WP_Session_Tokens::get_instance( $squat_id )->verify( $squat_token );
		$check( $known instanceof \WP_User && $known->ID === $known_id && $kept && $replaced, 'AUTH-13 a Google sign-in with the email of an existing customer signs in to that account; one whose email was never verified here has its password replaced and its sessions ended first, so whoever registered it cannot keep it' );

		// An address Google is not the authority for: never linked to an existing account, and a new account from it is
		// not taken as verified.
		$outside_id = t65_user( $s, "{$tag}-outside@example.invalid", 'customer', $password );
		$outside    = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-6", 'email' => "{$tag}-outside@example.invalid", 'hd' => '' ) + $good );
		$fresh      = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-7", 'email' => "{$tag}-fresh@example.invalid", 'hd' => '' ) + $good );
		if ( $fresh instanceof \WP_User ) {
			$s->track_user( $fresh->ID );
		}
		$check( is_wp_error( $outside ) && 'mizzey_google_link' === $outside->get_error_code() && '' === get_user_meta( $outside_id, GoogleSignIn::SUBJECT, true ) && is_wp_error( $fresh ) && false === get_user_by( 'email', "{$tag}-fresh@example.invalid" ), 'AUTH-13 an email Google is not the authority for (not Gmail, not the token\'s Workspace domain) signs in to nothing: it is never linked to an existing account, and makes none' );

		$staff_id = t65_user( $s, "{$tag}-staff@example.invalid", 'shop_manager', $password );
		$staff    = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-4", 'email' => "{$tag}-staff@example.invalid" ) + $good );
		$unproven = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-5", 'email' => "{$tag}-unproven@example.invalid", 'email_verified' => false ) + $good );
		$check( $staff_id > 0 && is_wp_error( $staff ) && 'mizzey_google_staff' === $staff->get_error_code() && '' === get_user_meta( $staff_id, GoogleSignIn::SUBJECT, true ) && is_wp_error( $unproven ) && false === get_user_by( 'email', "{$tag}-unproven@example.invalid" ), 'AUTH-10 a staff account cannot be signed in to with Google, and an email Google has not verified makes no account' );

		// A good code with a state that is not this browser's: only the state's binding to the cookie refuses it.
		$other_start = new \WP_REST_Request( 'GET', '/mizzey/v1/google/start' );
		parse_str( (string) wp_parse_url( (string) ( rest_do_request( $other_start )->get_headers()['Location'] ?? '' ), PHP_URL_QUERY ), $other_flow );
		$other_saved   = get_transient( 'mizzey_google_' . hash( 'sha256', (string) ( $other_flow['state'] ?? '' ) ) );
		$good['nonce'] = (string) ( $other_saved['nonce'] ?? '' );
		$_COOKIE[ GoogleSignIn::COOKIE ] = 'someone-elses-state';
		$crossed = new \WP_REST_Request( 'GET', '/mizzey/v1/google/callback' );
		$crossed->set_param( 'state', (string) ( $other_flow['state'] ?? '' ) );
		$crossed->set_param( 'code', 'good-code' );
		$crossed_answer = rest_do_request( $crossed );
		$crossed_user   = get_current_user_id();
		unset( $_COOKIE[ GoogleSignIn::COOKIE ] );
		wp_set_current_user( 0 );
		$check( 0 === $crossed_user && false !== strpos( (string) ( $crossed_answer->get_headers()['Location'] ?? '' ), 'mizzey-google=refused' ), 'AUTH-10 a good code with a state that is not bound to this browser\'s cookie signs no one in (login CSRF)' );

		// The callback, in this process with the start's state and cookie: signs in and returns to where it began.
		add_filter( 'send_auth_cookies', '__return_false' );
		$s->on_finish( static function () { remove_filter( 'send_auth_cookies', '__return_false' ); } );
		$begin = new \WP_REST_Request( 'GET', '/mizzey/v1/google/start' );
		$begin->set_param( 'redirect_to', home_url( '/shop/' ) );
		parse_str( (string) wp_parse_url( (string) ( rest_do_request( $begin )->get_headers()['Location'] ?? '' ), PHP_URL_QUERY ), $flow );
		$saved = get_transient( 'mizzey_google_' . hash( 'sha256', (string) ( $flow['state'] ?? '' ) ) );
		$good['nonce'] = (string) ( $saved['nonce'] ?? '' );
		$good['sub']   = "{$tag}-sub-8";
		$good['email'] = "{$tag}-callback@example.invalid";
		// The sign-up check as an anonymous request meets it, not as WP-CLI.
		add_filter( 'mizzey_signup_trust_cli', '__return_false' );
		$_COOKIE[ GoogleSignIn::COOKIE ] = (string) ( $flow['state'] ?? '' );
		$back = new \WP_REST_Request( 'GET', '/mizzey/v1/google/callback' );
		$back->set_param( 'state', (string) ( $flow['state'] ?? '' ) );
		$back->set_param( 'code', 'good-code' );
		$issued = 0;
		$catch  = static function ( $cookie, $expire, $expiration, $user_id ) use ( &$issued ) { $issued = (int) $user_id; };
		add_action( 'set_logged_in_cookie', $catch, 10, 4 );
		$landed = rest_do_request( $back );
		remove_action( 'set_logged_in_cookie', $catch, 10 );
		$signed = get_current_user_id();
		remove_filter( 'mizzey_signup_trust_cli', '__return_false' );
		$made   = get_user_by( 'email', "{$tag}-callback@example.invalid" );
		if ( $made instanceof \WP_User ) {
			$s->track_user( $made->ID );
		}
		unset( $_COOKIE[ GoogleSignIn::COOKIE ] );
		wp_set_current_user( 0 );
		$check( 302 === $landed->get_status() && home_url( '/shop/' ) === ( $landed->get_headers()['Location'] ?? '' ) && $made instanceof \WP_User && $signed === $made->ID && $issued === $made->ID, 'AUTH-10 the callback with the state, its cookie and a good code, for someone new, makes their account through the sign-up check, signs them in, issuing their sign-in cookie, and returns them to where they began (' . $landed->get_status() . ', user ' . $signed . ')' );

		sign_in_forget( array( "{$tag}-known", "{$tag}-squat", "{$tag}-staff", "{$tag}-outside" ) );
		return $ok;
	}
);

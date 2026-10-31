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
		$good    = array( 'iss' => 'https://accounts.google.com', 'aud' => $client, 'exp' => time() + 300, 'nonce' => 'n-1', 'sub' => "{$tag}-sub-1", 'email' => "{$tag}-new@example.invalid", 'email_verified' => true, 'given_name' => 'Hana', 'family_name' => 'Adel' );
		$valid   = GoogleSignIn::claims( t65_token( $good ), 'n-1' );
		$refused = array(
			'issuer'   => GoogleSignIn::claims( t65_token( array( 'iss' => 'https://evil.example' ) + $good ), 'n-1' ),
			'audience' => GoogleSignIn::claims( t65_token( array( 'aud' => 'other-client' ) + $good ), 'n-1' ),
			'expired'  => GoogleSignIn::claims( t65_token( array( 'exp' => time() - 5 ) + $good ), 'n-1' ),
			'nonce'    => GoogleSignIn::claims( t65_token( $good ), 'n-2' ),
			'garbage'  => GoogleSignIn::claims( 'not-a-token', 'n-1' ),
		);
		$passed = array_keys( array_filter( $refused, static fn ( $r ) => ! is_wp_error( $r ) ) );
		$check( is_array( $valid ) && array() === $passed, 'AUTH-10 an ID token is taken only with Google\'s issuer, this client as audience, an expiry ahead and the nonce sent' . ( array() === $passed ? '' : ' (passed: ' . implode( ', ', $passed ) . ')' ) );

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
		$created  = GoogleSignIn::account( $good );
		$again    = GoogleSignIn::account( array( 'email' => "{$tag}-changed@example.invalid" ) + $good );
		if ( $created instanceof \WP_User ) {
			$s->track_user( $created->ID );
		}
		$check( $created instanceof \WP_User && array( 'customer' ) === array_values( $created->roles ) && "{$tag}-new@example.invalid" === $created->user_email && "{$tag}-sub-1" === get_user_meta( $created->ID, GoogleSignIn::SUBJECT, true ) && VerificationOnRegistration::verified( $created->ID ) && 'Hana' === get_user_meta( $created->ID, 'first_name', true ) && $again instanceof \WP_User && $again->ID === $created->ID, 'AUTH-10, AUTH-13 a new Google user becomes one customer account, its email verified; signing in again, even with a changed Google email, is the same account' );

		$known_id  = t65_user( $s, "{$tag}-known@example.invalid", 'customer', $password );
		$class     = '\\Automattic\\WooCommerce\\Internal\\CustomerEmailVerification\\EmailVerificationService';
		wc_get_container()->get( $class )->mark_verified( $known_id );
		$known     = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-2", 'email' => "{$tag}-known@example.invalid" ) + $good );
		$kept      = $known instanceof \WP_User && wp_check_password( $password, get_userdata( $known_id )->user_pass, $known_id );
		$squat_id  = t65_user( $s, "{$tag}-squat@example.invalid", 'customer', $password );
		$squatted  = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-3", 'email' => "{$tag}-squat@example.invalid" ) + $good );
		clean_user_cache( $squat_id );
		$replaced  = $squatted instanceof \WP_User && $squatted->ID === $squat_id && ! wp_check_password( $password, get_userdata( $squat_id )->user_pass, $squat_id ) && VerificationOnRegistration::verified( $squat_id );
		$check( $known instanceof \WP_User && $known->ID === $known_id && $kept && $replaced, 'AUTH-13 a Google sign-in with the email of an existing customer signs in to that account; one whose email was never verified here has its password replaced first, so whoever registered it cannot keep it' );

		$staff_id = t65_user( $s, "{$tag}-staff@example.invalid", 'shop_manager', $password );
		$staff    = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-4", 'email' => "{$tag}-staff@example.invalid" ) + $good );
		$unproven = GoogleSignIn::account( array( 'sub' => "{$tag}-sub-5", 'email' => "{$tag}-unproven@example.invalid", 'email_verified' => false ) + $good );
		$check( $staff_id > 0 && is_wp_error( $staff ) && 'mizzey_google_staff' === $staff->get_error_code() && '' === get_user_meta( $staff_id, GoogleSignIn::SUBJECT, true ) && is_wp_error( $unproven ) && false === get_user_by( 'email', "{$tag}-unproven@example.invalid" ), 'AUTH-10 a staff account cannot be signed in to with Google, and an email Google has not verified makes no account' );

		// The callback, in this process with the start's state and cookie: signs in and returns to where it began.
		add_filter( 'send_auth_cookies', '__return_false' );
		$s->on_finish( static function () { remove_filter( 'send_auth_cookies', '__return_false' ); } );
		$begin = new \WP_REST_Request( 'GET', '/mizzey/v1/google/start' );
		$begin->set_param( 'redirect_to', home_url( '/shop/' ) );
		parse_str( (string) wp_parse_url( (string) ( rest_do_request( $begin )->get_headers()['Location'] ?? '' ), PHP_URL_QUERY ), $flow );
		$saved = get_transient( 'mizzey_google_' . hash( 'sha256', (string) ( $flow['state'] ?? '' ) ) );
		$good['nonce'] = (string) ( $saved['nonce'] ?? '' );
		$_COOKIE[ GoogleSignIn::COOKIE ] = (string) ( $flow['state'] ?? '' );
		$back = new \WP_REST_Request( 'GET', '/mizzey/v1/google/callback' );
		$back->set_param( 'state', (string) ( $flow['state'] ?? '' ) );
		$back->set_param( 'code', 'good-code' );
		$landed = rest_do_request( $back );
		$signed = get_current_user_id();
		unset( $_COOKIE[ GoogleSignIn::COOKIE ] );
		wp_set_current_user( 0 );
		$check( 302 === $landed->get_status() && home_url( '/shop/' ) === ( $landed->get_headers()['Location'] ?? '' ) && $created instanceof \WP_User && $signed === $created->ID, 'AUTH-10 the callback with the state, its cookie and a good code signs the customer in and returns them to where they began (' . $landed->get_status() . ', user ' . $signed . ')' );

		sign_in_forget( array( "{$tag}-known", "{$tag}-squat", "{$tag}-staff" ) );
		return $ok;
	}
);

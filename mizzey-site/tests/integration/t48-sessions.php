<?php
/**
 * t48, AUTH-15 (AC-3; FR-006): sessions are handled securely and expire per the documented policy.
 *
 * Over HTTP: the authentication and logged-in cookies are HttpOnly. Their embedded expiry and the server session's are
 * two days, fourteen with "remember me". Without "remember me" the browser cookie has no Expires (it ends with the
 * browser); with it, Expires is the authentication expiry plus twelve hours. A cookie whose server session has expired
 * no longer authenticates. Signing out rejects that session's cookies while another session of the same person
 * continues. A self-service password change rejects cookies issued before it and issues working replacements. Secure
 * is asserted on staging over HTTPS; on the HTTP runtime it is recorded as not applicable. Nothing is changed in
 * WordPress's session behaviour.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/**
 * Sign in on wp-login.php and return the raw Set-Cookie lines with the cookies.
 *
 * @return array{lines:list<string>,cookies:array}
 */
function t48_sign_in( string $login, string $password, bool $remember ): array {
	$test   = new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) );
	$fields = array( 'log' => $login, 'pwd' => $password, 'testcookie' => 1, 'redirect_to' => admin_url( 'profile.php' ) );
	if ( $remember ) {
		$fields['rememberme'] = 'forever';
	}
	$r     = wp_remote_post( site_url( 'wp-login.php', 'login_post' ), array( 'timeout' => 30, 'redirection' => 0, 'cookies' => array( $test ), 'body' => $fields ) ); // storefront-guard: exempt, sign-in answers.
	$lines = wp_remote_retrieve_headers( $r )->getAll()['set-cookie'] ?? array();
	return array( 'lines' => (array) $lines, 'cookies' => wp_remote_retrieve_cookies( $r ) );
}

/** The Set-Cookie line for a cookie whose name starts with a prefix, ignoring deletions. */
function t48_line( array $lines, string $prefix ): string {
	foreach ( $lines as $line ) {
		if ( str_starts_with( $line, $prefix ) && ! str_contains( $line, '=deleted' ) && ! preg_match( '/^[^=]+=;/', $line ) ) {
			return $line;
		}
	}
	return '';
}

/** The expiry embedded in an authentication cookie's value. */
function t48_embedded( string $line ): int {
	$value = rawurldecode( (string) strtok( substr( $line, (int) strpos( $line, '=' ) + 1 ), ';' ) );
	$parts = explode( '|', $value );
	return (int) ( $parts[1] ?? 0 );
}

/** The browser Expires of a Set-Cookie line, or 0 when there is none. */
function t48_expires( string $line ): int {
	return 1 === preg_match( '/expires=([^;]+)/i', $line, $m ) ? (int) strtotime( $m[1] ) : 0;
}

run(
	new Scenario( 't48-sessions', 'AUTH-15: sessions are handled securely and expire per the documented policy' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		// The scheme of the address the requests go to, not of this process.
		$https         = 'https' === wp_parse_url( site_url( 'wp-login.php', 'login_post' ), PHP_URL_SCHEME );
		$auth_prefix   = ( $https ? SECURE_AUTH_COOKIE : AUTH_COOKIE ) . '=';
		$logged_prefix = LOGGED_IN_COOKIE . '=';
		$day           = DAY_IN_SECONDS;

		list( $user, $password ) = two_factor_user( $s, 'customer' );
		$short = t48_sign_in( $user->user_login, $password, false );
		$auth  = t48_line( $short['lines'], $auth_prefix );
		$in    = t48_line( $short['lines'], $logged_prefix );
		$check( '' !== $auth && '' !== $in, 'AC-3 signing in sets the authentication and logged-in cookies' );
		$check( false !== stripos( $auth, 'httponly' ) && false !== stripos( $in, 'httponly' ), 'AC-3 both cookies are HttpOnly' );
		if ( $https ) {
			$check( 1 === preg_match( '/;\s*secure(?:;|$)/i', $auth ) && 1 === preg_match( '/;\s*secure(?:;|$)/i', $in ), 'AC-3 over HTTPS both the authentication and the logged-in cookie are Secure' );
		} else {
			$s->note( 'N/A   AC-3 Secure is asserted on staging over HTTPS; this runtime is HTTP' );
		}
		$check( abs( t48_embedded( $auth ) - ( time() + 2 * $day ) ) < 120, 'AC-3 without "remember me" the authentication lasts two days' );
		$check( 0 === t48_expires( $auth ) && 0 === t48_expires( $in ), 'AC-3 without "remember me" the browser keeps the cookies only for the session' );

		$long      = t48_sign_in( $user->user_login, $password, true );
		$long_auth = t48_line( $long['lines'], $auth_prefix );
		$embedded  = t48_embedded( $long_auth );
		$check( abs( $embedded - ( time() + 14 * $day ) ) < 120, 'AC-3 with "remember me" the authentication lasts fourteen days' );
		$check( abs( t48_expires( $long_auth ) - ( $embedded + 12 * HOUR_IN_SECONDS ) ) < 5, 'AC-3 with "remember me" the browser Expires is the authentication expiry plus twelve hours' );

		// The server keeps each session with the same expiry; an expired server session no longer authenticates.
		wp_cache_delete( $user->ID, 'user_meta' );
		$tokens   = \WP_Session_Tokens::get_instance( $user->ID );
		$sessions = $tokens->get_all();
		$expiries = array_map( static fn ( array $sess ): int => (int) $sess['expiration'], $sessions );
		$check( in_array( $embedded, $expiries, true ), 'AC-3 the server session expires when the cookie says it does' );
		$third   = t48_sign_in( $user->user_login, $password, false );
		// A customer is sent away from the administration, so the account page tells whether cookies authenticate.
		$verify  = function ( array $cookies ): bool {
			$page = two_factor_http( 'GET', sign_in_account_url( 'en' ), array(), sign_in_jar( $cookies ) );
			return 200 === $page['status'] && str_contains( $page['body'], 'woocommerce-MyAccount-navigation' );
		};
		$check( $verify( $third['cookies'] ), 'AC-3 a fresh session authenticates (the control for the next case)' );
		$cookie_value = rawurldecode( (string) strtok( substr( t48_line( $third['lines'], $logged_prefix ), strlen( $logged_prefix ) ), ';' ) );
		$token_part   = explode( '|', $cookie_value )[2] ?? '';
		wp_cache_delete( $user->ID, 'user_meta' );
		$session      = $tokens->get( $token_part );
		if ( is_array( $session ) ) {
			$session['expiration'] = time() - 10;
			$tokens->update( $token_part, $session );
		}
		$check( is_array( $session ), 'AC-3 the session behind the cookie is found on the server' );
		$check( ! $verify( $third['cookies'] ), 'AC-3 a cookie whose server session has expired no longer authenticates' );

		// Signing out ends that session only.
		$one = t48_sign_in( $user->user_login, $password, false );
		$two = t48_sign_in( $user->user_login, $password, false );
		$out = two_factor_http( 'GET', add_query_arg( array( 'action' => 'logout', '_wpnonce' => two_factor_nonce( $user, $one['cookies'], 'log-out' ) ), site_url( 'wp-login.php', 'login' ) ), array(), $one['cookies'] );
		$check( in_array( $out['status'], array( 302, 200 ), true ) && ! $verify( $one['cookies'] ) && $verify( $two['cookies'] ), 'AC-3 signing out rejects that session\'s cookies; another session continues' );

		// A self-service password change, on the account page's details form, rejects earlier cookies and issues
		// working replacements: the cookie's signature includes part of the password hash.
		$before  = t48_sign_in( $user->user_login, $password, false );
		$changer = t48_sign_in( $user->user_login, $password, false );
		$check( $verify( $before['cookies'] ) && $verify( $changer['cookies'] ), 'AC-3 two sessions authenticate before the change' );
		$details = wc_get_endpoint_url( 'edit-account', '', sign_in_account_url( 'en' ) );
		$form    = two_factor_http( 'GET', $details, array(), $changer['cookies'] );
		$new     = 'Pw-' . wp_generate_password( 20, false );
		$saved   = two_factor_http(
			'POST',
			$details,
			array(
				'save-account-details-nonce' => two_factor_field( $form['body'], 'save-account-details-nonce' ),
				'_wp_http_referer'           => wp_parse_url( $details, PHP_URL_PATH ),
				'action'                     => 'save_account_details',
				'account_first_name'         => 'Test',
				'account_last_name'          => 'Customer',
				'account_display_name'       => $user->user_login,
				'account_email'              => $user->user_email,
				'password_current'           => $password,
				'password_1'                 => $new,
				'password_2'                 => $new,
				'save_account_details'       => 'Save changes',
			),
			$changer['cookies']
		);
		clean_user_cache( $user->ID );
		$changed = wp_check_password( $new, get_userdata( $user->ID )->user_pass, $user->ID );
		$check( $changed, "AC-3 the password was changed through the account page (status {$saved['status']})" );
		$check( ! $verify( $before['cookies'] ), 'AC-3 after a password change, cookies issued before it no longer authenticate' );
		$check( $verify( $saved['cookies'] ), 'AC-3 and the person who changed it keeps working replacement cookies' );

		return $ok;
	}
);

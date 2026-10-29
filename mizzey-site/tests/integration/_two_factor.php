<?php
/**
 * Shared helpers for the two-factor scenarios, t42 to t45 (specs/008-admin-two-factor).
 *
 * The baseline is applied by the real command and the earlier WP 2FA policy restored, through WP 2FA's own API, when the
 * scenario finishes, failure included: the rest of the suite runs unenforced. Fixture users are enrolled through WP 2FA's
 * own functions; their codes are computed here by an independent RFC 6238 implementation, never by the plugin's. Secrets
 * and codes are never written to a scenario's notes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

/** Apply the baseline with the real command; restore the earlier policy when the scenario finishes. */
function two_factor_baseline( Scenario $s ): void {
	$earlier = \WP2FA\Utils\Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, null );
	$s->on_finish(
		static function () use ( $earlier ) {
			if ( is_array( $earlier ) ) {
				\WP2FA\WP2FA::update_plugin_settings( $earlier, false, WP_2FA_POLICY_SETTINGS_NAME );
			} else {
				\WP2FA\Utils\Settings_Utils::delete_option( WP_2FA_POLICY_SETTINGS_NAME );
			}
		}
	);
	$out  = array();
	$code = 0;
	exec( escapeshellarg( getenv( 'MIZZEY_WP_BIN' ) ?: 'wp' ) . ' --path=' . escapeshellarg( ABSPATH ) . ' mizzey security two-factor-baseline 2>&1', $out, $code );
	if ( 0 !== $code ) {
		throw new \RuntimeException( 'The two-factor baseline command failed: ' . implode( ' ', $out ) );
	}
	two_factor_reload();
}

/** Bring this process's WP 2FA settings in line with the stored policy, after another process changed it. */
function two_factor_reload(): void {
	$stored = \WP2FA\Utils\Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, null );
	if ( is_array( $stored ) ) {
		\WP2FA\WP2FA::update_plugin_settings( $stored, true, WP_2FA_POLICY_SETTINGS_NAME );
	}
	wp_cache_flush();
}

/**
 * A new user of a role with a known password, tracked for removal.
 *
 * @return array{0:\WP_User,1:string}
 */
function two_factor_user( Scenario $s, string $role ): array {
	$login    = 't4x_' . $role . '_' . wp_rand();
	$password = wp_generate_password( 24, false );
	$id       = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'role' => $role, 'user_email' => $login . '@example.invalid', 'locale' => 'en_US' ) );
	$s->track_user( $id );
	return array( get_userdata( $id ), $password );
}

/**
 * Enrol a user with an authenticator app and backup codes, through WP 2FA's own functions.
 *
 * @return list<string> The backup codes, in plain text, for the scenario to use.
 */
function two_factor_enrol( \WP_User $user ): array {
	\WP2FA\Methods\TOTP::set_user_method( $user, \WP2FA\Authenticator\Authentication::generate_key() );
	return array_values( (array) \WP2FA\Methods\Backup_Codes::generate_codes( $user ) );
}

/** A TOTP code for the user, from an independent RFC 6238 implementation: SHA-1, six digits, 30-second steps. */
function two_factor_code( \WP_User $user, int $step_offset = 0 ): string {
	return two_factor_code_for_key( (string) \WP2FA\Methods\TOTP::get_totp_decrypted( $user ), $step_offset );
}

/**
 * A nonce for a signed-in user's session, as their browser would hold it: tied to the session token in their cookie.
 */
function two_factor_nonce( \WP_User $user, array $cookies, string $action ): string {
	$previous_user   = get_current_user_id();
	$previous_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
	foreach ( $cookies as $cookie ) {
		if ( LOGGED_IN_COOKIE === $cookie->name ) {
			$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie->value;
		}
	}
	wp_set_current_user( $user->ID );
	$nonce = wp_create_nonce( $action );
	wp_set_current_user( $previous_user );
	if ( null === $previous_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $previous_cookie;
	}
	return $nonce;
}

/** A TOTP code for a base32 key, from the independent implementation below. */
function two_factor_code_for_key( string $base32, int $step_offset = 0 ): string {
	$secret  = two_factor_base32_decode( $base32 );
	$counter = intdiv( time(), 30 ) + $step_offset;
	$hmac    = hash_hmac( 'sha1', pack( 'N2', ( $counter >> 32 ) & 0xFFFFFFFF, $counter & 0xFFFFFFFF ), $secret, true );
	$offset  = ord( $hmac[19] ) & 0x0F;
	$value   = ( ( ord( $hmac[ $offset ] ) & 0x7F ) << 24 ) | ( ord( $hmac[ $offset + 1 ] ) << 16 ) | ( ord( $hmac[ $offset + 2 ] ) << 8 ) | ord( $hmac[ $offset + 3 ] );
	return str_pad( (string) ( $value % 1000000 ), 6, '0', STR_PAD_LEFT );
}

function two_factor_base32_decode( string $text ): string {
	$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	$bits     = '';
	foreach ( str_split( strtoupper( rtrim( $text, '=' ) ) ) as $char ) {
		$index = strpos( $alphabet, $char );
		if ( false !== $index ) {
			$bits .= str_pad( decbin( $index ), 5, '0', STR_PAD_LEFT );
		}
	}
	$bytes = '';
	foreach ( str_split( $bits, 8 ) as $byte ) {
		if ( 8 === strlen( $byte ) ) {
			$bytes .= chr( bindec( $byte ) );
		}
	}
	return $bytes;
}

/**
 * A visitor's HTTP request, keeping cookies; follows no redirect.
 *
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function two_factor_http( string $method, string $url, array $fields = array(), array $cookies = array(), array $headers = array() ): array {
	$args = array( 'timeout' => 30, 'redirection' => 0, 'cookies' => $cookies, 'headers' => $headers );
	$r    = 'POST' === $method ? wp_remote_post( $url, $args + array( 'body' => $fields ) ) : wp_remote_get( $url, $args ); // storefront-guard: exempt, sign-in and administration answers, not storefront pages.
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'body' => '', 'location' => '', 'cookies' => array() );
	}
	return array(
		'status'   => (int) wp_remote_retrieve_response_code( $r ),
		'body'     => (string) wp_remote_retrieve_body( $r ),
		'location' => (string) wp_remote_retrieve_header( $r, 'location' ),
		'cookies'  => array_merge( $cookies, wp_remote_retrieve_cookies( $r ) ),
	);
}

/** The value of a hidden field in a page, or an empty string. */
function two_factor_field( string $body, string $name ): string {
	return 1 === preg_match( '/name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]*value=["\']([^"\']*)["\']/', $body, $m ) ? html_entity_decode( $m[1] ) : '';
}

/** Whether a set of cookies authenticates: the profile screen answers 200 rather than sending to sign-in. */
function two_factor_signed_in( array $cookies ): bool {
	$profile = two_factor_http( 'GET', admin_url( 'profile.php' ), array(), $cookies );
	return 200 === $profile['status'];
}

/**
 * Sign in on wp-login.php with the password only.
 *
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function two_factor_password_step( string $login, string $password ): array {
	$test = new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) );
	return two_factor_http( 'POST', site_url( 'wp-login.php', 'login_post' ), array( 'log' => $login, 'pwd' => $password, 'testcookie' => 1, 'redirect_to' => admin_url( 'profile.php' ) ), array( $test ) );
}

/**
 * Answer a two-factor challenge page with a code.
 *
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function two_factor_answer( array $challenge, string $code, string $provider = 'totp' ): array {
	$fields = array(
		'provider'      => $provider,
		'wp-auth-id'    => two_factor_field( $challenge['body'], 'wp-auth-id' ),
		'wp-auth-nonce' => two_factor_field( $challenge['body'], 'wp-auth-nonce' ),
		'redirect_to'   => admin_url( 'profile.php' ),
	);
	$fields[ 'backup_codes' === $provider ? 'wp-2fa-backup-code' : 'authcode' ] = $code;
	return two_factor_http( 'POST', add_query_arg( 'action', 'validate_2fa', site_url( 'wp-login.php', 'login_post' ) ), $fields, $challenge['cookies'] );
}

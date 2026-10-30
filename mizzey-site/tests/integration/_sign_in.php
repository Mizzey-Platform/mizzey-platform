<?php
/**
 * Fixtures for the AUTH-15 scenarios (t46 to t48): CoreX's sign-in limit, the signup check and sessions.
 *
 * Settings are snapshotted and restored exactly; lockout cases use disposable identities and remove only their own
 * attempt rows. Never `wp corex security reset-login` or `releaseActiveLockouts()`: both act on every lockout.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_two_factor.php';

const SIGN_IN_COREX_OPTION = 'corex_login_protection_settings';

/**
 * Run a WP-CLI command line in its own process.
 *
 * @return array{code:int,out:string}
 */
function sign_in_wp( string $args ): array {
	$out  = array();
	$code = 0;
	exec( escapeshellarg( getenv( 'MIZZEY_WP_BIN' ) ?: 'wp' ) . ' --path=' . escapeshellarg( ABSPATH ) . ' ' . $args . ' 2>&1', $out, $code );
	return array( 'code' => $code, 'out' => implode( "\n", $out ) );
}

/**
 * Restore an option exactly as it was when the scenario finishes: its value, or its absence. Only the first call for
 * an option in a scenario takes the snapshot, so a later call cannot restore an intermediate value over the original.
 */
function sign_in_keep_option( Scenario $s, string $name ): void {
	global $wpdb;
	static $kept = array();
	$key = spl_object_id( $s ) . '|' . $name;
	if ( isset( $kept[ $key ] ) ) {
		return;
	}
	$kept[ $key ] = true;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$s->on_finish(
		static function () use ( $name, $row ) {
			global $wpdb;
			$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( is_array( $row ) ) {
				$wpdb->insert( $wpdb->options, array( 'option_name' => $name, 'option_value' => $row['option_value'], 'autoload' => $row['autoload'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}
	);
}

/** Apply the sign-in baseline with the real command; CoreX's settings are restored when the scenario finishes. */
function sign_in_baseline( Scenario $s ): void {
	sign_in_keep_option( $s, SIGN_IN_COREX_OPTION );
	$applied = sign_in_wp( 'mizzey security sign-in-baseline' );
	if ( 0 !== $applied['code'] ) {
		throw new \RuntimeException( 'The sign-in baseline command failed: ' . $applied['out'] );
	}
	wp_cache_delete( SIGN_IN_COREX_OPTION, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

/** Change CoreX's stored settings directly (snake_case keys), for a case that needs another value. */
function sign_in_corex_set( array $changes ): void {
	$stored = get_option( SIGN_IN_COREX_OPTION, array() );
	update_option( SIGN_IN_COREX_OPTION, array_replace( is_array( $stored ) ? $stored : array(), $changes ) );
}

/** Remove the attempt rows CoreX recorded for these identities, and nothing else. */
function sign_in_forget( array $identities ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'corex_login_attempts';
	foreach ( $identities as $identity ) {
		$wpdb->delete( $table, array( 'identity_hash' => hash( 'sha256', mb_strtolower( trim( (string) $identity ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

/** The account page in a language. */
function sign_in_account_url( string $lang ): string {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$url = (string) wc_get_page_permalink( 'myaccount' );
	do_action( 'wpml_switch_language', $current );
	return $url;
}

/**
 * Sign in on the account page with a password, the way its form does.
 *
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function sign_in_account( string $lang, string $login, string $password, array $headers = array() ): array {
	$account = sign_in_account_url( $lang );
	$form    = two_factor_http( 'GET', $account, array(), array(), $headers );
	return two_factor_http( 'POST', $account, array( 'username' => $login, 'password' => $password, 'woocommerce-login-nonce' => two_factor_field( $form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'], $headers );
}

/**
 * Sign in on wp-login.php with a password.
 *
 * @return array{status:int,body:string,location:string,cookies:array}
 */
function sign_in_wp_login( string $login, string $password, array $headers = array() ): array {
	$test = new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) );
	return two_factor_http( 'POST', site_url( 'wp-login.php', 'login_post' ), array( 'log' => $login, 'pwd' => $password, 'testcookie' => 1, 'redirect_to' => admin_url( 'profile.php' ) ), array( $test ), $headers );
}

/** Whether an answer set a live authenticating cookie; a deletion (empty value or an expiry in the past) is not one. */
function sign_in_has_session( array $answer ): bool {
	$live = array_filter(
		sign_in_jar( $answer['cookies'] ),
		static fn ( $c ) => str_starts_with( (string) $c->name, 'wordpress_logged_in_' )
			&& '' !== trim( (string) $c->value ) && 'deleted' !== $c->value
			&& ( null === $c->expires || (int) $c->expires > time() )
	);
	return array() !== $live;
}

/** Set options for the scenario, each restored exactly when it finishes. */
function sign_in_options( Scenario $s, array $values ): void {
	foreach ( $values as $name => $value ) {
		sign_in_keep_option( $s, $name );
		update_option( $name, $value );
	}
}

/** The signup token and trap field a page printed, or empty strings. */
function sign_in_page_token( string $body ): array {
	return array(
		'token' => two_factor_field( $body, 'mizzey_signup_token' ),
		'trap'  => 1 === preg_match( '/name=["\']mizzey_signup_extra["\']/', $body ) ? 'present' : '',
	);
}

/** Whether an account exists for an email. */
function sign_in_account_exists( string $email ): bool {
	clean_user_cache( 0 );
	return false !== get_user_by( 'email', $email );
}

/** Remove any account a case created for an email. */
function sign_in_remove_account( string $email ): void {
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user->ID );
	}
}

/**
 * A JSON request to the Store API (or any JSON route), keeping cookies and returning the response headers.
 *
 * @return array{status:int,data:mixed,headers:array,cookies:array}
 */
function sign_in_json( string $method, string $url, ?array $body = null, array $headers = array(), array $cookies = array() ): array {
	$args = array( 'method' => $method, 'timeout' => 60, 'redirection' => 0, 'cookies' => $cookies, 'headers' => $headers + array( 'Content-Type' => 'application/json' ) );
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( $url, $args ); // storefront-guard: exempt, Store API JSON answers, not storefront pages.
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'data' => null, 'headers' => array(), 'cookies' => $cookies );
	}
	return array(
		'status'  => (int) wp_remote_retrieve_response_code( $r ),
		'data'    => json_decode( (string) wp_remote_retrieve_body( $r ), true ),
		'headers' => array_change_key_case( (array) wp_remote_retrieve_headers( $r )->getAll(), CASE_LOWER ),
		'cookies' => array_merge( $cookies, wp_remote_retrieve_cookies( $r ) ),
	);
}

/** One header value from a sign_in_json answer. */
function sign_in_header( array $answer, string $name ): string {
	$value = $answer['headers'][ strtolower( $name ) ] ?? '';
	return is_array( $value ) ? (string) reset( $value ) : (string) $value;
}

/** Orders whose billing email is this one. */
function sign_in_orders_for( string $email ): array {
	$statuses = array_unique( array_merge( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ) );
	return wc_get_orders( array( 'billing_email' => $email, 'limit' => -1, 'status' => $statuses, 'return' => 'ids' ) );
}

/** A cookie list with one cookie per name, the latest winning. */
function sign_in_jar( array $cookies ): array {
	$jar = array();
	foreach ( $cookies as $cookie ) {
		$jar[ (string) $cookie->name ] = $cookie;
	}
	return array_values( $jar );
}

/** A fresh, unique test email. */
function sign_in_email( string $tag ): string {
	return 't4x-' . $tag . '-' . wp_rand() . '@example.invalid';
}

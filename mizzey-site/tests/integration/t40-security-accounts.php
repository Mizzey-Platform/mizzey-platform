<?php
/**
 * t40, NFR-05 (admin protection, rate limiting): accounts are harder to attack.
 *
 * AC-4: application passwords exist only for administrators; a customer or a shop manager can neither have one
 * available nor authenticate with one. AC-5: a failed sign-in gives one message whether the account exists or not,
 * on the WordPress sign-in page and on the account page in both languages; the failure still fires
 * `wp_login_failed`, and CoreX's lockout error passes unchanged. AC-6: password recovery is limited per account and
 * per client address, on both recovery handlers; a username and an email of one account share one allowance; a
 * refused request sends no email; invalid settings fall back safely. Concurrent requests are limited best-effort: two
 * requests read in the same instant can both pass.
 *
 * In process: the handlers themselves (WordPress `retrieve_password()` and WooCommerce's
 * `WC_Shortcode_My_Account::retrieve_password()`), with mail intercepted by `pre_wp_mail`, so the email claim is
 * exact and nothing leaves the runtime. Over HTTP: the messages a visitor reads, in English and Arabic. The HTTP
 * requests come from this machine, so the scenario removes the counters it creates for the loopback addresses.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\RecoveryLimiter;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/** A new user of a role, tracked for removal. */
function t40_user( Scenario $s, string $role ): \WP_User {
	$login = 't40_' . $role . '_' . wp_rand();
	$id    = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'role' => $role, 'user_email' => $login . '@example.invalid' ) );
	$s->track_user( $id );
	return get_userdata( $id );
}

/**
 * Load a page as a visitor, keep its cookies, and read a hidden field from it.
 *
 * @return array{body:string,cookies:array,field:string}
 */
function t40_form( string $url, string $field ): array {
	$r    = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0 ) );
	$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
	storefront_refuse_shell( $url, is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ), $body );
	preg_match( '/name=["\']' . preg_quote( $field, '/' ) . '["\'][^>]*value=["\']([^"\']+)["\']/', $body, $m );
	return array( 'body' => $body, 'cookies' => is_wp_error( $r ) ? array() : wp_remote_retrieve_cookies( $r ), 'field' => $m[1] ?? '' );
}

/** Submit a form as a visitor and return the page it answers with, following no redirect. */
function t40_post( string $url, array $fields, array $cookies = array() ): string {
	$r = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'body' => $fields, 'cookies' => $cookies ) );
	return is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
}

/**
 * The text of the first error notice on a page: WordPress's login error box, WooCommerce's notice banner (the markup
 * WooCommerce 11 prints), or its older error list.
 */
function t40_error_text( string $body ): string {
	$patterns = array(
		'/<div[^>]+id=["\']login_error["\'][^>]*>(.*?)<\/div>/s',
		'/<div[^>]+class=["\'][^"\']*wc-block-components-notice-banner[^"\']*is-error[^"\']*["\'][^>]*>.*?<div[^>]+class=["\'][^"\']*notice-banner__content[^"\']*["\'][^>]*>(.*?)<\/div>/s',
		'/<ul[^>]+class=["\'][^"\']*woocommerce-error[^"\']*["\'][^>]*>(.*?)<\/ul>/s',
	);
	foreach ( $patterns as $pattern ) {
		if ( 1 === preg_match( $pattern, $body, $m ) ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $m[1] ) ) );
		}
	}
	return '';
}

/** A URL in a language, built while that language is active so WCML translates the endpoint. */
function t40_in_language( string $lang, callable $build ): string {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$url = (string) $build();
	do_action( 'wpml_switch_language', $current );
	return $url;
}

run(
	new Scenario( 't40-security-accounts', 'NFR-05: application passwords, sign-in messages and recovery limits' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$sent = 0;
		add_filter(
			'pre_wp_mail',
			function () use ( &$sent ) {
				++$sent;
				return true;
			}
		);
		if ( null === WC()->session ) {
			WC()->initialize_session();
		}

		// ---- AC-4: application passwords -----------------------------------------------------------------------
		$customer = t40_user( $s, 'customer' );
		$manager  = t40_user( $s, 'shop_manager' );
		$admin    = t40_user( $s, 'administrator' );
		$check( ! wp_is_application_passwords_available_for_user( $customer ), 'AC-4: not available to a customer' );
		$check( ! wp_is_application_passwords_available_for_user( $manager ), 'AC-4: not available to a shop manager' );
		$check( wp_is_application_passwords_available_for_user( $admin ), 'AC-4: available to an administrator' );
		add_filter( 'application_password_is_api_request', '__return_true' );
		list( $customer_secret ) = \WP_Application_Passwords::create_new_application_password( $customer->ID, array( 'name' => 't40' ) );
		$used = wp_authenticate_application_password( null, $customer->user_login, (string) $customer_secret );
		$check( is_wp_error( $used ), 'AC-4: a customer cannot authenticate with an application password stored for them (' . ( is_wp_error( $used ) ? $used->get_error_code() : 'authenticated' ) . ')' );
		list( $admin_secret ) = \WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 't40' ) );
		$admin_used = wp_authenticate_application_password( null, $admin->user_login, (string) $admin_secret );
		$check( $admin_used instanceof \WP_User, 'AC-4: an administrator still can' );
		remove_filter( 'application_password_is_api_request', '__return_true' );
		foreach ( array( 'customer' => array( $customer, $customer_secret, false ), 'administrator' => array( $admin, $admin_secret, true ) ) as $who => list( $u, $secret, $allowed ) ) {
			$r      = wp_remote_get( rest_url( 'wp/v2/users/me' ), array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $u->user_login . ':' . $secret ) ) ) );
			$status = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$check( $allowed ? 200 === $status : in_array( $status, array( 401, 403 ), true ), "AC-4 [HTTP] a stored application password for the {$who} " . ( $allowed ? 'authenticates' : 'is refused' ) . " (status {$status})" );
		}

		// ---- AC-5: one message, in process ----------------------------------------------------------------------
		$failed_before = did_action( 'wp_login_failed' );
		$wrong         = wp_authenticate( $customer->user_login, 'not-the-password' );
		$unknown       = wp_authenticate( 't40_nobody_' . wp_rand(), 'not-the-password' );
		$unknown_email = wp_authenticate( 't40-nobody-' . wp_rand() . '@example.invalid', 'not-the-password' );
		$messages      = array_map( fn ( $e ) => is_wp_error( $e ) ? $e->get_error_code() . ': ' . $e->get_error_message() : 'signed in', array( $wrong, $unknown, $unknown_email ) );
		$check( 1 === count( array_unique( $messages ) ), 'AC-5: wrong password, unknown username and unknown email give one answer (' . implode( ' | ', array_unique( $messages ) ) . ')' );
		$check( 3 === did_action( 'wp_login_failed' ) - $failed_before, 'AC-5: each failure still fires wp_login_failed, which CoreX counts' );
		$lock = static fn () => new \WP_Error( 'corex_login_locked', 'locked' );
		add_filter( 'authenticate', $lock, 30 );
		$locked = wp_authenticate( $customer->user_login, 'not-the-password' );
		remove_filter( 'authenticate', $lock, 30 );
		$check( is_wp_error( $locked ) && 'corex_login_locked' === $locked->get_error_code(), "AC-5: CoreX's lockout error passes unchanged" );
		$password = wp_generate_password( 24 );
		wp_set_password( $password, $customer->ID );
		$check( wp_authenticate( $customer->user_login, $password ) instanceof \WP_User, 'AC-5: the right password still signs in' );
		$empty = wp_authenticate( '', '' );
		$check( is_wp_error( $empty ) && 'authentication_failed' !== $empty->get_error_code(), 'AC-5: empty credentials keep their own answer (' . ( is_wp_error( $empty ) ? $empty->get_error_code() : 'signed in' ) . ')' );

		// ---- AC-5: one message, over HTTP ----------------------------------------------------------------------
		$login_url = site_url( 'wp-login.php', 'login_post' );
		$a = t40_error_text( t40_post( $login_url, array( 'log' => $customer->user_login, 'pwd' => 'not-the-password', 'testcookie' => 1 ), array( new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) ) ) ) );
		$b = t40_error_text( t40_post( $login_url, array( 'log' => 't40_nobody_' . wp_rand(), 'pwd' => 'not-the-password', 'testcookie' => 1 ), array( new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) ) ) ) );
		$check( '' !== $a && $a === $b, "AC-5 [wp-login] the same message for a real and an unknown account ('{$a}' / '{$b}')" );
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$account = t40_in_language( $lang, fn () => wc_get_page_permalink( 'myaccount' ) );
			$texts   = array();
			foreach ( array( $customer->user_login, 't40_nobody_' . wp_rand() ) as $who ) {
				$form    = t40_form( $account, 'woocommerce-login-nonce' );
				$texts[] = t40_error_text( t40_post( $account, array( 'username' => $who, 'password' => 'not-the-password', 'woocommerce-login-nonce' => $form['field'], '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'] ) );
			}
			$check( '' !== $texts[0] && $texts[0] === $texts[1], "AC-5 [account, $lang] the same message for a real and an unknown account ('{$texts[0]}' / '{$texts[1]}')" );
		}

		// ---- AC-6: recovery limits, in process -----------------------------------------------------------------
		$_SERVER['REMOTE_ADDR'] = '198.51.100.40';
		$wp_user = t40_user( $s, 'customer' );
		$results = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$before    = $sent;
			$r         = retrieve_password( $wp_user->user_login );
			$results[] = ( is_wp_error( $r ) ? $r->get_error_code() : 'sent' ) . ( $sent > $before ? '+mail' : '' );
		}
		$check( array( 'sent+mail', 'sent+mail', 'sent+mail', 'mizzey_recovery_limited' ) === $results, 'AC-6 [WordPress] three requests for one account pass, the fourth is refused with no email (' . implode( ', ', $results ) . ')' );

		$woo_user = t40_user( $s, 'customer' );
		$results  = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			wc_clear_notices();
			$before             = $sent;
			$_POST['user_login'] = $woo_user->user_login;
			$r                  = \WC_Shortcode_My_Account::retrieve_password();
			$notice             = wp_strip_all_tags( implode( ' ', array_column( wc_get_notices( 'error' ), 'notice' ) ) );
			$results[]          = ( $r ? 'sent' : 'refused:' . $notice ) . ( $sent > $before ? '+mail' : '' );
		}
		wc_clear_notices();
		unset( $_POST['user_login'] );
		$refusal = __( 'Too many password reset requests. Please wait a few minutes and try again.', 'mizzey-site' );
		$check( array( 'sent+mail', 'sent+mail', 'sent+mail', 'refused:' . $refusal ) === $results, 'AC-6 [WooCommerce] three requests for one account pass, the fourth is refused with the message and no email (' . implode( ', ', $results ) . ')' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.41';
		$codes = array();
		for ( $i = 1; $i <= 11; $i++ ) {
			$r       = retrieve_password( 't40_nobody_' . $i . '_' . wp_rand() );
			$codes[] = is_wp_error( $r ) ? $r->get_error_code() : 'sent';
		}
		$check( 'mizzey_recovery_limited' === $codes[10] && ! in_array( 'mizzey_recovery_limited', array_slice( $codes, 0, 10 ), true ), 'AC-6: the eleventh request from one address is refused, whatever the accounts (' . implode( ', ', array_unique( $codes ) ) . ')' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
		$tight = static fn () => array( 'account' => 1, 'address' => 10, 'window' => 900 );
		add_filter( 'mizzey_recovery_limits', $tight );
		$limited_user = t40_user( $s, 'customer' );
		$first        = retrieve_password( $limited_user->user_login );
		$second       = retrieve_password( $limited_user->user_login );
		remove_filter( 'mizzey_recovery_limits', $tight );
		$check( true === $first && is_wp_error( $second ) && 'mizzey_recovery_limited' === $second->get_error_code(), 'AC-6: the limits are a setting: a filtered limit of one refuses the second request' );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.43';
		$alias_user = t40_user( $s, 'customer' );
		$aliases    = array( $alias_user->user_login, $alias_user->user_email, $alias_user->user_login, $alias_user->user_email );
		$codes      = array();
		foreach ( $aliases as $login ) {
			$r       = retrieve_password( $login );
			$codes[] = is_wp_error( $r ) ? $r->get_error_code() : 'sent';
		}
		$check( array( 'sent', 'sent', 'sent', 'mizzey_recovery_limited' ) === $codes, 'AC-6: the username and the email of one account share one allowance (' . implode( ', ', $codes ) . ')' );

		$broken = static fn () => array( 'account' => 0, 'address' => 'many', 'window' => -5 );
		add_filter( 'mizzey_recovery_limits', $broken );
		$safe = RecoveryLimiter::limits();
		remove_filter( 'mizzey_recovery_limits', $broken );
		$check( array( 'account' => 1, 'address' => 10, 'window' => 1 ) === $safe, 'AC-6: invalid settings fall back safely (' . wp_json_encode( $safe ) . ')' );

		foreach ( array( '198.51.100.40', '198.51.100.41', '198.51.100.42', '198.51.100.43' ) as $address ) {
			foreach ( array( $wp_user, $woo_user, $limited_user, $alias_user ) as $u ) {
				RecoveryLimiter::forget( $u->ID, $address );
			}
		}

		// ---- AC-6: the refusal a visitor reads, over HTTP, in both languages -----------------------------------
		$http_user = t40_user( $s, 'customer' );
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$lost = t40_in_language( $lang, fn () => wc_lostpassword_url() );
			$last = '';
			for ( $i = 1; $i <= 4; $i++ ) {
				$form = t40_form( $lost, 'woocommerce-lost-password-nonce' );
				$last = t40_error_text( t40_post( $lost, array( 'user_login' => $http_user->user_login, 'wc_reset_password' => 'true', 'woocommerce-lost-password-nonce' => $form['field'], '_wp_http_referer' => wp_parse_url( $lost, PHP_URL_PATH ) ), $form['cookies'] ) );
			}
			$expected = 'ar' === $lang ? 'طلبات كثيرة' : 'Too many password reset requests';
			$check( false !== strpos( $last, $expected ), "AC-6 [account, $lang] the fourth request reads the refusal in the visitor's language ('{$last}')" );
			foreach ( array( '127.0.0.1', '::1' ) as $loopback ) {
				RecoveryLimiter::forget( $http_user->ID, $loopback );
			}
		}

		return $ok;
	}
);

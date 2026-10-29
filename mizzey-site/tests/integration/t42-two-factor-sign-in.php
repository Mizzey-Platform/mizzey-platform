<?php
/**
 * t42, ADM-131 (AC-1, AC-2, FR-007): a stolen password is not enough.
 *
 * Over HTTP, with the baseline applied by the real command: an enrolled administrator who gives only the password, on
 * the WordPress sign-in page and on the account page in both languages, is challenged and holds no session; a code from
 * an independent RFC 6238 implementation completes sign-in, and a wrong one is refused. An enrolled shop manager is
 * challenged too. An unenrolled customer-only account signs in with no challenge. Visitor and customer pages load no
 * WP 2FA asset. Each sign-in uses its own user, because a TOTP code is refused when replayed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';
require_once __DIR__ . '/_two_factor.php';

/** A language's account page, built while that language is active so WCML gives its own address. */
function t42_account( string $lang ): string {
	$current = (string) apply_filters( 'wpml_current_language', null );
	do_action( 'wpml_switch_language', $lang );
	$url = (string) wc_get_page_permalink( 'myaccount' );
	do_action( 'wpml_switch_language', $current );
	return $url;
}

run(
	new Scenario( 't42-two-factor-sign-in', 'ADM-131: with two-factor in force, a password alone gives no session' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		two_factor_baseline( $s );

		// ---- AC-1 on wp-login.php ---------------------------------------------------------------------------------
		list( $admin, $password ) = two_factor_user( $s, 'administrator' );
		two_factor_enrol( $admin );
		$challenge = two_factor_password_step( $admin->user_login, $password );
		$check( false !== strpos( $challenge['body'], 'validate_2fa_form' ) && '' !== two_factor_field( $challenge['body'], 'wp-auth-nonce' ), 'AC-1 [wp-login] the right password alone brings the two-factor challenge' );
		$check( ! two_factor_signed_in( $challenge['cookies'] ), 'AC-1 [wp-login] and no session: the cookies it leaves do not open the administration' );
		$wrong = two_factor_answer( $challenge, '000000' === two_factor_code( $admin ) ? '111111' : '000000' );
		$check( ! two_factor_signed_in( $wrong['cookies'] ) && false !== strpos( $wrong['body'], 'validate_2fa_form' ), 'AC-1 [wp-login] a wrong code is refused, and the challenge is shown again' );
		$retry = array( 'body' => $wrong['body'], 'cookies' => $challenge['cookies'] );
		$right = two_factor_answer( $retry, two_factor_code( $admin ) );
		$check( 302 === $right['status'] && two_factor_signed_in( $right['cookies'] ), "AC-1 [wp-login] the right code completes sign-in (status {$right['status']})" );

		// ---- AC-1 on the account page, both languages -------------------------------------------------------------
		foreach ( array( 'en', 'ar' ) as $lang ) {
			list( $staff, $staff_password ) = two_factor_user( $s, 'administrator' );
			two_factor_enrol( $staff );
			$account = t42_account( $lang );
			$form    = two_factor_http( 'GET', $account );
			$posted  = two_factor_http( 'POST', $account, array( 'username' => $staff->user_login, 'password' => $staff_password, 'woocommerce-login-nonce' => two_factor_field( $form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'] );
			$check( false !== strpos( $posted['body'], 'validate_2fa_form' ) && ! two_factor_signed_in( $posted['cookies'] ), "AC-1 [account, {$lang}] the right password alone brings the challenge and no session" );
			$done = two_factor_answer( $posted, two_factor_code( $staff ) );
			$check( two_factor_signed_in( $done['cookies'] ), "AC-1 [account, {$lang}] the right code completes sign-in" );
		}

		// ---- AC-2: an enrolled shop manager is challenged; a customer is not --------------------------------------
		list( $manager, $manager_password ) = two_factor_user( $s, 'shop_manager' );
		two_factor_enrol( $manager );
		$manager_step = two_factor_password_step( $manager->user_login, $manager_password );
		$check( false !== strpos( $manager_step['body'], 'validate_2fa_form' ) && ! two_factor_signed_in( $manager_step['cookies'] ), 'AC-2 an enrolled shop manager is challenged too' );

		list( $customer, $customer_password ) = two_factor_user( $s, 'customer' );
		$account  = t42_account( 'en' );
		$form     = two_factor_http( 'GET', $account );
		$customer_in = two_factor_http( 'POST', $account, array( 'username' => $customer->user_login, 'password' => $customer_password, 'woocommerce-login-nonce' => two_factor_field( $form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'] );
		$logged_in = array_filter( $customer_in['cookies'], fn ( $c ) => str_starts_with( (string) $c->name, 'wordpress_logged_in_' ) );
		$check( 302 === $customer_in['status'] && false === strpos( $customer_in['body'], 'validate_2fa_form' ) && array() !== $logged_in, "AC-2 an unenrolled customer-only account signs in with no challenge (status {$customer_in['status']})" );

		// ---- FR-007: no WP 2FA asset on ordinary pages ------------------------------------------------------------
		$product = $s->simple_product( 't42 product', '100', null );
		$pages   = array(
			'home (visitor)'     => array( home_url( '/' ), array() ),
			'product (visitor)'  => array( (string) get_permalink( $product->get_id() ), array() ),
			'home (customer)'    => array( home_url( '/' ), $customer_in['cookies'] ),
			'account (customer)' => array( $account, $customer_in['cookies'] ),
		);
		foreach ( $pages as $label => list( $url, $cookies ) ) {
			$page = two_factor_http( 'GET', $url, array(), $cookies );
			storefront_refuse_shell( $url, $page['status'], $page['body'] );
			$check( 200 === $page['status'] && false === strpos( $page['body'], '/plugins/wp-2fa/' ), "FR-007 [{$label}] loads no WP 2FA asset (status {$page['status']})" );
		}

		return $ok;
	}
);

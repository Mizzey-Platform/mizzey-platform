<?php
/**
 * t44, ADM-131 (AC-3, FR-003): a staff member who must enrol can do nothing privileged until they have.
 *
 * Over HTTP. A shop manager signed in before the baseline (a pre-existing session) and one signed in after it (immediate
 * access) are both pending once the baseline applies. Neither can reach an administration page other than set-up, post
 * an administration action, call a privileged REST route with a valid nonce, or run an unlisted background action;
 * policy and reset actions are refused; set-up itself works. Enrolment is then completed the way the browser does it,
 * through the issued key and the plugin's nonce, and the same requests work. A user with both a customer and a staff
 * role is pending too, and a pending administrator's application password is refused.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';

/** Whether a background action is refused with the guard's 403. */
function t44_ajax_refused( array $cookies, string $action, array $fields = array() ): bool {
	$r = two_factor_http( 'POST', admin_url( 'admin-ajax.php' ), array( 'action' => $action ) + $fields, $cookies );
	return 403 === $r['status'];
}

run(
	new Scenario( 't44-two-factor-enrolment-guard', 'ADM-131: a staff member who must enrol can do nothing privileged first' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		// A session that existed before the baseline.
		list( $early, $early_password ) = two_factor_user( $s, 'shop_manager' );
		$early_session = two_factor_password_step( $early->user_login, $early_password );
		$check( two_factor_signed_in( $early_session['cookies'] ), 'before the baseline, the shop manager signs in normally' );

		two_factor_baseline( $s );

		// A session started after it.
		list( $manager, $manager_password ) = two_factor_user( $s, 'shop_manager' );
		$session = two_factor_password_step( $manager->user_login, $manager_password );

		foreach ( array( 'pre-existing session' => array( $early, $early_session['cookies'] ), 'new session' => array( $manager, $session['cookies'] ) ) as $label => list( $user, $cookies ) ) {
			$dashboard = two_factor_http( 'GET', admin_url( 'index.php' ), array(), $cookies );
			$check( 302 === $dashboard['status'] && false !== strpos( $dashboard['location'], 'show=wp-2fa-setup' ), "AC-3 [{$label}] an administration page sends to set-up (status {$dashboard['status']})" );
			$orders = two_factor_http( 'GET', admin_url( 'edit.php?post_type=shop_order' ), array(), $cookies );
			$check( 302 === $orders['status'] && false !== strpos( $orders['location'], 'show=wp-2fa-setup' ), "AC-3 [{$label}] the orders screen sends to set-up (status {$orders['status']})" );
			foreach ( array( 'page=wc-settings', 'import=wordpress' ) as $dispatch ) {
				$sneak = two_factor_http( 'GET', admin_url( 'profile.php?show=wp-2fa-setup&' . $dispatch ), array(), $cookies );
				// Sent to set-up, or refused by WordPress's own menu check first: either way the other screen never runs.
				$not_dispatched = 403 === $sneak['status'] || ( 302 === $sneak['status'] && false !== strpos( $sneak['location'], 'show=wp-2fa-setup' ) );
				$check( $not_dispatched && false === strpos( $sneak['body'], 'woocommerce-settings' ), "AC-3 [{$label}] the set-up exception cannot dispatch {$dispatch} (status {$sneak['status']})" );
			}
			$setup = two_factor_http( 'GET', admin_url( 'profile.php?show=wp-2fa-setup' ), array(), $cookies );
			$check( 200 === $setup['status'] && false !== strpos( $setup['body'], 'totpKey' ), "AC-3 [{$label}] the set-up screen opens (status {$setup['status']})" );
			$post = two_factor_http( 'POST', admin_url( 'admin-post.php' ), array( 'action' => 'mizzey_t44_probe' ), $cookies );
			// Refused outright, or sent to set-up before WordPress dispatches the action: either way it never runs.
			$check( 403 === $post['status'] || ( 302 === $post['status'] && false !== strpos( $post['location'], 'show=wp-2fa-setup' ) ), "AC-3 [{$label}] an administration action is refused (status {$post['status']}, location '{$post['location']}')" );
			$rest_nonce = two_factor_nonce( $user, $cookies, 'wp_rest' );
			$rest       = two_factor_http( 'GET', rest_url( 'wc/v3/orders' ), array(), $cookies, array( 'X-WP-Nonce' => $rest_nonce ) );
			$check( 403 === $rest['status'] && false !== strpos( $rest['body'], 'mizzey_two_factor_enrol_first' ), "AC-3 [{$label}] a privileged REST call with a valid nonce is refused (status {$rest['status']})" );
			// The one REST exception reaches WP 2FA, whose own transaction check still refuses a forged request.
			$validation = two_factor_http( 'POST', rest_url( 'wp-2fa-methods/v1/login/validate' ), array( 'user_id' => $user->ID, 'provider' => 'totp', 'token' => '000000', 'login_nonce' => 'not-an-issued-login-transaction' ), $cookies, array( 'X-WP-Nonce' => $rest_nonce ) );
			$validation_body = json_decode( $validation['body'], true );
			$check( 403 === $validation['status'] && 'invalid_request' === ( $validation_body['code'] ?? '' ), "AC-3 [{$label}] WP 2FA's sign-in route passes the guard and rejects the forged transaction (status {$validation['status']})" );
			$check( t44_ajax_refused( $cookies, 'heartbeat' ), "AC-3 [{$label}] an unlisted background action is refused" );
			$check( t44_ajax_refused( $cookies, 'remove_user_2fa' ) && t44_ajax_refused( $cookies, 'unlock_account' ), "AC-3 [{$label}] reset and unlock actions are refused while pending" );
		}

		// Enrolment the browser's way: the issued key from the set-up screen, the plugin's nonce, a code.
		$setup = two_factor_http( 'GET', admin_url( 'profile.php?show=wp-2fa-setup' ), array(), $session['cookies'] );
		preg_match( '/"totpKey"\s*:\s*"([A-Z2-7]+)"/', $setup['body'], $key );
		$enrol = two_factor_http(
			'POST',
			admin_url( 'admin-ajax.php' ),
			array(
				'action'                    => 'validate_authcode_via_ajax',
				'form[wp-2fa-totp-key]'     => $key[1] ?? '',
				'form[wp-2fa-totp-authcode]' => two_factor_code_for_key( $key[1] ?? '' ),
				'_wpnonce'                  => two_factor_nonce( $manager, $session['cookies'], 'wp-2fa-validate-authcode' ),
			),
			$session['cookies']
		);
		$answer = json_decode( $enrol['body'], true );
		$check( 200 === $enrol['status'] && true === ( $answer['success'] ?? false ), "AC-3 the allowed enrolment action completes enrolment (status {$enrol['status']})" );
		wp_cache_flush();
		$now = two_factor_http( 'GET', admin_url( 'index.php' ), array(), $session['cookies'] );
		$check( 200 === $now['status'], "AC-3 once enrolled, the administration opens (status {$now['status']})" );
		$rest_now = two_factor_http( 'GET', rest_url( 'wc/v3/orders' ), array(), $session['cookies'], array( 'X-WP-Nonce' => two_factor_nonce( $manager, $session['cookies'], 'wp_rest' ) ) );
		$check( 200 === $rest_now['status'], "AC-3 once enrolled, the same REST call works (status {$rest_now['status']})" );

		// A user with both a customer and a staff role is enforced as staff.
		list( $mixed, $mixed_password ) = two_factor_user( $s, 'customer' );
		$mixed->add_role( 'shop_manager' );
		$mixed_session = two_factor_password_step( $mixed->user_login, $mixed_password );
		$mixed_page    = two_factor_http( 'GET', admin_url( 'index.php' ), array(), $mixed_session['cookies'] );
		$check( 302 === $mixed_page['status'] && false !== strpos( $mixed_page['location'], 'show=wp-2fa-setup' ), 'AC-3 a customer who is also a shop manager is pending as staff' );

		// A pending administrator's application password is refused.
		list( $admin ) = two_factor_user( $s, 'administrator' );
		list( $app_password ) = \WP_Application_Passwords::create_new_application_password( $admin->ID, array( 'name' => 't44' ) );
		$api = two_factor_http( 'GET', rest_url( 'wp/v2/users/me' ), array(), array(), array( 'Authorization' => 'Basic ' . base64_encode( $admin->user_login . ':' . $app_password ) ) );
		$check( 403 === $api['status'], "AC-3 a pending administrator's application password is refused (status {$api['status']})" );

		return $ok;
	}
);

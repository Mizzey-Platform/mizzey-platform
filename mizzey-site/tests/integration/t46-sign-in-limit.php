<?php
/**
 * t46, AUTH-15 (AC-1, AC-4; FR-001, FR-007): the sign-in limit.
 *
 * The real command turns CoreX's login protection on with the documented settings and keeps every other setting. Over
 * HTTP, on wp-login.php and on the account page in both languages, five wrong passwords for one identity from one
 * address lock that pair: the right password is then refused with Mizzey's translated message, while another identity
 * signs in. Username and email are separate identities; case and spaces are not. A forwarding header from an untrusted
 * connection changes nothing; behind a trusted proxy, another address is unaffected. A lockout ends on its own with
 * protection still on. WP 2FA's challenge completes with the limit on. The security check reports the limit, and says
 * review when it is off or the default address is hidden. Settings are restored and only this scenario's attempt rows
 * are removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** The status the check gives a row, from its table output. */
function t46_row( string $out, string $key ): string {
	return 1 === preg_match( '/^' . preg_quote( $key, '/' ) . '\t(\S+)/m', $out, $m ) ? $m[1] : '';
}

run(
	new Scenario( 't46-sign-in-limit', 'AUTH-15: login attempts are rate limited per identity and address, with a documented setting' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$identities = array();
		$s->on_finish( static function () use ( &$identities ) { sign_in_forget( $identities ); } );
		$locked_en = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';
		$locked_ar = 'محاولات تسجيل دخول خاطئة كثيرة. يرجى الانتظار بضع دقائق ثم المحاولة مرة أخرى.';

		// ---- FR-001: the command sets its five fields and keeps the rest --------------------------------------
		sign_in_keep_option( $s, SIGN_IN_COREX_OPTION );
		update_option( SIGN_IN_COREX_OPTION, array( 'retain_days' => 45, 'custom_slug' => 'corex-login', 'threshold' => 9 ) );
		sign_in_baseline( $s );
		$stored = (array) get_option( SIGN_IN_COREX_OPTION, array() );
		$owned  = array( 'enabled' => true, 'threshold' => 5, 'window_seconds' => 300, 'lockout_seconds' => 900, 'block_default_endpoints' => false );
		$check( array_intersect_key( $stored, $owned ) == $owned, 'FR-001 the command sets the five fields' ); // phpcs:ignore Universal.Operators.StrictComparisons
		$check( 45 === (int) ( $stored['retain_days'] ?? 0 ) && 'corex-login' === ( $stored['custom_slug'] ?? '' ), 'FR-001 and keeps the others as they were' );

		// A wrong password five times, then the right one.
		$lock = function ( callable $attempt, string $login, string $password, array $headers = array() ) use ( &$identities ): array {
			$identities[] = $login;
			for ( $i = 0; $i < 5; $i++ ) {
				$attempt( $login, $password . '-wrong', $headers );
			}
			return $attempt( $login, $password, $headers );
		};
		$wp_login = static fn ( string $l, string $p, array $h = array() ): array => sign_in_wp_login( $l, $p, $h );

		// ---- AC-1 on wp-login.php ----------------------------------------------------------------------------
		list( $a, $a_pass ) = two_factor_user( $s, 'customer' );
		$refused = $lock( $wp_login, $a->user_login, $a_pass );
		$check( ! sign_in_has_session( $refused ) && false !== strpos( $refused['body'], $locked_en ), 'AC-1 [wp-login] after five failures the right password is refused with the message' );
		list( $b, $b_pass ) = two_factor_user( $s, 'customer' );
		$identities[] = $b->user_login;
		$check( sign_in_has_session( sign_in_wp_login( $b->user_login, $b_pass ) ), 'AC-1 [wp-login] another identity from the same address signs in' );

		// ---- AC-1 on the account page, both languages --------------------------------------------------------
		foreach ( array( 'en' => $locked_en, 'ar' => $locked_ar ) as $lang => $message ) {
			list( $c, $c_pass ) = two_factor_user( $s, 'customer' );
			$account = static fn ( string $l, string $p, array $h = array() ): array => sign_in_account( $lang, $l, $p, $h );
			$refused = $lock( $account, $c->user_login, $c_pass );
			$check( ! sign_in_has_session( $refused ) && false !== strpos( $refused['body'], $message ), "AC-1 [account, {$lang}] after five failures the right password is refused with the translated message" );
		}

		// ---- AC-1: what counts as one identity -----------------------------------------------------------------
		list( $e, $e_pass ) = two_factor_user( $s, 'customer' );
		$lock( $wp_login, $e->user_login, $e_pass );
		$identities[] = $e->user_email;
		$check( sign_in_has_session( sign_in_wp_login( $e->user_email, $e_pass ) ), 'AC-1 the email is a separate identity from the username' );
		list( $f, $f_pass ) = two_factor_user( $s, 'customer' );
		$identities[] = $f->user_login;
		foreach ( array( strtoupper( $f->user_login ), '  ' . $f->user_login . ' ', ucfirst( $f->user_login ), $f->user_login, strtoupper( $f->user_login ) ) as $variant ) {
			sign_in_wp_login( $variant, $f_pass . '-wrong' );
		}
		$check( ! sign_in_has_session( sign_in_wp_login( $f->user_login, $f_pass ) ), 'AC-1 case and surrounding spaces count as one identity' );

		// ---- AC-1: addresses -----------------------------------------------------------------------------------
		list( $g, $g_pass ) = two_factor_user( $s, 'customer' );
		$lock( $wp_login, $g->user_login, $g_pass );
		$spoofed = sign_in_wp_login( $g->user_login, $g_pass, array( 'X-Forwarded-For' => '203.0.113.9' ) );
		$check( ! sign_in_has_session( $spoofed ), 'AC-1 a forwarding header from an untrusted connection does not change the address' );
		sign_in_corex_set( array( 'trusted_proxy_mode' => true, 'trusted_proxy_ranges' => array( '127.0.0.1/32', '::1/128' ) ) );
		list( $h, $h_pass ) = two_factor_user( $s, 'customer' );
		$from_one = array( 'X-Forwarded-For' => '198.51.100.1' );
		$refused  = $lock( $wp_login, $h->user_login, $h_pass, $from_one );
		$other    = sign_in_wp_login( $h->user_login, $h_pass, array( 'X-Forwarded-For' => '198.51.100.2' ) );
		$check( ! sign_in_has_session( $refused ) && sign_in_has_session( $other ), 'AC-1 behind a trusted proxy, the same identity from another address is unaffected' );
		sign_in_corex_set( array( 'trusted_proxy_mode' => false, 'trusted_proxy_ranges' => array() ) );

		// ---- AC-1: a lockout ends on its own, protection still on ------------------------------------------
		sign_in_corex_set( array( 'lockout_seconds' => 4 ) );
		list( $i, $i_pass ) = two_factor_user( $s, 'customer' );
		$refused = $lock( $wp_login, $i->user_login, $i_pass );
		sleep( 5 );
		$after = sign_in_wp_login( $i->user_login, $i_pass );
		$check( ! sign_in_has_session( $refused ) && sign_in_has_session( $after ), 'AC-1 after the lockout period the identity signs in' );
		$check( true === (bool) ( get_option( SIGN_IN_COREX_OPTION )['enabled'] ?? false ), 'AC-1 and protection stayed on' );
		sign_in_corex_set( array( 'lockout_seconds' => 900 ) );

		// ---- ADM-131 unchanged: the two-factor challenge with the limit on ---------------------------------
		two_factor_baseline( $s );
		list( $staff, $staff_pass ) = two_factor_user( $s, 'administrator' );
		two_factor_enrol( $staff );
		$identities[] = $staff->user_login;
		$step  = sign_in_wp_login( $staff->user_login, $staff_pass );
		$wrong = two_factor_answer( $step, '000000' === two_factor_code( $staff ) ? '111111' : '000000' );
		$check( false !== strpos( $step['body'], 'validate_2fa_form' ) && ! sign_in_has_session( $wrong ), 'ADM-131 the challenge appears and a wrong code is refused with the limit on' );
		$retry = two_factor_answer( $wrong, two_factor_code( $staff ) );
		$check( two_factor_signed_in( $retry['cookies'] ), 'ADM-131 the right code then completes sign-in' );

		// ---- AC-4: the security check --------------------------------------------------------------------------
		$report = sign_in_wp( 'mizzey security check' );
		$check( 'pass' === t46_row( $report['out'], 'sign_in_limit' ), 'AC-4 with the baseline, sign_in_limit passes' );
		sign_in_corex_set( array( 'threshold' => 7, 'lockout_seconds' => 600 ) );
		$check( 'pass' === t46_row( sign_in_wp( 'mizzey security check' )['out'], 'sign_in_limit' ), 'AC-4 an owner\'s change to valid settings still passes' );
		sign_in_corex_set( array( 'block_default_endpoints' => true ) );
		$hidden = sign_in_wp( 'mizzey security check' );
		$check( 'review' === t46_row( $hidden['out'], 'sign_in_limit' ) && 1 === $hidden['code'], 'AC-4 with the default address hidden, sign_in_limit says review' );
		sign_in_corex_set( array( 'block_default_endpoints' => false, 'enabled' => false ) );
		$off = sign_in_wp( 'mizzey security check' );
		$check( 'review' === t46_row( $off['out'], 'sign_in_limit' ) && 1 === $off['code'], 'AC-4 with the limit off, sign_in_limit says review' );

		return $ok;
	}
);

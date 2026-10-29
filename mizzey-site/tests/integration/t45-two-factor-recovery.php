<?php
/**
 * t45, ADM-131 (AC-4, FR-006): recovery from a lost second factor is controlled.
 *
 * Over HTTP: a backup code completes sign-in once and is refused when used again, while another unused code works. The
 * runbook's own commands (`docs/two-factor-recovery.md`), run as the Developer would, remove an administrator's second
 * factor and end their sessions; that administrator then signs in with the password and is sent straight to enrol again.
 * Through WP 2FA's own reset action over HTTP, a shop manager cannot reset an administrator and another administrator
 * can.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';

run(
	new Scenario( 't45-two-factor-recovery', 'ADM-131: backup codes work once; a reset follows the written runbook' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		two_factor_baseline( $s );

		// ---- Backup codes --------------------------------------------------------------------------------------
		list( $admin, $password ) = two_factor_user( $s, 'administrator' );
		$codes = two_factor_enrol( $admin );
		$check( count( $codes ) >= 2, 'the enrolment gives backup codes (' . count( $codes ) . ')' );
		$first = two_factor_answer( two_factor_password_step( $admin->user_login, $password ), $codes[0], 'backup_codes' );
		$check( two_factor_signed_in( $first['cookies'] ), 'AC-4 a backup code completes sign-in' );
		$again = two_factor_answer( two_factor_password_step( $admin->user_login, $password ), $codes[0], 'backup_codes' );
		$check( ! two_factor_signed_in( $again['cookies'] ), 'AC-4 the same backup code is refused when used again' );
		$other = two_factor_answer( two_factor_password_step( $admin->user_login, $password ), $codes[1], 'backup_codes' );
		$check( two_factor_signed_in( $other['cookies'] ), 'AC-4 another unused backup code still works' );

		// ---- The runbook's reset, run as the Developer would --------------------------------------------------
		$wp  = escapeshellarg( getenv( 'MIZZEY_WP_BIN' ) ?: 'wp' ) . ' --path=' . escapeshellarg( ABSPATH );
		$out = array();
		$a   = 0;
		$b   = 0;
		exec( $wp . ' eval ' . escapeshellarg( '\WP2FA\Admin\Helpers\User_Helper::remove_2fa_for_user( ' . $admin->ID . ' );' ) . ' 2>&1', $out, $a );
		exec( $wp . ' user session destroy ' . (int) $admin->ID . ' --all 2>&1', $out, $b );
		$check( 0 === $a && 0 === $b, "AC-4 the runbook's two commands run (exits {$a} and {$b})" );
		$check( ! two_factor_signed_in( $other['cookies'] ), "AC-4 the reset ends the administrator's sessions" );
		$after = two_factor_password_step( $admin->user_login, $password );
		$check( false === strpos( $after['body'], 'validate_2fa_form' ) && 302 === $after['status'], 'AC-4 after the reset, the password is no longer followed by a challenge' );
		$page = two_factor_http( 'GET', admin_url( 'index.php' ), array(), $after['cookies'] );
		$check( 302 === $page['status'] && false !== strpos( $page['location'], 'show=wp-2fa-setup' ), 'AC-4 and the administrator is sent to enrol again before anything else' );

		// ---- Who may reset whom --------------------------------------------------------------------------------
		// Through WP 2FA's own reset action, over HTTP, by staff signed in with two factors: a shop manager is refused,
		// another administrator succeeds.
		list( $target, $target_password ) = two_factor_user( $s, 'administrator' );
		two_factor_enrol( $target );
		foreach ( array( 'shop_manager' => false, 'administrator' => true ) as $role => $allowed ) {
			list( $actor, $actor_password ) = two_factor_user( $s, $role );
			two_factor_enrol( $actor );
			$signed = two_factor_answer( two_factor_password_step( $actor->user_login, $actor_password ), two_factor_code( $actor ) );
			$check( two_factor_signed_in( $signed['cookies'] ), "the {$role} who attempts the reset is signed in with two factors" );
			$reset  = two_factor_http(
				'POST',
				admin_url( 'admin-ajax.php' ),
				array(
					'action'       => 'remove_user_2fa',
					'user_id'      => $target->ID,
					'admin_reset'  => 1,
					'wp_2fa_nonce' => two_factor_nonce( $actor, $signed['cookies'], 'wp-2fa-remove-user-2fa-nonce' ),
				),
				$signed['cookies']
			);
			$answer = json_decode( $reset['body'], true );
			$check( $allowed === ( $answer['success'] ?? null ), "AC-4 a {$role} " . ( $allowed ? 'can' : 'cannot' ) . ' reset an administrator\'s second factor' );
			wp_cache_delete( $target->ID, 'user_meta' );
			$removed = '' === get_user_meta( $target->ID, \WP2FA\Admin\Helpers\User_Helper::ENABLED_METHODS_META_KEY, true );
			$check( $allowed === $removed, "AC-4 and the administrator's second factor is " . ( $allowed ? 'removed' : 'kept' ) );
		}
		$pending = two_factor_password_step( $target->user_login, $target_password );
		$page    = two_factor_http( 'GET', admin_url( 'index.php' ), array(), $pending['cookies'] );
		$check( 302 === $page['status'] && false !== strpos( $page['location'], 'show=wp-2fa-setup' ), 'AC-4 the reset administrator must enrol again' );

		return $ok;
	}
);

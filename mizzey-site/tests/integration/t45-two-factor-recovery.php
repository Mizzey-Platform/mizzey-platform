<?php
/**
 * t45, ADM-131 (AC-4, FR-006): recovery from a lost second factor is controlled.
 *
 * Over HTTP: a backup code completes sign-in once and is refused when used again, while another unused code works. The
 * runbook's own commands (`docs/two-factor-recovery.md`), run as the Developer would, remove an administrator's second
 * factor and end their sessions; that administrator then signs in with the password and is sent straight to enrol again.
 * A shop manager cannot reset an administrator.
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
		// Asked as each user signed in, as the reset screen asks: WooCommerce's edit_user rule reads the current user.
		list( $manager )     = two_factor_user( $s, 'shop_manager' );
		list( $target )      = two_factor_user( $s, 'administrator' );
		list( $other_admin ) = two_factor_user( $s, 'administrator' );
		$previous            = get_current_user_id();
		wp_set_current_user( $manager->ID );
		$manager_may = current_user_can( 'edit_user', $target->ID );
		wp_set_current_user( $other_admin->ID );
		$admin_may = current_user_can( 'edit_user', $target->ID );
		wp_set_current_user( $previous );
		$check( ! $manager_may, 'AC-4 a shop manager cannot reset an administrator (no edit_user on them)' );
		$check( $admin_may, 'AC-4 another administrator can' );

		return $ok;
	}
);

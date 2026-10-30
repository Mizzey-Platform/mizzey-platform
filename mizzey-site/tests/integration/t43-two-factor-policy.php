<?php
/**
 * t43, ADM-131 (AC-2, AC-5; FR-002, FR-005): the baseline command, the owner's setting, and the check.
 *
 * The real command applies the baseline: its owned fields take their values and an unrelated field keeps its own. A
 * change the owner makes in WP 2FA's setting takes effect in a fresh request. The security check, run as its own
 * process, exits 0 on the full baseline; each way protection can lapse makes its row say review and the command exit 1:
 * the plugin deactivated, the emergency bypass on, a database fallback key, a leftover database key row. An absent
 * bypass and DISABLE_2FA_LOGIN=false both pass. Global state is restored after each case.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\Cli\SecurityCommand;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';

/**
 * Run a WP-CLI command line in its own process.
 *
 * @return array{code:int,out:string}
 */
function t43_wp( string $args ): array {
	$out  = array();
	$code = 0;
	exec( escapeshellarg( getenv( 'MIZZEY_WP_BIN' ) ?: 'wp' ) . ' --path=' . escapeshellarg( ABSPATH ) . ' ' . $args . ' 2>&1', $out, $code );
	return array( 'code' => $code, 'out' => implode( "\n", $out ) );
}

/** The status the check gives a row, from its table output. */
function t43_row( string $out, string $key ): string {
	return 1 === preg_match( '/^' . preg_quote( $key, '/' ) . '\t(\S+)/m', $out, $m ) ? $m[1] : '';
}

run(
	new Scenario( 't43-two-factor-policy', 'ADM-131: the baseline command, the owner\'s setting, and the protection check' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		// The restore is registered first, by the baseline helper, so nothing below can outlive the scenario.
		two_factor_baseline( $s );
		$stored = (array) \WP2FA\Utils\Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
		$stored['enable_destroy_session'] = 'enable_destroy_session';
		// Exclusions that would exempt staff, and a customer named for enforcement: the baseline must clear all three.
		$stored['excluded_roles'] = array( 'shop_manager' );
		$stored['excluded_users'] = array( 'pilot_admin' );
		$stored['enforced_users'] = array( 'a-customer' );
		\WP2FA\WP2FA::update_plugin_settings( $stored, false, WP_2FA_POLICY_SETTINGS_NAME );
		$applied = t43_wp( 'mizzey security two-factor-baseline' );
		$check( 0 === $applied['code'], 'FR-002 the baseline command succeeds with an unrelated field and contrary exclusions present' );
		two_factor_reload();

		$policy = (array) \WP2FA\Utils\Settings_Utils::get_option( WP_2FA_POLICY_SETTINGS_NAME, array() );
		$owned  = SecurityCommand::baselinePolicy();
		$check( array_intersect_key( $policy, $owned ) == $owned, 'FR-002 the baseline command sets every owned field' ); // phpcs:ignore Universal.Operators.StrictComparisons
		$check( 'enable_destroy_session' === ( $policy['enable_destroy_session'] ?? '' ), 'FR-002 and keeps an unrelated field as it was' );
		$check( \WP2FA\Utils\Settings_Utils::create_settings_hash( $policy ) === \WP2FA\Utils\Settings_Utils::get_option( WP_2FA_PREFIX . 'settings_hash' ), 'FR-002 through WP 2FA\'s API: the stored policy hash is the hash of this policy' );

		// A policy that drifts from the baseline is review, whichever owned field drifts.
		$drifts = array(
			'email codes on'        => array( 'enable_email' => 'enable_email' ),
			'passkeys on'           => array( 'enable_passkeys' => 'enable_passkeys' ),
			'expiry action changed' => array( 'grace-policy-after-expire-action' => 'block-user' ),
		);
		foreach ( $drifts as $label => $change ) {
			\WP2FA\WP2FA::update_plugin_settings( array_replace( $policy, $change ), false, WP_2FA_POLICY_SETTINGS_NAME );
			$drifted = t43_wp( 'mizzey security check' );
			$check( 'review' === t43_row( $drifted['out'], 'two_factor_roles' ) && 1 === $drifted['code'], "AC-5 with {$label}, the policy row says review" );
		}
		\WP2FA\WP2FA::update_plugin_settings( $policy, false, WP_2FA_POLICY_SETTINGS_NAME );

		// AC-2: the owner's setting decides. A fresh request asks WP 2FA whether a shop manager is enforced.
		list( $manager ) = two_factor_user( $s, 'shop_manager' );
		$enforced = fn (): string => trim( t43_wp( 'eval ' . escapeshellarg( "echo (int) \\WP2FA\\Admin\\Helpers\\User_Helper::is_enforced( get_user_by( 'id', " . $manager->ID . " ), true );" ) )['out'] );
		$check( '1' === $enforced(), 'AC-2 with the baseline, a shop manager is required to use two-factor' );
		$owner_choice                   = $policy;
		$owner_choice['enforced_roles'] = array( 'administrator' );
		\WP2FA\WP2FA::update_plugin_settings( $owner_choice, false, WP_2FA_POLICY_SETTINGS_NAME );
		$check( '0' === $enforced(), 'AC-2 when the owner requires it for administrators only, a shop manager is not' );
		// A second shop manager, judged in its own request under the same policy: WP 2FA now holds "optional" for both.
		list( $second ) = two_factor_user( $s, 'shop_manager' );
		t43_wp( 'eval ' . escapeshellarg( "\\WP2FA\\Admin\\Helpers\\User_Helper::is_enforced( get_user_by( 'id', " . $second->ID . " ), true );" ) );
		list( $enrolled ) = two_factor_user( $s, 'shop_manager' );
		two_factor_enrol( $enrolled );
		\WP2FA\WP2FA::update_plugin_settings( $policy, false, WP_2FA_POLICY_SETTINGS_NAME );
		// AC-5: the staff-to-enrol report follows the policy in force, not a state WP 2FA kept from an earlier policy. WP 2FA
		// refreshes that state for the first user it reads in a process only, so a report over many staff must not rely on it.
		$report  = t43_wp( 'mizzey security check' );
		$pending = 1 === preg_match( '/^two_factor_pending_staff\t\d+\t(.*)$/m', $report['out'], $line ) ? $line[1] : '';
		$check( false !== strpos( $pending, $manager->user_login ) && false !== strpos( $pending, $second->user_login ), 'AC-5 after the policy changes, every staff member still to enrol is reported' );
		$check( '' !== $pending && false === strpos( $pending, $enrolled->user_login ), 'AC-5 and a staff member already enrolled is not' );

		// AC-5: the check on the full baseline.
		$baseline = t43_wp( 'mizzey security check' );
		foreach ( array( 'two_factor_active', 'two_factor_roles', 'two_factor_bypass', 'two_factor_key' ) as $row ) {
			$check( 'pass' === t43_row( $baseline['out'], $row ), "AC-5 on the baseline, {$row} passes" );
		}
		$check( 0 === $baseline['code'], "AC-5 and the check exits 0 (exit {$baseline['code']})" );
		$check( 1 === preg_match( '/^two_factor_pending_staff\t\d+/m', $baseline['out'] ), 'AC-5 the staff still to enrol are reported' );

		// AC-5: each way protection can lapse.
		// Single quotes inside: on Windows, escapeshellarg() turns double quotes into spaces.
		$bypass_on  = t43_wp( '--exec=' . escapeshellarg( "define( 'DISABLE_2FA_LOGIN', true );" ) . ' mizzey security check' );
		$bypass_off = t43_wp( '--exec=' . escapeshellarg( "define( 'DISABLE_2FA_LOGIN', false );" ) . ' mizzey security check' );
		$check( 'review' === t43_row( $bypass_on['out'], 'two_factor_bypass' ) && 1 === $bypass_on['code'], 'AC-5 with the emergency bypass on, the bypass row says review and the check exits 1' );
		$check( 'pass' === t43_row( $bypass_off['out'], 'two_factor_bypass' ), 'AC-5 DISABLE_2FA_LOGIN=false is not a bypass' );
		$fallback = t43_wp( '--exec=' . escapeshellarg( "define( 'WP2FA_SECRET_IS_IN_DB', true );" ) . ' mizzey security check' );
		$check( 'review' === t43_row( $fallback['out'], 'two_factor_key' ) && 1 === $fallback['code'], 'AC-5 with a database fallback key, the key row says review' );

		// Only a row this scenario created is removed afterwards: a failed insert means one exists, and it is left alone.
		if ( 1 !== $wpdb->insert( $wpdb->options, array( 'option_name' => 'wp_2fa_secret_key', 'option_value' => 'not-a-key', 'autoload' => 'no' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw new \RuntimeException( 'The database-key probe was not created; existing rows were left unchanged.' );
		}
		try {
			$leftover = t43_wp( 'mizzey security check' );
		} finally {
			$wpdb->delete( $wpdb->options, array( 'option_name' => 'wp_2fa_secret_key' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$check( 'review' === t43_row( $leftover['out'], 'two_factor_key' ) && 1 === $leftover['code'], 'AC-5 with a leftover database key row, the key row says review' );
		$check( false === strpos( $leftover['out'], 'not-a-key' ), 'and the check never prints a key value' );

		t43_wp( 'plugin deactivate wp-2fa' );
		try {
			$off = t43_wp( 'mizzey security check' );
		} finally {
			t43_wp( 'plugin activate wp-2fa' );
		}
		$check( 'review' === t43_row( $off['out'], 'two_factor_active' ) && 1 === $off['code'], 'AC-5 with WP 2FA deactivated, the active row says review and the check exits 1' );
		$check( 'active' === trim( t43_wp( 'plugin get wp-2fa --field=status' )['out'] ), 'and WP 2FA is active again' );

		return $ok;
	}
);

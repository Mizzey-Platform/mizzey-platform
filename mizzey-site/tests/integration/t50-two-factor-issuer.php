<?php
/**
 * t50, ADM-131 addition (FR-008): the authenticator entry is named after the site's title, not its address.
 *
 * In-process: WP 2FA's QR code for a staff member is exactly the one WP 2FA draws for "<title>:<login>" with the title
 * as issuer, and not the one for the address; without Mizzey's filter it is the address one. Colons in the title are
 * removed; a translation of the title does not change the entry; every other site_url() is untouched. Over HTTP: a
 * staff member who must enrol asks WP 2FA for a new key on the set-up screen, and the QR code returned is the one for
 * the title. The title is restored when the scenario finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\TwoFactorIssuer;
use WP2FA\Authenticator\Authentication;
use WP2FA\Methods\TOTP;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** WP 2FA's QR code for a staff member and a key, drawn now. */
function t50_qr( \WP_User $user, string $key ): string {
	wp_set_current_user( $user->ID );
	return (string) TOTP::get_qr_code( $key );
}

run(
	new Scenario( 't50-two-factor-issuer', 'ADM-131 addition: the authenticator entry carries the site\'s title' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		sign_in_options( $s, array( 'blogname' => 'Mizzey (t50)' ) );
		wp_cache_delete( 'alloptions', 'options' );

		list( $staff ) = two_factor_user( $s, 'administrator' );
		$key   = Authentication::generate_key();
		$host  = trim( str_replace( array( 'http://', 'https://' ), '', site_url() ), '/' );
		$named = Authentication::get_google_qr_code( 'Mizzey (t50):' . $staff->user_login, $key, 'Mizzey (t50)' );
		$plain = Authentication::get_google_qr_code( $host . ':' . $staff->user_login, $key, $host );
		$drawn = t50_qr( $staff, $key );
		$check( '' !== $drawn && $named !== $plain, 'FR-008 WP 2FA can draw the QR code on this runtime' );
		$check( $named === $drawn, 'FR-008 the QR code names the entry after the site\'s title' );

		remove_filter( 'site_url', array( TwoFactorIssuer::class, 'issuer' ), 10 );
		$without = t50_qr( $staff, $key );
		add_filter( 'site_url', array( TwoFactorIssuer::class, 'issuer' ), 10, 3 );
		$check( $plain === $without, 'FR-008 without the filter, WP 2FA names it after the address (the control)' );

		update_option( 'blogname', 'Mizzey: Store' );
		wp_cache_delete( 'alloptions', 'options' );
		$check( Authentication::get_google_qr_code( 'Mizzey Store:' . $staff->user_login, $key, 'Mizzey Store' ) === t50_qr( $staff, $key ), 'FR-008 a colon in the title is removed (the label is name:login)' );

		update_option( 'blogname', 'Mizzey (t50)' );
		wp_cache_delete( 'alloptions', 'options' );
		$translated = static fn (): string => 'ميزي';
		add_filter( 'option_blogname', $translated );
		$check( $named === t50_qr( $staff, $key ), 'FR-008 a translation of the title does not change the entry' );
		remove_filter( 'option_blogname', $translated );

		$check( false !== strpos( site_url(), '://' ) && false !== strpos( site_url( 'wp-login.php' ), 'wp-login.php' ), 'FR-008 every other site_url() is untouched' );

		// Over HTTP: the set-up screen's own "new key" request returns a QR code for the title.
		two_factor_baseline( $s );
		list( $pending, $pending_pass ) = two_factor_user( $s, 'shop_manager' );
		$step   = two_factor_password_step( $pending->user_login, $pending_pass );
		$cookies = $step['cookies'];
		$nonce  = two_factor_nonce( $pending, $cookies, 'wp-2fa-backup-codes-generate-json-' . $pending->ID );
		$answer = two_factor_http( 'POST', admin_url( 'admin-ajax.php' ), array( 'action' => 'regenerate_authentication_key', '_ajax_nonce' => $nonce ), $cookies );
		$data   = json_decode( $answer['body'], true );
		$new    = (string) ( $data['data']['key'] ?? '' );
		$expect = Authentication::get_google_qr_code( 'Mizzey (t50):' . $pending->user_login, $new, 'Mizzey (t50)' );
		$check( true === ( $data['success'] ?? null ) && '' !== $new && $expect === ( $data['data']['qr'] ?? null ), "FR-008 [HTTP] the set-up screen's new key comes with a QR code for the title (status {$answer['status']})" );

		return $ok;
	}
);

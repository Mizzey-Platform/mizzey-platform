<?php
/**
 * t85: the transactional email service (INT-05). See specs/045-email-transport.
 *
 * AC-1: with no relay named nothing changes; with one named (constants, as the server sets them), every message
 *       leaves through it with WooCommerce's sender, and the password never reaches a screen.
 * AC-2: a failed message is logged under mizzey-email with addresses masked, and raised to operations.
 * AC-3: the test button sends one message to the person asking, behind a nonce and the capability.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Emails\Transport;
use MizzeySite\Security\Monitoring;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't85-email-transport', 'INT-05: the transactional email service' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag    = 't85' . wp_rand( 1000, 9999 );
		$mailer = static function (): object {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
			$m = new \PHPMailer\PHPMailer\PHPMailer( true );
			do_action_ref_array( 'phpmailer_init', array( &$m ) );
			return $m;
		};

		// ---- AC-1: nothing named, nothing changes ---------------------------------------------------------------
		$check( ! defined( 'MIZZEY_SMTP_HOST' ), 'precondition: this runtime names no relay' );
		$plain = $mailer();
		$check( 'smtp' !== $plain->Mailer && ! Transport::configured(), 'AC-1 with no relay named, WordPress sends mail itself' );

		// The server names a relay.
		define( 'MIZZEY_SMTP_HOST', 'smtp.relay.example.invalid' );
		define( 'MIZZEY_SMTP_PORT', '2525' );
		define( 'MIZZEY_SMTP_USER', "{$tag}user" );
		define( 'MIZZEY_SMTP_PASSWORD', " {$tag}-secret-pass " );
		$keep = get_option( 'woocommerce_email_from_address', null );
		$name = get_option( 'woocommerce_email_from_name', null );
		$s->on_finish(
			static function () use ( $keep, $name ): void {
				null === $keep ? delete_option( 'woocommerce_email_from_address' ) : update_option( 'woocommerce_email_from_address', $keep );
				null === $name ? delete_option( 'woocommerce_email_from_name' ) : update_option( 'woocommerce_email_from_name', $name );
				wp_set_current_user( 0 );
			}
		);
		update_option( 'woocommerce_email_from_address', "orders-{$tag}@example.com" );
		update_option( 'woocommerce_email_from_name', 'Mizzey' );
		$m = $mailer();
		$check( 'smtp' === $m->Mailer && 'smtp.relay.example.invalid' === $m->Host && 2525 === (int) $m->Port && 'tls' === $m->SMTPSecure && true === $m->SMTPAuth && "{$tag}user" === $m->Username && " {$tag}-secret-pass " === $m->Password && "orders-{$tag}@example.com" === $m->Sender, 'AC-1 every message leaves through the relay the server names, with TLS, authenticated, and WooCommerce\'s sender as its return path' );
		$preset = ( static function (): object {
			$m         = new \PHPMailer\PHPMailer\PHPMailer( true );
			$m->Sender = 'bounces@relay.example';
			do_action_ref_array( 'phpmailer_init', array( &$m ) );
			return $m;
		} )();
		$check( 'bounces@relay.example' === $preset->Sender, 'AC-1 a bounce address set by someone else is kept; the password is used exactly as given' );
		$check( "orders-{$tag}@example.com" === apply_filters( 'wp_mail_from', 'wordpress@example.com' ) && 'Mizzey' === apply_filters( 'wp_mail_from_name', 'WordPress' ) && 'shop@x.example' === apply_filters( 'wp_mail_from', 'shop@x.example' ) && 'wordpress@vendor.example' === apply_filters( 'wp_mail_from', 'wordpress@vendor.example' ), 'AC-1 WordPress\'s own messages take WooCommerce\'s sender; a sender set on purpose is kept' );
		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );
		ob_start();
		Transport::render();
		$screen = (string) ob_get_clean();
		$check( str_contains( $screen, 'smtp.relay.example.invalid:2525' ) && ! str_contains( $screen, "{$tag}-secret-pass" ), 'AC-1 Settings, Email delivery shows the relay and never its password' );

		// ---- AC-2: a failure, logged and raised -------------------------------------------------------------------
		$raised = array();
		Monitoring::$sink = static function ( array $entry ) use ( &$raised ) { $raised[] = $entry; };
		$s->on_finish( static function () { Monitoring::$sink = null; } );
		do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', "SMTP Error: The following recipients failed: {$tag}customer@example.com, jane.doe!{$tag}@example.com" ) );
		$dir   = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : trailingslashit( wp_upload_dir()['basedir'] ) . 'wc-logs/';
		$lines = '';
		foreach ( (array) glob( trailingslashit( $dir ) . Transport::SOURCE . '-*.log' ) as $file ) {
			$lines .= (string) file_get_contents( $file );
		}
		$check( str_contains( $lines, 'mail not sent: SMTP Error: The following recipients failed: [email]' ) && ! str_contains( $lines, "{$tag}customer@example.com" ) && ! str_contains( $lines, "{$tag}@example.com" ), 'AC-2 a failed message is logged under mizzey-email, with the address masked' );
		$check( array() !== array_filter( $raised, static fn ( $r ) => Transport::SOURCE === $r['where'] ), 'AC-2 the failure is raised to operations' );
		delete_transient( 'mizzey_monitor_' . md5( 'integration|' . Transport::SOURCE ) );

		// ---- AC-3: the test button ------------------------------------------------------------------------------
		$sent = array();
		$trap = static function ( $pre, $atts ) use ( &$sent ) { $sent[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_wp_mail', $trap, 10 ); } );
		$redirect = '';
		$catch    = static function ( $location ) use ( &$redirect ) { $redirect = (string) $location; throw new \RuntimeException( 'redirected' ); };
		add_filter( 'wp_redirect', $catch, 1 );
		$_REQUEST['_wpnonce'] = wp_create_nonce( Transport::ACTION );
		$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
		try {
			Transport::test();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		remove_filter( 'wp_redirect', $catch, 1 );
		$_REQUEST = array();
		$_POST    = array();
		$check( 1 === count( $sent ) && "{$tag}adm@example.invalid" === ( $sent[0]['to'] ?? '' ) && str_contains( $redirect, 'mizzey_test=sent' ), 'AC-3 the test button sends one message to the person asking' );
		$editor = (int) wp_insert_user( array( 'user_login' => "{$tag}ed", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}ed@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $editor );
		wp_set_current_user( $editor );
		$refused = false;
		$die     = static function () use ( &$refused ) { return static function () use ( &$refused ) { $refused = true; throw new \RuntimeException( 'refused' ); }; };
		add_filter( 'wp_die_handler', $die, 1 );
		try {
			Transport::test();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		remove_filter( 'wp_die_handler', $die, 1 );
		$check( $refused && 1 === count( $sent ), 'AC-3 a staff member who may not manage the site cannot send it' );

		return $ok;
	}
);

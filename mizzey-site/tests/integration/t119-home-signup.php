<?php
/**
 * t119, spec 066 slice 5: the home page's "Stay in touch" sign-up, as the closed design draws it (AC-3, HOME-09; the
 * owner's ruling of 25 November 2026 on DECISIONS A10: one small sign-up and consent record, no customer account, no
 * campaign engine).
 *
 * On the server, over addresses named "sg-...@example.com", removed when the scenario ends:
 *
 * - the section renders the labelled email field (type email, autocomplete email), the optional box in the words the
 *   board draws, unticked, the Sign up button, a nonce and a trap field, and no link to the account page;
 * - an address that is not an email is refused with words that say how to fix it, and nothing is stored;
 * - a sign-up stores one record: the address in lower case, the language, the date, the box's answer and its wording
 *   version only when it is ticked, the source "home", status subscribed and a random token for leaving; no
 *   WordPress account is made; one welcome email is queued, and sent in the sign-up's language with its leave link;
 * - signing up again gives the same answer and keeps one record (a tick given later is recorded), with no second email;
 * - a filled trap, a missing nonce and too many sign-ups from one address are refused without storing anything, and
 *   the answer never says whether an address is already on the list;
 * - the form's token (Security\SignupCheck) is single-use, for this form only, and refused within its first seconds;
 * - with the table missing the answer is "failed" with its own words, logged without the address;
 * - a welcome the mail server refuses fails its action and is queued again by a repeat sign-up; past the hourly
 *   ceiling it waits an hour; IPv6 addresses are counted per /64;
 * - leaving marks the record "left" and keeps it; signing up again after leaving makes it subscribed again;
 * - over HTTP: the leave page, asked and confirmed, loads no Tag Manager container or data layer and sends no
 *   referrer; a mail program's one-click POST leaves; an unknown token answers 404; past the limit the storefront's
 *   request is answered 429; a signed-in customer's sign-up is one record and leaves the account as it was;
 * - the list screen is read-only, searchable and counted, for the roles that manage the store's customers
 *   (`manage_woocommerce`), not for the agency's Marketing role (MKT-22), and offers no export.
 *
 * In Chromium and WebKit (mizzey-site/tests/browser/home-signup.cjs), at 390 (touch) and 1440, in English and Arabic:
 * the section as the boards measure it; the invalid state; the busy button with the i-dot loader while the server has
 * not answered; the success state replacing the form at the same height with nothing below moving and the focus on the
 * message; the same states with JavaScript off; the leave page in Arabic. Then, on the server: each address signed up
 * in the browser is one record in its language, with at most one welcome queued, and no account.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Blocks\Banners;
use MizzeySite\Marketing\TagManager;
use MizzeySite\Security\SignupCheck;
use MizzeySite\Signups\SignupRoutes;
use MizzeySite\Signups\SignupScreen;
use MizzeySite\Signups\SignupService;
use MizzeySite\Signups\SignupStore;
use MizzeySite\Signups\Welcome;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/** Run the browser script on a plan. @return array<string,mixed> */
function t119_browser( array $plan ): array {
	$file = wp_tempnam( 't119-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/home-signup.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/** The welcome actions queued for a record. */
function t119_queued( int $id ): int {
	if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
		return -1;
	}
	return count( as_get_scheduled_actions( array( 'hook' => Welcome::HOOK, 'args' => array( $id ), 'status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => 10 ), 'ids' ) );
}

run(
	new Scenario( 't119-home-signup', 'AC-3 (HOME-09), slice 5: the home sign-up as drawn, its record, the welcome email, leaving, and the read-only list' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag     = 'sg-' . strtolower( wp_generate_password( 5, false, false ) );
		$address = '127.0.0.1';
		$mail    = static fn ( string $name ): string => "{$tag}-{$name}@example.com";
		// Other builders share the database, so accounts are counted by this run's addresses only.
		$accounts = static fn (): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email LIKE %s", $wpdb->esc_like( $tag ) . '%@example.com' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		do_action( 'wpml_switch_language', 'en' );

		// Everything this run stores is an address starting with the tag; it goes, with its queued emails and counters.
		$s->on_finish(
			static function () use ( $tag, $address ): void {
				global $wpdb;
				if ( ! class_exists( SignupStore::class ) || ! SignupStore::ready() ) {
					return;
				}
				$table = SignupStore::table();
				$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE email LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				foreach ( $ids as $id ) {
					if ( function_exists( 'as_unschedule_all_actions' ) ) {
						as_unschedule_all_actions( Welcome::HOOK, array( (int) $id ), Welcome::GROUP );
					}
				}
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE email LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				SignupService::forget( $address );
			}
		);
		SignupService::forget( $address );
		$check( SignupStore::ready() && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', SignupStore::table() ) ) === SignupStore::table(), 'the sign-up store is installed as a table of its own' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// ---- The section as drawn ----------------------------------------------------------------------------------
		$html = Banners::renderSignup( array( 'heading' => "{$tag} Stay in touch", 'text' => 'One short email when something worth knowing lands.', 'ctaLabel' => 'Sign up' ) );
		$check( 1 === preg_match( '/<input[^>]*type="email"[^>]*>/', $html, $field ) && str_contains( $field[0], 'autocomplete="email"' ) && 1 === preg_match( '/id="([^"]+)"/', $field[0], $fid ) && str_contains( $html, 'for="' . $fid[1] . '"' ) && str_contains( $html, '>Email address<' ), 'HOME-09 the email field is labelled "Email address", type email, autocomplete email' );
		$check( 1 === preg_match( '/<input[^>]*type="checkbox"[^>]*>/', $html, $box ) && ! str_contains( $box[0], 'checked' ) && str_contains( $html, 'Email me news and offers. Optional, and separate from your account.' ), 'HOME-09 the optional box reads as drawn and starts unticked' );
		$check( 1 === preg_match( '/<button[^>]*type="submit"[^>]*>.*?Sign up.*?<\/button>/s', $html ), 'HOME-09 the Sign up button submits the form' );
		$check( ! str_contains( $html, 'name="' . SignupService::FIELD_TOKEN . '"' ) && str_contains( $html, 'name="' . SignupService::FIELD_TRAP . '"' ) && str_contains( $html, 'method="post"' ), 'the form as every visitor is sent it carries no token (it is single-use and the page may come from the page cache, NFR-02), a trap field, and posts without JavaScript' );
		// Without JavaScript, the first press therefore comes back as "again", the address kept; only that answer, built
		// for this visitor and never cached, carries a timed single-use token for the second press.
		$server = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array( SignupService::FIELD_FORM => '1', SignupService::FIELD_EMAIL => "{$tag}-nojs@example.com", SignupService::FIELD_TRAP => '' ); // phpcs:ignore WordPress.Security.NonceVerification
		SignupRoutes::posted();
		$_POST = array(); // phpcs:ignore WordPress.Security.NonceVerification
		if ( null === $server ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $server;
		}
		$answered = Banners::renderSignup( array( 'heading' => "{$tag} Stay in touch", 'ctaLabel' => 'Sign up' ) );
		$check( 'again' === ( SignupRoutes::result()['state'] ?? '' ) && null === SignupStore::find( "{$tag}-nojs@example.com" ) && str_contains( $answered, 'value="' . esc_attr( "{$tag}-nojs@example.com" ) . '"' ) && 1 === preg_match( '/name="' . SignupService::FIELD_TOKEN . '" value="([^"]+)"/', $answered, $tok ) && null !== SignupCheck::inspect( SignupService::ROUTE, html_entity_decode( $tok[1] ), '', time() + 10 ), 'a press without a token comes back as "again" with the address kept and nothing stored, and that answer carries a timed single-use token for this form (Security\SignupCheck)' );
		$check( 1 === preg_match( '/<h2[^>]*class="mizzey-signup__done-title"/', $html ), 'the success title is a heading, as the phone board draws it' );
		$check( ! str_contains( $html, (string) wc_get_page_permalink( 'myaccount' ) ), 'the prompt no longer sends the visitor to the account page' );
		$check( '' === Banners::renderSignup( array( 'heading' => '' ) ), 'without its heading the section renders nothing' );

		// ---- Refused: an address that is not one -----------------------------------------------------------------
		// Each sign-up spends its own token, issued as a page would be a little earlier.
		$token  = static fn (): string => SignupCheck::issue( SignupService::ROUTE, time() - 10 );
		$submit = static fn ( array $in, ?string $from = null ) => SignupService::submit( $in + array( 'token' => $token(), 'trap' => '', 'consent' => false, 'lang' => 'en' ), $from ?? $address );
		$bad    = $submit( array( 'email' => "{$tag}@example" ) );
		$check( 'invalid' === $bad['state'] && null === SignupStore::find( "{$tag}@example" ), 'an address without a domain is refused and nothing is stored' );
		$check( 'Enter an email address like name@example.com.' === SignupService::message( 'invalid' ), 'the refusal says how to fix it, in the board\'s words' );

		// ---- A sign-up, in Arabic, without the box --------------------------------------------------------------
		$one = $submit( array( 'email' => '  ' . strtoupper( $tag ) . '-One@Example.com ', 'lang' => 'ar' ) );
		$rec = SignupStore::find( $mail( 'one' ) );
		$check( 'ok' === $one['state'] && $mail( 'one' ) === $one['email'], 'a sign-up is accepted and answered with the address as stored: ' . $one['email'] );
		$check( is_array( $rec ) && $mail( 'one' ) === $rec['email'] && 'ar' === $rec['language'] && 'home' === $rec['source'] && 'subscribed' === $rec['status'], 'the record keeps the address in lower case, the language, the source "home" and status subscribed' );
		$check( is_array( $rec ) && 0 === (int) $rec['consent'] && '' === $rec['consent_version'] && null === $rec['consent_at'], 'without the box ticked no marketing consent is recorded' );
		$check( is_array( $rec ) && 1 === preg_match( '/^[a-f0-9]{40}$/', (string) $rec['token'] ) && strtotime( (string) $rec['created_at'] . ' UTC' ) > time() - 300, 'the record has a random leave token and its created date' );
		$check( false === get_user_by( 'email', $mail( 'one' ) ) && 0 === $accounts(), 'no WordPress account is made' );
		$check( is_array( $rec ) && 1 === t119_queued( (int) $rec['id'] ), 'one welcome email is queued' );

		// ---- The same address again, now with the box: one record, the same answer, no second email -------------
		$two  = $submit( array( 'email' => $mail( 'one' ), 'consent' => true, 'lang' => 'ar' ) );
		$rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . SignupStore::table() . ' WHERE email = %s', $mail( 'one' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rec2 = SignupStore::find( $mail( 'one' ) );
		$check( $one === $two && 1 === $rows, 'signing up twice gives the same answer and keeps one record' );
		$check( is_array( $rec2 ) && 1 === (int) $rec2['consent'] && SignupService::CONSENT_VERSION === $rec2['consent_version'] && null !== $rec2['consent_at'], 'the box ticked marks marketing consent with its wording version (' . ( $rec2['consent_version'] ?? '' ) . ')' );
		$check( is_array( $rec2 ) && 1 === t119_queued( (int) $rec2['id'] ), 'the second sign-up queues no second welcome' );

		// ---- The welcome email, in Arabic, with its leave link ---------------------------------------------------
		$sent = array();
		$trap = static function ( $pre, $atts ) use ( &$sent ) {
			$sent[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		do_action( Welcome::HOOK, (int) $rec2['id'] );
		do_action( Welcome::HOOK, (int) $rec2['id'] );
		remove_filter( 'pre_wp_mail', $trap, 10 );
		$m       = $sent[0] ?? array( 'to' => '', 'subject' => '', 'message' => '', 'headers' => '' );
		$leave   = SignupService::leaveUrl( (array) $rec2 );
		$headers = is_array( $m['headers'] ) ? implode( "\n", $m['headers'] ) : (string) $m['headers'];
		$check( 1 === count( $sent ) && ( is_array( $m['to'] ) ? in_array( $mail( 'one' ), $m['to'], true ) : str_contains( (string) $m['to'], $mail( 'one' ) ) ), 'the welcome is sent once, to the address (' . count( $sent ) . ' sent)' );
		$check( 1 === preg_match( '/\p{Arabic}/u', (string) $m['subject'] ) && 1 === preg_match( '/\p{Arabic}/u', wp_strip_all_tags( (string) $m['message'] ) ), 'the welcome is in the sign-up\'s language, Arabic: ' . $m['subject'] );
		$check( str_contains( (string) $m['message'], esc_url( $leave ) ) && str_contains( $leave, (string) $rec2['token'] ) && str_contains( $leave, '/ar/' ), 'the welcome carries the leave link, in Arabic: ' . $leave );
		$check( str_contains( $headers, 'List-Unsubscribe: <' . $leave . '>' ) && str_contains( $headers, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' ), 'the welcome names its leave link for mail programs (List-Unsubscribe, one click)' );
		$check( null !== SignupStore::find( $mail( 'one' ) )['welcomed_at'], 'the record notes the welcome was sent' );

		// ---- Refused without storing: a filled trap, no nonce, and too many from one address -------------------
		$trapped = $submit( array( 'email' => $mail( 'trap' ), 'trap' => 'http://spam.example' ) );
		$check( 'ok' === $trapped['state'] && null === SignupStore::find( $mail( 'trap' ) ), 'a filled trap field is answered as a sign-up and nothing is stored' );
		$stale = $submit( array( 'email' => $mail( 'stale' ), 'token' => 'nope' ) );
		$used  = $token();
		$submit( array( 'email' => $mail( 'used1' ), 'token' => $used ) );
		$twice = $submit( array( 'email' => $mail( 'used2' ), 'token' => $used ) );
		$other = $submit( array( 'email' => $mail( 'other' ), 'token' => SignupCheck::issue( 'account', time() - 10 ) ) );
		$quick = $submit( array( 'email' => $mail( 'quick' ), 'token' => SignupCheck::issue( SignupService::ROUTE ) ) );
		$check( 'expired' === $stale['state'] && null === SignupStore::find( $mail( 'stale' ) ), 'a form without a valid token is refused and nothing is stored' );
		$check( 'expired' === $twice['state'] && null === SignupStore::find( $mail( 'used2' ) ) && null !== SignupStore::find( $mail( 'used1' ) ), 'a token is spent by its sign-up: the same token again is refused' );
		$check( 'expired' === $other['state'] && 'expired' === $quick['state'] && null === SignupStore::find( $mail( 'other' ) ) && null === SignupStore::find( $mail( 'quick' ) ), 'a token for another form, or one used within seconds of the page, is refused' );

		// ---- A write that fails is never answered as a sign-up ----------------------------------------------------
		$missing = static fn ( string $sql ): string => str_replace( SignupStore::table(), SignupStore::table() . '_missing', $sql );
		$logged  = array();
		$listen  = static function ( $message ) use ( &$logged ) {
			$logged[] = (string) $message;
			return $message;
		};
		// The PHP error log is read too: wpdb would write a failing query there, address and all, unless the store keeps
		// it quiet. Nothing is suppressed here; the store must do it.
		$php_log  = wp_tempnam( 't119-php-log' );
		$was_log  = ini_set( 'error_log', $php_log );
		$was_show = $wpdb->show_errors( false );
		add_filter( 'query', $missing );
		add_filter( 'woocommerce_logger_log_message', $listen, 10, 1 );
		$failed = $submit( array( 'email' => $mail( 'nowhere' ) ) );
		remove_filter( 'query', $missing );
		remove_filter( 'woocommerce_logger_log_message', $listen, 10 );
		$wpdb->show_errors( $was_show );
		ini_set( 'error_log', false === $was_log ? '' : $was_log );
		$php_logged = (string) file_get_contents( $php_log ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $php_log );
		$check( ! str_contains( $php_logged, $tag ) && ! str_contains( $php_logged, '@example.com' ), 'the failing query never reaches the PHP error log with the address (' . strlen( $php_logged ) . ' bytes logged' . ( '' === $php_logged ? '' : ': ' . substr( $php_logged, 0, 120 ) ) . ')' );
		$check( 'failed' === $failed['state'] && null === SignupStore::find( $mail( 'nowhere' ) ) && '' !== SignupService::message( 'failed' ) && SignupService::message( 'failed' ) !== SignupService::message( 'expired' ), 'with the table missing the answer is "failed", with its own words: ' . SignupService::message( 'failed' ) );
		$check( array() !== $logged && ! preg_grep( '/@|' . preg_quote( $tag, '/' ) . '/', $logged ), 'the failure is logged without the address: ' . substr( implode( ' | ', $logged ), 0, 160 ) );

		// ---- A welcome that could not be sent is tried again ------------------------------------------------------
		$submit( array( 'email' => $mail( 'retry' ) ) );
		$retry = SignupStore::find( $mail( 'retry' ) );
		as_unschedule_all_actions( Welcome::HOOK, array( (int) $retry['id'] ), Welcome::GROUP ); // As if the action had run.
		add_filter( 'pre_wp_mail', '__return_false' );
		$thrown = false;
		try {
			Welcome::send( (int) $retry['id'] );
		} catch ( \Throwable $e ) {
			$thrown = true;
		}
		remove_filter( 'pre_wp_mail', '__return_false' );
		$check( $thrown && null === SignupStore::find( $mail( 'retry' ) )['welcomed_at'], 'a welcome the mail server refused fails its queued action and is not marked sent' );
		$submit( array( 'email' => $mail( 'retry' ) ) );
		$check( 1 === t119_queued( (int) $retry['id'] ), 'signing up again while the welcome is still owed queues it again' );
		$ceiling = static fn (): int => 0;
		add_filter( 'mizzey_signup_welcome_ceiling', $ceiling );
		as_unschedule_all_actions( Welcome::HOOK, array( (int) $retry['id'] ), Welcome::GROUP );
		$held = array();
		$hold = static function ( $pre, $atts ) use ( &$held ) {
			$held[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $hold, 10, 2 );
		Welcome::send( (int) $retry['id'] );
		remove_filter( 'pre_wp_mail', $hold, 10 );
		remove_filter( 'mizzey_signup_welcome_ceiling', $ceiling );
		$later = as_next_scheduled_action( Welcome::HOOK, array( (int) $retry['id'] ), Welcome::GROUP );
		$check( array() === $held && null === SignupStore::find( $mail( 'retry' ) )['welcomed_at'] && is_int( $later ) && $later > time() + 60, 'past the hourly ceiling of welcome emails the welcome waits and is scheduled for later' );
		$limits = SignupService::limits();
		for ( $i = 0; $i < $limits['email'] + 1; $i++ ) {
			$again = $submit( array( 'email' => $mail( 'one' ) ) );
		}
		$check( $one === $again, 'past the limit for one address the answer is still the same, so nothing says whether it is on the list' );
		SignupService::forget( $address );
		$results = array();
		for ( $i = 0; $i < $limits['address'] + 1; $i++ ) {
			$results[] = $submit( array( 'email' => $mail( "many{$i}" ) ) )['state'];
		}
		$check( 'limited' === end( $results ) && null === SignupStore::find( $mail( 'many' . $limits['address'] ) ), "past {$limits['address']} sign-ups from one network address the next is refused and not stored" );
		$claims = static fn (): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'mizzey_signup_claim_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$before = $claims();
		for ( $i = 0; $i < 3; $i++ ) {
			$submit( array( 'email' => $mail( "refused{$i}" ) ) );
		}
		$check( $before === $claims(), 'a client past the limit spends no token, so its requests add no rows (' . $before . ' claims before, ' . $claims() . ' after)' );
		$check( '' !== SignupService::message( 'limited' ) && '' !== SignupService::message( 'expired' ), 'each refusal has its own words' );
		SignupService::forget( $address, $mail( 'one' ) );

		// IPv6: one network (/64) is one counter, whatever host part each request uses.
		$v6 = array();
		for ( $i = 0; $i < $limits['address']; $i++ ) {
			$v6[] = $submit( array( 'email' => $mail( "six{$i}" ) ), '2001:db8:1:2::' . dechex( $i + 1 ) )['state'];
		}
		$same  = $submit( array( 'email' => $mail( 'sixsame' ) ), '2001:db8:1:2:ffff:ffff:ffff:9' )['state'];
		$apart = $submit( array( 'email' => $mail( 'sixother' ) ), '2001:db8:1:3::1' )['state'];
		SignupService::forget( '2001:db8:1:2::1' );
		SignupService::forget( '2001:db8:1:3::1' );
		$check( array( 'ok' ) === array_unique( $v6 ) && 'limited' === $same && 'ok' === $apart, "IPv6 addresses are counted per /64 network: the {$limits['address']} sign-ups fill it ({$same}), the next network is apart ({$apart})" );

		// ---- Leaving ----------------------------------------------------------------------------------------------
		$left = SignupService::leave( (string) $rec2['token'] );
		$rec3 = SignupStore::find( $mail( 'one' ) );
		$check( is_array( $left ) && is_array( $rec3 ) && 'left' === $rec3['status'] && null !== $rec3['left_at'], 'the leave token marks the record "left" and keeps it' );
		$check( null === SignupService::leave( str_repeat( 'a', 40 ) ), 'an unknown token leaves nothing' );
		$back = $submit( array( 'email' => $mail( 'one' ), 'lang' => 'en' ) );
		$rec4 = SignupStore::find( $mail( 'one' ) );
		$check( $one['state'] === $back['state'] && is_array( $rec4 ) && 'subscribed' === $rec4['status'] && 0 === (int) $rec4['consent'] && 'en' === $rec4['language'] && 1 === t119_queued( (int) $rec4['id'] ), 'signing up again after leaving subscribes again, in the new language, with the box as now answered, and queues a welcome' );

		// ---- The list screen ----------------------------------------------------------------------------------------
		$roles = array();
		foreach ( array( 'administrator', 'shop_manager', 'mizzey_marketing', 'editor' ) as $role ) {
			$id = wp_insert_user( array( 'user_login' => "{$tag}-{$role}", 'user_email' => "{$tag}-{$role}@staff.example", 'user_pass' => wp_generate_password( 24 ), 'role' => $role ) );
			if ( ! is_wp_error( $id ) ) {
				$s->track_user( (int) $id );
				$roles[ $role ] = user_can( (int) $id, SignupScreen::CAP );
			}
		}
		$check( array( 'administrator' => true, 'shop_manager' => true, 'mizzey_marketing' => false, 'editor' => false ) === $roles, 'the list is for the roles that manage customers, not the agency or editors: ' . wp_json_encode( $roles ) );
		wp_set_current_user( (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 ) );
		$_GET = array( 'page' => SignupScreen::SLUG, 's' => $tag . '-one' );
		ob_start();
		SignupScreen::render();
		$screen = (string) ob_get_clean();
		$_GET   = array();
		$check( str_contains( $screen, $mail( 'one' ) ) && ! str_contains( $screen, $mail( 'many0' ) ) && 1 === preg_match( '/\b1 sign-up\b/', $screen ), 'the list finds an address by search and counts what it shows' );
		$check( ! preg_match( '/export|download|\.csv|send/i', wp_strip_all_tags( $screen ) ) && ! preg_match( '/<input[^>]*type="checkbox"|<select[^>]*name="action/', $screen ), 'the list offers no export, no sending and no bulk action' );

		// ---- In the browser -------------------------------------------------------------------------------------
		$make_page = static function ( string $lang, string $title, array $attrs ) {
			do_action( 'wpml_switch_language', $lang );
			$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_content' => wp_slash( serialize_blocks( array( array( 'blockName' => 'mizzey/signup', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ) ) ) ) );
			do_action( 'wpml_switch_language', 'en' );
			return $id;
		};
		$en = $make_page( 'en', "{$tag} sign-up", array( 'heading' => 'Stay in touch', 'text' => 'One short email when something worth knowing lands.', 'ctaLabel' => 'Sign up' ) );
		$ar = $make_page( 'ar', "{$tag} الاشتراك", array( 'heading' => 'ابقَ على تواصل', 'text' => 'رسالة قصيرة واحدة عندما يصل ما يستحق أن تعرفه.', 'ctaLabel' => 'اشترك' ) );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$s->on_finish( static function () use ( $en, $ar ): void { wp_delete_post( $ar, true ); wp_delete_post( $en, true ); } );

		wp_set_current_user( 0 );

		// ---- Over HTTP, as a browser or a mail program asks --------------------------------------------------------
		// Every page fetched goes through the storefront guard; the REST answer is JSON and is read as such.
		$http = static function ( string $url, array $args = array() ) {
			$response = wp_remote_request( $url, $args + array( 'timeout' => 60, 'redirection' => 0, 'sslverify' => false ) );
			if ( ! is_wp_error( $response ) && str_contains( (string) wp_remote_retrieve_header( $response, 'content-type' ), 'html' ) ) {
				storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
			}
			return $response;
		};
		$was_gtm = get_option( TagManager::CONTAINER, null );
		$s->on_finish( static function () use ( $was_gtm ): void { null === $was_gtm ? delete_option( TagManager::CONTAINER ) : update_option( TagManager::CONTAINER, $was_gtm ); } );
		update_option( TagManager::CONTAINER, 'GTM-T119TEST' );
		$submit( array( 'email' => $mail( 'quiet' ), 'lang' => 'ar' ) );
		$quiet      = SignupStore::find( $mail( 'quiet' ) );
		$control    = (string) wp_remote_retrieve_body( $http( (string) get_permalink( $en ) ) );
		$ask        = $http( SignupService::leaveUrl( (array) $quiet ) );
		$confirm    = $http( SignupService::leaveUrl( (array) $quiet ), array( 'method' => 'POST', 'body' => array( 'mizzey_leave_confirm' => '1' ) ) );
		$no_tracker = static fn ( string $body ): bool => ! str_contains( $body, 'GTM-T119TEST' ) && ! str_contains( $body, 'mizzey-consent-default' ) && ! str_contains( $body, 'mizzeyEvents' ) && ! str_contains( $body, 'dataLayer' );
		$check( str_contains( $control, 'GTM-T119TEST' ), 'with a Tag Manager container set, an ordinary page loads it (the control for the next check)' );
		$check( 200 === wp_remote_retrieve_response_code( $ask ) && $no_tracker( (string) wp_remote_retrieve_body( $ask ) ) && $no_tracker( (string) wp_remote_retrieve_body( $confirm ) ) && 'no-referrer' === wp_remote_retrieve_header( $ask, 'referrer-policy' ), 'the leave page, asked and confirmed, loads no Tag Manager container and no data layer, and sends no referrer, so its token reaches no analytics' );
		$check( 'left' === ( SignupStore::find( $mail( 'quiet' ) )['status'] ?? '' ) && str_contains( (string) wp_remote_retrieve_body( $confirm ), 'data-mizzey-leave="left"' ), 'the leave page\'s button leaves the list' );

		$submit( array( 'email' => $mail( 'oneclick' ) ) );
		$oneclick = SignupStore::find( $mail( 'oneclick' ) );
		$click    = $http( SignupService::leaveUrl( (array) $oneclick ), array( 'method' => 'POST', 'body' => 'List-Unsubscribe=One-Click', 'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ) ) );
		$check( 200 === wp_remote_retrieve_response_code( $click ) && 'left' === ( SignupStore::find( $mail( 'oneclick' ) )['status'] ?? '' ), 'a mail program\'s one-click request (List-Unsubscribe-Post) leaves the list at once' );
		$unknown = $http( add_query_arg( SignupRoutes::LEAVE, str_repeat( 'b', 40 ), home_url( '/' ) ) );
		$check( 404 === wp_remote_retrieve_response_code( $unknown ) && str_contains( (string) wp_remote_retrieve_body( $unknown ), 'data-mizzey-leave="unknown"' ), 'a leave link that matches no one answers 404 with the page that says so' );
		$unknown_post = $http( add_query_arg( SignupRoutes::LEAVE, str_repeat( 'b', 40 ), home_url( '/' ) ), array( 'method' => 'POST', 'body' => 'List-Unsubscribe=One-Click' ) );
		$check( 404 === wp_remote_retrieve_response_code( $unknown_post ), 'a one-click request with an unknown token changes nothing and answers 404' );

		$rest = static fn ( array $body, array $headers = array() ) => $http( rest_url( SignupRoutes::NS . '/signup' ), array( 'method' => 'POST', 'headers' => array( 'Content-Type' => 'application/json' ) + $headers, 'body' => wp_json_encode( $body + array( 'token' => SignupCheck::issue( SignupService::ROUTE, time() - 10 ), 'trap' => '', 'lang' => 'en' ) ) ) );
		$fresh  = wp_remote_get( rest_url( SignupRoutes::NS . '/signup/token' ), array( 'timeout' => 60 ) ); // storefront-guard: exempt, a JSON endpoint
		$fresh2 = wp_remote_get( rest_url( SignupRoutes::NS . '/signup/token' ), array( 'timeout' => 60 ) ); // storefront-guard: exempt, a JSON endpoint
		$given  = array( json_decode( (string) wp_remote_retrieve_body( $fresh ), true )['token'] ?? '', json_decode( (string) wp_remote_retrieve_body( $fresh2 ), true )['token'] ?? '' );
		$check( 200 === wp_remote_retrieve_response_code( $fresh ) && str_contains( (string) wp_remote_retrieve_header( $fresh, 'cache-control' ), 'no-store' ) && null !== SignupCheck::inspect( SignupService::ROUTE, $given[0], null, time() + 10 ) && $given[0] !== $given[1], 'the script\'s token route answers a fresh, valid token each time, never cached (Cache-Control: no-store)' );
		SignupService::forget( $address );
		for ( $i = 0; $i < $limits['address']; $i++ ) {
			$submit( array( 'email' => $mail( "fill{$i}" ) ) );
		}
		$limited = $rest( array( 'email' => $mail( 'overflow' ) ) );
		$answer  = json_decode( (string) wp_remote_retrieve_body( $limited ), true );
		SignupService::forget( $address );
		$check( 429 === wp_remote_retrieve_response_code( $limited ) && 'limited' === ( $answer['state'] ?? '' ) && null === SignupStore::find( $mail( 'overflow' ) ), 'past the limit the storefront\'s request is answered 429 "limited" and nothing is stored' );

		$shopper = wp_insert_user( array( 'user_login' => "{$tag}-shopper", 'user_email' => "{$tag}-shopper@staff.example", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
		$s->track_user( (int) $shopper );
		$expire  = time() + HOUR_IN_SECONDS;
		$session = \WP_Session_Tokens::get_instance( (int) $shopper )->create( $expire );
		$logged  = wp_generate_auth_cookie( (int) $shopper, $expire, 'logged_in', $session );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $logged;
		wp_set_current_user( (int) $shopper );
		$rest_nonce = wp_create_nonce( 'wp_rest' );
		wp_set_current_user( 0 );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		$cookie = sprintf( '%s=%s; %s=%s', LOGGED_IN_COOKIE, rawurlencode( $logged ), AUTH_COOKIE, rawurlencode( wp_generate_auth_cookie( (int) $shopper, $expire, 'auth', $session ) ) );
		$signed = $rest( array( 'email' => $mail( 'signedin' ), 'consent' => true ), array( 'Cookie' => $cookie, 'X-WP-Nonce' => $rest_nonce ) );
		$srec   = SignupStore::find( $mail( 'signedin' ) );
		$check( 200 === wp_remote_retrieve_response_code( $signed ) && is_array( $srec ) && 1 === (int) $srec['consent'] && '' === (string) get_user_meta( (int) $shopper, '_mizzey_marketing_consent', true ) && $mail( 'signedin' ) !== get_userdata( (int) $shopper )->user_email, 'a signed-in customer signs up any address: one sign-up record, and the account is left as it was' );
		SignupService::forget( $address );

		$leaver = $submit( array( 'email' => $mail( 'leaver' ), 'lang' => 'ar' ) );
		$lrec   = SignupStore::find( $mail( 'leaver' ) );
		SignupService::forget( $address );
		$plan   = array(
			'tag'   => $tag,
			'en'    => (string) get_permalink( $en ),
			'ar'    => (string) apply_filters( 'wpml_permalink', get_permalink( $ar ), 'ar' ),
			'leave' => 'ok' === $leaver['state'] && is_array( $lrec ) ? SignupService::leaveUrl( $lrec ) : '',
			'leaveEmail' => $mail( 'leaver' ),
			'shots' => get_temp_dir(),
		);
		$result = t119_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 120, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $result['shots'] ?? array() ) as $shot ) {
			$s->note( '      shot: ' . $shot );
		}

		// ---- What the browser stored ----------------------------------------------------------------------------
		foreach ( (array) ( $result['signedUp'] ?? array() ) as $email => $lang ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . SignupStore::table() . ' WHERE email = %s', $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			$check( 1 === count( $rows ) && $lang === $rows[0]['language'] && 'subscribed' === $rows[0]['status'] && t119_queued( (int) $rows[0]['id'] ) <= 1 && false === get_user_by( 'email', $email ), "the browser's sign-up of {$email} is one record in {$lang}, with at most one welcome queued and no account" );
		}
		$check( count( (array) ( $result['signedUp'] ?? array() ) ) >= 9, 'the browser signed up in every engine, width and language, and without JavaScript (' . count( (array) ( $result['signedUp'] ?? array() ) ) . ')' );
		$lrec = SignupStore::find( $mail( 'leaver' ) );
		$check( is_array( $lrec ) && 'left' === $lrec['status'], 'the leave page in the browser marked the record "left"' );
		$check( 0 === $accounts(), 'no account was made by any sign-up' );

		return $ok;
	}
);

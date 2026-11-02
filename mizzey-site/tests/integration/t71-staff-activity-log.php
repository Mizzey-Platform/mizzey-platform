<?php
/**
 * t71: the staff log of sign-ins and security events (ADM-130, ADM-132 to ADM-134). See specs/031-staff-activity-log.
 *
 * AC-1: the owner creates staff accounts with the launch roles.
 * AC-2: staff sign-ins, sign-outs, failed sign-ins (password or second factor), lockouts and password resets are
 *       recorded in CoreX Activity, with the account and the address; a customer's sign-in is not.
 * AC-3: the log is searchable in the administration by account, event, outcome and dates, and order notes by text.
 * AC-4: no entry can be changed or deleted from the interface; only the owner and administrators see the log.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\StaffActivityLog;
use MizzeySite\Security\StaffActivityScreen;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';
require_once __DIR__ . '/_sign_in.php';

run(
	new Scenario( 't71-staff-activity-log', 'ADM-130, ADM-132 to ADM-134: the staff log' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$check( class_exists( '\Corex\Boot' ) && \Corex\Boot::booted(), 'fixture: CoreX is booted' );
		$activity = \Corex\Boot::app()->container()->make( \Corex\Activity\ActivityService::class );
		$table    = $wpdb->prefix . 'corex_activity_events';
		$started  = gmdate( 'Y-m-d H:i:s', time() - 2 );
		$tag      = 't71' . wp_rand( 1000, 9999 );
		$s->on_finish(
			static function () use ( $wpdb, $table, $tag ): void {
				// The test's own entries only; the interface offers no way to delete one (AC-4).
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE kind LIKE %s AND ( actor_label LIKE %s OR target_label LIKE %s )", 'mizzey.%', '%' . $wpdb->esc_like( $tag ) . '%', '%' . $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				sign_in_forget( array( "{$tag}ghost", "{$tag}mgr" ) );
				wp_set_current_user( 0 );
				$_GET = array();
			}
		);
		$mine = function ( array $filters ) use ( $activity, $tag ): array {
			$events = $activity->query( array_merge( array( 'area' => 'security' ), $filters ), 1, 100 );
			return array_values( array_filter( $events, static fn ( $e ) => str_contains( $e->actorLabel . ' ' . $e->targetLabel, $tag ) ) );
		};

		// ---- AC-1: staff accounts with the launch roles ------------------------------------------------------
		$pass    = 'Pw-' . wp_generate_password( 20, false );
		$manager = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr", 'user_pass' => $pass, 'user_email' => "{$tag}mgr@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $manager );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $manager ) );
		$buyer   = (int) wp_insert_user( array( 'user_login' => "{$tag}buyer", 'user_pass' => $pass, 'user_email' => "{$tag}buyer@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $buyer );
		$check( $manager > 1 && user_can( $manager, 'edit_shop_orders' ) && null !== get_role( 'administrator' ) && null !== get_role( 'shop_manager' ), 'AC-1 a staff account is created with a launch role (shop manager), beside the administrator role' );

		// ---- AC-2: what is recorded --------------------------------------------------------------------------
		$mgr_user = get_user_by( 'id', $manager );
		two_factor_completed_sign_in( $mgr_user );
		do_action( 'wp_login', "{$tag}buyer", get_user_by( 'id', $buyer ) );
		do_action( 'wp_login_failed', "{$tag}mgr", new \WP_Error( 'incorrect_password', 'x' ) );
		do_action( 'wp_login_failed', "{$tag}mgr", new \WP_Error( 'authentication_failed', 'Invalid verification code.' ) );
		do_action( 'wp_login_failed', "{$tag}ghost", new \WP_Error( 'invalid_username', 'x' ) );
		\Corex\Boot::app()->container()->make( \Corex\Events\EventDispatcher::class )->dispatch( new \Corex\Security\LoginLockoutEvent( "{$tag}mgr", '203.0.113.71', new \DateTimeImmutable( '+15 minutes' ) ) );
		do_action( 'retrieve_password', "{$tag}mgr" );
		do_action( 'after_password_reset', $mgr_user, 'unused' );
		do_action( 'wp_logout', $manager );
		$kinds = static fn ( array $events ): array => array_map( static fn ( $e ) => $e->kind, $events );
		$by_mgr = $mine( array( 'actor_id' => $manager ) );
		$k      = $kinds( $by_mgr );
		$check( in_array( StaffActivityLog::SIGNIN, $k, true ) && in_array( StaffActivityLog::SIGNOUT, $k, true ), 'AC-2 a staff sign-in and sign-out are recorded against the account' );
		$check( in_array( StaffActivityLog::SIGNIN_FAILED, $k, true ) && in_array( StaffActivityLog::SECOND_FACTOR, $k, true ), 'AC-2 a wrong password and a wrong second factor for the account are recorded, told apart' );
		$check( in_array( StaffActivityLog::LOCKOUT, $k, true ) && in_array( StaffActivityLog::RESET_REQUESTED, $k, true ) && in_array( StaffActivityLog::RESET_DONE, $k, true ), 'AC-2 CoreX\'s lockout and a password reset requested and completed are recorded' );
		$lock = array_values( array_filter( $by_mgr, static fn ( $e ) => StaffActivityLog::LOCKOUT === $e->kind ) )[0] ?? null;
		$check( null !== $lock && '203.0.113.71' === ( $lock->context['address'] ?? '' ) && 'denied' === $lock->outcome && 'security' === $lock->sensitivity, 'AC-2 the lockout keeps the address CoreX saw, as a refused, security-sensitive entry' );
		$ghost = $mine( array( 'kind' => StaffActivityLog::SIGNIN_FAILED, 'actor_id' => 0 ) );
		$check( 1 === count( $ghost ) && "{$tag}ghost" === $ghost[0]->targetLabel && StaffActivityLog::UNKNOWN === $ghost[0]->actorLabel, 'AC-2 a failed sign-in for an account that does not exist is recorded with what was typed' );
		$check( array() === $mine( array( 'actor_id' => $buyer ) ), 'AC-2 a customer\'s sign-in is not recorded' );
		$check( $by_mgr && $by_mgr[0]->retentionUntil > new \DateTimeImmutable( '+364 days' ), 'AC-2 entries are kept for a year' );

		// A wrong application password over the REST API's Basic authentication: WordPress reports it on its own action.
		$auth_user                = $_SERVER['PHP_AUTH_USER'] ?? null;
		$_SERVER['PHP_AUTH_USER'] = "{$tag}mgr";
		do_action( 'application_password_failed_authentication', new \WP_Error( 'incorrect_password', 'The provided password is an invalid application password.' ) );
		do_action( 'wp_login_failed', "{$tag}mgr", new \WP_Error( 'incorrect_password', 'x' ) );
		if ( null === $auth_user ) {
			unset( $_SERVER['PHP_AUTH_USER'] );
		} else {
			$_SERVER['PHP_AUTH_USER'] = $auth_user;
		}
		$api = array_values( array_filter( $mine( array( 'actor_id' => $manager, 'kind' => StaffActivityLog::SIGNIN_FAILED ) ), static fn ( $e ) => 'application-password' === ( $e->context['method'] ?? '' ) ) );
		$all_failed = count( $mine( array( 'actor_id' => $manager, 'kind' => StaffActivityLog::SIGNIN_FAILED ) ) );
		$check( 1 === count( $api ) && 'incorrect_password' === ( $api[0]->context['reason'] ?? '' ) && 'failure' === $api[0]->outcome && 2 === $all_failed, 'AC-2 a wrong application password for the account is recorded as a failed sign-in, once, even when the same attempt also reaches wp_login_failed (' . count( $api ) . ' of ' . $all_failed . ')' );

		// Over the real request: a failed sign-in on wp-login.php.
		$answer = sign_in_wp_login( "{$tag}mgr", 'wrong-' . $pass );
		$http   = array_values( array_filter( $mine( array( 'actor_id' => $manager, 'kind' => StaffActivityLog::SIGNIN_FAILED ) ), static fn ( $e ) => $e->occurredAt->format( 'Y-m-d H:i:s' ) >= $started ) );
		$check( count( $http ) >= 2 && '' !== (string) ( $http[0]->context['address'] ?? '' ), 'AC-2 over the real request a wrong password on the sign-in page is recorded, with the address (' . count( $http ) . ' since the start, ' . (int) $answer['status'] . ', ' . ( $http[0]->context['address'] ?? 'none' ) . ')' );

		// A staff sign-in is recorded only once it is complete: after WP 2FA's challenge, or when WP 2FA finishes it.
		$check( 99 === has_action( 'wp_login', array( StaffActivityLog::class, 'signedIn' ) ), 'AC-2 the sign-in is recorded after WP 2FA has had its say (priority 99 against its 20)' );
		$second = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr2", 'user_pass' => $pass, 'user_email' => "{$tag}mgr2@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $second );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $second ) );
		do_action( 'wp_2fa_user_authenticated', get_user_by( 'id', $second ) );
		two_factor_completed_sign_in( get_user_by( 'id', $second ) );
		$check( 1 === count( $mine( array( 'actor_id' => $second, 'kind' => StaffActivityLog::SIGNIN ) ) ), 'AC-2 a sign-in WP 2FA completes is recorded once, even when wp_login follows it' );

		// A wrong password sent with a second-factor field is still a wrong password.
		$spoof = two_factor_http( 'POST', site_url( 'wp-login.php', 'login_post' ), array( 'log' => "{$tag}mgr2", 'pwd' => 'wrong-' . $pass, 'authcode' => '123456', 'wp-auth-nonce' => 'x', 'testcookie' => 1 ), array( new \WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) ) ) );
		$kinds2 = $kinds( $mine( array( 'actor_id' => $second ) ) );
		$check( in_array( StaffActivityLog::SIGNIN_FAILED, $kinds2, true ) && ! in_array( StaffActivityLog::SECOND_FACTOR, $kinds2, true ), 'AC-2 a wrong password sent with second-factor fields is filed as a wrong password (' . (int) $spoof['status'] . ')' );

		// ---- AC-3: searchable in the administration ----------------------------------------------------------
		$q = static fn ( array $over ) => StaffActivityScreen::entries( array_merge( array( 'account' => '', 'kind' => '', 'outcome' => '', 'from' => '', 'to' => '', 'page' => 1 ), $over ) );
		$by_account = $q( array( 'account' => "{$tag}mgr@example.invalid" ) );
		$check( is_array( $by_account ) && count( $by_account ) >= 8 && array() === array_filter( $by_account, static fn ( $e ) => $e->actorId !== $manager ), 'AC-3 search by account (by its email) finds only that account\'s entries (' . ( is_array( $by_account ) ? count( $by_account ) : 'none' ) . ')' );
		$fails = $q( array( 'account' => "{$tag}mgr", 'outcome' => 'failure' ) );
		$check( is_array( $fails ) && array() === array_filter( $fails, static fn ( $e ) => 'failure' !== $e->outcome ) && count( $fails ) >= 3, 'AC-3 filter by outcome combines with the account' );
		$second = $q( array( 'account' => "{$tag}mgr", 'kind' => StaffActivityLog::SECOND_FACTOR ) );
		$check( is_array( $second ) && 1 === count( $second ), 'AC-3 filter by event (' . ( is_array( $second ) ? count( $second ) . ': ' . implode( ',', array_map( static fn ( $e ) => $e->kind, $second ) ) : 'none' ) . ')' );
		$today  = wp_date( 'Y-m-d' );
		$check( is_array( $q( array( 'account' => "{$tag}mgr", 'from' => $today, 'to' => $today ) ) ) && array() === $q( array( 'account' => "{$tag}mgr", 'to' => '2020-01-01' ) ), 'AC-3 filter by dates: today finds them, a range in the past finds none' );
		$check( null === $q( array( 'account' => "{$tag}nobody" ) ), 'AC-3 an account that does not exist is said so' );
		$order = wc_create_order();
		$order->save();
		$s->track_order( $order->get_id() );
		$order->add_order_note( "{$tag} parcel left with the neighbour", 0, false );
		$notes = StaffActivityScreen::notes( "{$tag} parcel left" );
		$check( 1 === count( $notes ) && (int) $notes[0]->comment_post_ID === $order->get_id(), 'AC-3 order notes are searched by their words' );
		$_GET = array( 'page' => StaffActivityScreen::SLUG, 'account' => "{$tag}mgr", 'notes' => "{$tag} parcel" );
		$owner = (int) wp_insert_user( array( 'user_login' => "{$tag}owner", 'user_pass' => $pass, 'user_email' => "{$tag}owner@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $owner );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $owner ) );
		wp_set_current_user( $owner );
		ob_start();
		StaffActivityScreen::render();
		$page = (string) ob_get_clean();
		$_GET = array();
		$check( str_contains( $page, 'Failed second factor' ) && str_contains( $page, '203.0.113.71' ) && str_contains( $page, 'parcel left with the neighbour' ), 'AC-3 the screen shows the account\'s entries and the matching order note' );

		$_GET = array( 'from' => '2026-13-01', 'to' => '2026-02-30' );
		$bad  = StaffActivityScreen::request();
		$_GET = array();
		$check( '' === $bad['from'] && '' === $bad['to'], 'AC-3 an impossible date in the filter is ignored, not a crash' );

		// ---- AC-4: not deletable from the interface; administrators only ---------------------------------------
		$check( 1 !== preg_match( '/<(a|button|input|form)[^>]*(delete|remove|trash|clear|method="post")/i', $page ), 'AC-4 the screen offers no control that changes or deletes an entry' );
		$routes  = rest_get_server()->get_routes();
		$methods = array();
		foreach ( $routes as $route => $handlers ) {
			if ( str_starts_with( $route, '/corex/v1/activity' ) ) {
				foreach ( $handlers as $h ) {
					$methods = array_merge( $methods, array_keys( (array) ( $h['methods'] ?? array() ) ) );
				}
			}
		}
		$check( array() !== $methods && array() === array_diff( array_unique( $methods ), array( 'GET' ) ), 'AC-4 CoreX\'s activity routes only read (' . implode( ',', array_unique( $methods ) ) . ')' );
		wp_set_current_user( $manager );
		$check( ! current_user_can( StaffActivityScreen::CAP ), 'AC-4 a shop manager cannot open the log' );
		$denied = null;
		$handler = static function () use ( &$denied ) {
			return static function ( $m, $t = '', $a = array() ) use ( &$denied ) {
				$denied = (int) ( is_array( $a ) ? ( $a['response'] ?? 0 ) : 0 );
				throw new \RuntimeException( 'die' );
			};
		};
		add_filter( 'wp_die_handler', $handler );
		try {
			StaffActivityScreen::render();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		wp_set_current_user( 0 );
		$check( 403 === $denied, 'AC-4 opened directly, the screen refuses a shop manager (403)' );

		return $ok;
	}
);

<?php
/**
 * t79: observability (NFR-10). See specs/039-observability.
 *
 * AC-1: a fatal error is written to the application log and raised to operations, with no card-like number.
 * AC-2: a failed scheduled action (WooCommerce's Action Scheduler) is logged and raised.
 * AC-3: an error an integration logs (payments, stock) is raised; the same failure again within the hour is not
 *       raised twice.
 * AC-4: a warning that is not fatal raises nothing; the admin audit is the staff log (specs/031) and the order notes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Security\Monitoring;

require_once __DIR__ . '/_bootstrap.php';

/** A notification store that is down. */
final class T79FailingNotifications implements \Corex\Notifications\NotificationService {
	public function publish( \Corex\Notifications\Notification $notification ): \Corex\Notifications\Notification {
		throw new \RuntimeException( 'the notification store is down' );
	}
	public function resolve( string $dedupKey, string $reason ): int {
		return 0;
	}
	public function forCurrentActor( \Corex\Notifications\NotificationQuery $query ): array {
		return array();
	}
	public function unreadCountForCurrentActor(): int {
		return 0;
	}
	public function findForCurrentActor( int $notificationId ): ?array {
		return null;
	}
	public function markReadForCurrentActor( int $notificationId ): bool {
		return false;
	}
	public function markUnreadForCurrentActor( int $notificationId ): bool {
		return false;
	}
	public function dismissForCurrentActor( int $notificationId ): bool {
		return false;
	}
	public function snoozeForCurrentActor( int $notificationId, \DateTimeImmutable $until ): bool {
		return false;
	}
	public function markAllReadForCurrentActor(): int {
		return 0;
	}
	public function resolveById( int $notificationId, string $reason ): bool {
		return false;
	}
}

run(
	new Scenario( 't79-observability', 'NFR-10: logs and error monitoring' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag    = 't79' . wp_rand( 1000, 9999 );
		$raised = array();
		Monitoring::$sink = static function ( array $entry ) use ( &$raised ) { $raised[] = $entry; };
		// Only what this run raised is cleared: the scenario also runs on staging, beside real notifications.
		$ours = array();
		$s->on_finish(
			static function () use ( $wpdb, &$ours ): void {
				Monitoring::$sink = null;
				foreach ( $ours as $kind_where ) {
					delete_transient( 'mizzey_monitor_' . md5( $kind_where ) );
					$wpdb->delete( $wpdb->prefix . 'corex_notifications', array( 'dedup_key' => 'mizzey.monitor|' . md5( $kind_where ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				}
				// The two WP-CLI runs below fail inside evaluated code, which no real request runs.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}corex_notifications WHERE source_type = %s AND source_id LIKE %s", 'mizzey_monitor', '%' . $wpdb->esc_like( "eval()'d code" ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		);
		$logged = static function ( string $needle, string $source = Monitoring::SOURCE ): bool {
			$dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : trailingslashit( wp_upload_dir()['basedir'] ) . 'wc-logs/';
			foreach ( (array) glob( trailingslashit( $dir ) . $source . '-*.log' ) as $file ) {
				if ( str_contains( (string) file_get_contents( $file ), $needle ) ) {
					return true;
				}
			}
			global $wpdb;
			$table = $wpdb->prefix . 'woocommerce_log';
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source = %s AND message LIKE %s", $source, '%' . $wpdb->esc_like( $needle ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		};

		$occurrences = static function ( string $needle ): int {
			$dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : trailingslashit( wp_upload_dir()['basedir'] ) . 'wc-logs/';
			$n   = 0;
			foreach ( (array) glob( trailingslashit( $dir ) . Monitoring::SOURCE . '-*.log' ) as $file ) {
				$n += substr_count( (string) file_get_contents( $file ), $needle );
			}
			return $n;
		};

		// ---- AC-1: a fatal error -----------------------------------------------------------------------------
		$card = '4111' . wp_rand( 100000000000, 999999999999 );
		Monitoring::fatal( "{$tag} call to undefined function with card {$card}", ABSPATH . "wp-content/plugins/mizzey-site/src/{$tag}.php", 42 );
		$fatal = array_values( array_filter( $raised, static fn ( $r ) => 'fatal' === $r['kind'] ) );
		$check( 1 === count( $fatal ) && "wp-content/plugins/mizzey-site/src/{$tag}.php:42" === $fatal[0]['where'] && ! str_contains( $fatal[0]['line'], $card ), 'AC-1 a fatal error is raised with its place inside the site, and a card-like number is masked (' . ( $fatal[0]['line'] ?? 'none' ) . ')' );
		$check( $logged( "{$tag} call to undefined function" ) && ! $logged( $card ), 'AC-1 it is in the application log (source ' . Monitoring::SOURCE . ')' );
		// A real fatal error in another process: WP-CLI loads the site and calls a function that does not exist.
		$bin = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		// The children's deliberate fatals belong in the application log under test, not in PHP's own error log, which
		// must stay free of errors: each child sends PHP's copy to a throwaway file, removed when the scenario ends.
		$sink  = wp_normalize_path( get_temp_dir() ) . "{$tag}-children.log";
		$quiet = "ini_set( 'error_log', " . var_export( $sink, true ) . ' ); ';
		$s->on_finish( static function () use ( $sink ) { if ( is_file( $sink ) ) { unlink( $sink ); } } );
		exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' eval ' . escapeshellarg( $quiet . "{$tag}_no_such_function();" ) . ' 2>&1', $out, $code );
		$check( 0 !== $code && $logged( "{$tag}_no_such_function" ), 'AC-1 a fatal error that ends a real request is in the application log (exit ' . $code . ')' );
		// Once output has started WordPress shows no error page: the end of the request is watched for that case.
		exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' eval ' . escapeshellarg( $quiet . "echo 'started'; {$tag}_after_output();" ) . ' 2>&1', $out2, $code2 );
		$check( 0 !== $code2 && $logged( "{$tag}_after_output" ), 'AC-1 a fatal error after the page has started is in the application log too (exit ' . $code2 . ')' );
		// WordPress's own handler shows its error page and ends the request: the error is taken from its message.
		( function () { self::$fatalSeen = false; } )->bindTo( null, Monitoring::class )();
		$before = count( $raised );
		apply_filters( 'wp_php_error_message', 'page', array( 'type' => E_ERROR, 'message' => "{$tag} shown by WordPress", 'file' => ABSPATH . "wp-content/plugins/{$tag}/y.php", 'line' => 7 ) );
		$check( count( $raised ) === $before + 1 && str_contains( $raised[ $before ]['line'] ?? '', "{$tag} shown by WordPress" ), 'AC-1 a fatal error WordPress shows its error page for is raised too' );
		$stored = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}corex_notifications WHERE dedup_key = %s", 'mizzey.monitor|' . md5( "fatal|wp-content/plugins/{$tag}/y.php:7" ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 1 === $stored, 'AC-1 it reaches the operations staff as a CoreX notification' );
		// An uncaught exception's message carries the server's paths, a stack trace and whatever the code put in it.
		( function () { self::$fatalSeen = false; } )->bindTo( null, Monitoring::class )();
		$token  = str_repeat( 'a1B2', 10 );
		$before = count( $raised );
		Monitoring::fromWordPress( 'page', array( 'type' => E_ERROR, 'message' => "Uncaught Error: {$tag} scrub for ops@example.com with key {$token} in " . ABSPATH . "wp-content/plugins/{$tag}/z.php:3 and C:\\srv\\other\\lib.php\nStack trace:\n#0 {$tag}_secret_frame()", 'file' => ABSPATH . "wp-content/plugins/{$tag}/z.php", 'line' => 3 ) );
		$scrubbed = (string) ( $raised[ $before ]['line'] ?? '' );
		$check( str_contains( $scrubbed, "{$tag} scrub" ) && ! str_contains( $scrubbed, 'ops@example.com' ) && ! str_contains( $scrubbed, $token ) && ! str_contains( $scrubbed, wp_normalize_path( ABSPATH ) ) && ! str_contains( $scrubbed, 'srv' ) && ! str_contains( $scrubbed, 'secret_frame' ) && str_contains( $scrubbed, "in wp-content/plugins/{$tag}/z.php:3" ) && str_contains( $scrubbed, ' lib.php' ) && ! $logged( "{$tag}_secret_frame" ), 'AC-1 no email address, token, server path or stack trace is raised or logged (' . $scrubbed . ')' );
		// Out of memory, reached in small steps so almost nothing is left: the monitor still reports, from memory it set aside.
		$oom_before = $occurrences( 'Allowed memory size' );
		exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' eval ' . escapeshellarg( $quiet . "if ( false === ini_set( 'memory_limit', (string) ( memory_get_usage( true ) + 33554432 ) ) ) { exit( 3 ); } \$keep = array(); while ( true ) { \$keep[] = str_repeat( 'x', 40 ); }" ) . ' 2>&1', $out3, $code3 );
		$check( 3 !== $code3 && 0 !== $code3 && $occurrences( 'Allowed memory size' ) > $oom_before, 'AC-1 an out-of-memory error is still logged (exit ' . $code3 . ')' );
		// A notification that could not be stored does not start the quiet hour: the next occurrence tries again.
		$container = \Corex\Boot::app()->container();
		$real      = $container->make( \Corex\Notifications\NotificationService::class );
		$container->instance( \Corex\Notifications\NotificationService::class, new T79FailingNotifications() );
		Monitoring::error( 'integration', "mizzey-{$tag}-down", "{$tag} notifications are down", 'error' );
		$container->instance( \Corex\Notifications\NotificationService::class, $real );
		$check( false === get_transient( 'mizzey_monitor_' . md5( "integration|mizzey-{$tag}-down" ) ) && $logged( "{$tag} notifications are down" ), 'AC-1 when the notification cannot be stored the line is still logged and the next occurrence is raised again' );

		// ---- AC-2: a failed scheduled action --------------------------------------------------------------------
		$hook = "{$tag}_failing_task";
		add_action( $hook, static function (): void { throw new \RuntimeException( 'the task broke' ); } );
		$id = as_schedule_single_action( time() - 60, $hook, array(), 'mizzey-t79' );
		$s->on_finish( static function () use ( $hook ) { as_unschedule_all_actions( $hook, array(), 'mizzey-t79' ); } );
		try {
			\ActionScheduler_QueueRunner::instance()->process_action( $id, 't79' );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$sched = array_values( array_filter( $raised, static fn ( $r ) => 'scheduled' === $r['kind'] && $hook === $r['where'] ) );
		$check( 1 === count( $sched ) && str_contains( $sched[0]['line'], 'the task broke' ) && $logged( $hook ), 'AC-2 a failed scheduled action is logged and raised (' . ( $sched[0]['line'] ?? 'none' ) . ')' );

		// ---- AC-3: integration errors, once an hour -----------------------------------------------------------
		$before = count( $raised );
		wc_get_logger()->error( "{$tag} stock source unavailable after 3 attempts", array( 'source' => "mizzey-{$tag}-stock" ) );
		wc_get_logger()->error( "{$tag} stock source unavailable after 3 attempts", array( 'source' => "mizzey-{$tag}-stock" ) );
		wc_get_logger()->error( "{$tag} callback refused", array( 'source' => "mizzey-{$tag}-payments" ) );
		wc_get_logger()->info( "{$tag} routine", array( 'source' => "mizzey-{$tag}-info" ) );
		wc_get_logger()->critical( "{$tag} the monitor's own line", array( 'source' => Monitoring::SOURCE ) );
		wc_get_logger()->error( "{$tag} someone else's plugin", array( 'source' => "{$tag}-other-plugin" ) );
		$new = array_slice( $raised, $before );
		$check( 2 === count( $new ) && "mizzey-{$tag}-stock" === $new[0]['where'] && "mizzey-{$tag}-payments" === $new[1]['where'], 'AC-3 an error from the stock and the payment integrations is raised once each; a repeat within the hour, an info line and another plugin\'s error are not (' . count( $new ) . ')' );

		$spaced = implode( ' ', str_split( $card, 4 ) );
		wc_get_logger()->error( "{$tag} declined card {$spaced}", array( 'source' => "mizzey-{$tag}-payments" ) );
		$check( $logged( "{$tag} declined card [#]", "mizzey-{$tag}-payments" ) && ! $logged( $spaced, "mizzey-{$tag}-payments" ), 'AC-3 a card-like number is masked in the integration log line too' );

		// ---- AC-4: not fatal --------------------------------------------------------------------------------------
		( function () { self::$fatalSeen = false; } )->bindTo( null, Monitoring::class )();
		$before = count( $raised );
		@trigger_error( "{$tag} a notice", E_USER_WARNING ); // phpcs:ignore
		Monitoring::shutdown();
		$check( count( $raised ) === $before, 'AC-4 a warning that does not end the request is not raised as a fatal error' );
		$check( false !== has_action( 'wp_login', array( \MizzeySite\Security\StaffActivityLog::class, 'signedIn' ) ), 'AC-4 sign-ins reach the staff log (specs/031), beside the order notes' );

		foreach ( $raised as $entry ) {
			$ours[] = $entry['kind'] . '|' . $entry['where'];
		}
		foreach ( array( "mizzey-{$tag}-info", Monitoring::SOURCE, "{$tag}-other-plugin" ) as $source ) {
			$ours[] = 'integration|' . $source;
		}

		return $ok;
	}
);

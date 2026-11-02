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
		$s->on_finish(
			static function () use ( $wpdb ): void {
				Monitoring::$sink = null;
				$wpdb->query( "DELETE FROM {$wpdb->prefix}corex_notifications WHERE dedup_key LIKE 'mizzey.monitor|%' AND created_at >= NOW() - INTERVAL 1 HOUR" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%mizzey\\_monitor\\_%' OR option_name LIKE '\\_transient\\_timeout\\_mizzey\\_monitor\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		);
		$logged = static function ( string $needle ): bool {
			$dir = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : trailingslashit( wp_upload_dir()['basedir'] ) . 'wc-logs/';
			foreach ( (array) glob( trailingslashit( $dir ) . Monitoring::SOURCE . '-*.log' ) as $file ) {
				if ( str_contains( (string) file_get_contents( $file ), $needle ) ) {
					return true;
				}
			}
			global $wpdb;
			$table = $wpdb->prefix . 'woocommerce_log';
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE source = %s AND message LIKE %s", Monitoring::SOURCE, '%' . $wpdb->esc_like( $needle ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		};

		// ---- AC-1: a fatal error -----------------------------------------------------------------------------
		Monitoring::fatal( "{$tag} call to undefined function with card 4111111111111111", ABSPATH . 'wp-content/plugins/mizzey-site/src/Example.php', 42 );
		$fatal = array_values( array_filter( $raised, static fn ( $r ) => 'fatal' === $r['kind'] ) );
		$check( 1 === count( $fatal ) && 'wp-content/plugins/mizzey-site/src/Example.php:42' === $fatal[0]['where'] && ! str_contains( $fatal[0]['line'], '4111111111111111' ), 'AC-1 a fatal error is raised with its place inside the site, and a card-like number is masked (' . ( $fatal[0]['line'] ?? 'none' ) . ')' );
		$check( $logged( "{$tag} call to undefined function" ) && ! $logged( '4111111111111111' ), 'AC-1 it is in the application log (source ' . Monitoring::SOURCE . ')' );
		// A real fatal error in another process: WP-CLI loads the site and calls a function that does not exist.
		$bin = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' eval ' . escapeshellarg( "{$tag}_no_such_function();" ) . ' 2>&1', $out, $code );
		$check( 0 !== $code && $logged( "{$tag}_no_such_function" ), 'AC-1 a fatal error that ends a real request is in the application log (exit ' . $code . ')' );
		// WordPress's own handler shows its error page and ends the request: the error is taken from its message.
		( function () { self::$fatalSeen = false; } )->bindTo( null, Monitoring::class )();
		$before = count( $raised );
		apply_filters( 'wp_php_error_message', 'page', array( 'type' => E_ERROR, 'message' => "{$tag} shown by WordPress", 'file' => ABSPATH . 'wp-content/plugins/x/y.php', 'line' => 7 ) );
		$check( count( $raised ) === $before + 1 && str_contains( $raised[ $before ]['line'] ?? '', "{$tag} shown by WordPress" ), 'AC-1 a fatal error WordPress shows its error page for is raised too' );
		$stored = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}corex_notifications WHERE dedup_key = %s", 'mizzey.monitor|' . md5( 'fatal|wp-content/plugins/x/y.php:7' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 1 === $stored, 'AC-1 it reaches the operations staff as a CoreX notification' );

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
		wc_get_logger()->error( "{$tag} stock source unavailable after 3 attempts", array( 'source' => 'mizzey-stock' ) );
		wc_get_logger()->error( "{$tag} stock source unavailable after 3 attempts", array( 'source' => 'mizzey-stock' ) );
		wc_get_logger()->error( "{$tag} callback refused", array( 'source' => 'mizzey-payments' ) );
		wc_get_logger()->info( "{$tag} routine", array( 'source' => 'mizzey-payments' ) );
		wc_get_logger()->error( "{$tag} someone else's plugin", array( 'source' => 'other-plugin' ) );
		$new = array_slice( $raised, $before );
		$check( 2 === count( $new ) && 'mizzey-stock' === $new[0]['where'] && 'mizzey-payments' === $new[1]['where'], 'AC-3 an error from the stock and the payment integrations is raised once each; a repeat within the hour, an info line and another plugin\'s error are not (' . count( $new ) . ')' );

		// ---- AC-4: not fatal --------------------------------------------------------------------------------------
		$before = count( $raised );
		@trigger_error( "{$tag} a notice", E_USER_WARNING ); // phpcs:ignore
		Monitoring::shutdown();
		$check( count( $raised ) === $before, 'AC-4 a warning that does not end the request is not raised as a fatal error' );
		$check( false !== has_action( 'wp_login', array( \MizzeySite\Security\StaffActivityLog::class, 'signedIn' ) ) , 'AC-4 sign-ins reach the staff log (specs/031), beside WooCommerce's order notes' );

		return $ok;
	}
);

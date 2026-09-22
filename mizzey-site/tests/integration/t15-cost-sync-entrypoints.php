<?php
/**
 * t15, AC-3: which hook actually carries the synchronisation in each supported update channel.
 *
 * t11 shows each channel ends with the right cost. This scenario records, inside the request that does the work,
 * which product hooks fire and in which context, so the claim "the sync runs on woocommerce_update_product(
 * _variation) in every channel" is measured per channel rather than inferred from one of them. A temporary
 * recorder is installed in the disposable runtime's mu-plugins for the channels that run in their own request
 * (wp-admin, the importer, REST, the front end) and included directly for WP-CLI.
 *
 * Channels: admin-http (the wp-admin product form and the variations AJAX save), import-http (the wp-admin CSV
 * importer AJAX step), rest-http (WooCommerce REST v3 with an application password), crud-cli (WooCommerce CRUD
 * under WP-CLI) and crud-web (WooCommerce CRUD in a front-end request, as custom code, a webhook or cron does).
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Read the recorded hook fires, newest state, and describe them against the posts under test. */
function t15_trace( array $labels ): array {
	wp_cache_delete( 't15_trace', 'options' );
	wp_cache_flush();
	$rows  = (array) get_option( 't15_trace', array() );
	$seen  = array();
	$order = array();
	foreach ( $rows as $row ) {
		$who = $labels[ (int) ( $row['id'] ?? 0 ) ] ?? ( 'post ' . (int) ( $row['id'] ?? 0 ) );
		$key = sprintf( '%s(%s)@%s', $row['hook'] ?? '?', $who, $row['context'] ?? '?' );
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = 0;
			$order[]      = $key;
		}
		$seen[ $key ]++;
	}
	$described = array();
	foreach ( $order as $key ) {
		$described[] = $seen[ $key ] > 1 ? $key . ' x' . $seen[ $key ] : $key;
	}
	return array( $rows, $described );
}

run(
	new Scenario( 't15-cost-sync-entrypoints', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();

		$mu_dir = WPMU_PLUGIN_DIR;
		if ( ! is_dir( $mu_dir ) ) {
			wp_mkdir_p( $mu_dir );
		}
		$mu_file = $mu_dir . '/t15-trace.php';
		copy( __DIR__ . '/fixtures/t15-trace.php.txt', $mu_file );
		$s->on_finish( function () use ( $mu_file ) {
			@unlink( $mu_file );
			delete_option( 't15_trace' );
		} );
		require_once __DIR__ . '/fixtures/t15-trace.php.txt'; // the same recorder, for the WP-CLI channel.

		$w      = new Workflows( $s );
		$ok_all = true;
		$n      = 0;

		foreach ( array( 'admin-http', 'import-http', 'rest-http', 'crud-cli', 'crud-web' ) as $channel ) {
			foreach ( array( 'simple', 'variation' ) as $type ) {
				$tag    = 'T15' . ( ++$n );
				$labels = array();
				if ( 'simple' === $type ) {
					$en                = $w->simple( $tag, null );
					$ar                = $w->translate( 'duplicate', $en );
					$labels[ $en ]     = 'EN';
					$labels[ $ar ]     = 'AR';
					$expected_hook     = 'woocommerce_update_product';
				} else {
					list( $pid, $vids ) = $w->variable( $tag, array( 'S' => null, 'L' => 70.0 ) );
					$ar_parent          = $w->translate( 'duplicate', $pid );
					$en                 = $vids[0];
					$ar                 = Workflows::ar_of( $en );
					$labels[ $pid ]     = 'EN parent';
					$labels[ $ar_parent ] = 'AR parent';
					$labels[ $en ]      = 'EN';
					$labels[ $ar ]      = 'AR';
					$labels[ $vids[1] ] = 'EN sibling';
					$sibling_ar         = Workflows::ar_of( $vids[1] );
					if ( $sibling_ar ) {
						$labels[ $sibling_ar ] = 'AR sibling';
					}
					$expected_hook = 'woocommerce_update_product_variation';
				}
				if ( ! $ar ) {
					$s->note( "FAIL $channel $type: no Arabic counterpart" );
					$ok_all = false;
					continue;
				}

				update_option( 't15_trace', array(), false );
				$result = $w->set_cost( $channel, $en, 65.0 );
				list( $rows, $described ) = t15_trace( $labels );

				$carried = false;
				$save_post_en = false;
				foreach ( $rows as $row ) {
					if ( (int) ( $row['id'] ?? 0 ) === $en && ( $row['hook'] ?? '' ) === $expected_hook ) {
						$carried = $row['context'] ?? '?';
					}
					if ( (int) ( $row['id'] ?? 0 ) === $en && 0 === strpos( (string) ( $row['hook'] ?? '' ), 'save_post_' ) ) {
						$save_post_en = true;
					}
				}
				$ok = ( 'ok' === $result && 65.0 === Workflows::cost( $en ) && 65.0 === Workflows::cost( $ar ) && false !== $carried );
				$s->note( sprintf(
					'%s %s %s: %s fired for the English post in context "%s"; save_post for it: %s; EN %s AR %s%s',
					$ok ? 'OK  ' : 'FAIL',
					$channel,
					$type,
					$expected_hook,
					false === $carried ? 'never' : $carried,
					$save_post_en ? 'yes (WPML and WCML can run)' : 'no (WPML and WCML never run)',
					null === Workflows::cost( $en ) ? 'none' : (string) Workflows::cost( $en ),
					null === Workflows::cost( $ar ) ? 'none' : (string) Workflows::cost( $ar ),
					'ok' === $result ? '' : " [$result]"
				) );
				$s->note( '     hooks: ' . ( $described ? implode( ', ', $described ) : 'none recorded' ) );
				$ok_all = $ok_all && $ok;
			}
		}

		$s->note( 'crud-cli covers WooCommerce CRUD and WP-CLI; crud-web covers front-end application code, webhooks and cron, which run in the same context.' );
		return $ok_all;
	}
);

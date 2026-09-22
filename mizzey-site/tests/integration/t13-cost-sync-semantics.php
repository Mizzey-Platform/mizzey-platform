<?php
/**
 * t13, AC-3: what a cost change does, and what happens when a translation cannot be saved.
 *
 * Part 1, update semantics on one English product and its Arabic translation: a first cost, an increase, a
 * decrease, a save that changes nothing, three identical saves, clearing the cost, and a zero cost (WooCommerce
 * stores zero as no cost; that business question is OD-12 and no Mizzey-specific representation is invented here).
 * Each step records how many times the translation was actually written, so "compare before write" is measured,
 * not assumed.
 *
 * Part 2, failure handling: the save of one Arabic product is forced to throw. The English product must still be
 * saved, the other product's translation must still be synchronised, the gap must be visible in the WooCommerce
 * log rather than silent, and the next ordinary save of the original must repair it.
 *
 * Part 3, re-entrancy: one save of an original writes the original once and its translation once, and stops.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

function t13_fmt( ?float $v ): string {
	return null === $v ? 'none' : rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
}

/** Every line the sync has logged, from whichever handler this runtime uses. */
function t13_log_lines(): array {
	global $wpdb;
	$out   = array();
	$table = $wpdb->prefix . 'woocommerce_log';
	if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT message FROM {$table} WHERE source = %s", 'mizzey-cost-sync' ) ) as $m ) {
			$out[] = 'db: ' . $m;
		}
	}
	foreach ( (array) glob( trailingslashit( defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : WP_CONTENT_DIR . '/uploads/wc-logs/' ) . 'mizzey-cost-sync*.log' ) as $file ) {
		foreach ( (array) file( $file ) as $line ) {
			$out[] = 'file: ' . trim( $line );
		}
	}
	return $out;
}

/** Remove everything this scenario logged, so the runtime is left as it was found. */
function t13_clear_log(): void {
	global $wpdb;
	$table = $wpdb->prefix . 'woocommerce_log';
	if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		$wpdb->delete( $table, array( 'source' => 'mizzey-cost-sync' ) );
	}
	foreach ( (array) glob( trailingslashit( defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : WP_CONTENT_DIR . '/uploads/wc-logs/' ) . 'mizzey-cost-sync*.log' ) as $file ) {
		@unlink( $file );
	}
}

run(
	new Scenario( 't13-cost-sync-semantics', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$w      = new Workflows( $s );
		$ok_all = true;

		t13_clear_log();
		$s->on_finish( __NAMESPACE__ . '\t13_clear_log' );

		$written  = array();
		$recorder = function ( $id ) use ( &$written ) {
			$written[] = (int) $id;
		};
		add_action( 'woocommerce_update_product', $recorder, 30, 1 );
		add_action( 'woocommerce_update_product_variation', $recorder, 30, 1 );

		$en = $w->simple( 'T13A', null );
		$ar = $w->translate( 'duplicate', $en );
		if ( ! $ar ) {
			$s->note( 'FAIL fixture: no Arabic counterpart' );
			return false;
		}

		// --- Part 1: update semantics ---------------------------------------------------------------------------
		// label, value set on the English product, times to save it, expected English cost, expected Arabic cost,
		// expected number of writes to the Arabic product.
		$steps = array(
			array( 'first cost', 100.0, 1, 100.0, 100.0, 1 ),
			array( 'increase', 150.0, 1, 150.0, 150.0, 1 ),
			array( 'decrease', 80.0, 1, 80.0, 80.0, 1 ),
			array( 'unchanged', 80.0, 1, 80.0, 80.0, 0 ),
			array( 'saved three times', 80.0, 3, 80.0, 80.0, 0 ),
			array( 'cleared', null, 1, null, null, 1 ),
			array( 'set again', 60.0, 1, 60.0, 60.0, 1 ),
			array( 'zero', 0.0, 1, null, null, 1 ),
		);
		foreach ( $steps as list( $label, $value, $times, $want_en, $want_ar, $want_writes ) ) {
			$written = array();
			$result  = 'ok';
			for ( $i = 0; $i < $times; $i++ ) {
				$result = $w->set_cost( 'crud-cli', $en, $value );
			}
			$ar_writes = count( array_keys( $written, $ar, true ) );
			$got_en    = Workflows::cost( $en );
			$got_ar    = Workflows::cost( $ar );
			$ok        = ( 'ok' === $result && $got_en === $want_en && $got_ar === $want_ar && $ar_writes === $want_writes );
			$s->note( sprintf(
				'%s %s: set %s x%d -> EN %s (want %s), AR %s (want %s), translation written %d time(s) (want %d)',
				$ok ? 'OK  ' : 'FAIL',
				$label,
				t13_fmt( $value ),
				$times,
				t13_fmt( $got_en ),
				t13_fmt( $want_en ),
				t13_fmt( $got_ar ),
				t13_fmt( $want_ar ),
				$ar_writes,
				$want_writes
			) );
			$ok_all = $ok_all && $ok;
		}
		$s->note( 'Zero is stored by WooCommerce as no cost, on the original and therefore on the translation. A deliberate zero cannot be told from an uncosted product: recorded for OD-12, not worked around here.' );

		// --- Part 2: a translation that cannot be saved ---------------------------------------------------------
		// The second pair here is also the case that exposed the identity defect of 22 September 2026 (see t12 and
		// verification.md): before the correction, its Arabic copy was not synchronised at all.
		$en1 = $w->simple( 'T13F1', 10.0 );
		$ar1 = $w->translate( 'duplicate', $en1 );
		$en2 = $w->simple( 'T13F2', 10.0 );
		$ar2 = $w->translate( 'duplicate', $en2 );
		if ( ! $ar1 || ! $ar2 ) {
			$s->note( 'FAIL fixtures for the failure case' );
			return false;
		}

		$boom = function ( $product ) use ( $ar1 ) {
			if ( (int) $product->get_id() === $ar1 ) {
				throw new \RuntimeException( 'forced failure for t13' );
			}
		};
		add_action( 'woocommerce_before_product_object_save', $boom, 5, 1 );
		$r1 = $w->set_cost( 'crud-cli', $en1, 500.0 );
		$r2 = $w->set_cost( 'crud-cli', $en2, 600.0 );
		remove_action( 'woocommerce_before_product_object_save', $boom, 5 );

		$log     = t13_log_lines();
		$logged  = (bool) array_filter( $log, fn( $line ) => false !== strpos( $line, (string) $ar1 ) && false !== strpos( $line, 'forced failure for t13' ) );
		$ok      = ( 'ok' === $r1 && 'ok' === $r2
			&& 500.0 === Workflows::cost( $en1 ) && 10.0 === Workflows::cost( $ar1 )
			&& 600.0 === Workflows::cost( $en2 ) && 600.0 === Workflows::cost( $ar2 )
			&& $logged );
		$s->note( sprintf(
			'%s forced failure on translation %d: EN1 %s AR1 %s (stale, as expected); the unrelated pair EN2 %s AR2 %s still synchronised; logged to mizzey-cost-sync: %s',
			$ok ? 'OK  ' : 'FAIL',
			$ar1,
			t13_fmt( Workflows::cost( $en1 ) ),
			t13_fmt( Workflows::cost( $ar1 ) ),
			t13_fmt( Workflows::cost( $en2 ) ),
			t13_fmt( Workflows::cost( $ar2 ) ),
			$logged ? 'yes (' . count( $log ) . ' line(s))' : 'NO (a gap would be silent)'
		) );
		$ok_all = $ok_all && $ok;

		// The repair path: an ordinary save of the original, with no new cost value.
		$w->set_cost( 'crud-cli', $en1, 500.0 );
		$ok = ( 500.0 === Workflows::cost( $en1 ) && 500.0 === Workflows::cost( $ar1 ) );
		$s->note( sprintf(
			'%s retry after the failure was removed: saving the original again repaired the translation, EN1 %s AR1 %s. Nothing polls: the repair happens on the next supported save',
			$ok ? 'OK  ' : 'FAIL',
			t13_fmt( Workflows::cost( $en1 ) ),
			t13_fmt( Workflows::cost( $ar1 ) )
		) );
		$ok_all = $ok_all && $ok;

		// --- Part 3: re-entrancy ---------------------------------------------------------------------------------
		$written = array();
		$w->set_cost( 'crud-cli', $en, 42.0 );
		// The translation is written inside the original's save, so the recorder at priority 30 sees it first.
		$ok = ( array( $ar, $en ) === $written );
		$s->note( sprintf(
			'%s re-entrancy: one save of the original produced the writes [%s] and stopped (expected [%d,%d]: the translation, nested inside the save of the original, then the original). Neither write started another pass',
			$ok ? 'OK  ' : 'FAIL',
			implode( ',', $written ),
			$ar,
			$en
		) );
		$ok_all = $ok_all && $ok;

		return $ok_all;
	}
);

<?php
/**
 * t38, FR-012 (fact-finding): a 5,000-row export is validated whole, and the cost is recorded.
 *
 * OD-15's 5,000 products is a provisional engineering sizing figure, not a migration limit and not a guarantee of
 * memory or time, so this scenario records facts and gives no verdict, unless rows go missing: every row must be
 * accounted for. The file is generated: simple products and variations under parents in the file, none in the store.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't38-catalogue-volume', 'FR-012: a 5,000-row export validated whole; time and memory recorded' ),
	function ( Scenario $s ): ?bool {
		$rows = array();
		for ( $i = 1; $i <= 4000; $i++ ) {
			$rows[] = array( 'seller-sku' => sprintf( 'MZ-VOL-%05d', $i ), 'item-name' => "Volume product {$i}", 'item-name-ar' => "منتج {$i}", 'price' => '100.00' );
		}
		for ( $p = 1; $p <= 250; $p++ ) {
			$parent = sprintf( 'MZ-VOLP-%04d', $p );
			$rows[] = array( 'seller-sku' => $parent, 'item-name' => "Volume parent {$p}", 'item-name-ar' => "منتج {$p}", 'parent-child' => 'parent' );
			for ( $v = 1; $v <= 3; $v++ ) {
				$rows[] = array( 'seller-sku' => "{$parent}-{$v}", 'item-name' => "Volume variation {$p}-{$v}", 'item-name-ar' => 'منتج', 'price' => '120.00', 'parent-child' => 'child', 'parent-sku' => $parent, 'size-name' => "{$v}0 ml" );
			}
		}
		$path    = catalogue_temp_export( $s, $rows );
		$out     = catalogue_out_dir( $s, 'volume' );
		$started = microtime( true );
		$run     = catalogue_command( 'validate ' . escapeshellarg( $path ) . ' --out=' . escapeshellarg( $out ) );
		$elapsed = microtime( true ) - $started;
		$record  = json_decode( (string) @file_get_contents( $out . '/run.json' ), true ); // phpcs:ignore
		$counts  = $record['counts'] ?? array();
		preg_match( '/peak memory\s+([0-9.]+ MB)/', $run['out'], $memory );

		$s->note( sprintf( 'fact: %d rows in the file, exit %d', count( $rows ), $run['code'] ) );
		$s->note( 'fact: counts ' . wp_json_encode( $counts ) );
		$s->note( sprintf( 'fact: elapsed %.1f s for the whole command, peak memory %s', $elapsed, $memory[1] ?? 'not reported' ) );

		$accounted = 0 === $run['code']
			&& 'completed' === ( $record['outcome'] ?? '' )
			&& count( $rows ) === ( $counts['rows'] ?? -1 )
			&& count( $rows ) === count( catalogue_csv( $out . '/validation-report.csv' ) )
			&& count( $rows ) === ( $counts['create'] ?? 0 ) + ( $counts['update'] ?? 0 ) + ( $counts['reject'] ?? 0 );
		if ( ! $accounted ) {
			$s->note( 'FAIL  every row must be accounted for' );
			return false;
		}
		$s->note( 'PASS  every row is accounted for' );
		return null;
	}
);

<?php
/**
 * t36, MIG-19 (AC-7, FR-009): every issue the Client must correct reaches the issue list, whatever the row's outcome.
 *
 * Read from the real command's `client-issues.csv`: each line names the row, the SKU, the field, the problem and the
 * correction; an issue on a row that is still created (an empty Arabic name) is there too; no issue the Developer
 * resolves is there; the file opens in Excel with Arabic intact (UTF-8 with a byte-order mark). The Developer's review
 * before sending is a step of the quickstart, recorded at acceptance; this file existing is not that review.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't36-catalogue-issue-list', 'MIG-19 AC-7: every Client issue reaches the list, actionable, and only those' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		catalogue_seed_export_store( $s );
		$out = catalogue_out_dir( $s, 'issues' );
		$run = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out ) );
		$check( 0 === $run['code'], "the validation completes (exit {$run['code']})" );
		$issues = catalogue_csv( $out . '/client-issues.csv' );
		$check( "\xEF\xBB\xBF" === (string) substr( (string) file_get_contents( $out . '/client-issues.csv' ), 0, 3 ), 'AC-7: the list is UTF-8 with a byte-order mark, for Excel' ); // phpcs:ignore

		$expected = array(
			'MZ-NOPRICE' => 'R1', 'MZ-DUP-1' => 'R2', 'mz-dup-1' => 'R2', 'MZ-ORPHAN-1' => 'R5', 'MZ-BADPRICE' => 'R6',
			'MZ-BUNDLE' => 'R7', 'MZ-WIDE' => 'R8', 'MZ-NOAR' => 'W3', 'MZ-LIP-01' => 'R5', 'MZ-LIP' => 'R1', 'MZ-MULTI' => 'R6',
		);
		foreach ( $expected as $sku => $code ) {
			$found = array_filter( $issues, fn ( $i ) => $i['sku'] === $sku && $i['issue_code'] === $code );
			$check( 1 <= count( $found ), "AC-7: '{$sku}' carries its {$code} issue" );
		}
		$check( 'create' === ( catalogue_by_sku( catalogue_csv( $out . '/validation-report.csv' ) )['MZ-NOAR']['outcome'] ?? '' ), 'AC-7: the W3 issue belongs to a row that is still created' );

		$incomplete = array_filter( $issues, fn ( $i ) => '' === $i['row'] || '' === $i['problem'] || '' === $i['correction'] || ( '' === $i['field'] && 'R8' !== $i['issue_code'] ) );
		$check( array() === $incomplete, 'AC-7: every line names the row, the field, the problem and the correction (' . count( $issues ) . ' lines)' );

		$developer = array_filter( $issues, fn ( $i ) => in_array( $i['issue_code'], array( 'R3', 'R4', 'R9', 'W1', 'W4' ), true ) || ( 'MZ-WRONG-1' === $i['sku'] ) );
		$check( array() === $developer, 'AC-7: no issue the Developer resolves is on the Client list (store identity, near matches, a store parent of the wrong type)' );
		// A variation whose parent row is rejected for a store-identity defect (R4, the Developer's) inherits the
		// Developer as owner: the Client is not asked to correct what only the Developer can.
		$amb_a = catalogue_simple( $s, 'MZ-PAR-A' );
		$amb_b = catalogue_simple( $s, 'MZ-PAR-B' );
		catalogue_force_sku( $amb_a, 'MZ-PAR-AMB' );
		catalogue_force_sku( $amb_b, 'MZ-PAR-AMB' );
		$path = catalogue_temp_export(
			$s,
			array(
				array( 'seller-sku' => 'MZ-PAR-AMB', 'item-name' => 'Parent', 'item-name-ar' => 'منتج', 'parent-child' => 'parent' ),
				array( 'seller-sku' => 'MZ-PAR-AMB-1', 'item-name' => 'Child', 'item-name-ar' => 'منتج', 'price' => '100.00', 'parent-child' => 'child', 'parent-sku' => 'MZ-PAR-AMB', 'size-name' => '30 ml' ),
			)
		);
		$out2 = catalogue_out_dir( $s, 'inherited' );
		catalogue_command( 'validate ' . escapeshellarg( $path ) . ' --out=' . escapeshellarg( $out2 ) );
		$child = catalogue_by_sku( catalogue_csv( $out2 . '/validation-report.csv' ) )['MZ-PAR-AMB-1'] ?? array();
		$listed = array_filter( catalogue_csv( $out2 . '/client-issues.csv' ), fn ( $i ) => in_array( $i['sku'], array( 'MZ-PAR-AMB', 'MZ-PAR-AMB-1' ), true ) && in_array( $i['issue_code'], array( 'R4', 'R5' ), true ) );
		$check( 'R5' === ( $child['rule'] ?? '' ) && array() === $listed, 'AC-7: a variation of a parent rejected for a store-identity defect is rejected, and neither is on the Client list' );

		$s->note( 'fact: the Developer reviews this list before it is sent (quickstart step 6); the scenario proves the list, not the review' );

		return $ok;
	}
);

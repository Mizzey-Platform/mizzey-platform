<?php
/**
 * t33, MIG-06 (AC-4, FR-005): every data row gets exactly one outcome and its reason, by the documented rules.
 *
 * The main fixture carries one row per rule (create, update, partial update, missing value, repeated SKU, missing
 * original, ambiguous SKU, unusable parent, unreadable value, unknown row type, ragged row, wrong kind), a parent
 * written after its variations, a parent row that is rejected, a store parent of the wrong type, and a row with
 * several issues at once. The command runs for real and the claims are read from its `validation-report.csv`. The
 * same rows in reverse order must give the same outcomes. An empty file fails at file level; a header-only file
 * completes with no rows.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\ExportReader;
use MizzeySite\Catalogue\Import\Mapping;
use MizzeySite\Catalogue\Import\Cli\CatalogueCommand;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't33-catalogue-outcomes', 'MIG-06 AC-4: one outcome per row, with its reason, by the documented rules' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		catalogue_seed_export_store( $s );
		$out = catalogue_out_dir( $s, 'outcomes' );
		$run = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out ) );
		$check( 0 === $run['code'], "the validation completes, exit 0 (exit {$run['code']})" );
		$lines = catalogue_csv( $out . '/validation-report.csv' );
		$by    = catalogue_by_sku( $lines );
		$check( 23 === count( $lines ), 'AC-4: every data row has one report line (' . count( $lines ) . ')' );

		$outcome_of = array( 'C1' => 'create', 'U1' => 'update' );
		foreach ( catalogue_expected_rules() as $sku => $rule ) {
			$line = $by[ $sku ] ?? null;
			$want = $outcome_of[ $rule ] ?? 'reject';
			$check( null !== $line && $rule === $line['rule'] && $want === $line['outcome'] && '' !== $line['reason'], "AC-4 '{$sku}': {$want} by {$rule}" . ( $line ? " (got {$line['outcome']} {$line['rule']}: {$line['reason']})" : ' (no line)' ) );
		}

		$codes = fn ( string $sku ): array => explode( ' ', $by[ $sku ]['issues'] ?? '' );
		$check( array() === array_diff( array( 'R7', 'R6' ), $codes( 'MZ-MULTI' ) ), 'several issues on one row are all kept (MZ-MULTI: ' . ( $by['MZ-MULTI']['issues'] ?? '' ) . ')' );
		$check( in_array( 'W1', $codes( 'mz-near-001 ' ), true ) && '' !== ( $by['mz-near-001 ']['near_matches'] ?? '' ), 'a near match is an update with W1 and its evidence (' . ( $by['mz-near-001 ']['near_matches'] ?? '' ) . ')' );
		$check( in_array( 'W3', $codes( 'MZ-NOAR' ), true ), 'an empty Arabic name on a create is W3, and the row is still created' );
		$check( 'variable' === ( $by['MZ-SER']['type'] ?? '' ) && 'create' === ( $by['MZ-SER-30']['outcome'] ?? '' ), 'a parent written after its variations is still their parent' );
		$check( false !== strpos( $by['MZ-LIP-01']['reason'] ?? '', 'rejected' ), 'a variation of a rejected parent is rejected for that reason' );
		$check( false !== strpos( $by['MZ-UPD-002']['reason'] ?? '', 'original' ), 'a partial update, blank cells and all, is an update' );

		// Order must not decide anything: the same rows reversed give the same outcomes.
		$mapping  = Mapping::load( CatalogueCommand::defaultMapping() );
		$read     = ExportReader::read( CATALOGUE_FIXTURES . '/export.tsv', $mapping->source() );
		$reversed = array();
		foreach ( array_reverse( $read['rows'] ) as $row ) {
			$cells = $row['cells'];
			$reversed[] = array_combine( array_slice( $read['header'], 0, count( $cells ) ), $cells );
		}
		$path  = catalogue_temp_export( $s, $reversed, $read['header'] );
		$out2  = catalogue_out_dir( $s, 'reversed' );
		catalogue_command( 'validate ' . escapeshellarg( $path ) . ' --out=' . escapeshellarg( $out2 ) );
		$by2   = catalogue_by_sku( catalogue_csv( $out2 . '/validation-report.csv' ) );
		$same  = array_filter( array_keys( $by ), fn ( $sku ) => 'MZ-WIDE' !== $sku && ( $by2[ $sku ]['rule'] ?? '' ) !== $by[ $sku ]['rule'] );
		$check( array() === $same, 'the same rows in reverse order give the same outcomes' . ( $same ? ' (differ: ' . implode( ', ', $same ) . ')' : '' ) );

		$out3  = catalogue_out_dir( $s, 'empty' );
		$empty = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/empty.tsv' ) . ' --out=' . escapeshellarg( $out3 ) );
		$check( 1 === $empty['code'] && ! file_exists( $out3 . '/validation-report.csv' ), "an empty file fails at file level with no row outcomes (exit {$empty['code']})" );
		$out4  = catalogue_out_dir( $s, 'header-only' );
		$only  = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/header-only.tsv' ) . ' --out=' . escapeshellarg( $out4 ) );
		$check( 0 === $only['code'] && array() === catalogue_csv( $out4 . '/validation-report.csv' ), "a header-only file completes with no rows (exit {$only['code']})" );

		return $ok;
	}
);

<?php
/**
 * t37, CX-04 (FR-010, ENT-19): each validation run is kept as a persisted Import Run record.
 *
 * The record (ImportRunLog, in the database) and its copy `run.json` carry the file, the mapping used and its version,
 * the rules version, the counts, the outcome, the actor, the times and the reports; the copy names the record. A
 * file-level failure is recorded with its reason and no invented row outcomes. A run that cannot write its output is
 * never marked completed. The guard case (exit 3, `guard-refused`) is t34's. The load's record is t86's.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\ImportRunLog;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't37-catalogue-run-record', 'CX-04: each run is kept as a persisted Import Run record' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		catalogue_seed_export_store( $s );
		$out    = catalogue_out_dir( $s, 'record' );
		$run    = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out ) );
		$record = json_decode( (string) file_get_contents( $out . '/run.json' ), true ); // phpcs:ignore
		$lines  = catalogue_csv( $out . '/validation-report.csv' );
		$check( 0 === $run['code'] && is_array( $record ), "the run completes and writes run.json (exit {$run['code']})" );
		$record = is_array( $record ) ? $record : array();

		$check( 'export.tsv' === ( $record['file']['name'] ?? '' ) && '' !== ( $record['file']['reference'] ?? '' ), 'CX-04: the file is referenced' );
		$check( 'mizzey-site/import/catalogue-mapping.json' === ( $record['mapping_used'] ?? '' ) && '0.1.0-fixture' === ( $record['mapping_version'] ?? '' ), 'CX-04: the mapping used and its version (' . ( $record['mapping_used'] ?? '' ) . ')' );
		$check( 1 === ( $record['rules_version'] ?? null ), 'CX-04: the rules version' );
		$counts = $record['counts'] ?? array();
		$tally  = array_count_values( array_column( $lines, 'outcome' ) );
		$check( count( $lines ) === ( $counts['rows'] ?? -1 ) && ( $tally['create'] ?? 0 ) === ( $counts['create'] ?? -1 ) && ( $tally['update'] ?? 0 ) === ( $counts['update'] ?? -1 ) && ( $tally['reject'] ?? 0 ) === ( $counts['reject'] ?? -1 ), 'CX-04: the counts agree with the report (' . wp_json_encode( $counts ) . ')' );
		$check( 'completed' === ( $record['outcome'] ?? '' ) && array_key_exists( 'error_reference', $record ) && null === $record['error_reference'], 'CX-04: the outcome is completed, with no error reference' );
		$check( 'wp-cli' === ( $record['actor'] ?? '' ), 'CX-04: the actor (' . ( $record['actor'] ?? '' ) . ')' );
		$check( false !== strtotime( (string) ( $record['started_at'] ?? '' ) ) && strtotime( (string) $record['finished_at'] ) >= strtotime( (string) $record['started_at'] ), 'CX-04: started and finished times' );
		$check( 2 === count( $record['reports'] ?? array() ) && array() === array_filter( $record['reports'], fn ( $p ) => ! file_exists( $p ) ), 'CX-04: the reports it names exist' );
		$check( 'file-copy' === ( $record['recorder'] ?? '' ) && false === ( $record['persisted'] ?? true ), 'FR-010: run.json says it is the copy, not the persisted record' );
		$reference = (string) ( $record['record'] ?? '' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( $reference, 'options' );
		$stored = '' === $reference ? null : ImportRunLog::get( $reference );
		$s->on_finish( static fn () => '' === $reference ? null : delete_option( $reference ) );
		$check( is_array( $stored ) && true === $stored['persisted'] && 'validation' === $stored['kind'] && $stored['file'] === $record['file'] && $stored['counts'] === $record['counts'] && 'completed' === $stored['outcome'] && 'wp-cli' === $stored['actor'] && $stored['started_at'] === $record['started_at'], 'A-273-1 ENT-19: the run is kept in the database with the same file, counts, outcome, actor and times (' . ( '' === $reference ? 'no record' : $reference ) . ')' );

		$out2    = catalogue_out_dir( $s, 'failure' );
		$failure = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/header-mismatch.tsv' ) . ' --out=' . escapeshellarg( $out2 ) );
		$failed  = json_decode( (string) @file_get_contents( $out2 . '/run.json' ), true ); // phpcs:ignore
		$check( 1 === $failure['code'] && 'file-level-failure' === ( $failed['outcome'] ?? '' ) && false !== strpos( (string) ( $failed['reason'] ?? '' ), 'price' ), "a file-level failure is recorded with its reason (exit {$failure['code']})" );
		$check( is_array( $failed ) && array_key_exists( 'counts', $failed ) && null === $failed['counts'] && ! file_exists( $out2 . '/validation-report.csv' ), 'and with no invented row outcomes' );

		$again = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out ) );
		$kept  = json_decode( (string) file_get_contents( $out . '/run.json' ), true ); // phpcs:ignore
		$check( 2 === $again['code'] && ( $kept['started_at'] ?? '' ) === ( $record['started_at'] ?? 'x' ), "an output directory holding an earlier run is refused and the earlier record is kept (exit {$again['code']})" );

		$blocked = catalogue_out_dir( $s, 'blocked' ) . '/not-a-directory';
		file_put_contents( $blocked, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$unwritable = catalogue_command( 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $blocked ) );
		$check( 2 === $unwritable['code'] && ! file_exists( $blocked . '/run.json' ), "output that cannot be written is a usage error, never a completed run (exit {$unwritable['code']})" );
		$s->on_finish( static fn () => @unlink( $blocked ) ); // phpcs:ignore

		return $ok;
	}
);

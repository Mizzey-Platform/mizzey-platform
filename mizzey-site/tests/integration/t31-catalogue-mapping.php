<?php
/**
 * t31, MIG-04 (AC-2): every column of the agreed export is mapped or recorded as not imported, and a mapping that
 * does not fit the file is refused before any row is read.
 *
 * Header differences by name: a missing mapped column (F1), a repeated header (F2) and no header (F3) are file-level
 * failures; an unlisted column (W2) is reported and stops the mapping being called complete; a column recorded as not
 * imported may be absent. The mapping file itself is validated: its version, the rules version, the format, each
 * column's target or reason, and no field filled twice.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\ExportReader;
use MizzeySite\Catalogue\Import\HeaderCheck;
use MizzeySite\Catalogue\Import\Mapping;
use MizzeySite\Catalogue\Import\Cli\CatalogueCommand;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't31-catalogue-mapping', 'MIG-04 AC-2: every column mapped or recorded as not imported; a misfit refused' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$mapping = Mapping::load( CatalogueCommand::defaultMapping() );
		$columns = $mapping->columns();
		$check( '' !== $mapping->version() && 1 === $mapping->rulesVersion(), 'the mapping states its version (' . $mapping->version() . ') and rules version 1' );
		$header = ExportReader::read( CATALOGUE_FIXTURES . '/export.tsv', $mapping->source(), 1 )['header'];
		$unaccounted = array_diff( $header, array_keys( $columns ) );
		$check( array() === $unaccounted, 'AC-2: every fixture header is mapped or recorded as not imported' );
		$reasons = array_filter( $columns, fn ( $e ) => isset( $e['ignore'] ) );
		$check( array() === array_filter( $reasons, fn ( $e ) => '' === trim( $e['ignore'] ) ), 'AC-2: every column not imported says why (' . count( $reasons ) . ' columns)' );
		$ok_check = HeaderCheck::check( $header, $mapping );
		$check( array() === $ok_check['failures'] && $ok_check['complete'], 'the fixture header fits the mapping completely' );

		$cases = array(
			'header-mismatch.tsv'   => array( 'F1', false ),
			'duplicate-headers.tsv' => array( 'F2', false ),
			'extra-column.tsv'      => array( 'W2', true ),
			'empty.tsv'             => array( 'F3', false ),
		);
		foreach ( $cases as $file => list( $code, $runs ) ) {
			$h      = ExportReader::read( CATALOGUE_FIXTURES . '/' . $file, $mapping->source(), 1 )['header'];
			$result = HeaderCheck::check( $h, $mapping );
			$codes  = array_merge( array_column( $result['failures'], 'code' ), array_column( $result['warnings'], 'code' ) );
			$check( in_array( $code, $codes, true ) && ( array() === $result['failures'] ) === $runs && ! $result['complete'], "{$file}: reports {$code}, " . ( $runs ? 'does not stop the run, and is not complete' : 'stops the run' ) );
		}

		$without_ignored = array_values( array_diff( $header, array( 'asin1' ) ) );
		$absent          = HeaderCheck::check( $without_ignored, $mapping );
		$check( array() === $absent['failures'] && array( 'asin1' ) === $absent['absent_ignored'], 'a column recorded as not imported may be absent without failing' );

		$base = json_decode( (string) file_get_contents( CatalogueCommand::defaultMapping() ), true );
		$bad  = array(
			'no version'            => array_merge( $base, array( 'version' => '' ) ),
			'another rules version' => array_merge( $base, array( 'rules_version' => 2 ) ),
			'unagreed format'       => array_merge( $base, array( 'source' => array( 'format' => 'xlsx', 'encoding' => 'UTF-8' ) ) ),
			'ignore without reason' => array_merge( $base, array( 'columns' => array_merge( $base['columns'], array( 'asin1' => array( 'ignore' => ' ' ) ) ) ) ),
			'unknown field'         => array_merge( $base, array( 'columns' => array_merge( $base['columns'], array( 'asin1' => array( 'to' => 'barcode' ) ) ) ) ),
			'field filled twice'    => array_merge( $base, array( 'columns' => array_merge( $base['columns'], array( 'asin1' => array( 'to' => 'sku' ) ) ) ) ),
		);
		$dir = catalogue_out_dir( $s, 'mapping' );
		foreach ( $bad as $label => $data ) {
			$path = $dir . '/m.json';
			file_put_contents( $path, wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$refused = false;
			try {
				Mapping::load( $path );
			} catch ( \InvalidArgumentException $e ) {
				$refused = true;
			}
			$check( $refused, "a mapping with {$label} is refused" );
		}

		return $ok;
	}
);

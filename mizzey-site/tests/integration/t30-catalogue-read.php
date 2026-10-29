<?php
/**
 * t30, MIG-01 (AC-1): the agreed export is read row for row, in its agreed format, keeping what it says.
 *
 * The agreed format is tab-delimited text with a byte-order mark, as Amazon's reports download; the fixture is that.
 * Identifiers and parsed text are kept exactly: a SKU with a trailing space keeps it, Arabic stays Arabic, row numbers
 * count the header as row 1, and a short row stays short for the rules to judge. Another format's cases are added when
 * that format is agreed (research R-3).
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
	new Scenario( 't30-catalogue-read', 'MIG-01 AC-1: the agreed export is read row for row, identifiers and text kept' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$mapping = Mapping::load( CatalogueCommand::defaultMapping() );
		$read    = ExportReader::read( CATALOGUE_FIXTURES . '/export.tsv', $mapping->source() );
		$check( 16 === count( $read['header'] ), 'AC-1: the header has its 16 columns (' . count( $read['header'] ) . ')' );
		$check( 'seller-sku' === $read['header'][0], "AC-1: the byte-order mark is stripped from the first header ('" . $read['header'][0] . "')" );
		$check( 23 === count( $read['rows'] ), 'AC-1: every data row is read (' . count( $read['rows'] ) . ')' );
		$check( 2 === $read['rows'][0]['row'] && 24 === end( $read['rows'] )['row'], 'AC-1: row numbers count the header as row 1' );

		$first = array_combine( $read['header'], $read['rows'][0]['cells'] );
		$check( 'MZ-VIT-C-1000' === $first['seller-sku'], 'AC-1: the first SKU is read as written' );
		$check( 'فيتامين سي ١٠٠٠ مجم، ٦٠ كبسولة' === $first['item-name-ar'], 'AC-1: Arabic text, Arabic digits included, is read intact' );
		$near = array_values( array_filter( $read['rows'], fn ( $r ) => str_starts_with( $r['cells'][0], 'mz-near' ) ) );
		$check( 1 === count( $near ) && 'mz-near-001 ' === $near[0]['cells'][0], 'AC-1: a trailing space in a SKU is kept, not trimmed' );
		$wide = array_values( array_filter( $read['rows'], fn ( $r ) => 'MZ-WIDE' === $r['cells'][0] ) );
		$check( 1 === count( $wide ) && 3 === count( $wide[0]['cells'] ), 'AC-1: a short row is kept short, for the rules to judge' );

		$limited = ExportReader::read( CATALOGUE_FIXTURES . '/export.tsv', $mapping->source(), 3 );
		$check( 3 === count( $limited['rows'] ), 'the preview read stops at its row limit' );
		$empty = ExportReader::read( CATALOGUE_FIXTURES . '/empty.tsv', $mapping->source() );
		$check( array() === $empty['header'] && array() === $empty['rows'], 'an empty file reads as no header and no rows' );

		return $ok;
	}
);

<?php
/**
 * t32, MIG-03 (AC-3): before anything is written, the preview shows the first rows as the store would read them.
 *
 * Run as the Developer runs it: `wp mizzey catalogue preview`, in its own process. It names the mapping version, the
 * format and the encoding it read with, the header check, and each row's fields under the mapping, Arabic intact, up to
 * the row limit. A file the mapping does not fit stops with exit 1 and its header differences.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't32-catalogue-preview', 'MIG-03 AC-3: the preview shows the first rows under the mapping, Arabic intact' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$run = catalogue_command( 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --rows=3' );
		$out = $run['out'];
		$check( 0 === $run['code'], "AC-3: the preview exits 0 (exit {$run['code']})" );
		$check( false !== strpos( $out, 'Mapping 0.1.0-fixture (initial-load rules 1)' ), 'AC-3: it names the mapping version and the rules version' );
		$check( false !== strpos( $out, 'format tsv, encoding UTF-8' ), 'AC-3: it names the format and the encoding it read with' );
		$check( false !== strpos( $out, 'every column is mapped or recorded as not imported' ), 'AC-3: it reports the header check' );
		$check( false !== strpos( $out, 'Row 2 (simple)' ) && false !== strpos( $out, 'Row 4 (variation)' ), 'AC-3: it shows rows by number and type' );
		$check( false === strpos( $out, 'Row 5' ), 'AC-3: it stops at the row limit' );
		$check( false !== strpos( $out, 'فيتامين سي ١٠٠٠ مجم' ), 'AC-3: Arabic text is shown as parsed' );
		$check( 1 === preg_match( '/regular_price\s+349\.00/', $out ) && 1 === preg_match( '/attribute_size\s+30 ml/', $out ), 'AC-3: fields are shown under their store names' );

		$misfit = catalogue_command( 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/header-mismatch.tsv' ) );
		$check( 1 === $misfit['code'] && false !== strpos( $misfit['out'], 'F1' ), "a file the mapping does not fit stops with exit 1 and names F1 (exit {$misfit['code']})" );
		$extra = catalogue_command( 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/extra-column.tsv' ) );
		$check( 0 === $extra['code'] && false !== strpos( $extra['out'], 'W2' ) && false !== strpos( $extra['out'], 'not complete' ), 'an unlisted column is reported, and the mapping is not called complete' );
		$missing = catalogue_command( 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/no-such-file.tsv' ) );
		$check( 2 === $missing['code'], "an unreadable file is a usage error, exit 2 (exit {$missing['code']})" );

		return $ok;
	}
);

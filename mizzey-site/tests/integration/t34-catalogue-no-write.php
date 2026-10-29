<?php
/**
 * t34, MIG-03 and MIG-06 (AC-5, FR-007): the preview and the validation pass write nothing to the catalogue.
 *
 * Both real commands run in their own processes, on a completed run and on a file-level failure, and every protected
 * catalogue table (products and media, their metadata, terms and relationships, term metadata, WooCommerce's product
 * lookup tables, WPML's translation records) has the same checksum before and after. The guard is then exercised:
 * in process, a WordPress API write and a direct SQL write are refused before they run while an option write, which
 * read-side housekeeping needs, passes; and in a real command run, a write attempted by another plugin during the run
 * stops it with exit 3 and a run record that says so.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\CatalogueWriteGuard;
use MizzeySite\Catalogue\Import\CatalogueWriteRefused;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't34-catalogue-no-write', 'MIG-03/MIG-06 AC-5: preview and validation write nothing to the catalogue' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		catalogue_seed_export_store( $s );
		wp_cache_flush();
		$before = catalogue_snapshot();
		$out    = catalogue_out_dir( $s, 'nowrite' );
		$runs   = array(
			'preview'                => 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ),
			'validate'               => 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out ),
			'validate, file failure' => 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/header-mismatch.tsv' ) . ' --out=' . escapeshellarg( $out ),
		);
		foreach ( $runs as $label => $args ) {
			$result = catalogue_command( $args );
			$after  = catalogue_snapshot();
			$moved  = array_keys( array_diff_assoc( $after, $before ) );
			$check( array() === $moved, "AC-5 [{$label}, exit {$result['code']}] every protected table is unchanged" . ( $moved ? ' (changed: ' . implode( ', ', $moved ) . ')' : '' ) );
		}

		// The guard, in process.
		CatalogueWriteGuard::install();
		$api_refused = false;
		try {
			wp_insert_post( array( 'post_title' => 't34 guard probe', 'post_type' => 'product', 'post_status' => 'draft' ) );
		} catch ( CatalogueWriteRefused $e ) {
			$api_refused = true;
		}
		$sql_refused = false;
		try {
			$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value WHERE meta_id = 0" ); // phpcs:ignore
		} catch ( CatalogueWriteRefused $e ) {
			$sql_refused = true;
		}
		$option_ok = false;
		try {
			$option_ok = update_option( 'mizzey_t34_probe', 'x', false );
			delete_option( 'mizzey_t34_probe' );
		} catch ( CatalogueWriteRefused $e ) {
			$option_ok = false;
		}
		CatalogueWriteGuard::remove();
		$probe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s", 't34 guard probe' ) ); // phpcs:ignore
		$check( $api_refused && 0 === $probe, 'AC-5: a WordPress API write to the catalogue is refused before it runs' );
		$check( $sql_refused, 'AC-5: a direct SQL write to a catalogue table is refused before it runs' );
		$check( $option_ok, 'an option write, which read-side housekeeping needs, is allowed' );

		// A write attempted by another plugin during a real run stops it with exit 3.
		$dir     = catalogue_out_dir( $s, 'intruder' );
		$require = $dir . '/intruder.php';
		file_put_contents( $require, "<?php\nWP_CLI::add_hook( 'after_wp_load', function () {\n\tadd_filter( 'wpml_element_language_details', function ( \$d ) {\n\t\tglobal \$wpdb;\n\t\t\$wpdb->query( \"UPDATE {\$wpdb->posts} SET post_title = post_title WHERE ID = 0\" );\n\t\treturn \$d;\n\t}, 1 );\n} );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$out2   = catalogue_out_dir( $s, 'guarded' );
		$before = catalogue_snapshot();
		$guard  = catalogue_command( '--require=' . escapeshellarg( $require ) . ' validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out2 ) );
		$record = json_decode( (string) @file_get_contents( $out2 . '/run.json' ), true ); // phpcs:ignore
		$check( 3 === $guard['code'], "AC-5: a write attempted during a run stops it with exit 3 (exit {$guard['code']})" );
		$check( 'guard-refused' === ( $record['outcome'] ?? '' ), "the run record says guard-refused, not completed ('" . ( $record['outcome'] ?? 'none' ) . "')" );
		$check( array() === array_diff_assoc( catalogue_snapshot(), $before ), 'and the catalogue is unchanged' );

		return $ok;
	}
);

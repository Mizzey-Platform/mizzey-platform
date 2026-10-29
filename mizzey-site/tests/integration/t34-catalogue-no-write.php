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
		$runs   = array(
			'preview'                => array( 0, 'preview ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) ),
			'validate'               => array( 0, 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( catalogue_out_dir( $s, 'nowrite' ) ) ),
			'validate, file failure' => array( 1, 'validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/header-mismatch.tsv' ) . ' --out=' . escapeshellarg( catalogue_out_dir( $s, 'nowrite-failure' ) ) ),
		);
		foreach ( $runs as $label => list( $expected, $args ) ) {
			$result = catalogue_command( $args );
			$check( $expected === $result['code'], "[{$label}] the command did its work: exit {$result['code']}, expected {$expected}" );
			$moved = array_keys( array_diff_assoc( catalogue_snapshot(), $before ) );
			$check( array() === $moved, "AC-5 [{$label}] every protected table is unchanged" . ( $moved ? ' (changed: ' . implode( ', ', $moved ) . ')' : '' ) );
		}

		// The guard, in process, always removed again.
		$probe_title = 't34 guard probe ' . wp_generate_uuid4();
		$refused     = function ( callable $write ): bool {
			try {
				$write();
			} catch ( CatalogueWriteRefused $e ) {
				return true;
			}
			return false;
		};
		CatalogueWriteGuard::install();
		try {
			$api = $refused(
				function () use ( $probe_title, $s ) {
					$id = wp_insert_post( array( 'post_title' => $probe_title, 'post_type' => 'product', 'post_status' => 'draft' ) );
					if ( is_int( $id ) && $id > 0 ) {
						$s->track_post( $id );
					}
				}
			);
			$p     = $wpdb->posts;
			$m     = $wpdb->postmeta;
			$shapes = array(
				'direct SQL'               => "UPDATE {$m} SET meta_value = meta_value WHERE meta_id = 0",
				'a leading line comment'   => "-- note\nUPDATE {$p} SET post_title = post_title WHERE ID = 0",
				'repeated block comments'  => "/* a */ /* b */ DELETE FROM {$m} WHERE meta_id = 0",
				'an executable comment'    => "/*!40000 UPDATE {$p} SET post_title = post_title WHERE ID = 0 */",
				'a quoted identifier'      => "UPDATE `{$p}` SET post_title = post_title WHERE ID = 0",
				'a common table expression' => "WITH x AS (SELECT 0 AS id) DELETE FROM {$m} WHERE meta_id IN (SELECT id FROM x)",
			);
			$shape_results = array();
			foreach ( $shapes as $label => $sql ) {
				$shape_results[ $label ] = $refused( fn () => $wpdb->query( $sql ) ); // phpcs:ignore
			}
			$option_ok = ! $refused( fn () => update_option( 'mizzey_t34_probe', "a value that mentions FROM {$p} WHERE", false ) );
			delete_option( 'mizzey_t34_probe' );
			$read_ok = ! $refused( fn () => $wpdb->get_var( "SELECT COUNT(*) FROM {$p} WHERE ID = 0" ) ); // phpcs:ignore
		} finally {
			CatalogueWriteGuard::remove();
		}
		$probe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s", $probe_title ) ); // phpcs:ignore
		$check( $api && 0 === $probe, 'AC-5: a WordPress API write to the catalogue is refused before it runs' );
		foreach ( $shape_results as $label => $was_refused ) {
			$check( $was_refused, "AC-5: a catalogue write written as {$label} is refused before it runs" );
		}
		$check( $option_ok, 'an option write whose value mentions a catalogue table is allowed: values are not statements' );
		$check( $read_ok, 'a read of a catalogue table is allowed' );

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

		// A write attempted while WordPress loads, before the command starts (as an install routine on `init` would),
		// is refused too: the plugin installs the guard as it loads when a catalogue command is the one being run.
		$early = $dir . '/early.php';
		file_put_contents( $early, "<?php\nWP_CLI::add_wp_hook( 'init', function () {\n\tglobal \$wpdb;\n\t\$wpdb->query( \"UPDATE {\$wpdb->posts} SET post_title = post_title WHERE ID = 0\" );\n}, 1 );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$out3   = catalogue_out_dir( $s, 'early' );
		$before = catalogue_snapshot();
		$boot   = catalogue_command( '--require=' . escapeshellarg( $early ) . ' validate ' . escapeshellarg( CATALOGUE_FIXTURES . '/export.tsv' ) . ' --out=' . escapeshellarg( $out3 ) );
		$check( 3 === $boot['code'] && false !== strpos( $boot['out'], 'catalogue write was attempted' ), "AC-5: a write during the load of WordPress is refused with exit 3 and its reason, and the run does not start (exit {$boot['code']})" );
		$check( ! file_exists( $out3 . '/run.json' ) && ! file_exists( $out3 . '/validation-report.csv' ), 'and no run is recorded, completed or otherwise' );
		$check( array() === array_diff_assoc( catalogue_snapshot(), $before ), 'and the catalogue is unchanged' );

		return $ok;
	}
);

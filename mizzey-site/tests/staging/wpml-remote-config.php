<?php
/**
 * WPML's downloaded configuration, carried from the development runtime to staging by `staging.py reset`.
 *
 *     wp --path=<development> eval-file wpml-remote-config.php export <file>
 *     wp --path=<staging>     eval-file wpml-remote-config.php import <file>
 *
 * Why. When a plugin is activated WPML asks its own host for a configuration index and for the file of every
 * active plugin the index names (WPML_Config_Update::run). Where the index marks a file `override_local`, it is
 * used in place of the plugin's bundled wpml-config.xml. Development makes that request. Staging is configured so
 * that no request leaves the machine, WordPress refuses it, and staging was left on the bundled files: one custom
 * field and one post type short of development, with the cost field among them (measured on 5 October 2026).
 *
 * Staging stays closed. Its third-party plugins are already copies of development's, and this copies the one part
 * of them that is not a file: the three options WPML keeps the download in. The wp-admin visit of the baseline
 * then applies them, exactly as it does on development.
 *
 * `export` reads and writes nothing to the database. `import` writes those three options, on staging only.
 * Both print one JSON line naming each configuration file by hash, so the caller can see that what landed is
 * what was sent.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;

$mode = (string) ( $args[0] ?? '' );
$path = (string) ( $args[1] ?? '' );
if ( ! in_array( $mode, array( 'export', 'import' ), true ) || '' === $path ) {
	WP_CLI::error( 'usage: wp eval-file wpml-remote-config.php <export|import> <file>' );
}

if ( 'export' === $mode ) {
	$index = get_option( 'wpml_config_index' );
	$files = get_option( 'wpml_config_files_arr' );
	if ( ! is_object( $index ) || ! is_object( $files ) || empty( $files->plugins ) ) {
		WP_CLI::error(
			'this runtime holds no downloaded WPML configuration, so there is nothing to carry. Reset it with '
			. 'mizzey-site/tests/integration/baseline/reset-runtime.sh while cdn.wpml.org can be reached, and check '
			. 'that its summary does not say "remote_config_index: not downloaded".'
		);
	}
	$carried = array(
		'index'   => $index,
		'updated' => (int) get_option( 'wpml_config_index_updated' ),
		'plugins' => (object) (array) $files->plugins,
		'themes'  => (object) (array) ( $files->themes ?? array() ),
	);
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a local tool file
	if ( false === file_put_contents( $path, wp_json_encode( $carried ) ) ) {
		WP_CLI::error( "could not write {$path}" );
	}
}

if ( 'import' === $mode ) {
	defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING && 'staging' === wp_get_environment_type() || WP_CLI::error( 'import is for staging only' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local tool file
	$carried = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ) ) : null;
	if ( ! is_object( $carried ) || ! is_object( $carried->index ?? null ) || ! is_object( $carried->plugins ?? null ) ) {
		WP_CLI::error( "{$path} does not hold a WPML configuration" );
	}
	// The shapes WPML itself stores: the index as decoded JSON, the files as arrays of XML keyed by name.
	$files          = new stdClass();
	$files->themes  = (array) ( $carried->themes ?? array() );
	$files->plugins = (array) $carried->plugins;
	update_option( 'wpml_config_index', $carried->index, false );
	update_option( 'wpml_config_index_updated', (int) $carried->updated, false );
	update_option( 'wpml_config_files_arr', $files, false );
	wp_cache_flush();
}

// What this runtime now holds, read back from storage and not from the variables above.
$held    = get_option( 'wpml_config_files_arr' );
$indexed = get_option( 'wpml_config_index' );
$hashes  = array();
foreach ( array( 'plugins', 'themes' ) as $kind ) {
	foreach ( (array) ( is_object( $held ) ? ( $held->$kind ?? array() ) : array() ) as $name => $xml ) {
		$hashes[ $kind . '/' . $name ] = md5( (string) $xml );
	}
}
ksort( $hashes );
echo wp_json_encode(
	array(
		'mode'    => $mode,
		'runtime' => wp_get_environment_type(),
		'index'   => is_object( $indexed ) ? md5( wp_json_encode( $indexed ) ) : null,
		'files'   => $hashes,
	)
), "\n";

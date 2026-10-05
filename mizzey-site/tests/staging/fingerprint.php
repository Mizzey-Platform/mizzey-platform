<?php
/**
 * A fingerprint of the staging database and uploads, printed as one JSON line. The restore test takes it before
 * the backup and after the restore: equal fingerprints are the evidence that what came back is what was saved.
 *
 * Row counts alone would pass a restore that scrambled content, so every stable table is also checksummed.
 *
 * A few tables are volatile: WordPress rewrites them on any request (options with its transients and cron, the
 * Action Scheduler queue, sessions, admin notes, user meta with its session tokens). They are restored like every
 * other table, but they are not compared, because they change between the reading and the dump while the site
 * is running. Their row count is reported on its own line and is not part of the comparison. The third restore
 * test failed on exactly this: a total that included them differed by a hundred rows while every stable table,
 * every count and every media file was identical.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
sort( $tables );

$volatile  = '/(_options|_actionscheduler_\w+|_wc_admin_note\w*|_woocommerce_sessions|_usermeta)$/';
$rows      = array();
$checksums = array();
foreach ( $tables as $table ) {
	$rows[ $table ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	if ( ! preg_match( $volatile, $table ) ) {
		$checksums[ $table ] = (string) $wpdb->get_row( $wpdb->prepare( 'CHECKSUM TABLE %i', $table ), ARRAY_A )['Checksum'];
	}
}

$uploads = wp_get_upload_dir()['basedir'];
$files   = array();
if ( is_dir( $uploads ) ) {
	$walk = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $uploads, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $walk as $file ) {
		if ( $file->isFile() && ! preg_match( '#[\\\\/](wc-logs|woocommerce_uploads|cache)[\\\\/]#', $file->getPathname() ) ) {
			$files[ str_replace( '\\', '/', substr( $file->getPathname(), strlen( $uploads ) ) ) ] = hash_file( 'sha256', $file->getPathname() );
		}
	}
}
ksort( $files );

$count = static function ( string $type, string $status = 'publish' ) use ( $wpdb ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_type = %s AND post_status = %s', $wpdb->posts, $type, $status ) );
};

echo wp_json_encode(
	array(
		'tables'            => count( $tables ),
		'rows_stable'       => array_sum( array_intersect_key( $rows, $checksums ) ),
		'rows_volatile'     => array_sum( array_diff_key( $rows, $checksums ) ),
		'tables_checksum'   => hash( 'sha256', wp_json_encode( $checksums ) ),
		'checksummed'       => count( $checksums ),
		'products'          => $count( 'product' ),
		'variations'        => $count( 'product_variation' ),
		'orders'            => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s', $wpdb->prefix . 'wc_orders', 'shop_order' ) ),
		'refunds'           => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type = %s', $wpdb->prefix . 'wc_orders', 'shop_order_refund' ) ),
		'users'             => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->users ) ),
		'translation_rows'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'icl_translations' ) ),
		'upload_files'      => count( $files ),
		'uploads_checksum'  => hash( 'sha256', wp_json_encode( $files ) ),
		'wordpress'         => get_bloginfo( 'version' ),
		'woocommerce'       => defined( 'WC_VERSION' ) ? WC_VERSION : null,
	)
), "\n";

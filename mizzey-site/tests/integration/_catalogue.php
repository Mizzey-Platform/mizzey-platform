<?php
/**
 * Shared helpers for the catalogue import scenarios, t30 to t38 (specs/006-catalogue-import-spec).
 *
 * Store records are seeded through WooCommerce and WPML themselves, so the SKU index meets the shapes it will meet in
 * the store: an English original, its Arabic duplicate, a variable product and its variations. The two shapes the
 * store refuses to create on its own, two unrelated records sharing a SKU and a translation whose SKU differs from its
 * original's, are made by writing the SKU where WooCommerce keeps it, the post meta and the lookup table, and nowhere
 * else. Every record is tracked and removed when the scenario finishes.
 *
 * The command is run for real, in its own WP-CLI process, because its exit code and its files are part of each claim.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

const CATALOGUE_FIXTURES = __DIR__ . '/fixtures/catalogue';

/** Register a product or variation as an English original, the way a store record made in the admin is. */
function catalogue_as_english( int $id, string $type = 'product' ): void {
	do_action( 'wpml_switch_language', 'en' );
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_' . $type ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_' . $type, 'trid' => false, 'language_code' => 'en' ) );
	}
}

/** A published simple product with a SKU, registered in English. */
function catalogue_simple( Scenario $s, string $sku, string $name = 'Catalogue fixture' ): int {
	do_action( 'wpml_switch_language', 'en' );
	$product = $s->simple_product( $name, '100', null );
	$product->set_sku( $sku );
	$product->save();
	catalogue_as_english( $product->get_id() );
	return $product->get_id();
}

/** A published variable product with one size attribute, and a variation per given SKU. */
function catalogue_variable( Scenario $s, string $sku, array $variation_skus = array() ): int {
	do_action( 'wpml_switch_language', 'en' );
	$attribute = new \WC_Product_Attribute();
	$attribute->set_name( 'Size' );
	$attribute->set_options( array( 'Small', 'Large' ) );
	$attribute->set_variation( true );
	$attribute->set_visible( true );
	$product = new \WC_Product_Variable();
	$product->set_name( 'Catalogue variable fixture' );
	$product->set_sku( $sku );
	$product->set_status( 'publish' );
	$product->set_attributes( array( $attribute ) );
	$product->save();
	$s->track_post( $product->get_id() );
	catalogue_as_english( $product->get_id() );
	foreach ( $variation_skus as $index => $variation_sku ) {
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $product->get_id() );
		$variation->set_attributes( array( 'size' => 0 === $index ? 'Small' : 'Large' ) );
		$variation->set_regular_price( '100' );
		$variation->set_sku( $variation_sku );
		$variation->save();
		$s->track_post( $variation->get_id() );
		catalogue_as_english( $variation->get_id(), 'product_variation' );
	}
	return $product->get_id();
}

/** The Arabic duplicate WPML makes of an English product, or 0. */
function catalogue_duplicate( Scenario $s, int $en_id ): int {
	global $sitepress;
	$sitepress->make_duplicate( $en_id, 'ar' );
	$ar = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
	do_action( 'wpml_switch_language', 'en' );
	if ( $ar > 0 && $ar !== $en_id ) {
		$s->track_post( $ar );
		return $ar;
	}
	return 0;
}

/** Write a SKU where WooCommerce keeps it, bypassing its uniqueness check, for shapes the store will not create itself. */
function catalogue_force_sku( int $id, string $sku ): void {
	global $wpdb;
	update_post_meta( $id, '_sku', $sku );
	$table   = $wpdb->prefix . 'wc_product_meta_lookup';
	$updated = $wpdb->update( $table, array( 'sku' => $sku ), array( 'product_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( 0 === $updated && null === $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$table} WHERE product_id = %d", $id ) ) ) { // phpcs:ignore
		$wpdb->insert( $table, array( 'product_id' => $id, 'sku' => $sku ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	wc_delete_product_transients( $id );
}

/**
 * The store records the main fixture (fixtures/catalogue/export.tsv) refers to, so each of its rows meets the case it
 * is written for.
 *
 * @return array<string,int> The seeded ids by name.
 */
function catalogue_seed_export_store( Scenario $s ): array {
	$ids              = array();
	$ids['upd1']      = catalogue_simple( $s, 'MZ-UPD-001', 'Whey Protein 1 kg' );
	$ids['upd1_ar']   = catalogue_duplicate( $s, $ids['upd1'] );
	$ids['upd2']      = catalogue_simple( $s, 'MZ-UPD-002', 'Magnesium 120 tablets' );
	$ids['trx']       = catalogue_simple( $s, 'MZ-TRX-ORIG', 'Omega 3' );
	$ids['trx_ar']    = catalogue_duplicate( $s, $ids['trx'] );
	wp_trash_post( $ids['trx'] );
	catalogue_force_sku( $ids['trx_ar'], 'MZ-TRX-001' );
	$ids['amb_a']     = catalogue_simple( $s, 'MZ-AMB-A', 'Band set A' );
	$ids['amb_b']     = catalogue_simple( $s, 'MZ-AMB-B', 'Band set B' );
	catalogue_force_sku( $ids['amb_a'], 'MZ-AMB-001' );
	catalogue_force_sku( $ids['amb_b'], 'MZ-AMB-001' );
	$ids['near']      = catalogue_simple( $s, 'MZ-NEAR-001', 'Collagen Powder' );
	$ids['simple']    = catalogue_simple( $s, 'MZ-SIMPLE-STORE', 'Dish Soap' );
	$ids['varparent'] = catalogue_variable( $s, 'MZ-VARPARENT-STORE' );
	$ids['kindparent'] = catalogue_variable( $s, 'MZ-KIND-PARENT', array( 'MZ-KIND-1' ) );
	return $ids;
}

/** The rule each row of the main fixture is written to meet, given catalogue_seed_export_store(). */
function catalogue_expected_rules(): array {
	return array(
		'MZ-VIT-C-1000' => 'C1', 'MZ-SER-30' => 'C1', 'MZ-SER-50' => 'C1', 'MZ-SER' => 'C1', 'MZ-UPD-001' => 'U1',
		'MZ-UPD-002' => 'U1', 'MZ-NOPRICE' => 'R1', 'MZ-DUP-1' => 'R2', 'mz-dup-1' => 'R2', 'MZ-TRX-001' => 'R3',
		'MZ-AMB-001' => 'R4', 'MZ-ORPHAN-1' => 'R5', 'MZ-BADPRICE' => 'R6', 'MZ-BUNDLE' => 'R7', 'mz-near-001 ' => 'U1',
		'MZ-NOAR' => 'C1', 'MZ-LIP-01' => 'R5', 'MZ-LIP' => 'R1', 'MZ-WRONG-1' => 'R5', 'MZ-WIDE' => 'R8',
		'MZ-STOREVAR-1' => 'C1', 'MZ-KIND-1' => 'R9', 'MZ-MULTI' => 'R7',
	);
}

/** A fresh directory for a run's output, removed when the scenario finishes. */
function catalogue_out_dir( Scenario $s, string $name ): string {
	$dir = rtrim( get_temp_dir(), '/\\' ) . '/mizzey-catalogue-' . $name . '-' . wp_rand();
	wp_mkdir_p( $dir );
	$s->on_finish(
		static function () use ( $dir ) {
			foreach ( (array) glob( $dir . '/*' ) as $file ) {
				unlink( $file );
			}
			rmdir( $dir );
		}
	);
	return $dir;
}

/** Write a temporary export from rows, with the fixture header unless another is given. */
function catalogue_temp_export( Scenario $s, array $rows, ?array $header = null ): string {
	$header = $header ?? catalogue_fixture_header();
	$dir    = catalogue_out_dir( $s, 'export' );
	$path   = $dir . '/export.tsv';
	$lines  = array( implode( "\t", $header ) );
	foreach ( $rows as $row ) {
		$cells = array();
		foreach ( $header as $column ) {
			$cells[] = $row[ $column ] ?? '';
		}
		$lines[] = implode( "\t", $cells );
	}
	file_put_contents( $path, "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $path;
}

/** @return list<string> */
function catalogue_fixture_header(): array {
	$first = (string) fgets( fopen( CATALOGUE_FIXTURES . '/export.tsv', 'rb' ) );
	return explode( "\t", trim( str_replace( "\xEF\xBB\xBF", '', $first ) ) );
}

/**
 * Run `wp mizzey catalogue <args>` in its own process.
 *
 * @return array{code:int,out:string}
 */
function catalogue_command( string $args ): array {
	$bin  = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
	$out  = array();
	$code = 0;
	exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' mizzey catalogue ' . $args . ' 2>&1', $out, $code );
	return array( 'code' => $code, 'out' => implode( "\n", $out ) );
}

/**
 * Read a report CSV written by the command.
 *
 * @return list<array<string,string>>
 */
function catalogue_csv( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$handle = fopen( $path, 'rb' );
	$bom    = fread( $handle, 3 );
	if ( "\xEF\xBB\xBF" !== $bom ) {
		rewind( $handle );
	}
	$header = fgetcsv( $handle, 0, ',', '"', '' );
	$rows   = array();
	while ( ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
		$rows[] = array_combine( $header, $cells );
	}
	fclose( $handle );
	return $rows;
}

/** Report lines keyed by source SKU. */
function catalogue_by_sku( array $lines ): array {
	$by = array();
	foreach ( $lines as $line ) {
		$by[ $line['sku'] ] = $line;
	}
	return $by;
}

/**
 * A checksum of every protected catalogue table, to compare before and after a run.
 *
 * @return array<string,string>
 */
function catalogue_snapshot(): array {
	global $wpdb;
	$tables = \MizzeySite\Catalogue\Import\CatalogueWriteGuard::tables();
	$sums   = array();
	$rows   = $wpdb->get_results( 'CHECKSUM TABLE ' . implode( ', ', array_map( fn ( $t ) => '`' . $t . '`', $tables ) ), ARRAY_A ); // phpcs:ignore
	// Fail closed: an empty or partial snapshot would make "unchanged" true by default.
	if ( ! is_array( $rows ) || count( $rows ) !== count( $tables ) || array() !== array_filter( $rows, fn ( $r ) => ! isset( $r['Checksum'] ) ) ) {
		throw new \RuntimeException( 'A complete catalogue checksum snapshot could not be read.' );
	}
	foreach ( $rows as $row ) {
		$sums[ (string) $row['Table'] ] = (string) $row['Checksum'];
	}
	return $sums;
}

<?php
/**
 * P-008. Does the Woo CSV importer detect Arabic encoding, and does a re-run update by SKU?
 *
 * MIG-01 to MIG-25 contract a migration tool with a dry run, saved mappings, a row-level error report
 * and safe re-runs. Section 7 row 6 assumes the native importer offers none of that. The re-run
 * question matters most: an importer that duplicates on the second pass makes a catalogue load a
 * one-shot operation with no way back.
 */

$notes   = array();
$verdict = 'partial';
$made    = array();
$file    = '';

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}
	$importer_class = 'WC_Product_CSV_Importer';
	if ( ! class_exists( $importer_class ) ) {
		include_once WP_PLUGIN_DIR . '/woocommerce/includes/import/class-wc-product-csv-importer.php';
	}
	if ( ! class_exists( $importer_class ) ) {
		throw new RuntimeException( 'WC_Product_CSV_Importer not available.' );
	}

	// 1. What does the importer offer?
	$methods  = get_class_methods( $importer_class );
	$has_dry  = (bool) array_filter( $methods, static function ( $m ) {
		return false !== stripos( $m, 'dry' );
	} );
	$has_map  = (bool) array_filter( $methods, static function ( $m ) {
		return false !== stripos( $m, 'saved_mapping' ) || false !== stripos( $m, 'load_mapping' );
	} );

	$notes[] = sprintf(
		'WooCommerce %s ships %s with %d methods. A dry-run method: %s. A saved-mapping method: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$importer_class,
		count( $methods ),
		$has_dry ? 'yes' : 'no',
		$has_map ? 'yes' : 'no'
	);

	// 2. Arabic content through a UTF-8 CSV, imported twice by the same SKU.
	$arabic = "\u{0639}\u{0637}\u{0631} \u{0627}\u{0644}\u{0645}\u{0644}\u{0643}";
	$sku    = 'P008-SKU-1';
	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['basedir'] ) . 'p008-probe.csv';
	$csv    = "Name,SKU,Regular price\n\"" . $arabic . "\",{$sku},100\n";
	file_put_contents( $file, "\xEF\xBB\xBF" . $csv ); // BOM, as Excel writes it

	// Positional, matching the raw header order. Woo's importer takes the mapping as an indexed array
	// in column order, not keyed by header name: keying it by name silently maps nothing, imports
	// nothing, and reads as a clean run that did no work.
	$args = array(
		'mapping'         => array( 'name', 'sku', 'regular_price' ),
		'parse'           => true,
		'update_existing' => true,
		'lines'           => 10,
	);

	$first  = ( new WC_Product_CSV_Importer( $file, $args ) )->import();
	$second = ( new WC_Product_CSV_Importer( $file, $args ) )->import();

	$found = wc_get_products( array( 'sku' => $sku, 'limit' => 10, 'return' => 'ids', 'status' => 'any' ) );
	$made  = is_array( $found ) ? $found : array();

	$name_ok = false;
	if ( $made ) {
		$name_ok = ( wc_get_product( $made[0] )->get_name() === $arabic );
	}

	$notes[] = sprintf(
		'A UTF-8 CSV with a BOM and an Arabic product name was imported twice with update_existing on. '
			. 'Pass one imported %d and updated %d; pass two imported %d and updated %d. Products carrying SKU %s '
			. 'afterwards: %d. The Arabic name round-tripped intact: %s.',
		count( $first['imported'] ), count( $first['updated'] ),
		count( $second['imported'] ), count( $second['updated'] ),
		$sku, count( $made ),
		$name_ok ? 'yes' : 'no'
	);

	$imported_anything = count( $first['imported'] ) + count( $first['updated'] ) > 0;
	$no_duplicate      = 1 === count( $made );

	if ( ! $imported_anything ) {
		// Refusing to conclude is the point. A run that imported nothing says nothing about
		// encoding or re-run safety, and reporting it as a finding would be an invention.
		$verdict = 'partial';
		$notes[] = 'Nothing imported on the first pass, so this run establishes nothing about encoding or re-run '
			. 'behaviour. The importer needs its arguments corrected before the question can be answered.';
	} elseif ( $no_duplicate && $name_ok && ! $has_dry && ! $has_map ) {
		$verdict = 'confirmed';
		$notes[] = 'Encoding and re-run by SKU are both sound, but there is no dry run and no saved mapping, so '
			. 'section 7 row 6 is right that the native importer does not meet MIG-01 to MIG-25. The gap is the '
			. 'operator safety net, not the parsing.';
	} elseif ( ! $no_duplicate ) {
		$verdict = 'confirmed';
		$notes[] = 'The second pass duplicated the SKU. A catalogue load would be one-shot with no safe correction '
			. 'path, which makes the Mizzey importer more necessary than section 7 row 6 states.';
	} else {
		$verdict = 'partial';
		$notes[] = 'Mixed result; read the counts above before concluding.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
	if ( $file && file_exists( $file ) ) {
		unlink( $file );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

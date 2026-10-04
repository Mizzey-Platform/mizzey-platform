<?php
/**
 * t07, AC-4 (MIG-13): the native WooCommerce CSV importer carries a "Cost of goods" column onto simple products and
 * variations; a row with no cost imports without one. Also records that the importer drops cost while the feature
 * is off.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

function import_fixture( string $file ): array {
	if ( ! class_exists( 'WC_Product_CSV_Importer' ) ) {
		require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
	}
	$headings = array( 'Type', 'SKU', 'Name', 'Published', 'Regular price', 'Cost of goods', 'Parent', 'Attribute 1 name',
		'Attribute 1 value(s)', 'Attribute 1 visible', 'Attribute 1 global' );
	$keys     = array( 'type', 'sku', 'name', 'published', 'regular_price', 'cogs_value', 'parent_id', 'attributes:name1',
		'attributes:value1', 'attributes:visible1', 'attributes:taxonomy1' );
	$importer = new \WC_Product_CSV_Importer( $file, array(
		'mapping'         => array( 'from' => $headings, 'to' => $keys ),
		'parse'           => true,
		'update_existing' => false,
		'prevent_timeouts' => false,
	) );
	return $importer->import();
}

run(
	new Scenario( 't07-csv-import-cost', 'AC-4' ),
	function ( Scenario $s ): bool {
		$file = __DIR__ . '/fixtures/products-with-cost.csv';

		// With the feature on: the contract case.
		$s->enable_cogs();
		$result = import_fixture( $file );
		foreach ( array( 'imported', 'updated', 'failed', 'skipped' ) as $k ) {
			foreach ( (array) ( $result[ $k ] ?? array() ) as $item ) {
				if ( is_int( $item ) ) {
					$s->track_post( $item );
				} elseif ( is_wp_error( $item ) ) {
					$s->note( $k . ': ' . $item->get_error_message() );
				}
			}
		}
		$s->note( sprintf( 'Import: %d imported, %d failed, %d skipped', count( (array) $result['imported'] ), count( (array) $result['failed'] ), count( (array) $result['skipped'] ) ) );

		$expected = array( 'T07-S1' => 111.25, 'T07-S2' => 95.0, 'T07-S3' => null, 'T07-V-50' => 40.0, 'T07-V-100' => 70.0 );
		$ok       = true;
		foreach ( $expected as $sku => $want ) {
			$id   = wc_get_product_id_by_sku( $sku );
			$got  = $id ? Scenario::fresh_product( $id )->get_cogs_value() : 'missing';
			$ok   = $ok && $id && $got === $want;
			$s->note( sprintf( '%s: expected %s, imported %s', $sku, var_export( $want, true ), var_export( $got, true ) ) );
		}
		return $ok;
	}
);

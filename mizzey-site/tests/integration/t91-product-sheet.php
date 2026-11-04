<?php
/**
 * t91: the importer reads Mizzey's product sheet (specs/051-product-sheet-import).
 *
 * The sample sheet the client received (`fixtures/catalogue/product-sheet.csv`) is read with the plugin's own mapping,
 * as the Developer runs the initial migration. Its SKUs, categories and brands are given this run's tag before the
 * load, so nothing is left in the store; every other cell is the sample's.
 *
 * AC-1: the plugin's mapping reads the sheet: every column is mapped, nothing is left over, and a validation pass plans
 *       every row (the simple product, two variable products and their five variations) with no rejection.
 * AC-2: any option_<name> column is an attribute <name>, without the mapping listing it: the sample's size, colour and
 *       capacity, and options the sample does not have (scent, pack size).
 * AC-3: an option column whose name cannot be read (empty, not in English letters, or the same option twice) stops the
 *       run, named; it is never dropped quietly.
 * AC-4: the load writes the sheet: name, description, price, sale price, cost, category path, brand, SEO fields and the
 *       hidden ASIN; the variable products with their variations, options and prices; the Arabic records.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\CatalogueLoad;
use MizzeySite\Catalogue\Import\Cli\CatalogueCommand;
use MizzeySite\Catalogue\Import\ExportReader;
use MizzeySite\Catalogue\Import\HeaderCheck;
use MizzeySite\Catalogue\Import\Mapping;
use MizzeySite\Catalogue\Import\RowInterpreter;
use MizzeySite\Catalogue\ProductFields;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't91-product-sheet', 'MIG-01, MIG-04, MIG-09, MIG-10: the importer reads Mizzey\'s product sheet' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T91' . wp_rand( 1000, 9999 );
		$s->enable_cogs(); // The sheet's cost is WooCommerce's cost of goods (MIG-13).
		$dir = trailingslashit( get_temp_dir() ) . strtolower( $tag );
		wp_mkdir_p( $dir );
		$keep = get_option( CatalogueLoad::RUN, null );
		$s->on_finish(
			static function () use ( $keep, $tag ): void {
				null === $keep ? delete_option( CatalogueLoad::RUN ) : update_option( CatalogueLoad::RUN, $keep, false );
				global $wpdb;
				$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( $ids as $id ) {
					wp_delete_post( (int) $id, true );
				}
				foreach ( array( 'product_cat', 'product_brand' ) as $taxonomy ) {
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $tag, 'fields' => 'ids' ) );
					foreach ( is_array( $terms ) ? $terms : array() as $term ) {
						foreach ( (array) get_term_children( (int) $term, $taxonomy ) as $child ) {
							wp_delete_term( (int) $child, $taxonomy );
						}
						wp_delete_term( (int) $term, $taxonomy );
					}
				}
			}
		);

		// ---- AC-1: the plugin's mapping reads the sheet -------------------------------------------------------------
		$sample  = CATALOGUE_FIXTURES . '/product-sheet.csv';
		$mapping = Mapping::load( CatalogueCommand::defaultMapping() );
		$read    = ExportReader::read( $sample, $mapping->source() );
		$sheet   = $mapping->forHeader( $read['header'] );
		$header  = HeaderCheck::check( $read['header'], $sheet );
		$check( 'csv' === $mapping->source()['format'] && array() === $header['failures'] && array() === $header['warnings'] && true === $header['complete'] && 8 === count( $read['rows'] ), 'AC-1 MIG-01, MIG-04 the plugin\'s mapping reads the sheet: every column mapped, none left over, eight rows (' . wp_json_encode( array( $header['failures'], $header['warnings'], count( $read['rows'] ) ) ) . ')' );
		$check( array( 'size' => 'option_size', 'colour' => 'option_colour', 'capacity' => 'option_capacity' ) === $sheet->attributeColumns() && array() === $mapping->attributeColumns(), 'AC-2 MIG-10 the sheet\'s options come from its option_ columns, none listed in the mapping (' . wp_json_encode( $sheet->attributeColumns() ) . ')' );

		// The sample with this run's SKUs, categories and brands, and its capacity column named in two words (the client
		// names the options); every other cell as the client received it.
		$text = (string) file_get_contents( $sample );
		$text = str_replace( array( 'MZ-', 'Kitchen >', 'Clothing >', 'Mizzey Home', 'Mizzey Basics', 'option_capacity' ), array( "{$tag}-MZ-", "{$tag} Kitchen >", "{$tag} Clothing >", "{$tag} Home", "{$tag} Basics", 'option_bottle_capacity' ), $text );
		file_put_contents( "{$dir}/sheet.csv", $text );
		$out = "{$dir}/validated";
		$run = catalogue_command( 'validate ' . escapeshellarg( "{$dir}/sheet.csv" ) . ' --mapping=' . escapeshellarg( CatalogueCommand::defaultMapping() ) . ' --out=' . escapeshellarg( $out ) );
		$lines    = catalogue_csv( $out . '/validation-report.csv' );
		$outcomes = array_count_values( array_column( $lines, 'outcome' ) );
		$check( 0 === $run['code'] && 8 === count( $lines ) && 8 === ( $outcomes['create'] ?? 0 ), 'AC-1 MIG-06 the validation pass plans every row for creation, none rejected (exit ' . $run['code'] . ', ' . wp_json_encode( $outcomes ) . ')' );
		$client = catalogue_csv( $out . '/client-issues.csv' );
		$check( array() === array_filter( $client, static fn ( $r ) => 'W3' === ( $r['issue_code'] ?? '' ) ), 'AC-1 MIG-19 the client issue list asks no variation row for an Arabic name: a variation takes the name of its product (' . wp_json_encode( array_count_values( array_column( $client, 'issue_code' ) ) ) . ')' );

		// ---- AC-2: any option, without changing the mapping -----------------------------------------------------------
		$more   = array_merge( $read['header'], array( 'option_scent', 'option_pack_size' ) );
		$wider  = $mapping->forHeader( $more );
		$cells  = array_merge( $read['rows'][2]['cells'], array( 'Lavender', 'Pack of 3' ) ); // A variation row of the sample.
		$row    = RowInterpreter::interpret( array( 'row' => 4, 'cells' => $cells ), $more, $wider );
		$wcheck = HeaderCheck::check( $more, $wider );
		$check( true === $wcheck['complete'] && 'Lavender' === ( $row['attributes']['scent'] ?? '' ) && 'Pack of 3' === ( $row['attributes']['pack-size'] ?? '' ) && 'M' === ( $row['attributes']['size'] ?? '' ), 'AC-2 an option the sample does not have (scent, pack size) is read as its own attribute, the mapping unchanged (' . wp_json_encode( $row['attributes'] ) . ')' );

		// ---- AC-3: an option column that cannot be read stops the run ----------------------------------------------
		$codes = array();
		foreach ( array( 'option_', 'option_اللون', 'option_Size' ) as $bad ) {
			$h            = array_merge( $read['header'], array( $bad ) );
			$result       = HeaderCheck::check( $h, $mapping->forHeader( $h ) );
			$codes[ $bad ] = implode( ',', array_column( $result['failures'], 'code' ) ) . ( str_contains( implode( ' ', array_column( $result['failures'], 'detail' ) ), $bad ) ? ' named' : '' );
		}
		$check( array( 'option_' => 'F4 named', 'option_اللون' => 'F4 named', 'option_Size' => 'F4 named' ) === $codes, 'AC-3 an option column with no name, a name not in English letters, or an option given twice stops the run, named (' . wp_json_encode( $codes, JSON_UNESCAPED_UNICODE ) . ')' );
		file_put_contents( "{$dir}/bad.csv", str_replace( 'option_bottle_capacity', 'option_سعة', $text ) );
		$refused = catalogue_command( 'validate ' . escapeshellarg( "{$dir}/bad.csv" ) . ' --mapping=' . escapeshellarg( CatalogueCommand::defaultMapping() ) . ' --out=' . escapeshellarg( "{$dir}/refused" ) );
		$check( 0 !== $refused['code'] && str_contains( $refused['out'], 'option_سعة' ), 'AC-3 the command refuses such a sheet and names the column (exit ' . $refused['code'] . ')' );

		// ---- AC-4: the load ---------------------------------------------------------------------------------------
		// Images are not downloaded here: the sample's addresses are placeholders, and the image download is t86's.
		$state = CatalogueLoad::start( "{$dir}/sheet.csv", Mapping::load( CatalogueCommand::defaultMapping() ), "{$dir}/run", false );
		$check( 8 === (int) $state['total'] && 0 === (int) ( $state['counts']['reject'] ?? 0 ), 'AC-4 the load plans the eight rows (' . wp_json_encode( $state['counts'] ?? null ) . ')' );
		$bin = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		for ( $round = 0; $round < 12 && in_array( CatalogueLoad::state()['status'] ?? '', array( 'sources', 'translations', 'images' ), true ); $round++ ) {
			exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' action-scheduler run --group=' . CatalogueLoad::GROUP . ' 2>&1', $ignored, $code );
			wp_cache_flush();
		}
		catalogue_register_attributes();
		$state = CatalogueLoad::state();
		$check( 'done' === ( $state['status'] ?? '' ) && array() === (array) ( $state['problems'] ?? array() ), 'AC-4 the load ran in the background to the end, with no problem (' . ( $state['status'] ?? '?' ) . ', ' . wp_json_encode( $state['problems'] ?? null ) . ')' );

		do_action( 'wpml_switch_language', 'en' );
		$en    = static fn ( string $sku, string $type = 'product' ): ?\WC_Product => wc_get_product( (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( "{$tag}-{$sku}" ), $type, true, 'en' ) ) ?: null;
		$path  = static function ( ?\WC_Product $p ): string {
			$leaf = $p ? wp_get_post_terms( $p->get_id(), 'product_cat' ) : array();
			$names = array();
			for ( $t = $leaf[0] ?? null; $t instanceof \WP_Term; $t = $t->parent ? get_term( $t->parent, 'product_cat' ) : null ) {
				array_unshift( $names, $t->name );
			}
			return implode( ' > ', $names );
		};
		$tray  = $en( 'MZ-1001' );
		$brand = $tray && taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $tray->get_id(), 'product_brand', array( 'fields' => 'names' ) ) : array();
		$check( $tray instanceof \WC_Product_Simple && 'Bamboo Drawer Organiser' === $tray->get_name() && str_contains( $tray->get_description(), 'Six sections.' ) && 349.0 === (float) $tray->get_regular_price() && 299.0 === (float) $tray->get_sale_price() && 180.0 === (float) $tray->get_cogs_value(), 'AC-4 MIG-13 the simple product carries its name, description, price, sale price and cost (' . ( $tray ? wp_json_encode( array( $tray->get_name(), $tray->get_regular_price(), $tray->get_sale_price(), $tray->get_cogs_value() ) ) : 'none' ) . ')' );
		$check( "{$tag} Kitchen > Storage" === $path( $tray ) && ( ! taxonomy_exists( 'product_brand' ) || array( "{$tag} Home" ) === $brand ), 'AC-4 MIG-11, MIG-12 the category path and the brand (' . $path( $tray ) . ', ' . implode( ',', $brand ) . ')' );
		$check( $tray && 'Bamboo Drawer Organiser | Mizzey' === $tray->get_meta( ProductFields::SEO_TITLE ) && str_contains( (string) $tray->get_meta( ProductFields::SEO_DESC ), 'Delivered across Egypt.' ) && 'B0EXAMPLE1' === $tray->get_meta( '_mizzey_source_asin' ), 'AC-4 MIG-15 the SEO title and description, and the ASIN kept hidden' );

		$shirt  = $en( 'MZ-2001' );
		// Each option is a store attribute (specs/053): its name, and its values as terms, read as stored.
		$label  = static fn ( \WC_Product_Attribute $a ): string => wc_attribute_label( $a->get_name() ) . ': ' . implode( '/', array_map( static fn ( $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", (int) $id ) ), $a->get_options() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$attrs  = $shirt instanceof \WC_Product_Variable ? $shirt->get_attributes() : array();
		$names  = array_map( $label, $attrs );
		$white  = $en( 'MZ-2001-M-WHT', 'product_variation' );
		$check( $shirt instanceof \WC_Product_Variable && 3 === count( $shirt->get_children() ) && array( 'pa_size' => 'Size: L/M', 'pa_colour' => 'Colour: Black/White' ) === $names && $white && 'M' === $white->get_attribute( 'pa_size' ) && 'White' === $white->get_attribute( 'pa_colour' ) && 259.0 === (float) $white->get_regular_price() && 229.0 === (float) $white->get_sale_price() && 125.0 === (float) $white->get_cogs_value(), 'AC-4 MIG-09, MIG-10 the variable product has its three variations by size and colour, each with its price, sale price and cost (' . wp_json_encode( array( $names, $white ? array( $white->get_attribute( 'pa_size' ), $white->get_attribute( 'pa_colour' ), $white->get_regular_price() ) : null ) ) . ')' );
		$bottle = $en( 'MZ-3001' );
		$battrs = $bottle instanceof \WC_Product_Variable ? $bottle->get_attributes() : array();
		$cap    = isset( $battrs['pa_bottle-capacity'] ) ? $label( $battrs['pa_bottle-capacity'] ) : '';
		$small  = $en( 'MZ-3001-500', 'product_variation' );
		$check( $bottle instanceof \WC_Product_Variable && 2 === count( $bottle->get_children() ) && array( 'pa_bottle-capacity' ) === array_keys( $battrs ) && 'Bottle capacity: 500 ml/750 ml' === $cap && $small && '500 ml' === $small->get_attribute( 'pa_bottle-capacity' ), 'AC-4 MIG-10 a product whose only option is its capacity, named in two words, has that option only, never an assumed size or colour (' . wp_json_encode( array( array_keys( $battrs ), $cap ) ) . ')' );

		$ar = static fn ( ?\WC_Product $p ): string => $p ? (string) get_post_field( 'post_title', (int) apply_filters( 'wpml_object_id', $p->get_id(), 'product', false, 'ar' ) ) : '';
		$check( 'منظم أدراج من البامبو' === $ar( $tray ) && 'تيشيرت قطن للمنزل' === $ar( $shirt ) && 'زجاجة مياه معزولة' === $ar( $bottle ), 'AC-4 MIG-02 each product has its Arabic record, with the sheet\'s Arabic name (' . $ar( $tray ) . ')' );

		return $ok;
	}
);

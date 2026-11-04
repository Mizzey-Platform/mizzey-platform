<?php
/**
 * t93: the product sheet carries its Arabic, and its Google Sheets tabs (specs/053-sheet-arabic-tabs).
 *
 * The revised sample sheet (`fixtures/catalogue/product-sheet-v2.csv`) and a workbook made here as Google Sheets
 * downloads one (`.xlsx`, a tab per category), with this run's SKUs, categories and brands.
 *
 * AC-1: a workbook is read as one sheet: every tab with the header, in order, each row with its tab and its line; a
 *       tab whose columns are in another order is read by name; an empty tab is skipped; numbers read as typed; the
 *       reports name each row's tab.
 * AC-2: a tab whose columns differ stops the read, naming the tab and the columns.
 * AC-3: the sheet with its Arabic columns is read whole, and the first sheet, without them, still is.
 * AC-4: the load gives each category level and the brand their Arabic names, and the Arabic product shows them; a
 *       category already translated in the store keeps its translation.
 * AC-5: the Arabic of the options: their names and values on the Arabic product, and each Arabic variation's values.
 * AC-6: an Arabic category with other levels than the English is a client issue (W11); an Arabic option column without
 *       its option's column stops the run (F4).
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\CatalogueLoad;
use MizzeySite\Catalogue\Import\Cli\CatalogueCommand;
use MizzeySite\Catalogue\Import\ExportReader;
use MizzeySite\Catalogue\Import\HeaderCheck;
use MizzeySite\Catalogue\Import\Mapping;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

/**
 * A workbook as Google Sheets downloads one: a tab per name, each a list of rows (the first its header). A number is
 * written as a number cell, everything else as an inline string; the first tab's text goes through shared strings.
 *
 * @param array<string, list<list<string|int|float>>> $tabs
 */
function t93_xlsx( string $path, array $tabs ): void {
	$esc    = static fn ( string $s ): string => htmlspecialchars( $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	$col    = static function ( int $i ): string {
		$name = '';
		for ( $n = $i + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$name = chr( 65 + ( $n - 1 ) % 26 ) . $name;
		}
		return $name;
	};
	$zip = new \ZipArchive();
	$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>' );
	$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
	$sheets  = '';
	$rels    = '';
	$shared  = array();
	$n       = 0;
	foreach ( $tabs as $name => $rows ) {
		++$n;
		$sheets .= '<sheet name="' . $esc( (string) $name ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
		$rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		$data    = '';
		foreach ( $rows as $r => $cells ) {
			$data .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( $cells as $c => $value ) {
				$ref = $col( $c ) . ( $r + 1 );
				if ( '' === $value ) {
					continue;
				}
				if ( is_int( $value ) || is_float( $value ) ) {
					$data .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
				} elseif ( 1 === $n ) {
					$shared[] = (string) $value;
					$data    .= '<c r="' . $ref . '" t="s"><v>' . ( count( $shared ) - 1 ) . '</v></c>';
				} else {
					$data .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $esc( (string) $value ) . '</t></is></c>';
				}
			}
			$data .= '</row>';
		}
		$zip->addFromString( "xl/worksheets/sheet{$n}.xml", '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $data . '</sheetData></worksheet>' );
	}
	$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $sheets . '</sheets></workbook>' );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>' );
	$sst = '';
	foreach ( $shared as $text ) {
		$sst .= '<si><t>' . $esc( $text ) . '</t></si>';
	}
	$zip->addFromString( 'xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $sst . '</sst>' );
	$zip->close();
}

run(
	new Scenario( 't93-sheet-arabic-tabs', 'MIG-01, MIG-10, MIG-11, MIG-12, MIG-19: the product sheet carries its Arabic and its tabs' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T93' . wp_rand( 1000, 9999 );
		$s->enable_cogs();
		$dir = trailingslashit( get_temp_dir() ) . strtolower( $tag );
		wp_mkdir_p( $dir );
		$keep = get_option( CatalogueLoad::RUN, null );
		$s->on_finish(
			static function () use ( $keep, $tag ): void {
				null === $keep ? delete_option( CatalogueLoad::RUN ) : update_option( CatalogueLoad::RUN, $keep, false );
				global $wpdb;
				foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) as $id ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					foreach ( array( (int) $id, (int) apply_filters( 'wpml_object_id', (int) $id, get_post_type( (int) $id ), false, 'ar' ) ) as $post ) {
						if ( $post > 0 ) {
							wp_delete_post( $post, true );
						}
					}
				}
				foreach ( array( 'product_cat', 'product_brand' ) as $taxonomy ) {
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					foreach ( array( 'en', 'ar' ) as $lang ) {
						do_action( 'wpml_switch_language', $lang );
						$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'search' => $tag, 'fields' => 'ids' ) );
						foreach ( is_array( $terms ) ? $terms : array() as $term ) {
							foreach ( (array) get_term_children( (int) $term, $taxonomy ) as $child ) {
								wp_delete_term( (int) $child, $taxonomy );
							}
							wp_delete_term( (int) $term, $taxonomy );
						}
					}
					do_action( 'wpml_switch_language', 'en' );
				}
			}
		);
		$mapping = Mapping::load( CatalogueCommand::defaultMapping() );

		// The revised sheet with this run's SKUs, categories and brands (each Arabic name tagged too).
		$text = (string) file_get_contents( CATALOGUE_FIXTURES . '/product-sheet-v2.csv' );
		$text = str_replace(
			array( 'MZ-', 'Kitchen >', 'Clothing >', 'Mizzey Home', 'Mizzey Basics', 'المطبخ >', 'الملابس >', 'ميزي هوم', 'ميزي بيسكس' ),
			array( "{$tag}-MZ-", "{$tag} Kitchen >", "{$tag} Clothing >", "{$tag} Home", "{$tag} Basics", "{$tag} المطبخ >", "{$tag} الملابس >", "{$tag} ميزي هوم", "{$tag} ميزي بيسكس" ),
			$text
		);
		file_put_contents( "{$dir}/sheet.csv", $text );

		// ---- AC-3: the sheet with its Arabic, and the first sheet without ------------------------------------------
		$v2      = ExportReader::read( "{$dir}/sheet.csv", $mapping->source() );
		$v2map   = $mapping->forHeader( $v2['header'] );
		$v2check = HeaderCheck::check( $v2['header'], $v2map );
		$v1      = ExportReader::read( CATALOGUE_FIXTURES . '/product-sheet.csv', $mapping->source() );
		$v1check = HeaderCheck::check( $v1['header'], $mapping->forHeader( $v1['header'] ) );
		$check( true === $v2check['complete'] && array() === $v2check['warnings'] && array( 'size' => 'option_size_ar', 'colour' => 'option_colour_ar', 'capacity' => 'option_capacity_ar' ) === $v2map->arabicAttributeColumns() && true === $v1check['complete'] && array() === $v1check['failures'], 'AC-3 the sheet with its Arabic columns is read whole, each option with its Arabic column; the first sheet, without them, still is (' . wp_json_encode( array( $v2check['failures'], $v1check['failures'] ) ) . ')' );

		// ---- AC-1: the workbook --------------------------------------------------------------------------------------
		$rows   = array_map( 'str_getcsv', array_values( array_filter( preg_split( '/\r\n|\n/', preg_replace( '/^\xEF\xBB\xBF/', '', $text ) ), 'strlen' ) ) );
		$header = array_shift( $rows );
		$num    = static function ( array $row ) use ( $header ): array {
			foreach ( array( 'price', 'sale_price', 'cost' ) as $money ) {
				$i = array_search( $money, $header, true );
				if ( false !== $i && '' !== $row[ $i ] ) {
					$row[ $i ] = (float) $row[ $i ]; // Typed as a number, as a person types a price.
				}
			}
			return $row;
		};
		$kitchen  = array_values( array_filter( $rows, static fn ( $r ) => str_contains( $r[1], 'MZ-1001' ) || str_contains( $r[1], 'MZ-3001' ) ) );
		$clothing = array_values( array_filter( $rows, static fn ( $r ) => str_contains( $r[1], 'MZ-2001' ) ) );
		$kitchen  = array_map( $num, $kitchen );
		$kitchen[0][ array_search( 'cost', $header, true ) ] = 180.00000000000003; // A number as a sheet can store it.
		// The clothing tab has its columns in another order: they are read by name.
		$reorder  = array_reverse( range( 0, count( $header ) - 1 ) );
		$pick     = static fn ( array $r ): array => array_map( static fn ( $i ) => $r[ $i ] ?? '', $reorder );
		$tabs     = array(
			'Kitchen'  => array_merge( array( $header ), $kitchen ),
			'Clothing' => array_merge( array( $pick( $header ) ), array_map( $pick, array_map( $num, $clothing ) ) ),
			'Notes'    => array(),
		);
		t93_xlsx( "{$dir}/sheet.xlsx", $tabs );
		$book    = ExportReader::read( "{$dir}/sheet.xlsx", $mapping->source() );
		$tabsOf  = array_count_values( array_column( $book['rows'], 'tab' ) );
		$first   = $book['rows'][0] ?? array( 'cells' => array() );
		$costCol = array_search( 'cost', $header, true );
		$priceCol = array_search( 'price', $header, true );
		$clothRow = array_values( array_filter( $book['rows'], static fn ( $r ) => 'Clothing' === $r['tab'] ) )[0] ?? array( 'cells' => array(), 'row' => 0 );
		$check( $header === $book['header'] && 8 === count( $book['rows'] ) && array( 'Kitchen' => 4, 'Clothing' => 4 ) === $tabsOf && 2 === ( $first['row'] ?? 0 ) && 2 === $clothRow['row'] && "{$tag}-MZ-2001" === ( $clothRow['cells'][1] ?? '' ), 'AC-1 MIG-01 a workbook is read as one sheet: both tabs, in order, each row with its tab and line, the reordered tab read by name, the empty tab skipped (' . wp_json_encode( array( $tabsOf, $clothRow['row'], $clothRow['cells'][1] ?? null ) ) . ')' );
		$check( '349' === ( $first['cells'][ $priceCol ] ?? '' ) && '180' === ( $first['cells'][ $costCol ] ?? '' ), 'AC-1 numbers read as typed: 349.00 as 349, and 180.00000000000003 as 180 (' . ( $first['cells'][ $costCol ] ?? '' ) . ')' );
		$out = "{$dir}/validated";
		$run = catalogue_command( 'validate ' . escapeshellarg( "{$dir}/sheet.xlsx" ) . ' --mapping=' . escapeshellarg( CatalogueCommand::defaultMapping() ) . ' --out=' . escapeshellarg( $out ) );
		$lines = catalogue_csv( $out . '/validation-report.csv' );
		$check( 0 === $run['code'] && 8 === count( array_filter( $lines, static fn ( $l ) => 'create' === $l['outcome'] ) ) && array( 'Kitchen' => 4, 'Clothing' => 4 ) === array_count_values( array_column( $lines, 'tab' ) ), 'AC-1 MIG-19 the validation of the workbook plans the eight rows, and the report names each row\'s tab (exit ' . $run['code'] . ')' );

		// ---- AC-2: a tab with other columns --------------------------------------------------------------------------
		$odd = $tabs;
		$odd['Clothing'][0][3] = 'title';
		t93_xlsx( "{$dir}/odd.xlsx", $odd );
		try {
			ExportReader::read( "{$dir}/odd.xlsx", $mapping->source() );
			$message = '';
		} catch ( \RuntimeException $e ) {
			$message = $e->getMessage();
		}
		$check( str_contains( $message, '"Clothing"' ) && str_contains( $message, 'title' ), 'AC-2 a tab whose columns differ stops the read, naming the tab and the column (' . $message . ')' );

		// ---- AC-6: W11 and F4 ------------------------------------------------------------------------------------------
		file_put_contents( "{$dir}/levels.csv", str_replace( "{$tag} المطبخ > التخزين", 'التخزين', $text ) );
		$lv     = catalogue_command( 'validate ' . escapeshellarg( "{$dir}/levels.csv" ) . ' --mapping=' . escapeshellarg( CatalogueCommand::defaultMapping() ) . ' --out=' . escapeshellarg( "{$dir}/levels" ) );
		$w11    = array_filter( catalogue_csv( "{$dir}/levels/client-issues.csv" ), static fn ( $i ) => 'W11' === $i['issue_code'] && "{$tag}-MZ-1001" === $i['sku'] && 'category_ar' === $i['field'] );
		$lonely = array_merge( $v2['header'], array( 'option_scent_ar' ) );
		$f4     = HeaderCheck::check( $lonely, $mapping->forHeader( $lonely ) );
		$check( 0 === $lv['code'] && 1 === count( $w11 ) && in_array( 'F4', array_column( $f4['failures'], 'code' ), true ) && str_contains( implode( ' ', array_column( $f4['failures'], 'detail' ) ), 'option_scent_ar' ), 'AC-6 an Arabic category with other levels is a client issue (W11) on its row; an Arabic option column without its option stops the run (F4)' );

		// ---- AC-4 and AC-5: the load --------------------------------------------------------------------------------
		// A category already translated in the store keeps its translation.
		do_action( 'wpml_switch_language', 'en' );
		$kept    = wp_insert_term( "{$tag} Kitchen", 'product_cat' );
		$kept_id = is_wp_error( $kept ) ? 0 : (int) $kept['term_id'];
		$trid    = apply_filters( 'wpml_element_trid', null, (int) get_term( $kept_id, 'product_cat' )->term_taxonomy_id, 'tax_product_cat' );
		do_action( 'wpml_switch_language', 'ar' );
		$old    = wp_insert_term( "{$tag} مطبخ قديم", 'product_cat' );
		do_action( 'wpml_switch_language', 'en' );
		$old_id = is_wp_error( $old ) ? 0 : (int) $old['term_id'];
		do_action( 'wpml_set_element_language_details', array( 'element_id' => (int) get_term( $old_id, 'product_cat' )->term_taxonomy_id, 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );

		$state = CatalogueLoad::start( "{$dir}/sheet.xlsx", Mapping::load( CatalogueCommand::defaultMapping() ), "{$dir}/run", false );
		$bin   = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		for ( $round = 0; $round < 12 && in_array( CatalogueLoad::state()['status'] ?? '', array( 'sources', 'translations', 'images' ), true ); $round++ ) {
			exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' action-scheduler run --group=' . CatalogueLoad::GROUP . ' 2>&1', $ignored, $code );
			wp_cache_flush();
		}
		$state = CatalogueLoad::state();
		$check( 'done' === ( $state['status'] ?? '' ) && array() === (array) ( $state['problems'] ?? array() ) && array() === array_values( array_filter( (array) ( $state['warnings'] ?? array() ), static fn ( $w ) => str_contains( (string) $w['warning'], 'has no ar name' ) ) ), 'AC-4 the workbook loads to the end with no problem, and no category or brand is left without its Arabic name (' . ( $state['status'] ?? '?' ) . ', ' . wp_json_encode( array( $state['problems'] ?? null, array_values( (array) ( $state['warnings'] ?? array() ) ) ), JSON_UNESCAPED_UNICODE ) . ')' );

		$en  = static fn ( string $sku, string $type = 'product' ): int => (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( "{$tag}-{$sku}" ), $type, true, 'en' );
		$ar  = static fn ( int $id, string $type = 'product' ): int => (int) apply_filters( 'wpml_object_id', $id, $type, false, 'ar' );
		$tray    = $en( 'MZ-1001' );
		$tray_ar = $ar( $tray );
		do_action( 'wpml_switch_language', 'ar' );
		$leaf    = $tray_ar > 0 ? wp_get_post_terms( $tray_ar, 'product_cat' ) : array();
		$path    = array();
		for ( $t = $leaf[0] ?? null; $t instanceof \WP_Term; $t = $t->parent ? get_term( $t->parent, 'product_cat' ) : null ) {
			array_unshift( $path, $t->name );
		}
		$brand_ar = $tray_ar > 0 && taxonomy_exists( 'product_brand' ) ? wp_get_post_terms( $tray_ar, 'product_brand', array( 'fields' => 'names' ) ) : array();
		do_action( 'wpml_switch_language', 'en' );
		$check( array( "{$tag} مطبخ قديم", 'التخزين' ) === $path && ( ! taxonomy_exists( 'product_brand' ) || array( "{$tag} ميزي هوم" ) === $brand_ar ), 'AC-4 MIG-11, MIG-12 the Arabic product shows the category in Arabic at each level and the brand in Arabic; the category already translated keeps its translation (' . implode( ' > ', $path ) . ', ' . implode( ',', $brand_ar ) . ')' );

		$shirt    = $en( 'MZ-2001' );
		$shirt_ar = $ar( $shirt );
		$attrs_ar = (array) get_post_meta( $shirt_ar, '_product_attributes', true );
		$labels   = (array) ( get_post_meta( $shirt_ar, 'attr_label_translations', true )['ar'] ?? array() );
		$white    = $en( 'MZ-2001-M-WHT', 'product_variation' );
		$white_ar = $ar( $white, 'product_variation' );
		$bottle_ar = $ar( $en( 'MZ-3001' ) );
		$small_ar  = $ar( $en( 'MZ-3001-500', 'product_variation' ), 'product_variation' );
		$cap_label = (string) ( ( (array) ( ( (array) get_post_meta( $bottle_ar, 'attr_label_translations', true ) )['ar'] ?? array() ) )['capacity'] ?? '' );
		$check( 'أسود | أبيض' === ( $attrs_ar['colour']['value'] ?? '' ) && 'M | L' === ( $attrs_ar['size']['value'] ?? '' ) && 'اللون' === ( $labels['colour'] ?? '' ) && 'المقاس' === ( $labels['size'] ?? '' ), 'AC-5 MIG-10 the Arabic product has its options\' Arabic names and values, a value with no Arabic kept as written (' . wp_json_encode( array( $attrs_ar['colour']['value'] ?? null, $attrs_ar['size']['value'] ?? null, $labels ), JSON_UNESCAPED_UNICODE ) . ')' );
		$check( $white_ar > 0 && 'أبيض' === get_post_meta( $white_ar, 'attribute_colour', true ) && 'M' === get_post_meta( $white_ar, 'attribute_size', true ) && 'White' === get_post_meta( $white, 'attribute_colour', true ) && '500 مل' === get_post_meta( $small_ar, 'attribute_capacity', true ) && 'السعة' === $cap_label, 'AC-5 each Arabic variation carries its values in Arabic, the English one keeps its own (' . wp_json_encode( array( get_post_meta( $white_ar, 'attribute_colour', true ), get_post_meta( $small_ar, 'attribute_capacity', true ) ), JSON_UNESCAPED_UNICODE ) . ')' );

		return $ok;
	}
);

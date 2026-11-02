<?php
/**
 * t86: the initial catalogue load (#247, #295, #296, #297). See specs/046-catalogue-load.
 *
 * The export and its mapping are written for this run, with SKUs of its own. The load is planned here and runs where
 * it runs on a server: in Action Scheduler, in other processes (WP-CLI), the English records before the Arabic ones.
 *
 * AC-1: nothing is written until the load runs; a row the rules reject is not written.
 * AC-2: the English records carry name, description, price, sale price, cost, category path, brand, SEO fields.
 * AC-3: a variable product with its variations and attributes.
 * AC-4: each Arabic record is its own (title, description), in the product's translation group; the Arabic
 *       variations keep their attributes; a row with no Arabic text has no Arabic record.
 * AC-5: images are downloaded once, the first as the product image, with the title as alt text, only when the
 *       rights are confirmed.
 * AC-6: the catalogue exports through WooCommerce's CSV exporter.
 * The audit of 2 November 2026 (B-01 to B-15, A-273-1): an update run's price reaches the Arabic record and the Arabic
 * order; a size added to a translated product reaches the Arabic product; a category with no Arabic name is warned
 * about; a refused or eleventh image address is reported; an Arabic name with "&" reads back; the reports and an Import
 * Run record are kept per run; a run directory under the server's document root is refused.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\CatalogueLoad;
use MizzeySite\Catalogue\Import\ImportRunLog;
use MizzeySite\Catalogue\Import\Mapping;
use MizzeySite\Catalogue\Import\ReportWriter;
use MizzeySite\Catalogue\ProductFields;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't86-catalogue-load', 'MIG-02 to MIG-25, SSC-21: the initial catalogue load' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T86' . wp_rand( 1000, 9999 );
		$s->enable_cogs(); // The cost is WooCommerce's cost of goods (MIG-13).
		$dir = trailingslashit( get_temp_dir() ) . strtolower( $tag );
		wp_mkdir_p( $dir );
		$keep = get_option( CatalogueLoad::RUN, null );
		$s->on_finish(
			static function () use ( $keep, $tag ): void {
				null === $keep ? delete_option( CatalogueLoad::RUN ) : update_option( CatalogueLoad::RUN, $keep, false );
				global $wpdb;
				$like = $wpdb->esc_like( $tag ) . '%';
				$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", CatalogueLoad::SOURCE_META, '%' . $wpdb->esc_like( $tag ) ) ) as $media ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					wp_delete_attachment( (int) $media, true );
				}
				foreach ( $ids as $id ) {
					foreach ( array_merge( array( (int) $id ), (array) get_children( array( 'post_parent' => (int) $id, 'post_type' => 'attachment', 'fields' => 'ids' ) ) ) as $post ) {
						wp_delete_post( (int) $post, true );
					}
				}
			}
		);

		// The export, as the client's file would carry it, and its mapping.
		// Addresses of this run only, so no image stored by an earlier run is reused.
		$img1 = home_url( '/wp-includes/images/w-logo-blue.png?' . $tag );
		$img2 = home_url( '/wp-includes/images/wpspin-2x.gif?' . $tag );
		// Eleven addresses after one that is refused: the refused one and the eleventh are reported (B-09). The refused
		// one is a well-formed address on a private network, which only WordPress's own check refuses.
		$many = array( 'http://10.255.255.1/refused.jpg' );
		for ( $n = 1; $n <= 11; $n++ ) {
			$many[] = home_url( "/wp-includes/images/w-logo-blue.png?n{$n}-{$tag}" );
		}
		$cols = array( 'seller-sku', 'item-name', 'item-name-ar', 'item-description', 'item-description-ar', 'price', 'sale-price', 'cost', 'parent-child', 'parent-sku', 'size-name', 'category', 'brand', 'main-image', 'other-image', 'seo-title', 'seo-description' );
		$rows = array(
			array( "{$tag}-ROSE", 'Rose Water Toner 200 ml', "\u{FEFF}تونر ماء الورد ٢٠٠ مل", 'A gentle toner.', 'تونر لطيف للبشرة.', '250.00', '199.00', '120.00', '', '', '', 'Beauty > Skin care > Toners', "{$tag} Labs", $img1, $img2, 'Rose water toner', 'Toner with rose water.' ),
			array( "{$tag}-MASK", 'Clay Mask', 'قناع الطين', 'A clay mask.', 'قناع من الطين.', '', '', '', 'parent', '', '', 'Beauty > Masks', "{$tag} Labs", '', '', '', '' ),
			array( "{$tag}-MASK-S", '', '', '', '', '150.00', '', '60.00', 'child', "{$tag}-MASK", 'Small', '', '', '', '', '', '' ),
			array( "{$tag}-MASK-L", '', '', '', '', '240.00', '', '95.00', 'child', "{$tag}-MASK", 'Large', '', '', '', '', '', '' ),
			array( "{$tag}-PLAIN", 'Cotton Pads', '', 'Soft pads.', '', '99.00', '', '', '', '', '', 'Beauty > Tools', '', '', '', '', '' ),
			array( "{$tag}-BAD", 'Bad Price', 'سعر خاطئ', '', '', '12,50', '', '', '', '', '', '', '', '', '', '', '' ),
			array( "{$tag}-AMP", 'Soap & Oil', 'صابون & زيت', 'Soap.', 'صابون.', '50.00', '', '', '', '', '', '', '', $many[0], implode( '|', array_slice( $many, 1 ) ), '', '' ),
		);
		$tsv = implode( "\t", $cols ) . "\n";
		foreach ( $rows as $r ) {
			$tsv .= implode( "\t", $r ) . "\n";
		}
		file_put_contents( "{$dir}/export.tsv", $tsv );
		$map = array( 'seller-sku' => 'sku', 'item-name' => 'name', 'item-name-ar' => 'name_ar', 'item-description' => 'description', 'item-description-ar' => 'description_ar', 'price' => 'regular_price', 'sale-price' => 'sale_price', 'cost' => 'cost', 'parent-child' => 'row_type', 'parent-sku' => 'parent_sku', 'size-name' => 'attribute_size', 'category' => 'category', 'brand' => 'brand', 'main-image' => 'images', 'other-image' => 'images', 'seo-title' => 'seo_title', 'seo-description' => 'seo_description' );
		file_put_contents( "{$dir}/mapping.json", (string) wp_json_encode( array( 'version' => 't86', 'rules_version' => 1, 'source' => array( 'format' => 'tsv', 'encoding' => 'UTF-8' ), 'columns' => array_map( static fn ( $to ) => array( 'to' => $to ), $map ), 'row_type' => array( 'values' => array( '' => 'simple', 'parent' => 'variable', 'child' => 'variation' ) ) ) ) );
		$mapping = Mapping::load( "{$dir}/mapping.json" );

		// ---- AC-1: planned, nothing written yet -------------------------------------------------------------------
		// A run directory under the server's document root is refused too, when the root sits above WordPress (B-14).
		$root = $_SERVER['DOCUMENT_ROOT'] ?? null;
		$_SERVER['DOCUMENT_ROOT'] = wp_slash( $dir ); // As WordPress keeps it: slashed.
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/served", true );
			$under_root = false;
		} catch ( \RuntimeException $e ) {
			$under_root = str_contains( $e->getMessage(), 'outside the website folder' );
		}
		if ( null === $root ) {
			unset( $_SERVER['DOCUMENT_ROOT'] );
		} else {
			$_SERVER['DOCUMENT_ROOT'] = $root;
		}
		$check( $under_root && ! file_exists( "{$dir}/served/plan.json" ), 'AC-1 B-14 a run directory under the server\'s document root is refused' );
		@rmdir( "{$dir}/served" ); // phpcs:ignore
		// Under WP-CLI the document root is WordPress's own folder. With WordPress in a subfolder of the site (its address
		// ends in that folder's name, as /wp does), the folder above it is the one the domain serves: refused too, and
		// nothing is created there.
		$sub     = basename( untrailingslashit( ABSPATH ) );
		$address = static fn (): string => 'http://example.test/' . $sub;
		add_filter( 'pre_option_siteurl', $address );
		$above = dirname( untrailingslashit( ABSPATH ) ) . "/{$tag}-runs/inner";
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, $above, true );
			$in_site = false;
		} catch ( \RuntimeException $e ) {
			$in_site = str_contains( $e->getMessage(), 'outside the website folder' );
		}
		remove_filter( 'pre_option_siteurl', $address );
		$check( $in_site && ! file_exists( dirname( $above ) ), 'AC-1 B-14 with WordPress in a subfolder of the site, a run directory beside it in the site\'s folder is refused under WP-CLI, and not created' );
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, wp_upload_dir()['basedir'] . "/{$tag}-run", true );
			$refused = false;
		} catch ( \RuntimeException $e ) {
			$refused = str_contains( $e->getMessage(), 'outside the website folder' );
		}
		$check( $refused && ! file_exists( wp_upload_dir()['basedir'] . "/{$tag}-run/plan.json" ), 'AC-1 a run directory inside the website is refused: the plan holds costs' );
		@rmdir( wp_upload_dir()['basedir'] . "/{$tag}-run" ); // phpcs:ignore
		$state = CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/run", true );
		$check( 6 === (int) $state['total'] && 1 === (int) $state['counts']['reject'] && 0 === wc_get_product_id_by_sku( "{$tag}-ROSE" ), 'AC-1 six rows planned, the bad price rejected, nothing written before the load runs' );
		$issues = array_map( 'str_getcsv', array_slice( (array) file( "{$dir}/run/" . ReportWriter::ISSUES, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ), 1 ) );
		$w7     = array_filter( $issues, static fn ( $r ) => "{$tag}-AMP" === ( $r[1] ?? '' ) && 'W7' === ( $r[5] ?? '' ) );
		$check( file_exists( "{$dir}/run/" . ReportWriter::REPORT ) && 2 === count( $w7 ), 'AC-1 B-13, B-15 the validation report and the client issue list are written into the run directory, and the refused and the eleventh image address are client issues before the load (' . count( $w7 ) . ' W7)' );

		// The load runs in the background, in other processes, as Action Scheduler's runner takes it.
		$bin    = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		$finish = static function () use ( $bin ): void {
			for ( $round = 0; $round < 12 && in_array( CatalogueLoad::state()['status'] ?? '', array( 'sources', 'translations', 'images' ), true ); $round++ ) {
				exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' action-scheduler run --group=' . CatalogueLoad::GROUP . ' 2>&1', $out, $code );
				wp_cache_flush();
			}
		};
		$finish();
		$state    = CatalogueLoad::state();
		// Once it has ended, a second run in the same directory is refused: each run keeps its own plan and reports.
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/run", true );
			$reused = false;
		} catch ( \RuntimeException $e ) {
			$reused = str_contains( $e->getMessage(), 'no earlier run' );
		}
		$check( $reused, 'AC-1 B-13 a run directory that holds an earlier run is refused, so each run keeps its plan and reports' );
		// The same for a directory a validation wrote: its run copy is not overwritten by a load's reports.
		wp_mkdir_p( "{$dir}/validated" );
		file_put_contents( "{$dir}/validated/run.json", '{"outcome":"completed"}' );
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/validated", true );
			$validated = false;
		} catch ( \RuntimeException $e ) {
			$validated = str_contains( $e->getMessage(), 'no earlier run' );
		}
		$check( $validated && ! file_exists( "{$dir}/validated/plan.json" ) && ! file_exists( "{$dir}/validated/" . ReportWriter::ISSUES ) && '{"outcome":"completed"}' === file_get_contents( "{$dir}/validated/run.json" ), 'AC-1 B-13 a directory holding a validation\'s run record is refused, and its evidence is left as it was' );
		$problems = (array) ( $state['problems'] ?? array() );
		$on_amp   = array_filter( $problems, static fn ( $p ) => "{$tag}-AMP" === $p['sku'] );
		$check( 'done' === ( $state['status'] ?? '' ) && 2 === count( $problems ) && 2 === count( $on_amp ), 'AC-1 the load ran in the background to the end; its only problems are the two image addresses it did not download (' . ( $state['status'] ?? '?' ) . ', ' . wp_json_encode( $problems ) . ')' );
		$check( 1 === count( array_filter( $problems, static fn ( $p ) => str_contains( $p['problem'], 'refused.jpg' ) ) ) && 1 === count( array_filter( $problems, static fn ( $p ) => str_contains( $p['problem'], "n11-{$tag}" ) ) ), 'AC-5 B-09 a refused image address and the eleventh are reported, never dropped quietly' );
		$record = ImportRunLog::get( (string) $state['id'] );
		$check( is_array( $record ) && true === $record['persisted'] && 'completed' === $record['outcome'] && hash_file( 'sha256', "{$dir}/export.tsv" ) === $record['file']['sha256'] && 't86' === $record['mapping_version'] && '' !== (string) $record['actor'] && 7 === (int) $record['counts']['rows'] && 6 === (int) $record['written']['sources'] && 2 === count( $record['problems'] ) && null !== $record['finished_at'], 'A-273-1 ENT-19 the load is kept as an Import Run record: file and hash, mapping version, actor, times, counts, outcome and problems (' . wp_json_encode( is_array( $record ) ? array( $record['outcome'], $record['actor'], $record['counts'], $record['written'] ?? null ) : null ) . ')' );
		$warned = array_filter( (array) ( $state['warnings'] ?? array() ), static fn ( $w ) => "{$tag}-ROSE" === $w['sku'] && str_contains( $w['warning'], '"Toners"' ) );
		$check( 1 === count( $warned ), 'B-06 a category with no Arabic name is reported as a warning for the Arabic record (' . wp_json_encode( array_values( (array) ( $state['warnings'] ?? array() ) ) ) . ')' );
		$check( 0 === wc_get_product_id_by_sku( "{$tag}-BAD" ), 'AC-1 the rejected row is not written' );

		// A SKU is shared by both language records: the English one is asked for by name.
		$by_sku = static fn ( string $sku ): int => (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( $sku ), 'product', true, 'en' );
		// ---- AC-2: the English record ---------------------------------------------------------------------------
		do_action( 'wpml_switch_language', 'en' );
		$rose = wc_get_product( $by_sku( "{$tag}-ROSE" ) );
		$cats = $rose ? wp_get_post_terms( $rose->get_id(), 'product_cat', array( 'fields' => 'names' ) ) : array();
		$leaf = $rose ? wp_get_post_terms( $rose->get_id(), 'product_cat' ) : array();
		$path = array();
		for ( $t = $leaf[0] ?? null; $t instanceof \WP_Term; $t = $t->parent ? get_term( $t->parent, 'product_cat' ) : null ) {
			array_unshift( $path, $t->name );
		}
		$brand = $rose ? wp_get_post_terms( $rose->get_id(), 'product_brand', array( 'fields' => 'names' ) ) : array();
		$check( $rose instanceof \WC_Product_Simple && 'Rose Water Toner 200 ml' === $rose->get_name() && 'A gentle toner.' === $rose->get_description() && '250' === (string) (float) $rose->get_regular_price() && '199' === (string) (float) $rose->get_sale_price() && 120.0 === (float) $rose->get_cogs_value(), 'AC-2 MIG-13 the product carries its name, description, price, sale price and cost (' . ( $rose ? wp_json_encode( array( get_class( $rose ), $rose->get_name(), $rose->get_regular_price(), $rose->get_sale_price(), $rose->get_cogs_value() ) ) : 'none' ) . ')' );
		$check( array( 'Beauty', 'Skin care', 'Toners' ) === $path && array( "{$tag} Labs" ) === $brand, 'AC-2 MIG-11, MIG-12 the category path and the brand are mapped (' . implode( ' > ', $path ) . ')' );
		$check( $rose && 'Rose water toner' === $rose->get_meta( ProductFields::SEO_TITLE ) && 'Toner with rose water.' === $rose->get_meta( ProductFields::SEO_DESC ), 'AC-2 MIG-15 the SEO title and description are carried' );

		// ---- AC-3: the variable product ---------------------------------------------------------------------------
		$mask   = wc_get_product( $by_sku( "{$tag}-MASK" ) );
		$small  = wc_get_product( (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( "{$tag}-MASK-S" ), 'product_variation', true, 'en' ) );
		$large  = wc_get_product( (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( "{$tag}-MASK-L" ), 'product_variation', true, 'en' ) );
		$values = $mask instanceof \WC_Product_Variable && isset( $mask->get_attributes()['size'] ) ? $mask->get_attributes()['size']->get_options() : array();
		$check( $mask instanceof \WC_Product_Variable && $small instanceof \WC_Product_Variation && $large instanceof \WC_Product_Variation && $small->get_parent_id() === $mask->get_id() && array( 'Small', 'Large' ) === $values && 'Small' === $small->get_attribute( 'size' ) && 60.0 === (float) $small->get_cogs_value() && '240' === (string) (float) $large->get_regular_price(), 'AC-3 MIG-09, MIG-10 the variable product has its two variations, their size attribute, prices and costs (' . wp_json_encode( array( $mask ? get_class( $mask ) : null, $values, $small ? array( $small->get_parent_id(), $small->get_attribute( 'size' ), $small->get_cogs_value() ) : null, $large ? $large->get_regular_price() : null ) ) . ')' );

		// ---- AC-4: the Arabic records ------------------------------------------------------------------------------
		$trid    = static fn ( int $id, string $type ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s", $id, $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rose_ar = $rose ? (int) apply_filters( 'wpml_object_id', $rose->get_id(), 'product', false, 'ar' ) : 0;
		$check( $rose_ar > 0 && $rose_ar !== $rose->get_id() && 'تونر ماء الورد ٢٠٠ مل' === get_post_field( 'post_title', $rose_ar ) && str_contains( (string) get_post_field( 'post_content', $rose_ar ), 'تونر لطيف' ) && $trid( $rose_ar, 'post_product' ) === $trid( $rose->get_id(), 'post_product' ), 'AC-4 MIG-02, SSC-21 the Arabic record is its own, in the product\'s translation group, its text exactly as given (the byte order mark dropped)' );
		$mask_ar  = $mask ? (int) apply_filters( 'wpml_object_id', $mask->get_id(), 'product', false, 'ar' ) : 0;
		$small_ar = $small ? (int) apply_filters( 'wpml_object_id', $small->get_id(), 'product_variation', false, 'ar' ) : 0;
		$large_ar = $large ? (int) apply_filters( 'wpml_object_id', $large->get_id(), 'product_variation', false, 'ar' ) : 0;
		wp_cache_flush();
		$sv = $small_ar > 0 ? wc_get_product( $small_ar ) : null;
		$lv = $large_ar > 0 ? wc_get_product( $large_ar ) : null;
		$check( $mask_ar > 0 && 'قناع الطين' === get_post_field( 'post_title', $mask_ar ) && $sv && $lv && '' !== $sv->get_attribute( 'size' ) && '' !== $lv->get_attribute( 'size' ) && $trid( $small_ar, 'post_product_variation' ) === $trid( $small->get_id(), 'post_product_variation' ) && $trid( $mask_ar, 'post_product' ) === $trid( $mask->get_id(), 'post_product' ), 'AC-4 #247 the Arabic variable product and its variations keep their attributes and their translation groups' );
		$amp    = $by_sku( "{$tag}-AMP" );
		$amp_ar = $amp > 0 ? (int) apply_filters( 'wpml_object_id', $amp, 'product', false, 'ar' ) : 0;
		$check( $amp_ar > 0 && $amp_ar !== $amp && 'صابون & زيت' === html_entity_decode( (string) get_post_field( 'post_title', $amp_ar ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 'AC-4 B-03 an Arabic name with "&" is written and reads back as written, not reported as failed (' . get_post_field( 'post_title', $amp_ar ) . ')' );
		$plain    = $by_sku( "{$tag}-PLAIN" );
		$plain_ar = (int) apply_filters( 'wpml_object_id', $plain, 'product', false, 'ar' );
		$check( $plain > 0 && ( 0 === $plain_ar || $plain_ar === $plain ), 'AC-4 a row with no Arabic text has no Arabic record' );

		// ---- AC-5: images ------------------------------------------------------------------------------------------
		$image   = $rose ? (int) $rose->get_image_id() : 0;
		$gallery = $rose ? $rose->get_gallery_image_ids() : array();
		$check( $image > 0 && 1 === count( $gallery ) && 'Rose Water Toner 200 ml' === get_post_meta( $image, '_wp_attachment_image_alt', true ) && $img1 === get_post_meta( $image, CatalogueLoad::SOURCE_META, true ), 'AC-5 MIG-16, MIG-17 the images are downloaded to the store, the first as the product image, the title as alt text' );
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", CatalogueLoad::SOURCE_META, $img1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 1 === $before, 'AC-5 each source image is stored once' );
		$rose_ar_image = $rose_ar > 0 ? (int) get_post_meta( $rose_ar, '_thumbnail_id', true ) : 0;
		$check( $image > 0 && $image === $rose_ar_image && implode( ',', $gallery ) === (string) get_post_meta( $rose_ar, '_product_image_gallery', true ), 'AC-5 the Arabic record shows the same images (' . $rose_ar_image . ')' );

		// ---- AC-6: export --------------------------------------------------------------------------------------------
		require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
		// As the export screen runs it, with no product ids: the query only narrowed to this run's SKUs (B-12).
		$export = static function ( string $language ) use ( $tag ): array {
			do_action( 'wpml_switch_language', $language );
			$narrow = static fn ( array $args ): array => array_merge( $args, array( 'sku' => $tag ) );
			add_filter( 'woocommerce_product_export_product_query_args', $narrow );
			$exporter = new \WC_Product_CSV_Exporter();
			$exporter->set_limit( 200 );
			$exporter->prepare_data_to_export();
			remove_filter( 'woocommerce_product_export_product_query_args', $narrow );
			do_action( 'wpml_switch_language', 'en' );
			return array_count_values( array_map( static fn ( $r ) => (string) ( $r['sku'] ?? '' ), (array) ( function () { return $this->row_data; } )->call( $exporter ) ) );
		};
		$english = $export( 'en' );
		$expect  = array( "{$tag}-ROSE", "{$tag}-MASK", "{$tag}-MASK-S", "{$tag}-MASK-L", "{$tag}-PLAIN", "{$tag}-AMP" );
		$check( array() === array_diff( $expect, array_keys( $english ) ) && array( 1 ) === array_values( array_unique( array_values( $english ) ) ), 'AC-6 MIG-25 the export screen\'s query, in English, lists every loaded product and variation once (' . wp_json_encode( $english ) . ')' );

		// A category with an Arabic translation, for an update that renames a product and moves it there.
		$serum    = wp_insert_term( "{$tag} Serums", 'product_cat' );
		$serum_ar = wp_insert_term( "{$tag} سيروم", 'product_cat', array( 'slug' => strtolower( $tag ) . '-serums-ar' ) );
		$s->on_finish(
			static function () use ( $serum, $serum_ar ): void {
				foreach ( array( $serum, $serum_ar ) as $made ) {
					if ( ! is_wp_error( $made ) ) {
						wp_delete_term( (int) $made['term_id'], 'product_cat' );
					}
				}
			}
		);
		$serum_tt = is_wp_error( $serum ) ? 0 : (int) $serum['term_taxonomy_id'];
		$serum_ar_id = is_wp_error( $serum_ar ) ? 0 : (int) $serum_ar['term_id'];
		do_action( 'wpml_set_element_language_details', array( 'element_id' => is_wp_error( $serum_ar ) ? 0 : (int) $serum_ar['term_taxonomy_id'], 'element_type' => 'tax_product_cat', 'trid' => apply_filters( 'wpml_element_trid', null, $serum_tt, 'tax_product_cat' ), 'language_code' => 'ar', 'source_language_code' => 'en' ) );

		// An update run, as the client's corrected file comes back (MIG-19): new prices with blank cells everywhere else,
		// a size added to the translated variable product, and a product renamed and moved to another category.
		$rose_trid = $rose ? $trid( $rose->get_id(), 'post_product' ) : 0;
		$update    = implode( "\t", $cols ) . "\n"
			. implode( "\t", array( "{$tag}-ROSE", '', '', '', '', '260.00', '', '', '', '', '', '', '', '', '', '', '' ) ) . "\n"
			. implode( "\t", array( "{$tag}-MASK-S", '', '', '', '', '155.00', '', '', 'child', "{$tag}-MASK", '', '', '', '', '', '', '' ) ) . "\n"
			. implode( "\t", array( "{$tag}-MASK-M", '', '', '', '', '180.00', '', '70.00', 'child', "{$tag}-MASK", 'Medium', '', '', '', '', '', '' ) ) . "\n"
			. implode( "\t", array( "{$tag}-PLAIN", '', '', '', '', '', '150.00', '', '', '', '', '', '', '', '', '', '' ) ) . "\n"
			. implode( "\t", array( "{$tag}-AMP", 'Soap & Oil Bar', '', '', '', '', '', '', '', '', '', "{$tag} Serums", '', '', '', '', '' ) ) . "\n";
		file_put_contents( "{$dir}/update.tsv", $update );
		$upd = CatalogueLoad::start( "{$dir}/update.tsv", $mapping, "{$dir}/run-update", false );
		$finish();
		wp_cache_flush();
		$upd_state = CatalogueLoad::state();
		$after     = wc_get_product( $rose->get_id() );
		$ar_now    = (int) apply_filters( 'wpml_object_id', $rose->get_id(), 'product', false, 'ar' );
		$check( 'done' === ( $upd_state['status'] ?? '' ) && array() === ( $upd_state['problems'] ?? array() ), 'AC-1 the update run ran to the end with no problem (' . wp_json_encode( $upd_state['problems'] ?? null ) . ')' );
		$upd_issues = array_map( 'str_getcsv', array_slice( (array) file( "{$dir}/run-update/" . ReportWriter::ISSUES, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ), 1 ) );
		$w5         = array_filter( $upd_issues, static fn ( $r ) => "{$tag}-PLAIN" === ( $r[1] ?? '' ) && 'W5' === ( $r[5] ?? '' ) );
		$check( 1 === count( $w5 ), 'B-15 a sale price given alone on an update, at or above the price the store holds (150 against 99), is a client issue before the load (' . count( $w5 ) . ' W5)' );
		$check( 4 === (int) $upd['counts']['update'] && '260' === (string) (float) $after->get_regular_price() && '199' === (string) (float) $after->get_sale_price() && 'Rose Water Toner 200 ml' === $after->get_name() && 120.0 === (float) $after->get_cogs_value(), 'AC-2 an update writes the new price; blank cells leave the sale price, name and cost as they were (' . wp_json_encode( array( $upd['counts'] ?? null, $after->get_regular_price(), $after->get_sale_price(), $after->get_name(), $after->get_cogs_value() ) ) . ')' );
		$check( $rose_trid > 0 && $rose_trid === $trid( $rose->get_id(), 'post_product' ) && $ar_now === $rose_ar && 'تونر ماء الورد ٢٠٠ مل' === get_post_field( 'post_title', $ar_now ), 'AC-4 the update keeps the product and its Arabic record in one translation group, the Arabic text unchanged' );

		// B-01: the Arabic records carry the new prices, and an Arabic order is charged them.
		$rose_ar_p = $ar_now > 0 ? wc_get_product( $ar_now ) : null;
		$check( $rose_ar_p && '260' === (string) (float) $rose_ar_p->get_regular_price() && '199' === (string) (float) $rose_ar_p->get_sale_price(), 'AC-2 B-01 after a price-only update the Arabic record has the new price, its sale price unchanged (' . wp_json_encode( $rose_ar_p ? array( $rose_ar_p->get_regular_price(), $rose_ar_p->get_sale_price() ) : null ) . ')' );
		$small_ar_now = $small ? (int) apply_filters( 'wpml_object_id', $small->get_id(), 'product_variation', false, 'ar' ) : 0;
		$sv_now       = $small_ar_now > 0 && $small_ar_now !== $small->get_id() ? wc_get_product( $small_ar_now ) : null;
		do_action( 'wpml_switch_language', 'ar' );
		$charged = $sv_now ? (float) $s->order_for( $sv_now, 1 )->get_subtotal() : 0.0;
		do_action( 'wpml_switch_language', 'en' );
		$check( $sv_now && '155' === (string) (float) $sv_now->get_regular_price() && 155.0 === $charged && '155' === (string) (float) wc_get_product( $small->get_id() )->get_regular_price(), 'AC-3 B-01 a variation\'s new price reaches its Arabic variation, and an Arabic order is charged it (' . wp_json_encode( array( $sv_now ? $sv_now->get_regular_price() : null, $charged ) ) . ')' );

		// Second review, 1: the renamed product's new category reaches its Arabic record as the Arabic category. WCML had
		// read the old terms on the rename's save_post in the same process; a stale read would leave the Arabic record
		// without it.
		$amp_ar_cats = $amp_ar > 0 ? array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = 'product_cat'", $amp_ar ) ) ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( $serum_ar_id > 0 && in_array( $serum_ar_id, $amp_ar_cats, true ), 'AC-2 a product moved to a translated category on an update shows the Arabic category on its Arabic record (' . wp_json_encode( $amp_ar_cats ) . ')' );

		// B-02: the added size reaches the Arabic product, buyable at its price.
		$medium    = (int) apply_filters( 'wpml_object_id', wc_get_product_id_by_sku( "{$tag}-MASK-M" ), 'product_variation', true, 'en' );
		$medium_ar = $medium > 0 ? (int) apply_filters( 'wpml_object_id', $medium, 'product_variation', false, 'ar' ) : 0;
		$mv        = $medium_ar > 0 && $medium_ar !== $medium ? wc_get_product( $medium_ar ) : null;
		$mask_arp  = $mask_ar > 0 ? wc_get_product( $mask_ar ) : null;
		$ar_values = $mask_arp instanceof \WC_Product_Variable && isset( $mask_arp->get_attributes()['size'] ) ? $mask_arp->get_attributes()['size']->get_options() : array();
		$check( $mv instanceof \WC_Product_Variation && $mv->get_parent_id() === $mask_ar && '180' === (string) (float) $mv->get_regular_price() && 'Medium' === $mv->get_attribute( 'size' ) && in_array( 'Medium', $ar_values, true ) && 3 === count( $mask_arp->get_children() ), 'AC-3 B-02 a size added to a translated product gets its Arabic variation, its price, and its place in the Arabic size list (' . wp_json_encode( array( $medium_ar, $ar_values, $mv ? $mv->get_regular_price() : null, $mask_arp ? count( $mask_arp->get_children() ) : null ) ) . ')' );
		$check( is_array( ImportRunLog::get( (string) $state['id'] ) ) && is_array( ImportRunLog::get( (string) $upd['id'] ) ) && (string) $state['id'] !== (string) $upd['id'], 'A-273-1 ENT-19 the earlier run\'s record is kept beside the update run\'s' );

		// B-10: an images action that died at a time limit is resumed from the saved progress, by the watchdog it queued,
		// and a product tried twice without finishing is given up and reported instead of being tried for ever.
		$stranded = wp_generate_uuid4();
		update_option( CatalogueLoad::RUN, array_merge( $state, array( 'id' => $stranded, 'status' => 'images', 'done' => array( 'sources' => 6, 'translations' => 6, 'images' => 0 ), 'problems' => $problems, 'warnings' => array(), 'image_tries' => array( 0 => 2 ), 'updated' => time() ) ), false );
		// A live worker of this run holds its lock (another database connection stands for it): an images action that
		// starts now takes nothing and only leaves the watchdog queued.
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$held  = 1 === (int) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', CatalogueLoad::imageLock( $stranded ) ) );
		CatalogueLoad::images( $stranded, 0 );
		$deferred = CatalogueLoad::state();
		$check( $held && 0 === (int) $deferred['done']['images'] && CatalogueLoad::watchdogPending( $stranded ), 'AC-5 B-10 while a live worker holds the run, another images action takes nothing and leaves the watchdog queued' );
		$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', CatalogueLoad::imageLock( $stranded ) ) );
		$other->close();
		// The worker has died: Action Scheduler runs the queued watchdog, which resumes; while it works, its own
		// watchdog is queued in turn, and a normal end removes it.
		$queued_during = false;
		$observe       = static function ( $option ) use ( &$queued_during, $stranded ): void {
			if ( CatalogueLoad::RUN === $option ) {
				$queued_during = $queued_during || CatalogueLoad::watchdogPending( $stranded );
			}
		};
		add_action( 'updated_option', $observe );
		$watchdogs = as_get_scheduled_actions( array( 'hook' => CatalogueLoad::IMAGES, 'args' => array( $stranded, 'resume' ), 'group' => CatalogueLoad::GROUP, 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		foreach ( $watchdogs as $action_id ) {
			\ActionScheduler::runner()->process_action( (int) $action_id, 't86' );
		}
		remove_action( 'updated_option', $observe );
		$check( 1 === count( $watchdogs ) && $queued_during, 'AC-5 B-10 the watchdog is a real queued action, and the worker it starts queues its own while it works (' . count( $watchdogs ) . ')' );
		$resumed = CatalogueLoad::state();
		$gave_up = array_filter( (array) $resumed['problems'], static fn ( $p ) => "{$tag}-ROSE" === $p['sku'] && str_contains( $p['problem'], 'two tries' ) );
		// The stranded run had already recorded AMP's two image problems; taking AMP up again records them once.
		$amp_again = array_filter( (array) $resumed['problems'], static fn ( $p ) => "{$tag}-AMP" === $p['sku'] );
		$check( 2 === count( $amp_again ), 'AC-5 B-10 a product taken up again by a resumed action reports each problem once (' . count( $amp_again ) . ')' );
		$check( 'done' === $resumed['status'] && 2 === (int) $resumed['done']['images'] && 1 === count( $gave_up ) && ! CatalogueLoad::watchdogPending( $stranded ), 'AC-5 B-10 a stranded images phase resumes from its saved progress, gives up a product after two tries with the reason, and leaves no watchdog behind (' . wp_json_encode( array( $resumed['status'], $resumed['done'], array_column( (array) $resumed['problems'], 'problem' ) ) ) . ')' );

		// The time budget is checked between downloads: an action whose time is spent before a product's next download
		// hands over without downloading it, and the handover is not counted as one of the product's two tries.
		$handover = wp_generate_uuid4();
		wp_mkdir_p( "{$dir}/handover" );
		$fresh_image = home_url( '/wp-includes/images/w-logo-blue.png?fresh-' . $tag );
		file_put_contents( "{$dir}/handover/plan.json", (string) wp_json_encode( array( array( 'row' => 2, 'sku' => "{$tag}-ROSE", 'type' => 'simple', 'outcome' => 'update', 'id' => $rose->get_id(), 'parent_sku' => '', 'price' => null, 'fields' => array( 'images' => $fresh_image ), 'attributes' => array() ) ) ) );
		update_option( CatalogueLoad::RUN, array( 'id' => $handover, 'plan' => "{$dir}/handover/plan.json", 'total' => 1, 'counts' => array(), 'images' => true, 'status' => 'images', 'ids' => array( "{$tag}-ROSE" => $rose->get_id() ), 'done' => array( 'sources' => 1, 'translations' => 1, 'images' => 0 ), 'problems' => array(), 'warnings' => array(), 'updated' => time() ), false );
		$no_time = static fn (): float => 0.0;
		add_filter( 'mizzey_catalogue_image_seconds', $no_time );
		CatalogueLoad::images( $handover, 0 );
		remove_filter( 'mizzey_catalogue_image_seconds', $no_time );
		$handed  = CatalogueLoad::state();
		$fetched = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", CatalogueLoad::SOURCE_META, $fresh_image ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$next    = as_get_scheduled_actions( array( 'hook' => CatalogueLoad::IMAGES, 'args' => array( $handover, 0 ), 'group' => CatalogueLoad::GROUP, 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		$check( 0 === $fetched && 'images' === $handed['status'] && 0 === (int) $handed['done']['images'] && 0 === (int) ( $handed['image_tries'][0] ?? -1 ) && array() === $handed['problems'] && 1 === count( $next ) && ! CatalogueLoad::watchdogPending( $handover ), 'AC-5 B-10 with its time spent, an images action hands over before the next download, queues the next action, and does not count the handover as a try (' . wp_json_encode( array( $fetched, $handed['done'], $handed['image_tries'] ?? null, count( $next ) ) ) . ')' );
		as_unschedule_all_actions( CatalogueLoad::IMAGES, array( $handover, 0 ), CatalogueLoad::GROUP );
		@unlink( "{$dir}/handover/plan.json" ); // phpcs:ignore

		// The run is read again once the worker lock is taken: here another worker ends the run between the first read
		// and the lock, and the late action leaves it as that worker left it.
		$late = wp_generate_uuid4();
		update_option( CatalogueLoad::RUN, array_merge( $state, array( 'id' => $late, 'status' => 'images', 'done' => array( 'sources' => 6, 'translations' => 6, 'images' => 0 ), 'problems' => array(), 'warnings' => array(), 'updated' => time() ) ), false );
		$ends = static function ( string $query ) use ( &$ends, $late ): string {
			if ( str_contains( $query, 'GET_LOCK' ) && str_contains( $query, CatalogueLoad::imageLock( $late ) ) ) {
				remove_filter( 'query', $ends );
				$ended           = CatalogueLoad::state();
				$ended['status'] = 'done';
				update_option( CatalogueLoad::RUN, $ended, false );
			}
			return $query;
		};
		add_filter( 'query', $ends );
		CatalogueLoad::images( $late, 0 );
		remove_filter( 'query', $ends );
		$after_late = CatalogueLoad::state();
		$late_next  = as_get_scheduled_actions( array( 'hook' => CatalogueLoad::IMAGES, 'args' => array( $late, 0 ), 'group' => CatalogueLoad::GROUP, 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		$check( 'done' === $after_late['status'] && 0 === (int) $after_late['done']['images'] && array() === $late_next && ! CatalogueLoad::watchdogPending( $late ), 'AC-5 B-10 an images action that takes the lock after another worker ended the run does no work on its stale copy (' . wp_json_encode( array( $after_late['status'], $after_late['done'] ) ) . ')' );

		// Images only with the rights confirmed: a second plan without the flag downloads nothing.
		// A run that stopped moving more than half an hour ago is replaced; its record is finalised, not left running.
		$stale = wp_generate_uuid4();
		( new ImportRunLog() )->record( array( 'id' => $stale, 'kind' => 'load', 'outcome' => 'running', 'finished_at' => null ) );
		update_option( CatalogueLoad::RUN, array( 'id' => $stale, 'status' => 'sources', 'done' => array( 'sources' => 1, 'translations' => 0, 'images' => 0 ), 'problems' => array(), 'warnings' => array(), 'updated' => time() - CatalogueLoad::STALE - 5 ), false );
		$no = CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/run2", false );
		$check( false === $no['images'], 'AC-5 without the rights confirmed, the run plans no image download' );
		wp_cache_delete( ImportRunLog::PREFIX . $stale, 'options' );
		$superseded = ImportRunLog::get( $stale );
		$check( is_array( $superseded ) && 'superseded' === $superseded['outcome'] && null !== $superseded['finished_at'] && 1 === (int) ( $superseded['written']['sources'] ?? 0 ), 'A-273-1 ENT-19 a run replaced after it stopped is recorded as superseded, with its finish time and what it wrote (' . wp_json_encode( $superseded ) . ')' );
		// B-12, last: the export with all languages, as WooCommerce Multilingual runs it when the export screen is shown
		// in "All languages" (its flag in the submitted form, its own hooks lifting WPML's language filters for the
		// query). One row per language record: twice for each product and variation with an Arabic record, once for
		// the one without. It is last because WooCommerce Multilingual does not restore the filters in this process.
		global $sitepress;
		( new \WCML\Exporter\AllLanguagesHooks( $sitepress ) )->add_hooks();
		$_POST['form'] = \WCML\Exporter\AllLanguagesHooks::KEY_EXPORT_ALL_LANGUAGES . '=1';
		$all_languages = $export( 'all' );
		unset( $_POST['form'] );
		$per_record = array( "{$tag}-ROSE" => 2, "{$tag}-MASK" => 2, "{$tag}-MASK-S" => 2, "{$tag}-MASK-L" => 2, "{$tag}-MASK-M" => 2, "{$tag}-AMP" => 2, "{$tag}-PLAIN" => 1 );
		ksort( $all_languages );
		ksort( $per_record );
		$check( $per_record === $all_languages, 'AC-6 B-12 the all-languages export lists each language record once: no row missing, none repeated (' . wp_json_encode( $all_languages ) . ')' );

		foreach ( array( CatalogueLoad::SOURCES, CatalogueLoad::TRANSLATIONS, CatalogueLoad::IMAGES ) as $hook ) {
			as_unschedule_all_actions( $hook, array(), CatalogueLoad::GROUP );
		}
		delete_option( CatalogueLoad::RUN );
		foreach ( array( $state['id'], $upd['id'], $no['id'], $stranded, $stale, $handover, $late ) as $run_id ) {
			delete_option( ImportRunLog::PREFIX . $run_id ); // This scenario's records only.
		}

		return $ok;
	}
);

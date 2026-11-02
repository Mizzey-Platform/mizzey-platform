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
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\Import\CatalogueLoad;
use MizzeySite\Catalogue\Import\Mapping;
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
		$dir = trailingslashit( get_temp_dir() ) . strtolower( $tag );
		wp_mkdir_p( $dir );
		$keep = get_option( CatalogueLoad::RUN, null );
		$s->on_finish(
			static function () use ( $keep, $tag ): void {
				null === $keep ? delete_option( CatalogueLoad::RUN ) : update_option( CatalogueLoad::RUN, $keep, false );
				global $wpdb;
				$like = $wpdb->esc_like( $tag ) . '%';
				$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				foreach ( $ids as $id ) {
					foreach ( array_merge( array( (int) $id ), (array) get_children( array( 'post_parent' => (int) $id, 'post_type' => 'attachment', 'fields' => 'ids' ) ) ) as $post ) {
						wp_delete_post( (int) $post, true );
					}
				}
			}
		);

		// The export, as the client's file would carry it, and its mapping.
		$img1 = home_url( '/wp-includes/images/w-logo-blue.png' );
		$img2 = home_url( '/wp-includes/images/wpspin-2x.gif' );
		$cols = array( 'seller-sku', 'item-name', 'item-name-ar', 'item-description', 'item-description-ar', 'price', 'sale-price', 'cost', 'parent-child', 'parent-sku', 'size-name', 'category', 'brand', 'main-image', 'other-image', 'seo-title', 'seo-description' );
		$rows = array(
			array( "{$tag}-ROSE", 'Rose Water Toner 200 ml', "\u{FEFF}تونر ماء الورد ٢٠٠ مل", 'A gentle toner.', 'تونر لطيف للبشرة.', '250.00', '199.00', '120.00', '', '', '', 'Beauty > Skin care > Toners', "{$tag} Labs", $img1, $img2, 'Rose water toner', 'Toner with rose water.' ),
			array( "{$tag}-MASK", 'Clay Mask', 'قناع الطين', 'A clay mask.', 'قناع من الطين.', '', '', '', 'parent', '', '', 'Beauty > Masks', "{$tag} Labs", '', '', '', '' ),
			array( "{$tag}-MASK-S", '', '', '', '', '150.00', '', '60.00', 'child', "{$tag}-MASK", 'Small', '', '', '', '', '', '' ),
			array( "{$tag}-MASK-L", '', '', '', '', '240.00', '', '95.00', 'child', "{$tag}-MASK", 'Large', '', '', '', '', '', '' ),
			array( "{$tag}-PLAIN", 'Cotton Pads', '', 'Soft pads.', '', '99.00', '', '', '', '', '', 'Beauty > Tools', '', '', '', '', '' ),
			array( "{$tag}-BAD", 'Bad Price', 'سعر خاطئ', '', '', '12,50', '', '', '', '', '', '', '', '', '', '', '' ),
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
		try {
			CatalogueLoad::start( "{$dir}/export.tsv", $mapping, wp_upload_dir()['basedir'] . "/{$tag}-run", true );
			$refused = false;
		} catch ( \RuntimeException $e ) {
			$refused = str_contains( $e->getMessage(), 'outside the website folder' );
		}
		$check( $refused && ! file_exists( wp_upload_dir()['basedir'] . "/{$tag}-run/plan.json" ), 'AC-1 a run directory inside the website is refused: the plan holds costs' );
		@rmdir( wp_upload_dir()['basedir'] . "/{$tag}-run" ); // phpcs:ignore
		$state = CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/run", true );
		$check( 5 === (int) $state['total'] && 1 === (int) $state['counts']['reject'] && 0 === wc_get_product_id_by_sku( "{$tag}-ROSE" ), 'AC-1 five rows planned, the bad price rejected, nothing written before the load runs' );

		// The load runs in the background, in other processes, as Action Scheduler's runner takes it.
		$bin    = getenv( 'MIZZEY_WP_BIN' ) ?: 'wp';
		$finish = static function () use ( $bin ): void {
			for ( $round = 0; $round < 12 && in_array( CatalogueLoad::state()['status'] ?? '', array( 'sources', 'translations', 'images' ), true ); $round++ ) {
				exec( escapeshellarg( $bin ) . ' --path=' . escapeshellarg( ABSPATH ) . ' action-scheduler run --group=' . CatalogueLoad::GROUP . ' 2>&1', $out, $code );
				wp_cache_flush();
			}
		};
		$finish();
		$state = CatalogueLoad::state();
		$check( 'done' === ( $state['status'] ?? '' ) && array() === ( $state['problems'] ?? array() ), 'AC-1 the load ran in the background to the end, with no problem (' . ( $state['status'] ?? '?' ) . ', ' . wp_json_encode( $state['problems'] ?? array() ) . ')' );
		$check( 0 === wc_get_product_id_by_sku( "{$tag}-BAD" ), 'AC-1 the rejected row is not written' );

		// ---- AC-2: the English record ---------------------------------------------------------------------------
		do_action( 'wpml_switch_language', 'en' );
		$rose = wc_get_product( wc_get_product_id_by_sku( "{$tag}-ROSE" ) );
		$cats = $rose ? wp_get_post_terms( $rose->get_id(), 'product_cat', array( 'fields' => 'names' ) ) : array();
		$leaf = $rose ? wp_get_post_terms( $rose->get_id(), 'product_cat' ) : array();
		$path = array();
		for ( $t = $leaf[0] ?? null; $t instanceof \WP_Term; $t = $t->parent ? get_term( $t->parent, 'product_cat' ) : null ) {
			array_unshift( $path, $t->name );
		}
		$brand = $rose ? wp_get_post_terms( $rose->get_id(), 'product_brand', array( 'fields' => 'names' ) ) : array();
		$check( $rose instanceof \WC_Product_Simple && 'Rose Water Toner 200 ml' === $rose->get_name() && 'A gentle toner.' === $rose->get_description() && '250' === (string) (float) $rose->get_regular_price() && '199' === (string) (float) $rose->get_sale_price() && 120.0 === (float) $rose->get_cogs_value(), 'AC-2 MIG-13 the product carries its name, description, price, sale price and cost' );
		$check( array( 'Beauty', 'Skin care', 'Toners' ) === $path && array( "{$tag} Labs" ) === $brand, 'AC-2 MIG-11, MIG-12 the category path and the brand are mapped (' . implode( ' > ', $path ) . ')' );
		$check( $rose && 'Rose water toner' === $rose->get_meta( ProductFields::SEO_TITLE ) && 'Toner with rose water.' === $rose->get_meta( ProductFields::SEO_DESC ), 'AC-2 MIG-15 the SEO title and description are carried' );

		// ---- AC-3: the variable product ---------------------------------------------------------------------------
		$mask   = wc_get_product( wc_get_product_id_by_sku( "{$tag}-MASK" ) );
		$small  = wc_get_product( wc_get_product_id_by_sku( "{$tag}-MASK-S" ) );
		$large  = wc_get_product( wc_get_product_id_by_sku( "{$tag}-MASK-L" ) );
		$values = $mask instanceof \WC_Product_Variable && isset( $mask->get_attributes()['size'] ) ? $mask->get_attributes()['size']->get_options() : array();
		$check( $mask instanceof \WC_Product_Variable && $small instanceof \WC_Product_Variation && $large instanceof \WC_Product_Variation && $small->get_parent_id() === $mask->get_id() && array( 'Small', 'Large' ) === $values && 'Small' === $small->get_attribute( 'size' ) && 60.0 === (float) $small->get_cogs_value() && '240' === (string) (float) $large->get_regular_price(), 'AC-3 MIG-09, MIG-10 the variable product has its two variations, their size attribute, prices and costs' );

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
		$plain    = wc_get_product_id_by_sku( "{$tag}-PLAIN" );
		$plain_ar = (int) apply_filters( 'wpml_object_id', $plain, 'product', false, 'ar' );
		$check( $plain > 0 && ( 0 === $plain_ar || $plain_ar === $plain ), 'AC-4 a row with no Arabic text has no Arabic record' );

		// ---- AC-5: images ------------------------------------------------------------------------------------------
		$image   = $rose ? (int) $rose->get_image_id() : 0;
		$gallery = $rose ? $rose->get_gallery_image_ids() : array();
		$check( $image > 0 && 1 === count( $gallery ) && 'Rose Water Toner 200 ml' === get_post_meta( $image, '_wp_attachment_image_alt', true ) && $img1 === get_post_meta( $image, CatalogueLoad::SOURCE_META, true ), 'AC-5 MIG-16, MIG-17 the images are downloaded to the store, the first as the product image, the title as alt text' );
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", CatalogueLoad::SOURCE_META, $img1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$check( 1 === $before, 'AC-5 each source image is stored once' );

		// ---- AC-6: export --------------------------------------------------------------------------------------------
		require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_ids_to_export( array_filter( array( $rose ? $rose->get_id() : 0, $mask ? $mask->get_id() : 0 ) ) );
		$exporter->prepare_data_to_export();
		$rows_out = ( function () { return $this->row_data; } )->call( $exporter );
		$skus     = array_map( static fn ( $r ) => (string) ( $r['sku'] ?? '' ), (array) $rows_out );
		$check( in_array( "{$tag}-ROSE", $skus, true ) && in_array( "{$tag}-MASK", $skus, true ), 'AC-6 MIG-25 the loaded catalogue exports through WooCommerce\'s CSV exporter' );

		// An update run: a new price, blank cells everywhere else.
		$rose_trid = $rose ? $trid( $rose->get_id(), 'post_product' ) : 0;
		$update    = implode( "\t", $cols ) . "\n" . implode( "\t", array( "{$tag}-ROSE", '', '', '', '', '260.00', '', '', '', '', '', '', '', '', '', '', '' ) ) . "\n";
		file_put_contents( "{$dir}/update.tsv", $update );
		$upd = CatalogueLoad::start( "{$dir}/update.tsv", $mapping, "{$dir}/run-update", false );
		$finish();
		wp_cache_flush();
		$after   = wc_get_product( $rose->get_id() );
		$ar_now  = (int) apply_filters( 'wpml_object_id', $rose->get_id(), 'product', false, 'ar' );
		$check( 1 === (int) $upd['counts']['update'] && '260' === (string) (float) $after->get_regular_price() && '199' === (string) (float) $after->get_sale_price() && 'Rose Water Toner 200 ml' === $after->get_name() && 120.0 === (float) $after->get_cogs_value(), 'AC-2 an update writes the new price; blank cells leave the sale price, name and cost as they were' );
		$check( $rose_trid > 0 && $rose_trid === $trid( $rose->get_id(), 'post_product' ) && $ar_now === $rose_ar && 'تونر ماء الورد ٢٠٠ مل' === get_post_field( 'post_title', $ar_now ), 'AC-4 the update keeps the product and its Arabic record in one translation group, the Arabic text unchanged' );

		// Images only with the rights confirmed: a second plan without the flag downloads nothing.
		update_option( CatalogueLoad::RUN, array( 'status' => 'done' ), false );
		$no = CatalogueLoad::start( "{$dir}/export.tsv", $mapping, "{$dir}/run2", false );
		$check( false === $no['images'], 'AC-5 without the rights confirmed, the run plans no image download' );
		foreach ( array( CatalogueLoad::SOURCES, CatalogueLoad::TRANSLATIONS, CatalogueLoad::IMAGES ) as $hook ) {
			as_unschedule_all_actions( $hook, array(), CatalogueLoad::GROUP );
		}
		delete_option( CatalogueLoad::RUN );

		return $ok;
	}
);

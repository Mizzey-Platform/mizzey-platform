<?php
/**
 * Staging seed, step 2: the catalogue, source-language records only.
 *
 * Every product here is invented. Names say "Sample", SKUs start with DEMO-, and no brand, price or description
 * is the client's. The set is shaped by what a review has to exercise, not by what the client sells:
 *
 *  - twelve simple products and two products with variants, across three categories and two brands;
 *  - one curated collection holding three of them, so the collection archive of IA-04 has something to show;
 *  - a barcode on some and none on others, for the search row of the store operations list (ADM-152);
 *  - stock that is healthy, low and exhausted, so the stock report of #246 has every case to show;
 *  - a product cost on most and none on two (ADM-27: zero and missing are different things);
 *  - one product that exists only in English and one that exists only in Arabic.
 *
 * Translations are NOT made here. Creating a record and translating it in the same process is the measured
 * trigger for translation-group corruption (finding A11), so step 3 does that in a process of its own.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

global $sitepress;
$sitepress->switch_lang( 'en', true );

/** Register a record as an original in one language: WP-CLI never reaches the save_post hook that would. */
$as_original = static function ( int $id, string $type, string $lang ) use ( $sitepress ): void {
	$sitepress->set_element_language_details( $id, $type, null, $lang );
};

$term = static function ( string $taxonomy, string $name, string $slug ) use ( $as_original ): int {
	$found = get_term_by( 'slug', $slug, $taxonomy );
	if ( $found ) {
		return (int) $found->term_id;
	}
	$made = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $made ) ) {
		WP_CLI::error( $made->get_error_message() );
	}
	$as_original( (int) $made['term_taxonomy_id'], 'tax_' . $taxonomy, 'en' );
	return (int) $made['term_id'];
};

$categories = array(
	'care'      => $term( 'product_cat', 'Sample Care', 'sample-care' ),
	'home'      => $term( 'product_cat', 'Sample Home', 'sample-home' ),
	'accessory' => $term( 'product_cat', 'Sample Accessories', 'sample-accessories' ),
);
$brands     = array(
	'north' => $term( 'product_brand', 'Sample Brand North', 'sample-brand-north' ),
	'south' => $term( 'product_brand', 'Sample Brand South', 'sample-brand-south' ),
);

$collection = $term( 'product_collection', 'Sample Collection', 'sample-collection' );
// The simple products placed in it, by number. A collection is curated by hand (ADM-57), so membership is a list.
$in_collection = array( 1, 5, 9 );

// number, category, brand, price, stock, cost (null is "not entered"), barcode or null.
$simple = array(
	array( 1, 'care', 'north', '120', 40, 70.0, '6220000000017' ),
	array( 2, 'care', 'north', '185', 25, 110.0, '6220000000024' ),
	array( 3, 'care', 'south', '95', 2, 55.0, null ),
	array( 4, 'care', 'south', '240', 0, 150.0, '6220000000048' ),
	array( 5, 'home', 'north', '310', 18, 190.0, null ),
	array( 6, 'home', 'north', '75', 60, 0.0, '6220000000062' ),
	array( 7, 'home', 'south', '450', 1, 300.0, null ),
	array( 8, 'home', 'south', '130', 0, null, '6220000000086' ),
	array( 9, 'accessory', 'north', '60', 90, 30.0, null ),
	array( 10, 'accessory', 'north', '205', 12, 120.0, '6220000000109' ),
	array( 11, 'accessory', 'south', '88', 33, 45.0, null ),
	array( 12, 'accessory', 'south', '520', 7, null, '6220000000123' ),
);

$made = array( 'simple' => 0, 'variable' => 0, 'variations' => 0, 'single_language' => 0 );

$place = static function ( int $id, int $category, ?int $brand ): void {
	wp_set_object_terms( $id, array( $category ), 'product_cat' );
	if ( $brand ) {
		wp_set_object_terms( $id, array( $brand ), 'product_brand' );
	}
};

foreach ( $simple as list( $n, $category, $brand, $price, $stock, $cost, $barcode ) ) {
	$sku = sprintf( 'DEMO-S-%03d', $n );
	if ( wc_get_product_id_by_sku( $sku ) ) {
		continue;
	}
	$p = new WC_Product_Simple();
	$p->set_name( sprintf( 'Sample Product %02d', $n ) );
	$p->set_sku( $sku );
	$p->set_regular_price( $price );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $stock );
	$p->set_low_stock_amount( 3 );
	$p->set_status( 'publish' );
	$p->set_short_description( 'Invented product for the staging copy. Not a real item.' );
	if ( null !== $cost ) {
		$p->set_cogs_value( $cost );
	}
	if ( $barcode ) {
		$p->set_global_unique_id( $barcode );
	}
	$p->save();
	$as_original( $p->get_id(), 'post_product', 'en' );
	$place( $p->get_id(), $categories[ $category ], $brands[ $brand ] );
	if ( in_array( $n, $in_collection, true ) ) {
		wp_set_object_terms( $p->get_id(), array( $collection ), 'product_collection' );
	}
	update_post_meta( $p->get_id(), '_mizzey_seed', 'translate' );
	++$made['simple'];
}

// Two products with variants. Each variant has its own SKU and its own stock (ADM-31, ADM-34).
$variable = array(
	array( 1, 'care', 'north', array( 'Small' => array( '150', 15 ), 'Medium' => array( '170', 2 ), 'Large' => array( '190', 0 ) ) ),
	array( 2, 'accessory', 'south', array( 'Small' => array( '90', 30 ), 'Medium' => array( '90', 30 ), 'Large' => array( '99', 6 ) ) ),
);
foreach ( $variable as list( $n, $category, $brand, $sizes ) ) {
	$sku = sprintf( 'DEMO-V-%03d', $n );
	if ( wc_get_product_id_by_sku( $sku ) ) {
		continue;
	}
	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Size' );
	$attribute->set_options( array_keys( $sizes ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$parent = new WC_Product_Variable();
	$parent->set_name( sprintf( 'Sample Variable Product %02d', $n ) );
	$parent->set_sku( $sku );
	$parent->set_status( 'publish' );
	$parent->set_attributes( array( $attribute ) );
	$parent->set_short_description( 'Invented product with variants for the staging copy. Not a real item.' );
	$parent->save();
	$as_original( $parent->get_id(), 'post_product', 'en' );
	$place( $parent->get_id(), $categories[ $category ], $brands[ $brand ] );
	update_post_meta( $parent->get_id(), '_mizzey_seed', 'translate' );
	foreach ( $sizes as $size => list( $price, $stock ) ) {
		$v = new WC_Product_Variation();
		$v->set_parent_id( $parent->get_id() );
		$v->set_attributes( array( 'size' => $size ) );
		$v->set_sku( $sku . '-' . strtoupper( $size[0] ) );
		$v->set_regular_price( $price );
		$v->set_manage_stock( true );
		$v->set_stock_quantity( $stock );
		$v->set_low_stock_amount( 3 );
		$v->set_cogs_value( round( (float) $price * 0.6, 2 ) );
		$v->save();
		$as_original( $v->get_id(), 'post_product_variation', 'en' );
		++$made['variations'];
	}
	WC_Product_Variable::sync( $parent->get_id() );
	++$made['variable'];
}

// The item the concurrency harness sells the last units of (workstream item B6). Its stock is set by the
// harness before each run.
if ( ! wc_get_product_id_by_sku( 'DEMO-LOAD-001' ) ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Sample Product 99, concurrency test item' );
	$p->set_sku( 'DEMO-LOAD-001' );
	$p->set_regular_price( '100' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 5 );
	$p->set_backorders( 'no' );
	$p->set_status( 'publish' );
	$p->save();
	$as_original( $p->get_id(), 'post_product', 'en' );
	$place( $p->get_id(), $categories['accessory'], null );
	update_post_meta( $p->get_id(), '_mizzey_seed', 'translate' );
}

// One product in English only: it must still appear once in every report, in either language context.
if ( ! wc_get_product_id_by_sku( 'DEMO-EN-ONLY' ) ) {
	$p = new WC_Product_Simple();
	$p->set_name( 'Sample Product, English only' );
	$p->set_sku( 'DEMO-EN-ONLY' );
	$p->set_regular_price( '140' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 2 );
	$p->set_low_stock_amount( 3 );
	$p->set_status( 'publish' );
	$p->save();
	$as_original( $p->get_id(), 'post_product', 'en' );
	$place( $p->get_id(), $categories['home'], null );
	update_post_meta( $p->get_id(), '_mizzey_seed', 'english-only' );
	++$made['single_language'];
}

// One product in Arabic only, created as an Arabic original. The language is switched before the insert, as the
// baseline does for pages: WPML registers a new record under the current language.
if ( ! wc_get_product_id_by_sku( 'DEMO-AR-ONLY' ) ) {
	$sitepress->switch_lang( 'ar', true );
	$p = new WC_Product_Simple();
	$p->set_name( 'منتج تجريبي، بالعربية فقط' );
	$p->set_sku( 'DEMO-AR-ONLY' );
	$p->set_regular_price( '160' );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 0 );
	$p->set_low_stock_amount( 3 );
	$p->set_status( 'publish' );
	$p->save();
	$as_original( $p->get_id(), 'post_product', 'ar' );
	update_post_meta( $p->get_id(), '_mizzey_seed', 'arabic-only' );
	$sitepress->switch_lang( 'en', true );
	++$made['single_language'];
}

echo wp_json_encode( $made ), "\n";

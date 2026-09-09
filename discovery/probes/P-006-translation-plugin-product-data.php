<?php
/**
 * P-006. Does the chosen translation plugin translate Woo product data, including variations and attributes?
 *
 * FIX-04 puts a fully supported Arabic storefront in the first release as a fixed decision, and the
 * Technical Design section 11 carries a commercial translation licence at about 107 USD a year, paid by
 * the client every year, on the claim that the free options do not translate product data reliably.
 * That licence is the only meaningful recurring cost in the build, so the claim is worth testing.
 *
 * Tested against WPML, which is the choice, with WooCommerce Multilingual and String Translation.
 *
 * Method note: the seeded row said playwright. The question is about the data layer, not the screen, so
 * it is answered directly. A browser test would only observe the same rows through a template.
 */

$notes   = array();
$verdict = 'partial';
$made    = array();
$taxonomy = '';
$attr_id  = 0;

try {
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		throw new RuntimeException( 'WPML core is not active.' );
	}
	if ( ! class_exists( 'woocommerce_wpml' ) ) {
		throw new RuntimeException( 'WooCommerce Multilingual is not active.' );
	}
	global $sitepress, $wpdb;

	$langs = array_keys( (array) apply_filters( 'wpml_active_languages', null, array() ) );
	if ( ! in_array( 'ar', $langs, true ) ) {
		throw new RuntimeException( 'Arabic is not an active language; configure it before running this probe.' );
	}

	$notes[] = sprintf(
		'WPML %s with WooCommerce Multilingual %s and String Translation, on WooCommerce %s. Active languages: %s.',
		ICL_SITEPRESS_VERSION,
		defined( 'WCML_VERSION' ) ? WCML_VERSION : 'unknown',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		implode( ', ', $langs )
	);

	// A global attribute, the kind a real catalogue uses for filtering.
	$attr_id = wc_create_attribute( array( 'name' => 'P006 Size', 'slug' => 'p006size', 'type' => 'select' ) );
	if ( is_wp_error( $attr_id ) ) {
		throw new RuntimeException( 'Could not create the attribute: ' . $attr_id->get_error_message() );
	}
	$taxonomy = wc_attribute_taxonomy_name( 'p006size' );
	register_taxonomy( $taxonomy, 'product', array( 'hierarchical' => false, 'show_ui' => false ) );
	foreach ( array( 'Small', 'Large' ) as $term ) {
		wp_insert_term( $term, $taxonomy );
	}
	$term_ids = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );

	// A variable product using it.
	$product = new WC_Product_Variable();
	$product->set_name( 'P-006 probe product' );
	$product->set_description( 'English description for the probe.' );
	$product->set_status( 'publish' );
	$attribute = new WC_Product_Attribute();
	$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	$attribute->set_name( $taxonomy );
	$attribute->set_options( $term_ids );
	$attribute->set_visible( true );
	$attribute->set_variation( true );
	$product->set_attributes( array( $attribute ) );
	$product->save();
	$product_id = $product->get_id();
	$made[]     = $product_id;

	foreach ( array( 'Small', 'Large' ) as $value ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_attributes( array( $taxonomy => sanitize_title( $value ) ) );
		$variation->set_regular_price( 'Small' === $value ? '100' : '150' );
		$variation->save();
		$made[] = $variation->get_id();
	}
	wp_set_object_terms( $product_id, $term_ids, $taxonomy );

	$english = wc_get_product( $product_id );
	$notes[] = sprintf(
		'Seeded an English variable product with a global attribute (%s, 2 terms) and %d variations.',
		$taxonomy,
		count( $english->get_children() )
	);

	// Translate it. make_duplicate is WPML's own path for producing a translation of a post.
	$ar_id = $sitepress->make_duplicate( $product_id, 'ar' );
	if ( ! $ar_id ) {
		throw new RuntimeException( 'WPML returned no Arabic product id.' );
	}
	$made[] = $ar_id;

	$ar_product = wc_get_product( $ar_id );
	$ar_kids    = $ar_product ? $ar_product->get_children() : array();
	foreach ( $ar_kids as $kid ) {
		$made[] = $kid;
	}

	// Is the pair actually linked in WPML's own table, or two unrelated products?
	$trid   = $sitepress->get_element_trid( $product_id, 'post_product' );
	$linked = $trid ? $sitepress->get_element_translations( $trid, 'post_product' ) : array();

	$notes[] = sprintf(
		'WPML produced Arabic product %d. It is type %s, carries %d variations, and the pair share translation '
			. 'group %s holding languages [%s].',
		$ar_id,
		$ar_product ? $ar_product->get_type() : 'none',
		count( $ar_kids ),
		var_export( $trid, true ),
		implode( ', ', array_keys( (array) $linked ) )
	);

	// Attributes: are the taxonomy terms themselves translatable, which is what filtering depends on?
	$tax_rows = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE element_type = %s",
		'tax_' . $taxonomy
	) );
	$ar_attrs = $ar_product ? $ar_product->get_attributes() : array();

	$notes[] = sprintf(
		'The attribute taxonomy has %d row(s) registered in icl_translations as tax_%s, and the Arabic product '
			. 'carries %d attribute(s): %s.',
		$tax_rows,
		$taxonomy,
		count( $ar_attrs ),
		$ar_attrs ? implode( ', ', array_keys( $ar_attrs ) ) : 'none'
	);

	$product_translated  = (bool) $ar_product && count( array_keys( (array) $linked ) ) >= 2;
	$variations_carried  = count( $ar_kids ) === count( $english->get_children() ) && count( $ar_kids ) > 0;
	$attributes_carried  = count( $ar_attrs ) > 0 && $tax_rows > 0;

	if ( $product_translated && $variations_carried && $attributes_carried ) {
		$verdict = 'confirmed';
		$notes[] = 'Product, variations and attribute taxonomy all cross the language boundary and stay linked as '
			. 'one translation group. The design assumption holds and the recurring licence buys something real. '
			. 'Note what this does and does not settle: it shows the data model supports translated product data. '
			. 'It does not measure translation quality, and it does not test the free alternatives, so it '
			. 'justifies the line item without proving it is the cheapest way to satisfy FIX-04.';
	} elseif ( $product_translated && ! $variations_carried ) {
		$verdict = 'partial';
		$notes[] = 'The product translated but the variations did not follow. Variation-level translation would be '
			. 'Mizzey work on top of the licence, which is not what section 11 assumes.';
	} elseif ( ! $product_translated ) {
		$verdict = 'refuted';
		$notes[] = 'No linked Arabic product was produced. The assumption behind the licence line does not hold as '
			. 'configured, and this needs settling before the client is asked to buy it.';
	} else {
		$verdict = 'partial';
		$notes[] = 'Mixed: read the counts above before concluding.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	foreach ( array_unique( $made ) as $id ) {
		wp_delete_post( $id, true );
	}
	if ( $taxonomy ) {
		foreach ( (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) ) as $t ) {
			wp_delete_term( $t, $taxonomy );
		}
	}
	if ( $attr_id && ! is_wp_error( $attr_id ) ) {
		wc_delete_attribute( $attr_id );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

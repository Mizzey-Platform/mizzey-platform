<?php
/**
 * Staging seed, step 3: the Arabic records, in a process of their own.
 *
 * Step 2 created every source record and stopped. This step only translates. Keeping the two apart is the
 * migration sequencing invariant that finding A11 established: created first, translated second, never
 * interleaved. The WPML duplicate method is used, which A11 measured as safe, and each duplicate is then
 * released so that its Arabic title is its own.
 *
 * The step ends by checking what it made, and fails if a product's two language records are not one group.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

global $sitepress;

$arabic_terms = array(
	'product_cat'   => array(
		'sample-care'        => 'عناية تجريبية',
		'sample-home'        => 'منزل تجريبي',
		'sample-accessories' => 'إكسسوارات تجريبية',
	),
	'product_brand' => array(
		'sample-brand-north' => 'علامة تجريبية شمال',
		'sample-brand-south' => 'علامة تجريبية جنوب',
	),
	'product_collection' => array(
		'sample-collection' => 'مجموعة تجريبية',
	),
);

// Terms first, so a translated product has a translated category and brand to land in.
$term_map = array();
foreach ( $arabic_terms as $taxonomy => $names ) {
	foreach ( $names as $slug => $arabic ) {
		$sitepress->switch_lang( 'en', true );
		$source = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $source ) {
			WP_CLI::error( "missing source term {$slug}" );
		}
		$type  = 'tax_' . $taxonomy;
		$trid  = $sitepress->get_element_trid( (int) $source->term_taxonomy_id, $type );
		$group = $sitepress->get_element_translations( $trid, $type );
		if ( isset( $group['ar'] ) ) {
			$term_map[ $taxonomy ][ (int) $source->term_id ] = (int) $group['ar']->term_id;
			continue;
		}
		$sitepress->switch_lang( 'ar', true );
		$made = wp_insert_term( $arabic, $taxonomy, array( 'slug' => $slug . '-ar' ) );
		if ( is_wp_error( $made ) ) {
			WP_CLI::error( $made->get_error_message() );
		}
		$sitepress->set_element_language_details( (int) $made['term_taxonomy_id'], $type, $trid, 'ar', 'en' );
		$term_map[ $taxonomy ][ (int) $source->term_id ] = (int) $made['term_id'];
	}
}
$sitepress->switch_lang( 'en', true );

$sources = get_posts(
	array(
		'post_type'        => 'product',
		'post_status'      => 'publish',
		'numberposts'      => 200, // The seed makes fewer than twenty; never an unbounded query.
		'meta_key'         => '_mizzey_seed',
		'meta_value'       => 'translate',
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => true,
		'fields'           => 'ids',
	)
);

// A duplicate copies its original's custom fields, the seed marker among them, so a second run finds the Arabic
// records too. Only English originals are sources.
$language = static fn ( int $id ) => apply_filters( 'wpml_post_language_details', null, $id )['language_code'] ?? null;
$sources  = array_values( array_filter( array_map( 'intval', $sources ), static fn ( int $id ): bool => 'en' === $language( $id ) ) );

$translated = 0;
foreach ( $sources as $en_id ) {
	$ar_id = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
	if ( ! $ar_id || $ar_id === $en_id ) {
		$sitepress->make_duplicate( $en_id, 'ar' );
		$ar_id = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
	}
	$sitepress->switch_lang( 'en', true );
	if ( ! $ar_id || $ar_id === $en_id ) {
		WP_CLI::error( "no Arabic record was made for product {$en_id}" );
	}
	delete_post_meta( $ar_id, '_mizzey_seed' );
	// Release the duplicate, then give it its own Arabic name. The slug stays shared, as the baseline pages do.
	delete_post_meta( $ar_id, '_icl_lang_duplicate_of' );
	$number = preg_replace( '/\D+/', '', get_the_title( $en_id ) );
	$name   = false !== strpos( get_the_title( $en_id ), 'Variable' ) ? 'منتج تجريبي بخيارات ' : 'منتج تجريبي ';
	wp_update_post(
		array(
			'ID'           => $ar_id,
			'post_title'   => $name . $number,
			'post_excerpt' => 'منتج غير حقيقي لنسخة العرض التجريبية.',
		)
	);
	foreach ( $term_map as $taxonomy => $pairs ) {
		$ar_terms = array();
		foreach ( wp_get_object_terms( $en_id, $taxonomy, array( 'fields' => 'ids' ) ) as $en_term ) {
			if ( isset( $pairs[ (int) $en_term ] ) ) {
				$ar_terms[] = $pairs[ (int) $en_term ];
			}
		}
		if ( $ar_terms ) {
			$sitepress->switch_lang( 'ar', true );
			wp_set_object_terms( $ar_id, $ar_terms, $taxonomy );
			$sitepress->switch_lang( 'en', true );
		}
	}
	++$translated;
}

// Check what was made. One commercial item is one translation group of exactly two records, and a variant of
// one is a variant of the other with the same SKU and the same cost (ADM-27: the cost is the item's, whatever the
// language). The cost is compared as read back from storage. Every pair agreed on 5 October 2026 only because
// no record held a cost at all, so the count of pairs that do hold one is reported beside the check.
wp_cache_flush();
$same_cost = static function ( WC_Product $a, WC_Product $b ): bool {
	$x = $a->get_cogs_value();
	$y = $b->get_cogs_value();
	return null === $x || null === $y ? $x === $y : abs( (float) $x - (float) $y ) < 0.005;
};
$with_cost = 0;
$problems  = array();
foreach ( $sources as $en_id ) {
	$ar_id = (int) apply_filters( 'wpml_object_id', $en_id, 'product', false, 'ar' );
	if ( 'en' !== $language( $en_id ) || 'ar' !== $language( $ar_id ) || $ar_id === $en_id ) {
		$problems[] = "product {$en_id}: languages are " . wp_json_encode( array( $language( $en_id ), $language( $ar_id ) ) );
		continue;
	}
	$en = wc_get_product( $en_id );
	$ar = wc_get_product( $ar_id );
	if ( $en->get_sku() !== $ar->get_sku() ) {
		$problems[] = "product {$en_id}: SKU differs between the language records";
	}
	if ( ! $same_cost( $en, $ar ) ) {
		$problems[] = "product {$en_id}: cost differs between the language records";
	}
	$with_cost += null === $en->get_cogs_value() ? 0 : 1;
	if ( $en->is_type( 'variable' ) ) {
		$en_children = $en->get_children();
		$ar_children = $ar->get_children();
		if ( count( $en_children ) !== count( $ar_children ) ) {
			$problems[] = "product {$en_id}: " . count( $en_children ) . ' variants in English, ' . count( $ar_children ) . ' in Arabic';
		}
		foreach ( $en_children as $child ) {
			$ar_child = (int) apply_filters( 'wpml_object_id', $child, 'product_variation', false, 'ar' );
			if ( ! in_array( $ar_child, $ar_children, true ) ) {
				$problems[] = "variation {$child}: its Arabic record is not a variant of the Arabic product";
			} elseif ( wc_get_product( $child )->get_sku() !== wc_get_product( $ar_child )->get_sku() ) {
				$problems[] = "variation {$child}: SKU differs between the language records";
			} elseif ( ! $same_cost( wc_get_product( $child ), wc_get_product( $ar_child ) ) ) {
				$problems[] = "variation {$child}: cost differs between the language records";
			} else {
				$with_cost += null === wc_get_product( $child )->get_cogs_value() ? 0 : 1;
			}
		}
	}
}

if ( $problems ) {
	echo implode( "\n", $problems ), "\n";
	WP_CLI::error( "the seeded catalogue is not sound" );
}
echo wp_json_encode( array( 'translated' => $translated, 'terms' => array_map( 'count', $term_map ), 'pairs_holding_a_cost' => $with_cost, 'problems' => 0 ) ), "\n";

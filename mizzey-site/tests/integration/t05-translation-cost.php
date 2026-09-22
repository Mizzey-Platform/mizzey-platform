<?php
/**
 * t05, AC-3: does the Arabic copy of a product carry its cost?
 *
 * Two ways an Arabic product can come to exist under WPML 4.9.7 and WCML 5.5.7:
 *  (a) a WPML duplicate of the English product (the path discovery probe P-006 used), including a variable product;
 *  (b) a separately authored Arabic product linked to the English one as its translation, which is the Mizzey
 *      workflow (US-16-03: the two language versions are maintained separately).
 * For each path: cost at creation, and cost after a later edit of the English product's cost made the way the
 * wp-admin product form makes it: WooCommerce writes the product (meta included) and then save_post fires, which is
 * when WPML copies "copy" fields. This is a simulation of the admin request, not a browser test; the manual admin
 * check is part of UAT. Programmatic edits (importer, REST, WP-CLI) are t10.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

/** An edit made the way the wp-admin product form makes it: CRUD save, then save_post. */
function admin_edit_cost( int $id, float $cost ): void {
	$p = wc_get_product( $id );
	$p->set_cogs_value( $cost );
	$p->save();
	wp_update_post( array( 'ID' => $id ) );
}

function ar_cost( int $id ): ?float {
	$p = Scenario::fresh_product( $id );
	return $p ? $p->get_cogs_value() : null;
}

run(
	new Scenario( 't05-translation-cost', 'AC-3' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		if ( ! $sitepress ) {
			$s->note( 'WPML is not active.' );
			return false;
		}
		$s->enable_cogs();
		$s->note( 'WPML setting for _cogs_value: ' . var_export( $sitepress->get_setting( 'translation-management' )['custom_fields_translation']['_cogs_value'] ?? 'unset', true ) );

		// (a) WPML duplicate.
		$en_a = $s->simple_product( 't05a EN', '300', 120.0 );
		$ar_a = (int) $sitepress->make_duplicate( $en_a->get_id(), 'ar' );
		$s->track_post( $ar_a );
		$a_created = ar_cost( $ar_a );
		admin_edit_cost( $en_a->get_id(), 130.0 );
		$a_edited = ar_cost( $ar_a );
		$s->note( sprintf( '(a) duplicate: EN 120 -> AR %s at creation; EN edited to 130 -> AR %s', var_export( $a_created, true ), var_export( $a_edited, true ) ) );

		// (a) duplicate of a variable product: do the variations carry cost?
		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Size' );
		$attr->set_options( array( '50ml', '100ml' ) );
		$attr->set_visible( true );
		$attr->set_variation( true );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 't05a EN variable' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attr ) );
		$parent->save();
		$s->track_post( $parent->get_id() );
		foreach ( array( '50ml' => 40.0, '100ml' => 70.0 ) as $size => $cost ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $parent->get_id() );
			$v->set_attributes( array( 'size' => $size ) );
			$v->set_regular_price( '200' );
			$v->set_cogs_value( $cost );
			$v->save();
			$s->track_post( $v->get_id() );
		}
		$ar_parent = (int) $sitepress->make_duplicate( $parent->get_id(), 'ar' );
		$s->track_post( $ar_parent );
		$ar_var = wc_get_product( $ar_parent );
		$ar_var_costs = array();
		foreach ( $ar_var ? $ar_var->get_children() : array() as $child ) {
			$s->track_post( (int) $child );
			$ar_var_costs[] = ar_cost( (int) $child );
		}
		$s->note( '(a) duplicate of variable product: AR variation costs ' . wp_json_encode( $ar_var_costs ) . ' (EN 40, 70)' );

		// (b) separately authored Arabic product, linked as the translation.
		$en_b = $s->simple_product( 't05b EN', '300', 150.0 );
		$ar_product = new \WC_Product_Simple();
		$ar_product->set_name( 't05b AR عطر' );
		$ar_product->set_regular_price( '300' );
		$ar_product->set_status( 'publish' );
		$ar_product->save();
		$ar_b = $ar_product->get_id();
		$s->track_post( $ar_b );
		$trid = apply_filters( 'wpml_element_trid', null, $en_b->get_id(), 'post_product' );
		do_action( 'wpml_set_element_language_details', array(
			'element_id'           => $ar_b,
			'element_type'         => 'post_product',
			'trid'                 => $trid,
			'language_code'        => 'ar',
			'source_language_code' => 'en',
		) );
		$linked = (int) apply_filters( 'wpml_object_id', $en_b->get_id(), 'product', false, 'ar' );
		$b_linked = ar_cost( $ar_b );
		wp_update_post( array( 'ID' => $en_b->get_id() ) );
		$b_after_save = ar_cost( $ar_b );
		admin_edit_cost( $en_b->get_id(), 160.0 );
		$b_edited = ar_cost( $ar_b );
		$s->note( sprintf( '(b) separate translation (linked: %s): EN 150 -> AR %s after linking, %s after the English product is next saved; EN edited to 160 -> AR %s',
			$linked === $ar_b ? 'yes' : 'NO', var_export( $b_linked, true ), var_export( $b_after_save, true ), var_export( $b_edited, true ) ) );

		$ok_a = 120.0 === $a_created && 130.0 === $a_edited && array( 40.0, 70.0 ) === $ar_var_costs;
		$ok_b = $linked === $ar_b && 150.0 === $b_after_save && 160.0 === $b_edited;
		$s->note( 'Path (a) ' . ( $ok_a ? 'carries' : 'does NOT carry' ) . ' cost; path (b) ' . ( $ok_b ? 'carries' : 'does NOT carry' ) . ' cost' );
		return $ok_a && $ok_b;
	}
);

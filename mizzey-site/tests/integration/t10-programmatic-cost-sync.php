<?php
/**
 * t10, AC-3 (gap check): when an English product's cost is changed programmatically, the way the CSV importer, the
 * REST API and WP-CLI change it (WooCommerce CRUD save only, no save_post afterwards), does the Arabic translation
 * follow?
 *
 * Expected to FAIL until a decision is made (verification.md, open item WPML-1). WPML copies "copy" fields only in
 * its save_post handler, and a CRUD save that changes only meta does not fire save_post after the meta is written.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't10-programmatic-cost-sync', 'AC-3 (WPML-1)' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		if ( ! $sitepress ) {
			$s->note( 'WPML is not active.' );
			return false;
		}
		$s->enable_cogs();
		$en = $s->simple_product( 't10 EN', '300', 100.0 );
		$ar = (int) $sitepress->make_duplicate( $en->get_id(), 'ar' );
		$s->track_post( $ar );
		$at_creation = Scenario::fresh_product( $ar )->get_cogs_value();

		$product = wc_get_product( $en->get_id() );
		$product->set_cogs_value( 125.0 );
		$product->save();
		$after_crud = Scenario::fresh_product( $ar )->get_cogs_value();

		$s->note( sprintf( 'AR at creation %s; EN changed to 125 by CRUD save only -> AR %s', var_export( $at_creation, true ), var_export( $after_crud, true ) ) );
		return 125.0 === $after_crud;
	}
);

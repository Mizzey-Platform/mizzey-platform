<?php
/**
 * t17 (probe B1): does one physical item hold one effective stock balance across its language versions?
 *
 * Measurement only. No fix is implemented here, and none may be implemented until this scenario has run.
 *
 * Why it exists. The product-cost pilot found that WooCommerce's data store skips wp_update_post() on a meta-only
 * save, so save_post never fires and WPML and WCML never copy the field. Stock is copied to translations by
 * WCML's own synchronisation component on save_post, while order stock reduction goes through
 * wc_update_product_stock(), which the data store implements as arithmetic SQL (P-009). If that write does not
 * fire save_post, each language version keeps its own quantity and one physical unit could be sold twice. P-020
 * already established that a translated pair holds one stock row per language version.
 *
 * Fixtures: a simple product with stock 5, a simple product with stock 1, a variable product whose variations are
 * stocked independently, and an untranslated control that separates WPML from WooCommerce.
 *
 * For every step, both language records are read from four places, because they can disagree: the _stock and
 * _stock_status post meta, the wc_product_meta_lookup row, WC_Product::get_stock_quantity(), and the
 * manage_stock setting. The hooks that fired during the step are recorded in the same request.
 *
 * Verdict: this scenario reports facts and gives no pass or fail. The classification (measured working, measured
 * defect, or partial and workflow-dependent) is recorded in the verification record from what it prints.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Everything that matters about one product's stock, read from storage rather than from an in-memory copy. */
function t17_read( int $id ): array {
	wp_cache_flush();
	global $wpdb;
	$product = wc_get_product( $id );
	$lookup  = $wpdb->get_row( $wpdb->prepare(
		"SELECT stock_quantity, stock_status FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d",
		$id
	) );
	$type = 'product_variation' === get_post_type( $id ) ? 'post_product_variation' : 'post_product';

	// Both the filter's answer and the database row, because the pilot established that WPML's cached identity
	// answers can disagree with its own rows inside one long-running process.
	$db_trid = $wpdb->get_var( $wpdb->prepare(
		"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s",
		$id,
		$type
	) );

	return array(
		'id'            => $id,
		'trid'          => (int) apply_filters( 'wpml_element_trid', null, $id, $type ),
		'db_trid'       => null === $db_trid ? '(no row)' : (int) $db_trid,
		'lang'          => (string) apply_filters( 'wpml_post_language_details', null, $id )['language_code'] ?? '',
		'sku'           => $product ? (string) $product->get_sku() : '(no product)',
		'meta_stock'    => get_post_meta( $id, '_stock', true ),
		'meta_status'   => get_post_meta( $id, '_stock_status', true ),
		'lookup_stock'  => null === $lookup ? '(no row)' : $lookup->stock_quantity,
		'lookup_status' => null === $lookup ? '(no row)' : $lookup->stock_status,
		'crud_stock'    => $product ? $product->get_stock_quantity() : null,
		'manage_stock'  => $product ? ( $product->get_manage_stock() ? 'yes' : 'no' ) : '-',
		'post_title'    => (string) get_post_field( 'post_title', $id ),
		'element_type'  => (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT element_type FROM {$wpdb->prefix}icl_translations WHERE element_id = %d LIMIT 1", $id ) ),
	);
}

/** One line per record, short enough to read in a table. */
function t17_fmt( array $r ): string {
	return sprintf(
		'id=%d lang=%s trid=%d/db=%s sku=%s | _stock=%s _stock_status=%s | lookup=%s/%s | crud=%s | manage=%s | title="%s" type=%s',
		$r['id'],
		$r['lang'] ?: '?',
		$r['trid'],
		var_export( $r['db_trid'], true ),
		$r['sku'],
		'' === $r['meta_stock'] ? '(empty)' : var_export( $r['meta_stock'], true ),
		'' === $r['meta_status'] ? '(empty)' : $r['meta_status'],
		var_export( $r['lookup_stock'], true ),
		var_export( $r['lookup_status'], true ),
		var_export( $r['crud_stock'], true ),
		$r['manage_stock'],
		$r['post_title'],
		$r['element_type']
	);
}

run(
	new Scenario( 't17-multilingual-stock', 'B1 (probe, no criterion)' ),
	function ( Scenario $s ): ?bool {
		$w = new Workflows( $s );

		// ---- Hook recorder: what fired, for which post, in this request ------------------------------------
		$fired    = array();
		$recorder = function ( $tag ) use ( &$fired ) {
			return function ( $arg = null ) use ( $tag, &$fired ) {
				$id = is_object( $arg ) && method_exists( $arg, 'get_id' ) ? $arg->get_id() : ( is_numeric( $arg ) ? (int) $arg : 0 );
				$fired[] = $id ? "$tag($id)" : $tag;
			};
		};
		foreach ( array(
			'save_post',
			'save_post_product',
			'save_post_product_variation',
			'woocommerce_update_product',
			'woocommerce_update_product_variation',
			'woocommerce_product_set_stock',
			'woocommerce_variation_set_stock',
			'woocommerce_reduce_order_stock',
			'woocommerce_restore_order_stock',
		) as $hook ) {
			add_action( $hook, $recorder( $hook ), 50, 1 );
		}
		$reset_hooks = function () use ( &$fired ) {
			$fired = array();
		};
		$hooks_of = function ( int ...$ids ) use ( &$fired ): string {
			$keep = array();
			foreach ( $fired as $entry ) {
				foreach ( $ids as $id ) {
					if ( false !== strpos( $entry, "($id)" ) ) {
						$keep[] = $entry;
						break;
					}
				}
			}
			return $keep ? implode( ', ', $keep ) : 'none for these posts';
		};

		// ---- Fixtures ---------------------------------------------------------------------------------------
		$f1_en = $w->simple( 'B1-FIVE', null );
		$w->translate( 'duplicate', $f1_en );
		$f1_ar = Workflows::ar_of( $f1_en );

		$f2_en = $w->simple( 'B1-ONE', null );
		$p     = wc_get_product( $f2_en );
		$p->set_stock_quantity( 1 );
		$p->save();
		$w->translate( 'duplicate', $f2_en );
		$f2_ar = Workflows::ar_of( $f2_en );

		list( $f3_pid, $f3_v ) = $w->variable( 'B1-VAR', array( 'S' => null, 'L' => null ) );
		$w->translate( 'editor', $f3_pid );
		$f3_v_ar = array_map( array( Workflows::class, 'ar_of' ), $f3_v );

		$f4 = $w->simple( 'B1-CONTROL', null );

		if ( ! $f1_ar || ! $f2_ar || in_array( 0, $f3_v_ar, true ) ) {
			$s->note( sprintf(
				'FIXTURES FAILED: f1_ar=%d f2_ar=%d f3 variations ar=%s',
				$f1_ar,
				$f2_ar,
				implode( ',', $f3_v_ar )
			) );
			return false;
		}

		$s->note( sprintf(
			'Fixtures: F1 simple stock 5 (EN %d / AR %d), F2 simple stock 1 (EN %d / AR %d), F3 variable (EN %d, variations EN %s / AR %s), F4 untranslated control (%d)',
			$f1_en, $f1_ar, $f2_en, $f2_ar, $f3_pid,
			implode( ',', $f3_v ),
			implode( ',', $f3_v_ar ),
			$f4
		) );

		// ---- Step 1: baseline -------------------------------------------------------------------------------
		$s->note( '--- step 1: baseline, before any order ---' );
		foreach ( array(
			'F1 EN' => $f1_en,
			'F1 AR' => $f1_ar,
			'F2 EN' => $f2_en,
			'F2 AR' => $f2_ar,
			'F3 EN S' => $f3_v[0],
			'F3 AR S' => $f3_v_ar[0],
			'F3 EN L' => $f3_v[1],
			'F3 AR L' => $f3_v_ar[1],
			'F4 control' => $f4,
		) as $label => $id ) {
			$s->note( sprintf( '  %-11s %s', $label, t17_fmt( t17_read( $id ) ) ) );
		}

		/**
		 * Place one order in $lang for $order_id, then report both records.
		 *
		 * @param int[] $watch The two ids whose hooks are reported.
		 */
		$step = function ( string $label, string $lang, int $order_id, int $en, int $ar, array $watch )
			use ( $s, $w, $reset_hooks, $hooks_of ) {
			$before_en = t17_read( $en );
			$before_ar = t17_read( $ar );
			$reset_hooks();

			do_action( 'wpml_switch_language', $lang );
			wp_cache_flush();
			$product = wc_get_product( $order_id );
			$ok      = $product instanceof \WC_Product;
			if ( $ok ) {
				$order = $s->order_for( $product, 1 );
				// The harness sets processing, which is what reduces stock in WooCommerce. Make it explicit so a
				// missing reduction is a measured fact about the translation and not about the harness.
				wc_maybe_reduce_stock_levels( $order->get_id() );
			}
			do_action( 'wpml_switch_language', 'en' );

			$after_en = t17_read( $en );
			$after_ar = t17_read( $ar );

			$s->note( sprintf( '--- %s: ordered 1 of id=%d in %s %s ---', $label, $order_id, $lang, $ok ? '' : '(PRODUCT COULD NOT BE LOADED)' ) );
			$s->note( sprintf( '  EN before  %s', t17_fmt( $before_en ) ) );
			$s->note( sprintf( '  EN after   %s', t17_fmt( $after_en ) ) );
			$s->note( sprintf( '  AR before  %s', t17_fmt( $before_ar ) ) );
			$s->note( sprintf( '  AR after   %s', t17_fmt( $after_ar ) ) );
			$s->note( sprintf( '  moved: EN %s -> %s, AR %s -> %s',
				var_export( $before_en['crud_stock'], true ), var_export( $after_en['crud_stock'], true ),
				var_export( $before_ar['crud_stock'], true ), var_export( $after_ar['crud_stock'], true ) ) );
			$s->note( sprintf( '  hooks:  %s', $hooks_of( ...$watch ) ) );

			return array( $before_en, $after_en, $before_ar, $after_ar );
		};

		// ---- Steps 2 to 5: ordinary orders, both languages, product and variation ---------------------------
		$step( 'step 2, English order on F1', 'en', $f1_en, $f1_en, $f1_ar, array( $f1_en, $f1_ar ) );
		$step( 'step 3, Arabic order on F1 (the core question)', 'ar', $f1_ar, $f1_en, $f1_ar, array( $f1_en, $f1_ar ) );
		$step( 'step 4, Arabic order on F3 variation S', 'ar', $f3_v_ar[0], $f3_v[0], $f3_v_ar[0], array( $f3_v[0], $f3_v_ar[0] ) );
		$s->note( sprintf( '  sibling check, F3 L: EN %s / AR %s', t17_fmt( t17_read( $f3_v[1] ) ), t17_fmt( t17_read( $f3_v_ar[1] ) ) ) );
		$step( 'step 5, English order on F3 variation S', 'en', $f3_v[0], $f3_v[0], $f3_v_ar[0], array( $f3_v[0], $f3_v_ar[0] ) );

		// ---- Step 6: the last unit, Arabic first ------------------------------------------------------------
		$s->note( '--- step 6: the last unit of F2, Arabic first then English ---' );
		$step( 'step 6a, Arabic order on F2 (stock 1)', 'ar', $f2_ar, $f2_en, $f2_ar, array( $f2_en, $f2_ar ) );
		$en_sellable = wc_get_product( $f2_en );
		$s->note( sprintf(
			'  after the Arabic sale, the ENGLISH record reports: in_stock=%s, has_enough_stock(1)=%s, quantity=%s',
			$en_sellable && $en_sellable->is_in_stock() ? 'YES' : 'no',
			$en_sellable && $en_sellable->has_enough_stock( 1 ) ? 'YES' : 'no',
			var_export( $en_sellable ? $en_sellable->get_stock_quantity() : null, true )
		) );
		$step( 'step 6b, English order on the same item', 'en', $f2_en, $f2_en, $f2_ar, array( $f2_en, $f2_ar ) );

		// ---- Step 7: the last unit, English first, on a fresh pair ------------------------------------------
		$s->note( '--- step 7: the last unit, English first then Arabic, on a fresh pair ---' );
		$f5_en = $w->simple( 'B1-ONE2', null );
		$p     = wc_get_product( $f5_en );
		$p->set_stock_quantity( 1 );
		$p->save();
		$w->translate( 'duplicate', $f5_en );
		$f5_ar = Workflows::ar_of( $f5_en );
		if ( ! $f5_ar ) {
			$s->note( '  FIXTURE FAILED for step 7' );
		} else {
			$step( 'step 7a, English order on F5 (stock 1)', 'en', $f5_en, $f5_en, $f5_ar, array( $f5_en, $f5_ar ) );
			$ar_sellable = wc_get_product( $f5_ar );
			$s->note( sprintf(
				'  after the English sale, the ARABIC record reports: in_stock=%s, has_enough_stock(1)=%s, quantity=%s',
				$ar_sellable && $ar_sellable->is_in_stock() ? 'YES' : 'no',
				$ar_sellable && $ar_sellable->has_enough_stock( 1 ) ? 'YES' : 'no',
				var_export( $ar_sellable ? $ar_sellable->get_stock_quantity() : null, true )
			) );
			$step( 'step 7b, Arabic order on the same item', 'ar', $f5_ar, $f5_en, $f5_ar, array( $f5_en, $f5_ar ) );
		}

		// ---- Step 8: cancellation and restoration ----------------------------------------------------------
		$s->note( '--- step 8: cancellation and restoration (WooCommerce only, no ERP) ---' );
		$f6_en = $w->simple( 'B1-CANCEL', null );
		$w->translate( 'duplicate', $f6_en );
		$f6_ar = Workflows::ar_of( $f6_en );
		if ( ! $f6_ar ) {
			$s->note( '  FIXTURE FAILED for step 8' );
		} else {
			do_action( 'wpml_switch_language', 'ar' );
			wp_cache_flush();
			$ar_product = wc_get_product( $f6_ar );
			$order      = $s->order_for( $ar_product, 2 );
			wc_maybe_reduce_stock_levels( $order->get_id() );
			do_action( 'wpml_switch_language', 'en' );
			$s->note( sprintf( '  after an Arabic order of 2: EN %s', t17_fmt( t17_read( $f6_en ) ) ) );
			$s->note( sprintf( '                              AR %s', t17_fmt( t17_read( $f6_ar ) ) ) );

			$reset_hooks();
			$order->set_status( 'cancelled' );
			$order->save();
			wc_maybe_increase_stock_levels( $order->get_id() );
			$s->note( sprintf( '  after cancelling it:        EN %s', t17_fmt( t17_read( $f6_en ) ) ) );
			$s->note( sprintf( '                              AR %s', t17_fmt( t17_read( $f6_ar ) ) ) );
			$s->note( sprintf( '  hooks:  %s', $hooks_of( $f6_en, $f6_ar ) ) );
		}

		// ---- Step 9: repeated runs, for cache-dependent behaviour ------------------------------------------
		$s->note( '--- step 9: three more Arabic orders on F1, looking for cache-dependent behaviour ---' );
		for ( $i = 1; $i <= 3; $i++ ) {
			do_action( 'wpml_switch_language', 'ar' );
			wp_cache_flush();
			$ar_product = wc_get_product( $f1_ar );
			$order      = $s->order_for( $ar_product, 1 );
			wc_maybe_reduce_stock_levels( $order->get_id() );
			do_action( 'wpml_switch_language', 'en' );
			$s->note( sprintf( '  run %d: EN crud=%s lookup=%s | AR crud=%s lookup=%s',
				$i,
				var_export( t17_read( $f1_en )['crud_stock'], true ),
				var_export( t17_read( $f1_en )['lookup_stock'], true ),
				var_export( t17_read( $f1_ar )['crud_stock'], true ),
				var_export( t17_read( $f1_ar )['lookup_stock'], true )
			) );
		}

		// ---- Step 10: the untranslated control -------------------------------------------------------------
		$s->note( '--- step 10: untranslated control, to separate WPML from WooCommerce ---' );
		$before = t17_read( $f4 );
		do_action( 'wpml_switch_language', 'en' );
		$order = $s->order_for( wc_get_product( $f4 ), 1 );
		wc_maybe_reduce_stock_levels( $order->get_id() );
		$s->note( sprintf( '  before %s', t17_fmt( $before ) ) );
		$s->note( sprintf( '  after  %s', t17_fmt( t17_read( $f4 ) ) ) );

		// ---- What WCML registers for stock, recorded as a fact ---------------------------------------------
		global $wp_filter;
		$stock_hooks = array();
		foreach ( array( 'save_post', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_reduce_order_stock' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( $cbs as $cb ) {
					$fn = $cb['function'];
					$name = is_array( $fn )
						? ( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0] ) . '::' . $fn[1]
						: ( is_string( $fn ) ? $fn : '(closure)' );
					if ( false !== stripos( $name, 'wcml' ) || false !== stripos( $name, 'sitepress' ) || false !== stripos( $name, 'wpml' ) ) {
						$stock_hooks[] = sprintf( '%s@%d %s', $hook, $prio, $name );
					}
				}
			}
		}
		$s->note( '--- which WPML and WCML callbacks are registered on the stock-relevant hooks ---' );
		foreach ( array_unique( $stock_hooks ) as $line ) {
			$s->note( '  ' . $line );
		}
		if ( ! $stock_hooks ) {
			$s->note( '  none' );
		}

		$s->note( 'This scenario measures and does not judge. No stock synchronisation fix is implemented here.' );
		return null; // fact-finding: no pass or fail
	}
);

<?php
/**
 * t19 (probe A11): does the B1 translation-group corruption reproduce through the supported workflows?
 *
 * Measurement only. No fix is implemented here. If sequencing alone prevents the defect, a migration runbook
 * invariant is preferred over custom runtime code, and that choice belongs to the owning PBI, not to this probe.
 *
 * B1 (t17) measured a defect: creating a product after a WCML translation of a variable product, inside one
 * long-running WP-CLI process, overwrote the Arabic variations' titles and rewrote the variation's
 * icl_translations row into a neighbouring trid. Once detached, stock stopped synchronising and overselling
 * became possible. The open question is whether that is a WP-CLI artefact, a general long-process WPML defect,
 * or specifically a migration sequencing risk.
 *
 * Seven workflows are compared. Each builds a translated variable product, then creates further products, and the
 * translation group is read from the database before and after.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/**
 * Everything A11 has to record about one post, read from the database rather than through a WPML cache.
 *
 * @return array<string,mixed>
 */
function t19_record( int $id ): array {
	wp_cache_flush();
	global $wpdb;
	$p   = wc_get_product( $id );
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT trid, element_type, language_code, source_language_code
		 FROM {$wpdb->prefix}icl_translations
		 WHERE element_id = %d AND element_type LIKE 'post_product%%' LIMIT 1",
		$id
	) );
	$attrs = array();
	if ( $p instanceof \WC_Product_Variation ) {
		foreach ( $p->get_attributes() as $k => $v ) {
			$attrs[] = "$k=$v";
		}
	}

	return array(
		'id'       => $id,
		'type'     => $p ? $p->get_type() : '(no product)',
		'trid'     => null === $row ? null : (int) $row->trid,
		'el_type'  => $row->element_type ?? null,
		'lang'     => $row->language_code ?? null,
		'src'      => $row->source_language_code ?? null,
		'title'    => (string) get_post_field( 'post_title', $id ),
		'sku'      => $p ? (string) $p->get_sku() : '',
		'stock'    => $p ? $p->get_stock_quantity() : null,
		'price'    => $p ? (string) $p->get_regular_price() : '',
		'attrs'    => implode( ',', $attrs ),
		'children' => $p && $p->is_type( 'variable' ) ? count( $p->get_children() ) : null,
	);
}

function t19_fmt( array $r ): string {
	return sprintf(
		'id=%d type=%s trid=%s el=%s lang=%s src=%s | title="%s" sku=%s stock=%s price=%s attrs=%s children=%s',
		$r['id'],
		$r['type'],
		var_export( $r['trid'], true ),
		var_export( $r['el_type'], true ),
		var_export( $r['lang'], true ),
		var_export( $r['src'], true ),
		$r['title'],
		$r['sku'] ?: '(none)',
		var_export( $r['stock'], true ),
		$r['price'] ?: '(none)',
		$r['attrs'] ?: '(none)',
		var_export( $r['children'], true )
	);
}

/** What moved between two records, named field by field. */
function t19_diff( array $a, array $b ): array {
	$moved = array();
	foreach ( array( 'trid', 'el_type', 'lang', 'src', 'title', 'sku', 'stock', 'price', 'attrs', 'type', 'children' ) as $k ) {
		if ( $a[ $k ] !== $b[ $k ] ) {
			$moved[] = sprintf( '%s %s -> %s', $k, var_export( $a[ $k ], true ), var_export( $b[ $k ], true ) );
		}
	}
	return $moved;
}

run(
	new Scenario( 't19-translation-group-integrity', 'A11 (probe, no criterion)' ),
	function ( Scenario $s ): ?bool {
		$w = new Workflows( $s );
		$verdicts = array();

		/**
		 * Build one translated variable product, run $after, and report what moved.
		 *
		 * @param callable $translate fn(int $parent_id): void
		 * @param callable $after     fn(): void, the work done afterwards in the same process
		 */
		$case = function ( string $label, string $tag, callable $translate, callable $after ) use ( $w, $s, &$verdicts ) {
			list( $pid, $vids ) = $w->variable( $tag, array( 'S' => null, 'L' => null ) );
			$translate( $pid );
			$ar_parent = (int) apply_filters( 'wpml_object_id', $pid, 'product', false, 'ar' );
			$ar_vids   = array_map( array( Workflows::class, 'ar_of' ), $vids );

			if ( ! $ar_parent || in_array( 0, $ar_vids, true ) ) {
				$s->note( sprintf( '  %s: translation incomplete (parent %d, variations %s), not measured', $label, $ar_parent, implode( ',', $ar_vids ) ) );
				$verdicts[ $label ] = 'not measured';
				return;
			}

			$before = array(
				'EN parent' => t19_record( $pid ),
				'EN var S'  => t19_record( $vids[0] ),
				'AR parent' => t19_record( $ar_parent ),
				'AR var S'  => t19_record( $ar_vids[0] ),
				'AR var L'  => t19_record( $ar_vids[1] ),
			);
			$s->note( sprintf( '  --- %s ---', $label ) );
			foreach ( $before as $k => $r ) {
				$s->note( sprintf( '    before %-10s %s', $k, t19_fmt( $r ) ) );
			}

			$after();

			$moved_any = false;
			foreach ( $before as $k => $r ) {
				$now   = t19_record( $r['id'] );
				$moved = t19_diff( $r, $now );
				if ( $moved ) {
					$moved_any = true;
					$s->note( sprintf( '    MOVED  %-10s %s', $k, implode( '; ', $moved ) ) );
				}
			}
			if ( ! $moved_any ) {
				$s->note( '    nothing moved on any of the five records' );
			}

			// The consequence that matters: does an Arabic variation sale still reach the English original?
			$en_before = wc_get_product( $vids[0] )->get_stock_quantity();
			do_action( 'wpml_switch_language', 'ar' );
			wp_cache_flush();
			$ar_product = wc_get_product( $ar_vids[0] );
			if ( $ar_product ) {
				$order = $s->order_for( $ar_product, 1 );
				wc_maybe_reduce_stock_levels( $order->get_id() );
			}
			do_action( 'wpml_switch_language', 'en' );
			$en_after = wc_get_product( $vids[0] )->get_stock_quantity();
			$synced   = ( null !== $en_after && (int) $en_after === (int) $en_before - 1 );

			$verdicts[ $label ] = $synced ? 'intact' : 'CORRUPTED';
			$s->note( sprintf(
				'    consequence: an Arabic variation sale moved EN %s -> %s  ==> %s',
				var_export( $en_before, true ),
				var_export( $en_after, true ),
				$synced ? 'group intact, stock synchronised' : '*** GROUP DETACHED, stock diverged, overselling possible ***'
			) );
		};

		$editor    = function ( int $pid ) use ( $w ) {
			$w->translate( 'editor', $pid );
		};
		$duplicate = function ( int $pid ) use ( $w ) {
			$w->translate( 'duplicate', $pid );
		};

		// --- 1. Control: the known WP-CLI reproducer -------------------------------------------------------
		$s->note( '=== 1. WP-CLI long-running process, one product created afterwards (the known reproducer) ===' );
		$case( 'wpcli-create-after', 'A11-1', $editor, function () use ( $w ) {
			$w->simple( 'A11-1-AFTER', null );
		} );

		// --- 2. Control: nothing created afterwards --------------------------------------------------------
		$s->note( '=== 2. WP-CLI, nothing created afterwards (negative control) ===' );
		$case( 'wpcli-nothing-after', 'A11-2', $editor, function () {} );

		// --- 3. A realistic migration batch ---------------------------------------------------------------
		$s->note( '=== 3. A migration-shaped batch: ten products created after the translation, one process ===' );
		$case( 'batch-ten-after', 'A11-3', $editor, function () use ( $w ) {
			for ( $i = 1; $i <= 10; $i++ ) {
				$w->simple( 'A11-3-B' . $i, null );
			}
		} );

		// --- 4. Sources first, translations second --------------------------------------------------------
		// The sequencing the MIG-13 precondition already recommends, measured rather than assumed.
		$s->note( '=== 4. All source products created first, translations only afterwards ===' );
		list( $pid4, $vids4 ) = $w->variable( 'A11-4', array( 'S' => null, 'L' => null ) );
		for ( $i = 1; $i <= 10; $i++ ) {
			$w->simple( 'A11-4-B' . $i, null );
		}
		$w->translate( 'editor', $pid4 );
		$ar4 = array_map( array( Workflows::class, 'ar_of' ), $vids4 );
		if ( in_array( 0, $ar4, true ) ) {
			$s->note( '  sources-first: Arabic variations not created, not measured' );
			$verdicts['sources-first-then-translate'] = 'not measured';
		} else {
			$r_before = t19_record( $ar4[0] );
			$s->note( sprintf( '    AR var S after the whole batch: %s', t19_fmt( $r_before ) ) );
			$en_before = wc_get_product( $vids4[0] )->get_stock_quantity();
			do_action( 'wpml_switch_language', 'ar' );
			wp_cache_flush();
			$order = $s->order_for( wc_get_product( $ar4[0] ), 1 );
			wc_maybe_reduce_stock_levels( $order->get_id() );
			do_action( 'wpml_switch_language', 'en' );
			$en_after = wc_get_product( $vids4[0] )->get_stock_quantity();
			$ok       = ( null !== $en_after && (int) $en_after === (int) $en_before - 1 );
			$verdicts['sources-first-then-translate'] = $ok ? 'intact' : 'CORRUPTED';
			$s->note( sprintf( '    consequence: EN %s -> %s ==> %s',
				var_export( $en_before, true ), var_export( $en_after, true ),
				$ok ? 'group intact' : '*** GROUP DETACHED ***' ) );
		}

		// --- 5. Interleaved source and translation creation -----------------------------------------------
		$s->note( '=== 5. Interleaved: create, translate, create, translate ===' );
		$s->note( '  (the second pair is the one measured, after a translation already happened in this process)' );
		list( $pid5a, $v5a ) = $w->variable( 'A11-5A', array( 'S' => null, 'L' => null ) );
		$w->translate( 'editor', $pid5a );
		$case( 'interleaved', 'A11-5B', $editor, function () use ( $w ) {
			$w->simple( 'A11-5-AFTER', null );
		} );

		// --- 6. The duplicate translation method, same trigger --------------------------------------------
		$s->note( '=== 6. WPML duplicate instead of the WCML editor, one product created afterwards ===' );
		$case( 'duplicate-create-after', 'A11-6', $duplicate, function () use ( $w ) {
			$w->simple( 'A11-6-AFTER', null );
		} );

		// --- 7. The native CSV importer as the creator afterwards -----------------------------------------
		$s->note( '=== 7. The native CSV importer creates the later products ===' );
		$case( 'importer-create-after', 'A11-7', $editor, function () use ( $w, $s ) {
			// One importer request creates several products inside one process, which is the shape a catalogue
			// migration takes. This is a create, not the update the cost scenarios use.
			$result = $w->import_create( array( 'A11-7-I1', 'A11-7-I2', 'A11-7-I3' ) );
			$s->note( sprintf( '    native CSV importer: %s', $result ) );
		} );

		// --- Summary ---------------------------------------------------------------------------------------
		$s->note( '=== A11 summary ===' );
		foreach ( $verdicts as $label => $verdict ) {
			$s->note( sprintf( '  %-32s %s', $label, $verdict ) );
		}
		$s->note( 'wp-admin itself is one request per save, so the single-request case is covered by case 2 and by the per-request nature of the admin channel; the wp-admin translation screen is a browser flow this runtime cannot drive and stays a staging check.' );
		$s->note( 'No fix is implemented here. If sequencing alone prevents the defect, a migration runbook invariant is preferred over runtime code.' );

		return null; // fact-finding
	}
);

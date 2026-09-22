<?php
/**
 * t12, AC-3: product and variation identity in the cost synchronisation.
 *
 * t11 proves the outcome in every channel. This scenario proves the sync reaches the right posts and only those:
 *   1. the source-language original is the one WPML calls the original, and the translation is resolved through
 *      WPML's own relationship (trid), not by guessing;
 *   2. a simple product's cost reaches its own translation, and nothing else is written;
 *   3. each variation's cost reaches its corresponding variation, not another variation of the same product;
 *   4. a product with no translation, and a translation that has been deleted, write nothing anywhere;
 *   5. a cost written on the translation does not overwrite the original, and the next save of the original
 *      restores the translation.
 * Creating a translation after a cost already exists is covered by t11 part 1 (both methods, both product types).
 *
 * Every write is observed through woocommerce_update_product(_variation), so "nothing else is written" is a
 * recorded fact rather than an inference.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Format a cost for the report. */
function t12_fmt( ?float $v ): string {
	return null === $v ? 'none' : rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
}

/** @param int[] $ids */
function t12_list( array $ids ): string {
	return $ids ? implode( ',', $ids ) : 'none';
}

run(
	new Scenario( 't12-cost-sync-identity', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$w      = new Workflows( $s );
		$ok_all = true;

		// Every product write in this process, in order, whoever makes it.
		$written  = array();
		$recorder = function ( $id ) use ( &$written ) {
			$written[] = (int) $id;
		};
		add_action( 'woocommerce_update_product', $recorder, 30, 1 );
		add_action( 'woocommerce_update_product_variation', $recorder, 30, 1 );
		$writes = function () use ( &$written ): array {
			$ids = array_values( array_unique( $written ) );
			sort( $ids );
			return $ids;
		};
		$reset = function () use ( &$written ) {
			$written = array();
		};

		// Fixtures: the pair under test, an unrelated translated pair, and an unrelated untranslated product.
		$en  = $w->simple( 'T12A', null );
		$ar  = $w->translate( 'duplicate', $en );
		$cen = $w->simple( 'T12CTRL', 55.0 );
		$car = $w->translate( 'duplicate', $cen );
		$one = $w->simple( 'T12LONE', 77.0 );
		if ( ! $ar || ! $car ) {
			$s->note( 'FAIL fixtures: the Arabic counterparts were not created' );
			return false;
		}

		// --- 1. Identity, read exactly as the sync reads it ------------------------------------------------------
		$trid_en = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_product' );
		$trid_ar = (int) apply_filters( 'wpml_element_trid', null, $ar, 'post_product' );
		$rows    = array();
		foreach ( (array) apply_filters( 'wpml_get_element_translations', null, $trid_en, 'post_product' ) as $row ) {
			$rows[ (int) $row->element_id ] = $row;
		}
		$ok = ( $trid_en > 0 && $trid_en === $trid_ar
			&& isset( $rows[ $en ], $rows[ $ar ] )
			&& (bool) (int) $rows[ $en ]->original && empty( $rows[ $en ]->source_language_code )
			&& ! (int) $rows[ $ar ]->original && 'en' === $rows[ $ar ]->source_language_code );
		$s->note( sprintf(
			'%s identity: trid %d/%d; EN %d original=%s source=%s; AR %d original=%s source=%s. The sync copies outwards only from the element WPML flags as the original',
			$ok ? 'OK  ' : 'FAIL',
			$trid_en,
			$trid_ar,
			$en,
			var_export( $rows[ $en ]->original ?? null, true ),
			var_export( $rows[ $en ]->source_language_code ?? null, true ),
			$ar,
			var_export( $rows[ $ar ]->original ?? null, true ),
			var_export( $rows[ $ar ]->source_language_code ?? null, true )
		) );
		$ok_all = $ok_all && $ok;
		$s->note( sprintf(
			'     for comparison, the separately cached wpml_original_element_id says EN %s and AR %s. It can be stale inside one long-running process, which is why the sync does not use it',
			var_export( apply_filters( 'wpml_original_element_id', null, $en, 'post_product' ), true ),
			var_export( apply_filters( 'wpml_original_element_id', null, $ar, 'post_product' ), true )
		) );

		// --- 2. A simple product writes to its own translation and to nothing else ------------------------------
		$reset();
		$w->set_cost( 'crud-cli', $en, 100.0 );
		$touched  = $writes();
		$expected = array( $en, $ar );
		sort( $expected );
		$controls = array( Workflows::cost( $cen ), Workflows::cost( $car ), Workflows::cost( $one ) );
		$ok       = ( 100.0 === Workflows::cost( $en ) && 100.0 === Workflows::cost( $ar )
			&& $touched === $expected && array( 55.0, 55.0, 77.0 ) === $controls );
		$s->note( sprintf(
			'%s simple: EN %s AR %s; products written %s (expected %s); unrelated pair %s/%s and untranslated %s unchanged',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( Workflows::cost( $en ) ),
			t12_fmt( Workflows::cost( $ar ) ),
			t12_list( $touched ),
			t12_list( $expected ),
			t12_fmt( $controls[0] ),
			t12_fmt( $controls[1] ),
			t12_fmt( $controls[2] )
		) );
		$ok_all = $ok_all && $ok;

		// --- 3. Variations reach their corresponding variation, not a sibling -----------------------------------
		list( $pid, $vids ) = $w->variable( 'T12V', array( 'S' => 11.0, 'L' => 22.0 ) );
		$arp                = $w->translate( 'editor', $pid );
		$arv                = array_map( array( Workflows::class, 'ar_of' ), $vids );
		if ( ! $arp || in_array( 0, $arv, true ) ) {
			$s->note( 'FAIL variations: the Arabic variations were not created' );
			return false;
		}
		$reset();
		$w->set_cost( 'crud-cli', $vids[0], 33.0 );
		$touched  = $writes();
		$expected = array( $vids[0], $arv[0] );
		sort( $expected );
		$ok = ( 33.0 === Workflows::cost( $arv[0] ) && 22.0 === Workflows::cost( $arv[1] )
			&& $touched === $expected );
		$s->note( sprintf(
			'%s variations: changed EN S only. AR S %s (want 33), AR L %s (want 22, the sibling must not move); written %s (expected %s)',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( Workflows::cost( $arv[0] ) ),
			t12_fmt( Workflows::cost( $arv[1] ) ),
			t12_list( $touched ),
			t12_list( $expected )
		) );
		$ok_all = $ok_all && $ok;

		// --- 4a. No translation at all --------------------------------------------------------------------------
		$reset();
		$w->set_cost( 'crud-cli', $one, 88.0 );
		$touched = $writes();
		$ok      = ( array( $one ) === $touched && 88.0 === Workflows::cost( $one ) && 100.0 === Workflows::cost( $ar ) );
		$s->note( sprintf(
			'%s untranslated product: cost %s, written %s (expected %d, and nothing else)',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( Workflows::cost( $one ) ),
			t12_list( $touched ),
			$one
		) );
		$ok_all = $ok_all && $ok;

		// --- 4b. Incomplete translation: the Arabic variation has been deleted -----------------------------------
		$gone = $arv[1];
		wc_get_product( $gone )->delete( true );
		$reset();
		$w->set_cost( 'crud-cli', $vids[1], 44.0 );
		$touched = $writes();
		$ok      = ( array( $vids[1] ) === $touched && 44.0 === Workflows::cost( $vids[1] )
			&& 33.0 === Workflows::cost( $arv[0] ) && 88.0 === Workflows::cost( $one ) );
		$s->note( sprintf(
			'%s deleted Arabic variation %d: EN L cost %s, written %s (expected %d only); AR S still %s, unrelated product still %s',
			$ok ? 'OK  ' : 'FAIL',
			$gone,
			t12_fmt( Workflows::cost( $vids[1] ) ),
			t12_list( $touched ),
			$vids[1],
			t12_fmt( Workflows::cost( $arv[0] ) ),
			t12_fmt( Workflows::cost( $one ) )
		) );
		$ok_all = $ok_all && $ok;

		// --- 5. The original is canonical -----------------------------------------------------------------------
		$translation = wc_get_product( $ar );
		$translation->set_cogs_value( 999.0 );
		$translation->save();
		$en_after = Workflows::cost( $en );
		$ar_after = Workflows::cost( $ar );
		$ok       = ( 100.0 === $en_after && 999.0 === $ar_after );
		$s->note( sprintf(
			'%s saving the translation with cost 999: EN %s (must stay 100, translations never push back), AR %s',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( $en_after ),
			t12_fmt( $ar_after )
		) );
		$ok_all = $ok_all && $ok;

		$w->set_cost( 'crud-cli', $en, 105.0 );
		$ok = ( 105.0 === Workflows::cost( $en ) && 105.0 === Workflows::cost( $ar ) );
		$s->note( sprintf(
			'%s the next save of the original restores the translation: EN %s AR %s',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( Workflows::cost( $en ) ),
			t12_fmt( Workflows::cost( $ar ) )
		) );
		$ok_all = $ok_all && $ok;

		// --- 6. A pair created after another translation, in the same process -----------------------------------
		// Regression case for the defect found in the review round of 22 September 2026: WPML's cached
		// original_element answer named an earlier product here, so a pair created second in one process was
		// silently not synchronised. Identity is now read from the WPML translation rows. Do not delete.
		// See verification.md and evidence/wpml-identity-cache.txt.
		$en2 = $w->simple( 'T12SECOND', 10.0 );
		$ar2 = $w->translate( 'duplicate', $en2 );
		if ( ! $ar2 ) {
			$s->note( 'FAIL second pair: no Arabic counterpart' );
			return false;
		}
		$w->set_cost( 'crud-cli', $en2, 250.0 );
		$ok = ( 250.0 === Workflows::cost( $en2 ) && 250.0 === Workflows::cost( $ar2 ) );
		$s->note( sprintf(
			'%s second pair created in the same process, with no cache flush: EN %s AR %s',
			$ok ? 'OK  ' : 'FAIL',
			t12_fmt( Workflows::cost( $en2 ) ),
			t12_fmt( Workflows::cost( $ar2 ) )
		) );
		$ok_all = $ok_all && $ok;

		$s->note( 'Creating a translation when a cost already exists is covered by t11 part 1 (WPML duplicate and WCML editor, simple and variations).' );
		return $ok_all;
	}
);

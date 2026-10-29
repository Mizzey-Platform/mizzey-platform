<?php
/**
 * t35, MIG-06 (AC-6, FR-006): every SKU resolves to the record a load would act on, with its language role.
 *
 * The store shapes that make a SKU hard to resolve, each seeded through WooCommerce and WPML: a WPML duplicate pair
 * sharing a SKU, a separately translated pair, no match, two unrelated records sharing a SKU, a record with no
 * language details, a translation whose original is in the trash, an original whose translation carries the SKU, a
 * trashed record, a case and whitespace variant, and a variation. The claims are read from the real command's
 * report: the resolved id, the language role, the translations and the near-match evidence.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_catalogue.php';

run(
	new Scenario( 't35-catalogue-sku-resolution', 'MIG-06 AC-6: each SKU resolves to its store record and language role' ),
	function ( Scenario $s ): bool {
		global $wpdb, $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$dup    = catalogue_simple( $s, 'MZ-R-DUP' );
		$dup_ar = catalogue_duplicate( $s, $dup );

		$sep    = catalogue_simple( $s, 'MZ-R-SEP' );
		$sep_ar = catalogue_simple( $s, 'MZ-R-SEP-AR' );
		$trid   = (int) apply_filters( 'wpml_element_trid', null, $sep, 'post_product' );
		$sitepress->set_element_language_details( $sep_ar, 'post_product', $trid, 'ar', 'en' );
		catalogue_force_sku( $sep_ar, 'MZ-R-SEP' );

		$amb_a = catalogue_simple( $s, 'MZ-R-AMB-A' );
		$amb_b = catalogue_simple( $s, 'MZ-R-AMB-B' );
		catalogue_force_sku( $amb_a, 'MZ-R-AMB' );
		catalogue_force_sku( $amb_b, 'MZ-R-AMB' );

		$bare = catalogue_simple( $s, 'MZ-R-BARE' );
		$wpdb->delete( $wpdb->prefix . 'icl_translations', array( 'element_id' => $bare, 'element_type' => 'post_product' ) ); // phpcs:ignore

		$orphan    = catalogue_simple( $s, 'MZ-R-ORPH-EN' );
		$orphan_ar = catalogue_duplicate( $s, $orphan );
		wp_trash_post( $orphan );
		catalogue_force_sku( $orphan_ar, 'MZ-R-ORPH' );

		$w4    = catalogue_simple( $s, 'MZ-R-W4-EN' );
		$w4_ar = catalogue_duplicate( $s, $w4 );
		catalogue_force_sku( $w4_ar, 'MZ-R-W4' );

		$trash = catalogue_simple( $s, 'MZ-R-TRASH' );
		wp_trash_post( $trash );

		$case = catalogue_simple( $s, 'MZ-R-CASE' );

		$vp        = catalogue_variable( $s, 'MZ-R-VP', array( 'MZ-R-VAR-1' ) );
		$variation = (int) wc_get_product_id_by_sku( 'MZ-R-VAR-1' );
		wp_cache_flush();

		$rows = array();
		foreach ( array( 'MZ-R-DUP', 'MZ-R-SEP', 'MZ-R-NONE', 'MZ-R-AMB', 'MZ-R-BARE', 'MZ-R-ORPH', 'MZ-R-W4', 'MZ-R-TRASH', 'mz-r-case ' ) as $sku ) {
			$rows[] = array( 'seller-sku' => $sku, 'item-name' => 'Resolution ' . $sku, 'item-name-ar' => 'منتج', 'price' => '100.00' );
		}
		$rows[] = array( 'seller-sku' => 'MZ-R-VAR-1', 'item-name' => 'Variation', 'item-name-ar' => 'منتج', 'price' => '100.00', 'parent-child' => 'child', 'parent-sku' => 'MZ-R-VP', 'size-name' => 'Small' );
		$path = catalogue_temp_export( $s, $rows );
		$out  = catalogue_out_dir( $s, 'resolution' );
		$run  = catalogue_command( 'validate ' . escapeshellarg( $path ) . ' --out=' . escapeshellarg( $out ) );
		$by   = catalogue_by_sku( catalogue_csv( $out . '/validation-report.csv' ) );
		$check( 0 === $run['code'] && 10 === count( $by ), "the validation completes with a line per row (exit {$run['code']}, " . count( $by ) . ' lines)' );

		$line = fn ( string $sku ): array => $by[ $sku ] ?? array( 'rule' => 'none', 'resolved_id' => '', 'language_role' => '', 'translations' => '', 'issues' => '', 'near_matches' => '', 'reason' => '' );
		$say  = fn ( string $sku ): string => $line( $sku )['rule'] . ' ' . $line( $sku )['resolved_id'] . ' ' . $line( $sku )['language_role'] . ' [' . $line( $sku )['translations'] . '] ' . $line( $sku )['issues'];

		$check( 'U1' === $line( 'MZ-R-DUP' )['rule'] && (string) $dup === $line( 'MZ-R-DUP' )['resolved_id'] && 'original' === $line( 'MZ-R-DUP' )['language_role'] && in_array( (string) $dup_ar, explode( ' ', $line( 'MZ-R-DUP' )['translations'] ), true ), 'AC-6 a WPML duplicate pair resolves to the English original and lists the translation (' . $say( 'MZ-R-DUP' ) . ')' );
		$check( 'U1' === $line( 'MZ-R-SEP' )['rule'] && (string) $sep === $line( 'MZ-R-SEP' )['resolved_id'] && in_array( (string) $sep_ar, explode( ' ', $line( 'MZ-R-SEP' )['translations'] ), true ), 'AC-6 a separately translated pair resolves to the original (' . $say( 'MZ-R-SEP' ) . ')' );
		$check( 'C1' === $line( 'MZ-R-NONE' )['rule'] && '' === $line( 'MZ-R-NONE' )['resolved_id'], 'AC-6 no match is a create with no record (' . $say( 'MZ-R-NONE' ) . ')' );
		$check( 'R4' === $line( 'MZ-R-AMB' )['rule'] && false !== strpos( $line( 'MZ-R-AMB' )['reason'], (string) $amb_a ) && false !== strpos( $line( 'MZ-R-AMB' )['reason'], (string) $amb_b ), 'AC-6 two unrelated records sharing a SKU are rejected, both named (' . $say( 'MZ-R-AMB' ) . ')' );
		$check( 'R3' === $line( 'MZ-R-BARE' )['rule'], 'AC-6 a record with no language details is an identity problem, not an original (' . $say( 'MZ-R-BARE' ) . ')' );
		$check( 'R3' === $line( 'MZ-R-ORPH' )['rule'] && 'translation' === $line( 'MZ-R-ORPH' )['language_role'], 'AC-6 a translation whose original is in the trash is rejected as a translation (' . $say( 'MZ-R-ORPH' ) . ')' );
		$check( 'U1' === $line( 'MZ-R-W4' )['rule'] && (string) $w4 === $line( 'MZ-R-W4' )['resolved_id'] && in_array( 'W4', explode( ' ', $line( 'MZ-R-W4' )['issues'] ), true ), 'AC-6 an original whose translation carries the SKU is found, with W4 (' . $say( 'MZ-R-W4' ) . ')' );
		$check( 'C1' === $line( 'MZ-R-TRASH' )['rule'], 'AC-6 a trashed record is not a match (' . $say( 'MZ-R-TRASH' ) . ')' );
		$check( 'U1' === $line( 'mz-r-case ' )['rule'] && (string) $case === $line( 'mz-r-case ' )['resolved_id'] && false !== strpos( $line( 'mz-r-case ' )['near_matches'], $case . '=MZ-R-CASE (collation)' ), 'AC-6 a case and whitespace variant matches as the store would, with its evidence (' . $line( 'mz-r-case ' )['near_matches'] . ')' );
		$check( 'U1' === $line( 'MZ-R-VAR-1' )['rule'] && (string) $variation === $line( 'MZ-R-VAR-1' )['resolved_id'], 'AC-6 a variation resolves to the store variation (' . $say( 'MZ-R-VAR-1' ) . ')' );
		unset( $vp );

		return $ok;
	}
);

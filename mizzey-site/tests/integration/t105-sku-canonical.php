<?php
/**
 * t105, a product looked up by its SKU is the canonical record (specs/001, the defect t15 found on 5 November 2026):
 * WooCommerce Multilingual copies a product's and a variation's SKU to the Arabic record, and WooCommerce's lookup has no
 * order, so an import by SKU could update the Arabic record and leave the English one as it was. The lookup is made to
 * land on the Arabic record, as the database sometimes answers, and must come back with the English one.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

run(
	new Scenario( 't105-sku-canonical', 'MIG-04, ADM-27: an SKU names the canonical record' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$w   = new Workflows( $s );
		$tag = 'T105' . wp_rand( 1000, 9999 );

		$simple    = $w->simple( "{$tag}S", null );
		$simple_ar = $w->translate( 'duplicate', $simple );
		[ $parent, $sizes ] = $w->variable( "{$tag}V", array( 'S' => null, 'M' => null ) );
		$parent_ar = $w->translate( 'duplicate', $parent );
		$small     = (int) $sizes[0];
		$small_ar  = (int) apply_filters( 'wpml_object_id', $small, 'product_variation', false, 'ar' );
		$sku       = static fn ( int $id ) => (string) get_post_meta( $id, '_sku', true );
		$check( $simple_ar > 0 && $small_ar > 0 && $small_ar !== $small && $sku( $simple ) === $sku( $simple_ar ) && $sku( $small ) === $sku( $small_ar ), 'setup: the Arabic records carry the same SKU as the English ones (' . $sku( $simple ) . ', ' . $sku( $small ) . ')' );

		// The database answers with the Arabic record, as it sometimes does: the lookup must still name the English one.
		$force = null;
		$lands = static function ( $id, $looked_up ) use ( &$force ) {
			return null !== $force && $looked_up === $force[0] ? $force[1] : $id;
		};
		add_filter( 'woocommerce_get_product_id_by_sku', $lands, 5, 2 );
		$force  = array( $sku( $simple ), $simple_ar );
		$found1 = ImporterLookup::make()->lookup( $sku( $simple ) );
		$force  = array( $sku( $small ), $small_ar );
		$found2 = ImporterLookup::make()->lookup( $sku( $small ) );
		$outside = wc_get_product_id_by_sku( $sku( $small ) );
		$force  = null;
		remove_filter( 'woocommerce_get_product_id_by_sku', $lands, 5 );
		$check( $simple === $found1, 'AC-1 the CSV importer, looking a simple product up by SKU, gets the English record even when the lookup lands on the Arabic one (' . "{$found1}, expected {$simple}" . ')' );
		$check( $small === $found2, 'AC-1 the CSV importer, looking a variation up by SKU, gets the English variation even when the lookup lands on the Arabic one (' . "{$found2}, expected {$small}" . ')' );
		$check( $small_ar === $outside, 'AC-1 the same lookup outside the importer keeps the answer WooCommerce gave, so WooCommerce Multilingual still finds an Arabic copy by its SKU (' . "{$outside}, expected {$small_ar}" . ')' );
		$check( 0 === wc_get_product_id_by_sku( "{$tag}-none" ), 'AC-1 an SKU no product has is still no product' );

		return $ok;
	}
);

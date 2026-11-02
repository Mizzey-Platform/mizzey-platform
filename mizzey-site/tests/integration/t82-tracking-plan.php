<?php
/**
 * t82: the event tracking plan (EVT-20). See specs/042-event-tracking-plan.
 *
 * AC-1: every contracted event row is in the plan with its event name; EVT-17 and EVT-19 are named as not included.
 * AC-2: the source-of-truth table names each business metric.
 * AC-3: the plan identifies an item by its SKU, and the Arabic record of a product carries the same SKU.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

run(
	new Scenario( 't82-tracking-plan', 'EVT-20: the event tracking plan' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$plan = (string) file_get_contents( dirname( __DIR__, 3 ) . '/specs/042-event-tracking-plan/tracking-plan.md' );

		// ---- AC-1: every contracted event, with its name; the exclusions -------------------------------------------
		$events = array(
			'EVT-01' => 'view_home',
			'EVT-02' => 'view_item_list',
			'EVT-03' => 'search',
			'EVT-04' => 'view_item',
			'EVT-05' => 'select_item',
			'EVT-06' => 'add_to_wishlist',
			'EVT-07' => 'add_to_cart',
			'EVT-08' => 'remove_from_cart',
			'EVT-09' => 'view_cart',
			'EVT-10' => 'apply_coupon',
			'EVT-11' => 'begin_checkout',
			'EVT-12' => 'add_shipping_info',
			'EVT-13' => 'add_payment_info',
			'EVT-14' => 'purchase',
			'EVT-15' => 'refund',
			'EVT-16' => 'sign_up',
			'EVT-18' => 'zero_result_search',
		);
		$missing = array();
		foreach ( $events as $id => $name ) {
			if ( 1 !== preg_match( '/^\| ' . preg_quote( $id, '/' ) . ' \| `' . preg_quote( $name, '/' ) . '`[^|]*\| [^|]+ \| [^|]+ \| P1(-L)? \|$/m', $plan ) ) {
				$missing[] = $id;
			}
		}
		$check( array() === $missing, 'AC-1 each contracted event has its row: name, trigger, parameters and scope (missing: ' . implode( ', ', $missing ) . ')' );
		$check( str_contains( $plan, 'EVT-17, P2' ) && str_contains( $plan, 'EVT-19, DEF' ) && 1 !== preg_match( '/^\| EVT-1[79] \|/m', $plan ), 'AC-1 promotion events (EVT-17) and return requests (EVT-19) are named as not included, and have no event row' );
		$check( str_contains( $plan, 'no email, phone, name or address' ), 'AC-1 no personal data in any parameter' );

		// ---- AC-2: one source of truth per metric ----------------------------------------------------------------
		$metrics = array( 'Revenue, orders, units, average order value', 'Refunds', 'Money received from the payment provider', 'Cash on delivery collected and remitted', 'Sessions, users, traffic', 'Conversion rate', 'Revenue by campaign', 'Advertising cost', 'Search terms', 'Stock' );
		$absent  = array_values( array_filter( $metrics, static fn ( string $m ): bool => 1 !== preg_match( '/^\| ' . preg_quote( $m, '/' ) . '[^|]* \| [^|]+ \| [^|]+ \|$/m', $plan ) ) );
		$check( array() === $absent, 'AC-2 each business metric has its source of truth (missing: ' . implode( ', ', $absent ) . ')' );

		// ---- AC-3: one item, two languages ----------------------------------------------------------------------
		$check( str_contains( $plan, '| `item_id` | SKU' ) && str_contains( $plan, 'Same on both language records' ), 'AC-3 the plan identifies an item by its SKU, shared by both language records' );
		$w   = new Workflows( $s );
		$en  = $w->simple( "t82-" . wp_rand( 1000, 9999 ), null );
		$ar  = $w->translate( 'editor', $en );
		wp_cache_flush();
		$sku_en = (string) wc_get_product( $en )->get_sku();
		$sku_ar = $ar > 0 ? (string) wc_get_product( $ar )->get_sku() : '';
		$check( '' !== $sku_en && $sku_en === $sku_ar, 'AC-3 the Arabic record carries the same SKU (' . $sku_en . ' / ' . $sku_ar . ')' );

		return $ok;
	}
);

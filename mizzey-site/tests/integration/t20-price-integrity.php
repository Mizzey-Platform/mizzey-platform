<?php
/**
 * t20: does a price change reach the Arabic record, per supported channel, and what is the customer charged?
 *
 * Measurement only. No fix is implemented here. A12 measured that regular_price and sale_price stayed stale on a
 * translation after a WooCommerce CRUD save on one channel. Price is customer-facing and financial, so before any
 * correction is proposed the question has to be answered per channel: if every launch-supported price maintenance
 * path already synchronises, no custom code is justified. If a supported path can leave a stale Arabic price or
 * charge the wrong amount, that is a defect in a contracted behaviour.
 *
 * Channels, as t15 defined them: admin-http (the wp-admin product form), admin-variation (the variations AJAX
 * save), import-http (the native CSV importer), rest-http (WooCommerce REST v3), crud-cli (WooCommerce CRUD under
 * WP-CLI) and crud-web (CRUD in a front-end request, standing for custom code, webhooks and cron). The
 * scheduled-sales cron is measured separately, because it is the one price write a store makes without anybody
 * scripting anything.
 *
 * For every case: the canonical English value, the Arabic stored value, the effective price an Arabic customer is
 * served, the amount an Arabic order actually charges, and whether save_post fired.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** The price fields, read from storage. */
function t20_prices( int $id ): array {
	wp_cache_flush();
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return array( 'regular' => '(no product)', 'sale' => '(no product)', 'price' => '(no product)' );
	}

	return array(
		'regular' => (string) $p->get_regular_price(),
		'sale'    => (string) $p->get_sale_price(),
		'price'   => (string) $p->get_price(),
	);
}

function t20_fmt( array $p ): string {
	return sprintf( 'reg=%s sale=%s price=%s', $p['regular'] ?: '-', $p['sale'] ?: '-', $p['price'] ?: '-' );
}

/** What an order in $lang actually charges for one unit of $id. NAN when the product cannot be loaded. */
function t20_charged( Scenario $s, int $id, string $lang ): float {
	do_action( 'wpml_switch_language', $lang );
	wp_cache_flush();
	$product = wc_get_product( $id );
	$total   = NAN;
	if ( $product ) {
		$order = $s->order_for( $product, 1 );
		$total = (float) $order->get_subtotal();
	}
	do_action( 'wpml_switch_language', 'en' );

	return $total;
}

run(
	new Scenario( 't20-price-integrity', 'price matrix (probe, no criterion)' ),
	function ( Scenario $s ): ?bool {
		$w = new Workflows( $s );

		$ran = array();
		foreach ( array( 'save_post', 'woocommerce_update_product', 'woocommerce_update_product_variation' ) as $hook ) {
			add_action( $hook, function ( $arg = null ) use ( $hook, &$ran ) {
				$id    = is_object( $arg ) && method_exists( $arg, 'get_id' ) ? $arg->get_id() : ( is_numeric( $arg ) ? (int) $arg : 0 );
				$ran[] = $id ? "$hook($id)" : $hook;
			}, 50, 1 );
		}

		$s->note( 'admin-http, admin-variation and import-http fire save_post, so WPML and WCML can run. rest-http, crud-cli and crud-web do not, for a meta-only change (t15).' );

		/** One case: change a price field on the English original through $channel, then measure everything. */
		$measure = function ( string $channel, string $type, string $field, string $value ) use ( $w, $s, &$ran ) {
			if ( 'simple' === $type ) {
				$en = $w->simple( 'PR-' . strtoupper( substr( md5( $channel . $field ), 0, 6 ) ), null );
				$w->translate( 'duplicate', $en );
				$ar = Workflows::ar_of( $en );
			} else {
				list( $pid, $vids ) = $w->variable( 'PRV-' . strtoupper( substr( md5( $channel . $field ), 0, 6 ) ), array( 'S' => null, 'L' => null ) );
				$w->translate( 'duplicate', $pid );
				$en = $vids[0];
				$ar = Workflows::ar_of( $en );
			}
			if ( ! $ar ) {
				$s->note( sprintf( '  %-16s %-9s %-13s no Arabic counterpart, not measured', $channel, $type, $field ) );
				return;
			}

			$key       = ( 'sale_price' === $field ) ? 'sale' : 'regular';
			$before_en = t20_prices( $en );
			$ran       = array();

			$result = $w->set_price( $channel, $en, $field, $value );

			$after_en = t20_prices( $en );
			$after_ar = t20_prices( $ar );
			$charged  = t20_charged( $s, $ar, 'ar' );
			$save     = (bool) array_filter( $ran, fn( $e ) => 0 === strpos( $e, 'save_post(' ) );

			$applied  = ( $after_en[ $key ] !== $before_en[ $key ] );
			$followed = ( $after_ar[ $key ] === $after_en[ $key ] && $after_ar['price'] === $after_en['price'] );

			$verdict = 'ok' !== $result
				? 'CHANNEL FAILED: ' . $result
				: ( ! $applied
					? 'not applied on the original'
					: ( $followed ? 'FOLLOWED' : '*** STALE ARABIC PRICE ***' ) );

			$s->note( sprintf(
				'  %-16s %-9s %-13s EN %s | AR %s | Arabic order charged %s | save_post %s | %s',
				$channel,
				$type,
				$field,
				t20_fmt( $after_en ),
				t20_fmt( $after_ar ),
				is_nan( $charged ) ? 'nothing' : number_format( $charged, 2, '.', '' ),
				$save ? 'fired' : 'no',
				$verdict
			) );
		};

		foreach ( array( 'admin-http', 'admin-variation', 'import-http', 'rest-http', 'crud-cli', 'crud-web' ) as $channel ) {
			$s->note( sprintf( '=== channel: %s ===', $channel ) );
			foreach ( array( 'simple', 'variation' ) as $type ) {
				if ( 'admin-http' === $channel && 'variation' === $type ) {
					continue; // the product form edits a product
				}
				if ( 'admin-variation' === $channel && 'simple' === $type ) {
					continue; // the variations endpoint edits a variation
				}
				foreach ( array( 'regular_price' => '444', 'sale_price' => '150' ) as $field => $value ) {
					$measure( $channel, $type, $field, $value );
				}
			}
		}

		// ---- A price written straight onto the translation, where the channel can reach it ----------------
		$s->note( '=== a price written directly onto the Arabic record (ownership question) ===' );
		foreach ( array( 'crud-cli', 'rest-http' ) as $channel ) {
			$en = $w->simple( 'PRDIR-' . strtoupper( substr( md5( $channel ), 0, 5 ) ), null );
			$w->translate( 'duplicate', $en );
			$ar = Workflows::ar_of( $en );
			if ( ! $ar ) {
				continue;
			}
			$result   = $w->set_price( $channel, $ar, 'regular_price', '999' );
			$ar_after = t20_prices( $ar );
			$charged  = t20_charged( $s, $ar, 'ar' );
			$s->note( sprintf(
				'  %-16s wrote 999 to the translation -> EN %s | AR %s | Arabic order charged %s | %s',
				$channel,
				t20_fmt( t20_prices( $en ) ),
				t20_fmt( $ar_after ),
				is_nan( $charged ) ? 'nothing' : number_format( $charged, 2, '.', '' ),
				'ok' === $result ? ( '999' === $ar_after['regular'] ? 'the translation kept its own price' : 'replaced by the original' ) : 'channel failed: ' . $result
			) );
		}

		// ---- The scheduled-sales cron, the one price write nobody scripts --------------------------------
		$s->note( '=== the scheduled-sales cron in WooCommerce (wc_scheduled_sales) ===' );
		$en = $w->simple( 'PRSCHED', null );
		$w->translate( 'duplicate', $en );
		$ar = Workflows::ar_of( $en );
		if ( ! $ar ) {
			$s->note( '  no Arabic counterpart, not measured' );
		} else {
			// The whole scheduled sale in ONE wp-admin submission, the way a store sets one: sale price plus both
			// dates, already expired. That leaves the pair synchronised, so the only write under test is the cron's.
			// An ACTIVE sale, set in one wp-admin save, so the pair starts synchronised and on sale.
			$from      = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
			$to        = gmdate( 'Y-m-d', time() + ( 2 * DAY_IN_SECONDS ) );
			$admin_set = $w->admin_product_fields( $en, array(
				'_sale_price'            => '150',
				'_sale_price_dates_from' => $from,
				'_sale_price_dates_to'   => $to,
			) );
			$s->note( sprintf( '  active sale 150 from %s to %s, set in one wp-admin save (%s)', $from, $to, $admin_set ) );
			$s->note( sprintf( '  both on sale after that admin save: EN %s | AR %s',
				t20_fmt( t20_prices( $en ) ), t20_fmt( t20_prices( $ar ) ) ) );

			// Move the end date into the past with a raw meta write on BOTH records, so the two still agree and
			// the only product-object save in this test is the one the cron makes. A raw meta write is a
			// measurement tool here; production code never does this (woo-guard rule 2).
			$expired = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
			foreach ( array( $en, $ar ) as $id ) {
				update_post_meta( $id, '_sale_price_dates_to', (string) strtotime( $expired . ' 23:59:59' ) );
			}
			wp_cache_flush();
			$s->note( sprintf( '  end date moved to %s on both records, by raw meta write, nothing else changed', $expired ) );
			$s->note( sprintf( '  still on sale, still agreeing:      EN %s | AR %s',
				t20_fmt( t20_prices( $en ) ), t20_fmt( t20_prices( $ar ) ) ) );

			if ( function_exists( 'wc_scheduled_sales' ) ) {
				wc_scheduled_sales();
			} else {
				do_action( 'woocommerce_scheduled_sales' );
			}

			$en_after = t20_prices( $en );
			$ar_after = t20_prices( $ar );
			$charged  = t20_charged( $s, $ar, 'ar' );
			$agree    = ( $en_after['price'] === $ar_after['price'] && $en_after['sale'] === $ar_after['sale'] );
			$s->note( sprintf(
				'  after wc_scheduled_sales(): EN %s | AR %s | Arabic order charged %s | %s',
				t20_fmt( $en_after ),
				t20_fmt( $ar_after ),
				is_nan( $charged ) ? 'nothing' : number_format( $charged, 2, '.', '' ),
				$agree ? 'the two agree' : '*** THE ARABIC PRICE DISAGREES, and the Arabic order charges it ***'
			) );
		}

		$s->note( 'The rendered Arabic storefront page is not served by this runtime, so what the customer is served is measured as the effective price on the product the Arabic context resolves, plus the amount an Arabic order charges. The rendered page stays a staging check.' );
		return null; // fact-finding
	}
);

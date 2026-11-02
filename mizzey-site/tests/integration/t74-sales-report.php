<?php
/**
 * t74: the sales report (RPT-01). See specs/034-sales-report.
 *
 * AC-1: for a known set of English and Arabic orders on one day, WooCommerce Analytics' revenue and orders figures
 *       (revenue, net sales, orders, units, average order value, discounts, shipping) match a hand calculation.
 * AC-2: the same figures through the real request the Analytics screen makes, opened in English and in Arabic.
 * AC-3: staff without access to reports cannot read them.
 *
 * The orders sit on 15 March 2025, a day no other scenario uses, so the day's figures are the fixtures' alone.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrderStats;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

const T74_DAY = '2025-03-15';

run(
	new Scenario( 't74-sales-report', 'RPT-01: sales for a period, seven figures, both languages' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't74' . wp_rand( 1000, 9999 );
		$s->on_finish( static function (): void { wp_set_current_user( 0 ); } );

		// Nothing else may sit on the day: an order a killed run left is removed first.
		$left = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}wc_order_stats WHERE date_created BETWEEN %s AND %s", T74_DAY . ' 00:00:00', T74_DAY . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $left as $id ) {
			$o = wc_get_order( (int) $id );
			if ( $o ) {
				$o->delete( true );
			}
			$wpdb->delete( "{$wpdb->prefix}wc_order_stats", array( 'order_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		$product = $s->simple_product( 't74 serum', '100.00', null );
		$coupon  = function ( string $type, string $amount ) use ( $s, $tag ): string {
			$c = new \WC_Coupon();
			$c->set_code( $tag . $type . $amount );
			$c->set_props( array( 'discount_type' => $type, 'amount' => $amount ) );
			$c->save();
			$s->on_finish( static function () use ( $c ) { wp_delete_post( $c->get_id(), true ); } );
			return $c->get_code();
		};
		$fixed   = $coupon( 'fixed_cart', '20' );
		$percent = $coupon( 'percent', '10' );
		$order   = function ( int $qty, string $lang, ?string $code, float $shipping ) use ( $s, $product ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( $product, $qty );
			$o->update_meta_data( 'wpml_language', $lang );
			if ( $shipping > 0 ) {
				$line = new \WC_Order_Item_Shipping();
				$line->set_method_title( 'Flat rate' );
				$line->set_total( (string) $shipping );
				$o->add_item( $line );
			}
			$o->calculate_totals();
			if ( null !== $code ) {
				$o->apply_coupon( $code );
			}
			$o->set_date_created( T74_DAY . ' 10:00:00' );
			$o->save();
			$s->track_order( $o->get_id() );
			$o->payment_complete();
			$o = wc_get_order( $o->get_id() );
			$o->set_date_paid( T74_DAY . ' 10:05:00' );
			$o->save();
			return $o;
		};
		// Hand calculation: three orders, 6 units at 100.00.
		$e  = $order( 2, 'en', $fixed, 30 );    // 200.00 - 20.00 + 30.00 = 210.00
		$a1 = $order( 1, 'ar', null, 0 );       // 100.00
		$a2 = $order( 3, 'ar', $percent, 30 );  // 300.00 - 30.00 + 30.00 = 300.00
		$check( '210.00' === $e->get_total() && '100.00' === $a1->get_total() && '300.00' === $a2->get_total(), 'fixture: order totals 210.00, 100.00 and 300.00 (' . $e->get_total() . ', ' . $a1->get_total() . ', ' . $a2->get_total() . ')' );
		foreach ( array( $e, $a1, $a2 ) as $o ) {
			OrderStats::sync_order( $o->get_id() );
		}
		$expected = array(
			'orders_count'    => 3,
			'num_items_sold'  => 6,
			'gross_sales'     => 600.0,
			'coupons'         => 50.0,
			'net_revenue'     => 550.0,
			'shipping'        => 60.0,
			'total_sales'     => 610.0,
			'avg_order_value' => round( 550 / 3, 2 ),
		);
		$compare = static function ( array $totals ) use ( $expected ): array {
			$wrong = array();
			foreach ( $expected as $key => $want ) {
				$got = isset( $totals[ $key ] ) ? round( (float) $totals[ $key ], 2 ) : null;
				if ( null === $got || abs( $got - (float) $want ) > 0.01 ) {
					$wrong[] = $key . '=' . var_export( $totals[ $key ] ?? null, true ) . ' (want ' . $want . ')';
				}
			}
			return $wrong;
		};
		$params = array( 'after' => T74_DAY . 'T00:00:00', 'before' => T74_DAY . 'T23:59:59', 'interval' => 'day', 'force_cache_refresh' => true );

		// ---- AC-1: in process ----------------------------------------------------------------------------
		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );
		$req = new \WP_REST_Request( 'GET', '/wc-analytics/reports/revenue/stats' );
		$req->set_query_params( $params );
		$res    = rest_do_request( $req );
		$totals = (array) ( $res->get_data()['totals'] ?? array() );
		$wrong  = $compare( $totals );
		$check( 200 === $res->get_status() && array() === $wrong, 'AC-1 revenue 610.00, net sales 550.00, 3 orders, 6 units, average order 183.33, discounts 50.00, shipping 60.00, English and Arabic orders together' . ( $wrong ? ' (' . implode( '; ', $wrong ) . ')' : '' ) );
		$oreq = new \WP_REST_Request( 'GET', '/wc-analytics/reports/orders/stats' );
		$oreq->set_query_params( $params );
		$ores = (array) ( rest_do_request( $oreq )->get_data()['totals'] ?? array() );
		$check( 3 === (int) ( $ores['orders_count'] ?? 0 ) && 6 === (int) ( $ores['num_items_sold'] ?? 0 ) && abs( (float) ( $ores['avg_order_value'] ?? 0 ) - 183.33 ) < 0.01, 'AC-1 the Orders report agrees: 3 orders, 6 units, 183.33 on average' );

		// A partial refund on the same day lowers net sales and revenue by its amount.
		wc_create_refund( array( 'order_id' => $e->get_id(), 'amount' => '50', 'reason' => 't74', 'date_created' => T74_DAY . ' 12:00:00' ) );
		foreach ( wc_get_order( $e->get_id() )->get_refunds() as $refund ) {
			OrderStats::sync_order( $refund->get_id() );
		}
		OrderStats::sync_order( $e->get_id() );
		$res2 = (array) ( rest_do_request( $req )->get_data()['totals'] ?? array() );
		$check( abs( (float) ( $res2['refunds'] ?? 0 ) - 50 ) < 0.01 && abs( (float) ( $res2['net_revenue'] ?? 0 ) - 500 ) < 0.01, 'AC-1 a 50.00 refund that day: refunds 50.00, net sales 500.00 (' . ( $res2['net_revenue'] ?? '?' ) . ')' );
		$expected['net_revenue'] = 500.0;
		$expected['total_sales'] = 560.0;
		$expected['avg_order_value'] = round( 500 / 3, 2 );
		unset( $expected['gross_sales'] );

		// ---- AC-2: the real request of the Analytics screen, in English and in Arabic ---------------------
		$w = new Workflows( $s );
		foreach ( array( 'en', 'ar' ) as $lang ) {
			$screen = $w->analytics_screen( 'revenue', $lang );
			$http   = $w->screen_get( $screen, 'wc-analytics/reports/revenue/stats', $params );
			$data   = json_decode( $http['body'], true );
			$wrong  = $compare( is_array( $data ) ? (array) ( $data['totals'] ?? array() ) : array() );
			$check( 200 === $screen['status'] && 200 === $http['status'] && array() === $wrong, "AC-2 [{$lang}] the Analytics screen's own request gives the same figures (" . $http['status'] . ( $wrong ? ': ' . implode( '; ', $wrong ) : '' ) . ')' );
		}

		// ---- AC-3: report access -------------------------------------------------------------------------
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}cust", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}cust@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $customer );
		wp_set_current_user( $customer );
		$denied = rest_do_request( $req );
		$check( in_array( $denied->get_status(), array( 401, 403 ), true ), 'AC-3 a customer is refused the report (' . $denied->get_status() . ')' );

		return $ok;
	}
);

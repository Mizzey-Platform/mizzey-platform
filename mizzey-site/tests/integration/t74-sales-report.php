<?php
/**
 * t74: the sales report (RPT-01). See specs/034-sales-report.
 *
 * AC-1: for a known set of English and Arabic orders on one day, WooCommerce Analytics' revenue and orders figures
 *       (revenue, net sales, orders, units, average order value, discounts, shipping) match a hand calculation.
 * AC-2: the same figures through the real request the Analytics screen makes, opened in English and in Arabic.
 * AC-3: staff without access to reports cannot read them.
 * AC-4: an order counts on the day it was placed, so a cash on delivery order counts that day through Confirmed,
 *       Shipped, COD Collected and Delivered; Delivery Failed / Returned to Origin is not a sale.
 *
 * The orders sit on 15 March 2025, a day no other scenario uses, so the day's figures are the fixtures' alone.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler;
use MizzeySite\I18n\RewriteRoot;
use MizzeySite\Reporting\SalesReport;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';
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
		// WooCommerce's own import of each order into Analytics: stats, products, coupons, taxes, customer.
		foreach ( array( $e, $a1, $a2 ) as $o ) {
			OrdersScheduler::import( $o->get_id() );
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
		$compare = static function ( array $totals ) use ( &$expected ): array {
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
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $admin ) );
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
			OrdersScheduler::import( $refund->get_id() );
		}
		OrdersScheduler::import( $e->get_id() );
		$res2 = (array) ( rest_do_request( $req )->get_data()['totals'] ?? array() );
		$check( abs( (float) ( $res2['refunds'] ?? 0 ) - 50 ) < 0.01 && abs( (float) ( $res2['net_revenue'] ?? 0 ) - 500 ) < 0.01, 'AC-1 a 50.00 refund that day: refunds 50.00, net sales 500.00 (' . ( $res2['net_revenue'] ?? '?' ) . ')' );
		$expected['net_revenue'] = 500.0;
		$expected['total_sales'] = 560.0;
		// The average order value is the orders' own (550 / 3): WooCommerce reports a refund apart from it.
		unset( $expected['gross_sales'] );

		// ---- AC-4: what counts as a sale: cash on delivery by the order's date, Delivery Failed never ------------
		$excluded_now = (array) get_option( 'woocommerce_excluded_report_order_statuses', array() );
		$check( SalesReport::DATE_TYPE === get_option( 'woocommerce_date_type' ) && 'date_created' === SalesReport::DATE_TYPE && in_array( 'delivery-failed', $excluded_now, true ) && array() === array_diff( array( 'pending', 'failed', 'cancelled' ), $excluded_now ), 'AC-4 Analytics\' own settings count an order by its date and leave out Pending Payment, Payment Failed, Cancelled and Delivery Failed (' . get_option( 'woocommerce_date_type' ) . '; ' . implode( ',', $excluded_now ) . ')' );
		$walk = static function ( \WC_Order $o, array $steps ): \WC_Order {
			foreach ( $steps as $step ) {
				wc_get_order( $o->get_id() )->update_status( $step, '', true );
			}
			$o = wc_get_order( $o->get_id() );
			OrdersScheduler::import( $o->get_id() );
			return $o;
		};
		$placed = function ( int $qty, string $method, int $hour ) use ( $s, $product ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( $product, $qty );
			$o->set_payment_method( $method );
			$o->update_meta_data( 'wpml_language', 'ar' );
			$o->calculate_totals();
			$o->set_date_created( T74_DAY . sprintf( ' %02d:00:00', $hour ) );
			$o->save();
			$s->track_order( $o->get_id() );
			return $o;
		};
		$day = static function () use ( $req ): array {
			return (array) ( rest_do_request( $req )->get_data()['totals'] ?? array() );
		};
		// A cash order placed that day and confirmed: a sale of that day, before any money is collected. The gateways are
		// loaded first, as on a checkout, so the cash gateway's own payment rule applies.
		WC()->payment_gateways();
		$cash      =$walk( $placed( 1, 'cod', 11 ), array( 'processing' ) );
		$confirmed = $day();
		$check( 4 === (int) ( $confirmed['orders_count'] ?? 0 ) && 7 === (int) ( $confirmed['num_items_sold'] ?? 0 ) && abs( (float) ( $confirmed['total_sales'] ?? 0 ) - 660 ) < 0.01 && null === $cash->get_date_paid(), 'AC-4 a cash on delivery order confirmed that day counts that day, unpaid: 4 orders, 7 units, revenue 660.00 (' . wp_json_encode( array( $confirmed['orders_count'] ?? null, $confirmed['num_items_sold'] ?? null, $confirmed['total_sales'] ?? null ) ) . ')' );
		// Shipped, COD Collected, then Delivered when the remittance is recorded (today): still that day's sale.
		$cash = $walk( $cash, array( 'preparing', 'shipped', 'cod-collected', 'completed' ) );
		$paid = $cash->get_date_paid();
		$delivered = $day();
		$check( 'completed' === $cash->get_status() && ( null === $paid || T74_DAY !== $paid->date( 'Y-m-d' ) ) && 4 === (int) ( $delivered['orders_count'] ?? 0 ) && abs( (float) ( $delivered['total_sales'] ?? 0 ) - 660 ) < 0.01, 'AC-4 the cash order, Delivered and paid on another day (' . ( $paid ? $paid->date( 'Y-m-d' ) : 'no paid date' ) . '), stays in the day it was placed, counted once (' . ( $delivered['orders_count'] ?? '?' ) . ' orders)' );
		// A prepaid order returned to origin, and a cash order the customer refused: neither is a sale.
		$returned = $placed( 2, 'bacs', 13 );
		$returned->payment_complete();
		$returned = wc_get_order( $returned->get_id() );
		$returned->set_date_paid( T74_DAY . ' 13:05:00' );
		$returned->save();
		$returned = $walk( $returned, array( 'preparing', 'shipped', 'delivery-failed' ) );
		$refused  = $walk( $placed( 1, 'cod', 14 ), array( 'processing', 'preparing', 'shipped', 'delivery-failed' ) );
		$failed   = $day();
		$check( 'delivery-failed' === $returned->get_status() && 'delivery-failed' === $refused->get_status() && 4 === (int) ( $failed['orders_count'] ?? 0 ) && 7 === (int) ( $failed['num_items_sold'] ?? 0 ) && abs( (float) ( $failed['total_sales'] ?? 0 ) - 660 ) < 0.01, 'AC-4 a prepaid order returned to origin and a refused cash order, both Delivery Failed, are not counted: still 4 orders, 7 units, 660.00 (' . wp_json_encode( array( $failed['orders_count'] ?? null, $failed['num_items_sold'] ?? null, $failed['total_sales'] ?? null ) ) . ')' );
		$expected['orders_count']    = 4;
		$expected['num_items_sold']  = 7;
		$expected['net_revenue']     = 600.0;
		$expected['total_sales']     = 660.0;
		$expected['avg_order_value'] = 650 / 4;

		// The web server's rules are written for the site's root even when WordPress writes them in Arabic (the cause of
		// the Arabic screen's 500 on an Apache host: RewriteBase /ar/ loops every Arabic address).
		do_action( 'wpml_switch_language', 'ar' );
		$rules = (string) $GLOBALS['wp_rewrite']->mod_rewrite_rules();
		do_action( 'wpml_switch_language', 'en' );
		$check( str_contains( $rules, 'RewriteBase ' . RewriteRoot::base() . "\n" ) && ! str_contains( $rules, '/ar/index.php' ), 'AC-2 the rewrite rules written in Arabic name the site root (' . RewriteRoot::base() . '), not /ar/' );
		do_action( 'wpml_switch_language', 'ar' );
		$verbose_before                       = $GLOBALS['wp_rewrite']->use_verbose_rules;
		$GLOBALS['wp_rewrite']->use_verbose_rules = true;
		$verbose                              = (string) $GLOBALS['wp_rewrite']->mod_rewrite_rules();
		$GLOBALS['wp_rewrite']->use_verbose_rules = $verbose_before;
		$foreign = "RewriteBase /portal/\nRewriteRule . /portal/index.php [L]\n";
		$kept    = RewriteRoot::root( $foreign );
		do_action( 'wpml_switch_language', 'en' );
		$check( ! str_contains( $verbose, '/ar/index.php' ) && $kept === $foreign, 'AC-2 with verbose rules too no target keeps /ar/, and another block\'s own base and target are left alone' );

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

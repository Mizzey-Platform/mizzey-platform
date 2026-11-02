<?php
/**
 * t75: the store at a glance on the dashboard (ADM-01 to ADM-04, ADM-06, ADM-08 to ADM-13, ADM-15).
 * See specs/035-admin-dashboard.
 *
 * AC-1: revenue, orders, units, average order value and refunds agree with the sales report for the same period.
 * AC-2: charts by day, week, month and range are one link away (Analytics).
 * AC-3: top products count an English and an Arabic record of one product as one line.
 * AC-4: orders requiring action and refunds still to make are counted and linked to the filtered lists.
 * AC-5: low and out of stock come from the stock source, one answer per physical item; an unanswered item is unknown.
 * AC-6: quick actions: add a product, create a coupon, view pending orders. Customers do not see the widget.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrderStats;
use MizzeySite\Reporting\StoreDashboard;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceRefused;
use MizzeySite\Stock\StockGateway;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** A stock source that knows only the SKUs it is given: what the ERP would report. */
final class T75Source implements StockSource {
	public function __construct( private array $levels ) {}
	public function name(): string { return 't75'; }
	public function level( StockItem $item ): int {
		if ( ! array_key_exists( $item->sku, $this->levels ) ) {
			throw new StockSourceRefused( 'unknown item' );
		}
		return (int) $this->levels[ $item->sku ];
	}
	public function adjust( StockItem $item, int $delta, string $operationKey ): int { throw new StockSourceRefused( 'read only' ); }
}

run(
	new Scenario( 't75-admin-dashboard', 'ADM-01 to ADM-15: the store at a glance' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't75' . wp_rand( 1000, 9999 );
		$w   = new Workflows( $s );
		$s->on_finish( static function (): void { delete_transient( StoreDashboard::STOCK_KEY ); wp_set_current_user( 0 ); } );
		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );

		// A product in both languages, and orders in each.
		$en = $w->simple( "{$tag}-DUO", null );
		$ar = $w->translate( 'duplicate', $en );
		$check( $ar > 0, 'fixture: an English product and its Arabic record' );
		$order = function ( int $product, int $qty, string $lang ) use ( $s ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( wc_get_product( $product ), $qty );
			$o->update_meta_data( 'wpml_language', $lang );
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			$o->payment_complete();
			OrderStats::sync_order( $o->get_id() );
			return wc_get_order( $o->get_id() );
		};
		$o1 = $order( $en, 7, 'en' );
		$o2 = $order( $ar, 5, 'ar' );

		// ---- AC-1, AC-2 --------------------------------------------------------------------------------------
		[ $after, $before ] = StoreDashboard::range( '7d' );
		$sales = StoreDashboard::sales( $after, $before );
		$req   = new \WP_REST_Request( 'GET', '/wc-analytics/reports/revenue/stats' );
		$req->set_query_params( array( 'after' => $after, 'before' => $before, 'interval' => 'day', 'force_cache_refresh' => true ) );
		$report = (array) ( rest_do_request( $req )->get_data()['totals'] ?? array() );
		$check( abs( $sales['revenue'] - (float) $report['total_sales'] ) < 0.01 && $sales['orders'] === (int) $report['orders_count'] && $sales['units'] === (int) $report['num_items_sold'] && abs( $sales['aov'] - (float) $report['avg_order_value'] ) < 0.01 && $sales['units'] >= 12, 'AC-1 the dashboard\'s revenue, orders, units and average order value are the sales report\'s for the same week (' . wp_json_encode( $sales ) . ')' );
		ob_start();
		StoreDashboard::render();
		$html = (string) ob_get_clean();
		$check( str_contains( $html, 'path=/analytics/revenue' ) && str_contains( $html, 'Charts by day, week, month or any range' ), 'AC-2 the charts are linked from the dashboard' );

		// ---- AC-3: top products once per physical item -------------------------------------------------------
		$top  = StoreDashboard::topProducts( $after, $before, 50 );
		$mine = array_values( array_filter( $top, static fn ( $l ) => in_array( $l['id'], array( $en, $ar ), true ) ) );
		$check( 1 === count( $mine ) && $en === $mine[0]['id'] && $mine[0]['units'] >= 12, 'AC-3 the product sold in English (7) and in Arabic (5) is one line with 12 units (' . wp_json_encode( $mine ) . ')' );

		// ---- AC-4: orders requiring action, refunds to make ---------------------------------------------------
		$before_counts = StoreDashboard::needingAction();
		$hold          = wc_create_order();
		$hold->save();
		$s->track_order( $hold->get_id() );
		$hold->update_status( 'on-hold' );
		$after_counts = StoreDashboard::needingAction();
		$check( $after_counts['on-hold'] === $before_counts['on-hold'] + 1 && $after_counts['processing'] >= 2, 'AC-4 an order put on hold is counted among the orders requiring action, beside the confirmed ones' );
		$pending_before = StoreDashboard::pendingRefunds();
		$o1->update_status( 'cancelled' );
		$pending = StoreDashboard::pendingRefunds();
		$check( in_array( $o1->get_id(), $pending['cancelled_paid'], true ) && ! in_array( $o1->get_id(), $pending_before['cancelled_paid'], true ), 'AC-4 a paid order cancelled and not refunded is a refund to make' );
		wc_create_refund( array( 'order_id' => $o1->get_id(), 'amount' => (string) $o1->get_total() ) );
		$check( ! in_array( $o1->get_id(), StoreDashboard::pendingRefunds()['cancelled_paid'], true ), 'AC-4 once refunded it is no longer counted' );
		$check( str_contains( $html, 'status=wc-on-hold' ) && str_contains( $html, 'status=wc-cancelled' ) && str_contains( $html, 'Orders requiring action' ), 'AC-4 the counts link to the filtered orders list' );

		// ---- AC-5: stock as the stock source reports it --------------------------------------------------------
		$low  = $w->simple( "{$tag}-LOW", null );
		$out  = $w->simple( "{$tag}-OUT", null );
		$out_ar = $w->translate( 'duplicate', $out );
		$fixture = new T75Source( array( "{$tag}-DUO" => 40, "{$tag}-LOW" => 1, "{$tag}-OUT" => 0 ) );
		$filter  = static fn () => $fixture;
		add_filter( StockGateway::SOURCE_FILTER, $filter );
		update_option( 'woocommerce_notify_low_stock_amount', 2 );
		$stock = StoreDashboard::stock( true );
		remove_filter( StockGateway::SOURCE_FILTER, $filter );
		$check( 1 <= $stock['low'] && 1 <= $stock['out'] && 0 < $stock['unknown'], 'AC-5 the source\'s figures give the low and out-of-stock counts; items it does not know are unknown, not in stock (' . wp_json_encode( $stock ) . ')' );
		add_filter( StockGateway::SOURCE_FILTER, static fn () => new T75Source( array( "{$tag}-OUT" => 0 ) ), 20 );
		$only = StoreDashboard::stock( true );
		$check( $only['out'] === 1 && $out_ar > 0, 'AC-5 the out-of-stock product in two languages is counted once (out ' . $only['out'] . ')' );
		$check( is_array( get_transient( StoreDashboard::STOCK_KEY ) ), 'AC-5 the answer is kept for ten minutes, so the dashboard does not ask the source on every visit' );

		// ---- AC-6: quick actions, and who sees the widget ------------------------------------------------------
		$check( str_contains( $html, 'post-new.php?post_type=product' ) && str_contains( $html, 'post-new.php?post_type=shop_coupon' ) && str_contains( $html, 'status=wc-processing' ), 'AC-6 add a product, create a coupon and view pending orders are on the dashboard' );
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}cust", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}cust@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $customer );
		wp_set_current_user( $customer );
		ob_start();
		StoreDashboard::render();
		$check( '' === (string) ob_get_clean(), 'AC-6 a customer gets nothing from it' );

		return $ok;
	}
);

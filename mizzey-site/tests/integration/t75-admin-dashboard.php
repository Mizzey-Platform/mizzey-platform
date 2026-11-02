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

use Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler;
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
		$threshold_before = get_option( 'woocommerce_notify_low_stock_amount', null );
		$s->on_finish(
			static function () use ( $threshold_before ): void {
				delete_transient( StoreDashboard::STOCK_KEY );
				delete_option( StoreDashboard::RUN_KEY );
				as_unschedule_all_actions( StoreDashboard::COUNT_HOOK, null, 'mizzey' );
				null === $threshold_before ? delete_option( 'woocommerce_notify_low_stock_amount' ) : update_option( 'woocommerce_notify_low_stock_amount', $threshold_before );
				wp_set_current_user( 0 );
			}
		);
		// The background count, run to the end in this process: each batch queues the next.
		$count = static function (): array {
			delete_transient( StoreDashboard::STOCK_KEY );
			delete_option( StoreDashboard::RUN_KEY );
			as_unschedule_all_actions( StoreDashboard::COUNT_HOOK, null, 'mizzey' );
			$after = 0;
			for ( $i = 0; $i < 1000; $i++ ) {
				StoreDashboard::countBatch( $after );
				$next = as_get_scheduled_actions( array( 'hook' => StoreDashboard::COUNT_HOOK, 'group' => 'mizzey', 'status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => 1 ) );
				if ( array() === $next ) {
					break;
				}
				$action = reset( $next );
				$after  = (int) ( $action->get_args()[0] ?? 0 );
				as_unschedule_all_actions( StoreDashboard::COUNT_HOOK, null, 'mizzey' );
			}
			return (array) get_transient( StoreDashboard::STOCK_KEY );
		};
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
			OrdersScheduler::import( $o->get_id() );
			return wc_get_order( $o->get_id() );
		};
		$o1 = $order( $en, 7, 'en' );
		$o2 = $order( $ar, 5, 'ar' );
		// Back to the default language: the dashboard is an administration screen, which is always in English.
		do_action( 'wpml_switch_language', null );

		// ---- AC-1, AC-2 --------------------------------------------------------------------------------------
		[ $after, $before ] = StoreDashboard::range( '7d' );
		$sales = StoreDashboard::sales( $after, $before );
		$req   = new \WP_REST_Request( 'GET', '/wc-analytics/reports/revenue/stats' );
		$req->set_query_params( array( 'after' => $after, 'before' => $before, 'interval' => 'day', 'force_cache_refresh' => true ) );
		$report = (array) ( rest_do_request( $req )->get_data()['totals'] ?? array() );
		$check( abs( $sales['revenue'] - (float) $report['total_sales'] ) < 0.01 && $sales['orders'] === (int) $report['orders_count'] && $sales['units'] === (int) $report['num_items_sold'] && abs( $sales['aov'] - (float) $report['avg_order_value'] ) < 0.01 && abs( $sales['refunds'] - (float) $report['refunds'] ) < 0.01 && $sales['units'] >= 12, 'AC-1 the dashboard\'s revenue, orders, units and average order value are the sales report\'s for the same week (' . wp_json_encode( $sales ) . ')' );
		ob_start();
		StoreDashboard::render();
		$html = (string) ob_get_clean();
		$check( str_contains( $html, 'path=/analytics/revenue' ) && str_contains( $html, 'Charts by day, week, month or any range' ), 'AC-2 the charts are linked from the dashboard' );

		// ---- AC-3: top products once per physical item -------------------------------------------------------
		$top  = StoreDashboard::topProducts( $after, $before, 50 );
		$mine = array_values( array_filter( $top, static fn ( $l ) => in_array( $l['id'], array( $en, $ar ), true ) ) );
		$check( 1 === count( $mine ) && $en === $mine[0]['id'] && 12 === $mine[0]['units'], 'AC-3 the product sold in English (7) and in Arabic (5) is one line with 12 units (' . wp_json_encode( $mine ) . ')' );

		[ $parent, $variations ] = $w->variable( "{$tag}-VAR", array( 'S' => null, 'L' => null ) );
		$order( $variations[0], 3, 'en' );
		$order( $variations[1], 2, 'en' );
		$refunded = $order( $en, 4, 'en' );
		$line     = array_key_first( $refunded->get_items() );
		$refund   = wc_create_refund( array( 'order_id' => $refunded->get_id(), 'amount' => (string) $refunded->get_total(), 'line_items' => array( $line => array( 'qty' => 4, 'refund_total' => (float) $refunded->get_total() ) ) ) );
		if ( $refund instanceof \WC_Order_Refund ) {
			OrdersScheduler::import( $refund->get_id() );
		}
		OrdersScheduler::import( $refunded->get_id() );
		$top2   = StoreDashboard::topProducts( $after, $before, 50 );
		$by_id  = array_column( $top2, 'units', 'id' );
		$check( 3 === ( $by_id[ $variations[0] ] ?? 0 ) && 2 === ( $by_id[ $variations[1] ] ?? 0 ) && 12 === ( $by_id[ $en ] ?? 0 ), 'AC-3 two variations are two lines, and 4 units sold and refunded leave the product at 12 (' . wp_json_encode( array_intersect_key( $by_id, array_flip( array( $variations[0], $variations[1], $en ) ) ) ) . ')' );

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
		// A store may keep a fully refunded order Cancelled rather than Refunded: it is still not a refund to make.
		add_filter( 'woocommerce_order_fully_refunded_status', '__return_false' );
		wc_create_refund( array( 'order_id' => $o1->get_id(), 'amount' => (string) $o1->get_total() ) );
		remove_filter( 'woocommerce_order_fully_refunded_status', '__return_false' );
		$check( 'cancelled' === wc_get_order( $o1->get_id() )->get_status() && ! in_array( $o1->get_id(), StoreDashboard::pendingRefunds()['cancelled_paid'], true ), 'AC-4 once refunded it is no longer counted, even while it stays Cancelled' );
		$_GET = array( 'mizzey_refunds' => 'cancelled_paid' );
		$listed = StoreDashboard::listOnly( array( 'type' => 'shop_order', 'status' => array( 'wc-cancelled' ) ) );
		$_GET = array();
		$check( array_values( StoreDashboard::pendingRefunds()['cancelled_paid'] ?: array( 0 ) ) === array_values( (array) ( $listed['id'] ?? array() ) ) && ! isset( $listed['status'] ), 'AC-4 the link from a refund count opens exactly the orders counted' );
		$flag = $order( $en, 1, 'en' );
		\MizzeySite\Payments\PaymentRecord::flag( $flag, 't75 a payment arrived for a cancelled order' );
		$flagged_now = in_array( $flag->get_id(), StoreDashboard::pendingRefunds()['flagged'], true );
		foreach ( array( 'preparing', 'shipped', 'completed' ) as $step ) {
			wc_get_order( $flag->get_id() )->update_status( $step );
		}
		$check( $flagged_now && ! in_array( $flag->get_id(), StoreDashboard::pendingRefunds()['flagged'], true ), 'AC-4 a flagged order is a refund to make until it is delivered or refunded (' . wp_json_encode( array( $flagged_now, wc_get_order( $flag->get_id() )->get_status() ) ) . ')' );
		// Every query the counts make for orders by status is bounded: a count, or a list with a limit.
		$seen = array();
		$spy  = static function ( $sql ) use ( &$seen ) {
			if ( is_string( $sql ) && preg_match( "/'wc-(cancelled|on-hold|processing|preparing|delivery-failed)'/", $sql ) ) {
				$seen[] = $sql;
			}
			return $sql;
		};
		add_filter( 'query', $spy, 99 );
		StoreDashboard::needingAction();
		StoreDashboard::pendingRefunds();
		remove_filter( 'query', $spy, 99 );
		$unbounded = array_values( array_filter( $seen, static fn ( $q ) => ! preg_match( '/\bLIMIT\b|COUNT\(|FOUND_ROWS/i', $q ) ) );
		$check( array() !== $seen && array() === $unbounded, 'AC-4 the orders requiring action are counted by the database and the refund lists are limited, never every order loaded (' . count( $seen ) . ' queries, unbounded: ' . wp_json_encode( array_map( static fn ( $q ) => substr( preg_replace( '/\s+/', ' ', $q ), 0, 160 ), $unbounded ) ) . ')' );
		$check( str_contains( $html, 'status=wc-on-hold' ) && str_contains( $html, 'mizzey_refunds=cancelled_paid' ) && str_contains( $html, 'mizzey_refunds=flagged' ) && str_contains( $html, 'Orders requiring action' ), 'AC-4 the counts link to the filtered orders list' );

		// ---- AC-5: stock as the stock source reports it --------------------------------------------------------
		$low  = $w->simple( "{$tag}-LOW", null );
		$out  = $w->simple( "{$tag}-OUT", null );
		$out_ar = $w->translate( 'duplicate', $out );
		$fixture = new T75Source( array( "{$tag}-DUO" => 40, "{$tag}-LOW" => 1, "{$tag}-OUT" => 0 ) );
		$filter  = static fn () => $fixture;
		add_filter( StockGateway::SOURCE_FILTER, $filter );
		update_option( 'woocommerce_notify_low_stock_amount', 2 );
		$stock = $count();
		remove_filter( StockGateway::SOURCE_FILTER, $filter );
		$check( 1 <= $stock['low'] && 1 <= $stock['out'] && 0 < $stock['unknown'], 'AC-5 the source\'s figures give the low and out-of-stock counts; items it does not know are unknown, not in stock (' . wp_json_encode( $stock ) . ')' );
		add_filter( StockGateway::SOURCE_FILTER, static fn () => new T75Source( array( "{$tag}-OUT" => 0 ) ), 20 );
		$only = $count();
		$check( $only['out'] === 1 && $out_ar > 0, 'AC-5 the out-of-stock product in two languages is counted once (out ' . $only['out'] . ')' );
		$check( is_array( get_transient( StoreDashboard::STOCK_KEY ) ), 'AC-5 the count is kept, so the dashboard does not ask the source on every visit' );
		delete_transient( StoreDashboard::STOCK_KEY );
		as_unschedule_all_actions( StoreDashboard::COUNT_HOOK, null, 'mizzey' );
		$first = StoreDashboard::stock();
		$check( null === $first && false !== as_next_scheduled_action( StoreDashboard::COUNT_HOOK, null, 'mizzey' ), 'AC-5 with no count yet the dashboard answers at once and queues the count in the background' );

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

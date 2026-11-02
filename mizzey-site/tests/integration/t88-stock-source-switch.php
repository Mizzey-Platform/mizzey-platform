<?php
/**
 * t88, the stock source switch (specs/049-stock-source-switch, D-28): the store default, a product's own choice and the
 * master switch route each item to the ERP or the store's own stock; the store's own stock applies each change once,
 * never below zero for a sale, keeps both languages in step and records every change; the checkout's reservation works
 * on it unchanged; staff set it with a reason; the feed route sets and changes it by SKU for an account allowed to, and
 * for nobody else; an ERP that does not answer never moves an item to the store's stock. In-process, and over HTTP
 * for the feed route's authentication. Every product, order, user, option and ledger row it makes is removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Checkout\OrderStock;
use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Stock\RoutedStockSource;
use MizzeySite\Stock\StockFeedRoute;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockIdentity;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockMode;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceAdmin;
use MizzeySite\Stock\StockSourceUnavailable;
use MizzeySite\Stock\StockWriteGuard;
use MizzeySite\Stock\StoreStockSource;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** The ERP stand-in: fixed levels, every change recorded by key, or no answer at all when told to be down. */
final class T88Erp implements StockSource {
	/** @var array<string,int> */
	public array $applied = array();
	public bool $down = false;
	/** @param array<int,int> $levels */
	public function __construct( public array $levels ) {}
	public function name(): string { return 't88-erp'; }
	public function level( StockItem $item ): int {
		if ( $this->down ) {
			throw new StockSourceUnavailable( 'down' );
		}
		return $this->levels[ $item->id ] ?? 0;
	}
	public function adjust( StockItem $item, int $delta, string $operationKey ): int {
		if ( $this->down ) {
			throw new StockSourceUnavailable( 'down' );
		}
		if ( ! isset( $this->applied[ $operationKey ] ) ) {
			$this->applied[ $operationKey ] = $delta;
			$this->levels[ $item->id ]       = ( $this->levels[ $item->id ] ?? 0 ) + $delta;
		}
		return $this->levels[ $item->id ];
	}
}

run(
	new Scenario( 't88-stock-source-switch', 'INT-16, ERP-07, ADM-73, D-28: the stock source switch' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		sign_in_options( $s, array( FixtureStockSource::OPTION => 'no', 'woocommerce_manage_stock' => 'yes', StockMode::DEFAULT_OPTION => StockMode::ERP, StockMode::ERP_OFF_OPTION => 'no' ) );
		StoreStockSource::install();
		$tag = 't88' . wp_rand( 1000, 9999 );
		$w   = new Workflows( $s );

		$a = $w->simple( "{$tag}-A", null );
		$b = $w->simple( "{$tag}-B", null );
		list( $parent, $vars ) = $w->variable( "{$tag}-V", array( 'S' => null, 'L' => null ) );
		$items = array( $a, $b, $parent, $vars[0], $vars[1] );
		$s->on_finish( static function () use ( $items, $wpdb ) {
			foreach ( $items as $id ) {
				$wpdb->delete( StoreStockSource::table(), array( 'item_id' => $id ), array( '%d' ) );
			}
		} );
		foreach ( array( $a => 7, $b => 4, $vars[0] => 3, $vars[1] => 6 ) as $id => $qty ) {
			$p = wc_get_product( $id );
			$p->set_manage_stock( true );
			$p->set_stock_quantity( $qty );
			$p->save();
		}
		$a_ar = $w->translate( 'duplicate', $a );

		$erp  = new T88Erp( array( $a => 50, $b => 40, $vars[0] => 30, $vars[1] => 20 ) );
		$bind = static fn () => $erp;
		add_filter( StockGateway::SOURCE_FILTER, $bind, 10 );
		$s->on_finish( static function () use ( $bind ) { remove_filter( StockGateway::SOURCE_FILTER, $bind, 10 ); } );
		$level = static fn ( int $id ) => StockGateway::make()->level( $id )->quantity;
		$item  = static fn ( int $id ) => StockIdentity::resolve( $id );

		// ---- S-1: the store default and each item's own choice --------------------------------------------------
		$check( apply_filters( StockGateway::SOURCE_FILTER, null ) instanceof RoutedStockSource, 'FR-001 the store\'s stock source is the router, wrapping the bound ERP adapter' );
		$check( 50 === $level( $a ) && 40 === $level( $b ) && 50 === $level( $a_ar ), 'S-1 with nothing set every item reads the ERP, in both languages (' . wp_json_encode( array( $level( $a ), $level( $b ), $level( $a_ar ) ) ) . ')' );
		StockMode::choose( $item( $a ), StockMode::STORE );
		$check( 7 === $level( $a ) && 7 === $level( $a_ar ) && 40 === $level( $b ), 'S-1 a product set to its own stock reads it, in both languages; the others still read the ERP (' . wp_json_encode( array( $level( $a ), $level( $a_ar ), $level( $b ) ) ) . ')' );
		StockMode::choose( $item( $vars[0] ), StockMode::STORE );
		$check( 3 === $level( $vars[0] ) && 20 === $level( $vars[1] ), 'S-1 one variation on its own stock, its sibling on the ERP' );
		update_option( StockMode::DEFAULT_OPTION, StockMode::STORE );
		$check( 4 === $level( $b ) && 6 === $level( $vars[1] ) && 7 === $level( $a ), 'S-1 the store default on own stock moves every item that follows it' );
		StockMode::choose( $item( $b ), StockMode::ERP );
		$check( 40 === $level( $b ), 'S-1 an item that names the ERP keeps it under an own-stock default' );

		// ---- S-2: the master switch ------------------------------------------------------------------------------
		update_option( StockMode::ERP_OFF_OPTION, 'yes' );
		$check( 4 === $level( $b ) && StockMode::onStore( $b ), 'S-2 "ERP off" sends even an item that names the ERP to the store\'s own stock' );
		update_option( StockMode::ERP_OFF_OPTION, 'no' );
		$check( 40 === $level( $b ) && StockMode::ERP === StockMode::choice( $item( $b )->id ), 'S-2 turned back on, each item\'s own choice applies again' );
		update_option( StockMode::DEFAULT_OPTION, StockMode::ERP );

		// ---- S-3: the store's own stock --------------------------------------------------------------------------
		$store = new StoreStockSource();
		$k1    = "{$tag}-k1";
		$after = StockGateway::make()->adjust( $a, -2, $k1 )->quantity;
		$again = StockGateway::make()->adjust( $a, -2, $k1 )->quantity;
		$ar    = wc_get_product( $a_ar );
		$check( 5 === $after && 5 === $again && 5 === (int) wc_get_product( $a )->get_stock_quantity() && 5 === (int) $ar->get_stock_quantity(), 'S-3 a change is applied once per key, and WooCommerce\'s figure follows in both languages (' . wp_json_encode( array( $after, $again, wc_get_product( $a )->get_stock_quantity(), $ar->get_stock_quantity() ) ) . ')' );
		$over = StockGateway::make()->adjust( $a, -6, "{$tag}-k2" );
		$check( ! $over->isKnown() && 5 === $level( $a ), 'S-3 a sale for more than the store holds is refused and changes nothing' );
		$rows = StoreStockSource::history( $a );
		$check( 1 === count( $rows ) && -2 === (int) $rows[0]->delta && 5 === (int) $rows[0]->level_after && StoreStockSource::ORDER === $rows[0]->origin, 'ADM-73 the change is recorded once, with its figure after and its origin' );
		$store->set( $item( $vars[0] ), 0, "{$tag}-zero", StoreStockSource::STAFF, 'counted', 0 );
		$check( 'outofstock' === wc_get_product( $vars[0] )->get_stock_status(), 'S-3 a variation set to nothing on its own stock is out of stock' );
		$store->set( $item( $vars[0] ), 3, "{$tag}-three", StoreStockSource::STAFF, 'counted', 0 );
		$blocked = update_post_meta( $a, '_stock', '99' );
		$check( 5 === $level( $a ) && 'instock' === wc_get_product( $vars[0] )->get_stock_status(), 'D-19 a direct write to the stock field of an own-stock item is still refused (' . var_export( $blocked, true ) . ')' );

		// ---- The checkout's reservation on the store's own stock ---------------------------------------------------
		$order = wc_create_order();
		$order->add_product( wc_get_product( $a ), 2 );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		$reserved = OrderStock::reserve( $order, StockGateway::make() );
		$twice    = OrderStock::reserve( wc_get_order( $order->get_id() ), StockGateway::make() );
		$mid      = $level( $a );
		$released = OrderStock::release( wc_get_order( $order->get_id() ), StockGateway::make() );
		$check( $reserved && $twice && 3 === $mid && $released && 5 === $level( $a ) && empty( $erp->applied ), 'CHK-12 on own stock an order reserves each line once and a cancellation gives it back; the ERP is not asked (' . $mid . ', ' . $level( $a ) . ')' );

		// ---- AC-2: staff set own stock with a reason --------------------------------------------------------------
		$mgr = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}mgr@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $mgr );
		$cust = (int) wp_insert_user( array( 'user_login' => "{$tag}c", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}c@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $cust );
		wp_set_current_user( $mgr );
		$no_reason = StockSourceAdmin::save( $a_ar, null, '12', '' );
		$saved     = StockSourceAdmin::save( $a_ar, null, '12', 'stock count' );
		$on_erp    = StockSourceAdmin::save( $b, null, '9', 'stock count' );
		$bad       = StockSourceAdmin::save( $a, null, '-1', 'stock count' );
		$last      = StoreStockSource::history( $a )[0] ?? null;
		$check( '' !== $no_reason && '' === $saved && '' !== $on_erp && '' !== $bad && 12 === $level( $a ) && 40 === $level( $b ) && $last && StoreStockSource::STAFF === $last->origin && 'stock count' === $last->reason && $mgr === (int) $last->user_id && 7 === (int) $last->delta, 'AC-2 staff set own stock from either language with a reason, recorded with the user; no reason, a negative figure or an ERP item changes nothing' );
		StockSourceAdmin::save( $b, StockMode::STORE, '', '' );
		$check( StockMode::STORE === StockMode::choice( $item( $b )->id ), 'FR-004 staff who may edit the product choose its source' );
		wp_set_current_user( $cust );
		StockSourceAdmin::save( $b, StockMode::ERP, '1', 'x' );
		$check( StockMode::STORE === StockMode::choice( $item( $b )->id ) && 4 === $level( $b ), 'FR-004 a customer changes neither the source nor the figure' );
		wp_set_current_user( $mgr );
		StockSourceAdmin::save( $b, StockMode::ERP, '', '' );
		wp_set_current_user( 0 );

		// ---- S-4: the feed route ----------------------------------------------------------------------------------
		StockFeedRoute::grant();
		$feeder = (int) wp_insert_user( array( 'user_login' => "{$tag}feed", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}feed@example.invalid", 'role' => StockFeedRoute::ROLE ) );
		$s->track_user( $feeder );
		$sku = static fn ( int $id ) => wc_get_product( $id )->get_sku();
		$post = static function ( array $body ) {
			$r = new \WP_REST_Request( 'POST', '/mizzey/v1/stock' );
			$r->set_header( 'Content-Type', 'application/json' );
			$r->set_body( wp_json_encode( $body ) );
			return rest_do_request( $r );
		};
		wp_set_current_user( $cust );
		$denied = $post( array( 'items' => array( array( 'sku' => $sku( $a ), 'set' => 1, 'key' => "{$tag}-x" ) ) ) );
		wp_set_current_user( $feeder );
		$batch = $post( array( 'items' => array(
			array( 'sku' => $sku( $a ), 'set' => 20, 'key' => "{$tag}-f1" ),
			array( 'sku' => $sku( $vars[0] ), 'change' => 2, 'key' => "{$tag}-f2" ),
			array( 'sku' => $sku( $b ), 'set' => 1, 'key' => "{$tag}-f3" ),
			array( 'sku' => "{$tag}-NOPE", 'set' => 1, 'key' => "{$tag}-f4" ),
			array( 'sku' => $sku( $a ), 'set' => -1, 'key' => "{$tag}-f5" ),
			array( 'sku' => $sku( $vars[0] ), 'change' => -99, 'key' => "{$tag}-f6" ),
			array( 'sku' => $sku( $a ), 'set' => 1, 'key' => 'has space' ),
		) ) );
		$st     = array_column( (array) ( $batch->get_data()['items'] ?? array() ), 'status' );
		$repeat = $post( array( 'items' => array( array( 'sku' => $sku( $a ), 'set' => 3, 'key' => "{$tag}-f1" ) ) ) );
		$big    = $post( array( 'items' => array_fill( 0, 101, array( 'sku' => $sku( $a ), 'set' => 1, 'key' => "{$tag}-big" ) ) ) );
		$read   = rest_do_request( new \WP_REST_Request( 'GET', '/mizzey/v1/stock' ) );
		$get    = new \WP_REST_Request( 'GET', '/mizzey/v1/stock' );
		$get->set_param( 'skus', $sku( $a ) . ',' . $sku( $b ) );
		$got    = rest_do_request( $get )->get_data()['items'] ?? array();
		$check( 403 === $denied->get_status() && 12 === $level( $a ), 'S-4 an account without the feed permission is refused (' . $denied->get_status() . ')' );
		$check( array( 'applied', 'applied', 'erp-item', 'unknown-sku', 'invalid', 'refused', 'invalid' ) === $st && 20 === $level( $a ) && 5 === $level( $vars[0] ) && 40 === $level( $b ), 'S-4 the feed sets and changes own stock by SKU, and answers each item: an ERP item, an unknown SKU, a bad value or key, and a change below zero are refused alone (' . wp_json_encode( $st ) . ')' );
		$check( 'already-applied' === ( $repeat->get_data()['items'][0]['status'] ?? '' ) && 20 === $level( $a ) && 400 === $big->get_status() && 400 === $read->get_status(), 'S-4 a key sent again changes nothing; more than 100 items, or a read without SKUs, is refused whole' );
		$check( 'store' === ( $got[0]['source'] ?? '' ) && 20 === ( $got[0]['level'] ?? null ) && 'erp' === ( $got[1]['source'] ?? '' ) && ! isset( $got[1]['level'] ), 'S-4 a read gives each SKU\'s source, and the store\'s figure only where it is the one in use' );
		$feed_row = StoreStockSource::history( $a )[0] ?? null;
		$check( $feed_row && StoreStockSource::FEED === $feed_row->origin && $feeder === (int) $feed_row->user_id, 'ADM-73 a fed change is recorded with the feeding account' );
		wp_set_current_user( 0 );

		// Over HTTP: WordPress's own authentication of the route.
		list( $password ) = \WP_Application_Passwords::create_new_application_password( $feeder, array( 'name' => $tag ) );
		$url    = add_query_arg( 'rest_route', '/mizzey/v1/stock', home_url( '/' ) );
		$anon   = sign_in_json( 'POST', $url, array( 'items' => array( array( 'sku' => $sku( $a ), 'set' => 1, 'key' => "{$tag}-h0" ) ) ) );
		$authed = sign_in_json( 'POST', $url, array( 'items' => array( array( 'sku' => $sku( $a ), 'set' => 9, 'key' => "{$tag}-h1" ) ) ), array( 'Authorization' => 'Basic ' . base64_encode( "{$tag}feed:{$password}" ) ) );
		$check( 401 === $anon['status'] && 200 === $authed['status'] && 'applied' === ( $authed['data']['items'][0]['status'] ?? '' ) && 9 === $level( $a ), 'S-4 over HTTP the route refuses an unauthenticated call (401) and takes one from the feeding account\'s application password (' . $anon['status'] . ', ' . $authed['status'] . ')' );

		// ---- S-5: no silent fail-over ----------------------------------------------------------------------------
		$erp->down = true;
		$down      = StockGateway::make()->level( $b );
		$check( ! $down->isKnown() && StockMode::ERP === StockMode::sourceFor( $item( $b ) ) && 9 === $level( $a ), 'S-5, OD-41 an ERP that does not answer leaves its items unknown, not sellable, and on the ERP; own-stock items carry on' );
		$erp->down = false;

		// The admin's kept figure follows a change.
		delete_transient( StockWriteGuard::FIGURE . $a );
		$fig = StockWriteGuard::figure( $a );
		$store->set( $item( $a ), 11, "{$tag}-fig", StoreStockSource::STAFF, 'counted', 0 );
		$check( 9 === $fig && 11 === StockWriteGuard::figure( $a ) && 11 === StockWriteGuard::figure( $a_ar ), 'ERP-07 the admin\'s figure follows the change at once, in both languages' );

		return $ok;
	}
);

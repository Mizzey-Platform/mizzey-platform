<?php
/**
 * t60, checkout (specs/019-checkout, #255): WooCommerce's own order stock writers switched off, stock reserved at
 * placement through the adapter once per line and released on cancellation, a refused placement undone, the Egyptian
 * address structure, validation messages in both languages, the price snapshot, and WooCommerce's own checkout rows.
 * In-process, and over HTTP for the Store API checkout. Every product, order, user and zone it makes is removed when it
 * finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Checkout\EgyptianAddress;
use MizzeySite\Checkout\OrderStock;
use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceRefused;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A stock source that records every change by key, applies each key once, and refuses the items it is told to. */
final class T60Source implements StockSource {
	/** @var array<string,int> */
	public array $applied = array();
	/** @param array<int,int> $levels */
	public function __construct( public array $levels, public array $refuse = array() ) {}
	public function name(): string { return 't60'; }
	public function level( StockItem $item ): int { return $this->levels[ $item->id ] ?? 0; }
	/** Items whose change is applied and then answered with a timeout, as a source that applied but never replied. */
	public array $silent = array();
	/** @var callable|null Called with each key once its change is applied, before the answer. */
	public $after = null;
	public function adjust( StockItem $item, int $delta, string $operationKey ): int {
		if ( in_array( $item->id, $this->refuse, true ) ) {
			throw new StockSourceRefused( 'refused' );
		}
		if ( ! isset( $this->applied[ $operationKey ] ) ) {
			$this->applied[ $operationKey ] = $delta;
			$this->levels[ $item->id ]       = ( $this->levels[ $item->id ] ?? 0 ) + $delta;
		}
		if ( null !== $this->after ) {
			( $this->after )( $operationKey );
		}
		if ( in_array( $item->id, $this->silent, true ) ) {
			throw new \MizzeySite\Stock\StockSourceUnavailable( 'timeout' );
		}
		return $this->levels[ $item->id ];
	}
}

/** A processing order for the lines, product id => quantity. */
function t60_order( Scenario $s, array $lines ): \WC_Order {
	$order = wc_create_order();
	foreach ( $lines as $id => $qty ) {
		$order->add_product( wc_get_product( $id ), $qty );
	}
	$order->calculate_totals();
	$order->save();
	$s->track_order( $order->get_id() );
	return $order;
}

/** A Store API checkout body in the Egyptian structure. */
function t60_body( string $email, ?string $district ): array {
	$address = array( 'first_name' => 'Mona', 'last_name' => 'Sayed', 'address_1' => '5 Tahrir Street', 'city' => 'Cairo', 'state' => 'EG-01', 'country' => 'EG', 'postcode' => '', 'email' => $email, 'phone' => '01000000000' );
	if ( null !== $district ) {
		$address[ EgyptianAddress::DISTRICT ] = $district;
		$address[ EgyptianAddress::LANDMARK ] = 'Next to the metro';
	}
	return array( 'billing_address' => $address, 'shipping_address' => $address, 'payment_method' => 'cod', 'customer_note' => 'Ring twice' );
}

run(
	new Scenario( 't60-checkout', 'CHK-01 to CHK-12, BR-005, ENT-08: checkout' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		wp_set_current_user( 0 );
		$cod = get_option( 'woocommerce_cod_settings', array() );
		sign_in_options(
			$s,
			array(
				FixtureStockSource::OPTION          => 'no',
				'woocommerce_manage_stock'          => 'yes',
				'woocommerce_enable_guest_checkout' => 'yes',
				'woocommerce_cod_settings'          => array_merge( is_array( $cod ) ? $cod : array(), array( 'enabled' => 'yes', 'enable_for_virtual' => 'yes', 'enable_for_methods' => array() ) ),
			)
		);
		$s->on_finish( static function () { delete_option( FixtureStockSource::LEDGER ); } );
		$tag  = 't60' . wp_rand( 1000, 9999 );
		$five = $s->simple_product( 't60 serum', '100', null );
		$five->set_manage_stock( true );
		$five->set_stock_quantity( 5 );
		$five->save();
		$two = $s->simple_product( 't60 balm', '40', null );
		$two->set_manage_stock( true );
		$two->set_stock_quantity( 2 );
		$two->save();

		// D-19 item 5: no WooCommerce order path changes stock on its own.
		$order = t60_order( $s, array( $five->get_id() => 2 ) );
		wc_reduce_stock_levels( $order );
		$reduced = (int) wc_get_product( $five->get_id() )->get_stock_quantity();
		wc_increase_stock_levels( $order );
		$restored = (int) wc_get_product( $five->get_id() )->get_stock_quantity();
		$held     = (bool) apply_filters( 'woocommerce_hold_stock_for_checkout', true );
		$restock  = (bool) apply_filters( 'woocommerce_can_restock_refunded_items', true, $order, array() );
		$lineedit = (bool) apply_filters( 'woocommerce_prevent_adjust_line_item_product_stock', false, null, 0 );
		$check( 5 === $reduced && 5 === $restored && ! $held && ! $restock && $lineedit, "CHK-12, D-19 WooCommerce's order stock reduction and restoration change nothing; the checkout hold, refund restocking and line-edit adjustment are off ({$reduced}, {$restored})" );

		// CHK-12: reserved through the adapter, once per line; a refusal undone; released once on cancellation.
		$source  = new T60Source( array( $five->get_id() => 5, $two->get_id() => 2 ) );
		$gateway = new StockGateway( $source, 1, null, 0 );
		$order   = t60_order( $s, array( $five->get_id() => 2, $two->get_id() => 1 ) );
		$first   = OrderStock::reserve( $order, $gateway );
		$again   = OrderStock::reserve( wc_get_order( $order->get_id() ), $gateway );
		$check( $first && $again && 2 === count( $source->applied ) && 3 === $source->levels[ $five->get_id() ] && 1 === $source->levels[ $two->get_id() ] && OrderStock::active( wc_get_order( $order->get_id() ) ), 'CHK-12 each line is reserved with the source, once: placing again reserves nothing more (' . wp_json_encode( $source->levels ) . ')' );
		$released = OrderStock::release( wc_get_order( $order->get_id() ), $gateway );
		$twice    = OrderStock::release( wc_get_order( $order->get_id() ), $gateway );
		$check( $released && $twice && 5 === $source->levels[ $five->get_id() ] && 2 === $source->levels[ $two->get_id() ] && 4 === count( $source->applied ), 'CHK-12, BR-007 a cancelled order releases each line once' );
		$picky   = new T60Source( array( $five->get_id() => 5, $two->get_id() => 2 ), array( $two->get_id() ) );
		$refused = t60_order( $s, array( $five->get_id() => 1, $two->get_id() => 1 ) );
		$no      = OrderStock::reserve( $refused, new StockGateway( $picky, 1, null, 0 ) );
		$level   = $picky->levels[ $five->get_id() ];
		$picky->refuse = array();
		$retry   = OrderStock::reserve( wc_get_order( $refused->get_id() ), new StockGateway( $picky, 1, null, 0 ) );
		$check( ! $no && 5 === $level && $retry && 4 === $picky->levels[ $five->get_id() ] && 1 === $picky->levels[ $two->get_id() ], 'CHK-12, BR-003 a line the source refuses refuses the placement, and the lines already reserved are released; placed again once the source accepts, each line is reserved (' . wp_json_encode( $picky->levels ) . ')' );
		$no_source = static fn () => new T60Source( array( $five->get_id() => 5 ), array( $five->get_id() ) );
		add_filter( StockGateway::SOURCE_FILTER, $no_source );
		$thrown = '';
		try {
			OrderStock::placedThroughStoreApi( t60_order( $s, array( $five->get_id() => 1 ) ) );
		} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e ) {
			$thrown = $e->getErrorCode() . ' ' . $e->getCode();
		}
		$classic = '';
		try {
			$classic_order = t60_order( $s, array( $five->get_id() => 1 ) );
			OrderStock::placedThroughClassic( $classic_order->get_id(), array(), $classic_order );
		} catch ( \Exception $e ) {
			$classic = $e->getMessage();
		}
		remove_filter( StockGateway::SOURCE_FILTER, $no_source );
		$check( 'mizzey_stock_not_reserved 409' === $thrown && false !== strpos( $classic, 'reserve the stock' ), 'CHK-12 a placement the source refuses is refused before payment: a 409 on the Store API, a message on the classic checkout (' . $thrown . ')' );
		// An edited order releases what it reserved, not what it now holds.
		$edit_source = new T60Source( array( $five->get_id() => 5 ) );
		$edit_gate   = new StockGateway( $edit_source, 1, null, 0 );
		$edited      = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $edited, $edit_gate );
		$edited = wc_get_order( $edited->get_id() );
		foreach ( $edited->get_items() as $edited_item ) {
			$edited_item->set_quantity( 4 );
			$edited_item->save();
		}
		$grown = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $grown, $edit_gate );
		foreach ( wc_get_order( $grown->get_id() )->get_items() as $grown_item ) {
			$grown_item->set_quantity( 3 );
			$grown_item->save();
		}
		$stale     = OrderStock::active( wc_get_order( $grown->get_id() ) );
		$resized   = OrderStock::reserve( wc_get_order( $grown->get_id() ), $edit_gate );
		$after_grow = $edit_source->levels[ $five->get_id() ];
		OrderStock::release( wc_get_order( $grown->get_id() ), $edit_gate );
		$check( ! $stale && $resized && 1 === $after_grow && OrderStock::active( wc_get_order( $grown->get_id() ) ) === false, 'CHK-12 an order edited from 1 to 3 is not taken as reserved; reserving again takes the 2 more, and its release gives back 3 (' . $after_grow . ' left while it held 3 and the other order 1)' );
		OrderStock::release( wc_get_order( $edited->get_id() ), $edit_gate );
		$check( 5 === $edit_source->levels[ $five->get_id() ], 'CHK-12 an order edited from 1 to 4 after placement releases the 1 it reserved, not 4 (' . $edit_source->levels[ $five->get_id() ] . ' left)' );
		// A quantity changed back and forth asks under a new key each time: 1, 3, 1, then 3 again holds 3.
		$cycle_source = new T60Source( array( $five->get_id() => 5 ) );
		$cycle_gate   = new StockGateway( $cycle_source, 1, null, 0 );
		$cycle        = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $cycle, $cycle_gate );
		$cycle_levels = array();
		foreach ( array( 3, 1, 3 ) as $cycle_qty ) {
			foreach ( wc_get_order( $cycle->get_id() )->get_items() as $cycle_item ) {
				$cycle_item->set_quantity( $cycle_qty );
				$cycle_item->save();
			}
			OrderStock::reserve( wc_get_order( $cycle->get_id() ), $cycle_gate );
			$cycle_levels[] = $cycle_source->levels[ $five->get_id() ];
		}
		OrderStock::release( wc_get_order( $cycle->get_id() ), $cycle_gate );
		$check( array( 2, 4, 2 ) === $cycle_levels && 5 === $cycle_source->levels[ $five->get_id() ], 'CHK-12 an order resized from 1 to 3, back to 1 and to 3 again takes and gives back each change, and its release gives back all (' . wp_json_encode( $cycle_levels ) . ', ' . $cycle_source->levels[ $five->get_id() ] . ' after release)' );
		// A crash after an undo reached the source, before the record was saved: the next placement does not take the
		// undone line as reserved. The source keeps what the record held when the undo was applied, and it is put back.
		$crash_source = new T60Source( array( $five->get_id() => 5, $two->get_id() => 2 ), array( $two->get_id() ) );
		$crash_gate   = new StockGateway( $crash_source, 1, null, 0 );
		$crash        = t60_order( $s, array( $five->get_id() => 1, $two->get_id() => 1 ) );
		$at_crash     = null;
		$crash_source->after = static function ( string $key ) use ( &$at_crash, $crash ): void {
			if ( null === $at_crash && str_ends_with( $key, '-undo' ) ) {
				wp_cache_flush();
				$at_crash = wc_get_order( $crash->get_id() )->get_meta( OrderStock::RECORD );
			}
		};
		$crash_first = OrderStock::reserve( $crash, $crash_gate );
		$crash_source->after  = null;
		$crash_source->refuse = array();
		$restored = wc_get_order( $crash->get_id() );
		$restored->update_meta_data( OrderStock::RECORD, is_array( $at_crash ) ? $at_crash : array() );
		$restored->save_meta_data();
		$crash_again = OrderStock::reserve( wc_get_order( $crash->get_id() ), $crash_gate );
		$check( ! $crash_first && null !== $at_crash && $crash_again && 4 === $crash_source->levels[ $five->get_id() ] && 1 === $crash_source->levels[ $two->get_id() ], 'CHK-12 a crash between an undo and saving the record leaves the undo on the record, so placing again reserves the line afresh (' . wp_json_encode( $crash_source->levels ) . ')' );
		// The same crash before the undo reached the source: the next placement sends the undo first, so the line's
		// stock is not held twice.
		$lost_source = new T60Source( array( $five->get_id() => 5, $two->get_id() => 2 ), array( $two->get_id() ) );
		$lost_gate   = new StockGateway( $lost_source, 1, null, 0 );
		$lost        = t60_order( $s, array( $five->get_id() => 1, $two->get_id() => 1 ) );
		$lost_record = null;
		$lost_key    = '';
		$lost_source->after = static function ( string $key ) use ( &$lost_record, &$lost_key, $lost ): void {
			if ( null === $lost_record && str_ends_with( $key, '-undo' ) ) {
				wp_cache_flush();
				$lost_record = wc_get_order( $lost->get_id() )->get_meta( OrderStock::RECORD );
				$lost_key    = $key;
			}
		};
		OrderStock::reserve( $lost, $lost_gate );
		$lost_source->after  = null;
		$lost_source->refuse = array();
		// The undo never arrived.
		$lost_source->levels[ $five->get_id() ] -= (int) ( $lost_source->applied[ $lost_key ] ?? 0 );
		unset( $lost_source->applied[ $lost_key ] );
		$lost_order = wc_get_order( $lost->get_id() );
		$lost_order->update_meta_data( OrderStock::RECORD, is_array( $lost_record ) ? $lost_record : array() );
		$lost_order->save_meta_data();
		$lost_again = OrderStock::reserve( wc_get_order( $lost->get_id() ), $lost_gate );
		$check( '' !== $lost_key && $lost_again && 4 === $lost_source->levels[ $five->get_id() ] && 1 === $lost_source->levels[ $two->get_id() ], 'CHK-12 a crash before an undo reached the source: placing again sends the undo first, so the line holds its stock once (' . wp_json_encode( $lost_source->levels ) . ')' );
		// A crash after a release reached the source, before the record was saved: paying the order again reserves afresh.
		$freed_source = new T60Source( array( $five->get_id() => 5 ) );
		$freed_gate   = new StockGateway( $freed_source, 1, null, 0 );
		$freed        = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $freed, $freed_gate );
		$at_release = null;
		$freed_source->after = static function ( string $key ) use ( &$at_release, $freed ): void {
			if ( null === $at_release && str_ends_with( $key, '-release' ) ) {
				wp_cache_flush();
				$at_release = wc_get_order( $freed->get_id() )->get_meta( OrderStock::RECORD );
			}
		};
		OrderStock::release( wc_get_order( $freed->get_id() ), $freed_gate );
		$freed_source->after = null;
		$freed_order = wc_get_order( $freed->get_id() );
		$freed_order->update_meta_data( OrderStock::RECORD, is_array( $at_release ) ? $at_release : array() );
		$freed_order->save_meta_data();
		$freed_again = OrderStock::reserve( wc_get_order( $freed->get_id() ), $freed_gate );
		$check( null !== $at_release && $freed_again && 4 === $freed_source->levels[ $five->get_id() ] && OrderStock::active( wc_get_order( $freed->get_id() ) ), 'CHK-12 a crash between a release and saving the record leaves the release on the record, so placing the order again reserves its stock afresh (' . $freed_source->levels[ $five->get_id() ] . ' left of 5)' );
		// A resize the source applied but never answered: the order changed back does not count as reserved, and
		// reserving again settles it first.
		$back_source = new T60Source( array( $five->get_id() => 5 ) );
		$back        = t60_order( $s, array( $five->get_id() => 3 ) );
		OrderStock::reserve( $back, new StockGateway( $back_source, 1, null, 0 ) );
		$set_back = static function ( int $qty ) use ( $back ): void {
			foreach ( wc_get_order( $back->get_id() )->get_items() as $back_item ) {
				$back_item->set_quantity( $qty );
				$back_item->save();
			}
		};
		$set_back( 1 );
		$back_source->silent = array( $five->get_id() );
		$shrunk = OrderStock::reserve( wc_get_order( $back->get_id() ), new StockGateway( $back_source, 1, null, 0 ) );
		$back_source->silent = array();
		$set_back( 3 );
		$back_active = OrderStock::active( wc_get_order( $back->get_id() ) );
		$regrown     = OrderStock::reserve( wc_get_order( $back->get_id() ), new StockGateway( $back_source, 1, null, 0 ) );
		$check( ! $shrunk && ! $back_active && $regrown && 2 === $back_source->levels[ $five->get_id() ] && OrderStock::active( wc_get_order( $back->get_id() ) ), 'CHK-12 an unanswered resize from 3 to 1, then the order back at 3: not taken as reserved; reserving again settles the resize and takes the 2 back (' . $back_source->levels[ $five->get_id() ] . ' left of 5)' );
		// A crash after a reserve reached the source, before the record was saved, then the line changed to 3: the reserve
		// is settled as sent (1), then resized to 3, so 3 are held, not 1.
		$sent_source = new T60Source( array( $five->get_id() => 5 ) );
		$sent_gate   = new StockGateway( $sent_source, 1, null, 0 );
		$sent        = t60_order( $s, array( $five->get_id() => 1 ) );
		$at_reserve  = null;
		$sent_source->after = static function ( string $key ) use ( &$at_reserve, $sent ): void {
			if ( null === $at_reserve && str_ends_with( $key, '-reserve' ) ) {
				wp_cache_flush();
				$at_reserve = wc_get_order( $sent->get_id() )->get_meta( OrderStock::RECORD );
			}
		};
		OrderStock::reserve( $sent, $sent_gate );
		$sent_source->after = null;
		$sent_order = wc_get_order( $sent->get_id() );
		$sent_order->update_meta_data( OrderStock::RECORD, is_array( $at_reserve ) ? $at_reserve : array() );
		$sent_order->save_meta_data();
		foreach ( wc_get_order( $sent->get_id() )->get_items() as $sent_item ) {
			$sent_item->set_quantity( 3 );
			$sent_item->save();
		}
		$sent_again = OrderStock::reserve( wc_get_order( $sent->get_id() ), $sent_gate );
		$check( $sent_again && 2 === $sent_source->levels[ $five->get_id() ] && OrderStock::active( wc_get_order( $sent->get_id() ) ), 'CHK-12 a crash after a reserve reached the source, then the line changed from 1 to 3: placing again holds 3, not 1 (' . $sent_source->levels[ $five->get_id() ] . ' left of 5)' );
		// Two payments of one order at once: the one that fails works from the record as stored, not the one it loaded,
		// so it does not undo what the other reserved.
		$twin_source = new T60Source( array( $five->get_id() => 5, $two->get_id() => 2 ) );
		$twin_gate   = new StockGateway( $twin_source, 1, null, 0 );
		$twin        = t60_order( $s, array( $five->get_id() => 1, $two->get_id() => 1 ) );
		$stale       = wc_get_order( $twin->get_id() );
		$stale->get_meta( OrderStock::RECORD );
		OrderStock::reserve( wc_get_order( $twin->get_id() ), $twin_gate );
		$twin_source->silent = array( $two->get_id() );
		OrderStock::reserve( $stale, $twin_gate );
		$twin_source->silent = array();
		$check( 4 === $twin_source->levels[ $five->get_id() ] && 1 === $twin_source->levels[ $two->get_id() ] && OrderStock::active( wc_get_order( $twin->get_id() ) ), 'CHK-12 a second payment of an order, loaded before the first reserved it, keeps the first one\'s reservation (' . wp_json_encode( $twin_source->levels ) . ')' );
		// A cancelled order whose reserve went unanswered asks it again, then gives back what it took.
		$gone_source = new T60Source( array( $five->get_id() => 5 ) );
		$gone_source->silent = array( $five->get_id() );
		$gone = t60_order( $s, array( $five->get_id() => 2 ) );
		OrderStock::reserve( $gone, new StockGateway( $gone_source, 1, null, 0 ) );
		$gone_source->silent = array();
		$gone_released = OrderStock::release( wc_get_order( $gone->get_id() ), new StockGateway( $gone_source, 1, null, 0 ) );
		$check( $gone_released && 5 === $gone_source->levels[ $five->get_id() ], 'CHK-12 an order released with its reserve unanswered settles the reserve, then gives the stock back (' . $gone_source->levels[ $five->get_id() ] . ' of 5)' );
		// A placement from a copy of the order loaded at 1, after another request changed it to 3 and reserved 3: the
		// stock work reads the order as stored, so the 3 stay held.
		$copy_source = new T60Source( array( $five->get_id() => 5 ) );
		$copy_gate   = new StockGateway( $copy_source, 1, null, 0 );
		$copied      = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $copied, $copy_gate );
		$old_copy = wc_get_order( $copied->get_id() );
		$old_copy->get_items();
		foreach ( wc_get_order( $copied->get_id() )->get_items() as $copied_item ) {
			$copied_item->set_quantity( 3 );
			$copied_item->save();
		}
		OrderStock::reserve( wc_get_order( $copied->get_id() ), $copy_gate );
		OrderStock::reserve( $old_copy, $copy_gate );
		$check( 2 === $copy_source->levels[ $five->get_id() ] && OrderStock::active( wc_get_order( $copied->get_id() ) ), 'CHK-12 a placement from a copy of the order loaded before another request changed it to 3 keeps the 3 held (' . $copy_source->levels[ $five->get_id() ] . ' left of 5)' );
		// Another request changes the stored quantity to 3 while this one still holds the order, at 1, in its caches: the
		// stock work under the lock reads the stored 3 and takes the 2 more.
		$cached_source = new T60Source( array( $five->get_id() => 5 ) );
		$cached_gate   = new StockGateway( $cached_source, 1, null, 0 );
		$cached        = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $cached, $cached_gate );
		$warm = wc_get_order( $cached->get_id() );
		foreach ( $warm->get_items() as $warm_item ) {
			$warm_item->get_quantity();
			// As another request writes it: straight to the database, past this request's caches.
			$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->prefix . 'woocommerce_order_itemmeta', array( 'meta_value' => '3' ), array( 'order_item_id' => $warm_item->get_id(), 'meta_key' => '_qty' ) );
		}
		OrderStock::reserve( wc_get_order( $cached->get_id() ), $cached_gate );
		$check( 2 === $cached_source->levels[ $five->get_id() ], 'CHK-12 a quantity another request stored as 3, while this request still holds the order at 1 in its caches, is reserved as 3 (' . $cached_source->levels[ $five->get_id() ] . ' left of 5)' );
		// A payment's failure and success arriving together: the failure releases, then the success, from a copy loaded
		// while the order was still reserved, reserves again instead of trusting its copy.
		$race_source = new T60Source( array( $five->get_id() => 5 ) );
		$bind_race   = static fn () => $race_source;
		add_filter( StockGateway::SOURCE_FILTER, $bind_race );
		$race = t60_order( $s, array( $five->get_id() => 1 ) );
		$race->set_status( 'pending' );
		$race->save();
		OrderStock::reserve( wc_get_order( $race->get_id() ), StockGateway::make() );
		$success = wc_get_order( $race->get_id() );
		$success->get_meta( OrderStock::RECORD );
		wc_get_order( $race->get_id() )->update_status( 'failed' );
		$after_failure = $race_source->levels[ $five->get_id() ];
		// The success callback works from its own copy: the stored order moves to processing, then the handler runs with
		// the copy, which still shows the reservation the failure gave back.
		remove_action( 'woocommerce_order_status_changed', array( OrderStock::class, 'statusChanged' ), 20 );
		wc_get_order( $race->get_id() )->update_status( 'processing' );
		add_action( 'woocommerce_order_status_changed', array( OrderStock::class, 'statusChanged' ), 20, 4 );
		OrderStock::statusChanged( $race->get_id(), 'failed', 'processing', $success );
		// A failure's release that reaches the order only after it was paid: the stored status is paid, so nothing goes back.
		$late_failure = wc_get_order( $race->get_id() );
		OrderStock::statusChanged( $race->get_id(), 'pending', 'failed', $late_failure );
		$after_late_failure = $race_source->levels[ $five->get_id() ];
		remove_filter( StockGateway::SOURCE_FILTER, $bind_race );
		$check( 5 === $after_failure && 4 === $race_source->levels[ $five->get_id() ] && 4 === $after_late_failure && OrderStock::active( wc_get_order( $race->get_id() ) ), 'CHK-12 a payment that fails and then succeeds from a copy loaded before the failure: the failure gives the stock back, the success reserves it again, and a failure release arriving after the payment gives nothing back (' . $after_failure . ' then ' . $race_source->levels[ $five->get_id() ] . ' left of 5, status ' . wc_get_order( $race->get_id() )->get_status() . ')' );
		// A reserve that the source applied but never answered: asked again with the same key, taken once.
		$quiet_source = new T60Source( array( $five->get_id() => 5 ) );
		$quiet_source->silent = array( $five->get_id() );
		$quiet = t60_order( $s, array( $five->get_id() => 2 ) );
		$unanswered = OrderStock::reserve( $quiet, new StockGateway( $quiet_source, 2, null, 0 ) );
		$quiet_source->silent = array();
		$answered = OrderStock::reserve( wc_get_order( $quiet->get_id() ), new StockGateway( $quiet_source, 1, null, 0 ) );
		$check( ! $unanswered && $answered && 3 === $quiet_source->levels[ $five->get_id() ], 'CHK-12 a reserve the source applied but did not answer is asked again with the same key, so the stock is taken once (' . $quiet_source->levels[ $five->get_id() ] . ' left of 5)' );
		// Paid without a reservation (a payment callback): reserved then; refused, the order is put on hold.
		$late_source = new T60Source( array( $five->get_id() => 5 ) );
		$bind_late   = static fn () => $late_source;
		add_filter( StockGateway::SOURCE_FILTER, $bind_late );
		$late = t60_order( $s, array( $five->get_id() => 1 ) );
		$late->update_status( 'processing' );
		$late_ok = OrderStock::active( wc_get_order( $late->get_id() ) );
		$late_source->refuse = array( $five->get_id() );
		$stuck = t60_order( $s, array( $five->get_id() => 1 ) );
		$stuck->update_status( 'processing' );
		$stuck_status = '' !== wc_get_order( $stuck->get_id() )->get_meta( OrderStock::UNRESERVED ) ? 'marked' : 'unmarked';
		$late_source->refuse = array();
		// An unpaid order trashed releases what it reserved.
		$trashed = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $trashed, StockGateway::make() );
		$before_trash = $late_source->levels[ $five->get_id() ];
		wc_get_order( $trashed->get_id() )->delete( false );
		$after_trash = $late_source->levels[ $five->get_id() ];
		// What the visitor's own unpaid order holds is theirs at the cart check.
		$mine = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $mine, StockGateway::make() );
		// WooCommerce's empty_cart() clears the order awaiting payment, so the cart is filled first.
		WC()->cart->empty_cart( false );
		WC()->cart->add_to_cart( $five->get_id(), 1 );
		WC()->session->set( 'order_awaiting_payment', $mine->get_id() );
		$held_back = apply_filters( 'mizzey_cart_stock_held', array() );
		$late_source->levels[ $five->get_id() ] = 0;
		$retry_problems = \MizzeySite\Cart\CartStock::problems( WC()->cart, StockGateway::make() );
		WC()->cart->empty_cart( false );
		$late_source->levels[ $five->get_id() ] = 5;
		WC()->session->set( 'order_awaiting_payment', null );
		remove_filter( StockGateway::SOURCE_FILTER, $bind_late );
		$check( $late_ok && 'marked' === $stuck_status && $after_trash === $before_trash + 1 && 1 === ( $held_back[ $five->get_id() ] ?? 0 ) && array() === $retry_problems, 'CHK-12 an order paid without a reservation is reserved then, or marked for staff when refused; an unpaid order trashed releases its stock; the visitor\'s own unpaid order counts as theirs at the cart check (' . wp_json_encode( array( $late_ok, $stuck_status, $before_trash, $after_trash, $held_back, $retry_problems ) ) . ')' );
		$bind = static fn () => $source;
		add_filter( StockGateway::SOURCE_FILTER, $bind );
		$cancel = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $cancel, StockGateway::make() );
		$cancel = wc_get_order( $cancel->get_id() );
		$cancel->update_status( 'cancelled' );
		remove_filter( StockGateway::SOURCE_FILTER, $bind );
		$check( ! OrderStock::active( wc_get_order( $cancel->get_id() ) ) && 'released' === ( current( OrderStock::record( wc_get_order( $cancel->get_id() ) ) )['state'] ?? '' ) && 5 === $source->levels[ $five->get_id() ], 'CHK-12, BR-007 an order moved to Cancelled releases its reservation' );

		// Paying an existing order checks its district too.
		$pay_order = t60_order( $s, array( $five->get_id() => 1 ) );
		$pay_order->set_billing_country( 'EG' );
		$pay_order->save();
		$pay_errors = new \WP_Error();
		do_action( 'woocommerce_checkout_validate_order_before_payment', wc_get_order( $pay_order->get_id() ), $pay_errors );
		$pay_order->update_meta_data( '_wc_billing/' . EgyptianAddress::DISTRICT, 'Maadi' );
		$pay_order->save();
		$pay_ok = new \WP_Error();
		do_action( 'woocommerce_checkout_validate_order_before_payment', wc_get_order( $pay_order->get_id() ), $pay_ok );
		$check( in_array( 'mizzey_district_missing', $pay_errors->get_error_codes(), true ) && ! in_array( 'mizzey_district_missing', $pay_ok->get_error_codes(), true ), 'CHK-01 paying an existing Egyptian order without a district is refused before payment; with it, not' );

		// CHK-01: the Egyptian address structure.
		$locale = WC()->countries->get_country_locale()['EG'] ?? array();
		$fields = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class )->get_fields_for_location( 'address' );
		$check( true === ( $locale['state']['required'] ?? null ) && 'Governorate' === ( $locale['state']['label'] ?? '' ) && true === ( $locale['postcode']['hidden'] ?? null ) && true === ( $locale['address_2']['hidden'] ?? null ) && isset( $fields[ EgyptianAddress::DISTRICT ], $fields[ EgyptianAddress::LANDMARK ] ) && 'EG' === ( $fields[ EgyptianAddress::DISTRICT ]['required']['customer']['properties']['address']['properties']['country']['const'] ?? '' ) && 'EG' === ( $fields[ EgyptianAddress::DISTRICT ]['hidden']['customer']['properties']['address']['properties']['country']['not']['const'] ?? '' ) && false === $fields[ EgyptianAddress::LANDMARK ]['required'], 'CHK-01 governorate required, city, street and building; postcode and second line hidden; district required and landmark optional as checkout address fields, for an Egyptian address only' );

		// [HTTP] CHK-01, CHK-11, CHK-12, BR-005: the Store API checkout.
		update_option( FixtureStockSource::OPTION, 'yes' );
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "{$tag} Egypt" );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'EG', 'country' );
		$zone->save();
		$s->on_finish( static function () use ( $zone ) { $zone->delete(); } );
		foreach ( \WC_Shipping_Zones::get_zones() as $existing ) {
			if ( (int) $existing['id'] !== $zone->get_id() ) {
				$moved = new \WC_Shipping_Zone( (int) $existing['id'] );
				$order = $moved->get_zone_order();
				$moved->set_zone_order( $order + 1000 );
				$moved->save();
				$s->on_finish( static function () use ( $moved, $order ) { $moved->set_zone_order( $order ); $moved->save(); } );
			}
		}
		$flat = $zone->add_shipping_method( 'flat_rate' );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Flat', 'cost' => '50', 'tax_status' => 'none' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$store = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth  = array( 'Cart-Token' => sign_in_header( $store, 'cart-token' ), 'Nonce' => sign_in_header( $store, 'nonce' ) );
		$cart  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 2 ), $auth, $store['cookies'] );
		$rates = (array) ( $cart['data']['shipping_rates'][0]['shipping_rates'] ?? array() );
		$methods = (array) ( $cart['data']['payment_methods'] ?? array() );
		$check( array() !== $rates && in_array( 'cod', $methods, true ) && isset( $cart['data']['totals']['total_shipping'] ), '[HTTP] CHK-03, CHK-04, CHK-08 the checkout offers shipping methods with their cost, the payment methods, and a total breakdown' );
		$missing = sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout' ), t60_body( "{$tag}-m@example.invalid", null ), $auth, $cart['cookies'] );
		$ar_url  = add_query_arg( 'lang', 'ar', rest_url( 'wc/store/v1/checkout' ) );
		// Its own cart: the store clears a cart when the language changes (WooCommerce Multilingual's cart setting).
		$store_ar   = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth_ar    = array( 'Cart-Token' => sign_in_header( $store_ar, 'cart-token' ), 'Nonce' => sign_in_header( $store_ar, 'nonce' ) );
		$cart_ar    = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 1 ), $auth_ar, $store_ar['cookies'] );
		$missing_ar = sign_in_json( 'POST', $ar_url, t60_body( "{$tag}-m@example.invalid", null ), $auth_ar, $cart_ar['cookies'] );
		// The Store API names the field in the error's details.
		$said    = (string) wp_json_encode( $missing['data']['data'] ?? array(), JSON_UNESCAPED_UNICODE );
		$said_ar = (string) wp_json_encode( $missing_ar['data']['data'] ?? array(), JSON_UNESCAPED_UNICODE );
		$check( 400 === $missing['status'] && false !== strpos( $said, 'District' ) && 400 === $missing_ar['status'] && false !== strpos( $said_ar, 'الحي' ), '[HTTP] CHK-01, CHK-11 a missing district is refused with a message naming it, in English and in Arabic (' . substr( $said, 0, 160 ) . ' | ' . substr( $said_ar, 0, 160 ) . ')' );
		$placed   = sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout' ), t60_body( "{$tag}-p@example.invalid", 'Garden City' ), $auth, $cart['cookies'] );
		$order_id = (int) ( $placed['data']['order_id'] ?? 0 );
		if ( $order_id > 0 ) {
			$s->track_order( $order_id );
		}
		wp_cache_flush();
		$made     = wc_get_order( $order_id );
		$district = $made ? (string) $made->get_meta( '_wc_billing/' . EgyptianAddress::DISTRICT ) : '';
		$ledger   = (array) get_option( FixtureStockSource::LEDGER, array() );
		$check( 200 === $placed['status'] && $made && 'Garden City' === $district && 'Ring twice' === $made->get_customer_note() && OrderStock::active( $made ) && 1 === count( $ledger ) && 5 === (int) wc_get_product( $five->get_id() )->get_stock_quantity() && 3 === StockGateway::make()->level( $five->get_id() )->quantity, '[HTTP] CHK-01, CHK-06, CHK-12 an order is placed with its district and note; its line is reserved with the stock source (3 of 5 left there), and WooCommerce\'s stock field is untouched (status ' . $placed['status'] . ' ' . wp_strip_all_tags( (string) ( $placed['data']['message'] ?? '' ) ) . '; before it ' . ( $missing['data']['code'] ?? '' ) . ', ' . ( $missing_ar['data']['code'] ?? '' ) . ')' );
		$five->set_regular_price( '150' );
		$five->save();
		$item = $made ? current( $made->get_items() ) : null;
		$check( $item && 200.0 === (float) wc_get_order( $order_id )->get_subtotal() && 100.0 === (float) $item->get_subtotal() / max( 1, $item->get_quantity() ), 'BR-005, ENT-08 a later price change leaves the order\'s item price as charged (100 a unit)' );
		// [HTTP] Paying an existing Egyptian order that has no district, through the Store API: the district sent with it
		// is kept and the order paid; sent without one, refused.
		$unpaid = t60_order( $s, array( $five->get_id() => 1 ) );
		$unpaid->set_billing_email( "{$tag}-u@example.invalid" );
		$unpaid->set_billing_country( 'EG' );
		$unpaid->set_status( 'pending' );
		$unpaid->save();
		$pay_body = static function ( ?string $district ) use ( $tag, $unpaid ): array {
			return array_merge( t60_body( "{$tag}-u@example.invalid", $district ), array( 'key' => $unpaid->get_order_key(), 'billing_email' => "{$tag}-u@example.invalid" ) );
		};
		$pay_url  = rest_url( 'wc/store/v1/checkout/' . $unpaid->get_id() );
		$store3   = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth3    = array( 'Cart-Token' => sign_in_header( $store3, 'cart-token' ), 'Nonce' => sign_in_header( $store3, 'nonce' ) );
		$pay_none = sign_in_json( 'POST', $pay_url, $pay_body( null ), $auth3, $store3['cookies'] );
		$pay_with = sign_in_json( 'POST', $pay_url, $pay_body( 'Heliopolis' ), $auth3, $store3['cookies'] );
		wp_cache_flush();
		$paid = wc_get_order( $unpaid->get_id() );
		$check( 200 !== $pay_none['status'] && 200 === $pay_with['status'] && $paid && 'Heliopolis' === (string) $paid->get_meta( '_wc_billing/' . EgyptianAddress::DISTRICT ) && 'pending' !== $paid->get_status(), '[HTTP] CHK-01 paying an existing Egyptian order through the Store API keeps the district sent with it, and is refused without one (' . $pay_none['status'] . ' ' . ( $pay_none['data']['code'] ?? '' ) . ', ' . $pay_with['status'] . ' ' . wp_strip_all_tags( (string) ( $pay_with['data']['message'] ?? '' ) ) . ')' );
		update_option( FixtureStockSource::OPTION, 'no' );
		$store2 = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth2  = array( 'Cart-Token' => sign_in_header( $store2, 'cart-token' ), 'Nonce' => sign_in_header( $store2, 'nonce' ) );
		$cart2  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $two->get_id(), 'quantity' => 1 ), $auth2, $store2['cookies'] );
		$blocked = sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout' ), t60_body( "{$tag}-b@example.invalid", 'Zamalek' ), $auth2, $cart2['cookies'] );
		$check( 200 !== $blocked['status'], '[HTTP] CHK-12, D-19 with no stock source bound, the checkout is refused (status ' . $blocked['status'] . ': ' . wp_strip_all_tags( (string) ( $blocked['data']['message'] ?? '' ) ) . ')' );

		return $ok;
	}
);

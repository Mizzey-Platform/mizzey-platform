<?php
/**
 * t102, adding to the cart takes account of the stock source (specs/062-add-to-cart-stock, #314, ERP-03): every route
 * that adds asks the stock source for what the cart would then hold, counts the item's other lines and its other
 * language record, lets the visitor's own unpaid order count as theirs, and fails closed. In-process with a stock
 * source that answers from a table, and over HTTP through the Store API and an add-to-cart link with the stand-in
 * fixture switched on and off. Every product it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Cart\AddToCartStock;
use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockIdentity;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceRefused;
use MizzeySite\Stock\StockSourceUnavailable;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_workflows.php';

/** A stock source answering from a fixed table, refusing an item it does not know, or down altogether. */
final class T102Source implements StockSource {
	/** @param array<int,int> $levels */
	public function __construct( public array $levels, public bool $down = false ) {}
	public function name(): string { return 't102'; }
	public function level( StockItem $item ): int {
		if ( $this->down ) {
			throw new StockSourceUnavailable( 'down' );
		}
		if ( ! array_key_exists( $item->id, $this->levels ) ) {
			throw new StockSourceRefused( 'unknown' );
		}
		return $this->levels[ $item->id ];
	}
	public function adjust( StockItem $item, int $delta, string $operationKey ): int { throw new StockSourceRefused( 'read only' ); }
}

function t102_gateway( array $levels, bool $down = false ): StockGateway {
	return new StockGateway( new T102Source( $levels, $down ), 1, null, 0 );
}

/** @param array<int,int> $lines record id => quantity, added the way code adds, which asks nothing */
function t102_cart( array $lines ): \WC_Cart {
	$cart = WC()->cart;
	$cart->empty_cart( false );
	foreach ( $lines as $id => $qty ) {
		$product = wc_get_product( $id );
		$parent  = $product instanceof \WC_Product_Variation ? $product->get_parent_id() : $id;
		$cart->add_to_cart( $parent, $qty, $product instanceof \WC_Product_Variation ? $id : 0, $product instanceof \WC_Product_Variation ? $product->get_variation_attributes() : array() );
	}
	wc_clear_notices();
	return $cart;
}

/** A fresh Store API session: its nonce and cookies. */
function t102_store( array $cookies = array() ): array {
	$cart = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $cookies );
	return array( 'auth' => array( 'Nonce' => sign_in_header( $cart, 'nonce' ) ), 'cookies' => $cart['cookies'] );
}

/** @return list<array{int,int}> the Store API cart as [id, quantity] pairs */
function t102_lines( array $answer ): array {
	return array_map( static fn ( $i ) => array( (int) $i['id'], (int) $i['quantity'] ), (array) ( $answer['data']['items'] ?? array() ) );
}

run(
	new Scenario( 't102-add-to-cart-stock', 'ERP-03: adding to the cart takes account of the stock source' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$w = new Workflows( $s );
		$fixture_before = get_option( FixtureStockSource::OPTION );
		$s->on_finish( static function () use ( $fixture_before ) {
			update_option( FixtureStockSource::OPTION, $fixture_before );
			WC()->cart->empty_cart( false );
		} );
		update_option( FixtureStockSource::OPTION, 'no' );

		$lamp = $s->simple_product( 't102 lamp', '300', null );
		$lamp->set_manage_stock( true );
		$lamp->set_stock_quantity( 3 );
		$lamp->save();
		$soap = $s->simple_product( 't102 soap', '100', null );
		[ $shirt_id, $sizes ] = $w->variable( 't102-shirt-' . wp_rand( 1000, 9999 ), array( 'S' => null, 'M' => null ) );
		$small  = (int) $sizes[0];
		$medium = (int) $sizes[1];
		$lamp_ar = $w->translate( 'duplicate', $lamp->get_id() );
		$check( $lamp_ar > 0, 'setup: the lamp has an Arabic record (' . $lamp_ar . ')' );

		// ---- AC-1: what the cart would hold is asked, and only that is let in ------------------------------------------
		$empty = t102_cart( array() );
		$check( null === AddToCartStock::problem( $lamp->get_id(), 3, $empty, t102_gateway( array( $lamp->get_id() => 3 ) ) ), 'AC-1 ERP-03 adding 3 when the source holds 3 is let in' );
		$four = (string) AddToCartStock::problem( $lamp->get_id(), 4, $empty, t102_gateway( array( $lamp->get_id() => 3 ) ) );
		$check( 'Only 3 of t102 lamp are available.' === $four, 'AC-1 adding 4 when the source holds 3 is refused, saying how many there are (' . $four . ')' );
		$two_in = t102_cart( array( $lamp->get_id() => 2 ) );
		$more   = (string) AddToCartStock::problem( $lamp->get_id(), 2, $two_in, t102_gateway( array( $lamp->get_id() => 3 ) ) );
		$check( 'Only 3 of t102 lamp are available, and your cart already holds 2.' === $more, 'AC-1 with 2 in the cart, 2 more is refused against 3, naming what the cart holds (' . $more . ')' );
		$check( null === AddToCartStock::problem( $lamp->get_id(), 1, $two_in, t102_gateway( array( $lamp->get_id() => 3 ) ) ), 'AC-1 with 2 in the cart, 1 more is let in against 3' );
		$out = (string) AddToCartStock::problem( $soap->get_id(), 1, $empty, t102_gateway( array( $soap->get_id() => 0 ) ) );
		$check( 't102 soap is out of stock.' === $out, 'AC-1 PDP-22 an item the source holds none of is refused as out of stock (' . $out . ')' );

		// ---- AC-2: one stock item, whatever line or language record holds it ------------------------------------------
		$ar_in = t102_cart( array( $lamp_ar => 2 ) );
		$both  = (string) AddToCartStock::problem( $lamp->get_id(), 2, $ar_in, t102_gateway( array( $lamp->get_id() => 3 ) ) );
		$check( $lamp_ar > 0 && str_starts_with( $both, 'Only 3 of t102 lamp' ), 'AC-2 2 in the cart under the Arabic record count against adding 2 of the English record (' . $both . ')' );
		$check( $lamp_ar > 0 && null !== AddToCartStock::problem( $lamp_ar, 2, t102_cart( array( $lamp->get_id() => 2 ) ), t102_gateway( array( $lamp->get_id() => 3 ) ) ), 'AC-2 and the other way round: the Arabic record asks about the same stock item' );
		$sizes_in = t102_cart( array( $small => 1 ) );
		$check( null === AddToCartStock::problem( $medium, 1, $sizes_in, t102_gateway( array( $small => 1, $medium => 1 ) ) ), 'AC-2 a variation is its own stock item: one small in the cart does not count against one medium' );
		$check( null !== AddToCartStock::problem( $small, 1, $sizes_in, t102_gateway( array( $small => 1, $medium => 1 ) ) ), 'AC-2 a second small against 1 is refused' );

		// ---- AC-3: no answer, no add -----------------------------------------------------------------------------------
		$down   = (string) AddToCartStock::problem( $lamp->get_id(), 1, $empty, t102_gateway( array(), true ) );
		$check( str_contains( $down, 'We could not confirm that t102 lamp is in stock' ), 'AC-3 ERP-05 a source that is down refuses the add (' . $down . ')' );
		$unknown = (string) AddToCartStock::problem( $lamp->get_id(), 1, $empty, t102_gateway( array() ) );
		$check( str_contains( $unknown, 'could not confirm' ), 'AC-3 a source that does not know the item refuses the add' );
		$unbound = (string) AddToCartStock::problem( $lamp->get_id(), 1, $empty, StockGateway::make() );
		$check( str_contains( $unbound, 'could not confirm' ), 'AC-3 with no stock source bound, nothing is let in (' . $unbound . ')' );

		// ---- AC-4: the visitor's own unpaid order counts as theirs -----------------------------------------------------
		$held = static fn () => array( $lamp->get_id() => 2 );
		add_filter( 'mizzey_cart_stock_held', $held );
		$retry = AddToCartStock::problem( $lamp->get_id(), 1, $two_in = t102_cart( array( $lamp->get_id() => 2 ) ), t102_gateway( array( $lamp->get_id() => 1 ) ) );
		remove_filter( 'mizzey_cart_stock_held', $held );
		$check( null === $retry, 'AC-4 2 in the cart already held by the visitor\'s unpaid order, and 1 left at the source: 1 more is let in' );

		// ---- AC-5: the filter every route runs -------------------------------------------------------------------------
		t102_cart( array() );
		$bind = static fn () => new T102Source( array( $lamp->get_id() => 3, $small => 5 ) );
		add_filter( StockGateway::SOURCE_FILTER, $bind, 50 );
		$pass   = apply_filters( 'woocommerce_add_to_cart_validation', true, $lamp->get_id(), 2 );
		$refuse = apply_filters( 'woocommerce_add_to_cart_validation', true, $lamp->get_id(), 5 );
		$notes  = wc_get_notices( 'error' );
		wc_clear_notices();
		$already = apply_filters( 'woocommerce_add_to_cart_validation', false, $lamp->get_id(), 1 );
		$quiet   = wc_get_notices( 'error' );
		wc_clear_notices();
		$var_ok  = apply_filters( 'woocommerce_add_to_cart_validation', true, $shirt_id, 1, $small );
		wc_clear_notices();
		remove_filter( StockGateway::SOURCE_FILTER, $bind, 50 );
		$check( true === $pass && false === $refuse && 1 === count( $notes ) && str_contains( (string) ( $notes[0]['notice'] ?? '' ), 'Only 3 of t102 lamp' ), 'AC-5 woocommerce_add_to_cart_validation lets 2 in and refuses 5 with one error notice' );
		$check( false === $already && array() === $quiet, 'AC-5 an add another check already refused stays refused, and stock is not asked or reported again' );
		$check( true === $var_ok, 'AC-5 a variation is asked about by its own record: the source knows the small shirt and not its parent, and it is let in' );

		// ---- AC-5 over HTTP: the Store API add-item route and an add-to-cart link --------------------------------------
		update_option( FixtureStockSource::OPTION, 'no' );
		$closed  = t102_store();
		$refused = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp->get_id(), 'quantity' => 1 ), $closed['auth'], $closed['cookies'] );
		$after   = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $refused['cookies'] );
		$check( 400 === $refused['status'] && str_contains( (string) ( $refused['data']['message'] ?? '' ), 'could not confirm' ) && array() === t102_lines( $after ), '[HTTP] AC-3 AC-5 the Store API refuses the add with no stock source, says why, and the cart stays empty (' . $refused['status'] . ', ' . ( $refused['data']['message'] ?? '' ) . ')' );

		// WooCommerce holds 5 lamps and the stand-in 3 (its own ledger takes 2), so only the stock source can refuse:
		// WooCommerce's own check would let the fourth and fifth in.
		$lamp->set_stock_quantity( 5 );
		$lamp->save();
		$ledger_key = 't102-' . wp_rand( 100000, 999999 );
		$s->on_finish( static function () use ( $ledger_key ) {
			$ledger = (array) get_option( FixtureStockSource::LEDGER, array() );
			unset( $ledger[ $ledger_key ] );
			update_option( FixtureStockSource::LEDGER, $ledger, false );
		} );
		update_option( FixtureStockSource::OPTION, 'yes' );
		$fixture_level = ( new FixtureStockSource() )->adjust( StockIdentity::resolve( $lamp->get_id() ), -2, $ledger_key );
		$check( 3 === $fixture_level, 'setup: the stand-in holds 3 lamps while WooCommerce holds 5 (' . $fixture_level . ')' );
		$open   = t102_store();
		$first  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp->get_id(), 'quantity' => 2 ), $open['auth'], $open['cookies'] );
		$second = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp->get_id(), 'quantity' => 2 ), $open['auth'], $first['cookies'] );
		$third  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp->get_id(), 'quantity' => 1 ), $open['auth'], $second['cookies'] );
		$check( in_array( $first['status'], array( 200, 201 ), true ) && array( array( $lamp->get_id(), 2 ) ) === t102_lines( $first ), '[HTTP] AC-1 the fixture holding 3: the Store API lets 2 in (' . $first['status'] . ')' );
		$check( 400 === $second['status'] && str_contains( (string) ( $second['data']['message'] ?? '' ), 'your cart already holds 2' ), '[HTTP] AC-1 2 more is refused, naming what the cart holds (' . $second['status'] . ', ' . ( $second['data']['message'] ?? '' ) . ')' );
		$check( in_array( $third['status'], array( 200, 201 ), true ) && array( array( $lamp->get_id(), 3 ) ) === t102_lines( $third ), '[HTTP] AC-1 1 more is let in, and the cart holds 3 (' . $third['status'] . ')' );
		if ( $lamp_ar > 0 ) {
			$ar = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $lamp_ar, 'quantity' => 1 ), $open['auth'], $third['cookies'] );
			$check( 400 === $ar['status'], '[HTTP] AC-2 with 3 English lamps in the cart, the Arabic lamp is refused: one stock item (' . $ar['status'] . ')' );
		}

		$jar  = sign_in_jar( t102_store()['cookies'] );
		$link = add_query_arg( array( 'add-to-cart' => $soap->get_id(), 'quantity' => 1 ), home_url( '/' ) );
		$r    = wp_remote_get( $link, array( 'redirection' => 0, 'timeout' => 60, 'cookies' => $jar ) ); // storefront-guard: exempt, only the cart it leaves is read.
		$jar  = sign_in_jar( array_merge( $jar, wp_remote_retrieve_cookies( $r ) ) );
		$got  = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $jar );
		$check( array( array( $soap->get_id(), 1 ) ) === t102_lines( $got ), '[HTTP] AC-5 an add-to-cart link (buy now, the product form) adds when the fixture answers' );

		update_option( FixtureStockSource::OPTION, 'no' );
		$jar2 = sign_in_jar( t102_store()['cookies'] );
		$r2   = wp_remote_get( $link, array( 'redirection' => 0, 'timeout' => 60, 'cookies' => $jar2 ) ); // storefront-guard: exempt, only the cart it leaves is read.
		$jar2 = sign_in_jar( array_merge( $jar2, wp_remote_retrieve_cookies( $r2 ) ) );
		$none = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $jar2 );
		$check( array() === t102_lines( $none ), '[HTTP] AC-3 AC-5 the same link with no stock source adds nothing' );

		// ---- AC-6: every message in Arabic -----------------------------------------------------------------------------
		switch_to_locale( 'ar' );
		$ar_msgs = array(
			AddToCartStock::problem( $soap->get_id(), 1, $empty = t102_cart( array() ), t102_gateway( array( $soap->get_id() => 0 ) ) ),
			AddToCartStock::problem( $lamp->get_id(), 4, $empty, t102_gateway( array( $lamp->get_id() => 3 ) ) ),
			AddToCartStock::problem( $lamp->get_id(), 2, t102_cart( array( $lamp->get_id() => 2 ) ), t102_gateway( array( $lamp->get_id() => 3 ) ) ),
			AddToCartStock::problem( $lamp->get_id(), 1, $empty, t102_gateway( array(), true ) ),
		);
		restore_previous_locale();
		$arabic = array_filter( $ar_msgs, static fn ( $m ) => is_string( $m ) && 1 === preg_match( '/\p{Arabic}/u', $m ) );
		$check( 4 === count( $arabic ), 'AC-6 FIX-04 each refusal reads in Arabic for an Arabic visitor (' . implode( ' | ', array_map( 'strval', $ar_msgs ) ) . ')' );

		return $ok;
	}
);

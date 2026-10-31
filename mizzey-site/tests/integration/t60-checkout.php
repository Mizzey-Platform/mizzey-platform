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
	public function adjust( StockItem $item, int $delta, string $operationKey ): int {
		if ( in_array( $item->id, $this->refuse, true ) ) {
			throw new StockSourceRefused( 'refused' );
		}
		if ( ! isset( $this->applied[ $operationKey ] ) ) {
			$this->applied[ $operationKey ] = $delta;
			$this->levels[ $item->id ]       = ( $this->levels[ $item->id ] ?? 0 ) + $delta;
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
		$check( $first && $again && 2 === count( $source->applied ) && 3 === $source->levels[ $five->get_id() ] && 1 === $source->levels[ $two->get_id() ] && 'yes' === wc_get_order( $order->get_id() )->get_meta( OrderStock::RESERVED ), 'CHK-12 each line is reserved with the source, once: placing again reserves nothing more (' . wp_json_encode( $source->levels ) . ')' );
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
		$bind = static fn () => $source;
		add_filter( StockGateway::SOURCE_FILTER, $bind );
		$cancel = t60_order( $s, array( $five->get_id() => 1 ) );
		OrderStock::reserve( $cancel, StockGateway::make() );
		$cancel = wc_get_order( $cancel->get_id() );
		$cancel->update_status( 'cancelled' );
		remove_filter( StockGateway::SOURCE_FILTER, $bind );
		$check( 'yes' === wc_get_order( $cancel->get_id() )->get_meta( OrderStock::RELEASED ) && 5 === $source->levels[ $five->get_id() ], 'CHK-12, BR-007 an order moved to Cancelled releases its reservation' );

		// CHK-01: the Egyptian address structure.
		$locale = WC()->countries->get_country_locale()['EG'] ?? array();
		$fields = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class )->get_fields_for_location( 'address' );
		$check( true === ( $locale['state']['required'] ?? null ) && 'Governorate' === ( $locale['state']['label'] ?? '' ) && true === ( $locale['postcode']['hidden'] ?? null ) && true === ( $locale['address_2']['hidden'] ?? null ) && isset( $fields[ EgyptianAddress::DISTRICT ], $fields[ EgyptianAddress::LANDMARK ] ) && true === $fields[ EgyptianAddress::DISTRICT ]['required'] && false === $fields[ EgyptianAddress::LANDMARK ]['required'], 'CHK-01 governorate required, city, street and building; postcode and second line hidden; district required and landmark optional as checkout address fields' );

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
		$missing_ar = sign_in_json( 'POST', $ar_url, t60_body( "{$tag}-m@example.invalid", null ), $auth, $cart['cookies'] );
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
		$check( 200 === $placed['status'] && $made && 'Garden City' === $district && 'Ring twice' === $made->get_customer_note() && 'yes' === $made->get_meta( OrderStock::RESERVED ) && 1 === count( $ledger ) && 5 === (int) wc_get_product( $five->get_id() )->get_stock_quantity() && 3 === StockGateway::make()->level( $five->get_id() )->quantity, '[HTTP] CHK-01, CHK-06, CHK-12 an order is placed with its district and note; its line is reserved with the stock source (3 of 5 left there), and WooCommerce\'s stock field is untouched (status ' . $placed['status'] . ')' );
		$five->set_regular_price( '150' );
		$five->save();
		$item = $made ? current( $made->get_items() ) : null;
		$check( $item && 200.0 === (float) wc_get_order( $order_id )->get_subtotal() && 100.0 === (float) $item->get_subtotal() / max( 1, $item->get_quantity() ), 'BR-005, ENT-08 a later price change leaves the order\'s item price as charged (100 a unit)' );
		update_option( FixtureStockSource::OPTION, 'no' );
		$store2 = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth2  = array( 'Cart-Token' => sign_in_header( $store2, 'cart-token' ), 'Nonce' => sign_in_header( $store2, 'nonce' ) );
		$cart2  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $two->get_id(), 'quantity' => 1 ), $auth2, $store2['cookies'] );
		$blocked = sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout' ), t60_body( "{$tag}-b@example.invalid", 'Zamalek' ), $auth2, $cart2['cookies'] );
		$check( 200 !== $blocked['status'], '[HTTP] CHK-12, D-19 with no stock source bound, the checkout is refused (status ' . $blocked['status'] . ': ' . wp_strip_all_tags( (string) ( $blocked['data']['message'] ?? '' ) ) . ')' );

		return $ok;
	}
);

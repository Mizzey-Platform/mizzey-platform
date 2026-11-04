<?php
/**
 * Scenario t90: the Egyptian address in full (specs/050, CHK-01, CHK-11, ACCT-04).
 *
 * AC-1: the address reads largest place first: governorate, city or area, district or neighbourhood, the kind of
 *       place, street or compound, building, floor, apartment, landmark; the phone is required for Egypt.
 * AC-2: a floor and an apartment are asked of a flat or an office, and not of a villa.
 * AC-3: one address: the checkout asks for a single address and the order is delivered to it.
 * AC-4: the building, floor and apartment reach every reader of the address as its second line, in the order's
 *       language: the order, the courier's address (Bosta's secondLine), the packing slip; a staff edit is carried.
 * AC-5: the address book keeps the same parts.
 *
 * Run: python mizzey-site/tests/integration/run.py --wp ../app/wp --only t90
 */


namespace MizzeySite\Tests\Integration;

use MizzeySite\Account\AddressBook;
use MizzeySite\Checkout\EgyptianAddress;
use MizzeySite\Stock\FixtureStockSource;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** A Store API checkout body: a full Egyptian address, with the parts given. */
function t90_body( string $email, array $parts ): array {
	$address = array_merge(
		array( 'first_name' => 'Mona', 'last_name' => 'Sayed', 'address_1' => 'Dar Misr Al Andalus, 70 Building', 'city' => 'New Cairo', 'state' => 'EG-01', 'country' => 'EG', 'postcode' => '', 'email' => $email, 'phone' => '01000000090', EgyptianAddress::DISTRICT => 'Third Settlement', EgyptianAddress::LANDMARK => 'Behind the club' ),
		$parts
	);
	return array( 'billing_address' => $address, 'shipping_address' => $address, 'payment_method' => 'cod', 'customer_note' => 'Call on arrival' );
}

run(
	new Scenario( 't90-egyptian-address', 'CHK-01, CHK-11, ACCT-04: the Egyptian address in full, one address' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		wp_set_current_user( 0 );
		$tag = 't90' . wp_rand( 1000, 9999 );

		// ---- AC-1, AC-2: the fields and their order -----------------------------------------------------------------
		$locale = WC()->countries->get_country_locale()['EG'] ?? array();
		$fields = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class )->get_fields_for_location( 'address' );
		$order  = array(
			'state'                       => (int) ( $locale['state']['index'] ?? 0 ),
			'city'                        => (int) ( $locale['city']['index'] ?? 0 ),
			EgyptianAddress::DISTRICT     => (int) ( $fields[ EgyptianAddress::DISTRICT ]['index'] ?? 0 ),
			EgyptianAddress::PLACE        => (int) ( $fields[ EgyptianAddress::PLACE ]['index'] ?? 0 ),
			'address_1'                   => (int) ( $locale['address_1']['index'] ?? 0 ),
			EgyptianAddress::BUILDING     => (int) ( $fields[ EgyptianAddress::BUILDING ]['index'] ?? 0 ),
			EgyptianAddress::FLOOR        => (int) ( $fields[ EgyptianAddress::FLOOR ]['index'] ?? 0 ),
			EgyptianAddress::APARTMENT    => (int) ( $fields[ EgyptianAddress::APARTMENT ]['index'] ?? 0 ),
			EgyptianAddress::LANDMARK     => (int) ( $fields[ EgyptianAddress::LANDMARK ]['index'] ?? 0 ),
		);
		$sorted = $order;
		asort( $sorted );
		$check( array_keys( $order ) === array_keys( $sorted ) && ! in_array( 0, $order, true ), 'AC-1 the address reads largest place first: governorate, city, district, kind of place, street or compound, building, floor, apartment, landmark (' . wp_json_encode( $order ) . ')' );
		$options = wp_list_pluck( (array) ( $fields[ EgyptianAddress::PLACE ]['options'] ?? array() ), 'value' );
		$check( true === ( $locale['phone']['required'] ?? null ) && 'select' === ( $fields[ EgyptianAddress::PLACE ]['type'] ?? '' ) && array( 'apartment', 'villa', 'office' ) === $options && 'Street or compound' === ( $locale['address_1']['label'] ?? '' ), 'AC-1 the phone is required for Egypt, the kind of place is apartment, villa or office, and the street line names a compound too' );

		// The form marks them required as the server does: building for Egypt; floor and number for a flat or an office.
		$rule = static fn ( string $key ): string => (string) wp_json_encode( $fields[ $key ]['required'] ?? false );
		$check( str_contains( $rule( EgyptianAddress::BUILDING ), '"EG"' ) && str_contains( $rule( EgyptianAddress::FLOOR ), '"office"' ) && str_contains( $rule( EgyptianAddress::APARTMENT ), '"office"' ) && ! str_contains( $rule( EgyptianAddress::FLOOR ), '"villa"' ), 'AC-2 the form marks the building required in Egypt, and the floor and number for a flat or an office only' );

		// ---- AC-3: one address --------------------------------------------------------------------------------------
		$check( wc_ship_to_billing_address_only() && 'billing_only' === get_option( 'woocommerce_ship_to_destination' ), 'AC-3 the checkout asks for one address, the one the order is delivered to' );

		// ---- AC-4: the second line, in the order's language ---------------------------------------------------------
		$lines = array(
			EgyptianAddress::line( 'apartment', '5', '4', '18' ),
			EgyptianAddress::line( 'villa', '12', '2', '9' ),
			EgyptianAddress::line( 'office', '3', '2', '7' ),
			EgyptianAddress::line( 'apartment', '', '', '' ),
		);
		$check( array( 'Building 5, floor 4, apartment 18', 'Villa 12', 'Building 3, floor 2, office 7', '' ) === $lines, 'AC-4 the building, floor and apartment as one line; a villa has no floor or apartment (' . wp_json_encode( $lines ) . ')' );
		$ar_line = '';
		if ( switch_to_locale( 'ar' ) ) {
			$ar_line   = EgyptianAddress::line( 'apartment', '5', '4', '18' );
			$ar_ground = EgyptianAddress::line( 'apartment', '5', 'أرضي', '1' );
			restore_previous_locale();
		}
		$check( str_contains( $ar_line, 'عمارة' ) && str_contains( $ar_line, '18' ), 'AC-4 in Arabic for an Arabic order (' . $ar_line . ')' );
		$check( 'عمارة 5، الدور الأرضي، شقة 1' === ( $ar_ground ?? '' ), 'AC-4 a ground floor reads as the ground floor, in Arabic too (' . ( $ar_ground ?? '' ) . ')' );

		// ---- [HTTP] AC-2, AC-3, AC-4 through the Store API checkout -------------------------------------------------
		$cod = get_option( 'woocommerce_cod_settings', array() );
		sign_in_options(
			$s,
			array(
				FixtureStockSource::OPTION          => 'yes',
				'woocommerce_manage_stock'          => 'yes',
				'woocommerce_enable_guest_checkout' => 'yes',
				'woocommerce_cod_settings'          => array_merge( is_array( $cod ) ? $cod : array(), array( 'enabled' => 'yes', 'enable_for_virtual' => 'yes', 'enable_for_methods' => array() ) ),
			)
		);
		$s->on_finish( static function () { delete_option( FixtureStockSource::LEDGER ); } );
		$product = $s->simple_product( 't90 serum', '100', null );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 9 );
		$product->save();
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "{$tag} Egypt" );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'EG', 'country' );
		$zone->save();
		$s->on_finish( static function () use ( $zone ) { $zone->delete(); } );
		$flat = $zone->add_shipping_method( 'flat_rate' );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Flat', 'cost' => '50', 'tax_status' => 'none' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$place = static function ( array $parts, string $email, string $lang = '' ) use ( $product, $s ): array {
			$store = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
			$auth  = array( 'Cart-Token' => sign_in_header( $store, 'cart-token' ), 'Nonce' => sign_in_header( $store, 'nonce' ) );
			$cart  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $product->get_id(), 'quantity' => 1 ), $auth, $store['cookies'] );
			$url   = '' === $lang ? rest_url( 'wc/store/v1/checkout' ) : add_query_arg( 'lang', $lang, rest_url( 'wc/store/v1/checkout' ) );
			$res   = sign_in_json( 'POST', $url, t90_body( $email, $parts ), $auth, $cart['cookies'] );
			$id    = (int) ( $res['data']['order_id'] ?? 0 );
			if ( $id > 0 ) {
				$s->track_order( $id );
			}
			wp_cache_flush();
			return array( $res, $id > 0 ? wc_get_order( $id ) : null );
		};
		$flat_parts = array( EgyptianAddress::PLACE => 'apartment', EgyptianAddress::BUILDING => '5', EgyptianAddress::FLOOR => '4', EgyptianAddress::APARTMENT => '18' );
		[ $no_floor ] = $place( array( EgyptianAddress::PLACE => 'apartment', EgyptianAddress::BUILDING => '5', EgyptianAddress::APARTMENT => '18' ), "{$tag}-a@example.invalid" );
		[ $villa_res, $villa ] = $place( array( EgyptianAddress::PLACE => 'villa', EgyptianAddress::BUILDING => '12' ), "{$tag}-v@example.invalid" );
		[ $no_building ] = $place( array( EgyptianAddress::PLACE => 'apartment', EgyptianAddress::FLOOR => '4', EgyptianAddress::APARTMENT => '18' ), "{$tag}-b@example.invalid" );
		[ $no_apartment ] = $place( array( EgyptianAddress::PLACE => 'office', EgyptianAddress::BUILDING => '5', EgyptianAddress::FLOOR => '4' ), "{$tag}-o@example.invalid" );
		[ $ground_res, $ground ] = $place( array( EgyptianAddress::PLACE => 'apartment', EgyptianAddress::BUILDING => '5', EgyptianAddress::FLOOR => '0', EgyptianAddress::APARTMENT => '1' ), "{$tag}-g@example.invalid" );
		$check( 400 === $no_apartment['status'] && 200 === $ground_res['status'] && $ground && 'Building 5, ground floor, apartment 1' === $ground->get_billing_address_2(), 'AC-2 an office without its number is refused; a ground floor typed as 0 is taken as the ground floor (' . $no_apartment['status'] . ', ' . $ground_res['status'] . ( $ground ? ', ' . $ground->get_billing_address_2() : '' ) . ')' );
		$check( 400 === $no_floor['status'] && 400 === $no_building['status'] && 200 === $villa_res['status'] && $villa && 'Villa 12' === $villa->get_billing_address_2(), 'AC-2 a flat without its floor is refused, and an address without its building; a villa is placed without them (' . $no_floor['status'] . ', ' . $no_building['status'] . ', ' . $villa_res['status'] . ')' );
		[ $flat_res, $flat ] = $place( $flat_parts, "{$tag}-f@example.invalid" );
		$check( 200 === $flat_res['status'] && $flat && 'Building 5, floor 4, apartment 18' === $flat->get_billing_address_2() && 'Dar Misr Al Andalus, 70 Building' === $flat->get_billing_address_1() && '18' === (string) $flat->get_meta( '_wc_billing/' . EgyptianAddress::APARTMENT ) && ( '' === $flat->get_shipping_address_1() || $flat->get_billing_address_1() === $flat->get_shipping_address_1() ), '[HTTP] AC-3, AC-4 an order placed with a flat keeps each part and carries them as its second line, and is delivered to the one address given (' . wp_json_encode( $flat ? array( $flat->get_billing_address_1(), $flat->get_billing_address_2(), $flat->get_shipping_address_1(), $flat->get_meta( '_wc_billing/' . EgyptianAddress::APARTMENT ) ) : $flat_res['status'] ) . ')' );
		$courier = $flat && function_exists( 'bosta_format_address_details' ) ? bosta_format_address_details( $flat ) : null;
		$slip    = $flat ? ( function () use ( $flat ) { return ( new \ReflectionMethod( \MizzeySite\Orders\PackingSlip::class, 'recipient' ) )->invoke( null, $flat ); } )() : array();
		$check( ( null === $courier || 'Building 5, floor 4, apartment 18' === $courier->secondLine ) && in_array( 'Building 5, floor 4, apartment 18', $slip, true ) && in_array( 'Behind the club', $slip, true ), 'AC-4 the courier\'s address (Bosta secondLine' . ( null === $courier ? ', plugin not active here' : '' ) . ') and the packing slip carry the line and the landmark' );
		if ( $flat ) {
			$flat->update_meta_data( '_wc_billing/' . EgyptianAddress::FLOOR, '6' );
			$flat->save();
			wp_cache_flush();
		}
		$edited  = $flat ? wc_get_order( $flat->get_id() ) : null;
		$slip2   = $edited ? ( new \ReflectionMethod( \MizzeySite\Orders\PackingSlip::class, 'recipient' ) )->invoke( null, $edited ) : array();
		$invoice = $edited ? implode( ' | ', ( new \ReflectionMethod( \MizzeySite\Invoices\InvoiceDocument::class, 'customer' ) )->invoke( null, $edited ) ) : '';
		$courier2 = $edited && function_exists( 'bosta_format_address_details' ) ? bosta_format_address_details( $edited ) : null;
		$check( $edited && 'Building 5, floor 6, apartment 18' === $edited->get_billing_address_2() && 'Building 5, floor 6, apartment 18' === $edited->get_shipping_address_2() && '6' === (string) $edited->get_meta( '_wc_shipping/' . EgyptianAddress::FLOOR ) && in_array( 'Building 5, floor 6, apartment 18', $slip2, true ) && str_contains( $invoice, 'Building 5, floor 6, apartment 18' ) && ( null === $courier2 || 'Building 5, floor 6, apartment 18' === $courier2->secondLine ), 'AC-3, AC-4 a change staff make on the order screen is carried to the line, and the one address\'s delivery copy, packing slip, invoice and courier all show it (' . $invoice . ')' );
		if ( $edited ) {
			$edited->set_billing_address_2( 'Back entrance, ask the guard' );
			$edited->save();
			$edited = wc_get_order( $edited->get_id() );
			$edited->update_meta_data( '_wc_billing/' . EgyptianAddress::FLOOR, '7' );
			$edited->save();
			wp_cache_flush();
		}
		$check( $edited && 'Back entrance, ask the guard' === wc_get_order( $edited->get_id() )->get_billing_address_2(), 'AC-4 a second line staff typed themselves is kept, also when a part changes afterwards' );
		if ( $edited ) {
			$edited = wc_get_order( $edited->get_id() );
			$edited->set_shipping_address_1( 'Dar Misr Al Andalus, 72 Building' );
			$edited->save();
			wp_cache_flush();
		}
		$moved = $edited ? wc_get_order( $edited->get_id() ) : null;
		$check( $moved && 'Dar Misr Al Andalus, 72 Building' === $moved->get_billing_address_1() && 'Dar Misr Al Andalus, 72 Building' === $moved->get_shipping_address_1(), 'AC-3 a correction staff make in the delivery column of a one-address order becomes the address, so every reader shows it' );
		// [HTTP] Paying an existing order: the parts are checked there too.
		$unpaid = wc_create_order();
		$unpaid->add_product( wc_get_product( $product->get_id() ), 1 );
		$unpaid->set_billing_email( "{$tag}-u@example.invalid" );
		$unpaid->set_billing_country( 'EG' );
		$unpaid->calculate_totals();
		$unpaid->set_status( 'pending' );
		$unpaid->save();
		$s->track_order( $unpaid->get_id() );
		$pay = static function ( array $parts ) use ( $unpaid, $tag ): array {
			$store = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
			$auth  = array( 'Cart-Token' => sign_in_header( $store, 'cart-token' ), 'Nonce' => sign_in_header( $store, 'nonce' ) );
			$body  = array_merge( t90_body( "{$tag}-u@example.invalid", $parts ), array( 'key' => $unpaid->get_order_key(), 'billing_email' => "{$tag}-u@example.invalid" ) );
			return sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout/' . $unpaid->get_id() ), $body, $auth, $store['cookies'] );
		};
		$pay_short = $pay( array( EgyptianAddress::PLACE => 'apartment', EgyptianAddress::FLOOR => '4', EgyptianAddress::APARTMENT => '18' ) );
		wp_cache_flush();
		$after_short = (string) wc_get_order( $unpaid->get_id() )->get_meta( '_wc_billing/' . EgyptianAddress::PLACE );
		$pay_full  = $pay( $flat_parts );
		wp_cache_flush();
		$paid = wc_get_order( $unpaid->get_id() );
		$check( 200 !== $pay_short['status'] && '' === $after_short && 200 === $pay_full['status'] && $paid && 'Building 5, floor 4, apartment 18' === $paid->get_billing_address_2() && 'yes' !== $paid->get_meta( EgyptianAddress::ONE_ADDRESS ), '[HTTP] AC-2 paying an existing order is refused without the building and keeps none of the refused parts; with them the order carries its line, and keeps its own address policy (' . $pay_short['status'] . ', ' . $pay_full['status'] . ')' );
		// A line staff typed on an unpaid order stays when the customer pays it from the form, which sends it back.
		$typed = wc_create_order();
		$typed->add_product( wc_get_product( $product->get_id() ), 1 );
		$typed->set_billing_email( "{$tag}-t@example.invalid" );
		$typed->set_billing_country( 'EG' );
		$typed->set_billing_address_2( 'Side gate, call first' );
		$typed->calculate_totals();
		$typed->set_status( 'pending' );
		$typed->save();
		$s->track_order( $typed->get_id() );
		$store_t = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$auth_t  = array( 'Cart-Token' => sign_in_header( $store_t, 'cart-token' ), 'Nonce' => sign_in_header( $store_t, 'nonce' ) );
		$body_t  = array_merge( t90_body( "{$tag}-t@example.invalid", $flat_parts ), array( 'key' => $typed->get_order_key(), 'billing_email' => "{$tag}-t@example.invalid" ) );
		$body_t['billing_address']['address_2']  = 'Side gate, call first';
		$body_t['shipping_address']['address_2'] = 'Side gate, call first';
		$paid_t = sign_in_json( 'POST', rest_url( 'wc/store/v1/checkout/' . $typed->get_id() ), $body_t, $auth_t, $store_t['cookies'] );
		wp_cache_flush();
		$check( 200 === $paid_t['status'] && 'Side gate, call first' === wc_get_order( $typed->get_id() )->get_billing_address_2(), '[HTTP] AC-4 a line staff typed on an unpaid order is kept when the customer pays it (' . $paid_t['status'] . ', ' . wc_get_order( $typed->get_id() )->get_billing_address_2() . ')' );

		// An Arabic order (WPML's order language): its line is composed in Arabic, whoever saves it.
		$ar_order = wc_create_order();
		$s->track_order( $ar_order->get_id() );
		$ar_order->set_billing_country( 'EG' );
		$ar_order->update_meta_data( 'wpml_language', 'ar' );
		foreach ( $flat_parts as $key => $value ) {
			$ar_order->update_meta_data( '_wc_billing/' . $key, $value );
		}
		$ar_order->save();
		wp_cache_flush();
		$ar_saved = wc_get_order( $ar_order->get_id() )->get_billing_address_2();
		$check( str_contains( $ar_saved, 'عمارة' ) && str_contains( $ar_saved, 'شقة' ), 'AC-4 an Arabic order\'s line is in Arabic though it is saved in English (' . $ar_saved . ')' );

		// ---- AC-5: the address book ---------------------------------------------------------------------------------
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}c", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer', 'user_email' => "{$tag}c@example.invalid" ) );
		$s->track_user( $customer );
		$base   = array( 'first_name' => 'Laila', 'last_name' => 'Fawzy', 'phone' => '01005550090', 'state' => 'EG-01', 'city' => 'New Cairo', 'district' => 'Third Settlement', 'address_1' => 'Dar Misr Al Andalus', 'landmark' => '' );
		$short  = AddressBook::add( $customer, $base + array( 'place' => 'apartment', 'building' => '5' ) );
		$villaB = AddressBook::add( $customer, $base + array( 'place' => 'villa', 'building' => '12', 'floor' => '3' ) );
		$flatB  = AddressBook::add( $customer, $base + array( 'place' => 'apartment', 'building' => '5', 'floor' => '4', 'apartment' => '18' ) );
		$book   = AddressBook::all( $customer );
		$kept   = array_values( array_filter( $book, static fn ( $a ) => ( $a['id'] ?? '' ) === $flatB ) )[0] ?? array();
		$v      = array_values( array_filter( $book, static fn ( $a ) => ( $a['id'] ?? '' ) === $villaB ) )[0] ?? array();
		$check( is_wp_error( $short ) && is_string( $villaB ) && '' === ( $v['floor'] ?? 'x' ) && is_string( $flatB ) && '18' === ( $kept['apartment'] ?? '' ) && 'apartment' === ( $kept['place'] ?? '' ), 'AC-5 the address book keeps the kind of place, building, floor and apartment, refuses a flat without them, and keeps no floor for a villa' );
		if ( is_string( $flatB ) ) {
			AddressBook::makeDefault( $customer, $flatB );
		}
		$woo = new \WC_Customer( $customer );
		$check( 'Building 5, floor 4, apartment 18' === $woo->get_billing_address_2() && '18' === (string) $woo->get_meta( '_wc_billing/' . EgyptianAddress::APARTMENT ), 'AC-5 the default address prefills the checkout with every part (CHK-02)' );

		return $ok;
	}
);

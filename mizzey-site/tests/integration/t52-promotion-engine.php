<?php
/**
 * t52, the promotion engine (specs/011-promotion-engine, #281).
 *
 * In-process on WooCommerce's real cart and discount calculation (`WC()->cart`, `WC_Discounts`), on orders, and
 * through the coupon screen's own save action; over HTTP, the block cart's Store API shows an automatic promotion
 * with its discount. Every coupon, product, term, order and user it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\PromotionRules;
use MizzeySite\Promotions\PromotionSettings;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';

/** A coupon with WooCommerce props and Mizzey settings. */
function t52_coupon( Scenario $s, string $code, array $props, array $mizzey = array(), array $meta = array() ): \WC_Coupon {
	$coupon = new \WC_Coupon();
	$coupon->set_code( $code );
	$coupon->set_props( $props );
	foreach ( $mizzey + $meta as $key => $value ) {
		$coupon->update_meta_data( $key, $value );
	}
	$coupon->save();
	$s->on_finish( static function () use ( $coupon ) { wp_delete_post( $coupon->get_id(), true ); } );
	AutomaticPromotions::forget();
	return $coupon;
}

function t52_product( Scenario $s, string $name, string $price, array $terms = array() ): \WC_Product_Simple {
	$product = new \WC_Product_Simple();
	$product->set_name( $name );
	$product->set_regular_price( $price );
	$product->set_status( 'publish' );
	$product->save();
	foreach ( $terms as $taxonomy => $term_id ) {
		wp_set_object_terms( $product->get_id(), array( $term_id ), $taxonomy );
	}
	$s->track_post( $product->get_id() );
	return wc_get_product( $product->get_id() );
}

/** @param array<int,int> $lines product id => quantity */
function t52_cart( array $lines ): \WC_Cart {
	$cart = WC()->cart;
	$cart->empty_cart();
	$cart->remove_coupons();
	foreach ( $lines as $id => $qty ) {
		$cart->add_to_cart( $id, $qty );
	}
	$cart->calculate_totals();
	return $cart;
}

/** True, or the refusal message, for a coupon on the cart. */
function t52_valid( \WC_Coupon $coupon, $object = null ) {
	$result = ( new \WC_Discounts( $object ?? WC()->cart ) )->is_coupon_valid( new \WC_Coupon( $coupon->get_code() ) );
	return true === $result ? true : $result->get_error_message();
}

/** The discount a coupon gives the cart, in currency units. */
function t52_discount( \WC_Coupon $coupon ): float {
	$discounts = new \WC_Discounts( WC()->cart );
	$discounts->apply_coupon( new \WC_Coupon( $coupon->get_code() ), false );
	return wc_remove_number_precision( array_sum( $discounts->get_discounts_by_coupon( true ) ) );
}

function t52_applied(): array {
	return array_values( array_map( 'wc_format_coupon_code', WC()->cart->get_applied_coupons() ) );
}

/** @return array{status:int,body:array,headers:array} */
function t52_store( string $method, string $path, array $body, array $headers ): array {
	$response = wp_remote_request( rest_url( 'wc/store/v1/' . $path ), array( 'method' => $method, 'timeout' => 30, 'headers' => $headers + array( 'Content-Type' => 'application/json' ), 'body' => $body ? wp_json_encode( $body ) : null ) ); // storefront-guard: exempt, the Store API answers JSON, not a storefront page.
	if ( is_wp_error( $response ) ) {
		return array( 'status' => 0, 'body' => array(), 'headers' => array() );
	}
	return array(
		'status'  => (int) wp_remote_retrieve_response_code( $response ),
		'body'    => (array) json_decode( wp_remote_retrieve_body( $response ), true ),
		'headers' => array( 'Nonce' => (string) wp_remote_retrieve_header( $response, 'nonce' ), 'Cart-Token' => (string) wp_remote_retrieve_header( $response, 'cart-token' ) ),
	);
}

run(
	new Scenario( 't52-promotion-engine', 'PROMO-01 to PROMO-23: the promotion engine' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous_user = get_current_user_id();
		$s->on_finish( static function () use ( $previous_user ) {
			WC()->cart->empty_cart();
			WC()->cart->remove_coupons();
			wp_set_current_user( $previous_user );
			AutomaticPromotions::forget();
		} );
		wc_load_cart();
		wp_set_current_user( 0 );

		$beauty   = wp_insert_term( 't52 Beauty ' . wp_rand(), 'product_cat' );
		$vitamins = wp_insert_term( 't52 Vitamins ' . wp_rand(), 'product_cat' );
		$brand    = wp_insert_term( 't52 Brand ' . wp_rand(), 'product_brand' );
		$s->on_finish( static function () use ( $beauty, $vitamins, $brand ) {
			wp_delete_term( $beauty['term_id'], 'product_cat' );
			wp_delete_term( $vitamins['term_id'], 'product_cat' );
			wp_delete_term( $brand['term_id'], 'product_brand' );
		} );
		$lipstick = t52_product( $s, 't52 lipstick', '100', array( 'product_cat' => $beauty['term_id'], 'product_brand' => $brand['term_id'] ) );
		$cream    = t52_product( $s, 't52 cream', '50', array( 'product_cat' => $beauty['term_id'] ) );
		$tablets  = t52_product( $s, 't52 tablets', '200', array( 'product_cat' => $vitamins['term_id'] ) );
		$odd      = t52_product( $s, 't52 odd', '33.33' );
		$tag      = 't52' . wp_rand( 1000, 9999 );

		// AC-1: the three rewards.
		$percent = t52_coupon( $s, "{$tag}pct", array( 'discount_type' => 'percent', 'amount' => '10' ) );
		$fixed   = t52_coupon( $s, "{$tag}fix", array( 'discount_type' => 'fixed_cart', 'amount' => '30' ) );
		$ship    = t52_coupon( $s, "{$tag}ship", array( 'discount_type' => 'fixed_cart', 'amount' => '0', 'free_shipping' => true ) );
		t52_cart( array( $lipstick->get_id() => 1, $cream->get_id() => 1 ) );
		$check( 15.0 === t52_discount( $percent ) && 30.0 === t52_discount( $fixed ), 'AC-1 a percentage and a fixed discount on a 150 cart give 15 and 30' );
		$check( true === WC()->cart->apply_coupon( $ship->get_code() ) && ( new \WC_Coupon( $ship->get_code() ) )->get_free_shipping(), 'AC-1 a free-shipping promotion applies' );

		// AC-2: conditions.
		$value = t52_coupon( $s, "{$tag}min", array( 'discount_type' => 'percent', 'amount' => '5', 'minimum_amount' => '200' ) );
		$check( true !== t52_valid( $value ), 'AC-2 below the minimum cart value the promotion is refused (native)' );
		$items = t52_coupon( $s, "{$tag}items", array( 'discount_type' => 'percent', 'amount' => '10', 'product_categories' => array( $beauty['term_id'] ) ), array( PromotionSettings::MIN_ITEMS => '3' ) );
		t52_cart( array( $lipstick->get_id() => 1, $cream->get_id() => 1, $tablets->get_id() => 5 ) );
		$refusal = t52_valid( $items );
		$check( 'This coupon needs at least 3 items in your cart.' === $refusal, "AC-2 two Beauty units of seven are below a minimum of three Beauty items ({$refusal})" );
		switch_to_locale( 'ar' );
		$arabic = t52_valid( $items );
		restore_previous_locale();
		$check( is_string( $arabic ) && str_contains( $arabic, 'تتطلب هذه القسيمة' ), "AC-2 the reason is given in Arabic ({$arabic})" );
		t52_cart( array( $lipstick->get_id() => 2, $cream->get_id() => 1 ) );
		$check( true === t52_valid( $items ) && 25.0 === t52_discount( $items ), 'AC-2 three Beauty units meet it, and only Beauty is discounted' );
		$product_only = t52_coupon( $s, "{$tag}prod", array( 'discount_type' => 'percent', 'amount' => '10', 'product_ids' => array( $tablets->get_id() ) ) );
		$check( true !== t52_valid( $product_only ), 'AC-2 a product condition refuses a cart without the product (native)' );
		$branded = t52_coupon( $s, "{$tag}brand", array( 'discount_type' => 'percent', 'amount' => '10' ), array(), array( 'product_brands' => array( $brand['term_id'] ) ) );
		t52_cart( array( $cream->get_id() => 1 ) );
		$check( true !== t52_valid( $branded ), 'AC-2 a brand condition refuses a cart without the brand (native core Brands)' );
		t52_cart( array( $lipstick->get_id() => 1, $cream->get_id() => 1 ) );
		$check( true === t52_valid( $branded ) && 10.0 === t52_discount( $branded ), 'AC-2 with the brand in the cart, only the brand\'s product is discounted (native)' );

		// AC-2: first order only.
		$first = t52_coupon( $s, "{$tag}first", array( 'discount_type' => 'percent', 'amount' => '10' ), array( PromotionSettings::FIRST_ORDER => 'yes' ) );
		list( $returning ) = two_factor_user( $s, 'customer' );
		list( $newcomer )  = two_factor_user( $s, 'customer' );
		$past = wc_create_order( array( 'customer_id' => $returning->ID ) );
		$past->add_product( $cream, 1 );
		$past->set_billing_email( 'returning-' . $tag . '@example.invalid' );
		$past->calculate_totals();
		$past->set_status( 'processing' );
		$past->save();
		$s->track_order( $past->get_id() );
		$failed_past = wc_create_order( array( 'customer_id' => $newcomer->ID ) );
		$failed_past->add_product( $cream, 1 );
		$failed_past->set_status( 'failed' );
		$failed_past->save();
		$s->track_order( $failed_past->get_id() );
		wp_set_current_user( $returning->ID );
		WC()->customer = new \WC_Customer( $returning->ID, true );
		$check( 'This coupon is for a first order only.' === t52_valid( $first ), 'AC-2 a customer with an earlier order is refused' );
		wp_set_current_user( $newcomer->ID );
		WC()->customer = new \WC_Customer( $newcomer->ID, true );
		$check( true === t52_valid( $first ), 'AC-2 a customer whose only earlier order failed is a first-order customer' );
		wp_set_current_user( 0 );
		WC()->customer = new \WC_Customer( 0, true );
		WC()->customer->set_billing_email( 'returning-' . $tag . '@example.invalid' );
		$check( 'This coupon is for a first order only.' === t52_valid( $first ), 'AC-2 a guest is judged by billing email' );
		WC()->customer->set_billing_email( 'new-' . $tag . '@example.invalid' );
		$check( true === t52_valid( $first ), 'AC-2 a guest with a new email is allowed' );
		$own = wc_create_order( array( 'customer_id' => $newcomer->ID ) );
		$own->add_product( $cream, 1 );
		$own->set_billing_email( 'new-' . $tag . '@example.invalid' );
		$own->set_status( 'processing' );
		$own->save();
		$s->track_order( $own->get_id() );
		$check( true === t52_valid( $first, $own ), 'AC-2 an order is not counted against itself: the first order of a newcomer qualifies in order context' );
		$second = wc_create_order( array( 'customer_id' => $returning->ID ) );
		$second->add_product( $cream, 1 );
		$second->save();
		$s->track_order( $second->get_id() );
		$check( 'This coupon is for a first order only.' === t52_valid( $first, $second ), 'AC-2 an order for a returning customer is refused in order context' );

		// AC-3 and Q1: automatic promotions.
		$auto_a = t52_coupon( $s, "{$tag}auto10", array( 'discount_type' => 'percent', 'amount' => '10', 'individual_use' => true ), array( PromotionSettings::AUTOMATIC => 'yes', PromotionSettings::MIN_ITEMS => '2' ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_a->get_code() ) === t52_applied() && 20.0 === (float) WC()->cart->get_discount_total(), 'AC-3 an automatic promotion applies with no code when it qualifies' );
		WC()->cart->set_quantity( array_key_first( WC()->cart->get_cart() ), 1 );
		WC()->cart->calculate_totals();
		$check( array() === t52_applied(), 'AC-3 it leaves when the cart stops qualifying' );
		$auto_b = t52_coupon( $s, "{$tag}auto25", array( 'discount_type' => 'fixed_cart', 'amount' => '25', 'individual_use' => true ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_b->get_code() ) === t52_applied(), 'Q1 of two qualifying automatic promotions (20 and 25 off) the larger applies, alone' );
		$auto_b->set_amount( '20' );
		$auto_b->save();
		AutomaticPromotions::forget();
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_a->get_code() ) === t52_applied(), 'Q1 on a tie (20 and 20) the one created first applies' );
		WC()->cart->apply_coupon( $percent->get_code() );
		WC()->cart->calculate_totals();
		$check( array( $percent->get_code() ) === t52_applied(), 'AC-3 a code the customer enters takes the place of the automatic promotion' );
		WC()->cart->remove_coupon( $percent->get_code() );
		WC()->cart->calculate_totals();
		$check( array( $auto_a->get_code() ) === t52_applied(), 'AC-3 removing the code brings the automatic promotion back' );

		// AC-3 over HTTP: the block cart's Store API.
		WC()->cart->empty_cart();
		$cart   = t52_store( 'GET', 'cart', array(), array() );
		$added  = t52_store( 'POST', 'cart/add-item', array( 'id' => $lipstick->get_id(), 'quantity' => 2 ), $cart['headers'] );
		$codes  = array_column( (array) ( $added['body']['coupons'] ?? array() ), 'code' );
		$off    = (int) ( $added['body']['totals']['total_discount'] ?? -1 );
		$check( 201 === $added['status'] && array( $auto_a->get_code() ) === array_map( 'wc_format_coupon_code', $codes ) && $off > 0, "AC-3 [HTTP] the block cart lists the automatic promotion with its discount (status {$added['status']}, discount {$off})" );

		// AC-2 over HTTP: a guest's "first order only" is checked again at checkout, once the billing email is known.
		$cod_before = get_option( 'woocommerce_cod_settings' );
		$s->on_finish( static function () use ( $cod_before ) { update_option( 'woocommerce_cod_settings', $cod_before ); } );
		update_option( 'woocommerce_cod_settings', array_merge( (array) $cod_before, array( 'enabled' => 'yes' ) ) );
		$guest   = t52_store( 'GET', 'cart', array(), array() );
		t52_store( 'POST', 'cart/add-item', array( 'id' => $cream->get_id(), 'quantity' => 1 ), $guest['headers'] );
		$coupon  = t52_store( 'POST', 'cart/apply-coupon', array( 'code' => $first->get_code() ), $guest['headers'] );
		$address = array( 'first_name' => 'T', 'last_name' => 'Fiftytwo', 'address_1' => '1 Tahrir Street', 'city' => 'Cairo', 'state' => 'EG-01', 'postcode' => '11511', 'country' => 'EG', 'email' => 'returning-' . $tag . '@example.invalid', 'phone' => '01000000000' );
		$placed  = t52_store( 'POST', 'checkout', array( 'billing_address' => $address, 'shipping_address' => $address, 'payment_method' => 'cod' ), $guest['headers'] );
		if ( ! empty( $placed['body']['order_id'] ) ) {
			$s->track_order( (int) $placed['body']['order_id'] );
		}
		$with_code = ! empty( $placed['body']['order_id'] ) && in_array( wc_format_coupon_code( $first->get_code() ), array_map( 'wc_format_coupon_code', wc_get_order( (int) $placed['body']['order_id'] )->get_coupon_codes() ), true );
		$error_code = (string) ( $placed['body']['code'] ?? '' );
		$message    = wp_strip_all_tags( (string) ( $placed['body']['message'] ?? '' ) );
		$check( 200 === $coupon['status'] && ! $with_code && str_contains( $error_code, 'coupon' ) && str_contains( $message, 'first order only' ), "AC-2 [HTTP] the code applies to a guest cart, and checkout with a returning customer's email refuses it for that reason (apply {$coupon['status']}, checkout {$placed['status']} {$error_code}: {$message})" );

		// AC-4: limits (native).
		$once = t52_coupon( $s, "{$tag}once", array( 'discount_type' => 'percent', 'amount' => '10', 'usage_limit' => 1, 'usage_count' => 1 ) );
		t52_cart( array( $cream->get_id() => 1 ) );
		$check( true !== t52_valid( $once ), 'AC-4 the total usage limit stops the promotion (native)' );
		$per = t52_coupon( $s, "{$tag}per", array( 'discount_type' => 'percent', 'amount' => '10', 'usage_limit_per_user' => 1 ) );
		add_post_meta( $per->get_id(), '_used_by', (string) $returning->ID ); // The row WooCommerce counts per customer.
		wp_set_current_user( $returning->ID );
		WC()->customer = new \WC_Customer( $returning->ID, true );
		$check( true !== t52_valid( $per ), 'AC-4 the per-customer limit stops the promotion (native)' );
		wp_set_current_user( 0 );
		WC()->customer = new \WC_Customer( 0, true );

		// AC-5: dates.
		$later   = t52_coupon( $s, "{$tag}later", array( 'discount_type' => 'percent', 'amount' => '10' ), array( PromotionSettings::STARTS => (string) ( time() + DAY_IN_SECONDS ) ) );
		$expired = t52_coupon( $s, "{$tag}gone", array( 'discount_type' => 'percent', 'amount' => '10', 'date_expires' => time() - DAY_IN_SECONDS ) );
		$check( 'This coupon is not active yet.' === t52_valid( $later ) && true !== t52_valid( $expired ), 'AC-5 before its start and after its expiry a promotion is refused' );
		$auto_later = t52_coupon( $s, "{$tag}autolater", array( 'discount_type' => 'fixed_cart', 'amount' => '90', 'individual_use' => true ), array( PromotionSettings::AUTOMATIC => 'yes', PromotionSettings::STARTS => (string) ( time() + DAY_IN_SECONDS ) ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( $auto_later->get_code(), t52_applied(), true ), 'AC-5 an automatic promotion does not apply before its start' );

		// AC-6: exclusions (native).
		$excluding = t52_coupon( $s, "{$tag}excl", array( 'discount_type' => 'percent', 'amount' => '20', 'excluded_product_categories' => array( $vitamins['term_id'] ), 'excluded_product_ids' => array( $cream->get_id() ) ) );
		t52_cart( array( $lipstick->get_id() => 1, $cream->get_id() => 1, $tablets->get_id() => 1 ) );
		$check( 20.0 === t52_discount( $excluding ), 'AC-6 the excluded category and product are not discounted (native)' );

		// AC-7: the cap.
		$capped = t52_coupon( $s, "{$tag}cap", array( 'discount_type' => 'percent', 'amount' => '10' ), array( PromotionSettings::MAX_DISCOUNT => '150' ) );
		t52_cart( array( $lipstick->get_id() => 20 ) );
		$check( 150.0 === t52_discount( $capped ), 'AC-7 10 percent of 2,000 is capped at 150' );
		$small = t52_coupon( $s, "{$tag}capodd", array( 'discount_type' => 'percent', 'amount' => '50' ), array( PromotionSettings::MAX_DISCOUNT => '5' ) );
		t52_cart( array( $odd->get_id() => 3, $cream->get_id() => 1, $lipstick->get_id() => 1 ) );
		$check( 5.0 === t52_discount( $small ), 'AC-7 across several lines and odd prices the cap holds to the cent' );
		t52_cart( array( $cream->get_id() => 1 ) );
		$check( 5.0 === t52_discount( $percent ) && 5.0 === t52_discount( $capped ), 'AC-7 below the cap the discount is the full percentage' );
		$check( true === WC()->cart->apply_coupon( $capped->get_code() ) && 5.0 === (float) WC()->cart->get_discount_total(), 'AC-7 the cart applies a capped promotion with the same discount' );
		$check( 'A promotion with a maximum discount applies at checkout, not to an existing order.' === t52_valid( $capped, $second ), 'AC-7 a capped promotion is refused on an existing order' );

		// AC-8: the coupon screen's save path.
		$screen = t52_coupon( $s, "{$tag}screen", array( 'discount_type' => 'percent', 'amount' => '10', 'date_expires' => time() + 10 * DAY_IN_SECONDS ) );
		$_POST  = array( 'mizzey_automatic' => 'yes', 'mizzey_starts' => wp_date( 'Y-m-d', time() + 2 * DAY_IN_SECONDS ), 'mizzey_max_discount' => '75', 'mizzey_min_items' => '4', 'mizzey_first_order' => 'yes' );
		do_action( 'woocommerce_coupon_options_save', $screen->get_id(), new \WC_Coupon( $screen->get_id() ) );
		$saved = new \WC_Coupon( $screen->get_id() );
		$check( PromotionSettings::automatic( $saved ) && null !== PromotionSettings::starts( $saved ) && 75.0 === PromotionSettings::maxDiscount( $saved ) && 4 === PromotionSettings::minItems( $saved ) && PromotionSettings::firstOrder( $saved ) && $saved->get_individual_use(), 'AC-8 every setting saves from the coupon screen, and an automatic promotion becomes individual-use' );
		$_POST = array( 'mizzey_starts' => wp_date( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ) );
		$before = PromotionSettings::starts( $saved );
		do_action( 'woocommerce_coupon_options_save', $screen->get_id(), new \WC_Coupon( $screen->get_id() ) );
		$check( $before === PromotionSettings::starts( new \WC_Coupon( $screen->get_id() ) ), 'AC-5 a start after the expiry is not saved' );
		$_POST = array();

		return $ok;
	}
);

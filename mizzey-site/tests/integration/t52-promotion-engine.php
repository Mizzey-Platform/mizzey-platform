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
		$auto_a = t52_coupon( $s, "{$tag}auto10", array( 'discount_type' => 'percent', 'amount' => '10', 'individual_use' => true, 'date_created' => time() - 3 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes', PromotionSettings::MIN_ITEMS => '2' ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_a->get_code() ) === t52_applied() && 20.0 === (float) WC()->cart->get_discount_total(), 'AC-3 an automatic promotion applies with no code when it qualifies' );
		WC()->cart->set_quantity( array_key_first( WC()->cart->get_cart() ), 1 );
		WC()->cart->calculate_totals();
		$check( array() === t52_applied(), 'AC-3 it leaves when the cart stops qualifying' );
		$auto_b = t52_coupon( $s, "{$tag}auto25", array( 'discount_type' => 'fixed_cart', 'amount' => '25', 'individual_use' => true, 'date_created' => time() - HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_b->get_code() ) === t52_applied(), 'Q1 of two qualifying automatic promotions (20 and 25 off) the larger applies, alone' );
		$auto_b->set_amount( '20' );
		$auto_b->save();
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_a->get_code() ) === t52_applied(), 'Q1 on a tie (20 and 20) the one created first applies' );
		WC()->cart->apply_coupon( $percent->get_code() );
		WC()->cart->calculate_totals();
		$check( array( $percent->get_code() ) === t52_applied(), 'AC-3 a code the customer enters takes the place of the automatic promotion' );
		WC()->cart->remove_coupon( $percent->get_code() );
		WC()->cart->calculate_totals();
		$check( array( $auto_a->get_code() ) === t52_applied(), 'AC-3 removing the code brings the automatic promotion back' );
		$auto_a->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto_a->save();
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_b->get_code() ) === t52_applied(), 'AC-3 switching a promotion off as automatic (saved through WooCommerce) takes effect at once' );
		$auto_a->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$auto_a->save();

		// AC-3: a guest who has used a promotion up by email is not given it again; the next one applies.
		$auto_b->set_usage_limit_per_user( 1 );
		$auto_b->set_amount( '30' );
		$auto_b->save();
		add_post_meta( $auto_b->get_id(), '_used_by', 'used-' . $tag . '@example.invalid' );
		WC()->customer = new \WC_Customer( 0, true );
		WC()->customer->set_billing_email( 'used-' . $tag . '@example.invalid' );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( array( $auto_a->get_code() ) === t52_applied(), 'AC-3, AC-4 a guest whose email has used up a promotion gets the next one instead (' . implode( ',', t52_applied() ) . ')' );
		WC()->customer->set_billing_email( '' );
		$auto_b->set_amount( '20' );
		$auto_b->set_usage_limit_per_user( 0 );
		$auto_b->save();

		// AC-1, AC-3: an automatic free-shipping promotion, valued at the rate it replaces, and stable.
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 't52 Egypt ' . $tag );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'EG', 'country' );
		$zone->save();
		$s->on_finish( static function () use ( $zone ) { $zone->delete(); } );
		// WooCommerce matches the first zone by order, then id, so a site's own zone for Egypt (staging has one) would
		// take the package. The other zones move after this one for the run and get their order back at the end.
		foreach ( \WC_Shipping_Zones::get_zones() as $other ) {
			if ( (int) $other['id'] === $zone->get_id() ) {
				continue;
			}
			$moved = new \WC_Shipping_Zone( (int) $other['id'] );
			$order = $moved->get_zone_order();
			$moved->set_zone_order( $order + 1000 );
			$moved->save();
			$s->on_finish( static function () use ( $moved, $order ) { $moved->set_zone_order( $order ); $moved->save(); } );
		}
		$flat = $zone->add_shipping_method( 'flat_rate' );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Flat', 'cost' => '50', 'tax_status' => 'none' ) );
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$ship_auto = t52_coupon( $s, "{$tag}autoship", array( 'discount_type' => 'fixed_cart', 'amount' => '0', 'free_shipping' => true, 'individual_use' => true, 'date_created' => time() - 2 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		WC()->customer->set_shipping_country( 'EG' );
		WC()->customer->set_billing_country( 'EG' );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$winners = array();
		for ( $pass = 0; $pass < 3; $pass++ ) {
			WC()->cart->calculate_totals();
			$winners[] = implode( ',', t52_applied() );
		}
		$check( array_fill( 0, 3, wc_format_coupon_code( $ship_auto->get_code() ) ) === $winners && 0.0 === (float) WC()->cart->get_shipping_total(), 'AC-1, Q1 free shipping worth 50 beats 20 off, ships free, and stays chosen over repeated calculations (' . implode( ' | ', $winners ) . ')' );
		$hide = get_option( 'woocommerce_shipping_hide_rates_when_free', 'no' );
		$s->on_finish( static function () use ( $hide ) { update_option( 'woocommerce_shipping_hide_rates_when_free', $hide ); } );
		update_option( 'woocommerce_shipping_hide_rates_when_free', 'yes' );
		$winners = array();
		for ( $pass = 0; $pass < 3; $pass++ ) {
			t52_cart( array( $lipstick->get_id() => 2 ) );
			WC()->cart->calculate_totals();
			$winners[] = implode( ',', t52_applied() );
		}
		update_option( 'woocommerce_shipping_hide_rates_when_free', $hide );
		$check( array_fill( 0, 3, wc_format_coupon_code( $ship_auto->get_code() ) ) === $winners, 'AC-3 with paid rates hidden once shipping is free, the choice is still stable (' . implode( ' | ', $winners ) . ')' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '100', 'ignore_discounts' => 'yes' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( wc_format_coupon_code( $ship_auto->get_code() ), t52_applied(), true ), 'AC-3 where the order already ships free by its amount, the free-shipping promotion is worth nothing and does not win' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '95', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$auto_a->update_meta_data( PromotionSettings::MIN_ITEMS, '1' );
		$auto_a->save();
		$auto_b->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto_b->save();
		t52_cart( array( $lipstick->get_id() => 1 ) );
		$passes = array();
		for ( $pass = 0; $pass < 3; $pass++ ) {
			WC()->cart->calculate_totals();
			$passes[] = implode( ',', t52_applied() );
		}
		$free_rate = false;
		foreach ( WC()->shipping()->get_packages() as $package ) {
			foreach ( (array) $package['rates'] as $rate ) {
				$free_rate = $free_rate || 'free_shipping' === $rate->get_method_id();
			}
		}
		$check( 1 === count( array_unique( $passes ) ) && ! in_array( wc_format_coupon_code( $auto_a->get_code() ), t52_applied(), true ) && $free_rate, 'Q1 a 10 off that would push a 100 cart below the 95 free shipping minimum is not applied: free shipping stays available, and the choice is stable (' . implode( ' | ', $passes ) . ')' );
		$auto_a->update_meta_data( PromotionSettings::MIN_ITEMS, '2' );
		$auto_a->save();
		$auto_b->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$auto_b->save();
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => '' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$zone->delete_shipping_method( $free );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( wc_format_coupon_code( $ship_auto->get_code() ), t52_applied(), true ), 'AC-3 where no free shipping method takes a coupon, the free-shipping promotion is worth nothing and does not win' );
		// Fourth review round: a cart mixing tax rates, and emails in mixed case.
		$tax_options = array( 'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_tax_display_cart', 'woocommerce_tax_display_shop' );
		$tax_before  = array_combine( $tax_options, array_map( 'get_option', $tax_options ) );
		$s->on_finish( static function () use ( $tax_before ) { foreach ( $tax_before as $name => $value ) { update_option( $name, $value ); } } );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_cart', 'incl' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		$rate_id = \WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => '', 'tax_rate' => '20.0000', 'tax_rate_name' => 't52 VAT', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 0, 'tax_rate_class' => '' ) );
		$s->on_finish( static function () use ( $rate_id ) { \WC_Tax::_delete_tax_rate( $rate_id ); } );
		$taxed   = t52_product( $s, 't52 taxed', '100' );
		$untaxed = t52_product( $s, 't52 untaxed', '100' );
		$untaxed->set_tax_class( 'zero-rate' );
		$untaxed->save();
		$free_taxed = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free_taxed}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '197', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$taxed_off = t52_coupon( $s, "{$tag}autotaxed", array( 'discount_type' => 'fixed_product', 'amount' => '20', 'individual_use' => true, 'product_ids' => array( $taxed->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		foreach ( array( $auto_a, $auto_b ) as $other ) {
			$other->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$other->save();
		}
		WC()->customer->set_shipping_country( 'EG' );
		WC()->customer->set_billing_country( 'EG' );
		t52_cart( array( $taxed->get_id() => 1, $untaxed->get_id() => 1 ) );
		$shown_subtotal = (float) WC()->cart->get_displayed_subtotal();
		$check( 220.0 === $shown_subtotal && ! in_array( wc_format_coupon_code( $taxed_off->get_code() ), t52_applied(), true ), "Q1 with 20 percent tax on one line only (shown 220), 20 off that line counts as 24 shown, takes the cart to 196 under a 197 free shipping minimum, and is not applied ({$shown_subtotal})" );
		// Fifth review round: the winner is the larger cut in what the customer pays, the discount's tax included,
		// whether prices are shown with tax or without it. 20 off the taxed line saves 24; 21 off the zero-rated line,
		// created earlier, saves 21.
		$zone->delete_shipping_method( $free_taxed );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$untaxed_off = t52_coupon( $s, "{$tag}autountaxed", array( 'discount_type' => 'fixed_product', 'amount' => '21', 'individual_use' => true, 'product_ids' => array( $untaxed->get_id() ), 'date_created' => time() - 3 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		foreach ( array( 'incl', 'excl' ) as $display ) {
			update_option( 'woocommerce_tax_display_cart', $display );
			update_option( 'woocommerce_tax_display_shop', $display );
			t52_cart( array( $taxed->get_id() => 1, $untaxed->get_id() => 1 ) );
			$check( array( wc_format_coupon_code( $taxed_off->get_code() ) ) === t52_applied(), "Q1 shown {$display} tax: 20 off a 20 percent taxed line (saves 24) beats 21 off a zero-rated line (saves 21), created earlier (" . implode( ',', t52_applied() ) . ')' );
		}
		$untaxed_off->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$untaxed_off->save();
		$taxed_off->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$taxed_off->save();
		// Sixth review round: shipping counts with its tax. Flat 50 plus 20 percent tax; free shipping saves 60, and 45
		// off the taxed line, created earlier, saves 54.
		\WC_Tax::_update_tax_rate( $rate_id, array( 'tax_rate_shipping' => 1 ) );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Flat', 'cost' => '50', 'tax_status' => 'taxable' ) );
		$free_coupon = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free_coupon}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$taxed_45 = t52_coupon( $s, "{$tag}autotaxed45", array( 'discount_type' => 'fixed_product', 'amount' => '45', 'individual_use' => true, 'product_ids' => array( $taxed->get_id() ), 'date_created' => time() - 4 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $taxed->get_id() => 1 ) );
		$check( array( wc_format_coupon_code( $ship_auto->get_code() ) ) === t52_applied(), 'Q1 free shipping of 50 plus 10 tax (saves 60) beats 45 off a 20 percent taxed line (saves 54), created earlier (' . implode( ',', t52_applied() ) . ')' );
		$taxed_45->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$taxed_45->save();
		$zone->delete_shipping_method( $free_coupon );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Flat', 'cost' => '50', 'tax_status' => 'none' ) );
		\WC_Tax::_update_tax_rate( $rate_id, array( 'tax_rate_shipping' => 0 ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		// Sixth review round: prices entered with tax, a tax-exempt customer. WooCommerce discounts the taxed line at
		// 100 less its tax (83.33), so 20 percent off it saves 16.67, less than 18 off the zero-rated line, created later.
		update_option( 'woocommerce_prices_include_tax', 'yes' );
		WC()->customer->set_is_vat_exempt( true );
		$s->on_finish( static function () { WC()->customer->set_is_vat_exempt( false ); } );
		$taxed_pct  = t52_coupon( $s, "{$tag}autotaxpct", array( 'discount_type' => 'percent', 'amount' => '20', 'individual_use' => true, 'product_ids' => array( $taxed->get_id() ), 'date_created' => time() - 5 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$untaxed_18 = t52_coupon( $s, "{$tag}autountaxed18", array( 'discount_type' => 'fixed_product', 'amount' => '18', 'individual_use' => true, 'product_ids' => array( $untaxed->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $taxed->get_id() => 1, $untaxed->get_id() => 1 ) );
		$check( array( wc_format_coupon_code( $untaxed_18->get_code() ) ) === t52_applied(), 'Q1 prices entered with 20 percent tax, a tax-exempt customer: 20 percent off the taxed line (saves 16.67) loses to 18 off the zero-rated line, created later (' . implode( ',', t52_applied() ) . ')' );
		WC()->customer->set_is_vat_exempt( false );
		foreach ( array( $taxed_pct, $untaxed_18 ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		// Seventh review round: prices are rebuilt from the unrounded tax, so they sort as WooCommerce sorts them.
		// Entered with tax, shown without it, tax rounded per line: a zero-rated 100 added before a taxed 100. 50
		// percent off one item falls on the zero-rated one, taking the shown 183.33 to 133.33, under a 135 free
		// shipping minimum, so it saves nothing; 20 off the zero-rated line keeps free shipping and applies.
		$round_before = get_option( 'woocommerce_tax_round_at_subtotal' );
		$s->on_finish( static function () use ( $round_before ) { update_option( 'woocommerce_tax_round_at_subtotal', $round_before ); } );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		$free_min = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free_min}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'min_amount', 'min_amount' => '135', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$half_one = t52_coupon( $s, "{$tag}autohalfone", array( 'discount_type' => 'percent', 'amount' => '50', 'individual_use' => true, 'limit_usage_to_x_items' => 1, 'date_created' => time() - 6 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$twenty   = t52_coupon( $s, "{$tag}autotwenty", array( 'discount_type' => 'fixed_product', 'amount' => '20', 'individual_use' => true, 'product_ids' => array( $untaxed->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		WC()->customer->set_shipping_country( 'EG' );
		WC()->customer->set_billing_country( 'EG' );
		t52_cart( array( $untaxed->get_id() => 1, $taxed->get_id() => 1 ) );
		$check( array( wc_format_coupon_code( $twenty->get_code() ) ) === t52_applied(), 'Q1 50 percent off one item, which WooCommerce puts on the zero-rated line and so loses free shipping, does not beat 20 off that keeps it (' . implode( ',', t52_applied() ) . ')' );
		foreach ( array( $half_one, $twenty ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		$zone->delete_shipping_method( $free_min );
		// Eighth review round: the discount keeps its fractions of a cent until the remaining total is rounded, as
		// WooCommerce does. Entered with tax, shown without it, tax rounded at subtotal: 100 with 20 percent tax shows
		// 83.33; 20 off leaves 66.666..., which rounds to 66.67 and meets a 66.67 free shipping minimum. So 20 off
		// (saves 20, ships free) beats 10 off, created earlier, and WooCommerce itself ships the cart free.
		update_option( 'woocommerce_tax_round_at_subtotal', 'yes' );
		$free_edge = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free_edge}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'min_amount', 'min_amount' => '66.67', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$ten_off    = t52_coupon( $s, "{$tag}autoten", array( 'discount_type' => 'fixed_product', 'amount' => '10', 'individual_use' => true, 'product_ids' => array( $taxed->get_id() ), 'date_created' => time() - 7 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$twenty_off = t52_coupon( $s, "{$tag}autotwentyedge", array( 'discount_type' => 'fixed_product', 'amount' => '20', 'individual_use' => true, 'product_ids' => array( $taxed->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $taxed->get_id() => 1 ) );
		$edge_free = false;
		foreach ( WC()->shipping()->get_packages() as $package ) {
			foreach ( (array) $package['rates'] as $rate ) {
				$edge_free = $edge_free || 'free_shipping' === $rate->get_method_id();
			}
		}
		$check( array( wc_format_coupon_code( $twenty_off->get_code() ) ) === t52_applied() && $edge_free, 'Q1 at the free shipping minimum to a fraction of a cent: 20 off that WooCommerce still ships free beats 10 off (' . implode( ',', t52_applied() ) . ', free shipping ' . ( $edge_free ? 'yes' : 'no' ) . ')' );
		foreach ( array( $ten_off, $twenty_off ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		$zone->delete_shipping_method( $free_edge );
		// Ninth review round: entered without tax, tax rounded per line, as WooCommerce rounds it. 100.03 off a 1,000.00
		// line removes 200.00 less 179.99 of tax (saves 120.04); 100.03 off a 1,000.02 line, created earlier, removes
		// 200.00 less 180.00 (saves 120.03). Rounding 100.03 with 20 percent once would tie them at 120.04.
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		$even = t52_product( $s, 't52 taxed 1000.00', '1000.00' );
		$odd  = t52_product( $s, 't52 taxed 1000.02', '1000.02' );
		$on_odd  = t52_coupon( $s, "{$tag}autoodd", array( 'discount_type' => 'fixed_product', 'amount' => '100.03', 'individual_use' => true, 'product_ids' => array( $odd->get_id() ), 'date_created' => time() - 8 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$on_even = t52_coupon( $s, "{$tag}autoeven", array( 'discount_type' => 'fixed_product', 'amount' => '100.03', 'individual_use' => true, 'product_ids' => array( $even->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $odd->get_id() => 1, $even->get_id() => 1 ) );
		$check( array( wc_format_coupon_code( $on_even->get_code() ) ) === t52_applied(), 'Q1 with tax rounded per line, 100.03 off that saves 120.04 beats 100.03 off that saves 120.03, created earlier (' . implode( ',', t52_applied() ) . ')' );
		foreach ( array( $on_odd, $on_even ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		$free_rate_now = static function (): bool {
			foreach ( WC()->shipping()->get_packages() as $package ) {
				foreach ( (array) $package['rates'] as $rate ) {
					if ( 'free_shipping' === $rate->get_method_id() ) {
						return true;
					}
				}
			}
			return false;
		};
		// Tenth review round, 1: the free shipping minimum sees the discount with its unrounded tax, as WooCommerce
		// does; only the saving is rounded per line. Shown with tax, the 1,000.02 line shows 1,200.02. 100.03 off leaves
		// 1,079.984, under a 1,079.99 minimum; 100.02 off, created later, leaves 1,079.996 and keeps free shipping.
		update_option( 'woocommerce_tax_display_cart', 'incl' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		$free_line = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free_line}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'min_amount', 'min_amount' => '1079.99', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$off_03  = t52_coupon( $s, "{$tag}auto10003", array( 'discount_type' => 'fixed_product', 'amount' => '100.03', 'individual_use' => true, 'product_ids' => array( $odd->get_id() ), 'date_created' => time() - 9 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$off_02  = t52_coupon( $s, "{$tag}auto10002", array( 'discount_type' => 'fixed_product', 'amount' => '100.02', 'individual_use' => true, 'product_ids' => array( $odd->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$code_03 = t52_coupon( $s, "{$tag}code10003", array( 'discount_type' => 'fixed_product', 'amount' => '100.03', 'individual_use' => true, 'product_ids' => array( $odd->get_id() ) ) );
		t52_cart( array( $odd->get_id() => 1 ) );
		$chosen      = t52_applied();
		$chosen_free = $free_rate_now();
		WC()->cart->apply_coupon( $code_03->get_code() );
		WC()->cart->calculate_totals();
		$code_free = $free_rate_now();
		$check( array( wc_format_coupon_code( $off_02->get_code() ) ) === $chosen && $chosen_free && ! $code_free, 'Q1 at a per-line-rounded free shipping minimum: 100.02 off, which WooCommerce ships free, beats 100.03 off, which WooCommerce does not (' . implode( ',', $chosen ) . ', free with it ' . ( $chosen_free ? 'yes' : 'no' ) . ', free with 100.03 ' . ( $code_free ? 'yes' : 'no' ) . ')' );
		foreach ( array( $off_03, $off_02 ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		$zone->delete_shipping_method( $free_line );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		// Tenth review round, 2: tax rounded at subtotal. 100.02 off a 1,000.03 taxed line takes the cart's tax from
		// 200.006 (200.01) to 180.002 (180.00), saving 120.03; 120.02 off a zero-rated line, created earlier, saves
		// 120.02. Checked against WooCommerce's own totals with each applied as a code.
		update_option( 'woocommerce_tax_round_at_subtotal', 'yes' );
		$taxed_03 = t52_product( $s, 't52 taxed 1000.03', '1000.03' );
		$zero_k   = t52_product( $s, 't52 zero-rated 1000', '1000' );
		$zero_k->set_tax_class( 'zero-rate' );
		$zero_k->save();
		$off_taxed = t52_coupon( $s, "{$tag}autosubtaxed", array( 'discount_type' => 'fixed_product', 'amount' => '100.02', 'individual_use' => true, 'product_ids' => array( $taxed_03->get_id() ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$off_zero  = t52_coupon( $s, "{$tag}autosubzero", array( 'discount_type' => 'fixed_product', 'amount' => '120.02', 'individual_use' => true, 'product_ids' => array( $zero_k->get_id() ), 'date_created' => time() - 10 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		$totals = array();
		foreach ( array( 'taxed' => $off_taxed, 'zero' => $off_zero ) as $label => $coupon ) {
			$copy = t52_coupon( $s, "{$tag}codesub{$label}", array( 'discount_type' => 'fixed_product', 'amount' => $coupon->get_amount(), 'individual_use' => true, 'product_ids' => $coupon->get_product_ids() ) );
			t52_cart( array( $taxed_03->get_id() => 1, $zero_k->get_id() => 1 ) );
			WC()->cart->apply_coupon( $copy->get_code() );
			WC()->cart->calculate_totals();
			$totals[ $label ] = (float) WC()->cart->get_total( 'edit' );
		}
		t52_cart( array( $taxed_03->get_id() => 1, $zero_k->get_id() => 1 ) );
		$check( array( wc_format_coupon_code( $off_taxed->get_code() ) ) === t52_applied() && $totals['taxed'] < $totals['zero'], 'Q1 with tax rounded at subtotal, 100.02 off the taxed line (WooCommerce total ' . $totals['taxed'] . ') beats 120.02 off the zero-rated line, created earlier (total ' . $totals['zero'] . ') (' . implode( ',', t52_applied() ) . ')' );
		foreach ( array( $off_taxed, $off_zero ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( 'woocommerce_tax_round_at_subtotal', $round_before );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		foreach ( $tax_before as $name => $value ) {
			update_option( $name, $value );
		}
		foreach ( array( $auto_a, $auto_b ) as $other ) {
			$other->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
			$other->save();
		}
		$taxed_off->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$taxed_off->save();
		WC()->customer->set_shipping_country( '' );
		WC()->customer->set_billing_country( '' );

		$cased = t52_coupon( $s, "{$tag}autocase", array( 'discount_type' => 'fixed_cart', 'amount' => '80', 'individual_use' => true, 'email_restrictions' => array( 'Mixed.Case-' . $tag . '@Example.invalid' ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		WC()->customer->set_billing_email( 'mixed.case-' . $tag . '@example.INVALID' );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( in_array( wc_format_coupon_code( $cased->get_code() ), t52_applied(), true ), 'AC-3 an email list matches regardless of letter case, as WooCommerce does' );
		WC()->customer->set_billing_email( '' );
		$cased->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$cased->save();

		$ship_auto->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$ship_auto->save();
		WC()->customer->set_shipping_country( '' );
		WC()->customer->set_billing_country( '' );

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

		// Third review round: an email list matches the account email as well as the billing email.
		$listed = t52_coupon( $s, "{$tag}autolist", array( 'discount_type' => 'fixed_cart', 'amount' => '70', 'individual_use' => true, 'email_restrictions' => array( $newcomer->user_email ) ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		wp_set_current_user( $newcomer->ID );
		WC()->customer = new \WC_Customer( $newcomer->ID, true );
		WC()->customer->set_billing_email( 'other-' . $tag . '@example.invalid' );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( in_array( wc_format_coupon_code( $listed->get_code() ), t52_applied(), true ), 'AC-3 an automatic promotion limited to an account email applies when the billing email differs, as WooCommerce accepts' );
		wp_set_current_user( 0 );
		WC()->customer = new \WC_Customer( 0, true );
		$listed->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$listed->save();

		// Second review round: usage across accounts and emails, provenance, shipping value rules, Arabic, expiry.
		$reuse = t52_coupon( $s, "{$tag}autoreuse", array( 'discount_type' => 'fixed_cart', 'amount' => '60', 'individual_use' => true, 'usage_limit_per_user' => 1, 'date_created' => time() - 4 * HOUR_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		add_post_meta( $reuse->get_id(), '_used_by', (string) $returning->ID );
		WC()->customer = new \WC_Customer( 0, true );
		WC()->customer->set_billing_email( $returning->user_email );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( wc_format_coupon_code( $reuse->get_code() ), t52_applied(), true ), 'AC-4 a guest using the email of an account that used the promotion up is not given it' );
		delete_post_meta( $reuse->get_id(), '_used_by' );
		add_post_meta( $reuse->get_id(), '_used_by', 'guestused-' . $tag . '@example.invalid' );
		wp_set_current_user( $newcomer->ID );
		WC()->customer = new \WC_Customer( $newcomer->ID, true );
		WC()->customer->set_billing_email( 'guestused-' . $tag . '@example.invalid' );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( wc_format_coupon_code( $reuse->get_code() ), t52_applied(), true ), 'AC-4 a customer whose billing email used the promotion up as a guest is not given it' );
		wp_set_current_user( 0 );
		WC()->customer = new \WC_Customer( 0, true );
		$reuse->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$reuse->save();

		t52_cart( array( $lipstick->get_id() => 2 ) );
		$held = t52_applied();
		$auto_a->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto_a->save();
		WC()->cart->calculate_totals();
		$check( array( $auto_a->get_code() ) === $held && array( $auto_b->get_code() ) === t52_applied(), 'AC-3 switching the flag off while a cart holds the promotion removes it and applies the next, without rebuilding the cart' );
		$auto_a->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$auto_a->save();

		$auto_gone = t52_coupon( $s, "{$tag}autogone", array( 'discount_type' => 'fixed_cart', 'amount' => '90', 'individual_use' => true, 'date_expires' => time() - DAY_IN_SECONDS ), array( PromotionSettings::AUTOMATIC => 'yes' ) );
		t52_cart( array( $lipstick->get_id() => 2 ) );
		$check( ! in_array( wc_format_coupon_code( $auto_gone->get_code() ), t52_applied(), true ), 'AC-5 an expired automatic promotion does not apply' );

		switch_to_locale( 'ar' );
		WC()->customer->set_billing_email( 'returning-' . $tag . '@example.invalid' );
		t52_cart( array( $cream->get_id() => 1 ) );
		$arabic_first = t52_valid( $first );
		$arabic_later = t52_valid( $later );
		restore_previous_locale();
		WC()->customer->set_billing_email( '' );
		$check( 'هذه القسيمة للطلب الأول فقط.' === $arabic_first && 'هذه القسيمة غير مفعّلة بعد.' === $arabic_later, 'AC-2, AC-5 the first-order and not-yet-active reasons are given in Arabic' );

		$deep = t52_coupon( $s, "{$tag}deep", array( 'discount_type' => 'fixed_product', 'amount' => '90', 'product_ids' => array( $lipstick->get_id() ) ) );
		$half = t52_coupon( $s, "{$tag}half", array( 'discount_type' => 'percent', 'amount' => '50' ), array( PromotionSettings::MAX_DISCOUNT => '70' ) );
		$twin_item = t52_product( $s, 't52 twin', '100' );
		t52_cart( array( $lipstick->get_id() => 1, $twin_item->get_id() => 1 ) );
		$discounts = new \WC_Discounts( WC()->cart );
		$discounts->apply_coupon( new \WC_Coupon( $deep->get_code() ), false );
		$discounts->apply_coupon( new \WC_Coupon( $half->get_code() ), false );
		$after_deep = (float) $discounts->get_discounts_by_coupon()[ wc_format_coupon_code( $half->get_code() ) ];
		$check( 60.01 === $after_deep, 'AC-7 after a deep discount on one line, a capped 50 percent that fits under its cap is not lowered (' . $after_deep . ')' );

		// AC-7: the cap, through WooCommerce's own allocation, rounding and leftover cents.
		$capped = t52_coupon( $s, "{$tag}cap", array( 'discount_type' => 'percent', 'amount' => '10' ), array( PromotionSettings::MAX_DISCOUNT => '150' ) );
		t52_cart( array( $lipstick->get_id() => 20 ) );
		$check( 150.0 === t52_discount( $capped ), 'AC-7 10 percent of 2,000 is capped at 150' );
		$tight = t52_coupon( $s, "{$tag}captight", array( 'discount_type' => 'percent', 'amount' => '50' ), array( PromotionSettings::MAX_DISCOUNT => '33.32' ) );
		$odd2  = t52_product( $s, 't52 odd two', '33.33' );
		t52_cart( array( $odd->get_id() => 1, $odd2->get_id() => 1 ) );
		$check( 33.32 === t52_discount( $tight ), 'AC-7 where WooCommerce would add a leftover cent (50 percent of 66.66, cap 33.32) the cap still holds' );
		WC()->cart->apply_coupon( $tight->get_code() );
		WC()->cart->calculate_totals();
		$check( 33.32 === (float) WC()->cart->get_discount_total(), 'AC-7 the cart total shows the capped discount ( ' . WC()->cart->get_discount_total() . ' )' );
		$small = t52_coupon( $s, "{$tag}capodd", array( 'discount_type' => 'percent', 'amount' => '50' ), array( PromotionSettings::MAX_DISCOUNT => '5' ) );
		t52_cart( array( $odd->get_id() => 3, $cream->get_id() => 1, $lipstick->get_id() => 1 ) );
		$check( 5.0 === t52_discount( $small ), 'AC-7 across several lines and odd prices the cap holds to the cent' );
		t52_cart( array( $cream->get_id() => 1 ) );
		$check( 5.0 === t52_discount( $percent ) && 5.0 === t52_discount( $capped ), 'AC-7 below the cap the discount is the full percentage' );
		$fixed_capped = t52_coupon( $s, "{$tag}capfix", array( 'discount_type' => 'fixed_cart', 'amount' => '100' ), array( PromotionSettings::MAX_DISCOUNT => '40' ) );
		t52_cart( array( $tablets->get_id() => 1 ) );
		$check( 40.0 === t52_discount( $fixed_capped ), 'AC-7 a fixed cart discount of 100 capped at 40 gives 40' );
		$limited = t52_coupon( $s, "{$tag}caplim", array( 'discount_type' => 'percent', 'amount' => '50', 'limit_usage_to_x_items' => 1 ), array( PromotionSettings::MAX_DISCOUNT => '30' ) );
		t52_cart( array( $lipstick->get_id() => 3 ) );
		$check( 30.0 === t52_discount( $limited ), 'AC-7 with the item limit (50 percent of one 100 item), the cap of 30 holds' );
		$sequential = get_option( 'woocommerce_calc_discounts_sequentially', 'no' );
		$s->on_finish( static function () use ( $sequential ) { update_option( 'woocommerce_calc_discounts_sequentially', $sequential ); } );
		update_option( 'woocommerce_calc_discounts_sequentially', 'yes' );
		$seq_cap = t52_coupon( $s, "{$tag}capseq", array( 'discount_type' => 'percent', 'amount' => '50' ), array( PromotionSettings::MAX_DISCOUNT => '20' ) );
		t52_cart( array( $lipstick->get_id() => 1, $cream->get_id() => 1 ) );
		$discounts = new \WC_Discounts( WC()->cart );
		$discounts->apply_coupon( new \WC_Coupon( $fixed->get_code() ), false );
		$discounts->apply_coupon( new \WC_Coupon( $seq_cap->get_code() ), false );
		$by_coupon = $discounts->get_discounts_by_coupon();
		update_option( 'woocommerce_calc_discounts_sequentially', $sequential );
		$check( 20.0 === (float) $by_coupon[ wc_format_coupon_code( $seq_cap->get_code() ) ], 'AC-7 with sequential discounts, after a fixed 30, the capped promotion gives its cap of 20' );
		$order = wc_create_order();
		$order->add_product( $lipstick, 20 );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		$order->apply_coupon( $capped->get_code() );
		$order = wc_get_order( $order->get_id() );
		$first_total = (float) $order->get_discount_total();
		$order->recalculate_coupons();
		$check( 150.0 === $first_total && 150.0 === (float) wc_get_order( $order->get_id() )->get_discount_total(), 'AC-7 on an order, applying and recalculating the coupons keeps the cap (' . $first_total . ')' );

		// AC-8: the coupon screen's own save.
		require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-coupon-data.php';
		$screen = t52_coupon( $s, "{$tag}screen", array( 'discount_type' => 'percent', 'amount' => '10' ) );
		$form   = static fn ( array $extra ): array => $extra + array(
			'discount_type' => 'percent', 'coupon_amount' => '10', 'expiry_date' => wp_date( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ),
			'usage_limit' => '', 'usage_limit_per_user' => '', 'limit_usage_to_x_items' => '', 'minimum_amount' => '', 'maximum_amount' => '', 'customer_email' => '',
		);
		$_POST = $form( array( 'mizzey_automatic' => 'yes', 'mizzey_starts' => wp_date( 'Y-m-d', time() + 2 * DAY_IN_SECONDS ), 'mizzey_max_discount' => '75', 'mizzey_min_items' => '4', 'mizzey_first_order' => 'yes' ) );
		\WC_Meta_Box_Coupon_Data::save( $screen->get_id(), get_post( $screen->get_id() ) );
		$saved = new \WC_Coupon( $screen->get_id() );
		$check( PromotionSettings::automatic( $saved ) && null !== PromotionSettings::starts( $saved ) && 75.0 === PromotionSettings::maxDiscount( $saved ) && 4 === PromotionSettings::minItems( $saved ) && PromotionSettings::firstOrder( $saved ) && $saved->get_individual_use(), 'AC-8 every setting saves through the coupon screen, and an automatic promotion becomes individual-use' );
		$before = PromotionSettings::starts( $saved );
		$_POST  = $form( array( 'mizzey_automatic' => 'yes', 'mizzey_starts' => wp_date( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), 'mizzey_max_discount' => '75' ) );
		\WC_Meta_Box_Coupon_Data::save( $screen->get_id(), get_post( $screen->get_id() ) );
		$check( $before === PromotionSettings::starts( new \WC_Coupon( $screen->get_id() ) ), 'AC-5 a start after the expiry is not saved' );
		$_POST = $form( array( 'discount_type' => 'fixed_product', 'mizzey_max_discount' => '75' ) );
		\WC_Meta_Box_Coupon_Data::save( $screen->get_id(), get_post( $screen->get_id() ) );
		$check( null === PromotionSettings::maxDiscount( new \WC_Coupon( $screen->get_id() ) ), 'AC-7 a cap is not saved on a fixed per-product discount' );
		$_POST = array();

		return $ok;
	}
);

<?php
/**
 * t55, promotions managed from admin (specs/014-promotions-admin, #289).
 *
 * In-process, through the coupon screen's own save handler (`WC_Meta_Box_Coupon_Data::save()`) and WooCommerce's real
 * cart and discount calculation: a promotion created automatic and one by code, its conditions, limits and exclusions,
 * its start and end, and a pause and reactivation on the same screen that keep its use count and its record of who
 * used it, with use recorded and reversed by a real order. Every coupon, product and user it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\PromotionSettings;

require_once __DIR__ . '/_bootstrap.php';

/** A coupon saved through the coupon screen's own save handler, with the screen's fields. */
function t55_screen( Scenario $s, string $code, array $fields ): \WC_Coupon {
	require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-coupon-data.php';
	$coupon = new \WC_Coupon();
	$coupon->set_code( $code );
	$coupon->save();
	$s->on_finish( static function () use ( $coupon ) { wp_delete_post( $coupon->get_id(), true ); } );
	$_POST = $fields + array(
		'discount_type' => 'fixed_cart', 'coupon_amount' => '10', 'expiry_date' => '', 'usage_limit' => '', 'usage_limit_per_user' => '',
		'limit_usage_to_x_items' => '', 'minimum_amount' => '', 'maximum_amount' => '', 'customer_email' => '',
	);
	\WC_Meta_Box_Coupon_Data::save( $coupon->get_id(), get_post( $coupon->get_id() ) );
	$_POST = array();
	AutomaticPromotions::forget();
	return new \WC_Coupon( $coupon->get_id() );
}

/** @param array<int,int> $lines product id => quantity */
function t55_cart( array $lines ): \WC_Cart {
	$cart = WC()->cart;
	$cart->empty_cart();
	$cart->remove_coupons();
	foreach ( $lines as $id => $qty ) {
		$cart->add_to_cart( $id, $qty );
	}
	$cart->calculate_totals();
	return $cart;
}

function t55_applied(): array {
	return array_values( array_map( 'wc_format_coupon_code', WC()->cart->get_applied_coupons() ) );
}

/** True, or the refusal, for a code typed by a customer: found by code, as WooCommerce finds it. */
function t55_valid( string $code ) {
	$result = ( new \WC_Discounts( WC()->cart ) )->is_coupon_valid( new \WC_Coupon( $code ) );
	return true === $result ? true : $result->get_error_message();
}

/** Save an existing promotion again through the coupon screen, with the given fields (an unticked box is absent). */
function t55_resave( \WC_Coupon $coupon, array $fields ): \WC_Coupon {
	$_POST = $fields + array(
		'discount_type' => $coupon->get_discount_type(), 'coupon_amount' => (string) $coupon->get_amount(), 'expiry_date' => '', 'usage_limit' => '',
		'usage_limit_per_user' => (string) ( $coupon->get_usage_limit_per_user() ?: '' ), 'limit_usage_to_x_items' => '',
		'minimum_amount' => (string) ( $coupon->get_minimum_amount() ?: '' ), 'maximum_amount' => '', 'customer_email' => '',
	);
	\WC_Meta_Box_Coupon_Data::save( $coupon->get_id(), get_post( $coupon->get_id() ) );
	$_POST = array();
	return new \WC_Coupon( $coupon->get_id() );
}

run(
	new Scenario( 't55-promotions-admin', 'ADM-110, ADM-111, ADM-113, ADM-115: promotions managed from admin' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) {
			WC()->cart->empty_cart();
			WC()->cart->remove_coupons();
			wp_set_current_user( $previous );
			AutomaticPromotions::forget();
		} );
		wp_set_current_user( 0 );
		$tag     = 't55' . wp_rand( 1000, 9999 );
		$product = $s->simple_product( 't55 serum', '100', null );
		$other   = $s->simple_product( 't55 excluded', '100', null );

		// AC-1, AC-2: an automatic promotion and a coupon code, made on the coupon screen, with conditions and limits.
		$auto = t55_screen( $s, "{$tag}auto", array( 'discount_type' => 'percent', 'coupon_amount' => '10', 'mizzey_automatic' => 'yes', 'mizzey_min_items' => '2', 'mizzey_max_discount' => '15', 'exclude_product_ids' => array( $other->get_id() ) ) );
		$check( PromotionSettings::automatic( $auto ) && 2 === PromotionSettings::minItems( $auto ) && 15.0 === PromotionSettings::maxDiscount( $auto ) && array( $other->get_id() ) === array_map( 'intval', $auto->get_excluded_product_ids() ), 'AC-1, AC-2 the coupon screen saves an automatic promotion with its condition (two items), cap (15) and exclusion' );
		t55_cart( array( $product->get_id() => 1 ) );
		$below = t55_applied();
		t55_cart( array( $product->get_id() => 2 ) );
		$above    = t55_applied();
		$discount = (float) WC()->cart->get_discount_total();
		$check( array() === $below && array( wc_format_coupon_code( $auto->get_code() ) ) === $above && 15.0 === $discount, "AC-1, AC-2 it applies with no code once the cart holds two items, capped at 15 ({$discount}); with one item it does not" );
		$auto->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto->save();
		AutomaticPromotions::forget();
		$code = t55_screen( $s, "{$tag}code", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '20', 'usage_limit_per_user' => '1', 'minimum_amount' => '50' ) );
		t55_cart( array( $product->get_id() => 1 ) );
		$check( true === t55_valid( $code->get_code() ) && 1 === $code->get_usage_limit_per_user() && 50.0 === (float) $code->get_minimum_amount(), 'AC-1, AC-2 a coupon code made on the screen, with a minimum spend and a per-customer limit, is valid on a qualifying cart' );

		// AC-3: start and end.
		$later = t55_screen( $s, "{$tag}later", array( 'mizzey_starts' => wp_date( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ) ) );
		$ended = t55_screen( $s, "{$tag}ended", array( 'expiry_date' => wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) );
		$check( true !== t55_valid( $later->get_code() ) && true !== t55_valid( $ended->get_code() ), 'AC-3 a promotion whose start is in three days, and one that ended yesterday, are both refused' );

		// AC-4: a code used on a real order, paused on the coupon screen, and reactivated there.
		$customer_id = (int) wp_insert_user( array( 'user_login' => "{$tag}-c", 'user_email' => "{$tag}-c@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
		$s->track_user( $customer_id );
		$placed = wc_create_order( array( 'customer_id' => $customer_id ) );
		$placed->add_product( $product, 1 );
		$placed->set_billing_email( "{$tag}-c@example.invalid" );
		$placed->apply_coupon( $code->get_code() );
		$placed->calculate_totals();
		$placed->set_status( 'processing' );
		$placed->save();
		$s->track_order( $placed->get_id() );
		$used     = new \WC_Coupon( $code->get_id() );
		$discount = (float) wc_get_order( $placed->get_id() )->get_discount_total();
		$check( 1 === $used->get_usage_count() && in_array( (string) $customer_id, array_map( 'strval', $used->get_used_by() ), true ) && 20.0 === $discount, "AC-4 an order that uses the code records one use by its customer ({$used->get_usage_count()}) and takes 20 off ({$discount})" );
		$paused = t55_resave( $code, array( 'mizzey_paused' => 'yes' ) );
		t55_cart( array( $product->get_id() => 1 ) );
		$refusal = t55_valid( $code->get_code() );
		$check( PromotionSettings::paused( $paused ) && 'publish' === get_post_status( $code->get_id() ) && 'This coupon is paused.' === $refusal, "AC-4 ticked Paused on the coupon screen, the promotion stays published and a new cart is refused it ({$refusal})" );
		$kept = new \WC_Coupon( $code->get_id() );
		$check( 1 === $kept->get_usage_count() && $used->get_used_by() === $kept->get_used_by(), 'AC-4 pausing keeps the use count and the record of who used it' );
		$order_again = wc_get_order( $placed->get_id() );
		$order_again->recalculate_coupons();
		$check( 20.0 === (float) wc_get_order( $placed->get_id() )->get_discount_total(), 'AC-4 an order that already has the paused promotion keeps its discount when its coupons are recalculated' );
		wc_get_order( $placed->get_id() )->update_status( 'cancelled', 't55 cancelled while paused' );
		$reversed = new \WC_Coupon( $code->get_id() );
		$check( 0 === $reversed->get_usage_count(), "AC-4 cancelling that order while the promotion is paused reverses its use, as WooCommerce does for a live one ({$reversed->get_usage_count()})" );
		$back = t55_resave( $code, array() );
		t55_cart( array( $product->get_id() => 1 ) );
		$check( ! PromotionSettings::paused( $back ) && true === t55_valid( $code->get_code() ), 'AC-4 unticked Paused, the promotion applies again' );

		// AC-4: a code that is its own number is paused like any other.
		$numeric = new \WC_Coupon();
		$numeric->set_code( "{$tag}tmp" );
		$numeric->save();
		$s->on_finish( static function () use ( $numeric ) { wp_delete_post( $numeric->get_id(), true ); } );
		$numeric->set_code( (string) $numeric->get_id() );
		$numeric->save();
		t55_resave( $numeric, array( 'mizzey_paused' => 'yes' ) );
		t55_cart( array( $product->get_id() => 1 ) );
		$number_refusal = t55_valid( (string) $numeric->get_id() );
		$check( 'This coupon is paused.' === $number_refusal, "AC-4 a paused code that is the coupon's own number is refused too ({$number_refusal})" );

		// AC-4: an automatic promotion paused while a cart holds it.
		$auto = t55_resave( $auto, array( 'discount_type' => 'percent', 'coupon_amount' => '10', 'mizzey_automatic' => 'yes', 'mizzey_min_items' => '2', 'mizzey_max_discount' => '15' ) );
		t55_cart( array( $product->get_id() => 2 ) );
		$held = t55_applied();
		t55_resave( $auto, array( 'discount_type' => 'percent', 'coupon_amount' => '10', 'mizzey_automatic' => 'yes', 'mizzey_min_items' => '2', 'mizzey_max_discount' => '15', 'mizzey_paused' => 'yes' ) );
		WC()->cart->calculate_totals();
		$after_pause = t55_applied();
		t55_resave( $auto, array( 'discount_type' => 'percent', 'coupon_amount' => '10', 'mizzey_automatic' => 'yes', 'mizzey_min_items' => '2', 'mizzey_max_discount' => '15' ) );
		WC()->cart->calculate_totals();
		$after_back = t55_applied();
		$name       = wc_format_coupon_code( $auto->get_code() );
		$check( array( $name ) === $held && array() === $after_pause && array( $name ) === $after_back, 'AC-4 an automatic promotion paused while a cart holds it is taken off on the next calculation, and comes back when reactivated' );
		t55_resave( $auto, array( 'discount_type' => 'percent', 'coupon_amount' => '10' ) );
		return $ok;
	}
);

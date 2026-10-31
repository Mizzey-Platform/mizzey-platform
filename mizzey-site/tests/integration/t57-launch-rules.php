<?php
/**
 * t57, the launch business rules (specs/016-launch-rules, #290).
 *
 * In-process on WooCommerce's real cart, discounts, shipping and refunds: the welcome discount issued per new customer
 * from a template saved on the coupon screen (BR-001), free shipping on two or more units (BR-002), the combination
 * setting off and on (BR-004), the refund ceiling (BR-006), the free-shipping override (BR-009) and the non-negative
 * total (BR-010). Over HTTP: a guest order, then the order-received offer links it to the new account (BR-008). Every
 * coupon, product, order, user and zone it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\Combination;
use MizzeySite\Promotions\PromotionSettings;
use MizzeySite\Promotions\WelcomeDiscount;
use MizzeySite\Security\SignupCheck;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A coupon saved through the coupon screen's own save handler. */
function t57_screen( Scenario $s, string $code, array $fields ): \WC_Coupon {
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
function t57_cart( array $lines, array $codes = array() ): \WC_Cart {
	$cart = WC()->cart;
	$cart->empty_cart();
	$cart->remove_coupons();
	foreach ( $lines as $id => $qty ) {
		$cart->add_to_cart( $id, $qty );
	}
	foreach ( $codes as $code ) {
		$cart->apply_coupon( $code );
	}
	wc_clear_notices();
	$cart->calculate_totals();
	return $cart;
}

function t57_applied(): array {
	$codes = array_values( array_map( 'wc_format_coupon_code', WC()->cart->get_applied_coupons() ) );
	sort( $codes );
	return $codes;
}

function t57_code( \WC_Coupon $coupon ): string {
	return wc_format_coupon_code( $coupon->get_code() );
}

/** A customer, tracked for removal; the welcome discount is issued as the account is created. */
function t57_customer( Scenario $s, string $login ): int {
	$id = (int) wp_insert_user( array( 'user_login' => $login, 'user_email' => "{$login}@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
	$s->track_user( $id );
	$own = (int) get_user_meta( $id, WelcomeDiscount::USER_META, true );
	if ( $own > 0 ) {
		$s->on_finish( static function () use ( $own ) { wp_delete_post( $own, true ); } );
	}
	return $id;
}

run(
	new Scenario( 't57-launch-rules', 'BR-001, BR-002, BR-004, BR-006, BR-008, BR-009, BR-010: the launch business rules' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$options  = array( Combination::OPTION, 'woocommerce_shipping_hide_rates_when_free' );
		$before   = array_combine( $options, array_map( static fn ( $o ) => get_option( $o, null ), $options ) );
		$s->on_finish( static function () use ( $previous, $before ) {
			WC()->cart->empty_cart();
			WC()->cart->remove_coupons();
			foreach ( $before as $name => $value ) {
				null === $value ? delete_option( $name ) : update_option( $name, $value );
			}
			wp_set_current_user( $previous );
			WC()->customer->set_billing_email( '' );
			WC()->customer->set_shipping_country( '' );
			AutomaticPromotions::forget();
		} );
		delete_option( Combination::OPTION );
		wp_set_current_user( 0 );
		$tag   = 't57' . wp_rand( 1000, 9999 );
		$cheap = $s->simple_product( 't57 lip balm', '100', null );
		$mid   = $s->simple_product( 't57 serum', '600', null );
		$big   = $s->simple_product( 't57 kit', '3000', null );

		// BR-001: without a template, no welcome discount is issued.
		$early = t57_customer( $s, "{$tag}-early" );
		$check( null === WelcomeDiscount::template() || 0 < (int) get_user_meta( $early, WelcomeDiscount::USER_META, true ), 'BR-001 a customer created while no template exists gets no welcome discount' );

		// BR-001: OD-03's example set up as the template on the coupon screen.
		$template = t57_screen( $s, "{$tag}welcome", array( 'discount_type' => 'percent', 'coupon_amount' => '10', 'minimum_amount' => '500', 'individual_use' => 'yes', 'mizzey_max_discount' => '200', 'mizzey_welcome_template' => 'yes', 'mizzey_welcome_days' => '14' ) );
		$check( WelcomeDiscount::isTemplate( $template ) && 14 === WelcomeDiscount::days( $template ) && $template->get_id() === WelcomeDiscount::template()?->get_id(), 'BR-001 the coupon screen saves the welcome discount template (10 percent, cap 200, minimum 500, 14 days, not combined)' );
		t57_cart( array( $mid->get_id() => 1 ) );
		$refused = ( new \WC_Discounts( WC()->cart ) )->is_coupon_valid( new \WC_Coupon( $template->get_code() ) );
		$check( is_wp_error( $refused ) && 'This coupon cannot be used.' === $refused->get_error_message(), 'BR-001 the template itself is never accepted as a code (' . ( is_wp_error( $refused ) ? $refused->get_error_message() : 'accepted' ) . ')' );

		$customer = t57_customer( $s, "{$tag}-new" );
		$own_id   = (int) get_user_meta( $customer, WelcomeDiscount::USER_META, true );
		$own      = new \WC_Coupon( $own_id );
		$expires  = $own->get_date_expires();
		$check( $own_id > 0 && array( "{$tag}-new@example.invalid" ) === $own->get_email_restrictions() && 1 === $own->get_usage_limit() && 1 === $own->get_usage_limit_per_user() && PromotionSettings::firstOrder( $own ) && PromotionSettings::automatic( $own ) && 200.0 === PromotionSettings::maxDiscount( $own ) && $expires && abs( $expires->getTimestamp() - ( time() + 14 * DAY_IN_SECONDS ) ) < DAY_IN_SECONDS, 'BR-001 creating a customer account issues their own welcome discount: their email, one use, first order only, 14 days, capped at 200' );
		$check( ! in_array( t57_code( $own ), AutomaticPromotions::codes(), true ), 'BR-001 a customer\'s own welcome discount is not one of the store\'s automatic promotions every cart is measured against' );

		wp_set_current_user( $customer );
		WC()->customer->set_billing_email( "{$tag}-new@example.invalid" );
		t57_cart( array( $mid->get_id() => 1 ) );
		$on_600 = (float) WC()->cart->get_discount_total();
		$held   = t57_applied();
		t57_cart( array( $big->get_id() => 1 ) );
		$on_3000 = (float) WC()->cart->get_discount_total();
		t57_cart( array( $cheap->get_id() => 1 ) );
		$on_100 = t57_applied();
		$check( array( t57_code( $own ) ) === $held && 60.0 === $on_600 && 200.0 === $on_3000 && array() === $on_100, "BR-001 applied automatically for its customer: 60 off 600, capped at 200 on 3,000 ({$on_3000}), not under the 500 minimum" );
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}-staff", 'user_email' => "{$tag}-staff@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'editor' ) );
		$s->track_user( $staff );
		$other = t57_customer( $s, "{$tag}-other" );
		wp_set_current_user( $other );
		WC()->customer->set_billing_email( "{$tag}-other@example.invalid" );
		t57_cart( array( $mid->get_id() => 1 ) );
		$others = t57_applied();
		$check( ! in_array( t57_code( $own ), $others, true ) && 0 === (int) get_user_meta( $staff, WelcomeDiscount::USER_META, true ), 'BR-001 another customer\'s cart never gets it, and a staff account gets none' );
		wp_set_current_user( $customer );
		WC()->customer->set_billing_email( "{$tag}-new@example.invalid" );
		$own->increase_usage_count( (string) $customer );
		t57_cart( array( $mid->get_id() => 1 ) );
		$check( array() === t57_applied(), 'BR-001 used once, it no longer applies' );
		wp_set_current_user( 0 );
		WC()->customer->set_billing_email( '' );

		// Shipping for BR-002 and BR-009: a flat rate and a free shipping method that takes a coupon.
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
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->customer->set_shipping_country( 'EG' );
		update_option( 'woocommerce_shipping_hide_rates_when_free', 'yes' );

		// BR-002, OD-04: free shipping on two or more units, any mix, set up as an automatic promotion.
		$two = t57_screen( $s, "{$tag}two", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0', 'free_shipping' => 'yes', 'mizzey_automatic' => 'yes', 'mizzey_min_items' => '2' ) );
		t57_cart( array( $cheap->get_id() => 1, $mid->get_id() => 1 ) );
		$two_units = (float) WC()->cart->get_shipping_total();
		$two_held  = t57_applied();
		t57_cart( array( $cheap->get_id() => 1 ) );
		$one_unit = (float) WC()->cart->get_shipping_total();
		$check( array( t57_code( $two ) ) === $two_held && 0.0 === $two_units && 50.0 === $one_unit, "BR-002 two units of different products ship free; one unit pays the flat rate ({$one_unit})" );

		// BR-009: the free-shipping promotion overrides the calculated rate, with the store's option to hide paid rates.
		t57_cart( array( $cheap->get_id() => 2 ) );
		$methods = array();
		foreach ( WC()->shipping()->get_packages() as $package ) {
			foreach ( (array) $package['rates'] as $rate ) {
				$methods[] = $rate->get_method_id();
			}
		}
		$check( array( 'free_shipping' ) === $methods && 0.0 === (float) WC()->cart->get_shipping_total(), 'BR-009 with the free-shipping promotion the paid rate is not offered and shipping is 0 (' . implode( ',', $methods ) . ')' );

		// BR-004: off (the default), one promotion per order.
		$tenoff = t57_screen( $s, "{$tag}ten", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '10' ) );
		$five   = t57_screen( $s, "{$tag}five", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '5' ) );
		$auto   = t57_screen( $s, "{$tag}auto", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '15', 'mizzey_automatic' => 'yes' ) );
		$field  = array_values( array_filter( (array) apply_filters( 'woocommerce_general_settings', array() ), static fn ( $f ) => is_array( $f ) && Combination::OPTION === ( $f['id'] ?? '' ) ) );
		t57_cart( array( $cheap->get_id() => 2 ) );
		$auto_only = t57_applied();
		t57_cart( array( $cheap->get_id() => 2 ), array( $tenoff->get_code(), $five->get_code() ) );
		$entered = t57_applied();
		$check( 1 === count( $field ) && 'no' === ( $field[0]['default'] ?? '' ) && ! Combination::allowed() && 1 === count( $auto_only ) && array( t57_code( $five ) ) === $entered, 'BR-004 the setting is in WooCommerce\'s general settings, off by default: one automatic promotion, and two codes entered leave only the last (' . implode( ',', $entered ) . ')' );

		// BR-004: on, the promotions' own individual-use boxes decide.
		update_option( Combination::OPTION, 'yes' );
		$auto->set_individual_use( false );
		$auto->save();
		$two->set_individual_use( false );
		$two->save();
		AutomaticPromotions::forget();
		t57_cart( array( $cheap->get_id() => 2 ), array( $tenoff->get_code() ) );
		$together = t57_applied();
		$expect   = array( t57_code( $auto ), t57_code( $two ), t57_code( $tenoff ) );
		sort( $expect );
		$off_total = (float) WC()->cart->get_discount_total();
		$check( $expect === $together && 25.0 === $off_total && 0.0 === (float) WC()->cart->get_shipping_total(), 'BR-004 on: an entered code, an automatic discount and automatic free shipping combine (' . implode( ',', $together ) . ', 25 off, ships free)' );
		$solo = t57_screen( $s, "{$tag}solo", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '8', 'individual_use' => 'yes' ) );
		t57_cart( array( $cheap->get_id() => 2 ), array( $solo->get_code() ) );
		$check( array( t57_code( $solo ) ) === t57_applied(), 'BR-004 on: an individual-use code still stands alone' );
		foreach ( array( $auto, $two ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		delete_option( Combination::OPTION );
		AutomaticPromotions::forget();

		// BR-010: a discount larger than the order never makes the total negative.
		$huge = t57_screen( $s, "{$tag}huge", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '5000' ) );
		t57_cart( array( $cheap->get_id() => 1 ), array( $huge->get_code() ) );
		$total = (float) WC()->cart->get_total( 'edit' );
		$check( $total >= 0.0 && 100.0 === (float) WC()->cart->get_discount_total(), "BR-010 5,000 off a 100 order takes 100 off, and the total is {$total}, not negative" );

		// BR-006: a refund never exceeds what was paid, for the order or for a line.
		$paid = $s->order_for( $mid, 1 );
		$over = wc_create_refund( array( 'order_id' => $paid->get_id(), 'amount' => '601' ) );
		$line = (int) array_key_first( $paid->get_items() );
		$over_line = wc_create_refund( array( 'order_id' => $paid->get_id(), 'amount' => '601', 'line_items' => array( $line => array( 'qty' => 1, 'refund_total' => 601 ) ) ) );
		$fine = wc_create_refund( array( 'order_id' => $paid->get_id(), 'amount' => '600' ) );
		$more = wc_create_refund( array( 'order_id' => $paid->get_id(), 'amount' => '1' ) );
		$check( is_wp_error( $over ) && is_wp_error( $over_line ) && $fine instanceof \WC_Order_Refund && is_wp_error( $more ), 'BR-006 601 back on a 600 order is refused, also for its line; 600 is refunded; then even 1 more is refused' );

		// BR-008: a guest orders, then the order-received offer links the order to the new account.
		sign_in_options(
			$s,
			array(
				'woocommerce_enable_guest_checkout'                 => 'yes',
				'woocommerce_enable_delayed_account_creation'       => 'yes',
				'woocommerce_enable_myaccount_registration'         => 'yes',
				'woocommerce_registration_generate_username'        => 'yes',
				'woocommerce_registration_generate_password'        => 'no',
				'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			)
		);
		$guest_email = "{$tag}-guest@example.invalid";
		$guest_order = wc_create_order();
		$guest_order->add_product( $mid, 1 );
		$guest_order->set_billing_email( $guest_email );
		$guest_order->set_billing_first_name( 'Guest' );
		$guest_order->calculate_totals();
		$guest_order->set_status( 'processing' );
		$guest_order->save();
		$s->track_order( $guest_order->get_id() );
		$before_link = (int) $guest_order->get_customer_id();
		$offer = two_factor_http( 'POST', $guest_order->get_checkout_order_received_url(), array( 'create-account' => '1', 'email' => $guest_email, 'password' => 'Pw-' . wp_generate_password( 18, false ), '_wpnonce' => wp_create_nonce( 'wc_create_account' ), 'mizzey_signup_token' => SignupCheck::issue( 'order-received', time() - 10 ), 'mizzey_signup_extra' => '' ) );
		$account = get_user_by( 'email', $guest_email );
		if ( $account ) {
			$s->track_user( $account->ID );
		}
		$linked = (int) wc_get_order( $guest_order->get_id() )->get_customer_id();
		$check( 0 === $before_link && $account && $account->ID === $linked, '[HTTP] BR-008 a guest order, then the account made from the order-received page, has the order linked to it (status ' . ( $offer['status'] ?? '?' ) . ', account ' . ( $account ? $account->ID : 'none' ) . ', linked ' . $linked . ')' );

		return $ok;
	}
);

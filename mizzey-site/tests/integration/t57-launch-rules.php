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
		// BR-001: a welcome discount is its customer's alone, for new customers only, once per email ever.
		$third    = t57_customer( $s, "{$tag}-third" );
		$third_cp = new \WC_Coupon( (int) get_user_meta( $third, WelcomeDiscount::USER_META, true ) );
		wp_set_current_user( $other );
		WC()->customer->set_billing_email( "{$tag}-third@example.invalid" );
		t57_cart( array( $mid->get_id() => 1 ) );
		$taken = ( new \WC_Discounts( WC()->cart ) )->is_coupon_valid( new \WC_Coupon( $third_cp->get_code() ) );
		$check( is_wp_error( $taken ) && 'This coupon cannot be used.' === $taken->get_error_message(), 'BR-001 another customer entering someone\'s welcome code with that person\'s email is refused (' . ( is_wp_error( $taken ) ? $taken->get_error_message() : 'accepted' ) . ')' );
		wp_set_current_user( 0 );
		$returning_email = "{$tag}-back@example.invalid";
		$earlier         = wc_create_order();
		$earlier->add_product( $cheap, 1 );
		$earlier->set_billing_email( $returning_email );
		$earlier->calculate_totals();
		$earlier->set_status( 'processing' );
		$earlier->save();
		$s->track_order( $earlier->get_id() );
		$returning = t57_customer( $s, "{$tag}-back" );
		wp_set_current_user( $returning );
		WC()->customer->set_billing_email( "{$tag}-elsewhere@example.invalid" );
		t57_cart( array( $mid->get_id() => 1 ) );
		$check( array() === t57_applied(), 'BR-001 a guest who ordered, then registered, gets no welcome discount, whatever billing email they give' );
		wp_set_current_user( 0 );
		WC()->customer->set_billing_email( '' );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $third );
		$again = (int) wp_insert_user( array( 'user_login' => "{$tag}-third2", 'user_email' => "{$tag}-third@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
		$s->track_user( $again );
		$s->on_finish( static function () use ( $third_cp ) { wp_delete_post( $third_cp->get_id(), true ); } );
		$check( 0 === (int) get_user_meta( $again, WelcomeDiscount::USER_META, true ), 'BR-001 an account deleted and made again with the same email gets no second welcome discount' );
		update_post_meta( $template->get_id(), 'exclude_product_brands', array( 991 ) );
		$branded    = t57_customer( $s, "{$tag}-brand" );
		$branded_cp = (int) get_user_meta( $branded, WelcomeDiscount::USER_META, true );
		$check( array( 991 ) === get_post_meta( $branded_cp, 'exclude_product_brands', true ), 'BR-001 the template\'s brand exclusions are copied to each customer\'s welcome discount' );
		delete_post_meta( $template->get_id(), 'exclude_product_brands' );
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
		// BR-004: entered codes measured in, then switching combining off leaves one, on carts, orders and the Store API.
		foreach ( array( $auto, $two ) as $quiet ) {
			$quiet->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$quiet->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$freeauto = t57_screen( $s, "{$tag}freeauto", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0', 'free_shipping' => 'yes', 'mizzey_automatic' => 'yes' ) );
		$freeauto->set_individual_use( false );
		$freeauto->save();
		$big_off = t57_screen( $s, "{$tag}twohundred", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '200' ) );
		AutomaticPromotions::forget();
		update_option( Combination::OPTION, 'yes' );
		t57_cart( array( $mid->get_id() => 1 ), array( $big_off->get_code() ) );
		$measured = t57_applied();
		$check( in_array( t57_code( $freeauto ), $measured, true ) && 0.0 === (float) WC()->cart->get_shipping_total(), 'BR-004 on: 200 off a 600 cart takes it under a 500 free shipping minimum, so the automatic free shipping is worth its rate and applies (' . implode( ',', $measured ) . ')' );
		$freeauto->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$freeauto->save();
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		AutomaticPromotions::forget();
		t57_cart( array( $cheap->get_id() => 2 ), array( $tenoff->get_code(), $five->get_code() ) );
		$both = t57_applied();
		delete_option( Combination::OPTION );
		WC()->cart->calculate_totals();
		wc_clear_notices();
		$left = t57_applied();
		$check( 2 === count( $both ) && array( t57_code( $five ) ) === $left, 'BR-004 two codes held while combining was on: switched off, the cart keeps one (' . implode( ',', $left ) . ')' );
		$admin_order = $s->order_for( $cheap, 2 );
		$admin_order->apply_coupon( $tenoff->get_code() );
		$second = wc_get_order( $admin_order->get_id() )->apply_coupon( $five->get_code() );
		$codes  = wc_get_order( $admin_order->get_id() )->get_coupon_codes();
		$check( is_wp_error( $second ) && array( t57_code( $tenoff ) ) === array_map( 'wc_format_coupon_code', $codes ), 'BR-004 off: a second code on an order is refused (' . ( is_wp_error( $second ) ? $second->get_error_message() : 'applied' ) . ')' );
		wp_set_current_user( 0 );
		$store   = rest_url( 'wc/store/v1/' );
		$first_r = wp_remote_get( $store . 'cart', array( 'timeout' => 30 ) ); // storefront-guard: exempt, the Store API answers JSON, not a storefront page.
		$jar     = array();
		foreach ( wp_remote_retrieve_cookies( $first_r ) as $cookie ) {
			$jar[ $cookie->name ] = $cookie;
		}
		$auth = array( 'Nonce' => (string) wp_remote_retrieve_header( $first_r, 'nonce' ), 'Cart-Token' => (string) wp_remote_retrieve_header( $first_r, 'cart-token' ), 'Content-Type' => 'application/json' );
		$post = static function ( string $path, array $body ) use ( $store, &$jar, $auth ) {
			$r = wp_remote_post( $store . $path, array( 'timeout' => 30, 'cookies' => array_values( $jar ), 'headers' => $auth, 'body' => wp_json_encode( $body ) ) ); // storefront-guard: exempt, the Store API answers JSON, not a storefront page.
			foreach ( wp_remote_retrieve_cookies( $r ) as $cookie ) {
				$jar[ $cookie->name ] = $cookie;
			}
			return $r;
		};
		$post( 'cart/add-item', array( 'id' => $cheap->get_id(), 'quantity' => 2 ) );
		$post( 'cart/apply-coupon', array( 'code' => $tenoff->get_code() ) );
		$replaced = $post( 'cart/apply-coupon', array( 'code' => $five->get_code() ) );
		$after    = array_map( 'wc_format_coupon_code', array_column( (array) ( json_decode( wp_remote_retrieve_body( $replaced ), true )['coupons'] ?? array() ), 'code' ) );
		$check( 200 === (int) wp_remote_retrieve_response_code( $replaced ) && array( t57_code( $five ) ) === $after, '[HTTP] BR-004 off: through the Store API a second code replaces the first, as on the classic cart (' . wp_remote_retrieve_response_code( $replaced ) . ': ' . implode( ',', $after ) . ')' );
		// BR-004, on: an order checks individual use both ways; candidates are measured after entered codes and after
		// the chosen discount.
		update_option( Combination::OPTION, 'yes' );
		$solo_order = $s->order_for( $cheap, 2 );
		$solo_order->apply_coupon( $tenoff->get_code() );
		$solo_added = wc_get_order( $solo_order->get_id() )->apply_coupon( $solo->get_code() );
		$check( is_wp_error( $solo_added ) && array( t57_code( $tenoff ) ) === array_map( 'wc_format_coupon_code', wc_get_order( $solo_order->get_id() )->get_coupon_codes() ), 'BR-004 on: an individual-use code cannot join a code already on an order (' . ( is_wp_error( $solo_added ) ? $solo_added->get_error_message() : 'applied' ) . ')' );
		$mask      = $s->simple_product( 't57 mask', '100', null );
		$entered_a = t57_screen( $s, "{$tag}enteredA", array( 'discount_type' => 'fixed_product', 'coupon_amount' => '100', 'product_ids' => array( $cheap->get_id() ) ) );
		$auto_a    = t57_screen( $s, "{$tag}autoA", array( 'discount_type' => 'fixed_product', 'coupon_amount' => '100', 'mizzey_automatic' => 'yes', 'product_ids' => array( $cheap->get_id() ) ) );
		$auto_b    = t57_screen( $s, "{$tag}autoB", array( 'discount_type' => 'fixed_product', 'coupon_amount' => '50', 'mizzey_automatic' => 'yes', 'product_ids' => array( $mask->get_id() ) ) );
		foreach ( array( $auto_a, $auto_b ) as $loose ) {
			$loose->set_individual_use( false );
			$loose->save();
		}
		AutomaticPromotions::forget();
		t57_cart( array( $cheap->get_id() => 1, $mask->get_id() => 1 ), array( $entered_a->get_code() ) );
		$after_entered = t57_applied();
		$check( in_array( t57_code( $auto_b ), $after_entered, true ) && ! in_array( t57_code( $auto_a ), $after_entered, true ) && 150.0 === (float) WC()->cart->get_discount_total(), 'BR-004 on: with a code already taking the lip balm to nothing, 50 off the mask applies rather than a second 100 off the lip balm (' . implode( ',', $after_entered ) . ')' );
		$auto_b->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto_b->save();
		AutomaticPromotions::forget();
		t57_cart( array( $cheap->get_id() => 1, $mask->get_id() => 1 ), array( $entered_a->get_code() ) );
		$check( ! in_array( t57_code( $auto_a ), t57_applied(), true ), 'BR-004 on: an automatic promotion that would add nothing to what the entered code already takes off is not applied (' . implode( ',', t57_applied() ) . ')' );
		foreach ( array( $auto_a, $auto_b ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$auto200 = t57_screen( $s, "{$tag}auto200", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '200', 'mizzey_automatic' => 'yes' ) );
		$auto200->set_individual_use( false );
		$auto200->save();
		$freeauto->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$freeauto->save();
		AutomaticPromotions::forget();
		t57_cart( array( $mid->get_id() => 1 ) );
		$paired = t57_applied();
		$check( in_array( t57_code( $auto200 ), $paired, true ) && in_array( t57_code( $freeauto ), $paired, true ) && 0.0 === (float) WC()->cart->get_shipping_total(), 'BR-004 on: the automatic 200 off takes 600 under the 500 free shipping minimum, so the automatic free shipping joins it and the cart ships free (' . implode( ',', $paired ) . ')' );
		foreach ( array( $auto200, $freeauto ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		delete_option( Combination::OPTION );
		AutomaticPromotions::forget();
		// BR-004, on: WooCommerce's coupon order with sequential discounts; individual-use free shipping valued alone;
		// a cart whose code becomes individual-use keeps one; one claim per email.
		update_option( Combination::OPTION, 'yes' );
		$sequential = get_option( 'woocommerce_calc_discounts_sequentially', 'no' );
		$s->on_finish( static function () use ( $sequential ) { update_option( 'woocommerce_calc_discounts_sequentially', $sequential ); } );
		update_option( 'woocommerce_calc_discounts_sequentially', 'yes' );
		$two_hundred = $s->simple_product( 't57 duo', '200', null );
		$cart_off    = t57_screen( $s, "{$tag}cartoff", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '100' ) );
		$half        = t57_screen( $s, "{$tag}half", array( 'discount_type' => 'percent', 'coupon_amount' => '50', 'mizzey_automatic' => 'yes' ) );
		$sixty       = t57_screen( $s, "{$tag}sixty", array( 'discount_type' => 'fixed_product', 'coupon_amount' => '60', 'mizzey_automatic' => 'yes', 'product_ids' => array( $two_hundred->get_id() ) ) );
		foreach ( array( $half, $sixty ) as $loose ) {
			$loose->set_individual_use( false );
			$loose->save();
		}
		AutomaticPromotions::forget();
		t57_cart( array( $two_hundred->get_id() => 1 ), array( $cart_off->get_code() ) );
		$ordered = t57_applied();
		$check( in_array( t57_code( $half ), $ordered, true ) && ! in_array( t57_code( $sixty ), $ordered, true ), 'BR-004 on, sequential: WooCommerce takes 50 percent before a fixed cart 100, so the 50 percent (100 more off) beats a fixed 60 (' . implode( ',', $ordered ) . ')' );
		foreach ( array( $half, $sixty ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( 'woocommerce_calc_discounts_sequentially', $sequential );
		$five_fifty = $s->simple_product( 't57 set', '550', null );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$sixty_off = t57_screen( $s, "{$tag}sixtyoff", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '60', 'mizzey_automatic' => 'yes' ) );
		$sixty_off->set_individual_use( false );
		$sixty_off->save();
		$lone_ship = t57_screen( $s, "{$tag}loneship", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0', 'free_shipping' => 'yes', 'mizzey_automatic' => 'yes', 'individual_use' => 'yes' ) );
		AutomaticPromotions::forget();
		t57_cart( array( $five_fifty->get_id() => 1 ) );
		$alone = t57_applied();
		$check( array( t57_code( $sixty_off ) ) === $alone, 'BR-004 on: an individual-use free shipping is valued alone (worth nothing on a cart that already ships free), so the 60 off applies rather than it (' . implode( ',', $alone ) . ')' );
		foreach ( array( $sixty_off, $lone_ship ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		AutomaticPromotions::forget();
		$tenoff->set_individual_use( false );
		$tenoff->save();
		$five->set_individual_use( false );
		$five->save();
		t57_cart( array( $cheap->get_id() => 2 ), array( $tenoff->get_code(), $five->get_code() ) );
		$pair = t57_applied();
		$tenoff->set_individual_use( true );
		$tenoff->save();
		WC()->cart->calculate_totals();
		wc_clear_notices();
		$check( 2 === count( $pair ) && array( t57_code( $five ) ) === t57_applied(), 'BR-004 on: two codes held, then one is made individual-use: the cart keeps one (' . implode( ',', t57_applied() ) . ')' );
		$tenoff->set_individual_use( false );
		$tenoff->save();
		// BR-004, on, round four: an individual-use automatic is no candidate beside an entered code; an entered free
		// shipping code keeps shipping free in both estimates; the minimum is read from the simulation, so the choice
		// holds from one calculation to the next.
		$five_ten = $s->simple_product( 't57 kit', '510', null );
		$entered  = t57_screen( $s, "{$tag}entered", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '5' ) );
		$entered->set_individual_use( false );
		$entered->save();
		$big   = t57_screen( $s, "{$tag}big", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '100', 'mizzey_automatic' => 'yes', 'individual_use' => 'yes' ) );
		$joins = t57_screen( $s, "{$tag}joins", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '80', 'mizzey_automatic' => 'yes' ) );
		$joins->set_individual_use( false );
		$joins->save();
		AutomaticPromotions::forget();
		t57_cart( array( $five_ten->get_id() => 1 ), array( $entered->get_code() ) );
		$compatible = t57_applied();
		$check( in_array( t57_code( $joins ), $compatible, true ) && ! in_array( t57_code( $big ), $compatible, true ), 'BR-004 on: beside an entered code, an individual-use 100 off is no candidate, so the joinable 80 off applies (' . implode( ',', $compatible ) . ')' );
		foreach ( array( $big, $joins ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$ship_code = t57_screen( $s, "{$tag}shipcode", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0', 'free_shipping' => 'yes' ) );
		$ship_code->set_individual_use( false );
		$ship_code->save();
		$twenty_off = t57_screen( $s, "{$tag}twentyoff", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '20', 'mizzey_automatic' => 'yes' ) );
		$twenty_off->set_individual_use( false );
		$twenty_off->save();
		AutomaticPromotions::forget();
		t57_cart( array( $five_ten->get_id() => 1 ), array( $ship_code->get_code() ) );
		$kept = t57_applied();
		$check( in_array( t57_code( $twenty_off ), $kept, true ) && 0.0 === (float) WC()->cart->get_shipping_total(), 'BR-004 on: an entered free shipping code keeps shipping free, so 20 off taking 510 under the 500 minimum still saves 20 and applies (' . implode( ',', $kept ) . ')' );
		$twenty_off->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$twenty_off->save();
		update_option( 'woocommerce_calc_discounts_sequentially', 'yes' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '325', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$six_hundred = $s->simple_product( 't57 hamper', '600', null );
		$halfcode    = t57_screen( $s, "{$tag}halfcode", array( 'discount_type' => 'percent', 'coupon_amount' => '50' ) );
		$halfcode->set_individual_use( false );
		$halfcode->save();
		$hundred = t57_screen( $s, "{$tag}hundred", array( 'discount_type' => 'fixed_product', 'coupon_amount' => '100', 'mizzey_automatic' => 'yes', 'product_ids' => array( $six_hundred->get_id() ) ) );
		$hundred->set_individual_use( false );
		$hundred->save();
		AutomaticPromotions::forget();
		t57_cart( array( $six_hundred->get_id() => 1 ), array( $halfcode->get_code() ) );
		$steady = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$steady[] = in_array( t57_code( $hundred ), t57_applied(), true ) ? 'on' : 'off';
			WC()->cart->calculate_totals();
		}
		$check( array( 'on', 'on', 'on' ) === $steady, 'BR-004 on, sequential: the 100 off beside an entered 50 percent stays applied from one calculation to the next (' . implode( ',', $steady ) . ')' );
		$hundred->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$hundred->save();
		update_option( 'woocommerce_calc_discounts_sequentially', $sequential );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		AutomaticPromotions::forget();
		// BR-004, on, round five: a discount that loses alone can win with free shipping beside it; the paid saving
		// does not credit a candidate with the rounding of the entered codes' tax.
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$ninety     = t57_screen( $s, "{$tag}ninety", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '90', 'mizzey_automatic' => 'yes' ) );
		$one_thirty = t57_screen( $s, "{$tag}onethirty", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '130', 'mizzey_automatic' => 'yes' ) );
		$ship_auto  = t57_screen( $s, "{$tag}shipauto", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0', 'free_shipping' => 'yes', 'mizzey_automatic' => 'yes' ) );
		foreach ( array( $ninety, $one_thirty, $ship_auto ) as $loose ) {
			$loose->set_individual_use( false );
			$loose->save();
		}
		AutomaticPromotions::forget();
		t57_cart( array( $six_hundred->get_id() => 1 ) );
		$paired = t57_applied();
		$expected = array( t57_code( $one_thirty ), t57_code( $ship_auto ) );
		sort( $expected );
		$check( $expected === $paired, 'BR-004 on: 130 off with free shipping (saves 130) beats 90 off alone (saves 90), though 130 off alone loses the 500 minimum and saves only 80 (' . implode( ',', $paired ) . ')' );
		foreach ( array( $ninety, $one_thirty, $ship_auto ) as $done ) {
			$done->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
			$done->save();
		}
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$tax_options = array( 'woocommerce_calc_taxes', 'woocommerce_prices_include_tax', 'woocommerce_tax_round_at_subtotal' );
		$tax_before  = array_combine( $tax_options, array_map( 'get_option', $tax_options ) );
		$s->on_finish( static function () use ( $tax_before ) { foreach ( $tax_before as $name => $value ) { update_option( $name, $value ); } } );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		$rate_id = \WC_Tax::_insert_tax_rate( array( 'tax_rate_country' => '', 'tax_rate' => '20.0000', 'tax_rate_name' => 't57 VAT', 'tax_rate_priority' => 1, 'tax_rate_shipping' => 0, 'tax_rate_class' => '' ) );
		$s->on_finish( static function () use ( $rate_id ) { \WC_Tax::_delete_tax_rate( $rate_id ); } );
		$one_pound = $s->simple_product( 't57 sachet', '1', null );
		$three_ent = t57_screen( $s, "{$tag}threeent", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0.03' ) );
		$three_ent->set_individual_use( false );
		$three_ent->save();
		$three_auto = t57_screen( $s, "{$tag}threeauto", array( 'discount_type' => 'fixed_cart', 'coupon_amount' => '0.03', 'mizzey_automatic' => 'yes' ) );
		$three_auto->set_individual_use( false );
		$three_auto->save();
		t57_cart( array( $one_pound->get_id() => 1 ), array( $three_ent->get_code() ) );
		$marginal = AutomaticPromotions::value( WC()->cart, $three_auto, array( t57_code( $three_ent ) ) );
		$check( 3 === $marginal, "BR-004 on, tax per line: a 1.00 line at 20 percent with .03 entered, .03 more takes the tax from .19 to .19, so it saves 3 cents, not 4 ({$marginal})" );
		$three_auto->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$three_auto->save();
		foreach ( $tax_before as $name => $value ) {
			update_option( $name, $value );
		}
		AutomaticPromotions::forget();
		delete_option( Combination::OPTION );
		$claim_email = "{$tag}-claim@example.invalid";
		$s->on_finish( static function () use ( $claim_email ) { delete_option( WelcomeDiscount::ISSUED . '_' . hash( 'sha256', wc_strtolower( $claim_email ) ) ); } );
		$check( WelcomeDiscount::claim( $claim_email ) && ! WelcomeDiscount::claim( strtoupper( $claim_email ) ), 'BR-001 an email is claimed once, whatever its case: the second claim fails' );
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
		// BR-006, per item: a discounted two-line order, each line 80 after 20 percent off.
		$second_item = $s->simple_product( 't57 toner', '100', null );
		$twenty      = t57_screen( $s, "{$tag}twenty", array( 'discount_type' => 'percent', 'coupon_amount' => '20' ) );
		$lines_order = wc_create_order();
		$lines_order->add_product( $cheap, 1 );
		$lines_order->add_product( $second_item, 1 );
		$lines_order->apply_coupon( $twenty->get_code() );
		$lines_order->calculate_totals();
		$lines_order->set_status( 'processing' );
		$lines_order->save();
		$s->track_order( $lines_order->get_id() );
		$first_line = (int) array_key_first( $lines_order->get_items() );
		$line_total = (float) $lines_order->get_item( $first_line )->get_total();
		$above = wc_create_refund( array( 'order_id' => $lines_order->get_id(), 'amount' => '90', 'line_items' => array( $first_line => array( 'qty' => 1, 'refund_total' => 90 ) ) ) );
		$exact = wc_create_refund( array( 'order_id' => $lines_order->get_id(), 'amount' => '80', 'line_items' => array( $first_line => array( 'qty' => 1, 'refund_total' => 80 ) ) ) );
		$extra = wc_create_refund( array( 'order_id' => $lines_order->get_id(), 'amount' => '1', 'line_items' => array( $first_line => array( 'qty' => 0, 'refund_total' => 1 ) ) ) );
		$check( 80.0 === $line_total && is_wp_error( $above ) && $exact instanceof \WC_Order_Refund && is_wp_error( $extra ), "BR-006 a line paid 80 after the discount cannot get 90 back though the order total would allow it; 80 is refunded; then 1 more for that line is refused (" . ( is_wp_error( $above ) ? $above->get_error_message() : 'refunded' ) . ')' );

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
		wp_cache_flush(); // The offer ran in the web server's process; read the order as it is stored.
		$linked = (int) wc_get_order( $guest_order->get_id() )->get_customer_id();
		$check( 0 === $before_link && $account && $account->ID === $linked, '[HTTP] BR-008 a guest order, then the account made from the order-received page, has the order linked to it (status ' . ( $offer['status'] ?? '?' ) . ', account ' . ( $account ? $account->ID : 'none' ) . ', linked ' . $linked . ')' );

		return $ok;
	}
);

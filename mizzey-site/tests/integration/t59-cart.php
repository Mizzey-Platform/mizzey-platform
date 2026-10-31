<?php
/**
 * t59, the cart (specs/018-cart, #306): stock re-validated through the adapter and failing closed, the stand-in stock
 * fixture, the saved and guest carts merged with quantities combined and capped, the merge marked for every sign-in,
 * a guest cart kept for the set window, the free shipping progress data, suggestions from the related-products
 * assignment, moving a line to the wishlist, and WooCommerce's own cart rows. In-process, and over HTTP for the Store
 * API cart, the guest cart through sign-in and registration, and the guest session's lifetime. Every product, user,
 * coupon and zone it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Cart\CartMerge;
use MizzeySite\Cart\CartStock;
use MizzeySite\Cart\FreeShippingProgress;
use MizzeySite\Cart\GuestCartRetention;
use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\PromotionSettings;
use MizzeySite\Security\SignupCheck;
use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockSourceRefused;
use MizzeySite\Wishlist\Wishlist;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A stock source answering from a fixed table, or refusing an item it does not know. */
final class T59Source implements StockSource {
	/** @param array<int,int> $levels */
	public function __construct( public array $levels ) {}
	public function name(): string { return 't59'; }
	public function level( StockItem $item ): int {
		if ( ! array_key_exists( $item->id, $this->levels ) ) {
			throw new StockSourceRefused( 'unknown' );
		}
		return $this->levels[ $item->id ];
	}
	public function adjust( StockItem $item, int $delta, string $operationKey ): int { throw new StockSourceRefused( 'read only' ); }
}

/** @param array<int,int> $lines product id => quantity */
function t59_cart( array $lines ): \WC_Cart {
	$cart = WC()->cart;
	$cart->empty_cart( false );
	foreach ( $lines as $id => $qty ) {
		$cart->add_to_cart( $id, $qty );
	}
	wc_clear_notices();
	$cart->calculate_totals();
	return $cart;
}

/** A cart line as WooCommerce keeps it in a session or a saved cart. */
function t59_line( \WC_Product $product, int $qty ): array {
	$key = WC()->cart->generate_cart_id( $product->get_id() );
	return array( $key => array( 'key' => $key, 'product_id' => $product->get_id(), 'variation_id' => 0, 'variation' => array(), 'quantity' => $qty, 'data_hash' => wc_get_cart_item_data_hash( $product ) ) );
}

/** The quantities of a saved or session cart, by product id. */
function t59_quantities( array $cart ): array {
	$out = array();
	foreach ( $cart as $line ) {
		$out[ (int) $line['product_id'] ] = ( $out[ (int) $line['product_id'] ] ?? 0 ) + (int) $line['quantity'];
	}
	ksort( $out );
	return $out;
}

/**
 * The Store API cart's nonce for writes, and its cookies. The cart is kept in WooCommerce's session cookie, as a
 * browser's is, not under a Cart-Token header, so the account page sees the same cart.
 */
function t59_store( array $cookies = array() ): array {
	$cart = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $cookies );
	return array( 'auth' => array( 'Nonce' => sign_in_header( $cart, 'nonce' ) ), 'cookies' => $cart['cookies'], 'cart' => $cart );
}

run(
	new Scenario( 't59-cart', 'CART-01 to CART-18: the cart' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		wp_set_current_user( 0 );
		sign_in_options(
			$s,
			array(
				FixtureStockSource::OPTION                   => 'no',
				GuestCartRetention::OPTION                   => '30',
				'woocommerce_enable_myaccount_registration'  => 'yes',
				'woocommerce_registration_generate_username' => 'yes',
				'woocommerce_registration_generate_password' => 'no',
				'woocommerce_shipping_hide_rates_when_free'  => 'yes',
			)
		);
		$tag   = 't59' . wp_rand( 1000, 9999 );
		$five  = $s->simple_product( 't59 cream', '300', null );
		$five->set_manage_stock( true );
		$five->set_stock_quantity( 5 );
		$five->save();
		$open  = $s->simple_product( 't59 soap', '100', null );
		$one   = $s->simple_product( 't59 kit', '200', null );
		$one->set_sold_individually( true );
		$one->save();
		$mails = array();
		$trap  = static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_wp_mail', $trap, 10 ); } );

		// CART-10: every stock question through the adapter, failing closed.
		$cart = t59_cart( array( $five->get_id() => 2, $open->get_id() => 1 ) );
		$none = CartStock::problems( $cart, StockGateway::make() );
		$check( 2 === count( $none ) && false !== strpos( $none[0], 'could not confirm' ), 'CART-10 with no stock source bound, every line is one the cart cannot confirm, so checkout is refused (' . count( $none ) . ')' );
		$short = CartStock::problems( $cart, new StockGateway( new T59Source( array( $five->get_id() => 1, $open->get_id() => 0 ) ), 1, null, 0 ) );
		$check( 2 === count( $short ) && false !== strpos( $short[0], 'Only 1 of t59 cream' ) && false !== strpos( $short[1], 't59 soap is out of stock' ), 'CART-10 the source holding 1 of 2, and 0, says how many are left, and that one is out of stock (' . implode( ' | ', $short ) . ')' );
		$enough = CartStock::problems( $cart, new StockGateway( new T59Source( array( $five->get_id() => 2, $open->get_id() => 1 ) ), 1, null, 0 ) );
		$check( array() === $enough, 'CART-10 a source holding what the cart asks for raises nothing' );
		CartStock::check();
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();
		$check( 2 === count( $notices ), 'CART-10 the cart check (woocommerce_check_cart_items) adds the problems as errors, which block checkout' );

		// The stand-in fixture: off by default, read-only, from the record's stock fields.
		$unbound = StockGateway::make()->level( $five->get_id() );
		update_option( FixtureStockSource::OPTION, 'yes' );
		$fixture = StockGateway::make();
		$levels  = array( $fixture->level( $five->get_id() )->quantity, $fixture->level( $open->get_id() )->quantity );
		$s->on_finish( static function () { delete_option( FixtureStockSource::LEDGER ); } );
		$changed = $fixture->adjust( $five->get_id(), -2, "{$tag}-op" );
		$again   = $fixture->adjust( $five->get_id(), -2, "{$tag}-op" );
		$over    = $fixture->adjust( $five->get_id(), -9, "{$tag}-op2" );
		$back    = $fixture->adjust( $five->get_id(), 2, "{$tag}-op3" );
		$check( ! $unbound->isKnown() && FixtureStockSource::enabled() && array( 5, FixtureStockSource::UNCOUNTED ) === $levels && 3 === $changed->quantity && 3 === $again->quantity && ! $over->isKnown() && 5 === $back->quantity && 5 === (int) wc_get_product( $five->get_id() )->get_stock_quantity(), 'Fixture: unbound until switched on outside production; it answers 5 for a counted item and the uncounted level for the rest; a change applies once per key, one below zero is refused, and the WooCommerce stock field is never written' );
		delete_option( FixtureStockSource::LEDGER );
		$refuse_write = static fn ( $new, $old ) => $old;
		add_filter( 'pre_update_option_' . FixtureStockSource::LEDGER, $refuse_write, 10, 2 );
		$unwritten = $fixture->adjust( $five->get_id(), -1, "{$tag}-lost" );
		remove_filter( 'pre_update_option_' . FixtureStockSource::LEDGER, $refuse_write, 10 );
		$check( ! $unwritten->isKnown() && 5 === $fixture->level( $five->get_id() )->quantity, 'Fixture: a change it could not store is not reported as made: the answer is unknown, and the level is unchanged' );
		$order_for = wc_create_order();
		$order_for->add_product( $five, 2 );
		$order_for->save();
		$s->track_order( $order_for->get_id() );
		$order_for->update_status( 'processing' );
		wc_reduce_stock_levels( $order_for->get_id() );
		$while_processing = (int) wc_get_product( $five->get_id() )->get_stock_quantity();
		$order_for->update_status( 'cancelled' );
		$check( 5 === $while_processing && 5 === (int) wc_get_product( $five->get_id() )->get_stock_quantity() && 5 === StockGateway::make()->level( $five->get_id() )->quantity, 'D-19 an order processed and cancelled changes neither WooCommerce\'s own stock nor what the fixture answers: WooCommerce\'s order stock writers are off' );
		$bound_wins = static fn () => new T59Source( array() );
		add_filter( StockGateway::SOURCE_FILTER, $bound_wins );
		$real = StockGateway::make()->level( $five->get_id() );
		remove_filter( StockGateway::SOURCE_FILTER, $bound_wins );
		$check( ! $real->isKnown(), 'Fixture: an adapter bound by the filter wins over it' );

		// CART-18: the saved cart and the guest cart, combined and capped.
		$gateway = new StockGateway( new T59Source( array( $five->get_id() => 5, $open->get_id() => 50, $one->get_id() => 50 ) ), 1, null, 0 );
		$merged  = CartMerge::combine( t59_line( $five, 2 ) + t59_line( $open, 1 ), t59_line( $five, 4 ) + t59_line( $one, 2 ), $gateway );
		$check( array( $five->get_id() => 5, $open->get_id() => 1, $one->get_id() => 1 ) === t59_quantities( $merged['cart'] ) && in_array( 't59 cream', $merged['lowered'], true ), 'CART-18 the same line\'s quantities are added (2 + 4) and capped at the 5 in stock; a saved-only line stays; a guest-only line sold one at a time comes in as 1; the customer is told (' . wp_json_encode( t59_quantities( $merged['cart'] ) ) . ')' );
		$gone = CartMerge::combine( t59_line( $five, 1 ), array(), new StockGateway( new T59Source( array( $five->get_id() => 0 ) ), 1, null, 0 ) );
		$kept = CartMerge::combine( t59_line( $five, 3 ), array(), new StockGateway( new T59Source( array() ), 1, null, 0 ) );
		$two_local = $s->simple_product( 't59 oil', '80', null );
		$two_local->set_manage_stock( true );
		$two_local->set_stock_quantity( 2 );
		$two_local->save();
		$ten     = new StockGateway( new T59Source( array( $two_local->get_id() => 10, $five->get_id() => 5 ) ), 1, null, 0 );
		$adapter = CartMerge::combine( t59_line( $two_local, 3 ), t59_line( $two_local, 3 ), $ten );
		$other   = t59_line( $five, 4 );
		$other_k = (string) array_key_first( $other );
		$shared  = CartMerge::combine( t59_line( $five, 4 ), array( 'x' . substr( $other_k, 1 ) => array_merge( $other[ $other_k ], array( 'key' => 'x' . substr( $other_k, 1 ) ) ) ), $ten );
		$one_line  = t59_line( $one, 1 );
		$one_key   = (string) array_key_first( $one_line );
		$one_other = array( 'y' . substr( $one_key, 1 ) => array_merge( $one_line[ $one_key ], array( 'key' => 'y' . substr( $one_key, 1 ) ) ) );
		$singles   = CartMerge::combine( $one_line, $one_other, $gateway );
		$check( array( $one->get_id() => 1 ) === t59_quantities( $singles['cart'] ), 'CART-18 an item sold one at a time is one in the merged cart, across all its lines (' . wp_json_encode( t59_quantities( $singles['cart'] ) ) . ')' );
		$check( array( $two_local->get_id() => 6 ) === t59_quantities( $adapter['cart'] ) && array( $five->get_id() => 5 ) === t59_quantities( $shared['cart'] ), 'CART-18, D-19 the merge is capped by the stock the source confirms, not WooCommerce\'s own field (6 kept with 2 there and 10 confirmed); two lines of one stock item share its 5 (' . wp_json_encode( t59_quantities( $shared['cart'] ) ) . ')' );
		$check( array() === $gone['cart'] && array( $five->get_id() => 3 ) === t59_quantities( $kept['cart'] ), 'CART-18 an item the source says is sold out leaves the merged cart; an unanswered question caps nothing (the cart check then refuses it)' );

		// CART-13, CART-14, CART-15: every sign-in marks the merge, whatever set the cookie.
		$password = 'Pw-' . wp_generate_password( 18, false );
		$customer = (int) wc_create_new_customer( "{$tag}-c@example.invalid", '', $password );
		$s->track_user( $customer );
		delete_user_meta( $customer, CartMerge::FLAG );
		do_action( 'set_logged_in_cookie', '', 0, 0, $customer, 'logged_in', '' );
		$check( (bool) get_user_meta( $customer, CartMerge::FLAG, true ), 'CART-15 a sign-in by any route (here the logged-in cookie being set, as a sign-in link or a social sign-in does) marks the saved cart to merge' );

		// [HTTP] CART-14, CART-09: a guest cart, then signing in on the account page with a saved cart.
		update_user_meta( $customer, '_woocommerce_persistent_cart_' . get_current_blog_id(), array( 'cart' => t59_line( $open, 1 ) ) );
		update_option( FixtureStockSource::OPTION, 'yes' );
		$guest = t59_store();
		$first = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 2 ), $guest['auth'], $guest['cookies'] );
		$add   = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 2 ), $guest['auth'], $first['cookies'] );
		$account = wc_get_page_permalink( 'myaccount' );
		$form    = two_factor_http( 'GET', $account, array(), $add['cookies'] );
		$signed  = two_factor_http( 'POST', $account, array( 'username' => "{$tag}-c@example.invalid", 'password' => $password, 'woocommerce-login-nonce' => two_factor_field( $form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'] );
		two_factor_http( 'GET', $account, array(), $signed['cookies'] );
		wp_cache_flush();
		$saved = get_user_meta( $customer, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
		$check( sign_in_has_session( $signed ) && array( $five->get_id() => 2, $open->get_id() => 3 ) === t59_quantities( (array) ( $saved['cart'] ?? array() ) ), '[HTTP] CART-14, CART-09 a guest with 2 creams and 2 soaps signs in to an account whose saved cart holds 1 soap: the account\'s cart holds 2 creams and 3 soaps, no line lost or doubled (' . wp_json_encode( t59_quantities( (array) ( $saved['cart'] ?? array() ) ) ) . ')' );
		$elsewhere = sign_in_account( 'en', "{$tag}-c@example.invalid", $password );
		two_factor_http( 'GET', $account, array(), $elsewhere['cookies'] );
		$again_form = two_factor_http( 'GET', $account, array(), array() );
		$again      = two_factor_http( 'POST', $account, array( 'username' => "{$tag}-c@example.invalid", 'password' => $password, 'woocommerce-login-nonce' => two_factor_field( $again_form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $again_form['cookies'] );
		two_factor_http( 'GET', $account, array(), $again['cookies'] );
		wp_cache_flush();
		$after = get_user_meta( $customer, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
		$check( sign_in_has_session( $elsewhere ) && array( $five->get_id() => 2, $open->get_id() => 3 ) === t59_quantities( (array) ( $after['cart'] ?? array() ) ), '[HTTP] CART-09, CART-18 signing in again from other browsers, with no guest cart, restores the account\'s cart and doubles nothing (' . wp_json_encode( t59_quantities( (array) ( $after['cart'] ?? array() ) ) ) . ')' );
		sign_in_forget( array( "{$tag}-c@example.invalid" ) );

		// [HTTP] CART-13: a guest cart, then registering on the account page.
		$joiner = t59_store();
		$added  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 1 ), $joiner['auth'], $joiner['cookies'] );
		$page   = two_factor_http( 'GET', $account, array(), $added['cookies'] );
		$email  = "{$tag}-new@example.invalid";
		$made   = two_factor_http( 'POST', $account, array( 'email' => $email, 'password' => 'Pw-' . wp_generate_password( 18, false ), 'woocommerce-register-nonce' => two_factor_field( $page['body'], 'woocommerce-register-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'register' => 'Register', 'mizzey_signup_token' => SignupCheck::issue( 'account', time() - 10 ), 'mizzey_signup_extra' => '' ), $page['cookies'] );
		two_factor_http( 'GET', $account, array(), $made['cookies'] );
		wp_cache_flush();
		$new_user = get_user_by( 'email', $email );
		if ( $new_user ) {
			$s->track_user( $new_user->ID );
		}
		$new_cart = $new_user ? get_user_meta( $new_user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), true ) : array();
		$check( $new_user && array( $five->get_id() => 1 ) === t59_quantities( (array) ( $new_cart['cart'] ?? array() ) ), '[HTTP] CART-13 a guest who fills a cart and then registers keeps every item' );

		// [HTTP] CART-16: the guest session, and so the cart, lasts the set window.
		$fresh  = t59_store();
		$put    = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 1 ), $fresh['auth'], $fresh['cookies'] );
		$expiry = 0;
		foreach ( $put['cookies'] as $cookie ) {
			if ( str_starts_with( (string) $cookie->name, 'wp_woocommerce_session_' ) && null !== $cookie->expires ) {
				$expiry = (int) $cookie->expires;
			}
		}
		$days  = $expiry > 0 ? (int) round( ( $expiry - time() ) / DAY_IN_SECONDS ) : 0;
		update_option( GuestCartRetention::OPTION, '7' );
		$seven = GuestCartRetention::days();
		update_option( GuestCartRetention::OPTION, 'nonsense' );
		$fallback = GuestCartRetention::days();
		update_option( GuestCartRetention::OPTION, '30' );
		$settings = array_column( apply_filters( 'woocommerce_general_settings', array( array( 'id' => 'woocommerce_calc_discounts_sequentially' ) ) ), 'id' );
		wp_set_current_user( $customer );
		$signed_in = (int) apply_filters( 'wc_session_expiration', WEEK_IN_SECONDS );
		wp_set_current_user( 0 );
		$check( 30 === $days && 7 === $seven && GuestCartRetention::DEFAULT === $fallback && in_array( GuestCartRetention::OPTION, $settings, true ) && WEEK_IN_SECONDS === $signed_in, "[HTTP] CART-16 a guest's session cookie lasts the 30 days set ({$days}); the setting is in WooCommerce's general settings, a bad value falls back to 30, and a signed-in customer's session is unchanged" );

		// CART-07: what is still needed for free shipping.
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
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->customer->set_shipping_country( 'EG' );
		WC()->customer->set_billing_country( 'EG' );
		$two = new \WC_Coupon();
		$two->set_code( "{$tag}two" );
		$two->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '0', 'free_shipping' => true ) );
		$two->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$two->update_meta_data( PromotionSettings::MIN_ITEMS, '2' );
		$two->save();
		$s->on_finish( static function () use ( $two ) { wp_delete_post( $two->get_id(), true ); AutomaticPromotions::forget(); } );
		AutomaticPromotions::forget();
		$single   = FreeShippingProgress::of( t59_cart( array( $open->get_id() => 1 ) ) );
		$entered  = new \WC_Coupon();
		$entered->set_code( "{$tag}entered" );
		$entered->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '5', 'individual_use' => true ) );
		$entered->save();
		$combine_before = get_option( \MizzeySite\Promotions\Combination::OPTION );
		update_option( \MizzeySite\Promotions\Combination::OPTION, 'yes' );
		$s->on_finish( static function () use ( $entered ) { wp_delete_post( $entered->get_id(), true ); } );
		$with_code = t59_cart( array( $open->get_id() => 1 ) );
		$with_code->apply_coupon( $entered->get_code() );
		$with_code->calculate_totals();
		wc_clear_notices();
		$beside = FreeShippingProgress::of( $with_code );
		$with_code->remove_coupons();
		update_option( \MizzeySite\Promotions\Combination::OPTION, $combine_before );
		$twenty_pc = new \WC_Coupon();
		$twenty_pc->set_code( "{$tag}twentypc" );
		$twenty_pc->set_props( array( 'discount_type' => 'percent', 'amount' => '20' ) );
		$twenty_pc->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		// Not yet valid for a one-unit cart: it competes at the two-unit target, not now.
		$twenty_pc->update_meta_data( PromotionSettings::MIN_ITEMS, '2' );
		$twenty_pc->save();
		$s->on_finish( static function () use ( $twenty_pc ) { wp_delete_post( $twenty_pc->get_id(), true ); AutomaticPromotions::forget(); } );
		AutomaticPromotions::forget();
		$competing = FreeShippingProgress::of( t59_cart( array( $open->get_id() => 1 ) ) );
		$twenty_pc->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$twenty_pc->save();
		AutomaticPromotions::forget();
		$check( null === $competing['items_needed'], 'CART-07, BR-004 with promotions not combining and another automatic promotion that competes at the two-unit target, no item target is promised for the free-shipping one (' . wp_json_encode( $competing ) . ')' );
		$pair     = FreeShippingProgress::of( t59_cart( array( $open->get_id() => 2 ) ) );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'min_amount', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$no_coupon = FreeShippingProgress::of( t59_cart( array( $open->get_id() => 1 ) ) );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'either', 'min_amount' => '500', 'ignore_discounts' => 'no' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$check( null === $no_coupon['items_needed'] && '400.00' === $no_coupon['amount_needed'], 'CART-07 where no free shipping method takes a promotion, no item target is promised; only the amount (' . wp_json_encode( $no_coupon ) . ')' );
		$two->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$two->save();
		AutomaticPromotions::forget();
		$pickup = $zone->add_shipping_method( 'flat_rate' );
		update_option( "woocommerce_flat_rate_{$pickup}_settings", array( 'enabled' => 'yes', 'title' => 'Pickup', 'cost' => '0', 'tax_status' => 'none' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		wp_cache_flush();
		WC()->session->set( 'shipping_for_package_0', null );
		t59_cart( array( $open->get_id() => 1 ) );
		WC()->session->set( 'chosen_shipping_methods', array( "flat_rate:{$flat}" ) );
		WC()->cart->calculate_totals();
		$paid_chosen = FreeShippingProgress::of( WC()->cart );
		WC()->session->set( 'chosen_shipping_methods', array( "flat_rate:{$pickup}" ) );
		WC()->cart->calculate_totals();
		$free_chosen = FreeShippingProgress::of( WC()->cart );
		$chosen_seen = array( WC()->session->get( 'chosen_shipping_methods' ), array_keys( (array) ( WC()->shipping()->get_packages()[0]['rates'] ?? array() ) ) );
		$zone->delete_shipping_method( $pickup );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->session->set( 'chosen_shipping_methods', array() );
		$check( false === $paid_chosen['free'] && true === $free_chosen['free'], 'CART-07 a free pickup on offer does not make shipping free while the paid delivery is the one chosen (' . wp_json_encode( array( $paid_chosen['free'], $free_chosen['free'], $chosen_seen ) ) . ')' );
		$by_value = FreeShippingProgress::of( t59_cart( array( $five->get_id() => 1 ) ) );
		$over     = FreeShippingProgress::of( t59_cart( array( $five->get_id() => 2 ) ) );
		$check( null === $beside['items_needed'], 'CART-07, BR-004 an individual-use code the customer entered keeps the automatic free shipping out, even with promotions combining, so no item target is promised (' . wp_json_encode( $beside ) . ')' );
		$check( false === $single['free'] && 1 === $single['items_needed'] && '400.00' === $single['amount_needed'] && true === $pair['free'], 'CART-07 one unit needs one more item for the two-unit promotion (or 400 more for the 500 minimum); two units ship free (' . wp_json_encode( $single ) . ')' );
		$check( null === $by_value['items_needed'] && '200.00' === $by_value['amount_needed'] && true === $over['free'], 'CART-07 with no item promotion, a 300 cart needs 200 more for the minimum; a 600 cart ships free (' . wp_json_encode( $by_value ) . ')' );
		$api = t59_store();
		$api_add = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 1 ), $api['auth'], $api['cookies'] );
		$progress = $api_add['data']['extensions']['mizzey']['free_shipping'] ?? null;
		$check( is_array( $progress ) && array_key_exists( 'items_needed', $progress ) && array_key_exists( 'amount_needed', $progress ) && array_key_exists( 'free', $progress ), '[HTTP] CART-07 the Store API cart carries the progress as extensions.mizzey.free_shipping (' . wp_json_encode( $progress ) . ')' );

		// CART-08: suggestions from the related-products assignment (Upsells), not Cross-sells.
		$suggested = $s->simple_product( 't59 toner', '120', null );
		$also      = $s->simple_product( 't59 mask', '90', null );
		$crossed   = $s->simple_product( 't59 brush', '40', null );
		$five->set_upsell_ids( array( $suggested->get_id(), $also->get_id() ) );
		$five->set_cross_sell_ids( array( $crossed->get_id() ) );
		$five->save();
		$cart  = t59_cart( array( $five->get_id() => 1, $also->get_id() => 1 ) );
		$ideas = $cart->get_cross_sells();
		$bare  = t59_cart( array( $open->get_id() => 1 ) )->get_cross_sells();
		$check( array( $suggested->get_id() ) === $ideas && array() === $bare, 'CART-08 the cart suggests the assigned related product not already in it, never the Cross-sells field, and nothing when nothing is assigned (' . implode( ',', $ideas ) . ')' );
		$api_cart = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 1 ), $api['auth'], $api_add['cookies'] );
		$api_ideas = array_map( static fn ( $p ) => (int) $p['id'], (array) ( $api_cart['data']['cross_sells'] ?? array() ) );
		$check( in_array( $suggested->get_id(), $api_ideas, true ) && ! in_array( $crossed->get_id(), $api_ideas, true ), '[HTTP] CART-08 the Store API cart\'s suggestions are the assignment (' . implode( ',', $api_ideas ) . ')' );

		// CART-04: a cart line moved to the wishlist.
		$cart  = t59_cart( array( $open->get_id() => 1 ) );
		$key   = (string) array_key_first( $cart->get_cart() );
		$moved = Wishlist::fromCart( $key );
		$miss  = Wishlist::fromCart( str_repeat( 'a', 32 ) );
		$check( true === $moved && in_array( $open->get_id(), Wishlist::ids(), true ) && WC()->cart->is_empty() && is_wp_error( $miss ), 'CART-04 moving a line saves it to the wishlist and takes it out of the cart; a line not in the cart changes nothing' );
		Wishlist::remove( $open->get_id() );
		$visitor  = t59_store();
		$put_in   = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 1 ), $visitor['auth'], $visitor['cookies'] );
		$line_key = (string) ( $put_in['data']['items'][0]['key'] ?? '' );
		$wl_nonce = (string) ( $put_in['data']['extensions']['mizzey']['wishlist_nonce'] ?? '' );
		$forged   = sign_in_json( 'POST', rest_url( 'mizzey/v1/wishlist/from-cart' ), array( 'key' => $line_key ), array(), $put_in['cookies'] );
		$moved_in = sign_in_json( 'POST', rest_url( 'mizzey/v1/wishlist/from-cart' ), array( 'key' => $line_key ), array( 'X-Mizzey-Nonce' => $wl_nonce ), $put_in['cookies'] );
		$left     = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $moved_in['cookies'] );
		$check( '' !== $line_key && '' !== $wl_nonce && 403 === $forged['status'] && 200 === $moved_in['status'] && array() === ( $left['data']['items'] ?? null ), '[HTTP] CART-04 moving a line needs the nonce tied to the visitor\'s session: without it 403, with it the line moves (' . $forged['status'] . ', ' . $moved_in['status'] . ')' );

		// CART-01, CART-02, CART-03, CART-05, CART-06: WooCommerce's Store API cart.
		$native  = t59_store();
		$filled  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 1 ), $native['auth'], $native['cookies'] );
		$line    = $filled['data']['items'][0] ?? array();
		$more    = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/update-item' ), array( 'key' => $line['key'] ?? '', 'quantity' => 3 ), $native['auth'], $filled['cookies'] );
		$coupon  = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/apply-coupon' ), array( 'code' => "{$tag}-no-such-code" ), $native['auth'], $more['cookies'] );
		$gone    = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/remove-item' ), array( 'key' => $line['key'] ?? '' ), $native['auth'], $coupon['cookies'] );
		$totals  = (array) ( $more['data']['totals'] ?? array() );
		$check( 't59 soap' === ( $line['name'] ?? '' ) && array_key_exists( 'images', $line ) && isset( $line['prices']['price'] ) && 1 === ( $line['quantity'] ?? 0 ) && 3 === ( $more['data']['items'][0]['quantity'] ?? 0 ) && array() === array_diff( array( 'total_items', 'total_discount', 'total_shipping', 'total_price' ), array_keys( $totals ) ) && 400 <= $coupon['status'] && '' !== (string) ( $coupon['data']['message'] ?? '' ) && array() === ( $gone['data']['items'] ?? null ), '[HTTP] CART-01 to CART-03, CART-05, CART-06 a line carries its name, images, price and quantity; its quantity changes; the totals break down; an unknown code is refused with a message; the line is removed' );

		update_option( FixtureStockSource::OPTION, 'no' );
		WC()->cart->empty_cart( false );
		return $ok;
	}
);

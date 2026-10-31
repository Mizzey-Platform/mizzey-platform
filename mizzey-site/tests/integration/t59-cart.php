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
		sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $five->get_id(), 'quantity' => 2 ), $guest['auth'], $guest['cookies'] );
		$add   = sign_in_json( 'POST', rest_url( 'wc/store/v1/cart/add-item' ), array( 'id' => $open->get_id(), 'quantity' => 2 ), $guest['auth'], $guest['cookies'] );
		$account = wc_get_page_permalink( 'myaccount' );
		$form    = two_factor_http( 'GET', $account, array(), $add['cookies'] );
		$signed  = two_factor_http( 'POST', $account, array( 'username' => "{$tag}-c@example.invalid", 'password' => $password, 'woocommerce-login-nonce' => two_factor_field( $form['body'], 'woocommerce-login-nonce' ), '_wp_http_referer' => wp_parse_url( $account, PHP_URL_PATH ), 'login' => 'Log in' ), $form['cookies'] );
		two_factor_http( 'GET', $account, array(), $signed['cookies'] );
		wp_cache_flush();
		$saved = get_user_meta( $customer, '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
		$check( sign_in_has_session( $signed ) && array( $five->get_id() => 2, $open->get_id() => 3 ) === t59_quantities( (array) ( $saved['cart'] ?? array() ) ), '[HTTP] CART-14, CART-09 a guest with 2 creams and 2 soaps signs in to an account whose saved cart holds 1 soap: the account\'s cart holds 2 creams and 3 soaps, no line lost or doubled (' . wp_json_encode( t59_quantities( (array) ( $saved['cart'] ?? array() ) ) ) . ')' );
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
		$pair     = FreeShippingProgress::of( t59_cart( array( $open->get_id() => 2 ) ) );
		$two->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$two->save();
		AutomaticPromotions::forget();
		$by_value = FreeShippingProgress::of( t59_cart( array( $five->get_id() => 1 ) ) );
		$over     = FreeShippingProgress::of( t59_cart( array( $five->get_id() => 2 ) ) );
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

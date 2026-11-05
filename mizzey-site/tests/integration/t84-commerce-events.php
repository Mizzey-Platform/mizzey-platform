<?php
/**
 * t84: the commerce events (EVT-01 to EVT-16, EVT-18). See specs/044-commerce-events and the tracking plan,
 * specs/042-event-tracking-plan.
 *
 * AC-1: page events over the real request, in both languages: home, list, product, search and zero-result search.
 * AC-2: cart and checkout pages; the store's queued events (cart changes, coupon, wishlist, sign-up, sign-in) are
 *       delivered once, and through the Store API's cart answer.
 * AC-3: purchase fires once, on confirmation only, with the order's value, tax and shipping.
 * AC-4: refund is sent to Analytics by the store when configured, and not otherwise.
 * AC-5: no personal data; the item is named by its SKU on both language records.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Marketing\Events;
use MizzeySite\Promotions\AutomaticPromotions;
use MizzeySite\Promotions\PromotionSettings;
use MizzeySite\Promotions\WelcomeDiscount;
use MizzeySite\Wishlist\Wishlist;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_two_factor.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't84-commerce-events', 'EVT-01 to EVT-18: the commerce events' ),
	function ( Scenario $s ): bool {
		// Adding to the cart asks the stock source (ERP-03, specs/062): the stand-in answers for this run.
		$fixture_before = get_option( \MizzeySite\Stock\FixtureStockSource::OPTION );
		$s->on_finish( static function () use ( $fixture_before ) { update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, $fixture_before ); } );
		update_option( \MizzeySite\Stock\FixtureStockSource::OPTION, 'yes' );
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag  = 't84' . wp_rand( 1000, 9999 );
		$keep = array( Events::GA4_ID => get_option( Events::GA4_ID, null ), Events::GA4_KEY => get_option( Events::GA4_KEY, null ) );
		$s->on_finish(
			static function () use ( $keep ): void {
				foreach ( $keep as $option => $value ) {
					null === $value ? delete_option( $option ) : update_option( $option, $value );
				}
				$_GET = array();
				wp_set_current_user( 0 );
				if ( WC()->cart ) {
					WC()->cart->empty_cart( false );
				}
			}
		);
		// The events a page hands its script.
		$events_of = static function ( string $body ): array {
			if ( 1 !== preg_match( '/window\.mizzeyEvents=(\{.*?\});/s', $body, $m ) ) {
				return array( 'language' => '', 'events' => array() );
			}
			return (array) json_decode( $m[1], true );
		};
		$named = static fn ( array $events, string $name ): array => array_values( array_filter( $events, static fn ( $e ) => ( $e['event'] ?? '' ) === $name ) );
		$page  = static function ( string $url ): string {
			$r    = wp_remote_get( $url, array( 'timeout' => 60 ) );
			$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ), $body );
			return $body;
		};

		$page_with = static function ( string $url, array $cookies ): string {
			$r    = wp_remote_get( $url, array( 'timeout' => 60, 'cookies' => $cookies ) );
			$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ), $body );
			return $body;
		};
		// The catalogue: one product in a category, with its Arabic record.
		$w     = new Workflows( $s );
		$sku   = "{$tag}-SKU";
		$en_id = $w->simple( $sku, null );
		$p     = wc_get_product( $en_id );
		$p->set_regular_price( '200' );
		$p->set_sale_price( '150' );
		$p->save();
		$term = wp_insert_term( "{$tag} serums", 'product_cat' );
		$tid  = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
		$s->on_finish( static function () use ( $tid ) { wp_delete_term( $tid, 'product_cat' ); } );
		wp_set_object_terms( $en_id, array( $tid ), 'product_cat' );
		$twin = $w->simple( "{$tag}-TWIN", null );
		$tw   = wc_get_product( $twin );
		$tw->set_regular_price( '80' );
		$tw->save();
		// Translated last: no product is created after a translation in one process (#247, A11).
		$ar_id = $w->translate( 'editor', $en_id );

		// ---- AC-1: page events ---------------------------------------------------------------------------------
		$home = $events_of( $page( home_url( '/' ) ) );
		$check( 'en' === ( $home['language'] ?? '' ) && 1 === count( $named( $home['events'] ?? array(), 'view_home' ) ), 'AC-1 EVT-01 the home page pushes view_home, in English' );
		$ar_home = $events_of( $page( (string) apply_filters( 'wpml_permalink', home_url( '/' ), 'ar' ) ) );
		$check( 'ar' === ( $ar_home['language'] ?? '' ) && 1 === count( $named( $ar_home['events'] ?? array(), 'view_home' ) ), 'AC-1 EVT-01 the Arabic home page pushes view_home, in Arabic' );
		$list = $named( $events_of( $page( (string) get_term_link( $tid, 'product_cat' ) ) )['events'] ?? array(), 'view_item_list' );
		$list_items = $list[0]['ecommerce']['items'] ?? array();
		$check( 1 === count( $list ) && "product_cat:{$tag}-serums" === ( $list[0]['ecommerce']['item_list_id'] ?? '' ) && $sku === ( $list_items[0]['item_id'] ?? '' ) && 0 === ( $list_items[0]['index'] ?? -1 ), 'AC-1 EVT-02 a category page pushes view_item_list, items named by SKU with their position' );
		$view = $named( $events_of( $page( (string) get_permalink( $en_id ) ) )['events'] ?? array(), 'view_item' );
		$item = $view[0]['ecommerce']['items'][0] ?? array();
		$check( 1 === count( $view ) && $sku === ( $item['item_id'] ?? '' ) && 150.0 === (float) ( $item['price'] ?? 0 ) && 50.0 === (float) ( $item['discount'] ?? 0 ) && "{$tag} serums" === ( $item['item_category'] ?? '' ) && 'EGP' === ( $view[0]['ecommerce']['currency'] ?? '' ), 'AC-1 EVT-04 a product page pushes view_item: SKU, price charged, discount, category, EGP' );
		$ar_view = $ar_id > 0 ? $named( $events_of( $page( (string) get_permalink( $ar_id ) ) )['events'] ?? array(), 'view_item' ) : array();
		$check( $sku === ( $ar_view[0]['ecommerce']['items'][0]['item_id'] ?? '' ), 'AC-5 the Arabic product page names the same item, by the same SKU' );
		$found = $events_of( $page( add_query_arg( array( 's' => $tag, 'post_type' => 'product' ), home_url( '/' ) ) ) )['events'] ?? array();
		$search = $named( $found, 'search' );
		$check( 1 === count( $search ) && $tag === ( $search[0]['params']['search_term'] ?? '' ) && 1 <= (int) ( $search[0]['params']['results_count'] ?? 0 ) && array() === $named( $found, 'zero_result_search' ), 'AC-1 EVT-03 a search pushes search with its term and result count (' . wp_json_encode( $search ) . ')' );
		// One match: WooCommerce sends the visitor to the product; the search still arrives, on the product page.
		$one     = wp_remote_get( add_query_arg( array( 's' => "{$tag}-TWIN", 'post_type' => 'product' ), home_url( '/' ) ), array( 'timeout' => 60, 'redirection' => 0 ) ); // storefront-guard: exempt, a redirect whose target is fetched next.
		$landing = (string) wp_remote_retrieve_header( $one, 'location' );
		$after   = '' !== $landing ? $events_of( $page_with( $landing, wp_remote_retrieve_cookies( $one ) ) )['events'] ?? array() : array();
		$check( 1 === (int) ( $named( $after, 'search' )[0]['params']['results_count'] ?? 0 ) && 1 === count( $named( $after, 'view_item' ) ), 'AC-1 EVT-03 a search with one result, sent straight to the product, still pushes search there' );
		$check( '[email] [number]' === Events::term( 'jane@example.com 01012345678' ), 'AC-5 an email address or a phone number typed in the search box is masked' );
		$nothing = $events_of( $page( add_query_arg( array( 's' => "{$tag}zzqx", 'post_type' => 'product' ), home_url( '/' ) ) ) )['events'] ?? array();
		$check( 1 === count( $named( $nothing, 'zero_result_search' ) ) && 0 === (int) ( $named( $nothing, 'search' )[0]['params']['results_count'] ?? -1 ), 'AC-1 EVT-18 a search with no results pushes zero_result_search' );

		// ---- AC-2: the store's events, queued and delivered once ------------------------------------------------------
		WC()->cart->empty_cart( false );
		Events::drain();
		WC()->cart->add_to_cart( $en_id, 2 );
		WC()->cart->calculate_totals();
		$key = array_key_first( WC()->cart->get_cart() );
		WC()->cart->set_quantity( $key, 1 );
		$coupon = new \WC_Coupon();
		$coupon->set_code( "{$tag}ten" );
		$coupon->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '10' ) );
		$coupon->save();
		$s->track_post( $coupon->get_id() );
		WC()->cart->apply_coupon( "{$tag}ten" );
		wc_clear_notices();
		$queued = Events::drain();
		$added  = $named( $queued, 'add_to_cart' );
		$fewer  = $named( $queued, 'remove_from_cart' );
		$check( 1 === count( $added ) && 2 === (int) ( $added[0]['ecommerce']['items'][0]['quantity'] ?? 0 ) && 300.0 === (float) ( $added[0]['ecommerce']['value'] ?? 0 ) && 1 === count( $fewer ) && 1 === (int) ( $fewer[0]['ecommerce']['items'][0]['quantity'] ?? 0 ), 'AC-2 EVT-07, EVT-08 adding two and going down to one: add_to_cart (2, 300) and remove_from_cart (1)' );
		$check( "{$tag}ten" === ( $named( $queued, 'apply_coupon' )[0]['params']['coupon'] ?? '' ) && 10.0 === (float) ( $named( $queued, 'apply_coupon' )[0]['params']['discount'] ?? 0 ), 'AC-2 EVT-10 an accepted coupon pushes apply_coupon with its discount' );
		WC()->cart->add_to_cart( $en_id, 1 );
		$again = Events::drain();
		$check( 1 === count( $named( $again, 'add_to_cart' ) ) && array() === $named( $again, 'remove_from_cart' ), 'AC-2 EVT-07 adding a product already in the cart is one add_to_cart' );
		WC()->cart->apply_coupon( "{$tag}nosuch" );
		wc_clear_notices();
		$check( array() === $named( Events::drain(), 'apply_coupon' ), 'AC-2 EVT-10 a refused coupon pushes nothing' );
		// E-312-1: an automatic promotion the store applies itself is not the customer's act: no apply_coupon.
		WC()->cart->remove_coupons();
		$auto = new \WC_Coupon();
		$auto->set_code( "{$tag}auto" );
		$auto->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '5' ) );
		$auto->update_meta_data( PromotionSettings::AUTOMATIC, 'yes' );
		$auto->save();
		$s->on_finish( static function () use ( $auto ) { wp_delete_post( $auto->get_id(), true ); AutomaticPromotions::forget(); } );
		AutomaticPromotions::forget();
		Events::drain();
		WC()->cart->calculate_totals();
		$auto_held = in_array( wc_format_coupon_code( "{$tag}auto" ), array_map( 'wc_format_coupon_code', WC()->cart->get_applied_coupons() ), true );
		$check( $auto_held && array() === $named( Events::drain(), 'apply_coupon' ), 'AC-2 EVT-10 an automatic promotion applied by the store pushes no apply_coupon' );
		$auto->update_meta_data( PromotionSettings::AUTOMATIC, 'no' );
		$auto->save();
		AutomaticPromotions::forget();
		WC()->cart->remove_coupons();
		WC()->cart->calculate_totals();
		// A customer's own welcome discount is named "welcome", never by its code, which is unique to one person. The
		// coupon belongs to a customer, who is the shopper here and the customer of the order below (PromotionRules
		// refuses a welcome discount to anyone else).
		$welcomer = (int) wp_insert_user( array( 'user_login' => "{$tag}w", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}w@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $welcomer );
		$issued = (int) get_user_meta( $welcomer, WelcomeDiscount::USER_META, true );
		if ( $issued > 0 ) {
			$s->on_finish( static function () use ( $issued ) { wp_delete_post( $issued, true ); } );
			delete_user_meta( $welcomer, WelcomeDiscount::USER_META );
		}
		$welcome_code = 'welcome-' . substr( md5( $tag ), 0, 10 );
		$welcome      = new \WC_Coupon();
		$welcome->set_code( $welcome_code );
		$welcome->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '1' ) );
		$welcome->update_meta_data( WelcomeDiscount::OWNER, (string) $welcomer );
		$welcome->save();
		$s->track_post( $welcome->get_id() );
		wp_set_current_user( $welcomer );
		Events::drain();
		$welcome_applied = WC()->cart->apply_coupon( $welcome_code );
		wc_clear_notices();
		$welcomed = $named( Events::drain(), 'apply_coupon' );
		WC()->cart->remove_coupons();
		WC()->cart->calculate_totals();
		wp_set_current_user( 0 );
		$check( true === $welcome_applied && 1 === count( $welcomed ) && 'welcome' === ( $welcomed[0]['params']['coupon'] ?? '' ) && ! str_contains( (string) wp_json_encode( $welcomed ), $welcome_code ), 'AC-5 a welcome code applied by its customer is pushed as "welcome", never by its own code (' . wp_json_encode( $welcomed[0]['params'] ?? array() ) . ')' );
		$check( 'welcome' === Events::couponName( $welcome_code ) && "{$tag}ten" === Events::couponName( "{$tag}ten" ), 'AC-5 a welcome code is named "welcome"; other codes keep theirs' );
		Events::drain();
		$check( array() === Events::drain() && '' !== (string) ( $added[0]['id'] ?? '' ), 'AC-2 each queued event has an id and is delivered once' );
		WC()->cart->remove_cart_item( $key );
		$check( 1 === count( $named( Events::drain(), 'remove_from_cart' ) ), 'AC-2 EVT-08 removing a line pushes remove_from_cart' );

		// The cart and checkout pages, over real requests with a cart session.
		$added   = wp_remote_get( add_query_arg( 'add-to-cart', $en_id, home_url( '/' ) ), array( 'timeout' => 60, 'redirection' => 0 ) ); // storefront-guard: exempt, the add-to-cart request; the pages are fetched next.
		$session = wp_remote_retrieve_cookies( $added );
		$cart_events = $events_of( $page_with( wc_get_cart_url(), $session ) )['events'] ?? array();
		$checkout    = $events_of( $page_with( wc_get_checkout_url(), $session ) )['events'] ?? array();
		$check( 1 === count( $named( $cart_events, 'view_cart' ) ) && $sku === ( $named( $cart_events, 'view_cart' )[0]['ecommerce']['items'][0]['item_id'] ?? '' ) && 1 === count( $named( array_merge( $events_of( (string) wp_remote_retrieve_body( $added ) )['events'] ?? array(), $cart_events ), 'add_to_cart' ) ), 'AC-2 EVT-07, EVT-09 the cart page pushes view_cart with its lines; the add_to_cart arrives once, on the next page shown (' . wp_json_encode( array_column( $cart_events, 'event' ) ) . ')' );
		$check( 1 === count( $named( $checkout, 'begin_checkout' ) ) && array() === $named( $checkout, 'add_to_cart' ), 'AC-2 EVT-11 the checkout page pushes begin_checkout; the add_to_cart is not pushed twice' );

		// The wishlist, sign-up and sign-in.
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}c", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}c@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $customer );
		$signed_up = Events::drain();
		wp_set_current_user( $customer );
		Wishlist::add( $en_id );
		do_action( 'wp_login', "{$tag}c", get_userdata( $customer ) );
		do_action( 'mizzey_signed_in_by', 'link', $customer );
		$account = Events::drain();
		wp_set_current_user( 0 );
		$check( 'email' === ( $named( $signed_up, 'sign_up' )[0]['params']['method'] ?? '' ), 'AC-2 EVT-16 a new customer account pushes sign_up' );
		$check( $sku === ( $named( $account, 'add_to_wishlist' )[0]['ecommerce']['items'][0]['item_id'] ?? '' ), 'AC-2 EVT-06 saving to the wishlist pushes add_to_wishlist' );
		$logins = array_map( static fn ( $e ) => $e['params']['method'] ?? '', $named( $account, 'login' ) );
		$check( array( 'password', 'link' ) === $logins, 'AC-2 EVT-16 a sign-in pushes login, with its method (' . implode( ',', $logins ) . ')' );
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}s", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}s@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $staff );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $staff ) );
		Events::drain();
		two_factor_completed_sign_in( get_userdata( $staff ) );
		$check( array() === Events::drain(), 'AC-2 a staff sign-in is not a customer event' );

		// The Store API delivers what the store queued.
		$r     = wp_remote_get( rest_url( 'wc/store/v1/cart' ), array( 'timeout' => 30 ) ); // storefront-guard: exempt, a Store API JSON answer and not a page.
		$token = (string) wp_remote_retrieve_header( $r, 'cart-token' );
		$add   = wp_remote_post( rest_url( 'wc/store/v1/cart/add-item' ), array( 'timeout' => 30, 'headers' => array( 'Cart-Token' => $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'id' => $en_id, 'quantity' => 1 ) ) ) ); // storefront-guard: exempt, a Store API JSON answer and not a page.
		$cart  = (array) json_decode( (string) wp_remote_retrieve_body( $add ), true );
		$api   = (array) ( $cart['extensions']['mizzey-events']['events'] ?? array() );
		$check( '' !== $token && 1 === count( $named( $api, 'add_to_cart' ) ) && $sku === ( $named( $api, 'add_to_cart' )[0]['ecommerce']['items'][0]['item_id'] ?? '' ), 'AC-2 EVT-07 a block adding to the cart gets add_to_cart in the Store API\'s answer' );

		// ---- AC-3: purchase, once, on confirmation -------------------------------------------------------------
		$order = wc_create_order();
		$order->add_product( wc_get_product( $en_id ), 2 );
		$order->set_billing_email( "{$tag}buyer@example.invalid" );
		$order->set_billing_phone( '01000000000' );
		$ship = new \WC_Order_Item_Shipping();
		$ship->set_method_title( 'Flat' );
		$ship->set_total( '45' );
		$order->add_item( $ship );
		$order->set_customer_id( $welcomer );
		$order->calculate_totals();
		$welcome_on_order = $order->apply_coupon( $welcome_code );
		$order->save();
		$s->track_order( $order->get_id() );
		$as_page = static function ( int $page_id ): void {
			$query                   = new \WP_Query( array( 'page_id' => $page_id ) );
			$GLOBALS['wp_query']     = $query;
			$GLOBALS['wp_the_query'] = $query;
		};
		$receipt = static function ( \WC_Order $o ) use ( $as_page ): ?array {
			$_GET['key'] = $o->get_order_key();
			$as_page( wc_get_page_id( 'checkout' ) );
			set_query_var( 'order-received', $o->get_id() );
			$event = Events::purchase();
			$_GET  = array();
			return $event;
		};
		$check( null === $receipt( wc_get_order( $order->get_id() ) ), 'AC-3 EVT-14 an order not yet confirmed (pending payment) pushes no purchase' );
		$held = wc_get_order( $order->get_id() );
		$held->update_status( 'on-hold' );
		$check( null === $receipt( wc_get_order( $order->get_id() ) ), 'AC-3 EVT-14 an order on hold, awaiting payment, pushes no purchase' );
		$order = wc_get_order( $order->get_id() );
		$order->update_status( 'processing' );
		$_GET['key'] = 'wrong';
		$as_page( wc_get_page_id( 'checkout' ) );
		set_query_var( 'order-received', $order->get_id() );
		$check( null === Events::purchase(), 'AC-3 a confirmation page with the wrong order key pushes nothing' );
		$_GET = array();
		$bought = $receipt( wc_get_order( $order->get_id() ) );
		$data   = $bought['ecommerce'] ?? array();
		$check( 'purchase' === ( $bought['event'] ?? '' ) && (string) $order->get_order_number() === ( $data['transaction_id'] ?? '' ) && abs( (float) $order->get_total() - (float) ( $data['value'] ?? 0 ) ) < 0.001 && 45.0 === (float) ( $data['shipping'] ?? 0 ) && 2 === (int) ( $data['items'][0]['quantity'] ?? 0 ), 'AC-3 EVT-14 a confirmed order pushes purchase with its number, total, shipping and items' );
		$check( null === $receipt( wc_get_order( $order->get_id() ) ), 'AC-3 EVT-14 reloading the confirmation page pushes no second purchase' );
		$flat = wp_json_encode( $bought );
		$check( ! str_contains( (string) $flat, "{$tag}buyer@example.invalid" ) && ! str_contains( (string) $flat, '01000000000' ) && true === $welcome_on_order && 'welcome' === ( $data['coupon'] ?? '' ) && ! str_contains( (string) $flat, $welcome_code ), 'AC-5 the purchase carries no email address, phone number or personal welcome code (coupon: ' . ( $data['coupon'] ?? 'none' ) . ')' );

		// ---- AC-4: refund, sent by the store --------------------------------------------------------------------
		$sent = array();
		$trap = static function ( $pre, $args, $url ) use ( &$sent ) {
			if ( str_contains( (string) $url, 'google-analytics.com/mp/collect' ) ) {
				$sent[] = array( 'url' => (string) $url, 'body' => (array) json_decode( (string) $args['body'], true ) );
				return array( 'response' => array( 'code' => 204, 'message' => 'No Content' ), 'body' => '', 'headers' => array(), 'cookies' => array() );
			}
			return $pre;
		};
		add_filter( 'pre_http_request', $trap, 10, 3 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_http_request', $trap, 10 ); } );
		delete_option( Events::GA4_ID );
		delete_option( Events::GA4_KEY );
		wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => '20', 'reason' => 't84' ) );
		$check( array() === $sent, 'AC-4 EVT-15 with no measurement id and secret, nothing is sent' );
		update_option( Events::GA4_ID, 'G-T84TEST' );
		update_option( Events::GA4_KEY, 't84secret' );
		$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => '30', 'reason' => 't84' ) );
		$rid    = $refund instanceof \WC_Order_Refund ? $refund->get_id() : 0;
		// E-312-2: the refund only queues the send, so it never waits on Google; the queued action sends it.
		$queued = as_get_scheduled_actions( array( 'hook' => Events::REFUND_HOOK, 'args' => array( $order->get_id(), $rid, 1 ), 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		$check( array() === $sent && 1 === count( $queued ), 'AC-4 EVT-15 the refund itself sends nothing: the send is queued (' . count( $queued ) . ' action)' );
		as_unschedule_all_actions( Events::REFUND_HOOK, array( $order->get_id(), $rid, 1 ), 'mizzey' );
		do_action( Events::REFUND_HOOK, $order->get_id(), $rid, 1 );
		$event = $sent[0]['body']['events'][0] ?? array();
		$check( 1 === count( $sent ) && str_contains( $sent[0]['url'], 'measurement_id=G-T84TEST' ) && 'refund' === ( $event['name'] ?? '' ) && 30.0 === (float) ( $event['params']['value'] ?? 0 ) && (string) $order->get_order_number() === ( $event['params']['transaction_id'] ?? '' ) && '' !== (string) ( $sent[0]['body']['client_id'] ?? '' ), 'AC-4 EVT-15 a refund is sent to Analytics with its order number and value' );
		// A failed first try is tried once more, later; a failed second try is not tried again.
		$fail = static function ( $pre, $args, $url ) {
			return str_contains( (string) $url, 'google-analytics.com/mp/collect' ) ? new \WP_Error( 'http_request_failed', 't84 unreachable' ) : $pre;
		};
		add_filter( 'pre_http_request', $fail, 20, 3 );
		Events::sendRefund( $order->get_id(), $rid, 1 );
		Events::sendRefund( $order->get_id(), $rid, 2 );
		remove_filter( 'pre_http_request', $fail, 20 );
		$retry = as_get_scheduled_actions( array( 'hook' => Events::REFUND_HOOK, 'args' => array( $order->get_id(), $rid, 2 ), 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		$third = as_get_scheduled_actions( array( 'hook' => Events::REFUND_HOOK, 'args' => array( $order->get_id(), $rid, 3 ), 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		as_unschedule_all_actions( Events::REFUND_HOOK, array( $order->get_id(), $rid, 2 ), 'mizzey' );
		$check( 1 === count( $retry ) && array() === $third, 'AC-4 EVT-15 an unreachable Analytics is tried once more, then left (logged)' );

		// ---- The browser half is loaded -----------------------------------------------------------------------
		$body = $page( (string) get_permalink( $en_id ) );
		$check( str_contains( $body, 'assets/events.js' ), 'AC-2 the storefront loads the events script (product card clicks, shipping and payment choices)' );

		return $ok;
	}
);

<?php
/**
 * t95: what the product page states beside the product (specs/055-product-page).
 *
 * AC-1: a product on sale states its saving: amount (minor units) and percent; a variable product its largest.
 * AC-2: Buy Now adds the item and its quantity to the cart and lands on checkout.
 * AC-3: the delivery estimate is the admin's for the customer's zone, or the default, stated as an estimate.
 * AC-4: the returns summary and the authenticity statement are the admin's words in the page's language, each with its
 *       page in that language.
 * AC-5: share addresses for the product, and Open Graph tags on its page so a shared link shows title, image, price.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\ProductPage;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't95-product-page', 'PDP-04, PDP-09, PDP-14, PDP-15, PDP-16, PDP-21: what the product page states' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'T95' . wp_rand( 1000, 9999 );
		// The settings, kept and put back.
		$options = array( ProductPage::RETURNS . 'en', ProductPage::RETURNS . 'ar', ProductPage::RETURNS_PAGE, ProductPage::AUTHENTIC . 'en', ProductPage::AUTHENTIC . 'ar', ProductPage::AUTHENTIC_PAGE, ProductPage::DAYS_MIN, ProductPage::DAYS_MAX );
		$kept    = array();
		foreach ( $options as $option ) {
			$kept[ $option ] = get_option( $option, null );
		}
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "{$tag} Cairo" );
		$zone->set_zone_order( 0 );
		$zone->add_location( 'EG:EG-01', 'state' );
		$zone->save();
		// WooCommerce keeps a zone's order unsigned and matches the first zone by order, then id, so a site's own zone
		// for Egypt (staging has one) would take the address. The other zones move after this one for the run and get
		// their order back at the end (as t52 does).
		foreach ( \WC_Shipping_Zones::get_zones() as $other ) {
			if ( (int) $other['id'] === $zone->get_id() ) {
				continue;
			}
			$moved = new \WC_Shipping_Zone( (int) $other['id'] );
			$order = $moved->get_zone_order();
			$moved->set_zone_order( $order + 1000 );
			$moved->save();
			$s->on_finish( static function () use ( $moved, $order ): void { $moved->set_zone_order( $order ); $moved->save(); } );
		}
		$s->on_finish(
			static function () use ( $kept, $zone ): void {
				foreach ( $kept as $option => $value ) {
					null === $value ? delete_option( $option ) : update_option( $option, $value );
				}
				delete_option( ProductPage::ZONE . $zone->get_id() );
				$zone->delete();
			}
		);
		$page = static function ( string $title, string $ar_title ) use ( $s, $sitepress ): array {
			do_action( 'wpml_switch_language', 'en' );
			$id = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title ) );
			$sitepress->make_duplicate( $id, 'ar' );
			$ar = (int) apply_filters( 'wpml_object_id', $id, 'page', false, 'ar' );
			wp_update_post( array( 'ID' => $ar, 'post_title' => $ar_title ) );
			delete_post_meta( $ar, '_icl_lang_duplicate_of' );
			$s->on_finish( static function () use ( $id, $ar ): void { wp_delete_post( $id, true ); wp_delete_post( $ar, true ); } );
			return array( $id, $ar );
		};
		list( $returns, $returns_ar )     = $page( "{$tag} Returns", "{$tag} الاسترجاع" );
		list( $authentic, $authentic_ar ) = $page( "{$tag} Authenticity", "{$tag} الأصالة" );
		update_option( ProductPage::RETURNS . 'en', "{$tag} Return within 14 days." );
		update_option( ProductPage::RETURNS . 'ar', "{$tag} الاسترجاع خلال 14 يوما." );
		update_option( ProductPage::RETURNS_PAGE, $returns );
		update_option( ProductPage::AUTHENTIC . 'en', "{$tag} Every product is genuine." );
		update_option( ProductPage::AUTHENTIC . 'ar', "{$tag} كل المنتجات أصلية." );
		update_option( ProductPage::AUTHENTIC_PAGE, $authentic );
		update_option( ProductPage::DAYS_MIN, 2 );
		update_option( ProductPage::DAYS_MAX, 5 );
		update_option( ProductPage::ZONE . $zone->get_id(), '1-2' );

		do_action( 'wpml_switch_language', 'en' );
		$lamp = $s->simple_product( "{$tag} Lamp", '200.00', null );
		$lamp->set_sale_price( '150.00' );
		$lamp->set_short_description( 'A warm desk lamp.' );
		$lamp->save();
		$sitepress->make_duplicate( $lamp->get_id(), 'ar' );
		$lamp_ar = (int) apply_filters( 'wpml_object_id', $lamp->get_id(), 'product', false, 'ar' );
		$s->track_post( $lamp_ar );
		$plain = $s->simple_product( "{$tag} Mug", '80.00', null );
		wp_cache_flush();
		$api  = static fn ( int $id, string $lang = 'en', array $cookies = array() ): array => sign_in_json( 'GET', 'ar' === $lang ? home_url( "/ar/wp-json/wc/store/v1/products/{$id}" ) : rest_url( "wc/store/v1/products/{$id}" ), null, array(), $cookies );
		$en   = $api( $lamp->get_id() );
		$page_data = (array) ( $en['data']['extensions']['mizzey-page'] ?? array() );

		// ---- AC-1: the saving ---------------------------------------------------------------------------------------
		$decimals = wc_get_price_decimals();
		$none     = (array) ( $api( $plain->get_id() )['data']['extensions']['mizzey-page'] ?? array() );
		$check( (string) ( 50 * ( 10 ** $decimals ) ) === ( $page_data['saving']['amount'] ?? '' ) && 25 === ( $page_data['saving']['percent'] ?? 0 ) && array_key_exists( 'saving', $none ) && null === $none['saving'], 'AC-1 PDP-04 a product on sale states its saving, 50 or 25%, in the Store API\'s minor units; one not on sale states none (' . wp_json_encode( array( $page_data['saving'] ?? null, $none['saving'] ?? 'absent' ) ) . ')' );
		$var = new \WC_Product_Variable();
		$var->set_name( "{$tag} Shirt" );
		$var->set_status( 'publish' );
		$attr = new \WC_Product_Attribute();
		$attr->set_name( 'Size' );
		$attr->set_options( array( 'S', 'M' ) );
		$attr->set_variation( true );
		$var->set_attributes( array( $attr ) );
		$var->save();
		$s->track_post( $var->get_id() );
		foreach ( array( array( 'S', '100', '90' ), array( 'M', '120', '60' ) ) as list( $size, $regular, $sale ) ) {
			$v = new \WC_Product_Variation();
			$v->set_parent_id( $var->get_id() );
			$v->set_attributes( array( 'size' => $size ) );
			$v->set_regular_price( $regular );
			$v->set_sale_price( $sale );
			$v->set_status( 'publish' );
			$v->save();
			$s->track_post( $v->get_id() );
		}
		\WC_Product_Variable::sync( $var->get_id() );
		$vsave = ProductPage::saving( wc_get_product( $var->get_id() ) );
		$check( (string) ( 60 * ( 10 ** $decimals ) ) === ( $vsave['amount'] ?? '' ) && 50 === ( $vsave['percent'] ?? 0 ), 'AC-1 a variable product states its largest saving, 60 or 50% (' . wp_json_encode( $vsave ) . ')' );

		// ---- AC-2: Buy Now -------------------------------------------------------------------------------------------
		$cart = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null );
		$jar  = sign_in_jar( $cart['cookies'] );
		$buy  = add_query_arg( 'quantity', 2, (string) ( $page_data['buy_now'] ?? '' ) );
		$r    = wp_remote_get( $buy, array( 'redirection' => 0, 'timeout' => 60, 'cookies' => $jar ) ); // storefront-guard: exempt, a redirect to checkout is the answer.
		$to   = (string) wp_remote_retrieve_header( $r, 'location' );
		$jar  = sign_in_jar( array_merge( $jar, wp_remote_retrieve_cookies( $r ) ) );
		$now  = sign_in_json( 'GET', rest_url( 'wc/store/v1/cart' ), null, array(), $jar );
		$items = array_map( static fn ( $i ) => array( (int) $i['id'], (int) $i['quantity'] ), (array) ( $now['data']['items'] ?? array() ) );
		$check( str_contains( (string) ( $page_data['buy_now'] ?? '' ), 'add-to-cart=' . $lamp->get_id() ) && 302 === (int) wp_remote_retrieve_response_code( $r ) && untrailingslashit( $to ) === untrailingslashit( wc_get_checkout_url() ) && array( array( $lamp->get_id(), 2 ) ) === $items, 'AC-2 PDP-09 Buy Now adds the item and its quantity to the cart and lands on checkout (' . wp_remote_retrieve_response_code( $r ) . ' ' . $to . ', ' . wp_json_encode( $items ) . ')' );

		// ---- AC-3: the delivery estimate ------------------------------------------------------------------------------
		$default = ProductPage::delivery( null );
		$cairo   = ProductPage::delivery( array( 'country' => 'EG', 'state' => 'EG-01', 'postcode' => '' ) );
		$other   = ProductPage::delivery( array( 'country' => 'EG', 'state' => 'EG-02', 'postcode' => '' ) );
		$check( 2 === ( $default['min'] ?? 0 ) && 5 === ( $default['max'] ?? 0 ) && 1 === ( $cairo['min'] ?? 0 ) && 2 === ( $cairo['max'] ?? 0 ) && "{$tag} Cairo" === ( $cairo['zone'] ?? '' ) && 5 === ( $other['max'] ?? 0 ) && str_contains( (string) ( $default['text'] ?? '' ), 'Estimated' ) && 2 === ( $page_data['delivery']['min'] ?? 0 ), 'AC-3 PDP-14 the estimate is the zone\'s (1 to 2 days) where the address is known and its zone has one, otherwise the default (2 to 5), stated as an estimate (' . wp_json_encode( array( $default, $cairo, $other ) ) . ')' );
		if ( switch_to_locale( 'ar' ) ) {
			$ar_text = (string) ( ProductPage::delivery( null )['text'] ?? '' );
			restore_previous_locale();
		}
		$check( str_contains( $ar_text ?? '', 'التوصيل المتوقع' ), 'AC-3 the estimate in Arabic (' . ( $ar_text ?? '' ) . ')' );

		// ---- AC-4: returns and authenticity ---------------------------------------------------------------------------
		$ar   = (array) ( $api( $lamp_ar, 'ar' )['data']['extensions']['mizzey-page'] ?? array() );
		$check( "{$tag} Return within 14 days." === ( $page_data['returns']['summary'] ?? '' ) && untrailingslashit( (string) get_permalink( $returns ) ) === untrailingslashit( (string) ( $page_data['returns']['url'] ?? '' ) ) && "{$tag} Every product is genuine." === ( $page_data['authenticity']['statement'] ?? '' ), 'AC-4 PDP-15, PDP-16 the English page states the returns summary and the authenticity statement, each with its page' );
		$check( "{$tag} الاسترجاع خلال 14 يوما." === ( $ar['returns']['summary'] ?? '' ) && "{$tag} كل المنتجات أصلية." === ( $ar['authenticity']['statement'] ?? '' ) && str_contains( (string) ( $ar['returns']['url'] ?? '' ), '/ar/' ) && str_contains( (string) ( $ar['authenticity']['url'] ?? '' ), '/ar/' ), 'AC-4 the Arabic page states them in Arabic, each with its Arabic page (' . wp_json_encode( array( $ar['returns'] ?? null, $ar['authenticity'] ?? null ), JSON_UNESCAPED_UNICODE ) . ')' );

		// ---- AC-5: share ---------------------------------------------------------------------------------------------
		$links = (array) ( $page_data['share']['links'] ?? array() );
		$url   = (string) get_permalink( $lamp->get_id() );
		$check( $url === ( $page_data['share']['url'] ?? '' ) && str_contains( (string) ( $links['whatsapp'] ?? '' ), rawurlencode( $url ) ) && str_contains( (string) ( $links['facebook'] ?? '' ), rawurlencode( $url ) ) && str_contains( (string) ( $links['x'] ?? '' ), rawurlencode( $url ) ) && str_starts_with( (string) ( $links['email'] ?? '' ), 'mailto:' ), 'AC-5 PDP-21 the share addresses carry the product\'s own address' );
		$html = wp_remote_get( $url, array( 'timeout' => 60 ) );
		$body = (string) wp_remote_retrieve_body( $html );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $html ), $body );
		$check( str_contains( $body, 'property="og:title" content="' . "{$tag} Lamp" . '"' ) && str_contains( $body, 'property="og:type" content="product"' ) && str_contains( $body, 'property="product:price:amount" content="150' ) && str_contains( $body, 'property="og:url"' ), 'AC-5 the product page carries Open Graph tags, so a shared link shows its title and price' );

		return $ok;
	}
);

<?php
/**
 * t67: stock is the stock source's, never written locally (D-19 item 5; ERP-07, ADM-70, ADM-72). See specs/026-admin-stock.
 *
 * AC-1: no local writer changes stored stock: a product's or a variation's save, WooCommerce's direct stock update,
 *       a new product, the REST API; the adapter's projection may.
 * AC-2: the products list and the product editor show the figure from the stock source, or that it is not known, and
 *       the stock fields are shown locked.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Stock\FixtureStockSource;
use MizzeySite\Stock\StockGateway;
use MizzeySite\Stock\StockItem;
use MizzeySite\Stock\StockSource;
use MizzeySite\Stock\StockWriteGuard;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';

/** A stock source that reports one quantity for everything and changes nothing. */
final class T67Source implements StockSource {
	public function __construct( public int $quantity ) {}
	public function name(): string { return 't67'; }
	public function level( StockItem $item ): int { return $this->quantity; }
	public function adjust( StockItem $item, int $delta, string $operationKey ): int { return $this->quantity; }
}

/** The stored stock of a product, read from the database. */
function t67_stored( int $id ): array {
	clean_post_cache( $id );
	wp_cache_delete( $id, 'post_meta' );
	return array(
		'qty'       => get_post_meta( $id, '_stock', true ),
		'status'    => get_post_meta( $id, '_stock_status', true ),
		'manage'    => get_post_meta( $id, '_manage_stock', true ),
		'backorder' => get_post_meta( $id, '_backorders', true ),
	);
}

run(
	new Scenario( 't67-admin-stock', 'ERP-07, ADM-70, ADM-72: stock is the stock source\'s' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		// No stand-in stock source unless this scenario binds one.
		sign_in_options( $s, array( FixtureStockSource::OPTION => 'no' ) );
		// Fixtures made with the permit the harness holds, as the ERP's projection would write them.
		$product = $s->simple_product( 't67 serum', '100', null );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( 5 );
		$product->set_backorders( 'no' );
		$product->save();
		$parent = new \WC_Product_Variable();
		$parent->set_name( 't67 balm' );
		$parent->save();
		$s->track_post( $parent->get_id() );
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '40' );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 4 );
		$variation->save();
		$s->track_post( $variation->get_id() );

		// Without the permit, as every local writer runs.
		remove_filter( StockWriteGuard::PERMIT, '__return_true' );
		$s->on_finish( static function () { add_filter( StockWriteGuard::PERMIT, '__return_true' ); } );

		// AC-1: a save, whoever makes it.
		$edited = wc_get_product( $product->get_id() );
		$edited->set_stock_quantity( 99 );
		$edited->set_stock_status( 'outofstock' );
		$edited->set_backorders( 'yes' );
		$edited->set_name( 't67 serum renamed' );
		$edited->save();
		$after_save = t67_stored( $product->get_id() );
		$edited_var = wc_get_product( $variation->get_id() );
		$edited_var->set_stock_quantity( 77 );
		$edited_var->save();
		$var_after = t67_stored( $variation->get_id() );
		$check( 5 === (int) $after_save['qty'] && 'instock' === $after_save['status'] && 'no' === $after_save['backorder'] && 'yes' === $after_save['manage'] && 't67 serum renamed' === get_the_title( $product->get_id() ) && 4 === (int) $var_after['qty'], 'ERP-07, D-19 a product\'s and a variation\'s save keeps their stored stock, status and backorders, while the rest of the edit is kept (' . wp_json_encode( array( $after_save, $var_after['qty'] ) ) . ')' );

		// WooCommerce's direct stock update, and turning stock management off.
		wc_update_product_stock( $product->get_id(), 1, 'set' );
		wc_update_product_stock( $product->get_id(), 2, 'decrease' );
		$unmanaged = wc_get_product( $product->get_id() );
		$unmanaged->set_manage_stock( false );
		$unmanaged->save();
		$direct = t67_stored( $product->get_id() );
		$check( 5 === (int) $direct['qty'] && 'yes' === $direct['manage'], 'ERP-07, D-19 WooCommerce\'s direct stock update writes nothing, and stock management cannot be switched off (' . wp_json_encode( $direct ) . ')' );

		// A new product made with stock.
		$made = new \WC_Product_Simple();
		$made->set_name( 't67 new' );
		$made->set_manage_stock( true );
		$made->set_stock_quantity( 30 );
		$made->set_stock_status( 'outofstock' );
		$made->save();
		$s->track_post( $made->get_id() );
		$fresh = t67_stored( $made->get_id() );
		$check( '' === (string) $fresh['qty'] && 'no' === $fresh['manage'] && 'instock' === $fresh['status'], 'ERP-07, D-19 a new product made with a stock figure stores none (' . wp_json_encode( $fresh ) . ')' );

		// The REST API.
		$manager = wp_insert_user( array( 'user_login' => 't67-manager-' . wp_rand(), 'user_email' => 't67-' . wp_rand() . '@example.invalid', 'user_pass' => wp_generate_password( 24 ), 'role' => 'shop_manager' ) );
		$manager = is_wp_error( $manager ) ? 0 : (int) $manager;
		if ( $manager > 1 ) {
			$s->track_user( $manager );
		}
		wp_set_current_user( $manager );
		$stock_put = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $product->get_id() );
		$stock_put->set_param( 'stock_quantity', 50 );
		$refused   = rest_do_request( $stock_put );
		$var_put   = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $parent->get_id() . '/variations/' . $variation->get_id() );
		$var_put->set_param( 'stock_quantity', 50 );
		$var_refused = rest_do_request( $var_put );
		$name_put  = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $product->get_id() );
		$name_put->set_param( 'name', 't67 serum by REST' );
		$renamed   = rest_do_request( $name_put );
		wp_set_current_user( 0 );
		$check( 403 === $refused->get_status() && 403 === $var_refused->get_status() && 200 === $renamed->get_status() && 5 === (int) t67_stored( $product->get_id() )['qty'] && 4 === (int) t67_stored( $variation->get_id() )['qty'], 'ERP-07, D-19 the REST API refuses a stock change for a product and a variation, and takes any other edit (' . $refused->get_status() . ', ' . $var_refused->get_status() . ', ' . $renamed->get_status() . ')' );

		// The adapter's projection is the one way through.
		StockWriteGuard::projection( static function () use ( $product ) {
			$projected = wc_get_product( $product->get_id() );
			$projected->set_stock_quantity( 12 );
			$projected->save();
		} );
		$check( 12 === (int) t67_stored( $product->get_id() )['qty'] && ! StockWriteGuard::permitted(), 'D-19 the adapter\'s projection writes the stock, and only while it runs' );

		// AC-2: the figure from the stock source, in the list and the editor; the fields shown locked.
		$source = new T67Source( 7 );
		$bind   = static fn () => $source;
		add_filter( StockGateway::SOURCE_FILTER, $bind );
		$list_html = (string) apply_filters( 'woocommerce_admin_stock_html', '<mark class="instock">In stock</mark> (12)', wc_get_product( $product->get_id() ) );
		$GLOBALS['product_object'] = wc_get_product( $product->get_id() );
		ob_start();
		do_action( 'woocommerce_product_options_stock_status' );
		$editor = (string) ob_get_clean();
		ob_start();
		do_action( 'woocommerce_variation_options_inventory', 0, array(), get_post( $variation->get_id() ) );
		$var_editor = (string) ob_get_clean();
		remove_filter( StockGateway::SOURCE_FILTER, $bind );
		$unknown = (string) apply_filters( 'woocommerce_admin_stock_html', 'In stock', wc_get_product( $product->get_id() ) );
		set_current_screen( 'product' );
		ob_start();
		do_action( 'admin_head' );
		$head = (string) ob_get_clean();
		set_current_screen( 'front' );
		unset( $GLOBALS['product_object'] );
		$check( false !== strpos( $list_html, 'Stock from the stock source: 7' ) && false === strpos( $list_html, '(12)' ) && false !== strpos( $editor, 'Stock from the stock source: 7' ) && false !== strpos( $var_editor, 'Stock from the stock source: 7' ) && false !== strpos( $unknown, 'not known' ) && false !== strpos( $head, '#_stock,' ), 'ADM-70, ADM-72 the products list and the product and variation editors show the figure from the stock source (7, not the stored 12), or that it is not known, with the stock fields shown locked' );

		return $ok;
	}
);

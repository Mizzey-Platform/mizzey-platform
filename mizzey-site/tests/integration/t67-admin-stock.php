<?php
/**
 * t67: stock is the stock source's, never written locally (D-19 item 5; ERP-07, ADM-70, ADM-72). See specs/026-admin-stock.
 *
 * AC-1: no local writer changes stored stock: an object's save, the metadata API by key and by id, a raw query, the
 *       direct stock update, a new product, the REST API; the adapter's projection may, and keeps a parent in step.
 * AC-2: the products list and the product editor show the figure from the stock source, read once, or that it is not
 *       known, with the stock fields disabled.
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

/** A stock source that reports one quantity for everything, counts its reads and changes nothing. */
final class T67Source implements StockSource {
	public int $reads = 0;
	public function __construct( public int $quantity ) {}
	public function name(): string { return 't67'; }
	public function level( StockItem $item ): int { $this->reads++; return $this->quantity; }
	public function adjust( StockItem $item, int $delta, string $operationKey ): int { return $this->quantity; }
}

/** The stored stock of a product, read from the database. */
function t67_stored( int $id ): array {
	global $wpdb;
	clean_post_cache( $id );
	wp_cache_delete( $id, 'post_meta' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ('_stock', '_stock_status', '_manage_stock', '_backorders')", $id ), OBJECT_K );
	return array(
		'qty'       => isset( $rows['_stock'] ) ? (string) $rows['_stock']->meta_value : null,
		'status'    => isset( $rows['_stock_status'] ) ? (string) $rows['_stock_status']->meta_value : null,
		'manage'    => isset( $rows['_manage_stock'] ) ? (string) $rows['_manage_stock']->meta_value : null,
		'backorder' => isset( $rows['_backorders'] ) ? (string) $rows['_backorders']->meta_value : null,
	);
}

function t67_rest( string $method, string $route, array $json ): int {
	$request = new \WP_REST_Request( $method, $route );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( (string) wp_json_encode( $json ) );
	return rest_do_request( $request )->get_status();
}

run(
	new Scenario( 't67-admin-stock', 'ERP-07, ADM-70, ADM-72: stock is the stock source\'s' ),
	function ( Scenario $s ): bool {
		global $wpdb;
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
		$id     = $product->get_id();
		$parent = new \WC_Product_Variable();
		$parent->set_name( 't67 balm' );
		$parent->save();
		$s->track_post( $parent->get_id() );
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '40' );
		$variation->set_manage_stock( true );
		$variation->set_stock_quantity( 0 );
		$variation->save();
		$s->track_post( $variation->get_id() );
		\WC_Product_Variable::sync( $parent->get_id() );
		$doomed = $s->simple_product( 't67 doomed', '10', null );
		$doomed->set_manage_stock( true );
		$doomed->set_stock_quantity( 3 );
		$doomed->save();

		// Without the permit, as every local writer runs.
		remove_filter( StockWriteGuard::PERMIT, '__return_true' );
		$s->on_finish( static function () { add_filter( StockWriteGuard::PERMIT, '__return_true' ); } );

		// AC-1: an object's save, whoever makes it; the rest of the edit is kept.
		$edited = wc_get_product( $id );
		$edited->set_stock_quantity( 99 );
		$edited->set_stock_status( 'outofstock' );
		$edited->set_backorders( 'yes' );
		$edited->set_manage_stock( false );
		$edited->set_name( 't67 serum renamed' );
		$edited->save();
		$var = wc_get_product( $variation->get_id() );
		$var->set_stock_quantity( 77 );
		$var->save();
		$after = t67_stored( $id );
		$check( '5' === $after['qty'] && 'instock' === $after['status'] && 'no' === $after['backorder'] && 'yes' === $after['manage'] && 't67 serum renamed' === get_the_title( $id ) && '0' === t67_stored( $variation->get_id() )['qty'], 'ERP-07, D-19 a product\'s and a variation\'s save keeps their stored stock, status, backorders and stock management, while the rest of the edit is kept (' . wp_json_encode( $after ) . ')' );

		// The metadata API, by key and by id, and a raw query as WooCommerce Multilingual writes.
		update_post_meta( $id, '_stock', '98' );
		delete_post_meta( $id, '_manage_stock' );
		add_post_meta( $id, '_backorders', 'yes' );
		$mid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $id, '_stock' ) );
		update_metadata_by_mid( 'post', $mid, '97' );
		delete_metadata_by_mid( 'post', $mid );
		$wpdb->update( $wpdb->postmeta, array( 'meta_value' => '96' ), array( 'post_id' => $id, 'meta_key' => '_stock' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = '95' WHERE post_id = %d AND meta_key = '_stock'", $id ) );
		$raw = t67_stored( $id );
		$check( '5' === $raw['qty'] && 'yes' === $raw['manage'] && 'no' === $raw['backorder'], 'ERP-07, D-19 the metadata API by key and by meta id, and a raw query on the post meta table, change no stored stock (' . wp_json_encode( $raw ) . ')' );

		// WooCommerce's direct stock update: nothing written, and the object read again.
		$direct  = wc_get_product( $id );
		$answer  = wc_update_product_stock( $direct, 1, 'set' );
		$direct->set_name( 't67 serum again' );
		$direct->save();
		$check( '5' === t67_stored( $id )['qty'] && 5 === (int) $direct->get_stock_quantity() && 5 === (int) $answer, 'ERP-07, D-19 WooCommerce\'s direct stock update writes nothing, and the product object keeps the stored figure, so a later save cannot write the refused one (' . var_export( $answer, true ) . ')' );

		// A new product made with stock stores none.
		$made = new \WC_Product_Simple();
		$made->set_name( 't67 new' );
		$made->set_manage_stock( true );
		$made->set_stock_quantity( 30 );
		$made->set_stock_status( 'outofstock' );
		$made->save();
		$s->track_post( $made->get_id() );
		$fresh = t67_stored( $made->get_id() );
		$check( in_array( $fresh['qty'], array( null, '' ), true ) && in_array( $fresh['manage'], array( null, 'no' ), true ) && in_array( $fresh['status'], array( null, 'instock' ), true ), 'ERP-07, D-19 a new product made with a stock figure stores none (' . wp_json_encode( $fresh ) . ')' );

		// A product deleted takes its stock meta with it.
		$doomed_id = $doomed->get_id();
		wp_delete_post( $doomed_id, true );
		$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $doomed_id ) );
		$check( 0 === $left, "ERP-07 a product deleted leaves no stock meta behind ({$left} rows)" );

		// The REST API: any version, a batch, a nested variation, a delta; schema defaults are not counted as sent.
		$manager = wp_insert_user( array( 'user_login' => 't67-manager-' . wp_rand(), 'user_email' => 't67-' . wp_rand() . '@example.invalid', 'user_pass' => wp_generate_password( 24 ), 'role' => 'shop_manager' ) );
		$manager = is_wp_error( $manager ) ? 0 : (int) $manager;
		if ( $manager > 1 ) {
			$s->track_user( $manager );
		}
		wp_set_current_user( $manager );
		$codes = array(
			'v3'        => t67_rest( 'PUT', "/wc/v3/products/{$id}", array( 'stock_quantity' => 50 ) ),
			'v2'        => t67_rest( 'PUT', "/wc/v2/products/{$id}", array( 'manage_stock' => false ) ),
			'variation' => t67_rest( 'PUT', '/wc/v3/products/' . $parent->get_id() . '/variations/' . $variation->get_id(), array( 'stock_quantity' => 50 ) ),
			'batch'     => t67_rest( 'POST', '/wc/v3/products/batch', array( 'update' => array( array( 'id' => $id, 'stock_quantity' => 50 ) ) ) ),
			'delta'     => t67_rest( 'PUT', "/wc/v3/products/{$id}", array( 'inventory_delta' => 3 ) ),
		);
		$create  = new \WP_REST_Request( 'POST', '/wc/v3/products' );
		$create->set_header( 'content-type', 'application/json' );
		$create->set_body( (string) wp_json_encode( array( 'name' => 't67 by REST' ) ) );
		$created = rest_do_request( $create );
		if ( 201 === $created->get_status() ) {
			$s->track_post( (int) ( $created->get_data()['id'] ?? 0 ) );
		}
		$renamed = t67_rest( 'PUT', "/wc/v3/products/{$id}", array( 'name' => 't67 serum by REST' ) );
		wp_set_current_user( 0 );
		$refused = array_keys( array_filter( $codes, static fn ( int $c ): bool => 403 !== $c ) );
		$check( array() === $refused && 201 === $created->get_status() && 200 === $renamed && '5' === t67_stored( $id )['qty'], 'ERP-07, D-19 the REST API refuses a stock change in v3, v2, a nested variation, a batch and a delta, and takes a product made or renamed without one (' . wp_json_encode( $codes ) . ', ' . $created->get_status() . ', ' . $renamed . ')' );

		// The adapter's projection writes; a stale copy saved afterwards does not undo it; a parent follows.
		$stale = wc_get_product( $id );
		StockWriteGuard::projection( static function () use ( $id, $variation ) {
			$projected = wc_get_product( $id );
			$projected->set_stock_quantity( 12 );
			$projected->save();
			wc_update_product_stock( $variation->get_id(), 6, 'set' );
		} );
		$stale->set_stock_quantity( 3 );
		$stale->set_name( 't67 serum stale' );
		$stale->save();
		clean_post_cache( $parent->get_id() );
		$check( '12' === t67_stored( $id )['qty'] && '6' === t67_stored( $variation->get_id() )['qty'] && 'instock' === t67_stored( $parent->get_id() )['status'] && ! StockWriteGuard::permitted(), 'D-19 the adapter\'s projection writes the stock and brings the variable parent back in stock; a copy loaded before it and saved after it does not undo it (' . wp_json_encode( array( t67_stored( $id )['qty'], t67_stored( $parent->get_id() )['status'] ) ) . ')' );

		// AC-2: the figure from the stock source, read once, in the list and the editors; the fields disabled.
		$source = new T67Source( 7 );
		$bind   = static fn () => $source;
		add_filter( StockGateway::SOURCE_FILTER, $bind );
		foreach ( array( $id, $variation->get_id() ) as $shown ) {
			delete_transient( StockWriteGuard::FIGURE . $shown );
		}
		$list_html = (string) apply_filters( 'woocommerce_admin_stock_html', '<mark class="instock">In stock</mark> (12)', wc_get_product( $id ) );
		$again     = (string) apply_filters( 'woocommerce_admin_stock_html', 'In stock', wc_get_product( $id ) );
		$GLOBALS['product_object'] = wc_get_product( $id );
		ob_start();
		do_action( 'woocommerce_product_options_stock_status' );
		$editor = (string) ob_get_clean();
		ob_start();
		do_action( 'woocommerce_variation_options_inventory', 0, array(), get_post( $variation->get_id() ) );
		$var_editor = (string) ob_get_clean();
		remove_filter( StockGateway::SOURCE_FILTER, $bind );
		delete_transient( StockWriteGuard::FIGURE . $id );
		$unknown = (string) apply_filters( 'woocommerce_admin_stock_html', 'In stock', wc_get_product( $id ) );
		delete_transient( StockWriteGuard::FIGURE . $id );
		set_current_screen( 'product' );
		do_action( 'admin_enqueue_scripts', 'post.php' );
		$locked = wp_script_is( 'mizzey-admin-stock', 'enqueued' );
		set_current_screen( 'front' );
		unset( $GLOBALS['product_object'] );
		$check( false !== strpos( $list_html, 'Stock from the stock source: 7' ) && false === strpos( $list_html, '(12)' ) && false !== strpos( $again, ': 7' ) && 2 === $source->reads && false !== strpos( $editor, 'Stock from the stock source: 7' ) && false !== strpos( $var_editor, 'Stock from the stock source: 7' ) && false !== strpos( $unknown, 'not known' ) && $locked, 'ADM-70, ADM-72 the products list and the product and variation editors show the figure from the stock source (7, not the stored 12), read once per product, or that it is not known; the stock fields\' lock is loaded on the product screen (' . $source->reads . ' reads)' );

		return $ok;
	}
);

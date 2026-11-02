<?php
/**
 * t77: the everyday tasks in the standard administration (Section G10: ADM-146, ADM-148, ADM-150 to ADM-154,
 * ADM-157 to ADM-161). See specs/037-everyday-admin.
 *
 * Over the real requests the screens make, as a signed-in administrator:
 * AC-1: a bulk price change by percentage and by amount across a selection, both language records following.
 * AC-2: bulk publish, unpublish (draft) and archive (private), and bulk category assignment.
 * AC-3: a product found by SKU, title and barcode; an order found by the customer's phone.
 * AC-4: bulk order status change; the order export is the Analytics CSV download.
 * AC-5: the administration is in English, also for a staff member whose site language is Arabic.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

run(
	new Scenario( 't77-everyday-admin', 'Section G10: everyday tasks in the standard administration' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't77' . wp_rand( 1000, 9999 );
		$w   = new Workflows( $s );

		// An administrator's browser: session cookies for a fresh account.
		$admin  = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => "{$tag}adm@example.invalid" ) );
		$s->track_user( $admin );
		$expiry  = time() + HOUR_IN_SECONDS;
		$token   = \WP_Session_Tokens::get_instance( $admin )->create( $expiry );
		$cookies = array();
		foreach ( array( AUTH_COOKIE => 'auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
			$cookies[] = new \WP_Http_Cookie( array( 'name' => $name, 'value' => wp_generate_auth_cookie( $admin, $expiry, $scheme, $token ) ) );
		}
		$get = static function ( string $url ) use ( $cookies ): array {
			$r = wp_remote_get( $url, array( 'cookies' => $cookies, 'timeout' => 90, 'redirection' => 0 ) );
			return is_wp_error( $r ) ? array( 0, $r->get_error_message(), '' ) : array( (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ), (string) wp_remote_retrieve_header( $r, 'location' ) );
		};
		$fresh = static function ( int $id ): \WC_Product {
			wp_cache_flush();
			return wc_get_product( $id );
		};
		// The bulk-edit form's nonce, as the products list hands it to the browser.
		[ $code, $list ] = $get( admin_url( 'edit.php?post_type=product' ) );
		preg_match( '/name="_wpnonce" value="([^"]+)"/', $list, $m );
		$nonce = $m[1] ?? '';
		$check( 200 === $code && '' !== $nonce, 'fixture: the products list opens and gives its bulk-edit nonce' );
		$bulk = static function ( array $ids, array $fields ) use ( $get, $nonce ): array {
			$query = array_merge( array( 'post_type' => 'product', 'action' => 'edit', 'action2' => '-1', '_wpnonce' => $nonce, 'bulk_edit' => 'Update', '_status' => '-1' ), $fields );
			$url   = add_query_arg( $query, admin_url( 'edit.php' ) );
			foreach ( $ids as $id ) {
				$url .= '&post%5B%5D=' . (int) $id;
			}
			return $get( $url );
		};

		// ---- Fixtures: two products, each in English and Arabic --------------------------------------------
		$a  = $w->simple( "{$tag}-A", null );
		$b  = $w->simple( "{$tag}-B", null );
		foreach ( array( $a => '100', $b => '50' ) as $id => $price ) {
			$p = $fresh( $id );
			$p->set_regular_price( $price );
			$p->save();
		}
		$pa = $fresh( $a );
		$pa->set_global_unique_id( '62' . wp_rand( 10000000000, 99999999999 ) );
		$pa->save();
		$gtin = $fresh( $a )->get_global_unique_id();
		$a_ar = $w->translate( 'duplicate', $a );
		$b_ar = $w->translate( 'duplicate', $b );
		$check( $a_ar > 0 && $b_ar > 0, 'fixture: both products have an Arabic record' );

		// ---- AC-1: bulk price by percentage, then by amount ---------------------------------------------------
		[ $c1 ] = $bulk( array( $a, $b ), array( 'change_regular_price' => '2', '_regular_price' => '10%' ) );
		$prices = array( $fresh( $a )->get_regular_price( 'edit' ), $fresh( $b )->get_regular_price( 'edit' ), $fresh( $a_ar )->get_regular_price( 'edit' ), $fresh( $b_ar )->get_regular_price( 'edit' ) );
		$check( 302 === $c1 && array( '110', '55', '110', '55' ) === $prices, 'AC-1 a 10% increase across a selection reaches both language records (' . implode( ', ', $prices ) . ')' );
		$bulk( array( $a, $b ), array( 'change_regular_price' => '3', '_regular_price' => '5' ) );
		$prices = array( $fresh( $a )->get_regular_price( 'edit' ), $fresh( $b )->get_regular_price( 'edit' ), $fresh( $a_ar )->get_regular_price( 'edit' ), $fresh( $b_ar )->get_regular_price( 'edit' ) );
		$check( array( '105', '50', '105', '50' ) === $prices, 'AC-1 a decrease by an amount does the same (' . implode( ', ', $prices ) . ')' );

		// ---- AC-2: status and category ----------------------------------------------------------------------
		$cat = wp_insert_term( "{$tag} Skincare", 'product_cat' );
		$cat_id = is_wp_error( $cat ) ? 0 : (int) $cat['term_id'];
		$s->on_finish( static function () use ( $cat_id ) { if ( $cat_id ) { wp_delete_term( $cat_id, 'product_cat' ); } } );
		$bulk( array( $a, $b ), array( 'tax_input[product_cat][]' => (string) $cat_id ) );
		wp_cache_flush();
		$check( has_term( $cat_id, 'product_cat', $a ) && has_term( $cat_id, 'product_cat', $b ), 'AC-2 a category is assigned across a selection' );
		$statuses = array();
		foreach ( array( 'draft', 'private', 'publish' ) as $status ) {
			$bulk( array( $a, $b ), array( '_status' => $status ) );
			wp_cache_flush();
			$statuses[] = get_post_status( $a ) . '/' . get_post_status( $b );
		}
		$check( array( 'draft/draft', 'private/private', 'publish/publish' ) === $statuses, 'AC-2 unpublish, archive and publish across a selection (' . implode( ', ', $statuses ) . ')' );

		// ---- AC-3: find a product by SKU, title, barcode; an order by phone ------------------------------------
		$found = static function ( string $term ) use ( $get, $a ): bool {
			[ , $html ] = $get( add_query_arg( array( 'post_type' => 'product', 's' => $term ), admin_url( 'edit.php' ) ) );
			return 1 === preg_match( '/id="post-' . $a . '"/', $html );
		};
		$check( $found( "{$tag}-A" ), 'AC-3 found by SKU' );
		$check( $found( get_the_title( $a ) ), 'AC-3 found by title' );
		$check( $found( $gtin ), 'AC-3 found by barcode (' . $gtin . ')' );
		$order = wc_create_order();
		$order->set_billing_phone( '01112223334' );
		$order->add_product( $fresh( $a ), 1 );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		[ , $orders ] = $get( add_query_arg( array( 'page' => 'wc-orders', 's' => '01112223334', 'search-filter' => 'all' ), admin_url( 'admin.php' ) ) );
		$check( str_contains( $orders, 'id="order-' . $order->get_id() . '"' ) || str_contains( $orders, 'post=' . $order->get_id() . '&' ) || str_contains( $orders, 'id=' . $order->get_id() . '&' ), 'AC-3 an order found by the customer\'s phone in the orders list' );

		// ---- AC-4: bulk order status, export --------------------------------------------------------------------
		$o2 = wc_create_order();
		$o2->add_product( $fresh( $b ), 1 );
		$o2->calculate_totals();
		$o2->save();
		$s->track_order( $o2->get_id() );
		$order->update_status( 'processing' );
		$o2->update_status( 'processing' );
		preg_match( '/name="_wpnonce" value="([^"]+)"/', $orders, $om );
		$url = add_query_arg( array( 'page' => 'wc-orders', 'action' => 'mark_preparing', '_wpnonce' => $om[1] ?? '' ), admin_url( 'admin.php' ) ) . '&id%5B%5D=' . $order->get_id() . '&id%5B%5D=' . $o2->get_id();
		$get( $url );
		wp_cache_flush();
		$st = wc_get_order( $order->get_id() )->get_status() . '/' . wc_get_order( $o2->get_id() )->get_status();
		$check( 'preparing/preparing' === $st, 'AC-4 a bulk status change moves two Confirmed orders to Preparing, through the status model (' . $st . ')' );
		$check( class_exists( '\Automattic\WooCommerce\Admin\ReportCSVExporter' ), 'AC-4 the order export is WooCommerce\'s report download (Analytics, Orders, Download)' );

		// ---- AC-5: English administration -----------------------------------------------------------------------
		update_user_meta( $admin, 'locale', 'ar' );
		[ , $screen ] = $get( admin_url( 'edit.php?post_type=product' ) );
		delete_user_meta( $admin, 'locale' );
		$check( str_contains( $screen, 'lang="en' ) && str_contains( $screen, 'Products' ) && ! str_contains( $screen, 'dir="rtl"' ), 'AC-5 the administration stays in English for a staff member who chose Arabic' );

		return $ok;
	}
);

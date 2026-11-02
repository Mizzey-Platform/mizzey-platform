<?php
/**
 * t80: the contracted data model exists where specs/040-data-model/data-model.md says (ENT rows of #273).
 *
 * Each entity's storage is checked in the running store: tables, post types, taxonomies, the named fields, and the
 * classes that keep the Mizzey parts. A record that names storage the store does not have fails here.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't80-data-model', 'ENT-01 to ENT-19: the data model as stored' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$table   = static fn ( string $name ): bool => $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $name ) ) === $wpdb->prefix . $name; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$columns = static fn ( string $name ): array => array_map( 'strval', (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . esc_sql( $wpdb->prefix . $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$record  = (string) file_get_contents( dirname( __DIR__, 3 ) . '/specs/040-data-model/data-model.md' );
		foreach ( array( 'ENT-01', 'ENT-02', 'ENT-03', 'ENT-05', 'ENT-06', 'ENT-07', 'ENT-09', 'ENT-10', 'ENT-11', 'ENT-13', 'ENT-14', 'ENT-15', 'ENT-16', 'ENT-17', 'ENT-19' ) as $id ) {
			if ( ! str_contains( $record, '| ' . $id . ' |' ) ) {
				$check( false, "the record has a line for {$id}" );
			}
		}
		$check( '' !== $record, 'the record lists every owned entity' );

		// ENT-01, ENT-02: products and variants, SKU and barcode in the lookup, one record per language.
		$lookup = $columns( 'wc_product_meta_lookup' );
		$check( post_type_exists( 'product' ) && post_type_exists( 'product_variation' ) && in_array( 'sku', $lookup, true ) && in_array( 'global_unique_id', $lookup, true ) && $table( 'icl_translations' ), 'ENT-01, ENT-02 products and variants, with SKU and barcode in the lookup and a translation table' );
		$check( class_exists( '\MizzeySite\Stock\StockIdentity' ) && class_exists( '\MizzeySite\Catalogue\CanonicalProduct' ), 'ENT-02 the stock is read through the seam by the canonical record' );

		// ENT-03: the three classifications.
		$check( taxonomy_exists( 'product_cat' ) && taxonomy_exists( 'product_brand' ) && taxonomy_exists( 'product_collection' ), 'ENT-03 categories, brands and collections' );

		// ENT-05: customers and addresses.
		$check( $table( 'wc_customer_lookup' ) && defined( '\MizzeySite\Account\AddressBook::META' ) && defined( '\MizzeySite\Accounts\MarketingConsent::META' ), 'ENT-05 customers, the address book and the consent record' );

		// ENT-06: carts.
		$check( $table( 'woocommerce_sessions' ), 'ENT-06 the cart in WooCommerce sessions' );

		// ENT-07: wishlist.
		$check( defined( '\MizzeySite\Wishlist\Wishlist::META' ) && '_mizzey_wishlist' === \MizzeySite\Wishlist\Wishlist::META, 'ENT-07 the wishlist (_mizzey_wishlist)' );

		// ENT-08 relations, ENT-09 payment, ENT-10 shipment, ENT-13 refund: on the order tables.
		$check( $table( 'wc_orders' ) && $table( 'wc_order_addresses' ) && $table( 'woocommerce_order_items' ), 'ENT-08 the order tables the record names' );
		$check( defined( '\MizzeySite\Payments\PaymentRecord::EVENTS' ) && '_mizzey_payment_events' === \MizzeySite\Payments\PaymentRecord::EVENTS && in_array( 'transaction_id', $columns( 'wc_orders' ), true ), 'ENT-09 the transaction id and the payment events' );
		$check( defined( '\MizzeySite\Emails\Shipped::CARRIER' ) && '_mizzey_carrier' === \MizzeySite\Emails\Shipped::CARRIER && '_mizzey_tracking_number' === \MizzeySite\Emails\Shipped::TRACKING, 'ENT-10 the shipment fields' );
		$check( class_exists( '\MizzeySite\Orders\RefundReference' ) && '_mizzey_payment_reference' === \MizzeySite\Orders\RefundReference::PAYMENT && '_mizzey_refund_reference' === \MizzeySite\Orders\RefundReference::REFERENCE, 'ENT-13 refunds with their payment and refund references' );

		// ENT-11: coupons.
		$check( post_type_exists( 'shop_coupon' ), 'ENT-11 promotions as coupons' );

		// ENT-14: reviews.
		$check( post_type_supports( 'product', 'comments' ) && defined( '\MizzeySite\Reviews\ReviewPhotos::META' ), 'ENT-14 reviews with ratings and photos' );

		// ENT-15: notifications.
		$emails = array_keys( WC()->mailer()->get_emails() );
		$check( in_array( 'WC_Email_Customer_Refunded_Order', $emails, true ) && in_array( 'MizzeySite_Customer_Shipped', $emails, true ) && class_exists( '\Corex\Notifications\Notification' ), 'ENT-15 email templates (WooCommerce and Mizzey) and CoreX notifications' );

		// ENT-16: roles.
		$check( null !== get_role( 'administrator' ) && null !== get_role( 'shop_manager' ) && null !== get_role( 'customer' ), 'ENT-16 the launch roles' );

		// ENT-17: the audit records.
		$activity = $columns( 'corex_activity_events' );
		$check( array() === array_diff( array( 'actor_id', 'kind', 'target_id', 'occurred_at', 'context_json' ), $activity ), 'ENT-17 CoreX Activity keeps actor, action, entity, details and time' );
		$check( class_exists( '\MizzeySite\Catalogue\PriceHistory' ) && '_mizzey_price_history' === \MizzeySite\Catalogue\PriceHistory::META, 'ENT-17 price changes with before and after values (specs/036)' );

		// ENT-19: import runs.
		$check( class_exists( '\MizzeySite\Catalogue\Import\FileImportRunRecorder' ) && interface_exists( '\MizzeySite\Catalogue\Import\ImportRunRecorder' ), 'ENT-19 each import run is recorded (run.json; CX-04 pending)' );

		return $ok;
	}
);

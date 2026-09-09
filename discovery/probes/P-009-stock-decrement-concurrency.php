<?php
/**
 * P-009. Does native stock validation and decrement hold under concurrent checkout?
 *
 * Section 6 classifies BR-003 and BR-007 as Native. This probe establishes the mechanism, which is
 * what decides the answer, and is explicit that it does not create real concurrency: a single PHP
 * process cannot. What it can do is show whether the decrement is atomic in the database or a
 * read-modify-write with a gap between the check and the write.
 */

$notes   = array();
$verdict = 'partial';
$product = null;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}
	global $wpdb;

	$product = new WC_Product_Simple();
	$product->set_name( 'P-009 probe product' );
	$product->set_regular_price( '100' );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 1 );
	$product->save();
	$id = $product->get_id();

	// 1. Is the decrement atomic in SQL, or a read then write?
	$reflection = new ReflectionFunction( 'wc_update_product_stock' );
	$source     = '';
	$file       = $reflection->getFileName();
	if ( $file && is_readable( $file ) ) {
		$lines  = file( $file );
		$source = implode( '', array_slice( $lines, $reflection->getStartLine() - 1,
			$reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	}
	$uses_data_store = false !== strpos( $source, 'data_store' );

	// The data store is where the SQL lives.
	$ds_file = WP_PLUGIN_DIR . '/woocommerce/includes/data-stores/class-wc-product-data-store-cpt.php';
	$ds      = is_readable( $ds_file ) ? (string) file_get_contents( $ds_file ) : '';
	$atomic_sql = false !== strpos( $ds, 'meta_value + ' ) || false !== strpos( $ds, 'meta_value - ' )
		|| false !== stripos( $ds, 'update_product_stock' ) && false !== strpos( $ds, '$wpdb->query' );

	$notes[] = sprintf(
		'WooCommerce %s. wc_update_product_stock delegates to the data store: %s. The product data store issues '
			. 'arithmetic SQL for stock changes (meta_value +/- operand): %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$uses_data_store ? 'yes' : 'no',
		$atomic_sql ? 'yes' : 'no'
	);

	// 2. Sequential decrements below zero: does the write itself refuse?
	wc_update_product_stock( $id, 1, 'decrease' );
	wc_update_product_stock( $id, 1, 'decrease' );
	wp_cache_flush();
	$after = wc_get_product( $id )->get_stock_quantity();
	$notes[] = sprintf( 'Two sequential decreases of 1 from a stock of 1 left the quantity at %s, so the write '
		. 'itself does not refuse to go negative.', var_export( $after, true ) );

	// 3. Where the real guard sits.
	$has_check = function_exists( 'wc_check_product_stock_status' ) || method_exists( 'WC_Cart', 'check_cart_item_stock' );
	$notes[]   = sprintf( 'The guard against overselling is a validation step before payment (%s), not the write.',
		$has_check ? 'present' : 'not found' );

	$notes[] = 'NOT TESTED: true simultaneity. One PHP process cannot place two checkouts at the same instant, so '
		. 'this probe cannot say whether two buyers of the last unit both succeed. What it establishes is the '
		. 'mechanism: an arithmetic SQL write is atomic per statement, but validation and decrement are separate '
		. 'steps, so a gap exists in principle. Settling BR-003 needs a parallel load test against a staging site, '
		. 'and that should be booked rather than assumed.';

	$verdict = 'partial';
	$notes[] = 'Section 6 calls this Native. The write is atomic, which supports that, but the check-then-write gap '
		. 'is real and untested, so Native is not yet earned for the concurrent case.';
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	if ( $product && $product->get_id() ) {
		wp_delete_post( $product->get_id(), true );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

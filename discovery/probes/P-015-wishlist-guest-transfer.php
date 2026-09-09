<?php
/**
 * P-015. Does Woo provide no wishlist, and would a guest wishlist inherit the cart's replace-not-merge risk?
 *
 * WISH-01 to WISH-04 and WISH-07 contract a wishlist, ENT-07 makes it a new table for account and
 * guest. The second half matters more than the first: whatever mechanism carries a guest wishlist
 * across login is the same class of problem as row 1's cart merge, and should be designed once.
 */

$notes   = array();
$verdict = 'partial';

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}

	// 1. Anything wishlist-shaped in core?
	$functions = array( 'wc_get_wishlist', 'wc_add_to_wishlist', 'wc_get_wishlist_items' );
	$classes   = array( 'WC_Wishlist', 'WC_Wishlist_Item', 'WC_Product_Wishlist' );
	$found_fn  = array_values( array_filter( $functions, 'function_exists' ) );
	$found_cls = array_values( array_filter( $classes, 'class_exists' ) );

	// Any wishlist table?
	global $wpdb;
	$tables = $wpdb->get_col( "SHOW TABLES LIKE '%wishlist%'" );

	$notes[] = sprintf(
		'WooCommerce %s: wishlist functions found %s; wishlist classes found %s; wishlist tables found %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$found_fn ? implode( ', ', $found_fn ) : 'none',
		$found_cls ? implode( ', ', $found_cls ) : 'none',
		$tables ? implode( ', ', $tables ) : 'none'
	);

	$nothing = ! $found_fn && ! $found_cls && ! $tables;

	// 2. The transferable part: what does Woo use to carry guest state across login?
	$has_session       = class_exists( 'WC_Session_Handler' );
	$persistent_cart   = 'yes' === get_option( 'woocommerce_cart_hash_key', 'unset' ) || $has_session;
	$session_table     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'woocommerce_sessions' ) );

	$notes[] = sprintf(
		'Guest state in Woo rides on WC_Session_Handler (%s) backed by %s, and the persistent cart is user meta. '
			. 'A guest wishlist has no equivalent, so whatever carries it across login has to be written, and it is '
			. 'the same replace-versus-merge decision as row 1. Designing the two together is cheaper than '
			. 'discovering the second one after the first is built.',
		$has_session ? 'present' : 'absent',
		$session_table ? $session_table : 'no sessions table'
	);

	if ( $nothing ) {
		$verdict = 'confirmed';
		$notes[] = 'Nothing wishlist-shaped exists in WooCommerce. WISH-01 to WISH-07 and ENT-07 are entirely custom, '
			. 'as section 7 row 13 assumes.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'Something wishlist-shaped exists and needs reading before the wishlist epic is priced as custom.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

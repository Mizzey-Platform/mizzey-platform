<?php
/**
 * Staging seed: removes what an integration scenario left behind when its run stopped before its own clean-up.
 *
 * A scenario names every fixture after its tag: the scenario's number and random digits (`t847286`, `t40_nobody_1234`,
 * `T72-PRICE-3390`, `Q164154`; `_scenario-tags.php` lists the forms), as the start of a category's or brand's slug
 * (`t847286-serums`), a product's SKU (`t847286-SKU`), a coupon's code (`t847286ten`), a user's login (`t847286c`)
 * and an order's billing address (`t847286buyer@example.invalid`). A run
 * that stops early, or whose clean-up fails part of the way, leaves them, and the store then shows them: the phone
 * menu listed "t847286 serums" on staging (25 November 2026). This step finds exactly those and removes them:
 *
 *  - terms of the catalogue's taxonomies (categories, brands, collections, tags, store attributes), in every language;
 *  - products and their variations, in every language, by SKU;
 *  - coupons, by code;
 *  - orders whose billing address is a run tag at example.invalid;
 *  - users whose login is a run tag, with an example.invalid address, who own no content;
 *  - WordPress's own "Sample Page", made by the installation with its example text (the owner, 26 November 2026: no
 *    "Sample" in the data).
 *
 * Nothing else matches the pattern: the seeds' own records start with SAMPLE-, DEMO- or sample-, and the accounts
 * are client_operator, demo_customer, staging_admin and sample_reviewer_*. With the argument `dry-run` it only lists
 * what it would remove. Idempotent: a second run finds nothing. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

require_once __DIR__ . '/_scenario-tags.php';

global $wpdb;
$dry  = isset( $args[0] ) && 'dry-run' === $args[0];
$gone = array( 'terms' => array(), 'products' => array(), 'coupons' => array(), 'orders' => array(), 'users' => array(), 'pages' => array(), 'kept' => array() );

// The fixtures' own orders and products go whatever their stock reservation, as a scenario's clean-up removes them.
add_filter( 'mizzey_order_stock_forget_permitted', '__return_true' );
add_filter( 'mizzey_stock_write_permitted', '__return_true' );

// ---- Orders, first: they hold the products ---------------------------------------------------------------------------
foreach ( wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'type' => 'shop_order', 'status' => array_keys( wc_get_order_statuses() ) ) ) as $id ) {
	$order = wc_get_order( $id );
	if ( $order instanceof WC_Order && mizzey_scenario_address( (string) $order->get_billing_email() ) ) {
		$gone['orders'][] = $order->get_id();
		$dry || $order->delete( true );
	}
}

// ---- Products and variations, every language, by SKU --------------------------------------------------------------
$rows = $wpdb->get_results( "SELECT p.ID, p.post_type, m.meta_value AS sku FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_sku' WHERE p.post_type IN ('product', 'product_variation') AND ( m.meta_value LIKE 't%' OR m.meta_value LIKE 'q%' )" ); // phpcs:ignore WordPress.DB
usort( $rows, static fn ( $a, $b ): int => strcmp( $b->post_type, $a->post_type ) ); // Variations before their products.
foreach ( $rows as $row ) {
	if ( ! mizzey_scenario_sku( (string) $row->sku ) ) {
		continue;
	}
	$gone['products'][] = (string) $row->sku;
	if ( ! $dry ) {
		$product = wc_get_product( (int) $row->ID );
		$product ? $product->delete( true ) : wp_delete_post( (int) $row->ID, true );
	}
}

// ---- Coupons, by code ---------------------------------------------------------------------------------------------
foreach ( get_posts( array( 'post_type' => 'shop_coupon', 'post_status' => 'any', 'numberposts' => -1, 'suppress_filters' => true ) ) as $coupon ) {
	if ( mizzey_scenario_tag( (string) $coupon->post_title ) ) {
		$gone['coupons'][] = $coupon->post_title;
		$dry || wp_delete_post( $coupon->ID, true );
	}
}

// ---- Terms, every language ----------------------------------------------------------------------------------------
add_filter( 'wpml_disable_term_adjust_id', '__return_true' );
$taxonomies = array_merge( array( 'product_cat', 'product_tag', 'product_brand', 'product_collection' ), function_exists( 'wc_get_attribute_taxonomy_names' ) ? wc_get_attribute_taxonomy_names() : array() );
foreach ( array_filter( $taxonomies, 'taxonomy_exists' ) as $taxonomy ) {
	// Each language's own list: WPML narrows a term query to the current language.
	$terms = array();
	foreach ( array( 'en', 'ar' ) as $lang ) {
		do_action( 'wpml_switch_language', $lang );
		$terms = array_merge( $terms, (array) get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) );
	}
	do_action( 'wpml_switch_language', 'en' );
	$terms = array_values( array_filter( $terms, static fn ( $t ): bool => $t instanceof WP_Term ) );
	// Children before their parents, so none is moved up a level on the way.
	usort( $terms, static fn ( WP_Term $a, WP_Term $b ): int => (int) ( 0 === $a->parent ) <=> (int) ( 0 === $b->parent ) );
	foreach ( is_array( $terms ) ? $terms : array() as $term ) {
		if ( $term instanceof WP_Term && mizzey_scenario_tag( rawurldecode( $term->slug ) ) ) {
			$gone['terms'][] = "{$taxonomy}:{$term->slug}";
			$dry || wp_delete_term( $term->term_id, $taxonomy );
		}
	}
}
remove_filter( 'wpml_disable_term_adjust_id', '__return_true' );

// ---- Users: a run tag at example.invalid, owning no content ---------------------------------------------------------
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( get_users( array( 'search' => '*@example.invalid', 'search_columns' => array( 'user_email' ), 'fields' => array( 'ID', 'user_login', 'user_email' ) ) ) as $user ) {
	if ( ! mizzey_scenario_tag( (string) $user->user_login ) || ! str_ends_with( strtolower( (string) $user->user_email ), '@example.invalid' ) || (int) $user->ID <= 1 ) {
		continue;
	}
	$owned = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d", $user->ID ) ); // phpcs:ignore WordPress.DB
	if ( $owned > 0 ) {
		$gone['kept'][] = "{$user->user_login} (owns {$owned} posts)";
		continue;
	}
	$gone['users'][] = (string) $user->user_login;
	$dry || wp_delete_user( (int) $user->ID );
}

// ---- WordPress's own "Sample Page", which an installation makes and nobody uses (no "Sample" in the data) ---------
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'name' => 'sample-page', 'numberposts' => -1, 'suppress_filters' => true ) ) as $page ) {
	if ( 'Sample Page' === $page->post_title && str_contains( (string) $page->post_content, 'This is an example page' ) ) {
		$gone['pages'][] = (int) $page->ID;
		$dry || wp_delete_post( $page->ID, true );
	}
}

$counts = array_map( 'count', $gone );
echo wp_json_encode( array( 'dry_run' => $dry ) + $gone ), "\n";
WP_CLI::success( sprintf( 'scenario leftovers %s: %d terms, %d products, %d coupons, %d orders, %d users, %d default pages; %d kept', $dry ? 'found' : 'removed', $counts['terms'], $counts['products'], $counts['coupons'], $counts['orders'], $counts['users'], $counts['pages'], $counts['kept'] ) );

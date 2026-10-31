<?php
/**
 * t54, launch staff roles, the part Stage 3 needs (specs/013-launch-roles, #251).
 *
 * In-process: the capabilities of the standard role set (Administrator for Owner / Super Admin, Shop manager for
 * Admin, Customer, visitors), the shop manager's editable roles, the default role for new accounts, and the refund
 * capability on the order screen's refund actions and the REST refunds routes, with refunds made by the store's own
 * code unaffected. Every user, product and order it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Roles\RefundCapability;

require_once __DIR__ . '/_bootstrap.php';

/** A user with a role, tracked for removal. */
function t54_user( Scenario $s, string $role, string $tag ): \WP_User {
	$id = wp_insert_user( array( 'user_login' => "{$tag}-{$role}", 'user_email' => "{$tag}-{$role}@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => $role ) );
	$s->track_user( (int) $id );
	return get_user_by( 'id', (int) $id );
}

/** Sign in as a user afresh: wp_set_current_user() with the same id keeps the old object and its old capabilities. */
function t54_as( int $id ): void {
	wp_set_current_user( 0 );
	wp_set_current_user( $id );
}

/** The REST answer to a request made as the current user. */
function t54_rest( string $method, string $route, array $body = array() ): \WP_REST_Response {
	$request = new \WP_REST_Request( $method, $route );
	foreach ( $body as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_get_server()->dispatch( $request );
}

/** The order screen's AJAX guard, run as the current user: the HTTP status it ends with, or 0 when it lets through. */
function t54_ajax_guard(): int {
	$status = 0;
	$doing  = static fn () => true;
	$die    = static function () use ( &$status ) {
		return static function () use ( &$status ) {
			$status = http_response_code() ?: 403;
			throw new \RuntimeException( 'died' );
		};
	};
	add_filter( 'wp_doing_ajax', $doing );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		RefundCapability::guardAjax();
	} catch ( \RuntimeException $e ) {
		$status = $status ?: 403;
	} finally {
		ob_end_clean();
		remove_filter( 'wp_doing_ajax', $doing );
		remove_filter( 'wp_die_ajax_handler', $die );
	}
	return (int) $status;
}

run(
	new Scenario( 't54-launch-roles', 'ROLE-01, ROLE-02, ROLE-07, ROLE-08, ROLE-09: launch staff roles' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		$tag   = 't54' . wp_rand( 1000, 9999 );
		$owner = get_role( 'administrator' );
		$admin = get_role( 'shop_manager' );
		$owner_level = array( 'manage_options', 'activate_plugins', 'install_plugins', 'edit_plugins', 'delete_plugins', 'switch_themes', 'edit_themes', 'edit_files', 'promote_users', 'delete_users', 'create_users' );

		// AC-1: Owner / Super Admin.
		$missing = array_values( array_filter( array_merge( $owner_level, array( RefundCapability::CAP, 'edit_shop_orders', 'manage_woocommerce', 'edit_users', 'list_users' ) ), static fn ( $cap ) => ! $owner->has_cap( $cap ) ) );
		$check( array() === $missing, 'AC-1 the Administrator role holds roles and users, sensitive settings, plugins and themes, the store, and refunds (missing: ' . implode( ',', $missing ) . ')' );

		// AC-2, AC-3: Admin is the Shop manager: the store's work, no owner-level capability.
		$store   = array( 'edit_products', 'edit_others_products', 'publish_products', 'edit_shop_orders', 'edit_others_shop_orders', 'edit_shop_coupons', 'publish_shop_coupons', 'manage_woocommerce', 'view_woocommerce_reports', 'list_users', 'create_customers', RefundCapability::CAP );
		$missing = array_values( array_filter( $store, static fn ( $cap ) => ! $admin->has_cap( $cap ) ) );
		$held    = array_values( array_filter( $owner_level, static fn ( $cap ) => $admin->has_cap( $cap ) ) );
		$check( array() === $missing, 'AC-2 the Shop manager manages products, orders, coupons, customers, the store settings and reports, and may refund (missing: ' . implode( ',', $missing ) . ')' );
		$check( array() === $held, 'AC-3 the Shop manager holds no owner-level capability (held: ' . implode( ',', $held ) . ')' );
		$manager = t54_user( $s, 'shop_manager', $tag );
		t54_as( $manager->ID );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$editable = array_keys( get_editable_roles() );
		$check( array( 'customer' ) === $editable, 'AC-3 a shop manager can give or edit only the Customer role (' . implode( ',', $editable ) . ')' );
		$a_customer = t54_user( $s, 'customer', $tag . 'c' );
		$an_admin   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$check( current_user_can( 'edit_user', $a_customer->ID ) && ( array() === $an_admin || ! current_user_can( 'edit_user', (int) $an_admin[0] ) ), "AC-2, AC-3 a shop manager can edit a customer's account, and not an administrator's" );
		$default = (string) get_option( 'default_role' );
		$staff   = array_keys( array_filter( wp_roles()->roles, static fn ( $role ) => ! empty( $role['capabilities']['edit_posts'] ) || ! empty( $role['capabilities']['manage_woocommerce'] ) ) );
		$check( ! in_array( $default, $staff, true ), "AC-3 a new account's role ({$default}) is not a staff role" );

		// AC-5: visitors and customers hold no staff capability.
		$customer = t54_user( $s, 'customer', $tag );
		$staff_caps = array( 'edit_shop_orders', 'manage_woocommerce', 'edit_products', 'edit_shop_coupons', 'list_users', 'edit_posts', RefundCapability::CAP );
		wp_set_current_user( 0 );
		$visitor_holds = array_values( array_filter( $staff_caps, 'current_user_can' ) );
		t54_as( $customer->ID );
		$customer_holds = array_values( array_filter( $staff_caps, 'current_user_can' ) );
		$check( array() === $visitor_holds && array() === $customer_holds && array( 'read' ) === array_keys( array_filter( get_role( 'customer' )->capabilities ) ), 'AC-5 a visitor and a customer hold no staff capability; the Customer role holds only read' );

		// FR-001: given once, and a removal by the owner is kept.
		$admin->remove_cap( RefundCapability::CAP );
		RefundCapability::grant();
		$kept = ! get_role( 'shop_manager' )->has_cap( RefundCapability::CAP );
		get_role( 'shop_manager' )->add_cap( RefundCapability::CAP );
		$check( $kept && '1' === get_option( RefundCapability::GRANTED ), 'FR-001 the capability is given once; after the owner removes it from Admin it is not given back' );

		// AC-4: the REST refunds routes.
		$product = $s->simple_product( 't54 product', '100', null );
		$order   = $s->order_for( $product, 1 );
		$route   = '/wc/v3/orders/' . $order->get_id() . '/refunds';
		$manager->add_cap( RefundCapability::CAP, false );
		t54_as( $manager->ID );
		$refused = t54_rest( 'POST', $route, array( 'amount' => '10', 'api_refund' => false ) );
		$none    = count( wc_get_order( $order->get_id() )->get_refunds() );
		$check( 403 === $refused->get_status() && 0 === $none && current_user_can( 'edit_shop_orders' ), "AC-4 [REST] a shop manager who edits orders but lacks the refund capability is refused ({$refused->get_status()}), and the order has no refund" );
		$manager->remove_cap( RefundCapability::CAP );
		t54_as( $manager->ID );
		$made   = t54_rest( 'POST', $route, array( 'amount' => '10', 'api_refund' => false ) );
		$refund = (int) ( $made->get_data()['id'] ?? 0 );
		$check( 201 === $made->get_status() && $refund > 0 && 1 === count( wc_get_order( $order->get_id() )->get_refunds() ), "AC-4 [REST] with the capability the refund is made ({$made->get_status()})" );
		$manager->add_cap( RefundCapability::CAP, false );
		t54_as( $manager->ID );
		$kept_refund = t54_rest( 'DELETE', $route . '/' . $refund, array( 'force' => true ) );
		$check( 403 === $kept_refund->get_status() && null !== wc_get_order( $refund ), "AC-4 [REST] deleting a refund without the capability is refused ({$kept_refund->get_status()})" );
		$read    = t54_rest( 'GET', $route )->get_status();
		$preview = t54_rest( 'POST', $route . '/preview', array( 'amount' => '5' ) )->get_status();
		$check( 200 === $read && 403 !== $preview, "AC-4 [REST] reading refunds ({$read}) and the refund preview ({$preview}), which make no refund, are not refused by the capability" );

		// AC-4: the order screen's refund and delete-refund actions.
		$check( 0 === has_action( 'wp_ajax_woocommerce_refund_line_items', array( RefundCapability::class, 'guardAjax' ) ) && 0 === has_action( 'wp_ajax_woocommerce_delete_refund', array( RefundCapability::class, 'guardAjax' ) ), 'AC-4 the guard runs before WooCommerce on the order screen\'s refund and delete-refund actions (priority 0)' );
		$denied = t54_ajax_guard();
		$manager->remove_cap( RefundCapability::CAP );
		t54_as( $manager->ID );
		$allowed = t54_ajax_guard();
		$check( 403 === $denied && 0 === $allowed, "AC-4 on the order screen a refund without the capability ends with 403 before WooCommerce acts ({$denied}); with it the guard lets it through ({$allowed})" );

		// AC-4, FR-003: a refund made by the store's own code, with nobody signed in.
		wp_set_current_user( 0 );
		$coded = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => '5', 'reason' => 't54 code refund' ) );
		$check( $coded instanceof \WC_Order_Refund, 'AC-4, FR-003 a refund made by code with nobody signed in is made (' . ( is_wp_error( $coded ) ? $coded->get_error_message() : 'refund ' . $coded->get_id() ) . ')' );

		return $ok;
	}
);

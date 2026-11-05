<?php
/**
 * t103, third-party admin styles kept to their own screens (specs/063-admin-plugin-styles, ADM-159): over HTTP as a
 * signed-in administrator, which of Paymob's and Bosta's sheets each admin screen loads, and that the order screens
 * carry our sheet after Bosta's. Every user and order it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

/** A signed-in administrator's cookies, enrolled in two-factor sign-in where it is enforced. */
function t103_admin( Scenario $s ): array {
	$id = wp_insert_user( array( 'user_login' => 't103_admin_' . wp_rand(), 'user_pass' => wp_generate_password( 32 ), 'role' => 'administrator', 'user_email' => 't103-' . wp_rand() . '@example.invalid' ) );
	$s->track_user( $id );
	if ( class_exists( '\WP2FA\Methods\TOTP' ) ) {
		require_once __DIR__ . '/_two_factor.php';
		two_factor_enrol( get_userdata( $id ) );
	}
	$expiry  = time() + HOUR_IN_SECONDS;
	$token   = \WP_Session_Tokens::get_instance( $id )->create( $expiry );
	$cookies = array();
	foreach ( array( AUTH_COOKIE => 'auth', SECURE_AUTH_COOKIE => 'secure_auth', LOGGED_IN_COOKIE => 'logged_in' ) as $name => $scheme ) {
		$cookies[] = new \WP_Http_Cookie( array( 'name' => $name, 'value' => wp_generate_auth_cookie( $id, $expiry, $scheme, $token ) ) );
	}
	return $cookies;
}

/** @return array{status:int,body:string} */
function t103_get( string $path, array $cookies ): array {
	$r = wp_remote_get( admin_url( $path ), array( 'cookies' => $cookies, 'timeout' => 120, 'redirection' => 0 ) ); // storefront-guard: exempt, an admin screen and not a storefront page.
	return is_wp_error( $r ) ? array( 'status' => 0, 'body' => $r->get_error_message() ) : array( 'status' => (int) wp_remote_retrieve_response_code( $r ), 'body' => (string) wp_remote_retrieve_body( $r ) );
}

/** Where a style handle's tag is in the page, or -1. */
function t103_at( string $body, string $id ): int {
	return preg_match( '/id=["\']' . preg_quote( $id, '/' ) . '["\']/', $body, $m, PREG_OFFSET_CAPTURE ) ? (int) $m[0][1] : -1;
}

run(
	new Scenario( 't103-admin-plugin-styles', 'ADM-159: third-party admin styles kept to their own screens' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$cookies = t103_admin( $s );
		$product = $s->simple_product( 't103 lamp', '100', null );
		$order   = $s->order_for( $product, 1 );
		$pages   = array(
			'order list'  => 'admin.php?page=wc-orders',
			'order edit'  => 'admin.php?page=wc-orders&action=edit&id=' . $order->get_id(),
			'posts'       => 'edit.php',
			'dashboard'   => 'index.php',
			'products'    => 'edit.php?post_type=product',
			'bosta'       => 'admin.php?page=wc-settings&tab=shipping&section=bosta',
			'bosta pages' => 'admin.php?page=bosta-woocommerce-create-edit-pickup',
			'paymob tabs' => 'admin.php?page=wc-settings&tab=checkout&section=paymob-main',
		);
		$got = array();
		foreach ( $pages as $name => $path ) {
			$got[ $name ] = t103_get( $path, $cookies );
		}
		$status = array_map( static fn ( $p ) => $p['status'], $got );
		$check( array( 200 ) === array_values( array_unique( $status ) ), 'setup: every admin screen answered 200 to the signed-in administrator (' . wp_json_encode( $status ) . ')' );

		$at = static fn ( string $page, string $id ) => t103_at( $got[ $page ]['body'], $id );

		// AC-1: the order list keeps WordPress's status filter row and bulk actions.
		$check( -1 === $at( 'order list', 'paymob-admin-css-css' ), 'AC-1 ADM-159 the order list does not load Paymob\'s admin sheet, which turns the status filter row into a card' );
		$check( $at( 'order list', 'myCSS-css' ) >= 0 && $at( 'order list', 'mizzey-admin-plugin-styles-css' ) > $at( 'order list', 'myCSS-css' ), 'AC-1 the order list keeps Bosta\'s sheet for its buttons and filters, and prints ours after it (' . $at( 'order list', 'myCSS-css' ) . ', ' . $at( 'order list', 'mizzey-admin-plugin-styles-css' ) . ')' );
		$body = $got['order list']['body'];
		$sheet = (string) file_get_contents( WP_PLUGIN_DIR . '/mizzey-site/assets/admin-plugin-styles.css' );
		// The sheet without its comments and spacing, so a rule reads as one string.
		$sheet = (string) preg_replace( '/\s*([{};:,])\s*/', '$1', (string) preg_replace( '#/\*.*?\*/#s', '', $sheet ) );
		$check( str_contains( $sheet, '.wp-core-ui .wrap .bulkactions .button,' ) && str_contains( $sheet, '.wrap .wp-heading-inline{font-size:23px' ) && str_contains( $sheet, 'var(--wp-admin-theme-color' ), 'AC-1 our sheet gives the title and the bulk-action buttons back to WordPress, in the admin colour scheme' );
		$check( str_contains( $sheet, '.wrap .tablenav .actions{float:left;width:auto' ) && str_contains( $sheet, '.wrap .widefat thead tr th,.wrap .widefat tfoot tr th{color:inherit' ), 'AC-1 the list\'s own filters stay on one row and its table header in WordPress\'s size' );
		$check( str_contains( $sheet, '.wrap .tablenav .bosta_status_search_tags{width:auto' ) && str_contains( $sheet, '.wrap .tablenav .bosta_custom_button.orders-button' ), 'AC-2 Bosta\'s filters are set on one row and its buttons in WordPress\'s button style' );

		// AC-3: our own filter is a checkbox the size of one.
		$check( 1 === preg_match( '/<label class="mizzey-unremitted"><input type="checkbox" name="mizzey_unremitted"/', $body ) && str_contains( $sheet, '.mizzey-unremitted input[type="checkbox"]{height:16px' ), 'AC-3 the "Cash collected, not remitted" filter is marked and sized as a checkbox' );

		// AC-4: everywhere else, neither sheet reaches WordPress's own screens.
		foreach ( array( 'posts', 'dashboard', 'products' ) as $page ) {
			$check( -1 === $at( $page, 'myCSS-css' ) && -1 === $at( $page, 'pickupsCSS-css' ) && -1 === $at( $page, 'paymob-admin-css-css' ) && -1 === $at( $page, 'mizzey-admin-plugin-styles-css' ), "AC-4 ADM-159 the {$page} screen loads neither Bosta's nor Paymob's admin sheet" );
		}

		// AC-5: each plugin's own screens keep its sheet.
		$check( $at( 'bosta', 'myCSS-css' ) >= 0 && -1 === $at( 'bosta', 'mizzey-admin-plugin-styles-css' ), 'AC-5 Bosta\'s settings section keeps its sheet, untouched by ours' );
		$check( $at( 'bosta pages', 'myCSS-css' ) >= 0 && $at( 'bosta pages', 'pickupsCSS-css' ) >= 0 && -1 === $at( 'bosta pages', 'mizzey-admin-plugin-styles-css' ), 'AC-5 Bosta\'s own pages keep its sheets, untouched by ours' );
		$check( $at( 'paymob tabs', 'paymob-admin-css-css' ) >= 0, 'AC-5 Paymob\'s settings keep its sheet' );
		$check( $at( 'order edit', 'paymob-admin-css-css' ) >= 0 && $at( 'order edit', 'myCSS-css' ) >= 0, 'AC-5 a single order keeps Paymob\'s and Bosta\'s sheets for their panels' );

		return $ok;
	}
);

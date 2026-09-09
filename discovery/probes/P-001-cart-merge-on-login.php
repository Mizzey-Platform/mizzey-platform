<?php
/**
 * P-001. On login, does Woo replace the guest cart rather than merging it?
 *
 * Section 7 row 1 calls this the most delicate logic in the storefront. CART-13 to CART-18 contract a
 * merge. The question is what the platform does unaided, so that the Mizzey merge is written against
 * the real behaviour rather than an assumed one.
 */

$notes   = array();
$verdict = 'partial';
$made    = array();
$user_id = 0;

try {
	if ( ! class_exists( 'WooCommerce' ) ) {
		throw new RuntimeException( 'WooCommerce is not active.' );
	}

	foreach ( array( 'guest', 'saved' ) as $which ) {
		$p = new WC_Product_Simple();
		$p->set_name( 'P-001 ' . $which . ' item' );
		$p->set_regular_price( '50' );
		$p->set_status( 'publish' );
		$p->save();
		$made[ $which ] = $p->get_id();
	}

	$user_id = wp_insert_user( array(
		'user_login' => 'p001probe_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 16 ),
		'role'       => 'customer',
	) );
	if ( is_wp_error( $user_id ) ) {
		throw new RuntimeException( 'Could not create the probe user: ' . $user_id->get_error_message() );
	}

	// The saved cart the user already had, in the shape Woo stores it.
	$persistent_key = '_woocommerce_persistent_cart_' . get_current_blog_id();
	update_user_meta( $user_id, $persistent_key, array(
		'cart' => array(
			md5( (string) $made['saved'] ) => array(
				'product_id'   => $made['saved'],
				'variation_id' => 0,
				'variation'    => array(),
				'quantity'     => 1,
			),
		),
	) );

	// The guest cart in the current session.
	if ( null === WC()->cart ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
	WC()->cart->add_to_cart( $made['guest'], 1 );
	$before = array_map( static function ( $i ) {
		return (int) $i['product_id'];
	}, array_values( WC()->cart->get_cart() ) );

	// Become the user and reload the cart the way a login does.
	wp_set_current_user( $user_id );
	WC()->session->set_customer_session_cookie( true );
	WC()->cart->get_cart_from_session();

	$after = array_map( static function ( $i ) {
		return (int) $i['product_id'];
	}, array_values( WC()->cart->get_cart() ) );

	$has_guest = in_array( $made['guest'], $after, true );
	$has_saved = in_array( $made['saved'], $after, true );

	$notes[] = sprintf(
		'WooCommerce %s. Guest session cart held product %d; the user had a saved persistent cart holding product '
			. '%d. Cart before login: [%s]. After becoming the user and reloading from session: [%s]. Guest item '
			. 'present: %s. Saved item present: %s.',
		defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
		$made['guest'], $made['saved'],
		implode( ', ', $before ), implode( ', ', $after ),
		$has_guest ? 'yes' : 'no',
		$has_saved ? 'yes' : 'no'
	);

	$hook_exists = has_action( 'woocommerce_cart_loaded_from_session' ) !== false;
	$notes[]     = sprintf( 'The hook the Technical Design builds the merge on, woocommerce_cart_loaded_from_session, '
		. 'is a real action in this version: %s.', did_action( 'woocommerce_cart_loaded_from_session' ) || $hook_exists !== false ? 'yes' : 'unconfirmed' );

	if ( $has_guest && $has_saved ) {
		$verdict = 'refuted';
		$notes[] = 'Both carts survived, so Woo merged them unaided and CART-13 to CART-18 may be closer to native '
			. 'than section 7 row 1 assumes.';
	} elseif ( $has_guest xor $has_saved ) {
		$verdict = 'confirmed';
		$notes[] = sprintf(
			'Exactly one cart survived: the %s one. Woo replaces rather than merges, so the merge in CART-13 to '
				. 'CART-18 is Mizzey work and section 7 row 1 is right to flag it. Whichever side is dropped is '
				. 'dropped silently, which is what makes this the delicate one.',
			$has_guest ? 'guest' : 'saved'
		);
	} else {
		$verdict = 'partial';
		$notes[] = 'Neither item survived the reload, which is a CLI session artefact rather than a storefront '
			. 'result. The replace-versus-merge question needs re-running through a real login before it is settled.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

try {
	if ( null !== WC()->cart ) {
		WC()->cart->empty_cart();
	}
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
	if ( $user_id && ! is_wp_error( $user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
	}
} catch ( Throwable $e ) {
	$notes[] = 'Cleanup warning: ' . $e->getMessage();
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

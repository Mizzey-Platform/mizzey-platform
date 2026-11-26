<?php
/**
 * Staging seed: sample reviews of every kind on the sample catalogue, so a reviewer sees how reviews and ratings look
 * (spec 066): ratings from 1 to 5, short and long, in English and Arabic, with no photo, one photo and several, and
 * products with none. Runs after both passes of 70-sample-catalog.php.
 *
 * The reviews, their customers' display names and the photos are in the private sample catalogue (`reviewers` and
 * `reviews` in catalog.json, read where step 70 reads it); without them this step says so and changes nothing. Each
 * review is written the way a customer's is, so every rule of the store applies to it:
 *
 *  - a sample customer per reviewer (`sample_reviewer_<key>`, an example.invalid address, a random password nobody
 *    keeps), made once; an account of that name this step did not make stops the run;
 *  - a delivered order for the product (D-17): placed for the customer, in the review's language, paid in cash and
 *    moved through every status up to COD Collected; only when the store's own rule (ReviewEligibility) says the
 *    customer may not review it yet. The sample stock is the ERP stand-in's figure, so these orders do not take from it;
 *  - the review, sent as that customer the way the product page's review form sends it, with its rating, held for
 *    moderation as every review is (specs/020), and marked by WooCommerce as from a verified owner; its photos sent
 *    through the review photo endpoint (D-23, specs/025); then approved, as a moderator does, which records the
 *    photos and updates the product's rating and count (REV-04) in WooCommerce;
 *  - the review's date set to the manifest's number of days ago, as staff may.
 *
 * Every record carries the `_mizzey_sample` mark. A later run with the same manifest changes nothing; a review whose
 * manifest entry changed is removed and written again; a sample review no longer listed is removed. It never touches
 * an account, order or review it did not make. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

use MizzeySite\Reviews\ReviewEligibility;

defined( 'MIZZEY_SAMPLE_MARK' ) || define( 'MIZZEY_SAMPLE_MARK', '_mizzey_sample' );

// ---- The manifest, from the server only (as step 70 reads it) ----------------------------------------------------------
$dir = (string) getenv( 'MIZZEY_STAGING_SAMPLE_DIR' );
if ( '' === $dir ) {
	$dir = is_dir( dirname( __DIR__, 2 ) . '/sample-catalog' ) ? dirname( __DIR__, 2 ) . '/sample-catalog' : rtrim( (string) getenv( 'HOME' ), '/' ) . '/mizzey-staging/sample-catalog';
}
$dir     = rtrim( $dir, '/' );
$catalog = is_readable( $dir . '/catalog.json' ) ? json_decode( (string) file_get_contents( $dir . '/catalog.json' ), true ) : null;
if ( ! is_array( $catalog ) || empty( $catalog['reviews'] ) ) {
	WP_CLI::success( "sample reviews skipped: no reviews in {$dir}/catalog.json" );
	return;
}

global $wpdb, $sitepress;
$sitepress->switch_lang( 'en', true );
// The seed stands in for the ERP's projection and its orders are not sales from it (D-19 item 5).
add_filter( 'mizzey_stock_write_permitted', '__return_true' );
// WordPress refuses a second comment from one person within seconds (a spam throttle, not a store rule).
add_filter( 'comment_flood_filter', '__return_false' );

$hash      = static fn ( $value ): string => md5( (string) wp_json_encode( $value ) );
$reviewers = array();
foreach ( (array) ( $catalog['reviewers'] ?? array() ) as $row ) {
	$reviewers[ sanitize_key( (string) $row['key'] ) ] = (string) $row['name'];
}
$file = static function ( string $relative ) use ( $dir ): string {
	$path = $dir . '/' . ltrim( $relative, '/' );
	if ( '' === $relative || false !== strpos( $relative, '..' ) || ! is_file( $path ) ) {
		WP_CLI::error( "the sample catalogue names {$relative}, which is not in {$dir}" );
	}
	return $path;
};
$record = static function ( string $sku, string $lang ): int {
	$id = (int) wc_get_product_id_by_sku( $sku );
	if ( ! $id ) {
		return 0;
	}
	$type = 'product_variation' === get_post_type( $id ) ? 'product_variation' : 'product';
	$en   = (int) apply_filters( 'wpml_object_id', $id, $type, true, 'en' );
	return 'en' === $lang ? $en : (int) apply_filters( 'wpml_object_id', $en, $type, false, $lang );
};
$rest = static function ( WP_REST_Request $request, string $what ): array {
	$answer = rest_do_request( $request );
	$data   = (array) $answer->get_data();
	if ( $answer->is_error() || $answer->get_status() >= 300 ) {
		WP_CLI::error( "{$what}: " . ( $data['message'] ?? wp_json_encode( $data ) ) );
	}
	return $data;
};
$moderator = get_users( array( 'role__in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) );
$moderator = $moderator ? (int) $moderator[0] : 0;

// ---- Sample reviews no longer listed, or listed differently, go -----------------------------------------------------
$listed = array();
foreach ( $catalog['reviews'] as $row ) {
	$listed[ (string) $row['key'] ] = $hash( array( $row, $reviewers[ sanitize_key( (string) $row['reviewer'] ) ] ?? '' ) );
}
$made = array( 'customers' => 0, 'orders' => 0, 'reviews' => 0, 'photos' => 0, 'unchanged' => 0, 'removed' => 0 );
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT comment_id, meta_value FROM {$wpdb->commentmeta} WHERE meta_key = %s", MIZZEY_SAMPLE_MARK . '_review' ) ) as $held ) { // phpcs:ignore WordPress.DB
	$key = (string) $held->meta_value;
	if ( ! isset( $listed[ $key ] ) || get_comment_meta( (int) $held->comment_id, MIZZEY_SAMPLE_MARK . '_hash', true ) !== $listed[ $key ] ) {
		wp_delete_comment( (int) $held->comment_id, true );
		++$made['removed'];
	}
}

// ---- Each review, as its customer writes it ---------------------------------------------------------------------------
$customers = array();
foreach ( $catalog['reviews'] as $row ) {
	$key  = (string) $row['key'];
	$lang = 'ar' === ( $row['lang'] ?? 'en' ) ? 'ar' : 'en';
	$who  = sanitize_key( (string) $row['reviewer'] );
	if ( ! isset( $reviewers[ $who ] ) ) {
		WP_CLI::error( "review {$key} names the reviewer {$who}, who is not listed under reviewers" );
	}
	$found = $wpdb->get_var( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", MIZZEY_SAMPLE_MARK . '_review', $key ) ); // phpcs:ignore WordPress.DB
	if ( $found ) {
		++$made['unchanged'];
		continue;
	}

	// The customer, made once.
	if ( ! isset( $customers[ $who ] ) ) {
		$login = 'sample_reviewer_' . $who;
		$user  = get_user_by( 'login', $login );
		if ( $user && get_user_meta( $user->ID, MIZZEY_SAMPLE_MARK, true ) !== $who ) {
			WP_CLI::error( "the account {$login} exists and this step did not make it" );
		}
		if ( ! $user ) {
			$id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'user_email' => 'sample-reviewer-' . $who . '@example.invalid', 'role' => 'customer', 'display_name' => $reviewers[ $who ], 'first_name' => $reviewers[ $who ], 'locale' => 'ar' === $lang ? 'ar' : 'en_US' ) );
			if ( is_wp_error( $id ) ) {
				WP_CLI::error( $id->get_error_message() );
			}
			update_user_meta( (int) $id, MIZZEY_SAMPLE_MARK, $who );
			$user = get_user_by( 'id', (int) $id );
			++$made['customers'];
		} elseif ( $user->display_name !== $reviewers[ $who ] ) {
			wp_update_user( array( 'ID' => $user->ID, 'display_name' => $reviewers[ $who ], 'first_name' => $reviewers[ $who ] ) );
		}
		$customers[ $who ] = (int) $user->ID;
	}
	$customer = $customers[ $who ];

	$product = $record( (string) $row['sku'], $lang );
	$line    = $record( (string) ( $row['variation'] ?? $row['sku'] ), $lang );
	if ( ! $product || ! $line ) {
		WP_CLI::error( "review {$key}: {$row['sku']} has no {$lang} record; run 70-sample-catalog.php (both passes) first" );
	}

	// A delivered order for it, when the store does not count one yet.
	if ( ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $customer, $product ) ) {
		// An order an earlier run placed for this review is taken on from where it stopped; otherwise one is placed.
		$held  = wc_get_orders( array( 'limit' => 1, 'customer_id' => $customer, 'meta_key' => MIZZEY_SAMPLE_MARK, 'meta_value' => $key, 'status' => array_keys( wc_get_order_statuses() ) ) );
		$order = $held ? $held[0] : null;
	}
	if ( ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $customer, $product ) && ! $order instanceof WC_Order ) {
		$order   = wc_create_order( array( 'customer_id' => $customer ) );
		$address = array( 'first_name' => $reviewers[ $who ], 'last_name' => '', 'email' => 'sample-reviewer-' . $who . '@example.invalid', 'phone' => '01000000' . str_pad( (string) ( 100 + count( $customers ) ), 3, '0', STR_PAD_LEFT ), 'address_1' => '1 Sample Street', 'city' => 'Cairo', 'state' => 'C', 'postcode' => '11511', 'country' => 'EG' );
		$order->add_product( wc_get_product( $line ), 1 );
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );
		$order->set_payment_method( 'cod' );
		$order->set_payment_method_title( 'Cash on delivery' );
		$order->set_created_via( 'staging-seed' );
		$order->set_date_created( time() - ( (int) ( $row['days_ago'] ?? 0 ) + 4 ) * DAY_IN_SECONDS );
		$order->update_meta_data( 'wpml_language', $lang );
		$order->update_meta_data( MIZZEY_SAMPLE_MARK, $key );
		$order->calculate_totals();
		$order->save();
		// The sample stock is the ERP stand-in's figure, drawn by the boards: these orders do not take from it.
		$order->get_data_store()->set_stock_reduced( $order->get_id(), true );
		++$made['orders'];
	}
	if ( isset( $order ) && $order instanceof WC_Order ) {
		// Through every step of the store's status model (specs/010): Confirmed, Preparing, Shipped, Out for
		// delivery, then COD Collected, which is how a cash order is delivered (the owner's cash route). A step the
		// order has already passed is skipped.
		$steps = array( 'processing', 'preparing', 'shipped', 'out-for-delivery', 'cod-collected' );
		$from  = array_search( $order->get_status(), $steps, true );
		foreach ( array_slice( $steps, false === $from ? 0 : $from + 1 ) as $step ) {
			$order->update_status( $step, 'Staging seed: sample order for a sample review.' );
			$order = wc_get_order( $order->get_id() );
		}
		$order = null;
		if ( null !== ReviewEligibility::refusal( $customer, $product ) ) {
			WP_CLI::error( "review {$key}: the store still does not let the customer review {$row['sku']} after a delivered order" );
		}
	}

	// The review, as the customer sends it from the product page's form: held for moderation, its rating and the
	// verified-owner mark kept by WooCommerce, which reads the rating from the form post.
	wp_set_current_user( $customer );
	$form    = array( 'rating' => (string) (int) $row['rating'], 'comment_post_ID' => (string) $product, 'comment' => (string) $row['text'], 'comment_parent' => '0' );
	$_POST   = wp_slash( $form );
	$comment = wp_handle_comment_submission( $form );
	$_POST   = array();
	if ( ! $comment instanceof WP_Comment ) {
		WP_CLI::error( "review {$key}: " . ( is_wp_error( $comment ) ? $comment->get_error_message() : 'not stored' ) );
	}
	$id = (int) $comment->comment_ID;
	update_comment_meta( $id, MIZZEY_SAMPLE_MARK . '_review', $key );
	update_comment_meta( $id, MIZZEY_SAMPLE_MARK . '_hash', $listed[ $key ] );

	// Its photos, through the review photo endpoint, while it is held.
	$photos = array_map( $file, array_map( 'strval', (array) ( $row['photos'] ?? array() ) ) );
	if ( $photos ) {
		$copies = array();
		foreach ( $photos as $photo ) {
			$copy = wp_tempnam( basename( $photo ) );
			copy( $photo, $copy );
			$copies[] = $copy;
		}
		$upload = new WP_REST_Request( 'POST', "/mizzey/v1/reviews/{$id}/photos" );
		$upload->set_file_params( array( 'photos' => array( 'name' => array_map( 'basename', $photos ), 'tmp_name' => $copies, 'size' => array_map( 'filesize', $copies ), 'error' => array_fill( 0, count( $copies ), UPLOAD_ERR_OK ) ) ) );
		$made['photos'] += (int) ( $rest( $upload, "review {$key}'s photos" )['photos'] ?? 0 );
		array_map( 'wp_delete_file', $copies );
	}

	// Approved, as a moderator does, and dated.
	wp_set_current_user( $moderator );
	wp_set_comment_status( $id, 'approve' );
	$when = time() - (int) ( $row['days_ago'] ?? 0 ) * DAY_IN_SECONDS;
	wp_update_comment( array( 'comment_ID' => $id, 'comment_date' => wp_date( 'Y-m-d H:i:s', $when ), 'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $when ) ) );
	wp_set_current_user( 0 );
	++$made['reviews'];
}

// ---- What the cards show: WooCommerce's own average and count ---------------------------------------------------------
$shown = array();
foreach ( array_unique( array_map( static fn ( array $row ): string => (string) $row['sku'], $catalog['reviews'] ) ) as $sku ) {
	$product = wc_get_product( $record( $sku, 'en' ) );
	if ( $product ) {
		WC_Comments::clear_transients( $product->get_id() );
		$product = wc_get_product( $product->get_id() );
		$shown[] = sprintf( '%s %s (%d)', $sku, number_format( (float) $product->get_average_rating(), 1 ), $product->get_review_count() );
	}
}

WP_CLI::success( sprintf( 'sample reviews: %d customers made, %d delivered orders placed, %d reviews written with %d photos, %d unchanged, %d removed; %s', $made['customers'], $made['orders'], $made['reviews'], $made['photos'], $made['unchanged'], $made['removed'], implode( ', ', $shown ) ) );

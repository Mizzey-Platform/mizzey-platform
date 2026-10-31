<?php
/**
 * t61, product reviews (specs/020-reviews, #309): who may review (D-17: a delivered purchase, one active review per
 * product), enforced on the form post and the REST comments endpoint; every review held for moderation; the rating
 * required; the average from approved reviews; the invitation after delivery, once, in the order's language.
 * In-process. Every product, order, user and comment it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Reviews\ReviewEligibility;
use MizzeySite\Reviews\ReviewInvitation;

require_once __DIR__ . '/_bootstrap.php';

/** A customer for the scenario, tracked for removal; 0 when WordPress refuses it. */
function t61_customer( Scenario $s, string $login ): int {
	$id = wp_insert_user( array( 'user_login' => $login, 'user_email' => "{$login}@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
	if ( is_wp_error( $id ) || (int) $id <= 1 ) {
		return 0;
	}
	$s->track_user( (int) $id );
	return (int) $id;
}

/** An order for the customer, in a status. */
function t61_order( Scenario $s, int $customer, \WC_Product $product, string $status, string $email = '' ): \WC_Order {
	$order = wc_create_order( array( 'customer_id' => $customer ) );
	$order->add_product( $product, 1 );
	$order->set_billing_email( '' !== $email ? $email : (string) get_userdata( $customer )->user_email );
	$order->set_billing_first_name( 'Salma' );
	$order->calculate_totals();
	$order->save();
	$s->track_order( $order->get_id() );
	return t61_walk( $order, $status );
}

/** Moves an order along the status model's path to the status (specs/010: no step may be skipped). */
function t61_walk( \WC_Order $order, string $status ): \WC_Order {
	$path = array( 'processing', 'preparing', 'shipped', 'completed' );
	$stop = array_search( $status, $path, true );
	foreach ( array_slice( $path, 0, false === $stop ? 1 : $stop + 1 ) as $step ) {
		$order = wc_get_order( $order->get_id() );
		if ( $order->get_status() !== $step ) {
			$order->update_status( $step );
		}
	}
	return wc_get_order( $order->get_id() );
}

/** A review submitted the way the product page's form does, as the user; the comment id, or the refusal's message. */
function t61_post( int $user, int $product, int $rating ) {
	wp_set_current_user( $user );
	$_POST['rating']          = (string) $rating;
	$_POST['comment_post_ID'] = (string) $product;
	$thrower         = static fn () => static function ( $message ) { throw new \RuntimeException( is_string( $message ) ? $message : 'died' ); };
	add_filter( 'wp_die_handler', $thrower );
	try {
		$id = wp_new_comment( array( 'comment_post_ID' => $product, 'comment_content' => 'Lovely texture.', 'comment_author' => 'Salma', 'comment_author_email' => (string) get_userdata( $user )->user_email, 'user_id' => $user, 'comment_type' => 'review' ), true );
		return is_wp_error( $id ) ? $id->get_error_message() : (int) $id;
	} catch ( \RuntimeException $e ) {
		return $e->getMessage();
	} finally {
		remove_filter( 'wp_die_handler', $thrower );
		unset( $_POST['rating'], $_POST['comment_post_ID'] );
		wp_set_current_user( 0 );
	}
}

run(
	new Scenario( 't61-reviews', 'REV-01 to REV-05: product reviews' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$previous = get_current_user_id();
		$s->on_finish( static function () use ( $previous ) { wp_set_current_user( $previous ); } );
		$comments = array();
		$s->on_finish( static function () use ( &$comments ) { foreach ( $comments as $c ) { wp_delete_comment( $c, true ); } } );
		$mails = array();
		$trap  = static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_wp_mail', $trap, 10 ); } );
		$reviews_before = get_option( 'woocommerce_enable_reviews' );
		$s->on_finish( static function () use ( $reviews_before ) { update_option( 'woocommerce_enable_reviews', $reviews_before ); } );
		update_option( 'woocommerce_enable_reviews', 'yes' );
		$tag     = 't61' . wp_rand( 1000, 9999 );
		$cream   = $s->simple_product( 't61 cream', '120', null );
		$balm    = $s->simple_product( 't61 balm', '60', null );
		$keen    = t61_customer( $s, "{$tag}-keen" );
		$waiting = t61_customer( $s, "{$tag}-waiting" );
		$refunded = t61_customer( $s, "{$tag}-refunded" );
		$eligible_rest = t61_customer( $s, "{$tag}-rest" );
		$by_amount     = t61_customer( $s, "{$tag}-amount" );
		t61_order( $s, $keen, $cream, 'completed' );
		t61_order( $s, $waiting, $cream, 'processing' );
		t61_order( $s, $eligible_rest, $cream, 'completed' );
		$free_customer = t61_customer( $s, "{$tag}-free" );
		$free_order    = wc_create_order( array( 'customer_id' => $free_customer ) );
		$free_order->add_product( $cream, 1 );
		foreach ( $free_order->get_items() as $free_item ) {
			$free_item->set_subtotal( 0 );
			$free_item->set_total( 0 );
			$free_item->save();
		}
		$free_order->set_billing_email( (string) get_userdata( $free_customer )->user_email );
		$free_order->calculate_totals( false );
		$free_order->save();
		$s->track_order( $free_order->get_id() );
		t61_walk( $free_order, 'completed' );
		$pair = wc_create_order( array( 'customer_id' => $by_amount ) );
		$pair->add_product( $cream, 1 );
		$pair->add_product( $balm, 1 );
		$pair->set_billing_email( (string) get_userdata( $by_amount )->user_email );
		$pair->calculate_totals();
		$pair->save();
		$s->track_order( $pair->get_id() );
		$pair = t61_walk( $pair, 'completed' );
		foreach ( $pair->get_items() as $pair_id => $pair_item ) {
			if ( (int) $pair_item->get_product_id() === $balm->get_id() ) {
				wc_create_refund( array( 'order_id' => $pair->get_id(), 'amount' => (string) $pair_item->get_total(), 'line_items' => array( $pair_id => array( 'qty' => 0, 'refund_total' => (float) $pair_item->get_total() ) ) ) );
			}
		}
		$back = t61_order( $s, $refunded, $balm, 'completed' );
		$line = (int) array_key_first( $back->get_items() );
		wc_create_refund( array( 'order_id' => $back->get_id(), 'amount' => (string) $back->get_total(), 'line_items' => array( $line => array( 'qty' => 1, 'refund_total' => (float) $back->get_total() ) ) ) );

		// REV-01, REV-02, D-17: who may review.
		$check( null === ReviewEligibility::refusal( $keen, $cream->get_id() ) && ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $waiting, $cream->get_id() ) && ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( 0, $cream->get_id() ) && ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $refunded, $balm->get_id() ) && ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $keen, $balm->get_id() ) && null === ReviewEligibility::refusal( $by_amount, $cream->get_id() ) && null === ReviewEligibility::refusal( $free_customer, $cream->get_id() ) && ReviewEligibility::NOT_ELIGIBLE === ReviewEligibility::refusal( $by_amount, $balm->get_id() ), 'REV-01, D-17 a delivered order makes its product reviewable; a processing order, a guest, a line refunded in full (by quantity, or by amount alone) and another product do not; a delivered line that cost nothing counts' );

		// The form post: refused for the ineligible, held for moderation for the eligible, once.
		$refused = t61_post( $waiting, $cream->get_id(), 5 );
		$first   = t61_post( $keen, $cream->get_id(), 4 );
		if ( is_int( $first ) ) {
			$comments[] = $first;
		}
		$second  = t61_post( $keen, $cream->get_id(), 5 );
		if ( is_int( $second ) ) {
			$comments[] = $second;
		}
		$held = is_int( $first ) ? get_comment( $first ) : null;
		$check( is_string( $refused ) && false !== strpos( $refused, 'delivered' ) && $held && '0' === (string) $held->comment_approved && is_string( $second ) && false !== strpos( $second, 'already reviewed' ), 'REV-02, REV-03, D-17 the form post refuses a customer without a delivery; a delivered customer\'s review is held for moderation; a second review of the same product is refused (' . ( is_string( $refused ) ? $refused : 'accepted' ) . ')' );

		// The REST comments endpoint.
		wp_set_current_user( $waiting );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/comments' );
		$request->set_param( 'post', $cream->get_id() );
		$request->set_param( 'content', 'Through the API.' );
		$rest = rest_do_request( $request );
		wp_set_current_user( 0 );
		$direct = ReviewEligibility::checkRest( array( 'comment_post_ID' => $cream->get_id(), 'user_id' => $waiting, 'comment_type' => '' ), $request );
		$check( 403 === $rest->get_status() && is_wp_error( $direct ) && 'mizzey_review_' . ReviewEligibility::NOT_ELIGIBLE === $direct->get_error_code(), 'D-17 the REST comments endpoint refuses a customer without a delivery (' . $rest->get_status() . ' ' . ( $rest->get_data()['code'] ?? '' ) . '), and its insert check refuses them even where the form is open' );

		// The product page: the Reviews tab and approved reviews for everyone; the form's fields only for someone who
		// may review, the reason otherwise.
		$GLOBALS['post'] = get_post( $cream->get_id() );
		setup_postdata( $GLOBALS['post'] );
		$base = array( 'title_reply' => 'Add a review', 'comment_field' => '<textarea name="comment"></textarea>', 'fields' => array( 'author' => 'x' ), 'submit_button' => '<input type="submit" />' );
		wp_set_current_user( $waiting );
		$open_waiting = comments_open( $cream->get_id() );
		$args_waiting = apply_filters( 'woocommerce_product_review_comment_form_args', $base );
		wp_set_current_user( $keen );
		$args_keen = apply_filters( 'woocommerce_product_review_comment_form_args', $base );
		wp_set_current_user( $eligible_rest );
		$args_open = apply_filters( 'woocommerce_product_review_comment_form_args', $base );
		wp_set_current_user( 0 );
		wp_reset_postdata();
		$check( $open_waiting && '' === $args_waiting['comment_field'] && '' === $args_waiting['submit_button'] && false !== strpos( (string) $args_waiting['comment_notes_before'], 'delivered' ) && false !== strpos( (string) $args_waiting['logged_in_as'], 'delivered' ) && false !== strpos( (string) $args_keen['comment_notes_before'], 'already reviewed' ) && $base['comment_field'] === $args_open['comment_field'], 'D-17 the Reviews tab stays open for everyone; a customer without a delivery, and one who has reviewed, see the reason in place of the form\'s fields; an eligible customer gets the form' );

		// Every route needs a rating; the REST comments endpoint keeps it; one review there too.
		$no_rating = t61_post( $eligible_rest, $cream->get_id(), 0 );
		wp_set_current_user( $eligible_rest );
		$rest_review = new \WP_REST_Request( 'POST', '/wp/v2/comments' );
		$rest_review->set_param( 'post', $cream->get_id() );
		$rest_review->set_param( 'content', 'Through the API, rated.' );
		$unrated = rest_do_request( $rest_review );
		$rest_review->set_param( 'rating', 5 );
		$rated = rest_do_request( $rest_review );
		$rated_id = (int) ( $rated->get_data()['id'] ?? 0 );
		if ( $rated_id > 0 ) {
			$comments[] = $rated_id;
		}
		$rest_review->set_param( 'content', 'And again.' );
		$twice = rest_do_request( $rest_review );
		wp_set_current_user( 0 );
		$check( is_string( $no_rating ) && false !== strpos( $no_rating, 'rating' ) && 400 === $unrated->get_status() && 201 === $rated->get_status() && 5 === (int) get_comment_meta( $rated_id, 'rating', true ) && '0' === (string) get_comment( $rated_id )->comment_approved && 'review' === get_comment( $rated_id )->comment_type && 403 === $twice->get_status(), 'REV-01, D-17 a review with no rating is refused on the form post and on REST; a rated REST review keeps its rating and is held; a second one is refused (' . $unrated->get_status() . ', ' . $rated->get_status() . ', ' . $twice->get_status() . ')' );

		// WooCommerce's REST reviews endpoint: the same rule, and no publishing without moderation rights.
		$editor = t61_customer( $s, "{$tag}-editor" );
		get_userdata( $editor )->add_cap( 'edit_products' );
		wp_set_current_user( $editor );
		$woo = new \WP_REST_Request( 'POST', '/wc/v3/products/reviews' );
		$woo->set_param( 'product_id', $cream->get_id() );
		$woo->set_param( 'review', 'Five stars, honestly.' );
		$woo->set_param( 'reviewer', 'Someone' );
		$woo->set_param( 'reviewer_email', "{$tag}-someone@example.invalid" );
		$woo->set_param( 'rating', 5 );
		$woo->set_param( 'status', 'approved' );
		$woo_answer = rest_do_request( $woo );
		$approve    = new \WP_REST_Request( 'PUT', '/wc/v3/products/reviews/' . ( is_int( $first ) ? $first : 0 ) );
		$approve->set_param( 'status', 'approved' );
		$approved   = rest_do_request( $approve );
		wp_set_current_user( 0 );
		$check( in_array( $approved->get_status(), array( 401, 403 ), true ) && is_int( $first ) && '1' !== (string) get_comment( $first )->comment_approved || ! is_int( $first ), 'D-17 a held review cannot be approved through the REST reviews update of WooCommerce by someone without moderation rights (' . $approved->get_status() . ')' );
		$check( in_array( $woo_answer->get_status(), array( 401, 403 ), true ), 'D-17 WooCommerce\'s REST reviews endpoint refuses a review from someone without moderation rights or a delivery, and an approved status from them (' . $woo_answer->get_status() . ' ' . ( $woo_answer->get_data()['code'] ?? '' ) . ')' );

		// REV-01, REV-04: the rating required, and the average from approved reviews only.
		$rating_required = get_option( 'woocommerce_review_rating_required' );
		$before = (float) wc_get_product( $cream->get_id() )->get_average_rating();
		if ( $held ) {
			wp_set_comment_status( $held->comment_ID, 'approve' );
		}
		wp_cache_flush();
		$after = (float) wc_get_product( $cream->get_id() )->get_average_rating();
		$check( 'yes' === $rating_required && 0.0 === $before && 4.0 === $after, "REV-01, REV-04 a rating is required; the average counts approved reviews only ({$before} while held, {$after} once approved)" );

		// REV-05: the invitation after delivery, once, in the order's language.
		$mails  = array();
		$guest  = t61_order( $s, 0, $balm, 'processing', "{$tag}-guest@example.invalid" );
		$to_guest = static function () use ( &$mails, $tag ): array {
			return array_values( array_filter( $mails, static fn ( $m ) => ( $m['to'] ?? '' ) === "{$tag}-guest@example.invalid" && false !== strpos( (string) ( $m['message'] ?? '' ), '#reviews' ) ) );
		};
		$guest  = t61_walk( $guest, 'completed' );
		$sent   = count( $to_guest() );
		ReviewInvitation::delivered( $guest->get_id() );
		$first_mail = $to_guest()[0] ?? array();
		$check( 1 === $sent && 1 === count( $to_guest() ) && "{$tag}-guest@example.invalid" === ( $first_mail['to'] ?? '' ) && false !== strpos( (string) ( $first_mail['message'] ?? '' ), 't61 balm' ) && false !== strpos( (string) ( $first_mail['message'] ?? '' ), '#reviews' ) && '' !== wc_get_order( $guest->get_id() )->get_meta( ReviewInvitation::SENT ), 'REV-05 a delivered order sends one invitation to its billing email, linking its products\' reviews; delivered again, none more' );
		$mails  = array();
		$arabic = wc_create_order( array( 'customer_id' => 0 ) );
		$arabic->add_product( $balm, 1 );
		$arabic->set_billing_email( "{$tag}-ar@example.invalid" );
		$arabic->update_meta_data( 'wpml_language', 'ar' );
		$arabic->calculate_totals();
		$arabic->save();
		$s->track_order( $arabic->get_id() );
		t61_walk( $arabic, 'completed' );
		$invites = array_values( array_filter( $mails, static fn ( $m ) => ( $m['to'] ?? '' ) === "{$tag}-ar@example.invalid" && false !== strpos( (string) ( $m['message'] ?? '' ), '#reviews' ) ) );
		$subject = (string) ( $invites[0]['subject'] ?? '' );
		$again_order = wc_get_order( $arabic->get_id() );
		$again_order->delete_meta_data( ReviewInvitation::SENT );
		$again_order->save_meta_data();
		$locale_before = get_locale();
		ReviewInvitation::delivered( $arabic->get_id() );
		$locale_after = get_locale();
		$check( false !== strpos( $subject, 'ما رأيك' ) && $locale_before === $locale_after, 'REV-05 the invitation of an Arabic order is in Arabic, and sending it leaves the language as it found it (' . $subject . ', ' . $locale_before . ' then ' . $locale_after . ')' );

		return $ok;
	}
);

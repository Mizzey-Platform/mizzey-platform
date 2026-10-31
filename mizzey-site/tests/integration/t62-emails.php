<?php
/**
 * t62, customer emails (specs/021-emails, #285): the welcome discount in the new account email, the retry link in the
 * failed order email, the Shipped email with carrier and tracking, once, in the order's language, and WooCommerce's own
 * order confirmed, delivered and refunded emails, also for a customer who declined marketing. In-process, through
 * WooCommerce's mailer, with mail captured. Every product, order, user and coupon it makes is removed when it finishes.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Emails\EmailAdditions;
use MizzeySite\Emails\Shipped;
use MizzeySite\Promotions\WelcomeDiscount;

require_once __DIR__ . '/_bootstrap.php';

/** Moves an order along the status model's path to the status (specs/010: no step may be skipped). */
function t62_walk( \WC_Order $order, string $status ): \WC_Order {
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

run(
	new Scenario( 't62-emails', 'NOTF-01 to NOTF-13: customer emails' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$mails = array();
		$trap  = static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_wp_mail', $trap, 10 ); } );
		$to = static function ( string $address ) use ( &$mails ): array {
			return array_values( array_filter( $mails, static fn ( $m ) => ( $m['to'] ?? '' ) === $address ) );
		};
		$tag     = 't62' . wp_rand( 1000, 9999 );
		$product = $s->simple_product( 't62 serum', '150', null );

		// NOTF-01: the new account email carries the welcome discount.
		$template = new \WC_Coupon();
		$template->set_code( "{$tag}welcome" );
		$template->set_props( array( 'discount_type' => 'percent', 'amount' => '10', 'individual_use' => true ) );
		$template->update_meta_data( WelcomeDiscount::TEMPLATE, 'yes' );
		$template->update_meta_data( WelcomeDiscount::DAYS, '14' );
		$template->save();
		$s->on_finish( static function () use ( $template ) { wp_delete_post( $template->get_id(), true ); } );
		$email   = "{$tag}-new@example.invalid";
		$created = wc_create_new_customer( $email, '', 'Pw-' . wp_generate_password( 18, false ) );
		$user_id = is_wp_error( $created ) ? 0 : (int) $created;
		if ( $user_id > 1 ) {
			$s->track_user( $user_id );
			$own = (int) get_user_meta( $user_id, WelcomeDiscount::USER_META, true );
			if ( $own > 0 ) {
				$s->on_finish( static function () use ( $own ) { wp_delete_post( $own, true ); } );
			}
		}
		$code     = $user_id > 1 ? (string) WelcomeDiscount::codeFor( $user_id ) : '';
		$welcomes = array_values( array_filter( $to( $email ), static fn ( $m ) => false !== strpos( (string) ( $m['message'] ?? '' ), 'welcome gift' ) ) );
		$check( '' !== $code && 1 === count( $welcomes ) && false !== strpos( (string) $welcomes[0]['message'], $code ) && false !== strpos( (string) $welcomes[0]['message'], '10% off' ), 'NOTF-01 the new account email names the welcome discount: what it gives and its code (' . $code . ')' );

		// An expired welcome code is not advertised.
		$own_coupon = new \WC_Coupon( $code );
		$own_coupon->set_date_expires( time() - DAY_IN_SECONDS );
		$own_coupon->save();
		$expired_line = EmailAdditions::welcome( 'Welcome.', get_userdata( $user_id ), null );
		$check( 'Welcome.' === $expired_line, 'NOTF-01 an expired welcome code is not advertised' );

		// NOTF-02, NOTF-11: order confirmed, also for a customer who declined marketing.
		$mails = array();
		$buyer = "{$tag}-buyer@example.invalid";
		$order = wc_create_order( array( 'customer_id' => $user_id ) );
		$order->add_product( $product, 1 );
		$order->set_billing_email( $buyer );
		$order->set_billing_first_name( 'Hana' );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		update_user_meta( $user_id, '_mizzey_marketing_consent', array( 'given' => false, 'at' => time(), 'source' => 'registration' ) );
		$order = t62_walk( $order, 'processing' );
		$confirmed = $to( $buyer );
		$check( 1 <= count( $confirmed ) && false !== strpos( (string) $confirmed[0]['message'], (string) $order->get_order_number() ), 'NOTF-02, NOTF-11 a placed order sends its confirmation, though the customer declined marketing (' . ( $confirmed[0]['subject'] ?? 'none' ) . ')' );

		// NOTF-04: Shipped, with the carrier and tracking number, once.
		$mails = array();
		$order->update_meta_data( Shipped::CARRIER, 'Bosta' );
		$order->update_meta_data( Shipped::TRACKING, 'BST-77001' );
		$order->save();
		$order = t62_walk( $order, 'shipped' );
		$shipped = array_values( array_filter( $to( $buyer ), static fn ( $m ) => false !== strpos( (string) ( $m['message'] ?? '' ), 'BST-77001' ) ) );
		Shipped::shipped( $order->get_id() );
		$again = array_values( array_filter( $to( $buyer ), static fn ( $m ) => false !== strpos( (string) ( $m['message'] ?? '' ), 'BST-77001' ) ) );
		$check( 1 === count( $shipped ) && 1 === count( $again ) && false !== strpos( (string) $shipped[0]['message'], 'Bosta' ) && '' !== wc_get_order( $order->get_id() )->get_meta( Shipped::SENT ), 'NOTF-04 entering Shipped sends one email with the carrier and the tracking number; again, none more' );

		// NOTF-06: delivered, WooCommerce's completed email; the review invitation is specs/020's.
		$mails = array();
		$order = t62_walk( $order, 'completed' );
		$delivered = $to( $buyer );
		$check( 1 <= count( $delivered ), 'NOTF-06 a delivered order sends its confirmation (' . implode( ' | ', array_map( static fn ( $m ) => (string) $m['subject'], $delivered ) ) . ')' );

		// NOTF-08: refunded, WooCommerce's refunded order email.
		$mails = array();
		wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => (string) $order->get_total() ) );
		$check( 1 <= count( $to( $buyer ) ), 'NOTF-08 a refund sends WooCommerce\'s refunded order email' );

		// NOTF-03: payment failed, with a link to pay again.
		$mails  = array();
		$failer = "{$tag}-failed@example.invalid";
		$failed = wc_create_order();
		$failed->add_product( $product, 1 );
		$failed->set_billing_email( $failer );
		$failed->calculate_totals();
		$failed->save();
		$s->track_order( $failed->get_id() );
		$failed->update_status( 'failed' );
		$failed_mail = $to( $failer )[0] ?? array();
		$check( false !== strpos( (string) ( $failed_mail['message'] ?? '' ), 'pay for your order here' ) && false !== strpos( (string) ( $failed_mail['message'] ?? '' ), 'order-pay' ) && 1 === preg_match( '/<a href="[^"]*order-pay[^"]*"/', (string) ( $failed_mail['message'] ?? '' ) ), 'NOTF-03 a failed payment sends the failed order email with a link to pay again' );

		// D6: the Shipped email in the order's language.
		$mails  = array();
		$arabic = wc_create_order();
		$arabic->add_product( $product, 1 );
		$arabic->set_billing_email( "{$tag}-ar@example.invalid" );
		$arabic->update_meta_data( 'wpml_language', 'ar' );
		$arabic->calculate_totals();
		$arabic->save();
		$s->track_order( $arabic->get_id() );
		t62_walk( $arabic, 'shipped' );
		$arabic_mails = array_values( array_filter( $to( "{$tag}-ar@example.invalid" ), static fn ( $m ) => false !== strpos( (string) ( $m['message'] ?? '' ), 'شركة الشحن' ) || false !== strpos( (string) ( $m['message'] ?? '' ), 'التتبع' ) ) );
		$check( 1 === count( $arabic_mails ) && false !== strpos( (string) $arabic_mails[0]['subject'], 'في الطريق' ), 'NOTF-04, D6 an Arabic order\'s Shipped email is in Arabic (' . ( $arabic_mails[0]['subject'] ?? 'none' ) . ')' );

		return $ok;
	}
);

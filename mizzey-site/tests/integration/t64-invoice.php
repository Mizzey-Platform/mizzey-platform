<?php
/**
 * t64: the invoice (ADM-92), downloaded by staff and from the account (ACCT-12). See specs/023-invoice.
 *
 * AC-1: a paid order gets an invoice number once, in sequence; an unpaid one gets none.
 * AC-2: the invoice carries the seller's details, the invoice and order numbers, the customer's billing details and
 *       the amounts charged: lines, discount, shipping, total, refunds.
 * AC-3: an Arabic order's invoice is in Arabic, right to left.
 * AC-4: [HTTP] the order's customer downloads it as a PDF from the account; another customer and a request without
 *       the nonce are refused; staff download it from the order.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;


use MizzeySite\Invoices\InvoiceDocument;
use MizzeySite\Invoices\InvoiceNumber;
use MizzeySite\Invoices\InvoicePdf;
use MizzeySite\Invoices\InvoiceRoutes;
use MizzeySite\Invoices\InvoiceSettings;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A customer with a known password. */
function t64_customer( Scenario $s, string $login, string $password ): int {
	$id = wp_insert_user( array( 'user_login' => $login, 'user_email' => "{$login}@example.invalid", 'user_pass' => $password, 'role' => 'customer' ) );
	if ( is_wp_error( $id ) || (int) $id <= 1 ) {
		return 0;
	}
	$s->track_user( (int) $id );
	return (int) $id;
}

/** An order for the customer with a 10 percent discount and shipping, left in a status. */
function t64_order( Scenario $s, int $customer, \WC_Product $product, string $status, string $language = 'en' ): \WC_Order {
	$order = wc_create_order( array( 'customer_id' => $customer ) );
	$order->add_product( $product, 2 );
	$order->set_billing_first_name( 'Mona' );
	$order->set_billing_last_name( 'Sayed' );
	$order->set_billing_address_1( '5 Tahrir Street' );
	$order->set_billing_city( 'Cairo' );
	$order->set_billing_state( 'EG-01' );
	$order->set_billing_country( 'EG' );
	$order->set_billing_phone( '01000000000' );
	$order->set_billing_email( $customer > 0 ? (string) get_userdata( $customer )->user_email : 't64-guest@example.invalid' );
	$order->update_meta_data( '_wc_billing/mizzey/district', 'Garden City' );
	$order->update_meta_data( 'wpml_language', $language );
	$shipping = new \WC_Order_Item_Shipping();
	$shipping->set_method_title( 'Flat' );
	$shipping->set_total( '50' );
	$order->add_item( $shipping );
	$order->calculate_totals( false );
	foreach ( $order->get_items() as $item ) {
		$item->set_total( (string) ( (float) $item->get_subtotal() * 0.9 ) );
		$item->save();
	}
	$order->calculate_totals( false );
	$order->save();
	$s->track_order( $order->get_id() );
	if ( 'pending' !== $status ) {
		$order->update_status( $status );
	}
	return wc_get_order( $order->get_id() );
}

run(
	new Scenario( 't64-invoice', 'ADM-92, ACCT-12: the invoice' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		sign_in_options(
			$s,
			array(
				InvoiceSettings::NAME     => 't64 Trading Company',
				InvoiceSettings::ADDRESS  => '12 Nile Street, Cairo',
				InvoiceSettings::TAX_ID   => '123-456-789',
				InvoiceSettings::REGISTER => 'CR 98765',
				InvoiceSettings::PREFIX   => 'T64-',
			)
		);
		// The counter is the store's: put back as it was.
		sign_in_keep_option( $s, InvoiceNumber::COUNTER );
		$tag      = 't64' . wp_rand( 1000, 9999 );
		$password = 'Pw-' . wp_generate_password( 18, false );
		$product  = $s->simple_product( 't64 serum', '100', null );
		$buyer    = t64_customer( $s, "{$tag}-buyer", $password );
		$other    = t64_customer( $s, "{$tag}-other", $password );

		// AC-1: numbers.
		$first   = t64_order( $s, $buyer, $product, 'processing' );
		$second  = t64_order( $s, $buyer, $product, 'processing' );
		$unpaid  = t64_order( $s, $buyer, $product, 'pending' );
		$number1 = InvoiceNumber::of( $first->get_id() );
		$number2 = InvoiceNumber::of( $second->get_id() );
		$again   = InvoiceNumber::issue( $first->get_id() );
		update_option( InvoiceNumber::COUNTER, (string) (int) substr( $number1, 4 ), false );
		$third   = t64_order( $s, $buyer, $product, 'processing' );
		$number3 = InvoiceNumber::of( $third->get_id() );
		$check( '' !== $number3 && ! in_array( $number3, array( $number1, $number2 ), true ), "ADM-92 with the counter set back to a number already used, the next order still gets a number no other order holds ({$number3})" );
		// A trashed order keeps its number: the counter set back to it, the next order still gets another.
		$trashed = t64_order( $s, $buyer, $product, 'processing' );
		$held_by_trash = InvoiceNumber::of( $trashed->get_id() );
		wc_get_order( $trashed->get_id() )->delete( false );
		update_option( InvoiceNumber::COUNTER, (string) (int) substr( $held_by_trash, 4 ), false );
		$fourth  = t64_order( $s, $buyer, $product, 'processing' );
		$number4 = InvoiceNumber::of( $fourth->get_id() );
		$check( '' !== $held_by_trash && '' !== $number4 && $number4 !== $held_by_trash, "ADM-92 a trashed order keeps its number: with the counter set back to it, the next order gets another ({$held_by_trash}, then {$number4})" );
		$check( 1 === preg_match( '/^T64-(\d{6})$/', $number1, $a ) && 1 === preg_match( '/^T64-(\d{6})$/', $number2, $b ) && (int) $b[1] === (int) $a[1] + 1 && $again === $number1 && '' === InvoiceNumber::of( $unpaid->get_id() ) && '' === InvoiceNumber::issue( $unpaid->get_id() ) && ! InvoiceNumber::issuable( $unpaid ), "ADM-92 a paid order gets its invoice number once, the next in sequence; an unpaid one gets none ({$number1}, {$number2}, again {$again})" );

		// AC-2: what it carries. A gift-wrap fee of 15 on the first order: 180 + 50 + 15 = 245.
		$fee = new \WC_Order_Item_Fee();
		$fee->set_name( 't64 gift wrap' );
		$fee->set_total( '15' );
		$first->add_item( $fee );
		$first->calculate_totals( false );
		$first->save();
		$first = wc_get_order( $first->get_id() );
		wc_create_refund( array( 'order_id' => $first->get_id(), 'amount' => '30', 'reason' => 't64' ) );
		$html  = InvoiceDocument::html( wc_get_order( $first->get_id() ) );
		$text  = wp_strip_all_tags( str_replace( '<', ' <', $html ) );
		$wants = array( 't64 Trading Company', '12 Nile Street', '123-456-789', 'CR 98765', $number1, (string) $first->get_order_number(), 'Mona Sayed', 'Garden City', '01000000000', 't64 serum', 't64 gift wrap', '100.00', '200.00', 'Discount', '20.00', '50.00', '15.00', '245.00', 'Refunded', '30.00', '215.00' );
		$missing = array_values( array_filter( $wants, static fn ( string $want ): bool => false === strpos( $text, $want ) ) );
		$check( array() === $missing && 245.0 === (float) $first->get_total() && 'ltr' === ( InvoiceDocument::rtl( $first ) ? 'rtl' : 'ltr' ), 'ADM-92, US-10-03 the invoice carries the seller, the numbers, the billing details and the amounts charged: unit price 100, discount 20, shipping 50, a fee of 15, total 245, the refund of 30 and the net 215 (' . ( array() === $missing ? 'all there' : 'missing: ' . implode( ' | ', $missing ) ) . ')' );

		// An order in a currency of three decimals, from a request in the store's own: its amounts keep three.
		sign_in_keep_option( $s, '_wcml_settings' );
		$wcml = get_option( '_wcml_settings', array() );
		$wcml = is_array( $wcml ) ? $wcml : array();
		$wcml['currency_options']['KWD']['num_decimals'] = 3;
		update_option( '_wcml_settings', $wcml );
		// Made as WooCommerce Multilingual makes a dinar order: three decimals while it is created.
		$three = static fn (): int => 3;
		add_filter( 'wc_get_price_decimals', $three );
		$dinar = t64_order( $s, $buyer, $product, 'processing' );
		$dinar->set_currency( 'KWD' );
		foreach ( $dinar->get_items() as $dinar_item ) {
			$dinar_item->set_subtotal( '123.456' );
			$dinar_item->set_total( '123.456' );
			$dinar_item->save();
		}
		$dinar->calculate_totals( false );
		$dinar->save();
		remove_filter( 'wc_get_price_decimals', $three );
		$dinar_text = wp_strip_all_tags( str_replace( '<', ' <', InvoiceDocument::html( wc_get_order( $dinar->get_id() ) ) ) );
		$check( false !== strpos( $dinar_text, '123.456' ) && false !== strpos( $dinar_text, '173.456' ), 'ADM-92 an order in a three-decimal currency keeps its three decimals on the invoice (123.456, and 173.456 with shipping)' );

		// AC-3: an Arabic order.
		$arabic = t64_order( $s, $buyer, $product, 'processing', 'ar' );
		// Asked for from an English request: the order's emails may have left the language switched, so it is set first.
		do_action( 'wpml_switch_language', 'en' );
		$switched    = switch_to_locale( 'en_US' );
		$arabic_html = InvoiceDocument::html( $arabic );
		$english     = InvoiceDocument::html( $first );
		if ( $switched ) {
			restore_previous_locale();
		}
		$check( InvoiceDocument::rtl( $arabic ) && false !== strpos( $arabic_html, 'dir="rtl"' ) && false !== strpos( $arabic_html, 'فاتورة' ) && false !== strpos( $arabic_html, 'رقم الفاتورة' ) && false !== strpos( $english, 'Invoice number' ) && false === strpos( $english, 'رقم الفاتورة' ), 'ADM-92 asked for from an English request, an Arabic order\'s invoice is in Arabic, right to left, and an English order\'s in English' );
		$pdf = InvoicePdf::bytes( $arabic );
		$check( str_starts_with( $pdf, '%PDF-' ) && strlen( $pdf ) > 2000 && false !== strpos( $pdf, 'DejaVuSans' ) && '' === InvoicePdf::bytes( $unpaid ), 'ADM-92 the invoice renders as a PDF; an unpaid order has none (' . strlen( $pdf ) . ' bytes)' );

		// AC-4: over HTTP.
		$signed  = sign_in_account( 'en', "{$tag}-buyer", $password );
		$orders  = two_factor_http( 'GET', wc_get_account_endpoint_url( 'orders' ), array(), $signed['cookies'] );
		$link    = 1 === preg_match( '#href="([^"]*' . InvoiceRoutes::QUERY . '=' . $first->get_id() . '[^"]*)"#', $orders['body'], $m ) ? html_entity_decode( $m[1] ) : '';
		$pending = false !== strpos( $orders['body'], InvoiceRoutes::QUERY . '=' . $unpaid->get_id() );
		$file    = '' !== $link ? two_factor_http( 'GET', $link, array(), $signed['cookies'] ) : array( 'status' => 0, 'body' => '' );
		$bare    = two_factor_http( 'GET', add_query_arg( InvoiceRoutes::QUERY, $first->get_id(), home_url( '/' ) ), array(), $signed['cookies'] );
		$intruder = sign_in_account( 'en', "{$tag}-other", $password );
		// A nonce that is the intruder's own, for the buyer's order: only the ownership check can refuse it.
		$their_cookie = '';
		foreach ( sign_in_jar( $intruder['cookies'] ) as $c ) {
			if ( LOGGED_IN_COOKIE === $c->name ) {
				$their_cookie = (string) $c->value;
			}
		}
		$_COOKIE[ LOGGED_IN_COOKIE ] = $their_cookie;
		wp_set_current_user( $other );
		$their_nonce = wp_create_nonce( InvoiceRoutes::ACTION . '_' . $first->get_id() );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		wp_set_current_user( 0 );
		$stolen = two_factor_http( 'GET', add_query_arg( array( InvoiceRoutes::QUERY => $first->get_id(), '_wpnonce' => $their_nonce ), home_url( '/' ) ), array(), $intruder['cookies'] );
		$check( '' !== $link && ! $pending && 200 === $file['status'] && str_starts_with( (string) $file['body'], '%PDF-' ) && 403 === $bare['status'] && 403 === $stolen['status'], '[HTTP] ACCT-12 the customer downloads a paid order\'s invoice as a PDF from the account\'s orders, and an unpaid order shows none; without the nonce, or as another customer with a nonce of their own, it is refused (' . $file['status'] . ', ' . $bare['status'] . ', ' . $stolen['status'] . ')' );

		$manager = wp_insert_user( array( 'user_login' => "{$tag}-manager", 'user_email' => "{$tag}-manager@example.invalid", 'user_pass' => $password, 'role' => 'shop_manager' ) );
		$manager = is_wp_error( $manager ) ? 0 : (int) $manager;
		if ( $manager > 1 ) {
			$s->track_user( $manager );
		}
		wp_set_current_user( $manager );
		$staff_actions = (array) apply_filters( 'woocommerce_admin_order_actions', array(), wc_get_order( $first->get_id() ) );
		ob_start();
		do_action( 'woocommerce_admin_order_data_after_order_details', wc_get_order( $first->get_id() ) );
		$screen = (string) ob_get_clean();
		wp_set_current_user( $other );
		$customer_actions = (array) apply_filters( 'woocommerce_admin_order_actions', array(), wc_get_order( $first->get_id() ) );
		wp_set_current_user( 0 );
		$check( isset( $staff_actions['mizzey_invoice'] ) && false !== strpos( $screen, InvoiceRoutes::QUERY . '=' . $first->get_id() ) && ! isset( $customer_actions['mizzey_invoice'] ), 'ADM-92 staff who edit orders find the invoice in the orders list and on the order\'s screen; a customer does not get the staff action' );

		sign_in_forget( array( "{$tag}-buyer", "{$tag}-other" ) );
		return $ok;
	}
);

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
 */

declare(strict_types=1);

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
		$check( 1 === preg_match( '/^T64-(\d{6})$/', $number1, $a ) && 1 === preg_match( '/^T64-(\d{6})$/', $number2, $b ) && (int) $b[1] === (int) $a[1] + 1 && $again === $number1 && '' === InvoiceNumber::of( $unpaid->get_id() ) && '' === InvoiceNumber::issue( $unpaid->get_id() ) && ! InvoiceNumber::issuable( $unpaid ), "ADM-92 a paid order gets its invoice number once, the next in sequence; an unpaid one gets none ({$number1}, {$number2}, again {$again})" );

		// AC-2: what it carries.
		wc_create_refund( array( 'order_id' => $first->get_id(), 'amount' => '20', 'reason' => 't64' ) );
		$html  = InvoiceDocument::html( wc_get_order( $first->get_id() ) );
		$text  = wp_strip_all_tags( str_replace( '<', ' <', $html ) );
		$money = static fn ( float $amount ): string => InvoiceDocument::money( $amount, $first );
		$wants = array( 't64 Trading Company', '12 Nile Street', '123-456-789', 'CR 98765', $number1, (string) $first->get_order_number(), 'Mona Sayed', 'Garden City', '01000000000', 't64 serum', $money( 100 ), $money( 200 ), $money( -20 ), $money( 50 ), $money( (float) $first->get_total() ), 'Refunded', $money( (float) $first->get_total() - 20 ) );
		$missing = array_values( array_filter( $wants, static fn ( string $want ): bool => false === strpos( $text, $want ) ) );
		$check( array() === $missing && 230.0 === (float) $first->get_total() && 'ltr' === ( InvoiceDocument::rtl( $first ) ? 'rtl' : 'ltr' ), 'ADM-92, US-10-03 the invoice carries the seller, the numbers, the billing details and the amounts charged: unit price 100, discount 20, shipping 50, total 230, the refund and the net (' . ( array() === $missing ? 'all there' : 'missing: ' . implode( ' | ', $missing ) ) . ')' );

		// AC-3: an Arabic order.
		$arabic      = t64_order( $s, $buyer, $product, 'processing', 'ar' );
		$arabic_html = InvoiceDocument::html( $arabic );
		$check( InvoiceDocument::rtl( $arabic ) && false !== strpos( $arabic_html, 'dir="rtl"' ) && false !== strpos( $arabic_html, 'فاتورة' ) && false !== strpos( $arabic_html, 'رقم الفاتورة' ), 'ADM-92 an Arabic order\'s invoice is in Arabic, right to left' );
		$pdf = InvoicePdf::bytes( $arabic );
		$check( str_starts_with( $pdf, '%PDF-' ) && strlen( $pdf ) > 2000 && '' === InvoicePdf::bytes( $unpaid ), 'ADM-92 the invoice renders as a PDF; an unpaid order has none (' . strlen( $pdf ) . ' bytes)' );

		// AC-4: over HTTP.
		$signed  = sign_in_account( 'en', "{$tag}-buyer", $password );
		$orders  = two_factor_http( 'GET', wc_get_account_endpoint_url( 'orders' ), array(), $signed['cookies'] );
		$link    = 1 === preg_match( '#href="([^"]*' . InvoiceRoutes::QUERY . '=' . $first->get_id() . '[^"]*)"#', $orders['body'], $m ) ? html_entity_decode( $m[1] ) : '';
		$pending = false !== strpos( $orders['body'], InvoiceRoutes::QUERY . '=' . $unpaid->get_id() );
		$file    = '' !== $link ? two_factor_http( 'GET', $link, array(), $signed['cookies'] ) : array( 'status' => 0, 'body' => '' );
		$bare    = two_factor_http( 'GET', add_query_arg( InvoiceRoutes::QUERY, $first->get_id(), home_url( '/' ) ), array(), $signed['cookies'] );
		$intruder = sign_in_account( 'en', "{$tag}-other", $password );
		$stolen   = '' !== $link ? two_factor_http( 'GET', $link, array(), $intruder['cookies'] ) : array( 'status' => 0 );
		$check( '' !== $link && ! $pending && 200 === $file['status'] && str_starts_with( (string) $file['body'], '%PDF-' ) && 403 === $bare['status'] && 403 === $stolen['status'], '[HTTP] ACCT-12 the customer downloads a paid order\'s invoice as a PDF from the account\'s orders, and an unpaid order shows none; without the nonce, or as another customer, it is refused (' . $file['status'] . ', ' . $bare['status'] . ', ' . $stolen['status'] . ')' );

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

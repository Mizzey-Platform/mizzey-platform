<?php
/**
 * t70: order management in admin (ADM-85, ADM-88 to ADM-91, ADM-93). See specs/030-order-management.
 *
 * AC-1: orders are found by number, customer, phone and email, and filtered by status, payment method, carrier and
 *       date; the filters combine.
 * AC-2: internal notes are added and never reach the customer.
 * AC-3: a shipment is attached and corrected on the order screen, noted with who did it, and shown to the customer.
 * AC-4: an order is cancelled with a reason; cancelling a paid order makes no refund by itself.
 * AC-5: a refund is subject to the refund permission (specs/013, specs/029).
 * AC-6: a packing slip is printed or downloaded for one order: items and quantities, where it goes, no prices, in the
 *       order's language; staff only.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Emails\Shipped;
use MizzeySite\Orders\CancellationField;
use MizzeySite\Orders\OrderFilters;
use MizzeySite\Orders\PackingSlip;
use MizzeySite\Orders\ShipmentBox;
use MizzeySite\Orders\StatusModel;
use MizzeySite\Roles\RefundCapability;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't70-order-management', 'ADM-85, ADM-88 to ADM-91, ADM-93: order management in admin' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$s->on_finish(
			static function (): void {
				$_GET  = array();
				$_POST = array();
				wp_set_current_user( 0 );
			}
		);
		$tag     = 't70' . wp_rand( 1000, 9999 );
		$product = $s->simple_product( 't70 serum', '120.00', null );
		$product->set_sku( "{$tag}-SKU" );
		$product->save();
		$fresh = static function ( int $id ): \WC_Order {
			wp_cache_flush();
			return wc_get_order( $id );
		};
		$make  = function ( string $method, string $carrier, string $date, string $phone, string $lang = 'en' ) use ( $s, $product, $tag, $fresh ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( $product, 2 );
			$o->set_payment_method( $method );
			$o->set_billing_first_name( 'Nour' );
			$o->set_billing_last_name( "{$tag}" );
			$o->set_billing_phone( $phone );
			$o->set_billing_email( "{$tag}-{$phone}@example.invalid" );
			$o->set_shipping_first_name( 'Nour' );
			$o->set_shipping_last_name( "{$tag}" );
			$o->set_shipping_address_1( '12 Tahrir Street' );
			$o->set_shipping_city( 'Cairo' );
			$o->set_shipping_country( 'EG' );
			$o->set_customer_note( 'Leave with the doorman' );
			$o->set_date_created( $date . ' 12:00:00' );
			$o->update_meta_data( 'wpml_language', $lang );
			if ( '' !== $carrier ) {
				$o->update_meta_data( Shipped::CARRIER, $carrier );
				$o->update_meta_data( Shipped::TRACKING, $carrier . '-' . $phone );
			}
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			return $fresh( $o->get_id() );
		};
		$a = $make( 'cod', "{$tag}Bosta", '2026-10-05', '01001110001' );
		$b = $make( 'bacs', "{$tag}Bosta", '2026-10-20', '01001110002' );
		$c = $make( 'cod', "{$tag}Aramex", '2026-10-25', '01001110003' );
		$d = $make( 'cod', '', '2026-11-01', '01001110004', 'ar' );
		$mine = array( $a->get_id(), $b->get_id(), $c->get_id(), $d->get_id() );

		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}mgr@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );

		// ---- AC-1: the list's filters, as the orders list applies them ---------------------------------------------
		$list = function ( array $get, array $extra = array() ) use ( $mine ): array {
			$_GET = $get;
			$args = OrderFilters::query( array_merge( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids', 'status' => 'any' ), $extra ) );
			$_GET = array();
			return array_values( array_intersect( array_map( 'intval', wc_get_orders( $args ) ), $mine ) );
		};
		$ids = static fn ( \WC_Order ...$orders ): array => array_map( static fn ( $o ) => $o->get_id(), $orders );
		$same = static function ( array $x, array $y ): bool { sort( $x ); sort( $y ); return $x === $y; };
		$check( $same( $list( array( 'mizzey_payment' => 'cod' ) ), $ids( $a, $c, $d ) ), 'AC-1 filter by payment method: cash on delivery finds its three orders' );
		$check( $same( $list( array( 'mizzey_carrier' => "{$tag}Bosta" ) ), $ids( $a, $b ) ), 'AC-1 filter by carrier' );
		$check( $same( $list( array( 'mizzey_from' => '2026-10-20', 'mizzey_to' => '2026-10-25' ) ), $ids( $b, $c ) ), 'AC-1 filter by a date range, both ends included (orders at noon on the first and the last day)' );
		$check( $same( $list( array( 'mizzey_payment' => 'cod', 'mizzey_carrier' => "{$tag}Bosta" ) ), $ids( $a ) ), 'AC-1 filters combine: payment and carrier' );
		$check( $same( $list( array( 'mizzey_payment' => 'cod', 'mizzey_from' => '2026-10-21' ), array( 'status' => array( 'wc-pending' ) ) ), $ids( $c, $d ) ), 'AC-1 filters combine with WooCommerce\'s status filter and an open-ended date' );
		$check( $same( $list( array( 'mizzey_from' => '2026-13-40', 'mizzey_payment' => '<script>' ) ), $ids( $a, $b, $c, $d ) ), 'AC-1 an invalid date or method is ignored, not applied' );
		$check( $same( $list( array( 'mizzey_payment' => 'does-not-exist' ) ), $ids( $a, $b, $c, $d ) ), 'AC-1 a payment method the store does not have is ignored' );
		$check( $same( $list( array( 'mizzey_to' => '2026-10-20' ) ), $ids( $a, $b ) ), 'AC-1 an end date alone finds everything up to that day' );
		$check( $same( $list( array( 'mizzey_from' => '2026-10-21', 'mizzey_to' => '2026-11-30' ), array( 'date_created' => '2026-10-01...2026-10-31' ) ), $ids( $c ) ), 'AC-1 a date range combines with the month filter WooCommerce offers: both apply' );
		$search = static fn ( string $term ): array => array_values( array_intersect( array_map( 'intval', wc_get_orders( array( 'type' => 'shop_order', 'limit' => -1, 'return' => 'ids', 's' => $term, 'search_filter' => 'all' ) ) ), $mine ) );
		$check( $same( $search( '01001110003' ), $ids( $c ) ), 'AC-1 WooCommerce\'s search finds an order by the customer\'s phone' );
		$check( $same( $search( "{$tag}-01001110002@example.invalid" ), $ids( $b ) ), 'AC-1 by email' );
		$check( in_array( $a->get_id(), $search( (string) $a->get_id() ), true ), 'AC-1 by order number' );
		$check( 4 === count( $search( $tag ) ), 'AC-1 by customer name' );
		ob_start();
		OrderFilters::render( 'shop_order', 'top' );
		$html = (string) ob_get_clean();
		$check( str_contains( $html, 'name="mizzey_payment"' ) && str_contains( $html, 'value="cod"' ) && str_contains( $html, "{$tag}Aramex" ) && str_contains( $html, 'name="mizzey_from"' ) && str_contains( $html, 'name="mizzey_to"' ), 'AC-1 the list shows the payment, carrier and date filters, with the carriers in use' );
		$check( 20 === has_action( 'woocommerce_order_list_table_restrict_manage_orders', array( OrderFilters::class, 'render' ) ) && false !== has_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( OrderFilters::class, 'query' ) ), 'AC-1 the filters are hooked into WooCommerce\'s orders list' );

		// ---- AC-2: internal notes -------------------------------------------------------------------------------
		$a->add_order_note( "{$tag} internal: customer called about delivery", 0, true );
		$a->add_order_note( "{$tag} to the customer: on its way", 1, true );
		$customer_notes = array_map( static fn ( $n ) => $n->comment_content, $fresh( $a->get_id() )->get_customer_order_notes() );
		$all_notes      = array_map( static fn ( $n ) => $n->content, wc_get_order_notes( array( 'order_id' => $a->get_id() ) ) );
		$check( in_array( "{$tag} internal: customer called about delivery", $all_notes, true ) && ! in_array( "{$tag} internal: customer called about delivery", $customer_notes, true ) && in_array( "{$tag} to the customer: on its way", $customer_notes, true ), 'AC-2 an internal note is on the order and never among the notes the customer sees' );

		// ---- AC-3: shipment attached, corrected, noted, shown --------------------------------------------------
		$_POST = array( ShipmentBox::NONCE . '_nonce' => wp_create_nonce( ShipmentBox::NONCE ), 'mizzey_shipment' => array( 'carrier' => 'Bosta', 'tracking' => "{$tag}TRK1", 'url' => 'https://bosta.co/tracking-shipment/?track_num=' . $tag ) );
		do_action( 'woocommerce_process_shop_order_meta', $d->get_id(), $fresh( $d->get_id() ) );
		$_POST = array();
		$sd    = Shipped::shipment( $fresh( $d->get_id() ) );
		$notes = implode( ' | ', array_map( static fn ( $n ) => $n->content, wc_get_order_notes( array( 'order_id' => $d->get_id() ) ) ) );
		$check( 'Bosta' === $sd['carrier'] && "{$tag}TRK1" === $sd['tracking'] && str_starts_with( $sd['url'], 'https://bosta.co/' ) && str_contains( $notes, "Shipment attached by {$tag}mgr" ), 'AC-3 staff attach a shipment from the order screen; the note names who did it' );
		$_POST = array( ShipmentBox::NONCE . '_nonce' => wp_create_nonce( ShipmentBox::NONCE ), 'mizzey_shipment' => array( 'carrier' => 'Bosta', 'tracking' => "{$tag}TRK2", 'url' => 'javascript:alert(1)' ) );
		ShipmentBox::save( $d->get_id(), $fresh( $d->get_id() ) );
		$_POST = array();
		$sd    = Shipped::shipment( $fresh( $d->get_id() ) );
		$notes = implode( ' | ', array_map( static fn ( $n ) => $n->content, wc_get_order_notes( array( 'order_id' => $d->get_id() ) ) ) );
		$bad_urls = array();
		foreach ( array( '/tracking', '#tracking', 'not a web address', 'https://' ) as $n => $bad ) {
			$_POST = array( ShipmentBox::NONCE . '_nonce' => wp_create_nonce( ShipmentBox::NONCE ), 'mizzey_shipment' => array( 'carrier' => 'Bosta', 'tracking' => "{$tag}TRK2", 'url' => $bad ) );
			do_action( 'woocommerce_process_shop_order_meta', $d->get_id(), $fresh( $d->get_id() ) );
			$bad_urls[] = Shipped::shipment( $fresh( $d->get_id() ) )['url'];
		}
		$_POST = array();
		$check( array( '', '', '', '' ) === $bad_urls, 'AC-3 a tracking address that is not a full web address is not kept (' . implode( ',', $bad_urls ) . ')' );
		$check( "{$tag}TRK2" === $sd['tracking'] && '' === $sd['url'] && str_contains( $notes, "tracking number {$tag}TRK1 to {$tag}TRK2" ), 'AC-3 a wrong code is corrected and the correction noted; a non-web address is not kept' );
		$_POST = array( ShipmentBox::NONCE . '_nonce' => 'bad', 'mizzey_shipment' => array( 'carrier' => 'X', 'tracking' => 'FORGED', 'url' => '' ) );
		ShipmentBox::save( $d->get_id(), $fresh( $d->get_id() ) );
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}cust", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}cust@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $customer );
		wp_set_current_user( $customer );
		$_POST[ ShipmentBox::NONCE . '_nonce' ] = wp_create_nonce( ShipmentBox::NONCE );
		ShipmentBox::save( $d->get_id(), $fresh( $d->get_id() ) );
		$_POST = array();
		wp_set_current_user( $admin );
		$check( "{$tag}TRK2" === Shipped::shipment( $fresh( $d->get_id() ) )['tracking'], 'AC-3 without the form\'s nonce, or by someone who cannot edit orders, the shipment does not change' );
		switch_to_locale( 'ar' );
		ob_start();
		do_action( 'woocommerce_order_details_after_order_table', $fresh( $d->get_id() ) );
		$view = (string) ob_get_clean();
		restore_previous_locale();
		$check( str_contains( $view, "{$tag}TRK2" ) && str_contains( $view, 'Bosta' ) && str_contains( $view, 'رقم التتبع' ), 'AC-3 the customer sees the carrier and the tracking number on the order, in Arabic on an Arabic page' );
		ob_start();
		ShipmentBox::fields( $fresh( $d->get_id() ) );
		$box = (string) ob_get_clean();
		$check( str_contains( $box, 'name="mizzey_shipment[tracking]"' ) && str_contains( $box, "{$tag}TRK2" ) && str_contains( $box, ShipmentBox::NONCE . '_nonce' ), 'AC-3 the order screen shows the shipment fields with the current values' );

		// ---- AC-4: cancel with a reason; a paid order is not refunded by it -----------------------------------
		$paid = $make( 'cod', '', '2026-11-02', '01001110005' );
		$paid->payment_complete();
		$paid = $fresh( $paid->get_id() );
		require_once WC_ABSPATH . 'includes/admin/meta-boxes/class-wc-meta-box-order-data.php';
		$_POST = array(
			'order_status'            => 'wc-cancelled',
			'_payment_method'         => $paid->get_payment_method(),
			'customer_user'           => (string) $paid->get_customer_id(),
			'order_date'              => $paid->get_date_created()->date( 'Y-m-d' ),
			'order_date_hour'         => $paid->get_date_created()->date( 'H' ),
			'order_date_minute'       => $paid->get_date_created()->date( 'i' ),
			'order_date_second'       => $paid->get_date_created()->date( 's' ),
			CancellationField::FIELD  => "{$tag} customer changed their mind",
		);
		do_action( 'woocommerce_process_shop_order_meta', $paid->get_id(), $paid );
		\WC_Meta_Box_Order_Data::save( $paid->get_id() );
		$_POST     = array();
		$cancelled = $fresh( $paid->get_id() );
		$check( 'cancelled' === $cancelled->get_status() && "{$tag} customer changed their mind" === (string) $cancelled->get_meta( StatusModel::REASON_META ) && 0 === count( $cancelled->get_refunds() ) && 0.0 === (float) $cancelled->get_total_refunded(), 'AC-4 a paid order cancelled from the order screen keeps its reason and has no refund made by the cancellation (' . $cancelled->get_status() . ')' );

		// ---- AC-5: refunds subject to the permission ----------------------------------------------------------
		$editor = (int) wp_insert_user( array( 'user_login' => "{$tag}editor", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}editor@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $editor );
		( new \WP_User( $editor ) )->add_cap( RefundCapability::CAP, false );
		$refund_order = $make( 'cod', '', '2026-11-02', '01001110006' );
		$refund_order->payment_complete();
		$rest = static function ( string $amount ) use ( $refund_order ): \WP_REST_Response {
			$r = new \WP_REST_Request( 'POST', '/wc/v3/orders/' . $refund_order->get_id() . '/refunds' );
			$r->set_param( 'amount', $amount );
			$r->set_param( 'api_refund', false );
			return rest_get_server()->dispatch( $r );
		};
		wp_set_current_user( $editor );
		$refused = $rest( '10' );
		wp_set_current_user( $admin );
		$partial = $rest( '60' );
		$full    = $rest( (string) ( (float) $fresh( $refund_order->get_id() )->get_total() - 60 ) );
		$check( user_can( $editor, 'edit_shop_orders' ) && 403 === $refused->get_status() && 201 === $partial->get_status() && 201 === $full->get_status() && 'refunded' === $fresh( $refund_order->get_id() )->get_status(), 'AC-5 staff who edit orders without the refund permission are refused (' . $refused->get_status() . '); permitted staff refund in part and then in full (' . $partial->get_status() . ', ' . $full->get_status() . ')' );
		// Moving an order to Refunded makes WooCommerce record a full refund: the same permission, on every route.
		$by_status = $make( 'cod', '', '2026-11-02', '01001110007' );
		$by_status->payment_complete();
		wp_set_current_user( $editor );
		$put = new \WP_REST_Request( 'PUT', '/wc/v3/orders/' . $by_status->get_id() );
		$put->set_param( 'status', 'refunded' );
		$put_answer = rest_get_server()->dispatch( $put );
		$screen     = $fresh( $by_status->get_id() );
		$screen->update_status( 'refunded', '', true );
		$after_staff = $fresh( $by_status->get_id() );
		wp_set_current_user( $admin );
		$allowed = $fresh( $by_status->get_id() );
		$allowed->update_status( 'refunded', '', true );
		$check( 400 === $put_answer->get_status() && 'refunded' !== $after_staff->get_status() && 0 === count( $after_staff->get_refunds() ) && 'refunded' === $fresh( $by_status->get_id() )->get_status(), 'AC-5 staff without the refund permission cannot move an order to Refunded, over REST or by a manual change, so no full refund is recorded; permitted staff can (' . $put_answer->get_status() . ', ' . $after_staff->get_status() . ', ' . count( $after_staff->get_refunds() ) . ', then ' . $fresh( $by_status->get_id() )->get_status() . ')' );

		// ---- AC-6: the packing slip ----------------------------------------------------------------------------
		$a_fresh = $fresh( $a->get_id() );
		$slip    = PackingSlip::html( $a_fresh );
		$check( str_contains( $slip, 'Packing slip' ) && str_contains( $slip, 't70 serum' ) && str_contains( $slip, "{$tag}-SKU" ) && str_contains( $slip, '<td>2</td>' ) && str_contains( $slip, '12 Tahrir Street' ) && str_contains( $slip, '01001110001' ) && str_contains( $slip, 'Leave with the doorman' ) && str_contains( $slip, "{$tag}Bosta" ) && ! str_contains( $slip, '120.00' ) && ! str_contains( $slip, '240.00' ), 'AC-6 the slip lists the item, SKU and quantity, where it goes, the phone, the note and the carrier, and no price' );
		$ar_slip = PackingSlip::html( $fresh( $d->get_id() ) );
		$check( str_contains( $ar_slip, 'dir="rtl"' ) && str_contains( $ar_slip, 'قائمة التعبئة' ) && str_contains( $ar_slip, 'الشحن إلى' ), 'AC-6 an Arabic order\'s slip is in Arabic, right to left' );
		do_action( 'wpml_switch_language', 'en' );
		$pdf = PackingSlip::pdf( $a_fresh );
		$check( str_starts_with( $pdf, '%PDF' ) && strlen( $pdf ) > 1000, 'AC-6 the slip is produced as a PDF (' . strlen( $pdf ) . ' bytes)' );
		wc_create_refund( array( 'order_id' => $a->get_id(), 'amount' => '120', 'line_items' => array( array_key_first( $a_fresh->get_items() ) => array( 'qty' => 1, 'refund_total' => 120 ) ) ) );
		$check( str_contains( PackingSlip::html( $fresh( $a->get_id() ) ), '<td>1</td>' ), 'AC-6 an item refunded before packing is taken off the slip (2 ordered, 1 refunded, 1 to pack)' );
		$actions = apply_filters( 'woocommerce_admin_order_actions', array(), $a_fresh );
		ob_start();
		PackingSlip::orderScreen( $a_fresh );
		$button = (string) ob_get_clean();
		$check( isset( $actions['mizzey_packing_slip'] ) && str_contains( $button, 'Print packing slip' ) && str_contains( $button, PackingSlip::QUERY . '=' . $a->get_id() ), 'AC-6 the slip is offered on the order screen and in the orders list' );
		wp_set_current_user( $customer );
		$die = null;
		$handler = static function () use ( &$die ) {
			return static function ( $message ) use ( &$die ) {
				$die = (string) $message;
				throw new \RuntimeException( 'die' );
			};
		};
		add_filter( 'wp_die_handler', $handler );
		$_GET = array( PackingSlip::QUERY => (string) $a->get_id(), '_wpnonce' => wp_create_nonce( PackingSlip::ACTION . '_' . $a->get_id() ) );
		$_REQUEST = $_GET;
		try {
			PackingSlip::maybeDownload();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		$_GET = array();
		$_REQUEST = array();
		wp_set_current_user( $admin );
		$check( null !== $die && str_contains( $die, 'not available' ), 'AC-6 a customer with a valid link is refused the packing slip (staff only)' );
		$res = wp_remote_get( add_query_arg( PackingSlip::QUERY, $a->get_id(), admin_url( 'admin.php' ) ), array( 'redirection' => 0, 'timeout' => 20 ) ); // storefront-guard: exempt, a staff address that must refuse a visitor, the page itself is not read
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$check( in_array( $code, array( 302, 403 ), true ) && ( 403 === $code || str_contains( (string) wp_remote_retrieve_header( $res, 'location' ), (string) wp_parse_url( wp_login_url(), PHP_URL_PATH ) ) ), 'AC-6 over the real request a visitor is sent to sign in, never given the slip (' . $code . ')' );

		return $ok;
	}
);

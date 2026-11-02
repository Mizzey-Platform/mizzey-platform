<?php
/**
 * t69: refunds with their payment reference (RET-08, ADM-124). See specs/029-refunds.
 *
 * AC-1: permitted staff refund in full or in part, by the gateway or by hand.
 * AC-2: every refund carries the payment's reference and, where there is one, its own reference, shown on the line.
 * AC-3: a refund made in Paymob's dashboard is recorded with the payment reference and marked as such.
 * AC-4: the standard refunded-order email names the refund's reference, in the order's language.
 * AC-5: a typed reference is taken only from permitted staff on WooCommerce's refund request.
 * AC-6: no customer returns area.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Orders\RefundReference;
use MizzeySite\Payments\Paymob\PaymobProvider;
use MizzeySite\Payments\PaymentGate;
use MizzeySite\Roles\RefundCapability;

require_once __DIR__ . '/_bootstrap.php';

/** A gateway that refunds the way Paymob's plugin reports it: a note carrying the refund's id. */
final class T69Gateway extends \WC_Payment_Gateway {
	public static int $calls = 0;
	public function __construct() {
		$this->id                 = 'paymob-t69';
		$this->method_title       = 'T69 Paymob stand-in';
		$this->title              = 'T69 card';
		$this->supports           = array( 'products', 'refunds' );
		$this->enabled            = 'yes';
	}
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		self::$calls++;
		$order = wc_get_order( $order_id );
		$order->add_order_note( 'Paymob : Partial refund of ' . $amount . '<br/>Woo Order Refund ID: 1<br/>Transaction Refund ID : 778' . sprintf( '%03d', self::$calls ) );
		return true;
	}
}

run(
	new Scenario( 't69-refunds', 'RET-08, ADM-124: refunds with their payment reference' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$mails = array();
		$trap  = static function ( $short, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		add_filter( 'woocommerce_payment_gateways', static fn ( $g ) => array_merge( (array) $g, array( new T69Gateway() ) ), 99 );
		WC()->payment_gateways()->init();
		$s->on_finish(
			static function () use ( $trap ): void {
				remove_filter( 'pre_wp_mail', $trap, 10 );
				unset( $_POST['action'], $_POST['security'], $_POST[ RefundReference::FIELD ] );
				PaymentGate::releaseAll();
				wp_set_current_user( 0 );
			}
		);
		$tag     = 't69' . wp_rand( 1000, 9999 );
		$product = $s->simple_product( 't69 cleanser', '100.00', null );
		$fresh   = static function ( int $id ): \WC_Order {
			wp_cache_flush();
			return wc_get_order( $id );
		};
		$paid    = function ( string $method, string $txn, string $lang = 'en' ) use ( $s, $product, $tag, $fresh ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( $product, 1 );
			$o->set_payment_method( $method );
			$o->set_billing_email( "{$tag}-{$method}-{$lang}@example.invalid" );
			$o->update_meta_data( 'wpml_language', $lang );
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			$o->payment_complete( $txn );
			return $fresh( $o->get_id() );
		};
		$line  = static function ( \WC_Order_Refund $r ): string {
			ob_start();
			RefundReference::line( $r );
			return (string) ob_get_clean();
		};
		$mail  = static function ( string $to ) use ( &$mails ): string {
			$hit = array_values( array_filter( $mails, static fn ( $m ) => ( $m['to'] ?? '' ) === $to ) );
			return $hit ? (string) end( $hit )['message'] . ' ' . (string) end( $hit )['subject'] : '';
		};

		// Staff with and without the refund permission, as the order screen's refund request runs.
		$manager = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}mgr@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $manager );
		$no_cap  = (int) wp_insert_user( array( 'user_login' => "{$tag}nocap", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}nocap@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $no_cap );
		( new \WP_User( $no_cap ) )->add_cap( RefundCapability::CAP, false );
		$panel = static function ( int $user, string $typed ) {
			wp_set_current_user( $user );
			add_filter( 'wp_doing_ajax', '__return_true' );
			$_POST['action']                     = 'woocommerce_refund_line_items';
			$_POST['security']                   = wp_create_nonce( 'order-item' );
			$_REQUEST['security']                = $_POST['security'];
			$_POST[ RefundReference::FIELD ]     = wp_slash( $typed );
		};
		$panel_off = static function (): void {
			remove_filter( 'wp_doing_ajax', '__return_true' );
			unset( $_POST['action'], $_POST['security'], $_REQUEST['security'], $_POST[ RefundReference::FIELD ] );
			wp_set_current_user( 0 );
		};
		$check( user_can( $manager, RefundCapability::CAP ) && ! user_can( $no_cap, RefundCapability::CAP ) && user_can( $no_cap, 'edit_shop_orders' ), 'fixture: one shop manager with the refund permission, one without it who still edits orders' );

		// ---- AC-1, AC-2, AC-4: a manual refund of an order paid on delivery, reference typed by staff ----------------
		$cod = $paid( 'cod', '' );
		$panel( $manager, 'TRF-2026-0042' );
		$mails = array();
		$r1    = wc_create_refund( array( 'order_id' => $cod->get_id(), 'amount' => '30', 'reason' => 't69 manual' ) );
		$panel_off();
		$ref1  = $r1 instanceof \WC_Order_Refund ? wc_get_order( $r1->get_id() ) : null;
		$got1  = $ref1 instanceof \WC_Order_Refund ? RefundReference::of( $ref1 ) : array();
		$check( $ref1 instanceof \WC_Order_Refund && 'TRF-2026-0042' === ( $got1['reference'] ?? '' ) && RefundReference::BY_STAFF === ( $got1['source'] ?? '' ) && '' === ( $got1['payment'] ?? 'x' ), 'AC-1, AC-2 a partial manual refund keeps the reference staff typed, marked as typed; the order paid on delivery has no payment reference (' . wp_json_encode( $got1 ) . ')' );
		$check( $ref1 instanceof \WC_Order_Refund && str_contains( $line( $ref1 ), 'TRF-2026-0042 (typed by staff)' ) && str_contains( $line( $ref1 ), 'Payment reference: none on the order' ), 'AC-2 the order screen\'s refund line shows it: ' . ( $ref1 ? wp_strip_all_tags( $line( $ref1 ) ) : '' ) );
		$m1 = $mail( "{$tag}-cod-en@example.invalid" );
		$check( str_contains( $m1, 'Refund reference: TRF-2026-0042' ) && str_contains( $m1, 'refunded' ), 'AC-4 the customer receives the standard refunded-order email naming the reference' );

		// ---- AC-5: a typed reference needs the permission and WooCommerce's own refund request ----------------------
		$panel( $no_cap, 'TRF-NOCAP' );
		$r2 = wc_create_refund( array( 'order_id' => $cod->get_id(), 'amount' => '5', 'reason' => 't69 nocap' ) );
		$panel_off();
		$check( $r2 instanceof \WC_Order_Refund && '' === RefundReference::of( wc_get_order( $r2->get_id() ) )['reference'], 'AC-5 a reference sent by staff without the refund permission is not kept' );
		// Permitted staff, a valid nonce, but another request: only WooCommerce's refund request carries the field.
		$panel( $manager, 'TRF-OUTSIDE' );
		$_POST['action'] = 'woocommerce_add_order_note';
		$r3 = wc_create_refund( array( 'order_id' => $cod->get_id(), 'amount' => '5', 'reason' => 't69 outside' ) );
		$panel_off();
		$check( $r3 instanceof \WC_Order_Refund && '' === RefundReference::of( wc_get_order( $r3->get_id() ) )['reference'], 'AC-5 the same field outside the order screen\'s refund request is not kept' );
		wp_set_current_user( $manager );
		$_POST['action']                 = 'woocommerce_refund_line_items';
		$_POST['security']               = 'not-a-nonce';
		$_POST[ RefundReference::FIELD ] = 'TRF-BADNONCE';
		add_filter( 'wp_doing_ajax', '__return_true' );
		$r4 = wc_create_refund( array( 'order_id' => $cod->get_id(), 'amount' => '5', 'reason' => 't69 nonce' ) );
		$panel_off();
		$check( $r4 instanceof \WC_Order_Refund && '' === RefundReference::of( wc_get_order( $r4->get_id() ) )['reference'], 'AC-5 a request without a valid nonce does not set a reference' );
		$panel( $manager, '<script>x</script>' . str_repeat( 'R', 150 ) );
		$r5 = wc_create_refund( array( 'order_id' => $cod->get_id(), 'amount' => '5', 'reason' => 't69 long' ) );
		$panel_off();
		$kept = $r5 instanceof \WC_Order_Refund ? RefundReference::of( wc_get_order( $r5->get_id() ) )['reference'] : '';
		$check( RefundReference::MAX === mb_strlen( $kept ) && ! str_contains( $kept, '<' ), 'FR-004 a typed reference is kept as plain text of at most 100 characters (' . mb_strlen( $kept ) . ')' );

		// The refund itself is refused to staff without the permission, on the REST API and the order screen (the guard
		// is specs/013's; checked here so this slice's AC-5 stands on its own).
		wp_set_current_user( $no_cap );
		$rest = new \WP_REST_Request( 'POST', '/wc/v3/orders/' . $cod->get_id() . '/refunds' );
		$rest->set_param( 'amount', '1' );
		$rest->set_param( 'api_refund', false );
		$before_refunds = count( $fresh( $cod->get_id() )->get_refunds() );
		$denied         = rest_get_server()->dispatch( $rest );
		$died           = null;
		$die            = static function () use ( &$died ) {
			return static function () use ( &$died ) {
				$died = http_response_code() ?: 403;
				throw new \RuntimeException( 'died' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $die );
		add_filter( 'wp_die_handler', $die );
		add_filter( 'wp_doing_ajax', '__return_true' );
		ob_start();
		try {
			RefundCapability::guardAjax();
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		ob_end_clean();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', $die );
		remove_filter( 'wp_die_handler', $die );
		wp_set_current_user( 0 );
		$check( 403 === $denied->get_status() && count( $fresh( $cod->get_id() )->get_refunds() ) === $before_refunds && null !== $died && 0 === has_action( 'wp_ajax_woocommerce_refund_line_items', array( RefundCapability::class, 'guardAjax' ) ), 'AC-5 staff without the refund permission are refused the refund by the REST API (' . $denied->get_status() . ') and on the order screen before WooCommerce acts (' . var_export( $died, true ) . ')' );

		// A reference with an apostrophe reaches a plain-text email as typed.
		$plain     = new \WC_Email_Customer_Refunded_Order();
		$plain->id = 'customer_partially_refunded_order';
		$apos      = $fresh( $cod->get_id() )->get_refunds()[0];
		$apos->update_meta_data( RefundReference::REFERENCE, "O'BRIEN-0042" );
		$apos->update_meta_data( RefundReference::SOURCE, RefundReference::BY_STAFF );
		$apos->save_meta_data();
		$plain->refund = $apos;
		ob_start();
		RefundReference::email( $fresh( $cod->get_id() ), false, true, $plain );
		$text = (string) ob_get_clean();
		$check( str_contains( $text, "Refund reference: O'BRIEN-0042" ) && ! str_contains( $text, '&#' ), 'AC-4 a plain-text refund email carries the reference exactly as typed (' . trim( $text ) . ')' );

		// ---- AC-1, AC-2, AC-4: a refund through the gateway keeps the provider's reference, in Arabic ---------------
		$card = $paid( 'paymob-t69', '991234567', 'ar' );
		$panel( $manager, 'TRF-IGNORED' );
		$mails = array();
		PaymentGate::releaseAll();
		$r6 = wc_create_refund( array( 'order_id' => $card->get_id(), 'amount' => '10', 'reason' => 't69 gateway', 'refund_payment' => true ) );
		$panel_off();
		PaymentGate::releaseAll();
		$ref6 = $r6 instanceof \WC_Order_Refund ? wc_get_order( $r6->get_id() ) : null;
		$got6 = $ref6 instanceof \WC_Order_Refund ? RefundReference::of( $ref6 ) : array();
		$check( $ref6 instanceof \WC_Order_Refund && $ref6->get_refunded_payment() && '991234567' === ( $got6['payment'] ?? '' ) && '778001' === ( $got6['reference'] ?? '' ) && RefundReference::BY_GATEWAY === ( $got6['source'] ?? '' ), 'AC-1, AC-2 a partial refund through the gateway keeps the payment\'s reference and the provider\'s refund reference; a typed one is not kept (' . ( is_wp_error( $r6 ) ? $r6->get_error_message() : wp_json_encode( $got6 ) ) . ')' );
		$m6 = $mail( "{$tag}-paymob-t69-ar@example.invalid" );
		do_action( 'wpml_switch_language', 'en' );
		switch_to_locale( 'en_US' );
		$check( $ref6 instanceof \WC_Order_Refund && str_contains( $line( $ref6 ), 'Payment reference: 991234567' ) && str_contains( $line( $ref6 ), '778001 (made through the payment gateway)' ), 'AC-2 its line: ' . ( $ref6 ? wp_strip_all_tags( $line( $ref6 ) ) : '' ) );
		$check( str_contains( $m6, 'مرجع الاسترداد: 778001' ), 'AC-4 the Arabic order\'s refunded-order email names the refund reference in Arabic' );
		do_action( 'wpml_switch_language', 'en' );
		switch_to_locale( 'en_US' );

		// Full refund of the rest: the order becomes Refunded, the email still names the reference.
		$mails = array();
		PaymentGate::releaseAll();
		$rest  = (string) ( (float) $fresh( $card->get_id() )->get_total() - (float) $fresh( $card->get_id() )->get_total_refunded() );
		$r7    = wc_create_refund( array( 'order_id' => $card->get_id(), 'amount' => $rest, 'reason' => 't69 full', 'refund_payment' => true ) );
		PaymentGate::releaseAll();
		$check( $r7 instanceof \WC_Order_Refund && 'refunded' === $fresh( $card->get_id() )->get_status() && '778002' === RefundReference::of( wc_get_order( $r7->get_id() ) )['reference'] && str_contains( $mail( "{$tag}-paymob-t69-ar@example.invalid" ), '778002' ), 'AC-1 the full refund of the rest sets Refunded, keeps its own reference and emails it' );
		do_action( 'wpml_switch_language', 'en' );
		switch_to_locale( 'en_US' );

		// ---- AC-3: a refund made in Paymob's dashboard, recorded from Paymob's report ------------------------------
		$secret   = 't69-' . wp_generate_password( 24, false );
		$settings = get_option( PaymobProvider::SETTINGS, null );
		update_option( PaymobProvider::SETTINGS, array_merge( is_array( $settings ) ? $settings : array(), array( 'hmac_hidden' => $secret ) ) );
		$s->on_finish( static function () use ( $settings ): void {
			null === $settings ? delete_option( PaymobProvider::SETTINGS ) : update_option( PaymobProvider::SETTINGS, $settings );
			PaymentGate::$finish = null;
		} );
		$last                = null;
		PaymentGate::$finish = function ( int $status, string $word, string $location ) use ( &$last ): void {
			$last = array( 'status' => $status, 'word' => $word );
		};
		$dash = $paid( 'paymob-t69', '991234999' );
		$obj  = array(
			'id' => 991234999, 'amount_cents' => (int) round( (float) $dash->get_total() * 100 ), 'created_at' => '2026-11-02T10:00:00.000000',
			'currency' => 'EGP', 'error_occured' => false, 'has_parent_transaction' => false, 'integration_id' => 6030296,
			'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false, 'is_refunded' => true, 'refunded_amount_cents' => 2500,
			'is_standalone_payment' => true, 'is_voided' => false, 'is_refund' => false, 'is_void' => false,
			'order' => array( 'id' => 650000001, 'merchant_order_id' => $dash->get_id() . '_1793539999' ), 'owner' => 1, 'pending' => false,
			'source_data' => array( 'pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card' ), 'success' => true,
		);
		PaymentGate::releaseAll();
		PaymentGate::handle( new PaymobProvider(), 'POST', array( 'hmac' => PaymobProvider::sign( PaymobProvider::fromNotice( $obj ), $secret ) ), (string) wp_json_encode( array( 'type' => 'TRANSACTION', 'obj' => $obj ) ) );
		PaymentGate::releaseAll();
		$dash_refunds = $fresh( $dash->get_id() )->get_refunds();
		$got8         = $dash_refunds ? RefundReference::of( $dash_refunds[0] ) : array();
		$check( 'refunds reconciled' === ( $last['word'] ?? '' ) && 1 === count( $dash_refunds ) && RefundReference::BY_DASHBOARD === ( $got8['source'] ?? '' ) && '991234999' === ( $got8['payment'] ?? '' ) && str_contains( $line( $dash_refunds[0] ), 'dashboard' ), 'AC-3 a 25.00 refund made in Paymob\'s dashboard is recorded once, with the payment reference, marked as made there (' . ( $last['word'] ?? 'no answer' ) . ', ' . wp_json_encode( $got8 ) . ')' );

		// ---- The field on the order screen ----------------------------------------------------------------------
		$js = WP_PLUGIN_DIR . '/mizzey-site/assets/admin-refund-reference.js';
		$check( is_readable( $js ) && str_contains( (string) file_get_contents( $js ), 'woocommerce_refund_line_items' ) && 20 === has_action( 'admin_enqueue_scripts', array( RefundReference::class, 'field' ) ), 'FR-004 the reference field script exists and is queued on the order screen' );

		// ---- AC-6: no customer returns area ---------------------------------------------------------------------
		$items = apply_filters( 'woocommerce_account_menu_items', array( 'dashboard' => 'Dashboard', 'orders' => 'Orders' ), array() );
		$eps   = array_keys( (array) WC()->query->get_query_vars() );
		$found = array_filter( array_merge( array_keys( (array) $items ), $eps ), static fn ( $k ) => 1 === preg_match( '/return|rma/i', (string) $k ) );
		$check( array() === $found, 'AC-6 no returns area in the account and no returns endpoint (' . implode( ',', $found ) . ')' );

		return $ok;
	}
);

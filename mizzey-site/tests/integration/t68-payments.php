<?php
/**
 * t68: payments through the payment layer (PAY-01 to PAY-08, PAY-13 to PAY-17, PAY-19, INT-01). See specs/028-payments.
 *
 * The gate is driven in process with Paymob's real signature, computed here with a test secret, and with a stand-in
 * provider. Where the gate lets a request through, the provider's gateway would act; this scenario applies the same
 * change by hand (payment completed, or failed), so the next request meets the order as it would be. The run through
 * the plugin itself is a separate HTTP check once the plugin is in the runtime.
 *
 * AC-1: a second payment of a paid order is refunded in full through Paymob, once, and does not move the order; when
 *       Paymob does not refund it, staff are flagged.
 * AC-2: the same success twice, the return after the notice, and the notice of a refund made from the order screen
 *       each change the order once; a transaction held by one order cannot pay another.
 * AC-3: a pending notice changes nothing; a success for a cancelled order is flagged and not applied.
 * AC-4: unsigned, wrongly signed, other-amount, other-currency, unknown-order, other-gateway and other-shape requests
 *       are refused or stopped with no change, by notice and by return; the gate answers the real request.
 * AC-5: no card number from the payloads reaches the database or the payment log.
 * AC-6: each verified event is stored once with reference, kind, outcome, amount, currency and time.
 * AC-8: a stand-in provider is gated the same way.
 * AC-10: the settlement note exists and the order screen says paid is not received.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Payments\CallbackResult;
use MizzeySite\Payments\Paymob\CallbackAddress;
use MizzeySite\Payments\Paymob\PaymobProvider;
use MizzeySite\Payments\Paymob\PluginSurface;
use MizzeySite\Payments\PaymentEvent;
use MizzeySite\Payments\PaymentGate;
use MizzeySite\Payments\PaymentProvider;
use MizzeySite\Payments\PaymentRecord;
use MizzeySite\Payments\RefundLedger;

require_once __DIR__ . '/_bootstrap.php';

/** A full card number that must never be stored, sent inside a payload where a careless handler could keep it. */
const T68_PAN = '5123456789012346';

/** A provider that trusts a body saying `"sig":"ok"`: enough to show the gate is the provider's, not Paymob's. */
final class T68Provider implements PaymentProvider {
	public function id(): string { return 't68'; }
	public function callbackAction(): string { return 't68_callback'; }
	public function ownsOrder( \WC_Order $order ): bool { return 't68pay' === $order->get_payment_method(); }
	public function read( string $method, array $query, string $body ): CallbackResult {
		$j = json_decode( $body, true );
		if ( ! is_array( $j ) || ( $j['sig'] ?? '' ) !== 'ok' ) {
			return CallbackResult::refused( 'bad stand-in signature', 'notice', (int) ( $j['order'] ?? 0 ) );
		}
		return CallbackResult::verified( new PaymentEvent( 't68', (int) $j['order'], (string) $j['ref'], PaymentEvent::PAYMENT, PaymentEvent::SUCCESS, (int) $j['amount'], 'EGP', 'now', 'notice' ) );
	}
	public function refundReferenceInNote( string $note ): ?string { return null; }
	public function refundTransaction( string $reference, int $amountCents ): ?string { return null; }
	public function bindingProblem( \WC_Order $order, PaymentEvent $event ): ?string { return null; }
}

/** A Paymob transaction object as Paymob sends it, for an order. */
function t68_obj( int $order_id, array $over = array() ): array {
	static $n = 0;
	$n++;
	return array_replace(
		array(
			'id'                     => 990000000 + $n * 7 + wp_rand( 0, 6 ),
			'amount_cents'           => 5000,
			'created_at'             => '2026-11-01T14:10:00.000000',
			'currency'               => 'EGP',
			'error_occured'          => false,
			'has_parent_transaction' => false,
			'integration_id'         => 6030296,
			'is_3d_secure'           => true,
			'is_auth'                => false,
			'is_capture'             => false,
			'is_refunded'            => false,
			'is_standalone_payment'  => true,
			'is_voided'              => false,
			'is_refund'              => false,
			'is_void'                => false,
			'order'                  => array( 'id' => 640000000 + $n, 'merchant_order_id' => $order_id . '_' . ( 1793530000 + $n ) ),
			'owner'                  => 1,
			'pending'                => false,
			'source_data'            => array( 'pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card' ),
			'success'                => true,
			// Not a Paymob field: a full card number where a handler that stores the body would keep it.
			'card_number'            => T68_PAN,
		),
		$over
	);
}

run(
	new Scenario( 't68-payments', 'PAY-01 to PAY-08, PAY-13 to PAY-17, PAY-19, INT-01: the payment layer' ),
	function ( Scenario $s ): bool {
		global $wpdb;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$secret   = 't68-' . wp_generate_password( 24, false );
		$settings = get_option( PaymobProvider::SETTINGS, null );
		update_option( PaymobProvider::SETTINGS, array_merge( is_array( $settings ) ? $settings : array(), array( 'hmac_hidden' => $secret ) ) );
		// The plugin's own settings: a test key of Egypt's API (never sent anywhere: Paymob's API is stood in for), and
		// test mode, without which the plugin resets its gateways on the next request.
		$main = get_option( 'woocommerce_paymob-main_settings', null );
		update_option( 'woocommerce_paymob-main_settings', array_merge( is_array( $main ) ? $main : array(), array( 'sec_key' => 'egy_sk_test_t68_local_only', 'pub_key' => 'egy_pk_test_t68_local_only', 'mode' => 'test' ) ) );
		$s->on_finish(
			function () use ( $settings, $main ): void {
				null === $settings ? delete_option( PaymobProvider::SETTINGS ) : update_option( PaymobProvider::SETTINGS, $settings );
				null === $main ? delete_option( 'woocommerce_paymob-main_settings' ) : update_option( 'woocommerce_paymob-main_settings', $main );
				PaymentGate::$finish = null;
				PaymentGate::releaseAll();
			}
		);

		$last                = null;
		PaymentGate::$finish = function ( int $status, string $word, string $location ) use ( &$last ): void {
			$last = array( 'status' => $status, 'word' => $word, 'location' => $location );
		};
		$paymob = new PaymobProvider();
		$notice = function ( array $obj, ?string $hmac = null ) use ( &$last, $paymob, $secret ): ?array {
			$last = null;
			PaymentGate::releaseAll();
			$query = array( 'hmac' => $hmac ?? PaymobProvider::sign( PaymobProvider::fromNotice( $obj ), $secret ) );
			PaymentGate::handle( $paymob, 'POST', $query, (string) wp_json_encode( array( 'type' => 'TRANSACTION', 'obj' => $obj ) ) );
			return $last;
		};
		$return = function ( array $obj, bool $signed = true ) use ( &$last, $paymob, $secret ): ?array {
			$last   = null;
			PaymentGate::releaseAll();
			$fields = PaymobProvider::fromNotice( $obj );
			$query  = $fields + array( 'merchant_order_id' => $obj['order']['merchant_order_id'] );
			if ( $signed ) {
				$query['hmac'] = PaymobProvider::sign( $fields, $secret );
			}
			PaymentGate::handle( $paymob, 'GET', $query, '' );
			return $last;
		};

		$product = $s->simple_product( 'T68 payment product', '50.00', null );
		// A placed order awaiting payment, as the checkout leaves it (WooCommerce stores a new order as Pending Payment).
		$order   = function ( string $method = 'paymob' ) use ( $s, $product ): \WC_Order {
			$o = wc_create_order();
			$o->add_product( $product, 1 );
			$o->set_payment_method( $method );
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			return wc_get_order( $o->get_id() );
		};
		$fresh = fn ( int $id ): \WC_Order => wc_get_order( $id );

		// ---- AC-4: nothing unverified reaches the gateway ---------------------------------------------------------
		$a     = $order();
		$total = $a->get_total();
		$check( 5000 === (int) round( (float) $total * 100 ), 'fixture order totals 50.00 EGP (' . $total . ' ' . $a->get_currency() . ')' );

		$r = $notice( t68_obj( $a->get_id() ), '' );
		$check( 403 === ( $r['status'] ?? 0 ), 'AC-4 unsigned notice refused 403' );
		$bad = t68_obj( $a->get_id() );
		$sig = PaymobProvider::sign( PaymobProvider::fromNotice( $bad ), $secret );
		$bad['amount_cents'] = 100;
		$r = $notice( $bad, $sig );
		$check( 403 === ( $r['status'] ?? 0 ), 'AC-4 notice altered after signing (amount) refused 403' );
		$r = $notice( t68_obj( 999999999 ) );
		$check( 403 === ( $r['status'] ?? 0 ), 'AC-4 signed notice for an unknown order refused 403' );
		// A card payment for an order that now pays by cash on delivery: the money was taken, so it is recorded and
		// flagged for staff, once, and the gateway never acts on the order.
		$cod     = $order( 'cod' );
		$cod_obj = t68_obj( $cod->get_id() );
		$r       = $notice( $cod_obj );
		$cod_now = $fresh( $cod->get_id() );
		$again   = $notice( $cod_obj );
		$check( 200 === ( $r['status'] ?? 0 ) && 'pending' === $cod_now->get_status() && 1 === count( PaymentRecord::events( $cod_now ) ) && 1 === count( PaymentRecord::attention( $cod_now ) ) && 403 === ( $again['status'] ?? 0 ) && 1 === count( PaymentRecord::attention( $fresh( $cod->get_id() ) ) ), 'AC-4 a signed payment for an order of another method is recorded and flagged for staff once, not applied (' . ( $r['status'] ?? 0 ) . ', ' . $cod_now->get_status() . ')' );
		$last = null;
		PaymentGate::handle( $paymob, 'POST', array( 'hmac' => 'x' ), (string) wp_json_encode( array( 'intention' => array( 'id' => 'pi_test' ) ) ) );
		$check( 403 === ( $last['status'] ?? 0 ), 'AC-4 a notice of another shape refused 403' );
		$unsigned = t68_obj( $a->get_id(), array( 'amount_cents' => 100 ) );
		$r        = $return( $unsigned, false );
		$check( 403 === ( $r['status'] ?? 0 ) && str_contains( (string) ( $r['location'] ?? '' ), wc_get_checkout_url() ), 'AC-4 unsigned return refused and sent to the checkout' );
		$check( 'pending' === $fresh( $a->get_id() )->get_status() && $total === $fresh( $a->get_id() )->get_total(), 'AC-4 after every refusal the order is still pending at ' . $total );
		$check( array() === PaymentRecord::events( $fresh( $a->get_id() ) ), 'AC-4 refusals record no event' );

		$r = $notice( t68_obj( $a->get_id(), array( 'amount_cents' => 4000 ) ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 'pending' === $fresh( $a->get_id() )->get_status(), 'AC-4 signed success of 40.00 for a 50.00 order stopped, order still pending' );
		$check( 1 === count( PaymentRecord::attention( $fresh( $a->get_id() ) ) ), 'AC-4 the other amount is flagged for staff' );
		$r = $notice( t68_obj( $a->get_id(), array( 'currency' => 'USD' ) ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 'pending' === $fresh( $a->get_id() )->get_status() && 2 === count( PaymentRecord::attention( $fresh( $a->get_id() ) ) ), 'AC-4 signed success in another currency stopped and flagged' );

		// ---- AC-6 and AC-2: the first success goes through once ------------------------------------------------
		$b    = $order();
		$paid = t68_obj( $b->get_id() );
		$r    = $notice( $paid );
		$check( null === $r, 'AC-6 a verified first success for a pending order goes on to the gateway' );
		$events = PaymentRecord::events( $fresh( $b->get_id() ) );
		$e      = $events[0] ?? array();
		$check( 1 === count( $events ) && (string) $paid['id'] === $e['reference'] && 'payment' === $e['kind'] && 'success' === $e['outcome'] && 5000 === $e['amount'] && 'EGP' === $e['currency'] && '' !== $e['occurred_at'] && $e['received_at'] > 0, 'AC-6 one event: reference, kind, outcome, amount, currency, times' );
		$fresh( $b->get_id() )->payment_complete( (string) $paid['id'] ); // what the gateway does
		$check( ! $fresh( $b->get_id() )->has_status( PaymentGate::AWAITING ), 'gateway step applied: order is ' . $fresh( $b->get_id() )->get_status() );

		$r = $notice( $paid );
		$check( 200 === ( $r['status'] ?? 0 ) && 'already handled' === $r['word'], 'AC-2 the same notice again is acknowledged and stops' );
		$r = $return( $paid );
		$check( 200 === ( $r['status'] ?? 0 ) && str_contains( (string) $r['location'], 'order-received' ), 'AC-2 the return after the notice stops and shows the order' );
		$check( 1 === count( PaymentRecord::events( $fresh( $b->get_id() ) ) ), 'AC-2 still one event after the repeats' );

		$moved = t68_obj( $a->get_id(), array( 'id' => $paid['id'] ) );
		$r     = $notice( $moved );
		$check( 403 === ( $r['status'] ?? 0 ) && 'pending' === $fresh( $a->get_id() )->get_status(), 'AC-2 order B\'s transaction cannot pay order A (refused, A still pending)' );

		// ---- AC-1: a second payment of a paid order is refunded at once, through Paymob, once --------------------
		// Paymob's refund API is stood in for: the request is recorded and answered, nothing leaves this machine.
		$dup_reply = array( 'id' => 556001, 'success' => true );
		$dup_asked = array();
		$dup_stub  = function ( $pre, $args, $req_url ) use ( &$dup_reply, &$dup_asked ) {
			if ( ! str_contains( (string) $req_url, 'void_refund/refund' ) ) {
				return $pre;
			}
			$dup_asked[] = array( 'url' => (string) $req_url, 'auth' => (string) ( $args['headers']['Authorization'] ?? '' ), 'body' => json_decode( (string) $args['body'], true ) );
			return array( 'headers' => array(), 'body' => (string) wp_json_encode( $dup_reply ), 'response' => array( 'code' => 201, 'message' => 'Created' ), 'cookies' => array(), 'filename' => null );
		};
		add_filter( 'pre_http_request', $dup_stub, 10, 3 );
		$status = $fresh( $b->get_id() )->get_status();
		$second = t68_obj( $b->get_id() );
		$r      = $notice( $second );
		$check( 200 === ( $r['status'] ?? 0 ) && $status === $fresh( $b->get_id() )->get_status(), 'AC-1 a second success for the paid order is stopped, status unchanged' );
		$asked = $dup_asked[0] ?? array();
		$check( 1 === count( $dup_asked ) && str_starts_with( $asked['url'] ?? '', 'https://accept.paymob.com/' ) && 'Token egy_sk_test_t68_local_only' === $asked['auth'] && (string) $second['id'] === (string) ( $asked['body']['transaction_id'] ?? '' ) && 5000 === ( $asked['body']['amount_cents'] ?? 0 ), 'AC-1 the second payment is refunded in full through Paymob, once, by its own reference' );
		$check( RefundLedger::madeHere( $fresh( $b->get_id() ), '556001' ) && array() === PaymentRecord::attention( $fresh( $b->get_id() ) ) && 0 === count( $fresh( $b->get_id() )->get_refunds() ), 'AC-1 the refund is kept by reference, nothing is left for staff, and the order\'s own refunds are untouched' );
		$r = $notice( $second );
		$check( 'already handled' === ( $r['word'] ?? '' ) && 1 === count( $dup_asked ), 'AC-1 the same second payment again is not refunded twice' );
		$r = $notice( t68_obj( $b->get_id(), array( 'id' => 556001, 'amount_cents' => 5000, 'is_refund' => true, 'has_parent_transaction' => true ) ) );
		$check( 'refund already recorded' === ( $r['word'] ?? '' ), 'AC-1 Paymob\'s notice of that refund is acknowledged without a record on the order' );
		$dup_reply = array( 'message' => 'Transaction is not refundable.' );
		$r         = $notice( t68_obj( $b->get_id() ) );
		$flags     = PaymentRecord::attention( $fresh( $b->get_id() ) );
		$check( 2 === count( $dup_asked ) && 1 === count( $flags ) && str_contains( $flags[0]['message'], 'did not go through' ), 'AC-1 when Paymob does not refund a further payment, staff are flagged to refund it' );
		remove_filter( 'pre_http_request', $dup_stub, 10 );

		// ---- AC-3: pending, failure, cancelled -------------------------------------------------------------------
		$c = $order();
		$r = $notice( t68_obj( $c->get_id(), array( 'pending' => true, 'success' => false ) ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 'pending' === $fresh( $c->get_id() )->get_status(), 'AC-3 a pending notice is recorded and changes nothing' );
		$r = $notice( t68_obj( $c->get_id(), array( 'success' => false ) ) );
		$check( null === $r, 'AC-3 a verified failure for an awaiting order goes on to the gateway' );
		$fresh( $c->get_id() )->update_status( 'failed' ); // what the gateway does
		$check( ! $fresh( $c->get_id() )->is_paid(), 'AC-3 the failed order is not paid' );
		$r = $notice( t68_obj( $c->get_id() ) );
		$check( null === $r, 'AC-3 a later success for the failed order (a retry) goes on to the gateway' );

		$d = $order();
		$d->set_status( 'cancelled' );
		$d->save();
		$r = $notice( t68_obj( $d->get_id() ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 'cancelled' === $fresh( $d->get_id() )->get_status(), 'AC-3 a success for a cancelled order is not applied' );
		$check( 1 === count( PaymentRecord::attention( $fresh( $d->get_id() ) ) ), 'AC-3 the payment of a cancelled order is flagged' );

		// ---- AC-2 and AC-7: refunds counted once ------------------------------------------------------------------
		$fresh( $b->get_id() )->add_order_note( 'Paymob : Partial refund of 20<br/>Woo Order Refund ID: 1<br/>Transaction Refund ID : 777001' );
		$check( RefundLedger::madeHere( $fresh( $b->get_id() ), '777001' ), 'AC-7 the refund made from the order screen is captured from the gateway note' );
		$r = $notice( t68_obj( $b->get_id(), array( 'id' => 777001, 'amount_cents' => 2000, 'is_refund' => true, 'has_parent_transaction' => true ) ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 'refund already recorded' === $r['word'], 'AC-2 the notice of that refund stops without a second record' );
		$dash = t68_obj( $b->get_id(), array( 'id' => 777002, 'amount_cents' => 1000, 'is_refund' => true, 'has_parent_transaction' => true ) );
		$r    = $notice( $dash );
		$check( 'recorded' === ( $r['word'] ?? '' ) && 0 === count( $fresh( $b->get_id() )->get_refunds() ) && 2 === count( PaymentRecord::attention( $fresh( $b->get_id() ) ) ), 'AC-2 a refund\'s own notice not made here, of another Paymob order, is recorded, flagged for staff, written nowhere and never reaches the gateway' );
		$r = $notice( $dash );
		$check( 200 === ( $r['status'] ?? 0 ) && 'already handled' === $r['word'], 'AC-2 that refund notice again stops' );

		// ---- PAY-17: Paymob reports a refund as the payment marked refunded, with the total refunded so far ---------
		$g      = $order();
		$g_paid = t68_obj( $g->get_id() );
		$notice( $g_paid );
		$fresh( $g->get_id() )->payment_complete( (string) $g_paid['id'] );
		// Paymob reports the same transaction, of the same Paymob order, marked refunded.
		$update = fn ( int $refunded ) => t68_obj( $g->get_id(), array( 'id' => $g_paid['id'], 'order' => $g_paid['order'], 'is_refunded' => true, 'refunded_amount_cents' => $refunded ) );
		PaymentGate::releaseAll();
		wc_create_refund( array( 'order_id' => $g->get_id(), 'amount' => 20, 'reason' => 't68 order screen', 'refund_payment' => false ) );
		$r = $notice( $update( 2000 ) );
		$check( 'refunds match' === ( $r['word'] ?? '' ) && 1 === count( $fresh( $g->get_id() )->get_refunds() ) && array() === PaymentRecord::attention( $fresh( $g->get_id() ) ), 'PAY-17 Paymob\'s refunded total equal to the order\'s refunds: nothing added, nothing flagged' );
		$r = $notice( $update( 2000 ) );
		$check( 'already handled' === ( $r['word'] ?? '' ), 'AC-2 the same refunded total again stops' );
		// A refund made in Paymob's dashboard is recorded from the refund's own notice, whose amount and Paymob order are
		// signed: once, for its amount (audit, Codex follow-up).
		$dash = t68_obj( $g->get_id(), array( 'id' => 990777001, 'amount_cents' => 1000, 'is_refund' => true, 'has_parent_transaction' => true, 'order' => $g_paid['order'] ) );
		$r     = $notice( $dash );
		$g_now = $fresh( $g->get_id() );
		$check( 'refund recorded' === ( $r['word'] ?? '' ) && 2 === count( $g_now->get_refunds() ) && 3000 === (int) round( (float) $g_now->get_total_refunded() * 100 ), 'PAY-17 a 10.00 refund made in Paymob\'s dashboard is recorded on the order from its own signed notice (refunded ' . $g_now->get_total_refunded() . ')' );
		$r = $notice( $dash );
		$check( 'already handled' === ( $r['word'] ?? '' ) && 2 === count( $fresh( $g->get_id() )->get_refunds() ), 'PAY-17 the same refund notice again records nothing' );
		$r = $notice( $update( 3000 ) );
		$check( 'refunds match' === ( $r['word'] ?? '' ) && 2 === count( $fresh( $g->get_id() )->get_refunds() ), 'PAY-17 the payment then reported with that total refunded matches' );
		$r = $notice( $update( 2500 ) );
		$check( 2 === count( $fresh( $g->get_id() )->get_refunds() ) && 1 === count( PaymentRecord::attention( $fresh( $g->get_id() ) ) ), 'PAY-17 a refunded total below the order\'s refunds adds nothing and is flagged' );
		// The refunded total is not signed by Paymob: it is only compared, never written, however high; and refunds are
		// taken from the server notice only, never a customer's return (audit, Codex).
		$r = $notice( $update( 4900 ) );
		$check( 'refund awaited' === ( $r['word'] ?? '' ) && 2 === count( $fresh( $g->get_id() )->get_refunds() ), 'PAY-17 a refunded total above the order\'s refunds writes nothing: the refund\'s own notice is awaited' );
		$r = $return( $update( 4000 ) );
		$check( 'ignored' === ( $r['word'] ?? '' ) && 2 === count( $fresh( $g->get_id() )->get_refunds() ), 'PAY-17 a refunded total on the customer\'s return is not even recorded' );
		// A refund notice of another Paymob order, presented with this order's reference, writes nothing and is flagged.
		$alien = t68_obj( $g->get_id(), array( 'id' => 990777002, 'amount_cents' => 1000, 'is_refund' => true, 'has_parent_transaction' => true ) );
		$r     = $notice( $alien );
		$check( 2 === count( $fresh( $g->get_id() )->get_refunds() ) && 2 === count( PaymentRecord::attention( $fresh( $g->get_id() ) ) ), 'PAY-17 a refund notice of another Paymob order records nothing and is flagged' );
		// The second payment's own refund, reported as that payment marked refunded, is not the paid order's refund (Codex 3).
		$b_refunds = count( $fresh( $b->get_id() )->get_refunds() );
		$r = $notice( t68_obj( $b->get_id(), array( 'id' => $second['id'], 'order' => $second['order'], 'is_refunded' => true, 'refunded_amount_cents' => 5000 ) ) );
		$check( $b_refunds === count( $fresh( $b->get_id() )->get_refunds() ) && 'refunded' !== $fresh( $b->get_id() )->get_status(), 'AC-1 the refunded second payment, reported again by Paymob, adds no refund to the order paid by the first' );
		$r = $notice( t68_obj( $b->get_id(), array( 'id' => 777003, 'is_void' => true, 'has_parent_transaction' => true ) ) );
		$check( 200 === ( $r['status'] ?? 0 ) && 3 === count( PaymentRecord::attention( $fresh( $b->get_id() ) ) ), 'AC-2 a void is recorded and flagged, not applied (' . ( $r['status'] ?? 0 ) . ', ' . wp_json_encode( array_column( PaymentRecord::attention( $fresh( $b->get_id() ) ), 'message' ) ) . ')' );

		// ---- AC-8: the gate is the provider's ---------------------------------------------------------------------
		$stand = new T68Provider();
		add_filter( 'mizzey_payment_providers', fn ( $list ) => array_merge( $list, array( $stand ) ) );
		$check( in_array( $stand, PaymentGate::providers(), true ), 'AC-8 a stand-in provider joins through mizzey_payment_providers' );
		$t = $order( 't68pay' );
		$last = null;
		PaymentGate::releaseAll();
		PaymentGate::handle( $stand, 'POST', array(), (string) wp_json_encode( array( 'sig' => 'no', 'order' => $t->get_id() ) ) );
		$check( 403 === ( $last['status'] ?? 0 ), 'AC-8 the stand-in\'s unverified notice is refused by the same gate' );
		$last = null;
		PaymentGate::releaseAll();
		PaymentGate::handle( $stand, 'POST', array(), (string) wp_json_encode( array( 'sig' => 'ok', 'order' => $t->get_id(), 'ref' => 't68-1', 'amount' => 5000 ) ) );
		$check( null === $last && 1 === count( PaymentRecord::events( $fresh( $t->get_id() ) ) ), 'AC-8 the stand-in\'s verified first payment goes through and is recorded' );
		$check( null === PaymentGate::providerFor( $cod ) && $paymob->ownsOrder( $fresh( $b->get_id() ) ), 'AC-8 orders are matched to their provider by gateway' );

		// ---- the callback address Paymob is given ---------------------------------------------------------------
		$data = apply_filters( 'paymob_intention_data', array( 'amount' => 5000 ), array( 'order_id' => $b->get_id() ) );
		$url  = untrailingslashit( (string) get_option( 'home' ) ) . '/wc-api/paymob_callback/';
		$check( ( $data['notification_url'] ?? '' ) === $url && ( $data['redirection_url'] ?? '' ) === $url && CallbackAddress::url() === $url, 'Paymob is given the path form of the callback address: ' . $url );

		// ---- AC-4 over the real request --------------------------------------------------------------------------
		$http = wp_remote_post( $url, array( 'body' => (string) wp_json_encode( array( 'type' => 'TRANSACTION', 'obj' => t68_obj( $a->get_id() ) ) ), 'timeout' => 20, 'headers' => array( 'Content-Type' => 'application/json' ) ) ); // storefront-guard: exempt, a callback endpoint answering a word, not a page
		$check( ! is_wp_error( $http ) && 403 === wp_remote_retrieve_response_code( $http ) && 'refused' === trim( wp_remote_retrieve_body( $http ) ), 'AC-4 the real request to the callback address, unsigned, is refused 403 by the gate (' . ( is_wp_error( $http ) ? $http->get_error_message() : wp_remote_retrieve_response_code( $http ) ) . ')' );

		// ---- AC-10: paid is not received -------------------------------------------------------------------------
		ob_start();
		PaymentRecord::panel( $fresh( $b->get_id() ) );
		$panel = (string) ob_get_clean();
		$check( str_contains( $panel, 'Payment record' ) && str_contains( $panel, 'not at this time' ) && str_contains( $panel, (string) $paid['id'] ), 'AC-10 the order screen shows the record and says paid is not received' );
		$note = dirname( __DIR__, 3 ) . '/docs/payments-settlement.md';
		$check( is_readable( $note ) && str_contains( (string) file_get_contents( $note ), 'PAY-19' ), 'AC-10 the settlement note exists (docs/payments-settlement.md)' );

		// ---- Through the plugin itself, over the real request (when the plugin is in the runtime) -----------------
		if ( in_array( 'paymob-for-woocommerce/init-paymob.php', (array) get_option( 'active_plugins', array() ), true ) ) {
			// The plugin loads its card gateway once it is set up with real keys and has created its payment methods, as
			// staging will be. Here the same gateway is loaded by a temporary file for the requests this scenario makes.
			$mu = WPMU_PLUGIN_DIR . '/t68-paymob-card-gateway.php';
			wp_mkdir_p( WPMU_PLUGIN_DIR );
			file_put_contents( $mu, "<?php\n// Temporary, written and removed by t68-payments.\nadd_action( 'plugins_loaded', function () {\n\t\$d = WP_PLUGIN_DIR . '/paymob-for-woocommerce/includes/gateways/';\n\tif ( class_exists( 'WC_Payment_Gateway' ) && is_readable( \$d . 'class-gateway-paymob.php' ) ) {\n\t\trequire_once \$d . 'class-paymob-payment.php';\n\t\trequire_once \$d . 'class-gateway-paymob.php';\n\t}\n}, 30 );\n" );
			$s->on_finish( fn () => @unlink( $mu ) );
			$fresh = function ( int $id ): \WC_Order {
				wp_cache_flush();
				return wc_get_order( $id );
			};
			$plugin_order = function () use ( $order ): \WC_Order {
				$o = $order();
				$o->update_meta_data( 'PaymobPaymentId', 'paymob' );
				$o->save();
				return $o;
			};
			$post = function ( array $obj, bool $signed = true ) use ( $url, $secret ): array {
				PaymentGate::releaseAll();
				$target = $signed ? add_query_arg( 'hmac', PaymobProvider::sign( PaymobProvider::fromNotice( $obj ), $secret ), $url ) : $url;
				$res    = wp_remote_post( $target, array( 'body' => (string) wp_json_encode( array( 'type' => 'TRANSACTION', 'obj' => $obj ) ), 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json' ) ) ); // storefront-guard: exempt, a callback endpoint answering a word, not a page
				return is_wp_error( $res ) ? array( 0, $res->get_error_message() ) : array( (int) wp_remote_retrieve_response_code( $res ), trim( wp_remote_retrieve_body( $res ) ) );
			};

			$e      = $plugin_order();
			$forged = PaymobProvider::fromNotice( t68_obj( $e->get_id(), array( 'amount_cents' => 100 ) ) ) + array( 'merchant_order_id' => $e->get_id() . '_1793530999' );
			PaymentGate::releaseAll();
			$res = wp_remote_get( add_query_arg( $forged, $url ), array( 'redirection' => 0, 'timeout' => 30 ) ); // storefront-guard: exempt, a callback endpoint that redirects, the page itself is not read
			$check( 302 === (int) wp_remote_retrieve_response_code( $res ) && str_contains( (string) wp_remote_retrieve_header( $res, 'location' ), 'checkout' ) && 'pending' === $fresh( $e->get_id() )->get_status() && $total === $fresh( $e->get_id() )->get_total(), 'AC-4 through the plugin: an unsigned return claiming success at 1.00 is sent to the checkout; the order stays pending at ' . $total . ' [' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) . ' ' . wp_remote_retrieve_header( $res, 'location' ) . ' ' . $fresh( $e->get_id() )->get_status() . ' ' . $fresh( $e->get_id() )->get_total() ) . ']' );

			$ok_obj        = t68_obj( $e->get_id() );
			[ $code, $body ] = $post( $ok_obj );
			$paid_e        = $fresh( $e->get_id() );
			$check( 200 === $code && str_contains( $body, 'Order updated' ) && ! $paid_e->has_status( PaymentGate::AWAITING ) && $total === $paid_e->get_total(), 'AC-6 through the plugin: a signed success marks the order paid (' . $paid_e->get_status() . '), total still ' . $total . ' [' . $code . ' ' . $body . ']' );
			$check( 1 === count( PaymentRecord::events( $paid_e ) ) && (string) $ok_obj['id'] === (string) $paid_e->get_meta( 'PaymobTransactionId' ), 'AC-6 through the plugin: one event recorded, and the plugin stored the same transaction' );
			[ $code, $body ] = $post( $ok_obj );
			$check( 200 === $code && 'already handled' === $body && 1 === count( PaymentRecord::events( $fresh( $e->get_id() ) ) ), 'AC-2 through the plugin: the same notice again is acknowledged by the gate, still one event [' . $body . ']' );
			[ $code, $body ] = $post( $ok_obj, false );
			$check( 403 === $code && 'refused' === $body, 'AC-4 through the plugin: the same notice without its signature is refused' );

			$f               = $plugin_order();
			[ $code, $body ] = $post( t68_obj( $f->get_id(), array( 'amount_cents' => 4000 ) ) );
			$check( 200 === $code && 'pending' === $fresh( $f->get_id() )->get_status() && $total === $fresh( $f->get_id() )->get_total(), 'AC-4 through the plugin: a signed success of 40.00 leaves the 50.00 order pending and its total unchanged [' . $body . ']' );

			// A refund from the order screen: the plugin's own gateway, with Paymob's reply stood in for.
			require_once WP_PLUGIN_DIR . '/paymob-for-woocommerce/includes/gateways/class-paymob-payment.php';
			require_once WP_PLUGIN_DIR . '/paymob-for-woocommerce/includes/gateways/class-gateway-paymob.php';
			add_filter( 'woocommerce_payment_gateways', fn ( $g ) => array_merge( (array) $g, array( new \Paymob_Gateway() ) ), 99 );
			WC()->payment_gateways()->init();
			$reply = array( 'id' => 555001, 'success' => true, 'amount_cents' => 1000 );
			$calls = 0;
			add_filter(
				'pre_http_request',
				function ( $pre, $args, $req_url ) use ( &$reply, &$calls ) {
					if ( ! str_contains( (string) $req_url, 'void_refund/refund' ) ) {
						return $pre;
					}
					$calls++;
					return array( 'headers' => array(), 'body' => (string) wp_json_encode( $reply ), 'response' => array( 'code' => 201, 'message' => 'Created' ), 'cookies' => array(), 'filename' => null );
				},
				10,
				3
			);
			PaymentGate::releaseAll();
			$refund = wc_create_refund( array( 'order_id' => $e->get_id(), 'amount' => 10, 'reason' => 't68', 'refund_payment' => true ) );
			$check( ! is_wp_error( $refund ) && 1 === $calls && RefundLedger::madeHere( $fresh( $e->get_id() ), '555001' ), 'AC-7 through the plugin: a 10.00 refund from the order screen goes to Paymob once and its reference is kept' . ( is_wp_error( $refund ) ? ' (' . $refund->get_error_message() . ')' : '' ) );
			PaymentGate::releaseAll();
			[ $code, $body ] = $post( t68_obj( $e->get_id(), array( 'id' => 555001, 'amount_cents' => 1000, 'is_refund' => true, 'has_parent_transaction' => true ) ) );
			$check( 'refund already recorded' === $body && 1 === count( $fresh( $e->get_id() )->get_refunds() ), 'AC-2 through the plugin: Paymob\'s notice of that refund does not add a second refund [' . $body . ', ' . count( $fresh( $e->get_id() )->get_refunds() ) . ' refund]' );

			$reply   = array( 'message' => 'Requested Refund Amount is greater than the maximum refund amount permissible.' );
			$refused = wc_create_refund( array( 'order_id' => $e->get_id(), 'amount' => 30, 'reason' => 't68', 'refund_payment' => true ) );
			$check( is_wp_error( $refused ) && 1 === count( $fresh( $e->get_id() )->get_refunds() ), 'AC-7 through the plugin: a refund Paymob refuses leaves no refund on the order' );
			$over = wc_create_refund( array( 'order_id' => $e->get_id(), 'amount' => 45, 'reason' => 't68', 'refund_payment' => true ) );
			$check( is_wp_error( $over ) && 2 === $calls, 'AC-7 a refund above what is left is refused before Paymob is asked (' . ( is_wp_error( $over ) ? $over->get_error_message() : 'made' ) . ')' );

			// Paymob's own shape for a refund: the payment again, marked refunded, with the total refunded so far.
			$refunded        = fn ( int $total ) => array_replace( $ok_obj, array( 'is_refunded' => true, 'refunded_amount_cents' => $total ) );
			[ $code, $body ] = $post( $refunded( 1000 ) );
			$check( 'refunds match' === $body && 1 === count( $fresh( $e->get_id() )->get_refunds() ) && array() === PaymentRecord::attention( $fresh( $e->get_id() ) ), 'AC-2 through the plugin: the payment marked refunded by 10.00 after the order-screen refund adds nothing [' . $body . ']' );
			$dash_obj        = t68_obj( $e->get_id(), array( 'id' => 555002, 'amount_cents' => 500, 'is_refund' => true, 'has_parent_transaction' => true, 'order' => $ok_obj['order'] ) );
			[ $code, $body ] = $post( $dash_obj );
			$check( 'refund recorded' === $body && 2 === count( $fresh( $e->get_id() )->get_refunds() ), 'PAY-17 through the plugin: a 5.00 refund made in Paymob\'s dashboard is recorded on the order once, from its own notice, which never reaches the plugin [' . $body . ']' );
			[ $code, $body ] = $post( $refunded( 1500 ) );
			$check( 'refunds match' === $body && 2 === count( $fresh( $e->get_id() )->get_refunds() ), 'PAY-17 through the plugin: the payment then reported with that total refunded matches [' . $body . ']' );
			[ $code, $body ] = $post( $refunded( 1500 ) );
			$check( 'already handled' === $body && 2 === count( $fresh( $e->get_id() )->get_refunds() ), 'AC-2 through the plugin: the same refunded total again adds nothing' );
		} else {
			$s->note( 'SKIP  the plugin is not active in this runtime: the checks through the plugin did not run' );
		}

		// ---- The plugin's unused entry points are closed; its settings are staff only ------------------------------
		$refused                     = array();
		PluginSurface::$refuse       = function ( string $action ) use ( &$refused ): void {
			$refused[] = $action;
		};
		$s->on_finish( static function (): void { PluginSurface::$refuse = null; } );
		$ask = function ( string $action, bool $ajax = true ) use ( &$refused ): bool {
			$refused = array();
			$was     = $_REQUEST['action'] ?? null;
			$_REQUEST['action'] = $action;
			$doing   = fn () => $ajax;
			add_filter( 'wp_doing_ajax', $doing, 99 );
			PluginSurface::guard();
			remove_filter( 'wp_doing_ajax', $doing, 99 );
			null === $was ? ( function () { unset( $_REQUEST['action'] ); } )() : $_REQUEST['action'] = $was;
			return array( $action ) === $refused;
		};
		$who      = get_current_user_id();
		$customer = wp_insert_user( array( 'user_login' => 't68c' . wp_rand( 10000, 99999 ), 'user_pass' => wp_generate_password(), 'user_email' => 't68c' . wp_rand( 10000, 99999 ) . '@example.invalid', 'role' => 'customer' ) );
		$admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		if ( ! is_wp_error( $customer ) ) {
			$s->track_user( (int) $customer );
		}
		$s->on_finish( static function () use ( $who ): void { wp_set_current_user( $who ); } );

		wp_set_current_user( 0 );
		$closed_anon = array_filter( PluginSurface::CLOSED, fn ( $a ) => ! $ask( $a ) );
		$check( array() === $closed_anon, 'Security: every unused plugin entry point (' . count( PluginSurface::CLOSED ) . ') is refused to a visitor' . ( $closed_anon ? ' (open: ' . implode( ',', $closed_anon ) . ')' : '' ) );
		$check( ! $ask( 'heartbeat' ) && ! $ask( 'create_order', false ), 'Security: other actions, and the same name outside an ajax request, are left alone' );
		if ( ! is_wp_error( $customer ) ) {
			wp_set_current_user( (int) $customer );
			$staff_customer = array_filter( PluginSurface::STAFF, fn ( $a ) => ! $ask( $a ) );
			$check( array() === $staff_customer && $ask( 'create_order' ), 'Security: a signed-in customer is refused the plugin\'s settings actions (' . count( PluginSurface::STAFF ) . ') and its unused entry points' );
		}
		if ( $admins ) {
			wp_set_current_user( (int) $admins[0] );
			$check( ! $ask( 'disconnect_save_keys' ) && ! $ask( 'toggle_gateway' ) && $ask( 'create_order' ), 'Security: staff reach the plugin\'s settings; the unused entry points stay closed to staff too' );
		}
		wp_set_current_user( $who );
		$res = wp_remote_post( admin_url( 'admin-ajax.php' ), array( 'body' => array( 'action' => 'paymob_aw_cart_amount' ), 'timeout' => 20 ) ); // storefront-guard: exempt, an ajax endpoint answering JSON, not a page
		$check( 403 === (int) wp_remote_retrieve_response_code( $res ) && str_contains( (string) wp_remote_retrieve_body( $res ), 'not_available' ), 'Security: over the real request, an unused plugin entry point answers 403 not_available (' . wp_remote_retrieve_response_code( $res ) . ')' );

		// ---- Binding: a signed transaction is tied to the order it pays (audit 2 November, C M-1, L-2, L-3) ---------
		// Someone else's success presented with a paid order's reference: refunded as a second payment, and never shown
		// that order's confirmation page.
		// Paymob's API is stood in for here too: nothing leaves this machine.
		$no_net = static fn ( $pre, $args, $req_url ) => str_contains( (string) $req_url, 'paymob.com' ) ? array( 'headers' => array(), 'body' => '{"success":false}', 'response' => array( 'code' => 400, 'message' => 'Bad Request' ), 'cookies' => array(), 'filename' => null ) : $pre;
		add_filter( 'pre_http_request', $no_net, 10, 3 );
		$replay = t68_obj( $b->get_id() );
		$r      = $return( $replay );
		remove_filter( 'pre_http_request', $no_net, 10 );
		$check( ( $r['status'] ?? 0 ) < 400 && ! str_contains( (string) ( $r['location'] ?? '' ), 'order-received' ) && (string) $paid['order']['id'] === (string) $fresh( $b->get_id() )->get_meta( PaymentGate::PROVIDER_ORDER ), 'M-1 a transaction of another Paymob order, returned with a paid order\'s reference, never reaches that order\'s confirmation page; the paying Paymob order stays pinned (' . ( $r['location'] ?? '' ) . ')' );
		// A failure of another amount cannot fail an order awaiting payment; one of its own amount can.
		$waiting = $order();
		$waiting->update_meta_data( 'PaymobCentsAmount', '5000' );
		$waiting->save();
		$r_other = $notice( t68_obj( $waiting->get_id(), array( 'success' => false, 'amount_cents' => 7300 ) ) );
		$r_own   = $notice( t68_obj( $waiting->get_id(), array( 'success' => false ) ) );
		$check( 403 === ( $r_other['status'] ?? 0 ) && null === $r_own && 'pending' === $fresh( $waiting->get_id() )->get_status(), 'M-1 a signed failure of another amount is refused for an order awaiting payment; a failure of its own amount goes to the gateway' );
		// A transaction of an integration the store does not use is refused.
		$plugin = get_option( PaymobProvider::SETTINGS );
		update_option( PaymobProvider::SETTINGS, array_merge( (array) $plugin, array( 'integration_id_hidden' => '111222 : Card (EGP),333444 : Wallet (EGP)' ) ) );
		$foreign = $notice( t68_obj( $order()->get_id() ) );
		$ours    = $notice( t68_obj( $order()->get_id(), array( 'integration_id' => 333444 ) ) );
		update_option( PaymobProvider::SETTINGS, $plugin );
		$check( 403 === ( $foreign['status'] ?? 0 ) && null === $ours, 'M-1 a transaction of an integration the store does not use is refused; one of its own goes on' );
		// A success of the wrong amount, returned again and again, is flagged once.
		$short = $order();
		$low   = t68_obj( $short->get_id(), array( 'amount_cents' => 4000 ) );
		$return( $low );
		$return( $low );
		$return( $low );
		$check( 1 === count( PaymentRecord::attention( $fresh( $short->get_id() ) ) ) && 'pending' === $fresh( $short->get_id() )->get_status(), 'L-2 a mismatched success returned three times is flagged once and does not pay the order' );
		// NFR-10: a signature that does not verify (as after the secret is changed at Paymob) is an error, which the monitor
		// raises; an unsigned request stays a warning.
		$count = static function ( string $needle ): int {
			$n = 0;
			foreach ( defined( 'WC_LOG_DIR' ) ? ( glob( trailingslashit( WC_LOG_DIR ) . PaymentGate::LOG_SOURCE . '*' ) ?: array() ) : array() as $file ) {
				$n += substr_count( (string) file_get_contents( $file ), $needle );
			}
			return $n;
		};
		$errors_before = $count( 'ERROR notice refused: bad_signature' );
		$notice( t68_obj( $order()->get_id() ), str_repeat( 'ab', 64 ) );
		$check( $count( 'ERROR notice refused: bad_signature' ) === $errors_before + 1, 'NFR-10 a notice whose signature does not verify is logged as an error, for the monitor to raise' );

		// FR-011: keys that do not match the environment are pointed out to staff.
		$keys = get_option( 'woocommerce_paymob-main_settings' );
		$test_mode = PaymobProvider::keyMode();
		update_option( 'woocommerce_paymob-main_settings', array_merge( (array) $keys, array( 'sec_key' => 'egy_sk_live_t68', 'pub_key' => 'egy_pk_live_t68' ) ) );
		$live_mode = PaymobProvider::keyMode();
		$admin_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		$was       = get_current_user_id();
		wp_set_current_user( (int) ( $admin_ids[0] ?? 0 ) );
		ob_start();
		PaymentGate::keyNotice();
		$warned = (string) ob_get_clean();
		wp_set_current_user( $was );
		update_option( 'woocommerce_paymob-main_settings', $keys );
		$check( 'test' === $test_mode && 'live' === $live_mode && ( 'production' === wp_get_environment_type() || str_contains( $warned, 'live keys' ) ), 'FR-011 the stored keys\' mode is read from their prefixes, and live keys outside the live store are pointed out to staff (' . $test_mode . ', ' . $live_mode . ')' );

		// ---- AC-5: no card number kept ---------------------------------------------------------------------------
		$like = '%' . $wpdb->esc_like( T68_PAN ) . '%';
		$hits = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders_meta WHERE meta_value LIKE %s", $like ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE %s", $like ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) );
		$logs = defined( 'WC_LOG_DIR' ) ? ( glob( trailingslashit( WC_LOG_DIR ) . PaymentGate::LOG_SOURCE . '*' ) ?: array() ) : array();
		foreach ( $logs as $file ) {
			$hits += substr_count( (string) file_get_contents( $file ), T68_PAN );
		}
		$check( 0 === $hits, 'AC-5 the full card number in the payloads is in no order meta, note, option or payment log (' . count( $logs ) . ' log files read)' );

		return $ok;
	}
);

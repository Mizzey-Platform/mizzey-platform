<?php
/**
 * t87: the second release, behind its switch (ADM-05, ADM-07, ADM-101, ADM-105, NFR-12a, ACCT-13, NOTF-14, JRN-11,
 * ACCT-09, IA-23, ACCT-11, PLP-11). See specs/047-second-release.
 *
 * AC-1: with the switch off (the default) nothing of it is offered.
 * AC-2: reorder from a delivered order.
 * AC-3: a deletion request is confirmed by email and then handled: data erased, account deleted; a staff account is
 *       never deleted; an export prepared by staff is built and not emailed to the customer.
 * AC-4: notification preferences: the review invitation and offers.
 * AC-5: the customer's lifetime figures and new and returning customers.
 * AC-6: email texts editable in WooCommerce (NOTF-14) and the rating filter block (PLP-11) are native.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Accounts\MarketingConsent;
use MizzeySite\SecondRelease\CustomerMetrics;
use MizzeySite\SecondRelease\NotificationPreferences;
use MizzeySite\SecondRelease\PrivacyRequests;
use MizzeySite\SecondRelease\ReleaseSwitch;
use MizzeySite\SecondRelease\Reorder;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't87-second-release', 'S2 rows: the second release behind its switch' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag  = 't87' . wp_rand( 1000, 9999 );
		$keep = get_option( ReleaseSwitch::OPTION, null );
		$s->on_finish(
			static function () use ( $keep ): void {
				null === $keep ? delete_option( ReleaseSwitch::OPTION ) : update_option( ReleaseSwitch::OPTION, $keep );
				wp_set_current_user( 0 );
				$_POST = array();
			}
		);
		$mails = array();
		$trap  = static function ( $pre, $atts ) use ( &$mails ) { $mails[] = $atts; return true; };
		add_filter( 'pre_wp_mail', $trap, 10, 2 );
		$s->on_finish( static function () use ( $trap ) { remove_filter( 'pre_wp_mail', $trap, 10 ); } );
		$customer = (int) wp_insert_user( array( 'user_login' => "{$tag}c", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}c@example.invalid", 'role' => 'customer' ) );
		$s->track_user( $customer );
		$product = $s->simple_product( "{$tag} serum", '150.00', null );
		$order   = static function ( string $status, float $qty ) use ( $s, $product, $customer ): \WC_Order {
			$o = wc_create_order( array( 'customer_id' => $customer ) );
			$o->add_product( $product, (int) $qty );
			$o->set_payment_method( 'paymob' );
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			foreach ( array( 'processing', 'preparing', 'shipped', 'completed' ) as $step ) {
				if ( $o->has_status( $status ) ) {
					break;
				}
				$o->update_status( $step );
			}
			return wc_get_order( $o->get_id() );
		};
		$done = $order( 'completed', 2 );

		// ---- AC-1: off by default ---------------------------------------------------------------------------------
		delete_option( ReleaseSwitch::OPTION );
		wp_set_current_user( $customer );
		ob_start();
		PrivacyRequests::buttons();
		NotificationPreferences::fields();
		$customer_off = (string) ob_get_clean();
		ob_start();
		CustomerMetrics::record( get_userdata( $customer ) );
		CustomerMetrics::dashboard();
		$staff_off = (string) ob_get_clean();
		$check( ! ReleaseSwitch::on() && array() === Reorder::statuses( array( 'completed' ) ) && '' === $customer_off && '' === $staff_off, 'AC-1 with the switch off, which is the default, nothing of the second release is offered' );
		update_option( ReleaseSwitch::OPTION, 'yes' );

		// ---- AC-2: reorder --------------------------------------------------------------------------------------------
		ob_start();
		woocommerce_order_again_button( $done );
		$again = (string) ob_get_clean();
		$check( in_array( 'completed', Reorder::statuses( array( 'completed' ) ), true ) && in_array( 'cod-collected', Reorder::statuses( array( 'completed' ) ), true ) && str_contains( $again, 'order_again=' . $done->get_id() ), 'AC-2 JRN-11, ACCT-09 a delivered order offers "Order again"' );

		// ---- AC-4: notification preferences -------------------------------------------------------------------------
		ob_start();
		NotificationPreferences::fields();
		$form = (string) ob_get_clean();
		$_POST = array( NotificationPreferences::NONCE . '_nonce' => wp_create_nonce( NotificationPreferences::NONCE ), 'mizzey_pref_offers' => 'yes' );
		NotificationPreferences::save( $customer );
		$_POST = array();
		$check( str_contains( $form, 'mizzey_pref_reviews' ) && false === apply_filters( 'mizzey_review_invitation_allowed', true, $done ) && MarketingConsent::given( $customer ), 'AC-4 IA-23, ACCT-11 the customer turns the review invitation off and offers on' );

		$later = $order( 'completed', 1 );
		$check( '' === (string) wc_get_order( $later->get_id() )->get_meta( \MizzeySite\Reviews\ReviewInvitation::SENT ), 'AC-4 an order delivered after the customer turned invitations off sends no review invitation' );

		// ---- AC-5: figures -------------------------------------------------------------------------------------------
		$second = $order( 'processing', 1 );
		wc_create_refund( array( 'order_id' => $done->get_id(), 'amount' => '50', 'reason' => 't87' ) );
		$m = CustomerMetrics::of( $customer );
		$check( 3 === $m['orders'] && 550.0 === $m['spend'] && round( 550 / 3, 2 ) === $m['average'] && 1 === $m['refunded'], 'AC-5 ADM-101 total spend net of refunds, paid orders, average order value, refunded orders (' . wp_json_encode( $m ) . ')' );
		foreach ( array( $done->get_id(), $second->get_id() ) as $id ) {
			\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler::import( $id );
		}
		$c = CustomerMetrics::newAndReturning();
		$check( $c['new'] >= 1 && $c['returning'] >= 1, 'AC-5 ADM-05 new and returning customers over the last 30 days (' . wp_json_encode( $c ) . ')' );
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $staff );
		wp_set_current_user( $staff );
		ob_start();
		do_action( 'mizzey_customer_record_panel', get_userdata( $customer ) );
		do_action( 'mizzey_dashboard_widget_end' );
		$panel = (string) ob_get_clean();
		$check( str_contains( $panel, 'Total spend' ) && str_contains( $panel, 'mizzey_customer_export' ) && str_contains( $panel, 'Google Analytics' ), 'AC-5 ADM-07, ADM-105 the record shows the figures and the export button; the dashboard names where the conversion rate is' );

		// ---- AC-3: an export prepared by staff, not emailed --------------------------------------------------------
		$mails = array();
		$export = PrivacyRequests::request( get_userdata( $customer ), 'export_personal_data', true );
		// Run as Action Scheduler would: with the arguments the request queued.
		$queued_export = as_get_scheduled_actions( array( 'hook' => PrivacyRequests::HOOK, 'args' => array( (int) $export, false ), 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		PrivacyRequests::run( (int) $export, array() === $queued_export );
		$file = (string) get_post_meta( (int) $export, '_export_file_name', true );
		$check( 1 === count( $queued_export ) && ! is_wp_error( $export ) && '' !== $file && array() === array_filter( $mails, static fn ( $m ) => str_contains( (string) $m['to'], "{$tag}c@" ) ), 'AC-3 ADM-105 a copy prepared by staff is queued to be built for download, not emailed to the customer (' . count( $queued_export ) . ' queued)' );

		// ---- AC-3: the customer's deletion request ----------------------------------------------------------------
		wp_set_current_user( $customer );
		$mails   = array();
		$request = PrivacyRequests::request( get_userdata( $customer ), 'remove_personal_data', false );
		$check( ! is_wp_error( $request ) && 'request-pending' === get_post_status( (int) $request ) && 1 === count( array_filter( $mails, static fn ( $m ) => str_contains( (string) $m['to'], "{$tag}c@" ) ) ), 'AC-3 ACCT-13 the request is pending until the customer confirms it from the email' );
		do_action( 'user_request_action_confirmed', (int) $request );
		$queued = as_get_scheduled_actions( array( 'hook' => PrivacyRequests::HOOK, 'args' => array( (int) $request, true ), 'status' => \ActionScheduler_Store::STATUS_PENDING ), 'ids' );
		PrivacyRequests::run( (int) $request, true );
		wp_set_current_user( 0 );
		clean_user_cache( $customer );
		$check( 1 === count( $queued ) && false === get_userdata( $customer ) && 'request-completed' === get_post_status( (int) $request ), 'AC-3 NFR-12a once confirmed it is handled in the background: the data erased and the account deleted' );
		$staff_request = PrivacyRequests::request( get_userdata( $staff ), 'remove_personal_data', true );
		PrivacyRequests::run( (int) $staff_request, true );
		clean_user_cache( $staff );
		$check( false !== get_userdata( $staff ), 'AC-3 a staff account is never deleted this way' );
		as_unschedule_all_actions( PrivacyRequests::HOOK );

		// ---- AC-6: native ------------------------------------------------------------------------------------------
		$emails  = WC()->mailer()->get_emails();
		$shipped = $emails['MizzeySite_Customer_Shipped'] ?? null;
		$check( $shipped instanceof \WC_Email && isset( $shipped->form_fields['subject'], $shipped->form_fields['heading'] ) && isset( $emails['WC_Email_Customer_Processing_Order']->form_fields['additional_content'] ), 'AC-6 NOTF-14 email subjects, headings and texts are edited in WooCommerce, Settings, Emails, for every email the store sends' );
		$check( \WP_Block_Type_Registry::get_instance()->is_registered( 'woocommerce/product-filter-rating' ), 'AC-6 PLP-11 the rating filter is WooCommerce\'s block, placed on the listing pages with their design' );

		return $ok;
	}
);

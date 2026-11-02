<?php
/**
 * t81: shipping through Bosta (SHIP-01, SHIP-03 to SHIP-05, SHIP-08, SHIP-09, SHIP-12 to SHIP-17, INT-02).
 * See specs/041-bosta.
 *
 * Bosta's calls are made through the official plugin's own webhook handler, as Bosta's webhook would make them. The
 * codes used are test values: Bosta's legend (I-08) fills the real table.
 *
 * AC-1: a zone for a governorate with its rate; a free-shipping coupon overrides it.
 * AC-2: a rate carries its estimate in the customer's language.
 * AC-3: the carrier layer reads the plugin; the tracking number fills the order's shipment unless staff gave one.
 * AC-4: an unmapped code, or a refused move, never moves the order and marks it for review; mapping it moves it.
 * AC-5: returned to origin is its own state, never delivered.
 * AC-6: a delivered cash order is COD Collected with the amount kept once; the remittance moves it to Delivered;
 *       the unremitted list.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Emails\Shipped;
use MizzeySite\Orders\StatusModel;
use MizzeySite\Shipping\BostaCarrier;
use MizzeySite\Shipping\CarrierStatus;
use MizzeySite\Shipping\CodReconciliation;
use MizzeySite\Shipping\DeliveryEstimate;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't81-bosta', 'SHIP-01 to SHIP-17, INT-02: shipping through Bosta' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag  = 't81' . wp_rand( 1000, 9999 );
		$keep = array();
		foreach ( array( CarrierStatus::MAP, CarrierStatus::SEEN ) as $option ) {
			$keep[ $option ] = get_option( $option, null );
		}
		$s->on_finish(
			static function () use ( $keep ): void {
				foreach ( $keep as $option => $value ) {
					null === $value ? delete_option( $option ) : update_option( $option, $value, false );
				}
				$_GET  = array();
				$_POST = array();
				do_action( 'wpml_switch_language', null );
				wp_set_current_user( 0 );
			}
		);
		if ( ! class_exists( '\Bosta_Webhook_Handler' ) && defined( 'WP_PLUGIN_DIR' ) && is_readable( WP_PLUGIN_DIR . '/bosta-woocommerce/includes/class-bosta-webhook-handler.php' ) ) {
			require_once WP_PLUGIN_DIR . '/bosta-woocommerce/includes/class-bosta-webhook-handler.php';
		}
		$check( class_exists( '\Bosta_Webhook_Handler' ) && method_exists( '\Bosta_Webhook_Handler', 'handle_status_update' ), 'INT-02 the official Bosta plugin is installed, with its webhook handler' );
		$fresh = static function ( int $id ): \WC_Order {
			wp_cache_flush();
			return wc_get_order( $id );
		};
		// Bosta's webhook, through the plugin's own handler.
		$bosta = static function ( int $id, int $state, string $description ) use ( $fresh ): \WC_Order {
			\Bosta_Webhook_Handler::handle_status_update( $fresh( $id ), array( 'state' => $state, 'description' => $description ) );
			return $fresh( $id );
		};
		$product = $s->simple_product( "{$tag} serum", '120.00', null );
		$make    = function ( string $method, string $status ) use ( $s, $product, $tag ): int {
			$o = wc_create_order();
			$o->add_product( $product, 2 );
			$o->set_payment_method( $method );
			$o->set_billing_email( "{$tag}@example.invalid" );
			$o->calculate_totals();
			$o->save();
			$s->track_order( $o->get_id() );
			// Walked through the status model's allowed moves, as staff would.
			foreach ( array( 'processing', StatusModel::PREPARING, StatusModel::SHIPPED ) as $step ) {
				if ( $o->has_status( $status ) ) {
					break;
				}
				$o->update_status( $step );
			}
			return $o->get_id();
		};

		// ---- AC-3: the carrier layer and the tracking number ------------------------------------------------------
		$card = $make( 'paymob', StatusModel::PREPARING );
		$o    = $fresh( $card );
		$o->update_meta_data( BostaCarrier::TRACKING, "{$tag}TRK" );
		$o->save();
		$carriers = CarrierStatus::carriers();
		$check( 1 === count( $carriers ) && $carriers[0] instanceof BostaCarrier && $carriers[0]->ownsOrder( $fresh( $card ) ), 'AC-3 Bosta is reached through the carrier layer and owns the order the plugin pushed' );
		$check( 'Bosta' === Shipped::shipment( $fresh( $card ) )['carrier'] && "{$tag}TRK" === Shipped::shipment( $fresh( $card ) )['tracking'], 'AC-3 the tracking number Bosta returned fills the order\'s shipment, for the customer and the Shipped email' );
		$typed = $fresh( $card );
		$typed->update_meta_data( Shipped::TRACKING, "{$tag}BYHAND" );
		$typed->save();
		$check( "{$tag}BYHAND" === Shipped::shipment( $fresh( $card ) )['tracking'], 'AC-3 a tracking number staff typed is kept' );
		$typed->delete_meta_data( Shipped::TRACKING );
		$typed->save();

		// ---- AC-4: an unmapped code, then mapped -----------------------------------------------------------------
		update_option( CarrierStatus::MAP, array(), false );
		$o = $bosta( $card, 20, 'Picked up' );
		$check( $o->has_status( StatusModel::PREPARING ) && '' !== (string) $o->get_meta( CarrierStatus::REVIEW ) && isset( get_option( CarrierStatus::SEEN )['bosta:20'] ), 'AC-4 a code with no mapping leaves the order where it was, marks it for review and lists the code (' . $o->get_status() . ')' );
		$notes_before = count( wc_get_order_notes( array( 'order_id' => $card ) ) );
		$o->save();
		$check( count( wc_get_order_notes( array( 'order_id' => $card ) ) ) === $notes_before, 'AC-4 saving the order again adds nothing while the code is still unmapped' );
		// Staff map the codes in WooCommerce, Settings, Shipping, Carrier statuses.
		$mapper = (int) wp_insert_user( array( 'user_login' => "{$tag}map", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}map@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $mapper );
		wp_set_current_user( $mapper );
		$_GET  = array( 'section' => 'mizzey_carrier_statuses' );
		$_POST = array( CarrierStatus::NONCE . '_nonce' => wp_create_nonce( CarrierStatus::NONCE ), 'mizzey_carrier_map' => array( '20' => 'in-transit', '41' => 'out-for-delivery', '45' => 'delivered', '46' => 'returned', '11' => 'informational' ) );
		CarrierStatus::save();
		$_GET  = array();
		$_POST = array();
		wp_set_current_user( 0 );
		$o = $fresh( $card );
		$check( $o->has_status( StatusModel::SHIPPED ) && '' === (string) $o->get_meta( CarrierStatus::REVIEW ), 'AC-4 once staff map the code the order in review moves (' . $o->get_status() . ')' );
		$o = $bosta( $card, 41, 'Out for delivery' );
		$check( $o->has_status( StatusModel::OUT_FOR_DELIVERY ), 'AC-4 out for delivery is reflected on the order' );
		$o = $bosta( $card, 11, 'Note' );
		$check( $o->has_status( StatusModel::OUT_FOR_DELIVERY ), 'AC-4 an informational code changes nothing' );
		$o = $bosta( $card, 45, 'Delivered' );
		$check( $o->has_status( 'completed' ) && '' === (string) $o->get_meta( CodReconciliation::COLLECTED ), 'AC-4 a paid order Bosta delivered is Delivered, with no cash record' );

		// A move the status model refuses.
		$early = $make( 'paymob', 'processing' );
		$o     = $bosta( $early, 45, 'Delivered' );
		$check( $o->has_status( 'processing' ) && str_contains( (string) $o->get_meta( CarrierStatus::REVIEW ), 'cannot move there' ), 'AC-4 delivered on an order still Processing is not applied: the order is marked for review' );

		// ---- AC-5: returned to origin --------------------------------------------------------------------------
		$back = $make( 'paymob', StatusModel::SHIPPED );
		$o    = $bosta( $back, 46, 'Returned to business' );
		$check( $o->has_status( StatusModel::DELIVERY_FAILED ) && ! $o->has_status( 'completed' ), 'AC-5 returned to origin is its own state, never Delivered (' . $o->get_status() . ')' );

		// ---- AC-6: cash on delivery ----------------------------------------------------------------------------
		$cash = $make( 'cod', StatusModel::SHIPPED );
		$o    = $bosta( $cash, 45, 'Delivered' );
		$collected = (string) $o->get_meta( CodReconciliation::COLLECTED );
		$check( $o->has_status( StatusModel::COD_COLLECTED ) && wc_format_decimal( $o->get_total() ) === $collected && (int) $o->get_meta( CodReconciliation::COLLECTED_AT ) > 0, 'AC-6 a delivered cash order is COD Collected, with the amount and time kept (' . $o->get_status() . ', ' . $collected . ')' );
		$o->update_meta_data( BostaCarrier::CODE, '' );
		$o->save();
		$o = $bosta( $cash, 45, 'Delivered' );
		$check( $collected === (string) $o->get_meta( CodReconciliation::COLLECTED ) && 1 === count( array_filter( wc_get_order_notes( array( 'order_id' => $cash ) ), static fn ( $n ) => str_contains( $n->content, '45' ) ) ), 'AC-6 the same code again changes nothing' );
		$unremitted = static function (): array {
			$_GET = array( 'mizzey_unremitted' => '1' );
			$args = CodReconciliation::query( array( 'limit' => -1, 'type' => 'shop_order', 'return' => 'ids' ) );
			$_GET = array();
			return array_map( 'intval', (array) wc_get_orders( $args ) );
		};
		$check( in_array( $cash, $unremitted(), true ) && ! in_array( $card, $unremitted(), true ), 'AC-6 the cash order collected and not remitted is listed; a card order is not' );
		$check( null !== CodReconciliation::remit( $fresh( $cash ), '0', '2026-11-02', 'X' ) && $fresh( $cash )->has_status( StatusModel::COD_COLLECTED ), 'AC-6 a remittance with no amount is refused' );
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}fin", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}fin@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $staff );
		wp_set_current_user( $staff );
		$check( null === CodReconciliation::remit( $fresh( $cash ), $collected, '2026-11-02', "{$tag}RMT" ), 'AC-6 staff record the remittance' );
		wp_set_current_user( 0 );
		$o = $fresh( $cash );
		$r = CodReconciliation::of( $o );
		$check( $o->has_status( 'completed' ) && $collected === $r['remitted'] && '2026-11-02' === $r['remitted_at'] && "{$tag}RMT" === $r['reference'] && $collected === $r['collected'], 'AC-6 the remittance is kept apart from the collection, and the order is Delivered (' . $o->get_status() . ')' );
		$check( ! in_array( $cash, $unremitted(), true ), 'AC-6 a remitted order leaves the unremitted list' );

		// ---- No path from a doubtful carrier status to Delivered ------------------------------------------------------
		// An unmapped code on a shipped order: it stays shipped, in review.
		$odd = $make( 'paymob', StatusModel::SHIPPED );
		$o   = $bosta( $odd, 77, 'Unknown' );
		$check( $o->has_status( StatusModel::SHIPPED ) && '' !== (string) $o->get_meta( CarrierStatus::REVIEW ), 'AC-4 an unmapped code on a shipped order is never taken as delivered: it stays Shipped, in review' );
		// Staff cannot move an order in review to Delivered until they mark it reviewed.
		$o->update_status( 'completed' );
		$check( ! $fresh( $odd )->has_status( 'completed' ), 'AC-4 an order whose carrier status is in review cannot be moved to Delivered' );
		$reviewer = (int) wp_insert_user( array( 'user_login' => "{$tag}rev", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}rev@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $reviewer );
		wp_set_current_user( $reviewer );
		$_POST = array( CarrierStatus::CLEAR => '1', CarrierStatus::CLEAR . '_nonce' => wp_create_nonce( CarrierStatus::CLEAR ) );
		CarrierStatus::reviewed( $odd );
		$_POST = array();
		wp_set_current_user( 0 );
		$o = $fresh( $odd );
		$o->update_status( 'completed' );
		$check( '' === (string) $fresh( $odd )->get_meta( CarrierStatus::REVIEW ) && $fresh( $odd )->has_status( 'completed' ), 'AC-4 once staff mark it reviewed, they decide' );
		// Older statuses never move an order back.
		$ahead = $make( 'paymob', StatusModel::PREPARING );
		$bosta( $ahead, 20, 'Picked up' );
		$bosta( $ahead, 41, 'Out for delivery' );
		$o = $bosta( $ahead, 20, 'Picked up' );
		$check( $o->has_status( StatusModel::OUT_FOR_DELIVERY ), 'AC-4 an older code arriving late does not move the order back (' . $o->get_status() . ')' );
		// Delivered cash, then returned: review, and no remittance until it is resolved.
		$both = $make( 'cod', StatusModel::SHIPPED );
		$bosta( $both, 45, 'Delivered' );
		$o = $bosta( $both, 46, 'Returned to business' );
		$check( $o->has_status( StatusModel::COD_COLLECTED ) && '' !== (string) $o->get_meta( CarrierStatus::REVIEW ), 'AC-5 returned after delivered cannot be applied: the order is in review' );
		$check( null !== CodReconciliation::remit( $fresh( $both ), (string) $o->get_total(), '2026-11-02', 'X' ) && ! $fresh( $both )->has_status( 'completed' ), 'AC-5 an order in review takes no remittance, so it cannot reach Delivered' );
		// Remittance before collection, malformed money, impossible dates.
		$early_cash = $make( 'cod', StatusModel::SHIPPED );
		$check( null !== CodReconciliation::remit( $fresh( $early_cash ), '100', '2026-11-02', 'X' ) && '' === (string) $fresh( $early_cash )->get_meta( CodReconciliation::REMITTED ), 'AC-6 a remittance before the carrier reported the cash collected is refused' );
		$staff_cash = $make( 'cod', StatusModel::SHIPPED );
		$o          = $fresh( $staff_cash );
		$o->update_status( StatusModel::COD_COLLECTED );
		$o = $fresh( $staff_cash );
		$check( wc_format_decimal( $o->get_total() ) === (string) $o->get_meta( CodReconciliation::COLLECTED ) && (int) $o->get_meta( CodReconciliation::COLLECTED_AT ) > 0, 'AC-6 staff moving a cash order to COD Collected records what was collected and when' );
		$check( null !== CodReconciliation::remit( $o, '100-50', '2026-11-02', 'X' ) && null !== CodReconciliation::remit( $o, '100', '2026-99-99', 'X' ) && '' === (string) $fresh( $staff_cash )->get_meta( CodReconciliation::REMITTED ), 'AC-6 a malformed amount or an impossible date is refused' );

		// ---- AC-4: the table is in the administration ------------------------------------------------------------
		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );
		$_GET  = array( 'section' => 'mizzey_carrier_statuses' );
		$_POST = array( CarrierStatus::NONCE . '_nonce' => wp_create_nonce( CarrierStatus::NONCE ), 'mizzey_carrier_map' => array( '20' => 'in-transit', '46' => 'returned', '99' => 'delivered', 'x' => 'delivered', '47' => 'paid' ), 'mizzey_carrier_new_code' => '33' );
		CarrierStatus::save();
		ob_start();
		CarrierStatus::settings();
		$screen = (string) ob_get_clean();
		$map    = CarrierStatus::map();
		$check( array( '20' => 'in-transit', '46' => 'returned', '99' => 'delivered' ) === $map && str_contains( $screen, '>33<' ) && str_contains( $screen, 'Returned to origin is its own state' ), 'AC-4 staff edit the mapping in WooCommerce, Settings, Shipping, Carrier statuses; a code that is not a number and a state that does not exist are refused' );
		$_POST = array( 'mizzey_carrier_map' => array( '20' => 'delivered' ) );
		CarrierStatus::save();
		$check( 'in-transit' === ( CarrierStatus::map()['20'] ?? '' ), 'AC-4 a change without the form\'s nonce is refused' );

		// ---- AC-1: a governorate zone, its rate, and free shipping by coupon --------------------------------------
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "{$tag} Cairo" );
		$zone->set_zone_order( -1000 );
		$zone->add_location( 'EG:EGC', 'state' );
		$zone->save();
		$s->on_finish( static function () use ( $zone ) { $zone->delete(); } );
		$flat = $zone->add_shipping_method( 'flat_rate' );
		update_option( "woocommerce_flat_rate_{$flat}_settings", array( 'enabled' => 'yes', 'title' => 'Cairo', 'cost' => '45', 'tax_status' => 'none', 'mizzey_estimate_en' => '1 to 2 working days', 'mizzey_estimate_ar' => 'من يوم إلى يومين عمل' ) );
		$free = $zone->add_shipping_method( 'free_shipping' );
		update_option( "woocommerce_free_shipping_{$free}_settings", array( 'enabled' => 'yes', 'title' => 'Free', 'requires' => 'coupon' ) );
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
		$package = array( 'destination' => array( 'country' => 'EG', 'state' => 'EGC', 'postcode' => '', 'city' => 'Cairo', 'address' => '', 'address_2' => '' ), 'contents' => array(), 'contents_cost' => 240, 'applied_coupons' => array() );
		$matched = \WC_Shipping_Zones::get_zone_matching_package( $package );
		$check( $matched->get_id() === $zone->get_id(), 'AC-1 an address in Cairo governorate falls in the Cairo zone staff made in admin' );
		$coupon = new \WC_Coupon();
		$coupon->set_code( "{$tag}ship" );
		$coupon->set_props( array( 'discount_type' => 'fixed_cart', 'amount' => '0', 'free_shipping' => true ) );
		$coupon->save();
		$s->track_post( $coupon->get_id() );
		WC()->cart->empty_cart( false );
		WC()->customer->set_shipping_country( 'EG' );
		WC()->customer->set_shipping_state( 'EGC' );
		WC()->cart->add_to_cart( $product->get_id(), 2 );
		WC()->cart->calculate_totals();
		$rates_of = static function (): array {
			$out = array();
			foreach ( WC()->shipping()->get_packages() as $p ) {
				foreach ( (array) ( $p['rates'] ?? array() ) as $rate ) {
					$out[ $rate->get_method_id() ] = $rate;
				}
			}
			return $out;
		};
		$before = $rates_of();
		WC()->cart->apply_coupon( "{$tag}ship" );
		WC()->cart->calculate_totals();
		$after = $rates_of();
		WC()->cart->empty_cart( false );
		wc_clear_notices();
		$check( isset( $before['flat_rate'] ) && '45.00' === wc_format_decimal( $before['flat_rate']->get_cost(), 2 ) && ! isset( $before['free_shipping'] ) && isset( $after['free_shipping'] ), 'AC-1 the zone\'s rate is charged, and a free-shipping coupon brings free shipping' );
		$check( '1 to 2 working days' === (string) ( isset( $before['flat_rate'] ) ? ( $before['flat_rate']->get_meta_data()[ DeliveryEstimate::META ] ?? '' ) : '' ), 'AC-2 the rate the cart offers carries its estimate' );

		// ---- AC-2: the estimate, in the customer's language -------------------------------------------------------
		$estimate = static function ( string $lang ) use ( $flat ): string {
			do_action( 'wpml_switch_language', $lang );
			$rate = new \WC_Shipping_Rate( "flat_rate:{$flat}", 'Cairo', 45, array(), 'flat_rate', $flat );
			DeliveryEstimate::rates( array( $rate ) );
			return (string) ( $rate->get_meta_data()[ DeliveryEstimate::META ] ?? '' );
		};
		$en = $estimate( 'en' );
		$ar = $estimate( 'ar' );
		do_action( 'wpml_switch_language', null );
		$check( '1 to 2 working days' === $en && 'من يوم إلى يومين عمل' === $ar, 'AC-2 the rate carries its estimate in the customer\'s language (' . $en . ' / ' . $ar . ')' );
		$bare = new \WC_Shipping_Rate( 'free_shipping:' . $free, 'Free', 0, array(), 'free_shipping', $free );
		DeliveryEstimate::rates( array( $bare ) );
		$check( ! isset( $bare->get_meta_data()[ DeliveryEstimate::META ] ), 'AC-2 a rate with no estimate shows none' );

		return $ok;
	}
);

<?php
/**
 * t113, spec 066 slice 9: the delivery estimate as dates on the product page (PDP-14; the owner's ruling of
 * 26 November 2026).
 *
 * With the estimate of specs/055 at 2 to 5 working days, Saturday to Thursday working, the cut-off at 14:00 Cairo:
 *
 * - an order on Thursday 26 November 2026 at 13:00 is handled that day: Sun 29 Nov to Wed 2 Dec;
 * - at 15:00 it counts from Saturday: Mon 30 Nov to Thu 3 Dec;
 * - on Friday (a day off) it counts from Saturday too;
 * - a holiday on Sunday 29 November is skipped;
 * - the cut-off is Cairo's, whatever the server's and the site's time zone;
 * - exact mode states the last day only ("get it by"), range mode both; in English and in Arabic.
 *
 * The settings are put back at the end.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Shipping\DeliveryDates;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't113-delivery-dates', 'PDP-14 (slice 9): the delivery estimate as dates, in Cairo time, on the working days, after the cut-off and the holidays, in both languages' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$kept = array();
		foreach ( array( DeliveryDates::MODE, DeliveryDates::DAYS, DeliveryDates::CUTOFF, DeliveryDates::HOLIDAYS, 'timezone_string', 'gmt_offset' ) as $option ) {
			$kept[ $option ] = get_option( $option, null );
		}
		$php_zone = date_default_timezone_get();
		$s->on_finish(
			static function () use ( $kept, $php_zone ): void {
				foreach ( $kept as $option => $value ) {
					null === $value ? delete_option( $option ) : update_option( $option, $value );
				}
				date_default_timezone_set( $php_zone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions
			}
		);
		update_option( DeliveryDates::DAYS, DeliveryDates::DEFAULT_DAYS );
		update_option( DeliveryDates::CUTOFF, '14:00' );
		update_option( DeliveryDates::HOLIDAYS, '' );
		$cairo = new \DateTimeZone( 'Africa/Cairo' );
		$at    = static fn ( string $when ): \DateTimeImmutable => new \DateTimeImmutable( $when, $cairo );
		$ymd   = static function ( array $d ): string {
			return $d['from']->format( 'D Y-m-d' ) . ' to ' . $d['to']->format( 'D Y-m-d' );
		};

		$d = DeliveryDates::dates( 2, 5, $at( '2026-11-26 13:00' ) );
		$check( 'Sun 2026-11-29 to Wed 2026-12-02' === $ymd( $d ), 'Thursday 13:00, before the cut-off: handled that day, Sun 29 Nov to Wed 2 Dec (' . $ymd( $d ) . ')' );
		$d = DeliveryDates::dates( 2, 5, $at( '2026-11-26 15:00' ) );
		$check( 'Mon 2026-11-30 to Thu 2026-12-03' === $ymd( $d ), 'Thursday 15:00, after the cut-off: counts from Saturday, Mon 30 Nov to Thu 3 Dec (' . $ymd( $d ) . ')' );
		$d = DeliveryDates::dates( 2, 5, $at( '2026-11-27 10:00' ) );
		$check( 'Mon 2026-11-30 to Thu 2026-12-03' === $ymd( $d ), 'Friday, a day off: counts from Saturday, Mon 30 Nov to Thu 3 Dec (' . $ymd( $d ) . ')' );
		update_option( DeliveryDates::HOLIDAYS, "2026-11-29\n" );
		$d = DeliveryDates::dates( 2, 5, $at( '2026-11-26 13:00' ) );
		$check( 'Mon 2026-11-30 to Thu 2026-12-03' === $ymd( $d ), 'a holiday on Sunday 29 Nov is skipped: Mon 30 Nov to Thu 3 Dec (' . $ymd( $d ) . ')' );
		update_option( DeliveryDates::HOLIDAYS, '' );

		// Cairo's clock, whatever the server's and the site's: 12:30 UTC is 14:30 in Cairo (after the cut-off) and
		// 07:30 in New York (before it).
		date_default_timezone_set( 'Pacific/Kiritimati' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions
		update_option( 'timezone_string', 'America/New_York' );
		$d = DeliveryDates::dates( 2, 5, new \DateTimeImmutable( '2026-11-26 12:30', new \DateTimeZone( 'UTC' ) ) );
		$check( 'Mon 2026-11-30 to Thu 2026-12-03' === $ymd( $d ), 'the cut-off is read in Cairo time, with the server in Kiritimati and the site in New York (' . $ymd( $d ) . ')' );
		$en_range = DeliveryDates::sentence( array( 'min' => 2, 'max' => 5 ), $at( '2026-11-26 15:00' ) );
		$check( 'Order now, get it between <strong class="mizzey-buy__date">Mon 30 Nov</strong> and <strong class="mizzey-buy__date">Thu 3 Dec</strong>' === $en_range, 'the dates are written in Cairo time whatever the site\'s zone: ' . wp_strip_all_tags( $en_range ) );
		date_default_timezone_set( $php_zone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions
		null === $kept['timezone_string'] ? delete_option( 'timezone_string' ) : update_option( 'timezone_string', $kept['timezone_string'] );

		// The two modes, in English and in Arabic.
		update_option( DeliveryDates::MODE, 'range' );
		$en_range = wp_strip_all_tags( DeliveryDates::sentence( array( 'min' => 2, 'max' => 5 ), $at( '2026-11-26 15:00' ) ) );
		update_option( DeliveryDates::MODE, 'exact' );
		$en_exact = wp_strip_all_tags( DeliveryDates::sentence( array( 'min' => 2, 'max' => 5 ), $at( '2026-11-26 15:00' ) ) );
		$check( 'Order now, get it between Mon 30 Nov and Thu 3 Dec' === $en_range, "range, English: \"{$en_range}\"" );
		$check( 'Order now, get it by Thu 3 Dec' === $en_exact, "exact, English, the estimate's last day: \"{$en_exact}\"" );
		switch_to_locale( 'ar' );
		$ar_exact = wp_strip_all_tags( DeliveryDates::sentence( array( 'min' => 2, 'max' => 5 ), $at( '2026-11-26 15:00' ) ) );
		update_option( DeliveryDates::MODE, 'range' );
		$ar_range = wp_strip_all_tags( DeliveryDates::sentence( array( 'min' => 2, 'max' => 5 ), $at( '2026-11-26 15:00' ) ) );
		restore_previous_locale();
		$check( str_contains( $ar_exact, 'يصلك بحلول' ) && str_contains( $ar_exact, 'الخميس 3 ديسمبر' ), "exact, Arabic: \"{$ar_exact}\"" );
		$check( str_contains( $ar_range, 'يصلك بين' ) && str_contains( $ar_range, '30 نوفمبر' ) && str_contains( $ar_range, 'الخميس 3 ديسمبر' ), "range, Arabic: \"{$ar_range}\"" );

		// A setting that is not what it should be falls back: no working day, a cut-off that is not a time.
		update_option( DeliveryDates::DAYS, array() );
		update_option( DeliveryDates::CUTOFF, '25:99' );
		$d = DeliveryDates::dates( 2, 5, $at( '2026-11-26 15:00' ) );
		$check( 'Mon 2026-11-30 to Thu 2026-12-03' === $ymd( $d ), 'with no working day set and a wrong cut-off, the defaults stand (' . $ymd( $d ) . ')' );

		return $ok;
	}
);

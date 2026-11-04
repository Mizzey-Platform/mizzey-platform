<?php
/**
 * t99: the WhatsApp click-to-chat link (specs/059-whatsapp-contact).
 *
 * AC-1: with a number set in the admin, the link opens a chat with that number and the greeting in the page's
 *       language (English when the Arabic is empty); a number written with spaces, a plus or 00 is read as digits.
 * AC-2: with no number, or an unusable one, there is no link.
 * AC-3: the link reaches the storefront: the REST route in each language, and a page that carries the shortcode.
 * AC-4: the settings are in WooCommerce > Settings > General.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\ContactLinks;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't99-whatsapp-contact', 'CMS-10: the WhatsApp click-to-chat link' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$kept = array();
		foreach ( array( ContactLinks::NUMBER, ContactLinks::MESSAGE . 'en', ContactLinks::MESSAGE . 'ar' ) as $o ) {
			$kept[ $o ] = get_option( $o, null );
		}
		$s->on_finish( static function () use ( $kept ): void { foreach ( $kept as $o => $v ) { null === $v ? delete_option( $o ) : update_option( $o, $v ); } } );

		// ---- AC-2: no number, no link -------------------------------------------------------------------------------
		update_option( ContactLinks::NUMBER, '' );
		$none = ContactLinks::whatsappUrl( 'en' );
		update_option( ContactLinks::NUMBER, '12 34' );
		$short = ContactLinks::whatsappUrl( 'en' );
		$check( '' === $none && '' === $short, 'AC-2 with no number, or one too short to be a number, there is no link' );

		// ---- AC-1: the link -----------------------------------------------------------------------------------------
		update_option( ContactLinks::NUMBER, '+20 100 123 4567' );
		update_option( ContactLinks::MESSAGE . 'en', 'Hello Mizzey, I have a question.' );
		update_option( ContactLinks::MESSAGE . 'ar', 'مرحبا ميزي، لدي سؤال.' );
		$en = ContactLinks::whatsappUrl( 'en' );
		$ar = ContactLinks::whatsappUrl( 'ar' );
		update_option( ContactLinks::NUMBER, '00201001234567' );
		update_option( ContactLinks::MESSAGE . 'ar', '' );
		$fallback = ContactLinks::whatsappUrl( 'ar' );
		$check( 'https://wa.me/201001234567?text=' . rawurlencode( 'Hello Mizzey, I have a question.' ) === $en && 'https://wa.me/201001234567?text=' . rawurlencode( 'مرحبا ميزي، لدي سؤال.' ) === $ar, 'AC-1 CMS-10 the link opens a chat with the number and the greeting in the page\'s language (' . $en . ')' );
		$check( 'https://wa.me/201001234567?text=' . rawurlencode( 'Hello Mizzey, I have a question.' ) === $fallback, 'AC-1 a number written with 00 is read as digits; an empty Arabic greeting falls back to the English one' );

		// ---- AC-3: on the storefront ------------------------------------------------------------------------------------
		update_option( ContactLinks::MESSAGE . 'ar', 'مرحبا ميزي، لدي سؤال.' );
		$rest_en = sign_in_json( 'GET', rest_url( 'mizzey/v1/contact' ), null );
		$rest_ar = sign_in_json( 'GET', home_url( '/ar/wp-json/mizzey/v1/contact' ), null );
		$check( str_contains( (string) ( $rest_en['data']['whatsapp'] ?? '' ), rawurlencode( 'Hello' ) ) && str_contains( (string) ( $rest_ar['data']['whatsapp'] ?? '' ), rawurlencode( 'مرحبا' ) ), 'AC-3 the REST route gives the link in the request\'s language' );
		$page = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'T99 contact ' . wp_rand(), 'post_content' => '<!-- wp:shortcode -->[mizzey_whatsapp]<!-- /wp:shortcode -->' ) );
		$s->on_finish( static function () use ( $page ): void { wp_delete_post( $page, true ); } );
		$url  = (string) get_permalink( $page );
		$r    = wp_remote_get( $url, array( 'timeout' => 60 ) );
		$body = (string) wp_remote_retrieve_body( $r );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), $body );
		$check( str_contains( $body, 'https://wa.me/201001234567?text=' ), 'AC-3 a page with the shortcode block carries the click-to-chat address' );

		// ---- AC-4: the settings -------------------------------------------------------------------------------------------
		$ids = array_column( (array) apply_filters( 'woocommerce_general_settings', array() ), 'id' );
		$check( array() === array_diff( array( ContactLinks::NUMBER, ContactLinks::MESSAGE . 'en', ContactLinks::MESSAGE . 'ar' ), $ids ), 'AC-4 CMS-09 the number and the greetings are set in WooCommerce > Settings > General, without a developer' );

		return $ok;
	}
);

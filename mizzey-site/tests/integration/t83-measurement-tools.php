<?php
/**
 * t83: measurement and advertising tools (MKT-01 to MKT-03, MKT-05, MKT-06, MKT-09, MKT-10).
 * See specs/043-measurement-tools.
 *
 * AC-1: with a container named, every storefront page loads Tag Manager after consent defaults of "denied"; with
 *       none, nothing is loaded; the data layer names the language; the admin is untouched.
 * AC-2: the consent banner is on every storefront page, in the visitor's language; the standing choice is pushed as the
 *       mizzey_consent event on every page; campaign attribution on the order (WooCommerce's) runs only with consent,
 *       decided in the browser, never in the cached page.
 * AC-3: one product feed per language, in Google's product format: the SKU as the id, the price in EGP, the
 *       language's link, variations grouped; a hidden or draft product is not in it.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Marketing\ConsentBanner;
use MizzeySite\Marketing\ProductFeed;
use MizzeySite\Marketing\TagManager;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't83-measurement-tools', 'MKT-01 to MKT-10: measurement and advertising tools' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag  = 't83' . wp_rand( 1000, 9999 );
		$keep = get_option( TagManager::CONTAINER, null );
		$s->on_finish(
			static function () use ( $keep ): void {
				null === $keep ? delete_option( TagManager::CONTAINER ) : update_option( TagManager::CONTAINER, $keep );
				ProductFeed::forget();
				unset( $_COOKIE[ ConsentBanner::COOKIE ] );
			}
		);
		$page = static function ( string $url ): array {
			$r    = wp_remote_get( $url, array( 'timeout' => 60 ) );
			$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, $code, $body );
			return array( $code, $body, is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'content-type' ) );
		};
		$ar_home = (string) apply_filters( 'wpml_permalink', home_url( '/' ), 'ar' );

		// ---- AC-1: Tag Manager, after consent defaults ----------------------------------------------------------
		delete_option( TagManager::CONTAINER );
		[ , $none ] = $page( home_url( '/' ) );
		$check( ! str_contains( $none, 'googletagmanager.com' ) && str_contains( $none, "gtag('consent', 'default'" ), 'AC-1 with no container named, nothing is loaded but the consent defaults and the data layer' );
		update_option( TagManager::CONTAINER, 'GTM-T83TEST' );
		[ , $home ] = $page( home_url( '/' ) );
		$consent_at = strpos( $home, "gtag('consent', 'default'" );
		$gtm_at     = strpos( $home, 'googletagmanager.com/gtm.js' );
		$check( false !== $consent_at && false !== $gtm_at && $consent_at < $gtm_at && str_contains( $home, "'GTM-T83TEST'" ) , 'AC-1 Tag Manager loads on the storefront, after the consent defaults' );
		$check( ! str_contains( $home, 'ns.html?id=' ), 'AC-1 no no-script container: without the script no consent can be given, so nothing loads' );
		$check( str_contains( $home, "value = match && match[1] === 'granted' ? 'granted' : 'denied'" ) && str_contains( $home, 'window.mizzeyLanguage="en"' ), 'AC-1 consent is denied until the visitor accepts; the data layer names the language (en)' );
		// E-288-1: the standing choice is pushed as an event on every page, before the container, so a pixel that does not
		// read Google's consent mode can wait for it.
		$event_at = strpos( $home, "window.dataLayer.push({ event: 'mizzey_consent', consent: value });" );
		$check( false !== $event_at && false !== $gtm_at && $event_at < $gtm_at, 'AC-2 every page pushes the standing choice of the visitor (mizzey_consent) before Tag Manager loads' );
		[ , $ar ] = $page( $ar_home );
		$check( str_contains( $ar, 'window.mizzeyLanguage="ar"' ) && str_contains( $ar, "'GTM-T83TEST'" ), 'AC-1 the Arabic storefront loads the same container, with the language ar' );
		update_option( TagManager::CONTAINER, '"><script>alert(1)</script>' );
		$check( '' === TagManager::container(), 'AC-1 a malformed container id is refused, so nothing can be injected' );
		update_option( TagManager::CONTAINER, 'GTM-T83TEST' );

		// ---- AC-2: the consent banner and consent-gated attribution ----------------------------------------------
		$check( str_contains( $home, 'id="mizzey-consent"' ) && str_contains( $home, 'Accept all' ) && str_contains( $home, 'Essential only' ) && str_contains( $home, 'assets/consent.js' ), 'AC-2 the consent banner and its script are on the English storefront' );
		$check( str_contains( $home, 'data-mizzey-consent-open' ) && str_contains( $ar, 'إعدادات ملفات تعريف الارتباط' ), 'AC-2 a "Cookie settings" link reopens the choice, in both languages' );
		$check( 'manage_woocommerce' === apply_filters( 'option_page_capability_' . TagManager::PAGE, 'manage_options' ), 'AC-1 shop managers can save the container' );
		$check( str_contains( $ar, 'قبول الكل' ) && str_contains( $ar, 'الأساسية فقط' ), 'AC-2 the banner speaks Arabic on the Arabic storefront' );
		unset( $_COOKIE[ ConsentBanner::COOKIE ] );
		$denied = apply_filters( 'wc_order_attribution_allow_tracking', true );
		$_COOKIE[ ConsentBanner::COOKIE ] = 'denied';
		$refused = apply_filters( 'wc_order_attribution_allow_tracking', true );
		$_COOKIE[ ConsentBanner::COOKIE ] = 'granted';
		$granted = apply_filters( 'wc_order_attribution_allow_tracking', true );
		unset( $_COOKIE[ ConsentBanner::COOKIE ] );
		// E-288-2: the server never prints attribution on, whatever the cookie, so a cached page cannot carry one visitor's
		// consent to another; the browser switches it on from its own cookie, before WooCommerce's script starts.
		$switch    = 'mizzey_consent=granted(?:;|$)/.test(document.cookie)){window.wc_order_attribution.params.allowTracking=true;}';
		$switch_at = strpos( $home, $switch );
		$wc_at     = strpos( $home, 'order-attribution' );
		$script_at = false === $switch_at ? false : strpos( $home, 'js/frontend/order-attribution', $switch_at );
		$check( false === $denied && false === $refused && false === $granted && 'yes' === get_option( 'woocommerce_feature_order_attribution_enabled' ), 'AC-2 the page is always printed with campaign attribution off, whatever the cookie of the visitor' );
		$check( false !== $switch_at && false !== $wc_at && false !== $script_at, 'AC-2 the campaign is recorded on the order (WooCommerce order attribution) only once the visitor accepts: switched on in the browser from the consent cookie, before the WooCommerce script' );

		// ---- AC-3: the product feeds --------------------------------------------------------------------------------
		$w     = new Workflows( $s );
		$sku   = "{$tag}-FEED";
		$en_id = $w->simple( $sku, null );
		$p     = wc_get_product( $en_id );
		$p->set_regular_price( '320' );
		$p->set_sale_price( '280' );
		$p->save();
		[ $parent, $children ] = $w->variable( "{$tag}-VAR", array( 'S' => null, 'L' => null ) );
		foreach ( $children as $child ) {
			$v = wc_get_product( $child );
			$v->set_regular_price( '150' );
			$v->save();
		}
		$hidden = $w->simple( "{$tag}-HID", null );
		$h      = wc_get_product( $hidden );
		$h->set_regular_price( '99' );
		$h->set_catalog_visibility( 'hidden' );
		$h->save();
		$locked = $w->simple( "{$tag}-PWD", null );
		$lk     = wc_get_product( $locked );
		$lk->set_regular_price( '99' );
		$lk->set_post_password( 'secret' );
		$lk->save();
		$draft = $w->simple( "{$tag}-DRF", null );
		$d     = wc_get_product( $draft );
		$d->set_regular_price( '99' );
		$d->set_status( 'draft' );
		$d->save();
		// Translated last: no product is created after a translation in one process (#247, A11).
		$ar_id = $w->translate( 'editor', $en_id );
		ProductFeed::forget();
		$items = static function ( string $xml ): array {
			$out = array();
			// A page that is not a feed (the feed switched off) is an expected answer here, not a parser warning to log.
			$quiet = libxml_use_internal_errors( true );
			$doc   = simplexml_load_string( $xml );
			libxml_clear_errors();
			libxml_use_internal_errors( $quiet );
			if ( false === $doc ) {
				return $out;
			}
			foreach ( $doc->channel->item as $item ) {
				$g = $item->children( 'http://base.google.com/ns/1.0' );
				$out[ (string) $g->id ] = array( 'price' => (string) $g->price, 'sale' => (string) $g->sale_price, 'link' => (string) $g->link, 'group' => (string) $g->item_group_id, 'availability' => (string) $g->availability );
			}
			return $out;
		};
		[ $code, $feed_en, $type ] = $page( ProductFeed::url( 'en' ) );
		$en_items = $items( $feed_en );
		$check( 200 === $code && str_contains( $type, 'application/xml' ) && isset( $en_items[ $sku ] ) && '320.00 EGP' === $en_items[ $sku ]['price'] && '280.00 EGP' === $en_items[ $sku ]['sale'] && 'in_stock' === $en_items[ $sku ]['availability'] && ! str_contains( $en_items[ $sku ]['link'], '/ar/' ), 'AC-3 the English feed lists the product by its SKU, price and sale price in EGP, availability and English address (' . count( $en_items ) . ' items)' );
		$grouped = array_filter( $en_items, static fn ( array $i ): bool => "{$tag}-VAR" === $i['group'] );
		$check( 2 === count( $grouped ) && ! isset( $en_items[ "{$tag}-VAR" ] ), 'AC-3 a variable product is one item per variation, grouped by the product\'s SKU' );
		$check( ! isset( $en_items[ "{$tag}-HID" ] ) && ! isset( $en_items[ "{$tag}-DRF" ] ) && ! isset( $en_items[ "{$tag}-PWD" ] ), 'AC-3 a hidden, draft or password-protected product is not in the feed' );
		[ , $feed_ar ] = $page( ProductFeed::url( 'ar' ) );
		$ar_items = $items( $feed_ar );
		$check( $ar_id > 0 && isset( $ar_items[ $sku ] ) && str_contains( $ar_items[ $sku ]['link'], '/ar/' ), 'AC-3 the Arabic feed lists the same item, by the same SKU, at its Arabic address' );

		return $ok;
	}
);

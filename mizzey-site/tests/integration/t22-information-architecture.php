<?php
/**
 * t22, #242 AC-242-01 to AC-242-07 and guardrail G-1: every contracted page is reachable in both languages, the
 * account screens stay on their native routes, and no screen has two managed routes.
 *
 * Asserted against served HTTP, because a URL cannot be asserted honestly any other way. The pattern follows
 * t21: #241 measured that an in-process language switch reports direction correctly while the cached language
 * tag lags, so only a real request distinguishes a correct document from an artifact.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/**
 * Fetch a URL and return the status plus the <html> element.
 *
 * @return array{status:int,html:string,lang:string,rtl:bool,body:string}
 */
function t22_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'cookies' => array() ) );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'html' => $r->get_error_message(), 'lang' => '', 'rtl' => false, 'body' => '' );
	}
	$body = (string) wp_remote_retrieve_body( $r );
	storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), $body );
	preg_match( '/<html[^>]*>/i', $body, $m );
	$html = $m[0] ?? '';
	preg_match( '/\blang="([^"]*)"/i', $html, $l );
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $r ),
		'html'   => $html,
		'lang'   => $l[1] ?? '',
		'rtl'    => (bool) preg_match( '/\bdir="rtl"/i', $html ),
		'body'   => $body,
	);
}

/**
 * The Location header of a URL that redirects, or an empty string.
 */
function t22_checkout_target( string $url ): string {
	$r = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'cookies' => array() ) );
	if ( is_wp_error( $r ) ) {
		return '';
	}
	storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
	return (string) wp_remote_retrieve_header( $r, 'location' );
}

run(
	new Scenario( 't22-information-architecture', 'AC-242-01 to AC-242-07, G-1' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok = true;

		// ---- AC-242-01 and AC-242-02: every contracted page, both languages -----------------------------
		// Page rows that are delivered by a page record, and the WooCommerce pages. The rows delivered by an
		// archive, a singular product or an account endpoint are asserted separately below, because creating a
		// page for them would be the error G-1 forbids.
		$pages = array(
			'IA-01' => 'home',
			'IA-08' => 'cart',
			'IA-09' => 'checkout',
			'IA-11' => 'wishlist',
			'IA-17' => 'my-account',
			'IA-21' => 'track-your-order',
			'IA-24' => 'about',
			'IA-25' => 'contact-us',
			'IA-26' => 'help',
			'IA-27' => 'authenticity-guarantee',
			'IA-28' => 'shipping-policy',
			'IA-29' => 'returns-and-refunds',
			'IA-30' => 'privacy-policy',
			'IA-31' => 'terms-and-conditions',
			'IA-32' => 'offers',
			'IA-02' => 'shop',
		);
		foreach ( $pages as $row => $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page instanceof \WP_Post ) {
				$s->note( "FAIL {$row}: no page record for '{$slug}'" );
				$ok = false;
				continue;
			}
			$en = t22_get( (string) get_permalink( $page->ID ) );

			$trid  = $sitepress->get_element_trid( $page->ID, 'post_page' );
			$group = $trid ? $sitepress->get_element_translations( $trid, 'post_page' ) : array();
			$ar_id = isset( $group['ar'] ) ? (int) $group['ar']->element_id : 0;

			if ( ! $ar_id || $ar_id === (int) $page->ID ) {
				$s->note( "FAIL {$row} ({$slug}): no distinct Arabic record" );
				$ok = false;
				continue;
			}
			$sitepress->switch_lang( 'ar', true );
			$ar_url = (string) get_permalink( $ar_id );
			$sitepress->switch_lang( 'en', true );
			$ar = t22_get( $ar_url );

			// Checkout is the one row the platform answers with a redirect: WooCommerce sends an empty cart
			// back to the cart page, identically in both languages, measured under #241. So for that row the
			// obligation is the record and language-correct routing, and the redirect target is asserted below
			// instead of a 200.
			if ( 'checkout' === $slug ) {
				$good = 302 === $en['status'] || 200 === $en['status'];
				$good = $good && ( 302 === $ar['status'] || 200 === $ar['status'] );
			} else {
				$good = 200 === $en['status'] && 'en-US' === $en['lang'] && ! $en['rtl']
					&& 200 === $ar['status'] && 'ar' === $ar['lang'] && $ar['rtl'];
				// And each answer must be that page: its own title or text, or, for a page that renders a
				// component instead of text, that component. Status, language and direction are all right on a
				// placeholder too.
				$component = array( 'my-account' => 'woocommerce-form-login', 'track-your-order' => 'woocommerce-form-track-order' )[ $slug ] ?? '';
				$shown     = '' !== $component
					? false !== strpos( $en['body'], $component ) && false !== strpos( $ar['body'], $component )
					: storefront_shows_page( $en['body'], $page ) && storefront_shows_page( $ar['body'], get_post( $ar_id ) );
				if ( ! $shown ) {
					$s->note( "FAIL {$row} ({$slug}): an address answered without rendering its page" );
				}
				$good = $good && $shown;
			}
			$s->note( sprintf(
				'%s %-22s en=%d/%s  ar=%d/%s%s',
				$good ? 'ok  ' : 'FAIL',
				$row . ' ' . $slug,
				$en['status'],
				$en['lang'] ?: '-',
				$ar['status'],
				$ar['lang'] ?: '-',
				'checkout' === $slug ? ' (empty-cart redirect)' : ( $ar['rtl'] ? ' rtl' : ' NOT-RTL' )
			) );
			if ( ! $good ) {
				$ok = false;
			}

			if ( 'checkout' === $slug ) {
				// The redirect must stay inside its own language: an Arabic checkout request must not land on
				// the English cart. That is the bilingual failure a 200 would never have caught.
				$cart_en = (int) get_option( 'woocommerce_cart_page_id' );
				$en_target = t22_checkout_target( (string) get_permalink( $page->ID ) );
				$sitepress->switch_lang( 'ar', true );
				$cart_ar_url = (string) get_permalink( (int) get_option( 'woocommerce_cart_page_id' ) );
				$sitepress->switch_lang( 'en', true );
				$ar_target = t22_checkout_target( $ar_url );
				$en_good = '' === $en_target || false !== strpos( $en_target, (string) get_permalink( $cart_en ) );
				$ar_good = '' === $ar_target || false !== strpos( $ar_target, $cart_ar_url );
				$s->note( '     IA-09 redirect targets: en -> ' . ( $en_target ?: '(none)' )
					. ' | ar -> ' . ( $ar_target ?: '(none)' ) );
				if ( ! $en_good || ! $ar_good ) {
					$s->note( 'FAIL IA-09: the empty-cart redirect left its own language' );
					$ok = false;
				}
			}

			// AC-242-03: one translation group, English as source.
			$source = isset( $group['ar'] ) ? (string) $group['ar']->source_language_code : '';
			if ( 'en' !== $source ) {
				$s->note( "FAIL AC-242-03 ({$slug}): Arabic source language is " . var_export( $source, true ) );
				$ok = false;
			}

			// AC-242-08: no contracted page reachable only by a query-string identity.
			if ( false !== strpos( (string) get_permalink( $page->ID ), 'page_id=' ) ) {
				$s->note( "FAIL AC-242-08 ({$slug}): permalink is a query string" );
				$ok = false;
			}
		}

		// ---- AC-242-04: the account screens stay on their native routes ---------------------------------
		$account = get_page_by_path( 'my-account', OBJECT, 'page' );
		$account_url = $account ? rtrim( (string) get_permalink( $account->ID ), '/' ) : '';
		$endpoints = array(
			'IA-16' => 'lost-password',
			'IA-18' => 'edit-address',
			'IA-19' => 'orders',
		);
		foreach ( $endpoints as $row => $endpoint ) {
			$en = t22_get( "{$account_url}/{$endpoint}/" );
			$ar_slug = (string) apply_filters( 'wpml_translate_single_string', $endpoint, 'WP Endpoints', $endpoint, 'ar' );
			$sitepress->switch_lang( 'ar', true );
			$ar_account = rtrim( (string) get_permalink( (int) get_option( 'woocommerce_myaccount_page_id' ) ), '/' );
			$sitepress->switch_lang( 'en', true );
			$ar = t22_get( "{$ar_account}/{$ar_slug}/" );
			$good = 200 === $en['status'] && 200 === $ar['status'] && 'ar' === $ar['lang'];
			$s->note( sprintf( '%s %-22s en=%d  ar=%d (%s)', $good ? 'ok  ' : 'FAIL',
				$row . ' ' . $endpoint, $en['status'], $ar['status'], $ar_slug ) );
			if ( ! $good ) {
				$ok = false;
			}
		}

		// G-1: no page record duplicates an endpoint slug, in either language.
		$endpoint_slugs = array();
		foreach ( (array) WC()->query->get_query_vars() as $var ) {
			$endpoint_slugs[] = (string) $var;
			$endpoint_slugs[] = (string) apply_filters( 'wpml_translate_single_string', $var, 'WP Endpoints', (string) $var, 'ar' );
		}
		$endpoint_slugs = array_filter( array_unique( $endpoint_slugs ) );
		$collisions = array();
		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'any', 'suppress_filters' => true ) ) as $p ) {
			if ( in_array( $p->post_name, $endpoint_slugs, true ) ) {
				$collisions[] = "{$p->ID}:{$p->post_name}";
			}
		}
		$s->note( 'G-1 page records duplicating an endpoint slug: ' . ( $collisions ? implode( ', ', $collisions ) : 'none' ) );
		if ( $collisions ) {
			$s->note( 'FAIL G-1: a page record duplicates a native endpoint route' );
			$ok = false;
		}

		// ---- AC-242-05: term archives, per language -----------------------------------------------------
		foreach ( array( 'IA-03' => 'product_cat', 'IA-05' => 'product_brand', 'IA-04' => 'product_collection' ) as $row => $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				$s->note( "FAIL {$row}: taxonomy {$tax} does not exist" );
				$ok = false;
				continue;
			}
			$translatable = (bool) $sitepress->is_translated_taxonomy( $tax );
			// A clearly non-production fixture term: the real names are a client content input.
			$term = wp_insert_term( "t22 fixture {$tax}", $tax );
			if ( is_wp_error( $term ) ) {
				$existing = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 1 ) );
				$term_id = ( ! is_wp_error( $existing ) && $existing ) ? (int) $existing[0]->term_id : 0;
			} else {
				$term_id = (int) $term['term_id'];
				$s->on_finish(
					static function () use ( $term_id, $tax ) {
						wp_delete_term( $term_id, $tax );
					}
				);
			}
			$link = $term_id ? get_term_link( $term_id, $tax ) : '';
			$res = is_string( $link ) ? t22_get( $link ) : array( 'status' => 0, 'lang' => '-' );
			$good = $translatable && 200 === $res['status'];
			$s->note( sprintf( '%s %-22s translatable=%s archive=%d %s', $good ? 'ok  ' : 'FAIL',
				$row . ' ' . $tax, $translatable ? 'yes' : 'NO', $res['status'],
				is_string( $link ) ? $link : '(no link)' ) );
			if ( ! $good ) {
				$ok = false;
			}
		}

		// ---- AC-242-07: search has a stable URL carrying the query, both languages ----------------------
		$en_search = t22_get( (string) add_query_arg( 's', 'mizzey', home_url( '/' ) ) );
		// The Arabic root comes from the language URL WPML reports. home_url() under switch_lang() returned the
		// English root here, which made the first run of this assertion test the wrong URL.
		$languages = (array) apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
		$ar_root = isset( $languages['ar']['url'] ) ? (string) $languages['ar']['url'] : '';
		$ar_search_url = $ar_root ? (string) add_query_arg( 's', 'mizzey', $ar_root ) : '';
		$ar_search = $ar_search_url ? t22_get( $ar_search_url ) : array( 'status' => 0, 'lang' => '-' );
		$good = 200 === $en_search['status'] && 200 === $ar_search['status'] && 'ar' === $ar_search['lang'];
		$s->note( sprintf( '%s IA-06 search          en=%d  ar=%d (%s)', $good ? 'ok  ' : 'FAIL',
			$en_search['status'], $ar_search['status'], $ar_search_url ) );
		if ( ! $good ) {
			$ok = false;
		}

		// ---- AC-242-06: order confirmation is an endpoint, not a listed page ----------------------------
		$confirmation_page = get_page_by_path( 'order-received', OBJECT, 'page' );
		$s->note( 'AC-242-06 order-received as a page record: ' . ( $confirmation_page ? 'PRESENT, which is wrong' : 'none, correct' ) );
		if ( $confirmation_page ) {
			$s->note( 'FAIL AC-242-06: order confirmation has a page record as well as its endpoint' );
			$ok = false;
		}
		$s->note( 'AC-242-06 order-received endpoint query var: '
			. ( WC()->query->get_query_vars()['order-received'] ?? 'MISSING' ) );

		// Recorded, not asserted: WooCommerce redirects an empty cart away from checkout, in both languages
		// identically, so a checkout request says nothing about language resolution. Measured under #241.
		return $ok;
	}
);

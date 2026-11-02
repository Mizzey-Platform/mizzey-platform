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
		// IA-04 in the admin: the products list gives the Collections column a width, so it is not squeezed.
		set_current_screen( 'edit-product' );
		// A web request always names its host; WordPress's admin head reads it (wp_admin_canonical_url).
		$had_host             = $_SERVER['HTTP_HOST'] ?? null;
		$_SERVER['HTTP_HOST'] = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		ob_start();
		do_action( 'admin_head' );
		$admin_head = (string) ob_get_clean();
		if ( null === $had_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $had_host;
		}
		set_current_screen( 'front' );
		$width_ok = false !== strpos( $admin_head, '.column-taxonomy-product_collection{width:9%}' );
		$s->note( ( $width_ok ? 'ok   ' : 'FAIL ' ) . 'IA-04 the products list gives the Collections column a width' );
		$ok = $ok && $width_ok;
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
			$res = is_string( $link ) ? t22_get( $link ) : array( 'status' => 0, 'lang' => '-', 'body' => '' );
			// The archive must be the term's own: its name on the page, not only a 200. The collection archive
			// answered 200 with nothing on it until 5 October 2026, and this line read "archive=200" and passed.
			// Both languages, a product on the page and every link the archive points at are t28.
			$good = $translatable && 200 === $res['status'] && storefront_shows( $res['body'], "t22 fixture {$tax}" );
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

		// ---- The production path (audit of 2 November 2026): `wp mizzey languages provision` ---------------
		// The baseline is made by LanguageSetup, the code the release command runs. Run again it changes nothing,
		// keeps every endpoint wording, and makes a missing Arabic record again, served at its Arabic address.
		$before_pages = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'page'" );
		$front_before = array( get_option( 'show_on_front' ), (int) get_option( 'page_on_front' ), (int) get_option( 'woocommerce_terms_page_id' ) );
		$again        = \MizzeySite\I18n\LanguageSetup::provision( false );
		$after_pages  = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'page'" );
		$front_after  = array( get_option( 'show_on_front' ), (int) get_option( 'page_on_front' ), (int) get_option( 'woocommerce_terms_page_id' ) );
		$good = ! isset( $again['error'] ) && $before_pages === $after_pages && $front_before === $front_after
			&& array() === $again['endpoints']['made'] && array() === $again['endpoints']['missing']
			&& count( \MizzeySite\I18n\LanguageSetup::ENDPOINTS ) === count( $again['endpoints']['kept'] )
			&& 'orders-ar' === ( $again['endpoints']['kept']['orders'] ?? '' )
			&& array( 'product_brand' => true, 'product_collection' => true ) === $again['taxonomies']
			&& count( \MizzeySite\I18n\LanguageSetup::PAGES ) === count( $again['pages']['ar'] ) && array() === $again['pages']['drafts']
			&& array() === \MizzeySite\I18n\LanguageSetup::problems( $again );
		$s->note( sprintf( '%s provision run again changes nothing: pages %d -> %d, endpoints kept %d, made %d, missing %s, taxonomies %s, drafts %s',
			$good ? 'ok  ' : 'FAIL', $before_pages, $after_pages, count( $again['endpoints']['kept'] ?? array() ), count( $again['endpoints']['made'] ?? array() ),
			implode( ',', $again['endpoints']['missing'] ?? array( 'error' ) ), wp_json_encode( $again['taxonomies'] ?? null ), implode( ',', $again['pages']['drafts'] ?? array() ) ) );
		if ( ! $good ) {
			$ok = false;
		}

		// A missing Arabic record is made again, in the English record's group, with the same slug. Without --publish it
		// is a draft, so no copied English text goes live; --publish then publishes it, and it is served.
		$offers_en = (int) ( $again['pages']['en']['offers'] ?? 0 );
		$offers_ar = (int) ( $again['pages']['ar']['offers'] ?? 0 );
		$new_ar    = 0;
		$as_draft  = false;
		$published = false;
		$served    = 0;
		if ( $offers_en > 0 && $offers_ar > 0 ) {
			wp_delete_post( $offers_ar, true );
			$made     = \MizzeySite\I18n\LanguageSetup::provision( false );
			$new_ar   = (int) ( $made['pages']['ar']['offers'] ?? 0 );
			$trid     = $sitepress->get_element_trid( $offers_en, 'post_page' );
			$group    = $trid ? $sitepress->get_element_translations( $trid, 'post_page' ) : array();
			$as_draft = $new_ar > 0 && $new_ar !== $offers_ar && $new_ar !== $offers_en && isset( $group['ar'] ) && (int) $group['ar']->element_id === $new_ar
				&& 'offers' === get_post_field( 'post_name', $new_ar ) && 'draft' === get_post_status( $new_ar );
			\MizzeySite\I18n\LanguageSetup::provision( true );
			$published = 'publish' === get_post_status( $new_ar );
			$served    = t22_get( (string) apply_filters( 'wpml_permalink', get_permalink( $offers_en ), 'ar' ) )['status'];
		}
		$good = $as_draft && $published && 200 === $served;
		$s->note( sprintf( '%s provision remakes a missing Arabic record (offers) in its group with its slug, as a draft without --publish (%s), published by --publish (%s); /ar/ answers %d',
			$good ? 'ok  ' : 'FAIL', $as_draft ? 'draft' : 'NOT a draft', $published ? 'published' : 'NOT published', $served ) );
		if ( ! $good ) {
			$ok = false;
		}

		// --publish never changes a status staff set: a private English page and a pending Arabic one stay so.
		$kept_status = false;
		if ( $offers_en > 0 && $new_ar > 0 ) {
			wp_update_post( array( 'ID' => $offers_en, 'post_status' => 'private' ) );
			wp_update_post( array( 'ID' => $new_ar, 'post_status' => 'pending' ) );
			\MizzeySite\I18n\LanguageSetup::provision( true );
			$kept_status = 'private' === get_post_status( $offers_en ) && 'pending' === get_post_status( $new_ar );
			wp_update_post( array( 'ID' => $offers_en, 'post_status' => 'publish' ) );
			wp_update_post( array( 'ID' => $new_ar, 'post_status' => 'publish' ) );
		}
		$s->note( sprintf( '%s provision --publish leaves a private and a pending page as staff set them', $kept_status ? 'ok  ' : 'FAIL' ) );
		if ( ! $kept_status ) {
			$ok = false;
		}

		// A slug held only by the Arabic record (the English one renamed) still resolves to the English record through
		// its group: the Arabic page is never taken for English, and nothing new is made.
		global $wpdb;
		$about_en  = (int) ( $again['pages']['en']['about'] ?? 0 );
		$about_ar  = (int) ( $again['pages']['ar']['about'] ?? 0 );
		$languages_kept = false;
		$renamed_report = array();
		if ( $about_en > 0 && $about_ar > 0 ) {
			$wpdb->update( $wpdb->posts, array( 'post_name' => 'about-t22' ), array( 'ID' => $about_en ) );
			clean_post_cache( $about_en );
			$count_before   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page'" );
			$renamed_report = \MizzeySite\I18n\LanguageSetup::provision( false );
			$count_after    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page'" );
			$wpdb->update( $wpdb->posts, array( 'post_name' => 'about' ), array( 'ID' => $about_en ) );
			clean_post_cache( $about_en );
			$languages_kept = $about_en === (int) ( $renamed_report['pages']['en']['about'] ?? 0 ) && $about_ar === (int) ( $renamed_report['pages']['ar']['about'] ?? 0 )
				&& $count_before === $count_after && array() === \MizzeySite\I18n\LanguageSetup::problems( $renamed_report );
		}
		$s->note( sprintf( '%s a slug held only in Arabic resolves to the English record through its group (en %d, ar %d)', $languages_kept ? 'ok  ' : 'FAIL',
			(int) ( $renamed_report['pages']['en']['about'] ?? 0 ), (int) ( $renamed_report['pages']['ar']['about'] ?? 0 ) ) );
		if ( ! $languages_kept ) {
			$ok = false;
		}

		// A nested page with a contracted slug (/company-t22/about/) is another page: with the English About renamed, the
		// lookup must still reach it through the Arabic record's group, never take the nested English page, and make
		// nothing for the decoy.
		$decoy_parent  = 0;
		$decoy         = 0;
		$decoy_ignored = false;
		$decoy_report  = array();
		if ( $about_en > 0 && $about_ar > 0 ) {
			$decoy_parent = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 't22 company', 'post_name' => 'company-t22' ) );
			$decoy        = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 't22 nested about', 'post_name' => 'about', 'post_parent' => $decoy_parent ) );
			foreach ( array( $decoy_parent, $decoy ) as $decoy_id ) {
				if ( $decoy_id > 0 ) {
					$sitepress->set_element_language_details( $decoy_id, 'post_page', null, 'en' );
				}
			}
			$wpdb->update( $wpdb->posts, array( 'post_name' => 'about-t22' ), array( 'ID' => $about_en ) );
			clean_post_cache( $about_en );
			$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page'" );
			$decoy_report = \MizzeySite\I18n\LanguageSetup::provision( false );
			$count_after  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page'" );
			$wpdb->update( $wpdb->posts, array( 'post_name' => 'about' ), array( 'ID' => $about_en ) );
			clean_post_cache( $about_en );
			$decoy_trid    = $decoy > 0 ? $sitepress->get_element_trid( $decoy, 'post_page' ) : 0;
			$decoy_group   = $decoy_trid ? $sitepress->get_element_translations( $decoy_trid, 'post_page' ) : array();
			$decoy_ignored = $decoy > 0 && 'about' === get_post_field( 'post_name', $decoy )
				&& $about_en === (int) ( $decoy_report['pages']['en']['about'] ?? 0 ) && $about_ar === (int) ( $decoy_report['pages']['ar']['about'] ?? 0 )
				&& ! isset( $decoy_group['ar'] ) && $count_before === $count_after;
			if ( isset( $decoy_group['ar'] ) ) {
				wp_delete_post( (int) $decoy_group['ar']->element_id, true );
			}
			wp_delete_post( $decoy, true );
			wp_delete_post( $decoy_parent, true );
		}
		$s->note( sprintf( '%s a nested page with a contracted slug is not taken for the contracted page (en %d, decoy %d)', $decoy_ignored ? 'ok  ' : 'FAIL',
			(int) ( $decoy_report['pages']['en']['about'] ?? 0 ), $decoy ) );
		if ( ! $decoy_ignored ) {
			$ok = false;
		}

		// Incomplete provisioning is reported, so the command exits non-zero: a WooCommerce page not assigned, and a
		// page that could not be made.
		$no_cart = static fn () => 0;
		add_filter( 'pre_option_woocommerce_cart_page_id', $no_cart );
		$cartless = \MizzeySite\I18n\LanguageSetup::provision( false );
		remove_filter( 'pre_option_woocommerce_cart_page_id', $no_cart );
		$broken = $again;
		$broken['pages']['failed'] = array( 'offers' );
		unset( $broken['pages']['en']['offers'], $broken['pages']['ar']['offers'] );
		$cart_problems   = \MizzeySite\I18n\LanguageSetup::problems( $cartless );
		$failed_problems = \MizzeySite\I18n\LanguageSetup::problems( $broken );
		$good = in_array( 'system-page=cart', $cart_problems, true ) && in_array( 'page=offers', $failed_problems, true ) && in_array( 'incomplete-page=offers', $failed_problems, true );
		$s->note( sprintf( '%s provision reports what it could not make (%s | %s)', $good ? 'ok  ' : 'FAIL', implode( ',', $cart_problems ), implode( ',', $failed_problems ) ) );
		if ( ! $good ) {
			$ok = false;
		}

		// The command itself, in this process: it succeeds when everything is in place, and exits non-zero naming the
		// missing cart page when the cart page is not assigned.
		$cli = static fn () => \WP_CLI::runcommand( 'mizzey languages provision', array( 'launch' => false, 'return' => 'all', 'exit_error' => false ) );
		$whole = $cli();
		add_filter( 'pre_option_woocommerce_cart_page_id', $no_cart );
		$cartless_cli = $cli();
		remove_filter( 'pre_option_woocommerce_cart_page_id', $no_cart );
		$good = 0 === (int) $whole->return_code
			&& 0 !== (int) $cartless_cli->return_code && false !== strpos( (string) $cartless_cli->stderr, 'system-page=cart' );
		$s->note( sprintf( '%s wp mizzey languages provision exits %d when complete and %d naming the cart when it is missing (%s)', $good ? 'ok  ' : 'FAIL',
			(int) $whole->return_code, (int) $cartless_cli->return_code, trim( (string) $cartless_cli->stderr ) ) );
		if ( ! $good ) {
			$ok = false;
		}

		// Recorded, not asserted: WooCommerce redirects an empty cart away from checkout, in both languages
		// identically, so a checkout request says nothing about language resolution. Measured under #241.
		return $ok;
	}
);

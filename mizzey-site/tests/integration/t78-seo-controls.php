<?php
/**
 * t78: the search engine controls (MKT-12 to MKT-18, MKT-20). See specs/038-seo-controls.
 *
 * Read back from the pages over the real request, in both languages where the page exists in both.
 * AC-1: title, description and canonical edited on a page and a category reach their pages.
 * AC-2: a permanent redirect created in admin is followed (301), in both languages' paths, with the visitor's query
 *       string; a loop is refused, also one through another redirect.
 * AC-3: a missing page is logged by path, with no visitor address, in the log's own table (no option is rewritten by a
 *       visitor); new paths are taken at most NEW_PER_MINUTE a minute and the log keeps KEEP paths, the least asked
 *       leaving first.
 * AC-4: the sitemap lists the chosen kinds of content and leaves out an item marked "do not index"; robots.txt carries
 *       the extra lines.
 * AC-5: product, organisation and breadcrumb structured data; hreflang for English and Arabic; verification codes.
 * AC-6: a landing page is built from blocks without a developer (MKT-20, P1-L).
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Seo\Redirects;
use MizzeySite\Seo\SearchSettings;
use MizzeySite\Seo\SeoFields;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't78-seo-controls', 'MKT-12 to MKT-18, MKT-20: search engine controls' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag  = 't78' . wp_rand( 1000, 9999 );
		$keep = array();
		foreach ( array( Redirects::OPTION, Redirects::MISSING, SearchSettings::EXCLUDED, SearchSettings::ROBOTS, SearchSettings::GOOGLE, SearchSettings::BING, 'woocommerce_store_address', 'woocommerce_store_city' ) as $option ) {
			$keep[ $option ] = get_option( $option, null );
		}
		$s->on_finish(
			static function () use ( $keep, $tag ): void {
				global $wpdb;
				$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Redirects::table() . ' WHERE path LIKE %s', '%' . $wpdb->esc_like( $tag ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
				foreach ( $keep as $option => $value ) {
					null === $value ? delete_option( $option ) : update_option( $option, $value, false );
				}
				$_POST = array();
				wp_set_current_user( 0 );
			}
		);
		$page = static function ( string $url ): array {
			$r    = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0 ) );
			$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
			return array( $code, $body, is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_header( $r, 'location' ) );
		};
		$admin = (int) wp_insert_user( array( 'user_login' => "{$tag}adm", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}adm@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );

		// ---- AC-1: a page and a category ------------------------------------------------------------------
		$pid = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} About us", 'post_content' => '<!-- wp:paragraph --><p>About the store.</p><!-- /wp:paragraph -->' ) );
		$s->track_post( $pid );
		$_POST = array( SeoFields::NONCE . '_nonce' => wp_create_nonce( SeoFields::NONCE ), 'mizzey_seo' => array( 'title' => "{$tag} page title", 'description' => "{$tag} page description", 'canonical' => 'https://example.com/about-' . $tag, 'robots' => '' ) );
		SeoFields::savePage( $pid );
		$term  = wp_insert_term( "{$tag} Skincare", 'product_cat' );
		$tid   = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
		$s->on_finish( static function () use ( $tid ) { if ( $tid ) { wp_delete_term( $tid, 'product_cat' ); } } );
		$_POST['mizzey_seo'] = array( 'title' => "{$tag} category title", 'description' => "{$tag} category description", 'canonical' => 'https://example.com/cat-' . $tag, 'robots' => '' );
		SeoFields::saveTerm( $tid );
		$_POST = array();
		[ $c, $body ] = $page( get_permalink( $pid ) );
		storefront_refuse_shell( get_permalink( $pid ), $c, $body );
		$check( str_contains( $body, "<title>{$tag} page title" ) && str_contains( $body, "{$tag} page description" ) && str_contains( $body, 'href="https://example.com/about-' . $tag . '"' ), 'AC-1 a page carries its edited title, description and canonical' );
		$product = $s->simple_product( "{$tag} product in category", '10', null );
		wp_set_object_terms( $product->get_id(), array( $tid ), 'product_cat' );
		$term_url = get_term_link( $tid, 'product_cat' );
		[ $c, $body ] = $page( (string) $term_url );
		storefront_refuse_shell( (string) $term_url, $c, $body );
		$check( str_contains( $body, "<title>{$tag} category title" ) && str_contains( $body, "{$tag} category description" ) && str_contains( $body, 'https://example.com/cat-' . $tag ), 'AC-1 a category carries its edited title, description and canonical' );

		// The Shop page is served as the product archive: its own fields still reach it.
		$shop      = (int) wc_get_page_id( 'shop' );
		$shop_keep = get_post_meta( $shop, ProductFields::SEO_TITLE, true );
		$s->on_finish( static function () use ( $shop, $shop_keep ): void { update_post_meta( $shop, ProductFields::SEO_TITLE, $shop_keep ); } );
		update_post_meta( $shop, ProductFields::SEO_TITLE, "{$tag} shop title" );
		$shop_url = wc_get_page_permalink( 'shop' );
		[ $c, $body ] = $page( $shop_url );
		storefront_refuse_shell( $shop_url, $c, $body );
		$check( $shop > 0 && str_contains( $body, "<title>{$tag} shop title" ), 'AC-1 the Shop page carries its edited title' );

		// ---- AC-2: redirects -----------------------------------------------------------------------------
		// The requests ran in other processes: read the stored lists afresh.
		$afresh = static function (): void {
			wp_cache_delete( Redirects::OPTION, 'options' );
			wp_cache_delete( Redirects::MISSING, 'options' );
		};
		$check( null === Redirects::save( "/{$tag}-old-page/", '/' . get_page_uri( $pid ) . '/' ) && null !== Redirects::save( '/' . get_page_uri( $pid ), get_permalink( $pid ) ), 'AC-2 a redirect is saved; one from a page to itself is refused' );
		[ $c, , $loc ] = $page( home_url( "/{$tag}-old-page/" ) );
		$check( 301 === $c && str_contains( $loc, get_page_uri( $pid ) ), 'AC-2 the old path answers 301 to the new address (' . $c . ' ' . $loc . ')' );
		$afresh();
		Redirects::save( "/ar/{$tag}-qadim/", home_url( '/ar/' ) );
		[ $c, , $loc ] = $page( home_url( "/ar/{$tag}-qadim/" ) );
		$check( 301 === $c && str_contains( $loc, '/ar/' ), 'AC-2 an Arabic path is redirected too (' . $c . ')' );
		$check( null !== Redirects::save( "/{$tag}-query/", '?utm_source=x' ) && null !== Redirects::save( "/{$tag}-part/", '#top' ), 'AC-2 a target with no address of its own (a bare query or fragment) is refused' );
		// Saved while the admin works in either language, the target is the path given.
		$targets = array();
		foreach ( array( 'en', 'ar' ) as $lang ) {
			do_action( 'wpml_switch_language', $lang );
			Redirects::save( "/ar/{$tag}-old-{$lang}/", "/ar/{$tag}-new/" );
			$targets[] = (string) ( Redirects::all()[ "/ar/{$tag}-old-{$lang}" ]['to'] ?? '' );
		}
		do_action( 'wpml_switch_language', null );
		$check( str_ends_with( $targets[0], "/ar/{$tag}-new/" ) && $targets[0] === $targets[1] && ! str_contains( $targets[1], '/ar/ar/' ), 'AC-2 an Arabic target given as a path keeps its language, whatever the language of the admin' );
		$afresh();
		$used = (int) ( Redirects::all()[ "/{$tag}-old-page" ]['hits'] ?? 0 );
		$check( 1 <= $used, 'AC-2 each redirect counts its use' );
		// E-287-3: the campaign parameters reach the new page.
		[ $c, , $loc ] = $page( home_url( "/{$tag}-old-page/?utm_source=meta&utm_campaign=eid" ) );
		$check( 301 === $c && str_contains( $loc, get_page_uri( $pid ) ) && str_contains( $loc, 'utm_source=meta' ) && str_contains( $loc, 'utm_campaign=eid' ), 'AC-2 the query string of the visitor (utm_ parameters) is carried to the target (' . $loc . ')' );
		$check( 'https://example.com/x?a=1' === Redirects::withQuery( 'https://example.com/x?a=1', 'utm_source=meta' ) && 'https://example.com/x?b=2#top' === Redirects::withQuery( 'https://example.com/x#top', 'b=2' ), 'AC-2 a target with its own query keeps it; a fragment stays last' );
		// E-287-1: each use is one row update in the log's table, counted by the request itself.
		$check( Redirects::ready() && $used + 1 === (int) ( Redirects::all()[ "/{$tag}-old-page" ]['hits'] ?? 0 ) && 0 === (int) ( get_option( Redirects::OPTION )[ "/{$tag}-old-page" ]['hits'] ?? 0 ), 'AC-2 each use of a redirect is counted in the log table, not by rewriting the redirects option' );
		// E-287-2: a loop through two entries is refused, as is one to the page itself.
		$check( null === Redirects::save( "/{$tag}-loop-a/", "/{$tag}-loop-b/" ) && null !== Redirects::save( "/{$tag}-loop-b/", "/{$tag}-loop-a/" ) && ! isset( Redirects::all()[ "/{$tag}-loop-b" ] ), 'AC-2 a redirect that would lead back to its own path through another redirect is refused' );
		Redirects::remove( "/{$tag}-loop-a/" );

		// ---- AC-3: missing pages ---------------------------------------------------------------------------
		$r = wp_remote_get( home_url( "/{$tag}-nowhere/" ), array( 'timeout' => 30, 'headers' => array( 'Referer' => 'https://referrer.example/x' ) ) ); // storefront-guard: exempt, a missing page whose 404 status is what is checked.
		$afresh();
		$missing = Redirects::missing();
		$check( 404 === (int) wp_remote_retrieve_response_code( $r ) && isset( $missing[ "/{$tag}-nowhere" ] ) && 'https://referrer.example/x' === $missing[ "/{$tag}-nowhere" ]['referrer'] && array( 'hits', 'last', 'referrer' ) === array_keys( $missing[ "/{$tag}-nowhere" ] ), 'AC-3 a missing page is logged by path with where it came from, and no visitor address' );
		wp_cache_delete( Redirects::MISSING, 'options' );
		$check( false === get_option( Redirects::MISSING, false ), 'AC-3 the missing page is in the log table: no visitor request writes the old option' );
		wp_remote_get( home_url( "/{$tag}-nowhere/" ), array( 'timeout' => 30 ) ); // storefront-guard: exempt, a missing page counted a second time.
		$check( 2 === (int) ( Redirects::missing()[ "/{$tag}-nowhere" ]['hits'] ?? 0 ) && 'https://referrer.example/x' === ( Redirects::missing()[ "/{$tag}-nowhere" ]['referrer'] ?? '' ), 'AC-3 a second visit adds one to the count and keeps the last known referrer' );
		Redirects::save( "/{$tag}-nowhere/", home_url( '/' ) );
		$check( ! isset( Redirects::missing()[ "/{$tag}-nowhere" ] ), 'AC-3 redirecting a missing path takes it off the list' );

		// The log keeps KEEP paths: filled with old paths asked once, a new one drops the least asked, not one asked often.
		global $wpdb, $wp_query;
		$table = Redirects::table();
		$old   = time() - 2 * HOUR_IN_SECONDS;
		$rows  = array();
		for ( $n = 0; $n < Redirects::KEEP; $n++ ) {
			$path   = "/{$tag}-junk-{$n}";
			$rows[] = $wpdb->prepare( "('m', %s, %s, 1, %d, %d, '')", md5( $path ), $path, $old, $old );
		}
		$often  = "/{$tag}-asked-often";
		$rows[] = $wpdb->prepare( "('m', %s, %s, 9, %d, %d, '')", md5( $often ), $often, $old, $old );
		$wpdb->query( "INSERT INTO {$table} (kind, path_hash, path, hits, first_seen, last_seen, referrer) VALUES " . implode( ',', $rows ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$uri = $_SERVER['REQUEST_URI'] ?? null;
		$wp_query->set_404();
		$_SERVER['REQUEST_URI'] = "/{$tag}-fresh/";
		Redirects::logMissing();
		$kept = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE kind = 'm'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$log  = Redirects::missing();
		$check( Redirects::KEEP === $kept && isset( $log[ $often ] ) && isset( $log[ "/{$tag}-fresh" ] ), 'AC-3 the log stays at ' . Redirects::KEEP . ' paths; a new path drops one asked once, never one asked often (' . $kept . ')' );
		// The log full of paths asked twice: a new path is trimmed again at once, so only counting admissions limits it.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET hits = 2 WHERE kind = 'm' AND path LIKE %s", '/' . $wpdb->esc_like( "{$tag}-junk-" ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$inserts       = 0;
		$count_inserts = static function ( $sql ) use ( &$inserts, $table ) {
			if ( str_starts_with( ltrim( (string) $sql ), "INSERT INTO {$table}" ) && str_contains( (string) $sql, "VALUES ('m', " ) ) {
				++$inserts;
			}
			return $sql;
		};
		add_filter( 'query', $count_inserts );
		// New paths are admitted one request at a time: while another connection holds the admission lock, none is.
		$other      = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other_held = 1 === (int) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 5)', Redirects::admitLock() ) );
		$_SERVER['REQUEST_URI'] = "/{$tag}-while-locked/";
		Redirects::logMissing();
		$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', Redirects::admitLock() ) );
		$other->close();
		$check( $other_held && 0 === $inserts, 'AC-3 a new missing path is admitted (counted and trimmed) by one request at a time: none while another holds the admission lock' );
		// New paths are admitted at most NEW_PER_MINUTE a minute, within one minute; a path already logged is still counted.
		if ( time() % MINUTE_IN_SECONDS > 45 ) {
			sleep( MINUTE_IN_SECONDS - time() % MINUTE_IN_SECONDS + 1 );
		}
		$inserts = 0;
		for ( $n = 0; $n < Redirects::NEW_PER_MINUTE + 5; $n++ ) {
			$_SERVER['REQUEST_URI'] = "/{$tag}-flood-{$n}/";
			Redirects::logMissing();
		}
		remove_filter( 'query', $count_inserts );
		$_SERVER['REQUEST_URI'] = $often . '/';
		Redirects::logMissing();
		$kept_now = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE kind = 'm'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$check( $inserts <= Redirects::NEW_PER_MINUTE && Redirects::KEEP === $kept_now && 10 === (int) ( Redirects::missing()[ $often ]['hits'] ?? 0 ), 'AC-3 a flood of new paths is admitted at most ' . Redirects::NEW_PER_MINUTE . ' a minute, also when each is trimmed again at once (' . $inserts . ' of ' . ( Redirects::NEW_PER_MINUTE + 5 ) . '); the log stays at ' . Redirects::KEEP . '; a known path is still counted' );
		if ( null === $uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $uri;
		}
		$wp_query->init();
		Redirects::clearMissing();
		$check( array() === Redirects::missing(), 'AC-3 "Clear the list" empties the log' );

		// ---- AC-4: sitemap and robots ----------------------------------------------------------------------
		$hidden = $s->simple_product( "{$tag} hidden product", '10', null );
		$hidden->update_meta_data( ProductFields::ROBOTS, 'noindex' );
		$hidden->save();
		$listed = $s->simple_product( "{$tag} listed product", '10', null );
		$all_products = '';
		for ( $n = 1; $n <= 5; $n++ ) {
			[ $c, $xml ] = $page( home_url( "/wp-sitemap-posts-product-{$n}.xml" ) );
			if ( 200 !== $c ) {
				break;
			}
			$all_products .= $xml;
		}
		$check( str_contains( $all_products, (string) get_permalink( $listed->get_id() ) ) && ! str_contains( $all_products, (string) get_permalink( $hidden->get_id() ) ), 'AC-4 the sitemap lists a product and leaves out one marked "do not index"' );
		update_option( SearchSettings::EXCLUDED, array(), false );
		[ , $index_before ] = $page( home_url( '/wp-sitemap.xml' ) );
		update_option( SearchSettings::EXCLUDED, array( 'product_cat' ), false );
		update_option( SearchSettings::ROBOTS, SearchSettings::robotsLines( "Disallow: /{$tag}-private/\n<script>bad</script>" ), false );
		[ , $index ] = $page( home_url( '/wp-sitemap.xml' ) );
		[ , $robots ] = $page( home_url( '/robots.txt' ) );
		$check( str_contains( $index_before, 'taxonomies-product_cat' ) && ! str_contains( $index, 'taxonomies-product_cat' ) && str_contains( $index, 'posts-product' ), 'AC-4 a kind of content switched off is not in the sitemap index' );
		$check( str_contains( $robots, "Disallow: /{$tag}-private/" ) && ! str_contains( $robots, 'script' ) && str_contains( $robots, 'Sitemap:' ), 'AC-4 robots.txt carries the extra line, refuses what is not a directive, and keeps the sitemap line' );

		// ---- AC-5: structured data, hreflang, verification ----------------------------------------------------
		update_option( SearchSettings::GOOGLE, SearchSettings::code( "g-{$tag}\"><x" ), false );
		update_option( SearchSettings::BING, SearchSettings::code( "B{$tag}" ), false );
		update_option( 'woocommerce_store_address', "{$tag} 9 Nile Street" );
		update_option( 'woocommerce_store_city', 'Cairo' );
		[ $c, $home ] = $page( home_url( '/' ) );
		storefront_refuse_shell( home_url( '/' ), $c, $home );
		$check( str_contains( $home, 'name="google-site-verification" content="g-' . $tag . 'x"' ) && str_contains( $home, 'name="msvalidate.01" content="B' . $tag . '"' ), 'AC-5 the Google and Bing verification codes are on the home page, cleaned' );
		$check( 1 === preg_match( '/hreflang="ar"/', $home ) && 1 === preg_match( '/hreflang="en"/', $home ), 'AC-5 hreflang names English and Arabic' );
		[ $c, $pdp ] = $page( (string) get_permalink( $listed->get_id() ) );
		storefront_refuse_shell( (string) get_permalink( $listed->get_id() ), $c, $pdp );
		$check( str_contains( $pdp, '"@type":"Product"' ) && str_contains( $pdp, '"@type":"BreadcrumbList"' ), 'AC-5 a product page carries product and breadcrumb structured data' );
		$check( str_contains( $home, '"@type":"Organization"' ) && str_contains( $home, '"url":"' . home_url( '/' ) . '"' ) && str_contains( $home, '"streetAddress":"' . $tag . ' 9 Nile Street"' ) && str_contains( $home, '"addressLocality":"Cairo"' ), 'AC-5 the home page carries the organisation\'s structured data' );

		// ---- AC-6: a landing page from blocks ------------------------------------------------------------------
		$landing = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} Ramadan offers", 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Ramadan offers</h2><!-- /wp:heading --><!-- wp:woocommerce/product-collection {"queryId":1} /-->' ) );
		$s->track_post( $landing );
		[ $c, $lbody ] = $page( (string) get_permalink( $landing ) );
		storefront_refuse_shell( (string) get_permalink( $landing ), $c, $lbody );
		$check( 200 === $c && storefront_shows( $lbody, 'Ramadan offers' ), 'AC-6 a campaign landing page made of blocks is published without a developer' );

		return $ok;
	}
);

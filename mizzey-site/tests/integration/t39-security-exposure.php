<?php
/**
 * t39, NFR-05 (admin protection): what a visitor can learn about the software and the accounts.
 *
 * AC-1: no generator mark in a page, the head or a feed. AC-2: every XML-RPC entry, `?rsd` included, is refused with
 * 403, and no page carries a pingback header or a discovery link. AC-3: a visitor cannot list or discover accounts through the users endpoint, a numeric author
 * query or the users sitemap, while an editor keeps the users endpoint the block editor needs. The audit of 2 November
 * added the bypasses: a route in another letter case, an author number padded with a space or a plus sign, the oEmbed
 * answer and the feeds' author name.
 *
 * Every visitor claim is read over HTTP, as a visitor with no cookies, because a hook that only works in process
 * proves nothing about the page a scanner receives. The editor claim is in process: it is about authorisation, not
 * about the page.
 *
 * Fixtures: one published post by a new author, one published product. Both are removed with the author.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/**
 * Fetch as a visitor, never following a redirect. A shell standing in for a page fails the scenario.
 *
 * @return array{status:int,body:string,headers:array<string,string>}
 */
function t39_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'cookies' => array() ) );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'body' => '', 'headers' => array() );
	}
	$status = (int) wp_remote_retrieve_response_code( $r );
	$body   = (string) wp_remote_retrieve_body( $r );
	storefront_refuse_shell( $url, $status, $body );
	$headers = array();
	foreach ( wp_remote_retrieve_headers( $r ) as $name => $value ) {
		$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}
	return array( 'status' => $status, 'body' => $body, 'headers' => $headers );
}

/** Post an XML-RPC call as a visitor and return the raw answer. */
function t39_xmlrpc( string $method, string $params = '' ): array {
	$xml = '<?xml version="1.0"?><methodCall><methodName>' . $method . '</methodName><params>' . $params . '</params></methodCall>';
	$r   = wp_remote_post( site_url( '/xmlrpc.php' ), array( 'timeout' => 30, 'body' => $xml, 'headers' => array( 'Content-Type' => 'text/xml' ) ) );
	return array(
		'status' => is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ),
		'body'   => is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r ),
	);
}

run(
	new Scenario( 't39-security-exposure', 'NFR-05: no generator mark, no XML-RPC, no account discovery for visitors' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		// ---- Fixtures ----------------------------------------------------------------------------------------
		$author_login = 't39_author_' . wp_rand();
		$author_name  = 'T39 Staff Name ' . wp_rand();
		$author_id    = wp_insert_user( array( 'user_login' => $author_login, 'user_pass' => wp_generate_password( 32 ), 'role' => 'author', 'user_email' => $author_login . '@example.invalid', 'display_name' => $author_name ) );
		$s->track_user( $author_id );
		$author_slug = (string) get_userdata( $author_id )->user_nicename;
		$post_id     = wp_insert_post( array( 'post_title' => 't39 post', 'post_content' => 't39', 'post_status' => 'publish', 'post_author' => $author_id ) );
		$s->track_post( $post_id );
		$product = $s->simple_product( 't39 product', '100', null );
		// A product created by a staff account, as one made in wp-admin would be.
		wp_update_post( array( 'ID' => $product->get_id(), 'post_author' => $author_id ) );

		$pages = array(
			'home (en)'    => home_url( '/' ),
			'home (ar)'    => (string) apply_filters( 'wpml_permalink', home_url( '/' ), 'ar' ),
			'post'         => (string) get_permalink( $post_id ),
			'product'      => (string) get_permalink( $product->get_id() ),
			'shop (ar)'    => (string) apply_filters( 'wpml_permalink', get_permalink( wc_get_page_id( 'shop' ) ), 'ar' ),
		);

		// ---- AC-1 and AC-2: generator marks, pingback header, discovery links ---------------------------------
		foreach ( $pages as $label => $url ) {
			$page = t39_get( $url );
			$check( 200 === $page['status'], "[$label] {$url} answers 200 (got {$page['status']})" );
			$check( 1 !== preg_match( '/<meta[^>]+name=["\']generator["\']/i', $page['body'] ), "[$label] AC-1: no generator meta tag" );
			$check( ! isset( $page['headers']['x-pingback'] ), "[$label] AC-2: no X-Pingback header" );
			$check( 1 !== preg_match( '/<link[^>]+rel=["\'](EditURI|pingback|wlwmanifest)["\']/i', $page['body'] ), "[$label] AC-2: no RSD, pingback or manifest link" );
		}
		foreach ( array( 'feed' => get_feed_link(), 'comments feed' => get_feed_link( 'comments_rss2' ) ) as $label => $url ) {
			$feed = t39_get( $url );
			$check( 200 === $feed['status'], "[$label] {$url} answers 200 (got {$feed['status']})" );
			$check( false === stripos( $feed['body'], '<generator' ) && false === stripos( $feed['body'], 'generator=' ), "[$label] AC-1: no generator element or attribute" );
		}

		// ---- AC-2: XML-RPC is refused before it dispatches anything ----------------------------------------------
		$calls = array(
			'system.listMethods'     => '',
			'system.getCapabilities' => '',
			'system.multicall'       => '<param><value><array><data></data></array></value></param>',
			'pingback.ping'          => '<param><value><string>https://example.invalid/</string></value></param><param><value><string>' . esc_url( get_permalink( $post_id ) ) . '</string></value></param>',
		);
		foreach ( $calls as $method => $params ) {
			$answer = t39_xmlrpc( $method, $params );
			$check( 403 === $answer['status'] && false === strpos( $answer['body'], '<methodResponse>' ), "AC-2: {$method} is refused with 403 and no XML-RPC answer (status {$answer['status']})" );
		}
		$rsd = t39_get( add_query_arg( 'rsd', '', site_url( '/xmlrpc.php' ) ) );
		$check( 403 === $rsd['status'] && false === strpos( $rsd['body'], '<rsd' ), "AC-2: xmlrpc.php?rsd is refused (status {$rsd['status']})" );
		$check( ! pings_open( $post_id ), 'AC-2: pings are closed on a post' );

		// ---- AC-3: the users routes, pretty and as ?rest_route= ----------------------------------------------
		$routes = array(
			'collection'           => rest_url( 'wp/v2/users' ),
			'single user'          => rest_url( 'wp/v2/users/' . $author_id ),
			'collection, query'    => add_query_arg( 'rest_route', '/wp/v2/users', home_url( '/' ) ),
			'single user, query'   => add_query_arg( 'rest_route', '/wp/v2/users/' . $author_id, home_url( '/' ) ),
			// WordPress matches REST routes without regard to case.
			'collection, case'     => rest_url( 'wp/v2/Users' ),
			'single user, case'    => rest_url( 'wp/v2/USERS/' . $author_id ),
			'collection, query, case' => add_query_arg( 'rest_route', '/wp/v2/USERS', home_url( '/' ) ),
		);
		foreach ( $routes as $label => $url ) {
			$answer = t39_get( $url );
			$check( in_array( $answer['status'], array( 401, 403 ), true ) && false === strpos( $answer['body'], $author_slug ) && false === strpos( $answer['body'], $author_name ), "AC-3: a visitor is refused the users {$label} (status {$answer['status']})" );
		}

		// An editor keeps the users routes: in process, because the claim is about authorisation. Native refusals
		// still apply: the edit context stays closed to an editor, who may not list users.
		$editor_id = wp_insert_user( array( 'user_login' => 't39_editor_' . wp_rand(), 'user_pass' => wp_generate_password( 32 ), 'role' => 'editor', 'user_email' => 't39e-' . wp_rand() . '@example.invalid' ) );
		$s->track_user( $editor_id );
		$previous = get_current_user_id();
		wp_set_current_user( $editor_id );
		$as_editor = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/users' ) );
		$edit_ctx  = new \WP_REST_Request( 'GET', '/wp/v2/users' );
		$edit_ctx->set_param( 'context', 'edit' );
		$edit_answer = rest_do_request( $edit_ctx );
		wp_set_current_user( $previous );
		$check( 200 === $as_editor->get_status(), 'AC-3: an editor keeps the users collection the block editor reads (status ' . $as_editor->get_status() . ')' );
		$check( in_array( $edit_answer->get_status(), array( 401, 403 ), true ), 'AC-3: the native refusal of the edit context to an editor still holds (status ' . $edit_answer->get_status() . ')' );

		// ---- AC-3: numeric author queries, in both languages, for an existing and a missing account ------------
		$homes = array( 'en' => home_url( '/' ), 'ar' => (string) apply_filters( 'wpml_permalink', home_url( '/' ), 'ar' ) );
		foreach ( $homes as $lang => $home ) {
			foreach ( array( 'existing' => $author_id, 'missing' => 999999 ) as $which => $number ) {
				$answer   = t39_get( add_query_arg( 'author', $number, $home ) );
				$location = $answer['headers']['location'] ?? '';
				$check( 404 === $answer['status'] && false === strpos( $location, $author_slug ) && false === strpos( $answer['body'], $author_slug ), "AC-3 [$lang] ?author= for a {$which} account answers 404 with no slug (status {$answer['status']}, location '{$location}')" );
			}
			// WP_Query keeps only digits, commas and minus signs, so a padded number still names the account.
			$joiner = false === strpos( $home, '?' ) ? '?' : '&';
			foreach ( array( 'leading space' => '%20' . $author_id, 'trailing space' => $author_id . '%20', 'plus sign' => '+' . $author_id, 'letters between' => substr( (string) $author_id, 0, 1 ) . 'x' . substr( (string) $author_id, 1 ) ) as $which => $value ) {
				$answer   = t39_get( $home . $joiner . 'author=' . $value );
				$location = $answer['headers']['location'] ?? '';
				$check( 404 === $answer['status'] && false === strpos( $location, $author_slug ) && false === strpos( $answer['body'], $author_slug ) && false === strpos( $answer['body'], $author_name ), "AC-3 [$lang] ?author= with a {$which} answers 404 with no name or slug (status {$answer['status']}, location '{$location}')" );
			}
		}

		// ---- AC-3: the oEmbed answer and the feeds name the site, not the author ------------------------------
		foreach ( array( 'post' => (string) get_permalink( $post_id ), 'product' => (string) get_permalink( $product->get_id() ) ) as $label => $permalink ) {
			$answer = t39_get( add_query_arg( 'url', rawurlencode( $permalink ), rest_url( 'oembed/1.0/embed' ) ) );
			$data   = json_decode( $answer['body'], true );
			$check(
				200 === $answer['status'] && is_array( $data ) && ( $data['author_name'] ?? null ) === get_bloginfo( 'name' )
					&& false === strpos( $answer['body'], $author_slug ) && false === strpos( $answer['body'], $author_name ),
				"AC-3 [$label] the oEmbed answer names the site, not the author (status {$answer['status']}, author_name '" . ( is_array( $data ) ? (string) ( $data['author_name'] ?? '' ) : '' ) . "')"
			);
		}
		$feeds = array(
			'feed'         => array( get_feed_link(), 't39 post' ),
			'atom feed'    => array( get_feed_link( 'atom' ), 't39 post' ),
			'product feed' => array( (string) get_post_type_archive_feed_link( 'product' ), 't39 product' ),
		);
		foreach ( $feeds as $label => list( $url, $title ) ) {
			$feed = t39_get( $url );
			// The fixture must be in the feed, or the absence below proves nothing.
			$check( 200 === $feed['status'] && false !== strpos( $feed['body'], $title ), "[$label] {$url} lists the '{$title}' fixture (status {$feed['status']})" );
			$check( false === strpos( $feed['body'], $author_name ) && false === strpos( $feed['body'], $author_slug ), "AC-3 [$label] no item carries the author's name or slug" );
		}

		// ---- AC-3: the users sitemap, with the other sitemaps kept ---------------------------------------------
		$index = t39_get( home_url( '/wp-sitemap.xml' ) );
		$check( 200 === $index['status'] && false === strpos( $index['body'], 'wp-sitemap-users' ), 'AC-3: the sitemap index lists no users sitemap' );
		$check( false !== strpos( $index['body'], 'wp-sitemap-posts-product' ), 'AC-3: the product sitemap is still listed' );
		$users_map = t39_get( home_url( '/wp-sitemap-users-1.xml' ) );
		$check( 404 === $users_map['status'], "AC-3: the users sitemap does not exist (status {$users_map['status']})" );

		return $ok;
	}
);

<?php
/**
 * t121, NFR-02 (caching): the storefront's page cache and the object cache's set-up, through the requests a browser
 * makes (mizzey-site/dropins/README.md).
 *
 * What it needs, besides the runtime: Redis, and a web server for this WordPress that runs the two drop-ins.
 *
 * - Redis: the real one, or on a machine without it the stand-in (`php mizzey-site/tests/integration/_redis_stand_in.php
 *   6390 <password>`). Its address is given to this process and to the web server alike, as the hosting stack gives it
 *   to the PHP container: `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`.
 * - The web server: wp-content/advanced-cache.php and wp-content/object-cache.php copied from mizzey-site/dropins/, and
 *   `WP_CACHE` true. The scenario reads `home_url()` for its address and stops, failing, when the answers carry no
 *   `X-Mizzey-Cache` header.
 *
 * What it proves:
 *
 * 1. The rules, decided before WordPress loads (`PageCacheRequest`): every method, cookie, query string and address
 *    the brief names, in-process.
 * 2. Hit and miss over HTTP, in English and Arabic, on a page, a category and a product: the first answer is a miss,
 *    the second a hit with the same page, and a product page sets no cookie on a guest (the recently viewed list is
 *    kept by the page's script, `POST mizzey/v1/recently-viewed`, which this checks too).
 * 3. What is never answered from the cache or stored: a signed-in visitor, a cart or session, a comment author, a
 *    wishlist; a search, a filter, add to cart, a preview; the cart, the checkout, the account, the REST API, the
 *    administration, the sign-in page, feeds; a 404; a POST. And what does not stop it: Cloudflare Access, consent and
 *    analytics cookies, campaign parameters (served, never stored), the chosen language when it is the page's.
 * 4. Nothing personal in a stored page: the entry in Redis holds no cookie header and none of the cookie values the
 *    visitor sent, the consent banner is printed hidden for the browser to decide, the language script is there, and
 *    the cart shows no items.
 * 5. Purging: a price change and a stock change on the English product drop the English and the Arabic pages at once
 *    and the next answer shows the new price and stock in both; so do a category's description, a page and a setting.
 *    Serving pages purges nothing.
 * 6. Failing safe: Redis refusing connections (in-process: the purge reports it and nothing throws), Redis
 *    unresponsive (`CLIENT PAUSE`: the page is answered by WordPress within the timeout, uncached), and the object
 *    cache's drop-in with Redis unreachable (in its own WP-CLI process: WordPress loads, the cache works for the
 *    request, no fatal error).
 *
 * Everything made here is prefixed "pc-t121-" and removed after; the setting it changes is restored.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\I18n\LanguagePreference;
use MizzeySite\Performance\PageCache;
use MizzeySite\Performance\PageCachePurge;
use MizzeySite\Performance\PageCacheRequest;
use MizzeySite\Performance\RedisLink;
use MizzeySite\Performance\RedisFailure;
use MizzeySite\Performance\RedisSettings;
use MizzeySite\Security\RecoveryLimiter;
use MizzeySite\Signups\SignupService;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/**
 * One request as a browser makes it, with no redirect followed.
 *
 * @param array<string,string> $cookies
 * @return array{status:int,body:string,cache:string,skip:string,cookie:string,location:string,seconds:float,error:string}
 */
function t121_fetch( string $url, array $cookies = array(), string $method = 'GET' ): array {
	$start    = microtime( true );
	$response = wp_remote_request( $url, array( 'method' => $method, 'timeout' => 60, 'redirection' => 0, 'cookies' => $cookies, 'body' => 'POST' === $method ? array( 'pc' => '1' ) : null ) );
	$seconds  = microtime( true ) - $start;
	if ( ! is_wp_error( $response ) ) {
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_body( $response ) );
	}
	if ( is_wp_error( $response ) ) {
		return array( 'status' => 0, 'body' => '', 'cache' => '', 'skip' => '', 'cookie' => '', 'location' => '', 'seconds' => $seconds, 'error' => $response->get_error_message() );
	}
	$cookie = wp_remote_retrieve_header( $response, 'set-cookie' );
	return array(
		'status'   => (int) wp_remote_retrieve_response_code( $response ),
		'body'     => (string) wp_remote_retrieve_body( $response ),
		'cache'    => (string) wp_remote_retrieve_header( $response, 'x-mizzey-cache' ),
		'skip'     => (string) wp_remote_retrieve_header( $response, 'x-mizzey-cache-skip' ),
		'cookie'   => is_array( $cookie ) ? implode( "\n", $cookie ) : (string) $cookie,
		'location' => (string) wp_remote_retrieve_header( $response, 'location' ),
		'seconds'  => $seconds,
		'error'    => '',
	);
}

/** A decision of the request rules for an address, cookies and a method. */
function t121_rule( string $uri, array $cookies = array(), string $method = 'GET', array $server = array() ): PageCacheRequest {
	return PageCacheRequest::from(
		$server + array( 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'QUERY_STRING' => (string) wp_parse_url( $uri, PHP_URL_QUERY ), 'HTTP_HOST' => 'shop.example' ),
		$cookies,
		LanguagePreference::LANGUAGES,
		true
	);
}

/**
 * The stored entry of an address, read straight from Redis: its headers and its page.
 *
 * @return array{headers:list<string>,body:string,generation:string}|null
 */
function t121_entry( RedisLink $redis, RedisSettings $settings, string $url ): ?array {
	$parts  = wp_parse_url( $url );
	$host   = $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	$rule   = PageCacheRequest::from( array( 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $parts['path'] ?? '/', 'HTTP_HOST' => $host ), array(), LanguagePreference::LANGUAGES, 'https' === $parts['scheme'] );
	$stored = $redis->command( 'GET', $settings->prefix . $rule->key() );
	if ( ! is_string( $stored ) || false === strpos( $stored, "\n" ) ) {
		return null;
	}
	$meta = json_decode( substr( $stored, 0, strpos( $stored, "\n" ) ), true );
	$body = substr( $stored, strpos( $stored, "\n" ) + 1 );
	$body = ! empty( $meta['gz'] ) ? (string) gzinflate( $body ) : $body;
	return array( 'headers' => (array) ( $meta['headers'] ?? array() ), 'body' => $body, 'generation' => (string) ( $meta['generation'] ?? '' ) );
}

/**
 * WordPress in its own WP-CLI process, with the given environment, reporting its object cache as JSON: whether the
 * drop-in is in use, whether it reached Redis, and whether a value set in the request is read back. Null when the
 * drop-in is not installed in this runtime's wp-content.
 *
 * @param array<string,string> $env
 * @return array{code:int,out:string,json:array<string,mixed>|null}|null
 */
function t121_object_cache( array $env, string $code = '' ): ?array {
	$dropin = WP_CONTENT_DIR . '/object-cache.php';
	if ( ! is_readable( $dropin ) || ! str_contains( (string) file_get_contents( $dropin ), 'RedisSettings' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return null;
	}
	// Without PhpRedis the drop-in loads only when a client is named; Predis is not installed, so connecting fails,
	// which is the failure this checks.
	$client = wp_tempnam( 't121-client' );
	file_put_contents( $client, "<?php\nif ( ! class_exists( 'Redis' ) && ! defined( 'WP_REDIS_CLIENT' ) ) { define( 'WP_REDIS_CLIENT', 'predis' ); }\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$requires = array();
	foreach ( (array) ( \WP_CLI::get_runner()->config['require'] ?? array() ) as $file ) {
		$requires[] = '--require=' . escapeshellarg( (string) $file );
	}
	if ( array() !== $env ) {
		$requires[] = '--require=' . escapeshellarg( $client );
	}
	$base   = wp_tempnam( 't121-object-cache' );
	$script = $base . '.php';
	file_put_contents( $script, "<?php\nglobal \$wp_object_cache;\n{$code}\n\$c = \$wp_object_cache;\n\$connected = method_exists( \$c, 'redis_status' ) ? \$c->redis_status() : null;\nwp_cache_set( 'pc-t121-probe', 'v', 'pc-t121' );\necho \"\\n\" . wp_json_encode( array( 'ext' => wp_using_ext_object_cache(), 'connected' => \$connected, 'roundtrip' => 'v' === wp_cache_get( 'pc-t121-probe', 'pc-t121' ), 'found' => \$found ?? null, 'home' => home_url() ) );\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$cmd   = 'wp --path=' . escapeshellarg( ABSPATH ) . ' ' . implode( ' ', $requires ) . ' eval-file ' . escapeshellarg( $script );
	$pipes = array();
	$proc  = proc_open( $cmd, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env + getenv() );
	$out   = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	$exit  = proc_close( $proc );
	wp_delete_file( $client );
	wp_delete_file( $script );
	wp_delete_file( $base );
	$json = null;
	foreach ( array_reverse( explode( "\n", trim( (string) $out ) ) ) as $line ) {
		if ( str_starts_with( trim( $line ), '{' ) ) {
			$json = json_decode( trim( $line ), true );
			break;
		}
	}
	return array( 'code' => (int) $exit, 'out' => substr( (string) $out, -400 ), 'json' => is_array( $json ) ? $json : null );
}

run(
	new Scenario( 't121-page-cache', 'NFR-02: the page cache answers guests from Redis, never anyone else, is purged on every change, and fails safe' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 'pc-t121-' . strtolower( wp_generate_password( 5, false, false ) );

		// ---- 1. The rules, before WordPress ------------------------------------------------------------------------
		$rules = array(
			array( t121_rule( '/' ), true, true, 'a guest\'s GET of a page is looked up and stored' ),
			array( t121_rule( '/', array(), 'HEAD' ), true, false, 'a HEAD is answered from the cache, never stored' ),
			array( t121_rule( '/', array(), 'POST' ), false, false, 'a POST is never cached' ),
			array( t121_rule( '/', array( 'wordpress_logged_in_abc' => 'x' ) ), false, false, 'a WordPress sign-in cookie' ),
			array( t121_rule( '/', array( 'wordpress_sec_abc' => 'x' ) ), false, false, 'a WordPress secure sign-in cookie' ),
			array( t121_rule( '/', array( 'woocommerce_items_in_cart' => '1' ) ), false, false, 'woocommerce_items_in_cart' ),
			array( t121_rule( '/', array( 'woocommerce_cart_hash' => 'x' ) ), false, false, 'woocommerce_cart_hash' ),
			array( t121_rule( '/', array( 'wp_woocommerce_session_abc' => 'x' ) ), false, false, 'a WooCommerce session cookie' ),
			array( t121_rule( '/', array( 'comment_author_abc' => 'x' ) ), false, false, 'a comment author cookie' ),
			array( t121_rule( '/', array( 'wp-postpass_abc' => 'x' ) ), false, false, 'a password-protected post\'s cookie' ),
			array( t121_rule( '/', array( 'mizzey_wishlist' => 'x' ) ), false, false, 'a guest wishlist (the header counts it)' ),
			array( t121_rule( '/', array( 'some_unknown_cookie' => 'x' ) ), false, false, 'a cookie the list does not know' ),
			array( t121_rule( '/', array( 'CF_Authorization' => 'x', 'CF_AppSession' => 'y', '__cf_bm' => 'z' ) ), true, true, 'Cloudflare Access and bot cookies do not stop it' ),
			array( t121_rule( '/', array( 'mizzey_consent' => 'granted', '_ga' => 'GA1.1.1', '_ga_ABC' => 'x', '_gcl_au' => 'x', '_fbp' => 'x', 'sbjs_current' => 'x' ) ), true, true, 'consent, analytics and attribution cookies do not stop it' ),
			array( t121_rule( '/', array( 'wp-wpml_current_language' => 'en', 'mizzey_recently_viewed' => 'x' ) ), true, true, 'WPML\'s language cookie and the recently viewed list do not stop it' ),
			array( t121_rule( '/product/some-serum/', array( 'mizzey_recently_viewed' => 'x' ) ), false, false, 'the recently viewed list on a product page, which draws it (slice 10)' ),
			array( t121_rule( '/ar/product/some-serum/', array( 'mizzey_recently_viewed' => 'x' ) ), false, false, 'the recently viewed list on an Arabic product page' ),
			array( t121_rule( '/product/some-serum/' ), true, true, 'a product page without the list (its empty rail is the same for every guest)' ),
			array( t121_rule( '/', array( 'mizzey_language' => 'en' ) ), true, true, 'the chosen language, when it is the page\'s' ),
			array( t121_rule( '/', array( 'mizzey_language' => 'ar' ) ), false, false, 'the chosen language, when it is not the page\'s (WordPress moves the visitor)' ),
			array( t121_rule( '/ar/', array( 'mizzey_language' => 'ar' ) ), true, true, 'Arabic chosen, on an Arabic page' ),
			array( t121_rule( '/?utm_source=x&utm_medium=y&gclid=1&fbclid=2' ), true, false, 'campaign parameters: answered from the cache, never stored' ),
			array( t121_rule( '/?s=serum' ), false, false, 'a search' ),
			array( t121_rule( '/?add-to-cart=12' ), false, false, 'add to cart' ),
			array( t121_rule( '/shop/?filter_brand=x' ), false, false, 'a filter' ),
			array( t121_rule( '/?p=1&preview=true' ), false, false, 'a preview' ),
			array( t121_rule( '/?wc-api=x' ), false, false, 'the WooCommerce API by parameter' ),
			array( t121_rule( '/wc-api/x/' ), false, false, 'the WooCommerce API by address' ),
			array( t121_rule( '/wp-json/wc/store/v1/cart' ), false, false, 'the REST API' ),
			array( t121_rule( '/ar/wp-json/mizzey/v1/header' ), false, false, 'the REST API under the Arabic prefix' ),
			array( t121_rule( '/wp-admin/' ), false, false, 'the administration' ),
			array( t121_rule( '/wp-login.php' ), false, false, 'the sign-in page' ),
			array( t121_rule( '/cart/' ), false, false, 'the cart' ),
			array( t121_rule( '/checkout/' ), false, false, 'the checkout' ),
			array( t121_rule( '/my-account/orders/' ), false, false, 'the account' ),
			array( t121_rule( '/ar/cart/' ), false, false, 'the Arabic cart by its English slug' ),
			array( t121_rule( '/feed/' ), false, false, 'a feed' ),
			array( t121_rule( '/product-category/x/feed/' ), false, false, 'a category feed' ),
			array( t121_rule( '/wp-cron.php' ), false, false, 'cron' ),
			array( t121_rule( '/', array(), 'GET', array( 'HTTP_AUTHORIZATION' => 'Basic x' ) ), false, false, 'a request with credentials' ),
		);
		foreach ( $rules as [ $rule, $lookup, $store, $what ] ) {
			$check( $rule->lookup === $lookup && $rule->store === $store, sprintf( 'rule: %s (lookup %s, store %s; reason %s)', $what, $rule->lookup ? 'yes' : 'no', $rule->store ? 'yes' : 'no', $rule->reason ) );
		}
		$en = t121_rule( '/product/x/' );
		$ar = t121_rule( '/ar/product/x/' );
		$check( 'en' === $en->language && 'ar' === $ar->language && $en->key() !== $ar->key() && t121_rule( '/product/x/?utm_source=y' )->key() === $en->key(), 'the key is the release, scheme, host, path and language: English and Arabic apart, campaign parameters ignored' );

		// ---- The environment this needs ----------------------------------------------------------------------------
		$settings = RedisSettings::fromEnvironment();
		if ( null === $settings ) {
			$check( false, 'Redis is configured for this run (REDIS_HOST, REDIS_PORT, REDIS_PASSWORD), as for the web server' );
			return false;
		}
		try {
			$redis = RedisLink::open( $settings );
		} catch ( RedisFailure $failure ) {
			$check( false, 'Redis answers this process: ' . $failure->getMessage() );
			return false;
		}
		$s->on_finish( static fn () => $redis->close() );
		$fresh = static function (): void {
			PageCachePurge::reset();
			PageCachePurge::replaceGeneration();
		};
		$probe = t121_fetch( home_url( '/?pc-probe=1' ) );
		if ( '' === $probe['cache'] ) {
			$check( false, 'the web server at ' . home_url( '/' ) . ' runs the page cache (WP_CACHE true, the drop-ins in wp-content, the same Redis): ' . ( $probe['error'] ?: 'no X-Mizzey-Cache header, status ' . $probe['status'] ) );
			return false;
		}

		// ---- Fixtures: a category and two products in both languages, a page in both languages ----------------------
		global $sitepress;
		do_action( 'wpml_switch_language', 'en' );
		$term_en = wp_insert_term( "{$tag} Category", 'product_cat', array( 'slug' => $tag, 'description' => "{$tag} first words" ) );
		$term_ar = wp_insert_term( "{$tag} الفئة", 'product_cat', array( 'slug' => "{$tag}-ar" ) );
		$cat_en  = (int) $term_en['term_id'];
		$cat_ar  = (int) $term_ar['term_id'];
		$s->on_finish(
			static function () use ( $cat_en, $cat_ar ): void {
				wp_delete_term( $cat_ar, 'product_cat' );
				wp_delete_term( $cat_en, 'product_cat' );
			}
		);
		$trid = apply_filters( 'wpml_element_trid', null, $cat_en, 'tax_product_cat' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => get_term( $cat_ar )->term_taxonomy_id, 'element_type' => 'tax_product_cat', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$products = array();
		foreach ( array( 'one', 'two' ) as $i => $name ) {
			do_action( 'wpml_switch_language', 'en' );
			$product = new \WC_Product_Simple();
			$product->set_name( "{$tag} Product {$name}" );
			$product->set_slug( "{$tag}-{$name}" );
			$product->set_status( 'publish' );
			$product->set_regular_price( (string) ( 120 + $i ) );
			$product->set_manage_stock( true );
			$product->set_stock_quantity( 7 );
			$product->set_category_ids( array( $cat_en ) );
			$product->save();
			$s->track_post( $product->get_id() );
			$sitepress->make_duplicate( $product->get_id(), 'ar' );
			$ar_id = (int) apply_filters( 'wpml_object_id', $product->get_id(), 'product', false, 'ar' );
			$s->track_post( $ar_id );
			$products[ $name ] = array( 'en' => $product->get_id(), 'ar' => $ar_id );
		}
		do_action( 'wpml_switch_language', 'en' );
		$page_en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} page", 'post_name' => $tag, 'post_content' => "<!-- wp:paragraph --><p>{$tag} words</p><!-- /wp:paragraph -->" ) );
		do_action( 'wpml_switch_language', 'ar' );
		$page_ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} صفحة", 'post_name' => "{$tag}-ar", 'post_content' => "<!-- wp:paragraph --><p>{$tag} كلمات</p><!-- /wp:paragraph -->" ) );
		do_action( 'wpml_switch_language', 'en' );
		// Removed by a finisher, before the product fixtures, so they never outlive the run (they did when left to the
		// shutdown removal with the products).
		$s->on_finish(
			static function () use ( $page_en, $page_ar ): void {
				wp_delete_post( $page_ar, true );
				wp_delete_post( $page_en, true );
			}
		);
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $page_ar, 'element_type' => 'post_page', 'trid' => apply_filters( 'wpml_element_trid', null, $page_en, 'post_page' ), 'language_code' => 'ar', 'source_language_code' => 'en' ) );

		do_action( 'wpml_switch_language', 'ar' );
		$arabic = array(
			'page-ar'     => (string) get_permalink( $page_ar ),
			'category-ar' => (string) get_term_link( $cat_ar ),
			'product-ar'  => (string) get_permalink( $products['one']['ar'] ),
		);
		do_action( 'wpml_switch_language', 'en' );
		$url = array(
			'page-en'     => (string) get_permalink( $page_en ),
			'page-ar'     => $arabic['page-ar'],
			'category-en' => (string) get_term_link( $cat_en ),
			'category-ar' => $arabic['category-ar'],
			'product-en'  => (string) get_permalink( $products['one']['en'] ),
			'product-ar'  => $arabic['product-ar'],
			'home-en'     => home_url( '/' ),
			'home-ar'     => home_url( '/ar/' ),
		);
		// The address a visitor lands on: one redirect (WPML's own canonical address) followed, once.
		foreach ( $url as $name => $address ) {
			$answer = t121_fetch( $address, array( 'mizzey_probe' => '1' ) );
			if ( in_array( $answer['status'], array( 301, 302 ), true ) && '' !== $answer['location'] ) {
				$url[ $name ] = $answer['location'];
			}
		}
		$s->note( 'addresses: ' . wp_json_encode( $url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );

		// ---- 2. Hit and miss, both languages ----------------------------------------------------------------------
		$fresh();
		$pages = array();
		foreach ( array( 'page-en', 'page-ar', 'category-en', 'category-ar', 'product-en', 'product-ar', 'home-en', 'home-ar' ) as $name ) {
			$first  = t121_fetch( $url[ $name ] );
			$second = t121_fetch( $url[ $name ] );
			if ( str_starts_with( $name, 'home-' ) && 200 !== $first['status'] ) {
				$s->note( "NOTE  {$name}: the front page answers {$first['status']} on this shared runtime (page_on_front " . get_option( 'page_on_front' ) . '), so it is not judged here' );
				continue;
			}
			$check( 200 === $first['status'] && 'miss' === $first['cache'] && 'hit' === $second['cache'] && $first['body'] === $second['body'], sprintf( '%s: first a miss, then a hit with the same page (%s %s, then %s; %.2f s then %.3f s)', $name, $first['status'], $first['cache'] . ( $first['skip'] ? ' ' . $first['skip'] : '' ), $second['cache'] . ( $second['skip'] ? ' ' . $second['skip'] : '' ), $first['seconds'], $second['seconds'] ) );
			if ( str_starts_with( $name, 'product-' ) ) {
				$check( '' === $first['cookie'], "{$name}: a guest's product page sets no cookie (" . ( $first['cookie'] ?: 'none' ) . ')' );
			}
			$pages[ $name ] = $first['body'];
		}
		$html_tag = static fn ( string $page ): string => preg_match( '/<html[^>]*>/', $page, $m ) ? $m[0] : '';
		$check( str_contains( $html_tag( $pages['page-ar'] ?? '' ), 'lang="ar"' ) && str_contains( $html_tag( $pages['page-ar'] ?? '' ), 'dir="rtl"' ) && ! str_contains( $html_tag( $pages['page-en'] ?? '' ), 'lang="ar"' ), 'the Arabic page is stored and served in Arabic, right to left, apart from the English one' );

		// The recently viewed list, kept by the product page's script.
		$view = wp_remote_post( rest_url( 'mizzey/v1/recently-viewed' ), // storefront-guard: exempt, a JSON endpoint
			 array( 'timeout' => 30, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'id' => $products['one']['en'] ) ) ) );
		$kept = is_wp_error( $view ) ? '' : implode( ';', (array) wp_remote_retrieve_header( $view, 'set-cookie' ) );
		$check( ! is_wp_error( $view ) && 200 === wp_remote_retrieve_response_code( $view ) && str_contains( $kept, 'mizzey_recently_viewed=' . $products['one']['en'] ), 'WISH-07 kept: the product page\'s script records the view (POST mizzey/v1/recently-viewed), which sets the guest\'s list' );
		$check( 1 === preg_match( '#<script id="mizzey-recently-viewed">.*mizzey/v1/recently-viewed#s', str_replace( '\/', '/', $pages['product-en'] ?? '' ) ), 'WISH-07 kept: a guest\'s product page carries that script' );

		// ---- 2b. Nothing on a stored page is one visitor's ---------------------------------------------------------
		// Two guests' pages, each built by WordPress for them (an unknown cookie sends both past the cache), are the same
		// page the cache stored, apart from two values that are drawn afresh on every build and belong to nobody: the
		// time WPML's language-cookie script stamps, and the id WooCommerce's quantity field draws (`uniqid()`, tying its
		// label to it inside the page). A per-visitor value printed into the page (a single-use token, a session's
		// nonce, a count) would differ here.
		$stamp = static fn ( string $html ): string => (string) preg_replace( array( '/"expires":"[^"]*"/', '/quantity_[0-9a-f]{13}/' ), array( '"expires":""', 'quantity_' ), $html );
		foreach ( array( 'category-en', 'product-ar', 'home-en' ) as $name ) {
			if ( ! isset( $pages[ $name ] ) ) {
				continue;
			}
			$stored = t121_fetch( $url[ $name ] );
			$guest1 = t121_fetch( $url[ $name ], array( 'pc_guest' => 'one' ) );
			$guest2 = t121_fetch( $url[ $name ], array( 'pc_guest' => 'two' ) );
			$check( 'hit' === $stored['cache'] && 'miss' === $guest1['cache'] && $stamp( $guest1['body'] ) === $stamp( $guest2['body'] ) && $stamp( $stored['body'] ) === $stamp( $guest1['body'] ), "{$name}: two guests' pages built for each of them are the same page the cache serves (nothing per visitor in it)" );
		}
		// The nonces a stored page carries are the ones every guest without a session gets (user 0, no session token):
		// WordPress's REST nonce (header, wishlist button) and the Store API's (add to cart). A guest with a cart or a
		// session is never sent a stored page, and the wishlist's session nonce travels only in Store API answers.
		wp_set_current_user( 0 );
		$body = html_entity_decode( implode( "
", $pages ), ENT_QUOTES );
		preg_match_all( '/"(storeNonce|restNonce|nonce)":"([a-f0-9]{10})"/', $body, $found, PREG_SET_ORDER );
		$expected = array( 'storeNonce' => wp_create_nonce( 'wc_store_api' ), 'restNonce' => wp_create_nonce( 'wp_rest' ), 'nonce' => wp_create_nonce( 'wp_rest' ) );
		$wrong    = array_filter( $found, static fn ( $m ) => $expected[ $m[1] ] !== $m[2] );
		$check( array() !== $found && array() === $wrong && ! str_contains( $body, \MizzeySite\Wishlist\WishlistRoutes::NONCE ) && ! str_contains( $body, 'signup_token' ), 'the stored page\'s nonces (' . implode( ', ', array_unique( array_column( $found, 1 ) ) ) . ') are every session-less guest\'s, and it carries no wishlist session nonce and no sign-up token' );
		$check( 1 === preg_match( '#mizzey/v1/header#', str_replace( '\/', '/', $body ) ), 'the cart count and sign-in state come from mizzey/v1/header in the browser (a REST answer, never cached)' );

		// ---- 3. Never from the cache, never stored; and what does not stop it ---------------------------------------
		$target   = $url['category-en'];
		$never    = array(
			'signed in'      => array( $target, array( 'wordpress_logged_in_' . COOKIEHASH => 'x' ) ),
			'cart'           => array( $target, array( 'woocommerce_items_in_cart' => '1', 'woocommerce_cart_hash' => 'x' ) ),
			'session'        => array( $target, array( 'wp_woocommerce_session_' . COOKIEHASH => 'x' ) ),
			'comment author' => array( $target, array( 'comment_author_' . COOKIEHASH => 'x' ) ),
			'wishlist'       => array( $target, array( 'mizzey_wishlist' => 'x' ) ),
			'search'         => array( add_query_arg( 's', $tag, home_url( '/' ) ), array() ),
			'add to cart'    => array( add_query_arg( 'add-to-cart', $products['two']['en'], $target ), array() ),
			'filter'         => array( add_query_arg( 'orderby', 'price', $target ), array() ),
			'preview'        => array( add_query_arg( array( 'p' => $page_en, 'preview' => 'true' ), home_url( '/' ) ), array() ),
			'cart page'      => array( wc_get_cart_url(), array() ),
			'checkout'       => array( wc_get_checkout_url(), array() ),
			'account'        => array( wc_get_page_permalink( 'myaccount' ), array() ),
			'REST'           => array( rest_url( 'mizzey/v1/navigation' ), array() ),
			'feed'           => array( home_url( '/feed/' ), array() ),
			'not found'      => array( home_url( "/{$tag}-nothing-here/" ), array() ),
		);
		foreach ( array( 'cart page' => 'cart', 'checkout' => 'checkout', 'account' => 'myaccount' ) as $name => $page ) {
			do_action( 'wpml_switch_language', 'ar' );
			$never[ "{$name} (Arabic)" ] = array( (string) apply_filters( 'wpml_permalink', (string) wc_get_page_permalink( $page ), 'ar' ), array() );
			do_action( 'wpml_switch_language', 'en' );
		}
		foreach ( $never as $name => [ $address, $cookies ] ) {
			t121_fetch( $address, $cookies );
			$again = t121_fetch( $address, $cookies );
			$check( 'hit' !== $again['cache'], sprintf( 'never cached: %s (%s, %s%s)', $name, $again['status'], $again['cache'] ?: 'no header', $again['skip'] ? ', ' . $again['skip'] : '' ) );
		}
		$post = t121_fetch( $target, array(), 'POST' );
		$check( 'hit' !== $post['cache'], 'never cached: a POST (' . $post['cache'] . ', ' . $post['skip'] . ')' );
		$token = wp_generate_password( 24, false, false );
		$harmless = array( 'CF_Authorization' => "cfauth{$token}", 'CF_AppSession' => "cfapp{$token}", 'mizzey_consent' => 'granted', '_ga' => "GA1.1.{$token}", 'mizzey_language' => 'en' );
		$with     = t121_fetch( $target, $harmless );
		$check( 'hit' === $with['cache'], 'Cloudflare Access, consent and analytics cookies and the page\'s own language still get the stored page (' . $with['cache'] . ' ' . $with['skip'] . ')' );
		$other = t121_fetch( $target, array( 'mizzey_language' => 'ar' ) );
		$check( 'hit' !== $other['cache'] && 302 === $other['status'] && str_contains( $other['location'], '/ar/' ), 'Arabic chosen on an English page: WordPress answers and moves the visitor to Arabic (' . $other['status'] . ' ' . $other['location'] . ')' );
		$campaign = t121_fetch( add_query_arg( array( 'utm_source' => "src{$token}", 'gclid' => "g{$token}" ), $target ) );
		$check( 'hit' === $campaign['cache'] && ! str_contains( $campaign['body'], $token ), 'campaign parameters get the stored page, which never carries them (' . $campaign['cache'] . ')' );
		$fresh();
		t121_fetch( add_query_arg( 'utm_source', "src{$token}", $target ) );
		$after = t121_fetch( $target );
		$check( 'miss' === $after['cache'], 'a request with campaign parameters stores nothing: the next plain request is a miss (' . $after['cache'] . ')' );
		$head = wp_remote_head( $target, array( 'timeout' => 30, 'redirection' => 0 ) ); // storefront-guard: exempt, a HEAD has no body to judge
		$check( ! is_wp_error( $head ) && 'hit' === wp_remote_retrieve_header( $head, 'x-mizzey-cache' ) && '' === wp_remote_retrieve_body( $head ), 'a HEAD is answered from the stored page, with no body' );

		// ---- 4. Nothing personal in a stored page -------------------------------------------------------------------
		$fresh();
		t121_fetch( $target, $harmless );
		$stored = t121_entry( $redis, $settings, $target );
		$admin  = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
		$check( null !== $stored, 'the page a visitor with Access, consent and analytics cookies was sent is stored' );
		if ( null !== $stored ) {
			$headers = strtolower( implode( "\n", $stored['headers'] ) );
			$check( ! str_contains( $headers, 'set-cookie' ) && ! str_contains( $headers . $stored['body'], $token ), 'the stored page holds no cookie header and none of the cookie values the visitor sent (' . count( $stored['headers'] ) . ' headers kept: ' . implode( ' | ', $stored['headers'] ) . ')' );
			$check( ! str_contains( $stored['body'], 'wordpress_logged_in' ) && ! str_contains( $stored['body'], 'id="wpadminbar"' ) && ( null === $admin || ! str_contains( $stored['body'], (string) $admin->user_email ) ), 'the stored page holds no sign-in, no admin bar and no staff email address' );
			$check( 1 === preg_match( '/<div id="mizzey-consent"[^>]*\shidden[\s>]/', $stored['body'] ) && str_contains( $stored['body'], 'mizzey-consent-js' ), 'consent: the stored banner is printed hidden and its script decides in each visitor\'s browser, so a visitor who accepted is not asked again and a new one is' );
			$check( str_contains( $stored['body'], '<script id="mizzey-language">' ), 'language: the stored page carries the script that moves a visitor to their chosen language' );
			$check( 1 === preg_match( '/class="mizzey-count"[^>]*hidden[^>]*>0</', $stored['body'] ), 'cart: the stored header shows an empty cart, the only cart a visitor without a cart cookie has' );
		}

		// ---- 5. Purging, both languages ------------------------------------------------------------------------------
		$store_all    = static function () use ( $url ): void {
			foreach ( array( 'product-en', 'product-ar', 'category-en', 'category-ar', 'page-en', 'page-ar' ) as $name ) {
				t121_fetch( $url[ $name ] );
			}
		};
		$after_change = static function ( array $names ) use ( $url ): array {
			$out = array();
			foreach ( $names as $name ) {
				$out[ $name ] = t121_fetch( $url[ $name ] );
			}
			return $out;
		};
		$fresh();
		$store_all();
		$hits = $after_change( array( 'product-en', 'product-ar', 'category-en', 'category-ar' ) );
		$check( array( 'hit' ) === array_values( array_unique( array_column( $hits, 'cache' ) ) ), 'serving pages purges nothing: the stored pages are hits before any change (' . implode( ', ', array_column( $hits, 'cache' ) ) . ')' );

		PageCachePurge::reset();
		$product = wc_get_product( $products['one']['en'] );
		$product->set_regular_price( '149' );
		$product->save();
		foreach ( $after_change( array( 'product-en', 'product-ar', 'category-en', 'category-ar' ) ) as $name => $answer ) {
			$check( 'miss' === $answer['cache'] && str_contains( $answer['body'], '149' ), "price change on the English product: {$name} is built again and shows the new price ({$answer['cache']})" );
		}

		$store_all();
		PageCachePurge::reset();
		wc_update_product_stock( $products['one']['en'], 0, 'set' );
		foreach ( $after_change( array( 'product-en', 'product-ar' ) ) as $name => $answer ) {
			$check( 'miss' === $answer['cache'] && str_contains( $answer['body'], 'out-of-stock' ), "stock change on the English product: {$name} is built again and shows it out of stock ({$answer['cache']})" );
		}

		$store_all();
		PageCachePurge::reset();
		wp_update_term( $cat_en, 'product_cat', array( 'description' => "{$tag} new words" ) );
		$seen = $after_change( array( 'category-en', 'page-ar' ) );
		$check( 'miss' === $seen['category-en']['cache'] && str_contains( $seen['category-en']['body'], "{$tag} new words" ) && 'miss' === $seen['page-ar']['cache'], 'a category\'s description: its page shows the new words, and the Arabic pages are dropped too' );

		$store_all();
		PageCachePurge::reset();
		wp_update_post( array( 'ID' => $page_ar, 'post_content' => "<!-- wp:paragraph --><p>{$tag} كلمات جديدة</p><!-- /wp:paragraph -->" ) );
		$seen = $after_change( array( 'page-ar', 'product-en' ) );
		$check( 'miss' === $seen['page-ar']['cache'] && str_contains( $seen['page-ar']['body'], "{$tag} كلمات جديدة" ) && 'miss' === $seen['product-en']['cache'], 'a page (the home sections are the home page\'s blocks): its new content is shown, and the other pages are dropped' );

		$store_all();
		PageCachePurge::reset();
		$tagline = get_option( 'blogdescription' );
		$s->on_finish( static fn () => update_option( 'blogdescription', $tagline ) );
		update_option( 'blogdescription', "{$tag} tagline" );
		$seen = $after_change( array( 'page-en' ) );
		$check( 'miss' === $seen['page-en']['cache'], 'a setting: the stored pages are dropped (' . $seen['page-en']['cache'] . ')' );
		update_option( 'blogdescription', $tagline );

		// ---- 6. Failing safe ---------------------------------------------------------------------------------------
		$closed = new RedisSettings( '127.0.0.1', 1, '', 0, $settings->prefix, 1.0 );
		$thrown = null;
		$start  = microtime( true );
		try {
			RedisLink::open( $closed );
		} catch ( \Throwable $e ) {
			$thrown = $e;
		}
		$check( $thrown instanceof RedisFailure && microtime( true ) - $start < 3, sprintf( 'Redis refusing connections: the connection fails as a RedisFailure within the timeout (%.2f s), which the cache and the purge catch', microtime( true ) - $start ) );

		$pause_start = microtime( true );
		$redis->command( 'CLIENT', 'PAUSE', '9000' );
		$paused = t121_fetch( $url['page-en'] );
		$check( 200 === $paused['status'] && str_contains( $paused['body'], '</html>' ) && 'miss' === $paused['cache'] && 'redis' === $paused['skip'] && $paused['seconds'] < 8, sprintf( 'Redis unresponsive for 9 s: the page is answered by WordPress, whole and uncached, without waiting for Redis (%s, %s %s, %.2f s)', $paused['status'], $paused['cache'], $paused['skip'], $paused['seconds'] ) );
		usleep( (int) max( 0, ( 9.3 - ( microtime( true ) - $pause_start ) ) * 1000000 ) );
		$back = array( t121_fetch( $url['page-en'] ), t121_fetch( $url['page-en'] ) );
		$check( 'hit' === $back[1]['cache'], 'Redis back: pages are stored and served again (' . $back[0]['cache'] . ', then ' . $back[1]['cache'] . ')' );

		$object = t121_object_cache( array( 'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1' ) );
		if ( null === $object ) {
			$s->note( 'NOTE  the object cache drop-in is not in ' . WP_CONTENT_DIR . ', so its fail-safe is not judged here' );
		} else {
			$check( 0 === $object['code'] && null !== $object['json'] && true !== ( $object['json']['ext'] ?? null ) && true === ( $object['json']['roundtrip'] ?? null ), 'the object cache with Redis unreachable: the drop-in is not loaded at all (a 50 ms probe), so WordPress keeps its own cache and its transients in the database, no fatal error (' . ( $object['json'] ? wp_json_encode( $object['json'] ) : $object['out'] ) . ')' );
			if ( class_exists( 'Redis' ) ) {
				$key   = $tag . '-persist';
				$write = t121_object_cache( array(), "wp_cache_set('{$key}', 'kept', 'pc-t121');" );
				$read  = t121_object_cache( array(), "\$found = wp_cache_get('{$key}', 'pc-t121') === 'kept'; wp_cache_delete('{$key}', 'pc-t121');" );
				$check( true === ( $write['json']['connected'] ?? null ) && true === ( $read['json']['found'] ?? null ), 'the object cache with Redis: a value set in one request is read in the next' );
			} else {
				$s->note( 'NOTE  PhpRedis is not installed here, so the object cache never loads (WordPress keeps its own request cache); its persistent half is judged where PhpRedis is, on staging' );
			}
		}

		// ---- 7. Security counters across separate requests, whatever the object cache does ------------------------
		// Each WP-CLI process below is one request. The password-recovery limit per account (3) and the sign-up limit
		// per email (3) must refuse the fourth request, both with Redis refusing connections and with Redis reachable
		// but the drop-in falling back to its request-only cache (here: no Redis client, so it cannot connect).
		if ( null !== $object ) {
			$person = (int) wp_insert_user( array( 'user_login' => "{$tag}-recover", 'user_email' => "{$tag}-recover@example.com", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
			$s->track_user( $person );
			$email = "{$tag}-counter@example.com";
			$clear = static function () use ( $person, $email ): void {
				RecoveryLimiter::forget( $person, '' );
				SignupService::forget( '', $email );
			};
			$s->on_finish( $clear );
			$cases   = array(
				'Redis refusing connections'                => array( 'REDIS_HOST' => '127.0.0.1', 'REDIS_PORT' => '1' ),
				'Redis reachable, the drop-in falling back' => array( 'REDIS_HOST' => $settings->host, 'REDIS_PORT' => (string) $settings->port, 'REDIS_PASSWORD' => $settings->password ),
			);
			$recover = '$u = get_user_by( \'id\', ' . $person . ' ); $e = new WP_Error(); \MizzeySite\Security\RecoveryLimiter::limit( $e, $u ); $found = ! $e->has_errors();';
			$signup  = '$m = new ReflectionMethod( \MizzeySite\Signups\SignupService::class, \'count\' ); $found = \'\' === $m->invoke( null, \'' . $email . '\', \'\' );';
			$log     = WP_CONTENT_DIR . '/debug.log';
			$before  = is_readable( $log ) ? count( (array) file( $log ) ) : 0;
			foreach ( $cases as $case => $env ) {
				foreach ( array( 'password recovery per account' => $recover, 'sign-up per email' => $signup ) as $what => $code ) {
					$clear();
					$answers = array();
					for ( $i = 0; $i < 4; $i++ ) {
						$run       = t121_object_cache( $env, $code );
						$answers[] = $run['json']['found'] ?? $run['out'];
					}
					$check( array( true, true, true, false ) === $answers, "{$case}: the {$what} limit counts across separate requests and refuses the fourth (" . wp_json_encode( $answers ) . ')' );
				}
			}
			$clear();
			$lines = is_readable( $log ) ? array_slice( (array) file( $log ), $before ) : array();
			$down  = array_values( array_filter( $lines, static fn ( $l ) => str_contains( (string) $l, 'Mizzey object cache:' ) ) );
			$named = array_filter( $down, static fn ( $l ) => 1 === preg_match( '/\d{1,3}(\.\d{1,3}){3}|:\d{2,5}\b/', substr( (string) $l, 26 ) ) );
			$check( count( $down ) <= 1 && array() === $named, 'Redis unreachable is logged at most once a minute (' . count( $down ) . ' line in 8 refused requests), with no address or port in it' );
		}

		return $ok;
	}
);

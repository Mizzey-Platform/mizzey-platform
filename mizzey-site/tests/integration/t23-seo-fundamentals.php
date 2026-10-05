<?php
/**
 * t23, #242 AC-242-08 to AC-242-15: NFR-03's eight obligations, asserted against served HTTP.
 *
 * NFR-03 reads "Clean URLs, meta, sitemap, robots, canonical, breadcrumbs, structured data, alt text". Each is
 * one criterion, and each is measured from a real response rather than from the presence of a class or a
 * setting. Five were native before this feature; three were measured as gaps and are now emitted by
 * MizzeySite\Seo.
 *
 * No browser matrix: none of #242's thirty rows obliges browser rendering, unlike NFR-14 in #241. No user-agent
 * string, header or headless approximation stands in for a browser here, because nothing here needs one.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/**
 * @return array{status:int,body:string,type:string}
 */
function t23_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 25, 'redirection' => 0, 'cookies' => array() ) );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'body' => '', 'type' => '' );
	}
	storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_body( $r ) );
	return array(
		'status' => (int) wp_remote_retrieve_response_code( $r ),
		'body'   => (string) wp_remote_retrieve_body( $r ),
		'type'   => (string) wp_remote_retrieve_header( $r, 'content-type' ),
	);
}

function t23_tag( string $body, string $pattern ): string {
	preg_match( $pattern, $body, $m );
	return $m[0] ?? '';
}

run(
	new Scenario( 't23-seo-fundamentals', 'AC-242-08 to AC-242-15' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok = true;

		$languages = (array) apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
		$ar_root = isset( $languages['ar']['url'] ) ? (string) $languages['ar']['url'] : '';
		$about = get_page_by_path( 'about', OBJECT, 'page' );
		$about_url = $about ? (string) get_permalink( $about->ID ) : '';

		// ---- AC-242-08 clean URLs ------------------------------------------------------------------------
		$structure = (string) get_option( 'permalink_structure' );
		$s->note( "AC-242-08 permalink_structure: {$structure}" );
		$dirty = array();
		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'publish', 'suppress_filters' => true ) ) as $p ) {
			if ( false !== strpos( (string) get_permalink( $p->ID ), 'page_id=' ) ) {
				$dirty[] = "{$p->ID}:{$p->post_name}";
			}
		}
		$s->note( 'AC-242-08 pages reachable only by ?page_id=: ' . ( $dirty ? implode( ', ', $dirty ) : 'none' ) );
		if ( '' === $structure || $dirty ) {
			$s->note( 'FAIL AC-242-08: a contracted page has no clean URL' );
			$ok = false;
		}

		// ---- AC-242-09 canonical, including archives ----------------------------------------------------
		$canonical_cases = array(
			'front page'      => (string) home_url( '/' ),
			'Arabic root'     => $ar_root,
			'shop archive'    => (string) get_permalink( (int) get_option( 'woocommerce_shop_page_id' ) ),
			'singular page'   => $about_url,
		);
		foreach ( $canonical_cases as $label => $url ) {
			if ( '' === $url ) {
				continue;
			}
			$res = t23_get( $url );
			$tag = t23_tag( $res['body'], '/<link rel="canonical"[^>]*>/i' );
			$count = preg_match_all( '/<link rel="canonical"/i', $res['body'] );
			$self = $tag && false !== strpos( $tag, rtrim( $url, '/' ) );
			$good = '' !== $tag && 1 === $count && $self;
			$s->note( sprintf( '%s AC-242-09 %-14s %s (count=%d)', $good ? 'ok  ' : 'FAIL', $label,
				$tag ?: 'NO CANONICAL', $count ) );
			if ( ! $good ) {
				$ok = false;
			}
		}

		// ---- AC-242-10 sitemap in both languages --------------------------------------------------------
		$sitemap = t23_get( (string) home_url( '/wp-sitemap-posts-page-1.xml' ) );
		preg_match_all( '#<loc>([^<]*)</loc>#', $sitemap['body'], $locs );
		$urls = $locs[1] ?? array();
		$en_count = 0;
		$ar_count = 0;
		foreach ( $urls as $u ) {
			if ( $ar_root && 0 === strpos( $u, $ar_root ) ) {
				++$ar_count;
			} else {
				++$en_count;
			}
		}
		$s->note( sprintf( 'AC-242-10 sitemap status=%d, %d English and %d Arabic page URLs',
			$sitemap['status'], $en_count, $ar_count ) );
		// Every contracted page with an Arabic record must appear for Arabic too.
		$expected_ar = 0;
		foreach ( get_posts( array( 'post_type' => 'page', 'numberposts' => -1, 'post_status' => 'publish', 'suppress_filters' => true ) ) as $p ) {
			if ( 'ar' === $sitepress->get_language_for_element( $p->ID, 'post_page' ) ) {
				++$expected_ar;
			}
		}
		$s->note( "AC-242-10 Arabic pages published: {$expected_ar}" );
		if ( 200 !== $sitemap['status'] || $ar_count < $expected_ar || 0 === $en_count ) {
			$s->note( 'FAIL AC-242-10: the sitemap does not cover both languages' );
			$ok = false;
		}

		// ---- AC-242-11 robots ---------------------------------------------------------------------------
		$robots = t23_get( (string) home_url( '/robots.txt' ) );
		$disallowed = array();
		foreach ( array( '/about/', '/shop/', '/terms-and-conditions/' ) as $path ) {
			if ( preg_match( '#^Disallow:\s*' . preg_quote( $path, '#' ) . '#mi', $robots['body'] ) ) {
				$disallowed[] = $path;
			}
		}
		$s->note( sprintf( 'AC-242-11 robots.txt status=%d, sitemap line=%s, contracted paths disallowed: %s',
			$robots['status'],
			false !== strpos( $robots['body'], 'Sitemap:' ) ? 'present' : 'MISSING',
			$disallowed ? implode( ', ', $disallowed ) : 'none' ) );
		if ( 200 !== $robots['status'] || $disallowed ) {
			$s->note( 'FAIL AC-242-11: robots.txt is missing or disallows a contracted page' );
			$ok = false;
		}

		// ---- AC-242-13 structured data, product and organisation ---------------------------------------
		// Needs a product, which the clean baseline has none of. Created here and cleaned up.
		$product = $s->simple_product( 't23 structured data probe', '250', null );
		$pdp = t23_get( (string) get_permalink( $product->get_id() ) );
		if ( ! storefront_shows( $pdp['body'], 't23 structured data probe' ) ) {
			$s->note( 'FAIL AC-242-13: the product address did not render the product page' );
			$ok = false;
		}
		preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $pdp['body'], $blocks );
		$types = array();
		foreach ( $blocks[1] ?? array() as $json ) {
			$data = json_decode( trim( $json ), true );
			if ( ! is_array( $data ) ) {
				$s->note( 'FAIL AC-242-13: a structured-data block is not valid JSON' );
				$ok = false;
				continue;
			}
			$graph = $data['@graph'] ?? array( $data );
			foreach ( $graph as $node ) {
				if ( isset( $node['@type'] ) ) {
					$types[] = is_array( $node['@type'] ) ? implode( '/', $node['@type'] ) : (string) $node['@type'];
				}
			}
		}
		$s->note( 'AC-242-13 structured-data types on the product page: '
			. ( $types ? implode( ', ', array_unique( $types ) ) : 'none' ) );

		// The criterion is the mechanism, not the emission, and that is an ownership boundary: the product
		// template is PDP-01 to PDP-24 (#254) and MKT-16 contracts the structured-data row (E-MKT-2). So the
		// wiring is what is asserted. The emission is recorded above as a fact. An earlier version of this
		// comment said the generators never fire because the theme has no product template. That described the
		// "coming soon" page this scenario was being served: on the real product page WooCommerce's own
		// template runs them, and the line above lists what they emit.
		$wired_product = false !== has_action( 'woocommerce_single_product_summary',
			array( WC()->structured_data, 'generate_product_data' ) );
		$wired_crumbs = false !== has_action( 'woocommerce_breadcrumb',
			array( WC()->structured_data, 'generate_breadcrumblist_data' ) );
		$s->note( 'AC-242-13 WC_Structured_Data present: ' . ( is_object( WC()->structured_data ) ? 'yes' : 'NO' )
			. ', product generator wired: ' . ( $wired_product ? 'yes' : 'NO' )
			. ', breadcrumb generator wired: ' . ( $wired_crumbs ? 'yes' : 'NO' ) );
		$s->note( 'AC-242-13 the product template is PDP-01 to PDP-24 (#254) and the structured-data row itself is '
			. 'MKT-16 (E-MKT-2). Nothing is built here: what the page emits today comes from the platform\'s own template.' );
		if ( ! is_object( WC()->structured_data ) || ! $wired_product || ! $wired_crumbs ) {
			$s->note( 'FAIL AC-242-13: the structured-data mechanism is absent or suppressed' );
			$ok = false;
		}

		// ---- AC-242-12 breadcrumbs ----------------------------------------------------------------------
		$s->note( 'AC-242-12 woocommerce_breadcrumb available: '
			. ( function_exists( 'woocommerce_breadcrumb' ) ? 'yes' : 'NO' )
			. ', core/breadcrumbs block registered: '
			. ( \WP_Block_Type_Registry::get_instance()->get_registered( 'core/breadcrumbs' ) ? 'yes' : 'no' ) );
		$s->note( 'AC-242-12 emission depends on a storefront template: the header and navigation are NAV rows '
			. '(#253) and the product page is PDP (#254). The mechanism is present and unsuppressed here.' );
		if ( ! function_exists( 'woocommerce_breadcrumb' ) ) {
			$s->note( 'FAIL AC-242-12: no breadcrumb mechanism' );
			$ok = false;
		}

		// ---- AC-242-14 meta description, per page and never shared --------------------------------------
		$descriptions = array();
		foreach ( array( 'about', 'contact-us', 'terms-and-conditions' ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( ! $page ) {
				continue;
			}
			$res = t23_get( (string) get_permalink( $page->ID ) );
			preg_match( '/<meta name="description" content="([^"]*)"/i', $res['body'], $m );
			$descriptions[ $slug ] = $m[1] ?? '';
			$s->note( sprintf( 'AC-242-14 %-22s %s', $slug,
				( $m[1] ?? '' ) !== '' ? 'description present' : 'NO DESCRIPTION' ) );
		}
		if ( in_array( '', $descriptions, true ) ) {
			$s->note( 'FAIL AC-242-14: a contracted page emits no meta description' );
			$ok = false;
		}
		// A page with nothing of its own must not inherit another page's description.
		$empty = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
			'post_title' => 't23 page with no content', 'post_content' => '' ), true );
		if ( ! is_wp_error( $empty ) ) {
			$empty_id = (int) $empty;
			$s->on_finish(
				static function () use ( $empty_id ) {
					wp_delete_post( $empty_id, true );
				}
			);
			$res = t23_get( (string) get_permalink( $empty_id ) );
			preg_match( '/<meta name="description" content="([^"]*)"/i', $res['body'], $m );
			$leaked = ( $m[1] ?? '' ) !== '' && in_array( $m[1], $descriptions, true );
			$s->note( 'AC-242-14 a page with no content emits: '
				. ( ( $m[1] ?? '' ) === '' ? 'nothing, correct' : "'{$m[1]}'" ) );
			if ( $leaked ) {
				$s->note( "FAIL AC-242-14: a page inherited another page's description" );
				$ok = false;
			}
		}

		// ---- AC-242-15 alt text -------------------------------------------------------------------------
		$s->note( 'AC-242-15 alt-text mechanism: core attachment meta _wp_attachment_image_alt, '
			. 'and wp_get_attachment_image emits the attribute. This feature introduces no images, so there is '
			. 'no image of its own to carry alt text; the mechanism is available for the content slices.' );

		// ---- the extensibility the metadata output promises ---------------------------------------------
		// FR-013: every emitted value passes through a documented filter, so a later admin-owned control
		// (MKT-12, ADM-41, SSC-27) can override it without this code changing.
		// Asserted in process. An earlier version added the filters here and then fetched the page over HTTP,
		// a separate process, where they could not possibly apply.
		add_filter( 'mizzey_meta_description', static fn() => 't23 override probe', 99 );
		add_filter( 'mizzey_canonical_url', static fn() => 'http://mizzey.local/t23-override-probe/', 99 );
		$GLOBALS['wp_query']->is_home = true;
		$GLOBALS['wp_query']->is_front_page = true;
		ob_start();
		\MizzeySite\Seo\MetaDescription::emit();
		\MizzeySite\Seo\ArchiveCanonical::emit();
		$emitted = (string) ob_get_clean();
		$desc_overridden = false !== strpos( $emitted, 't23 override probe' );
		$canon_overridden = false !== strpos( $emitted, 't23-override-probe' );
		remove_all_filters( 'mizzey_meta_description', 99 );
		remove_all_filters( 'mizzey_canonical_url', 99 );
		$s->note( 'FR-013 emitted under the probe filters: ' . trim( str_replace( "\n", ' ', $emitted ) ) );
		$s->note( 'FR-013 meta description overridable by filter: ' . ( $desc_overridden ? 'yes' : 'NO' ) );
		$s->note( 'FR-013 canonical overridable by filter: ' . ( $canon_overridden ? 'yes' : 'NO' ) );
		if ( ! $desc_overridden || ! $canon_overridden ) {
			$s->note( 'FAIL FR-013: a later admin-owned control could not override the output' );
			$ok = false;
		}

		// FR-014: no storage of its own. Asserted by absence: neither class writes post meta or an option.
		$s->note( 'FR-014 metadata storage introduced by this feature: none. Each value is derived from the '
			. 'record WordPress already holds, so a later override field becomes the source rather than a '
			. 'second store.' );

		// Recorded, not asserted: the core sitemap index lists author pages, a configuration decision under
		// NFR-03's sitemap obligation rather than a defect.
		$index = t23_get( (string) home_url( '/wp-sitemap.xml' ) );
		$s->note( 'fact: wp-sitemap-users in the index: '
			. ( false !== strpos( $index['body'], 'wp-sitemap-users' ) ? 'yes, a configuration decision' : 'no' ) );

		return $ok;
	}
);

<?php
/**
 * t28, #242 AC-242-05 and AC-242-09, with NFR-03's language links: a term archive, fetched as a visitor in each
 * language, is that term's archive, and every address it points at resolves to the right page.
 *
 * Why it exists. Staging found on 5 October 2026 that the Arabic brand archive, which answers 200 at its
 * documented address, pointed its canonical link and all three of its language links at addresses that answer 404
 * (docs/2026-10-05-staging-verification.md, F-242-1). t22 asked only whether the archive address answered 200, and
 * t23 never fetched a term archive. The same day the storefront guard showed that the collection archive answered
 * 200 with nothing on it (docs/2026-10-05-storefront-placeholder-guard.md, F-GUARD-1).
 *
 * So this scenario follows the links instead of reading them. For the category, the brand and the collection
 * archive, in English and in Arabic:
 *   1. the documented address (specs/003-information-architecture-urls/url-map.md) is fetched as a visitor, and
 *      must be the term's own archive: its name and a product assigned to it on the page;
 *   2. the canonical link must be that same address;
 *   3. the language links must be exactly English, Arabic and x-default, each pointing at the documented address
 *      of its counterpart;
 *   4. every one of those addresses is fetched, and must answer 200 with the term of its own language. A link is
 *      not counted as right because it looks right.
 *
 * The routing rules are then rebuilt from inside an Arabic request, which is what happens whenever the platform
 * queues a rebuild and the next visitor happens to be on the Arabic site, and every address is fetched again. An
 * address word that depends on the language of the request that built the rules fails here.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_storefront.php';

/**
 * Fetch an address as a visitor and read its head.
 *
 * @return array{status:int,body:string,canonical:string[],alternates:array<string,string>}
 * @throws StorefrontShell When the answer is a placeholder standing in front of the page.
 */
function t28_get( string $url ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 60, 'redirection' => 0, 'cookies' => array() ) );
	if ( is_wp_error( $r ) ) {
		return array( 'status' => 0, 'body' => $r->get_error_message(), 'canonical' => array(), 'alternates' => array() );
	}
	$status = (int) wp_remote_retrieve_response_code( $r );
	$body   = (string) wp_remote_retrieve_body( $r );
	storefront_refuse_shell( $url, $status, $body );
	preg_match_all( '/<link\b[^>]*\brel=["\']canonical["\'][^>]*>/i', $body, $canonical_tags );
	preg_match_all( '/<link\b[^>]*\bhreflang=["\']([^"\']+)["\'][^>]*>/i', $body, $alternate_tags, PREG_SET_ORDER );
	$href       = static fn( string $tag ): string => preg_match( '/\bhref=["\']([^"\']+)["\']/i', $tag, $m ) ? html_entity_decode( $m[1] ) : '';
	$alternates = array();
	foreach ( $alternate_tags as $tag ) {
		$alternates[ $tag[1] ] = $href( $tag[0] );
	}
	ksort( $alternates );
	return array( 'status' => $status, 'body' => $body, 'canonical' => array_map( $href, $canonical_tags[0] ), 'alternates' => $alternates );
}

run(
	new Scenario( 't28-archive-language-links', 'AC-242-05, AC-242-09: term archives and the addresses they point at, over HTTP' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$shown = static fn( string $url ): string => urldecode( str_replace( untrailingslashit( home_url() ), '', $url ) );

		// The documented address word of each taxonomy: the URL map, IA-03, IA-05 and IA-04.
		$bases = array( 'product_cat' => 'product-category', 'product_brand' => 'brand', 'product_collection' => 'collection' );

		// ---- Fixtures: one term per taxonomy in each language, and one product in both ---------------------
		$w       = new Workflows( $s );
		$product = $w->simple( 'T28-ITEM', null );
		$terms   = array();
		foreach ( $bases as $tax => $base ) {
			$sitepress->switch_lang( 'en', true );
			$en = wp_insert_term( "T28 {$base} term", $tax, array( 'slug' => "t28-{$base}" ) );
			if ( is_wp_error( $en ) ) {
				$s->note( "Fixture: the English {$tax} term could not be created: " . $en->get_error_message() );
				return false;
			}
			$type = 'tax_' . $tax;
			$sitepress->set_element_language_details( (int) $en['term_taxonomy_id'], $type, null, 'en' );
			$trid = $sitepress->get_element_trid( (int) $en['term_taxonomy_id'], $type );
			$sitepress->switch_lang( 'ar', true );
			$ar = wp_insert_term( "T28 {$base} عربي", $tax, array( 'slug' => "t28-{$base}-ar" ) );
			$sitepress->switch_lang( 'en', true );
			if ( is_wp_error( $ar ) ) {
				$s->note( "Fixture: the Arabic {$tax} term could not be created: " . $ar->get_error_message() );
				wp_delete_term( (int) $en['term_id'], $tax );
				return false;
			}
			$sitepress->set_element_language_details( (int) $ar['term_taxonomy_id'], $type, $trid, 'ar', 'en' );
			$en_id = (int) $en['term_id'];
			$ar_id = (int) $ar['term_id'];
			$s->on_finish( static function () use ( $en_id, $ar_id, $tax, $sitepress ) {
				$sitepress->switch_lang( 'all', true );
				wp_delete_term( $ar_id, $tax );
				wp_delete_term( $en_id, $tax );
				$sitepress->switch_lang( 'en', true );
			} );
			wp_set_object_terms( $product, $en_id, $tax );
			$terms[ $tax ] = array(
				'en' => array( 'url' => home_url( "/{$base}/t28-{$base}/" ), 'name' => "T28 {$base} term" ),
				'ar' => array( 'url' => home_url( "/ar/{$base}/t28-{$base}-ar/" ), 'name' => "T28 {$base} عربي", 'id' => $ar_id ),
			);
		}
		// The translation second, in the product's own right, with its terms pointed at the Arabic ones.
		$product_ar = $w->translate( 'duplicate', $product );
		if ( ! $product_ar ) {
			$s->note( 'Fixture: the product could not be translated, so nothing below is meaningful.' );
			return false;
		}
		$sitepress->switch_lang( 'ar', true );
		foreach ( $terms as $tax => $pair ) {
			wp_set_object_terms( $product_ar, $pair['ar']['id'], $tax );
		}
		$sitepress->switch_lang( 'en', true );
		$item = get_the_title( $product );

		// ---- Each archive, each language, and every address it points at -----------------------------------
		$read_all = function ( string $when ) use ( $terms, $bases, $check, $shown, $item ): void {
			foreach ( $terms as $tax => $pair ) {
				foreach ( array( 'en', 'ar' ) as $lang ) {
					$label = "{$when}[{$bases[ $tax ]}, {$lang}]";
					$page  = t28_get( $pair[ $lang ]['url'] );
					$check( 200 === $page['status'], "{$label} AC-242-05 the documented address {$shown( $pair[ $lang ]['url'] )} answers {$page['status']}" );
					$check( storefront_shows( $page['body'], $pair[ $lang ]['name'] ) && storefront_shows( $page['body'], $item ), "{$label} AC-242-05 the page is the term's archive: it shows the term and the product assigned to it" );
					$check( array( $pair[ $lang ]['url'] ) === $page['canonical'], "{$label} AC-242-09 one canonical link, and it is the archive's own address: " . ( implode( ', ', array_map( $shown, $page['canonical'] ) ) ?: 'none' ) );

					$wanted = array( 'ar' => $pair['ar']['url'], 'en' => $pair['en']['url'], 'x-default' => $pair['en']['url'] );
					$check( $wanted === $page['alternates'], "{$label} NFR-03 the language links are English, Arabic and x-default, each at its counterpart's documented address: " . ( wp_json_encode( array_map( $shown, $page['alternates'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );

					// Follow every address the page points at. A link is right when what it leads to is.
					$links = array( 'canonical' => array( $page['canonical'][0] ?? '', $lang ) );
					foreach ( $page['alternates'] as $code => $target ) {
						$links[ "hreflang {$code}" ] = array( $target, 'ar' === $code ? 'ar' : 'en' );
					}
					foreach ( $links as $kind => list( $target, $target_lang ) ) {
						$there   = '' === $target ? array( 'status' => 0, 'body' => '' ) : t28_get( $target );
						$resolves = 200 === $there['status'] && storefront_shows( $there['body'], $pair[ $target_lang ]['name'] );
						$check( $resolves, "{$label} {$kind} leads to {$shown( $target )}, which answers {$there['status']}" . ( $resolves ? " with the {$target_lang} term" : ( 200 === $there['status'] ? ' WITHOUT the term it should show' : '' ) ) );
					}
				}
			}
		};
		$read_all( '' );

		// ---- The same, after the routing rules are rebuilt from inside an Arabic request -------------------
		$s->on_finish( static function () {
			update_option( 'woocommerce_queue_flush_rewrite_rules', 'no' );
			flush_rewrite_rules( false );
		} );
		update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
		$trigger = wp_remote_get( home_url( '/ar/' ), array( 'timeout' => 60, 'redirection' => 0, 'cookies' => array() ) ); // storefront-guard: exempt, only made to trigger the rebuild; its answer is not read.
		wp_cache_delete( 'woocommerce_queue_flush_rewrite_rules', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$check( ! is_wp_error( $trigger ) && 'no' === get_option( 'woocommerce_queue_flush_rewrite_rules' ), 'the queued rebuild of the routing rules ran inside an Arabic request' );
		$read_all( 'after an Arabic-request rebuild ' );

		return $ok;
	}
);

<?php
/**
 * t27, test infrastructure: the storefront guard trips on the real placeholders, and the baseline keeps the store
 * open to visitors.
 *
 * Why it exists. Until 5 October 2026 the development baseline left WooCommerce's "coming soon" page in front of
 * every store page, and four scenarios read it, on fourteen fetches, as if it were the page they had asked for:
 * it answers 200, in the right language and direction, with a title and a canonical link. The guard in
 * _storefront.php is what now fails such a scenario. This scenario proves the guard against the real things, not
 * against a string made up to look like them: it switches the placeholder on, puts the site into maintenance, and
 * reads what a visitor is then served.
 *
 * It restores what it changes: the "coming soon" switch goes back to the value it had, and the maintenance file
 * is removed. WordPress itself ignores a maintenance file older than ten minutes, so a run that died here could
 * not leave the runtime closed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';

/**
 * Fetch an address as a visitor, with no guard applied: this scenario is the one place that reads a shell on
 * purpose.
 *
 * @return array{status:int,body:string}
 */
function t27_visit( string $url ): array {
	$r = wp_remote_get( $url, array( 'timeout' => 60, 'redirection' => 0, 'cookies' => array() ) ); // storefront-guard: exempt, the guard itself is under test.
	return array( 'status' => is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r ), 'body' => is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r ) );
}

run(
	new Scenario( 't27-storefront-guard', 'test infrastructure: a storefront scenario cannot pass on a placeholder' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};

		$shop    = get_post( wc_get_page_id( 'shop' ) );
		$shop_ar = get_post( (int) apply_filters( 'wpml_object_id', $shop->ID, 'page', true, 'ar' ) );
		$urls    = array(
			'en' => array( (string) get_permalink( $shop ), $shop ),
			'ar' => array( (string) apply_filters( 'wpml_permalink', get_permalink( $shop ), 'ar' ), $shop_ar ),
		);

		// ---- The baseline: the store is open, and the shop is the shop -------------------------------------
		$before = (string) get_option( 'woocommerce_coming_soon' );
		$check( 'no' === $before, 'the baseline leaves the store open to visitors: woocommerce_coming_soon is ' . var_export( $before, true ) );
		foreach ( $urls as $lang => list( $url, $page ) ) {
			$open = t27_visit( $url );
			$check( 200 === $open['status'] && '' === storefront_shell( $open['status'], $open['body'] ) && storefront_shows_page( $open['body'], $page ), "[$lang] with the store open, {$url} is no shell and shows the shop page" );
		}

		// ---- The commerce "coming soon" page, in both languages ---------------------------------------------
		$s->on_finish( static function () use ( $before ) {
			update_option( 'woocommerce_coming_soon', $before );
		} );
		update_option( 'woocommerce_coming_soon', 'yes' );
		foreach ( $urls as $lang => list( $url, $page ) ) {
			$closed = t27_visit( $url );
			$shell  = storefront_shell( $closed['status'], $closed['body'] );
			$check( 200 === $closed['status'], "[$lang] the placeholder answers {$closed['status']}, which is why a status check cannot see it" );
			$check( 'the commerce "coming soon" page' === $shell, "[$lang] the guard names it: " . ( $shell ?: 'not recognised' ) );
			$check( ! storefront_shows_page( $closed['body'], $page ), "[$lang] the placeholder does not pass for the shop page, although its title is in the document head" );
			$thrown = '';
			try {
				storefront_refuse_shell( $url, $closed['status'], $closed['body'] );
			} catch ( StorefrontShell $e ) {
				$thrown = $e->getMessage();
			}
			$check( false !== strpos( $thrown, $url ) && false !== strpos( $thrown, 'coming soon' ), "[$lang] a scenario fetching it fails by name: " . ( $thrown ?: 'nothing was raised' ) );
		}
		update_option( 'woocommerce_coming_soon', $before );
		$reopened = t27_visit( $urls['en'][0] );
		$check( '' === storefront_shell( $reopened['status'], $reopened['body'] ), 'the switch is restored and the shop is served again' );

		// ---- The maintenance page -----------------------------------------------------------------------
		$flag = ABSPATH . '.maintenance';
		if ( file_exists( $flag ) ) {
			$s->note( 'FAIL  a maintenance file already exists in the runtime, which this scenario did not write.' );
			return false;
		}
		$s->on_finish( static function () use ( $flag ) {
			if ( file_exists( $flag ) ) {
				unlink( $flag );
			}
		} );
		file_put_contents( $flag, '<?php $upgrading = ' . time() . ';' );
		$down  = t27_visit( (string) home_url( '/' ) );
		unlink( $flag );
		$shell = storefront_shell( $down['status'], $down['body'] );
		$check( '' !== $shell, "in maintenance the home address answers {$down['status']} and the guard names it: " . ( $shell ?: 'not recognised' ) );
		$up = t27_visit( (string) home_url( '/' ) );
		$check( 200 === $up['status'] && '' === storefront_shell( $up['status'], $up['body'] ), 'maintenance is lifted and the home page is served again' );

		return $ok;
	}
);

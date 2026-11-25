<?php
/**
 * t106, spec 066 slice 1: the section library pattern, with the trust signals section as its proof (G11; SSC-01,
 * HOME-10, HOME-11, SSC-02, SSC-03, SSC-12, SSC-13 through AC-4).
 *
 * In Chromium (mizzey-site/tests/browser/section-library.cjs), signed in as an editor account:
 *
 * - on the page set as the front page, in English and in Arabic, the editor's inserter lists only the library's
 *   sections; on another page it is unchanged;
 * - the trust signals section is added twice and edited in English, added and edited in Arabic, and each language's
 *   storefront shows its own words;
 * - the second instance is moved above the first, previewed (the preview shows the new order while the live page
 *   still shows the old one), then published, and the live page follows;
 * - one instance is hidden with "Show on the site": the storefront no longer shows it, and the editor still holds
 *   its content.
 *
 * Then, on the server: the stored front page holds both instances in the new order, the hidden one with its words
 * and `visible` false, and the Arabic page holds its own words. The front page setting, the pages and the account
 * are restored or removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

/** The storefront's home in a language, as WPML links it. */
function t106_home( string $lang ): string {
	return (string) apply_filters( 'wpml_permalink', home_url( '/' ), $lang );
}

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t106_browser( array $plan ): array {
	$file = wp_tempnam( 't106-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/section-library.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/**
 * The trust signals blocks of a stored page, in order: their titles and their Show on the site setting.
 *
 * @return list<array{titles: list<string>, visible: bool}>
 */
function t106_sections( int $post_id ): array {
	clean_post_cache( $post_id );
	$out = array();
	foreach ( parse_blocks( (string) get_post_field( 'post_content', $post_id, 'raw' ) ) as $block ) {
		if ( 'mizzey/trust-signals' === $block['blockName'] ) {
			$out[] = array(
				'titles'  => array_map( static fn ( $item ) => wp_strip_all_tags( html_entity_decode( (string) ( $item['title'] ?? '' ) ) ), (array) ( $block['attrs']['items'] ?? array() ) ),
				'visible' => false !== ( $block['attrs']['visible'] ?? true ),
			);
		}
	}
	return $out;
}

run(
	new Scenario( 't106-section-library', 'AC-4 (G11): the front page takes library sections only; the trust signals section is added, edited in each language, hidden, reordered, previewed and published by an editor' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't106-' . wp_generate_password( 5, false, false );

		// ---- The editor account, signed in by cookie (the sign-in form is AUTH-15's, tested by t49) ----------------
		$user_id = wp_insert_user(
			array(
				'user_login' => $tag . '-editor',
				'user_email' => $tag . '-editor@example.com',
				'user_pass'  => wp_generate_password( 24 ),
				'role'       => 'editor',
				'locale'     => 'en_US',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( 'The editor account could not be made: ' . $user_id->get_error_message() );
		}
		$s->track_user( (int) $user_id );
		global $wpdb;
		// The editor's own first-run screens off, as a returning editor has them.
		update_user_meta(
			(int) $user_id,
			$wpdb->get_blog_prefix() . 'persisted_preferences',
			array(
				'core/edit-post' => array( 'welcomeGuide' => false, 'fullscreenMode' => false ),
				'core'           => array( 'enableChoosePatternModal' => false, 'editorMode' => 'visual', 'isComplementaryAreaVisible' => true ),
				'_modified'      => gmdate( 'c' ),
			)
		);
		$expire  = time() + 4 * HOUR_IN_SECONDS;
		$cookies = array(
			array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user_id, $expire, 'auth' ) ),
			array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( (int) $user_id, $expire, 'logged_in' ) ),
		);

		// ---- A front page in each language, and an ordinary page ---------------------------------------------------
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		do_action( 'wpml_switch_language', 'en' );
		$en    = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} home", 'post_content' => '' ) );
		$other = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} other", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'ar' );
		$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} الرئيسية", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'en' );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $ar, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$was = array( 'show_on_front' => get_option( 'show_on_front' ), 'page_on_front' => get_option( 'page_on_front' ) );
		$s->on_finish(
			static function () use ( $was, $en, $ar, $other ): void {
				update_option( 'show_on_front', $was['show_on_front'] );
				update_option( 'page_on_front', $was['page_on_front'] );
				foreach ( array( $ar, $en, $other ) as $id ) {
					wp_delete_post( $id, true );
				}
			}
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $en );
		$check( (int) apply_filters( 'wpml_object_id', $en, 'page', false, 'ar' ) === $ar, 'the Arabic page is the front page\'s translation' );

		$plan = array(
			'tag'     => $tag,
			'base'    => home_url( '/' ),
			'cookies' => $cookies,
			'library' => \MizzeySite\Blocks\SectionLibrary::names(),
			'link'    => home_url( '/authenticity/' ),
			'en'      => array( 'edit' => admin_url( "post.php?post={$en}&action=edit&lang=en" ), 'view' => t106_home( 'en' ) ),
			'ar'      => array( 'edit' => admin_url( "post.php?post={$ar}&action=edit&lang=ar" ), 'view' => t106_home( 'ar' ) ),
			'other'   => array( 'edit' => admin_url( "post.php?post={$other}&action=edit&lang=en" ) ),
			'shots'   => get_temp_dir(),
		);
		$result = t106_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 20, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );

		// ---- What is stored ------------------------------------------------------------------------------------
		$stored = t106_sections( $en );
		$s->note( '      stored English sections: ' . wp_json_encode( $stored, JSON_UNESCAPED_UNICODE ) );
		$check(
			2 === count( $stored ) && "{$tag} B1" === ( $stored[0]['titles'][0] ?? '' ) && "{$tag} A1" === ( $stored[1]['titles'][0] ?? '' ),
			'SSC-02, HOME-11: the stored front page holds both sections, the second instance now first'
		);
		$check(
			isset( $stored[1] ) && false === $stored[1]['visible'] && array( "{$tag} A1", "{$tag} A2", "{$tag} A3" ) === $stored[1]['titles'] && true === $stored[0]['visible'],
			'SSC-03: the hidden section is stored with its content and Show on the site off'
		);
		$stored_ar = t106_sections( $ar );
		$s->note( '      stored Arabic sections: ' . wp_json_encode( $stored_ar, JSON_UNESCAPED_UNICODE ) );
		$check( 1 === count( $stored_ar ) && "{$tag} أصلي" === ( $stored_ar[0]['titles'][0] ?? '' ), 'SSC-12: the Arabic front page holds its own section and words' );

		return $ok;
	}
);

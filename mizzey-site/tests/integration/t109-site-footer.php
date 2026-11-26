<?php
/**
 * t109, spec 066 slice 3: the footer, the announcement strip, the WhatsApp link and the consent banner in a real
 * browser (AC-2, AC-14; SSC-08, CMS-10, MKT-10, NFR-11).
 *
 * The runtime is prepared here and the browser part is mizzey-site/tests/browser/site-footer.cjs:
 *
 * - each footer column's navigation menu in each language, made as staff make them in the Site Editor (a long link,
 *   a phone and an email link, and a link that opens the cookie preferences), named one per column and per language
 *   on the footer block, beside a menu of the same kind the footer does not name;
 * - the WhatsApp number and its greeting in each language (WooCommerce > Settings > General > Store contact);
 * - a page in each language holding the two blocks with their settings, as the Site Editor stores them on the blocks
 *   in the template parts: the footer's style, its columns and its legal line; the strip's variant and its messages.
 *   The site's own template parts are never written, so other scenarios on the same database keep their header and
 *   footer; the browser reads the blocks inside the page's content.
 *
 * The browser runs twice. First the White footer and the fixed strip; then, after a menu link is renamed in the admin
 * and the pages are saved again, the Indigo footer and the slider. Each run checks both languages. Everything made
 * here is prefixed "s3-" (the menus are named mizzey-footer-<column>-..., never the header's mizzey-header) and
 * removed, also when the run stops partway; the options it sets are restored.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Storefront\ContactLinks;

require_once __DIR__ . '/_bootstrap.php';

/**
 * Run the browser script on a plan.
 *
 * @return array<string,mixed>
 */
function t109_browser( array $plan ): array {
	$file = wp_tempnam( 't109-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/site-footer.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/**
 * Run the boards' checks (mizzey-site/tests/browser/_board.cjs) on a plan: { part, urls }.
 *
 * @return array<string,mixed>
 */
function t109_board( array $plan ): array {
	$file = wp_tempnam( 't109-board' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out = array();
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/_board.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

/**
 * A navigation menu's markup, as the Site Editor saves it.
 *
 * @param list<array{0:string,1:string,2?:string}> $links Label, address and, optionally, the link's description.
 */
function t109_menu_markup( array $links ): string {
	$out = '';
	foreach ( $links as $link ) {
		$attrs = array( 'label' => $link[0], 'type' => 'custom', 'url' => $link[1], 'kind' => 'custom', 'isTopLevelLink' => true );
		if ( ! empty( $link[2] ) ) {
			$attrs['description'] = $link[2];
		}
		$out .= '<!-- wp:navigation-link ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' /-->';
	}
	return $out;
}

/** A published navigation menu in a language, named mizzey-footer-<column>-<language>-<tag>. */
function t109_menu( string $lang, string $column, string $title, array $links ): int {
	do_action( 'wpml_switch_language', $lang );
	$id = (int) wp_insert_post(
		array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => sanitize_title( "mizzey-footer-{$column}-{$lang}-" . strtok( $title, ' ' ) ),
			'post_content' => t109_menu_markup( $links ),
		)
	);
	do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_wp_navigation', 'trid' => false, 'language_code' => $lang ) );
	do_action( 'wpml_switch_language', 'en' );
	return $id;
}

/**
 * The blocks with their settings, as the Site Editor stores them; the strip followed by the site header, as the
 * theme's header part places them (checked below), so the browser can see the strip above the header.
 */
function t109_blocks( array $footer, array $strip ): string {
	return '<!-- wp:mizzey/announcement-strip ' . serialize_block_attributes( $strip ) . ' /-->'
		. '<!-- wp:mizzey/site-header /-->'
		. '<!-- wp:mizzey/site-footer ' . serialize_block_attributes( $footer ) . ' /-->';
}

run(
	new Scenario( 't109-site-footer', 'AC-2: the footer\'s columns from the admin in each language, the WhatsApp link, the strip\'s variant, the consent banner' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag   = 's3-t109-' . wp_generate_password( 5, false, false );
		$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
		wp_set_current_user( $admin );
		do_action( 'wpml_switch_language', 'en' );

		// ---- The options this run sets, restored afterwards ---------------------------------------------------------
		$options = array( ContactLinks::NUMBER, ContactLinks::MESSAGE . 'en', ContactLinks::MESSAGE . 'ar' );
		$before  = array();
		foreach ( $options as $name ) {
			$before[ $name ] = get_option( $name, null );
		}
		$s->on_finish(
			static function () use ( $before ): void {
				foreach ( $before as $name => $value ) {
					null === $value ? delete_option( $name ) : update_option( $name, $value );
				}
			}
		);
		update_option( ContactLinks::NUMBER, '+20 100 555 0109' );
		update_option( ContactLinks::MESSAGE . 'en', "{$tag} Hello" );
		update_option( ContactLinks::MESSAGE . 'ar', "{$tag} مرحبا" );

		// ---- The menus, one per column and per language, and one the footer does not name ------------------------
		$long  = "{$tag} Shipping policy, delivery times and costs by governorate across Egypt";
		$menus = array(
			'shop-en'  => t109_menu( 'en', 'shop', "{$tag} Shop", array( array( "{$tag} Beauty", home_url( '/s3-beauty/' ) ), array( "{$tag} Tools and Home Improvement", home_url( '/s3-tools/' ) ) ) ),
			'shop-ar'  => t109_menu( 'ar', 'shop', "{$tag} تسوّق", array( array( "{$tag} الجمال", home_url( '/ar/s3-beauty/' ) ), array( "{$tag} الأدوات وتحسين المنزل", home_url( '/ar/s3-tools/' ) ) ) ),
			'help-en'  => t109_menu( 'en', 'help', "{$tag} Help", array( array( "{$tag} Contact us", home_url( '/s3-contact/' ) ), array( $long, home_url( '/s3-shipping/' ) ) ) ),
			'help-ar'  => t109_menu( 'ar', 'help', "{$tag} المساعدة", array( array( "{$tag} تواصل معنا", home_url( '/ar/s3-contact/' ) ) ) ),
			'legal-en' => t109_menu( 'en', 'legal', "{$tag} Legal", array( array( "{$tag} Privacy policy", home_url( '/s3-privacy/' ) ), array( "{$tag} Cookie preferences", '#cookie-preferences' ) ) ),
			'legal-ar' => t109_menu( 'ar', 'legal', "{$tag} قانوني", array( array( "{$tag} سياسة الخصوصية", home_url( '/ar/s3-privacy/' ) ), array( "{$tag} تفضيلات ملفات تعريف الارتباط", '#cookie-preferences' ) ) ),
			// The address carries a description, which the footer shows as a tag under it (C1-Home-Phone, "Sample").
			'care-en'  => t109_menu( 'en', 'care', "{$tag} Customer care", array( array( '+20 100 000 0000', 'tel:+201000000000' ), array( 'hello@example.com', 'mailto:hello@example.com' ), array( '12 Sample Street, Cairo', home_url( '/s3-address/' ), 'Sample' ) ) ),
			'care-ar'  => t109_menu( 'ar', 'care', "{$tag} خدمة العملاء", array( array( '+20 100 000 0000', 'tel:+201000000000' ), array( '12 شارع العيّنة، القاهرة', home_url( '/ar/s3-address/' ), 'عيّنة' ) ) ),
			'unnamed'  => t109_menu( 'en', 'unnamed', "{$tag} Not in the footer", array( array( "{$tag} Should not show", home_url( '/s3-no/' ) ) ) ),
		);
		$s->on_finish(
			static function () use ( $menus ): void {
				foreach ( $menus as $id ) {
					wp_delete_post( $id, true );
				}
			}
		);

		// ---- A page in each language for the blocks; the site's template parts are left alone ------------------
		do_action( 'wpml_switch_language', 'en' );
		$page_en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} footer", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'ar' );
		$page_ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => "{$tag} التذييل", 'post_content' => '' ) );
		do_action( 'wpml_switch_language', 'en' );
		$s->on_finish(
			static function () use ( $page_en, $page_ar ): void {
				wp_delete_post( $page_ar, true );
				wp_delete_post( $page_en, true );
			}
		);
		$trid = (int) apply_filters( 'wpml_element_trid', null, $page_en, 'post_page' );
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $page_ar, 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'ar', 'source_language_code' => 'en' ) );
		$save = static function ( string $content ) use ( $page_en, $page_ar ): void {
			foreach ( array( $page_en, $page_ar ) as $id ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
			}
		};

		$columns = static fn ( array $m ): array => array(
			array( 'menus' => array( 'en' => $m['shop-en'], 'ar' => $m['shop-ar'] ), 'wide' => true ),
			array( 'menus' => array( 'en' => $m['help-en'], 'ar' => $m['help-ar'] ) ),
			array( 'menus' => array( 'en' => $m['legal-en'], 'ar' => $m['legal-ar'] ), 'under' => true ),
		);
		$footer = static fn ( string $style ): array => array(
			'style'   => $style,
			'columns' => $columns( $menus ),
			'care'    => array( 'en' => $menus['care-en'], 'ar' => $menus['care-ar'] ),
			'legal'   => array( 'en' => "© 2026 {$tag} Mizzey.", 'ar' => "© 2026 {$tag} ميزي." ),
		);
		$messages = array(
			'en' => array(
				array( 'text' => "{$tag} Free delivery on orders over EGP 1,000", 'label' => "{$tag} See details", 'url' => home_url( '/s3-delivery/' ) ),
				array( 'text' => "{$tag} Authentic products, guaranteed", 'label' => '', 'url' => '' ),
				array( 'text' => "{$tag} Easy returns within 14 days", 'label' => '', 'url' => '' ),
			),
			'ar' => array(
				array( 'text' => "{$tag} توصيل مجاني للطلبات فوق 1,000 ج.م", 'label' => "{$tag} التفاصيل", 'url' => home_url( '/ar/s3-delivery/' ) ),
				array( 'text' => "{$tag} منتجات أصلية مضمونة", 'label' => '', 'url' => '' ),
			),
		);

		$plan = array(
			'tag'      => $tag,
			'en'       => (string) apply_filters( 'wpml_permalink', (string) get_permalink( $page_en ), 'en' ),
			'ar'       => (string) apply_filters( 'wpml_permalink', (string) get_permalink( $page_ar ), 'ar' ),
			'scope'    => '.wp-block-post-content',
			'whatsapp' => array( 'en' => ContactLinks::whatsappUrl( 'en' ), 'ar' => ContactLinks::whatsappUrl( 'ar' ), 'number' => '201005550109' ),
			'long'     => $long,
			'columns'  => array(
				'en' => array( "{$tag} Shop", "{$tag} Help", "{$tag} Legal", "{$tag} Customer care" ),
				'ar' => array( "{$tag} تسوّق", "{$tag} المساعدة", "{$tag} قانوني", "{$tag} خدمة العملاء" ),
			),
			'links'    => array(
				'en' => array( "{$tag} Beauty", "{$tag} Tools and Home Improvement", "{$tag} Contact us", $long, "{$tag} Privacy policy", "{$tag} Cookie preferences", '+20 100 000 0000', 'hello@example.com' ),
				'ar' => array( "{$tag} الجمال", "{$tag} الأدوات وتحسين المنزل", "{$tag} تواصل معنا", "{$tag} سياسة الخصوصية", "{$tag} تفضيلات ملفات تعريف الارتباط", '+20 100 000 0000' ),
			),
			'unnamed'  => "{$tag} Should not show",
			'legal'    => array( 'en' => "© 2026 {$tag} Mizzey.", 'ar' => "© 2026 {$tag} ميزي." ),
			'messages' => array( 'en' => array_column( $messages['en'], 'text' ), 'ar' => array_column( $messages['ar'], 'text' ) ),
			'shots'    => get_temp_dir(),
		);

		// ---- The theme's header part places the strip first, above the site header ----------------------------
		$header_part = (string) file_get_contents( get_theme_file_path( 'parts/header.html' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$strip_at    = strpos( $header_part, '<!-- wp:mizzey/announcement-strip' );
		$header_at   = strpos( $header_part, '<!-- wp:mizzey/site-header' );
		$check( false !== $strip_at && false !== $header_at && $strip_at < $header_at, 'G11 parts/header.html places the announcement strip before the site header' );

		// ---- Run 1: the White footer and the fixed strip -------------------------------------------------------
		$save( t109_blocks( $footer( 'white' ), array( 'variant' => 'fixed', 'messages' => $messages ) ) );
		$first = t109_browser( $plan + array( 'run' => 1, 'style' => 'white', 'variant' => 'fixed' ) );
		$check( empty( $first['error'] ), 'run 1: the browser ran' . ( empty( $first['error'] ) ? '' : ': ' . substr( (string) $first['error'], 0, 1500 ) ) );
		foreach ( (array) ( $first['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], 'run 1: ' . (string) $row['what'] );
		}
		$check( count( (array) ( $first['checks'] ?? array() ) ) >= 30, 'run 1: the browser made every check (' . count( (array) ( $first['checks'] ?? array() ) ) . ')' );

		// ---- In the admin: a link of the Arabic Help menu renamed, as the Site Editor saves a menu --------------
		$renamed = "{$tag} اتصل بنا الآن";
		wp_update_post( array( 'ID' => $menus['help-ar'], 'post_content' => t109_menu_markup( array( array( $renamed, home_url( '/ar/s3-contact/' ) ) ) ) ) );
		$plan['links']['ar'][2] = $renamed;

		// ---- Run 2: the Indigo footer and the slider ------------------------------------------------------------
		$save( t109_blocks( $footer( 'indigo' ), array( 'variant' => 'slider', 'messages' => $messages ) ) );
		$second = t109_browser( $plan + array( 'run' => 2, 'style' => 'indigo', 'variant' => 'slider' ) );
		$check( empty( $second['error'] ), 'run 2: the browser ran' . ( empty( $second['error'] ) ? '' : ': ' . substr( (string) $second['error'], 0, 1500 ) ) );
		foreach ( (array) ( $second['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], 'run 2: ' . (string) $row['what'] );
		}
		$check( count( (array) ( $second['checks'] ?? array() ) ) >= 30, 'run 2: the browser made every check (' . count( (array) ( $second['checks'] ?? array() ) ) . ')' );

		// ---- The boards' numbers (staging review, 25 November 2026), Chromium and WebKit, 390 and 1440, both languages:
		// the space above the footer, the language link in its own script, a link's tag under it. -------------------
		$board = t109_board( array( 'part' => 'footer', 'urls' => array( 'en' => $plan['en'], 'ar' => $plan['ar'], 'scope' => $plan['scope'], 'consent' => array( 'name' => 'mizzey_consent', 'value' => 'denied' ) ) ) );
		$check( empty( $board['error'] ), 'board: the browser ran' . ( empty( $board['error'] ) ? '' : ': ' . substr( (string) $board['error'], 0, 1500 ) ) );
		foreach ( (array) ( $board['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], 'board: ' . (string) $row['what'] );
		}
		$check( count( (array) ( $board['checks'] ?? array() ) ) >= 40, 'board: the browser made every check (' . count( (array) ( $board['checks'] ?? array() ) ) . ')' );

		return $ok;
	}
);

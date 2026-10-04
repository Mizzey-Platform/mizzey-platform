<?php
/**
 * t21, #241 AC-1 to AC-8: the bilingual platform baseline, asserted against served HTTP requests.
 *
 * The first scenario in this harness to issue real requests, and it has to be. An in-process
 * `wpml_switch_language` to Arabic reports `dir="rtl"` with `lang="en-US"`: the direction follows immediately and
 * the cached language tag does not. That is an artifact of switching mid-request, not a defect, and the only way
 * to tell the difference is to ask the web server. A rendered document attribute cannot be asserted honestly any
 * other way.
 *
 * AC-9, the browser matrix, is deliberately absent. A command-line runtime has no browsers, and nothing here may
 * report it as verified. See specs/002-bilingual-platform-baseline/verification.md.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

/**
 * Fetch a storefront URL as a visitor would, following no redirect, and return status plus the <html> element.
 *
 * @param string $path Path relative to the site root, for example '/ar/'.
 * @return array{status:int,html:string,title:string,body_classes:string,error:string}
 */
function t21_get( string $path ): array {
	$response = wp_remote_get(
		home_url( $path ),
		array(
			'timeout'     => 20,
			'redirection' => 0,
			// A fresh cookie jar every time: AC-1 is about a request carrying no language hint, and a shared
			// session would hide a default that drifts.
			'cookies'     => array(),
			'headers'     => array( 'Accept-Language' => 'en-US,en;q=0.9' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return array(
			'status'       => 0,
			'html'         => '',
			'title'        => '',
			'body_classes' => '',
			'error'        => $response->get_error_message(),
		);
	}
	$body = (string) wp_remote_retrieve_body( $response );
	preg_match( '/<html[^>]*>/i', $body, $html );
	preg_match( '#<title>(.*?)</title>#is', $body, $title );
	preg_match( '/<body[^>]+class="([^"]*)"/i', $body, $classes );
	return array(
		'status'       => (int) wp_remote_retrieve_response_code( $response ),
		'html'         => $html[0] ?? '',
		'title'        => trim( $title[1] ?? '' ),
		'body_classes' => $classes[1] ?? '',
		'error'        => '',
	);
}

/**
 * Build a minimal .mo file in memory, so AC-8 can be asserted through the production loader.
 *
 * Written by hand because the alternative is shipping a translation file containing a test string. The format is
 * the GNU gettext binary catalogue: a little-endian magic number, counts and offsets, then the original and
 * translated strings. Only what a lookup needs is emitted.
 *
 * @param array<string,string> $pairs Original string to translation.
 * @return string The .mo bytes.
 */
function t21_mo( array $pairs ): string {
	$originals    = array_keys( $pairs );
	$translations = array_values( $pairs );
	$count        = count( $pairs );
	$header_size  = 28;
	$table_size   = $count * 8;

	$orig_offset  = $header_size + ( 2 * $table_size );
	$orig_table   = '';
	$orig_data    = '';
	foreach ( $originals as $text ) {
		$orig_table .= pack( 'VV', strlen( $text ), $orig_offset + strlen( $orig_data ) );
		$orig_data  .= $text . "\0";
	}

	$trans_offset = $orig_offset + strlen( $orig_data );
	$trans_table  = '';
	$trans_data   = '';
	foreach ( $translations as $text ) {
		$trans_table .= pack( 'VV', strlen( $text ), $trans_offset + strlen( $trans_data ) );
		$trans_data  .= $text . "\0";
	}

	return pack( 'VVVVVVV', 0x950412de, 0, $count, $header_size, $header_size + $table_size, 0, 0 )
		. $orig_table . $trans_table . $orig_data . $trans_data;
}

/**
 * The language a URL resolves to, read from the rendered document rather than from the process.
 *
 * @param string $element The <html> element as served.
 * @return array{lang:string,rtl:bool}
 */
function t21_document_language( string $element ): array {
	preg_match( '/\blang="([^"]*)"/i', $element, $lang );
	return array(
		'lang' => $lang[1] ?? '',
		'rtl'  => (bool) preg_match( '/\bdir="rtl"/i', $element ),
	);
}

run(
	new Scenario( 't21-bilingual-baseline', 'AC-1 to AC-8' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok = true;

		// ---- the environment this scenario needs ------------------------------------------------------
		$structure = (string) get_option( 'permalink_structure' );
		$htaccess  = file_exists( ABSPATH . '.htaccess' );
		$s->note( "permalink_structure: {$structure}" );
		$s->note( 'htaccess present: ' . ( $htaccess ? 'yes' : 'no' ) );
		if ( '' === $structure || ! $htaccess ) {
			$s->note( 'FAIL: the baseline has no rewrite configuration, so no language prefix can resolve. T-01.' );
			return false;
		}

		$languages = array_keys( $sitepress->get_active_languages() );
		sort( $languages );
		$s->note( 'enabled languages: ' . implode( ', ', $languages ) );
		$s->note( 'default language: ' . $sitepress->get_default_language() );

		// ---- AC-1 and AC-4: English at the root, left to right ----------------------------------------
		$root = t21_get( '/' );
		$en   = t21_document_language( $root['html'] );
		$s->note( "GET / -> {$root['status']} {$root['html']}" );
		if ( 200 !== $root['status'] || 'en-US' !== $en['lang'] || $en['rtl'] ) {
			$s->note( 'FAIL AC-1/AC-4: the root did not serve English left to right.' );
			$ok = false;
		}
		if ( 'en' !== $sitepress->get_default_language() ) {
			$s->note( 'FAIL AC-1: the default language is not English.' );
			$ok = false;
		}

		// ---- AC-2 and AC-3: Arabic under its prefix, right to left ------------------------------------
		$ar_home = t21_get( '/ar/' );
		$ar      = t21_document_language( $ar_home['html'] );
		$s->note( "GET /ar/ -> {$ar_home['status']} {$ar_home['html']}" );
		if ( 200 !== $ar_home['status'] || 'ar' !== $ar['lang'] || ! $ar['rtl'] ) {
			$s->note( 'FAIL AC-2/AC-3: the Arabic prefix did not serve Arabic right to left.' );
			$ok = false;
		}

		$ar_shop = t21_get( '/ar/shop/' );
		$ar_s    = t21_document_language( $ar_shop['html'] );
		$s->note( "GET /ar/shop/ -> {$ar_shop['status']} {$ar_shop['html']}" );
		if ( 200 !== $ar_shop['status'] || 'ar' !== $ar_s['lang'] || ! $ar_s['rtl'] ) {
			$s->note( 'FAIL AC-2: an Arabic storefront page did not serve Arabic right to left.' );
			$ok = false;
		}

		// ---- AC-1 again: the default must not drift after an Arabic request ---------------------------
		$root_again = t21_get( '/' );
		$en_again   = t21_document_language( $root_again['html'] );
		$s->note( "GET / after Arabic -> {$root_again['status']} {$root_again['html']}" );
		if ( 'en-US' !== $en_again['lang'] || $en_again['rtl'] ) {
			$s->note( 'FAIL AC-1: the root defaulted away from English after an Arabic request.' );
			$ok = false;
		}

		// ---- AC-2: the Arabic storefront addresses its own page records -------------------------------
		// Without an Arabic record, /ar/cart/ silently serves the English one, which is the gap C-2 assigns
		// to this feature.
		foreach ( array( 'shop', 'cart', 'myaccount' ) as $slug ) {
			$en_id = (int) get_option( 'woocommerce_' . $slug . '_page_id' );
			$trid  = $en_id ? $sitepress->get_element_trid( $en_id, 'post_page' ) : 0;
			$group = $trid ? $sitepress->get_element_translations( $trid, 'post_page' ) : array();
			$ar_id = isset( $group['ar'] ) ? (int) $group['ar']->element_id : 0;
			$s->note( "{$slug}: en={$en_id} ar={$ar_id}" );
			if ( ! $ar_id || $ar_id === $en_id ) {
				$s->note( "FAIL AC-2: the {$slug} page has no distinct Arabic record." );
				$ok = false;
			}
		}
		// Checkout is deliberately not requested over HTTP: WooCommerce redirects an empty cart to the cart
		// page, in both languages identically, so the response would say nothing about language resolution.
		// Measured and recorded in specs/002-bilingual-platform-baseline/research.md.

		// ---- AC-5: one translation group, English as source ------------------------------------------
		$source = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 't21 identity source',
			),
			true
		);
		if ( is_wp_error( $source ) ) {
			$s->note( 'FAIL AC-5: could not create the English record: ' . $source->get_error_message() );
			return false;
		}
		$s->track_post( (int) $source );
		$source_trid = $sitepress->get_element_trid( (int) $source, 'post_page' );

		$counterpart = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 't21 identity counterpart',
			),
			true
		);
		if ( is_wp_error( $counterpart ) ) {
			$s->note( 'FAIL AC-5: could not create the Arabic record: ' . $counterpart->get_error_message() );
			return false;
		}
		$s->track_post( (int) $counterpart );
		$sitepress->set_element_language_details( (int) $counterpart, 'post_page', $source_trid, 'ar', 'en' );

		$group = $sitepress->get_element_translations( $source_trid, 'post_page', false, true );
		$langs = array_keys( (array) $group );
		sort( $langs );
		$s->note( 'translation group: ' . implode( ', ', $langs ) );
		$resolved = isset( $group['ar'] ) ? (int) $group['ar']->element_id : 0;
		$source_of_ar = isset( $group['ar'] ) ? (string) $group['ar']->source_language_code : '';
		$s->note( "resolved Arabic counterpart: {$resolved}, source language: {$source_of_ar}" );
		if ( array( 'ar', 'en' ) !== $langs || $resolved !== (int) $counterpart || 'en' !== $source_of_ar ) {
			$s->note( 'FAIL AC-5: the pair is not one group with the English record as source.' );
			$ok = false;
		}

		// ---- AC-6: one template set renders both languages -------------------------------------------
		$theme     = get_stylesheet_directory();
		$ar_files  = glob( $theme . '/templates/*-ar.html' ) ?: array();
		$ar_files  = array_merge( $ar_files, glob( $theme . '/parts/*-ar.html' ) ?: array() );
		$templates = glob( $theme . '/templates/*.html' ) ?: array();
		$s->note( 'theme templates: ' . count( $templates ) . ', Arabic-specific: ' . count( $ar_files ) );
		if ( $ar_files ) {
			$s->note( 'FAIL AC-6: an Arabic-specific template exists, so the baseline is not language neutral.' );
			$ok = false;
		}

		// ---- AC-7 and AC-8: the mechanism exists and resolves Arabic ---------------------------------
		// `is_textdomain_loaded` is deliberately NOT asserted. It reads false, correctly: no .mo file exists
		// yet, because the storefront has no strings of its own. Asserting it would be asserting that a
		// translation exists, which is not what AC-7 or AC-8 obliges. What is asserted is that the loader is
		// hooked and that a string bound to the domain resolves.
		$hooked = has_action( 'init', array( 'MizzeySite\\I18n\\Localisation', 'loadPluginTextdomain' ) );
		$s->note( 'plugin text domain loader hooked on init: ' . ( false !== $hooked ? 'yes' : 'no' ) );
		$s->note( 'fact: the storefront ships no .mo of its own yet, because it has no strings of its own. The '
			. 'mechanism is asserted below through a temporary catalogue.' );
		$s->note( 'theme languages directory: ' . ( is_dir( $theme . '/languages' ) ? 'present' : 'missing' ) );
		$s->note( 'translation template: ' . ( file_exists( WP_PLUGIN_DIR . '/mizzey-site/languages/mizzey-site.pot' ) ? 'present' : 'missing' ) );
		if ( false === $hooked ) {
			$s->note( 'FAIL AC-7: nothing loads the site text domain, so a string cannot be translatable.' );
			$ok = false;
		}
		if ( ! is_dir( $theme . '/languages' ) ) {
			$s->note( 'FAIL AC-7: the theme has no languages directory to load from.' );
			$ok = false;
		}

		// AC-8 end to end, through the production loader and a real translation file.
		//
		// An earlier version of this probe added a `gettext` filter and asserted the string changed under
		// Arabic. That tested WordPress, not this feature: it would have passed with Localisation.php deleted.
		// So a real .mo is written, the production method loads it, and the file is removed afterwards. Nothing
		// test-shaped is shipped, and the assertion fails if the loader is removed or misconfigured.
		$probe     = 'Mizzey t21 translation probe';
		$arabic_mo = WP_PLUGIN_DIR . '/mizzey-site/languages/mizzey-site-ar.mo';
		$pre_existing = file_exists( $arabic_mo );
		if ( ! $pre_existing ) {
			file_put_contents( $arabic_mo, t21_mo( array( $probe => 'مزي t21' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$s->on_finish(
				static function () use ( $arabic_mo ) {
					if ( file_exists( $arabic_mo ) ) {
						unlink( $arabic_mo );
					}
				}
			);
		}

		// What is asserted is the resolved string, not `is_textdomain_loaded`. On WordPress 7.1 translation
		// loading is lazy: `load_plugin_textdomain()` registers the file and returns true, and the catalogue is
		// materialised when a string is first requested, so `is_textdomain_loaded()` and
		// `WP_Translation_Controller::is_textdomain_loaded()` both read false at that moment while `__()`
		// translates correctly. Measured on this runtime; see research.md. Asserting the flag failed the
		// scenario while the mechanism worked, which is the wrong way round.
		$sitepress->switch_lang( 'ar', true );
		unload_textdomain( 'mizzey-site' );
		\MizzeySite\I18n\Localisation::loadPluginTextdomain();
		$arabic = __( $probe, 'mizzey-site' ); // phpcs:ignore WordPress.WP.I18n

		$sitepress->switch_lang( 'en', true );
		unload_textdomain( 'mizzey-site' );
		\MizzeySite\I18n\Localisation::loadPluginTextdomain();
		$english = __( $probe, 'mizzey-site' ); // phpcs:ignore WordPress.WP.I18n

		$s->note( "probe in Arabic: {$arabic}" );
		$s->note( "probe in English: {$english}" );
		$s->note( 'fact: WordPress 7.1 loads translations lazily, so is_textdomain_loaded() reads false until a '
			. 'string is requested even though the loader returned true' );
		if ( 'مزي t21' !== $arabic ) {
			$s->note( 'FAIL AC-8: a string bound to the site text domain did not resolve in Arabic.' );
			$ok = false;
		}
		if ( $probe !== $english ) {
			$s->note( 'FAIL AC-8: the English source string did not survive, so the control is not sound.' );
			$ok = false;
		}

		// ---- the admin boundary: switching the storefront language must not move the admin ------------
		// ADM-159 is not owed here. This asserts only that nothing in this feature changed the admin locale.
		$s->note( 'admin locale (WPLANG): ' . (string) get_option( 'WPLANG' ) );
		if ( 'en_US' !== (string) get_option( 'WPLANG' ) ) {
			$s->note( 'FAIL: the administrative interface language moved away from English.' );
			$ok = false;
		}

		return $ok;
	}
);

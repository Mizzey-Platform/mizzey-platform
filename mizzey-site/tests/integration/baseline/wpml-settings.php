<?php
/**
 * The WPML settings a baseline ends with, printed as one JSON line. Reads only, changes nothing, and is the same
 * file for the development runtime and for staging, so the two can be compared setting by setting
 * (`staging.py parity`, and the end of `staging.py reset`).
 *
 * Why it exists. Both runtimes run the same baseline files, and on 5 October 2026 they still ended differently.
 * WPML downloads its published configuration from its own host when a plugin is activated, and where that
 * download names a plugin it replaces the plugin's bundled wpml-config.xml (WPML_Config::check_on_config_file).
 * Development made the request. Staging may not, so it kept the bundled files, and the setting that copies a
 * product's cost to its translations was missing there. A baseline therefore has an input that is not in this
 * directory, and this file reports both that input and what came of it:
 *
 *  - `sources`:  which configuration WPML was working from, the downloaded files and the bundled ones, by hash;
 *  - `settings`: everything those files decide, together with the languages the baseline sets.
 *
 * WPML's keys that begin with two underscores hold the previous value of a setting while a configuration is being
 * applied. They are bookkeeping, not settings, and are left out.
 *
 * @package MizzeySite\Tests\Integration
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mizzey_baseline_wpml_settings' ) ) {
	/**
	 * Sort a setting so that two runtimes that hold the same thing print the same thing: a list by its values,
	 * anything keyed by its keys.
	 *
	 * @param mixed $value A setting, or part of one.
	 * @return mixed
	 */
	function mizzey_baseline_sorted( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$value = array_map( 'mizzey_baseline_sorted', $value );
		if ( array_is_list( $value ) ) {
			usort( $value, static fn ( $a, $b ): int => strcmp( wp_json_encode( $a ), wp_json_encode( $b ) ) );
			return $value;
		}
		ksort( $value, SORT_STRING );
		return $value;
	}

	/**
	 * @return array{wpml:?string,wcml:?string,sources:array<string,mixed>,settings:array<string,mixed>}
	 */
	function mizzey_baseline_wpml_settings(): array {
		global $sitepress;

		$all      = (array) get_option( 'icl_sitepress_settings', array() );
		$settings = array(
			'default_language'         => $sitepress->get_default_language(),
			'active_languages'         => array_keys( $sitepress->get_active_languages() ),
			'custom_posts_sync_option' => $all['custom_posts_sync_option'] ?? null,
			'taxonomies_sync_option'   => $all['taxonomies_sync_option'] ?? null,
		);
		foreach ( (array) ( $all['translation-management'] ?? array() ) as $key => $value ) {
			if ( 0 !== strpos( (string) $key, '__' ) ) {
				$settings[ 'translation-management.' . $key ] = $value;
			}
		}

		$downloaded = get_option( 'wpml_config_files_arr' );
		$sources    = array(
			'downloaded_index'   => is_object( get_option( 'wpml_config_index' ) ),
			'downloaded_plugins' => array(),
			'downloaded_themes'  => array(),
			'bundled_plugins'    => array(),
		);
		foreach ( array( 'plugins', 'themes' ) as $kind ) {
			foreach ( (array) ( is_object( $downloaded ) ? ( $downloaded->$kind ?? array() ) : array() ) as $name => $xml ) {
				$sources[ 'downloaded_' . $kind ][ $name ] = md5( (string) $xml );
			}
		}
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			$file = WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/wpml-config.xml';
			if ( is_readable( $file ) ) {
				$sources['bundled_plugins'][ dirname( $plugin ) ] = md5_file( $file );
			}
		}

		return array(
			'wpml'     => defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : null,
			'wcml'     => defined( 'WCML_VERSION' ) ? WCML_VERSION : null,
			'sources'  => mizzey_baseline_sorted( $sources ),
			'settings' => mizzey_baseline_sorted( $settings ),
		);
	}
}

// staging/fingerprint.php loads this file for the function and prints its own line.
if ( ! defined( 'MIZZEY_WPML_SETTINGS_QUIET' ) ) {
	echo wp_json_encode( mizzey_baseline_wpml_settings() ), "\n";
}

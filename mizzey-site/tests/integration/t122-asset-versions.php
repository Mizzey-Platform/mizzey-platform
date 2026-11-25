<?php
/**
 * t122, spec 066 (K-8): every style and script of the site plugin and the theme carries a version that changes when
 * its file changes, so a browser or Cloudflare never keeps an old copy after a release.
 *
 * The measured defect (owner's iPhone, 26 November 2026): blocks registered from block.json without a "version"
 * (the product card's style, the hero's style and view script, the sign-up's view script) were printed with
 * `?ver=7.1.4`, WordPress's own version, which does not change between our releases. The card's adding loader fix
 * (#568) was live on staging while his phone and the edge cache still served the card's old stylesheet.
 *
 * In process: every registered style, script and script module whose file lives in the site plugin or the theme is
 * passed through WordPress's own source filters with the version WordPress would give it, and must come out with the
 * file's modification time; a source outside the plugin and theme, or with a version of its own, is left as it is.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';

run(
	new Scenario( 't122-asset-versions', 'K-8 (spec 066): our styles and scripts carry a version that changes with their file' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$wp_version = (string) get_bloginfo( 'version' );
		$plugin_dir = wp_normalize_path( (string) realpath( dirname( __DIR__, 2 ) ) );
		$theme_dir  = wp_normalize_path( (string) realpath( get_stylesheet_directory() ) );
		$ours       = static function ( string $src ) use ( $plugin_dir, $theme_dir ): ?string {
			$path = (string) wp_parse_url( $src, PHP_URL_PATH );
			foreach ( array( array( plugins_url( '', $plugin_dir . '/mizzey-site.php' ), $plugin_dir ), array( get_stylesheet_directory_uri(), $theme_dir ) ) as $root ) {
				$prefix = (string) wp_parse_url( $root[0], PHP_URL_PATH );
				if ( '' !== $prefix && 0 === strpos( $path, $prefix . '/' ) ) {
					$file = $root[1] . substr( $path, strlen( $prefix ) );
					return is_file( $file ) ? $file : null;
				}
			}
			return null;
		};
		$ver_of = static function ( string $src ): string {
			parse_str( (string) wp_parse_url( $src, PHP_URL_QUERY ), $query );
			return (string) ( $query['ver'] ?? '' );
		};

		// The block assets of the measured defect must be registered at all (they are, from block.json, on init).
		$seen    = 0;
		$named   = array();
		$sources = array();
		foreach ( wp_styles()->registered as $handle => $item ) {
			if ( is_string( $item->src ) && '' !== $item->src ) {
				$sources[] = array( 'style_loader_src', $handle, $item->src, $item->ver );
			}
		}
		foreach ( wp_scripts()->registered as $handle => $item ) {
			if ( is_string( $item->src ) && '' !== $item->src ) {
				$sources[] = array( 'script_loader_src', $handle, $item->src, $item->ver );
			}
		}
		$modules = ( function () {
			return $this->registered;
		} )->call( wp_script_modules() );
		foreach ( (array) $modules as $id => $module ) {
			$sources[] = array( 'script_module_loader_src', $id, (string) $module['src'], $module['version'] );
		}

		foreach ( $sources as list( $filter, $handle, $src ) ) {
			$absolute = 0 === strpos( $src, '/' ) && 0 !== strpos( $src, '//' ) ? site_url( $src ) : $src;
			$file     = $ours( $absolute );
			if ( null === $file ) {
				continue;
			}
			++$seen;
			// What staging printed for a block asset registered without a version: WordPress's own. Whatever a local
			// setting registers, every file of ours must come out with its modification time.
			$out = (string) apply_filters( $filter, add_query_arg( 'ver', $wp_version, $absolute ), $handle );
			$got = $ver_of( $out );
			$named[ basename( dirname( $file ) ) . '/' . basename( $file ) ] = $got;
			$check( (string) filemtime( $file ) === $got, sprintf( '%s %s: version %s is the file modification time', $filter, $handle, $got ) );
		}
		$check( $seen >= 10, sprintf( 'the plugin\'s and the theme\'s registered assets were found (%d)', $seen ) );
		foreach ( array( 'product-card/style.css', 'hero/style.css', 'hero/view.js', 'signup/view.js' ) as $defect ) {
			$check( isset( $named[ $defect ] ) && $named[ $defect ] !== $wp_version, sprintf( 'the measured defect %s no longer carries WordPress\'s version (%s)', $defect, $named[ $defect ] ?? 'not registered' ) );
		}

		// Somebody else's asset is not ours to change.
		$foreign = add_query_arg( 'ver', $wp_version, includes_url( 'js/dist/hooks.min.js' ) );
		$check( apply_filters( 'script_loader_src', $foreign, 'wp-hooks' ) === $foreign, 'a WordPress core script keeps its own version' );

		return $ok;
	}
);

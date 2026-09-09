<?php
/**
 * P-005. Is corex-kit-woo inactive scaffolding only, gating nothing in the running store?
 *
 * Technical Design section 4: "there is no CoreX commerce kit". The estimate is priced on that being
 * true. If the kit turned out to carry real commerce behaviour, the custom epics would be overpriced;
 * if it gates something silently, activating it later could change the store.
 */

$notes   = array();
$verdict = 'partial';

try {
	$dir = WP_PLUGIN_DIR . '/corex-kit-woo';
	if ( ! is_dir( $dir ) ) {
		throw new RuntimeException( 'corex-kit-woo is not present at ' . $dir );
	}

	$php_files = array();
	$iterator  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$php_files[] = str_replace( $dir . DIRECTORY_SEPARATOR, '', $file->getPathname() );
		}
	}
	sort( $php_files );

	$active = in_array( 'corex-kit-woo/corex-kit-woo.php', (array) get_option( 'active_plugins', array() ), true );

	$body = '';
	foreach ( $php_files as $rel ) {
		$body .= (string) file_get_contents( $dir . DIRECTORY_SEPARATOR . $rel );
	}

	// Store behaviour means registering something a shopper or an admin would meet. A hook on its own
	// is not that: declaring HPOS compatibility is boilerplate every Woo-adjacent plugin ships, and
	// counting it as behaviour is how the first run of this probe reached the wrong answer.
	$structural = array(
		'register_post_type'          => substr_count( $body, 'register_post_type' ),
		'register_taxonomy'           => substr_count( $body, 'register_taxonomy' ),
		'add_shortcode'               => substr_count( $body, 'add_shortcode' ),
		'register_block_type'         => substr_count( $body, 'register_block_type' ),
		'woocommerce_locate_template' => substr_count( $body, 'woocommerce_locate_template' ),
	);
	$live = array_filter( $structural );

	$hooks = array();
	if ( preg_match_all( '/add_(?:action|filter)\(\s*[\'"]([^\'"]+)/', $body, $matches ) ) {
		$hooks = array_values( array_unique( $matches[1] ) );
	}

	$notes[] = sprintf(
		'corex-kit-woo holds %d PHP file(s) totalling about %d lines: %s. Active on this site: %s.',
		count( $php_files ),
		substr_count( $body, "\n" ),
		implode( ', ', $php_files ),
		$active ? 'yes' : 'no'
	);
	$notes[] = $live
		? 'Store registrations found: ' . implode( ', ', array_map(
			static function ( $k, $v ) {
				return $k . ' x' . $v;
			},
			array_keys( $live ),
			$live
		) ) . '.'
		: 'It registers no post type, taxonomy, shortcode, block or template override.';
	$notes[] = $hooks ? 'It hooks: ' . implode( ', ', $hooks ) . '.' : 'It hooks nothing.';

	$only_compat = ! $live && ( ! $hooks || array( 'before_woocommerce_init' ) === $hooks );

	if ( $only_compat ) {
		$verdict = 'confirmed';
		$notes[] = 'Its only hook declares HPOS compatibility, which is boilerplate, not behaviour. It reserves a '
			. 'seam and does nothing else, exactly as Technical Design section 4 says. The custom epic pricing that '
			. 'rests on CoreX contributing no commerce is sound.';
	} elseif ( ! $live ) {
		$verdict = 'partial';
		$notes[] = 'No store registrations, but it hooks more than compatibility. Read those hooks before relying on '
			. 'section 4, though nothing here contradicts it yet.';
	} else {
		$verdict = 'refuted';
		$notes[] = 'It registers store behaviour. Section 4 understates it, and what it does needs reading before the '
			. 'custom epics are priced on the assumption that CoreX contributes nothing to commerce.';
	}
} catch ( Throwable $e ) {
	$notes[] = 'Probe error: ' . $e->getMessage();
	$verdict = 'partial';
}

echo wp_json_encode( array( 'observed' => implode( ' ', $notes ), 'verdict' => $verdict ) );

<?php
/**
 * t29, AC-3: a cost that is on the English product before its Arabic record exists reaches the Arabic record,
 * whichever way the translation is created, on a runtime that holds no downloaded WPML configuration.
 *
 * Why it exists. The pilot verified these four cases inside t11 on 4 October 2026, and they passed. What nobody
 * recorded was the condition: WPML had downloaded a configuration from its publisher's host, and that download,
 * not anything in this repository, declared the stored cost field `_cogs_total_value` as copied to translations.
 * WooCommerce Multilingual copies a custom field to a new translation only when WPML holds a setting for it. On
 * staging, which makes no outside request, the setting was absent and three of the four cases failed (measured on
 * 5 October 2026, issue #252). The site plugin now declares the setting itself, in `mizzey-site/wpml-config.xml`.
 *
 * The condition is part of the scenario. Run on the baseline built without the download:
 *
 *   MIZZEY_WPML_REMOTE_CONFIG=off MIZZEY_CONFIRM_RESET=yes sh mizzey-site/tests/integration/baseline/reset-runtime.sh ../app/wp
 *
 * it fails without the declaration and passes with it. On the ordinary baseline the download declares the field
 * too, so a pass there says nothing about the declaration, and the first note of every run says which baseline
 * it was.
 *
 * In process, as t11 creates translations: WPML's duplicate, and the save of WooCommerce Multilingual's
 * translation editor. Fresh products only, removed on finish.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

run(
	new Scenario( 't29-cost-before-translation', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$settings   = get_option( 'icl_sitepress_settings' )['translation-management'] ?? array();
		$downloaded = is_object( get_option( 'wpml_config_index' ) );
		$s->note( sprintf(
			'Baseline: WPML downloaded configuration %s; setting for _cogs_total_value %s; mizzey-site/wpml-config.xml %s',
			$downloaded ? 'PRESENT, so this run does not test the site declaration' : 'absent',
			var_export( $settings['custom_fields_translation']['_cogs_total_value'] ?? 'unset', true ),
			file_exists( WP_PLUGIN_DIR . '/mizzey-site/wpml-config.xml' ) ? 'present' : 'absent'
		) );

		$w      = new Workflows( $s );
		$show   = static fn ( ?float $cost ): string => null === $cost ? 'none' : (string) $cost;
		$ok_all = true;
		$n      = 0;
		foreach ( array( 'duplicate', 'editor' ) as $method ) {
			$en = $w->simple( 'T29S' . ( ++$n ), 120.0 );
			$ar = $w->translate( $method, $en );
			$ok = $ar && 120.0 === Workflows::cost( $ar );
			$s->note( sprintf( '%s simple product, %s: English %s, Arabic %s', $ok ? 'OK  ' : 'FAIL', $method, $show( Workflows::cost( $en ) ), $ar ? $show( Workflows::cost( $ar ) ) : 'no translation' ) );
			$ok_all = $ok_all && $ok;

			list( $parent, $variations ) = $w->variable( 'T29V' . ( ++$n ), array( 'S' => 40.0, 'L' => 70.0 ) );
			$ar_parent     = $w->translate( $method, $parent );
			$ar_variations = array_map( array( Workflows::class, 'ar_of' ), $variations );
			$got           = array_map( array( Workflows::class, 'cost' ), $ar_variations );
			$ok            = $ar_parent && ! in_array( 0, $ar_variations, true ) && array( 40.0, 70.0 ) === $got;
			$s->note( sprintf( '%s product with variants, %s: English 40/70, Arabic %s', $ok ? 'OK  ' : 'FAIL', $method, implode( '/', array_map( $show, $got ) ) ) );
			$ok_all = $ok_all && $ok;
		}
		return $ok_all;
	}
);

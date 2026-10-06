<?php
/**
 * t11, AC-3 (and AC-1, AC-2, AC-4): product cost consistency between English products and their Arabic
 * translations, across every supported catalogue workflow. Replaces t05, t06 and t10.
 *
 * Part 1, cost present before the translation exists: an English product created with cost (CRUD, and native CSV
 * import), then translated. Does the Arabic copy carry the cost?
 * Part 2, cost written after the translation exists: for each translation method (WPML duplicate, WCML editor) x
 * product type (simple, a variation) x channel (admin-http, import-http, rest-http, crud-cli, crud-web; see
 * _workflows.php for why each channel runs in its real request context):
 *   A. set a cost where there was none; B. change it. After each: English cost, Arabic cost.
 *   Then orders for the English and the Arabic product record the current cost, and price, stock, SKU, title and
 *   status of both are unchanged.
 * Every case uses fresh products. Pass only if every case is consistent.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

function fmt( ?float $v ): string {
	if ( null !== $v && is_nan( $v ) ) {
		return 'n/a';
	}
	return null === $v ? 'none' : rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
}

run(
	new Scenario( 't11-cost-sync-matrix', 'AC-3' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$s->enable_cogs();
		$tm = $sitepress->get_setting( 'translation-management' );
		// _cogs_total_value is the stored key. This note printed _cogs_value until 6 October 2026, which is the
		// form field's name and never has a setting, so every run said "unset" whatever WPML held (issue #252).
		$s->note( 'WPML setting _cogs_total_value=' . var_export( $tm['custom_fields_translation']['_cogs_total_value'] ?? 'unset', true )
			. '; WPML downloaded configuration ' . ( is_object( get_option( 'wpml_config_index' ) ) ? 'present' : 'absent' )
			. '; mizzey-site/wpml-config.xml ' . ( file_exists( WP_PLUGIN_DIR . '/mizzey-site/wpml-config.xml' ) ? 'present' : 'absent' ) );
		$w      = new Workflows( $s );
		$ok_all = true;
		$n      = 0;

		// Part 1: cost before translation.
		foreach ( array( 'duplicate', 'editor' ) as $method ) {
			$sku = 'P1' . ( ++$n );
			$id  = $w->simple( $sku, 120.0 );
			$ar  = $w->translate( $method, $id );
			$ok  = $ar && 120.0 === Workflows::cost( $ar );
			$s->note( sprintf( '%s part1 crud-created simple, %s: AR %s', $ok ? 'OK  ' : 'FAIL', $method, $ar ? fmt( Workflows::cost( $ar ) ) : 'no translation' ) );
			$ok_all = $ok_all && $ok;

			list( $pid, $vids ) = $w->variable( 'P1V' . ( ++$n ), array( 'S' => 40.0, 'L' => 70.0 ) );
			$arp = $w->translate( $method, $pid );
			$arv = array_map( array( Workflows::class, 'ar_of' ), $vids );
			$got = array_map( array( Workflows::class, 'cost' ), $arv );
			$ok  = $arp && ! in_array( 0, $arv, true ) && array( 40.0, 70.0 ) === $got;
			$s->note( sprintf( '%s part1 crud-created variations, %s: AR %s', $ok ? 'OK  ' : 'FAIL', $method, implode( '/', array_map( __NAMESPACE__ . '\fmt', $got ) ) ) );
			$ok_all = $ok_all && $ok;
		}

		// Part 2: cost written after the translation exists.
		foreach ( array( 'duplicate', 'editor' ) as $method ) {
			foreach ( array( 'simple', 'variation' ) as $type ) {
				foreach ( array( 'admin-http', 'import-http', 'rest-http', 'crud-cli', 'crud-web' ) as $channel ) {
					$tag = 'M' . ( ++$n );
					if ( 'simple' === $type ) {
						$en    = $w->simple( $tag, null );
						$ar_p  = $w->translate( $method, $en );
						$ar    = $ar_p;
						$target = array( 100.0, 111.0 );
					} else {
						list( $pid, $vids ) = $w->variable( $tag, array( 'S' => null, 'L' => 70.0 ) );
						$ar_p   = $w->translate( $method, $pid );
						$en     = $vids[0];
						$ar     = Workflows::ar_of( $en );
						$target = array( 40.0, 44.0 );
					}
					if ( ! $ar ) {
						$s->note( "FAIL $method $type $channel: no Arabic counterpart" );
						$ok_all = false;
						continue;
					}
					// The importer addresses products by SKU, and a WPML duplicate carries the SKU of its
					// original, so a row can reach either of them. When it reaches the translation the cost is
					// not applied at all, because the original owns it (t16): the two language versions still
					// agree, which is what AC-3 asks, and the attempt is recorded in the log. Reported either way.
					$addressed = 'import-http' === $channel ? Workflows::sku_target( $en ) : $en;
					$reaches_original = $addressed === $en;

					$before = array( Workflows::snapshot( $en ), Workflows::snapshot( $ar ) );
					$steps  = array();
					$ok     = true;
					foreach ( $target as $i => $value ) {
						$was     = Workflows::cost( $en );
						$res     = $w->set_cost( $channel, $en, $value );
						$en_cost = Workflows::cost( $en );
						$ar_cost = Workflows::cost( $ar );
						$want    = $reaches_original ? $value : $was;
						$steps[] = sprintf( '%s %s: EN %s AR %s%s', 0 === $i ? 'set' : 'change', fmt( $value ), fmt( $en_cost ), fmt( $ar_cost ), 'ok' === $res ? '' : " [$res]" );
						$ok      = $ok && 'ok' === $res && $want === $en_cost && $want === $ar_cost;
					}
					$after   = array( Workflows::snapshot( $en ), Workflows::snapshot( $ar ) );
					$changed = array();
					$facts   = array();
					foreach ( array( 'EN', 'AR' ) as $k => $label ) {
						foreach ( $before[ $k ] as $field => $v ) {
							if ( $after[ $k ][ $field ] === $v ) {
								continue;
							}
							$moved = "$label $field " . var_export( $v, true ) . '->' . var_export( $after[ $k ][ $field ], true );
							// A variation's post_title is generated by WooCommerce from the parent name and the
							// attributes, and rewritten on every save of that variation (variation data store
							// update()). WCML's own admin sync renames Arabic variations the same way, with or
							// without the Mizzey sync. It is recorded, not treated as a contract failure; the
							// authored fields (price, stock, sku, status) are.
							if ( 'variation' === $type && 'post_title' === $field ) {
								$facts[] = $moved . ' (generated variation title, rewritten on any save)';
								continue;
							}
							$changed[] = $moved;
						}
					}
					$order_en = $w->order_cost( $en, 'en' );
					$order_ar = $w->order_cost( $ar, 'ar' );
					$want     = $reaches_original ? end( $target ) : Workflows::cost( $en );
					$ok       = $ok && ! $changed && ! is_nan( $order_en ) && ! is_nan( $order_ar ) && abs( $order_en - $want ) < 0.001 && abs( $order_ar - $want ) < 0.001;
					$s->note( sprintf( '%s %s %s %s%s: %s | orders EN %s AR %s | %s%s', $ok ? 'OK  ' : 'FAIL', $method, $type, $channel,
						$reaches_original ? '' : ' (the SKU reached the translation, so the cost is unchanged and the two still agree)',
						implode( '; ', $steps ),
						fmt( $order_en ), fmt( $order_ar ),
						$changed ? 'CHANGED ' . implode( ', ', $changed ) : 'price/stock/sku/status unchanged',
						$facts ? ' | noted: ' . implode( ', ', $facts ) : '' ) );
					$ok_all = $ok_all && $ok;
				}
			}
		}
		return $ok_all;
	}
);

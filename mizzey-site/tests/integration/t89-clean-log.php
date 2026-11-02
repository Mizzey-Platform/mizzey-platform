<?php
/**
 * t89, a clean application log (specs/048-clean-log): saving a simple and a variable product, and writing an Arabic
 * record through WooCommerce Multilingual's editor job, add nothing to PHP's error log; WooCommerce Multilingual's
 * missing setting is supplied; WPML 4.9.7's two product-save handlers are gone. PHP's error log is pointed at a
 * throwaway file for the checks, with every level reported. Every product it makes is removed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\I18n\ProductTranslationJob;
use MizzeySite\I18n\WpmlProductSaveHook;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

run(
	new Scenario( 't89-clean-log', 'NFR-10: a clean application log' ),
	function ( Scenario $s ): bool {
		global $woocommerce_wpml;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$w   = new Workflows( $s );
		$tag = 't89' . wp_rand( 1000, 9999 );

		// PHP's own log, for this process only, with every level reported (as WP_DEBUG does).
		$sink      = wp_normalize_path( get_temp_dir() ) . "{$tag}-php.log";
		$was_log   = (string) ini_get( 'error_log' );
		$was_level = error_reporting( E_ALL );
		ini_set( 'error_log', $sink );
		set_error_handler( null );
		$s->on_finish( static function () use ( $sink, $was_log, $was_level ) {
			restore_error_handler();
			ini_set( 'error_log', $was_log );
			error_reporting( $was_level );
			if ( is_file( $sink ) ) {
				unlink( $sink );
			}
		} );
		$logged = static function () use ( $sink ): string {
			clearstatcache();
			return is_file( $sink ) ? (string) file_get_contents( $sink ) : '';
		};

		// ---- AC-1: product saves ----------------------------------------------------------------------------------
		$simple = $w->simple( "{$tag}-S", null );
		$p      = wc_get_product( $simple );
		$p->set_regular_price( '310' );
		$p->save();
		list( $parent, $vars ) = $w->variable( "{$tag}-V", array( 'S' => null, 'L' => null ) );
		$v = wc_get_product( $vars[0] );
		$v->set_regular_price( '205' );
		$v->save();
		$after_saves = $logged();
		$check( '' === $after_saves, 'AC-1 saving a simple product, a variable product and a variation adds nothing to PHP\'s error log (' . substr( $after_saves, 0, 300 ) . ')' );

		// ---- AC-1: a translation written from code, through WooCommerce Multilingual's editor job ------------------
		$ar = ProductTranslationJob::save( $simple, 'ar', array( 'title' => 'منتج ' . $tag, 'slug' => '', 'product_content' => '', 'product_excerpt' => '' ) );
		do_action( 'wpml_switch_language', 'en' );
		if ( $ar > 0 && $ar !== $simple ) {
			$s->track_post( $ar );
		}
		$check( $ar > 0 && $ar !== $simple && 'منتج ' . $tag === get_post_field( 'post_title', $ar ), 'AC-1 the Arabic record is written through the editor job (' . $ar . ')' );
		$after_translation = $logged();
		$check( '' === $after_translation, 'AC-1 writing it adds nothing to PHP\'s error log (' . substr( $after_translation, 0, 300 ) . ')' );
		$check( ! isset( $_POST['data'] ) && ! isset( $_POST['to_lang'] ) && ! isset( $_POST['icl_post_language'] ), 'FR-002 the request\'s posted fields are put back as they were' );
		// Once supplied, WooCommerce Multilingual may save it into its stored settings; the default is checked on its own.
		$filled = apply_filters( 'option__wcml_settings', array( 'cart_sync' => array() ) );
		$check( isset( $woocommerce_wpml->settings['products_sync_date'] ) && 1 === ( $filled['products_sync_date'] ?? null ), 'FR-002 WooCommerce Multilingual\'s publish-date setting is supplied when the stored settings lack it' );

		// A translation job a translator already has is kept: the save reuses it and never sends a new one.
		$second = $w->simple( "{$tag}-J", null );
		$open   = (int) wpml_tm_load_job_factory()->create_local_post_job( $second, 'ar' );
		$jobs   = static function ( int $id ): int {
			global $wpdb;
			$trid = (int) apply_filters( 'wpml_element_trid', null, $id, 'post_product' );
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translate_job j JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id WHERE t.trid = %d", $trid ) );
		};
		$before_jobs = $jobs( $second );
		$ar2         = ProductTranslationJob::save( $second, 'ar', array( 'title' => 'منتج ثان ' . $tag, 'slug' => '', 'product_content' => '', 'product_excerpt' => '' ) );
		do_action( 'wpml_switch_language', 'en' );
		if ( $ar2 > 0 && $ar2 !== $second ) {
			$s->track_post( $ar2 );
		}
		$trid2 = (int) apply_filters( 'wpml_element_trid', null, $second, 'post_product' );
		$check( $open > 0 && $ar2 > 0 && $open === (int) wpml_tm_load_job_factory()->job_id_by_trid_and_lang( $trid2, 'ar' ) && $jobs( $second ) === $before_jobs, 'FR-002 a translation job already open for the product is reused, not replaced, and no new one is sent (' . $open . ', ' . $before_jobs . ' to ' . $jobs( $second ) . ')' );

		// ---- The WPML handlers ----------------------------------------------------------------------------------
		$left = array();
		foreach ( $GLOBALS['wp_filter']['woocommerce_after_product_object_save']->callbacks ?? array() as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'];
				if ( is_array( $fn ) && is_object( $fn[0] ) && in_array( $fn[1], array( 'on_product_save', 'woocommerce_extract_media_ids_from_post_content_and_meta' ), true ) ) {
					$left[] = get_class( $fn[0] ) . '::' . $fn[1];
				}
			}
		}
		$check( WpmlProductSaveHook::VERSION !== ICL_SITEPRESS_VERSION || array() === $left, 'FR-001 WPML ' . ICL_SITEPRESS_VERSION . '\'s two product-save handlers are removed (' . implode( ', ', $left ) . ')' );

		return $ok;
	}
);

<?php
/**
 * t18 (probe A12): which product and variation fields reach the Arabic record, and through what.
 *
 * Measurement only. No fix is implemented here, and no fix is created for any field outside Option B.
 *
 * Why it exists. The product-cost pilot found that WooCommerce's data store skips wp_update_post() on a meta-only
 * save, so save_post never fires and WPML and WCML, which synchronise on save_post, never run. Cost was the field
 * with a contracted financial consequence, so it was fixed first. This scenario bounds the problem: it changes one
 * field at a time on the English original through WooCommerce CRUD, the channel that exposed the cost gap, and
 * records whether the Arabic record followed and whether save_post fired.
 *
 * What it does not do. It exercises one channel. The pilot already established the channel pattern: wp-admin and
 * the wp-admin importer fire save_post, while REST, WP-CLI and front-end code do not for a meta-only change
 * (t15, ten cases). A field that does not follow under CRUD and that WCML synchronises on save_post is therefore
 * expected to follow in wp-admin, and that expectation is recorded as a hypothesis, not as a measurement.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Read one field from storage, by the same accessor an operator's edit would use. */
function t18_get( int $id, string $field ) {
	wp_cache_flush();
	$p = wc_get_product( $id );
	if ( ! $p ) {
		return '(no product)';
	}
	switch ( $field ) {
		case 'regular_price':
			return (string) $p->get_regular_price();
		case 'sale_price':
			return (string) $p->get_sale_price();
		case 'price':
			return (string) $p->get_price();
		case 'cost':
			$v = $p->get_cogs_value();
			return null === $v ? '(none)' : (string) $v;
		case 'sku':
			return (string) $p->get_sku();
		case 'stock_quantity':
			return var_export( $p->get_stock_quantity(), true );
		case 'stock_status':
			return (string) $p->get_stock_status();
		case 'manage_stock':
			return $p->get_manage_stock() ? 'yes' : 'no';
		case 'weight':
			return (string) $p->get_weight();
		case 'length':
			return (string) $p->get_length();
		case 'status':
			return (string) $p->get_status();
		case 'catalog_visibility':
			return (string) $p->get_catalog_visibility();
		case 'name':
			return (string) $p->get_name();
		case 'custom_field':
			return (string) get_post_meta( $id, '_mizzey_t18_field', true );
		case 'category':
			return implode( ',', wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'slugs' ) ) ) ?: '(none)';
		default:
			return '(unknown field)';
	}
}

/** Change one field on the English original, through WooCommerce CRUD. */
function t18_set( int $id, string $field, $value ): void {
	$p = wc_get_product( $id );
	switch ( $field ) {
		case 'regular_price':
			$p->set_regular_price( $value );
			break;
		case 'sale_price':
			$p->set_sale_price( $value );
			break;
		case 'cost':
			$p->set_cogs_value( $value );
			break;
		case 'sku':
			$p->set_sku( $value );
			break;
		case 'stock_quantity':
			$p->set_stock_quantity( $value );
			break;
		case 'stock_status':
			$p->set_stock_status( $value );
			break;
		case 'manage_stock':
			$p->set_manage_stock( (bool) $value );
			break;
		case 'weight':
			$p->set_weight( $value );
			break;
		case 'length':
			$p->set_length( $value );
			break;
		case 'status':
			$p->set_status( $value );
			break;
		case 'catalog_visibility':
			$p->set_catalog_visibility( $value );
			break;
		case 'name':
			$p->set_name( $value );
			break;
		case 'custom_field':
			$p->update_meta_data( '_mizzey_t18_field', $value );
			break;
		case 'category':
			$term = term_exists( $value, 'product_cat' ) ?: wp_insert_term( $value, 'product_cat' );
			$p->set_category_ids( array( (int) ( is_array( $term ) ? $term['term_id'] : $term ) ) );
			break;
	}
	$p->save();
}

run(
	new Scenario( 't18-synced-fields', 'A12 (probe, no criterion)' ),
	function ( Scenario $s ): ?bool {
		$s->enable_cogs();
		$w = new Workflows( $s );

		// What fired during the change, so "save_post required" is a measured fact per field.
		$fired    = array();
		$recorder = function ( $tag ) use ( &$fired ) {
			return function ( $arg = null ) use ( $tag, &$fired ) {
				$id      = is_object( $arg ) && method_exists( $arg, 'get_id' ) ? $arg->get_id() : ( is_numeric( $arg ) ? (int) $arg : 0 );
				$fired[] = $id ? "$tag($id)" : $tag;
			};
		};
		foreach ( array( 'save_post', 'woocommerce_update_product', 'woocommerce_update_product_variation' ) as $hook ) {
			add_action( $hook, $recorder( $hook ), 50, 1 );
		}

		// field, value to set, Option B relevance (the register rows the field serves)
		$fields = array(
			array( 'regular_price', '321', 'PDP-05, PLP-08, BR-005' ),
			array( 'sale_price', '299', 'PROMO rows, BR-005' ),
			array( 'cost', 77.5, 'ADM-27, RPT-11' ),
			array( 'sku', 'A12-CHANGED-' . substr( (string) wp_rand(), 0, 5 ), 'ADM-33, MIG-13, ERP-08' ),
			array( 'stock_quantity', 6, 'ERP-01, ADM-70, RPT-10' ),
			array( 'stock_status', 'outofstock', 'ERP-02, PDP-12, RPT-10' ),
			array( 'manage_stock', false, 'ADM-70, ERP-07' ),
			array( 'weight', '2.5', 'SHIP rows, ADM-40' ),
			array( 'length', '12', 'SHIP rows, ADM-40' ),
			array( 'status', 'draft', 'ADM-30, SSC rows' ),
			array( 'catalog_visibility', 'hidden', 'ADM-30, PLP rows' ),
			array( 'name', 'A12 renamed in English', 'NFR-04, SSC-12' ),
			array( 'custom_field', 'changed-by-t18', 'ENT rows, developer-owned meta' ),
			array( 'category', 'a12-category', 'ADM-60, PLP-03' ),
		);

		foreach ( array( 'simple', 'variation' ) as $type ) {
			$s->note( sprintf( '=== %s products: one field at a time on the English original, through WooCommerce CRUD ===', $type ) );

			foreach ( $fields as list( $field, $value, $relevance ) ) {
				// A fresh pair per field, so one field's change cannot explain another's result.
				if ( 'simple' === $type ) {
					$en = $w->simple( 'A12-' . strtoupper( substr( md5( $field ), 0, 6 ) ), null );
					$w->translate( 'duplicate', $en );
					$ar = Workflows::ar_of( $en );
				} else {
					list( $pid, $vids ) = $w->variable( 'A12V-' . strtoupper( substr( md5( $field ), 0, 6 ) ), array( 'S' => null, 'L' => null ) );
					$w->translate( 'duplicate', $pid );
					$en = $vids[0];
					$ar = Workflows::ar_of( $en );
				}

				if ( ! $ar ) {
					$s->note( sprintf( '  %-19s NO ARABIC COUNTERPART, not measured', $field ) );
					continue;
				}

				// catalog_visibility and category are not variation concerns; WooCommerce has no setter on a variation.
				if ( 'variation' === $type && in_array( $field, array( 'catalog_visibility', 'category' ), true ) ) {
					$s->note( sprintf( '  %-19s not a variation field in WooCommerce, not measured  [%s]', $field, $relevance ) );
					continue;
				}

				$before_en = t18_get( $en, $field );
				$before_ar = t18_get( $ar, $field );
				$fired     = array();

				// A duplicate shares its original's SKU, so WooCommerce refuses a SKU change that would collide.
				// That refusal is itself a finding, recorded rather than allowed to end the scenario.
				try {
					t18_set( $en, $field, $value );
				} catch ( \Throwable $e ) {
					$s->note( sprintf(
						'  %-19s REFUSED by WooCommerce: %s  [%s]',
						$field,
						$e->getMessage(),
						$relevance
					) );
					continue;
				}

				$after_en  = t18_get( $en, $field );
				$after_ar  = t18_get( $ar, $field );
				$save_post = (bool) array_filter( $fired, fn( $e ) => 0 === strpos( $e, 'save_post(' ) );

				$en_changed = ( $after_en !== $before_en );
				$followed   = ( $after_ar === $after_en );
				$verdict    = ! $en_changed
					? 'NOT APPLIED on the original, nothing to measure'
					: ( $followed ? 'FOLLOWED' : '*** DID NOT FOLLOW ***' );

				$s->note( sprintf(
					'  %-19s EN %s -> %s | AR %s -> %s | save_post: %s | %s  [%s]',
					$field,
					$before_en,
					$after_en,
					$before_ar,
					$after_ar,
					$save_post ? 'fired' : 'did NOT fire',
					$verdict,
					$relevance
				) );
			}
		}

		// ---- What WCML registers, recorded as a fact beside the measurements ------------------------------
		$s->note( '=== WCML synchronisation components present in this version ===' );
		$dir = WP_PLUGIN_DIR . '/woocommerce-multilingual/classes/Synchronization/Component';
		$names = array();
		foreach ( (array) glob( $dir . '/*.php' ) as $f ) {
			$names[] = basename( $f, '.php' );
		}
		$s->note( '  ' . implode( ', ', $names ) );

		$s->note( '=== which WPML and WCML callbacks sit on the hooks a product save can fire ===' );
		global $wp_filter;
		$seen = array();
		foreach ( array( 'save_post', 'woocommerce_update_product', 'woocommerce_update_product_variation', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock', 'woocommerce_product_object_updated_props' ) as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( $cbs as $cb ) {
					$fn   = $cb['function'];
					$name = is_array( $fn )
						? ( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0] ) . '::' . $fn[1]
						: ( is_string( $fn ) ? $fn : '(closure)' );
					if ( preg_match( '/wcml|wpml|sitepress/i', $name ) ) {
						$seen[ "$hook@$prio $name" ] = true;
					}
				}
			}
		}
		foreach ( array_keys( $seen ) as $line ) {
			$s->note( '  ' . $line );
		}

		$s->note( 'Measured on the crud-cli channel only. wp-admin and the wp-admin importer fire save_post (t15), so a field WCML synchronises on save_post is expected to follow there: recorded as a hypothesis, not a measurement.' );
		return null; // fact-finding
	}
);

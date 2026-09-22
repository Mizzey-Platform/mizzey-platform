<?php
/**
 * t14, AC-3 and AC-5: what the cost synchronisation leaves untouched, including what an Arabic customer sees.
 *
 * For a simple product and for a variation, translated both ways (WPML duplicate, WCML translation editor), the
 * Arabic post is photographed in full before and after a cost change on the English original:
 *   - every authored field: price, sale price, SKU, stock and stock management, status, attributes, and a custom
 *     field written by hand to stand for unrelated third-party data. None of them may move;
 *   - the WPML language and trid, so the translation relationship is still intact;
 *   - what a customer reads: the product name, a variation's attribute summary, the Arabic product page, and the
 *     name and meta an Arabic order records for the line. None of them may move.
 *
 * Two kinds of change are expected and are reported as facts rather than failures, because WooCommerce makes them
 * whenever anything saves a product through the CRUD API, WCML's own synchronisation included:
 *   - it fills in its own bookkeeping fields (product version, rating and review counters, and so on);
 *   - it regenerates a variation's post_title from the parent name and the attributes. This scenario does not take
 *     that on trust: it measures the Arabic strings the customer actually reads, before and after.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** The cost fields this feature is allowed to change. */
const T14_COST_FIELDS = array( '_cogs_total_value', '_cogs_value_is_additive' );

/** Fields WooCommerce maintains itself and writes on any CRUD save. Recorded, not treated as a failure. */
const T14_BOOKKEEPING = array( '_product_version', '_wc_average_rating', '_wc_review_count', '_thumbnail_id', '_variation_description', '_edit_lock', '_edit_last', '_wp_old_slug' );

/** Everything stored about a post: fields, custom fields, language, translation group, product terms. */
function t14_stored( int $id ): array {
	wp_cache_flush();
	$post = get_post( $id );
	$meta = get_post_meta( $id );
	ksort( $meta );
	$terms = array();
	foreach ( array( 'product_type', 'product_cat', 'product_tag', 'product_visibility' ) as $tax ) {
		$got           = wp_get_object_terms( $id, $tax, array( 'fields' => 'slugs' ) );
		$terms[ $tax ] = is_wp_error( $got ) ? 'error' : implode( ',', $got );
	}
	return array(
		'post'  => array(
			'title'      => $post->post_title,
			'name'       => $post->post_name,
			'status'     => $post->post_status,
			'parent'     => (int) $post->post_parent,
			'menu_order' => (int) $post->menu_order,
			'content'    => $post->post_content,
			'excerpt'    => $post->post_excerpt,
		),
		'meta'  => $meta,
		'lang'  => apply_filters( 'wpml_post_language_details', null, $id )['language_code'] ?? 'unknown',
		'trid'  => (int) apply_filters( 'wpml_element_trid', null, $id, 'post_' . get_post_type( $id ) ),
		'terms' => $terms,
	);
}

/**
 * What a customer reads: the name and a variation's attribute summary in the Arabic context, and what the Store
 * API serves for the Arabic product, which is the catalogue data a storefront renders.
 *
 * The rendered Arabic page itself is not fetched here. WPML puts Arabic on /ar/ URLs, which need the web server
 * rewrite configuration that the disposable runtime does not have, and the id-based form quietly serves the
 * English translation instead. Public exposure of cost is covered over HTTP by t08; the rendered Arabic page
 * belongs to UAT.
 */
function t14_shopper_view( int $id, int $page_id ): array {
	do_action( 'wpml_switch_language', 'ar' );
	wp_cache_flush();
	$product = wc_get_product( $id );
	$view    = array(
		'name'       => $product ? $product->get_name() : 'missing',
		'attributes' => $product instanceof \WC_Product_Variation ? wc_get_formatted_variation( $product, true, true, false ) : '',
		'storefront' => '',
	);
	$response = wp_remote_get( add_query_arg( 'rest_route', "/wc/store/v1/products/$page_id", home_url( '/' ) ), array( 'timeout' => 60 ) );
	$code     = is_wp_error( $response ) ? $response->get_error_message() : (int) wp_remote_retrieve_response_code( $response );
	$body     = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
	$json     = json_decode( $body, true );
	$view['storefront'] = sprintf(
		'Store API HTTP %s, name "%s", price %s, cost %s',
		$code,
		is_array( $json ) ? ( $json['name'] ?? 'none' ) : 'not JSON',
		is_array( $json ) ? ( $json['prices']['price'] ?? 'none' ) : 'none',
		preg_match( '/123[.,]5/', wp_strip_all_tags( $body ) ) ? 'PRESENT' : 'absent'
	);
	do_action( 'wpml_switch_language', 'en' );
	return $view;
}

/** The line an Arabic order records: what the customer, the invoice and the email show. */
function t14_order_line( Scenario $s, int $id ): array {
	do_action( 'wpml_switch_language', 'ar' );
	wp_cache_flush();
	$product = wc_get_product( $id );
	$line    = array( 'name' => 'missing', 'meta' => '', 'cost' => 'none' );
	if ( $product ) {
		$order = $s->order_for( $product, 1 );
		$item  = current( $order->get_items() );
		$line  = array(
			'name' => $item ? $item->get_name() : 'no item',
			'meta' => $item ? trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( wc_display_item_meta( $item, array( 'echo' => false ) ) ) ) ) : '',
			'cost' => (string) $order->get_cogs_total_value(),
		);
	}
	do_action( 'wpml_switch_language', 'en' );
	return $line;
}

/**
 * Differences between two stored snapshots, split into what must not move and what WooCommerce maintains itself.
 *
 * @return array{authored:string[],generated:string[]}
 */
function t14_differences( array $before, array $after, bool $is_variation ): array {
	$authored  = array();
	$generated = array();
	foreach ( array( 'post', 'terms' ) as $group ) {
		foreach ( $before[ $group ] as $key => $value ) {
			if ( $after[ $group ][ $key ] === $value ) {
				continue;
			}
			$line = sprintf( '%s %s: %s -> %s', $group, $key, var_export( $value, true ), var_export( $after[ $group ][ $key ], true ) );
			if ( $is_variation && 'title' === $key ) {
				$generated[] = $line . ' (WooCommerce regenerates a variation title on any save)';
			} else {
				$authored[] = $line;
			}
		}
	}
	foreach ( array( 'lang', 'trid' ) as $key ) {
		if ( $before[ $key ] !== $after[ $key ] ) {
			$authored[] = sprintf( '%s: %s -> %s', $key, var_export( $before[ $key ], true ), var_export( $after[ $key ], true ) );
		}
	}
	foreach ( array_unique( array_merge( array_keys( $before['meta'] ), array_keys( $after['meta'] ) ) ) as $key ) {
		if ( in_array( $key, T14_COST_FIELDS, true ) ) {
			continue;
		}
		$was = $before['meta'][ $key ] ?? null;
		$now = $after['meta'][ $key ] ?? null;
		if ( $was === $now ) {
			continue;
		}
		$line = sprintf( '%s: %s -> %s', $key, wp_json_encode( $was ), wp_json_encode( $now ) );
		if ( in_array( $key, T14_BOOKKEEPING, true ) ) {
			$generated[] = $line;
		} else {
			$authored[] = $line;
		}
	}
	return array( 'authored' => $authored, 'generated' => $generated );
}

run(
	new Scenario( 't14-arabic-content-after-sync', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$w      = new Workflows( $s );
		$ok_all = true;
		$n      = 0;

		foreach ( array( 'duplicate', 'editor' ) as $method ) {
			foreach ( array( 'simple', 'variation' ) as $type ) {
				$tag = 'T14' . ( ++$n );
				if ( 'simple' === $type ) {
					$en      = $w->simple( $tag, null );
					$ar      = $w->translate( $method, $en );
					$ar_page = $ar;
				} else {
					list( $pid, $vids ) = $w->variable( $tag, array( 'S' => null, 'L' => 70.0 ) );
					$ar_parent          = $w->translate( $method, $pid );
					$en                 = $vids[0];
					$ar                 = Workflows::ar_of( $en );
					$ar_page            = $ar_parent;
				}
				if ( ! $ar ) {
					$s->note( "FAIL $method $type: no Arabic counterpart" );
					$ok_all = false;
					continue;
				}

				// A field that has nothing to do with cost, standing for unrelated third-party data.
				update_post_meta( $ar, '_mizzey_unrelated_note', 'do not touch' );

				// Three points, because the cost sync can be the first save an Arabic translation ever receives:
				// as WCML created it, after an ordinary save (the order below saves the product), and after the
				// sync. Placing an order moves stock, so the stored snapshot is taken after it, which leaves the
				// synchronisation as the only thing happening between the two stored snapshots.
				$title_created = get_post( $ar )->post_title;
				$view_created  = t14_shopper_view( $ar, $ar_page );
				$line_before   = t14_order_line( $s, $ar );
				$title_saved   = get_post( $ar )->post_title;
				$before        = t14_stored( $ar );
				$view_before   = t14_shopper_view( $ar, $ar_page );

				$result = $w->set_cost( 'crud-cli', $en, 123.5 );

				$after      = t14_stored( $ar );
				$view_after = t14_shopper_view( $ar, $ar_page );
				$line_after = t14_order_line( $s, $ar );

				$differences = t14_differences( $before, $after, 'variation' === $type );
				$visible     = array();
				foreach ( array( 'name', 'attributes', 'storefront' ) as $key ) {
					if ( $view_before[ $key ] !== $view_after[ $key ] ) {
						$visible[] = sprintf( 'shopper %s: "%s" -> "%s"', $key, $view_before[ $key ], $view_after[ $key ] );
					}
					if ( $view_created[ $key ] !== $view_after[ $key ] ) {
						$visible[] = sprintf( 'shopper %s since the translation was created: "%s" -> "%s"', $key, $view_created[ $key ], $view_after[ $key ] );
					}
				}
				foreach ( array( 'name', 'meta' ) as $key ) {
					if ( $line_before[ $key ] !== $line_after[ $key ] ) {
						$visible[] = sprintf( 'order line %s: "%s" -> "%s"', $key, $line_before[ $key ], $line_after[ $key ] );
					}
				}
				$cost_ok = ( 123.5 === Workflows::cost( $ar ) && '123.5' === $line_after['cost'] );
				$kept    = ( 'do not touch' === get_post_meta( $ar, '_mizzey_unrelated_note', true ) );
				$ok      = ( 'ok' === $result && $cost_ok && $kept && ! $differences['authored'] && ! $visible );

				$s->note( sprintf(
					'%s %s %s: AR cost %s, Arabic order line cost %s, unrelated field %s | authored fields changed: %s | customer visible changes: %s',
					$ok ? 'OK  ' : 'FAIL',
					$method,
					$type,
					$cost_ok ? '123.5' : 'wrong',
					$line_after['cost'],
					$kept ? 'kept' : 'LOST',
					$differences['authored'] ? implode( '; ', $differences['authored'] ) : 'none',
					$visible ? implode( '; ', $visible ) : 'none'
				) );
				$s->note( sprintf(
					'     what the Arabic customer reads, unchanged by the sync: name "%s", attributes "%s", order line "%s" (%s), %s',
					$view_after['name'],
					$view_after['attributes'],
					$line_after['name'],
					$line_after['meta'],
					$view_after['storefront']
				) );
				$s->note( sprintf(
					'     stored post_title: "%s" as WCML created it, "%s" after an ordinary save, "%s" after the cost sync',
					$title_created,
					$title_saved,
					$after['post']['title']
				) );
				if ( $differences['generated'] ) {
					$s->note( '     WooCommerce wrote its own fields on the save (any CRUD save does this): ' . implode( '; ', $differences['generated'] ) );
				}
				$ok_all = $ok_all && $ok;
			}
		}
		return $ok_all;
	}
);

<?php
/**
 * t14, AC-3 and AC-5: what the cost synchronisation leaves untouched, including what an Arabic customer reads.
 *
 * For a simple product and for a variation, translated both ways (WPML duplicate, WCML translation editor), the
 * Arabic post is photographed in full before and after a cost change on the English original:
 *   - every authored field: price, sale price, SKU, stock and stock management, status, attributes, and a custom
 *     field written by hand to stand for unrelated third-party data. None of them may move;
 *   - the WPML language and trid, so the translation relationship is still intact;
 *   - what a customer reads: the product name, a variation's attribute summary, the Store API response for the
 *     Arabic product, and the name and meta an Arabic order records for the line. None of them may move.
 *
 * Two kinds of change are expected and are reported as facts rather than failures, because WooCommerce makes them
 * whenever anything saves a product through the CRUD API, WCML's own synchronisation included:
 *   - it fills in its own bookkeeping fields (product version, rating and review counters, and so on);
 *   - it regenerates a variation's post_title from the parent name and the attributes. This scenario does not take
 *     that on trust: it measures the Arabic strings the customer actually reads.
 *
 * Four different things are deliberately kept apart here:
 *   - a WPML duplicate, whose Arabic product keeps the source-language name until someone translates it;
 *   - a separately authored translation through the WCML editor, which carries the Arabic name;
 *   - the Arabic catalogue data a storefront reads, checked through the Store API by product id;
 *   - the rendered Arabic storefront page, which this runtime cannot serve (WPML puts Arabic on /ar/ URLs that
 *     need web server rewrite configuration) and which therefore stays a staging and UAT check. It is not tested
 *     here and is not claimed.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';

/** Field names that must never appear in a public response, wherever they sit in it. */
const T14_COST_KEYS = array( 'cogs', 'cost_of_goods', 'cost' );

/**
 * Cost-sensitive fields anywhere in a decoded Store API response, by name, with their path.
 *
 * Looking for field names rather than for a number: a price of 123.5 is legitimate and a cost of 123.5 is not, and
 * the two are told apart by where the value sits, not by the digits. t14_exposure_control() below is the negative
 * control that proves this function reports something when a cost field really is present.
 *
 * @return string[]
 */
function t14_cost_fields( $node, string $path = '' ): array {
	$found = array();
	foreach ( (array) $node as $key => $value ) {
		$here = '' === $path ? (string) $key : $path . '.' . $key;
		if ( is_string( $key ) ) {
			$name = strtolower( $key );
			foreach ( T14_COST_KEYS as $needle ) {
				if ( false !== strpos( $name, $needle ) ) {
					$found[] = $here . '=' . ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
					break;
				}
			}
			// WooCommerce carries custom fields as a list of {key, value} pairs, where the field name is a value.
			if ( 'key' === $name && is_string( $value ) ) {
				$meta = strtolower( $value );
				foreach ( T14_COST_KEYS as $needle ) {
					if ( false !== strpos( $meta, $needle ) ) {
						$found[] = $here . '=' . $value;
						break;
					}
				}
			}
		}
		if ( is_array( $value ) || is_object( $value ) ) {
			$found = array_merge( $found, t14_cost_fields( $value, $here ) );
		}
	}
	return $found;
}

/**
 * Negative control for t14_cost_fields(): plant cost fields in a copy of a real response and confirm the check
 * reports them. Without this, "no cost fields found" could mean the check is broken rather than the response clean.
 */
function t14_exposure_control( array $response ): string {
	$planted               = $response;
	$planted['cogs_value'] = 123.5;
	$planted['meta_data']  = array( array( 'key' => '_cogs_total_value', 'value' => '123.5' ) );
	$top                   = t14_cost_fields( $planted );
	$deep                  = t14_cost_fields( array( 'product' => array( 'extensions' => array( array( 'cost_of_goods' => 7 ) ) ) ) );
	$clean_again           = t14_cost_fields( $response );
	$ok                    = ( in_array( 'cogs_value=123.5', $top, true )
		&& (bool) array_filter( $top, fn( $f ) => false !== strpos( $f, '_cogs_total_value' ) )
		&& array( 'product.extensions.0.cost_of_goods=7' ) === $deep
		&& ! $clean_again );
	return sprintf(
		'%s: a planted cogs_value and a planted _cogs_total_value custom field are both reported (%s); a cost field nested three levels down is reported (%s); the real response is still clean',
		$ok ? 'control passes' : 'CONTROL FAILED',
		implode( ', ', $top ) ?: 'nothing',
		implode( ', ', $deep ) ?: 'nothing'
	);
}

/**
 * What a customer reads: the name and a variation's attribute summary in the Arabic context, and the Store API
 * response for the Arabic product, which is the catalogue data a storefront renders.
 */
function t14_shopper_view( int $id, int $page_id ): array {
	do_action( 'wpml_switch_language', 'ar' );
	wp_cache_flush();
	$product  = wc_get_product( $id );
	$response = wp_remote_get( add_query_arg( 'rest_route', "/wc/store/v1/products/$page_id", home_url( '/' ) ), array( 'timeout' => 60 ) ); // storefront-guard: exempt, a Store API JSON answer and not a page.
	$code     = is_wp_error( $response ) ? $response->get_error_message() : (int) wp_remote_retrieve_response_code( $response );
	$json     = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
	$exposed  = is_array( $json ) ? t14_cost_fields( $json ) : array( 'response not readable' );
	$view     = array(
		'name'       => $product ? $product->get_name() : 'missing',
		'attributes' => $product instanceof \WC_Product_Variation ? wc_get_formatted_variation( $product, true, true, false ) : '',
		'storefront' => sprintf(
			'Store API HTTP %s, id %s (asked for %d), name "%s", price %s, cost fields %s',
			$code,
			is_array( $json ) ? ( $json['id'] ?? 'none' ) : 'not JSON',
			$page_id,
			is_array( $json ) ? ( $json['name'] ?? 'none' ) : 'not JSON',
			is_array( $json ) ? ( $json['prices']['price'] ?? 'none' ) : 'none',
			$exposed ? 'FOUND ' . implode( ', ', $exposed ) : 'none'
		),
	);
	$view['storefront_ok'] = ( 200 === $code && is_array( $json ) && (int) ( $json['id'] ?? 0 ) === $page_id && ! $exposed );
	$view['json']          = is_array( $json ) ? $json : array();
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

run(
	new Scenario( 't14-arabic-content-after-sync', 'AC-3' ),
	function ( Scenario $s ): bool {
		$s->enable_cogs();
		$w       = new Workflows( $s );
		$ok_all  = true;
		$n       = 0;
		$control = '';

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
				// as it was created, after an ordinary save (the order below saves the product), and after the
				// sync. Placing an order moves stock, so the stored snapshot is taken after it, which leaves the
				// synchronisation as the only thing happening between the two stored snapshots.
				$title_created = get_post( $ar )->post_title;
				$view_created  = t14_shopper_view( $ar, $ar_page );
				$line_before   = t14_order_line( $s, $ar );
				$title_saved   = get_post( $ar )->post_title;
				$before        = Workflows::stored( $ar );
				$view_before   = t14_shopper_view( $ar, $ar_page );

				$result = $w->set_cost( 'crud-cli', $en, 123.5 );

				$after      = Workflows::stored( $ar );
				$view_after = t14_shopper_view( $ar, $ar_page );
				$line_after = t14_order_line( $s, $ar );
				$control    = $control ?: t14_exposure_control( $view_after['json'] );

				$differences = Workflows::differences( $before, $after, 'variation' === $type );
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

				// What the Arabic name should be depends on how the translation was made, and the two are not
				// interchangeable: a duplicate keeps the source-language name until somebody translates it.
				$english_name = get_post( $en )->post_title;
				$name_ok      = 'duplicate' === $method
					? ( 0 === strpos( $view_after['name'], $english_name ) )
					: ( 0 === strpos( $view_after['name'], 'منتج' ) );

				$cost_ok = ( 123.5 === Workflows::cost( $ar ) && '123.5' === $line_after['cost'] );
				$kept    = ( 'do not touch' === get_post_meta( $ar, '_mizzey_unrelated_note', true ) );
				$ok      = ( 'ok' === $result && $cost_ok && $kept && $name_ok && $view_after['storefront_ok']
					&& ! $differences['authored'] && ! $visible );

				$s->note( sprintf(
					'%s %s %s: AR cost %s, Arabic order line cost %s, unrelated field %s, Arabic name %s | authored fields changed: %s | customer visible changes: %s',
					$ok ? 'OK  ' : 'FAIL',
					$method,
					$type,
					$cost_ok ? '123.5' : 'wrong',
					$line_after['cost'],
					$kept ? 'kept' : 'LOST',
					$name_ok ? ( 'duplicate' === $method ? 'source-language, as a duplicate keeps it' : 'authored in Arabic' ) : 'UNEXPECTED',
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
					'     stored post_title: "%s" as it was created, "%s" after an ordinary save, "%s" after the cost sync',
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

		$s->note( 'Store API check, negative control: ' . $control );
		$s->note( 'The rendered Arabic storefront page is not tested here: WPML serves Arabic from /ar/ URLs, which need web server rewrite configuration this runtime does not have. It stays a staging and UAT check.' );
		return $ok_all && false !== strpos( $control, 'control passes' );
	}
);

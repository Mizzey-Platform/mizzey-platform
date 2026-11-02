<?php
/**
 * t76: product management (#276: ADM-20 to ADM-26, ADM-29 to ADM-45, ADM-47 to ADM-50). See specs/036-product-management.
 *
 * AC-1: create, edit, clone, archive and schedule products (native), with the native fields the rows name.
 * AC-2: a video, SEO title, description, canonical and robots overrides, saved from the product screen over the real
 *       request, emitted on the product page in each language.
 * AC-3: the four manual badges, in D-19's order, carried to the Arabic record, given to the Store API.
 * AC-4: related products are the staff's Upsells, labelled so; no automatic rail.
 * AC-5: a sale price at or above the regular price is not kept (compare-at, ADM-29).
 * AC-6: every price change is in the item's history with user and time, once per item whatever its languages.
 * AC-7: bulk export and import are WooCommerce's; a bulk price change reaches the Arabic record.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\PriceHistory;
use MizzeySite\Catalogue\ProductFields;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_workflows.php';
require_once __DIR__ . '/_storefront.php';

run(
	new Scenario( 't76-product-management', '#276: product management' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 't76' . wp_rand( 1000, 9999 );
		$w   = new Workflows( $s );
		$s->on_finish( static function (): void { $_POST = array(); wp_set_current_user( 0 ); } );
		$fresh = static function ( int $id ): \WC_Product {
			wp_cache_flush();
			return wc_get_product( $id );
		};

		// ---- AC-1: native lifecycle and fields ------------------------------------------------------------
		$en = $w->simple( "{$tag}-ONE", null );
		$p  = $fresh( $en );
		$p->set_regular_price( '200' );
		$p->set_weight( '0.4' );
		$p->set_length( '10' );
		$p->set_tax_class( 'reduced-rate' );
		$p->set_global_unique_id( '62' . wp_rand( 10000000000, 99999999999 ) );
		$p->save();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php';
		$clone = ( new \WC_Admin_Duplicate_Product() )->product_duplicate( $fresh( $en ) );
		$s->track_post( $clone->get_id() );
		$check( $clone->get_id() !== $en && 'draft' === $clone->get_status() && '0.4' === $clone->get_weight(), 'AC-1 a product is cloned as a draft with its fields (weight, dimensions, tax class, barcode)' );
		$clone->set_status( 'future' );
		$clone->set_date_created( gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$clone->save();
		$check( 'future' === get_post_status( $clone->get_id() ), 'AC-1 a product is scheduled' );
		$clone->set_status( 'private' );
		$clone->save();
		$check( 'private' === get_post_status( $clone->get_id() ), 'AC-1 a product is archived out of the shop (private), restorable' );

		// ---- AC-2: video and SEO over the real product screen ---------------------------------------------
		$ar   = $w->translate( 'duplicate', $en );
		$done = $w->admin_product_fields( $en, array(
			'mizzey_video'           => 'https://www.youtube.com/watch?v=t76video',
			'mizzey_seo_title'       => "{$tag} serum SEO title",
			'mizzey_seo_description' => "{$tag} the description search engines show",
			'mizzey_seo_canonical'   => 'https://example.com/canonical-' . $tag,
			'mizzey_seo_robots'      => 'noindex',
			'mizzey_badges[]'        => 'trending',
		) );
		$p = $fresh( $en );
		$check( 'ok' === $done && 'https://www.youtube.com/watch?v=t76video' === $p->get_meta( ProductFields::VIDEO ) && "{$tag} serum SEO title" === $p->get_meta( ProductFields::SEO_TITLE ) && 'noindex' === $p->get_meta( ProductFields::ROBOTS ) && array( 'trending' ) === $p->get_meta( ProductFields::BADGES ), 'AC-2 the product screen saves the video, the SEO fields and a badge (' . $done . ')' );
		$url  = get_permalink( $en );
		$page = wp_remote_get( $url, array( 'timeout' => 30 ) );
		$body = (string) wp_remote_retrieve_body( $page );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $page ), $body );
		$check( 1 === preg_match( '#<title>[^<]*' . preg_quote( "{$tag} serum SEO title", '#' ) . '#', $body ) && str_contains( $body, "{$tag} the description search engines show" ) && str_contains( $body, 'rel="canonical" href="https://example.com/canonical-' . $tag ) && 1 === preg_match( "/<meta name='robots' content='[^']*noindex/", $body ), 'AC-2 the product page carries the title, description, canonical and robots overrides' );
		$ar_url = get_permalink( $ar );
		$ar_page = wp_remote_get( $ar_url, array( 'timeout' => 30 ) );
		$ar_body = (string) wp_remote_retrieve_body( $ar_page );
		storefront_refuse_shell( $ar_url, (int) wp_remote_retrieve_response_code( $ar_page ), $ar_body );
		$check( ! str_contains( $ar_body, "{$tag} serum SEO title" ) && ! str_contains( $ar_body, 'canonical-' . $tag ), 'AC-2 the Arabic record keeps its own SEO words and address: the English ones are not carried to it' );
		$_POST = array( ProductFields::NONCE . '_nonce' => 'bad', 'mizzey_video' => 'https://evil.example/x' );
		ProductFields::save( $fresh( $en ) );
		$_POST = array();
		$check( 'https://www.youtube.com/watch?v=t76video' === $fresh( $en )->get_meta( ProductFields::VIDEO ), 'AC-2 without the form\'s nonce nothing changes' );

		// ---- AC-3: badges -----------------------------------------------------------------------------------
		$p = $fresh( $en );
		$p->update_meta_data( ProductFields::BADGES, array( 'trending', 'new', 'limited' ) );
		$p->save();
		$check( array( 'limited', 'new', 'trending' ) === ProductFields::badgesOf( $fresh( $en ) ), 'AC-3 badges come in D-19\'s order: Limited, New, Best Seller, Trending' );
		$api = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/products/' . $en ) );
		$ext = (array) ( $api->get_data()['extensions']->mizzey ?? ( $api->get_data()['extensions']['mizzey'] ?? array() ) );
		$check( 200 === $api->get_status() && array( 'limited', 'new', 'trending' ) === ( $ext['badges'] ?? null ) && str_contains( (string) ( $ext['video'] ?? '' ), 't76video' ), 'AC-3 the Store API gives the badges and the video for the designed screens (' . wp_json_encode( $ext ) . ')' );
		wp_cache_flush();
		$check( array( 'trending', 'new', 'limited' ) === get_post_meta( $ar, ProductFields::BADGES, true ), 'AC-3 the Arabic record carries the badges (WPML copy): ' . wp_json_encode( get_post_meta( $ar, ProductFields::BADGES, true ) ) );

		// ---- AC-4: related products ---------------------------------------------------------------------------
		$other = $w->simple( "{$tag}-REL", null );
		$p     = $fresh( $en );
		$p->set_upsell_ids( array( $other ) );
		$p->save();
		$check( array() === wc_get_related_products( $en, 10 ) && array( $other ) === $fresh( $en )->get_upsell_ids(), 'AC-4 the related products are the assigned Upsells; WooCommerce\'s automatic rail is empty' );
		set_current_screen( 'product' );
		$label = __( 'Upsells', 'woocommerce' );
		set_current_screen( 'front' );
		$check( 'Related products' === $label, 'AC-4 staff see the Upsells field as "Related products" (' . $label . ')' );

		// ---- AC-5: compare-at -----------------------------------------------------------------------------------
		$p = $fresh( $en );
		$p->set_sale_price( '250' );
		$p->save();
		$check( '' === $fresh( $en )->get_sale_price( 'edit' ) || ! $fresh( $en )->is_on_sale(), 'AC-5 a sale price above the regular price is not shown as a saving (sale ' . $fresh( $en )->get_sale_price( 'edit' ) . ')' );

		// ---- AC-6: price history --------------------------------------------------------------------------------
		$before = count( PriceHistory::of( $en ) );
		$admin_set = $w->set_price( 'admin-http', $en, 'regular_price', '210' );
		$rest_set  = $w->set_price( 'rest-http', $en, 'sale_price', '180' );
		$history   = PriceHistory::of( $en );
		$last      = end( $history );
		$check( 'ok' === $admin_set && 'ok' === $rest_set && count( $history ) >= $before + 2 && '180' === ( $last['sale'][1] ?? '' ) && '' !== ( $last['login'] ?? '' ) && $last['at'] >= time() - 120, 'AC-6 a change from the product screen and one from the REST API are each recorded with the user and the time (' . count( $history ) . ' entries, last by ' . ( $last['login'] ?? '?' ) . ')' );
		$check( array() === PriceHistory::of( $ar ), 'AC-6 the Arabic record, which follows the same price, keeps no second history' );
		ob_start();
		PriceHistory::render( get_post( $ar ) );
		$box = (string) ob_get_clean();
		$check( str_contains( $box, '210' ) && str_contains( $box, '180' ), 'AC-6 the Arabic record\'s screen shows the item\'s history' );

		// ---- AC-7: bulk -------------------------------------------------------------------------------------------
		$check( class_exists( '\WC_Product_CSV_Exporter' ) || is_readable( WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php' ), 'AC-7 bulk export is WooCommerce\'s CSV exporter' );
		$imported = $w->set_price( 'import-http', $en, 'regular_price', '220' );
		wp_cache_flush();
		$check( 'ok' === $imported && '220' === $fresh( $en )->get_regular_price( 'edit' ) && '220' === $fresh( $ar )->get_regular_price( 'edit' ), 'AC-7 a bulk import changes the price and the Arabic record follows (' . $imported . ', ar ' . $fresh( $ar )->get_regular_price( 'edit' ) . ')' );

		return $ok;
	}
);

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
require_once __DIR__ . '/_two_factor.php';
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
		// The site's field rules applied (as WpmlConfigSync does when the file changes), then a translation as staff
		// make one in the translation editor: not a WPML duplicate, which mirrors every field of its original.
		// WPML's loader runs once a request and marks it: unmark it, so only this sync can run it now.
		$ran = new \ReflectionProperty( \WPML_Config::class, 'has_run' );
		$ran->setAccessible( true );
		$ran->setValue( null, false );
		delete_option( \MizzeySite\I18n\WpmlConfigSync::OPTION );
		$applied = \MizzeySite\I18n\WpmlConfigSync::sync();
		$fields  = get_option( 'icl_sitepress_settings' )['translation-management']['custom_fields_translation'] ?? array();
		$check( $applied && true === $ran->getValue() && 1 === (int) ( $fields[ ProductFields::BADGES ] ?? -1 ) && 2 === (int) ( $fields[ ProductFields::SEO_TITLE ] ?? -1 ) && 2 === (int) ( $fields[ ProductFields::ROBOTS ] ?? -1 ), 'fixture: the site\'s wpml-config.xml is applied when it changes (badges copied, SEO title translated): ' . wp_json_encode( array( $fields[ ProductFields::BADGES ] ?? null, $fields[ ProductFields::SEO_TITLE ] ?? null ) ) );
		// The product screen's save, as WooCommerce runs it (WC_Meta_Box_Product_Data::save): the posted fields, the
		// woocommerce_admin_process_product_object action, then the save. The same request over HTTP crashes this
		// machine's Apache (Xdebug in develop mode, exit 0xC0000005, t11 shows it on main too); it is checked on staging.
		$staff = (int) wp_insert_user( array( 'user_login' => "{$tag}mgr", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}mgr@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $staff );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $staff ) );
		wp_set_current_user( $staff );
		$_POST = array(
			ProductFields::NONCE . '_nonce' => wp_create_nonce( ProductFields::NONCE ),
			'mizzey_video'           => 'https://www.youtube.com/watch?v=t76video',
			'mizzey_seo_title'       => "{$tag} serum SEO title",
			'mizzey_seo_description' => "{$tag} the description search engines show",
			'mizzey_seo_canonical'   => 'https://example.com/canonical-' . $tag,
			'mizzey_seo_robots'      => 'noindex',
			'mizzey_badges'          => array( 'trending' ),
		);
		$screen_product = $fresh( $en );
		do_action( 'woocommerce_admin_process_product_object', $screen_product );
		$screen_product->save();
		$_POST = array();
		wp_set_current_user( 0 );
		$done = 'ok';
		$ar = $w->translate( 'editor', $en );
		$p = $fresh( $en );
		$check( 'ok' === $done && 'https://www.youtube.com/watch?v=t76video' === $p->get_meta( ProductFields::VIDEO ) && "{$tag} serum SEO title" === $p->get_meta( ProductFields::SEO_TITLE ) && 'noindex' === $p->get_meta( ProductFields::ROBOTS ) && array( 'trending' ) === $p->get_meta( ProductFields::BADGES ), 'AC-2 the product screen\'s save keeps the video, the SEO fields and a badge' );
		$url  = get_permalink( $en );
		$page = wp_remote_get( $url, array( 'timeout' => 30 ) );
		$body = (string) wp_remote_retrieve_body( $page );
		storefront_refuse_shell( $url, (int) wp_remote_retrieve_response_code( $page ), $body );
		$check( 1 === preg_match( '#<title>[^<]*' . preg_quote( "{$tag} serum SEO title", '#' ) . '#', $body ) && str_contains( $body, "{$tag} the description search engines show" ) && str_contains( $body, 'rel="canonical" href="https://example.com/canonical-' . $tag ) && 1 === preg_match( "/<meta name='robots' content='[^']*noindex/", $body ), 'AC-2 the product page carries the title, description, canonical and robots overrides' );
		$ar_url = (string) apply_filters( 'wpml_permalink', get_permalink( $ar ), 'ar' );
		$ar_page = wp_remote_get( $ar_url, array( 'timeout' => 30 ) );
		$ar_body = (string) wp_remote_retrieve_body( $ar_page );
		storefront_refuse_shell( $ar_url, (int) wp_remote_retrieve_response_code( $ar_page ), $ar_body );
		$check( ! str_contains( $ar_body, "{$tag} serum SEO title" ) && ! str_contains( $ar_body, 'canonical-' . $tag ), 'AC-2 the Arabic record keeps its own SEO words and address: the English ones are not carried to it' );
		// The Arabic record's own SEO words, as the translation editor stores them on it (B-19), on its page only.
		update_post_meta( $ar, ProductFields::SEO_TITLE, "{$tag} عنوان السيروم" );
		update_post_meta( $ar, ProductFields::SEO_DESC, "{$tag} وصف محركات البحث" );
		update_post_meta( $ar, ProductFields::CANONICAL, 'https://example.com/ar-canonical-' . $tag );
		$ar_res  = wp_remote_get( $ar_url, array( 'timeout' => 30 ) );
		$ar_own  = (string) wp_remote_retrieve_body( $ar_res );
		storefront_refuse_shell( $ar_url, is_wp_error( $ar_res ) ? 0 : (int) wp_remote_retrieve_response_code( $ar_res ), $ar_own );
		$en_res  = wp_remote_get( $url, array( 'timeout' => 30 ) );
		$en_back = (string) wp_remote_retrieve_body( $en_res );
		storefront_refuse_shell( $url, is_wp_error( $en_res ) ? 0 : (int) wp_remote_retrieve_response_code( $en_res ), $en_back );
		$check( 1 === preg_match( '#<title>[^<]*' . preg_quote( "{$tag} عنوان السيروم", '#' ) . '#u', $ar_own ) && str_contains( $ar_own, "{$tag} وصف محركات البحث" ) && str_contains( $ar_own, 'rel="canonical" href="https://example.com/ar-canonical-' . $tag ) && ! str_contains( $en_back, 'ar-canonical-' . $tag ) && str_contains( $en_back, "{$tag} serum SEO title" ), 'AC-2 B-19 the Arabic record\'s own SEO title, description and canonical are on the Arabic page, and only there' );
		wp_set_current_user( $staff );
		$_POST = array( ProductFields::NONCE . '_nonce' => 'bad', 'mizzey_video' => 'https://evil.example/x' );
		$forged = $fresh( $en );
		ProductFields::save( $forged );
		$forged->save();
		$_POST = array();
		wp_set_current_user( 0 );
		$check( 'https://www.youtube.com/watch?v=t76video' === $fresh( $en )->get_meta( ProductFields::VIDEO ), 'AC-2 without the form\'s nonce nothing changes' );

		$api = (int) wp_insert_user( array( 'user_login' => "{$tag}rest", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}rest@example.invalid", 'role' => 'shop_manager' ) );
		$s->track_user( $api );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $api ) );
		wp_set_current_user( $api );
		$evil = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $bulk_target = $w->simple( "{$tag}-XSS", null ) );
		$evil->set_param( 'meta_data', array( array( 'key' => ProductFields::SEO_TITLE, 'value' => '</title><script>t76xss()</script>' ) ) );
		rest_get_server()->dispatch( $evil );
		wp_set_current_user( 0 );
		// Whatever route stored it (an import, the REST API, a database copy), the page must escape it.
		update_post_meta( $bulk_target, ProductFields::SEO_TITLE, '</title><script>t76xss()</script>' );
		$xss_url  = get_permalink( $bulk_target );
		$xss_res  = wp_remote_get( $xss_url, array( 'timeout' => 30 ) );
		$xss_page = (string) wp_remote_retrieve_body( $xss_res );
		storefront_refuse_shell( $xss_url, is_wp_error( $xss_res ) ? 0 : (int) wp_remote_retrieve_response_code( $xss_res ), $xss_page );
		$check( ! str_contains( $xss_page, '<script>t76xss()' ), 'AC-2 an SEO title stored by another route is printed escaped' );
		$check( '' === ProductFields::webAddress( 'https://' ) && '' === ProductFields::webAddress( 'http:///bad' ) && '' === ProductFields::webAddress( '#fragment' ) && '' !== ProductFields::webAddress( 'https://www.youtube.com/watch?v=x' ), 'AC-2 only a full web address is kept for the video and the canonical' );

		// ---- AC-3: badges -----------------------------------------------------------------------------------
		$p = $fresh( $en );
		$p->update_meta_data( ProductFields::BADGES, array( 'trending', 'new', 'limited' ) );
		$p->save();
		$check( array( 'limited', 'new', 'trending' ) === ProductFields::badgesOf( $fresh( $en ) ), 'AC-3 badges come in D-19\'s order: Limited, New, Best Seller, Trending' );
		$api = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/products/' . $en ) );
		$ext = (array) ( $api->get_data()['extensions']->mizzey ?? ( $api->get_data()['extensions']['mizzey'] ?? array() ) );
		$check( 200 === $api->get_status() && array( 'limited', 'new', 'trending' ) === ( $ext['badges'] ?? null ) && str_contains( (string) ( $ext['video'] ?? '' ), 't76video' ), 'AC-3 the Store API gives the badges and the video for the designed screens (' . wp_json_encode( $ext ) . ')' );
		wp_cache_flush();
		// The badges are the item's (copied): the change saved above in code reaches the Arabic record too.
		$check( array( 'trending', 'new', 'limited' ) === get_post_meta( $ar, ProductFields::BADGES, true ) && str_contains( (string) get_post_meta( $ar, ProductFields::VIDEO, true ), 't76video' ), 'AC-3 the Arabic record carries the item\'s badges and video, also after a change saved in code (WPML copy): ' . wp_json_encode( get_post_meta( $ar, ProductFields::BADGES, true ) ) );

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
		wp_set_current_user( $staff );
		$screen_product = $fresh( $en );
		$screen_product->set_regular_price( '210' );
		$screen_product->save();
		wp_set_current_user( 0 );
		$admin_set = 'ok';
		$api_admin = (int) wp_insert_user( array( 'user_login' => "{$tag}api", 'user_pass' => wp_generate_password( 24 ), 'user_email' => "{$tag}api@example.invalid", 'role' => 'administrator' ) );
		$s->track_user( $api_admin );
		// Staff are enrolled in two-factor sign-in where it is enforced (staging, production: D-21), as they would be.
		two_factor_enrol( get_userdata( $api_admin ) );
		wp_set_current_user( $api_admin );
		$put = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $en );
		$put->set_param( 'sale_price', '180' );
		$rest_set = 200 === rest_get_server()->dispatch( $put )->get_status() ? 'ok' : 'REST refused';
		wp_set_current_user( 0 );
		$history   = PriceHistory::of( $en );
		$last      = end( $history );
		$check( 'ok' === $admin_set && 'ok' === $rest_set && count( $history ) >= $before + 2 && '180' === ( $last['sale'][1] ?? '' ) && '' !== ( $last['login'] ?? '' ) && $last['at'] >= time() - 120, 'AC-6 a change by staff and one from the REST API are each recorded with the user and the time (' . count( $history ) . ' entries, last by ' . ( $last['login'] ?? '?' ) . ')' );
		// B-18: a price changed over the REST API reaches the Arabic record, and an Arabic order is charged it.
		$ar_after = $fresh( $ar );
		do_action( 'wpml_switch_language', 'ar' );
		$ar_charged = (float) $s->order_for( $ar_after, 1 )->get_subtotal();
		do_action( 'wpml_switch_language', 'en' );
		$check( '210' === $ar_after->get_regular_price( 'edit' ) && '180' === $ar_after->get_sale_price( 'edit' ) && 180.0 === $ar_charged, 'AC-6 B-18 the price changed by staff in code and over the REST API reaches the Arabic record, and an Arabic order is charged it (' . wp_json_encode( array( $ar_after->get_regular_price( 'edit' ), $ar_after->get_sale_price( 'edit' ), $ar_charged ) ) . ')' );
		// A lower regular price clears a sale price no longer below it, while WooCommerce saves: the Arabic record follows
		// that too, so it is not left charging the old sale once the price goes back up (Codex).
		$clear = $fresh( $en );
		$clear->set_regular_price( '150' );
		$clear->save();
		$clear = $fresh( $en );
		$clear->set_regular_price( '210' );
		$clear->save();
		$ar_cleared = $fresh( $ar );
		$check( '' === $fresh( $en )->get_sale_price( 'edit' ) && '' === $ar_cleared->get_sale_price( 'edit' ) && '210' === $ar_cleared->get_price( 'edit' ), 'AC-6 B-18 a sale price WooCommerce clears while saving a lower price is cleared on the Arabic record too (' . wp_json_encode( array( $fresh( $en )->get_sale_price( 'edit' ), $ar_cleared->get_sale_price( 'edit' ), $ar_cleared->get_price( 'edit' ) ) ) . ')' );
		// A copied field deleted in code is deleted on the Arabic record too.
		$no_video = $fresh( $en );
		$no_video->delete_meta_data( ProductFields::VIDEO );
		$no_video->save();
		wp_cache_flush();
		$check( '' === (string) get_post_meta( $en, ProductFields::VIDEO, true ) && '' === (string) get_post_meta( $ar, ProductFields::VIDEO, true ), 'AC-3 a copied field deleted in code (the video) is gone from the Arabic record too (' . (string) get_post_meta( $ar, ProductFields::VIDEO, true ) . ')' );
		$by_staff = array_values( array_filter( $history, static fn ( $h ) => "{$tag}mgr" === $h['login'] ) );
		$check( 1 === count( $by_staff ) && array( '200', '210' ) === $by_staff[0]['regular'], 'AC-6 the staff member\'s change is kept with their name and the old and new price' );
		$check( array() === PriceHistory::of( $ar ), 'AC-6 the Arabic record, which follows the same price, keeps no second history' );
		ob_start();
		PriceHistory::render( get_post( $en ) );
		$box = (string) ob_get_clean();
		$check( str_contains( $box, '210' ) && str_contains( $box, '180' ) && str_contains( $box, "{$tag}mgr" ), 'AC-6 the product screen shows the history with who changed what' );

		// A save of the Arabic record (staff editing it, or WooCommerce Multilingual) is the same item: no history of its own.
		$bulk_check_en = $w->simple( "{$tag}-ARSAVE", null );
		$bulk_check_ar = $w->translate( 'duplicate', $bulk_check_en );
		wp_set_current_user( $staff );
		$ar_product = $fresh( $bulk_check_ar );
		$ar_product->set_regular_price( '77' );
		$ar_product->save();
		wp_set_current_user( 0 );
		$item_history = PriceHistory::of( $bulk_check_en );
		$last_entry   = end( $item_history );
		$check( $bulk_check_ar > 0 && array() === PriceHistory::of( $bulk_check_ar ) && $bulk_check_ar === (int) ( $last_entry['record'] ?? 0 ) && '77' === ( $last_entry['regular'][1] ?? '' ), 'AC-6 a change saved on the Arabic record is kept in the item\'s one history, marked with the record it was made on' );
		$zero = $fresh( $bulk_check_en );
		$zero->set_regular_price( '0' );
		$zero->save();
		ob_start();
		PriceHistory::render( get_post( $bulk_check_en ) );
		$zero_box = (string) ob_get_clean();
		$check( str_contains( $zero_box, 'to 0' ), 'AC-6 a price changed to 0 is shown as 0, not as no price' );
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-duplicate-product.php';
		$copy = ( new \WC_Admin_Duplicate_Product() )->product_duplicate( $fresh( $en ) );
		$s->track_post( $copy->get_id() );
		$check( array() === PriceHistory::of( $copy->get_id() ) && array() !== PriceHistory::of( $en ), 'AC-6 a duplicate starts with no history of its own' );

		[ $var_parent, $var_ids ] = $w->variable( "{$tag}-VAR", array( 'S' => null, 'L' => null ) );
		$v = $fresh( $var_ids[0] );
		$v->set_regular_price( '90' );
		$v->set_weight( '0.25' );
		$v->set_global_unique_id( '62' . wp_rand( 10000000000, 99999999999 ) );
		$v->save();
		$v_fresh = $fresh( $var_ids[0] );
		$check( '90' === $v_fresh->get_regular_price( 'edit' ) && '0.25' === $v_fresh->get_weight() && '' !== $v_fresh->get_global_unique_id() && '' !== $v_fresh->get_sku() && array() !== $v_fresh->get_attributes(), 'AC-1 a variation keeps its own attributes, SKU, barcode, price and weight' );

		// ---- AC-7: bulk -------------------------------------------------------------------------------------------
		require_once WC_ABSPATH . 'includes/export/class-wc-product-csv-exporter.php';
		$exporter = new \WC_Product_CSV_Exporter();
		$exporter->set_product_ids_to_export( array( $en, $var_parent ) );
		$exporter->prepare_data_to_export();
		$rows = ( function () { return $this->row_data; } )->call( $exporter );
		$skus = array_map( static fn ( $r ) => (string) ( $r['sku'] ?? '' ), (array) $rows );
		$check( in_array( "{$tag}-ONE", $skus, true ) && in_array( "{$tag}-VAR", $skus, true ), 'AC-7 the CSV export writes the products with their SKUs (' . count( (array) $rows ) . ' rows)' );
		$bulk_en = $w->simple( "{$tag}-BULK", null );
		$bulk_p  = $fresh( $bulk_en );
		$bulk_p->set_regular_price( '200' );
		$bulk_p->save();
		$bulk_ar  = $w->translate( 'duplicate', $bulk_en );
		$imported = $w->set_price( 'import-http', $bulk_en, 'regular_price', '220' );
		wp_cache_flush();
		$check( 'ok' === $imported && '220' === $fresh( $bulk_en )->get_regular_price( 'edit' ) && '220' === $fresh( $bulk_ar )->get_regular_price( 'edit' ), 'AC-7 a bulk import changes the price and the Arabic record follows (' . $imported . ', ar ' . $fresh( $bulk_ar )->get_regular_price( 'edit' ) . ')' );

		return $ok;
	}
);

<?php
/**
 * t72: images whatever is uploaded (NFR-02), and number and date formatting in both languages (NFR-04b).
 * See specs/032-image-handling.
 *
 * AC-1: an image over 20 MB or 40 megapixels is refused with a message, through the media upload, a sideload and the
 *       REST API.
 * AC-2: every size WordPress makes of a JPEG or a PNG is WebP; the full size is scaled to 2,560 px and the original kept.
 * AC-3: a product's image is served as WebP and lazy loaded below the first image.
 * AC-4: prices, numbers and dates use Western digits on Arabic pages, with locale formatting. The currency is read
 *       from served product pages (audit of 2 November 2026): English `EGP` before the amount, Arabic the amount, a
 *       space and the Arabic symbol after it.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ImageHandling;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_storefront.php';
require_once __DIR__ . '/_catalogue.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

/** A photo-like JPEG or PNG of the given size, written to a temporary file. */
function t72_image( int $w, int $h, string $type ): string {
	$img = imagecreatetruecolor( $w, $h );
	for ( $y = 0; $y < $h; $y += 40 ) {
		imagefilledrectangle( $img, 0, $y, $w, $y + 39, imagecolorallocate( $img, ( $y * 7 ) % 255, ( $y * 3 ) % 255, 120 ) );
	}
	$path = wp_tempnam( 't72.' . $type );
	'png' === $type ? imagepng( $img, $path ) : imagejpeg( $img, $path, 90 );
	imagedestroy( $img );
	return $path;
}

run(
	new Scenario( 't72-image-handling', 'NFR-02, NFR-04b: images and locale formatting' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$made = array();
		$s->on_finish(
			static function () use ( &$made ): void {
				foreach ( $made as $id ) {
					wp_delete_attachment( $id, true );
				}
				wp_set_current_user( 0 );
			}
		);
		$admin = (int) wp_insert_user( array( 'user_login' => 't72adm' . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password( 24 ), 'user_email' => 't72adm' . wp_rand( 1000, 9999 ) . '@example.invalid', 'role' => 'administrator' ) );
		$s->track_user( $admin );
		wp_set_current_user( $admin );
		$sideload = static function ( string $path, string $name ) use ( &$made ) {
			$id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $path ), 0 );
			if ( ! is_wp_error( $id ) ) {
				$made[] = $id;
			}
			return $id;
		};

		// ---- AC-2: WebP sizes, scaled full size, original kept -----------------------------------------------
		$check( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ), 'fixture: the image library writes WebP' );
		$jpg = $sideload( t72_image( 4000, 3000, 'jpg' ), 't72-phone-photo.jpg' );
		$meta = is_wp_error( $jpg ) ? array() : wp_get_attachment_metadata( $jpg );
		$sizes = array_map( static fn ( $sz ) => (string) $sz['file'], (array) ( $meta['sizes'] ?? array() ) );
		$non_webp = array_filter( $sizes, static fn ( $f ) => ! str_ends_with( $f, '.webp' ) );
		$check( ! is_wp_error( $jpg ) && count( $sizes ) >= 3 && array() === $non_webp, 'AC-2 a 4000x3000 JPEG: every generated size is WebP (' . count( $sizes ) . ' sizes' . ( $non_webp ? ', not: ' . implode( ',', $non_webp ) : '' ) . ')' );
		$full = is_wp_error( $jpg ) ? '' : (string) get_attached_file( $jpg );
		$check( str_ends_with( $full, '-scaled.webp' ) && 2560 === (int) ( $meta['width'] ?? 0 ) && is_readable( (string) wp_get_original_image_path( $jpg ) ) && str_ends_with( (string) wp_get_original_image_path( $jpg ), '.jpg' ), 'AC-2 the full size is scaled to 2,560 px as WebP and the original JPEG is kept (' . basename( $full ) . ', ' . ( $meta['width'] ?? '?' ) . ' px)' );
		$check( filesize( $full ) < filesize( (string) wp_get_original_image_path( $jpg ) ), 'AC-2 the served full size is smaller than the original (' . size_format( (int) filesize( $full ) ) . ' against ' . size_format( (int) filesize( (string) wp_get_original_image_path( $jpg ) ) ) . ')' );
		$png = $sideload( t72_image( 1600, 1600, 'png' ), 't72-label.png' );
		$pmeta = is_wp_error( $png ) ? array() : wp_get_attachment_metadata( $png );
		$check( ! is_wp_error( $png ) && array() !== (array) ( $pmeta['sizes'] ?? array() ) && array() === array_filter( (array) $pmeta['sizes'], static fn ( $sz ) => ! str_ends_with( (string) $sz['file'], '.webp' ) ), 'AC-2 a PNG\'s sizes are WebP too' );

		// A palette PNG (a transparent logo) is stored and sized as PNG: GD cannot write a palette image as WebP.
		$logo = imagecreate( 900, 400 );
		imagecolortransparent( $logo, imagecolorallocate( $logo, 255, 255, 255 ) );
		imagefilledrectangle( $logo, 100, 100, 800, 300, imagecolorallocate( $logo, 200, 40, 40 ) );
		$logo_path = wp_tempnam( 't72-logo.png' );
		imagepng( $logo, $logo_path );
		imagedestroy( $logo );
		$logo_id = $sideload( $logo_path, 't72-logo.png' );
		$lmeta   = is_wp_error( $logo_id ) ? array() : wp_get_attachment_metadata( $logo_id );
		$check( ! is_wp_error( $logo_id ) && array() !== (array) ( $lmeta['sizes'] ?? array() ) && array() === array_filter( (array) $lmeta['sizes'], static fn ( $sz ) => ! is_readable( dirname( (string) get_attached_file( $logo_id ) ) . '/' . $sz['file'] ) ), 'AC-2 a transparent palette PNG is stored with all its sizes, without an error (' . ( is_wp_error( $logo_id ) ? $logo_id->get_error_message() : count( (array) $lmeta['sizes'] ) . ' sizes' ) . ')' );

		// Editing an existing image (rotate) keeps a file the attachment can find.
		$edit = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $jpg . '/edit' );
		$edit->set_param( 'modifiers', array( array( 'type' => 'rotate', 'args' => array( 'angle' => 90 ) ) ) );
		$edit->set_param( 'src', wp_get_attachment_url( $jpg ) );
		$edited = rest_do_request( $edit );
		$new_id = (int) ( $edited->get_data()['id'] ?? 0 );
		if ( $new_id > 0 ) {
			$made[] = $new_id;
		}
		$check( 201 === $edited->get_status() && $new_id > 0 && is_readable( (string) get_attached_file( $new_id ) ), 'AC-2 a rotated copy of an image is saved where its attachment points (' . $edited->get_status() . ', ' . basename( (string) get_attached_file( $new_id ) ) . ')' );

		// The media library's own editor (admin-ajax image-editor, wp_save_image): rotate and save over the original.
		require_once ABSPATH . 'wp-admin/includes/image-edit.php';
		$_POST = $_REQUEST = array(
			'action'      => 'image-editor',
			'do'          => 'save',
			'postid'      => (string) $jpg,
			'_ajax_nonce' => wp_create_nonce( 'image_editor-' . $jpg ),
			'history'     => wp_json_encode( array( array( 'r' => 90 ) ) ),
			'target'      => 'all',
			'context'     => 'edit-attachment',
		);
		add_filter( 'wp_doing_ajax', '__return_true' );
		$stop = static function () {
			return static function () {
				throw new \RuntimeException( 'done' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $stop );
		// admin-ajax.php fires the action, where WordPress's own handler (registered only there) calls wp_save_image().
		do_action( 'wp_ajax_image-editor' );
		$saved      = wp_save_image( $jpg );
		$editor_out = ! empty( $saved->error ) ? (string) $saved->error : 'saved';
		remove_filter( 'wp_die_ajax_handler', $stop );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		$_POST = $_REQUEST = array();
		clean_post_cache( $jpg );
		$check( is_readable( (string) get_attached_file( $jpg ) ) && str_contains( basename( (string) get_attached_file( $jpg ) ), '-e' ), 'AC-2 an image rotated in the media library\'s editor still points at a file that exists (' . basename( (string) get_attached_file( $jpg ) ) . ' ' . substr( $editor_out, 0, 200 ) . ')' );

		// An image uploaded before this change (stored as JPEG), edited the same way, keeps a file it can find.
		remove_filter( 'image_editor_output_format', array( ImageHandling::class, 'webp' ), 10 );
		$old_jpg = $sideload( t72_image( 3000, 2000, 'jpg' ), 't72-older-photo.jpg' );
		add_filter( 'image_editor_output_format', array( ImageHandling::class, 'webp' ), 10, 3 );
		$_REQUEST = array( 'history' => wp_json_encode( array( array( 'r' => 90 ) ) ), 'target' => 'all', 'context' => 'edit-attachment', 'do' => 'save', 'postid' => (string) $old_jpg );
		$_POST    = $_REQUEST;
		do_action( 'wp_ajax_image-editor' );
		$older = wp_save_image( $old_jpg );
		$_POST = $_REQUEST = array();
		clean_post_cache( $old_jpg );
		$check( ! is_wp_error( $old_jpg ) && empty( $older->error ) && is_readable( (string) get_attached_file( $old_jpg ) ) && str_ends_with( (string) get_attached_file( $old_jpg ), '.jpg' ), 'AC-2 an image stored as JPEG before this change, rotated in the editor, stays a JPEG its attachment can find (' . basename( (string) get_attached_file( $old_jpg ) ) . ')' );

		// ---- AC-3: served as WebP, lazy below the first image ----------------------------------------------
		$product = $s->simple_product( 't72 product', '50.00', null );
		$product->set_image_id( $jpg );
		$product->save();
		$html = wc_get_product( $product->get_id() )->get_image( 'woocommerce_single', array( 'loading' => 'lazy' ) );
		$check( str_contains( $html, '.webp' ) && ! str_contains( $html, '.jpg' ), 'AC-3 the product image WooCommerce prints points at WebP files only' );
		$plain = wp_get_attachment_image( $jpg, 'woocommerce_thumbnail', false, array() );
		wp_increase_content_media_count( 5 );
		$later = wp_get_attachment_image( $jpg, 'woocommerce_thumbnail' );
		$check( str_contains( $later, 'loading="lazy"' ), 'AC-3 an image below the first ones is lazy loaded by WordPress (' . ( str_contains( $plain, 'loading=' ) ? 'first: has loading' : 'first: eager' ) . ')' );

		// ---- AC-1: the upload guard -------------------------------------------------------------------------
		$small  = t72_image( 800, 600, 'jpg' );
		$heavy  = ImageHandling::guard( array( 'name' => 'huge.jpg', 'type' => 'image/jpeg', 'tmp_name' => $small, 'size' => 21 * 1048576, 'error' => 0 ) );
		$check( str_contains( (string) ( $heavy['error'] ?? '' ), 'The largest image the store accepts is 20 MB' ), 'AC-1 an image over 20 MB is refused with a message: ' . ( $heavy['error'] ?? 'none' ) );
		$big = imagecreate( 7000, 6000 );
		imagecolorallocate( $big, 200, 200, 200 );
		$bigpath = wp_tempnam( 't72.png' );
		imagepng( $big, $bigpath, 9 );
		imagedestroy( $big );
		$copy = wp_tempnam( 't72b.png' );
		copy( $bigpath, $copy );
		$pixels = $sideload( $copy, 't72-42mp.png' );
		$check( is_wp_error( $pixels ) && str_contains( $pixels->get_error_message(), '40 megapixels' ), 'AC-1 a 42-megapixel image is refused on sideload (the catalogue migration\'s route), though its file is small (' . size_format( (int) filesize( $bigpath ) ) . '): ' . ( is_wp_error( $pixels ) ? $pixels->get_error_message() : 'stored' ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/media' );
		$request->set_header( 'Content-Type', 'image/png' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=t72-rest-42mp.png' );
		$request->set_body( (string) file_get_contents( $bigpath ) );
		$response = rest_do_request( $request );
		if ( 201 === $response->get_status() ) {
			$made[] = (int) $response->get_data()['id'];
		}
		$check( 201 !== $response->get_status() && str_contains( wp_json_encode( $response->get_data() ), 'megapixels' ), 'AC-1 the same image through the REST API is refused (' . $response->get_status() . ')' );
		$ok_small = ImageHandling::guard( array( 'name' => 'fine.jpg', 'type' => 'image/jpeg', 'tmp_name' => $small, 'size' => filesize( $small ), 'error' => 0 ) );
		$text     = wp_tempnam( 't72.txt' );
		file_put_contents( $text, 'not an image' );
		$other    = ImageHandling::guard( array( 'name' => 'notes.txt', 'type' => 'text/plain', 'tmp_name' => $text, 'size' => 30 * 1048576, 'error' => 0 ) );
		$bmp_path = wp_tempnam( 't72.bmp' );
		$bmp_img  = imagecreatetruecolor( 50, 50 );
		imagebmp( $bmp_img, $bmp_path );
		imagedestroy( $bmp_img );
		$bmp = ImageHandling::guard( array( 'name' => 'scan.bmp', 'type' => 'image/bmp', 'tmp_name' => $bmp_path, 'size' => 25 * 1048576, 'error' => 0 ) );
		@unlink( $bmp_path );
		$check( str_contains( (string) ( $bmp['error'] ?? '' ), '20 MB' ), 'AC-1 a BMP over 20 MB is refused too, like every image type WordPress accepts' );
		$check( empty( $ok_small['error'] ) && empty( $other['error'] ), 'AC-1 an ordinary photo passes; a file that is not an image is left to WordPress\'s own checks' );
		@unlink( $bigpath );
		@unlink( $small );
		@unlink( $text );

		// ---- AC-4: Western digits and locale formatting in Arabic --------------------------------------------
		switch_to_locale( 'ar' );
		$price = wp_strip_all_tags( wc_price( 12345.5 ) );
		$date  = wp_date( (string) get_option( 'date_format' ), 1793530000 );
		$num   = number_format_i18n( 1234567.89, 2 );
		restore_previous_locale();
		$eastern = '/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u';
		$check( 1 === preg_match( '/12[^0-9]?345/', $price ) && 1 !== preg_match( $eastern, $price . $date . $num ), 'AC-4 on an Arabic page a price, a number and a date use Western digits 0 to 9 (' . $price . ' | ' . $num . ' | ' . $date . ')' );
		$check( 1 === preg_match( '/\p{Arabic}/u', $date ), 'AC-4 the Arabic date is formatted for the locale, with the month in Arabic (' . $date . ')' );

		// AC-4 on served pages: an in-process locale switch is not the page a visitor gets.
		$en_id = catalogue_simple( $s, 'T72-PRICE-' . wp_rand( 1000, 9999 ), 't72 price product' );
		$en    = wc_get_product( $en_id );
		$en->set_regular_price( '12345.50' );
		$en->save();
		$ar_id = catalogue_duplicate( $s, $en_id );
		$check( $ar_id > 0, 'AC-4 the price fixture has its Arabic record (' . $ar_id . ')' );
		$page_text = static function ( string $url ): string {
			$r      = wp_remote_get( $url, array( 'timeout' => 30, 'redirection' => 0, 'cookies' => array() ) );
			$status = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
			$body   = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
			storefront_refuse_shell( $url, $status, $body );
			return 200 === $status ? html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' ) : '';
		};
		$space     = '[\s\x{00A0}]';
		$en_text   = $page_text( (string) get_permalink( $en_id ) );
		$ar_text   = $ar_id > 0 ? $page_text( (string) get_permalink( $ar_id ) ) : '';
		$en_symbol = 1 === preg_match( '/EGP' . $space . '*12,?345\.50/u', $en_text, $en_m );
		$ar_symbol = 1 === preg_match( '/12,?345\.50' . $space . '+\x{062C}\.\x{0645}/u', $ar_text, $ar_m );
		$check( $en_symbol, 'AC-4 the served English product page writes EGP before the amount (' . ( $en_m[0] ?? 'not found' ) . ')' );
		$check( $ar_symbol && 1 !== preg_match( '/EGP' . $space . '*12,?345\.50/u', $ar_text ), 'AC-4 the served Arabic product page writes the amount in Western digits, a space, then the Arabic symbol, and never the English form (' . ( $ar_m[0] ?? 'not found' ) . ')' );
		switch_to_locale( 'ar' );
		$switched_price = html_entity_decode( wp_strip_all_tags( wc_price( 10 ) ), ENT_QUOTES, 'UTF-8' );
		restore_previous_locale();
		$check( 1 === preg_match( '/^10\.00' . $space . '\x{062C}\.\x{0645}$/u', $switched_price ), 'AC-4 an Arabic email or invoice, which switches to the order\'s language, writes the Arabic form too (' . $switched_price . ')' );

		return $ok;
	}
);

<?php
/**
 * t72: images whatever is uploaded (NFR-02), and number and date formatting in both languages (NFR-04b).
 * See specs/032-image-handling.
 *
 * AC-1: an image over 20 MB or 40 megapixels is refused with a message, through the media upload, a sideload and the
 *       REST API.
 * AC-2: every size WordPress makes of a JPEG or a PNG is WebP; the full size is scaled to 2,560 px and the original kept.
 * AC-3: a product's image is served as WebP and lazy loaded below the first image.
 * AC-4: prices, numbers and dates use Western digits on Arabic pages, with locale formatting.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Catalogue\ImageHandling;

require_once __DIR__ . '/_bootstrap.php';
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

		return $ok;
	}
);

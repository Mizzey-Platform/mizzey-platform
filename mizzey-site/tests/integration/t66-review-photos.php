<?php
/**
 * t66: photos in reviews, a recorded gift (D-23). See specs/025-review-photos.
 *
 * AC-1: the reviewer attaches up to 5 JPEG, PNG or WebP photos to their own held review; anything else, a sixth
 *       photo, another customer, or a review already published is refused.
 * AC-2: a photo is stored cleaned: its location and camera data gone, at most 2000 pixels a side, under a random name.
 * AC-3: [HTTP] a photo is seen only while its review is approved, with its alternative text; deleting the review
 *       deletes its files.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Reviews\ReviewPhotos;

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_sign_in.php';
require_once __DIR__ . '/_two_factor.php';

/** A file in the temporary directory, as PHP leaves an upload. */
function t66_file( string $bytes, string $suffix ): string {
	$path = trailingslashit( get_temp_dir() ) . 't66-' . wp_rand() . $suffix;
	file_put_contents( $path, $bytes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $path;
}

/** An image of the size, encoded as the type. */
function t66_image( int $width, int $height, string $type ): string {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 200, 120, 90 ) );
	ob_start();
	'png' === $type ? imagepng( $image ) : imagejpeg( $image, null, 90 );
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/** A JPEG carrying an Exif segment with a GPS marker, as a phone writes one. */
function t66_jpeg_with_location(): string {
	$jpeg = t66_image( 3000, 1500, 'jpeg' );
	$exif = "Exif\0\0" . 'MM GPSLatitude 30.0444 N GPSLongitude 31.2357 E Model PhoneCam';
	return substr( $jpeg, 0, 2 ) . "\xFF\xE1" . pack( 'n', strlen( $exif ) + 2 ) . $exif . substr( $jpeg, 2 );
}

/** The REST upload, as the user. */
function t66_upload( int $user, int $review, array $paths, array $alts = array() ): \WP_REST_Response {
	wp_set_current_user( $user );
	$request = new \WP_REST_Request( 'POST', "/mizzey/v1/reviews/{$review}/photos" );
	$request->set_file_params( array( 'photos' => array(
		'name'     => array_map( 'basename', $paths ),
		'tmp_name' => $paths,
		'size'     => array_map( 'filesize', $paths ),
		'error'    => array_fill( 0, count( $paths ), UPLOAD_ERR_OK ),
	) ) );
	if ( array() !== $alts ) {
		$request->set_param( 'alt', $alts );
	}
	$response = rest_do_request( $request );
	wp_set_current_user( 0 );
	return $response;
}

run(
	new Scenario( 't66-review-photos', 'D-23: photos in reviews' ),
	function ( Scenario $s ): bool {
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag      = 't66' . wp_rand( 1000, 9999 );
		$product  = $s->simple_product( 't66 cream', '120', null );
		$users    = array();
		foreach ( array( 'reviewer', 'other' ) as $who ) {
			$id = wp_insert_user( array( 'user_login' => "{$tag}-{$who}", 'user_email' => "{$tag}-{$who}@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer' ) );
			$users[ $who ] = is_wp_error( $id ) ? 0 : (int) $id;
			if ( $users[ $who ] > 1 ) {
				$s->track_user( $users[ $who ] );
			}
		}
		$review = wp_insert_comment( array( 'comment_post_ID' => $product->get_id(), 'comment_content' => 'Lovely texture.', 'comment_author' => 'Salma', 'comment_author_email' => "{$tag}-reviewer@example.invalid", 'user_id' => $users['reviewer'], 'comment_type' => 'review', 'comment_approved' => 0 ) );
		$files  = array();
		$s->on_finish( static function () use ( &$review, &$files ) {
			if ( $review && get_comment( $review ) ) {
				wp_delete_comment( $review, true );
			}
			foreach ( $files as $file ) {
				wp_delete_file( $file );
			}
		} );

		// AC-1: what is accepted and what is refused.
		$located = $files[] = t66_file( t66_jpeg_with_location(), '.jpg' );
		$png     = $files[] = t66_file( t66_image( 400, 300, 'png' ), '.png' );
		$script  = $files[] = t66_file( "\xFF\xD8\xFF<?php echo 'x'; ?>", '.jpg' );
		$huge    = $files[] = t66_file( t66_image( 6001, 10, 'png' ), '.png' );
		$first   = t66_upload( $users['reviewer'], (int) $review, array( $located, $png ), array( 'A jar of cream on a shelf' ) );
		$faked   = t66_upload( $users['reviewer'], (int) $review, array( $script ) );
		$wide    = t66_upload( $users['reviewer'], (int) $review, array( $huge ) );
		$stolen  = t66_upload( $users['other'], (int) $review, array( $png ) );
		$extra   = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$extra[] = $files[] = t66_file( t66_image( 200, 200, 'jpeg' ), '.jpg' );
		}
		$sixth  = t66_upload( $users['reviewer'], (int) $review, $extra );
		$photos = ReviewPhotos::photos( (int) $review );
		$check( 201 === $first->get_status() && 400 === $faked->get_status() && 400 === $wide->get_status() && in_array( $stolen->get_status(), array( 401, 403 ), true ) && 400 === $sixth->get_status() && 2 === count( $photos ), 'D-23 the reviewer attaches JPEG and PNG photos to their held review; a script named .jpg, an image over 6000 pixels, another customer and a sixth photo are refused (' . implode( ', ', array( $first->get_status(), $faked->get_status(), $wide->get_status(), $stolen->get_status(), $sixth->get_status() ) ) . ')' );

		// AC-2: stored cleaned.
		$stored  = isset( $photos[0] ) ? (string) file_get_contents( ReviewPhotos::dir() . $photos[0]['file'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$size    = '' !== $stored ? getimagesizefromstring( $stored ) : false;
		$check( '' !== $stored && false === strpos( $stored, 'GPSLatitude' ) && false === strpos( $stored, 'PhoneCam' ) && is_array( $size ) && 2000 === max( $size[0], $size[1] ) && 1 === preg_match( '/^[0-9a-f]{32}\.jpg$/', (string) $photos[0]['file'] ) && is_file( ReviewPhotos::dir() . '.htaccess' ), 'D-23 a photo is stored without its location and camera data, at most 2000 pixels a side, under a random name, in a folder the web server is told to refuse (' . ( is_array( $size ) ? $size[0] . 'x' . $size[1] : 'unreadable' ) . ')' );

		// AC-3: seen only while approved.
		$url  = ReviewPhotos::url( (int) $review, 0 );
		$held = two_factor_http( 'GET', $url );
		wp_set_comment_status( (int) $review, 'approve' );
		$open = two_factor_http( 'GET', $url );
		ob_start();
		do_action( 'woocommerce_review_after_comment_text', get_comment( (int) $review ) );
		$markup = (string) ob_get_clean();
		$late   = t66_upload( $users['reviewer'], (int) $review, array( $png ) );
		wp_set_comment_status( (int) $review, 'hold' );
		$rejected = two_factor_http( 'GET', $url );
		$check( 404 === $held['status'] && 200 === $open['status'] && str_starts_with( (string) $open['body'], "\xFF\xD8" ) && false === strpos( (string) $open['body'], 'GPSLatitude' ) && 404 === $rejected['status'] && false !== strpos( $markup, 'alt="A jar of cream on a shelf"' ) && false !== strpos( $markup, 't66 cream' ) && in_array( $late->get_status(), array( 401, 403 ), true ), '[HTTP] D-23 a photo is served only while its review is approved, shown with its alternative text (the reviewer\'s, or one naming the product), and none can be added once it is published (' . implode( ', ', array( $held['status'], $open['status'], $rejected['status'], $late->get_status() ) ) . ')' );

		$paths = array_map( static fn ( array $p ): string => ReviewPhotos::dir() . $p['file'], ReviewPhotos::photos( (int) $review ) );
		wp_delete_comment( (int) $review, true );
		$review = 0;
		$check( array() !== $paths && array() === array_filter( $paths, 'is_file' ), 'D-23 deleting a review deletes its photos' );

		return $ok;
	}
);

<?php
/**
 * t66: photos in reviews, a recorded gift (D-23). See specs/025-review-photos.
 *
 * AC-1: the reviewer attaches up to 5 JPEG, PNG or WebP photos to their own held review, over HTTP with WordPress's
 *       REST nonce; anything else, a sixth photo, another customer, or a review already published is refused.
 * AC-2: a photo is stored cleaned: no location, camera or profile data, nothing after the picture, at most 2000
 *       pixels a side, under a random name, in a folder the web server refuses.
 * AC-3: [HTTP] a photo is seen only while its review is approved, never cached, and only if it was attached before
 *       the approval; staff who moderate see held ones; deleting the review deletes its files.
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
	if ( 'png' === $type ) {
		imagepng( $image, null, 9 );
	} elseif ( 'webp' === $type ) {
		imagewebp( $image, null, 80 );
	} else {
		imagejpeg( $image, null, 90 );
	}
	imagedestroy( $image );
	return (string) ob_get_clean();
}

/** A JPEG carrying Exif (GPS, camera), XMP and an ICC profile, as a phone writes one, and a script after its end. */
function t66_jpeg_with_metadata(): string {
	$jpeg    = t66_image( 3000, 1500, 'jpeg' );
	$segment = static fn ( string $marker, string $data ): string => $marker . pack( 'n', strlen( $data ) + 2 ) . $data;
	$exif    = $segment( "\xFF\xE1", "Exif\0\0MM GPSLatitude 30.0444 N GPSLongitude 31.2357 E Model PhoneCam" );
	$xmp     = $segment( "\xFF\xE1", "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>t66-xmp-creator</x:xmpmeta>" );
	$icc     = $segment( "\xFF\xE2", "ICC_PROFILE\0\x01\x01t66-icc-profile" );
	return substr( $jpeg, 0, 2 ) . $exif . $xmp . $icc . substr( $jpeg, 2 ) . '<?php echo "t66-tail"; ?>';
}

/** A multipart body of photos and their alternative text. */
function t66_multipart( array $paths, array $alts, string $boundary ): string {
	$body = '';
	foreach ( $paths as $i => $path ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"photos[]\"; filename=\"" . basename( $path ) . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . file_get_contents( $path ) . "\r\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( isset( $alts[ $i ] ) ) {
			$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"alt[]\"\r\n\r\n" . $alts[ $i ] . "\r\n";
		}
	}
	return $body . "--{$boundary}--\r\n";
}

/** The upload over HTTP, as the signed-in browser sends it. */
function t66_http_upload( int $review, array $paths, array $alts, array $cookies, ?string $nonce ): int {
	$boundary = 't66' . wp_rand();
	$headers  = array( 'Content-Type' => "multipart/form-data; boundary={$boundary}" );
	if ( null !== $nonce ) {
		$headers['X-WP-Nonce'] = $nonce;
	}
	$r = wp_remote_post( rest_url( "mizzey/v1/reviews/{$review}/photos" ), array( 'timeout' => 60, 'redirection' => 0, 'cookies' => $cookies, 'headers' => $headers, 'body' => t66_multipart( $paths, $alts, $boundary ) ) ); // storefront-guard: exempt, a REST upload, not a storefront page.
	return is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
}

/** The upload in this process, as the user. */
function t66_upload( int $user, int $review, array $paths ): \WP_REST_Response {
	wp_set_current_user( $user );
	$request = new \WP_REST_Request( 'POST', "/mizzey/v1/reviews/{$review}/photos" );
	$request->set_file_params( array( 'photos' => array(
		'name'     => array_map( 'basename', $paths ),
		'tmp_name' => $paths,
		'size'     => array_map( 'filesize', $paths ),
		'error'    => array_fill( 0, count( $paths ), UPLOAD_ERR_OK ),
	) ) );
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
		$password = 'Pw-' . wp_generate_password( 18, false );
		$product  = $s->simple_product( 't66 cream', '120', null );
		$users    = array();
		foreach ( array( 'reviewer' => 'customer', 'other' => 'customer', 'moderator' => 'shop_manager' ) as $who => $role ) {
			$id = wp_insert_user( array( 'user_login' => "{$tag}-{$who}", 'user_email' => "{$tag}-{$who}@example.invalid", 'user_pass' => $password, 'role' => $role ) );
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
		sign_in_keep_option( $s, ReviewPhotos::STORAGE );
		delete_option( ReviewPhotos::STORAGE );

		// AC-1 over HTTP: the REST nonce is required.
		$signed = sign_in_account( 'en', "{$tag}-reviewer", $password );
		$jar    = '';
		foreach ( sign_in_jar( $signed['cookies'] ) as $c ) {
			if ( LOGGED_IN_COOKIE === $c->name ) {
				$jar = (string) $c->value;
			}
		}
		$_COOKIE[ LOGGED_IN_COOKIE ] = $jar;
		wp_set_current_user( $users['reviewer'] );
		$nonce = wp_create_nonce( 'wp_rest' );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		wp_set_current_user( 0 );
		$located   = $files[] = t66_file( t66_jpeg_with_metadata(), '.jpg' );
		$png       = $files[] = t66_file( t66_image( 400, 300, 'png' ), '.png' );
		$no_nonce  = t66_http_upload( (int) $review, array( $png ), array(), $signed['cookies'], null );
		$bad_nonce = t66_http_upload( (int) $review, array( $png ), array(), $signed['cookies'], 'not-a-nonce' );
		$sent      = t66_http_upload( (int) $review, array( $located, $png ), array( 'A jar of cream on a shelf' ), $signed['cookies'], $nonce );
		$check( 201 === $sent && in_array( $no_nonce, array( 401, 403 ), true ) && 403 === $bad_nonce && 2 === count( ReviewPhotos::photos( (int) $review ) ), "[HTTP] D-23 the signed-in reviewer attaches photos with WordPress's REST nonce; without it, or with a wrong one, nothing is attached ({$sent}, {$no_nonce}, {$bad_nonce})" );

		// AC-1 in this process: what else is accepted and refused.
		$webp   = $files[] = t66_file( t66_image( 300, 300, 'webp' ), '.webp' );
		$script = $files[] = t66_file( "\xFF\xD8\xFF<?php echo 'x'; ?>", '.jpg' );
		$wide   = $files[] = t66_file( t66_image( 6001, 10, 'png' ), '.png' );
		$bomb   = $files[] = t66_file( t66_image( 6000, 6000, 'png' ), '.png' );
		$got_webp = t66_upload( $users['reviewer'], (int) $review, array( $webp ) );
		$faked    = t66_upload( $users['reviewer'], (int) $review, array( $script ) );
		$too_wide = t66_upload( $users['reviewer'], (int) $review, array( $wide ) );
		$bombed   = t66_upload( $users['reviewer'], (int) $review, array( $bomb ) );
		$stolen   = t66_upload( $users['other'], (int) $review, array( $png ) );
		$extra    = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$extra[] = $files[] = t66_file( t66_image( 200, 200, 'jpeg' ), '.jpg' );
		}
		$sixth  = t66_upload( $users['reviewer'], (int) $review, $extra );
		// With memory already held, a 4000 by 3000 picture with transparency is more than the decoder can hold.
		$rgba  = imagecreatetruecolor( 4000, 3000 );
		imagesavealpha( $rgba, true );
		imagefill( $rgba, 0, 0, imagecolorallocatealpha( $rgba, 10, 20, 30, 60 ) );
		ob_start();
		imagepng( $rgba, null, 9 );
		imagedestroy( $rgba );
		$heavy      = $files[] = t66_file( (string) ob_get_clean(), '.png' );
		$was_limit  = ini_get( 'memory_limit' );
		// A busy worker: 256 MB, WordPress's limit for images, with all but about 60 MB already held.
		$cap       = static fn () => '256M';
		add_filter( 'image_memory_limit', $cap );
		$capped    = false !== ini_set( 'memory_limit', '256M' ); // phpcs:ignore WordPress.PHP.IniSet
		$ballast   = str_repeat( 'x', max( 0, 256 * 1024 * 1024 - memory_get_usage( true ) - 60 * 1024 * 1024 ) );
		$too_heavy = t66_upload( $users['reviewer'], (int) $review, array( $heavy ) );
		unset( $ballast );
		ini_set( 'memory_limit', (string) $was_limit ); // phpcs:ignore WordPress.PHP.IniSet
		remove_filter( 'image_memory_limit', $cap );
		$photos = ReviewPhotos::photos( (int) $review );
		$check( 201 === $got_webp->get_status() && 400 === $faked->get_status() && 400 === $too_wide->get_status() && 400 === $bombed->get_status() && in_array( $stolen->get_status(), array( 401, 403 ), true ) && 400 === $sixth->get_status() && $capped && 400 === $too_heavy->get_status() && 3 === count( $photos ), 'D-23 a WebP is attached; a script named .jpg, an image over 6000 pixels a side, one of 36 million pixels (filesize ' . size_format( filesize( $bomb ) ) . '), another customer, a sixth photo, and a 4000 by 3000 transparent picture in a busy worker are refused (' . implode( ', ', array( $got_webp->get_status(), $faked->get_status(), $too_wide->get_status(), $bombed->get_status(), $stolen->get_status(), $sixth->get_status(), $too_heavy->get_status() ) ) . ')' );

		// AC-2: stored cleaned, in a folder the web server refuses.
		$stored = isset( $photos[0] ) ? (string) file_get_contents( ReviewPhotos::dir() . $photos[0]['file'] ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$size   = '' !== $stored ? getimagesizefromstring( $stored ) : false;
		$leaks  = array_values( array_filter( array( 'GPSLatitude', 'PhoneCam', 't66-xmp-creator', 't66-icc-profile', 't66-tail', '<?php' ), static fn ( string $mark ): bool => false !== strpos( $stored, $mark ) ) );
		$direct = isset( $photos[0] ) ? sign_in_json( 'GET', trailingslashit( (string) wp_upload_dir( null, false )['baseurl'] ) . 'mizzey-review-photos/' . $photos[0]['file'] ) : array( 'status' => 0 );
		$check( '' !== $stored && array() === $leaks && is_array( $size ) && 2000 === max( $size[0], $size[1] ) && 1 === preg_match( '/^[0-9a-f]{32}\.jpg$/', (string) $photos[0]['file'] ) && 200 !== $direct['status'] && ReviewPhotos::storageReady(), '[HTTP] D-23 a photo is stored without its location, camera, XMP or colour profile data and without what followed the picture, at most 2000 pixels a side, under a random name; its file cannot be fetched from the web (' . ( is_array( $size ) ? $size[0] . 'x' . $size[1] : 'unreadable' ) . ', direct ' . $direct['status'] . ( array() === $leaks ? '' : ', leaked: ' . implode( ' ', $leaks ) ) . ')' );

		// AC-3: seen only while approved, never cached; staff who moderate see held ones.
		$url  = ReviewPhotos::url( (int) $review, 0 );
		$held = sign_in_json( 'GET', $url );
		wp_set_current_user( $users['moderator'] );
		set_current_screen( 'edit-comments' );
		$admin_list = (string) ReviewPhotos::adminList( 'Lovely texture.', get_comment( (int) $review ) );
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		wp_set_comment_status( (int) $review, 'approve' );
		$open = sign_in_json( 'GET', $url );
		// A photo committed just after the approval recorded the review's photos, as a racing upload would: it waits for
		// the next approval.
		$late_photo         = ReviewPhotos::photos( (int) $review )[0];
		$late_photo['file'] = bin2hex( random_bytes( 16 ) ) . '.jpg';
		copy( ReviewPhotos::dir() . ReviewPhotos::photos( (int) $review )[0]['file'], ReviewPhotos::dir() . $late_photo['file'] );
		update_comment_meta( (int) $review, ReviewPhotos::META, array_merge( ReviewPhotos::photos( (int) $review ), array( $late_photo ) ) );
		$racing = sign_in_json( 'GET', ReviewPhotos::url( (int) $review, count( ReviewPhotos::photos( (int) $review ) ) - 1 ) );
		ob_start();
		do_action( 'woocommerce_review_after_comment_text', get_comment( (int) $review ) );
		$markup = (string) ob_get_clean();
		$late   = t66_upload( $users['reviewer'], (int) $review, array( $png ) );
		wp_set_comment_status( (int) $review, 'hold' );
		$rejected = sign_in_json( 'GET', $url );
		$cache    = strtolower( sign_in_header( $open, 'cache-control' ) );
		$check( 404 === $held['status'] && false !== strpos( $admin_list, 'mizzey-review-photo=' ) && 200 === $open['status'] && false !== strpos( $cache, 'no-store' ) && 404 === $racing['status'] && 404 === $rejected['status'] && false !== strpos( $markup, 'alt="A jar of cream on a shelf"' ) && false !== strpos( $markup, 't66 cream' ) && 3 === substr_count( $markup, '<img' ) && in_array( $late->get_status(), array( 401, 403 ), true ), '[HTTP] D-23 a photo is served only while its review is approved, never cached, and only if the review had it when approved; staff who moderate see held ones in the admin list; it is shown with its alternative text (the reviewer\'s, or one naming the product); none can be added once published (' . implode( ', ', array( $held['status'], $open['status'], $racing['status'], $rejected['status'], $late->get_status(), $cache ) ) . ')' );

		// The sweep: a stored photo no review refers to goes once it is ten minutes old; a referred one, and a young one, stay.
		$orphan = ReviewPhotos::dir() . bin2hex( random_bytes( 16 ) ) . '.jpg';
		$young  = ReviewPhotos::dir() . bin2hex( random_bytes( 16 ) ) . '.jpg';
		copy( ReviewPhotos::dir() . ReviewPhotos::photos( (int) $review )[0]['file'], $orphan );
		copy( ReviewPhotos::dir() . ReviewPhotos::photos( (int) $review )[0]['file'], $young );
		touch( $orphan, time() - HOUR_IN_SECONDS );
		$swept = ReviewPhotos::sweep();
		$check( $swept >= 1 && ! is_file( $orphan ) && is_file( $young ) && is_file( ReviewPhotos::dir() . ReviewPhotos::photos( (int) $review )[0]['file'] ), 'D-23 a stored photo no review refers to is swept away once it is ten minutes old; a younger one, and every photo a review refers to, stay (' . $swept . ' swept)' );
		wp_delete_file( $young );

		$paths = array_map( static fn ( array $p ): string => ReviewPhotos::dir() . $p['file'], ReviewPhotos::photos( (int) $review ) );
		wp_delete_comment( (int) $review, true );
		$review = 0;
		$check( array() !== $paths && array() === array_filter( $paths, 'is_file' ), 'D-23 deleting a review deletes its photos' );

		sign_in_forget( array( "{$tag}-reviewer", "{$tag}-other", "{$tag}-moderator" ) );
		return $ok;
	}
);

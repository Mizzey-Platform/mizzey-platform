<?php
/**
 * t116, spec 066 slice 10: the lower product page, details, specifications, reviews, related and recently viewed
 * (AC-12, AC-11 for PDP-12 and PDP-13; PDP-12, PDP-13, PDP-17, PDP-18, PDP-19, REV-04; D-23's photos, a gift), in a
 * real browser over a fixture, every record named "s10-" and removed when the scenario ends:
 *
 * - a product with a long description in several paragraphs, a brand, visible attributes and one hidden one, a weight
 *   and a SKU, three related products (the staff's Upsells), and reviews of every kind: ratings 1 to 5, short and very
 *   long texts, English and Arabic, one photo and five photos, all approved; one more review held for moderation with
 *   a photo, and one marked spam, which must never reach the page; and its Arabic record;
 * - a product with a description only: no specifications, no reviews, no related products.
 *
 * In Chromium (mizzey-site/tests/browser/product-details.cjs), at 1440 and 390 wide in English and Arabic, and in
 * WebKit at 390 with touch, the page is measured against C1-Product-Desktop, C1-Product-Phone and C1-Photo-Reviews and
 * used: the jump-to bar, "Read more", the order, "With photos", "Show all" and the "all reviews" view with its filters
 * and "Load more", the photo viewer, "Write a review", and the recently viewed rail filled by the browser's own visits.
 *
 * @package MizzeySite\Tests\Integration
 */

namespace MizzeySite\Tests\Integration;

use MizzeySite\Reviews\ReviewPhotos;
use MizzeySite\Storefront\ProductReviews;

require_once __DIR__ . '/_bootstrap.php';

/** Register a fixture record as English, whatever language WPML's process was left in. */
function t116_english( int $id ): void {
	$details = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $id, 'element_type' => 'post_product' ) );
	if ( ! $details || 'en' !== ( $details->language_code ?? null ) ) {
		do_action( 'wpml_set_element_language_details', array( 'element_id' => $id, 'element_type' => 'post_product', 'trid' => false, 'language_code' => 'en' ) );
	}
}

/** A photo file drawn here and stored where review photos are kept, as ReviewPhotos records one. */
function t116_photo( int $width, int $height, array $rgb, string $alt ): array {
	$image = imagecreatetruecolor( $width, $height );
	imagefill( $image, 0, 0, imagecolorallocate( $image, 240, 240, 236 ) );
	imagefilledellipse( $image, (int) ( $width / 2 ), (int) ( $height / 2 ), (int) ( $width * 0.6 ), (int) ( $height * 0.7 ), imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] ) );
	$name = 's10-' . bin2hex( random_bytes( 12 ) ) . '.png';
	wp_mkdir_p( ReviewPhotos::dir() );
	imagepng( $image, ReviewPhotos::dir() . $name );
	imagedestroy( $image );
	return array( 'file' => $name, 'alt' => $alt, 'width' => $width, 'height' => $height );
}

/** A review as WooCommerce stores one, held first and then given the status asked, so its photos are recorded. */
function t116_review( Scenario $s, int $product, string $author, int $rating, string $text, string $date, array $photos, string $status ): int {
	$id = (int) wp_insert_comment(
		array(
			'comment_post_ID'  => $product,
			'comment_author'   => $author,
			'comment_content'  => $text,
			'comment_type'     => 'review',
			'comment_approved' => 0,
			'comment_date'     => $date,
			'comment_date_gmt' => get_gmt_from_date( $date ),
		)
	);
	update_comment_meta( $id, 'rating', $rating );
	if ( $photos ) {
		update_comment_meta( $id, ReviewPhotos::META, $photos );
	}
	if ( 'hold' !== $status ) {
		wp_set_comment_status( $id, $status );
	}
	$s->on_finish( static fn () => wp_delete_comment( $id, true ) );
	return $id;
}

/** @return array<string,mixed> */
function t116_browser( array $plan ): array {
	$file = wp_tempnam( 't116-plan' );
	file_put_contents( $file, wp_json_encode( $plan ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$out  = array();
	$code = 0;
	exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/browser/product-details.cjs' ) . ' ' . escapeshellarg( $file ) . ' 2>&1', $out, $code );
	wp_delete_file( $file );
	$json = json_decode( (string) end( $out ), true );
	return is_array( $json ) ? $json : array( 'error' => implode( "\n", array_slice( $out, -20 ) ) );
}

run(
	new Scenario( 't116-product-details', 'AC-12 (slice 10): the product page details, specifications, reviews with photos, related and recently viewed, in both languages, at 390 and 1440' ),
	function ( Scenario $s ): bool {
		global $sitepress;
		$ok    = true;
		$check = function ( bool $condition, string $line ) use ( &$ok, $s ): void {
			$s->note( ( $condition ? 'PASS  ' : 'FAIL  ' ) . $line );
			$ok = $ok && $condition;
		};
		$tag = 's10-' . strtolower( wp_generate_password( 5, false, false ) );
		do_action( 'wpml_switch_language', 'en' );

		$brand = taxonomy_exists( 'product_brand' ) ? wp_insert_term( "{$tag} Vital Proteins", 'product_brand' ) : null;
		$brand = is_array( $brand ) ? (int) $brand['term_id'] : 0;
		if ( $brand > 0 ) {
			$s->on_finish( static fn () => wp_delete_term( $brand, 'product_brand' ) );
		}

		$related = array();
		foreach ( array( 'Sports Research L-Theanine', 'Bloom Green Juice Powder', 'Otiem Copper Water Bottle' ) as $i => $name ) {
			$p = $s->simple_product( "{$tag} {$name}", (string) ( 900 + 100 * $i ), null );
			t116_english( $p->get_id() );
			$related[] = $p->get_id();
		}

		$description = "<p>{$tag} Vital Proteins Collagen Peptides is an unflavoured powder made from one ingredient: collagen peptides from pasture raised, grass fed cattle. The powder is fine and neutral in taste and smell, so it mixes into hot or cold drinks without changing them. Stir it into coffee, tea, juice or a smoothie, or add it to oatmeal, soup and baking.</p>\n"
			. "<p>The 24 ounce tub holds 680 grams. It is paleo friendly and gluten free, with no added sugar, flavouring or sweetener. Each tub is sealed under the lid, so check that the seal is whole when your order arrives.</p>\n"
			. '<p><strong>How to use:</strong> stir one to two scoops into a hot or cold drink once a day.</p>';
		$full = $s->simple_product( "{$tag} Collagen Peptides", '5200', null );
		$full->set_description( $description );
		$full->set_short_description( "<ul>
<li>Collagen peptides, single ingredient</li>
<li>Pasture raised and grass fed</li>
</ul>" );
		$full->set_sku( strtoupper( $tag ) . '-CP-24' );
		$full->set_weight( '0.68' );
		$full->set_upsell_ids( $related );
		$attributes = array();
		foreach ( array( 'Form' => array( array( 'Powder' ), true ), 'Diet' => array( array( 'Paleo friendly', 'Gluten free' ), true ), 'Made in' => array( array( 'USA' ), true ), 'Supplier code' => array( array( 'HIDDEN-42' ), false ) ) as $name => list( $values, $visible ) ) {
			$a = new \WC_Product_Attribute();
			$a->set_name( $name );
			$a->set_options( $values );
			$a->set_visible( $visible );
			$a->set_variation( false );
			$attributes[] = $a;
		}
		$full->set_attributes( $attributes );
		$full->save();
		if ( $brand > 0 ) {
			wp_set_object_terms( $full->get_id(), array( $brand ), 'product_brand' );
		}
		t116_english( $full->get_id() );

		$bare = $s->simple_product( "{$tag} Lip Balm", '120', null );
		$bare->set_description( "<p>{$tag} A small balm for dry lips, made with shea butter.</p>" );
		$bare->save();
		t116_english( $bare->get_id() );

		// Reviews of every kind, the most recent first as the page lists them.
		$long  = 'I was not sure about collagen at first, so I gave it a full month before writing this. ' . str_repeat( 'It mixes into hot coffee and into cold water without lumps, it has no taste at all, and the tub lasts a long time. ', 6 ) . 'Overall a good buy that I will order again.';
		$one   = array( t116_photo( 800, 600, array( 38, 59, 115 ), 'The tub on a kitchen shelf' ) );
		$five  = array(
			t116_photo( 600, 800, array( 255, 106, 74 ), 'The powder in a glass' ),
			t116_photo( 800, 800, array( 36, 123, 71 ), 'The seal under the lid' ),
			t116_photo( 1200, 400, array( 38, 59, 115 ), 'The tub beside a mug' ),
			t116_photo( 500, 900, array( 145, 99, 0 ), 'The scoop' ),
			t116_photo( 700, 700, array( 205, 41, 73 ), 'The label' ),
		);
		$held  = array( t116_photo( 600, 600, array( 0, 0, 0 ), 'A held photo' ) );
		$reviews = array(
			'five'   => t116_review( $s, $full->get_id(), 'Mariam Hassan', 5, 'Mixes well and has no taste. Photos of the tub on my tray attached.', '2026-11-20 10:00:00', $five, 'approve' ),
			'arabic' => t116_review( $s, $full->get_id(), 'سارة محمود', 4, 'وصل المنتج بسرعة والتغليف ممتاز. يذوب في القهوة بدون أي طعم.', '2026-11-18 10:00:00', array(), 'approve' ),
			'long'   => t116_review( $s, $full->get_id(), 'Omar Samir', 3, $long, '2026-11-15 10:00:00', array(), 'approve' ),
			'one'    => t116_review( $s, $full->get_id(), 'Nour Adel', 2, 'The seal was fine but the scoop sinks into the powder.', '2026-11-10 10:00:00', $one, 'approve' ),
			'low'    => t116_review( $s, $full->get_id(), 'Karim', 1, 'Too expensive for me.', '2026-11-05 10:00:00', array(), 'approve' ),
		);
		for ( $i = 0; $i < 9; $i++ ) {
			$reviews[ 'more' . $i ] = t116_review( $s, $full->get_id(), "Customer {$i} Test", 5 - ( $i % 2 ), "Good product, order number {$i}.", sprintf( '2026-10-%02d 10:00:00', 20 - $i ), array(), 'approve' );
		}
		$hidden = t116_review( $s, $full->get_id(), 'Held Reviewer', 1, "{$tag} HELD REVIEW TEXT", '2026-11-24 10:00:00', $held, 'hold' );
		$spam   = t116_review( $s, $full->get_id(), 'Spam Reviewer', 5, "{$tag} SPAM REVIEW TEXT", '2026-11-23 10:00:00', array(), 'spam' );
		\WC_Comments::clear_transients( $full->get_id() );
		ProductReviews::forget();
		$summary = ProductReviews::summary( wc_get_product( $full->get_id() ) );
		$check( 14 === $summary['count'] && array( 5 => 6, 4 => 5, 3 => 1, 2 => 1, 1 => 1 ) === $summary['stars'] && 2 === $summary['photos'], 'PDP-17 only the approved reviews are read: 14, by star, 2 with photos (' . wp_json_encode( $summary ) . ')' );
		$check( 5 === count( ProductReviews::find( wc_get_product( $full->get_id() ), 'photos', 'recent' )[0]['photos'] ?? array() ), 'D-23 the approved review shows its five photos, recorded when it was approved' );

		$sitepress->make_duplicate( $full->get_id(), 'ar' );
		$full_ar = (int) apply_filters( 'wpml_object_id', $full->get_id(), 'product', false, 'ar' );
		if ( $full_ar > 0 ) {
			wp_update_post( array( 'ID' => $full_ar, 'post_title' => "{$tag} ببتيدات الكولاجين" ) );
			delete_post_meta( $full_ar, '_icl_lang_duplicate_of' );
			$s->track_post( $full_ar );
		}
		$check( $full_ar > 0, 'setup: the product has its Arabic record' );

		// A review written on the Arabic page is kept on the Arabic record: both languages count it, with the same
		// average (WooCommerce counts each record alone; ProductReviews::rating merges them).
		t116_review( $s, $full_ar, 'ليلى أحمد', 5, 'منتج رائع وأصلي.', '2026-09-01 10:00:00', array(), 'approve' );
		ProductReviews::forget();
		$en_rating = ProductReviews::rating( $full->get_id() );
		$ar_rating = ProductReviews::rating( $full_ar );
		$check( array( 'average' => 4.1, 'count' => 15 ) === $en_rating && $en_rating === $ar_rating, 'REV-04 the rating is the same in both languages: the English and the Arabic reviews together, 4.1 from 15 (' . wp_json_encode( array( $en_rating, $ar_rating ) ) . ')' );
		$only = $s->simple_product( "{$tag} Arabic only", '300', null );
		t116_english( $only->get_id() );
		$sitepress->make_duplicate( $only->get_id(), 'ar' );
		$only_ar = (int) apply_filters( 'wpml_object_id', $only->get_id(), 'product', false, 'ar' );
		delete_post_meta( $only_ar, '_icl_lang_duplicate_of' );
		$s->track_post( $only_ar );
		t116_review( $s, $only_ar, 'ليلى أحمد', 4, 'جيد.', '2026-11-01 10:00:00', array(), 'approve' );
		t116_review( $s, $only->get_id(), 'Held One', 1, 'Held.', '2026-11-02 10:00:00', array(), 'hold' );
		ProductReviews::forget();
		$check( array( 'average' => 4.0, 'count' => 1 ) === ProductReviews::rating( $only->get_id() ) && ProductReviews::rating( $only_ar ) === ProductReviews::rating( $only->get_id() ), 'REV-04 a product whose only review is in Arabic shows it in English too, and a held review is not counted (' . wp_json_encode( ProductReviews::rating( $only->get_id() ) ) . ')' );

		// A buyer of the lip balm, its order delivered (D-17), signed in by cookie in the browser: they write a review with
		// two photos on the page; it is held for approval with both (specs/020, specs/025).
		$buyer = (int) wp_insert_user( array( 'user_login' => "{$tag}-buyer", 'user_email' => "{$tag}-buyer@example.invalid", 'user_pass' => wp_generate_password( 24 ), 'role' => 'customer', 'first_name' => 'Salma', 'last_name' => 'Fathy' ) );
		$check( $buyer > 1, 'setup: a customer' );
		$s->track_user( $buyer );
		$order = wc_create_order( array( 'customer_id' => $buyer ) );
		$order->add_product( $bare, 1 );
		$order->set_billing_email( "{$tag}-buyer@example.invalid" );
		$order->calculate_totals();
		$order->save();
		$s->track_order( $order->get_id() );
		foreach ( array( 'processing', 'preparing', 'shipped', 'completed' ) as $step ) {
			$order = wc_get_order( $order->get_id() );
			$order->update_status( $step );
		}
		$check( null === \MizzeySite\Reviews\ReviewEligibility::refusal( $buyer, $bare->get_id() ), 'setup: the customer may review the lip balm (delivered)' );
		$s->on_finish( static function () use ( $bare ): void {
			foreach ( get_comments( array( 'post_id' => $bare->get_id(), 'status' => 'all' ) ) as $c ) {
				wp_delete_comment( $c->comment_ID, true );
			}
		} );
		$expire = time() + 2 * HOUR_IN_SECONDS;

		$plan   = array(
			'buyer'   => array(
				array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $buyer, $expire, 'auth' ) ),
				array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $buyer, $expire, 'logged_in' ) ),
			),
			'tag'     => $tag,
			'urls'    => array(
				'full'    => get_permalink( $full->get_id() ),
				'bare'    => get_permalink( $bare->get_id() ),
				'full_ar' => $full_ar > 0 ? (string) apply_filters( 'wpml_permalink', get_permalink( $full_ar ), 'ar' ) : '',
			),
			'related' => array_map( static fn ( int $id ): string => get_the_title( $id ), $related ),
			'bare'    => get_the_title( $bare->get_id() ),
			'held'    => array( 'id' => $hidden, 'text' => "{$tag} HELD REVIEW TEXT", 'photo' => ReviewPhotos::url( $hidden, 0 ) ),
			'spam'    => "{$tag} SPAM REVIEW TEXT",
			'reviews' => array(
				'count'   => 15,
				'five'    => 7,
				'average' => '4.1',
				'photos'  => 2,
				'arabic'  => 'وصل المنتج بسرعة',
				'long'    => 'Overall a good buy that I will order again.',
			),
			'specs'   => array( 'Brand', 'Form', 'Diet', 'Made in', 'Weight', 'SKU' ),
			'hidden'  => 'HIDDEN-42',
			'shots'   => get_temp_dir(),
		);
		$result = t116_browser( $plan );
		$check( empty( $result['error'] ), 'the browser ran' . ( empty( $result['error'] ) ? '' : ': ' . substr( (string) $result['error'], 0, 1500 ) ) );
		foreach ( (array) ( $result['checks'] ?? array() ) as $row ) {
			$check( true === $row['ok'], (string) $row['what'] );
		}
		$check( count( (array) ( $result['checks'] ?? array() ) ) >= 60, 'the browser made every check (' . count( (array) ( $result['checks'] ?? array() ) ) . ')' );
		foreach ( (array) ( $result['shots'] ?? array() ) as $shot ) {
			$s->note( '      screenshot: ' . $shot );
		}

		// The buyer's review, as the browser sent it: held, four stars, with its two photos.
		wp_cache_flush();
		$written = get_comments( array( 'post_id' => $bare->get_id(), 'status' => 'hold', 'user_id' => $buyer ) );
		$mine    = $written[0] ?? null;
		$check( 1 === count( $written ) && '4' === (string) get_comment_meta( $mine->comment_ID, 'rating', true ) && 2 === count( ReviewPhotos::photos( (int) $mine->comment_ID ) ), 'PDP-17, D-23 the review written on the page is held for approval with its rating and its two photos (' . count( $written ) . ', ' . ( $mine ? count( ReviewPhotos::photos( (int) $mine->comment_ID ) ) : 0 ) . ' photos)' );
		$check( 0 === ProductReviews::rating( $bare->get_id() )['count'], 'a held review is not counted' );
		// Nothing else the page did wrote a review, a rating or a photo.
		$check( '0' === (string) get_comment( $hidden )->comment_approved && 'spam' === (string) get_comment( $spam )->comment_approved, 'the held and the spam review are as they were' );
		return $ok;
	}
);

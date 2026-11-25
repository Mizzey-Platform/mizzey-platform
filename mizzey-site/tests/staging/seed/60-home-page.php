<?php
/**
 * Staging seed: a home page built from the section library, in English and Arabic, so a reviewer browsing staging
 * sees the designed home page (spec 066, slice 5).
 *
 * Every word and image here is sample content for the staging copy, not the client's: the words are the boards'
 * (C1-Home-Desktop, C1-Home-Phone-Arabic), the banner and section artwork is the boards' own (no brand name, label or
 * face; spec 066, Assumptions), and the team replaces both in the editor. It:
 *
 *  - adds the artwork in home-art/ to the media library once, found again by its seed name on a later run;
 *  - sets the site logo (logo-light.png) and gives the hero the logo for Indigo grounds (logo-indigo.png), never
 *    recoloured, read from MIZZEY_STAGING_BRAND_DIR on the server (default ~/mizzey-staging/brand/), never from this
 *    repository; without them the logo is skipped with one line in the output;
 *  - marks the sample categories, brands and collection "Show on the home page", in order, with the boards' images;
 *  - flags sample products New, Trending and Best Seller, so the product sections have something to show;
 *  - builds the English home page and its Arabic translation from the library's sections, and sets it as the front
 *    page.
 *
 * Idempotent: a second run finds what the first made and changes nothing else. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Storefront\HomeSections;

global $sitepress;
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
$sitepress->switch_lang( 'en', true );

// ---- The artwork, once ---------------------------------------------------------------------------------------------
$art = static function ( string $name, string $dir = '' ): int {
	$found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mizzey_seed_art', 'meta_value' => $name, 'suppress_filters' => true ) );
	if ( $found ) {
		return (int) $found[0];
	}
	$files = glob( ( '' !== $dir ? $dir : __DIR__ . '/home-art' ) . '/' . $name . '.*' );
	if ( ! $files ) {
		if ( '' !== $dir ) {
			return 0;
		}
		WP_CLI::error( "no artwork named {$name}" );
	}
	$copy = wp_tempnam( basename( $files[0] ) );
	copy( $files[0], $copy );
	$id = media_handle_sideload( array( 'name' => basename( $files[0] ), 'tmp_name' => $copy ), 0, 'Sample image (staging)' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( (int) $id, '_mizzey_seed_art', $name );
	return (int) $id;
};

// ---- The logo ------------------------------------------------------------------------------------------------------
// The brand identity is not public before launch, so the logo files are never in this repository: they wait on the
// server, in MIZZEY_STAGING_BRAND_DIR (default ~/mizzey-staging/brand/), named as below, copied there by hand. When
// they are not there, the logo is left as it is and the run says so.
// On the server the reset copies them into the staging layer's brand/ folder, which the container reads
// (staging_server.py, BRAND_FILES); on the developer's own staging copy they are read where they were put.
$brand_dir = (string) getenv( 'MIZZEY_STAGING_BRAND_DIR' );
if ( '' === $brand_dir ) {
	$brand_dir = is_dir( dirname( __DIR__, 2 ) . '/brand' ) ? dirname( __DIR__, 2 ) . '/brand' : rtrim( (string) getenv( 'HOME' ), '/' ) . '/mizzey-staging/brand';
}
$brand_dir = rtrim( $brand_dir, '/' );
$logo_files = array( 'logo-light' => 'the logo for light grounds (PNG)', 'logo-indigo' => 'the logo for Indigo grounds (PNG)' );
$logo_light  = $art( 'logo-light', $brand_dir );
$logo_indigo = $art( 'logo-indigo', $brand_dir );
if ( $logo_light && $logo_indigo ) {
	update_post_meta( $logo_light, '_wp_attachment_image_alt', 'Mizzey' );
	update_post_meta( $logo_indigo, '_wp_attachment_image_alt', 'Mizzey' );
	set_theme_mod( 'custom_logo', $logo_light );
	update_option( 'site_logo', $logo_light );
} else {
	WP_CLI::log( sprintf( 'logo skipped: put %s in %s', implode( ' and ', array_map( static fn ( string $n, string $d ): string => "{$n}.png ({$d})", array_keys( $logo_files ), $logo_files ) ), $brand_dir ) );
}

// ---- Terms on the home page ----------------------------------------------------------------------------------------
$show = static function ( string $taxonomy, string $slug, int $position, string $image ) use ( $art ): void {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	update_term_meta( $term->term_id, HomeSections::ON_HOME, 'yes' );
	update_term_meta( $term->term_id, HomeSections::POSITION, $position );
	if ( '' !== $image && ! (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) ) {
		update_term_meta( $term->term_id, 'thumbnail_id', $art( $image ) );
	}
};
$show( 'product_cat', 'sample-care', 1, 'category-beauty' );
$show( 'product_cat', 'sample-home', 2, 'category-home' );
$show( 'product_cat', 'sample-accessories', 3, 'category-electronics' );
$show( 'product_brand', 'sample-brand-north', 1, '' );
$show( 'product_brand', 'sample-brand-south', 2, '' );
$show( 'product_collection', 'sample-collection', 1, 'collection-morning' );

// ---- Badges on sample products, in both languages ------------------------------------------------------------------
$badges = array( 'trending' => array( 1, 2, 3, 4, 5, 6 ), 'new' => array( 7, 8, 9, 10, 11, 12 ), 'best-seller' => array( 2, 5, 8, 11 ) );
$flagged = 0;
foreach ( range( 1, 12 ) as $n ) {
	$want = array();
	foreach ( $badges as $badge => $numbers ) {
		if ( in_array( $n, $numbers, true ) ) {
			$want[] = $badge;
		}
	}
	$id = wc_get_product_id_by_sku( sprintf( 'DEMO-S-%03d', $n ) );
	$id = $id ? (int) apply_filters( 'wpml_object_id', $id, 'product', true, 'en' ) : 0;
	foreach ( array_unique( array_filter( array( $id, $id ? (int) apply_filters( 'wpml_object_id', $id, 'product', false, 'ar' ) : 0 ) ) ) as $record ) {
		$product = wc_get_product( $record );
		if ( $product instanceof WC_Product && (array) $product->get_meta( ProductFields::BADGES ) !== $want ) {
			$product->update_meta_data( ProductFields::BADGES, $want );
			$product->save();
			++$flagged;
		}
	}
}

// ---- The home page, from the library's sections, in each language --------------------------------------------------
// wp_insert_post() unslashes what it is given; the blocks' JSON escapes "<" as <, so it is slashed first.
$block = static fn ( string $name, array $attrs ): array => array( 'blockName' => 'mizzey/' . $name, 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
$slides = static function ( array $words ) use ( $art ): array {
	$images = array( 'hero-1-ribbon', 'hero-2-ribbon-indigo', 'hero-3-arches', 'hero-4-vitamins', 'hero-5-shelf', 'hero-6-boxes' );
	$tones  = array( 'light', 'dark', 'light', 'dark', 'dark', 'dark' );
	$out    = array();
	foreach ( $words as $i => list( $title, $line, $cta, $url ) ) {
		$out[] = array( 'imageId' => $art( $images[ $i ] ), 'imageAlt' => '', 'tone' => $tones[ $i ], 'title' => $title, 'line' => $line, 'ctaLabel' => $cta, 'ctaUrl' => $url );
	}
	return $out;
};
$shop = (string) wc_get_page_permalink( 'shop' );
$page = static function ( string $lang, array $w ) use ( $block, $slides, $art, $logo_indigo, $shop ): string {
	return serialize_blocks(
		array(
			$block( 'hero', array( 'variant' => 'slider', 'label' => $w['offers'], 'darkLogo' => $logo_indigo, 'slides' => $slides( $w['slides'] ) ) ),
			$block( 'trust-signals', array( 'label' => $w['why'], 'items' => $w['trust'] ) ),
			$block( 'category-shortcuts', array( 'heading' => $w['categories'], 'allLabel' => $w['all_categories'] ) ),
			$block( 'products', array( 'heading' => $w['trending'], 'source' => 'badge', 'badge' => 'trending', 'link' => $shop ) ),
			$block( 'featured-collections', array( 'heading' => $w['collections'] ) ),
			$block( 'products', array( 'heading' => $w['new'], 'source' => 'badge', 'badge' => 'new', 'link' => $shop ) ),
			$block( 'deals', array( 'heading' => $w['deals'], 'line' => $w['deals_line'], 'source' => 'badge', 'badge' => 'best-seller', 'link' => $shop ) ),
			$block( 'offer-banner', array( 'imageId' => $art( 'offer-cream' ), 'tag' => $w['offer'][0], 'heading' => $w['offer'][1], 'text' => $w['offer'][2], 'ctaLabel' => $w['offer'][3], 'ctaUrl' => $shop ) ),
			$block( 'brand-highlights', array( 'heading' => $w['brands'] ) ),
			$block( 'signup', array( 'heading' => $w['signup'][0], 'text' => $w['signup'][1], 'ctaLabel' => $w['signup'][2] ) ),
		)
	);
};
$en_words = array(
	'offers'         => 'Offers',
	'slides'         => array(
		array( 'The real thing,<br>delivered', 'Authentic products, guaranteed', 'Authenticity guarantee', $shop ),
		array( 'Originals,<br>well chosen', 'Beauty, health, sport and vitamins from brands you know', 'Shop new arrivals', $shop ),
		array( 'New in<br>Beauty', 'Cushions, balms and oils from the brands you ask for', 'Shop Beauty', $shop ),
		array( 'Vitamins week', 'Up to 25% off selected supplements', 'Shop vitamins', $shop ),
		array( 'Your wellness<br>shelf', 'Picked for quality, checked for authenticity', 'Explore', $shop ),
		array( 'Free delivery<br>over EGP 1,000', 'Across Egypt', 'Start shopping', $shop ),
	),
	'why'            => 'Why Mizzey',
	'trust'          => array(
		array( 'icon' => 'shield', 'title' => 'Authentic products, guaranteed', 'line' => 'Authenticity guarantee', 'url' => '' ),
		array( 'icon' => 'van', 'title' => 'Fast delivery', 'line' => 'Free delivery on orders over EGP 1,000', 'url' => '' ),
		array( 'icon' => 'returns', 'title' => 'Easy returns', 'line' => 'Return & refund policy', 'url' => '' ),
	),
	'categories'     => 'Shop by category',
	'all_categories' => 'All categories',
	'trending'       => 'Trending now',
	'collections'    => 'Featured collections',
	'new'            => 'New arrivals',
	'deals'          => 'Deals of the week',
	'deals_line'     => 'Up to 25% off this week',
	'offer'          => array( 'New', 'Fresh on the beauty shelf', 'This month’s arrivals, all original stock.', 'See what’s new' ),
	'brands'         => 'Shop by brand',
	'signup'         => array( 'Stay in touch', 'One short email when something worth knowing lands.', 'Sign up' ),
);
$ar_words = array(
	'offers'         => 'العروض',
	'slides'         => array(
		array( 'الأصلي،<br>حتى باب بيتك', 'منتجات أصلية بضمان ميزي', 'ضمان الأصالة', $shop ),
		array( 'منتجات أصلية،<br>مختارة بعناية', 'جمال وصحة ورياضة وفيتامينات من ماركات تعرفها', 'تسوّق أحدث المنتجات', $shop ),
		array( 'جديد<br>في الجمال', 'كوشن وبلسم وزيوت من الماركات التي تطلبها', 'تسوّق الجمال', $shop ),
		array( 'أسبوع الفيتامينات', 'خصم حتى 25% على مكملات مختارة', 'تسوّق الفيتامينات', $shop ),
		array( 'عافيتك<br>على رفّ واحد', 'مختارة للجودة ومضمونة الأصالة', 'اكتشف', $shop ),
		array( 'توصيل مجاني<br>فوق 1,000 ج.م', 'لكل أنحاء مصر', 'ابدأ التسوّق', $shop ),
	),
	'why'            => 'لماذا ميزي',
	'trust'          => array(
		array( 'icon' => 'shield', 'title' => 'منتجات أصلية مضمونة', 'line' => 'ضمان الأصالة', 'url' => '' ),
		array( 'icon' => 'van', 'title' => 'توصيل سريع', 'line' => 'توصيل مجاني للطلبات فوق 1,000 ج.م', 'url' => '' ),
		array( 'icon' => 'returns', 'title' => 'إرجاع سهل', 'line' => 'سياسة الإرجاع والاسترداد', 'url' => '' ),
	),
	'categories'     => 'تسوّق حسب القسم',
	'all_categories' => 'كل الأقسام',
	'trending'       => 'الأكثر رواجًا الآن',
	'collections'    => 'مجموعات مختارة',
	'new'            => 'وصل حديثًا',
	'deals'          => 'عروض الأسبوع',
	'deals_line'     => 'خصم حتى 25% هذا الأسبوع',
	'offer'          => array( 'جديد', 'وصل حديثًا لرفّ الجمال', 'أحدث ما وصل هذا الشهر، كلها منتجات أصلية.', 'شاهد الجديد' ),
	'brands'         => 'تسوّق حسب الماركة',
	'signup'         => array( 'ابقَ على تواصل', 'رسالة قصيرة واحدة عندما يصل ما يستحق أن تعرفه.', 'اشترك' ),
);

$find = static function ( string $lang ): int {
	$found = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mizzey_seed_home', 'meta_value' => $lang, 'suppress_filters' => true ) );
	return $found ? (int) $found[0] : 0;
};
$en = $find( 'en' );
if ( ! $en ) {
	$en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home', 'post_content' => wp_slash( $page( 'en', $en_words ) ) ), true );
	$sitepress->set_element_language_details( $en, 'post_page', null, 'en' );
	update_post_meta( $en, '_mizzey_seed_home', 'en' );
}
$ar = $find( 'ar' );
if ( ! $ar ) {
	$sitepress->switch_lang( 'ar', true );
	$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'الرئيسية', 'post_content' => wp_slash( $page( 'ar', $ar_words ) ) ), true );
	$sitepress->switch_lang( 'en', true );
	$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
	$sitepress->set_element_language_details( $ar, 'post_page', $trid, 'ar', 'en' );
	update_post_meta( $ar, '_mizzey_seed_home', 'ar' );
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $en );

WP_CLI::success( sprintf( 'home page %d (Arabic %d), logo %d, %d product records flagged', $en, $ar, $logo_light, $flagged ) );

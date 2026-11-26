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
 *    repository; without them the logo is skipped with one line in the output; and sets the site icon
 *    (site-icon.png, from the same folder) the same way. The boards draw the plain wordmark with its Arabic name and
 *    no line under it and no registered mark: logo-light.png is the brand pack's "Logo Arabic, Ink" (cropped) and
 *    logo-indigo.png its "Logo Arabic, Royal BG" (cropped), not the full logo;
 *  - takes the words the boards draw with brand names in them (the offer banner's line) from the private sample
 *    catalogue's `home_words` (MIZZEY_STAGING_SAMPLE_DIR, as 70-sample-catalog.php reads it), so no brand name is in
 *    this repository; without it the line stays brand-free;
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
	$id = media_handle_sideload( array( 'name' => basename( $files[0] ), 'tmp_name' => $copy ), 0, 'Staging image' );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( (int) $id, '_mizzey_seed_art', $name );
	return (int) $id;
};

// A picture an earlier run added keeps its file and takes the current title (the word "Sample" left the data).
foreach ( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_mizzey_seed_art', 'title' => 'Sample image (staging)', 'suppress_filters' => true ) ) as $renamed ) {
	wp_update_post( array( 'ID' => (int) $renamed, 'post_title' => 'Staging image' ) );
}

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

// ---- The site icon ------------------------------------------------------------------------------------------------
// The browser tab's and the phone's icon, WordPress's own site icon, from the same folder (site-icon.png, square, made
// from the brand's monogram for favicons). Added once and reused; without it the icon is left as it is.
$site_icon = $art( 'site-icon', $brand_dir );
if ( $site_icon ) {
	if ( (int) get_option( 'site_icon' ) !== $site_icon ) {
		update_option( 'site_icon', $site_icon );
	}
} else {
	WP_CLI::log( "site icon skipped: put site-icon.png in {$brand_dir}" );
}

// ---- Terms on the home page ----------------------------------------------------------------------------------------
$show = static function ( string $taxonomy, string $slug, int $position, string $image ) use ( $art ): void {
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	// Once the sample catalogue (step 70) has loaded its own terms, the placeholders stay off the home page.
	if ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 1, 'fields' => 'ids', 'meta_key' => '_mizzey_sample', 'meta_compare' => 'EXISTS' ) ) ) {
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

// ---- The policy pages the trust strip, the strip and the footer lead to (owner, 26 November 2026) -------------------
// The store's own pages at the inventory's routes (/authenticity-guarantee/, /shipping-policy/, /returns-and-refunds/,
// the same slugs under /ar/), made when missing. On the staging copy their words are plain ones in line with what the
// store says elsewhere (free delivery over EGP 1,000, delivery in 2 to 5 working days, returns within 14 days), written
// only while a page holds nothing or the "client input" placeholder; staff wording is never touched. The product
// page's return and authenticity links (WooCommerce > Settings > Products) point at them when they are not set.
$policy_text = array(
	'authenticity' => array(
		'en' => array( 'Authenticity guarantee', 'authenticity-guarantee', array(
			'Every product we sell is original. We buy from the brands themselves or from their authorised distributors, and we check every delivery before it reaches our shelves.',
			'If you have a doubt about an item you bought from us, contact us with your order number. We will look into it, and if an item is not genuine we will refund it in full.',
		) ),
		'ar' => array( 'ضمان الأصالة', array(
			'كل منتج نبيعه أصلي. نشتري من العلامات التجارية نفسها أو من موزعيها المعتمدين، ونفحص كل شحنة قبل أن تصل إلى رفوفنا.',
			'إذا كان لديك شك في منتج اشتريته منا، تواصل معنا برقم طلبك. سنراجع الأمر، وإذا لم يكن المنتج أصليًا سنرد لك ثمنه كاملًا.',
		) ),
	),
	'shipping'     => array(
		'en' => array( 'Shipping and delivery', 'shipping-policy', array(
			'We deliver across Egypt. Delivery is free on orders over EGP 1,000; below that, the delivery fee shows at checkout before you pay.',
			'Orders usually arrive within 2 to 5 working days. You will receive a message when your order is on its way.',
		) ),
		'ar' => array( 'الشحن والتوصيل', array(
			'نوصّل إلى جميع أنحاء مصر. التوصيل مجاني للطلبات فوق 1,000 ج.م، وما دون ذلك تظهر رسوم التوصيل عند إتمام الطلب قبل الدفع.',
			'تصل الطلبات عادة خلال 2 إلى 5 أيام عمل. ستصلك رسالة عندما يكون طلبك في الطريق.',
		) ),
	),
	'returns'      => array(
		'en' => array( 'Returns and refunds', 'returns-and-refunds', array(
			'You can return an item within 14 days of delivery if it is unused and in its original packaging. Contact us with your order number to arrange the return.',
			'Once we receive and check the item, we refund you to the way you paid.',
		) ),
		'ar' => array( 'الإرجاع والاسترداد', array(
			'يمكنك إرجاع المنتج خلال 14 يومًا من التوصيل إذا كان غير مستخدم وفي عبوته الأصلية. تواصل معنا برقم طلبك لترتيب الإرجاع.',
			'بعد أن نستلم المنتج ونفحصه، نرد لك المبلغ بطريقة الدفع نفسها.',
		) ),
	),
);
$paragraphs = static fn ( array $lines ): string => implode( '', array_map( static fn ( string $line ): string => "<!-- wp:paragraph -->\n<p>" . esc_html( $line ) . "</p>\n<!-- /wp:paragraph -->", $lines ) );
$policy     = array();
$placeholder = static fn ( int $id ): bool => '' === trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $id, 'raw' ) ) ) || false !== strpos( (string) get_post_field( 'post_content', $id, 'raw' ), 'is a client input' );
$policy_ids  = array();
foreach ( $policy_text as $key => $text ) {
	$slug = $text['en'][1];
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$en   = $page instanceof WP_Post ? (int) apply_filters( 'wpml_object_id', $page->ID, 'page', true, 'en' ) : 0;
	if ( $en <= 0 ) {
		$en = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $text['en'][0], 'post_name' => $slug ), true );
		$sitepress->set_element_language_details( $en, 'post_page', null, 'en' );
	}
	$ar = (int) apply_filters( 'wpml_object_id', $en, 'page', false, 'ar' );
	if ( $ar <= 0 ) {
		$sitepress->switch_lang( 'ar', true );
		$ar = (int) wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $text['ar'][0], 'post_name' => $slug ), true );
		$sitepress->switch_lang( 'en', true );
		$trid = (int) apply_filters( 'wpml_element_trid', null, $en, 'post_page' );
		$sitepress->set_element_language_details( $ar, 'post_page', $trid, 'ar', 'en' );
	}
	foreach ( array( 'en' => array( $en, $text['en'][2] ), 'ar' => array( $ar, $text['ar'][1] ) ) as $lang => list( $id, $lines ) ) {
		if ( $placeholder( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $paragraphs( $lines ) ) ) );
		}
	}
	$policy_ids[ $key ] = $en;
	$policy[ $key ]     = array(
		'en' => (string) get_permalink( $en ),
		'ar' => (string) apply_filters( 'wpml_permalink', (string) get_permalink( $ar ), 'ar' ),
	);
}
foreach ( array( 'mizzey_returns_page' => 'returns', 'mizzey_authenticity_page' => 'authenticity' ) as $option => $key ) {
	if ( (int) get_option( $option, 0 ) <= 0 ) {
		update_option( $option, $policy_ids[ $key ] );
	}
}

// The words with brand names in them, from the private sample catalogue when it is there (never from this repository).
$sample_dir = (string) getenv( 'MIZZEY_STAGING_SAMPLE_DIR' );
if ( '' === $sample_dir ) {
	$sample_dir = is_dir( dirname( __DIR__, 2 ) . '/sample-catalog' ) ? dirname( __DIR__, 2 ) . '/sample-catalog' : rtrim( (string) getenv( 'HOME' ), '/' ) . '/mizzey-staging/sample-catalog';
}
$sample_words = array();
if ( is_readable( rtrim( $sample_dir, '/' ) . '/catalog.json' ) ) {
	$sample       = json_decode( (string) file_get_contents( rtrim( $sample_dir, '/' ) . '/catalog.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$sample_words = is_array( $sample ) && is_array( $sample['home_words'] ?? null ) ? $sample['home_words'] : array();
}
$sample_word = static fn ( string $lang, string $key, string $fallback ): string => is_string( $sample_words[ $lang ][ $key ] ?? null ) && '' !== $sample_words[ $lang ][ $key ] ? (string) $sample_words[ $lang ][ $key ] : $fallback;

$page = static function ( string $lang, array $w ) use ( $block, $slides, $art, $logo_indigo, $shop ): string {
	return serialize_blocks(
		array(
			$block( 'hero', array( 'variant' => 'slider', 'label' => $w['offers'], 'showLogo' => true, 'darkLogo' => $logo_indigo, 'slides' => $slides( $w['slides'] ) ) ),
			$block( 'trust-signals', array( 'label' => $w['why'], 'items' => $w['trust'] ) ),
			$block( 'category-shortcuts', array( 'heading' => $w['categories'], 'allLabel' => $w['all_categories'] ) ),
			$block( 'products', array( 'heading' => $w['trending'], 'source' => 'badge', 'badge' => 'trending', 'link' => $shop ) ),
			$block( 'featured-collections', array( 'heading' => $w['collections'] ) ),
			$block( 'products', array( 'heading' => $w['new'], 'source' => 'badge', 'badge' => 'new', 'link' => $shop ) ),
			$block( 'deals', array( 'heading' => $w['deals'], 'line' => $w['deals_line'], 'source' => 'badge', 'badge' => 'best-seller', 'link' => $shop ) ),
			$block( 'offer-banner', array( 'imageId' => $art( 'offer-cream' ), 'tag' => $w['offer'][0], 'heading' => $w['offer'][1], 'text' => $w['offer'][2], 'ctaLabel' => $w['offer'][3], 'ctaUrl' => $shop ) ),
			$block( 'brand-highlights', array( 'heading' => $w['brands'], 'allLabel' => $w['all_brands'], 'allUrl' => '#' ) ),
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
		array( 'Vitamins week', 'Up to <strong>25%</strong> off selected supplements', 'Shop vitamins', $shop ),
		array( 'Your wellness<br>shelf', 'Picked for quality, checked for authenticity', 'Explore', $shop ),
		array( 'Free delivery<br>over EGP 1,000', 'Across Egypt', 'Start shopping', $shop ),
	),
	'why'            => 'Why Mizzey',
	'trust'          => array(
		array( 'icon' => 'shield', 'title' => 'Authentic products, guaranteed', 'line' => 'Authenticity guarantee', 'url' => $policy['authenticity']['en'] ),
		array( 'icon' => 'van', 'title' => 'Fast delivery', 'line' => 'Free delivery on orders over EGP 1,000', 'url' => $policy['shipping']['en'] ),
		array( 'icon' => 'returns', 'title' => 'Easy returns', 'line' => 'Return & refund policy', 'url' => $policy['returns']['en'] ),
	),
	'categories'     => 'Shop by category',
	'all_categories' => 'All categories',
	'trending'       => 'Trending now',
	'collections'    => 'Featured collections',
	'new'            => 'New arrivals',
	'deals'          => 'Deals of the week',
	'deals_line'     => 'Up to 25% off this week',
	'offer'          => array( 'New', 'Fresh on the beauty shelf', $sample_word( 'en', 'offer_text', 'This month’s arrivals, all original stock.' ), 'See what’s new' ),
	'brands'         => 'Shop by brand',
	'all_brands'     => 'All brands',
	'signup'         => array( 'Stay in touch', 'One short email when something worth knowing lands.', 'Sign up' ),
);
$ar_words = array(
	'offers'         => 'العروض',
	'slides'         => array(
		array( 'الأصلي،<br>حتى باب بيتك', 'منتجات أصلية بضمان ميزي', 'ضمان الأصالة', $shop ),
		array( 'منتجات أصلية،<br>مختارة بعناية', 'جمال وصحة ورياضة وفيتامينات من ماركات تعرفها', 'تسوّق أحدث المنتجات', $shop ),
		array( 'جديد<br>في الجمال', 'كوشن وبلسم وزيوت من الماركات التي تطلبها', 'تسوّق الجمال', $shop ),
		array( 'أسبوع الفيتامينات', 'خصم حتى <strong>25%</strong> على مكملات مختارة', 'تسوّق الفيتامينات', $shop ),
		array( 'عافيتك<br>على رفّ واحد', 'مختارة للجودة ومضمونة الأصالة', 'اكتشف', $shop ),
		array( 'توصيل مجاني<br>فوق 1,000 ج.م', 'لكل أنحاء مصر', 'ابدأ التسوّق', $shop ),
	),
	'why'            => 'لماذا ميزي',
	'trust'          => array(
		array( 'icon' => 'shield', 'title' => 'منتجات أصلية مضمونة', 'line' => 'ضمان الأصالة', 'url' => $policy['authenticity']['ar'] ),
		array( 'icon' => 'van', 'title' => 'توصيل سريع', 'line' => 'توصيل مجاني للطلبات فوق 1,000 ج.م', 'url' => $policy['shipping']['ar'] ),
		array( 'icon' => 'returns', 'title' => 'إرجاع سهل', 'line' => 'سياسة الإرجاع والاسترداد', 'url' => $policy['returns']['ar'] ),
	),
	'categories'     => 'تسوّق حسب القسم',
	'all_categories' => 'كل الأقسام',
	'trending'       => 'الأكثر رواجًا الآن',
	'collections'    => 'مجموعات مختارة',
	'new'            => 'وصل حديثًا',
	'deals'          => 'عروض الأسبوع',
	'deals_line'     => 'خصم حتى 25% هذا الأسبوع',
	'offer'          => array( 'جديد', 'وصل حديثًا لرفّ الجمال', $sample_word( 'ar', 'offer_text', 'أحدث ما وصل هذا الشهر، كلها منتجات أصلية.' ), 'شاهد الجديد' ),
	'brands'         => 'تسوّق حسب الماركة',
	'all_brands'     => 'كل الماركات',
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
// The home pages an earlier run made: trust-strip links that still lead nowhere now lead to the policy pages.
$by_icon = static fn ( string $lang ): array => array( 'shield' => $policy['authenticity'][ $lang ], 'van' => $policy['shipping'][ $lang ], 'returns' => $policy['returns'][ $lang ] );
foreach ( array( 'en' => $en, 'ar' => $ar ) as $lang => $id ) {
	$blocks  = parse_blocks( (string) get_post_field( 'post_content', $id, 'raw' ) );
	$changed = false;
	foreach ( $blocks as &$b ) {
		if ( 'mizzey/trust-signals' !== ( $b['blockName'] ?? '' ) ) {
			continue;
		}
		foreach ( (array) ( $b['attrs']['items'] ?? array() ) as $i => $row ) {
			$to = $by_icon( $lang )[ (string) ( $row['icon'] ?? '' ) ] ?? '';
			if ( '' !== $to && in_array( (string) ( $row['url'] ?? '' ), array( '', '#' ), true ) ) {
				$b['attrs']['items'][ $i ]['url'] = $to;
				$changed                          = true;
			}
		}
	}
	unset( $b );
	if ( $changed ) {
		wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( serialize_blocks( $blocks ) ) ) );
	}
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $en );

WP_CLI::success( sprintf( 'home page %d (Arabic %d), logo %d, site icon %d, %d product records flagged', $en, $ar, $logo_light, $site_icon, $flagged ) );

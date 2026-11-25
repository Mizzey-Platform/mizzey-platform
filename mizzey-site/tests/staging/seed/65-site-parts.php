<?php
/**
 * Staging seed: the header's menu, the footer's columns, the announcement strip and a sample WhatsApp number, in
 * English and Arabic, so a reviewer browsing staging sees the designed header and footer filled in (spec 066, slices
 * 2 and 3). Runs after 60-home-page.php, which sets the logo.
 *
 * Every value is sample content for the staging copy, not the client's: the links and words are the boards'
 * (C1-Global, C1-Home-Desktop, C1-Home-Phone-Arabic); a link with no page of its own on staging points at "#"; the
 * phone, email and address are marked "(sample)". The team replaces all of it in the Site Editor. It:
 *
 *  - makes the menus as WordPress navigation menus, one per language, the Arabic one a WPML translation of the
 *    English: the header's category row (slug `mizzey-header`), and the footer's Shop, Help, About, Legal and
 *    Customer care columns;
 *  - rebuilds the stored header and footer template parts from the theme's current parts/*.html on every run, adding
 *    only the menus, the strip's message and the logo for Indigo grounds, as staff would set them in the Site Editor
 *    (a stored part would otherwise freeze the markup of the release it was saved under);
 *  - sets a sample WhatsApp number (+20 100 000 0000) and greeting (specs/059).
 *
 * Idempotent: a second run finds what the first made by its seed name and changes nothing else. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

use MizzeySite\Storefront\ContactLinks;
use MizzeySite\Storefront\HeaderMenu;

global $sitepress;
$sitepress->switch_lang( 'en', true );

// ---- Links ---------------------------------------------------------------------------------------------------------
$category = static function ( string $slug, string $lang ): array {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term instanceof WP_Term ) {
		return array( 0, '#' );
	}
	$id  = (int) apply_filters( 'wpml_object_id', $term->term_id, 'product_cat', true, $lang );
	$url = get_term_link( $id, 'product_cat' );
	return array( $id, is_wp_error( $url ) ? '#' : (string) $url );
};
$page_url = static function ( int $id ): string {
	return $id > 0 && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '#';
};
$privacy = $page_url( (int) get_option( 'wp_page_for_privacy_policy' ) );
$terms   = $page_url( function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0 );
$shop    = (string) wc_get_page_permalink( 'shop' );

/**
 * A navigation-link block. $item: [label, url] or [label, url, term id] for a category, or [label, url, 0, 'offer'].
 */
$link = static function ( array $item ): string {
	$attrs = array( 'label' => $item[0], 'url' => $item[1], 'kind' => 'custom', 'isTopLevelLink' => true );
	if ( ! empty( $item[2] ) ) {
		$attrs = array( 'label' => $item[0], 'type' => 'product_cat', 'id' => (int) $item[2], 'url' => $item[1], 'kind' => 'taxonomy', 'isTopLevelLink' => true );
	}
	if ( ! empty( $item[3] ) ) {
		$attrs['className'] = 'is-style-' . HeaderMenu::OFFER_STYLE;
	}
	return serialize_block( array( 'blockName' => 'core/navigation-link', 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
};

/**
 * A navigation menu in each language, found again by its slug; the Arabic one is the English one's translation.
 *
 * @param array{en:array{0:string,1:list<array>},ar:array{0:string,1:list<array>}} $menu Title and links per language.
 * @return array{en:int,ar:int}
 */
$nav = static function ( string $slug, array $menu ) use ( $link, $sitepress ): array {
	$ids = array();
	foreach ( array( 'en', 'ar' ) as $lang ) {
		$name  = 'en' === $lang ? $slug : $slug . '-ar';
		$found = get_posts( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'name' => $name, 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
		if ( $found ) {
			$ids[ $lang ] = (int) $found[0];
			continue;
		}
		$sitepress->switch_lang( $lang, true );
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_name'    => $name,
				'post_title'   => $menu[ $lang ][0],
				'post_content' => wp_slash( implode( "\n", array_map( $link, $menu[ $lang ][1] ) ) ),
			),
			true
		);
		$sitepress->switch_lang( 'en', true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		$ids[ $lang ] = (int) $id;
		if ( 'en' === $lang ) {
			$sitepress->set_element_language_details( $ids['en'], 'post_wp_navigation', null, 'en' );
		} else {
			$trid = (int) apply_filters( 'wpml_element_trid', null, $ids['en'], 'post_wp_navigation' );
			$sitepress->set_element_language_details( $ids['ar'], 'post_wp_navigation', $trid, 'ar', 'en' );
		}
	}
	return $ids;
};

$cats = array( 'sample-care', 'sample-home', 'sample-accessories' );
$cat_links = static function ( string $lang ) use ( $cats, $category ): array {
	$out = array();
	foreach ( $cats as $slug ) {
		list( $id, $url ) = $category( $slug, $lang );
		$term = $id ? get_term( $id, 'product_cat' ) : null;
		if ( $term instanceof WP_Term ) {
			$out[] = array( html_entity_decode( $term->name ), $url, $id );
		}
	}
	return $out;
};

// ---- The header's category row ---------------------------------------------------------------------------------------
$header = $nav(
	HeaderMenu::SLUG,
	array(
		'en' => array( 'Mizzey header', array_merge( $cat_links( 'en' ), array( array( 'Offers', $shop, 0, 'offer' ), array( 'Brands', '#' ) ) ) ),
		'ar' => array( 'قائمة ميزي', array_merge( $cat_links( 'ar' ), array( array( 'العروض', $shop, 0, 'offer' ), array( 'الماركات', '#' ) ) ) ),
	)
);

// ---- The footer's columns --------------------------------------------------------------------------------------------
$shop_col = $nav( 'mizzey-footer-shop', array( 'en' => array( 'Shop', $cat_links( 'en' ) ), 'ar' => array( 'تسوّق', $cat_links( 'ar' ) ) ) );
$help_col = $nav(
	'mizzey-footer-help',
	array(
		'en' => array( 'Help', array( array( 'Contact us', '#' ), array( 'Help & FAQ', '#' ), array( 'Shipping policy', '#' ), array( 'Returns and refunds', '#' ) ) ),
		'ar' => array( 'المساعدة', array( array( 'تواصل معنا', '#' ), array( 'المساعدة والأسئلة الشائعة', '#' ), array( 'سياسة الشحن', '#' ), array( 'الإرجاع والاسترداد', '#' ) ) ),
	)
);
$about_col = $nav(
	'mizzey-footer-about',
	array(
		'en' => array( 'About', array( array( 'About us', '#' ), array( 'Authenticity guarantee', '#' ) ) ),
		'ar' => array( 'عن ميزي', array( array( 'من نحن', '#' ), array( 'ضمان الأصالة', '#' ) ) ),
	)
);
$legal_col = $nav(
	'mizzey-footer-legal',
	array(
		'en' => array( 'Legal', array( array( 'Privacy policy', $privacy ), array( 'Terms and conditions', $terms ), array( 'Cookie preferences', '#cookie-preferences' ) ) ),
		'ar' => array( 'قانوني', array( array( 'سياسة الخصوصية', $privacy ), array( 'الشروط والأحكام', $terms ), array( 'تفضيلات ملفات تعريف الارتباط', '#cookie-preferences' ) ) ),
	)
);
$care_col = $nav(
	'mizzey-footer-care',
	array(
		'en' => array( 'Customer care', array( array( '+20 100 000 0000 (sample)', 'tel:+201000000000' ), array( 'hello@example.com (sample)', 'mailto:hello@example.com' ), array( '12 Sample Street, Cairo (sample)', '#' ) ) ),
		'ar' => array( 'خدمة العملاء', array( array( '+20 100 000 0000 (عيّنة)', 'tel:+201000000000' ), array( 'hello@example.com (عيّنة)', 'mailto:hello@example.com' ), array( '12 شارع تجريبي، القاهرة (عيّنة)', '#' ) ) ),
	)
);

// ---- The WhatsApp number (specs/059): a sample, never a real line ----------------------------------------------------
if ( '' === (string) get_option( ContactLinks::NUMBER, '' ) ) {
	update_option( ContactLinks::NUMBER, '+20 100 000 0000' );
	update_option( ContactLinks::MESSAGE . 'en', 'Hello Mizzey (sample number on the staging copy)' );
	update_option( ContactLinks::MESSAGE . 'ar', 'مرحبًا ميزي (رقم تجريبي في نسخة العرض)' );
}

// ---- The header and footer template parts, as staff would save them in the Site Editor -------------------------------
$logo_indigo = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mizzey_seed_art', 'meta_value' => 'logo-indigo', 'suppress_filters' => true ) );
$logo_indigo = $logo_indigo ? (int) $logo_indigo[0] : 0;
$theme       = get_stylesheet();
// The attributes this seed sets, by block; everything else comes from the theme's own part files.
$seeded = array(
	'mizzey/announcement-strip' => array(
		'variant'  => 'fixed',
		'messages' => array(
			'en' => array( array( 'text' => 'Free delivery on orders over EGP 1,000', 'label' => 'See details', 'url' => '#' ) ),
			'ar' => array( array( 'text' => 'توصيل مجاني للطلبات فوق 1,000 ج.م', 'label' => 'التفاصيل', 'url' => '#' ) ),
		),
	),
	'mizzey/site-header'        => array( 'menu' => $header['en'], 'darkLogo' => $logo_indigo ),
	'mizzey/site-footer'        => array(
		'style'    => 'indigo',
		'darkLogo' => $logo_indigo,
		'columns'  => array(
			array( 'menus' => $shop_col, 'wide' => true ),
			array( 'menus' => $help_col ),
			array( 'menus' => $about_col ),
			array( 'menus' => $legal_col, 'under' => true ),
		),
		'care'     => $care_col,
	),
);

/*
 * A stored template part freezes its markup: a later release's change to the theme's part would not show on staging.
 * So every run rebuilds the stored header and footer from the theme's current parts/*.html and adds only the
 * attributes above. Run on every deploy (the release's reset reseeds), the stored parts follow the theme.
 */
$saved = array();
foreach ( array( 'header', 'footer' ) as $slug ) {
	$file = get_theme_file_path( "parts/{$slug}.html" );
	if ( ! is_file( $file ) ) {
		WP_CLI::warning( "the theme has no parts/{$slug}.html; the {$slug} is left as it is" );
		continue;
	}
	$blocks = parse_blocks( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	foreach ( $blocks as $i => $b ) {
		if ( isset( $seeded[ $b['blockName'] ?? '' ] ) ) {
			$blocks[ $i ]['attrs'] = array_merge( (array) $b['attrs'], $seeded[ $b['blockName'] ] );
		}
	}
	$content  = serialize_blocks( $blocks );
	$existing = get_posts(
		array(
			'post_type'        => 'wp_template_part',
			'post_status'      => array( 'publish', 'draft' ),
			'name'             => $slug,
			'numberposts'      => 1,
			'suppress_filters' => true,
			'tax_query'        => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $theme ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$args = array( 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => ucfirst( $slug ), 'post_content' => wp_slash( $content ) );
	$id   = $existing ? wp_update_post( array( 'ID' => $existing[0]->ID ) + $args, true ) : wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	wp_set_post_terms( (int) $id, array( $theme ), 'wp_theme' );
	wp_set_post_terms( (int) $id, array( $slug ), 'wp_template_part_area' );
	update_post_meta( (int) $id, '_mizzey_seed_part', '1' );
	$saved[] = $slug;
}

WP_CLI::success( sprintf( 'menus: header %d, footer %d columns; template parts saved: %s', $header['en'], 4, implode( ', ', $saved ) ?: 'none' ) );

<?php
/**
 * Staging seed: the header's menu, the footer's columns, the announcement strip and a sample WhatsApp number, in
 * English and Arabic, so a reviewer browsing staging sees the designed header and footer filled in (spec 066, slices
 * 2 and 3). Runs after 60-home-page.php, which sets the logo.
 *
 * Every value is sample content for the staging copy, not the client's: the links and words are the boards'
 * (C1-Global, C1-Home-Desktop, C1-Home-Phone, C1-Home-Phone-Arabic); a link with no page of its own on staging points
 * at "#"; the phone (+20 100 000 0000), email (hello@mizzey.com) and address (Cairo, Egypt) are plain demo values and
 * carry no label, as the owner asked on 26 November 2026 (no word "sample" in the data; a link's description still
 * prints as its tag when staff set one). The team replaces all of it in the Site Editor. It:
 *
 *  - makes the menus as WordPress navigation menus, one per language, the Arabic one a WPML translation of the
 *    English: the header's category row (slug `mizzey-header`: the first six categories, then Offers and Brands, as
 *    C1-Home-Desktop draws it), and the footer's Shop (every top category, twelve on the boards), Help, About, Legal
 *    and Customer care columns; each Arabic link carries the Arabic category's own name;
 *  - sets the header's Help link and the footer's legal line in each language (C1-Home-Desktop, C1-Home-Phone);
 *  - rebuilds the stored header and footer template parts from the theme's current parts/*.html on every run, adding
 *    only the menus, the strip's message and the logo for Indigo grounds, as staff would set them in the Site Editor
 *    (a stored part would otherwise freeze the markup of the release it was saved under);
 *  - sets a sample WhatsApp number (+20 100 000 0000) and greeting (specs/059);
 *  - links the category row and the footer's Shop column to the sample catalogue's top categories when step 70 has
 *    loaded them (the staging tool runs it first), else to step 20's placeholder categories; a menu this step wrote is
 *    brought up to date when its links change, and one staff have edited since is kept.
 *
 * Idempotent: a second run finds what the first made by its seed name and changes nothing else. Staging only.
 *
 * @package MizzeySite\Tests\Staging
 */

defined( 'ABSPATH' ) || exit;
defined( 'MIZZEY_STAGING' ) && MIZZEY_STAGING || WP_CLI::error( 'staging only' );

use MizzeySite\Storefront\ContactLinks;
use MizzeySite\Storefront\HeaderMenu;
use MizzeySite\Storefront\HomeSections;

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
// The store's policy pages (60-home-page.php makes them when missing), in each language; '#' when there is none.
$policy = static function ( string $key, string $lang ): string {
	$slug = array( 'authenticity' => 'authenticity-guarantee', 'shipping' => 'shipping-policy', 'returns' => 'returns-and-refunds' )[ $key ];
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$id   = $page instanceof WP_Post ? (int) apply_filters( 'wpml_object_id', $page->ID, 'page', false, $lang ) : 0;
	return $id > 0 && 'publish' === get_post_status( $id ) ? (string) apply_filters( 'wpml_permalink', (string) get_permalink( $id ), $lang ) : '#';
};

/**
 * A navigation-link block. $item: [label, url] or [label, url, term id] for a category, or [label, url, 0, 'offer'],
 * or [label, url, 0, '', description] for a link with a tag under it.
 */
$link = static function ( array $item ): string {
	$attrs = array( 'label' => $item[0], 'url' => $item[1], 'kind' => 'custom', 'isTopLevelLink' => true );
	if ( ! empty( $item[2] ) ) {
		$attrs = array( 'label' => $item[0], 'type' => 'product_cat', 'id' => (int) $item[2], 'url' => $item[1], 'kind' => 'taxonomy', 'isTopLevelLink' => true );
	}
	if ( ! empty( $item[3] ) ) {
		$attrs['className'] = 'is-style-' . HeaderMenu::OFFER_STYLE;
	}
	if ( ! empty( $item[4] ) ) {
		$attrs['description'] = $item[4];
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
		$want  = implode( "\n", array_map( $link, $menu[ $lang ][1] ) );
		if ( $found ) {
			$ids[ $lang ] = (int) $found[0];
			// Brought up to date when its links change (the sample catalogue's categories arriving), unless staff
			// changed it since this step wrote it: a menu edited in the Site Editor is kept. One with no record is ours.
			$held = (string) get_post_field( 'post_content', $ids[ $lang ] );
			$ours = (string) get_post_meta( $ids[ $lang ], '_mizzey_seed_nav', true );
			if ( $held !== $want && ( '' === $ours || md5( $held ) === $ours ) ) {
				wp_update_post( array( 'ID' => $ids[ $lang ], 'post_content' => wp_slash( $want ) ) );
				$held = (string) get_post_field( 'post_content', $ids[ $lang ] );
				$ours = '';
			}
			if ( '' === $ours ) {
				update_post_meta( $ids[ $lang ], '_mizzey_seed_nav', md5( $held ) );
			}
			continue;
		}
		$sitepress->switch_lang( $lang, true );
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_name'    => $name,
				'post_title'   => $menu[ $lang ][0],
				'post_content' => wp_slash( $want ),
			),
			true
		);
		$sitepress->switch_lang( 'en', true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		$ids[ $lang ] = (int) $id;
		update_post_meta( $ids[ $lang ], '_mizzey_seed_nav', md5( (string) get_post_field( 'post_content', $ids[ $lang ] ) ) );
		if ( 'en' === $lang ) {
			$sitepress->set_element_language_details( $ids['en'], 'post_wp_navigation', null, 'en' );
		} else {
			$trid = (int) apply_filters( 'wpml_element_trid', null, $ids['en'], 'post_wp_navigation' );
			$sitepress->set_element_language_details( $ids['ar'], 'post_wp_navigation', $trid, 'ar', 'en' );
		}
	}
	return $ids;
};

// The sample catalogue's top categories (70-sample-catalog.php marks the terms it makes), in their home page order,
// when it has been loaded; otherwise the placeholder categories of step 20.
$cats = array();
foreach ( (array) get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => 0, 'meta_query' => array( array( 'key' => '_mizzey_sample', 'compare' => 'EXISTS' ) ) ) ) as $term ) {
	if ( ! $term instanceof WP_Term ) {
		continue;
	}
	$lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => (int) $term->term_taxonomy_id, 'element_type' => 'product_cat' ) );
	if ( in_array( $lang, array( null, 'en' ), true ) ) {
		$cats[ $term->slug ] = array( (int) get_term_meta( $term->term_id, HomeSections::POSITION, true ) ?: PHP_INT_MAX, $term->name );
	}
}
asort( $cats );
$cats = $cats ? array_keys( $cats ) : array( 'sample-care', 'sample-home', 'sample-accessories' );
$cat_links = static function ( string $lang, int $limit = 0 ) use ( $cats, $category ): array {
	$out = array();
	foreach ( $limit > 0 ? array_slice( $cats, 0, $limit ) : $cats as $slug ) {
		list( $id, $url ) = $category( $slug, $lang );
		// The term with exactly this id: under English, WPML would hand back the English term, and the Arabic menu would
		// read the English names (staging review, 25 November 2026).
		add_filter( 'wpml_disable_term_adjust_id', '__return_true' );
		$term = $id ? get_term( $id, 'product_cat' ) : null;
		remove_filter( 'wpml_disable_term_adjust_id', '__return_true' );
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
		'en' => array( 'Mizzey header', array_merge( $cat_links( 'en', 6 ), array( array( 'Offers', $shop, 0, 'offer' ), array( 'Brands', '#' ) ) ) ),
		'ar' => array( 'قائمة ميزي', array_merge( $cat_links( 'ar', 6 ), array( array( 'العروض', $shop, 0, 'offer' ), array( 'الماركات', '#' ) ) ) ),
	)
);

// ---- The footer's columns --------------------------------------------------------------------------------------------
$shop_col = $nav( 'mizzey-footer-shop', array( 'en' => array( 'Shop', $cat_links( 'en' ) ), 'ar' => array( 'تسوّق', $cat_links( 'ar' ) ) ) );
$help_col = $nav(
	'mizzey-footer-help',
	array(
		'en' => array( 'Help', array( array( 'Contact us', '#' ), array( 'Help & FAQ', '#' ), array( 'Shipping policy', $policy( 'shipping', 'en' ) ), array( 'Returns and refunds', $policy( 'returns', 'en' ) ) ) ),
		'ar' => array( 'المساعدة', array( array( 'تواصل معنا', '#' ), array( 'المساعدة والأسئلة الشائعة', '#' ), array( 'سياسة الشحن', $policy( 'shipping', 'ar' ) ), array( 'الإرجاع والاسترداد', $policy( 'returns', 'ar' ) ) ) ),
	)
);
$about_col = $nav(
	'mizzey-footer-about',
	array(
		'en' => array( 'About', array( array( 'About us', '#' ), array( 'Authenticity guarantee', $policy( 'authenticity', 'en' ) ) ) ),
		'ar' => array( 'عن ميزي', array( array( 'من نحن', '#' ), array( 'ضمان الأصالة', $policy( 'authenticity', 'ar' ) ) ) ),
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
		'en' => array( 'Customer care', array( array( '+20 100 000 0000', 'tel:+201000000000' ), array( 'hello@mizzey.com', 'mailto:hello@mizzey.com' ), array( 'Cairo, Egypt', '#' ) ) ),
		'ar' => array( 'خدمة العملاء', array( array( '+20 100 000 0000', 'tel:+201000000000' ), array( 'hello@mizzey.com', 'mailto:hello@mizzey.com' ), array( 'القاهرة، مصر', '#' ) ) ),
	)
);

// ---- The WhatsApp number (specs/059): a demo number, never a real line ------------------------------------------------
if ( '' === (string) get_option( ContactLinks::NUMBER, '' ) ) {
	update_option( ContactLinks::NUMBER, '+20 100 000 0000' );
}
// The greeting, without the "sample" note an earlier run wrote (26 November 2026); one staff changed is kept.
foreach ( array( 'en' => array( 'Hello Mizzey (sample number on the staging copy)', 'Hello Mizzey' ), 'ar' => array( 'مرحبًا ميزي (رقم تجريبي في نسخة العرض)', 'مرحبًا ميزي' ) ) as $lang => list( $old, $new ) ) {
	$held = (string) get_option( ContactLinks::MESSAGE . $lang, '' );
	if ( '' === $held || $old === $held ) {
		update_option( ContactLinks::MESSAGE . $lang, $new );
	}
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
			'en' => array( array( 'text' => 'Free delivery on orders over EGP 1,000', 'label' => 'See details', 'url' => $policy( 'shipping', 'en' ) ) ),
			'ar' => array( array( 'text' => 'توصيل مجاني للطلبات فوق 1,000 ج.م', 'label' => 'التفاصيل', 'url' => $policy( 'shipping', 'ar' ) ) ),
		),
	),
	'mizzey/site-header'        => array( 'menu' => $header['en'], 'darkLogo' => $logo_indigo, 'helpUrl' => '#' ),
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
		'legal'    => array(
			'en' => '© 2026 Mizzey. Mizzey is a registered trademark.',
			'ar' => '© 2026 ميزي. ميزي علامة تجارية مسجلة.',
		),
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

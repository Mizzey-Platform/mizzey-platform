<?php
/**
 * Clean-baseline configuration for the product-cost scenarios, run by reset-runtime.sh after a fresh WordPress
 * install. Uses the plugins' own APIs, never direct table writes:
 *  - WooCommerce: Egypt, EGP, HPOS on.
 *  - WPML: English default, Arabic active, setup completed through WPML_Installation (the setup wizard's steps),
 *    "Translate Everything" off, so translations are authored separately (US-16-03).
 * Prints a JSON summary. Disposable runtime only.
 *
 * @package MizzeySite\Tests\Integration
 */

update_option( 'woocommerce_default_country', 'EG:C' );
update_option( 'woocommerce_currency', 'EGP' );
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

global $sitepress;
$setup = wpml_get_setup_instance();
$setup->finish_step1( 'en' );
$setup->finish_step2( array( 'en', 'ar' ) );
$setup->finish_installation();
$sitepress->set_default_language( 'en' );
$setup_option = (array) get_option( 'WPML(setup)', array() );
$setup_option['translate-everything'] = false;
update_option( 'WPML(setup)', $setup_option );

// Products translatable, as WCML's setup wizard sets them (WCML_Setup::save_product_translation_mode, "translate",
// not "display as translated"), plus variations, categories and tags.
$helper = wpml_load_settings_helper();
foreach ( array( 'product', 'product_variation' ) as $type ) {
	$helper->set_post_type_translatable( $type );
	$helper->set_post_type_translation_unlocked_option( $type, false );
}
foreach ( array( 'product_cat', 'product_tag' ) as $tax ) {
	$helper->set_taxonomy_translatable( $tax );
	$helper->set_taxonomy_translation_unlocked_option( $tax, false );
}

// WCML: mark its own setup as done, keeping its defaults (no multi-currency).
$wcml = (array) get_option( '_wcml_settings', array() );
$wcml['set_up']             = 1;
$wcml['set_up_wizard_run']  = 1;
$wcml['enable_multi_currency'] = 0;
update_option( '_wcml_settings', $wcml );

// Arabic records for the WooCommerce system pages (#241, clarification C-2). Without them an Arabic storefront
// cannot resolve its own cart or checkout address: /ar/cart/ falls back to the English record. The page RECORDS
// and their per-language assignment are platform configuration; their content and interface belong to the
// storefront slices and are deliberately not touched here.
//
// A11's invariant is applied: every source page already exists, and the translations are created afterwards in
// their own pass. Creating and translating in one interleaved pass is the measured trigger for translation-group
// corruption.
$system_pages = array();
foreach ( array( 'shop', 'cart', 'checkout', 'myaccount' ) as $slug ) {
	$id = (int) get_option( 'woocommerce_' . $slug . '_page_id' );
	if ( $id > 0 && 'page' === get_post_type( $id ) ) {
		$system_pages[ $slug ] = $id;
	}
}

$translated = array();
foreach ( $system_pages as $slug => $source_id ) {
	$trid = $sitepress->get_element_trid( $source_id, 'post_page' );
	if ( ! $trid ) {
		continue;
	}
	$existing = $sitepress->get_element_translations( $trid, 'post_page' );
	if ( isset( $existing['ar'] ) ) {
		$translated[ $slug ] = (int) $existing['ar']->element_id;
		continue;
	}

	// The Arabic record carries the English title at the baseline. Arabic page copy is content work, owned by
	// the storefront slices, and inventing it here would be putting words in the client's mouth.
	//
	// The language is switched BEFORE the insert. WPML registers a new post under the current language at insert
	// time, and its slug-uniqueness filter only permits a shared slug when it knows the post is a translation.
	// Inserting first and assigning the language afterwards gets the slug suffixed to `shop-2`, and `/ar/shop/`
	// then answers 301 to the canonical URL. Measured, not predicted: the first run of t21 caught it.
	$previous = $sitepress->get_current_language();
	$sitepress->switch_lang( 'ar', true );
	$ar_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => get_the_title( $source_id ),
			'post_name'    => get_post_field( 'post_name', $source_id ),
			'post_content' => get_post_field( 'post_content', $source_id ),
		),
		true
	);
	$sitepress->switch_lang( $previous, true );
	if ( is_wp_error( $ar_id ) ) {
		continue;
	}
	// Register the Arabic record in the source's translation group, with the English record as source.
	$sitepress->set_element_language_details(
		$ar_id,
		'post_page',
		$trid,
		'ar',
		$sitepress->get_language_for_element( $source_id, 'post_page' )
	);
	$translated[ $slug ] = (int) $ar_id;
}

// Assign the Arabic pages to WooCommerce's own options per language, so the Arabic storefront addresses its own
// records. WPML keeps a per-language copy of an option it is told to translate; WCML already registers the
// WooCommerce page options, so writing the option while the Arabic language is current is what sets it.
if ( $translated ) {
	$previous_language = $sitepress->get_current_language();
	$sitepress->switch_lang( 'ar', true );
	foreach ( $translated as $slug => $ar_id ) {
		update_option( 'woocommerce_' . $slug . '_page_id', $ar_id );
	}
	$sitepress->switch_lang( $previous_language, true );
	foreach ( $system_pages as $slug => $source_id ) {
		update_option( 'woocommerce_' . $slug . '_page_id', $source_id );
	}
}

// ---------------------------------------------------------------------------------------------------------
// #242 information architecture: the contracted page inventory, the endpoint slugs and the taxonomies.
//
// Only the rows that genuinely need a page record get one. IA-02, IA-03, IA-06 and IA-07 are WooCommerce
// archives and singulars; IA-10 and IA-14 to IA-20 are the account and checkout experience, which is endpoints
// plus the My Account base page. Creating pages for those would give one screen two managed routes, which
// guardrail G-1 forbids.
//
// Page bodies are placeholders. Copy, category names, collection names and marketing content are client input
// and are not invented here.
//
// A11's invariant throughout: every source record is created first, and the Arabic counterparts afterwards in
// their own pass, with the language switched BEFORE insert so WPML does not suffix the slug.
// ---------------------------------------------------------------------------------------------------------

/**
 * The pages #242 creates, slug => array( title, register id ).
 *
 * Reusing an existing record where WordPress or WooCommerce already made one, rather than adding a duplicate.
 */
$ia_pages = array(
	'home'                   => array( 'Home', 'IA-01' ),
	'wishlist'               => array( 'Wishlist', 'IA-11' ),
	'track-your-order'       => array( 'Track your order', 'IA-21' ),
	'about'                  => array( 'About', 'IA-24' ),
	'contact-us'             => array( 'Contact us', 'IA-25' ),
	'help'                   => array( 'Help and FAQ', 'IA-26' ),
	'authenticity-guarantee' => array( 'Authenticity guarantee', 'IA-27' ),
	'shipping-policy'        => array( 'Shipping policy', 'IA-28' ),
	'returns-and-refunds'    => array( 'Return and refund policy', 'IA-29' ),
	'privacy-policy'         => array( 'Privacy policy', 'IA-30' ),
	'terms-and-conditions'   => array( 'Terms and conditions', 'IA-31' ),
	'offers'                 => array( 'Offers', 'IA-32' ),
);

/** IA-21: the native order-tracking form, which looks an order up by number and email. */
$ia_body = function ( $slug ) {
	if ( 'track-your-order' === $slug ) {
		return '<!-- wp:shortcode -->[woocommerce_order_tracking]<!-- /wp:shortcode -->';
	}
	return '<!-- wp:paragraph --><p>Placeholder. Content for this page is a client input and is not written here.</p><!-- /wp:paragraph -->';
};

$ia_en = array();
foreach ( $ia_pages as $slug => $meta ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $existing instanceof WP_Post ) {
		// Two WordPress defaults, privacy-policy and refund_returns, exist as drafts reachable only by
		// ?page_id=, which is a clean-URL failure. Publish and reuse rather than duplicate.
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		}
		$ia_en[ $slug ] = (int) $existing->ID;
		continue;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $meta[0],
			'post_name'    => $slug,
			'post_content' => $ia_body( $slug ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		continue;
	}
	// Register the language explicitly. WPML attaches a post to a language on save_post, which a page inserted
	// inside this script never reaches, so without this the record has trid NULL and language NULL and belongs
	// to neither language. Measured on the first run: eleven of twelve pages were in that state.
	$sitepress->set_element_language_details( (int) $id, 'post_page', null, 'en' );
	$ia_en[ $slug ] = (int) $id;
}

// The pre-existing WordPress drafts were registered by WPML when WordPress created them; the pages above were
// not, so make sure every source record now has a group before the Arabic pass reads it.
foreach ( $ia_en as $slug => $source_id ) {
	if ( ! $sitepress->get_element_trid( $source_id, 'post_page' ) ) {
		$sitepress->set_element_language_details( $source_id, 'post_page', null, 'en' );
	}
}

// The existing WordPress refund draft covers IA-29; keep one record, not two.
$refund_draft = get_page_by_path( 'refund_returns', OBJECT, 'page' );
if ( $refund_draft instanceof WP_Post && 'publish' !== $refund_draft->post_status ) {
	wp_update_post( array( 'ID' => $refund_draft->ID, 'post_status' => 'publish' ) );
}

// Arabic counterparts, second pass, language set before insert.
$ia_ar = array();
foreach ( $ia_en as $slug => $source_id ) {
	$trid = $sitepress->get_element_trid( $source_id, 'post_page' );
	if ( ! $trid ) {
		continue;
	}
	$group = $sitepress->get_element_translations( $trid, 'post_page' );
	if ( isset( $group['ar'] ) ) {
		$ia_ar[ $slug ] = (int) $group['ar']->element_id;
		continue;
	}
	$previous = $sitepress->get_current_language();
	$sitepress->switch_lang( 'ar', true );
	$ar_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => get_the_title( $source_id ),
			'post_name'    => $slug,
			'post_content' => get_post_field( 'post_content', $source_id ),
		),
		true
	);
	$sitepress->switch_lang( $previous, true );
	if ( is_wp_error( $ar_id ) ) {
		continue;
	}
	$sitepress->set_element_language_details( (int) $ar_id, 'post_page', $trid, 'ar', 'en' );
	$ia_ar[ $slug ] = (int) $ar_id;
}

// IA-01: a static front page. The storefront's home is a page, not the blog index.
if ( isset( $ia_en['home'] ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ia_en['home'] );
}

// IA-31: WooCommerce's terms page assignment, which was unset.
if ( isset( $ia_en['terms-and-conditions'] ) ) {
	update_option( 'woocommerce_terms_page_id', $ia_en['terms-and-conditions'] );
}

// IA-05: product_brand ships with WooCommerce 11.1.0 and is not registered translatable in WPML. A P1 row needs
// its Arabic archive, so register it. IA-04 and IA-35: the collection taxonomy the site plugin registers.
$ia_taxonomies = array();
foreach ( array( 'product_brand', 'product_collection' ) as $tax ) {
	if ( ! taxonomy_exists( $tax ) ) {
		continue;
	}
	$helper->set_taxonomy_translatable( $tax );
	$helper->set_taxonomy_translation_unlocked_option( $tax, false );
	$ia_taxonomies[ $tax ] = (bool) $sitepress->is_translated_taxonomy( $tax );
}

// The store is open to visitors on this runtime. A fresh WooCommerce answers every store page with a "coming
// soon" page, status 200, and a scenario that fetches the shop, a product or the cart as a visitor then reads
// that placeholder and not the page. Four scenarios did, on fourteen fetches, until 5 October 2026. This is the
// test and development baseline only: whether the live store opens to visitors is the launch policy, and is not
// set here. tests/integration/_storefront.php is the guard that fails a scenario if the placeholder returns.
update_option( 'woocommerce_coming_soon', 'no' );

// IA-10 and the account endpoints are translated in ia-endpoints.php, which runs after admin-visit.php.
// WooCommerce registers its endpoint slugs under context "WP Endpoints" only when it runs in an admin context,
// so at this point in the reset the strings do not exist yet. Measured on the first run: every lookup returned
// no id and nothing was translated.

flush_rewrite_rules( false );

$tm = $sitepress->get_setting( 'translation-management' );
echo wp_json_encode(
	array(
		'wpml_setup_complete' => (bool) $sitepress->get_setting( 'setup_complete' ),
		'default_language'    => $sitepress->get_default_language(),
		'product_translatable' => (bool) $sitepress->is_translated_post_type( 'product' ),
		'variation_translatable' => (bool) $sitepress->is_translated_post_type( 'product_variation' ),
		'active_languages'    => array_keys( $sitepress->get_active_languages() ),
		'cogs_value_setting'  => $tm['custom_fields_translation']['_cogs_value'] ?? 'unset',
		'mizzey_wpml_config'  => file_exists( WP_PLUGIN_DIR . '/mizzey-site/wpml-config.xml' ),
		'remote_config_index' => get_option( 'wpml_config_index_updated' ) ? gmdate( 'c', (int) get_option( 'wpml_config_index_updated' ) ) : 'not downloaded',
		'wc_currency'         => get_option( 'woocommerce_currency' ),
		'hpos'                => get_option( 'woocommerce_custom_orders_table_enabled' ),
		'permalink_structure' => get_option( 'permalink_structure' ),
		'htaccess'            => file_exists( ABSPATH . '.htaccess' ),
		'system_pages_en'     => $system_pages,
		'system_pages_ar'     => $translated,
		'ia_pages_en'         => $ia_en,
		'ia_pages_ar'         => $ia_ar,
		'ia_front_page'       => array( get_option( 'show_on_front' ), (int) get_option( 'page_on_front' ) ),
		'ia_terms_page'       => (int) get_option( 'woocommerce_terms_page_id' ),
		'ia_taxonomies'       => $ia_taxonomies,
		'coming_soon'         => get_option( 'woocommerce_coming_soon' ),
	)
), "\n";

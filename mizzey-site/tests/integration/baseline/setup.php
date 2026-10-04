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
	)
), "\n";

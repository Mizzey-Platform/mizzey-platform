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

// The Arabic storefront's records and settings: the Arabic records of the WooCommerce system pages (#241, C-2) and
// of the contracted pages (#242), the static front page (IA-01), the terms page (IA-31), and the brand and collection
// taxonomies as translatable (IA-04, IA-05, IA-35). Made by the plugin's own MizzeySite\I18n\LanguageSetup, the code
// `wp mizzey languages provision` runs at release, so this baseline proves the production path (audit of
// 2 November 2026). The baseline publishes the pages and gives them placeholder bodies; production makes drafts with
// no body until the copy is written. Page copy is client input and is not invented here.
//
// The endpoint slugs are made in ia-endpoints.php, which runs after admin-visit.php, as before.
$ia_body = function ( $slug ) {
	return '<!-- wp:paragraph --><p>Placeholder. Content for this page is a client input and is not written here.</p><!-- /wp:paragraph -->';
};
// WordPress's own privacy-policy and refund drafts are published here, as the baseline always has. The release
// command never publishes a page it did not make, so on a live site staff publish these when their copy is ready.
foreach ( array( 'privacy-policy', 'refund_returns' ) as $wp_draft_slug ) {
	$wp_draft = get_page_by_path( $wp_draft_slug, OBJECT, 'page' );
	if ( $wp_draft instanceof WP_Post && 'publish' !== $wp_draft->post_status ) {
		wp_update_post( array( 'ID' => $wp_draft->ID, 'post_status' => 'publish' ) );
	}
}

$system       = \MizzeySite\I18n\LanguageSetup::systemPages();
$system_pages = $system['en'];
$translated   = $system['ar'];
$ia           = \MizzeySite\I18n\LanguageSetup::pages( true, $ia_body );
$ia_en        = $ia['en'];
$ia_ar        = $ia['ar'];

\MizzeySite\I18n\LanguageSetup::frontPage( $ia_en['home'] ?? 0 );
\MizzeySite\I18n\LanguageSetup::termsPage( $ia_en['terms-and-conditions'] ?? 0 );
$ia_taxonomies = \MizzeySite\I18n\LanguageSetup::taxonomies();

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

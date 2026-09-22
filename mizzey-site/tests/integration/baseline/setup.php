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
	)
), "\n";

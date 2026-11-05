<?php
/**
 * Arabic slugs for the WooCommerce account and checkout endpoints (#242: IA-10, IA-16, IA-18, IA-19, IA-20).
 *
 * Runs after admin-visit.php. Made by the plugin's own MizzeySite\I18n\LanguageSetup::endpoints(), the code
 * `wp mizzey languages provision` runs at release (audit of 2 November 2026). It registers an endpoint string the way
 * WooCommerce Multilingual does when the string is not there yet, so it no longer depends on the admin visit; the
 * order is kept so the baseline stays as it was.
 *
 * No pages are created for these rows. They are endpoints on the account and checkout pages, and a page would
 * give one screen two managed routes, which guardrail G-1 forbids.
 *
 * The Arabic values are deliberate placeholders, suffixed rather than translated: the URLs must differ per
 * language and stay stable for the scenarios, while the final Arabic wording is a client content input.
 *
 * @package MizzeySite\Tests\Integration
 */

$endpoints = \MizzeySite\I18n\LanguageSetup::endpoints();

// WPML is set up (setup.php) before WooCommerce Multilingual's configuration marks the product_type taxonomy as not
// translatable (admin-visit.php), so WPML records the four product type terms as English originals. No product is
// affected, but WooCommerce Multilingual then warns on every admin screen that product_type "was set incorrectly
// for some product translations". Its own repair, the one its troubleshooting page runs, removes those records.
global $wpdb;
$product_type_records = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE element_type = 'tax_product_type'" );
WCML_Install::check_product_type_terms();

flush_rewrite_rules( false );

echo wp_json_encode(
	array(
		'ia_endpoints_ar'      => $endpoints['made'] + $endpoints['kept'],
		'ia_endpoints_missing' => $endpoints['missing'],
		'product_type_records_removed' => $product_type_records,
	)
), "\n";

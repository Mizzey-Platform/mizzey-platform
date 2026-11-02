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

flush_rewrite_rules( false );

echo wp_json_encode(
	array(
		'ia_endpoints_ar'      => $endpoints['made'] + $endpoints['kept'],
		'ia_endpoints_missing' => $endpoints['missing'],
	)
), "\n";

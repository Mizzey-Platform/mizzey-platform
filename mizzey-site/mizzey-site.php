<?php

/**
 * Plugin Name:       Mizzey Site
 * Description:       Application code for the Mizzey website (a Corex client site).
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Mizzey
 * License:           GPL-2.0-or-later
 * Text Domain:       mizzey-site
 *
 * @package MizzeySite
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// Mizzey site app code lives here under the MizzeySite\ namespace — never edit the
// Corex framework. Register the service provider with Corex's container on boot.

require_once __DIR__ . '/src/I18n/Localisation.php';
require_once __DIR__ . '/src/Catalogue/Collections.php';
require_once __DIR__ . '/src/Catalogue/Brands.php';
require_once __DIR__ . '/src/Catalogue/CostTranslationSync.php';
require_once __DIR__ . '/src/Seo/ArchiveCanonical.php';
require_once __DIR__ . '/src/Seo/MetaDescription.php';
require_once __DIR__ . '/src/Seo/SitemapLanguages.php';
require_once __DIR__ . '/src/Reporting/PhysicalItems.php';
require_once __DIR__ . '/src/Reporting/StockReport.php';

// The site text domain, for this plugin and for the theme. Nothing else loads it: Corex loads only its own
// `corex` domain. See specs/002-bilingual-platform-baseline (NFR-04a).
MizzeySite\I18n\Localisation::register();

// Product cost must stay equal across a product's language versions, including when the cost is changed by code
// rather than through wp-admin. See the class for the reason and specs/001-product-cost-capture for the evidence.
add_action('plugins_loaded', [MizzeySite\Catalogue\CostTranslationSync::class, 'register'], 20);

// The collection taxonomy, which gives IA-04 and IA-35 their archive route. The register settles the model:
// ADM-57 makes manually curated collections P1, ADM-58 defers rules-based membership to P2.
MizzeySite\Catalogue\Collections::register();

// IA-05: the brand archive's address word is `brand` in every language. WooCommerce's default is a translatable
// string, which gave an Arabic request links that no routing rule answered.
MizzeySite\Catalogue\Brands::register();

// NFR-03's three measured output gaps. Each emits only, through a documented filter, with no storage of its own,
// so MKT-12, MKT-15, ADM-41 and SSC-27 can override it later without this code changing.
// See specs/003-information-architecture-urls.
MizzeySite\Seo\ArchiveCanonical::register();
MizzeySite\Seo\MetaDescription::register();
MizzeySite\Seo\SitemapLanguages::register();

// RPT-10: the standard stock report lists one line per physical item, whichever language its records are in and
// whichever language the operator's session is in. Confined to that report's own query.
// See specs/004-inventory-report-one-item-once.
MizzeySite\Reporting\StockReport::register();

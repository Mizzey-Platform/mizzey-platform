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

require_once __DIR__ . '/src/Catalogue/CostTranslationSync.php';

// Product cost must stay equal across a product's language versions, including when the cost is changed by code
// rather than through wp-admin. See the class for the reason and specs/001-product-cost-capture for the evidence.
add_action('plugins_loaded', [MizzeySite\Catalogue\CostTranslationSync::class, 'register'], 20);

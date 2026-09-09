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

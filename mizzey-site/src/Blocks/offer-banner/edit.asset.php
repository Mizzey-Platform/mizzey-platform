<?php

/**
 * The editor script's dependencies, written by hand: there is no build step in this repository.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    'dependencies' => ['mizzey-section-library', 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-data'],
    'version'      => (string) filemtime(__DIR__ . '/edit.js'),
];

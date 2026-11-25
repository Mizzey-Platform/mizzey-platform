<?php

/**
 * The buying area's script module needs the Interactivity API WordPress ships; there is no build step.
 *
 * @package MizzeySite
 */

return ['dependencies' => ['@wordpress/interactivity'], 'version' => (string) filemtime(__DIR__ . '/view.js')];

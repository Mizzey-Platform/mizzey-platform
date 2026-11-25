<?php

/**
 * The product rail block: MizzeySite\Storefront\ProductRail renders it, escaped there.
 *
 * @package MizzeySite
 *
 * @var array<string,mixed> $attributes
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$mizzey_attributes = isset($attributes) && is_array($attributes) ? $attributes : [];
echo MizzeySite\Storefront\ProductRail::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	array_map('intval', (array) ($mizzey_attributes['products'] ?? [])),
	[
		'layout'  => (string) ($mizzey_attributes['layout'] ?? 'rail'),
		'heading' => (string) ($mizzey_attributes['heading'] ?? ''),
		'link'    => (string) ($mizzey_attributes['link'] ?? ''),
	]
);

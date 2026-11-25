<?php

/**
 * The product card block: MizzeySite\Storefront\ProductCard renders it, escaped there. The product is the block's own
 * `productId`, or the post of the loop it sits in (a product collection's template).
 *
 * @package MizzeySite
 *
 * @var array<string,mixed> $attributes
 * @var WP_Block            $block
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$mizzey_attributes = isset($attributes) && is_array($attributes) ? $attributes : [];
if (! empty($mizzey_attributes['skeleton'])) {
	echo MizzeySite\Storefront\ProductCard::skeleton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
$mizzey_id      = (int) ($mizzey_attributes['productId'] ?? 0);
$mizzey_id      = $mizzey_id > 0 ? $mizzey_id : (int) (isset($block) && $block instanceof WP_Block ? ($block->context['postId'] ?? 0) : 0);
$mizzey_product = $mizzey_id > 0 ? wc_get_product($mizzey_id) : null;
if ($mizzey_product instanceof WC_Product) {
	echo MizzeySite\Storefront\ProductCard::render($mizzey_product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

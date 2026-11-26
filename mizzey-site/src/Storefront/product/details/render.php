<?php

/**
 * The lower product page: MizzeySite\Storefront\ProductDetails renders it, escaped there. The product is the page's own
 * (the single product template's post).
 *
 * @package MizzeySite
 *
 * @var array<string,mixed> $attributes
 * @var WP_Block            $block
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

$mizzey_id      = (int) (isset($block) && $block instanceof WP_Block ? ($block->context['postId'] ?? 0) : 0);
$mizzey_id      = $mizzey_id > 0 ? $mizzey_id : (int) get_queried_object_id();
$mizzey_product = $mizzey_id > 0 ? wc_get_product($mizzey_id) : null;
if ($mizzey_product instanceof WC_Product) {
	echo MizzeySite\Storefront\ProductDetails::render($mizzey_product); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

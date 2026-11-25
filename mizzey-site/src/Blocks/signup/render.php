<?php

/**
 * The sign-up prompt section on the storefront: MizzeySite\Blocks\Banners renders it, escaped there.
 *
 * @package MizzeySite
 *
 * @var array<string,mixed> $attributes
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

echo MizzeySite\Blocks\Banners::renderSignup(isset($attributes) && is_array($attributes) ? $attributes : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

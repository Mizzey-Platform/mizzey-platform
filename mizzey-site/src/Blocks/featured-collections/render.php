<?php

/**
 * The featured collections section on the storefront: MizzeySite\Blocks\TermSections renders it, escaped there.
 *
 * @package MizzeySite
 *
 * @var array<string,mixed> $attributes
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

echo MizzeySite\Blocks\TermSections::renderCollections(isset($attributes) && is_array($attributes) ? $attributes : []); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

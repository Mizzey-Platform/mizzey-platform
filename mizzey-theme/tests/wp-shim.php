<?php

/**
 * Test-only stand-ins for the few WordPress functions the theme's render helpers call, so the helpers can be
 * rendered by the PHP command line for tools/tests/test_theme_components.py and the component preview. They
 * follow WordPress's behaviour where it matters to the checks: escaping uses ENT_QUOTES, and esc_url() drops any
 * scheme that is not http, https or mailto. Never loaded by WordPress; tests/ is not shipped.
 *
 * @package MizzeySite\Tests
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        $catalogue = $GLOBALS['mizzey_preview_strings'] ?? [];
        return $catalogue[$text] ?? $text;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        $url = trim($url);
        if ('' === $url) {
            return '';
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $scheme) && ! in_array(strtolower($scheme[1]), ['http', 'https', 'mailto'], true)) {
            return '';
        }
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false);
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string|false
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $html): string
    {
        return $html;
    }
}

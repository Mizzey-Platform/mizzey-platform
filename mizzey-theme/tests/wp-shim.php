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

/**
 * The catalogue the shim translates with: none (English), or, when the preview asks for Arabic
 * (`$GLOBALS['mizzey_preview_locale'] = 'ar'`), the compiled Arabic file the site loads,
 * mizzey-site/languages/mizzey-site-ar.l10n.php. A string missing there stays English, as on the site.
 *
 * @return array<string, string>
 */
function mizzey_shim_messages(): array
{
    static $loaded = [];
    $locale = (string) ($GLOBALS['mizzey_preview_locale'] ?? 'en');
    if (! isset($loaded[$locale])) {
        $file              = dirname(__DIR__, 2) . '/mizzey-site/languages/mizzey-site-' . $locale . '.l10n.php';
        $loaded[$locale]   = 'en' !== $locale && is_file($file) ? (array) ((require $file)['messages'] ?? []) : [];
    }
    return $loaded[$locale];
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        $found = mizzey_shim_messages()[$text] ?? '';
        return '' === $found ? $text : (string) $found;
    }
}

if (! function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        $messages = mizzey_shim_messages();
        // The compiled file keys a plural by its singular, its forms joined by NUL.
        $found = (string) ($messages[$single] ?? '');
        if ('' === $found) {
            return 1 === $number ? $single : $plural;
        }
        $forms = explode("\0", $found);
        // Arabic's six forms, as the file's Plural-Forms header says.
        $n     = $number;
        $index = 0 === $n ? 0 : (1 === $n ? 1 : (2 === $n ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5))));
        return $forms[$index] ?? end($forms);
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
    /**
     * WordPress's post filter, as far as the theme's markup meets it: a tag not in $allowedposttags goes (its text
     * stays), and an attribute goes unless WordPress allows it on that tag or on every tag (_wp_add_global_attributes:
     * class, id, style, title, role, dir, lang, hidden, data-*, and only these aria-* names). So input, select,
     * textarea and svg are removed, and aria-busy, aria-checked and aria-invalid are dropped, exactly as on a site.
     */
    function wp_kses_post(string $html): string
    {
        $tags = [
            'a' => ['href', 'rel', 'rev', 'name', 'target', 'download', 'referrerpolicy'], 'abbr' => [], 'address' => [],
            'article' => ['align'], 'aside' => ['align'], 'b' => [], 'bdo' => [], 'big' => [], 'blockquote' => ['cite'],
            'br' => [], 'button' => ['disabled', 'name', 'type', 'value'], 'caption' => ['align'], 'cite' => [],
            'code' => [], 'col' => ['align', 'char', 'charoff', 'span', 'valign', 'width'], 'dd' => [],
            'del' => ['datetime'], 'details' => ['align', 'open'], 'div' => ['align'], 'dl' => [], 'dt' => [],
            'em' => [], 'fieldset' => [], 'figure' => ['align'], 'figcaption' => ['align'], 'footer' => ['align'],
            'form' => ['action', 'accept', 'accept-charset', 'enctype', 'method', 'name', 'target'],
            'h1' => ['align'], 'h2' => ['align'], 'h3' => ['align'], 'h4' => ['align'], 'h5' => ['align'], 'h6' => ['align'],
            'header' => ['align'], 'hgroup' => ['align'], 'hr' => ['align', 'noshade', 'size', 'width'], 'i' => [],
            'img' => ['alt', 'align', 'border', 'height', 'hspace', 'loading', 'longdesc', 'vspace', 'src', 'usemap', 'width', 'decoding', 'srcset', 'sizes'],
            'ins' => ['datetime', 'cite'], 'kbd' => [], 'label' => ['for'], 'legend' => ['align'],
            'li' => ['align', 'value'], 'main' => ['align'], 'mark' => [], 'nav' => ['align'], 'object' => [],
            'ol' => ['start', 'type', 'reversed'], 'p' => ['align'], 'pre' => ['width'], 'q' => ['cite'], 's' => [],
            'section' => ['align'], 'small' => [], 'span' => ['align'], 'strike' => [], 'strong' => [], 'sub' => [],
            'summary' => ['align'], 'sup' => [], 'table' => ['align', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'rules', 'summary', 'width'],
            'tbody' => ['align', 'char', 'charoff', 'valign'], 'td' => ['abbr', 'align', 'axis', 'bgcolor', 'char', 'charoff', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'scope', 'valign', 'width'],
            'tfoot' => ['align', 'char', 'charoff', 'valign'], 'th' => ['abbr', 'align', 'axis', 'bgcolor', 'char', 'charoff', 'colspan', 'headers', 'height', 'nowrap', 'rowspan', 'scope', 'valign', 'width'],
            'thead' => ['align', 'char', 'charoff', 'valign'], 'time' => ['datetime'], 'tr' => ['align', 'bgcolor', 'char', 'charoff', 'valign'],
            'u' => [], 'ul' => ['type'], 'var' => [],
        ];
        $global = ['aria-controls', 'aria-current', 'aria-describedby', 'aria-details', 'aria-expanded', 'aria-hidden',
            'aria-label', 'aria-labelledby', 'aria-live', 'class', 'dir', 'hidden', 'id', 'lang', 'style', 'title', 'role', 'xml:lang'];
        return (string) preg_replace_callback(
            '/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/',
            static function (array $m) use ($tags, $global): string {
                $name = strtolower($m[2]);
                if (! isset($tags[$name])) {
                    return '';
                }
                if ('/' === $m[1]) {
                    return '</' . $name . '>';
                }
                $kept = '';
                preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'>]+))?/', $m[3], $attrs, PREG_SET_ORDER);
                foreach ($attrs as $attr) {
                    $key = strtolower($attr[1]);
                    if (in_array($key, $tags[$name], true) || in_array($key, $global, true) || str_starts_with($key, 'data-')) {
                        $kept .= ' ' . $key . (isset($attr[2]) ? '=' . $attr[2] : '');
                    }
                }
                return '<' . $name . $kept . '>';
            },
            $html
        );
    }
}

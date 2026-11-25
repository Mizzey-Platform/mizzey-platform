<?php

/**
 * Mizzey theme bootstrap.
 *
 * Presentation stays in theme.json and style.css. This file exists for one reason: the theme's translations have
 * to be loaded, and nothing else loads them. Corex provides no after_setup_theme or theme-support abstraction and
 * never calls load_theme_textdomain, so a theme that needs its own text domain has to say so itself.
 *
 * The domain is `mizzey-site`, shared with the site plugin, as both headers declare. Each artifact loads its own
 * translations from its own `languages/` directory, which is the WordPress convention and means neither has to
 * test whether the other is active.
 *
 * Do not add business logic here. It belongs in the Mizzey Site plugin.
 *
 * Register ids: NFR-04a, supported by FIX-04. See specs/002-bilingual-platform-baseline.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action(
    'after_setup_theme',
    static function (): void {
        load_theme_textdomain('mizzey-site', get_stylesheet_directory() . '/languages');
    }
);

require_once __DIR__ . '/inc/icons.php';

/*
 * The token layer (spec 066, slice 1). tokens.css and fonts.css are generated from design/tokens/tokens.json and
 * the font files by tools/theme_tokens.py and tools/theme_fonts.py; base.css holds the theme-level type roles,
 * focus ring and motion. The same three files style the editor, so what an editor sees is what a visitor sees.
 * The web font faces themselves are declared in theme.json, which WordPress prints inline in the head.
 */
const MIZZEY_THEME_STYLES = ['tokens', 'fonts', 'base'];

add_action(
    'wp_enqueue_scripts',
    static function (): void {
        $previous = [];
        foreach (MIZZEY_THEME_STYLES as $name) {
            $relative = 'assets/css/' . $name . '.css';
            $file     = get_theme_file_path($relative);
            wp_enqueue_style(
                'mizzey-' . $name,
                get_theme_file_uri($relative),
                $previous,
                is_file($file) ? (string) filemtime($file) : null
            );
            $previous = ['mizzey-' . $name];
        }
    }
);

add_action(
    'after_setup_theme',
    static function (): void {
        add_editor_style(array_map(static fn (string $name): string => 'assets/css/' . $name . '.css', MIZZEY_THEME_STYLES));
    }
);

/*
 * Preload only the face the first screen needs in the page's language (body text), before any stylesheet, as
 * listed in assets/fonts/fonts.json. crossorigin is required even on the same origin, or the file loads twice.
 */
add_action(
    'wp_head',
    static function (): void {
        $manifest = get_theme_file_path('assets/fonts/fonts.json');
        $fonts    = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        if (! is_array($fonts) || ! isset($fonts['preload']) || ! is_array($fonts['preload'])) {
            return;
        }
        $language = str_starts_with(determine_locale(), 'ar') ? 'ar' : 'en';
        foreach ((array) ($fonts['preload'][$language] ?? []) as $file) {
            printf(
                '<link rel="preload" as="font" type="font/woff2" href="%s" crossorigin>' . "\n",
                esc_url(get_theme_file_uri('assets/fonts/' . basename((string) $file)))
            );
        }
    },
    1
);

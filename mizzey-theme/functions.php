<?php

/**
 * Mizzey theme bootstrap: what theme.json cannot say on its own.
 *
 * - The theme's translations. CoreX never calls load_theme_textdomain, so the theme loads the `mizzey-site` domain
 *   it shares with the site plugin. The Arabic catalogue of that domain, the theme's strings included, is the
 *   plugin's `mizzey-site/languages/`; the theme's own `languages/` folder is where a theme-only override would go.
 * - The site logo: core's site logo, with the theme's support for a custom logo (global brand controls, spec 066).
 * - The token layer, the base components and their behaviour (spec 066, slice 1), for the storefront and the editor.
 * - The preload of the first screen's body font.
 *
 * Presentation stays in theme.json and the stylesheets. Do not add business logic here: it belongs in the Mizzey
 * Site plugin.
 *
 * Register ids: NFR-04a, FIX-04a, NFR-04, NFR-11, SSC-01, MER-06. See specs/002-bilingual-platform-baseline and
 * specs/066-stage-2-shop-front, slice 1.
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

/*
 * The site logo is core's: the Site Logo block and its `site_logo` setting, which WordPress keeps in step with the
 * custom logo when the theme supports it. The supplied file is never cropped or recoloured, so both sides are free.
 */
add_action(
    'after_setup_theme',
    static function (): void {
        add_theme_support('custom-logo', ['flex-width' => true, 'flex-height' => true, 'unlink-homepage-logo' => false]);
    }
);

require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/components.php';
require_once __DIR__ . '/inc/header.php';
// The footer, the announcement strip and the consent banner's look (spec 066, slice 3).
require_once __DIR__ . '/inc/footer.php';
require_once __DIR__ . '/inc/strip.php';
require_once __DIR__ . '/inc/consent.php';

/*
 * The token layer (spec 066, slice 1). tokens.css and fonts.css are generated from design/tokens/tokens.json and
 * the font files by tools/theme_tokens.py and tools/theme_fonts.py; base.css holds the theme-level type roles,
 * focus ring and motion; components.css the base components. The same files style the editor, so what an editor
 * sees is what a visitor sees. The web font faces themselves are declared in theme.json, which WordPress prints
 * inline in the head.
 */
const MIZZEY_THEME_STYLES = ['tokens', 'fonts', 'base', 'components', 'header'];

/*
 * The components' behaviour: one script module on the Interactivity API WordPress ships. It and the Interactivity
 * runtime it imports load on every storefront page on purpose: the header's drawers (the phone menu and the mini
 * cart, spec 066 slice 2) are on every page. Their bytes are inside the every-page budget of
 * tools/tests/test_byte_budgets.py (D-21).
 */
add_action(
    'init',
    static function (): void {
        $file = get_theme_file_path('assets/js/ui.js');
        wp_register_script_module(
            'mizzey-theme/ui',
            get_theme_file_uri('assets/js/ui.js'),
            [['id' => '@wordpress/interactivity', 'import' => 'static']],
            is_file($file) ? (string) filemtime($file) : false
        );
        // The header's own behaviour (spec 066, slice 2): the mega menu, the phone menu's levels, the Categories tab
        // and the cart's live count. The header is on every page, so it is too.
        $header = get_theme_file_path('assets/js/header.js');
        wp_register_script_module(
            'mizzey-theme/header',
            get_theme_file_uri('assets/js/header.js'),
            [['id' => '@wordpress/interactivity', 'import' => 'static']],
            is_file($header) ? (string) filemtime($header) : false
        );
    }
);

add_action(
    'wp_enqueue_scripts',
    static function (): void {
        wp_enqueue_script_module('mizzey-theme/ui');
        wp_enqueue_script_module('mizzey-theme/header');
    }
);

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

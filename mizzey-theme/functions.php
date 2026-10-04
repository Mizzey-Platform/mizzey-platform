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

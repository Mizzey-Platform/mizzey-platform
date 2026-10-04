<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * Loads the site text domain, for the site plugin and for the theme.
 *
 * This exists because nothing else loads it. Corex loads one hardcoded domain, `corex`, for its own strings
 * (corex-core CoreServiceProvider), and never a client's; its CLI stubs declare a client `Text Domain:` header
 * and no generated code loads it. The Mizzey plugin and theme were in exactly that state: both headers declare
 * `mizzey-site`, and neither the plugin nor the theme loaded it.
 *
 * WordPress can load a domain just in time on first use, and for a plugin in its ordinary location it usually
 * does. This site is not in an ordinary location: Corex mounts the client under `sites/<client>` through a
 * junction. An explicit load is one call and removes the question.
 *
 * One domain for both, `mizzey-site`, as both headers already declare. A second domain would mean a second
 * template file to keep and a second chance for a later slice to bind a string to the wrong one.
 *
 * The theme loads that same domain from its own `languages/` directory, in `mizzey-theme/functions.php`. Each
 * artifact loading its own translations is the WordPress convention, and it means neither has to test whether the
 * other is active. A string resolves from whichever file carries it.
 *
 * What this class deliberately does not do: no locale filter, no reading-direction handling, no language
 * switcher, and nothing that touches the administrative interface language. Direction and locale already follow
 * the active language natively, measured on a served request, and the admin is English by a requirement this
 * feature does not own.
 *
 * Register ids: NFR-04a (no hard-coded text anywhere in the storefront interface), supported by FIX-04.
 * See specs/002-bilingual-platform-baseline.
 */
final class Localisation
{
    public const DOMAIN = 'mizzey-site';

    /**
     * Hooks the two loads. Called from the plugin bootstrap.
     *
     * The hook does not touch the Corex container, so `Corex::onReady()` is not needed here. A later slice that
     * does resolve a Corex service must use it: Corex boots on `plugins_loaded` priority 10 and so does a
     * generated client plugin, and the loser of that race gets a fatal from `Boot::app()` on every request.
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'loadPluginTextdomain']);
    }

    /**
     * The site plugin's translations.
     *
     * The domain is written as a literal, not as `self::DOMAIN`, because the string-extraction tools read this
     * argument statically. `DOMAIN` stays above for a later slice to reference in PHP.
     *
     * The path is relative to the plugins directory and is written out rather than derived from
     * `plugin_basename(__FILE__)`. Under the junction Corex mounts the client site through, a derived path can
     * resolve to the real target instead of the plugin slug; a literal cannot.
     */
    public static function loadPluginTextdomain(): void
    {
        load_plugin_textdomain('mizzey-site', false, 'mizzey-site/languages');
    }
}

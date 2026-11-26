<?php

/**
 * The consent banner's designed look and the cookie preferences (spec 066, slice 3), as C1-Global B3 draws them: one
 * bar at the foot of the window (above the phone's tab bar) with the two choices, and the preferences as a dialog on
 * desktop and a bottom sheet on the phone.
 *
 * The banner and its behaviour are specs/043's (MizzeySite\Marketing\ConsentBanner, assets/consent.js in the site
 * plugin): one choice, "granted" or "denied", kept in the `mizzey_consent` cookie, with Google's consent mode denied
 * until the visitor accepts. Nothing here records a choice: every button of the preferences presses the banner's own
 * button for the choice, so the cookie, the consent mode update and the data layer event stay exactly theirs.
 *
 * The banner has one choice for everything that is not necessary, so the preferences record one too: "Accept all"
 * is "granted", "Decline" is "denied", and "Save choices" is "granted" only when Analytics and Marketing are both on,
 * and "denied" otherwise, so a visitor is never tracked further than they allowed. The switches open on the standing
 * choice. Any link to `#cookie-preferences` (the footer's, the banner's) opens the preferences.
 *
 * The banner covers no content: while it shows, the footer gains room at its own foot as tall as the banner reaches
 * (`--mizzey-consent-space`, measured by assets/js/site-parts.js), so the end of the page scrolls clear above it and
 * no empty band shows under the footer.
 *
 * Register ids: MKT-10, NFR-11. See specs/066-stage-2-shop-front, slice 3, and specs/043-measurement-tools.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Consent;

use function MizzeySite\Theme\Components\button;
use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Components\overlay;

defined('ABSPATH') || exit;

const DIALOG = 'mizzey-consent-preferences';

add_action('init', __NAMESPACE__ . '\\register');
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue');
add_action('wp_footer', __NAMESPACE__ . '\\preferences', 6);

/** Present when the site plugin's banner is. */
function active(): bool
{
    return class_exists('MizzeySite\\Marketing\\ConsentBanner') && ! is_admin();
}

function register(): void
{
    $style = get_theme_file_path('assets/css/consent.css');
    wp_register_style('mizzey-consent', get_theme_file_uri('assets/css/consent.css'), ['mizzey-components'], is_file($style) ? (string) filemtime($style) : null);

    // The strip's slider and the preferences: one script module on the Interactivity API, store `mizzey/site`.
    $module = get_theme_file_path('assets/js/site-parts.js');
    wp_register_script_module(
        'mizzey-theme/site-parts',
        get_theme_file_uri('assets/js/site-parts.js'),
        [['id' => '@wordpress/interactivity', 'import' => 'static']],
        is_file($module) ? (string) filemtime($module) : false
    );

    // The footer's and the strip's settings in the Site Editor.
    $editor = get_theme_file_path('assets/js/site-parts-editor.js');
    wp_register_script(
        \MizzeySite\Theme\Footer\EDITOR_SCRIPT,
        get_theme_file_uri('assets/js/site-parts-editor.js'),
        ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
        is_file($editor) ? (string) filemtime($editor) : false,
        true
    );
    wp_set_script_translations(\MizzeySite\Theme\Footer\EDITOR_SCRIPT, 'mizzey-site', WP_PLUGIN_DIR . '/mizzey-site/languages');
    wp_localize_script(\MizzeySite\Theme\Footer\EDITOR_SCRIPT, 'mizzeySiteParts', ['languages' => languages()]);
}

/**
 * The store's languages, the main one first, for the editor.
 *
 * @return list<array{code:string,name:string}>
 */
function languages(): array
{
    $active = apply_filters('wpml_active_languages', null, ['skip_missing' => 0, 'orderby' => 'id']);
    $out    = [];
    foreach (is_array($active) ? $active : [] as $code => $language) {
        $out[] = ['code' => (string) $code, 'name' => (string) ($language['translated_name'] ?? $language['native_name'] ?? $code)];
    }
    if ([] === $out) {
        return [['code' => 'en', 'name' => 'English']];
    }
    $default = (string) apply_filters('wpml_default_language', null);
    usort($out, static fn (array $a, array $b): int => ($b['code'] === $default) <=> ($a['code'] === $default));

    return $out;
}

function enqueue(): void
{
    if (! active()) {
        return;
    }
    wp_enqueue_style('mizzey-consent');
    wp_enqueue_script_module('mizzey-theme/site-parts');
}

/** One category's row: its name, what it does, and its switch. */
function row(string $category, string $name, string $line, bool $always): string
{
    $id     = DIALOG . '-' . $category;
    $switch = element(
        'button',
        [
            'type'                       => 'button',
            'class'                      => implode(' ', ['mizzey-switch', 'mizzey-consent-row__switch']),
            'role'                       => 'switch',
            'aria-checked'               => $always ? 'true' : 'false',
            'aria-labelledby'            => $id . '-name',
            'aria-describedby'           => $id . '-line',
            'disabled'                   => $always,
            'data-mizzey-consent-category' => $category,
            'data-wp-interactive'        => 'mizzey/ui',
            'data-wp-on--click'          => 'actions.toggle',
        ],
        element('span', ['class' => 'mizzey-switch__track', 'aria-hidden' => 'true'], element('span', ['class' => 'mizzey-switch__thumb']))
    );

    return element(
        'div',
        ['class' => 'mizzey-consent-row'],
        element(
            'div',
            ['class' => 'mizzey-consent-row__text'],
            element('span', ['class' => 'mizzey-consent-row__name', 'id' => $id . '-name'], esc_html($name))
            . element('span', ['class' => 'mizzey-consent-row__line', 'id' => $id . '-line'], esc_html($line))
        )
        . ($always ? element('span', ['class' => 'mizzey-consent-row__always'], esc_html__('Always on', 'mizzey-site')) : '')
        . $switch
    );
}

/** The preferences, printed once after the page. */
function preferences(): void
{
    if (! active()) {
        return;
    }
    $choice = static fn (string $value, string $label, string $variant): string => button([
        'label'   => $label,
        'variant' => $variant,
        'attrs'   => [
            'data-mizzey-consent-choice' => $value,
            'data-wp-interactive'        => 'mizzey/site',
            'data-wp-on--click'          => 'actions.consentChoice',
        ],
    ]);
    $body = element('p', ['class' => 'mizzey-consent-preferences__intro'], esc_html__('Choose what you allow. You can change this any time from Cookie preferences at the foot of every page.', 'mizzey-site'))
        . row('necessary', __('Necessary', 'mizzey-site'), __('Keep your cart, checkout and sign-in working.', 'mizzey-site'), true)
        . row('analytics', __('Analytics', 'mizzey-site'), __('Help us see which pages are used most, without your name.', 'mizzey-site'), false)
        . row('marketing', __('Marketing', 'mizzey-site'), __('Show you offers that fit you on other sites.', 'mizzey-site'), false);
    $foot = $choice('denied', __('Decline', 'mizzey-site'), 'secondary')
        . $choice('save', __('Save choices', 'mizzey-site'), 'secondary')
        . $choice('granted', __('Accept all', 'mizzey-site'), 'primary');

    $html = overlay([
        'id'     => DIALOG,
        'kind'   => 'dialog',
        'title'  => __('Cookie preferences', 'mizzey-site'),
        'body'   => $body,
        'footer' => $foot,
        'close'  => true,
    ]);
    // On a phone the preferences are a bottom sheet with a handle (C1-Global B3); from 600 wide a dialog, without one.
    $html = preg_replace('/(<div class="mizzey-overlay__panel">)/', '$1<span class="mizzey-consent-preferences__handle" aria-hidden="true"></span>', $html, 1) ?? $html;
    // The preferences open from any link to #cookie-preferences; the store watches for it once the page is live.
    echo element('div', ['class' => 'mizzey-consent-preferences', 'data-wp-interactive' => 'mizzey/site', 'data-wp-init' => 'callbacks.consentInit'], $html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
}

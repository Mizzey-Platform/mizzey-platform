<?php

/**
 * The designed footer (spec 066, slice 3), as C1-Header-Footer-Styles (F1 Indigo, F2 White) and C1-Global (B8, long
 * content) draw it: the logo and the language link, the columns of links, Customer care with the WhatsApp link, the
 * legal line, and the ZZ mark in the end corner.
 *
 * One dynamic block, `mizzey/site-footer`, placed in the footer template part (parts/footer.html), renders it on the
 * server. Its settings are edited on the block in the Site Editor (assets/js/site-parts-editor.js):
 *
 * - **style**: `indigo` (F1, the Deep gradient) or `white` (F2). Nothing else is offered (G11).
 * - **columns**: in order, each column's navigation menu named for each language (`menus`, language code to menu),
 *   so a column never takes another menu by accident, and the header's menu is never read here. `wide` sets a column
 *   over two tracks with its links in two columns (Shop on the boards); `under` stacks it under the column before
 *   it (Legal under About). The column's heading is its menu's title, in that language.
 * - **care**: the Customer care column's menu for each language (the phone, the email, the address). The WhatsApp
 *   link comes first in it, from the number and greeting of specs/059 (MizzeySite\Storefront\ContactLinks).
 * - **legal**: the legal line for each language; with none, the year and the site's name.
 * - **darkLogo**: the logo file for the Indigo footer (the brand's Royal BG artwork); the site logo serves White.
 *
 * Menus are WordPress's own `wp_navigation` (native first, M-4), made and edited by staff in the Site Editor, one per
 * language (SSC-08, SSC-12). A link whose address is `#cookie-preferences` reopens the consent choice of specs/043
 * and opens the cookie preferences; with no such link in any column, the legal line carries the banner's own
 * "Cookie settings" link, so the choice can always be reopened. A phone number or an email address stays on one
 * line, left to right, in both languages.
 *
 * Register ids: SSC-08, SSC-12, CMS-10, MKT-10, NFR-11. See specs/066-stage-2-shop-front, slice 3.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Footer;

use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Components\zz;

defined('ABSPATH') || exit;

const BLOCK          = 'mizzey/site-footer';
const STYLES         = ['indigo', 'white'];
const COOKIE_ANCHOR  = '#cookie-preferences';
const LINK_BLOCKS    = ['core/navigation-link', 'core/navigation-submenu'];
const EDITOR_SCRIPT  = 'mizzey-site-parts-editor';

add_action('init', __NAMESPACE__ . '\\register');

function register(): void
{
    $style = get_theme_file_path('assets/css/footer.css');
    wp_register_style('mizzey-site-footer', get_theme_file_uri('assets/css/footer.css'), ['mizzey-components'], is_file($style) ? (string) filemtime($style) : null);

    register_block_type(BLOCK, [
        'api_version'          => 3,
        'title'                => __('Site footer', 'mizzey-site'),
        'category'             => 'theme',
        'icon'                 => 'align-wide',
        'description'          => __('The store footer: logo, language, the link columns from your menus, customer care and the legal line.', 'mizzey-site'),
        'attributes'           => [
            'style'    => ['type' => 'string', 'enum' => STYLES, 'default' => 'indigo'],
            'columns'  => ['type' => 'array', 'default' => []],
            'care'     => ['type' => 'object', 'default' => []],
            'legal'    => ['type' => 'object', 'default' => []],
            'darkLogo' => ['type' => 'integer', 'default' => 0],
        ],
        'supports'             => ['html' => false, 'inserter' => false, 'multiple' => false, 'reusable' => false, 'className' => false, 'customClassName' => false],
        'editor_script'        => EDITOR_SCRIPT,
        'style_handles'        => ['mizzey-site-footer'],
        'editor_style_handles' => ['mizzey-site-footer'],
        'render_callback'      => __NAMESPACE__ . '\\render',
    ]);
}

/** The request's language code. */
function language(): string
{
    $code = class_exists('MizzeySite\\I18n\\LanguagePreference')
        ? \MizzeySite\I18n\LanguagePreference::current()
        : (string) apply_filters('wpml_current_language', null);

    return '' !== $code ? $code : 'en';
}

/**
 * The menu named for this language. A column set only in the store's main language follows that menu to its
 * translation in this one, when WPML has one; it never falls back to another language's words.
 *
 * @param mixed $menus Language code to menu id.
 */
function menu_for($menus, string $lang): ?\WP_Post
{
    $menus = is_array($menus) ? $menus : [];
    $id    = (int) ($menus[$lang] ?? 0);
    if ($id <= 0) {
        $default = (string) apply_filters('wpml_default_language', null);
        $source  = (int) ($menus[$default] ?? 0);
        $id      = $source > 0 ? (int) apply_filters('wpml_object_id', $source, 'wp_navigation', false, $lang) : 0;
    }
    $post = $id > 0 ? get_post($id) : null;

    return $post instanceof \WP_Post && 'wp_navigation' === $post->post_type && 'publish' === $post->post_status ? $post : null;
}

/** Plain words from what an editor typed. */
function text(string $value): string
{
    return trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * A menu's plain links, in order; a submenu's own links follow its label, flat.
 *
 * @param array<int,array<string,mixed>>       $blocks
 * @param list<array{label:string,url:string}> $links
 */
function collect(array $blocks, array &$links): void
{
    foreach ($blocks as $block) {
        if (in_array($block['blockName'] ?? '', LINK_BLOCKS, true)) {
            $label = text((string) ($block['attrs']['label'] ?? ''));
            $url   = trim((string) ($block['attrs']['url'] ?? ''));
            if ('' !== $label && '' !== $url) {
                $links[] = ['label' => $label, 'url' => $url];
            }
        }
        if (! empty($block['innerBlocks'])) {
            collect((array) $block['innerBlocks'], $links);
        }
    }
}

/** @return list<array{label:string,url:string}> */
function menu_links(\WP_Post $menu): array
{
    $links = [];
    collect(parse_blocks((string) $menu->post_content), $links);

    return $links;
}

/** One link: the cookie preferences opener, a phone or email kept left to right, or an ordinary link. */
function link_html(string $label, string $url, bool &$opener): string
{
    if (COOKIE_ANCHOR === $url) {
        $opener = true;

        return element('a', ['class' => 'mizzey-footer__link', 'href' => COOKIE_ANCHOR, 'data-mizzey-consent-open' => '1'], esc_html($label));
    }
    $href = esc_url($url, ['http', 'https', 'tel', 'mailto']);
    if ('' === $href) {
        return '';
    }
    $inner = preg_match('/^(tel|mailto):/i', $url)
        ? element('span', ['class' => 'mizzey-footer__ltr', 'dir' => 'ltr'], esc_html($label))
        : esc_html($label);

    return element('a', ['class' => 'mizzey-footer__link', 'href' => $href], $inner);
}

/**
 * One column: its heading and its list.
 *
 * @param list<string> $items Each link's HTML.
 */
function column(string $heading, array $items, bool $wide = false): string
{
    $items = array_values(array_filter($items, static fn (string $item): bool => '' !== $item));
    if ([] === $items) {
        return '';
    }
    $id = wp_unique_id('mizzey-footer-heading-');

    return element(
        'div',
        ['class' => 'mizzey-footer__column'],
        element('h2', ['class' => 'mizzey-footer__heading', 'id' => $id], esc_html($heading))
        . element(
            'ul',
            ['class' => 'mizzey-footer__links' . ($wide ? ' mizzey-footer__links--two' : ''), 'aria-labelledby' => $id],
            implode('', array_map(static fn (string $item): string => element('li', [], $item), $items))
        )
    );
}

/** The logo: the site logo on White, the chosen Royal BG file on Indigo, or the site's name. */
function logo(string $style, int $darkLogo): string
{
    $id   = 'indigo' === $style ? $darkLogo : (int) get_theme_mod('custom_logo');
    $name = get_bloginfo('name');
    $img  = $id > 0 ? wp_get_attachment_image($id, 'medium', false, ['class' => 'mizzey-footer__logo-image', 'alt' => $name, 'loading' => 'lazy', 'decoding' => 'async']) : '';
    $home = (string) apply_filters('wpml_home_url', home_url('/'));

    return element(
        'a',
        ['class' => 'mizzey-footer__logo', 'href' => esc_url($home), 'rel' => 'home'],
        '' !== $img ? $img : element('span', ['class' => 'mizzey-footer__logo-text'], esc_html($name))
    );
}

/** The link to the other language, named in itself. */
function language_link(string $current): string
{
    if (! class_exists('MizzeySite\\I18n\\LanguagePreference')) {
        return '';
    }
    $other = 'ar' === $current ? 'en' : 'ar';
    $names = ['en' => 'English', 'ar' => 'العربية'];

    return element(
        'a',
        ['class' => 'mizzey-footer__language', 'href' => esc_url(\MizzeySite\I18n\LanguagePreference::switchUrl($other)), 'hreflang' => $other, 'lang' => $other],
        esc_html($names[$other])
    );
}

/**
 * The block's markup.
 *
 * @param array<string,mixed> $attributes
 */
function render(array $attributes): string
{
    $style  = in_array($attributes['style'] ?? '', STYLES, true) ? (string) $attributes['style'] : 'indigo';
    $lang   = language();
    $opener = false;

    // Customer care: the WhatsApp link first, then its menu's links.
    $care_menu  = menu_for($attributes['care'] ?? [], $lang);
    $care_items = [];
    $whatsapp   = class_exists('MizzeySite\\Storefront\\ContactLinks') ? \MizzeySite\Storefront\ContactLinks::whatsappUrl($lang) : '';
    if ('' !== $whatsapp) {
        $care_items[] = element(
            'a',
            ['class' => implode(' ', ['mizzey-footer__link', 'mizzey-footer__whatsapp']), 'href' => esc_url($whatsapp), 'target' => '_blank', 'rel' => 'noopener noreferrer'],
            esc_html__('Chat on WhatsApp', 'mizzey-site')
            . element('span', ['class' => 'mizzey-visually-hidden'], esc_html__('(opens in a new tab)', 'mizzey-site'))
        );
    }
    foreach ($care_menu ? menu_links($care_menu) : [] as $link) {
        $care_items[] = link_html($link['label'], $link['url'], $opener);
    }
    $care_heading = $care_menu ? text(get_the_title($care_menu)) : '';
    $care         = column('' !== $care_heading ? $care_heading : __('Customer care', 'mizzey-site'), $care_items);

    // The columns, each from the menu named for this language; a column marked "under" joins the one before it.
    $groups = [];
    foreach (array_values(is_array($attributes['columns'] ?? null) ? $attributes['columns'] : []) as $setting) {
        $setting = is_array($setting) ? $setting : [];
        $menu    = menu_for($setting['menus'] ?? [], $lang);
        if (! $menu) {
            continue;
        }
        $wide  = ! empty($setting['wide']);
        $items = [];
        foreach (menu_links($menu) as $link) {
            $items[] = link_html($link['label'], $link['url'], $opener);
        }
        $html  = column(text(get_the_title($menu)), $items, $wide);
        if ('' === $html) {
            continue;
        }
        if (! empty($setting['under']) && [] !== $groups) {
            $groups[count($groups) - 1]['html'] .= $html;
            $groups[count($groups) - 1]['wide']  = $groups[count($groups) - 1]['wide'] || $wide;
            continue;
        }
        $groups[] = ['html' => $html, 'wide' => $wide];
    }

    $brand = element('div', ['class' => 'mizzey-footer__brand'], logo($style, (int) ($attributes['darkLogo'] ?? 0)) . language_link($lang));
    $cells = $brand
        . ('' !== $care ? element('div', ['class' => implode(' ', ['mizzey-footer__group', 'mizzey-footer__group--care'])], $care) : '')
        . implode('', array_map(static fn (array $group): string => element('div', ['class' => 'mizzey-footer__group' . ($group['wide'] ? ' mizzey-footer__group--wide' : '')], $group['html']), $groups));

    // The legal line; the cookie settings link of specs/043 when no column carries the preferences link.
    $legal_words = is_array($attributes['legal'] ?? null) ? text((string) ($attributes['legal'][$lang] ?? '')) : '';
    /* translators: 1: the year, 2: the site's name. */
    $legal_words = '' !== $legal_words ? $legal_words : sprintf(__('© %1$s %2$s.', 'mizzey-site'), wp_date('Y'), get_bloginfo('name'));
    $legal       = element('p', ['class' => 'mizzey-footer__legal'], esc_html($legal_words));
    if (! $opener) {
        $legal .= element('a', ['class' => implode(' ', ['mizzey-footer__link', 'mizzey-cookie-settings']), 'href' => COOKIE_ANCHOR, 'data-mizzey-consent-open' => '1'], esc_html__('Cookie settings', 'mizzey-site'));
    }
    // The banner's own footer link is not printed again (MizzeySite\Marketing\ConsentBanner::settingsLink).
    do_action('mizzey_footer_consent_link');

    return element(
        'div',
        ['class' => 'mizzey-footer mizzey-footer--' . $style . ('indigo' === $style ? ' mizzey-on-brand' : '')],
        element('div', ['class' => 'mizzey-footer__grid'], $cells)
        . element('div', ['class' => 'mizzey-footer__foot'], $legal)
        . element('span', ['class' => 'mizzey-footer__zz', 'aria-hidden' => 'true'], zz())
    );
}

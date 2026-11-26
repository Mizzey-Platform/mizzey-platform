<?php

/**
 * The designed header (spec 066, slice 2): the desktop bar and category row with the mega menu, the phone header in
 * its three forms, the phone menu drawer with its levels, the phone tab bar with its Categories tab, and the entry
 * points, as C1-Header-Footer-Styles, C1-Menus-Desktop, C1-Menus-Phone and C1-Global draw them.
 *
 * One dynamic block, `mizzey/site-header`, placed in the header template part (parts/header.html), renders it on
 * the server for the first paint. Its settings are edited on the block in the Site Editor:
 *
 * - **style**: one of the three headers the board draws, `white` (logo at the start, the default), `indigo` (Royal
 *   Indigo, logo at the start) or `centred` (White, logo centred). Nothing else is offered (G11).
 * - **menu**: the navigation menu of the category row; with none chosen, the menu named "Mizzey header" (slug
 *   `mizzey-header`). MizzeySite\Storefront\HeaderMenu reads it, per language;
 * - **helpUrl**: where Help leads; with none, Help is not shown;
 * - **darkLogo**: the logo file for the Indigo header (the brand's Royal BG artwork); the site logo serves the others.
 *
 * Everything it shows comes from data that already exists: the header data and the category tree of specs/054
 * (MizzeySite\Storefront\HeaderData), the switcher link of specs/052 (MizzeySite\I18n\LanguagePreference, whose
 * signed-in nonce is D-31's gift) and the site logo. Every word a visitor reads is translatable in the mizzey-site
 * domain; names, labels and links of the menu are the store's own, per language.
 *
 * The phone header takes the form the board gives each page: the home page and the not-found page carry the search
 * row and the chips ("home"); a product, the cart and checkout carry a search control instead of the row ("compact"),
 * and no tab bar, because their action bar takes its place; every other page carries the search row ("listing").
 *
 * Behaviour is in assets/js/header.js (store `mizzey/header`), the drawer's opening, modal focus and return in
 * assets/js/ui.js (store `mizzey/ui`); the look in assets/css/header.css.
 *
 * Register ids: NAV-01 to NAV-10, SSC-07, NFR-11; the signed-in switcher shows D-31 (a gift). See
 * specs/066-stage-2-shop-front, slice 2.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Header;

use function MizzeySite\Theme\Components\attributes;
use function MizzeySite\Theme\Components\chip;
use function MizzeySite\Theme\Components\counter;
use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Components\opens;
use function MizzeySite\Theme\Components\tooltip;
use function MizzeySite\Theme\Icons\icon;

defined('ABSPATH') || exit;

const BLOCK  = 'mizzey/site-header';
const STORE  = 'mizzey/header';
const STYLES = ['white', 'indigo', 'centred'];
/** The category row carries at most this many links; the rest are under "More" (C1-Global B2). */
const ROW_MAX = 8;
const DRAWER  = 'mizzey-menu';
const MEGA    = 'mizzey-mega';
const PANEL   = 'mizzey-categories';

add_action('init', __NAMESPACE__ . '\\register');

/** A class attribute from its class names. */
function classes(string ...$names): string
{
    return implode(' ', $names);
}

function register(): void
{
    $editor = get_theme_file_path('assets/js/header-editor.js');
    wp_register_script(
        'mizzey-site-header-editor',
        get_theme_file_uri('assets/js/header-editor.js'),
        ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render'],
        is_file($editor) ? (string) filemtime($editor) : false,
        true
    );
    wp_set_script_translations('mizzey-site-header-editor', 'mizzey-site');

    register_block_type(BLOCK, [
        'api_version'     => 3,
        'title'           => __('Site header', 'mizzey-site'),
        'category'        => 'theme',
        'icon'            => 'menu',
        'description'     => __('The store header: logo, search, language, account, wishlist, cart and the category menus.', 'mizzey-site'),
        'attributes'      => [
            'style'    => ['type' => 'string', 'enum' => STYLES, 'default' => 'white'],
            'menu'     => ['type' => 'integer', 'default' => 0],
            'helpUrl'  => ['type' => 'string', 'default' => ''],
            'darkLogo' => ['type' => 'integer', 'default' => 0],
        ],
        'supports'        => ['html' => false, 'inserter' => false, 'multiple' => false, 'reusable' => false, 'className' => false, 'customClassName' => false],
        'editor_script'   => 'mizzey-site-header-editor',
        'render_callback' => __NAMESPACE__ . '\\render',
    ]);
}

/**
 * The header's data, gathered once.
 *
 * @param array<string,mixed> $attributes
 * @return array<string,mixed>
 */
function data(array $attributes): array
{
    $style = in_array($attributes['style'] ?? '', STYLES, true) ? (string) $attributes['style'] : 'white';
    $plugin = class_exists('MizzeySite\\Storefront\\HeaderData');
    $header = $plugin ? \MizzeySite\Storefront\HeaderData::header() : [];
    $tree   = $plugin ? \MizzeySite\Storefront\HeaderData::categories() : [];
    $links  = class_exists('MizzeySite\\Storefront\\HeaderMenu') ? \MizzeySite\Storefront\HeaderMenu::links((int) ($attributes['menu'] ?? 0)) : [];
    $user   = wp_get_current_user();
    $name   = $user->exists() ? trim((string) ($user->first_name ?: $user->display_name)) : '';

    return [
        'style'    => $style,
        'form'     => form(),
        'home'     => (string) apply_filters('wpml_home_url', home_url('/')),
        'logo'     => logo('indigo' === $style ? (int) ($attributes['darkLogo'] ?? 0) : 0),
        'search'   => (array) ($header['search'] ?? ['url' => home_url('/'), 'parameter' => 's', 'post_type' => 'product']),
        'cart'     => (array) ($header['cart'] ?? ['count' => 0, 'url' => '']),
        'wishlist' => (array) ($header['wishlist'] ?? ['count' => 0, 'url' => '']),
        'account'  => ['url' => (string) ($header['account']['url'] ?? ''), 'name' => $name],
        'language' => language(),
        'help'     => help((string) ($attributes['helpUrl'] ?? '')),
        'links'    => $links,
        'tree'     => $tree,
    ];
}

/** The phone header's form for this page (C1-Header-Footer-Styles, H1 phone). */
function form(): string
{
    if (is_front_page() || is_404()) {
        return 'home';
    }
    $compact = (function_exists('is_product') && is_product())
        || (function_exists('is_cart') && is_cart())
        || (function_exists('is_checkout') && is_checkout());

    return $compact ? 'compact' : 'listing';
}

/**
 * The logo: the file staff set (the site logo, or the Indigo header's own), never recoloured; the store's name when
 * no file is set.
 *
 * @return array{id:int,alt:string}
 */
function logo(int $dark): array
{
    $site = (int) get_theme_mod('custom_logo');
    if ($site <= 0) {
        $site = (int) get_option('site_logo');
    }

    return ['id' => $dark > 0 ? $dark : $site, 'alt' => (string) get_bloginfo('name')];
}

/**
 * The other language: its code, its name in itself, and the switcher link of specs/052 (with the signed-in nonce,
 * D-31). Null when the language layer is not there.
 *
 * @return array{code:string,name:string,short:string,url:string}|null
 */
function language(): ?array
{
    if (! class_exists('MizzeySite\\I18n\\LanguagePreference')) {
        return null;
    }
    $current = \MizzeySite\I18n\LanguagePreference::current();
    $other   = 'ar' === $current ? 'en' : 'ar';
    // Each language is named in itself, as the account page's field names them.
    $names = ['en' => ['English', 'En'], 'ar' => ['العربية', 'ع']];

    return [
        'code'  => $other,
        'name'  => $names[$other][0],
        'short' => $names[$other][1],
        'url'   => \MizzeySite\I18n\LanguagePreference::switchUrl($other),
    ];
}

/** Help's address in the request's language: a page of this site is followed to its translation. */
function help(string $url): string
{
    $url = trim($url);
    if ('' === $url) {
        return '';
    }
    $post = url_to_postid($url);
    if ($post > 0) {
        $translated = (int) apply_filters('wpml_object_id', $post, (string) get_post_type($post), true);
        $link       = get_permalink($translated > 0 ? $translated : $post);

        return is_string($link) ? $link : $url;
    }

    return $url;
}

/**
 * The block's markup.
 *
 * @param array<string,mixed> $attributes
 */
function render(array $attributes = []): string
{
    $d = data($attributes);

    $context = [
        'rest'       => esc_url_raw(rest_url('mizzey/v1/header')),
        'nonce'      => wp_create_nonce('wp_rest'),
        'cartLabels' => cart_labels(),
        'cartEmpty'  => __('Cart, empty', 'mizzey-site'),
        'rowMax'     => ROW_MAX,
    ];

    $classes = [
        'mizzey-header',
        'mizzey-header--' . $d['style'],
        'mizzey-header--form-' . $d['form'],
    ];

    $inner = bar($d) . nav($d) . chips($d) . mega($d) . element('div', ['class' => 'mizzey-mega__scrim', 'hidden' => true, 'data-wp-on--click' => 'actions.megaClose']);
    $tail  = drawer($d) . ('compact' === $d['form'] ? '' : tabbar($d) . categories_panel($d)) . \MizzeySite\Theme\MiniCart\render();

    return element(
        'div',
        [
            'class'                       => implode(' ', $classes),
            'data-wp-interactive'         => STORE,
            'data-wp-context'             => (string) wp_json_encode($context),
            'data-wp-init'                => 'callbacks.init',
            'data-wp-on--keydown'         => 'actions.key',
            'data-wp-on-document--click'  => 'actions.outside',
        ],
        $inner
    ) . $tail;
}

/**
 * The cart's spoken name for any count, in each plural form the language has (CLDR categories), from the
 * translation's own plural forms; the script picks one with Intl.PluralRules.
 *
 * @return array<string,string>
 */
function cart_labels(): array
{
    $samples = ['zero' => 0, 'one' => 1, 'two' => 2, 'few' => 3, 'many' => 11, 'other' => 100];
    $out     = [];
    foreach ($samples as $category => $n) {
        /* translators: %d: the number of items in the cart, read out with the cart link. */
        $out[$category] = _n('Cart, %d item', 'Cart, %d items', $n, 'mizzey-site');
    }

    return $out;
}

function cart_label(int $count): string
{
    if ($count <= 0) {
        return __('Cart, empty', 'mizzey-site');
    }

    /* translators: %d: the number of items in the cart, read out with the cart link. */
    return sprintf(_n('Cart, %d item', 'Cart, %d items', $count, 'mizzey-site'), $count);
}

/** @param array<string,mixed> $d */
function logo_html(array $d, string $class): string
{
    $logo  = $d['logo'];
    $image = $logo['id'] > 0 ? wp_get_attachment_image($logo['id'], 'medium', false, ['class' => 'mizzey-header__logo-image', 'alt' => $logo['alt'], 'loading' => 'eager', 'decoding' => 'async', 'fetchpriority' => 'high']) : '';
    $inner = '' !== $image ? $image : element('span', ['class' => 'mizzey-header__logo-text'], esc_html($logo['alt']));

    return element('a', ['class' => $class, 'href' => esc_url($d['home']), 'rel' => 'home'], $inner);
}

/** @param array<string,mixed> $d */
function bar(array $d): string
{
    $menu = element(
        'button',
        // aria-expanded follows the drawer (header.js), and the wave icon turns into the cross with it.
        ['type' => 'button', 'class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-header__menu'), 'aria-label' => __('Menu', 'mizzey-site'), 'aria-expanded' => 'false'] + opens(DRAWER),
        icon('menu')
    );

    $field = 'mizzey-header-search';
    $search = element(
        'form',
        ['class' => 'mizzey-header__search', 'role' => 'search', 'method' => 'get', 'action' => esc_url((string) $d['search']['url'])],
        element('label', ['class' => 'mizzey-visually-hidden', 'for' => $field], esc_html__('Search products and brands', 'mizzey-site'))
        . icon('search', ['size' => 20, 'class' => 'mizzey-header__search-icon'])
        . sprintf(
            '<input%s>',
            attributes([
                'class'        => 'mizzey-header__search-input',
                'id'           => $field,
                'type'         => 'search',
                'name'         => (string) $d['search']['parameter'],
                'placeholder'  => __('Search products and brands', 'mizzey-site'),
                'autocomplete' => 'off',
                'enterkeyhint' => 'search',
            ])
        )
        . sprintf('<input type="hidden" name="post_type" value="%s">', esc_attr((string) $d['search']['post_type']))
    );

    $actions = '';
    if (null !== $d['language']) {
        $lang     = $d['language'];
        $actions .= element('a', ['class' => 'mizzey-header__language', 'href' => esc_url($lang['url']), 'hreflang' => $lang['code'], 'lang' => $lang['code']], esc_html($lang['name']));
    }
    if ('' !== $d['help']) {
        $actions .= element('a', ['class' => 'mizzey-header__help', 'href' => esc_url($d['help'])], icon('help') . esc_html__('Help', 'mizzey-site'));
    }
    $actions .= account_link($d);
    $actions .= tooltip(
        element('a', ['class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-header__wishlist'), 'href' => esc_url((string) $d['wishlist']['url']), 'aria-label' => __('Wishlist', 'mizzey-site')], icon('heart')),
        __('Wishlist', 'mizzey-site')
    );
    if ('compact' === $d['form']) {
        $actions .= element(
            'button',
            ['type' => 'button', 'class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-header__search-toggle'), 'aria-label' => __('Search', 'mizzey-site'), 'aria-expanded' => 'false', 'aria-controls' => $field, 'data-wp-on--click' => 'actions.searchToggle'],
            icon('search')
        );
    }
    $count    = (int) $d['cart']['count'];
    $actions .= tooltip(
        element(
            'a',
            // The control opens the mini cart (slice 12) where the page carries it, and is a link to the cart page.
            ['class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-header__cart'), 'href' => esc_url((string) $d['cart']['url'])] + \MizzeySite\Theme\MiniCart\control_attributes(),
            icon('cart')
            . element('span', ['class' => classes('mizzey-visually-hidden', 'mizzey-header__cart-label')], esc_html(cart_label($count)))
            // An empty cart shows no count (C1-Global B2); the element stays for the live count to fill.
            . ($count > 0 ? counter($count) : element('span', ['class' => 'mizzey-count', 'aria-hidden' => 'true', 'hidden' => true], '0'))
        ),
        __('Cart', 'mizzey-site')
    );

    return element(
        'div',
        // On the Indigo header only the bar is on the brand ground; the category row and the chips stay White.
        ['class' => 'mizzey-header__bar' . ('indigo' === $d['style'] ? ' mizzey-on-brand' : '')],
        element('div', ['class' => 'mizzey-header__top'], $menu . logo_html($d, 'mizzey-header__logo') . $search . element('div', ['class' => 'mizzey-header__actions'], $actions))
    );
}

/** @param array<string,mixed> $d */
function account_link(array $d): string
{
    $url = esc_url((string) $d['account']['url']);
    if ('' === $d['account']['name']) {
        return tooltip(
            element('a', ['class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-header__account'), 'href' => $url, 'aria-label' => __('Account', 'mizzey-site')], icon('user')),
            __('Account', 'mizzey-site')
        );
    }

    return tooltip(
        element(
            'a',
            ['class' => classes('mizzey-header__account', 'mizzey-header__account--named'), 'href' => $url],
            icon('userf')
            . element('span', ['class' => 'mizzey-visually-hidden'], esc_html__('Account', 'mizzey-site') . ': ')
            . element('span', ['class' => 'mizzey-header__account-name'], esc_html($d['account']['name']))
        ),
        __('Account', 'mizzey-site')
    );
}

/** The desktop category row: "All categories", then the menu's links, the overflow under "More". */
function nav(array $d): string
{
    $all = element(
        'button',
        [
            'type'              => 'button',
            'class'             => 'mizzey-header__all',
            'aria-expanded'     => 'false',
            'aria-controls'     => MEGA,
            'data-wp-on--click' => 'actions.megaToggle',
        ],
        icon('grid', ['class' => 'mizzey-header__all-rest']) . icon('gridf', ['class' => 'mizzey-header__all-open']) . esc_html__('All categories', 'mizzey-site')
    );

    $items = '';
    foreach ($d['links'] as $link) {
        $items .= element(
            'li',
            ['class' => 'mizzey-header__item'],
            element(
                'a',
                ['class' => 'mizzey-header__link' . ($link['offer'] ? ' mizzey-header__link--offer' : ''), 'href' => esc_url($link['url'])],
                ($link['offer'] ? icon('tag', ['size' => 22]) : '') . esc_html($link['label'])
            )
        );
    }
    $more = element(
        'li',
        ['class' => 'mizzey-header__more', 'hidden' => true],
        element(
            'button',
            ['type' => 'button', 'class' => classes('mizzey-header__link', 'mizzey-header__more-button'), 'aria-expanded' => 'false', 'aria-controls' => 'mizzey-header-more', 'data-wp-on--click' => 'actions.moreToggle'],
            esc_html__('More', 'mizzey-site') . icon('down', ['size' => 20])
        )
        . element('ul', ['class' => classes('mizzey-popover', 'mizzey-header__more-list'), 'id' => 'mizzey-header-more', 'hidden' => true])
    );

    return element(
        'nav',
        ['class' => 'mizzey-header__row', 'aria-label' => __('Main menu', 'mizzey-site')],
        element(
            'div',
            ['class' => 'mizzey-header__row-inner'],
            element('div', ['class' => 'mizzey-header__row-start'], $all . element('span', ['class' => 'mizzey-header__divider', 'aria-hidden' => 'true']))
            . element('ul', ['class' => 'mizzey-header__links'], $items . $more)
        )
    );
}

/** The phone's chips on the home page: the offer links first, then the menu's other links. */
function chips(array $d): string
{
    if ('home' !== $d['form'] || [] === $d['links']) {
        return '';
    }
    $ordered = array_merge(
        array_filter($d['links'], static fn (array $l): bool => $l['offer']),
        array_filter($d['links'], static fn (array $l): bool => ! $l['offer'])
    );
    $items = '';
    foreach ($ordered as $link) {
        $args = ['label' => $link['label'], 'url' => $link['url']];
        if ($link['offer']) {
            $args['icon'] = 'tag';
        } elseif ('' !== $link['image']) {
            $args['image'] = $link['image'];
        }
        $items .= element('li', [], chip($args));
    }

    return element('nav', ['class' => 'mizzey-header__chips', 'aria-label' => __('Shop by category', 'mizzey-site')], element('ul', [], $items));
}

/** A category's small picture, decorative beside its name. */
function thumb(string $url, int $size, string $class): string
{
    if ('' === $url) {
        return element('span', ['class' => $class . ' is-empty', 'aria-hidden' => 'true']);
    }

    return sprintf('<img class="%1$s" src="%2$s" alt="" width="%3$d" height="%3$d" loading="lazy" decoding="async">', esc_attr($class), esc_url($url), $size);
}

/** The address of a category's newest products. */
function newest(string $url): string
{
    return add_query_arg('orderby', 'date', $url);
}

/** The desktop mega menu: the top categories at the start, the active one's panel beside them. */
function mega(array $d): string
{
    if ([] === $d['tree']) {
        return '';
    }
    $rows   = '';
    $panels = '';
    foreach (array_values($d['tree']) as $i => $node) {
        $id   = MEGA . '-' . (int) $node['id'];
        $kids = (array) $node['children'];
        $rows .= element(
            'li',
            [],
            element(
                'a',
                [
                    'class'                  => 'mizzey-mega__row' . (0 === $i ? ' is-active' : ''),
                    'href'                   => esc_url((string) $node['url']),
                    'data-wp-context'        => (string) wp_json_encode(['panel' => $id]),
                    'data-wp-on--focus'      => 'actions.megaShow',
                    'data-wp-on--mouseenter' => 'actions.megaShow',
                ],
                thumb((string) $node['image'], 32, 'mizzey-mega__icon')
                . element('span', ['class' => 'mizzey-mega__name'], esc_html((string) $node['name']))
                . ([] === $kids ? '' : icon('fwd', ['size' => 20, 'class' => 'mizzey-mega__chevron']))
            )
        );
        $panels .= mega_panel($node, $id, 0 === $i);
    }

    return element(
        'div',
        ['class' => 'mizzey-mega', 'id' => MEGA, 'hidden' => true, 'role' => 'region', 'aria-label' => __('All categories', 'mizzey-site'), 'data-wp-on--keydown' => 'actions.megaKey'],
        element('div', ['class' => 'mizzey-mega__inner'], element('ul', ['class' => 'mizzey-mega__rows'], $rows) . $panels)
    );
}

/** @param array<string,mixed> $node */
function mega_panel(array $node, string $id, bool $shown): string
{
    $name = (string) $node['name'];
    $url  = (string) $node['url'];
    $kids = (array) $node['children'];

    if ([] === $kids) {
        /* translators: %s: a category name, for a category with no sub-categories, for example "Shop Gift cards". */
        $cta  = sprintf(__('Shop %s', 'mizzey-site'), $name);
        $body = element('h2', ['class' => 'mizzey-mega__title'], esc_html($name))
            . (has_description($node) ? element('p', ['class' => 'mizzey-mega__text'], esc_html(description($node))) : '')
            . element('a', ['class' => classes('mizzey-button', 'mizzey-button--primary', 'mizzey-button--compact', 'mizzey-mega__all'), 'href' => esc_url($url)], element('span', ['class' => 'mizzey-button__label'], esc_html($cta)))
            . element('a', ['class' => classes('mizzey-mega__photo', 'mizzey-mega__photo--wide'), 'href' => esc_url($url), 'tabindex' => '-1', 'aria-hidden' => 'true'], '' === (string) $node['image'] ? '' : sprintf('<img src="%s" alt="" loading="lazy" decoding="async">', esc_url((string) $node['image'])));

        return element('div', ['class' => classes('mizzey-mega__panel', 'mizzey-mega__panel--single'), 'id' => $id, 'hidden' => ! $shown], $body);
    }

    $children = '';
    foreach ($kids as $kid) {
        $children .= element(
            'li',
            [],
            element(
                'a',
                ['class' => 'mizzey-mega__child', 'href' => esc_url((string) $kid['url'])],
                element('span', ['class' => 'mizzey-mega__child-name'], esc_html((string) $kid['name'])) . icon('fwd', ['size' => 20])
            )
        );
    }
    /* translators: %s: a category name, on the link to all of its products. */
    $all = sprintf(__('Shop all %s', 'mizzey-site'), $name);
    /* translators: %s: a category name, on the link to its newest products. */
    $new   = sprintf(__('New in %s', 'mizzey-site'), $name);
    $photo = '' === (string) $node['image']
        ? ''
        : element(
            'a',
            ['class' => 'mizzey-mega__photo', 'href' => esc_url(newest($url))],
            sprintf('<img src="%s" alt="" loading="lazy" decoding="async">', esc_url((string) $node['image']))
            . element('span', ['class' => 'mizzey-mega__photo-pill'], esc_html($new) . icon('fwd', ['size' => 20]))
        );

    return element(
        'div',
        ['class' => 'mizzey-mega__panel', 'id' => $id, 'hidden' => ! $shown],
        element(
            'div',
            ['class' => 'mizzey-mega__head'],
            element('h2', ['class' => 'mizzey-mega__title'], esc_html($name))
            . element('a', ['class' => classes('mizzey-button', 'mizzey-button--primary', 'mizzey-button--compact', 'mizzey-mega__all'), 'href' => esc_url($url)], element('span', ['class' => 'mizzey-button__label'], esc_html($all)) . icon('fwd', ['size' => 20]))
        )
        . element('ul', ['class' => 'mizzey-mega__children'], $children)
        . $photo
    );
}

/** @param array<string,mixed> $node */
function has_description(array $node): bool
{
    return '' !== description($node);
}

/**
 * A category's own description, its first sentence, as plain text.
 *
 * @param array<string,mixed> $node
 */
function description(array $node): string
{
    $text = trim(wp_strip_all_tags((string) term_description((int) $node['id'], 'product_cat')));
    if ('' === $text) {
        return '';
    }
    $sentences = preg_split('/(?<=[.!?\x{061F}])\s+/u', $text) ?: [$text];

    return (string) $sentences[0];
}

/** The phone menu: a drawer from the start edge, one level per category with children. */
function drawer(array $d): string
{
    $levels = [];
    $top    = '';
    // The open stagger's steps (header.css): the head 0, then the offers, the rows and the foot, six at most.
    $step = 1;
    foreach ($d['links'] as $link) {
        if ($link['offer']) {
            $top .= element(
                'a',
                ['class' => 'mizzey-menu__offer', 'href' => esc_url($link['url']), 'data-mizzey-step' => (string) min($step++, 5)],
                icon('tag', ['size' => 22]) . element('span', ['class' => 'mizzey-menu__offer-name'], esc_html($link['label'])) . icon('fwd', ['size' => 20])
            );
        }
    }
    $top   .= element('ul', ['class' => 'mizzey-menu__list'], drawer_rows($d['tree'], DRAWER . '-0', $levels, true, $step));
    $head   = element(
        'div',
        ['class' => 'mizzey-menu__head', 'data-mizzey-step' => '0'],
        logo_html(['logo' => logo(0)] + $d, 'mizzey-menu__logo') . close_button(true)
    );
    $first  = element('div', ['class' => 'mizzey-menu__level', 'id' => DRAWER . '-0'], $head . element('div', ['class' => 'mizzey-menu__body'], $top));

    $panel = element('div', ['class' => classes('mizzey-overlay__panel', 'mizzey-menu__panel')], $first . implode('', $levels) . drawer_foot($d, min($step, 5)));

    return element(
        'dialog',
        [
            'id'                  => DRAWER,
            'class'               => 'mizzey-overlay mizzey-overlay--drawer-start mizzey-menu',
            'aria-label'          => __('Menu', 'mizzey-site'),
            // The base drawer's behaviour (store mizzey/ui, as overlay() wires it): showModal() moves focus in, keeps
            // the page behind inert, closes on Escape and returns focus; a press that starts and ends on the scrim
            // closes. The drawer's own markup holds one head per level, which overlay()'s single title does not.
            'data-wp-interactive'     => 'mizzey/ui',
            'data-wp-on--pointerdown' => 'actions.scrimDown',
            'data-wp-on--click'       => 'actions.backdrop',
            'data-wp-init'            => 'mizzey/header::callbacks.initDrawer',
        ],
        $panel
    );
}

/**
 * The drawer's close. On the first level it carries the wave menu icon, which turns into the cross as the drawer
 * opens (C1-Menus-Phone, frame 1; header.css); its label is "Close" from the start. The header's own menu control
 * keeps its 24 icon.
 */
function close_button(bool $morph = false): string
{
    return element(
        'button',
        ['type' => 'button', 'class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-menu__close', ...($morph ? ['mizzey-menu__close--morph'] : [])), 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-interactive' => 'mizzey/ui', 'data-wp-on--click' => 'actions.close'],
        // 20 in the 44 target, as C1-Menus-Phone draws the drawer's close (frames 1, 2 and 5).
        icon($morph ? 'menu' : 'close', ['size' => 20])
    );
}

/**
 * The rows of one level; a category with children opens its own level, which is added to $levels.
 *
 * @param list<array<string,mixed>> $nodes
 * @param list<string>              $levels
 */
function drawer_rows(array $nodes, string $parent, array &$levels, bool $icons = true, ?int &$step = null): string
{
    $rows = '';
    foreach ($nodes as $node) {
        $li    = null === $step ? [] : ['data-mizzey-step' => (string) min($step++, 5)];
        $kids  = (array) $node['children'];
        $inner = ($icons ? thumb((string) $node['image'], 32, 'mizzey-menu__icon') : '')
            . element('span', ['class' => 'mizzey-menu__name'], esc_html((string) $node['name']));
        if ([] === $kids) {
            // On the deeper levels every row carries its chevron, as C1-Menus-Phone draws them.
            $chevron = $icons ? '' : icon('fwd', ['size' => 20, 'class' => 'mizzey-menu__chevron']);
            $rows   .= element('li', $li, element('a', ['class' => 'mizzey-menu__row' . ($icons ? '' : ' mizzey-menu__row--soft'), 'href' => esc_url((string) $node['url'])], $inner . $chevron));
            continue;
        }
        $id    = DRAWER . '-' . (int) $node['id'];
        $rows .= element(
            'li',
            $li,
            element(
                'button',
                [
                    'type'                => 'button',
                    'class'               => 'mizzey-menu__row' . ($icons ? '' : ' mizzey-menu__row--soft'),
                    'aria-expanded'       => 'false',
                    'aria-controls'       => $id,
                    'data-wp-interactive' => STORE,
                    'data-wp-context'     => (string) wp_json_encode(['level' => $id]),
                    'data-wp-on--click'   => 'actions.levelOpen',
                ],
                $inner . icon('fwd', ['size' => 20, 'class' => 'mizzey-menu__chevron'])
            )
        );
        $levels[] = drawer_level($node, $id, $parent, $levels);
    }

    return $rows;
}

/**
 * @param array<string,mixed> $node
 * @param list<string>        $levels
 */
function drawer_level(array $node, string $id, string $parent, array &$levels): string
{
    $name = (string) $node['name'];
    $url  = (string) $node['url'];
    /* translators: %s: a category name, on the link to all of its products. */
    $all = sprintf(__('Shop all %s', 'mizzey-site'), $name);
    if ('' !== (string) $node['image']) {
        $shop = element(
            'a',
            ['class' => classes('mizzey-menu__all', 'mizzey-menu__banner'), 'href' => esc_url($url)],
            sprintf('<img src="%s" alt="" loading="lazy" decoding="async">', esc_url((string) $node['image']))
            . element('span', ['class' => 'mizzey-menu__banner-pill'], esc_html($all) . icon('fwd', ['size' => 20]))
        );
    } else {
        $shop = element('a', ['class' => classes('mizzey-menu__all', 'mizzey-menu__all--plain'), 'href' => esc_url($url)], element('span', [], esc_html($all)) . icon('fwd', ['size' => 20]));
    }
    $head = element(
        'div',
        ['class' => 'mizzey-menu__head'],
        element(
            'button',
            [
                'type'                => 'button',
                'class'               => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-menu__back'),
                'aria-label'          => __('Back', 'mizzey-site'),
                'data-wp-interactive' => STORE,
                'data-wp-context'     => (string) wp_json_encode(['level' => $parent, 'from' => $id]),
                'data-wp-on--click'   => 'actions.levelBack',
            ],
            icon('back')
        )
        . element('h2', ['class' => 'mizzey-menu__title', 'id' => $id . '-title'], esc_html($name))
        . close_button()
    );

    return element(
        'div',
        ['class' => 'mizzey-menu__level', 'id' => $id, 'hidden' => true, 'role' => 'group', 'aria-labelledby' => $id . '-title'],
        $head . element('div', ['class' => 'mizzey-menu__body'], $shop . element('ul', ['class' => 'mizzey-menu__list'], drawer_rows((array) $node['children'], $id, $levels, false)))
    );
}

/** The drawer's foot: language, account, wishlist. */
function drawer_foot(array $d, int $step): string
{
    $tiles = '';
    if (null !== $d['language']) {
        $lang   = $d['language'];
        $tiles .= element(
            'a',
            ['class' => classes('mizzey-menu__tile', 'mizzey-menu__language'), 'href' => esc_url($lang['url']), 'hreflang' => $lang['code'], 'lang' => $lang['code']],
            element('span', ['class' => 'mizzey-menu__tile-mark', 'aria-hidden' => 'true'], esc_html($lang['short'])) . element('span', [], esc_html($lang['name']))
        );
    }
    $named  = '' !== $d['account']['name'];
    $tiles .= element(
        'a',
        ['class' => classes('mizzey-menu__tile', 'mizzey-menu__account'), 'href' => esc_url((string) $d['account']['url'])],
        icon($named ? 'userf' : 'user') . element('span', [], $named ? esc_html($d['account']['name']) : esc_html__('Sign in', 'mizzey-site'))
    );
    $tiles .= element(
        'a',
        ['class' => classes('mizzey-menu__tile', 'mizzey-menu__wishlist'), 'href' => esc_url((string) $d['wishlist']['url'])],
        icon('heart') . element('span', [], esc_html__('Wishlist', 'mizzey-site'))
    );

    return element('div', ['class' => 'mizzey-menu__foot', 'data-mizzey-step' => (string) $step], $tiles);
}

/** The phone tab bar: Home, Categories, Wishlist, Account. */
function tabbar(array $d): string
{
    $front = is_front_page();
    $named = '' !== $d['account']['name'];
    $tab   = static fn (string $class, string $icon, string $label, array $attrs, string $tag = 'a'): string => element(
        $tag,
        ['class' => 'mizzey-tabbar__item ' . $class] + $attrs,
        element('span', ['class' => 'mizzey-tabbar__icon'], $icon) . element('span', ['class' => 'mizzey-tabbar__label'], esc_html($label))
    );

    $items = $tab('mizzey-tabbar__home', icon($front ? 'homef' : 'home'), __('Home', 'mizzey-site'), ['href' => esc_url($d['home']), 'aria-current' => $front ? 'page' : null])
        . $tab('mizzey-tabbar__categories', icon('grid', ['class' => 'mizzey-tabbar__rest']) . icon('gridf', ['class' => 'mizzey-tabbar__on']), __('Categories', 'mizzey-site'), ['type' => 'button', 'aria-expanded' => 'false', 'aria-controls' => PANEL, 'data-wp-on--click' => 'actions.categoriesToggle'], 'button')
        . $tab('mizzey-tabbar__wishlist', icon('heart'), __('Wishlist', 'mizzey-site'), ['href' => esc_url((string) $d['wishlist']['url'])])
        . $tab('mizzey-tabbar__account', icon($named ? 'userf' : 'user'), $named ? (string) $d['account']['name'] : __('Account', 'mizzey-site'), ['href' => esc_url((string) $d['account']['url'])]);

    return element('nav', ['class' => 'mizzey-tabbar', 'aria-label' => __('Shortcuts', 'mizzey-site'), 'data-wp-interactive' => STORE], $items);
}

/** The Categories tab's view: the top categories as tiles. */
function categories_panel(array $d): string
{
    if ([] === $d['tree']) {
        return '';
    }
    $tiles = '';
    foreach ($d['tree'] as $node) {
        $tiles .= element(
            'li',
            [],
            element(
                'a',
                ['class' => 'mizzey-categories__item', 'href' => esc_url((string) $node['url'])],
                element('span', ['class' => 'mizzey-categories__tile'], '' === (string) $node['image'] ? icon('grid-empty') : sprintf('<img src="%s" alt="" width="76" height="76" loading="lazy" decoding="async">', esc_url((string) $node['image'])))
                . element('span', ['class' => 'mizzey-categories__name'], esc_html((string) $node['name']))
            )
        );
    }

    return element(
        'section',
        ['class' => 'mizzey-categories', 'id' => PANEL, 'hidden' => true, 'aria-labelledby' => PANEL . '-title', 'tabindex' => '-1', 'data-wp-interactive' => STORE, 'data-wp-on--keydown' => 'actions.key'],
        element('h2', ['class' => 'mizzey-categories__title', 'id' => PANEL . '-title', 'tabindex' => '-1'], esc_html__('Categories', 'mizzey-site'))
        . element('ul', ['class' => 'mizzey-categories__grid'], $tiles)
    );
}

<?php

/**
 * The designed category listing (spec 066, slice 6), as C1-Category-Desktop, C1-Category-Phone and C1-Options (the
 * price filter P1, chosen) draw it: the breadcrumb, the banner card with the description and the banner image, the
 * child categories as chips, the filter column on a desktop and the filters drawer on a phone, the toolbar (the
 * result count, the sort, the Filters control), the active filters each with its remove control, the grid of product
 * cards (slice 4), numbered paging, the loading state and the two empty states.
 *
 * One dynamic block, `mizzey/category-listing`, placed in templates/taxonomy-product_cat.html, renders it on the
 * server. Its data is MizzeySite\Storefront\Listing, which reads WooCommerce's product query (specs/057); the filters
 * and the sort are WooCommerce's own address parameters. The page works without JavaScript: the filter column is a
 * form whose button applies it, the sorts are links, and on a phone the Filters control opens the form as a page
 * section. With JavaScript (assets/js/listing.js, store `mizzey/listing`) a desktop applies each change at once, the
 * phone's drawer asks the server for its one count, and the price group is a slider over a bar chart of prices.
 *
 * Every word a visitor reads is translatable in the mizzey-site domain; names of categories, brands and values are
 * the store's own, per language. The look is in assets/css/listing.css; the drawer, the sheet and the sort list are
 * slice 1's components, driven by assets/js/ui.js.
 *
 * Register ids: PLP-06 to PLP-10, PLP-12 to PLP-22, SSC-04. See specs/066-stage-2-shop-front, slice 6.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Listing;

use MizzeySite\Storefront\Listing;
use MizzeySite\Storefront\ProductCard;
use MizzeySite\Storefront\ProductRail;

use function MizzeySite\Theme\Components\attributes;
use function MizzeySite\Theme\Components\button;
use function MizzeySite\Theme\Components\check;
use function MizzeySite\Theme\Components\chip;
use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Components\empty_state;
use function MizzeySite\Theme\Components\loader;
use function MizzeySite\Theme\Components\opens;
use function MizzeySite\Theme\Components\radio;
use function MizzeySite\Theme\Icons\icon;

defined('ABSPATH') || exit;

const BLOCK  = 'mizzey/category-listing';
const STORE  = 'mizzey/listing';
const DRAWER = 'mizzey-filters-drawer';
const SHEET  = 'mizzey-sort-sheet';
const ASIDE  = 'mizzey-filters';

add_action('init', __NAMESPACE__ . '\\register');
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\assets');

function register(): void
{
    $file = get_theme_file_path('assets/js/listing.js');
    wp_register_script_module(
        'mizzey-theme/listing',
        get_theme_file_uri('assets/js/listing.js'),
        [['id' => '@wordpress/interactivity', 'import' => 'static']],
        is_file($file) ? (string) filemtime($file) : false
    );
    register_block_type(BLOCK, [
        'api_version'     => 3,
        'title'           => __('Category listing', 'mizzey-site'),
        'category'        => 'theme',
        'icon'            => 'grid-view',
        'description'     => __('The category page: banner, filters, sort, products and paging.', 'mizzey-site'),
        'supports'        => ['html' => false, 'inserter' => false, 'multiple' => false, 'reusable' => false, 'className' => false, 'customClassName' => false],
        'render_callback' => __NAMESPACE__ . '\\render',
    ]);
}

/** The listing's look and behaviour, on a category page only. */
function assets(): void
{
    if (! class_exists(Listing::class) || ! Listing::isListing()) {
        return;
    }
    $file = get_theme_file_path('assets/css/listing.css');
    wp_enqueue_style('mizzey-listing', get_theme_file_uri('assets/css/listing.css'), ['mizzey-header'], is_file($file) ? (string) filemtime($file) : null);
    wp_enqueue_script_module('mizzey-theme/listing');
    // The grid is slice 4's rail in its grid layout, rendered here directly, so its stylesheet is asked for here.
    wp_enqueue_style('mizzey-product-rail-style');
}

/** A class attribute from its class names. */
function classes(string ...$names): string
{
    return implode(' ', array_filter($names));
}

/** One count, in words: "248 products". */
function products(int $count): string
{
    /* translators: %s: a number of products. */
    return sprintf(_n('%s product', '%s products', $count, 'mizzey-site'), number_format_i18n($count));
}

/** The drawer button's words: "Show 248 products". */
function show(int $count): string
{
    /* translators: %s: how many products the chosen filters find. */
    return sprintf(_n('Show %s product', 'Show %s products', $count, 'mizzey-site'), number_format_i18n($count));
}

/** A number set apart in a line of muted text. */
function strong(int $number): string
{
    return element('span', ['class' => 'mizzey-listing__number'], esc_html(number_format_i18n($number)));
}

/** @param array<string,mixed> $view */
function render(array $attributes = [], string $content = '', $block = null): string
{
    $view = class_exists(Listing::class) ? Listing::view() : null;
    if (null === $view) {
        return '';
    }

    $html = element('div', ['class' => 'mizzey-listing__crumbs'], crumbs())
        . banner($view)
        . chips($view);

    if (0 === $view['found'] && ! $view['filtered']) {
        $html .= emptyCategory($view);
    } else {
        $html .= element('div', ['class' => 'mizzey-listing__layout'], filters($view) . main($view))
            . drawer($view) . sheet($view)
            . sprintf('<template id="mizzey-listing-skeleton">%s</template>', ProductCard::skeleton());
    }

    return element('div', [
        'class'               => 'mizzey-listing alignfull',
        'data-wp-interactive' => STORE,
        'data-wp-init'        => 'callbacks.init',
        'data-count-url'      => esc_url((string) $view['countUrl']),
        'data-base'           => esc_url((string) $view['base']),
        'data-default-sort'   => Listing::defaultSort(),
        'data-updating'       => __('Updating', 'mizzey-site'),
    ], $html);
}

/** WooCommerce's breadcrumb, with a translated name and the designed separator. */
function crumbs(): string
{
    $defaults = static function (array $args): array {
        $args['delimiter']   = '<span class="mizzey-listing__crumb-sep" aria-hidden="true">/</span>';
        $args['wrap_before'] = sprintf('<nav class="woocommerce-breadcrumb" aria-label="%s">', esc_attr__('Breadcrumb', 'mizzey-site'));
        return $args;
    };
    add_filter('woocommerce_breadcrumb_defaults', $defaults, 20);
    $html = render_block(['blockName' => 'woocommerce/breadcrumbs', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
    remove_filter('woocommerce_breadcrumb_defaults', $defaults, 20);

    return (string) $html;
}

/** @param array<string,mixed> $view */
function banner(array $view): string
{
    $intro = element('h1', ['class' => 'mizzey-listing__title'], esc_html((string) $view['name']))
        . ('' !== $view['description'] ? element('p', ['class' => 'mizzey-listing__description'], esc_html((string) $view['description'])) : '');
    $tile = '';
    if (is_array($view['image'])) {
        $img  = sprintf('<img%s>', attributes([
            'src'           => esc_url((string) $view['image']['url']),
            'alt'           => esc_attr((string) $view['image']['alt']),
            'width'         => '128',
            'height'        => '128',
            'decoding'      => 'async',
            'fetchpriority' => 'high',
        ]));
        $tile = '' !== $view['image']['link']
            ? element('a', ['class' => 'mizzey-listing__tile', 'href' => esc_url((string) $view['image']['link'])], $img)
            : element('span', ['class' => 'mizzey-listing__tile'], $img);
    }

    return element('section', ['class' => 'mizzey-listing__banner' . ('' === $tile ? ' is-plain' : ''), 'aria-labelledby' => 'mizzey-listing-title'], element('div', ['class' => 'mizzey-listing__intro', 'id' => 'mizzey-listing-title'], $intro) . $tile);
}

/** @param array<string,mixed> $view */
function chips(array $view): string
{
    if ([] === $view['cats'] || (0 === $view['found'] && ! $view['filtered'])) {
        return '';
    }
    $items = '';
    foreach ($view['cats'] as $cat) {
        $items .= element('li', [], chip(['label' => (string) $cat['label'], 'count' => (int) $cat['count'], 'url' => (string) $cat['url'], 'chosen' => (bool) $cat['chosen']]));
    }

    return element('nav', ['class' => 'mizzey-listing__cats', 'aria-label' => __('Categories', 'mizzey-site')], element('ul', ['role' => 'list'], $items));
}

/* ---- The filters ------------------------------------------------------------------------------------------------ */

/** @param array<string,mixed> $view */
function filters(array $view): string
{
    $groups = '';
    $open   = 0;
    foreach ($view['groups'] as $group) {
        $groups .= 'price' === $group['kind'] ? price($group, (int) $view['found']) : terms($group, $open < 4 || $group['picked'] > 0);
        ++$open;
    }
    $groups .= element('div', ['class' => 'mizzey-filter mizzey-filter--switches', 'data-group' => 'availability'],
        toggle(__('In stock only', 'mizzey-site'), Listing::STOCK, 'instock', (bool) $view['stock'])
        . toggle(__('On sale', 'mizzey-site'), Listing::SALE, '1', (bool) $view['sale']));

    $hidden = '';
    if (isset($view['chosen']['orderby'])) {
        $hidden .= sprintf('<input%s>', attributes(['type' => 'hidden', 'name' => 'orderby', 'value' => (string) $view['chosen']['orderby']]));
    }
    $hidden .= sprintf('<input%s>', attributes(['type' => 'hidden', 'name' => Listing::FORM, 'value' => '1']));

    $head = element('div', ['class' => 'mizzey-filters__head'],
        element('h2', ['class' => 'mizzey-filters__title', 'id' => 'mizzey-filters-title'], esc_html__('Filters', 'mizzey-site'))
        . element('a', ['class' => 'mizzey-filters__close', 'href' => '#', 'aria-label' => __('Close the filters', 'mizzey-site')], icon('close')));
    $foot = element('div', ['class' => 'mizzey-filters__foot'],
        element('button', ['type' => 'submit', 'class' => classes('mizzey-button', 'mizzey-button--primary', 'mizzey-button--block', 'mizzey-listing__show', 'mizzey-filters__submit'), 'data-shown' => show((int) $view['found'])],
            loader(['label' => __('Updating', 'mizzey-site')]) . element('span', ['class' => 'mizzey-button__label'], esc_html(show((int) $view['found'])))));

    return element('aside', ['class' => 'mizzey-filters', 'id' => ASIDE, 'aria-labelledby' => 'mizzey-filters-title'],
        element('form', ['class' => 'mizzey-filters__panel', 'method' => 'get', 'action' => esc_url((string) $view['base'])],
            $head . element('div', ['class' => 'mizzey-filters__groups'], $groups) . $hidden . $foot));
}

/** A group's header: its name, what is chosen, and the chevron; the group itself is a native disclosure. */
function summary(string $label, string $status): string
{
    return element('summary', ['class' => 'mizzey-filter__summary'],
        element('span', ['class' => 'mizzey-filter__name'], esc_html($label))
        . element('span', ['class' => 'mizzey-filter__status'], esc_html($status) . icon('up', ['size' => 20, 'class' => 'mizzey-filter__chevron'])));
}

/** @param array<string,mixed> $group */
function terms(array $group, bool $open): string
{
    $picked = (int) $group['picked'];
    /* translators: %d: how many values of one filter are chosen, for example brands. */
    $status = $picked > 0 ? sprintf(_n('%d chosen', '%d chosen', $picked, 'mizzey-site'), $picked) : ('all' === $group['any'] ? __('All', 'mizzey-site') : __('Any', 'mizzey-site'));
    $rows   = '';
    $index  = 0;
    $more   = count($group['options']) > Listing::SHOWN;
    foreach ($group['options'] as $option) {
        $row   = check([
            'name'     => $group['param'] . '[]',
            'value'    => (string) $option['value'],
            'label'    => (string) $option['label'],
            'count'    => (int) $option['count'],
            'checked'  => (bool) $option['checked'],
            'disabled' => 0 === (int) $option['count'] && ! $option['checked'],
        ]);
        $rows .= element('li', ['class' => 'mizzey-filter__option' . ($more && $index >= Listing::SHOWN && ! $option['checked'] ? ' is-extra' : ''), 'data-name' => mb_strtolower((string) $option['label'])], $row);
        ++$index;
    }
    $search = '';
    if ('brands' === $group['param'] && count($group['options']) > Listing::SHOWN) {
        $id     = 'mizzey-filter-search-' . sanitize_html_class((string) $group['param']);
        $search = element('div', ['class' => 'mizzey-filter__search'],
            element('label', ['class' => 'mizzey-visually-hidden', 'for' => $id], esc_html__('Search brands', 'mizzey-site'))
            . icon('search', ['size' => 20])
            . sprintf('<input%s>', attributes(['type' => 'search', 'id' => $id, 'class' => 'mizzey-filter__find', 'placeholder' => __('Search brands', 'mizzey-site'), 'autocomplete' => 'off'])));
    }
    $toggle = $more
        ? element('button', ['type' => 'button', 'class' => 'mizzey-filter__more', 'aria-expanded' => 'false', 'data-more' => __('Show more', 'mizzey-site'), 'data-fewer' => __('Show fewer', 'mizzey-site')], esc_html__('Show more', 'mizzey-site'))
        : '';

    return element('details', ['class' => 'mizzey-filter', 'data-group' => (string) $group['param'], 'open' => $open],
        summary((string) $group['label'], $status)
        . element('div', ['class' => 'mizzey-filter__body'], $search . element('ul', ['class' => 'mizzey-filter__options', 'role' => 'list'], $rows) . $toggle));
}

/**
 * The price group (C1-Options P1): bars of how many products sit at each price, a two-handle slider over them with a
 * bubble while dragging, the range line that opens two fields for typing, and the count line. Two native range inputs
 * carry the values, so the slider works by keyboard and without JavaScript.
 *
 * @param array<string,mixed> $group
 */
function price(array $group, int $found): string
{
    $low   = (int) $group['low'];
    $high  = (int) $group['high'];
    $from  = (int) $group['from'];
    $to    = (int) $group['to'];
    $range = Listing::range($from, $to);
    $peak  = max(1, max($group['bars']));
    $width = ($high - $low) / count($group['bars']);
    $bars  = '';
    foreach ($group['bars'] as $i => $n) {
        $start = $low + $i * $width;
        $bars .= element('span', [
            'class'      => 'mizzey-price__bar' . ($start + $width > $from && $start < $to ? ' is-in' : ''),
            'style'      => sprintf('--mizzey-price-bar:%d%%', $n > 0 ? max(8, (int) round(100 * $n / $peak)) : 4),
            'data-start' => (string) (int) round($start),
            'data-end'   => (string) (int) round($start + $width),
        ]);
    }
    $slider = static fn (string $end, string $name, int $value, string $label): string => sprintf('<input%s>', attributes([
        'type'       => 'range',
        'class'      => classes('mizzey-price__input', 'mizzey-price__' . $end),
        'name'       => $name,
        'min'        => (string) $low,
        'max'        => (string) $high,
        'step'       => (string) (int) $group['step'],
        'value'      => (string) $value,
        'aria-label' => $label,
        'data-end'   => $end,
    ]));
    $field = static fn (string $end, string $label, int $value): string => element('label', ['class' => 'mizzey-price__field'],
        element('span', ['class' => 'mizzey-price__field-label'], esc_html($label))
        . sprintf('<input%s>', attributes(['type' => 'number', 'inputmode' => 'numeric', 'class' => 'mizzey-price__type', 'min' => (string) $low, 'max' => (string) $high, 'step' => '1', 'value' => (string) $value, 'data-end' => $end, 'dir' => 'ltr'])));
    $symbol = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $body = element('div', ['class' => 'mizzey-price', 'data-low' => (string) $low, 'data-high' => (string) $high, 'data-step' => (string) (int) $group['step'], 'data-format' => Listing::range(1111, 2222)],
        element('div', ['class' => 'mizzey-price__bars', 'aria-hidden' => 'true'], $bars)
        . element('div', ['class' => 'mizzey-price__track', 'style' => sprintf('--mizzey-price-from:%s%%;--mizzey-price-to:%s%%', round(100 * ($from - $low) / ($high - $low), 2), round(100 * ($to - $low) / ($high - $low), 2))],
            element('span', ['class' => 'mizzey-price__rail', 'aria-hidden' => 'true'], element('span', ['class' => 'mizzey-price__fill']))
            /* translators: the lower handle of the price filter. */
            . $slider('min', 'min_price', $from, __('Minimum price', 'mizzey-site'))
            /* translators: the upper handle of the price filter. */
            . $slider('max', 'max_price', $to, __('Maximum price', 'mizzey-site'))
            . element('span', ['class' => 'mizzey-price__bubble', 'aria-hidden' => 'true', 'hidden' => true])
            // The bounds the slider was drawn with: a handle left at its bound applies no price (no JavaScript too).
            . sprintf('<input%s>', attributes(['type' => 'hidden', 'name' => Listing::LOW, 'value' => (string) $low]))
            . sprintf('<input%s>', attributes(['type' => 'hidden', 'name' => Listing::HIGH, 'value' => (string) $high])))
        . element('button', ['type' => 'button', 'class' => 'mizzey-price__range', 'aria-expanded' => 'false', 'aria-controls' => 'mizzey-price-fields', 'aria-label' => sprintf(/* translators: %s: the chosen price range. */ __('Type a price range, now %s', 'mizzey-site'), $range)], esc_html($range))
        . element('div', ['class' => 'mizzey-price__fields', 'id' => 'mizzey-price-fields', 'hidden' => true],
            /* translators: %s: the currency, for example EGP. */
            $field('min', sprintf(__('From, %s', 'mizzey-site'), $symbol), $from)
            /* translators: %s: the currency, for example EGP. */
            . $field('max', sprintf(__('To, %s', 'mizzey-site'), $symbol), $to))
        . element('p', ['class' => 'mizzey-price__count', 'aria-live' => 'polite'],
            loader(['label' => __('Updating the count', 'mizzey-site')])
            . element('span', ['class' => 'mizzey-price__waiting'], esc_html__('Updating the count', 'mizzey-site'))
            . element('span', ['class' => 'mizzey-price__found'], sprintf(
                /* translators: %s: a number of products. */
                esc_html(_n('%s product', '%s products', $found, 'mizzey-site')),
                strong($found)
            ))));

    return element('details', ['class' => 'mizzey-filter mizzey-filter--price', 'data-group' => 'price', 'open' => true],
        summary((string) $group['label'], $group['picked'] ? $range : '')
        . element('div', ['class' => 'mizzey-filter__body'], $body));
}

/** "In stock only" and "On sale": a checkbox shown as the switch, so the form carries it without JavaScript. */
function toggle(string $label, string $name, string $value, bool $on): string
{
    return element('label', ['class' => classes('mizzey-switch', 'mizzey-listing__switch')],
        element('span', [], esc_html($label))
        . sprintf('<input%s>', attributes(['type' => 'checkbox', 'role' => 'switch', 'class' => 'mizzey-listing__switch-box', 'name' => $name, 'value' => $value, 'checked' => $on]))
        . element('span', ['class' => 'mizzey-switch__track', 'aria-hidden' => 'true'], element('span', ['class' => 'mizzey-switch__thumb'])));
}

/* ---- The results ------------------------------------------------------------------------------------------------ */

/** @param array<string,mixed> $view */
function main(array $view): string
{
    $found = (int) $view['found'];
    $count = $view['filtered']
        ? sprintf(
            /* translators: 1: how many products the filters find, 2: how many the category has. */
            esc_html(_n('%1$s of %2$s product', '%1$s of %2$s products', (int) $view['total'], 'mizzey-site')),
            strong($found),
            esc_html(number_format_i18n((int) $view['total']))
        )
        : sprintf(
            /* translators: %s: how many products the category has. */
            esc_html(_n('%s product', '%s products', $found, 'mizzey-site')),
            strong($found)
        );
    $applied = count($view['applied']);

    $tools = sortSelect($view)
        . element('button', ['type' => 'button', 'class' => classes('mizzey-listing__tool', 'mizzey-listing__sort-open')] + opens(SHEET), icon('sort', ['size' => 20]) . element('span', [], esc_html__('Sort', 'mizzey-site')))
        . element('a', ['href' => '#' . ASIDE, 'class' => classes('mizzey-listing__tool', 'mizzey-listing__filters-open'), 'role' => 'button'] + opens(DRAWER),
            icon('filter', ['size' => 20]) . element('span', [], esc_html__('Filters', 'mizzey-site'))
            . ($applied > 0 ? element('span', ['class' => 'mizzey-listing__badge', 'aria-hidden' => 'true'], esc_html((string) $applied))
                /* translators: %d: how many filters are applied. */
                . element('span', ['class' => 'mizzey-visually-hidden'], esc_html(sprintf(_n('%d applied', '%d applied', $applied, 'mizzey-site'), $applied))) : ''))
        . sortLinks($view);

    $toolbar = element('div', ['class' => 'mizzey-listing__toolbar'],
        element('p', ['class' => classes('mizzey-listing__count', 'woocommerce-result-count'), 'role' => 'status'], $count)
        . element('div', ['class' => 'mizzey-listing__tools'], $tools));

    $chips = '';
    if ($applied > 0) {
        $items = '';
        foreach ($view['applied'] as $a) {
            $items .= element('li', [], chip(['label' => (string) $a['label'], 'url' => (string) $a['url'], 'applied' => true, 'attrs' => ['data-param' => (string) $a['param']]]));
        }
        $items .= element('li', [], element('a', ['class' => 'mizzey-listing__clear', 'href' => esc_url((string) $view['clear'])], esc_html__('Clear all', 'mizzey-site')));
        $chips  = element('ul', ['class' => 'mizzey-listing__applied', 'role' => 'list', 'aria-label' => __('Active filters', 'mizzey-site')], $items);
    }

    if ($found > 0) {
        $results = ProductRail::render((array) $view['products'], ['layout' => ProductRail::GRID, 'label' => __('Products', 'mizzey-site')]);
    } else {
        $results = element('div', ['class' => 'mizzey-listing__empty'], empty_state([
            'icon'   => 'filter',
            'title'  => __('No products match these filters', 'mizzey-site'),
            'text'   => __('Your filters are still applied. Remove one, or clear them all.', 'mizzey-site'),
            'action' => ['label' => __('Clear filters', 'mizzey-site'), 'variant' => 'secondary', 'url' => (string) $view['clear']],
        ]));
    }

    return element('div', ['class' => 'mizzey-listing__main'],
        $toolbar . $chips
        . element('div', ['class' => 'mizzey-listing__results', 'aria-busy' => 'false'], $results)
        . paging($view));
}

/**
 * The desktop sort: slice 1's select, as the board's 44 high pill ("Sort: Relevance").
 *
 * @param array<string,mixed> $view
 */
function sortSelect(array $view): string
{
    $items   = '';
    $current = '';
    foreach ($view['sorts'] as $i => $sort) {
        $chosen = $sort['value'] === $view['sort'];
        if ($chosen) {
            $current = (string) $sort['label'];
        }
        $items .= element('li', [
            'id'            => 'mizzey-sort-option-' . $i,
            'class'         => 'mizzey-select__option',
            'role'          => 'option',
            'aria-selected' => $chosen ? 'true' : 'false',
            'data-value'    => (string) $sort['value'],
            'data-label'    => (string) $sort['label'],
            'data-url'      => esc_url((string) $sort['url']),
        ], esc_html((string) $sort['label']) . icon('check', ['size' => 20]));
    }

    return element('div', ['class' => classes('mizzey-select', 'mizzey-listing__sort'), 'data-wp-interactive' => 'mizzey/ui', 'data-wp-on-document--click' => 'actions.selectOutside'],
        element('span', ['class' => 'mizzey-visually-hidden', 'id' => 'mizzey-sort-label'], esc_html__('Sort by', 'mizzey-site'))
        . element('button', [
            'type'                => 'button',
            'id'                  => 'mizzey-sort',
            'class'               => 'mizzey-select__button',
            'role'                => 'combobox',
            'aria-haspopup'       => 'listbox',
            'aria-expanded'       => 'false',
            'aria-controls'       => 'mizzey-sort-list',
            'aria-labelledby'     => 'mizzey-sort-label mizzey-sort',
            'data-wp-on--click'   => 'actions.selectToggle',
            'data-wp-on--keydown' => 'actions.selectKey',
        ], element('span', ['class' => 'mizzey-listing__sort-prefix', 'aria-hidden' => 'true'], esc_html__('Sort:', 'mizzey-site'))
            . element('span', ['class' => 'mizzey-select__value'], esc_html($current))
            . icon('down', ['size' => 20, 'class' => 'mizzey-select__chevron']))
        . element('ul', ['id' => 'mizzey-sort-list', 'class' => 'mizzey-select__list', 'role' => 'listbox', 'aria-labelledby' => 'mizzey-sort-label', 'tabindex' => '-1', 'hidden' => true, 'data-wp-on--click' => 'actions.selectChoose'], $items)
        . sprintf('<input%s>', attributes(['type' => 'hidden', 'class' => 'mizzey-listing__sort-value', 'value' => (string) $view['sort']])));
}

/**
 * The sorts as links, for a browser without JavaScript.
 *
 * @param array<string,mixed> $view
 */
function sortLinks(array $view): string
{
    $links = '';
    foreach ($view['sorts'] as $sort) {
        $links .= element('li', [], element('a', ['href' => esc_url((string) $sort['url']), 'aria-current' => $sort['value'] === $view['sort'] ? 'true' : null], esc_html((string) $sort['label'])));
    }

    return element('nav', ['class' => 'mizzey-listing__sort-links', 'aria-label' => __('Sort by', 'mizzey-site')], element('ul', ['role' => 'list'], $links));
}

/**
 * Numbered paging (C1-Category-Desktop: Previous and Next as words on a desktop, arrows on a phone) and the line under
 * it.
 *
 * @param array<string,mixed> $view
 */
function paging(array $view): string
{
    $total   = (int) $view['pages'];
    $current = (int) $view['page'];
    if ($view['found'] <= 0) {
        return '';
    }
    $showing = element('p', ['class' => 'mizzey-listing__showing'], sprintf(
        /* translators: 1: the first product shown, 2: the last, 3: how many there are. */
        esc_html(_n('Showing %1$s to %2$s of %3$s product', 'Showing %1$s to %2$s of %3$s products', (int) $view['found'], 'mizzey-site')),
        strong((int) $view['from']),
        strong((int) $view['to']),
        strong((int) $view['found'])
    ));
    if ($total <= 1) {
        return element('div', ['class' => 'mizzey-listing__paging'], $showing);
    }
    if ($total <= 7) {
        $pages = range(1, $total);
    } elseif ($current <= 3) {
        $pages = [1, 2, 3, $total - 1, $total];
    } elseif ($current >= $total - 2) {
        $pages = [1, 2, $total - 2, $total - 1, $total];
    } else {
        $pages = [1, $current - 1, $current, $current + 1, $total];
    }
    $pages = array_values(array_unique(array_filter($pages, static fn (int $p): bool => $p >= 1 && $p <= $total)));

    $edge = static function (string $icon, string $word, ?int $page, string $side): string {
        $inner = ('back' === $icon ? icon($icon, ['size' => 20]) : '')
            . element('span', ['class' => 'mizzey-listing__page-word'], esc_html($word))
            . ('fwd' === $icon ? icon($icon, ['size' => 20]) : '');
        $attributes = ['class' => classes('mizzey-pagination__item', 'mizzey-pagination__item--' . $side), 'aria-label' => $word];
        if (null === $page) {
            return element('a', $attributes + ['role' => 'link', 'aria-disabled' => 'true'], $inner);
        }
        return element('a', ['href' => esc_url(get_pagenum_link($page))] + $attributes, $inner);
    };
    $items    = $edge('back', __('Previous', 'mizzey-site'), $current > 1 ? $current - 1 : null, 'previous');
    $previous = 0;
    foreach ($pages as $page) {
        if ($page - $previous > 1) {
            $items .= element('span', ['class' => 'mizzey-pagination__gap', 'aria-hidden' => 'true'], esc_html('...'));
        }
        $items .= element('a', [
            'class'        => 'mizzey-pagination__item',
            'href'         => esc_url(get_pagenum_link($page)),
            /* translators: %d: a page number in a list of results. */
            'aria-label'   => sprintf(__('Page %d', 'mizzey-site'), $page),
            'aria-current' => $page === $current ? 'page' : null,
        ], esc_html(number_format_i18n($page)));
        $previous = $page;
    }
    $items .= $edge('fwd', __('Next', 'mizzey-site'), $current < $total ? $current + 1 : null, 'next');

    return element('div', ['class' => 'mizzey-listing__paging'], element('nav', ['class' => 'mizzey-pagination', 'aria-label' => __('Pages', 'mizzey-site')], $items) . $showing);
}

/* ---- Overlays ---------------------------------------------------------------------------------------------------- */

/**
 * The phone's filters drawer: slice 1's side drawer from the start edge. The filter form moves into its body when it
 * opens (assets/js/listing.js), so there is one form; its "Show N products" button is pinned to the bottom.
 *
 * @param array<string,mixed> $view
 */
function drawer(array $view): string
{
    $close = element('button', ['type' => 'button', 'class' => 'mizzey-button mizzey-button--icon', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.close'], icon('close'));
    $clear = $view['filtered'] ? element('a', ['class' => 'mizzey-listing__clear', 'href' => esc_url((string) $view['clear'])], esc_html__('Clear all', 'mizzey-site')) : '';

    return element('dialog', [
        'id'                      => DRAWER,
        'class'                   => classes('mizzey-overlay', 'mizzey-overlay--drawer-start', 'mizzey-listing__drawer'),
        'aria-labelledby'         => DRAWER . '-title',
        'data-wp-interactive'     => 'mizzey/ui',
        'data-wp-on--pointerdown' => 'actions.scrimDown',
        'data-wp-on--click'       => 'actions.backdrop',
    ], element('div', ['class' => 'mizzey-overlay__panel'],
        element('div', ['class' => 'mizzey-overlay__header'],
            element('h2', ['class' => 'mizzey-overlay__title', 'id' => DRAWER . '-title'], esc_html__('Filters', 'mizzey-site'))
            . element('span', ['class' => 'mizzey-listing__drawer-tools'], $clear . $close))
        . element('div', ['class' => classes('mizzey-overlay__body', 'mizzey-listing__drawer-body')], '')));
}

/**
 * The phone's sort: a bottom sheet of plain radios (C1-Category-Phone, frame 3); a choice leads to the same address
 * as the desktop list's option of that value.
 *
 * @param array<string,mixed> $view
 */
function sheet(array $view): string
{
    $radios = '';
    foreach ($view['sorts'] as $sort) {
        $radios .= element('li', [], radio(['name' => 'mizzey-sort-choice', 'value' => (string) $sort['value'], 'label' => (string) $sort['label'], 'checked' => $sort['value'] === $view['sort']]));
    }
    $close = element('button', ['type' => 'button', 'class' => 'mizzey-button mizzey-button--icon', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.close'], icon('close'));

    return element('dialog', [
        'id'                      => SHEET,
        'class'                   => classes('mizzey-overlay', 'mizzey-overlay--sheet', 'mizzey-listing__sheet'),
        'aria-labelledby'         => SHEET . '-title',
        'data-wp-interactive'     => 'mizzey/ui',
        'data-wp-on--pointerdown' => 'actions.scrimDown',
        'data-wp-on--click'       => 'actions.backdrop',
    ], element('div', ['class' => 'mizzey-overlay__panel'],
        element('span', ['class' => 'mizzey-overlay__handle', 'aria-hidden' => 'true'])
        . element('div', ['class' => 'mizzey-overlay__header'], element('h2', ['class' => 'mizzey-overlay__title', 'id' => SHEET . '-title'], esc_html__('Sort by', 'mizzey-site')) . $close)
        . element('div', ['class' => 'mizzey-overlay__body'], element('fieldset', ['class' => 'mizzey-listing__sorts'], element('legend', ['class' => 'mizzey-visually-hidden'], esc_html__('Sort by', 'mizzey-site')) . element('ul', ['role' => 'list'], $radios)))));
}

/* ---- An empty category ------------------------------------------------------------------------------------------- */

/** @param array<string,mixed> $view */
function emptyCategory(array $view): string
{
    $empty = element('div', ['class' => classes('mizzey-listing__empty', 'mizzey-listing__empty--category')], empty_state([
        'icon'   => 'grid-empty',
        /* translators: %s: the name of a category that has no products. */
        'title'  => sprintf(__('Nothing in %s yet', 'mizzey-site'), (string) $view['name']),
        'text'   => __('New products land here soon. Until then, these are close.', 'mizzey-site'),
        'action' => ['label' => __('Browse all categories', 'mizzey-site'), 'variant' => 'secondary', 'url' => (string) $view['shop']],
    ]));
    $rail = [] !== $view['suggestions'] ? ProductRail::render((array) $view['suggestions'], ['layout' => ProductRail::RAIL, 'label' => __('You may like', 'mizzey-site')]) : '';

    return $empty . ('' !== $rail ? element('section', ['class' => 'mizzey-listing__suggestions', 'aria-labelledby' => 'mizzey-listing-suggestions'],
        element('h2', ['class' => 'mizzey-listing__suggestions-title', 'id' => 'mizzey-listing-suggestions'], esc_html__('You may like', 'mizzey-site')) . $rail) : '');
}

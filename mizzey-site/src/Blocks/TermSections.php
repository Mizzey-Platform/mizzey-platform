<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

use MizzeySite\Catalogue\Collections;
use MizzeySite\Storefront\HomeSections;

/**
 * The home page's term sections, as C1-Home-Phone, C1-Home-Phone-Arabic and C1-Home-Desktop draw them:
 *
 * - **Category shortcuts** (HOME-05): up to seven categories as image tiles with their names, and an "All categories"
 *   tile with the number of categories; four to a row on a phone, eight on a desktop.
 * - **Featured collections** (HOME-04): up to four collections as square images, each with its name on a white pill;
 *   two to a row on a phone, four on a desktop.
 * - **Brand highlights** (HOME-06): the brands as chips, in a row that scrolls sideways on a phone and wraps on a
 *   desktop, with "All brands" at the heading's end.
 *
 * Which terms show, and in what order, is set on each category, collection and brand ("Show on the home page" and a
 * position, specs/056, HomeSections::onHome), in the visitor's language. The editor writes the heading and the link
 * words. A section with no term to show renders nothing (AC-3).
 *
 * Register ids: HOME-04, HOME-05, HOME-06, HOME-10. See specs/066-stage-2-shop-front, slice 5, and specs/056.
 */
final class TermSections
{
    public const CATEGORY_TILES   = 7;
    public const COLLECTION_TILES = 4;
    public const BRAND_CHIPS      = 24;

    /** @param array<string,mixed> $attributes */
    public static function renderCategories(array $attributes): string
    {
        $terms = array_slice(HomeSections::onHome('product_cat'), 0, self::CATEGORY_TILES);
        if ([] === $terms) {
            return '';
        }
        $tiles = '';
        foreach ($terms as $term) {
            $tiles .= Section::el('li', ['class' => 'mizzey-shortcuts__item'], Section::el('a', ['class' => 'mizzey-shortcuts__link', 'href' => esc_url((string) $term['url'])],
                Section::el('span', ['class' => 'mizzey-shortcuts__image'], self::image((string) $term['image']))
                . Section::el('span', ['class' => 'mizzey-shortcuts__name'], esc_html((string) $term['name']))));
        }
        $allUrl = trim((string) ($attributes['allUrl'] ?? ''));
        $allUrl = '' !== $allUrl ? $allUrl : (function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : '');
        $label  = Section::plain($attributes['allLabel'] ?? '');
        if ('' !== esc_url($allUrl)) {
            $count  = self::categoryCount();
            /* translators: %s: the number of product categories, as digits. */
            $counted = sprintf(_n('%s category', '%s categories', $count, 'mizzey-site'), number_format_i18n($count));
            $tiles  .= Section::el('li', ['class' => ['mizzey-shortcuts__item', 'mizzey-shortcuts__item--all']], Section::el('a', ['class' => 'mizzey-shortcuts__link', 'href' => esc_url($allUrl)],
                Section::el('span', ['class' => ['mizzey-shortcuts__image', 'mizzey-shortcuts__image--all']],
                    self::icon('gridf', 32) . Section::el('span', ['class' => 'mizzey-shortcuts__count'], esc_html($counted)))
                . Section::el('span', ['class' => 'mizzey-shortcuts__name'], esc_html('' !== $label ? $label : __('All categories', 'mizzey-site')))));
        }
        $heading = Section::plain($attributes['heading'] ?? '');
        $id      = wp_unique_id('mizzey-shortcuts-');
        return sprintf(
            '<section %s>%s<ul class="mizzey-shortcuts__list" role="list">%s</ul></section>',
            Section::wrapper('category-shortcuts', ['mizzey-shortcuts'], ['aria-labelledby' => '' !== $heading ? $id : null]),
            Section::head($heading, '', '', $id),
            $tiles
        );
    }

    /** @param array<string,mixed> $attributes */
    public static function renderCollections(array $attributes): string
    {
        $terms = taxonomy_exists(Collections::TAXONOMY) ? array_slice(HomeSections::onHome(Collections::TAXONOMY), 0, self::COLLECTION_TILES) : [];
        if ([] === $terms) {
            return '';
        }
        $tiles = '';
        foreach ($terms as $term) {
            $tiles .= Section::el('li', ['class' => 'mizzey-collections__item'], Section::el('a', ['class' => 'mizzey-collections__link', 'href' => esc_url((string) $term['url'])],
                self::image((string) $term['image'])
                . Section::el('span', ['class' => 'mizzey-collections__name'], Section::el('span', ['class' => 'mizzey-collections__text'], esc_html((string) $term['name'])) . self::icon('fwd', 20))));
        }
        $heading = Section::plain($attributes['heading'] ?? '');
        $id      = wp_unique_id('mizzey-collections-');
        return sprintf(
            '<section %s>%s<ul class="mizzey-collections__list" role="list">%s</ul></section>',
            Section::wrapper('featured-collections', ['mizzey-collections'], ['aria-labelledby' => '' !== $heading ? $id : null]),
            Section::head($heading, '', '', $id),
            $tiles
        );
    }

    /** @param array<string,mixed> $attributes */
    public static function renderBrands(array $attributes): string
    {
        $terms = taxonomy_exists('product_brand') ? array_slice(HomeSections::onHome('product_brand'), 0, self::BRAND_CHIPS) : [];
        if ([] === $terms) {
            return '';
        }
        $chips = '';
        foreach ($terms as $term) {
            $chips .= Section::el('li', ['class' => 'mizzey-brands__item'], Section::el('a', ['class' => 'mizzey-brands__link', 'href' => esc_url((string) $term['url'])],
                Section::el('span', ['class' => 'mizzey-brands__chip'], esc_html((string) $term['name']))));
        }
        $heading = Section::plain($attributes['heading'] ?? '');
        $id      = wp_unique_id('mizzey-brands-');
        return sprintf(
            '<section %s>%s<ul class="mizzey-brands__list" role="list">%s</ul></section>',
            Section::wrapper('brand-highlights', ['mizzey-brands'], ['aria-labelledby' => '' !== $heading ? $id : null]),
            Section::head($heading, Section::plain($attributes['allLabel'] ?? ''), (string) ($attributes['allUrl'] ?? ''), $id),
            $chips
        );
    }

    /** The term's image, or the tint alone: the tile keeps its square either way. */
    private static function image(string $url): string
    {
        return '' === esc_url($url) ? '' : sprintf('<img class="mizzey-home__tile-image" src="%s" alt="" loading="lazy" decoding="async" width="300" height="300">', esc_url($url));
    }

    private static function icon(string $name, int $size): string
    {
        return function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => $size]) : '';
    }

    /** The store's top-level categories that hold products, in the visitor's language. */
    private static function categoryCount(): int
    {
        $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'fields' => 'ids']);
        return is_array($terms) ? count($terms) : 0;
    }
}

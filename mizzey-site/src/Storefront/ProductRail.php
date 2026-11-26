<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

/**
 * Cards in a row: the rail that carries them on the home page and the product page, and the grid of a listing
 * (C1-Home-Phone, C1-Home-Desktop, C1-Category-Phone, C1-Category-Desktop).
 *
 * - **Rail**: a heading, "View all" when a link is given, and on a desktop the two arrows; the cards scroll sideways
 *   and snap, by touch on a phone (156 wide, peeking past the page margin) and six to the 1272 grid on a desktop.
 * - **Grid**: two in a row on a phone, three on a tablet, four from 1024 wide.
 *
 * Which products a rail carries is its caller's: the home sections read the rule or curation of specs/056 (slice 5),
 * the product page its related and recently viewed products (slice 10). Each card is rendered as the card block, so its
 * own stylesheet and script load with it. A rail with no product to show renders nothing, so it leaves no broken
 * region.
 *
 * Register ids: PLP-01, HOME-02, HOME-03 (the carrier only). See specs/066-stage-2-shop-front, slice 4.
 */
final class ProductRail
{
    public const RAIL = 'rail';
    public const GRID = 'grid';

    /**
     * @param list<int>                                                        $productIds In the order shown.
     * @param array{layout?:string,heading?:string,link?:string,label?:string} $args
     */
    public static function render(array $productIds, array $args = []): string
    {
        $layout = self::GRID === ($args['layout'] ?? self::RAIL) ? self::GRID : self::RAIL;
        $items  = '';
        foreach (array_values(array_unique(array_map('intval', $productIds))) as $id) {
            $product = $id > 0 ? wc_get_product(self::local($id)) : null;
            if (! $product instanceof \WC_Product || $product instanceof \WC_Product_Variation) {
                continue;
            }
            $card = ProductCard::block($product->get_id());
            if ('' !== $card) {
                $items .= ProductCard::el('li', ['class' => 'mizzey-products__item'], $card);
            }
        }
        if ('' === $items) {
            return '';
        }

        $heading = trim((string) ($args['heading'] ?? ''));
        $link    = esc_url((string) ($args['link'] ?? ''));
        $listId  = wp_unique_id('mizzey-products-');
        $head    = '';
        if (self::RAIL === $layout && ('' !== $heading || '' !== $link)) {
            $tools = ('' !== $link ? ProductCard::el('a', ['class' => 'mizzey-products__all', 'href' => $link], esc_html__('View all', 'mizzey-site')) : '')
                . ProductCard::el('span', ['class' => 'mizzey-products__arrows'],
                    self::arrow('back', __('Previous products', 'mizzey-site'), $listId, -1)
                    . self::arrow('fwd', __('Next products', 'mizzey-site'), $listId, 1));
            $head = ProductCard::el('div', ['class' => 'mizzey-products__head'],
                ('' !== $heading ? ProductCard::el('h2', ['class' => 'mizzey-products__heading', 'id' => $listId . '-title'], esc_html($heading)) : '')
                . ProductCard::el('span', ['class' => 'mizzey-products__tools'], $tools));
        }
        $label = (string) ($args['label'] ?? '');

        return ProductCard::el('section', [
            'class'               => ['mizzey-products', 'mizzey-products--' . $layout, 'alignfull'],
            'aria-labelledby'     => '' !== $heading ? $listId . '-title' : null,
            'aria-label'          => '' === $heading && '' !== $label ? $label : null,
            'data-wp-interactive' => self::RAIL === $layout ? ProductCard::STORE : null,
            'data-wp-init'        => self::RAIL === $layout ? 'callbacks.rail' : null,
        ], $head . ProductCard::el('ul', ['class' => 'mizzey-products__list', 'id' => $listId, 'role' => 'list'], $items));
    }

    /** The product in the current language, when it has a translation the visitor may see. */
    private static function local(int $id): int
    {
        $local = (int) apply_filters('wpml_object_id', $id, 'product', true);

        return $local > 0 ? $local : $id;
    }

    private static function arrow(string $icon, string $label, string $listId, int $step): string
    {
        $svg = function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($icon, ['size' => 20]) : '';

        return ProductCard::el('button', [
            'type'              => 'button',
            'class'             => 'mizzey-products__arrow',
            'aria-label'        => $label,
            'aria-controls'     => $listId,
            'data-step'         => (string) $step,
            'data-wp-on--click' => 'actions.scroll',
            // The pointer reaching an arrow fetches the card's on-first-use module, which does the scrolling.
            'data-wp-on--pointerenter' => 'actions.warm',
            'data-wp-on--pointerdown'  => 'actions.warm',
        ], $svg);
    }
}

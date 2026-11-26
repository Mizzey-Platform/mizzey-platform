<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

use MizzeySite\Reviews\ReviewEligibility;
use MizzeySite\Reviews\ReviewPhotos;
use MizzeySite\Wishlist\RecentlyViewed;

defined('ABSPATH') || exit;

/**
 * The lower product page (spec 066, slice 10), as C1-Product-Phone, C1-Product-Desktop and C1-Photo-Reviews draw it,
 * under the gallery and buying area of slice 9:
 *
 * - **The jump-to bar**: Details, Specifications and Reviews, a link for each part the page has; it sticks to the top of
 *   the window once the buying area has scrolled away, and the part on screen is underlined.
 * - **Details** (PDP-12): the full description; on a phone, six lines and "Read more". Absent when there is none.
 * - **Specifications** (PDP-13): the brand, the attributes staff mark visible, weight and size, and the SKU, as a list
 *   of name and value. Absent when there is none.
 * - **Reviews** (PDP-17, REV-04): the average, the stars, the count and the bars for each star; the approved reviews,
 *   three on the page and every one on the "all reviews" view, most recent first, each with its photos (D-23, a gift),
 *   which open in a viewer; "With photos" and the order; "Write a review" with who may write one. With no review yet:
 *   empty stars, one line and "Write a review", no score and no bars (E8).
 * - **You may also like** (PDP-18): the staff's related products (specs/036, the Upsells field), on slice 4's rail.
 * - **Recently viewed** (PDP-19): the visitor's own list (specs/015), without this product, on the same rail. A rail with
 *   nothing to show is left out.
 *
 * The reviews are read by ProductReviews; nothing here writes a review, a rating, a photo or a list. The review form is
 * WordPress's own comment post, which specs/020 checks on the server.
 *
 * Register ids: PDP-12, PDP-13, PDP-17, PDP-18, PDP-19, REV-04. See specs/066-stage-2-shop-front, slice 10.
 */
final class ProductDetails
{
    public const BLOCK    = 'mizzey/product-details';
    public const ON_PAGE  = 3;
    public const PER_LOAD = 10;

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        add_filter('woocommerce_get_breadcrumb', [self::class, 'crumbs'], 20, 1);
    }

    public static function registerBlock(): void
    {
        register_block_type(__DIR__ . '/product/details');
    }

    /** Whether the page asked is the product's "all reviews" view. */
    public static function allView(): bool
    {
        return isset($_GET['reviews']) && 'all' === sanitize_key(wp_unslash((string) $_GET['reviews'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view.
    }

    /**
     * On the "all reviews" view the breadcrumb ends with the product, then "Reviews", as drawn.
     *
     * @param mixed $crumbs
     * @return mixed
     */
    public static function crumbs($crumbs)
    {
        if (! is_array($crumbs) || ! self::allView() || ! function_exists('is_product') || ! is_product() || $crumbs === []) {
            return $crumbs;
        }
        $last              = array_key_last($crumbs);
        $crumbs[$last][1]  = (string) get_permalink((int) get_queried_object_id());
        $crumbs[]          = [__('Reviews', 'mizzey-site'), ''];

        return $crumbs;
    }

    private static function el(string $tag, array $attributes = [], string $inner = ''): string
    {
        return ProductCard::el($tag, $attributes, $inner);
    }

    /** A void input element, its attributes escaped. */
    private static function input(array $attributes): string
    {
        return substr(self::el('input', $attributes), 0, -strlen('</input>'));
    }

    private static function icon(string $name, int $size = 24, string $class = ''): string
    {
        return function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => $size, 'class' => $class]) : '';
    }

    public static function render(\WC_Product $product): string
    {
        if (self::allView()) {
            return self::all($product);
        }
        $details = self::description($product);
        $specs   = self::specifications($product);
        $parts   = [];
        $about   = '';
        if ($details !== '') {
            $parts['details'] = __('Details', 'mizzey-site');
            $about           .= self::el('section', ['class' => 'mizzey-details__text', 'id' => 'details', 'aria-labelledby' => 'mizzey-details-title'],
                self::el('h2', ['class' => 'mizzey-details__title', 'id' => 'mizzey-details-title'], esc_html__('Details', 'mizzey-site'))
                . self::el('div', ['class' => 'mizzey-details__body', 'id' => 'mizzey-details-body'], $details)
                . self::el('button', ['type' => 'button', 'class' => 'mizzey-details__more', 'aria-expanded' => 'false', 'aria-controls' => 'mizzey-details-body', 'hidden' => true, 'data-less' => __('Read less', 'mizzey-site')], esc_html__('Read more', 'mizzey-site')));
        }
        if ($specs !== []) {
            $parts['specifications'] = __('Specifications', 'mizzey-site');
            $rows                    = '';
            foreach ($specs as list($name, $value)) {
                $rows .= self::el('div', ['class' => 'mizzey-details__row'], self::el('dt', [], esc_html($name)) . self::el('dd', [], esc_html($value)));
            }
            $about .= self::el('section', ['class' => 'mizzey-details__specs', 'id' => 'specifications', 'aria-labelledby' => 'mizzey-specs-title'],
                self::el('h2', ['class' => 'mizzey-details__title', 'id' => 'mizzey-specs-title'], esc_html__('Specifications', 'mizzey-site'))
                . self::el('dl', [], $rows));
        }
        $parts['reviews'] = __('Reviews', 'mizzey-site');

        $html = self::jump($parts);
        if ($about !== '') {
            $html .= self::el('div', ['class' => ['mizzey-details__about', $details === '' || $specs === [] ? 'is-single' : '']], $about);
        }
        $html .= self::reviews($product, false);
        $html .= self::rail(self::related($product), __('You may also like', 'mizzey-site'), 'related');
        $html .= self::rail(array_map(static fn (\WC_Product $p): int => $p->get_id(), RecentlyViewed::products(RecentlyViewed::LIMIT, $product->get_id())), __('Recently viewed', 'mizzey-site'), 'recent');

        return self::el('div', ['class' => 'mizzey-details'], $html);
    }

    /**
     * The staff's related products (specs/036: the Upsells field), set on the product's own record; the rail shows each
     * in the page's language.
     *
     * @return list<int>
     */
    private static function related(\WC_Product $product): array
    {
        $own = $product->get_upsell_ids();
        if ($own !== []) {
            return array_map('intval', $own);
        }
        $canonical = \MizzeySite\Catalogue\CanonicalProduct::of($product->get_id());

        return $canonical instanceof \WC_Product ? array_map('intval', $canonical->get_upsell_ids()) : [];
    }

    /** @param array<string,string> $parts */
    private static function jump(array $parts): string
    {
        $links = '';
        $first = true;
        foreach ($parts as $id => $label) {
            $links .= self::el('a', ['href' => '#' . $id, 'class' => 'mizzey-details__jump-link', 'aria-current' => $first ? 'true' : null], esc_html($label));
            $first  = false;
        }

        return self::el('div', ['class' => 'mizzey-details__sentinel', 'aria-hidden' => 'true'], '')
            . self::el('nav', ['class' => ['mizzey-details__jump', 'mizzey-details__jump--' . count($parts)], 'aria-label' => __('On this page', 'mizzey-site')],
                self::el('div', ['class' => 'mizzey-details__jump-inner'], $links . self::el('span', ['class' => 'mizzey-details__jump-bar', 'aria-hidden' => 'true'], self::el('span', [], ''))));
    }

    /** The full description, as WooCommerce formats a product's content; nothing when there is none. */
    public static function description(\WC_Product $product): string
    {
        $text = trim((string) $product->get_description());

        return $text === '' ? '' : wp_kses_post(function_exists('wc_format_content') ? wc_format_content($text) : wpautop($text));
    }

    /**
     * The specifications, as name and value: the brand, each attribute staff mark visible, weight and size, and the SKU.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function specifications(\WC_Product $product): array
    {
        $rows   = [];
        $brands = taxonomy_exists('product_brand') ? get_the_terms($product->get_id(), 'product_brand') : false;
        if (is_array($brands) && $brands !== []) {
            $rows[] = [__('Brand', 'mizzey-site'), implode(', ', wp_list_pluck($brands, 'name'))];
        }
        foreach ($product->get_attributes() as $attribute) {
            if (! $attribute instanceof \WC_Product_Attribute || ! $attribute->get_visible()) {
                continue;
            }
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                : $attribute->get_options();
            $values = array_values(array_filter(array_map(static fn ($v): string => trim(wp_strip_all_tags((string) $v)), (array) $values), 'strlen'));
            if ($values !== []) {
                $rows[] = [wc_attribute_label($attribute->get_name(), $product), implode(', ', $values)];
            }
        }
        if ($product->has_weight()) {
            $rows[] = [__('Weight', 'mizzey-site'), wp_strip_all_tags(wc_format_weight($product->get_weight()))];
        }
        if ($product->has_dimensions()) {
            $rows[] = [__('Dimensions', 'mizzey-site'), wp_strip_all_tags(wc_format_dimensions($product->get_dimensions(false)))];
        }
        if ((string) $product->get_sku() !== '') {
            $rows[] = [__('SKU', 'mizzey-site'), (string) $product->get_sku()];
        }

        return $rows;
    }

    /** A rail of slice 4, as its block, so its own stylesheet loads; nothing when no product is left to show. */
    private static function rail(array $ids, string $heading, string $kind): string
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return '';
        }
        $rail = render_block(['blockName' => 'mizzey/product-rail', 'attrs' => ['products' => $ids, 'layout' => 'rail', 'heading' => $heading], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);

        return trim($rail) === '' ? '' : self::el('div', ['class' => ['mizzey-details__rail', 'mizzey-details__rail--' . $kind]], $rail);
    }

    // -----------------------------------------------------------------------------------------------------------
    // Reviews.

    /** Stars for a rating, whole and half, read as one phrase. */
    /** @param string|list<string> $class */
    private static function stars(float $rating, int $size, $class = 'mizzey-stars'): string
    {
        $out = '';
        for ($i = 1; $i <= 5; ++$i) {
            $fill = $rating >= $i ? 'full' : ($rating >= $i - 0.5 ? 'half' : 'empty');
            $half = 'half' === $fill ? self::el('span', ['class' => 'mizzey-stars__half'], self::icon('star', $size)) : '';
            $out .= self::el('span', ['class' => ['mizzey-stars__star', 'is-' . $fill]], self::icon('star', $size) . $half);
        }
        /* translators: %s: a rating, for example 4.6. */
        $label = sprintf(__('Rated %s out of 5', 'mizzey-site'), number_format_i18n($rating, $rating === floor($rating) ? 0 : 1));

        return self::el('span', ['class' => $class, 'role' => 'img', 'aria-label' => $label], $out);
    }

    private static function url(\WC_Product $product, array $args, string $anchor = 'reviews'): string
    {
        $base = (string) get_permalink($product->get_id());
        $args = array_filter($args, static fn ($v): bool => $v !== '' && $v !== null && $v !== 'recent' && $v !== 0);

        return add_query_arg(array_map('rawurlencode', array_map('strval', $args)), $base) . ($anchor !== '' ? '#' . $anchor : '');
    }

    /** The reviews part: the summary, the toolbar, the cards, and the way to more and to write one. */
    private static function reviews(\WC_Product $product, bool $all): string
    {
        $summary = ProductReviews::summary($product);
        $title   = self::el('h2', ['class' => 'mizzey-details__title', 'id' => 'mizzey-reviews-title'], esc_html__('Reviews', 'mizzey-site'));
        if ($summary['count'] === 0) {
            return self::el('section', ['class' => ['mizzey-reviews', 'mizzey-reviews--none'], 'id' => 'reviews', 'aria-labelledby' => 'mizzey-reviews-title'],
                self::el('div', ['class' => 'mizzey-reviews__side'], $title)
                . self::el('div', ['class' => 'mizzey-reviews__none'],
                    self::stars(0, 24, ['mizzey-stars', 'mizzey-stars--none'])
                    . self::el('div', ['class' => 'mizzey-reviews__none-text'],
                        self::el('p', ['class' => 'mizzey-reviews__none-title'], esc_html__('No reviews yet', 'mizzey-site'))
                        . self::el('p', [], esc_html__('Bought it? Tell others what you think. Reviews appear after a quick check.', 'mizzey-site')))
                    . self::writeButton(true))
                . self::writePanel($product));
        }

        $filter = ProductReviews::filterAsked();
        $sort   = ProductReviews::sortAsked();
        $found  = ProductReviews::find($product, $filter, $sort);
        $page   = $all ? max(1, isset($_GET['review_page']) ? absint(wp_unslash((string) $_GET['review_page'])) : 1) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only view.
        $limit  = $all ? self::PER_LOAD * $page : self::ON_PAGE;
        $shown  = array_slice($found, 0, $limit);

        $cards = '';
        foreach ($shown as $index => $review) {
            $cards .= self::card($review, $all && $page > 1 && $index === self::PER_LOAD * ($page - 1));
        }
        if ($cards === '') {
            $cards = self::el('p', ['class' => 'mizzey-reviews__nothing'], esc_html__('No reviews match this choice.', 'mizzey-site'));
        }

        $more = '';
        if (! $all && $summary['count'] > count($shown)) {
            /* translators: %s: the number of reviews. */
            $more = self::el('a', ['class' => ['mizzey-button', 'mizzey-button--secondary', 'mizzey-reviews__more'], 'href' => esc_url(self::url($product, ['reviews' => 'all', 'review_filter' => $filter, 'review_sort' => $sort], ''))],
                self::el('span', ['class' => 'mizzey-button__label'], esc_html(sprintf(_n('Show all %s review', 'Show all %s reviews', $summary['count'], 'mizzey-site'), number_format_i18n($summary['count'])))));
        } elseif ($all && count($found) > count($shown)) {
            $more = self::el('a', ['class' => ['mizzey-button', 'mizzey-button--secondary', 'mizzey-reviews__more'], 'href' => esc_url(self::url($product, ['reviews' => 'all', 'review_filter' => $filter, 'review_sort' => $sort, 'review_page' => $page + 1], 'review-more'))],
                self::el('span', ['class' => 'mizzey-button__label'], esc_html__('Load more reviews', 'mizzey-site')));
        }

        $side = self::el('div', ['class' => 'mizzey-reviews__side'],
            self::el('div', ['class' => 'mizzey-reviews__head'], $title)
            . self::summary($summary)
            . self::el('div', ['class' => 'mizzey-reviews__write'], self::writeButton(false)
                . self::el('p', ['class' => 'mizzey-reviews__who'], esc_html__('Only customers who bought this product can review it.', 'mizzey-site'))));

        $list = self::el('div', ['class' => 'mizzey-reviews__main'],
            self::toolbar($product, $summary, $filter, $sort, $all)
            . self::el('div', ['class' => 'mizzey-reviews__list'], $cards)
            . ($more !== '' ? self::el('div', ['class' => 'mizzey-reviews__more-row'], $more) : ''));

        return self::el('section', ['class' => 'mizzey-reviews', 'id' => 'reviews', 'aria-labelledby' => 'mizzey-reviews-title'], $side . $list . self::writePanel($product) . self::viewer());
    }

    /** @param array{average:float,count:int,stars:array<int,int>,photos:int} $summary */
    private static function summary(array $summary): string
    {
        $bars = '';
        // Each bar is its star's share of every review, as drawn (92 of 128 fills 141 of 196).
        $top  = max(1, $summary['count']);
        foreach ($summary['stars'] as $star => $count) {
            $label = sprintf(
                /* translators: 1: a number of stars, such as "5 stars"; 2: how many reviews gave it, such as "92 reviews". */
                __('%1$s: %2$s', 'mizzey-site'),
                /* translators: %d: a number of stars, 1 to 5. */
                sprintf(_n('%d star', '%d stars', $star, 'mizzey-site'), $star),
                /* translators: %s: the number of reviews. */
                sprintf(_n('%s review', '%s reviews', $count, 'mizzey-site'), number_format_i18n($count))
            );
            $bars .= self::el('li', ['class' => 'mizzey-reviews__bar', 'aria-label' => $label],
                self::el('span', ['class' => 'mizzey-reviews__bar-star', 'aria-hidden' => 'true'], esc_html(number_format_i18n($star)) . self::icon('star', 12))
                . self::el('span', ['class' => 'mizzey-reviews__bar-track', 'aria-hidden' => 'true'], self::el('span', ['style' => sprintf('--mizzey-bar:%s%%', round(100 * $count / $top, 2))], ''))
                . self::el('span', ['class' => 'mizzey-reviews__bar-count', 'aria-hidden' => 'true'], esc_html(number_format_i18n($count))));
        }
        $average = number_format_i18n($summary['average'], 1);

        return self::el('div', ['class' => 'mizzey-reviews__summary'],
            self::el('div', ['class' => 'mizzey-reviews__score'],
                self::el('p', ['class' => 'mizzey-reviews__average'],
                    self::el('span', ['class' => 'mizzey-reviews__number'], esc_html($average))
                    . self::el('span', ['class' => 'mizzey-reviews__of'], esc_html__('out of 5', 'mizzey-site')))
                . self::stars($summary['average'], 16)
                /* translators: %s: the number of reviews. */
                . self::el('p', ['class' => 'mizzey-reviews__count'], esc_html(sprintf(_n('%s review', '%s reviews', $summary['count'], 'mizzey-site'), number_format_i18n($summary['count'])))))
            . self::el('ul', ['class' => 'mizzey-reviews__bars', 'role' => 'list', 'aria-label' => __('Reviews by rating', 'mizzey-site')], $bars));
    }

    /** "With photos" and the order, as drawn above the cards. */
    private static function toolbar(\WC_Product $product, array $summary, string $filter, string $sort, bool $all): string
    {
        $chip = '';
        if ($summary['photos'] > 0) {
            $chosen = $filter === 'photos';
            $chip   = self::el('a', ['class' => ['mizzey-chip', $chosen ? 'mizzey-chip--chosen' : ''], 'aria-current' => $chosen ? 'true' : null, 'href' => esc_url(self::url($product, ['reviews' => $all ? 'all' : '', 'review_filter' => $chosen ? '' : 'photos', 'review_sort' => $sort]))],
                esc_html__('With photos', 'mizzey-site') . self::el('span', ['class' => 'mizzey-chip__count'], esc_html(number_format_i18n($summary['photos']))));
        }
        $sortBox = '';
        if ($summary['count'] > 1) {
            // Slice 1's select (our own list, the select-only combobox), "Sort by" beside it as drawn; a choice sends the
            // form (view.js). The board draws "Most recent"; Highest and Lowest rating were added by the owner's ruling.
            $names  = ['recent' => __('Most recent', 'mizzey-site'), 'high' => __('Highest rating', 'mizzey-site'), 'low' => __('Lowest rating', 'mizzey-site')];
            $hidden = ($all ? self::input(['type' => 'hidden', 'name' => 'reviews', 'value' => 'all']) : '')
                . ($filter !== '' ? self::input(['type' => 'hidden', 'name' => 'review_filter', 'value' => $filter]) : '');
            $select = function_exists('MizzeySite\Theme\Components\select')
                ? \MizzeySite\Theme\Components\select(['id' => 'mizzey-review-sort', 'name' => 'review_sort', 'label' => __('Sort by', 'mizzey-site'), 'options' => $names, 'selected' => $sort])
                : '';
            $sortBox = $select === '' ? '' : self::el('form', ['class' => 'mizzey-reviews__sort', 'method' => 'get', 'action' => esc_url((string) get_permalink($product->get_id()) . '#reviews')],
                $select . $hidden . self::el('button', ['type' => 'submit', 'class' => 'mizzey-visually-hidden'], esc_html__('Sort', 'mizzey-site')));
        }

        return $chip === '' && $sortBox === '' ? '' : self::el('div', ['class' => 'mizzey-reviews__tools'], $chip . $sortBox);
    }

    /** @param array<string,mixed> $review */
    private static function card(array $review, bool $firstNew): string
    {
        $photos = '';
        $list   = (array) $review['photos'];
        $total  = count($list);
        $show   = $total > 4 ? 3 : $total;
        foreach (array_slice($list, 0, $show) as $index => $photo) {
            $photos .= self::el('li', [], self::el('button', ['type' => 'button', 'class' => 'mizzey-reviews__photo', 'data-index' => $index,
                /* translators: 1: the photo's number; 2: how many photos the review has; 3: the reviewer's name. */
                'aria-label' => sprintf(__('Photo %1$s of %2$s, by %3$s', 'mizzey-site'), number_format_i18n($index + 1), number_format_i18n($total), (string) $review['name'])],
                self::el('img', ['src' => esc_url((string) $photo['url']), 'alt' => (string) $photo['alt'], 'width' => (int) $photo['width'] ?: null, 'height' => (int) $photo['height'] ?: null, 'loading' => 'lazy', 'decoding' => 'async'], '')));
        }
        if ($total > $show) {
            $photos .= self::el('li', [], self::el('button', ['type' => 'button', 'class' => ['mizzey-reviews__photo', 'mizzey-reviews__photo--more'], 'data-index' => $show, 'dir' => 'ltr',
                /* translators: %s: how many more photos the review has. */
                'aria-label' => sprintf(_n('%s more photo', '%s more photos', $total - $show, 'mizzey-site'), number_format_i18n($total - $show))],
                /* translators: %s: how many more photos, shown as "+2". */
                esc_html(sprintf(__('+%s', 'mizzey-site'), number_format_i18n($total - $show)))));
        }
        $data = wp_json_encode(array_map(static fn (array $p): array => ['src' => $p['url'], 'alt' => $p['alt']], $list));
        if ($total > 0 && function_exists('wp_enqueue_script_module')) {
            // The photo viewer's script, only where a review shows photos (D-23).
            wp_enqueue_script_module('mizzey-site/review-photos', plugins_url('product/details/viewer.js', __FILE__), [], (string) filemtime(__DIR__ . '/product/details/viewer.js'));
        }

        return self::el('article', ['class' => 'mizzey-review', 'id' => $firstNew ? 'review-more' : 'review-' . (int) $review['id'], 'data-name' => (string) $review['name'], 'data-photos' => $total > 0 ? (string) $data : null],
            self::el('header', ['class' => 'mizzey-review__head'],
                self::el('p', ['class' => 'mizzey-review__name', 'dir' => 'auto'], esc_html((string) $review['name']))
                . self::el('time', ['class' => 'mizzey-review__date', 'datetime' => gmdate('Y-m-d', (int) $review['time'])], esc_html((string) $review['date'])))
            . self::stars((float) $review['rating'], 16, ['mizzey-stars', 'mizzey-review__stars'])
            . self::el('div', ['class' => 'mizzey-review__text', 'dir' => 'auto'], wpautop(esc_html((string) $review['text'])))
            . ($photos !== '' ? self::el('ul', ['class' => 'mizzey-review__photos', 'role' => 'list', 'aria-label' => __('Photos from this review', 'mizzey-site')], $photos) : ''));
    }

    /** The photo viewer: one photo at a time, its number and who took it (C1-Photo-Reviews). */
    private static function viewer(): string
    {
        return self::el('dialog', ['class' => 'mizzey-photo-viewer', 'aria-label' => __('Review photo', 'mizzey-site')],
            self::el('div', ['class' => 'mizzey-photo-viewer__panel'],
                self::el('img', ['class' => 'mizzey-photo-viewer__img', 'src' => '', 'alt' => ''], '')
                . self::el('p', ['class' => 'mizzey-photo-viewer__label', 'aria-live' => 'polite', 'data-template' => __('%1$s / %2$s, by %3$s', 'mizzey-site')], '')
                . self::el('button', ['type' => 'button', 'class' => 'mizzey-photo-viewer__close', 'aria-label' => __('Close', 'mizzey-site')], self::icon('close', 20))
                . self::el('button', ['type' => 'button', 'class' => ['mizzey-photo-viewer__step', 'mizzey-photo-viewer__step--back'], 'data-step' => '-1', 'aria-label' => __('Previous photo', 'mizzey-site')], self::icon('back', 24))
                . self::el('button', ['type' => 'button', 'class' => ['mizzey-photo-viewer__step', 'mizzey-photo-viewer__step--fwd'], 'data-step' => '1', 'aria-label' => __('Next photo', 'mizzey-site')], self::icon('fwd', 24))));
    }

    private static function writeButton(bool $outlined): string
    {
        return self::el('button', ['type' => 'button', 'class' => ['mizzey-button', 'mizzey-button--secondary', 'mizzey-reviews__write-button', $outlined ? 'is-outlined' : ''], 'aria-expanded' => 'false', 'aria-controls' => 'mizzey-review-entry'],
            self::el('span', ['class' => 'mizzey-button__label'], esc_html__('Write a review', 'mizzey-site')));
    }

    /**
     * What "Write a review" opens in place (E11): for a customer who may review, the stars, the text and "Post review",
     * posted to WordPress's comment form, which specs/020 checks on the server; for anyone else, why not.
     */
    private static function writePanel(\WC_Product $product): string
    {
        $user   = get_current_user_id();
        $reason = ReviewEligibility::refusal($user, $product->get_id());
        $held   = isset($_GET['unapproved']) ? absint(wp_unslash((string) $_GET['unapproved'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only says thank you.
        $mine   = $held > 0 && ($comment = get_comment($held)) instanceof \WP_Comment && $user > 0 && (int) $comment->user_id === $user;
        if ($mine) {
            $body = self::el('p', ['class' => 'mizzey-review-entry__note', 'role' => 'status'], esc_html__('Thanks, your review is waiting for approval.', 'mizzey-site'));
        } elseif ($reason === null) {
            $stars = '';
            for ($i = 1; $i <= 5; ++$i) {
                /* translators: %d: a number of stars, 1 to 5. */
                $name   = sprintf(_n('%d star', '%d stars', $i, 'mizzey-site'), $i);
                $stars .= self::el('label', ['class' => 'mizzey-review-entry__star'],
                    self::input(['type' => 'radio', 'name' => 'rating', 'value' => (string) $i, 'required' => true]) . self::icon('star', 24) . self::el('span', ['class' => 'mizzey-visually-hidden'], esc_html($name)));
            }
            // Photos, as C1-Photo-Reviews draws them (D-23, a gift): added in the browser, sent after the review is made,
            // through specs/025's route. Its script loads only here, for a customer who may review (entry.js).
            if (function_exists('wp_enqueue_script_module')) {
                wp_enqueue_script_module('mizzey-site/review-entry', plugins_url('product/details/entry.js', __FILE__), [], (string) filemtime(__DIR__ . '/product/details/entry.js'));
            }
            $photos = self::el('div', ['class' => 'mizzey-review-entry__photos'],
                self::el('p', ['class' => 'mizzey-review-entry__label'], esc_html__('Photos', 'mizzey-site') . ' ' . self::el('span', [], esc_html(sprintf(
                    /* translators: %d: the most photos a review may have. */
                    __('(optional, up to %d)', 'mizzey-site'),
                    ReviewPhotos::MAX
                ))))
                . self::el('ul', ['class' => 'mizzey-review-entry__chosen', 'role' => 'list'], '')
                . self::el('label', ['class' => 'mizzey-review-entry__add'],
                    self::icon('plus', 20) . self::el('span', [], esc_html__('Add', 'mizzey-site'))
                    . self::input(['type' => 'file', 'name' => 'mizzey_photos[]', 'accept' => 'image/jpeg,image/png,image/webp', 'multiple' => true, 'class' => 'mizzey-visually-hidden', 'aria-label' => __('Add photos', 'mizzey-site')])));
            $loader = function_exists('MizzeySite\Theme\Components\loader') ? \MizzeySite\Theme\Components\loader(['label' => __('Sending', 'mizzey-site')]) : '';
            $body = self::el('form', [
                'class'       => 'mizzey-review-entry__form',
                'method'      => 'post',
                'action'      => esc_url(site_url('/wp-comments-post.php')),
                'data-rest'   => esc_url_raw(rest_url('wp/v2/comments')),
                'data-photos' => esc_url_raw(rest_url('mizzey/v1/reviews/')),
                'data-nonce'  => wp_create_nonce('wp_rest'),
                'data-done'   => __('Thanks, your review is waiting for approval.', 'mizzey-site'),
                'data-max'    => ReviewPhotos::MAX,
            ],
                self::el('p', ['class' => 'mizzey-review-entry__title', 'id' => 'mizzey-review-entry-title'], esc_html__('How was it?', 'mizzey-site'))
                . self::el('fieldset', ['class' => 'mizzey-review-entry__stars'], self::el('legend', ['class' => 'mizzey-visually-hidden'], esc_html__('Your rating', 'mizzey-site')) . $stars)
                . self::el('label', ['class' => 'mizzey-review-entry__label', 'for' => 'mizzey-review-text'], esc_html__('Your review', 'mizzey-site'))
                . self::el('textarea', ['id' => 'mizzey-review-text', 'name' => 'comment', 'rows' => '4', 'required' => true, 'placeholder' => __('What did you like, and what could be better?', 'mizzey-site')], '')
                . $photos
                . self::el('p', ['class' => 'mizzey-review-entry__error', 'role' => 'alert'], '')
                . self::el('button', ['type' => 'submit', 'class' => ['mizzey-button', 'mizzey-button--primary', 'mizzey-review-entry__send']],
                    self::el('span', ['class' => 'mizzey-button__label'], esc_html__('Send review', 'mizzey-site')) . self::el('span', ['class' => 'mizzey-review-entry__loader'], $loader))
                . self::input(['type' => 'hidden', 'name' => 'comment_post_ID', 'value' => (string) $product->get_id()]) . self::input(['type' => 'hidden', 'name' => 'comment_parent', 'value' => '0']));
        } else {
            $sign = $user > 0 ? '' : self::el('a', ['class' => 'mizzey-review-entry__sign-in', 'href' => esc_url(function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : wp_login_url())], esc_html__('Sign in', 'mizzey-site'));
            $body = self::el('p', ['class' => 'mizzey-review-entry__note'], esc_html(ReviewEligibility::message($reason)) . ($sign !== '' ? ' ' . $sign : ''));
        }

        return self::el('div', ['class' => 'mizzey-review-entry', 'id' => 'mizzey-review-entry', 'hidden' => ! $mine], $body);
    }

    /** The "all reviews" view (E8): back to the product, the title, the filters, then the reviews with "Load more". */
    private static function all(\WC_Product $product): string
    {
        $summary = ProductReviews::summary($product);
        $filter  = ProductReviews::filterAsked();
        $sort    = ProductReviews::sortAsked();
        $chips   = '';
        $choices = ['' => __('All', 'mizzey-site')];
        if ($summary['photos'] > 0) {
            $choices['photos'] = __('With photos', 'mizzey-site');
        }
        for ($star = 5; $star >= 1; --$star) {
            /* translators: %d: a number of stars, 1 to 5. */
            $choices[(string) $star] = sprintf(_n('%d star', '%d stars', $star, 'mizzey-site'), $star);
        }
        foreach ($choices as $value => $label) {
            $chosen = (string) $value === $filter;
            $chips .= self::el('a', ['class' => ['mizzey-chip', $chosen ? 'mizzey-chip--chosen' : ''], 'aria-current' => $chosen ? 'true' : null, 'href' => esc_url(self::url($product, ['reviews' => 'all', 'review_filter' => (string) $value, 'review_sort' => $sort], ''))], esc_html($label));
        }
        /* translators: %s: the number of reviews. */
        $title = sprintf(_n('All %s review', 'All %s reviews', $summary['count'], 'mizzey-site'), number_format_i18n($summary['count']));

        return self::el('div', ['class' => ['mizzey-details', 'mizzey-details--all']],
            self::el('a', ['class' => 'mizzey-details__back', 'href' => esc_url((string) get_permalink($product->get_id()) . '#reviews')], self::icon('back', 24) . self::el('span', [], esc_html__('Back to the product', 'mizzey-site')))
            . self::el('div', ['class' => 'mizzey-details__all-head'],
                self::el('h1', ['class' => 'mizzey-details__all-title'], esc_html($title))
                . ($summary['count'] > 0 ? self::el('nav', ['class' => 'mizzey-details__filters', 'aria-label' => __('Filter reviews', 'mizzey-site')], $chips) : ''))
            . self::reviews($product, true));
    }
}

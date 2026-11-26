<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

use MizzeySite\Catalogue\CanonicalProduct;
use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Wishlist\Wishlist;
use MizzeySite\Wishlist\WishlistRoutes;

defined('ABSPATH') || exit;

/**
 * The top of the product page: the gallery and the buying area, as C1-Product-Phone, C1-Product-Desktop and
 * C1-Arabic-Stage-2 (G1) draw them. The lower page (description, specifications, reviews, rails) is slice 10's.
 *
 * What it shows, each read from where it is kept, none rebuilt:
 *
 * - **The gallery** (PDP-01, PDP-02): WooCommerce's product image and gallery, any shape shown whole; the first photo
 *   loads first (not lazy, fetchpriority high, its size written), the others when they are shown, over the breathing
 *   ZZ; a variant with its own photo leads its own set ("Showing Vanilla"). Zoom opens the photos in a dialog with a
 *   level control. The video of specs/036 plays in the gallery when pressed, muted, with its controls; never by itself.
 *   No photo: the image glyph and "No photo yet".
 * - **Name and brand** (PDP-03), the name clamped to three lines with "Show full name"; **the rating** (REV-04).
 * - **Price, sale price and saving** (PDP-04): the price, the struck regular price, "Save N%" and "You save" (the
 *   saving of specs/055, here for the variant chosen).
 * - **The options** (PDP-05): the product's own attributes, whatever they are; a value no variant in stock has with the
 *   other choices is struck "Sold out"; a combination that is not made, or sold out, says so in a plain line and Add
 *   to Cart waits. A value with a colour of its own (`SWATCH` on the attribute term) shows as a swatch.
 * - **Stock per variant** (PDP-06, PDP-22): WooCommerce's figure, which the stock source keeps (D-28, D-29): "In stock
 *   in ...", "Only N left in ...", "Out of stock", or, on backorder, "Availability is being confirmed". Read only.
 * - **Quantity, Add to Cart, Buy Now** (PDP-07 to PDP-09): Add to Cart posts to WooCommerce's Store API, which asks the
 *   stock source (specs/062); Buy Now is the address of specs/055. Without a script the form is WooCommerce's own.
 * - **The wishlist heart** (PDP-10, specs/015), **share** (PDP-21, the addresses of specs/055), **key features**
 *   (PDP-11, the short description), **the delivery estimate, returns summary and authenticity statement** (PDP-14 to
 *   PDP-16, the texts of specs/055).
 *
 * The block `mizzey/product-buy` carries it, so its stylesheet and script load only on a product page. Markup is built
 * element by element (`ProductCard::el()`), every attribute escaped where it is written.
 *
 * Register ids: PDP-01 to PDP-11, PDP-14 to PDP-16, PDP-21, PDP-22. See specs/066-stage-2-shop-front, slice 9.
 */
final class ProductBuy
{
    public const BLOCK = 'mizzey/product-buy';
    public const STORE = 'mizzey/buy';
    /** A colour for an attribute value, as a hex code, on the attribute's term: the option shows as a swatch. */
    public const SWATCH = \MizzeySite\Catalogue\AttributeSwatch::META;
    /** WooCommerce's uncropped size, so every shape is shown whole. */
    public const IMAGE_SIZE = 'woocommerce_single';
    public const THUMB_SIZE = 'woocommerce_gallery_thumbnail';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlock']);
        add_filter('woocommerce_breadcrumb_defaults', [self::class, 'crumbs']);
    }

    /**
     * The product page's breadcrumb as drawn: its separators their own element, 8 either side.
     *
     * @param mixed $defaults
     * @return mixed
     */
    public static function crumbs($defaults)
    {
        if (is_array($defaults) && function_exists('is_product') && is_product()) {
            $defaults['delimiter'] = '<span class="mizzey-crumb-sep" aria-hidden="true">/</span>';
        }

        return $defaults;
    }

    public static function registerBlock(): void
    {
        register_block_type(__DIR__ . '/product/buy');
    }

    private static function el(string $tag, array $attributes = [], string $inner = ''): string
    {
        return ProductCard::el($tag, $attributes, $inner);
    }

    private static function icon(string $name, int $size = 24, string $class = ''): string
    {
        return function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => $size, 'class' => $class]) : '';
    }

    private static function money(float $amount): string
    {
        return wp_kses(wc_price($amount), ['span' => ['class' => true], 'bdi' => []]);
    }

    private static function loader(): string
    {
        $dots = function_exists('MizzeySite\Theme\Components\loader') ? \MizzeySite\Theme\Components\loader(['label' => __('Adding', 'mizzey-site')]) : '';

        return self::el('span', ['class' => 'mizzey-buy__loader'], $dots);
    }

    /** The stock state of one sellable record, read from WooCommerce's figure. @return array{state:string,left:int|null,max:int} */
    public static function stock(\WC_Product $product): array
    {
        if (! $product->is_in_stock()) {
            return ['state' => 'out', 'left' => null, 'max' => 0];
        }
        if ($product->get_stock_status() === 'onbackorder') {
            return ['state' => 'unknown', 'left' => null, 'max' => 99];
        }
        if ($product->managing_stock() && ! $product->backorders_allowed()) {
            $count = max(0, (int) $product->get_stock_quantity());
            $low   = $count > 0 && $count <= (int) wc_get_low_stock_amount($product);

            return ['state' => $low ? 'low' : 'in', 'left' => $low ? $count : null, 'max' => min(99, $count)];
        }

        return ['state' => 'in', 'left' => null, 'max' => 99];
    }

    /**
     * The price facts of one sellable record, as the page shows them.
     *
     * @return array{price:string,old:string,save:string,saving:string}
     */
    private static function priceOf(\WC_Product $product): array
    {
        $now     = (float) wc_get_price_to_display($product);
        $regular = (float) wc_get_price_to_display($product, ['price' => $product->get_regular_price()]);
        if (! $product->is_on_sale() || $regular <= $now || $regular <= 0) {
            return ['price' => self::money($now), 'old' => '', 'save' => '', 'saving' => ''];
        }
        $percent = (int) floor(($regular - $now) / $regular * 100);

        return [
            'price'  => self::money($now),
            'old'    => self::money($regular),
            /* translators: %d: the discount in percent, beside a sale price on the product page. */
            'save'   => sprintf(__('Save %d%%', 'mizzey-site'), $percent),
            'saving' => self::money($regular - $now),
        ];
    }

    /** @return list<int> The product's photos: its image, then its gallery. */
    private static function photos(\WC_Product $product): array
    {
        $ids = array_merge([(int) $product->get_image_id()], array_map('intval', $product->get_gallery_image_ids()));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0 && wp_attachment_is_image($id))));
    }

    /** @return array{kind:string,src:string}|null The product video: an uploaded file, or a YouTube or Vimeo page. */
    public static function video(\WC_Product $product): ?array
    {
        $url = ProductFields::webAddress((string) $product->get_meta(ProductFields::VIDEO));
        if ($url === '') {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([\w-]{6,})~', $url, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&mute=1&rel=0'];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) === 1) {
            return ['kind' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&muted=1'];
        }

        return ['kind' => 'file', 'src' => $url];
    }

    /**
     * The options of a variable product: its attributes with their values, each value's label and swatch.
     *
     * @return list<array{key:string,name:string,label:string,options:list<array{value:string,label:string,swatch:string}>}>
     */
    private static function attributes(\WC_Product_Variable $product): array
    {
        $out = [];
        foreach ($product->get_variation_attributes() as $name => $values) {
            $name  = (string) $name;
            $terms = taxonomy_exists($name) ? wc_get_product_terms($product->get_id(), $name, ['fields' => 'all']) : [];
            $list  = [];
            foreach ((array) $values as $value) {
                $label  = (string) $value;
                $swatch = '';
                foreach ($terms as $term) {
                    if ($term->slug === $value) {
                        $label  = $term->name;
                        $swatch = (string) sanitize_hex_color((string) get_term_meta($term->term_id, self::SWATCH, true));
                    }
                }
                $list[] = ['value' => (string) $value, 'label' => $label, 'swatch' => $swatch];
            }
            $out[] = ['key' => 'attribute_' . sanitize_title($name), 'name' => $name, 'label' => wc_attribute_label($name, $product), 'options' => $list];
        }

        return $out;
    }

    /** One product's page top, or nothing for a product the visitor may not see. */
    public static function render(\WC_Product $product): string
    {
        if (! $product->is_visible() && ! current_user_can('read_post', $product->get_id())) {
            return '';
        }
        self::state();
        // The structured data WooCommerce writes for its own summary (specs/038): the designed page keeps it.
        if (function_exists('WC') && isset(WC()->structured_data)) {
            WC()->structured_data->generate_product_data($product);
        }

        $id       = $product->get_id();
        $variable = $product instanceof \WC_Product_Variable;
        $photos   = self::photos($product);
        $images   = [];
        $sets     = ['' => []];
        foreach ($photos as $photo) {
            $images[]   = $photo;
            $sets[''][] = count($images) - 1;
        }

        $attributes = $variable ? self::attributes($product) : [];
        $variations = [];
        if ($variable) {
            $rest = array_slice($sets[''], 1);
            foreach ($product->get_children() as $childId) {
                $variation = wc_get_product($childId);
                if (! $variation instanceof \WC_Product_Variation || ! $variation->variation_is_visible()) {
                    continue;
                }
                $set   = '';
                $photo = (int) $variation->get_image_id();
                if ($photo > 0 && $photo !== ($photos[0] ?? 0) && wp_attachment_is_image($photo)) {
                    $set = 'p' . $photo;
                    if (! isset($sets[$set])) {
                        $at = array_search($photo, $images, true);
                        if ($at === false) {
                            $images[] = $photo;
                            $at       = count($images) - 1;
                        }
                        // Its photo leads; the product's others follow, without it a second time.
                        $sets[$set] = array_merge([(int) $at], array_values(array_diff($rest, [(int) $at])));
                    }
                }
                $variations[] = ['id' => $variation->get_id(), 'attrs' => $variation->get_variation_attributes(), 'set' => $set, 'buyable' => $variation->is_purchasable()]
                    + self::stock($variation) + self::priceOf($variation);
            }
        }
        $chosen = [];
        if ($variable) {
            $defaults = $product->get_default_attributes();
            foreach ($attributes as $attribute) {
                $want  = (string) ($defaults[sanitize_title($attribute['name'])] ?? '');
                $known = array_column($attribute['options'], 'value');
                if ($want === '' && count($known) === 1) {
                    $want = (string) $known[0];
                }
                $chosen[$attribute['key']] = in_array($want, $known, true) ? $want : '';
            }
        }
        $video = self::video($product);
        $page  = ProductPage::data($product);
        $stock = self::stock($product);
        $out   = ! $variable ? $stock['state'] === 'out' : ! array_filter($variations, static fn (array $v): bool => $v['state'] !== 'out');

        $context = [
            'id'         => $id,
            'variable'   => $variable,
            'attributes' => $attributes,
            'variations' => $variations,
            'chosen'     => (object) $chosen,
            'sets'       => $sets,
            'set'        => '',
            'index'      => 0,
            'qty'        => 1,
            'max'        => $variable ? 99 : $stock['max'],
            'busy'       => false,
            'wished'     => in_array(self::canonical($id), Wishlist::ids(), true),
            'buyNow'     => (string) $page['buy_now'],
            'simple'     => self::priceOf($product) + $stock,
            // The lowest price, shown after "From" while no variant is chosen or the combination is not made.
            'from'       => $variable ? self::money(ProductCard::prices($product)['price']) : '',
            'zoom'       => 100,
        ];

        // A default variant with a photo of its own leads with it: its photo is the first one the page loads.
        $chosenVariation = self::matching($variations, $chosen);
        if ($chosenVariation !== null && $chosenVariation['set'] !== '') {
            $context['set'] = $chosenVariation['set'];
        }
        $gallery = self::gallery($product, $images, $sets[$context['set']], $video);
        $summary = self::summary($product, $page, $attributes, $chosen, $variable, $out, $stock, $context);
        $bar     = self::bar($product, $out);
        $back    = self::back($product);


        return self::el('section', [
            'class'               => ['mizzey-buy', $out ? 'is-out' : '', $variable ? 'is-variable' : ''],
            'aria-label'          => $product->get_name(),
            'data-product-id'     => (string) $id,
            'data-wp-interactive' => self::STORE,
            'data-wp-context'     => (string) wp_json_encode($context),
            'data-wp-init'        => 'callbacks.init',
        ], $back . $gallery . $summary . $bar . self::zoom($images) . self::share($product, $page));
    }

    /** @param list<array<string,mixed>> $variations @param array<string,string> $chosen @return array<string,mixed>|null */
    private static function matching(array $variations, array $chosen): ?array
    {
        if ($chosen === [] || in_array('', $chosen, true)) {
            return null;
        }
        foreach ($variations as $variation) {
            $fits = true;
            foreach ($chosen as $key => $value) {
                $has = (string) ($variation['attrs'][$key] ?? '');
                if ($has !== '' && $has !== $value) {
                    $fits = false;
                }
            }
            if ($fits) {
                return $variation;
            }
        }

        return null;
    }

    /** The way back on a phone: the product's first category, or the shop. */
    private static function back(\WC_Product $product): string
    {
        $terms = get_the_terms($product->get_id(), 'product_cat');
        $term  = is_array($terms) && $terms !== [] ? $terms[0] : null;
        $url   = $term instanceof \WP_Term ? get_term_link($term) : (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/'));
        $name  = $term instanceof \WP_Term ? $term->name : __('Shop', 'mizzey-site');

        return self::el('a', ['class' => 'mizzey-buy__back', 'href' => esc_url(is_string($url) ? $url : home_url('/'))], self::icon('back', 20) . self::el('span', [], esc_html($name)));
    }

    /**
     * The gallery: the stage with its slides, counter, arrows and zoom, the thumbnails and the video.
     *
     * @param list<int> $images
     * @param list<int> $first The indices of the photos shown first: the product's own, or the default variant's.
     * @param array{kind:string,src:string}|null $video
     */
    private static function gallery(\WC_Product $product, array $images, array $first, ?array $video): string
    {
        $zz     = function_exists('MizzeySite\Theme\Components\zz') ? \MizzeySite\Theme\Components\zz() : '';
        $total  = count($first);
        $slides = '';
        foreach ($images as $index => $photo) {
            $lead = $index === ($first[0] ?? 0);
            $img  = wp_get_attachment_image($photo, self::IMAGE_SIZE, false, [
                'class'         => 'mizzey-gallery__image',
                'alt'           => (string) get_post_meta($photo, '_wp_attachment_image_alt', true),
                'loading'       => $lead ? 'eager' : 'lazy',
                'fetchpriority' => $lead ? 'high' : 'low',
                'decoding'      => $lead ? 'sync' : 'async',
            ]);
            if (! $lead) {
                // The photos after the first load when they are shown, so the first one is the page's largest paint.
                $img = (string) preg_replace('/\s(src|srcset)="/', ' data-$1="', $img);
            }
            $slides .= self::el('li', [
                'class'       => ['mizzey-gallery__slide', $lead ? 'is-current' : ''],
                'data-index'  => (string) $index,
                'data-full'   => (string) wp_get_attachment_image_url($photo, 'full'),
                'aria-hidden' => $lead ? null : 'true',
            ], self::el('span', ['class' => 'mizzey-gallery__loading', 'aria-hidden' => 'true'], $zz) . $img);
        }
        if ($video !== null) {
            $slides .= self::el('li', ['class' => ['mizzey-gallery__slide', 'mizzey-gallery__slide--video'], 'data-index' => 'video', 'aria-hidden' => 'true', 'data-kind' => $video['kind'], 'data-src' => esc_url($video['src'])], '');
        }

        $stage = '';
        if ($images === []) {
            $stage .= self::el('div', ['class' => 'mizzey-gallery__none'], self::icon('image', 32) . self::el('span', ['class' => 'mizzey-gallery__none-label'], esc_html__('No photo yet', 'mizzey-site')));
        } else {
            $stage .= self::el('ul', ['class' => 'mizzey-gallery__slides', 'data-wp-on--click' => 'actions.zoomOpen'], $slides);
            $stage .= self::el('span', ['class' => 'mizzey-gallery__showing', 'hidden' => true], '');
            if ($total > 1 || $video !== null) {
                $stage .= function_exists('MizzeySite\Theme\Components\gallery_counter') ? \MizzeySite\Theme\Components\gallery_counter(1, $total + ($video !== null ? 1 : 0)) : '';
            }
            if ($total > 1) {
                $stage .= self::el('button', ['type' => 'button', 'class' => ['mizzey-gallery__arrow', 'mizzey-gallery__arrow--prev'], 'aria-label' => __('Previous photo', 'mizzey-site'), 'data-step' => '-1', 'data-wp-on--click' => 'actions.step'], self::icon('back'))
                    . self::el('button', ['type' => 'button', 'class' => ['mizzey-gallery__arrow', 'mizzey-gallery__arrow--next'], 'aria-label' => __('Next photo', 'mizzey-site'), 'data-step' => '1', 'data-wp-on--click' => 'actions.step'], self::icon('fwd'));
            }
            $stage .= self::el('button', ['type' => 'button', 'class' => 'mizzey-gallery__zoom', 'aria-label' => __('Zoom image', 'mizzey-site'), 'aria-haspopup' => 'dialog', 'data-wp-on--click' => 'actions.zoomOpen'], self::icon('search'));
        }

        $thumbs = '';
        if (count($images) > 1 || ($images !== [] && $video !== null)) {
            foreach ($images as $index => $photo) {
                $in      = in_array($index, $first, true);
                $thumbs .= self::el('button', [
                    'type'              => 'button',
                    'class'             => 'mizzey-gallery__thumb',
                    'data-index'        => (string) $index,
                    'aria-current'      => $index === ($first[0] ?? 0) ? 'true' : 'false',
                    /* translators: %d: a photo's place in the product's gallery. */
                    'aria-label'        => sprintf(__('Photo %d', 'mizzey-site'), $index + 1),
                    'hidden'            => $in ? null : true,
                    'data-wp-on--click' => 'actions.show',
                ], wp_get_attachment_image($photo, self::THUMB_SIZE, false, ['alt' => '', 'loading' => 'lazy', 'class' => 'mizzey-gallery__thumb-image']));
            }
            if ($video !== null) {
                $poster  = $images !== [] ? wp_get_attachment_image($images[0], self::THUMB_SIZE, false, ['alt' => '', 'loading' => 'lazy', 'class' => 'mizzey-gallery__thumb-image']) : '';
                $thumbs .= self::el('button', ['type' => 'button', 'class' => ['mizzey-gallery__thumb', 'is-video'], 'data-index' => 'video', 'aria-current' => 'false', 'aria-label' => __('Play the product video', 'mizzey-site'), 'data-wp-on--click' => 'actions.show'],
                    $poster . self::el('span', ['class' => 'mizzey-gallery__play', 'aria-hidden' => 'true'], self::el('span', ['class' => 'mizzey-gallery__play-disc'], self::icon('play', 20))))
                    . self::el('span', ['class' => 'mizzey-gallery__video-label', 'aria-hidden' => 'true'], esc_html__('Video', 'mizzey-site'));
            }
        }

        return self::el('div', ['class' => 'mizzey-gallery'],
            self::el('div', ['class' => ['mizzey-gallery__stage', $images === [] ? 'is-empty' : '']], $stage)
            . ($thumbs !== '' ? self::el('div', ['class' => 'mizzey-gallery__thumbs'], $thumbs) : ''));
    }

    /**
     * The buying area.
     *
     * @param array<string,mixed> $page specs/055's data.
     * @param list<array<string,mixed>> $attributes
     * @param array<string,string> $chosen
     * @param array{state:string,left:int|null,max:int} $stock
     * @param array<string,mixed> $context
     */
    private static function summary(\WC_Product $product, array $page, array $attributes, array $chosen, bool $variable, bool $out, array $stock, array $context): string
    {
        $html = '';
        $brands = taxonomy_exists('product_brand') ? get_the_terms($product->get_id(), 'product_brand') : false;
        if (is_array($brands) && $brands !== []) {
            $link  = get_term_link($brands[0]);
            $html .= self::el('a', ['class' => 'mizzey-buy__brand', 'href' => esc_url(is_string($link) ? $link : '')], esc_html($brands[0]->name));
        }
        $html .= self::el('h1', ['class' => 'mizzey-buy__name', 'id' => 'mizzey-buy-name-' . $product->get_id()], esc_html($product->get_name()));
        $html .= self::el('button', ['type' => 'button', 'class' => 'mizzey-buy__more', 'aria-expanded' => 'false', 'aria-controls' => 'mizzey-buy-name-' . $product->get_id(), 'data-wp-on--click' => 'actions.more'], esc_html__('Show full name', 'mizzey-site'));

        $count = (int) $product->get_review_count();
        if ($count > 0) {
            $average = number_format((float) $product->get_average_rating(), 1);
            $html   .= self::el('p', ['class' => 'mizzey-buy__rating'],
                self::icon('star', 16, 'mizzey-buy__star')
                . self::el('span', ['class' => 'mizzey-buy__average', 'aria-hidden' => 'true'], esc_html($average))
                . self::el('span', ['class' => 'mizzey-visually-hidden'], esc_html(sprintf(
                    /* translators: %s: the average rating, for example 4.7. */
                    __('Rated %s out of 5', 'mizzey-site'),
                    $average
                )))
                /* translators: %d: the number of reviews. */
                . self::el('a', ['class' => 'mizzey-buy__reviews', 'href' => '#reviews'], esc_html(sprintf(_n('%d review', '%d reviews', $count, 'mizzey-site'), $count))));
        }

        // The price: the chosen variant's, "From" the lowest while nothing is chosen.
        $prices = self::priceOf($product);
        $from   = '';
        if ($variable) {
            $lowest = ProductCard::prices($product);
            $prices = ['price' => self::money($lowest['price']), 'old' => '', 'save' => '', 'saving' => ''];
            $from   = self::el('span', ['class' => 'mizzey-buy__from', 'hidden' => true], esc_html__('From', 'mizzey-site'));
            $match  = self::matching($context['variations'], $chosen);
            if ($match !== null) {
                $prices = array_intersect_key($match, $prices);
            } elseif ($from !== '') {
                $from = self::el('span', ['class' => 'mizzey-buy__from'], esc_html__('From', 'mizzey-site'));
            }
        }
        $html .= self::el('div', ['class' => 'mizzey-buy__prices'],
            $from
            . self::el('span', ['class' => 'mizzey-buy__price'], $prices['price'])
            . self::el('s', ['class' => 'mizzey-buy__old', 'hidden' => $prices['old'] === ''], $prices['old'])
            . self::el('span', ['class' => 'mizzey-buy__save', 'hidden' => $prices['save'] === ''], esc_html($prices['save'])));
        // A variable product with a sale anywhere keeps the saving's line, so a choice moves nothing below it.
        $keep  = $variable && array_filter(array_column($context['variations'], 'saving')) !== [];
        $html .= self::el('p', ['class' => ['mizzey-buy__saving', $prices['saving'] === '' ? 'is-blank' : ''], 'hidden' => $prices['saving'] === '' && ! $keep],
            /* translators: %s: the amount saved, with its currency. */
            sprintf(esc_html__('You save %s', 'mizzey-site'), self::el('strong', ['class' => 'mizzey-buy__saving-amount'], $prices['saving'])));

        $authentic = (array) $page['authenticity'];
        if ((string) ($authentic['statement'] ?? '') !== '') {
            $text  = esc_html((string) $authentic['statement']);
            $html .= self::el('p', ['class' => 'mizzey-buy__authentic'], self::icon('shield', 20) . ((string) ($authentic['url'] ?? '') !== '' ? self::el('a', ['href' => esc_url((string) $authentic['url'])], $text) : self::el('span', [], $text)));
        }
        // The state is the one the line states: the chosen variant's, none while an option is missing.
        $shown = $stock['state'];
        if ($variable) {
            $match = self::matching($context['variations'], $chosen);
            $shown = $match !== null ? (string) $match['state'] : '';
        }
        // No line while an option is missing, as E4 draws.
        $html .= self::el('p', ['class' => 'mizzey-buy__stock', 'data-state' => $shown, 'aria-live' => 'polite', 'hidden' => $variable && $shown === ''], self::stockLine($variable, $stock, $context));

        // The form: WooCommerce's own add-to-cart post, so it works without a script.
        $form = self::el('input', ['type' => 'hidden', 'name' => 'add-to-cart', 'value' => (string) $product->get_id()]);
        if ($variable) {
            $form .= self::el('input', ['type' => 'hidden', 'name' => 'product_id', 'value' => (string) $product->get_id()])
                . self::el('input', ['type' => 'hidden', 'name' => 'variation_id', 'value' => '0', 'class' => 'mizzey-buy__variation']);
            foreach ($attributes as $attribute) {
                $form .= self::group($attribute, (string) ($chosen[$attribute['key']] ?? ''), $context['variations'], $chosen);
            }
        }
        $form .= self::el('p', ['class' => 'mizzey-buy__message', 'hidden' => true, 'aria-live' => 'polite'], self::icon('info', 20) . self::el('span', ['class' => 'mizzey-buy__message-text'], ''));
        $label  = $out ? __('Out of stock', 'mizzey-site') : __('Add to cart', 'mizzey-site');
        $add    = self::el('button', [
            'type'              => 'submit',
            'class' => ['mizzey-button', 'mizzey-button--primary', 'mizzey-buy__add'],
            'disabled'          => $out,
        ], self::el('span', ['class' => 'mizzey-buy__label'], esc_html($label)) . self::loader() . self::el('span', ['class' => 'mizzey-buy__done'], self::icon('tick', 20, 'mizzey-buy__tick') . self::el('span', [], esc_html__('Added', 'mizzey-site'))));
        $actions = '';
        if (! $out) {
            $step     = static fn (int $by, string $text, string $icon, bool $off): string => self::el('button', ['type' => 'button', 'class' => 'mizzey-buy__step', 'aria-label' => $text, 'data-step' => (string) $by, 'disabled' => $off, 'data-wp-on--click' => 'actions.qty'], self::icon($icon, 22));
            $stepper  = self::el('span', ['class' => 'mizzey-buy__stepper', 'role' => 'group', 'aria-label' => __('Quantity', 'mizzey-site')],
                $step(-1, __('Decrease quantity', 'mizzey-site'), 'minus', true)
                . self::el('output', ['class' => 'mizzey-buy__count', 'aria-live' => 'polite'], '1')
                . $step(1, __('Increase quantity', 'mizzey-site'), 'plus', ! $variable && $stock['max'] <= 1))
                . self::el('input', ['type' => 'hidden', 'name' => 'quantity', 'value' => '1', 'class' => 'mizzey-buy__quantity']);
            $actions .= self::el('div', ['class' => 'mizzey-buy__qty'], self::el('span', ['class' => 'mizzey-buy__qty-label', 'aria-hidden' => 'true'], esc_html__('Quantity', 'mizzey-site')) . $stepper);
        }
        $form .= self::el('div', ['class' => 'mizzey-buy__actions'], $actions . $add);
        if (! $out) {
            // While an option is missing the boards draw no Buy Now (E4): it comes once a variant is chosen.
            $form .= self::el('a', [
                'class' => ['mizzey-button', 'mizzey-button--secondary', 'mizzey-buy__now'],
                'hidden'            => $variable && self::matching($context['variations'], $chosen) === null,
                'href'              => esc_url((string) $page['buy_now']),
                'data-wp-on--click' => 'actions.buyNow',
            ], self::el('span', ['class' => 'mizzey-buy__label'], esc_html__('Buy now', 'mizzey-site')) . self::loader());
        }
        $html .= self::el('form', ['class' => 'mizzey-buy__form', 'method' => 'post', 'action' => esc_url((string) $product->get_permalink()), 'enctype' => 'multipart/form-data', 'data-wp-on--submit' => 'actions.submit'], $form);
        if ($out) {
            $html .= self::el('p', ['class' => 'mizzey-buy__out'], self::icon('unavailable', 20) . self::el('span', [], esc_html__('Out of stock in every option. Add it to your wishlist to find it again.', 'mizzey-site')));
        }

        $wished = (bool) $context['wished'];
        $html  .= self::el('div', ['class' => 'mizzey-buy__links'],
            self::el('button', ['type' => 'button', 'class' => 'mizzey-buy__wish', 'aria-pressed' => $wished ? 'true' : 'false', 'data-wp-on--click' => 'actions.wish'],
                self::el('span', ['class' => 'mizzey-buy__heart', 'aria-hidden' => 'true'], self::icon('heart', 20, 'mizzey-buy__heart-line') . self::icon('heart-filled', 20, 'mizzey-buy__heart-fill'))
                . self::el('span', ['class' => 'mizzey-buy__wish-label'], esc_html($wished ? __('In your wishlist', 'mizzey-site') : __('Add to wishlist', 'mizzey-site'))))
            . self::el('button', ['type' => 'button', 'class' => 'mizzey-buy__share', 'aria-haspopup' => 'dialog', 'data-wp-on--click' => 'actions.share'], self::icon('share', 20) . self::el('span', [], esc_html__('Share', 'mizzey-site'))));

        $html .= self::delivery($page);
        $features = trim((string) $product->get_short_description());
        if ($features !== '') {
            $html .= self::el('section', ['class' => 'mizzey-buy__features', 'aria-labelledby' => 'mizzey-buy-features-' . $product->get_id()],
                self::el('h2', ['id' => 'mizzey-buy-features-' . $product->get_id()], esc_html__('Key features', 'mizzey-site'))
                // Each point carries its Indigo dot, as drawn.
                . self::el('div', ['class' => 'mizzey-buy__features-text'], (string) preg_replace('/<li(\s[^>]*)?>/i', '$0<span class="mizzey-buy__dot" aria-hidden="true"></span>', wp_kses_post(wpautop($features)))));
        }

        return self::el('div', ['class' => 'mizzey-buy__summary'], $html);
    }

    /** @param array<string,mixed> $context */
    private static function stockLine(bool $variable, array $stock, array $context): string
    {
        if ($variable) {
            $match = self::matching($context['variations'], (array) $context['chosen']);
            if ($match === null) {
                return self::el('span', ['class' => 'mizzey-buy__stock-text'], '');
            }
            $stock = ['state' => $match['state'], 'left' => $match['left']];
            $which = self::chosenLabel($context['attributes'], (array) $context['chosen']);
        }
        $icons = '';
        foreach (['in' => 'check', 'low' => 'warning', 'out' => 'unavailable', 'unknown' => 'info'] as $state => $icon) {
            $icons .= self::icon($icon, 16, implode(' ', ['mizzey-buy__stock-icon', 'mizzey-buy__stock-icon--' . $state]));
        }

        return $icons . self::el('span', ['class' => 'mizzey-buy__stock-text'], esc_html(self::stockText($stock['state'], $stock['left'], $which ?? '')));
    }

    /** @param list<array<string,mixed>> $attributes @param array<string,string> $chosen */
    private static function chosenLabel(array $attributes, array $chosen): string
    {
        $parts = [];
        foreach ($attributes as $attribute) {
            foreach ($attribute['options'] as $option) {
                if ($option['value'] === ($chosen[$attribute['key']] ?? null)) {
                    $parts[] = $option['label'];
                }
            }
        }

        /* translators: the separator between the chosen options, for example "24 oz, Vanilla". */
        return implode(__(', ', 'mizzey-site'), $parts);
    }

    private static function stockText(string $state, ?int $left, string $which): string
    {
        switch ($state) {
            case 'out':
                return __('Out of stock', 'mizzey-site');
            case 'unknown':
                return __('Availability is being confirmed', 'mizzey-site');
            case 'low':
                return $which === ''
                    /* translators: %d: how many are left. */
                    ? sprintf(__('Only %d left', 'mizzey-site'), (int) $left)
                    /* translators: 1: how many are left, 2: the chosen options, for example "24 oz, Vanilla". */
                    : sprintf(__('Only %1$d left in %2$s', 'mizzey-site'), (int) $left, $which);
            default:
                return $which === ''
                    ? __('In stock', 'mizzey-site')
                    /* translators: %s: the chosen options, for example "24 oz, Vanilla". */
                    : sprintf(__('In stock in %s', 'mizzey-site'), $which);
        }
    }

    /**
     * One option group: its legend with the chosen value, and its values as pills or swatches.
     *
     * @param array<string,mixed> $attribute
     * @param list<array<string,mixed>> $variations
     * @param array<string,string> $chosen
     */
    private static function group(array $attribute, string $current, array $variations, array $chosen): string
    {
        $buttons = '';
        $value   = '';
        $swatch  = array_filter(array_column($attribute['options'], 'swatch')) !== [];
        foreach ($attribute['options'] as $option) {
            $checked = $current === $option['value'];
            if ($checked) {
                $value = $option['label'];
            }
            $want             = $chosen;
            $want[$attribute['key']] = $option['value'];
            $outHere          = ! self::available($variations, $want);
            $label            = self::el('span', ['class' => 'mizzey-buy__option-label'], esc_html($option['label']));
            $note             = self::el('span', ['class' => 'mizzey-buy__option-note'], esc_html__('Sold out', 'mizzey-site'));
            $inner            = $swatch && $option['swatch'] !== ''
                ? self::el('span', ['class' => 'mizzey-buy__disc', 'style' => sprintf('--mizzey-swatch:%s', $option['swatch'])], '') . $label . $note
                : $label . $note;
            $buttons         .= self::el('button', [
                'type'              => 'button',
                'role'              => 'radio',
                'class'             => ['mizzey-buy__option', $swatch ? 'mizzey-buy__option--swatch' : '', $outHere ? 'is-out' : ''],
                'aria-checked'      => $checked ? 'true' : 'false',
                'tabindex'          => $checked || ($current === '' && $option === $attribute['options'][0]) ? '0' : '-1',
                'data-key'          => $attribute['key'],
                'data-value'        => $option['value'],
                'data-wp-on--click' => 'actions.choose',
                'data-wp-on--keydown' => 'actions.arrows',
            ], $inner);
        }
        $legend = self::el('span', ['class' => 'mizzey-buy__legend-name'], esc_html(sprintf(
            /* translators: %s: the name of a product option, for example "Flavour", followed by the chosen value. */
            __('%s:', 'mizzey-site'),
            $attribute['label']
        ))) . self::el('span', ['class' => 'mizzey-buy__legend-value', 'data-key' => $attribute['key']], esc_html(' ' . $value));
        $id = 'mizzey-buy-group-' . sanitize_html_class($attribute['key']);

        return self::el('div', ['class' => 'mizzey-buy__group', 'role' => 'radiogroup', 'aria-labelledby' => $id, 'data-key' => $attribute['key'], 'data-label' => $attribute['label']],
            self::el('p', ['class' => 'mizzey-buy__legend', 'id' => $id], $legend)
            . self::el('div', ['class' => 'mizzey-buy__options'], $buttons)
            . self::el('input', ['type' => 'hidden', 'name' => $attribute['key'], 'value' => $current]));
    }

    /** @param list<array<string,mixed>> $variations @param array<string,string> $want */
    private static function available(array $variations, array $want): bool
    {
        foreach ($variations as $variation) {
            if ($variation['state'] === 'out') {
                continue;
            }
            $fits = true;
            foreach ($want as $key => $value) {
                $has = (string) ($variation['attrs'][$key] ?? '');
                if ($value !== '' && $has !== '' && $has !== $value) {
                    $fits = false;
                }
            }
            if ($fits) {
                return true;
            }
        }

        return false;
    }

    /** The delivery box: the estimate of specs/055 beside the van, then the returns summary and its page. */
    private static function delivery(array $page): string
    {
        $estimate = (array) ($page['delivery'] ?? []);
        $returns  = (array) ($page['returns'] ?? []);
        if ((string) ($estimate['text'] ?? '') === '' && (string) ($returns['summary'] ?? '') === '') {
            return '';
        }
        $van  = function_exists('get_theme_file_uri') ? get_theme_file_uri(is_rtl() ? 'assets/images/van-rtl.webp' : 'assets/images/van.webp') : '';
        $html = '';
        if ((string) ($estimate['text'] ?? '') !== '') {
            $html .= self::el('p', ['class' => 'mizzey-buy__estimate'], self::el('img', ['class' => 'mizzey-buy__van', 'src' => esc_url($van), 'alt' => '', 'width' => '64', 'height' => '31', 'loading' => 'lazy', 'decoding' => 'async'], '') . self::el('span', ['class' => 'mizzey-buy__when'], \MizzeySite\Shipping\DeliveryDates::sentence(['min' => (int) ($estimate['min'] ?? 0), 'max' => (int) ($estimate['max'] ?? 0)])));
        }
        if ((string) ($returns['summary'] ?? '') !== '') {
            // As drawn: the line between, then the arrow, the summary and its page's link, 8 apart.
            $link  = (string) ($returns['url'] ?? '') !== '' ? self::el('a', ['href' => esc_url((string) $returns['url'])], esc_html__('Return & refund policy', 'mizzey-site')) : '';
            $html .= ($html !== '' ? self::el('span', ['class' => 'mizzey-buy__rule', 'aria-hidden' => 'true'], '') : '')
                . self::el('p', ['class' => 'mizzey-buy__returns'], self::icon('returns', 20) . self::el('span', [], esc_html((string) $returns['summary'])) . $link);
        }

        return self::el('div', ['class' => 'mizzey-buy__delivery'], $html);
    }

    /** The phone's buy bar: the price and the chosen options, and the same Add to Cart. */
    private static function bar(\WC_Product $product, bool $out): string
    {
        $label = $out ? __('Out of stock', 'mizzey-site') : __('Add to cart', 'mizzey-site');

        return self::el('div', ['class' => 'mizzey-buy-bar', 'aria-hidden' => 'true', 'inert' => true],
            self::el('div', ['class' => 'mizzey-buy-bar__summary'],
                self::el('span', ['class' => 'mizzey-buy-bar__price'], '')
                . self::el('span', ['class' => 'mizzey-buy-bar__note'], '')
                . self::el('a', ['class' => 'mizzey-buy-bar__cart', 'href' => esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')), 'hidden' => true], esc_html__('View cart', 'mizzey-site')))
            . self::el('button', ['type' => 'button', 'class' => ['mizzey-button', 'mizzey-button--primary', 'mizzey-buy-bar__add'], 'disabled' => $out, 'data-wp-on--click' => 'actions.barAdd'],
                self::el('span', ['class' => 'mizzey-buy__label'], esc_html($label)) . self::loader() . self::el('span', ['class' => 'mizzey-buy__done'], self::icon('tick', 20, 'mizzey-buy__tick') . self::el('span', [], esc_html__('Added', 'mizzey-site')))));
    }

    /** @param list<int> $images The zoom dialog: one photo at a time, its level, its thumbnails. */
    private static function zoom(array $images): string
    {
        if ($images === []) {
            return '';
        }
        $thumbs = '';
        foreach ($images as $index => $photo) {
            $thumbs .= self::el('button', ['type' => 'button', 'class' => 'mizzey-zoom__thumb', 'data-index' => (string) $index, 'aria-current' => 'false',
                /* translators: %d: a photo's place in the product's gallery. */
                'aria-label' => sprintf(__('Photo %d', 'mizzey-site'), $index + 1), 'data-wp-on--click' => 'actions.show'],
                wp_get_attachment_image($photo, self::THUMB_SIZE, false, ['alt' => '', 'loading' => 'lazy']));
        }
        $step = static fn (string $class, string $text, string $icon, int $by): string => self::el('button', ['type' => 'button', 'class' => 'mizzey-zoom__' . $class, 'aria-label' => $text, 'data-level' => (string) $by, 'data-wp-on--click' => 'actions.level'], self::icon($icon, 22));

        return self::el('dialog', ['class' => 'mizzey-zoom', 'aria-label' => __('Product photos', 'mizzey-site'), 'data-wp-on--close' => 'actions.zoomClosed', 'data-wp-on--click' => 'actions.zoomBackdrop'],
            self::el('div', ['class' => 'mizzey-zoom__panel'],
                self::el('div', ['class' => 'mizzey-zoom__head'],
                    self::el('span', ['class' => 'mizzey-zoom__counter'], '')
                    . self::el('button', ['type' => 'button', 'class' => 'mizzey-zoom__close', 'aria-label' => __('Close photos', 'mizzey-site'), 'data-wp-on--click' => 'actions.zoomClose'], self::icon('close')))
                . self::el('div', ['class' => 'mizzey-zoom__view'],
                    self::el('img', ['class' => 'mizzey-zoom__image', 'src' => '', 'alt' => ''], '')
                    . self::el('button', ['type' => 'button', 'class' => ['mizzey-gallery__arrow', 'mizzey-gallery__arrow--prev'], 'aria-label' => __('Previous photo', 'mizzey-site'), 'data-step' => '-1', 'data-wp-on--click' => 'actions.step'], self::icon('back'))
                    . self::el('button', ['type' => 'button', 'class' => ['mizzey-gallery__arrow', 'mizzey-gallery__arrow--next'], 'aria-label' => __('Next photo', 'mizzey-site'), 'data-step' => '1', 'data-wp-on--click' => 'actions.step'], self::icon('fwd'))
                    . self::el('span', ['class' => 'mizzey-zoom__level'],
                        $step('out', __('Zoom out', 'mizzey-site'), 'minus', -30)
                        . self::el('span', ['class' => 'mizzey-zoom__value', 'aria-live' => 'polite'], '100%')
                        . $step('in', __('Zoom in', 'mizzey-site'), 'plus', 30)))
                . self::el('div', ['class' => 'mizzey-zoom__thumbs'], $thumbs)));
    }

    /** Share: Copy link and the addresses of specs/055, as a popover on a desktop and a sheet on a phone. */
    private static function share(\WC_Product $product, array $page): string
    {
        $share = (array) ($page['share'] ?? []);
        $links = (array) ($share['links'] ?? []);
        $names = ['whatsapp' => ['WhatsApp', 'whatsapp'], 'facebook' => ['Facebook', 'facebook'], 'x' => ['X', 'x'], 'email' => [__('Email', 'mizzey-site'), 'mail']];
        $items = self::el('button', ['type' => 'button', 'class' => ['mizzey-share__item', 'mizzey-share__copy'], 'data-url' => esc_url((string) ($share['url'] ?? '')), 'data-wp-on--click' => 'actions.copy'],
            self::el('span', ['class' => 'mizzey-share__icon'], self::icon('link', 20)) . self::el('span', ['class' => 'mizzey-share__copy-label'], esc_html__('Copy link', 'mizzey-site')));
        foreach ($names as $key => [$label, $icon]) {
            if (! empty($links[$key])) {
                $items .= self::el('a', ['class' => 'mizzey-share__item', 'href' => esc_url((string) $links[$key], ['https', 'mailto']), 'target' => $key === 'email' ? null : '_blank', 'rel' => 'noopener'],
                    self::el('span', ['class' => 'mizzey-share__icon'], self::icon($icon, 20)) . self::el('span', [], esc_html($label)));
            }
        }
        $thumb = (int) $product->get_image_id() > 0 ? wp_get_attachment_image((int) $product->get_image_id(), self::THUMB_SIZE, false, ['alt' => '', 'loading' => 'lazy', 'class' => 'mizzey-share__thumb']) : '';

        return self::el('dialog', ['class' => 'mizzey-share', 'aria-labelledby' => 'mizzey-share-title-' . $product->get_id(), 'data-wp-on--close' => 'actions.shareClosed', 'data-wp-on--click' => 'actions.shareBackdrop'],
            self::el('div', ['class' => 'mizzey-share__panel'],
                self::el('span', ['class' => 'mizzey-share__handle', 'aria-hidden' => 'true'], '')
                . self::el('div', ['class' => 'mizzey-share__head'],
                    self::el('h2', ['class' => 'mizzey-share__title', 'id' => 'mizzey-share-title-' . $product->get_id()], esc_html__('Share', 'mizzey-site'))
                    . self::el('button', ['type' => 'button', 'class' => 'mizzey-share__close', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.shareClose'], self::icon('close')))
                . self::el('p', ['class' => 'mizzey-share__product'], $thumb . self::el('span', [], esc_html($product->get_name())))
                . self::el('div', ['class' => 'mizzey-share__list'], $items)));
    }

    private static function canonical(int $productId): int
    {
        $canonical = CanonicalProduct::of($productId);

        return $canonical instanceof \WC_Product ? $canonical->get_id() : $productId;
    }

    private static bool $stated = false;

    /** What the script needs: the addresses, the nonces and every word it shows, translated. */
    private static function state(): void
    {
        if (self::$stated) {
            return;
        }
        self::$stated = true;
        wp_interactivity_state(self::STORE, [
            'cartApi'     => esc_url_raw(rest_url('wc/store/v1/cart/add-item')),
            // The zoom's gestures, a module of their own fetched on the first zoom, versioned by its change time.
            'zoomModule'  => esc_url_raw(add_query_arg('ver', (string) filemtime(__DIR__ . '/product/buy/zoom.js'), plugins_url('product/buy/zoom.js', __FILE__))),
            // The flight to the cart and the share popover's place, fetched on the first press.
            'motionModule' => esc_url_raw(add_query_arg('ver', (string) filemtime(__DIR__ . '/product/buy/motion.js'), plugins_url('product/buy/motion.js', __FILE__))),
            'storeNonce'  => wp_create_nonce('wc_store_api'),
            'wishlistApi' => esc_url_raw(rest_url(WishlistRoutes::NS . '/wishlist/items')),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'words'       => [
                'in'         => __('In stock', 'mizzey-site'),
                /* translators: %s: the chosen options, for example "24 oz, Vanilla". */
                'inWhich'    => __('In stock in %s', 'mizzey-site'),
                /* translators: 1: how many are left, 2: the chosen options. */
                'lowWhich'   => __('Only %1$d left in %2$s', 'mizzey-site'),
                /* translators: %d: how many are left. */
                'low'        => __('Only %d left', 'mizzey-site'),
                'out'        => __('Out of stock', 'mizzey-site'),
                'unknown'    => __('Availability is being confirmed', 'mizzey-site'),
                /* translators: 1: the first chosen option, 2: the others, as in "24 oz in Chocolate". */
                'combo'      => __('%1$s in %2$s', 'mizzey-site'),
                /* translators: 1: a list of option names, 2: the last one, as in "flavour or size". */
                'or'         => __('%1$s or %2$s', 'mizzey-site'),
                /* translators: 1: the chosen options, as in "24 oz in Chocolate", 2: the option names, as in "flavour or size". */
                'soldOut'    => __('%1$s is sold out. Choose another %2$s.', 'mizzey-site'),
                /* translators: 1: the chosen options, as in "10 oz in Salted caramel", 2: the option names, as in "flavour or size". */
                'notMade'    => __('%1$s is not made. Choose another %2$s.', 'mizzey-site'),
                /* translators: %s: the name of an option not chosen yet, for example "size", in the buy bar. */
                'chooseShort' => __('Choose a %s', 'mizzey-site'),
                'unknownShort' => __('Availability being confirmed', 'mizzey-site'),
                /* translators: %s: the name of an option not chosen yet, for example "Size". */
                'choose'     => __('Choose a %s to add this to your cart.', 'mizzey-site'),
                'chooseBtn'  => __('Choose options', 'mizzey-site'),
                'add'        => __('Add to cart', 'mizzey-site'),
                'sep'        => __(', ', 'mizzey-site'),
                /* translators: 1: the photo shown, 2: the number of photos. */
                'counter'    => __('%1$d / %2$d', 'mizzey-site'),
                /* translators: 1: the photo shown, 2: the number of photos, 3: the option whose photos they are. */
                'counterOf'  => __('%1$d / %2$d · %3$s', 'mizzey-site'),
                /* translators: %s: the option whose photos the gallery shows, for example "Vanilla". */
                'showing'    => __('Showing %s', 'mizzey-site'),
                /* translators: %d: a photo's place in the product's gallery. */
                'photoShown' => __('Photo %d, shown', 'mizzey-site'),
                /* translators: %d: a photo's place in the product's gallery. */
                'photo'      => __('Photo %d', 'mizzey-site'),
                'more'       => __('Show full name', 'mizzey-site'),
                'less'       => __('Show less', 'mizzey-site'),
                'wish'       => __('Add to wishlist', 'mizzey-site'),
                'wished'     => __('In your wishlist', 'mizzey-site'),
                'copied'     => __('Link copied', 'mizzey-site'),
                'copy'       => __('Copy link', 'mizzey-site'),
                'failed'     => __('This could not be added. Please try again.', 'mizzey-site'),
                'wishFailed' => __('Your wishlist could not be changed. Please try again.', 'mizzey-site'),
            ],
        ]);
        add_action('wp_footer', [self::class, 'toasts']);
    }

    /** Where the page's notices appear, and the three it clones: added to the cart, removed from the wishlist, an error. */
    public static function toasts(): void
    {
        $region = self::el('div', ['class' => 'mizzey-buy-toasts', 'aria-live' => 'polite']);
        if (! function_exists('MizzeySite\Theme\Components\notice')) {
            echo $region; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by el().
            return;
        }
        $added   = \MizzeySite\Theme\Components\notice(['type' => 'cart', 'text' => __('Added to your cart', 'mizzey-site'), 'action' => ['label' => __('View cart', 'mizzey-site'), 'url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')], 'life' => true]);
        $removed = \MizzeySite\Theme\Components\notice(['type' => 'info', 'text' => __('Removed from your wishlist', 'mizzey-site'), 'action' => ['label' => __('Undo', 'mizzey-site'), 'url' => '#undo'], 'life' => true]);
        $error   = \MizzeySite\Theme\Components\notice(['type' => 'error', 'text' => __('This could not be added. Please try again.', 'mizzey-site'), 'life' => true]);
        $strip   = static fn (string $html): string => (string) preg_replace('/\sdata-wp-interactive="[^"]*"/', '', $html, 1);
        echo $region // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by el() and notice().
            . self::el('template', ['id' => 'mizzey-buy-toast-added'], $strip($added))
            . self::el('template', ['id' => 'mizzey-buy-toast-removed'], $strip($removed))
            . self::el('template', ['id' => 'mizzey-buy-toast-error'], $strip($error));
    }
}

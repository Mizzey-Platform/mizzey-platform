<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

use MizzeySite\Catalogue\CanonicalProduct;
use MizzeySite\Catalogue\ProductFields;
use MizzeySite\Stock\CardStock;
use MizzeySite\Wishlist\Wishlist;
use MizzeySite\Wishlist\WishlistRoutes;

defined('ABSPATH') || exit;

/**
 * The product card: one card everywhere a product is listed (rails, grids, search), as C1-Components 06 and 07 and
 * C1-Options (g, the chosen labels) draw it, and the loading card of C1-Components 09.
 *
 * What a card shows, each fact in its own zone:
 *
 * - **The image**, any shape, shown whole in its well (WooCommerce's uncropped size, never the square crop); with no
 *   image, the image glyph. While the photo loads, the well holds the tint and the breathing ZZ (slice 1).
 * - **One merchandising tag** at most (MER-01), in the top start corner: the first of the product's badges in D-19's
 *   precedence (Limited, New, Best Seller, Trending; `ProductFields::badgesOf()`). The wishlist heart keeps the top end
 *   corner (PLP-05).
 * - **The name**, clamped to two lines, linking to the product; **the rating** (REV-04), WooCommerce's average and
 *   review count, with the number of options of a variable product after it. No rating leaves the line empty.
 * - **The price line** (PLP-02, MER-07): the price, the struck regular price and the orange "-N%" tag; under 260 wide
 *   the struck price is line 2 and the tag line 3. A variable product shows its lowest price.
 * - **Line 2**: the stock state (PLP-03) from `CardStock`: "Only N left", or "Out of stock", which also dims the
 *   image to 60%, hides the sale and the add button; after an add, "Added".
 * - **Quick add** (PLP-04): the "+" adds one through WooCommerce's Store API (`cart/add-item`), which asks the stock
 *   source (specs/062); a product with options opens its options as a sheet on a phone and a popover on a desktop.
 *   Nothing claims success before the server answers.
 *
 * The behaviour is the block's script module (store `mizzey/card`, the Interactivity API); every word it shows comes
 * from here, translated. A card is the block `mizzey/product-card`, so its stylesheet and script load only on a page
 * that shows one; `ProductRail` places cards in a rail or a grid. Markup is built element by element (`el()`), never
 * from a template string, so every attribute is escaped where it is written.
 *
 * Register ids: PLP-01, PLP-02, PLP-03, PLP-04, PLP-05, MER-01, MER-07, REV-04. See specs/066-stage-2-shop-front,
 * slice 4. D-29's gift shows as the stock state of whichever source the product is set to.
 */
final class ProductCard
{
    public const BLOCK      = 'mizzey/product-card';
    public const RAIL_BLOCK = 'mizzey/product-rail';
    public const STORE      = 'mizzey/card';
    /** The image size: WooCommerce's single image size is never cropped, so every shape is shown whole. */
    public const IMAGE_SIZE = 'woocommerce_single';

    /** @var list<int>|null The visitor's saved canonical product ids, read once per request. */
    private static ?array $wished = null;
    private static bool $footer = false;

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlocks']);
    }

    public static function registerBlocks(): void
    {
        register_block_type(__DIR__ . '/blocks/product-card');
        register_block_type(__DIR__ . '/blocks/product-rail');
    }

    /**
     * One element. A null or false attribute is left out, true is written bare; the inner HTML is the caller's,
     * already escaped.
     *
     * @param array<string, string|int|bool|list<string>|null> $attributes A list (classes) is joined with a space.
     */
    public static function el(string $tag, array $attributes = [], string $inner = ''): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(' ', array_filter($value));
            }
            $out .= true === $value ? ' ' . esc_attr((string) $name) : ' ' . esc_attr((string) $name) . '="' . esc_attr((string) $value) . '"';
        }

        return '<' . $tag . $out . '>' . $inner . '</' . $tag . '>';
    }

    /**
     * One card, as a block, so its assets load with it.
     */
    public static function block(int $productId): string
    {
        return render_block(['blockName' => self::BLOCK, 'attrs' => ['productId' => $productId], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []]);
    }

    /** A card for a product the visitor may see, or nothing. */
    public static function render(\WC_Product $product): string
    {
        if (! $product->is_visible() && ! current_user_can('read_post', $product->get_id())) {
            return '';
        }
        self::state();

        $id       = $product->get_id();
        $name     = $product->get_name();
        $stock    = CardStock::of($product);
        $out      = CardStock::OUT === $stock['state'];
        $variable = $product instanceof \WC_Product_Variable && ! $out;
        $prices   = self::prices($product);
        $sale     = ! $out && null !== $prices['regular'];
        $wished   = in_array(self::canonical($id), self::wished(), true);

        $line = '';
        if ($out) {
            $line = __('Out of stock', 'mizzey-site');
        } elseif (CardStock::LOW === $stock['state']) {
            /* translators: %d: how many of the product are left, shown on a product card. */
            $line = sprintf(__('Only %d left', 'mizzey-site'), (int) $stock['left']);
        }

        $labels = [
            'add'    => $variable
                ? __('Add to cart, choose options', 'mizzey-site')
                /* translators: %s: the product's name. */
                : sprintf(__('Add %s to cart', 'mizzey-site'), $name),
            'adding' => __('Adding', 'mizzey-site'),
            /* translators: %s: the product's name. */
            'added'  => sprintf(__('%s is in your cart. Add one more', 'mizzey-site'), $name),
            'wish'   => __('Add to wishlist', 'mizzey-site'),
            'wished' => __('Remove from wishlist', 'mizzey-site'),
        ];
        $context = [
            'id'        => $id,
            'variable'  => $variable,
            'added'     => false,
            'busy'      => false,
            'loaded'    => false,
            'wished'    => $wished,
            'addLabel'  => $labels['add'],
            'wishLabel' => $wished ? $labels['wished'] : $labels['wish'],
            'labels'    => $labels,
        ];
        $picker = $product instanceof \WC_Product_Variable && $variable ? self::picker($product, $context) : '';

        $heart = self::el('button', [
            'type'                       => 'button',
            'class'                      => 'mizzey-card__heart',
            'aria-pressed'               => $wished ? 'true' : 'false',
            'aria-label'                 => $context['wishLabel'],
            'data-wp-on--click'          => 'actions.wish',
            'data-wp-bind--aria-pressed' => 'context.wished',
            'data-wp-bind--aria-label'   => 'context.wishLabel',
        ], self::icon('heart', 20, 'mizzey-card__heart-line') . self::icon('heart-filled', 20, 'mizzey-card__heart-fill'));

        $prices_html = self::el('span', ['class' => 'mizzey-card__price'], self::money($prices['price']));
        if ($sale) {
            $prices_html .= self::el('s', ['class' => 'mizzey-card__old'], self::el('span', ['class' => 'mizzey-visually-hidden'], esc_html__('Was', 'mizzey-site')) . ' ' . self::money((float) $prices['regular']));
        }
        if ('' !== $line) {
            $prices_html .= self::el('span', ['class' => 'mizzey-card__line'], esc_html($line));
        }
        if (! $out) {
            $prices_html .= self::el('span', ['class' => 'mizzey-card__added', 'aria-hidden' => 'true'], self::icon('tick', 14, 'mizzey-card__added-tick') . esc_html__('Added', 'mizzey-site'));
        }
        if ($sale) {
            $prices_html .= self::saleTag((int) $prices['percent']);
        }
        $foot = self::el('span', ['class' => 'mizzey-card__prices'], $prices_html);
        if (! $out) {
            $foot .= self::el('button', [
                'type'                     => 'button',
                'class'                    => 'mizzey-card__add',
                'aria-label'               => $labels['add'],
                'aria-haspopup'            => $variable ? 'dialog' : null,
                'aria-controls'            => $variable ? (string) ($context['pickerId'] ?? '') : null,
                'data-wp-on--click'        => 'actions.add',
                'data-wp-bind--aria-label' => 'context.addLabel',
                'data-wp-bind--aria-busy'  => 'context.busy',
            ], self::el('span', ['class' => 'mizzey-card__disc', 'aria-hidden' => 'true'], self::icon('plus', 20, 'mizzey-card__plus') . self::loader() . self::icon('tick', 20, 'mizzey-card__tick')));
        }

        $inner = self::tag($product)
            . self::el('div', ['class' => 'mizzey-card__well', 'data-wp-init' => 'callbacks.image', 'data-wp-class--is-loaded' => 'context.loaded'], self::image($product) . $heart)
            . self::el('h3', ['class' => 'mizzey-card__name'], self::el('a', ['href' => esc_url((string) $product->get_permalink())], esc_html($name)))
            . self::meta($product, $variable)
            . self::el('div', ['class' => 'mizzey-card__foot'], $foot)
            . $picker;

        return self::el('article', [
            'class'                     => ['mizzey-card', $out ? 'is-out' : '', $variable ? 'is-variable' : '', $sale ? 'has-sale' : '', '' !== $line ? 'has-line' : ''],
            'data-product-id'           => (string) $id,
            'data-wp-interactive'       => self::STORE,
            'data-wp-context'           => (string) wp_json_encode($context),
            'data-wp-class--is-added'   => 'context.added',
            'data-wp-class--is-busy'    => 'context.busy',
            'data-wp-class--is-wished'  => 'context.wished',
        ], $inner);
    }

    /** The loading card: the same box, the well's breathing ZZ, and bars at the size of what they stand for. */
    public static function skeleton(): string
    {
        $zz  = function_exists('MizzeySite\Theme\Components\zz') ? \MizzeySite\Theme\Components\zz() : '';
        $bar = static fn (string $modifier): string => self::el('span', ['class' => ['mizzey-skeleton', 'mizzey-card__bar', 'mizzey-card__bar--' . $modifier], 'aria-hidden' => 'true']);

        return self::el('div', ['class' => ['mizzey-card', 'mizzey-card--skeleton'], 'role' => 'img', 'aria-label' => __('Loading', 'mizzey-site')],
            self::el('span', ['class' => ['mizzey-card__well', 'mizzey-well']], $zz)
            . self::el('span', ['class' => 'mizzey-card__name'], $bar('name') . $bar('name-short'))
            . self::el('span', ['class' => 'mizzey-card__meta'], $bar('meta'))
            . self::el('span', ['class' => 'mizzey-card__foot'], $bar('price') . self::el('span', ['class' => ['mizzey-skeleton', 'mizzey-card__bar-add'], 'aria-hidden' => 'true']))
        );
    }

    /**
     * The price shown, and the regular price and percent when the product is on sale (null otherwise). A variable
     * product shows its lowest price, and its sale is that variation's.
     *
     * @return array{price:float,regular:float|null,percent:int|null}
     */
    public static function prices(\WC_Product $product): array
    {
        if ($product instanceof \WC_Product_Variable) {
            $prices = $product->get_variation_prices(true);
            $price  = (array) ($prices['price'] ?? []);
            if ([] === $price) {
                return ['price' => 0.0, 'regular' => null, 'percent' => null];
            }
            asort($price);
            $cheapest = (int) array_key_first($price);
            $now      = (float) $price[$cheapest];
            $regular  = (float) ($prices['regular_price'][$cheapest] ?? $now);

            return $regular > $now && $regular > 0
                ? ['price' => $now, 'regular' => $regular, 'percent' => (int) floor(($regular - $now) / $regular * 100)]
                : ['price' => $now, 'regular' => null, 'percent' => null];
        }
        $now = (float) wc_get_price_to_display($product);
        if (! $product->is_on_sale()) {
            return ['price' => $now, 'regular' => null, 'percent' => null];
        }
        $regular = (float) wc_get_price_to_display($product, ['price' => $product->get_regular_price()]);
        $saving  = ProductPage::saving($product);

        return $regular > $now
            ? ['price' => $now, 'regular' => $regular, 'percent' => (int) ($saving['percent'] ?? floor(($regular - $now) / $regular * 100))]
            : ['price' => $now, 'regular' => null, 'percent' => null];
    }

    /** The one tag: the first of the product's badges in D-19's precedence, or nothing. */
    private static function tag(\WC_Product $product): string
    {
        $badges = ProductFields::badgesOf($product);
        $names  = ProductFields::badges();
        if ([] === $badges || ! isset($names[$badges[0]])) {
            return '';
        }

        return self::el('span', ['class' => 'mizzey-corner-tag'], esc_html($names[$badges[0]]));
    }

    /** The photo, lazy, at WordPress's automatic sizes; the loading well under it; or the image glyph. */
    private static function image(\WC_Product $product): string
    {
        $imageId = (int) $product->get_image_id();
        $html    = $imageId > 0
            ? wp_get_attachment_image($imageId, self::IMAGE_SIZE, false, [
                'class'    => 'mizzey-card__image',
                'alt'      => (string) get_post_meta($imageId, '_wp_attachment_image_alt', true),
                'loading'  => 'lazy',
                'decoding' => 'async',
            ])
            : '';
        if ('' === $html) {
            return self::el('span', ['class' => 'mizzey-card__noimage'], self::icon('image', 32));
        }
        $zz = function_exists('MizzeySite\Theme\Components\zz') ? \MizzeySite\Theme\Components\zz() : '';

        return self::el('span', ['class' => 'mizzey-card__loading', 'aria-hidden' => 'true'], $zz) . $html;
    }

    /** The rating line: star, average, review count, and a variable product's number of options. */
    private static function meta(\WC_Product $product, bool $variable): string
    {
        $count = (int) $product->get_review_count();
        $parts = '';
        if ($count > 0) {
            $average = number_format((float) $product->get_average_rating(), 1);
            $parts  .= self::el('span', ['class' => 'mizzey-card__rating', 'aria-hidden' => 'true'],
                self::icon('star', 14, 'mizzey-card__star')
                . self::el('span', ['class' => 'mizzey-card__average'], esc_html($average))
                . self::el('span', ['class' => 'mizzey-card__count'], esc_html('(' . $count . ')'))
            );
            $parts .= self::el('span', ['class' => 'mizzey-visually-hidden'], esc_html(sprintf(
                /* translators: 1: the average rating, for example 4.7, 2: the number of reviews. */
                _n('Rated %1$s out of 5, %2$d review', 'Rated %1$s out of 5, %2$d reviews', $count, 'mizzey-site'),
                $average,
                $count
            )));
        }
        if ($variable) {
            $options = count($product->get_children());
            if ($count > 0) {
                $parts .= self::el('span', ['class' => 'mizzey-card__dot', 'aria-hidden' => 'true'], '&middot;');
            }
            /* translators: %d: how many options (variations) a product has, shown on its card. */
            $parts .= self::el('span', ['class' => 'mizzey-card__options'], esc_html(sprintf(_n('%d option', '%d options', $options, 'mizzey-site'), $options)));
        }

        return self::el('p', ['class' => 'mizzey-card__meta'], $parts);
    }

    private static function saleTag(int $percent): string
    {
        if (function_exists('MizzeySite\Theme\Components\sale_tag')) {
            return \MizzeySite\Theme\Components\sale_tag($percent);
        }

        /* translators: %d: the discount in percent, shown as a tag beside a sale price, for example "-20%". */
        return self::el('span', ['class' => 'mizzey-sale-tag', 'dir' => 'ltr'], esc_html(sprintf(__('-%d%%', 'mizzey-site'), $percent)));
    }

    private static function money(float $amount): string
    {
        return wp_kses(wc_price($amount), ['span' => ['class' => true], 'bdi' => []]);
    }

    /** The i-dot loader of slice 1, shown in the disc while the server has not answered. */
    private static function loader(): string
    {
        $dots = function_exists('MizzeySite\Theme\Components\loader') ? \MizzeySite\Theme\Components\loader(['label' => __('Adding', 'mizzey-site')]) : '';

        return self::el('span', ['class' => 'mizzey-card__loader'], $dots);
    }

    private static function icon(string $name, int $size = 24, string $class = ''): string
    {
        return function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => $size, 'class' => $class]) : '';
    }

    /**
     * The options of a variable product, chosen in the card: a sheet on a phone, a popover on a desktop
     * (C1-Category-Phone frame 5, C1-Category-Desktop). Options are the product's own attributes, whatever they are;
     * an option no in-stock variation has is marked sold out and cannot be chosen.
     *
     * @param array<string,mixed> $context The card's context; the picker's data is added to it.
     */
    private static function picker(\WC_Product_Variable $product, array &$context): string
    {
        $attributes = [];
        foreach ($product->get_variation_attributes() as $name => $options) {
            $key   = 'attribute_' . sanitize_title((string) $name);
            $terms = taxonomy_exists((string) $name) ? wc_get_product_terms($product->get_id(), (string) $name, ['fields' => 'all']) : [];
            $list  = [];
            foreach ((array) $options as $value) {
                $label = (string) $value;
                foreach ($terms as $term) {
                    if ($term->slug === $value) {
                        $label = $term->name;
                    }
                }
                $list[] = ['value' => (string) $value, 'label' => $label];
            }
            $attributes[] = ['key' => $key, 'name' => (string) $name, 'label' => wc_attribute_label((string) $name, $product), 'options' => $list];
        }
        $variations = [];
        foreach ($product->get_children() as $childId) {
            $variation = wc_get_product($childId);
            if (! $variation instanceof \WC_Product_Variation || ! $variation->variation_is_visible()) {
                continue;
            }
            $variations[] = [
                'id'    => $variation->get_id(),
                'attrs' => $variation->get_variation_attributes(),
                'in'    => $variation->is_in_stock() && $variation->is_purchasable(),
                'price' => wp_strip_all_tags(wc_price((float) wc_get_price_to_display($variation))),
            ];
        }
        // The first variation that can be bought is chosen to start with.
        $chosen = [];
        foreach ($variations as $variation) {
            if ($variation['in']) {
                foreach ($attributes as $attribute) {
                    $chosen[$attribute['key']] = (string) ($variation['attrs'][$attribute['key']] ?? '') ?: (string) ($attribute['options'][0]['value'] ?? '');
                }
                break;
            }
        }
        $id                  = 'mizzey-card-options-' . $product->get_id() . '-' . wp_unique_id();
        $context['picker']   = ['attributes' => $attributes, 'variations' => $variations, 'chosen' => $chosen, 'qty' => 1];
        $context['pickerId'] = $id;

        $groups = '';
        foreach ($attributes as $attribute) {
            $buttons     = '';
            $chosenLabel = '';
            foreach ($attribute['options'] as $option) {
                $checked = ($chosen[$attribute['key']] ?? '') === $option['value'];
                if ($checked) {
                    $chosenLabel = $option['label'];
                }
                $buttons .= self::el('button', [
                    'type'              => 'button',
                    'role'              => 'radio',
                    'class'             => 'mizzey-option',
                    'aria-checked'      => $checked ? 'true' : 'false',
                    'aria-disabled'     => self::available($variations, $chosen, $attribute['key'], $option['value']) ? 'false' : 'true',
                    'data-key'          => $attribute['key'],
                    'data-value'        => $option['value'],
                    'data-wp-on--click' => 'actions.choose',
                ], self::el('span', ['class' => 'mizzey-option__label'], esc_html($option['label'])) . self::el('span', ['class' => 'mizzey-option__note'], esc_html__('Sold out', 'mizzey-site')));
            }
            /* translators: %s: the name of a product option, for example "Flavour", followed by the chosen value. */
            $legend  = self::el('span', ['class' => 'mizzey-card-picker__name'], esc_html(sprintf(__('%s:', 'mizzey-site'), $attribute['label'])));
            $legend .= ' ' . self::el('span', ['class' => 'mizzey-card-picker__chosen', 'data-key' => $attribute['key']], esc_html($chosenLabel));
            $groups .= self::el('div', ['class' => 'mizzey-card-picker__group', 'role' => 'radiogroup', 'aria-label' => $attribute['label']],
                self::el('p', ['class' => 'mizzey-card-picker__legend'], $legend) . self::el('div', ['class' => 'mizzey-card-picker__options'], $buttons));
        }
        $price = '';
        foreach ($variations as $variation) {
            if (self::matches($variation, $chosen)) {
                $price = $variation['price'];
            }
        }
        $thumb = (int) $product->get_image_id() > 0
            ? wp_get_attachment_image((int) $product->get_image_id(), 'woocommerce_gallery_thumbnail', false, ['alt' => '', 'loading' => 'lazy', 'class' => 'mizzey-card-picker__image'])
            : self::icon('image', 24);
        $step  = static fn (int $by, string $label, string $icon): string => self::el('button', ['type' => 'button', 'class' => 'mizzey-stepper__button', 'aria-label' => $label, 'data-step' => (string) $by, 'data-wp-on--click' => 'actions.step'], self::icon($icon, 20));
        $stepper = self::el('div', ['class' => 'mizzey-stepper', 'role' => 'group', 'aria-label' => __('Quantity', 'mizzey-site')],
            $step(-1, __('Decrease quantity', 'mizzey-site'), 'minus')
            . self::el('span', ['class' => 'mizzey-stepper__value', 'aria-live' => 'polite'], '1')
            . $step(1, __('Increase quantity', 'mizzey-site'), 'plus'));
        $add = self::el('button', ['type' => 'button', 'class' => ['mizzey-button', 'mizzey-button--primary', 'mizzey-button--compact', 'mizzey-card-picker__add'], 'data-wp-on--click' => 'actions.addChosen'], esc_html__('Add to cart', 'mizzey-site'));

        // The card's own controls live in the card's store inside slice 1's overlay, whose store closes it on a press
        // on the scrim; showModal() keeps focus inside and closes on Escape.
        $body = self::el('div', ['class' => 'mizzey-card-picker__content', 'data-wp-interactive' => self::STORE],
            self::el('div', ['class' => 'mizzey-card-picker__product'],
                self::el('span', ['class' => 'mizzey-card-picker__thumb'], $thumb)
                . self::el('span', ['class' => 'mizzey-card-picker__summary'], self::el('span', ['class' => 'mizzey-card-picker__title'], esc_html($product->get_name())) . self::el('span', ['class' => 'mizzey-card-picker__price'], esc_html($price))))
            . $groups);
        $footer = self::el('div', ['class' => 'mizzey-card-picker__actions', 'data-wp-interactive' => self::STORE], $stepper . $add);
        $title  = __('Choose options', 'mizzey-site');

        if (function_exists('MizzeySite\Theme\Components\overlay')) {
            $dialog = \MizzeySite\Theme\Components\overlay(['id' => $id, 'kind' => 'sheet', 'title' => $title, 'body' => $body, 'footer' => $footer]);

            // The picker's own class, beside the overlay's: the popover form and the card's spacing hang on it.
            $tags = new \WP_HTML_Tag_Processor($dialog);
            if ($tags->next_tag('dialog')) {
                $tags->add_class('mizzey-card-picker');
            }

            return $tags->get_updated_html();
        }

        // Without the theme's components (the site plugin on another theme): the same overlay, written here.
        $close = self::el('button', ['type' => 'button', 'class' => ['mizzey-button', 'mizzey-button--icon'], 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.close'], self::icon('close'));
        $panel = self::el('span', ['class' => 'mizzey-overlay__handle', 'aria-hidden' => 'true'])
            . self::el('div', ['class' => 'mizzey-overlay__header'], self::el('h2', ['class' => 'mizzey-overlay__title', 'id' => $id . '-title'], esc_html($title)) . $close)
            . self::el('div', ['class' => 'mizzey-overlay__body'], $body)
            . self::el('div', ['class' => 'mizzey-overlay__footer'], $footer);

        return self::el('dialog', [
            'id'                      => $id,
            'class'                   => ['mizzey-card-picker', 'mizzey-overlay', 'mizzey-overlay--sheet'],
            'aria-labelledby'         => $id . '-title',
            'data-wp-interactive'     => 'mizzey/ui',
            'data-wp-on--pointerdown' => 'actions.scrimDown',
            'data-wp-on--click'       => 'actions.backdrop',
        ], self::el('div', ['class' => 'mizzey-overlay__panel'], $panel));
    }

    /**
     * Whether a value of one option can be bought together with every other option as chosen: with Red chosen, L is
     * sold out when no Red/L variation is in stock.
     *
     * @param list<array<string,mixed>> $variations
     * @param array<string,string>      $chosen
     */
    private static function available(array $variations, array $chosen, string $key, string $value): bool
    {
        $want       = $chosen;
        $want[$key] = $value;
        foreach ($variations as $variation) {
            if ($variation['in'] && self::matches($variation, $want)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a variation fits the values wanted (an empty value on the variation means any).
     *
     * @param array<string,mixed>  $variation
     * @param array<string,string> $want
     */
    private static function matches(array $variation, array $want): bool
    {
        foreach ($want as $key => $value) {
            $has = (string) ($variation['attrs'][$key] ?? '');
            if ('' !== $has && $has !== $value) {
                return false;
            }
        }

        return true;
    }

    private static function canonical(int $productId): int
    {
        $canonical = CanonicalProduct::of($productId);

        return $canonical instanceof \WC_Product ? $canonical->get_id() : $productId;
    }

    /** @return list<int> */
    private static function wished(): array
    {
        if (null === self::$wished) {
            self::$wished = Wishlist::ids();
        }

        return self::$wished;
    }

    /** What every card on the page shares: the addresses, the nonces and the words the script shows. */
    private static function state(): void
    {
        if (self::$footer) {
            return;
        }
        self::$footer = true;
        wp_interactivity_state(self::STORE, [
            'cartApi'     => esc_url_raw(rest_url('wc/store/v1/cart/add-item')),
            'storeNonce'  => wp_create_nonce('wc_store_api'),
            'wishlistApi' => esc_url_raw(rest_url(WishlistRoutes::NS . '/wishlist/items')),
            'restNonce'   => wp_create_nonce('wp_rest'),
            'words'       => [
                'failed'     => __('This could not be added. Please try again.', 'mizzey-site'),
                'wishFailed' => __('Your wishlist could not be changed. Please try again.', 'mizzey-site'),
            ],
        ]);
        add_action('wp_footer', [self::class, 'toasts']);
    }

    /** Where the card's notices appear, and the two notices it clones: added to the cart, and an error. */
    public static function toasts(): void
    {
        $region = self::el('div', ['class' => 'mizzey-card-toasts', 'aria-live' => 'polite']);
        if (! function_exists('MizzeySite\Theme\Components\notice')) {
            echo $region; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by el().
            return;
        }
        $added = \MizzeySite\Theme\Components\notice([
            'type'   => 'cart',
            'text'   => __('Added to your cart', 'mizzey-site'),
            'action' => ['label' => __('View cart', 'mizzey-site'), 'url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/')],
            'life'   => true,
        ]);
        $error = \MizzeySite\Theme\Components\notice(['type' => 'error', 'text' => __('This could not be added. Please try again.', 'mizzey-site'), 'life' => true]);
        // The notices are cloned by the card's script, which wires their close control: the slice 1 store is left off.
        $strip = static fn (string $html): string => (string) preg_replace('/\sdata-wp-interactive="[^"]*"/', '', $html, 1);
        echo $region // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by el() and notice().
            . self::el('template', ['id' => 'mizzey-card-toast-added'], $strip($added))
            . self::el('template', ['id' => 'mizzey-card-toast-error'], $strip($error));
    }
}

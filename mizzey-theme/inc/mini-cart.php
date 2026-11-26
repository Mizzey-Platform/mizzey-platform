<?php

/**
 * The mini cart (spec 066, slice 12), as C1-Mini-Cart draws it: from 1024 wide a drawer out of the end edge (the start
 * edge in Arabic), full height, square on that edge and 28 on the two corners facing the page; below 1024 a bottom
 * sheet whose top edge is 56 from the top of the screen, rounding its two top corners only. It opens only on a press
 * on the header's cart control, never by itself after an add.
 *
 * It shows what WooCommerce's cart holds, read from WooCommerce's Store API (the cart the cart page reads, so its
 * lines and subtotal are the cart page's): each line with its image, name, option and price, the quantity stepper and
 * remove; the free-delivery line from what specs/018 puts on the Store API cart (`extensions.mizzey.free_shipping`,
 * CART-07), in the board's two forms only ("Add EGP 530 more for free delivery" and "You have free delivery"); the
 * subtotal; the cart and checkout buttons. Every word and price format is the board's. While the cart loads it shows the loading rows; an empty
 * cart shows the line icon in a disc. A quantity change or a removal shows the i-dot loader and changes nothing until
 * the server answers.
 *
 * Native first (M-4): WooCommerce 11.1's own Mini-Cart block was weighed and not used. Its drawer is its own overlay
 * (not a modal dialog), has no bottom sheet, no free-delivery line and no loading rows, and its cart store changes
 * quantities and totals before the server answers (optimistic updates with a roll back), which C1-Motion-Playground's
 * rule forbids; its scripts (the mini-cart module, the cart store, the notices and products stores) are also over the
 * slice's 25 KB. What this builds on is WooCommerce's own cart through its Store API, with no other cart logic.
 *
 * The drawer's markup is server-rendered with the header, with its stylesheet; its script is imported by header.js on
 * the first press of the cart control (no page carries it up front);
 * the lines are filled by assets/js/mini-cart.js (store `mizzey/mini-cart`), the look is assets/css/mini-cart.css.
 * The cart and checkout pages keep the control as a plain link: the mini cart is not rendered there.
 *
 * Register ids: CART-12, NFR-11; the free-delivery line shows CART-07's data. See specs/066-stage-2-shop-front,
 * slice 12, and specs/018-cart.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\MiniCart;

use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Header\classes;
use function MizzeySite\Theme\Components\empty_state;
use function MizzeySite\Theme\Components\loader;
use function MizzeySite\Theme\Components\skeleton;
use function MizzeySite\Theme\Icons\icon;

defined('ABSPATH') || exit;

const ID     = 'mizzey-mini-cart';
const STORE  = 'mizzey/mini-cart';
const MODULE = 'mizzey-theme/mini-cart';
const STYLE  = 'mizzey-mini-cart';

add_action('init', __NAMESPACE__ . '\\register');

function register(): void
{
    $script = get_theme_file_path('assets/js/mini-cart.js');
    wp_register_script_module(
        MODULE,
        get_theme_file_uri('assets/js/mini-cart.js'),
        [['id' => '@wordpress/interactivity', 'import' => 'static']],
        is_file($script) ? (string) filemtime($script) : false
    );
    $style = get_theme_file_path('assets/css/mini-cart.css');
    wp_register_style(STYLE, get_theme_file_uri('assets/css/mini-cart.css'), ['mizzey-components'], is_file($style) ? (string) filemtime($style) : null);
}

/** Whether this page carries the mini cart: WooCommerce's cart exists, and this is not the cart or checkout page. */
function wanted(): bool
{
    if (! function_exists('WC') || ! function_exists('wc_get_cart_url') || is_admin()) {
        return false;
    }

    return ! ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout()));
}

/**
 * The attributes that make the header's cart control open the mini cart. The control stays a link to the cart page,
 * which is where it leads before the script runs.
 *
 * @return array<string, string>
 */
function control_attributes(): array
{
    if (! wanted()) {
        return [];
    }

    return [
        // header.js opens it: it imports the mini cart's script on the first press (not with the page).
        'aria-haspopup' => 'dialog',
        'aria-controls' => ID,
    ];
}

/**
 * Each plural form of a count, for the script to pick with Intl.PluralRules (as the header's cart label does).
 *
 * @return array<string, string>
 */
function plural_forms(callable $form): array
{
    $out = [];
    foreach (['zero' => 0, 'one' => 1, 'two' => 2, 'few' => 3, 'many' => 11, 'other' => 100] as $category => $n) {
        $out[$category] = (string) $form($n);
    }

    return $out;
}

/** The words and addresses the script needs, in the page's language. */
function state(): array
{
    return [
        'rest'     => esc_url_raw(rest_url('wc/store/v1/')),
        'incTax'   => 'incl' === get_option('woocommerce_tax_display_cart'),
        'status'   => 'idle',
        'lines'    => [],
        'busyKey'  => '',
        'error'    => '',
        'subtotal' => ['pre' => '', 'num' => '', 'suf' => ''],
        'count'    => '',
        'words'    => [
            /* translators: %d: how many items the cart holds, beside the mini cart's title ("Your cart 4 items"). */
            'items'      => plural_forms(static fn (int $n): string => _nx('%d item', '%d items', $n, 'mini cart count', 'mizzey-site')),
            /* translators: %s: an amount of money, such as EGP 530. */
            'amount'     => __('Add %s more for free delivery', 'mizzey-site'),
            /* translators: %s: a product's name, the accessible name of the button that takes it out of the cart. */
            'remove'     => __('Remove %s', 'mizzey-site'),
            'reached'    => __('You have free delivery', 'mizzey-site'),
            'reachedAlt' => __('Free delivery reached', 'mizzey-site'),
            // Between a line's options ("24 oz, Unflavoured"; Arabic writes its own comma).
            'listSep'    => _x(', ', 'between the options of a cart line', 'mizzey-site'),
            /* translators: %d: a percentage, how far the cart is towards free delivery. */
            'percent'    => __('%d percent of the way to free delivery', 'mizzey-site'),
            'error'      => __('Something went wrong with your cart. Please try again.', 'mizzey-site'),
        ],
    ];
}

/** One loading row: the 64 tile, two name bars, the price bar and the stepper's pill (C1-Mini-Cart C). */
function loading_row(): string
{
    return element(
        'div',
        ['class' => classes('mizzey-minicart__line', 'mizzey-minicart__line--loading'), 'aria-hidden' => 'true'],
        element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__tile-skeleton')])
        . element(
            'span',
            ['class' => 'mizzey-minicart__main'],
            element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__bar', 'mizzey-minicart__bar--long')])
            . element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__bar', 'mizzey-minicart__bar--short')])
            . element('span', ['class' => 'mizzey-minicart__row'], element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__bar', 'mizzey-minicart__bar--price')]) . element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__stepper-skeleton')]))
        )
    );
}

/**
 * An amount of money as the board sets it: the symbol, the digits left to right in Inter, an Arabic symbol in
 * Tajawal ("EGP 5,200", "5,200 ج.م"). $path is the state or context path of the script's { pre, num, suf }.
 *
 * @param array<string, string|bool> $attrs
 */
function money(string $tag, string $class, string $path, array $attrs = []): string
{
    return element(
        $tag,
        ['class' => $class] + $attrs,
        element('span', ['class' => 'mizzey-minicart__money-symbol', 'data-wp-text' => $path . '.pre'])
        . element('bdi', ['class' => 'mizzey-minicart__money-digits', 'dir' => 'ltr', 'data-wp-text' => $path . '.num'])
        . element('span', ['class' => 'mizzey-minicart__money-symbol', 'data-wp-text' => $path . '.suf'])
    );
}

/** A line of the cart, as the template the script repeats for each line (data-wp-each). */
function line_template(): string
{
    $image = element(
        'a',
        ['class' => 'mizzey-minicart__tile', 'data-wp-bind--href' => 'context.line.url', 'tabindex' => '-1', 'aria-hidden' => 'true'],
        '<img alt="" width="48" height="48" loading="lazy" decoding="async" sizes="48px" data-wp-bind--src="context.line.image" data-wp-bind--srcset="context.line.srcset" data-wp-bind--hidden="!context.line.image">' . icon('image')
    );
    $remove = element(
        'button',
        [
            'type'                    => 'button',
            'class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-minicart__remove'),
            'aria-label'              => __('Remove', 'mizzey-site'),
            // Named for its product ("Remove" and the name), so a screen reader tells the lines apart.
            'data-wp-bind--aria-label' => 'context.line.removeLabel',
            // While a change is with the server every control waits (send() takes one change at a time); it keeps its
            // look, as the board's "Updating" stepper does.
            'data-wp-bind--aria-disabled' => 'state.lockedAttr',
            'data-wp-on--click'       => 'actions.remove',
        ],
        icon('close', ['size' => 18])
    );
    $step = static fn (string $class, string $label, string $icon, string $action, string $disabled): string => element(
        'button',
        ['type' => 'button', 'class' => 'mizzey-stepper__button ' . $class, 'aria-label' => $label, 'data-wp-on--click' => $action, 'data-wp-bind--disabled' => $disabled, 'data-wp-bind--aria-disabled' => 'state.lockedAttr'],
        icon($icon, ['size' => 22])
    );
    $stepper = element(
        'span',
        ['class' => 'mizzey-stepper', 'role' => 'group', 'aria-label' => __('Quantity', 'mizzey-site')],
        $step('mizzey-minicart__less', __('Decrease quantity', 'mizzey-site'), 'minus', 'actions.less', 'state.lessOff')
        . element(
            'span',
            ['class' => 'mizzey-stepper__value', 'aria-live' => 'polite'],
            element('span', ['class' => 'mizzey-minicart__qty', 'data-wp-text' => 'context.line.quantity', 'data-wp-bind--hidden' => 'state.lineBusy'])
            . element('span', ['class' => 'mizzey-minicart__updating', 'data-wp-bind--hidden' => '!state.lineBusy'], loader(['label' => __('Updating', 'mizzey-site')]))
        )
        . $step('mizzey-minicart__more', __('Increase quantity', 'mizzey-site'), 'plus', 'actions.more', 'state.moreOff')
    );

    $line = element(
        'li',
        ['class' => 'mizzey-minicart__line', 'data-wp-class--is-busy' => 'state.lineBusy'],
        $image
        . element(
            'span',
            ['class' => 'mizzey-minicart__main'],
            element('span', ['class' => 'mizzey-minicart__top'], element('a', ['class' => 'mizzey-minicart__name', 'data-wp-bind--href' => 'context.line.url', 'data-wp-text' => 'context.line.name']) . $remove)
            . element('span', ['class' => 'mizzey-minicart__option', 'data-wp-text' => 'context.line.option', 'data-wp-bind--hidden' => '!context.line.option'])
            . element('span', ['class' => 'mizzey-minicart__row'], money('span', 'mizzey-minicart__price', 'context.line.price') . $stepper)
        )
    );

    return '<template data-wp-each--line="state.lines" data-wp-each-key="context.line.key">' . $line . '</template>';
}

/** The free-delivery line: the message, the track, the van on the end of its filled part, the tick when reached. */
function delivery(): string
{
    $van = static fn (string $file, string $class): string => sprintf(
        '<img class="mizzey-minicart__van %s" src="%s" alt="" width="56" height="27" decoding="async">',
        esc_attr($class),
        esc_url(get_theme_file_uri('assets/images/' . $file))
    );

    return element(
        'div',
        ['class' => 'mizzey-minicart__delivery', 'hidden' => true, 'data-wp-bind--hidden' => '!state.deliveryShown', 'data-wp-class--is-reached' => 'state.deliveryReached'],
        element(
            'span',
            ['class' => 'mizzey-minicart__delivery-text'],
            element('span', ['data-wp-text' => 'state.deliveryBefore'])
            . money('strong', 'mizzey-minicart__delivery-amount', 'state.deliveryAmount')
            . element('span', ['data-wp-text' => 'state.deliveryAfter'])
        )
        . element(
            'span',
            ['class' => 'mizzey-minicart__track', 'role' => 'img', 'data-wp-bind--aria-label' => 'state.deliveryLabel'],
            element('span', ['class' => 'mizzey-minicart__rail']) . element('span', ['class' => 'mizzey-minicart__fill'])
            . $van('van.webp', 'mizzey-minicart__van--ltr') . $van('van-rtl.webp', 'mizzey-minicart__van--rtl')
            . element('span', ['class' => 'mizzey-minicart__tick'], icon('tick', ['size' => 12]))
        )
    );
}

/** The mini cart's markup, once per page, with its assets. Empty where the page does not carry it. */
function render(): string
{
    if (! wanted()) {
        return '';
    }
    wp_enqueue_style(STYLE);
    // The script is not enqueued: header.js imports it on the first press of the cart control (the import map names
    // it through the header module's dynamic dependency, functions.php), so no page carries it up front (D-21).
    wp_interactivity_state(STORE, state());

    $shop  = function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url('/');
    $title = element(
        'h2',
        ['class' => classes('mizzey-overlay__title', 'mizzey-minicart__title'), 'id' => ID . '-title', 'tabindex' => '-1'],
        esc_html__('Your cart', 'mizzey-site') . ' ' . element('span', ['class' => 'mizzey-minicart__count', 'data-wp-text' => 'state.count', 'data-wp-bind--hidden' => '!state.hasLines', 'hidden' => true])
    );
    $close = element(
        'button',
        ['type' => 'button', 'class' => classes('mizzey-button', 'mizzey-button--icon', 'mizzey-minicart__close'), 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'mizzey/ui::actions.close'],
        icon('close', ['size' => 20])
    );
    $loading = element(
        'div',
        ['class' => 'mizzey-minicart__loading', 'role' => 'status', 'aria-label' => __('Loading your cart', 'mizzey-site'), 'data-wp-bind--hidden' => '!state.loading'],
        loading_row() . loading_row() . loading_row()
    );
    $empty = element(
        'div',
        ['class' => 'mizzey-minicart__empty', 'hidden' => true, 'data-wp-bind--hidden' => '!state.empty'],
        empty_state([
            'icon'   => 'cart',
            'title'  => __('Your cart is empty', 'mizzey-site'),
            'text'   => __('Add something you like and it will wait for you here.', 'mizzey-site'),
            'level'  => 3,
            'action' => ['label' => __('Start shopping', 'mizzey-site'), 'url' => $shop],
        ])
    );
    $error = element('p', ['class' => 'mizzey-minicart__error', 'role' => 'alert', 'hidden' => true, 'data-wp-text' => 'state.error', 'data-wp-bind--hidden' => '!state.error']);
    $list  = element('ul', ['class' => 'mizzey-minicart__list', 'hidden' => true, 'data-wp-bind--hidden' => '!state.hasLines'], line_template());

    $button = static fn (string $class, string $variant, string $label, string $url): string => element(
        'a',
        ['class' => classes('mizzey-button', 'mizzey-button--' . $variant, $class), 'href' => esc_url($url), 'data-wp-bind--aria-disabled' => 'state.footOff', 'data-wp-bind--tabindex' => 'state.footTab'],
        element('span', ['class' => 'mizzey-button__label'], esc_html($label))
    );
    $foot = element(
        'div',
        ['class' => 'mizzey-minicart__foot', 'data-wp-bind--hidden' => 'state.empty'],
        element(
            'div',
            ['class' => 'mizzey-minicart__total'],
            element('span', ['class' => 'mizzey-minicart__total-label'], esc_html__('Subtotal', 'mizzey-site'))
            . money('span', 'mizzey-minicart__subtotal', 'state.subtotal', ['data-wp-bind--hidden' => '!state.hasLines', 'hidden' => true])
            . element('span', ['class' => classes('mizzey-skeleton', 'mizzey-minicart__subtotal-skeleton'), 'aria-hidden' => 'true', 'data-wp-bind--hidden' => 'state.hasLines'])
        )
        . element('p', ['class' => 'mizzey-minicart__note'], esc_html__('Delivery and any code are worked out in the cart.', 'mizzey-site'))
        . element(
            'div',
            ['class' => 'mizzey-minicart__actions'],
            $button('mizzey-minicart__view', 'secondary', __('View cart', 'mizzey-site'), wc_get_cart_url())
            . $button('mizzey-minicart__checkout', 'primary', __('Checkout', 'mizzey-site'), wc_get_checkout_url())
        )
    );

    $panel = element('span', ['class' => classes('mizzey-overlay__handle', 'mizzey-minicart__handle'), 'aria-hidden' => 'true'])
        . element('div', ['class' => 'mizzey-minicart__head'], $title . $close)
        . element('div', ['class' => 'mizzey-minicart__body', 'data-wp-bind--aria-busy' => 'state.busy'], $loading . $empty . $error . $list . delivery())
        . $foot;

    return element(
        'dialog',
        [
            'id'                      => ID,
            'class' => classes('mizzey-overlay', 'mizzey-minicart'),
            'aria-labelledby'         => ID . '-title',
            'data-state'              => 'idle',
            'data-wp-interactive'     => STORE,
            'data-wp-bind--data-state' => 'state.status',
            // A press that starts and ends on the scrim closes (the base overlay's behaviour, store mizzey/ui).
            'data-wp-on--pointerdown' => 'mizzey/ui::actions.scrimDown',
            'data-wp-on--click'       => 'mizzey/ui::actions.backdrop',
        ],
        element('div', ['class' => classes('mizzey-overlay__panel', 'mizzey-minicart__panel')], $panel)
    );
}

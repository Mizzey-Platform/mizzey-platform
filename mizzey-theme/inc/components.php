<?php

/**
 * Render helpers for the base components of slice 1, so markup that repeats is written once.
 *
 * The look is in assets/css/components.css and the behaviour in assets/js/ui.js (the WordPress Interactivity API,
 * store "mizzey/ui"). Every helper returns escaped HTML; every word a visitor reads is translatable in the
 * mizzey-site domain, and a caller's own text is escaped here, never trusted. Decorative parts are hidden from
 * assistive technology; a control that shows only an icon carries its name in aria-label.
 *
 * Register ids: FIX-04a, NFR-04, NFR-04a, NFR-11, MER-06. See specs/066-stage-2-shop-front, slice 1.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Components;

use function MizzeySite\Theme\Icons\icon;

defined('ABSPATH') || exit;

const STORE = 'mizzey/ui';

/* The ZZ mark, from the brand pack (final-pack/SVG/ZZ-White BG.svg), in its own two colours. */
const ZZ_INDIGO_A = 'M555.82,412.46c-.19.45-.38.92-.59,1.38-.6,1.35-1.26,2.73-1.96,4.07-.47.91-.98,1.79-1.49,2.68-.79,1.34-1.62,2.66-2.51,3.98-1.18,1.73-2.47,3.45-3.84,5.13-1.4,1.68-2.87,3.34-4.44,4.94-.6.62-1.22,1.25-1.86,1.91-40.46,40.68-153.49,131.3-153.49,131.3l61.51-8.7c25.64-3.64,50.31-4.2,76.13-4.39,4.17-.02,6.74,1.04,8.19,2.58.28.31.51.61.71.93.1.17.19.34.27.51.15.32.28.66.38,1.02v.01c.47,1.85.05,3.89-.76,5.49-8.6,17.07-17.3,33.28-20.73,52.08-.15.85-.3,1.72-.43,2.59-.08.39-.13.81-.19,1.21-.25,1.65-.59,2.96-1.08,3.98-.3.6-.66,1.11-1.05,1.54-5.11,5.43-18.73-3.15-46.22,3.3-23.56,5.55-44.96,14.58-67.32,23.96-41.29,17.31-97.86,37.99-139.83,27.1-4.9-1.28-9.37-3.15-13.35-5.52-2.28-1.36-4.41-2.89-6.38-4.57-.49-.41-.96-.85-1.43-1.28-.94-.89-1.83-1.79-2.7-2.74-3.43-3.81-6.18-8.16-8.18-12.97-.51-1.19-.97-2.42-1.36-3.66-1.66-5.13-2.51-10.68-2.47-16.53q0-1.16.05-2.32c.27-5.41,1.27-10.92,2.87-16.48q.09-.28.17-.57c.19-.68.42-1.36.64-2.03,1.59-4.98,3.64-9.96,6.06-14.91,15.94-32.76,47.19-63.49,61.47-75.07l99.29-82.17h-125.17c-14.94-.03-17.22-7.94-13.84-16.37l15.75-50.8c1.75-5.92,3.75-10.94,6.3-15.07.37-.6.75-1.2,1.15-1.77.41-.58.81-1.13,1.24-1.68.42-.53.87-1.04,1.32-1.55.4-.45.83-.88,1.27-1.3.09-.09.18-.19.3-.28.43-.4.87-.79,1.32-1.15.51-.43,1.04-.83,1.58-1.21.42-.28.83-.56,1.27-.83.02-.02.03-.04.07-.05.32-.19.64-.38.98-.55.15-.1.29-.17.44-.23.34-.21.69-.38,1.05-.55.44-.22.89-.41,1.36-.6.92-.39,1.89-.73,2.89-1.04.49-.15,1-.3,1.51-.43.52-.13,1.09-.26,1.66-.38.45-.09.92-.19,1.39-.26.53-.11,1.08-.19,1.64-.27.32-.03.66-.09,1-.13,1.12-.13,2.29-.22,3.49-.28.49-.04,1-.06,1.51-.06.66-.02,1.34-.03,2.04-.03l145.05-.42c45.73,0,75.68-4.13,96.22,14.37,1.94,1.74,3.7,3.59,5.26,5.53,1.04,1.28,2,2.62,2.89,3.98.87,1.36,1.68,2.75,2.39,4.19,1.44,2.85,2.55,5.83,3.34,8.88q.88,3.45,1.25,7.02C560.65,395.26,559.29,403.99,555.82,412.46Z';
const ZZ_INDIGO_B = 'M863.29,566.39c3.1,7.03,6.73,13.76,10.77,20.16-51.9,24.03-95.82,44.76-142.45,61.36l-15.9,6.07c-41.46,16.86-97.85,37.96-139.82,27.09-22.46-5.86-36.03-24.33-35.89-47.26.06-6.16,1.1-12.46,2.93-18.83.07-.16.13-.36.17-.56.2-.67.43-1.34.63-2.04,1.6-4.96,3.67-9.96,6.07-14.89,15.93-32.77,47.19-63.5,61.49-75.06l99.29-82.19h-125.15c-4.44,0-7.24-4.77-5.17-8.73,6.97-13.37,10.4-27.83,9.93-42.37-.1-2.96-.36-5.86-.76-8.73-1.14-7.76.06-15.73,4.16-22.39,6-9.84,14.97-14.64,30.5-14.64l145.05-.43c45.72,0,75.69-4.13,96.22,14.37,11,9.89,15.96,23.13,15.33,36.86-.7,13.86-7.17,28.33-19,40.46-4.46,4.57-9.96,9.86-16.16,15.63-47.83,44.33-139.22,117.59-139.22,117.59,14.6-1.9,28.16-3.84,40.83-5.77,46.29-7.13,80.32-14.56,107.09-22.26C856.43,548.82,859.46,557.72,863.29,566.39Z';
const ZZ_PERSIMMON = 'M200.86,659.42c3.94,30.1,26.25,57.05,55.67,65.18,29.56,8.78,61,.83,89-10.04,36.31-13.33,69.88-33.27,106.74-45.37,34-10.51,58.55-5.42,77.87,26.1,48.08,67.73,155.97,18.14,215.2-9.69,34.74-16.85,68.64-35.31,103.55-51.95.7-.34,1.54-.04,1.87.66.33.71.04,1.52-.64,1.89-22.84,15.25-55.07,36.78-77.94,51.07-50.55,30.82-107.86,64.29-168.42,67.76-36.31,1.92-71.76-10.82-94.25-40.56,0,0-2.21-2.98-2.21-2.98-10.57-16.31-17.91-21.96-37.88-18.01-16.89,3.32-33.13,10.61-49.46,17.2-21.66,9.11-43.76,18.36-66.35,25.38-24.36,7.81-49.9,13.09-75.62,9.96-35.85-4-67.08-26.88-77.03-62.13-2.16-7.97-3.36-16.14-2.89-24.36.03-.77.69-1.37,1.47-1.33C200.23,658.23,200.78,658.76,200.86,659.42H200.86Z';

/**
 * An attribute string from a map. A null or false value drops the attribute, true writes it bare.
 *
 * @param array<string, string|int|bool|null> $attributes
 */
function attributes(array $attributes): string
{
    $out = '';
    foreach ($attributes as $name => $value) {
        if (null === $value || false === $value) {
            continue;
        }
        $out .= true === $value
            ? ' ' . esc_attr((string) $name)
            : sprintf(' %s="%s"', esc_attr((string) $name), esc_attr((string) $value));
    }
    return $out;
}

/**
 * One element.
 *
 * @param array<string, string|int|bool|null> $attributes
 */
function element(string $tag, array $attributes, string $inner = ''): string
{
    return sprintf('<%1$s%2$s>%3$s</%1$s>', $tag, attributes($attributes), $inner);
}

/** One i-dot, the brand's dot shape, in the colour of the text around it. */
function idot(string $class): string
{
    return sprintf(
        '<svg class="%s" viewBox="680 406.75 73.5 63.25" fill="currentColor" focusable="false" aria-hidden="true"><path d="%s"/></svg>',
        esc_attr($class),
        'M692.62,406.81h48.33c8.47,0,14.5,8.22,11.96,16.3l-11.92,38c-1.64,5.23-6.49,8.79-11.97,8.79h-24.75c-5.52,0-10.38-3.6-12-8.87l-11.65-38C678.15,414.97,684.18,406.81,692.62,406.81Z'
    );
}

/** The three i-dots, the middle one upside down. */
function loader(array $args = []): string
{
    $dot = idot('mizzey-loader__dot');
    $label = (string) ($args['label'] ?? __('Loading', 'mizzey-site'));
    return element(
        'span',
        [
            'class'      => 'mizzey-loader' . (empty($args['still']) ? '' : ' mizzey-loader--still'),
            'role'       => 'img',
            'aria-label' => $label,
        ],
        $dot . $dot . $dot
    );
}

/**
 * A button or a link that looks like one.
 *
 * @param array{label: string, variant?: string, size?: string, icon?: string, url?: string, busy?: bool,
 *              busy_label?: string, done?: bool, disabled?: bool, block?: bool, count?: int|null,
 *              type?: string, attrs?: array<string, string|int|bool|null>} $args
 */
function button(array $args): string
{
    $label   = (string) ($args['label'] ?? '');
    $variant = (string) ($args['variant'] ?? 'primary');
    $size    = (string) ($args['size'] ?? 'regular');
    $classes = ['mizzey-button', 'mizzey-button--' . $variant];
    if ('regular' !== $size) {
        $classes[] = 'mizzey-button--' . $size;
    }
    if (! empty($args['block'])) {
        $classes[] = 'mizzey-button--block';
    }
    if (! empty($args['done'])) {
        $classes[] = 'is-done';
    }

    $attributes = ['class' => implode(' ', $classes)];
    $busy       = ! empty($args['busy']);
    $disabled   = $busy || ! empty($args['disabled']);

    if ('icon' === $variant) {
        $inner                    = icon((string) ($args['icon'] ?? ''));
        $attributes['aria-label'] = $label;
        if (isset($args['count'])) {
            $inner .= counter((int) $args['count']);
            /* translators: 1: a button's name, such as Cart; 2: its count in words, such as "3 items". */
            $attributes['aria-label'] = sprintf(__('%1$s, %2$s', 'mizzey-site'), $label, count_words((int) $args['count']));
        }
    } elseif ($busy) {
        $inner = loader(['label' => __('Working', 'mizzey-site')])
            . element('span', ['class' => 'mizzey-button__label'], esc_html((string) ($args['busy_label'] ?? $label)));
    } elseif (! empty($args['done'])) {
        $inner = '<svg class="mizzey-button__tick" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>'
            . element('span', ['class' => 'mizzey-button__label'], esc_html($label));
    } else {
        $inner = (isset($args['icon']) ? icon((string) $args['icon'], ['size' => 20]) : '')
            . element('span', ['class' => 'mizzey-button__label'], esc_html($label));
        if (isset($args['count'])) {
            $inner .= counter((int) $args['count'])
                . element('span', ['class' => 'mizzey-visually-hidden'], esc_html(count_words((int) $args['count'])));
        }
    }

    $attributes += (array) ($args['attrs'] ?? []);
    if (isset($args['url']) && ! $disabled) {
        return element('a', ['href' => esc_url((string) $args['url'])] + $attributes, $inner);
    }
    return element(
        'button',
        ['type' => (string) ($args['type'] ?? 'button'), 'disabled' => $disabled, 'aria-busy' => $busy ? 'true' : null] + $attributes,
        $inner
    );
}

/** A count in words, for assistive technology: the badge itself is decoration. */
function count_words(int $number): string
{
    $number = max(0, $number);
    /* translators: %d: how many, read after a button's name: cart items, chosen filters. */
    return sprintf(_n('%d item', '%d items', $number, 'mizzey-site'), $number);
}

/** The Persimmon count: cart items, active filters. */
function counter(int $number): string
{
    /* translators: shown on a count badge when the number is over 99. */
    $text = $number > 99 ? __('99+', 'mizzey-site') : (string) max(0, $number);
    return element('span', ['class' => 'mizzey-count', 'aria-hidden' => 'true'], esc_html($text));
}

/** A badge: ink (Out of stock), sale (Save 15%) or tint (Limited, New, Trending, Best Seller). */
function badge(string $label, string $tone = 'tint'): string
{
    return element('span', ['class' => 'mizzey-badge mizzey-badge--' . $tone], esc_html($label));
}

/** The one merchandising tag in a card's top start corner. */
function corner_tag(string $label): string
{
    return element('span', ['class' => 'mizzey-corner-tag'], esc_html($label));
}

/** The orange sale tag in the price line, Inter in both languages. */
function sale_tag(int $percent): string
{
    /* translators: %d: the discount in percent, shown as a tag beside a sale price, for example "-20%". */
    $text = sprintf(__('-%d%%', 'mizzey-site'), $percent);
    return element('span', ['class' => 'mizzey-sale-tag', 'dir' => 'ltr'], esc_html($text));
}

/** The gallery's "1 / 4". */
function gallery_counter(int $current, int $total): string
{
    /* translators: 1: the photo shown, 2: the number of photos. */
    $text = sprintf(__('%1$d / %2$d', 'mizzey-site'), $current, $total);
    return element('span', ['class' => 'mizzey-gallery-counter'], esc_html($text));
}

/**
 * A chip: a filter, the chosen one, an applied filter with its remove control, or a category with its icon.
 *
 * @param array{label: string, count?: int, url?: string, chosen?: bool, applied?: bool, image?: string,
 *              icon?: string, attrs?: array<string, string|int|bool|null>} $args
 */
function chip(array $args): string
{
    $label   = (string) $args['label'];
    $classes = ['mizzey-chip'];
    $inner   = '';
    if (! empty($args['image'])) {
        $classes[] = 'mizzey-chip--category';
        $inner    .= sprintf('<img src="%s" alt="" width="24" height="24" loading="lazy" decoding="async">', esc_url((string) $args['image']));
    } elseif (! empty($args['icon'])) {
        $classes[] = 'mizzey-chip--category';
        $inner    .= icon((string) $args['icon'], ['size' => 20]);
    }
    $inner .= esc_html($label);
    if (isset($args['count'])) {
        $inner .= element('span', ['class' => 'mizzey-chip__count'], esc_html((string) (int) $args['count']));
    }

    $attributes = (array) ($args['attrs'] ?? []);
    if (! empty($args['applied'])) {
        $classes[] = 'mizzey-chip--applied';
        $inner    .= icon('close', ['size' => 16]);
        /* translators: %s: the name of an applied filter, for example a brand. */
        $attributes['aria-label'] = sprintf(__('Remove %s', 'mizzey-site'), $label);
    }
    if (! empty($args['chosen'])) {
        $classes[] = 'mizzey-chip--chosen';
    }
    $attributes = ['class' => implode(' ', $classes)] + $attributes;

    if (isset($args['url'])) {
        $attributes['aria-current'] = empty($args['chosen']) ? null : 'true';
        return element('a', ['href' => esc_url((string) $args['url'])] + $attributes, $inner);
    }
    $attributes['aria-pressed'] = empty($args['applied']) ? (empty($args['chosen']) ? 'false' : 'true') : null;
    return element('button', ['type' => 'button'] + $attributes, $inner);
}

/**
 * A field: the label above, the box, the line, and one reserved line for the helper or the error.
 *
 * @param array{id: string, label: string, name?: string, type?: string, value?: string, placeholder?: string,
 *              helper?: string, error?: string, ok?: bool, disabled?: bool, multiline?: bool,
 *              autocomplete?: string, inputmode?: string, required?: bool} $args
 */
function field(array $args): string
{
    $id      = (string) $args['id'];
    $error   = (string) ($args['error'] ?? '');
    $ok      = ! empty($args['ok']) && '' === $error;
    $classes = ['mizzey-field'];
    if ('' !== $error) {
        $classes[] = 'is-error';
    }
    if ($ok) {
        $classes[] = 'is-ok';
    }
    if ('' !== $error || $ok) {
        $classes[] = 'mizzey-field--status';
    }

    $control = [
        'class'            => 'mizzey-field__control',
        'id'               => $id,
        'name'             => (string) ($args['name'] ?? $id),
        'placeholder'      => isset($args['placeholder']) ? (string) $args['placeholder'] : null,
        'autocomplete'     => $args['autocomplete'] ?? null,
        'inputmode'        => $args['inputmode'] ?? null,
        // Numbers, addresses and codes read left to right in both languages.
        'dir'              => in_array($args['type'] ?? 'text', ['tel', 'email', 'url'], true) ? 'ltr' : null,
        'required'         => ! empty($args['required']),
        'disabled'         => ! empty($args['disabled']),
        'aria-invalid'     => '' === $error ? null : 'true',
        'aria-describedby' => $id . '-message',
    ];
    $input = ! empty($args['multiline'])
        ? element('textarea', $control, esc_html((string) ($args['value'] ?? '')))
        : sprintf('<input%s>', attributes(['type' => (string) ($args['type'] ?? 'text'), 'value' => (string) ($args['value'] ?? '')] + $control));

    $status = '';
    if ('' !== $error || $ok) {
        $status = element('span', ['class' => 'mizzey-field__status', 'aria-hidden' => 'true'], icon('' !== $error ? 'alert' : 'check'));
    }

    if ('' !== $error) {
        $message = element('span', ['class' => 'mizzey-field__message', 'id' => $id . '-message', 'role' => 'alert'], esc_html($error));
    } elseif (isset($args['helper'])) {
        $message = element('span', ['class' => 'mizzey-field__helper', 'id' => $id . '-message'], esc_html((string) $args['helper']));
    } else {
        $message = element('span', ['class' => 'mizzey-field__message', 'id' => $id . '-message']);
    }

    return element(
        'div',
        ['class' => implode(' ', $classes)],
        element('label', ['class' => 'mizzey-field__label', 'for' => $id], esc_html((string) $args['label']))
        . element('span', ['class' => 'mizzey-field__box'], $input . $status . element('span', ['class' => 'mizzey-field__line', 'aria-hidden' => 'true']))
        . $message
    );
}

/**
 * The select: our own list in the select-only combobox pattern, a hidden input carrying the value.
 *
 * @param array{id: string, label: string, name?: string, options: array<string, string>, selected?: string} $args
 */
function select(array $args): string
{
    $id       = (string) $args['id'];
    $options  = (array) $args['options'];
    $selected = (string) ($args['selected'] ?? array_key_first($options));
    $items    = '';
    $index    = 0;
    foreach ($options as $value => $text) {
        $chosen = (string) $value === $selected;
        $items .= element(
            'li',
            [
                'id'            => $id . '-option-' . $index++,
                'class'         => 'mizzey-select__option',
                'role'          => 'option',
                'aria-selected' => $chosen ? 'true' : 'false',
                'data-value'    => (string) $value,
                'data-label'    => (string) $text,
            ],
            esc_html((string) $text) . icon('check', ['size' => 20])
        );
    }

    $button = element(
        'button',
        [
            'type'               => 'button',
            'id'                 => $id,
            'class'              => 'mizzey-select__button',
            'role'               => 'combobox',
            'aria-haspopup'      => 'listbox',
            'aria-expanded'      => 'false',
            'aria-controls'      => $id . '-list',
            'aria-labelledby'    => implode(' ', [$id . '-label', $id]),
            'data-wp-on--click'  => 'actions.selectToggle',
            'data-wp-on--keydown' => 'actions.selectKey',
        ],
        element('span', ['class' => 'mizzey-select__value'], esc_html((string) ($options[$selected] ?? '')))
    );

    return element(
        'div',
        [
            'class'                       => 'mizzey-field mizzey-select',
            'data-wp-interactive'         => STORE,
            'data-wp-on-document--click'  => 'actions.selectOutside',
        ],
        element('span', ['class' => 'mizzey-field__label', 'id' => $id . '-label'], esc_html((string) $args['label']))
        . element(
            'span',
            ['class' => 'mizzey-field__box'],
            $button
            . element('span', ['class' => 'mizzey-select__chevron', 'aria-hidden' => 'true'], icon('down'))
            . element('span', ['class' => 'mizzey-field__line', 'aria-hidden' => 'true'])
            . element(
                'ul',
                [
                    'id'                => $id . '-list',
                    'class'             => 'mizzey-select__list',
                    'role'              => 'listbox',
                    'aria-labelledby'   => $id . '-label',
                    'tabindex'          => '-1',
                    'hidden'            => true,
                    'data-wp-on--click' => 'actions.selectChoose',
                ],
                $items
            )
        )
        . sprintf('<input%s>', attributes(['type' => 'hidden', 'name' => (string) ($args['name'] ?? $id), 'value' => $selected]))
        . element('span', ['class' => 'mizzey-field__message', 'aria-hidden' => 'true'])
    );
}

/**
 * A checkbox row (filters, consent): the box, the label, an optional count.
 *
 * @param array{name: string, label: string, value?: string, count?: int, checked?: bool, disabled?: bool} $args
 */
function check(array $args): string
{
    $box = sprintf('<input%s>', attributes([
        'type'     => 'checkbox',
        'class'    => 'mizzey-check__box',
        'name'     => (string) $args['name'],
        'value'    => (string) ($args['value'] ?? '1'),
        'checked'  => ! empty($args['checked']),
        'disabled' => ! empty($args['disabled']),
    ]));
    $count = isset($args['count']) ? element('span', ['class' => 'mizzey-check__count'], esc_html((string) (int) $args['count'])) : '';
    return element(
        'label',
        ['class' => 'mizzey-check'],
        $box . element('span', ['class' => 'mizzey-check__tick', 'aria-hidden' => 'true'], icon('tick', ['size' => 20]))
        . element('span', [], esc_html((string) $args['label'])) . $count
    );
}

/**
 * A radio row, with an optional hint line.
 *
 * @param array{name: string, label: string, value: string, hint?: string, checked?: bool, disabled?: bool} $args
 */
function radio(array $args): string
{
    $dot = sprintf('<input%s>', attributes([
        'type'     => 'radio',
        'class'    => 'mizzey-radio__dot',
        'name'     => (string) $args['name'],
        'value'    => (string) $args['value'],
        'checked'  => ! empty($args['checked']),
        'disabled' => ! empty($args['disabled']),
    ]));
    $hint = isset($args['hint']) ? element('span', ['class' => 'mizzey-radio__hint'], esc_html((string) $args['hint'])) : '';
    return element(
        'label',
        ['class' => 'mizzey-radio'],
        $dot . element('span', ['class' => 'mizzey-radio__text'], element('span', [], esc_html((string) $args['label'])) . $hint)
    );
}

/**
 * A switch, 52 by 32.
 *
 * @param array{label: string, checked?: bool, disabled?: bool, id?: string} $args
 */
function switch_control(array $args): string
{
    return element(
        'button',
        [
            'type'                => 'button',
            'id'                  => $args['id'] ?? null,
            'class'               => 'mizzey-switch',
            'role'                => 'switch',
            'aria-checked'        => empty($args['checked']) ? 'false' : 'true',
            'disabled'            => ! empty($args['disabled']),
            'data-wp-interactive' => STORE,
            'data-wp-on--click'   => 'actions.toggle',
        ],
        element('span', [], esc_html((string) $args['label']))
        . element('span', ['class' => 'mizzey-switch__track', 'aria-hidden' => 'true'], element('span', ['class' => 'mizzey-switch__thumb']))
    );
}

/**
 * A notice: added to cart, success, information, warning or error.
 *
 * @param array{type?: string, text: string, action?: array{label: string, url: string}, close?: bool,
 *              life?: bool} $args
 */
function notice(array $args): string
{
    $type  = (string) ($args['type'] ?? 'info');
    $icons = ['cart' => 'cart', 'success' => 'check', 'info' => 'help', 'warning' => 'alert', 'error' => 'alert'];
    $inner = element('span', ['class' => 'mizzey-notice__icon', 'aria-hidden' => 'true'], icon($icons[$type] ?? 'help', ['size' => 20]))
        . element('span', ['class' => 'mizzey-notice__text'], esc_html((string) $args['text']));
    if (isset($args['action'])) {
        $url = esc_url((string) $args['action']['url']);
        if ('' !== $url) {
            $inner .= element('a', ['class' => 'mizzey-notice__action', 'href' => $url], esc_html((string) $args['action']['label']));
        }
    }
    if ($args['close'] ?? true) {
        $inner .= element(
            'button',
            ['type' => 'button', 'class' => 'mizzey-notice__close', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.dismiss'],
            icon('close', ['size' => 18])
        );
    }
    if (! empty($args['life'])) {
        $inner .= element('span', ['class' => 'mizzey-notice__life', 'aria-hidden' => 'true']);
    }
    return element(
        'div',
        [
            'class'               => 'mizzey-notice mizzey-notice--' . ('cart' === $type ? 'info' : $type),
            'role'                => 'error' === $type ? 'alert' : 'status',
            'data-wp-interactive' => STORE,
        ],
        $inner
    );
}

/**
 * The promo strip's look (its slider is slice 3).
 *
 * @param array{text: string, link?: array{label: string, url: string}} $args
 */
function strip(array $args): string
{
    $link = isset($args['link']) ? element('a', ['href' => esc_url((string) $args['link']['url'])], esc_html((string) $args['link']['label'])) : '';
    return element('div', ['class' => 'mizzey-strip'], element('span', [], esc_html((string) $args['text'])) . $link);
}

/**
 * The store-wide notice strip, information or warning.
 *
 * @param array{type?: string, text: string, link?: array{label: string, url: string}, close?: bool} $args
 */
function sitewide(array $args): string
{
    $warning = 'warning' === ($args['type'] ?? 'info');
    $inner   = icon($warning ? 'alert' : 'help', ['size' => 20])
        . element('span', ['class' => 'mizzey-notice__text'], esc_html((string) $args['text']));
    if (isset($args['link'])) {
        $inner .= element('a', ['class' => 'mizzey-notice__action', 'href' => esc_url((string) $args['link']['url'])], esc_html((string) $args['link']['label']));
    }
    if ($args['close'] ?? true) {
        $inner .= element(
            'button',
            ['type' => 'button', 'class' => 'mizzey-notice__close', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.dismiss'],
            icon('close', ['size' => 18])
        );
    }
    return element(
        'div',
        ['class' => 'mizzey-sitewide' . ($warning ? ' mizzey-sitewide--warning' : ''), 'role' => 'status', 'data-wp-interactive' => STORE],
        $inner
    );
}

/** A skeleton block. $inline is a percentage of the slot; $block a space or control token name. */
function skeleton(int $inline = 100, string $block = 'space-3', bool $tile = false): string
{
    $inline = max(1, min(100, $inline));
    $block  = preg_match('/^(space|control)-[a-z0-9-]+$/', $block) ? $block : 'space-3';
    return element('span', [
        'class'       => 'mizzey-skeleton' . ($tile ? ' mizzey-skeleton--block' : ''),
        'style'       => sprintf('--mizzey-skeleton-inline:%d%%;--mizzey-skeleton-block:var(--mizzey-%s)', $inline, $block),
        'aria-hidden' => 'true',
    ]);
}

/** The ZZ mark in the supplied colours, never recoloured. */
function zz(): string
{
    $paths = '';
    foreach ([['mizzey-zz__indigo', ZZ_INDIGO_A], ['mizzey-zz__indigo', ZZ_INDIGO_B], ['mizzey-zz__persimmon', ZZ_PERSIMMON]] as [$class, $d]) {
        $paths .= sprintf('<path class="%s" d="%s"/>', $class, $d);
    }
    return sprintf('<svg class="mizzey-zz" viewBox="0 0 1080 1080" focusable="false" aria-hidden="true">%s</svg>', $paths);
}

/** An image well while its photo loads: the tint and the breathing ZZ. */
function loading_well(): string
{
    return element('span', ['class' => 'mizzey-well', 'role' => 'img', 'aria-label' => __('Loading', 'mizzey-site')], zz());
}

/**
 * An empty state: a line icon in a 96 disc, a title, one sentence and at most one action.
 *
 * @param array{icon: string, title: string, text: string, action?: array<string, mixed>, level?: int} $args
 */
function empty_state(array $args): string
{
    $level = in_array($args['level'] ?? 2, [2, 3], true) ? (int) ($args['level'] ?? 2) : 2;
    return element(
        'div',
        ['class' => 'mizzey-empty'],
        element('span', ['class' => 'mizzey-empty__disc', 'aria-hidden' => 'true'], icon((string) $args['icon'], ['size' => 40]))
        . element('h' . $level, ['class' => 'mizzey-empty__title'], esc_html((string) $args['title']))
        . element('p', ['class' => 'mizzey-empty__text'], esc_html((string) $args['text']))
        . (isset($args['action']) ? button((array) $args['action']) : '')
    );
}

/**
 * Pagination: Previous, the first page, the pages around the current one, the last page, Next.
 *
 * @param callable(int): string $url
 */
function pagination(int $current, int $total, callable $url): string
{
    $total   = max(1, $total);
    $current = max(1, min($total, $current));
    if ($total <= 7) {
        $pages = range(1, $total);
    } elseif ($current <= 3) {
        $pages = [1, 2, 3, 4, $total];
    } elseif ($current >= $total - 2) {
        $pages = [1, $total - 3, $total - 2, $total - 1, $total];
    } else {
        $pages = [1, $current - 1, $current, $current + 1, $total];
    }

    $edge = static function (string $icon, string $label, ?int $page) use ($url): string {
        $attributes = ['class' => 'mizzey-pagination__item', 'aria-label' => $label];
        if (null === $page) {
            return element('a', $attributes + ['role' => 'link', 'aria-disabled' => 'true'], icon($icon, ['size' => 20]));
        }
        return element('a', ['href' => esc_url($url($page))] + $attributes, icon($icon, ['size' => 20]));
    };

    $items    = $edge('back', __('Previous page', 'mizzey-site'), $current > 1 ? $current - 1 : null);
    $previous = 0;
    foreach ($pages as $page) {
        if ($page - $previous > 1) {
            $items .= element('span', ['class' => 'mizzey-pagination__gap', 'aria-hidden' => 'true'], esc_html('...'));
        }
        $items .= element('a', [
            'class'        => 'mizzey-pagination__item',
            'href'         => esc_url($url($page)),
            /* translators: %d: a page number in a list of results. */
            'aria-label'   => sprintf(__('Page %d', 'mizzey-site'), $page),
            'aria-current' => $page === $current ? 'page' : null,
        ], esc_html((string) $page));
        $previous = $page;
    }
    $items .= $edge('fwd', __('Next page', 'mizzey-site'), $current < $total ? $current + 1 : null);

    return element('nav', ['class' => 'mizzey-pagination', 'aria-label' => __('Pages', 'mizzey-site')], $items);
}

/**
 * The attributes a control needs to open an overlay.
 *
 * @return array<string, string>
 */
function opens(string $id): array
{
    return [
        'data-wp-interactive' => STORE,
        'data-wp-context'     => (string) wp_json_encode(['target' => $id]),
        'data-wp-on--click'   => 'actions.open',
        'aria-haspopup'       => 'dialog',
        'aria-controls'       => $id,
    ];
}

/**
 * An overlay: a side drawer (from the end or the start edge), a bottom sheet, or a dialog. A native modal dialog,
 * opened by a control carrying opens($id).
 *
 * @param array{id: string, kind: string, title: string, body: string, footer?: string, close?: bool} $args
 *        body and footer are HTML the caller has already escaped.
 */
function overlay(array $args): string
{
    $id    = (string) $args['id'];
    $kind  = in_array($args['kind'], ['drawer-end', 'drawer-start', 'sheet', 'sheet-full', 'dialog'], true) ? (string) $args['kind'] : 'dialog';
    $class = 'mizzey-overlay mizzey-overlay--' . ('sheet-full' === $kind ? 'sheet mizzey-overlay--sheet-full' : $kind);
    $close = ($args['close'] ?? 'dialog' !== $kind)
        ? element(
            'button',
            ['type' => 'button', 'class' => 'mizzey-button mizzey-button--icon', 'aria-label' => __('Close', 'mizzey-site'), 'data-wp-on--click' => 'actions.close'],
            icon('close')
        )
        : '';
    $handle = str_starts_with($kind, 'sheet') ? element('span', ['class' => 'mizzey-overlay__handle', 'aria-hidden' => 'true']) : '';

    $panel = $handle
        . element('div', ['class' => 'mizzey-overlay__header'], element('h2', ['class' => 'mizzey-overlay__title', 'id' => $id . '-title'], esc_html((string) $args['title'])) . $close)
        . element('div', ['class' => 'mizzey-overlay__body'], (string) $args['body'])
        . (isset($args['footer']) ? element('div', ['class' => 'mizzey-overlay__footer'], (string) $args['footer']) : '');

    return element(
        'dialog',
        [
            'id'                  => $id,
            'class'               => $class,
            'aria-labelledby'     => $id . '-title',
            'data-wp-interactive' => STORE,
            'data-wp-on--pointerdown' => 'actions.scrimDown',
            'data-wp-on--click'       => 'actions.backdrop',
        ],
        element('div', ['class' => 'mizzey-overlay__panel'], $panel)
    );
}

/**
 * The steps line: done, current, to do.
 *
 * @param list<string> $labels
 */
function steps(array $labels, int $current): string
{
    $items = '';
    foreach (array_values($labels) as $i => $label) {
        $number = $i + 1;
        $state  = $number < $current ? 'is-done' : ($number === $current ? 'is-current' : 'is-todo');
        if ($i > 0) {
            $items .= element('li', ['class' => 'mizzey-steps__line' . ($number <= $current ? ' is-done' : ''), 'aria-hidden' => 'true']);
        }
        $dot = 'is-done' === $state ? icon('check', ['size' => 16]) : esc_html((string) $number);
        $done = 'is-done' === $state ? element('span', ['class' => 'mizzey-visually-hidden'], esc_html(__('completed', 'mizzey-site'))) : '';
        $items .= element(
            'li',
            ['class' => 'mizzey-steps__step ' . $state, 'aria-current' => 'is-current' === $state ? 'step' : null],
            element('span', ['class' => 'mizzey-steps__dot', 'aria-hidden' => 'true'], $dot) . esc_html($label) . $done
        );
    }
    // The host is the size container the narrow layout answers to (components.css).
    return element('div', ['class' => 'mizzey-steps-host'], element('ol', ['class' => 'mizzey-steps'], $items));
}

/**
 * A tooltip around a control that already has its accessible name; it shows on hover after 400 ms, at once on
 * keyboard focus, and Escape dismisses it.
 */
function tooltip(string $control_html, string $tip, string $placement = 'bottom'): string
{
    $placement = in_array($placement, ['bottom', 'top', 'start', 'end'], true) ? $placement : 'bottom';
    return element(
        'span',
        [
            'class'                 => 'mizzey-tooltip-host',
            'data-wp-interactive'   => STORE,
            'data-wp-on--keydown'   => 'actions.tipKey',
            'data-wp-on--mouseleave' => 'actions.tipReset',
            'data-wp-on--focusout'  => 'actions.tipReset',
        ],
        $control_html . element('span', ['class' => 'mizzey-tooltip' . ('bottom' === $placement ? '' : ' mizzey-tooltip--' . $placement), 'role' => 'tooltip', 'aria-hidden' => 'true'], esc_html($tip))
    );
}

/**
 * Tabs with their panels.
 *
 * @param list<array{label: string, panel: string}> $tabs panel is HTML the caller has already escaped.
 */
function tabs(string $id, array $tabs, int $selected = 0, string $label = ''): string
{
    $buttons = '';
    $panels  = '';
    foreach (array_values($tabs) as $i => $tab) {
        $on       = $i === $selected;
        $buttons .= element('button', [
            'type'                => 'button',
            'class'               => 'mizzey-tabs__tab',
            'role'                => 'tab',
            'id'                  => $id . '-tab-' . $i,
            'aria-controls'       => $id . '-panel-' . $i,
            'aria-selected'       => $on ? 'true' : 'false',
            'tabindex'            => $on ? '0' : '-1',
            'data-wp-on--click'   => 'actions.selectTab',
            'data-wp-on--keydown' => 'actions.tabKey',
        ], esc_html($tab['label']));
        $panels .= element('div', [
            'class'           => 'mizzey-tabs__panel',
            'role'            => 'tabpanel',
            'id'              => $id . '-panel-' . $i,
            'aria-labelledby' => $id . '-tab-' . $i,
            'tabindex'        => '0',
            'hidden'          => ! $on,
        ], (string) $tab['panel']);
    }
    return element(
        'div',
        ['class' => 'mizzey-tabs', 'data-wp-interactive' => STORE],
        element('div', [
            'class'      => 'mizzey-tabs__list',
            'role'       => 'tablist',
            'aria-label' => '' === $label ? null : $label,
            'style'      => sprintf('--mizzey-tabs-count:%d;--mizzey-tab-index:%d', count($tabs), $selected),
        ], $buttons . element('span', ['class' => 'mizzey-tabs__indicator', 'aria-hidden' => 'true'])) . $panels
    );
}

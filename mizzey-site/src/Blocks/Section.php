<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

use MizzeySite\Storefront\ProductCard;

/**
 * What the home page's sections share on the storefront (spec 066, slice 5): their wrapper, their words, their images
 * and their buttons. Every word comes from the editor, per language; every value a visitor reads is escaped here.
 *
 * Each section's root carries `data-mizzey-section` with its type, so the storefront, the editor's preview and the
 * tests read the same order the editor set.
 *
 * Register ids: SSC-01, SSC-02. See specs/066-stage-2-shop-front, slice 5.
 */
final class Section
{
    /**
     * The words of a field written in place (RichText): it stores markup with its text as entities, so the tags go and
     * the entities are decoded (TrustSignals::text). Escape the result where it is printed.
     */
    public static function text($value): string
    {
        return TrustSignals::text($value);
    }

    /**
     * The words of a text field in the sidebar (TextControl): stored raw, so every character is a character and "<" is
     * never the start of a tag ("Under <EGP 500" stays whole). Escape the result where it is printed.
     */
    public static function plain($value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * The words of a field that may keep a line break and bold words (a headline, a banner's line), as the boards draw
     * them; anything else is dropped.
     */
    public static function rich($value): string
    {
        $kept = wp_kses((string) $value, ['br' => [], 'strong' => []]);
        return '' === self::text(str_replace('<br>', ' ', $kept)) ? '' : trim($kept);
    }

    /**
     * The section's root attributes, through the block's own wrapper.
     *
     * @param list<string>                     $classes
     * @param array<string,string|bool|null>   $extra
     */
    public static function wrapper(string $type, array $classes, array $extra = []): string
    {
        $attributes = ['class' => implode(' ', array_merge(['mizzey-section'], $classes, ['alignfull'])), 'data-mizzey-section' => $type];
        foreach ($extra as $name => $value) {
            if (null !== $value && false !== $value && '' !== $value) {
                $attributes[$name] = true === $value ? $name : (string) $value;
            }
        }
        return get_block_wrapper_attributes($attributes);
    }

    /** One element, escaped (ProductCard::el). @param array<string,mixed> $attributes */
    public static function el(string $tag, array $attributes = [], string $inner = ''): string
    {
        return ProductCard::el($tag, $attributes, $inner);
    }

    /** A link that looks like a button, or nothing without both its words and its address. */
    public static function button(string $label, string $url, string $variant = 'primary', string $class = ''): string
    {
        $href = esc_url($url);
        if ('' === $label || '' === $href) {
            return '';
        }
        return self::el('a', ['class' => ['mizzey-button', 'mizzey-button--' . $variant, $class], 'href' => $href], self::el('span', ['class' => 'mizzey-button__label'], esc_html($label)));
    }

    /** A section heading row: the heading and, at the end, an optional link. */
    public static function head(string $heading, string $linkLabel = '', string $url = '', string $id = ''): string
    {
        $link = '' !== $linkLabel && '' !== esc_url($url)
            ? self::el('a', ['class' => 'mizzey-home__all', 'href' => esc_url($url)], esc_html($linkLabel))
            : '';
        if ('' === $heading && '' === $link) {
            return '';
        }
        return self::el('div', ['class' => 'mizzey-home__head'],
            ('' !== $heading ? self::el('h2', ['class' => 'mizzey-home__heading', 'id' => '' !== $id ? $id : null], esc_html($heading)) : '')
            . $link);
    }

    /**
     * An image from the media library, with its own dimensions, so the page does not move when it arrives.
     *
     * @param array<string,string|bool> $attributes
     */
    public static function image(int $id, string $alt, string $class, string $sizes, array $attributes = []): string
    {
        if ($id <= 0 || ! wp_attachment_is_image($id)) {
            return '';
        }
        return (string) wp_get_attachment_image($id, 'full', false, ['class' => $class, 'alt' => $alt, 'sizes' => $sizes, 'decoding' => 'async'] + $attributes);
    }

    /** The ZZ mark breathing in an image well while the photo loads, from the theme's components. */
    public static function well(): string
    {
        return function_exists('MizzeySite\Theme\Components\zz')
            ? self::el('span', ['class' => 'mizzey-home__well', 'aria-hidden' => 'true'], \MizzeySite\Theme\Components\zz())
            : '';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

/**
 * The trust signals section: the row under the hero that C1-Home-Phone, C1-Home-Phone-Arabic and
 * C1-Home-Desktop draw ("Authentic products, guaranteed", "Fast delivery", "Easy returns").
 *
 * Three items, as the boards draw. Each has a mark chosen from the three the boards draw (the authenticity shield,
 * the van, the returns arrow), a short title and a line, with an optional link on the line. Every word is the
 * editor's, per language: nothing a visitor reads is in this code (SSC-01). An item without a title is left out,
 * and a section with no item renders nothing, so an unfinished section leaves no broken region.
 *
 * The layout is fixed in the block's stylesheet: on a phone each item stacks its mark over its title; from 1024 wide
 * the items sit in one 72 high row with the line under the title, as the desktop board draws. The van is the theme's
 * artwork, its own mirrored drawing in Arabic (the logo on its side is never mirrored); the returns arrow mirrors.
 *
 * Register ids: SSC-01, as the proof of the section library pattern (slice 1). The trust signals' own row is
 * traced by slice 5, the home page sections. See specs/066-stage-2-shop-front.
 */
final class TrustSignals
{
    public const MARKS = ['shield', 'van', 'returns'];
    public const ITEMS = 3;

    /** @param array<string,mixed> $attributes */
    public static function render(array $attributes): string
    {
        $items = [];
        foreach (array_slice(array_values((array) ($attributes['items'] ?? [])), 0, self::ITEMS) as $item) {
            $item  = is_array($item) ? $item : [];
            $title = self::text($item['title'] ?? '');
            if ('' === $title) {
                continue;
            }
            $mark    = in_array($item['icon'] ?? '', self::MARKS, true) ? (string) $item['icon'] : self::MARKS[0];
            $items[] = sprintf(
                '<li class="mizzey-trust__item">%s<span class="mizzey-trust__text"><span class="mizzey-trust__title">%s</span>%s</span></li>',
                self::markHtml($mark, is_rtl()),
                esc_html($title),
                self::line(self::text($item['line'] ?? ''), (string) ($item['url'] ?? ''))
            );
        }
        if ([] === $items) {
            return '';
        }
        $label   = Section::plain($attributes['label'] ?? '');
        $wrapper = get_block_wrapper_attributes(
            ['class' => 'mizzey-section mizzey-trust alignfull'] + ('' === $label ? [] : ['aria-label' => $label])
        );
        return sprintf('<section %s><ul class="mizzey-trust__list">%s</ul></section>', $wrapper, implode('', $items));
    }

    /** One mark: a theme icon in its disc, or the van artwork on its own, as the boards draw them. */
    public static function markHtml(string $mark, bool $rtl): string
    {
        if ('van' === $mark) {
            $file = $rtl ? 'assets/images/van-rtl.webp' : 'assets/images/van.webp';
            if (! is_file(get_theme_file_path($file))) {
                return '<span class="mizzey-trust__mark mizzey-trust__mark--art" aria-hidden="true"></span>';
            }
            return sprintf(
                '<span class="mizzey-trust__mark mizzey-trust__mark--art" aria-hidden="true"><img class="mizzey-trust__van" src="%s" alt="" width="112" height="53" decoding="async"></span>',
                esc_url(get_theme_file_uri($file))
            );
        }
        $icon = function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($mark) : '';
        return '<span class="mizzey-trust__mark" aria-hidden="true">' . $icon . '</span>';
    }

    private static function line(string $line, string $url): string
    {
        if ('' === $line) {
            return '';
        }
        $href = esc_url($url);
        return '' === $href
            ? '<span class="mizzey-trust__line">' . esc_html($line) . '</span>'
            : sprintf('<a class="mizzey-trust__line" href="%s">%s</a>', $href, esc_html($line));
    }

    /**
     * The plain words of a field the editor wrote in place. The text field stores markup with its text as entities,
     * so tags go first and the entities are decoded after: "Save <50% today" is stored as "Save &lt;50% today" and
     * comes back whole, where decoding first would have read "<50% today" as a tag and dropped it.
     */
    public static function text($value): string
    {
        return trim(html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}

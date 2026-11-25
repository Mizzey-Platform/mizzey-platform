<?php

/**
 * The storefront icon set: inline SVG icons that take their colour from the text around them.
 *
 * Drawn on C1-System, section 10 (24 px, 2 px stroke, round caps and joins, the colour of their ground), plus the
 * few C1-Components adds (the filled heart, the tick that draws, the alert, the image glyph, the empty-state grid).
 * Every stroke and fill is `currentColor`. The two-tone icons keep their second colour through a class that
 * base.css points at a token: `mizzey-icon__knockout` (the ground showing through) and `mizzey-icon__accent` (the
 * interface orange, a fill only, on the Offers tag). The menu icon's third line is the logo's wave, and it is not
 * mirrored in Arabic, because the logo never is. The category icons on the same board are artwork images, not part
 * of this set.
 *
 * A decorative icon is hidden from assistive technology. Pass a translated `label` when the icon is the only
 * content of its control: it then has role="img" and that accessible name.
 *
 * Register ids: FIX-04a, NFR-04, NFR-11, MER-06. See specs/066-stage-2-shop-front, slice 1.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Icons;

defined('ABSPATH') || exit;

/**
 * The inner markup of every icon, keyed by name.
 *
 * @return array<string, string>
 */
function shapes(): array
{
    $grid  = '<rect x="4" y="4" width="6.5" height="6.5" rx="1.75"%1$s/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.75"%1$s/>'
        . '<rect x="4" y="13.5" width="6.5" height="6.5" rx="1.75"%1$s/><circle cx="16.75" cy="16.75" r="3.25"%1$s/>';

    return [
        'search'       => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'cart'         => '<path d="M3 4h2.2l2.3 10.2a1.5 1.5 0 0 0 1.46 1.17h7.9a1.5 1.5 0 0 0 1.45-1.12L20 8H6.1"/>'
            . '<circle cx="9.5" cy="19.5" r="1.25"/><circle cx="17" cy="19.5" r="1.25"/>',
        'heart'        => '<path d="M12 20s-7.5-4.4-7.5-10A4.3 4.3 0 0 1 12 7.2 4.3 4.3 0 0 1 19.5 10c0 5.6-7.5 10-7.5 10z"/>',
        'heart-filled' => '<path d="M12 20s-7.5-4.4-7.5-10A4.3 4.3 0 0 1 12 7.2 4.3 4.3 0 0 1 19.5 10c0 5.6-7.5 10-7.5 10z" fill="currentColor"/>',
        'user'         => '<circle cx="12" cy="8" r="3.75"/><path d="M4.5 20c.6-3.6 3.6-5.5 7.5-5.5s6.9 1.9 7.5 5.5"/>',
        'userf'        => '<circle cx="12" cy="8" r="3.75" fill="currentColor"/>'
            . '<path d="M4.5 20c.6-3.6 3.6-5.5 7.5-5.5s6.9 1.9 7.5 5.5z" fill="currentColor"/>',
        // The third line is the logo's wave.
        'menu'         => '<path d="M4 6.5h16"/><path d="M4 12h16"/>'
            . '<path d="M4 16.6C4.5 18.2 5.3 18.85 6.5 18.85C8.4 18.85 9.8 17.2 11.2 17.2C12.6 17.2 13.1 19 14.6 19C16.6 19 18.4 17.4 20 15.8"/>',
        'home'         => '<path d="M4 10.5 12 4l8 6.5V19a1 1 0 0 1-1 1h-4.5v-5.5h-5V20H5a1 1 0 0 1-1-1z"/>',
        'homef'        => '<path d="M4 10.5 12 4l8 6.5V19a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z" fill="currentColor"/>'
            . '<path class="mizzey-icon__knockout" d="M10 20v-4.5h4V20"/>',
        'grid'         => sprintf($grid, ''),
        'gridf'        => sprintf($grid, ' fill="currentColor"'),
        'grid-empty'   => '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/>'
            . '<rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
        'help'         => '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.6a2.5 2.5 0 1 1 3.6 2.2c-.8.5-1.2 1-1.2 1.9"/>'
            . '<path d="M12 16.6v.01"/>',
        'returns'      => '<path d="M8 5 4 9l4 4"/><path d="M4 9h10a5 5 0 0 1 0 10H9"/>',
        'shield'       => '<path d="M12 3.5 5 6v5.5c0 4.2 2.9 7.4 7 9 4.1-1.6 7-4.8 7-9V6z" fill="currentColor"/>'
            . '<path class="mizzey-icon__knockout" d="m9 11.8 2.2 2.2 3.8-4"/>',
        'cod'          => '<rect x="2.5" y="6.5" width="19" height="11" rx="2"/><circle cx="12" cy="12" r="2.5"/>'
            . '<path d="M6 10.5v3M18 10.5v3"/>',
        'card'         => '<rect x="2.5" y="5.5" width="19" height="13" rx="2.5"/><path d="M2.5 10h19M6.5 14.5h4"/>',
        'wallet'       => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M10.5 18h3"/>',
        'lock'         => '<rect x="5" y="10.5" width="14" height="9.5" rx="2.5"/><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>',
        'filter'       => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2" fill="currentColor"/>'
            . '<circle cx="9" cy="17" r="2" fill="currentColor"/>',
        'sort'         => '<path d="M7 5v14M4 16l3 3 3-3"/><path d="M17 19V5M14 8l3-3 3 3"/>',
        'close'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'back'         => '<path d="M14.5 6l-6 6 6 6"/>',
        'fwd'          => '<path d="M9.5 6l6 6-6 6"/>',
        'down'         => '<path d="M6 9.5l6 6 6-6"/>',
        'up'           => '<path d="M6 14.5l6-6 6 6"/>',
        'plus'         => '<path d="M12 5.5v13M5.5 12h13"/>',
        'minus'        => '<path d="M5.5 12h13"/>',
        'check'        => '<path d="m6 12.5 4 4 8-9"/>',
        'tick'         => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'alert'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5v.01"/>',
        'image'        => '<rect x="3.5" y="4.5" width="17" height="15" rx="3"/><circle cx="9" cy="10" r="1.75"/>'
            . '<path d="M20.5 16l-5-5-8.5 8.5"/>',
        'star'         => '<path d="M12 4l2.4 5.1 5.6.7-4.1 3.9 1 5.5L12 16.6 7.1 19.2l1-5.5L4 9.8l5.6-.7z" fill="currentColor"/>',
        'pin'          => '<path d="M12 20.5s6-5.2 6-10a6 6 0 0 0-12 0c0 4.8 6 10 6 10z"/><circle cx="12" cy="10.5" r="2"/>',
        'restart'      => '<path d="M5 12a7 7 0 1 0 2.2-5.1"/><path d="M5 4.5v3.9h3.9"/>',
        'share'        => '<circle cx="6.5" cy="12" r="2.5"/><circle cx="17" cy="6" r="2.5"/><circle cx="17" cy="18" r="2.5"/>'
            . '<path d="M8.7 10.8l6.1-3.5M8.7 13.2l6.1 3.5"/>',
        // The product page's share list and stock lines (spec 066, slice 9), as C1-Product-Phone draws them.
        'link'         => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'whatsapp'     => '<path d="M5 19l1.2-3.6A7.5 7.5 0 1 1 9 18.3z"/><path d="M9.5 9.5c0 2.5 2.5 5 5 5l1-1.5-2-1-1 .8a3.4 3.4 0 0 1-1.8-1.8l.8-1-1-2z"/>',
        'facebook'     => '<path d="M14.5 8.5H16V5.5h-2a3.5 3.5 0 0 0-3.5 3.5v2H8.5v3h2V20h3v-6h2.2l.5-3h-2.7V9.5a1 1 0 0 1 1-1z"/>',
        'x'            => '<path d="M5 5l14 14M19 5l-5.6 6.2M10.6 12.8 5 19"/>',
        'mail'         => '<rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.5v.01"/>',
        'unavailable'  => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'warning'      => '<path d="M12 3.5 21 19.5H3z"/><path d="M12 10v4.5"/><path d="M12 17v.01"/>',
        'play'         => '<path d="M9 7v10l8-5z" fill="currentColor"/>',
        'pause'        => '<rect x="7" y="6" width="3.5" height="12" rx="1.5" fill="currentColor" stroke="none"/>'
            . '<rect x="13.5" y="6" width="3.5" height="12" rx="1.5" fill="currentColor" stroke="none"/>',
        'trash'        => '<path d="M5 7h14"/><path d="M9.5 7V5h5v2"/><path d="M7 7l.8 12h8.4L17 7"/>',
        'tag'          => '<path class="mizzey-icon__accent" stroke="none" d="M21.2 13.6l-7.6 7.6a2 2 0 0 1-2.83 0L3 13.43V4.6A1.6 1.6 0 0 1 4.6 3h8.83l7.77 7.77a2 2 0 0 1 0 2.83z"/>'
            . '<circle class="mizzey-icon__knockout-fill" stroke="none" cx="8" cy="8" r="1.75"/>',
    ];
}

/**
 * Every icon name in the set.
 *
 * @return list<string>
 */
function names(): array
{
    return array_keys(shapes());
}

/**
 * One icon as inline SVG, or an empty string for a name the set does not have.
 *
 * @param array{label?: string, class?: string, size?: int} $args `label` is a translated accessible name (omit for
 *                                                                a decorative icon), `class` extra classes, `size`
 *                                                                the rendered size in px (24 by default).
 */
function icon(string $name, array $args = []): string
{
    $shapes = shapes();
    if (! isset($shapes[$name])) {
        return '';
    }

    $size       = isset($args['size']) ? max(1, (int) $args['size']) : 24;
    $class      = trim('mizzey-icon mizzey-icon--' . $name . ' ' . ($args['class'] ?? ''));
    $label      = isset($args['label']) ? trim((string) $args['label']) : '';
    $attributes = '' === $label
        ? ['aria-hidden' => 'true']
        : ['role' => 'img', 'aria-label' => $label];

    $pairs = '';
    foreach ($attributes as $key => $value) {
        $pairs .= sprintf(' %s="%s"', $key, esc_attr($value));
    }

    return sprintf(
        '<svg class="%1$s" viewBox="0 0 24 24" width="%2$d" height="%2$d" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"%3$s>%4$s</svg>',
        esc_attr($class),
        $size,
        $pairs,
        $shapes[$name]
    );
}

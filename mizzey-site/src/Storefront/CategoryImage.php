<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

/**
 * A category's picture, the one way every place shows it (spec 066; the owner's comments 7 and 8 of 26 November 2026).
 *
 * Each place has the box and the fit its board draws:
 *
 * | Place       | Where                                                   | Box (1440 / 390) | Fit     |
 * |-------------|---------------------------------------------------------|------------------|---------|
 * | `row`       | the mega menu's rows, the phone menu's rows             | 32 x 32          | contain |
 * | `chip`      | the phone header's category chips                       | 24 x 24          | contain |
 * | `tile`      | the phone's Categories tab                              | 76 x 76          | cover   |
 * | `mega`      | the mega menu panel's photo                             | 408 x 300        | cover   |
 * | `mega-wide` | the mega menu panel of a category with no children      | 640 x 200        | cover   |
 * | `banner`    | the phone menu's photo banner on a category's level     | 310 x 120        | cover   |
 * | `shortcut`  | the home page's category shortcuts                      | 138 / a quarter of the width | cover |
 * | `listing`   | the category page's banner tile                         | 128 / 88         | cover   |
 *
 * The picture is the whole uploaded image at its uncropped sizes (WordPress's own srcset: medium, medium large, large
 * and the bigger ones), never a small square crop. It is offered per screen density, 1x, 2x and 3x, each the smallest
 * of those files at least as wide as the picture is painted after the fit (a width-described srcset lets Chromium take
 * a smaller file than the density asks for, so it is not used). Where a place's box changes with the screen, a
 * `<picture>` gives each range its own set, for the widest box in that range. Nothing is upscaled unless the uploaded
 * file itself is smaller than the place needs. A category with no picture gets the product card's "No image" state
 * (C1-Components 10): the image glyph on the tint, in the same box, 32 in a large box and smaller in the 32 and 24
 * boxes so rows stay aligned. The look is in the theme's components.css and each place's own stylesheet.
 *
 * Register ids: NAV-07, NAV-08, NAV-10, HOME-05, PLP-06, MER-06. See specs/066-stage-2-shop-front.
 */
final class CategoryImage
{
    /**
     * Each place: its box on the smallest screen [width, height], the wider screens' boxes (a media query and the
     * widest box in its range, the widest range first), the fit, and the placeholder glyph's size. The shortcuts are
     * four to a row up to 1023 (16 margins and 12 gaps to 599, 24 margins to 1023) and eight from 1024 (138 at most).
     *
     * @var array<string,array{box:array{0:float,1:float},media?:list<array{0:string,1:array{0:float,1:float}}>,fit:string,glyph:int}>
     */
    public const PLACES = [
        'row'       => ['box' => [32, 32], 'fit' => 'contain', 'glyph' => 20],
        'chip'      => ['box' => [24, 24], 'fit' => 'contain', 'glyph' => 16],
        'tile'      => ['box' => [76, 76], 'fit' => 'cover', 'glyph' => 32],
        'mega'      => ['box' => [408, 300], 'fit' => 'cover', 'glyph' => 32],
        'mega-wide' => ['box' => [640, 200], 'fit' => 'cover', 'glyph' => 32],
        'banner'    => ['box' => [310, 120], 'fit' => 'cover', 'glyph' => 32],
        'shortcut'  => ['box' => [133, 133], 'media' => [['(min-width:1024px)', [138, 138]], ['(min-width:600px)', [235, 235]]], 'fit' => 'cover', 'glyph' => 32],
        // 88 on a phone, 128 from 1024: one set for the larger box costs a phone at most one file size.
        'listing'   => ['box' => [128, 128], 'fit' => 'cover', 'glyph' => 32],
    ];

    /** The screen densities offered. */
    private const DENSITIES = [1, 2, 3];

    /** The picture of a category, kept on the category in the store's main language (specs/054); 0 when it has none. */
    public static function id(int $termId): int
    {
        if ($termId <= 0) {
            return 0;
        }
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = '' !== $default ? (int) apply_filters('wpml_object_id', $termId, 'product_cat', true, $default) : 0;
        $image    = (int) get_term_meta($original > 0 ? $original : $termId, 'thumbnail_id', true);

        return $image > 0 && wp_attachment_is_image($image) ? $image : 0;
    }

    /**
     * The picture in its place, or the placeholder when there is none.
     *
     * @param array<string,string> $attrs Extra attributes for the picture: `class`, `alt` (decorative by default),
     *                                    `loading` and `fetchpriority`.
     */
    public static function html(int $attachment, string $place, array $attrs = []): string
    {
        if (! isset(self::PLACES[$place])) {
            return '';
        }
        $class = trim('mizzey-catimg ' . ($attrs['class'] ?? ''));
        $files = $attachment > 0 ? self::files($attachment) : [];
        if ([] === $files) {
            return self::placeholder($place, $class);
        }
        $spec  = self::PLACES[$place];
        $ratio = $files[count($files) - 1]['w'] / max(1, $files[count($files) - 1]['h']);
        $own   = self::densities($files, self::paint($place, $spec['box'], $ratio));
        $html  = [
            'class'         => $class,
            'data-place'    => $place,
            'src'           => esc_url($own[0]['url']),
            'srcset'        => count($own) > 1 ? esc_attr(self::srcset($own)) : null,
            'width'         => (string) (int) round($spec['box'][0]),
            'height'        => (string) (int) round($spec['box'][1]),
            'alt'           => esc_attr($attrs['alt'] ?? ''),
            'loading'       => esc_attr($attrs['loading'] ?? 'lazy'),
            'fetchpriority' => isset($attrs['fetchpriority']) ? esc_attr($attrs['fetchpriority']) : null,
            'decoding'      => 'async',
        ];
        $img = '<img';
        foreach ($html as $name => $value) {
            if (null !== $value) {
                $img .= sprintf(' %s="%s"', $name, $value);
            }
        }
        $img .= '>';
        if (empty($spec['media'])) {
            return $img;
        }
        $sources = '';
        foreach ($spec['media'] as [$query, $box]) {
            $sources .= sprintf('<source media="%s" srcset="%s">', esc_attr($query), esc_attr(self::srcset(self::densities($files, self::paint($place, $box, $ratio)))));
        }

        return '<picture class="mizzey-catimg-frame">' . $sources . $img . '</picture>';
    }

    /** A category with no picture: the image glyph on the tint, in the place's box (the owner's ruling, 26 Nov 2026). */
    public static function placeholder(string $place, string $class = 'mizzey-catimg'): string
    {
        $glyph = (int) (self::PLACES[$place]['glyph'] ?? 32);
        $icon  = function_exists('MizzeySite\\Theme\\Icons\\icon') ? \MizzeySite\Theme\Icons\icon('image', ['size' => $glyph]) : '';
        $class = str_contains($class, 'mizzey-catimg') ? $class : trim('mizzey-catimg ' . $class);

        return sprintf('<span class="%s is-empty" data-place="%s" aria-hidden="true">%s</span>', esc_attr($class), esc_attr($place), $icon);
    }

    /**
     * The width a picture of this shape is painted at in a box after the place's fit: cover paints it at least as wide
     * as the box, and wider when the picture is wider than the box; contain at most as wide.
     *
     * @param array{0:float,1:float} $box
     */
    public static function paint(string $place, array $box, float $ratio): float
    {
        $wide = $box[1] * $ratio;

        return 'cover' === self::PLACES[$place]['fit'] ? max($box[0], $wide) : min($box[0], $wide);
    }

    /**
     * For each density, the smallest file at least as wide as the paint needs; the largest file when none is. A file
     * that serves several densities is listed once, at the lowest.
     *
     * @param list<array{url:string,w:int,h:int}> $files Sorted by width.
     * @return list<array{url:string,w:int,h:int,x:int}>
     */
    public static function densities(array $files, float $paint): array
    {
        $out = [];
        foreach (self::DENSITIES as $x) {
            $pick = $files[count($files) - 1];
            foreach ($files as $file) {
                if ($file['w'] >= ceil($paint * $x)) {
                    $pick = $file;
                    break;
                }
            }
            if ([] === $out || $out[count($out) - 1]['url'] !== $pick['url']) {
                $out[] = $pick + ['x' => $x];
            }
        }

        return $out;
    }

    /** @param list<array{url:string,x:int}> $set */
    private static function srcset(array $set): string
    {
        return implode(', ', array_map(static fn (array $f): string => $f['url'] . ' ' . $f['x'] . 'x', $set));
    }

    /**
     * The attachment's files of the uploaded picture's own shape (WordPress's srcset candidates for the full size, so
     * never a crop of another shape), smallest first.
     *
     * @return list<array{url:string,w:int,h:int}>
     */
    private static function files(int $attachment): array
    {
        $full = wp_get_attachment_image_src($attachment, 'full');
        if (! is_array($full) || '' === (string) $full[0] || (int) $full[1] <= 0 || (int) $full[2] <= 0) {
            return [];
        }
        $ratio = (int) $full[1] / (int) $full[2];
        $files = [(string) $full[0] => ['url' => (string) $full[0], 'w' => (int) $full[1], 'h' => (int) $full[2]]];
        foreach (explode(',', (string) wp_get_attachment_image_srcset($attachment, 'full')) as $candidate) {
            if (1 === preg_match('/^\s*(\S+)\s+(\d+)w\s*$/', $candidate, $m)) {
                $files[$m[1]] = ['url' => $m[1], 'w' => (int) $m[2], 'h' => (int) round((int) $m[2] / $ratio)];
            }
        }
        $files = array_values($files);
        usort($files, static fn (array $a, array $b): int => $a['w'] <=> $b['w']);

        return $files;
    }
}

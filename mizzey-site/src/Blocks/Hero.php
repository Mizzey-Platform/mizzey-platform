<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

/**
 * The hero (HOME-01), in the two variants the boards draw: a single banner (C1-Options, "Desktop: one banner, no dots
 * and no progress line"; C1-Home-Phone) and a slider (C1-Hero-Banners, C1-Home-Desktop).
 *
 * Each slide is the editor's image, its ground (light, or Indigo), a headline, a line and a button, in the page's
 * language. On a phone the image sits above a white card that holds the words (C1-Home-Phone); from 1024 wide the
 * image fills a 440 high rounded banner and the words sit at its start side, on a soft white veil over a light image
 * or in white over an Indigo one, under the logo (C1-Hero-Banners). The logo is the site logo on a light slide and
 * the logo file chosen for Indigo grounds on an Indigo one, never recoloured.
 *
 * The slider moves as C1-Hero-Banners draws it: six seconds a slide at least, the next slide sliding in from the end
 * side with its words, the active dot the only orange, the Indigo line filling over the slide's time; arrows on a
 * desktop, a swipe on a phone, a pause button always. It pauses while it is touched, hovered or focused, and stays
 * still when the visitor asks for reduced motion. How it moves is kept in one place, the `MOTION` setting of view.js
 * and its `data-motion` class in style.css, so another of the options of C1-Slider-Options is a small change.
 *
 * The first slide's image is the page's Largest Contentful Paint: it is never lazy, carries its dimensions and a high
 * fetch priority, and is preloaded from the page's head when the hero is the page's first section (K-6). While it
 * loads, its well shows the breathing ZZ (the hero skeleton the boards draw).
 *
 * Register ids: HOME-01, HOME-10, SSC-02. See specs/066-stage-2-shop-front, slice 5.
 */
final class Hero
{
    public const VARIANTS     = ['single', 'slider'];
    public const TONES        = ['light', 'dark'];
    public const MIN_INTERVAL = 6;
    public const MAX_SLIDES   = 8;
    /** The image's sizes: the 1272 banner from 1024 wide, the screen's width below. */
    public const SIZES        = ['(min-width:1024px)', '1272px', '100vw'];
    public const BLOCK        = 'mizzey/hero';

    /** Whether a hero has already given the page its one main heading. */
    private static bool $headed = false;

    /** @param array<string,mixed> $attributes */
    public static function render(array $attributes): string
    {
        $slides = self::slides($attributes);
        if ([] === $slides) {
            return '';
        }
        $slider = 'slider' === ($attributes['variant'] ?? 'single') && count($slides) > 1;
        $slides = $slider ? $slides : [$slides[0]];
        $total  = count($slides);
        $logos  = ! empty($attributes['showLogo']) ? self::logos((int) ($attributes['darkLogo'] ?? 0)) : ['light' => '', 'dark' => ''];
        $track  = wp_unique_id('mizzey-hero-');
        $html   = '';
        foreach ($slides as $index => $slide) {
            $html .= self::slide($slide, $index, $total, $slider, $logos);
        }
        $label    = Section::plain($attributes['label'] ?? '');
        $interval = max(self::MIN_INTERVAL, (int) ($attributes['interval'] ?? self::MIN_INTERVAL));

        return sprintf(
            '<section %s><div class="mizzey-hero__viewport"><div class="mizzey-hero__track" id="%s">%s</div></div>%s</section>',
            Section::wrapper('hero', ['mizzey-hero', 'mizzey-hero--' . ($slider ? 'slider' : 'single')], [
                'aria-roledescription' => $slider ? __('carousel', 'mizzey-site') : null,
                'aria-label'           => '' !== $label ? $label : null,
                'data-interval'        => $slider ? (string) $interval : null,
                'data-motion'          => $slider ? 'slide' : null,
            ]),
            esc_attr($track),
            $html,
            $slider ? self::controls($track, $total) : ''
        );
    }

    /**
     * The slides that can be shown: those with a headline or an image.
     *
     * @param array<string,mixed> $attributes
     * @return list<array{imageId:int,imageAlt:string,tone:string,title:string,line:string,ctaLabel:string,ctaUrl:string}>
     */
    public static function slides(array $attributes): array
    {
        $out = [];
        foreach (array_slice(array_values((array) ($attributes['slides'] ?? [])), 0, self::MAX_SLIDES) as $slide) {
            $slide = is_array($slide) ? $slide : [];
            $row   = [
                'imageId'  => (int) ($slide['imageId'] ?? 0),
                'imageAlt' => Section::plain($slide['imageAlt'] ?? ''),
                'tone'     => in_array($slide['tone'] ?? '', self::TONES, true) ? (string) $slide['tone'] : 'light',
                'title'    => Section::rich($slide['title'] ?? ''),
                'line'     => Section::rich($slide['line'] ?? ''),
                'ctaLabel' => Section::text($slide['ctaLabel'] ?? ''),
                'ctaUrl'   => trim((string) ($slide['ctaUrl'] ?? '')),
            ];
            $hasImage = $row['imageId'] > 0 && wp_attachment_is_image($row['imageId']);
            if ('' !== $row['title'] || $hasImage) {
                $row['imageId'] = $hasImage ? $row['imageId'] : 0;
                $out[]          = $row;
            }
        }
        return $out;
    }

    /**
     * @param array{imageId:int,imageAlt:string,tone:string,title:string,line:string,ctaLabel:string,ctaUrl:string} $slide
     * @param array{light:string,dark:string} $logos
     */
    private static function slide(array $slide, int $index, int $total, bool $slider, array $logos): string
    {
        $first = 0 === $index;
        $image = Section::image($slide['imageId'], $slide['imageAlt'], 'mizzey-hero__image', self::sizes(), $first
            ? ['loading' => false, 'fetchpriority' => 'high']
            : ['loading' => 'lazy', 'fetchpriority' => 'low']);
        $tag   = $first && ! self::$headed ? 'h1' : 'h2';
        if ('h1' === $tag) {
            self::$headed = true;
        }
        $dark  = 'dark' === $slide['tone'];
        $words = ('' !== $logos[$slide['tone']] ? Section::el('span', ['class' => 'mizzey-hero__logo'], $logos[$slide['tone']]) : '')
            . ('' !== $slide['title'] ? Section::el($tag, ['class' => 'mizzey-hero__title'], $slide['title']) : '')
            . ('' !== $slide['line'] ? Section::el('p', ['class' => 'mizzey-hero__line'], $slide['line']) : '')
            . Section::button($slide['ctaLabel'], $slide['ctaUrl'], 'primary', 'mizzey-hero__button' . ($dark ? ' mizzey-hero__button--on-dark' : ''));

        return Section::el('div', [
            'class'                => ['mizzey-hero__slide', 'mizzey-hero__slide--' . $slide['tone'], $first ? 'is-active' : ''],
            'role'                 => $slider ? 'group' : null,
            'aria-roledescription' => $slider ? __('slide', 'mizzey-site') : null,
            /* translators: 1: the slide's number; 2: how many slides there are. */
            'aria-label'           => $slider ? sprintf(__('%1$d of %2$d', 'mizzey-site'), $index + 1, $total) : null,
            'data-index'           => (string) $index,
        ], Section::el('div', ['class' => 'mizzey-hero__media'], Section::well() . $image)
            . Section::el('div', ['class' => 'mizzey-hero__panel'], $words));
    }

    public static function sizes(): string
    {
        return vsprintf('%s %s, %s', self::SIZES);
    }

    /** The arrows, the pause button, the dots and the progress line under the slider. */
    private static function controls(string $track, int $total): string
    {
        $icon = static fn (string $name): string => function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => 20]) : '';
        $dots = '';
        for ($i = 0; $i < $total; $i++) {
            $dots .= Section::el('span', ['class' => ['mizzey-hero__dot', 0 === $i ? 'is-active' : '']]);
        }
        $arrow = static fn (string $name, string $label, int $step): string => Section::el('button', [
            'type' => 'button', 'class' => 'mizzey-hero__arrow', 'aria-label' => $label, 'aria-controls' => $track, 'data-step' => (string) $step,
        ], $icon($name));

        return Section::el('div', ['class' => 'mizzey-hero__controls'],
            $arrow('back', __('Previous slide', 'mizzey-site'), -1)
            . Section::el('div', ['class' => 'mizzey-hero__status'],
                Section::el('button', [
                    'type'          => 'button',
                    'class'         => 'mizzey-hero__pause',
                    'aria-label'    => __('Pause the slides', 'mizzey-site'),
                    'aria-controls' => $track,
                    'data-label-pause' => __('Pause the slides', 'mizzey-site'),
                    'data-label-play'  => __('Play the slides', 'mizzey-site'),
                ], $icon('pause') . $icon('play'))
                /* translators: 1: the slide shown; 2: how many slides there are. */
                . Section::el('span', ['class' => 'mizzey-hero__dots', 'role' => 'img', 'aria-label' => sprintf(__('Slide %1$d of %2$d', 'mizzey-site'), 1, $total), 'data-label' => __('Slide %1$d of %2$d', 'mizzey-site')], $dots)
                . Section::el('span', ['class' => 'mizzey-hero__progress', 'aria-hidden' => 'true'], Section::el('span', ['class' => 'mizzey-hero__progress-fill'])))
            . $arrow('fwd', __('Next slide', 'mizzey-site'), 1));
    }

    /**
     * The logo on a light slide (the site logo) and on an Indigo one (the file chosen for Indigo grounds).
     *
     * @return array{light:string,dark:string}
     */
    private static function logos(int $dark): array
    {
        $site = (int) get_theme_mod('custom_logo');
        $site = $site > 0 ? $site : (int) get_option('site_logo');
        $name = (string) get_bloginfo('name', 'display');
        $img  = static fn (int $id): string => $id > 0 && wp_attachment_is_image($id)
            ? (string) wp_get_attachment_image($id, 'medium', false, ['class' => 'mizzey-hero__logo-image', 'alt' => $name, 'loading' => 'lazy', 'decoding' => 'async'])
            : '';
        return ['light' => $img($site), 'dark' => $img($dark)];
    }

    /**
     * The preload of the first slide's image, printed in the head of the front page when a hero is its first shown
     * section, so the browser fetches the Largest Contentful Paint image before it reads the body.
     */
    public static function preload(): void
    {
        $tag = self::preloadTag();
        if ('' !== $tag) {
            echo $tag . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in preloadTag().
        }
    }

    public static function preloadTag(?int $postId = null): string
    {
        if (null === $postId) {
            if (! is_singular('page') && ! is_front_page()) {
                return '';
            }
            $postId = (int) get_queried_object_id();
        }
        if ($postId <= 0 || ! SectionLibrary::isFrontPage($postId)) {
            return '';
        }
        $content = (string) get_post_field('post_content', $postId, 'raw');
        if (is_preview() && current_user_can('edit_post', $postId)) {
            $preview = wp_get_post_autosave($postId, get_current_user_id());
            $content = $preview instanceof \WP_Post ? $preview->post_content : $content;
        }
        foreach (parse_blocks($content) as $block) {
            if (null === $block['blockName'] || false === ($block['attrs'][SectionLibrary::VISIBLE] ?? true)) {
                continue;
            }
            if (self::BLOCK !== $block['blockName']) {
                return '';
            }
            $slides = self::slides((array) $block['attrs']);
            $id     = $slides[0]['imageId'] ?? 0;
            if ($id <= 0) {
                return '';
            }
            $src    = wp_get_attachment_image_src($id, 'full');
            $srcset = (string) wp_get_attachment_image_srcset($id, 'full');
            if (! is_array($src)) {
                return '';
            }
            return sprintf(
                '<link rel="preload" as="image" href="%s"%s imagesizes="%s" fetchpriority="high">',
                esc_url((string) $src[0]),
                '' !== $srcset ? ' imagesrcset="' . esc_attr($srcset) . '"' : '',
                esc_attr(self::sizes())
            );
        }
        return '';
    }
}

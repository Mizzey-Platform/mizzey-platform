<?php

/**
 * The announcement strip above the header (spec 066, slice 3), in its two variants (G11, the owner's ruling of
 * 24 November 2026): a fixed strip with one message, as C1-Header-Footer-Styles and C1-Global draw it, and a slider
 * of up to three messages, as C1-Strip-Slider draws it.
 *
 * One dynamic block, `mizzey/announcement-strip`, placed first in the header template part (parts/header.html). Its
 * settings are edited on the block in the Site Editor (assets/js/site-parts-editor.js):
 *
 * - **variant**: `fixed` (the default, the board's recommendation at launch) or `slider`;
 * - **messages**: for each language, up to three messages, each with its words and an optional link (its words and
 *   address). The fixed strip shows the first; the slider shows each in turn.
 *
 * With no message in the page's language the strip is not printed and the header starts at the top (C1-Global B6).
 * A long message takes two lines, then ends in an ellipsis. The slider shows each message for
 * `--mizzey-motion-life-strip` (6 seconds), each sliding up into place; it pauses while the pointer is over it, while
 * it holds the keyboard focus and after a touch, and its pause control stops it. Under reduced motion it stands still
 * on the first message until the visitor presses play. A message that is not showing is inert, so the keyboard never
 * lands on a hidden link. Behaviour is in assets/js/site-parts.js (store `mizzey/site`); the look in
 * assets/css/strip.css.
 *
 * Register ids: SSC-01, SSC-12, NFR-11 (the strip's two variants are the section library's, G11). See
 * specs/066-stage-2-shop-front, slice 3.
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Strip;

use function MizzeySite\Theme\Components\element;
use function MizzeySite\Theme\Icons\icon;

defined('ABSPATH') || exit;

const BLOCK    = 'mizzey/announcement-strip';
const VARIANTS = ['fixed', 'slider'];
const MOST     = 3;

add_action('init', __NAMESPACE__ . '\\register');

function register(): void
{
    $style = get_theme_file_path('assets/css/strip.css');
    wp_register_style('mizzey-announcement-strip', get_theme_file_uri('assets/css/strip.css'), ['mizzey-components'], is_file($style) ? (string) filemtime($style) : null);

    register_block_type(BLOCK, [
        'api_version'          => 3,
        'title'                => __('Announcement strip', 'mizzey-site'),
        'category'             => 'theme',
        'icon'                 => 'megaphone',
        'description'          => __('The short message above the header, fixed or as a slider of up to three, in each language.', 'mizzey-site'),
        'attributes'           => [
            'variant'  => ['type' => 'string', 'enum' => VARIANTS, 'default' => 'fixed'],
            'messages' => ['type' => 'object', 'default' => []],
        ],
        'supports'             => ['html' => false, 'inserter' => false, 'multiple' => false, 'reusable' => false, 'className' => false, 'customClassName' => false],
        'editor_script'        => \MizzeySite\Theme\Footer\EDITOR_SCRIPT,
        'style_handles'        => ['mizzey-announcement-strip'],
        'editor_style_handles' => ['mizzey-announcement-strip'],
        'render_callback'      => __NAMESPACE__ . '\\render',
    ]);
}

/**
 * The messages for a language, words first; a message without words is left out.
 *
 * @param mixed $messages Language code to a list of messages.
 * @return list<array{text:string,label:string,url:string}>
 */
function messages($messages, string $lang): array
{
    $list = is_array($messages) && is_array($messages[$lang] ?? null) ? array_values($messages[$lang]) : [];
    $out  = [];
    foreach ($list as $message) {
        $message = is_array($message) ? $message : [];
        $text    = \MizzeySite\Theme\Footer\text((string) ($message['text'] ?? ''));
        if ('' === $text) {
            continue;
        }
        $out[] = [
            'text'  => $text,
            'label' => \MizzeySite\Theme\Footer\text((string) ($message['label'] ?? '')),
            'url'   => trim((string) ($message['url'] ?? '')),
        ];
        if (count($out) === MOST) {
            break;
        }
    }

    return $out;
}

/**
 * The block's markup.
 *
 * @param array<string,mixed> $attributes
 */
function render(array $attributes): string
{
    $variant  = in_array($attributes['variant'] ?? '', VARIANTS, true) ? (string) $attributes['variant'] : 'fixed';
    $messages = messages($attributes['messages'] ?? [], \MizzeySite\Theme\Footer\language());
    if ([] === $messages) {
        return '';
    }
    if ('fixed' === $variant) {
        $messages = array_slice($messages, 0, 1);
    }
    $moving = count($messages) > 1;
    if ($moving) {
        wp_enqueue_script_module('mizzey-theme/site-parts');
    }

    $items = '';
    foreach ($messages as $i => $message) {
        $href  = esc_url($message['url']);
        $link  = '' !== $message['label'] && '' !== $href
            ? element('a', ['class' => 'mizzey-announcement__link', 'href' => $href], esc_html($message['label']))
            : '';
        $items .= element(
            'p',
            [
                'class'       => 'mizzey-announcement__message' . (0 === $i ? ' is-current' : ''),
                'inert'       => 0 !== $i,
                'aria-hidden' => 0 !== $i ? 'true' : null,
            ],
            element('span', ['class' => 'mizzey-announcement__text'], esc_html($message['text'])) . $link
        );
    }

    $pause = $moving
        ? element(
            'button',
            [
                'type'              => 'button',
                'class'             => 'mizzey-announcement__pause',
                'aria-label'        => __('Pause the messages', 'mizzey-site'),
                'data-label-pause'  => __('Pause the messages', 'mizzey-site'),
                'data-label-play'   => __('Play the messages', 'mizzey-site'),
                'data-wp-on--click' => 'actions.stripToggle',
            ],
            icon('pause', ['size' => 16, 'class' => 'mizzey-announcement__icon-pause']) . icon('play', ['size' => 16, 'class' => 'mizzey-announcement__icon-play'])
        )
        : '';

    return element(
        'div',
        [
            'class'               => 'mizzey-announcement mizzey-announcement--' . $variant,
            'role'                => 'region',
            'aria-label'          => __('Announcements', 'mizzey-site'),
            'data-wp-interactive' => $moving ? 'mizzey/site' : null,
            'data-wp-init'        => $moving ? 'callbacks.stripInit' : null,
        ],
        element('div', ['class' => 'mizzey-announcement__track'], $items) . $pause
    );
}

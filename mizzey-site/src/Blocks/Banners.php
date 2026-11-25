<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

/**
 * Two sections of words and a button, as C1-Home-Phone, C1-Home-Phone-Arabic and C1-Home-Desktop draw them:
 *
 * - **Offer banner** (HOME-07, "Fresh on the beauty shelf"): an image with a small tag, a heading, a line and a
 *   button. On a phone the image sits above the words in one rounded card; from 1024 wide the image fills a 320 high
 *   banner and the words sit at its start side on a soft white veil. Without a heading it renders nothing.
 * - **Sign-up prompt** (HOME-09, "Stay in touch"): a heading, a line and a button to the account's sign-up, on the
 *   soft Indigo panel. Its button opens the account page's registration (specs/056: "a link to the account's
 *   registration, whose consent is separate", specs/017). The email field the boards draw in place of the button needs
 *   a signup record that is not built (DECISIONS A10): it waits for the owner's ruling, and is not drawn here.
 *
 * Every word and the image are the editor's, per language.
 *
 * Register ids: HOME-07, HOME-09, HOME-10. See specs/066-stage-2-shop-front, slice 5.
 */
final class Banners
{
    /** @param array<string,mixed> $attributes */
    public static function renderOffer(array $attributes): string
    {
        $heading = Section::text($attributes['heading'] ?? '');
        if ('' === $heading) {
            return '';
        }
        $tag   = Section::text($attributes['tag'] ?? '');
        $text  = Section::rich($attributes['text'] ?? '');
        $image = Section::image((int) ($attributes['imageId'] ?? 0), Section::text($attributes['imageAlt'] ?? ''), 'mizzey-offer__image', Hero::sizes(), ['loading' => 'lazy']);
        return sprintf(
            '<section %s><div class="mizzey-offer__card">%s<div class="mizzey-offer__body">%s%s%s%s</div></div></section>',
            Section::wrapper('offer-banner', ['mizzey-offer', '' === $image ? 'mizzey-offer--no-image' : '']),
            '' !== $image ? Section::el('div', ['class' => 'mizzey-offer__media'], Section::well() . $image) : '',
            '' !== $tag ? Section::el('span', ['class' => ['mizzey-badge', 'mizzey-badge--tint', 'mizzey-offer__tag']], esc_html($tag)) : '',
            Section::el('h2', ['class' => 'mizzey-offer__heading'], esc_html($heading)),
            '' !== $text ? Section::el('p', ['class' => 'mizzey-offer__text'], $text) : '',
            Section::button(Section::text($attributes['ctaLabel'] ?? ''), (string) ($attributes['ctaUrl'] ?? ''), 'primary', 'mizzey-offer__button')
        );
    }

    /** @param array<string,mixed> $attributes */
    public static function renderSignup(array $attributes): string
    {
        $heading = Section::text($attributes['heading'] ?? '');
        if ('' === $heading) {
            return '';
        }
        $url = trim((string) ($attributes['ctaUrl'] ?? ''));
        if ('' === $url && function_exists('wc_get_page_permalink')) {
            $url = (string) wc_get_page_permalink('myaccount');
        }
        $text = Section::rich($attributes['text'] ?? '');
        return sprintf(
            '<section %s><div class="mizzey-signup__words">%s%s</div><div class="mizzey-signup__action">%s</div></section>',
            Section::wrapper('signup', ['mizzey-signup'], ['aria-label' => $heading]),
            Section::el('h2', ['class' => 'mizzey-signup__heading'], esc_html($heading)),
            '' !== $text ? Section::el('p', ['class' => 'mizzey-signup__text'], $text) : '',
            Section::button(Section::text($attributes['ctaLabel'] ?? ''), $url, 'primary', 'mizzey-signup__button')
        );
    }
}

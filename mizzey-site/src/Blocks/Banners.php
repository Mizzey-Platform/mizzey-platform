<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

use MizzeySite\Security\SignupCheck;
use MizzeySite\Signups\SignupRoutes;
use MizzeySite\Signups\SignupService;

/**
 * Two sections of words and a button, as C1-Home-Phone, C1-Home-Phone-Arabic and C1-Home-Desktop draw them:
 *
 * - **Offer banner** (HOME-07, "Fresh on the beauty shelf"): an image with a small tag, a heading, a line and a
 *   button. On a phone the image sits above the words in one rounded card; from 1024 wide the image fills a 320 high
 *   banner and the words sit at its start side on a soft white veil. Without a heading it renders nothing.
 * - **Sign-up prompt** (HOME-09, "Stay in touch"), on the soft Indigo panel: the heading and line, the "Email address"
 *   field, the optional box for news and offers and the Sign up button, with its invalid, busy and success states
 *   (C1-Home-Phone, C1-Home-Desktop and C1-Home-Phone-Arabic, frames A2 and C2). The sign-up is one small record with
 *   its consent, no account (DECISIONS A10, ruled by the owner on 25 November 2026; MizzeySite\Signups). The success
 *   replaces the form in the same grid cell, so the panel keeps its height and nothing below moves. Without
 *   JavaScript the form posts to its page, which answers in the same states (SignupRoutes::result()).
 *
 * The heading, line, button label and image are the editor's, per language; the field's words are translated.
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
        $image = Section::image((int) ($attributes['imageId'] ?? 0), Section::plain($attributes['imageAlt'] ?? ''), 'mizzey-offer__image', Hero::sizes(), ['loading' => 'lazy']);
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
        static $count = 0;
        ++$count;
        $id     = 1 === $count ? 'mizzey-signup' : 'mizzey-signup-' . $count;
        $text   = Section::rich($attributes['text'] ?? '');
        $label  = Section::text($attributes['ctaLabel'] ?? '');
        $result = SignupRoutes::result();
        $state  = null === $result ? '' : $result['state'];
        $sent   = 'ok' === $state;
        $words  = '' !== $state && ! $sent ? SignupService::message($state) : '';
        // "again": the token was refused (a cached or old page); the address is kept and one more press finishes. It
        // is not the address's fault, so the field is not marked invalid.
        $again  = 'again' === $state;
        $error  = $again ? '' : $words;

        $input = self::input([
            'class'            => 'mizzey-signup__input',
            'id'               => $id . '-email',
            'type'             => 'email',
            'name'             => SignupService::FIELD_EMAIL,
            'autocomplete'     => 'email',
            'inputmode'        => 'email',
            'dir'              => 'ltr',
            'placeholder'      => 'name@example.com',
            'required'         => true,
            'maxlength'        => '191',
            'value'            => null === $result || $sent ? null : $result['typed'],
            'aria-invalid'     => '' === $error ? null : 'true',
            'aria-describedby' => $id . '-error',
            'autofocus'        => '' !== $error,
        ]);
        $box = self::input([
            'class'   => ['mizzey-check__box', 'mizzey-signup__optin'],
            'type'    => 'checkbox',
            'name'    => SignupService::FIELD_OPTIN,
            'value'   => '1',
            'checked' => null !== $result && ! $sent && $result['optin'],
        ]);
        $hidden = self::input(['type' => 'hidden', 'name' => SignupService::FIELD_FORM, 'value' => '1'])
            // A page anyone can be sent carries no token: it is single-use, and the page may be served from the page
            // cache to every visitor (NFR-02). The script asks for its own (SignupRoutes::token()). Without JavaScript
            // the first press comes back as "again", the address kept, and only that answer, built for this visitor
            // and never cached, carries a token for the second press.
            . (null === $result ? '' : self::input(['type' => 'hidden', 'name' => SignupService::FIELD_TOKEN, 'value' => SignupCheck::issue(SignupService::ROUTE)]))
            . self::input(['type' => 'hidden', 'name' => SignupService::FIELD_LANG, 'value' => SignupService::language(apply_filters('wpml_current_language', null))])
            // A trap for scripts: a person never sees or reaches it, and it must arrive empty.
            . Section::el('span', ['class' => 'mizzey-signup__trap', 'aria-hidden' => 'true'], self::input(['type' => 'text', 'name' => SignupService::FIELD_TRAP, 'value' => '', 'tabindex' => '-1', 'autocomplete' => 'off']));

        $form = Section::el(
            'form',
            [
                'class'         => 'mizzey-signup__form',
                'method'        => 'post',
                'action'        => '#' . $id,
                'novalidate'    => true,
                'inert'         => $sent,
                'data-endpoint' => esc_url_raw(rest_url(SignupRoutes::NS . '/signup')),
                'data-token'    => esc_url_raw(rest_url(SignupRoutes::NS . '/signup/token')),
                'data-invalid'  => SignupService::message('invalid'),
                'data-expired'  => SignupService::message('expired'),
                'data-limited'  => SignupService::message('limited'),
                'data-failed'   => SignupService::message('failed'),
                'data-offline'  => SignupService::message('offline'),
                'data-unavailable' => SignupService::message('unavailable'),
            ],
            Section::el('label', ['class' => 'mizzey-signup__label', 'for' => $id . '-email'], esc_html__('Email address', 'mizzey-site'))
            . Section::el('span', ['class' => 'mizzey-signup__box'], $input . Section::el('span', ['class' => 'mizzey-signup__line', 'aria-hidden' => 'true']))
            . Section::el(
                'span',
                ['class' => ['mizzey-signup__error', $again ? 'is-again' : ''], 'id' => $id . '-error', 'hidden' => '' === $words],
                self::icon('alert', 20, 'mizzey-signup__error-icon') . Section::el('span', ['class' => 'mizzey-signup__error-text'], esc_html($words))
            )
            . Section::el(
                'label',
                ['class' => ['mizzey-check', 'mizzey-signup__consent']],
                $box . Section::el('span', ['class' => 'mizzey-check__tick', 'aria-hidden' => 'true'], self::icon('tick', 20))
                . Section::el('span', ['class' => 'mizzey-signup__consent-text'], esc_html__('Email me news and offers. Optional, and separate from your account.', 'mizzey-site'))
            )
            . Section::el('button', ['type' => 'submit', 'class' => ['mizzey-button', 'mizzey-button--primary', 'mizzey-signup__button'], 'autofocus' => $again], Section::el('span', ['class' => 'mizzey-button__label'], esc_html('' !== $label ? $label : __('Sign up', 'mizzey-site'))))
            . $hidden
            . Section::el('template', ['class' => 'mizzey-signup__busy'], self::loader(__('Signing up', 'mizzey-site')))
        );

        $address = Section::el('bdi', ['class' => 'mizzey-signup__address', 'dir' => 'ltr'], esc_html($sent ? $result['email'] : ''));
        $done    = Section::el(
            'div',
            ['class' => 'mizzey-signup__done', 'id' => $id . '-done', 'tabindex' => '-1', 'hidden' => ! $sent, 'autofocus' => $sent],
            Section::el('span', ['class' => 'mizzey-signup__disc', 'aria-hidden' => 'true'], self::icon('tick', 24))
            . Section::el(
                'div',
                ['class' => 'mizzey-signup__done-words'],
                Section::el('h2', ['class' => 'mizzey-signup__done-title'], esc_html__('You are on the list', 'mizzey-site'))
                . Section::el('p', ['class' => 'mizzey-signup__done-text'], sprintf(
                    /* translators: %s: the email address that signed up. */
                    esc_html__('We sent a short welcome note to %s. You can leave any time from the link in every email.', 'mizzey-site'),
                    $address
                ))
            )
        );

        return sprintf(
            '<section %s><div class="mizzey-signup__words">%s%s</div>%s%s</section>',
            Section::wrapper('signup', ['mizzey-signup', $sent ? 'is-sent' : '', '' !== $error ? 'is-error' : ''], ['id' => $id, 'aria-labelledby' => $id . '-heading']),
            Section::el('h2', ['class' => 'mizzey-signup__heading', 'id' => $id . '-heading'], esc_html($heading)),
            '' !== $text ? Section::el('p', ['class' => 'mizzey-signup__text'], $text) : '',
            $form,
            $done
        );
    }

    /** A void input element, escaped. @param array<string,string|list<string>|bool|null> $attributes */
    private static function input(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if (null === $value || false === $value) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(' ', $value);
            }
            $out .= true === $value ? ' ' . esc_attr($name) : sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
        }

        return '<input' . $out . '>';
    }

    private static function icon(string $name, int $size, string $class = ''): string
    {
        return function_exists('MizzeySite\Theme\Icons\icon') ? \MizzeySite\Theme\Icons\icon($name, ['size' => $size, 'class' => $class]) : '';
    }

    /** The i-dot loader of slice 1, named for what the button is doing; the busy button shows only the dots. */
    private static function loader(string $label): string
    {
        return function_exists('MizzeySite\Theme\Components\loader') ? \MizzeySite\Theme\Components\loader(['label' => $label]) : '';
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

/**
 * The WhatsApp click-to-chat link (CMS-10), from the store's own number and a greeting in each language.
 *
 * Set in WooCommerce > Settings > General > Store contact: the WhatsApp number in international form (digits only,
 * country code first, for example 201001234567), and the message a chat opens with, in English and in Arabic. The link
 * is WhatsApp's own click-to-chat address (`https://wa.me/<number>?text=<message>`), in the page's language. It is
 * read by the designed footer, contact page and product page through `GET mizzey/v1/contact`, and by any block with the
 * `[mizzey_whatsapp]` shortcode, which prints the address. With no number set there is no link, never a broken one.
 *
 * The contact pages themselves, the contact form (CoreX Forms, its notification and the sender's confirmation set in
 * its builder) and the policy pages are the client's to edit as pages; see specs/059-whatsapp-contact.
 *
 * Register ids: CMS-10. See specs/059-whatsapp-contact.
 */
final class ContactLinks
{
    public const NUMBER  = 'mizzey_whatsapp_number';
    public const MESSAGE = 'mizzey_whatsapp_message_';

    public static function register(): void
    {
        add_filter('woocommerce_general_settings', [self::class, 'settings']);
        add_action('rest_api_init', [self::class, 'routes']);
        add_shortcode('mizzey_whatsapp', [self::class, 'shortcode']);
    }

    /**
     * @param mixed $settings
     * @return mixed
     */
    public static function settings($settings)
    {
        if (! is_array($settings)) {
            return $settings;
        }
        $settings[] = ['type' => 'title', 'id' => 'mizzey_store_contact', 'title' => __('Store contact', 'mizzey-site'), 'desc' => __('The WhatsApp click-to-chat link the storefront shows.', 'mizzey-site')];
        $settings[] = ['type' => 'text', 'id' => self::NUMBER, 'title' => __('WhatsApp number', 'mizzey-site'), 'desc_tip' => __('International form, digits only, country code first, for example 201001234567.', 'mizzey-site'), 'placeholder' => '201001234567'];
        $settings[] = ['type' => 'text', 'id' => self::MESSAGE . 'en', 'title' => __('WhatsApp greeting (English)', 'mizzey-site'), 'desc_tip' => __('The message a chat opens with. Optional.', 'mizzey-site')];
        $settings[] = ['type' => 'text', 'id' => self::MESSAGE . 'ar', 'title' => __('WhatsApp greeting (Arabic)', 'mizzey-site'), 'custom_attributes' => ['dir' => 'rtl', 'lang' => 'ar']];
        $settings[] = ['type' => 'sectionend', 'id' => 'mizzey_store_contact'];

        return $settings;
    }

    /** The number as WhatsApp takes it: digits only, without a leading 00 or +; empty when unusable. */
    public static function number(): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) get_option(self::NUMBER, ''));
        $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : '';
    }

    /** The click-to-chat address in the page's language, or an empty string when no number is set. */
    public static function whatsappUrl(?string $language = null): string
    {
        $number = self::number();
        if ($number === '') {
            return '';
        }
        $language ??= (string) apply_filters('wpml_current_language', null) === 'ar' ? 'ar' : 'en';
        $message    = trim((string) get_option(self::MESSAGE . $language, ''));
        $message    = $message !== '' ? $message : trim((string) get_option(self::MESSAGE . 'en', ''));

        return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/contact', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => static fn (): \WP_REST_Response => new \WP_REST_Response(['whatsapp' => self::whatsappUrl()]),
        ]);
    }

    public static function shortcode(): string
    {
        return esc_url(self::whatsappUrl());
    }
}

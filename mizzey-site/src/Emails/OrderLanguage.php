<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Emails;

defined('ABSPATH') || exit;

/**
 * Runs work in an order's language: WPML's language (the order's `wpml_language`, which WooCommerce Multilingual keeps)
 * and the WordPress locale are switched before it and restored after, as they were found.
 *
 * See specs/021-emails.
 */
final class OrderLanguage
{
    /**
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public static function during(\WC_Order $order, callable $work)
    {
        $language = (string) $order->get_meta('wpml_language');
        $current  = (string) apply_filters('wpml_current_language', null);
        $locale   = (string) (((array) apply_filters('wpml_active_languages', null))[$language]['default_locale'] ?? '');
        if ($language !== '') {
            do_action('wpml_switch_language', $language);
        }
        $switched = $locale !== '' && switch_to_locale($locale);
        try {
            return $work();
        } finally {
            if ($switched) {
                restore_previous_locale();
            }
            if ($language !== '') {
                do_action('wpml_switch_language', $current !== '' ? $current : null);
            }
        }
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Catalogue;

defined('ABSPATH') || exit;

/**
 * A colour for an attribute's value (Products > Attributes > the attribute's values), so the product page shows that
 * value as a swatch, as C1-Product-Phone frame 7 and C1-Product-Desktop draw it. Kept as the term's meta
 * `mizzey_swatch`, a hex code; anything else is refused and the saved colour kept. A value with no colour stays a
 * text option. Global attributes only: a product's own custom attribute has no term to hold a colour.
 *
 * Register ids: PDP-05. See specs/066-stage-2-shop-front, slice 9.
 */
final class AttributeSwatch
{
    public const META  = 'mizzey_swatch';
    public const NONCE = 'mizzey_swatch';

    public static function register(): void
    {
        add_action('admin_init', [self::class, 'hook']);
    }

    /** The field on every global attribute's value screens, and its saving. */
    public static function hook(): void
    {
        if (! function_exists('wc_get_attribute_taxonomy_names')) {
            return;
        }
        foreach (wc_get_attribute_taxonomy_names() as $taxonomy) {
            if (has_action("{$taxonomy}_edit_form_fields", [self::class, 'editField'])) {
                continue;
            }
            add_action("{$taxonomy}_add_form_fields", [self::class, 'addField']);
            add_action("{$taxonomy}_edit_form_fields", [self::class, 'editField']);
            add_action("created_{$taxonomy}", [self::class, 'save']);
            add_action("edited_{$taxonomy}", [self::class, 'save']);
        }
    }

    private static function input(string $value): string
    {
        return wp_nonce_field(self::NONCE, self::NONCE . '_nonce', true, false)
            . sprintf('<input type="color" id="mizzey-swatch" name="%s" value="%s" />', esc_attr(self::META), esc_attr($value !== '' ? $value : '#ffffff'))
            . sprintf(' <label><input type="checkbox" name="%s_none" value="1"%s /> %s</label>', esc_attr(self::META), checked($value, '', false), esc_html__('No colour: show this value as text', 'mizzey-site'))
            . '<p class="description">' . esc_html__('The colour shown as a swatch for this value on the product page.', 'mizzey-site') . '</p>';
    }

    public static function addField(): void
    {
        echo '<div class="form-field"><label for="mizzey-swatch">' . esc_html__('Swatch colour', 'mizzey-site') . '</label>' . self::input('') . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in input().
    }

    /** @param mixed $term */
    public static function editField($term): void
    {
        $value = $term instanceof \WP_Term ? (string) get_term_meta($term->term_id, self::META, true) : '';
        echo '<tr class="form-field"><th scope="row"><label for="mizzey-swatch">' . esc_html__('Swatch colour', 'mizzey-site') . '</label></th><td>' . self::input($value) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in input().
    }

    /** Save a hex colour, clear it when "No colour" is ticked; refuse anything else. */
    public static function save(int $termId): void
    {
        $nonce = isset($_POST[self::NONCE . '_nonce']) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE . '_nonce'])) : '';
        if ($nonce === '' || ! wp_verify_nonce($nonce, self::NONCE) || ! current_user_can('manage_product_terms')) {
            return;
        }
        if (! empty($_POST[self::META . '_none'])) {
            delete_term_meta($termId, self::META);
            return;
        }
        $hex = sanitize_hex_color(isset($_POST[self::META]) ? sanitize_text_field(wp_unslash((string) $_POST[self::META])) : '');
        if (is_string($hex) && $hex !== '') {
            update_term_meta($termId, self::META, strtolower($hex));
        }
    }
}

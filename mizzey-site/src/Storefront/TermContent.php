<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

use MizzeySite\Catalogue\Collections;

/**
 * Each category's, brand's and collection's page content (SSC-04, SSC-05, SSC-06): its description, its image and its
 * banner, set on its own admin screen.
 *
 * WooCommerce gives a category a description and an image, and a brand the same; a collection has its description. This
 * adds the banner the listing page opens with (an image from the media library, with its alternative text, and an
 * optional link), for all three, and an image for a collection. The description is the term's own, so it is written in
 * each language on the term's translation (SSC-12); the banner and image are set on the main-language term and shared,
 * unless the Arabic term sets its own. `GET mizzey/v1/term-content?taxonomy=&id=` reads them in the request's
 * language, for the designed listing page (PRE-03b).
 *
 * Register ids: SSC-04, SSC-05, SSC-06, SSC-12. See specs/060-store-content.
 */
final class TermContent
{
    public const BANNER      = 'mizzey_banner_id';
    public const BANNER_LINK = 'mizzey_banner_link';
    public const IMAGE       = 'thumbnail_id';
    public const TAXONOMIES  = ['product_cat', 'product_brand', Collections::TAXONOMY];
    private const NONCE      = 'mizzey_term_content';

    public static function register(): void
    {
        foreach (self::TAXONOMIES as $taxonomy) {
            add_action("{$taxonomy}_add_form_fields", [self::class, 'addFields']);
            add_action("{$taxonomy}_edit_form_fields", [self::class, 'editFields'], 20);
            add_action("created_{$taxonomy}", [self::class, 'save']);
            add_action("edited_{$taxonomy}", [self::class, 'save']);
        }
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function addFields(): void
    {
        wp_nonce_field(self::NONCE, 'mizzey_term_content_nonce');
        echo '<div class="form-field"><label for="mizzey_banner">' . esc_html__('Banner image (media library address or id)', 'mizzey-site') . '</label><input type="text" name="mizzey_banner" id="mizzey_banner" value=""></div>';
        echo '<div class="form-field"><label for="mizzey_banner_link">' . esc_html__('Banner link (optional)', 'mizzey-site') . '</label><input type="url" name="mizzey_banner_link" id="mizzey_banner_link" value=""></div>';
    }

    /** @param mixed $term */
    public static function editFields($term): void
    {
        if (! $term instanceof \WP_Term) {
            return;
        }
        $banner = (int) get_term_meta($term->term_id, self::BANNER, true);
        echo '<tr class="form-field"><th scope="row">' . esc_html__('Banner', 'mizzey-site') . '</th><td>';
        wp_nonce_field(self::NONCE, 'mizzey_term_content_nonce');
        echo '<p><label for="mizzey_banner">' . esc_html__('Banner image (media library address or id)', 'mizzey-site') . '</label><br><input type="text" name="mizzey_banner" id="mizzey_banner" value="' . esc_attr($banner > 0 ? (string) $banner : '') . '"></p>';
        if ($banner > 0) {
            echo wp_get_attachment_image($banner, 'medium');
        }
        echo '<p><label for="mizzey_banner_link">' . esc_html__('Banner link (optional)', 'mizzey-site') . '</label><br><input type="url" name="mizzey_banner_link" id="mizzey_banner_link" value="' . esc_attr((string) get_term_meta($term->term_id, self::BANNER_LINK, true)) . '"></p>';
        if ($term->taxonomy === Collections::TAXONOMY) {
            $image = (int) get_term_meta($term->term_id, self::IMAGE, true);
            echo '<p><label for="mizzey_term_image">' . esc_html__('Collection image (media library address or id)', 'mizzey-site') . '</label><br><input type="text" name="mizzey_term_image" id="mizzey_term_image" value="' . esc_attr($image > 0 ? (string) $image : '') . '"></p>';
        }
        echo '<p class="description">' . esc_html__('Set on the main language, the banner shows in both; set on the Arabic term, the Arabic page shows its own.', 'mizzey-site') . '</p></td></tr>';
    }

    /** An attachment from an id or a media library address; 0 when it is neither. */
    public static function attachment(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $id = ctype_digit($value) ? (int) $value : (int) attachment_url_to_postid(esc_url_raw($value));

        return $id > 0 && wp_attachment_is_image($id) ? $id : 0;
    }

    public static function save(int $termId): void
    {
        $nonce = isset($_POST['mizzey_term_content_nonce']) ? sanitize_text_field(wp_unslash($_POST['mizzey_term_content_nonce'])) : '';
        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE) === false || ! current_user_can('manage_product_terms')) {
            return;
        }
        $fields = [self::BANNER => 'mizzey_banner', self::IMAGE => 'mizzey_term_image'];
        foreach ($fields as $meta => $field) {
            if (! isset($_POST[$field])) {
                continue;
            }
            $id = self::attachment(sanitize_text_field(wp_unslash($_POST[$field])));
            $id > 0 ? update_term_meta($termId, $meta, $id) : delete_term_meta($termId, $meta);
        }
        if (isset($_POST['mizzey_banner_link'])) {
            $link = esc_url_raw(trim(wp_unslash((string) $_POST['mizzey_banner_link']))); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $link !== '' ? update_term_meta($termId, self::BANNER_LINK, $link) : delete_term_meta($termId, self::BANNER_LINK);
        }
    }

    /** A term's own value, or its main-language term's. */
    private static function meta(\WP_Term $term, string $key): string
    {
        $own = (string) get_term_meta($term->term_id, $key, true);
        if ($own !== '') {
            return $own;
        }
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = $default !== '' ? (int) apply_filters('wpml_object_id', $term->term_id, $term->taxonomy, true, $default) : 0;

        return $original > 0 && $original !== (int) $term->term_id ? (string) get_term_meta($original, $key, true) : '';
    }

    /** @return array<string,mixed>|null */
    public static function content(\WP_Term $term): ?array
    {
        if (! in_array($term->taxonomy, self::TAXONOMIES, true)) {
            return null;
        }
        $banner = (int) self::meta($term, self::BANNER);
        $image  = (int) self::meta($term, self::IMAGE);
        $url    = get_term_link($term);

        return [
            'name'        => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url'         => is_wp_error($url) ? '' : (string) $url,
            'description' => (string) $term->description,
            'image'       => $image > 0 ? (string) wp_get_attachment_image_url($image, 'woocommerce_thumbnail') : '',
            'banner'      => $banner > 0 ? [
                'url'  => (string) wp_get_attachment_image_url($banner, 'full'),
                'alt'  => (string) get_post_meta($banner, '_wp_attachment_image_alt', true),
                'link' => self::meta($term, self::BANNER_LINK),
            ] : null,
        ];
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/term-content', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => ['taxonomy' => ['required' => true, 'type' => 'string', 'enum' => self::TAXONOMIES], 'id' => ['required' => true, 'type' => 'integer']],
            'callback'            => static function (\WP_REST_Request $request) {
                $taxonomy = (string) $request->get_param('taxonomy');
                $id       = (int) apply_filters('wpml_object_id', (int) $request->get_param('id'), $taxonomy, true);
                $term     = get_term($id, $taxonomy);
                $content  = $term instanceof \WP_Term ? self::content($term) : null;

                return $content === null ? new \WP_Error('mizzey_no_term', __('No such category, brand or collection.', 'mizzey-site'), ['status' => 404]) : new \WP_REST_Response($content);
            },
        ]);
    }
}

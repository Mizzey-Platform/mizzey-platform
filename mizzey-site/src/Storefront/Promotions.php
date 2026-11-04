<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

/**
 * Promotional banners (SSC-09): created, scheduled, targeted to a page, and removed by staff, in each language.
 *
 * A banner is a "Promotion" (WooCommerce > Promotional banners): its content is blocks (an image, words, a link), so
 * the client writes it in the editor in each language, with preview and revisions (SSC-12 to SSC-14). It has a start
 * and an end (either may be empty) and its targets: every page, the home page, the shop, given pages, given product
 * categories. Outside its dates or off its targets it is not shown; removing it is moving it to the bin.
 *
 * The designed storefront places the banners with the block `mizzey/promo-banners` (server-rendered, no script),
 * which shows the active banners for the page being viewed; `GET mizzey/v1/promos?context=` answers the same for a
 * page, the home page, the shop or a category.
 *
 * Register ids: SSC-09, SSC-12, SSC-13, SSC-14. See specs/060-store-content.
 */
final class Promotions
{
    public const TYPE    = 'mizzey_promo';
    public const START   = '_mizzey_promo_start';
    public const END     = '_mizzey_promo_end';
    public const TARGETS = '_mizzey_promo_targets';
    private const NONCE  = 'mizzey_promo';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerType']);
        add_action('add_meta_boxes_' . self::TYPE, [self::class, 'metaBox']);
        add_action('save_post_' . self::TYPE, [self::class, 'save'], 10, 1);
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function registerType(): void
    {
        register_post_type(self::TYPE, [
            'labels'       => [
                'name'          => __('Promotional banners', 'mizzey-site'),
                'singular_name' => __('Promotional banner', 'mizzey-site'),
                'add_new_item'  => __('Add promotional banner', 'mizzey-site'),
                'edit_item'     => __('Edit promotional banner', 'mizzey-site'),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'woocommerce',
            'show_in_rest' => true,
            'supports'     => ['title', 'editor', 'revisions'],
            'map_meta_cap' => true,
            'capability_type' => 'page',
        ]);
        register_block_type('mizzey/promo-banners', [
            'api_version'     => 3,
            'attributes'      => ['context' => ['type' => 'string', 'default' => '']],
            'render_callback' => [self::class, 'render'],
        ]);
    }

    public static function metaBox(): void
    {
        add_meta_box('mizzey_promo_schedule', __('Schedule and targets', 'mizzey-site'), [self::class, 'box'], self::TYPE, 'side');
    }

    /** @param mixed $post */
    public static function box($post): void
    {
        if (! $post instanceof \WP_Post) {
            return;
        }
        $start   = (string) get_post_meta($post->ID, self::START, true);
        $end     = (string) get_post_meta($post->ID, self::END, true);
        $targets = (array) get_post_meta($post->ID, self::TARGETS, true);
        wp_nonce_field(self::NONCE, 'mizzey_promo_nonce');
        $local = static fn (string $utc): string => $utc === '' ? '' : wp_date('Y-m-d\TH:i', (int) strtotime($utc . ' UTC'));
        echo '<p><label>' . esc_html__('Shown from', 'mizzey-site') . '<br><input type="datetime-local" name="mizzey_promo_start" value="' . esc_attr($local($start)) . '"></label></p>';
        echo '<p><label>' . esc_html__('Shown until', 'mizzey-site') . '<br><input type="datetime-local" name="mizzey_promo_end" value="' . esc_attr($local($end)) . '"></label></p>';
        echo '<p>' . esc_html__('Leave either empty for no limit. Times are the store\'s time zone.', 'mizzey-site') . '</p>';
        foreach (['all' => __('Every page', 'mizzey-site'), 'home' => __('The home page', 'mizzey-site'), 'shop' => __('The shop', 'mizzey-site')] as $key => $label) {
            echo '<label><input type="checkbox" name="mizzey_promo_targets[]" value="' . esc_attr($key) . '"' . checked(in_array($key, $targets, true), true, false) . '> ' . esc_html($label) . '</label><br>';
        }
        $ids = static fn (string $prefix): string => implode(', ', array_map(static fn (string $t): string => substr($t, strlen($prefix)), array_values(array_filter($targets, static fn ($t): bool => is_string($t) && str_starts_with($t, $prefix)))));
        echo '<p><label>' . esc_html__('Pages (ids, separated by commas)', 'mizzey-site') . '<br><input type="text" name="mizzey_promo_pages" value="' . esc_attr($ids('page:')) . '"></label></p>';
        echo '<p><label>' . esc_html__('Product categories (ids, separated by commas)', 'mizzey-site') . '<br><input type="text" name="mizzey_promo_categories" value="' . esc_attr($ids('product_cat:')) . '"></label></p>';
    }

    public static function save(int $postId): void
    {
        $nonce = isset($_POST['mizzey_promo_nonce']) ? sanitize_text_field(wp_unslash($_POST['mizzey_promo_nonce'])) : '';
        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE) === false || ! current_user_can('edit_post', $postId) || wp_is_post_revision($postId)) {
            return;
        }
        $utc = static function (string $field): string {
            $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
            if ($value === '') {
                return '';
            }
            $date = date_create_immutable($value, wp_timezone());

            return $date === false ? '' : $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        };
        update_post_meta($postId, self::START, $utc('mizzey_promo_start'));
        update_post_meta($postId, self::END, $utc('mizzey_promo_end'));
        $targets = array_values(array_intersect(['all', 'home', 'shop'], array_map('sanitize_key', (array) wp_unslash($_POST['mizzey_promo_targets'] ?? [])))); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
        foreach (['mizzey_promo_pages' => 'page:', 'mizzey_promo_categories' => 'product_cat:'] as $field => $prefix) {
            foreach (wp_parse_id_list(isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '') as $id) { // phpcs:ignore WordPress.Security.NonceVerification
                $targets[] = $prefix . $id;
            }
        }
        update_post_meta($postId, self::TARGETS, array_values(array_unique($targets)));
    }

    /**
     * The context of the page being viewed, as targets name it.
     *
     * @return list<string>
     */
    public static function currentContext(): array
    {
        $context = ['all'];
        if (is_front_page()) {
            $context[] = 'home';
        }
        if (function_exists('is_shop') && is_shop()) {
            $context[] = 'shop';
        }
        if (is_page()) {
            $context[] = 'page:' . self::original((int) get_queried_object_id(), 'page');
        }
        if (function_exists('is_product_category') && is_product_category()) {
            $context[] = 'product_cat:' . self::originalTerm((int) get_queried_object_id());
        }

        return $context;
    }

    /** Targets name the main-language page or category, so one banner's targets serve both languages. */
    private static function original(int $id, string $type): int
    {
        $default = (string) apply_filters('wpml_default_language', null);
        $orig    = $default !== '' ? (int) apply_filters('wpml_object_id', $id, $type, true, $default) : 0;

        return $orig > 0 ? $orig : $id;
    }

    private static function originalTerm(int $id): int
    {
        return self::original($id, 'product_cat');
    }

    /**
     * The banners shown now for a context, in the request's language, newest first.
     *
     * @param list<string> $context
     * @return list<\WP_Post>
     */
    public static function active(array $context, ?int $now = null): array
    {
        $now   = $now ?? time();
        $posts = get_posts(['post_type' => self::TYPE, 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false]);
        $shown = [];
        foreach ($posts as $post) {
            $source  = self::original((int) $post->ID, self::TYPE);
            $start   = (string) get_post_meta($source, self::START, true);
            $end     = (string) get_post_meta($source, self::END, true);
            $targets = (array) get_post_meta($source, self::TARGETS, true);
            if (($start !== '' && strtotime($start . ' UTC') > $now) || ($end !== '' && strtotime($end . ' UTC') <= $now)) {
                continue;
            }
            if (array_intersect($targets, $context) === []) {
                continue;
            }
            $shown[] = $post;
        }

        return $shown;
    }

    /** @param mixed $attributes */
    public static function render($attributes = []): string
    {
        $banners = self::active(self::currentContext());
        if ($banners === []) {
            return '';
        }
        $html = '';
        foreach ($banners as $post) {
            $html .= '<div class="mizzey-promo" data-promo="' . esc_attr((string) $post->ID) . '">' . do_blocks((string) $post->post_content) . '</div>';
        }

        return '<div class="mizzey-promos">' . $html . '</div>';
    }

    public static function routes(): void
    {
        register_rest_route('mizzey/v1', '/promos', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => ['context' => ['type' => 'string', 'default' => 'all']],
            'callback'            => static function (\WP_REST_Request $request): \WP_REST_Response {
                $asked   = sanitize_text_field((string) $request->get_param('context'));
                $context = ['all'];
                if (in_array($asked, ['home', 'shop'], true)) {
                    $context[] = $asked;
                } elseif (preg_match('/^(page|product_cat):(\d+)$/', $asked, $m) === 1) {
                    $context[] = $m[1] . ':' . self::original((int) $m[2], $m[1]);
                }
                $out = array_map(static fn (\WP_Post $post): array => ['id' => (int) $post->ID, 'title' => get_the_title($post), 'content' => do_blocks((string) $post->post_content)], self::active($context));

                return new \WP_REST_Response(['promos' => $out]);
            },
        ]);
    }
}

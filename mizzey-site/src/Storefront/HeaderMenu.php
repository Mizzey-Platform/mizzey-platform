<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Storefront;

defined('ABSPATH') || exit;

/**
 * The links of the header's category row, the phone's chips and the drawer's offer row, read from the navigation menu
 * staff manage in the Site Editor (Appearance > Editor > Navigation), one menu per language through WPML (SSC-07,
 * NAV-07). Native first: the menu is WordPress's own `wp_navigation`; this class only reads it.
 *
 * - **Which menu.** Named, never guessed: the one chosen in the header's `menu` setting, or else the menu whose slug
 *   is `mizzey-header` (a menu titled "Mizzey header" gets it), each in the store's main language and followed to the
 *   request's language. Other menus (the footer's, a page's) are never taken, however recent. With no named menu,
 *   the top-level categories stand in (specs/054), so a new store still has a row.
 * - **An offer link.** A link given the "Offer" style in the menu editor (a block style registered here on the core
 *   navigation link) carries the Offers tag on desktop, leads the phone's chips and is the drawer's highlighted row.
 * - **A category link** carries its category's picture for the phone's chips, from the category in the store's main
 *   language, where specs/054 keeps it.
 *
 * Only plain links are read, in their order; a submenu's own links are read after its label link, flat, because the
 * designed row is one line. Labels are the menu's own words in each language (SSC-12).
 *
 * Register ids: NAV-07, SSC-07, SSC-12. See specs/066-stage-2-shop-front, slice 2.
 */
final class HeaderMenu
{
    public const OFFER_STYLE = 'offer';
    /** The slug that names the header's menu when the header block names none. */
    public const SLUG = 'mizzey-header';
    private const LINK_BLOCKS = ['core/navigation-link', 'core/navigation-submenu'];

    public static function register(): void
    {
        add_action('init', [self::class, 'offerStyle']);
    }

    /** The "Offer" style on the core navigation link, offered in the menu editor. */
    public static function offerStyle(): void
    {
        if (function_exists('register_block_style')) {
            register_block_style('core/navigation-link', ['name' => self::OFFER_STYLE, 'label' => __('Offer', 'mizzey-site')]);
        }
    }

    /**
     * The links, in the menu's order, in the request's language.
     *
     * @return list<array{label:string,url:string,offer:bool,image:string,category:bool,image_id:int}>
     */
    public static function links(int $menu = 0): array
    {
        $post = self::menu($menu);
        if (! $post instanceof \WP_Post) {
            return self::categoryLinks();
        }
        $links = [];
        self::collect(parse_blocks((string) $post->post_content), $links);

        return $links;
    }

    /** The menu in the request's language. */
    private static function menu(int $menu): ?\WP_Post
    {
        if ($menu <= 0) {
            // The menu named for the header, whatever the request's language (WPML would hide the original).
            $named = get_posts([
                'post_type'        => 'wp_navigation',
                'post_status'      => 'publish',
                'name'             => self::SLUG,
                'numberposts'      => 1,
                'suppress_filters' => true,
            ]);
            $menu = isset($named[0]) && $named[0] instanceof \WP_Post ? (int) $named[0]->ID : 0;
        }
        if ($menu <= 0) {
            return null;
        }
        $translated = (int) apply_filters('wpml_object_id', $menu, 'wp_navigation', true);
        $post       = get_post($translated > 0 ? $translated : $menu);

        return $post instanceof \WP_Post && 'wp_navigation' === $post->post_type && 'publish' === $post->post_status ? $post : null;
    }

    /**
     * @param array<int,array<string,mixed>>                               $blocks
     * @param list<array{label:string,url:string,offer:bool,image:string,category:bool,image_id:int}> $links
     */
    private static function collect(array $blocks, array &$links): void
    {
        foreach ($blocks as $block) {
            if (in_array($block['blockName'] ?? '', self::LINK_BLOCKS, true)) {
                $link = self::link((array) ($block['attrs'] ?? []));
                if (null !== $link) {
                    $links[] = $link;
                }
            }
            if (! empty($block['innerBlocks'])) {
                self::collect((array) $block['innerBlocks'], $links);
            }
        }
    }

    /**
     * @param array<string,mixed> $attrs
     * @return array{label:string,url:string,offer:bool,image:string,category:bool,image_id:int}|null
     */
    private static function link(array $attrs): ?array
    {
        $label = trim(wp_strip_all_tags(html_entity_decode((string) ($attrs['label'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $url   = (string) ($attrs['url'] ?? '');
        if ('' === $label || '' === $url) {
            return null;
        }
        $image    = '';
        $imageId  = 0;
        $category = false;
        if ('taxonomy' === ($attrs['kind'] ?? '') && (int) ($attrs['id'] ?? 0) > 0) {
            $term     = get_term((int) $attrs['id']);
            $category = $term instanceof \WP_Term && 'product_cat' === $term->taxonomy;
            $image    = self::termImage((int) $attrs['id']);
            $imageId  = $category ? CategoryImage::id((int) $attrs['id']) : 0;
        }
        $classes = preg_split('/\s+/', (string) ($attrs['className'] ?? '')) ?: [];

        return [
            'label'    => $label,
            'url'      => $url,
            'offer'    => in_array('is-style-' . self::OFFER_STYLE, $classes, true),
            'image'    => $image,
            // A category link shows its picture, or the placeholder when it has none (CategoryImage).
            'category' => $category,
            'image_id' => $imageId,
        ];
    }

    /** A category's picture, from the category in the store's main language (specs/054). */
    private static function termImage(int $termId): string
    {
        $term = get_term($termId);
        if (! $term instanceof \WP_Term || 'product_cat' !== $term->taxonomy) {
            return '';
        }
        $default  = (string) apply_filters('wpml_default_language', null);
        $original = '' !== $default ? (int) apply_filters('wpml_object_id', $termId, 'product_cat', true, $default) : 0;
        $image    = (int) get_term_meta($original > 0 ? $original : $termId, 'thumbnail_id', true);

        return $image > 0 ? (string) wp_get_attachment_image_url($image, 'thumbnail') : '';
    }

    /**
     * With no menu, the top-level categories (specs/054) stand in.
     *
     * @return list<array{label:string,url:string,offer:bool,image:string,category:bool,image_id:int}>
     */
    private static function categoryLinks(): array
    {
        return array_map(
            static fn (array $node): array => ['label' => (string) $node['name'], 'url' => (string) $node['url'], 'offer' => false, 'image' => (string) $node['image'], 'category' => true, 'image_id' => (int) $node['image_id']],
            HeaderData::categories()
        );
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Blocks;

defined('ABSPATH') || exit;

/**
 * The section library: the storefront's editable sections, each one complete block (spec 066, "The section library",
 * Feature Register G11).
 *
 * A section's structure, grid, spacing and responsive behaviour are fixed in its code; staff edit only what it
 * exposes. Each section is a dynamic block in its own directory beside this class, registered from its `block.json`,
 * with a plain editor script on WordPress's `wp.*` globals (there is no build step) and a PHP render that uses the
 * theme's tokens and components. What every section shares lives here:
 *
 * - **Sections only on the front page**: on the page set as the front page and on its translations, the editor
 *   offers only the library's sections; every other page is unchanged. In the post editor the server decides
 *   (`allowed_block_types_all`), also for a translation WPML is starting ("+" in the languages column), which is
 *   an auto-draft with no translation group yet but carries the group's `trid` on post-new.php. The Site Editor
 *   loads its settings once for every page and template it opens, so there library.js answers each insertion
 *   (`blockEditor.__unstableCanInsertBlockType`): only sections while the front page is the entity being edited.
 * - **"Show on the site"**: every section carries `visible`. A hidden section renders nothing on the storefront and
 *   keeps its content in the editor, where it is marked as hidden.
 * - **One category** in the inserter, and the editor script and style the sections share.
 *
 * Each language has its own content: the front page's Arabic translation is its own page, edited the same way
 * (WPML), and the storefront previews and publishes through WordPress's own preview.
 *
 * Register ids: SSC-01 (the section library pattern, G11). The sections' own rows are traced by the slice that
 * builds each section. See specs/066-stage-2-shop-front, slice 1.
 */
final class SectionLibrary
{
    /** The section directories beside this class; each holds a block.json. */
    public const SECTIONS = ['hero', 'category-shortcuts', 'products', 'featured-collections', 'deals', 'offer-banner', 'brand-highlights', 'trust-signals', 'signup'];
    public const CATEGORY = 'mizzey-sections';
    public const VISIBLE  = 'visible';
    public const EDITOR_SCRIPT = 'mizzey-section-library';
    public const EDITOR_STYLE  = 'mizzey-section-library-editor';
    public const SHARED_STYLE  = 'mizzey-home-sections';

    public static function register(): void
    {
        add_action('init', [self::class, 'registerBlocks']);
        add_filter('block_categories_all', [self::class, 'category']);
        add_filter('allowed_block_types_all', [self::class, 'allowedBlocks'], 10, 2);
        add_filter('pre_render_block', [self::class, 'hideHidden'], 10, 2);
        add_action('enqueue_block_editor_assets', [self::class, 'editorAssets']);
        // The hero's first image is the home page's Largest Contentful Paint: fetched from the head (slice 5, K-6).
        add_action('wp_head', [Hero::class, 'preload'], 2);
    }

    /** @return list<string> The library's block names. */
    public static function names(): array
    {
        return array_map(static fn (string $section): string => 'mizzey/' . $section, self::SECTIONS);
    }

    public static function registerBlocks(): void
    {
        $plugin  = dirname(__DIR__, 2) . '/mizzey-site.php';
        $version = static fn (string $file): string => is_file(__DIR__ . '/' . $file) ? (string) filemtime(__DIR__ . '/' . $file) : '0';

        wp_register_script(
            self::EDITOR_SCRIPT,
            plugins_url('src/Blocks/library.js', $plugin),
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-core-data', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n', 'wp-server-side-render'],
            $version('library.js'),
            true
        );
        wp_set_script_translations(self::EDITOR_SCRIPT, 'mizzey-site', dirname(__DIR__, 2) . '/languages');
        wp_register_style(self::EDITOR_STYLE, plugins_url('src/Blocks/library-editor.css', $plugin), [], $version('library-editor.css'));
        // What the home sections share on the storefront (the heading row, image tiles, the loading well): slice 5.
        wp_register_style(self::SHARED_STYLE, plugins_url('src/Blocks/library.css', $plugin), [], $version('library.css'));

        foreach (self::SECTIONS as $section) {
            register_block_type(__DIR__ . '/' . $section);
            // The editor script's words: the JSON files `wp i18n make-json` writes beside the plugin's catalogue.
            wp_set_script_translations(generate_block_asset_handle('mizzey/' . $section, 'editorScript'), 'mizzey-site', dirname(__DIR__, 2) . '/languages');
        }
    }

    /** The editor's data, as window.mizzeySections, only where an editor loads (not on every request). */
    public static function editorAssets(): void
    {
        wp_localize_script(self::EDITOR_SCRIPT, 'mizzeySections', self::editorData());
    }

    /**
     * What the editor scripts need from the server: the library's names, the front page in every language (for the
     * Site Editor), and the trust signals' marks drawn by the theme's own icon set, so the editor shows what the
     * storefront shows.
     *
     * @return array<string,mixed>
     */
    private static function editorData(): array
    {
        $marks = [];
        foreach (TrustSignals::MARKS as $mark) {
            $marks[$mark] = TrustSignals::markHtml($mark, false);
        }
        $logo = (int) get_theme_mod('custom_logo');
        $logo = $logo > 0 ? $logo : (int) get_option('site_logo');
        return [
            'blocks'     => self::names(),
            'frontPages' => self::frontPageIds(),
            'trustMarks' => $marks,
            'siteLogo'   => $logo > 0 ? (string) wp_get_attachment_image_url($logo, 'medium') : '',
            'minSeconds' => Hero::MIN_INTERVAL,
            'maxSlides'  => Hero::MAX_SLIDES,
            'maxCount'   => ProductSections::MAX,
        ];
    }

    /** @return list<int> The front page and its translations. */
    public static function frontPageIds(): array
    {
        $front = 'page' === get_option('show_on_front') ? (int) get_option('page_on_front') : 0;
        if ($front <= 0) {
            return [];
        }
        $ids   = [$front];
        $group = apply_filters('wpml_element_trid', null, $front, 'post_page');
        if ($group) {
            foreach ((array) apply_filters('wpml_get_element_translations', null, $group, 'post_page') as $translation) {
                $ids[] = (int) ($translation->element_id ?? 0);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param array<int,array<string,mixed>> $categories
     * @return array<int,array<string,mixed>>
     */
    public static function category(array $categories): array
    {
        array_unshift($categories, ['slug' => self::CATEGORY, 'title' => __('Mizzey sections', 'mizzey-site'), 'icon' => null]);
        return $categories;
    }

    /**
     * On the front page and its translations, the library's sections and nothing else; elsewhere, unchanged.
     *
     * @param bool|array<int,string> $allowed
     * @param mixed                  $context A WP_Block_Editor_Context.
     * @return bool|array<int,string>
     */
    public static function allowedBlocks($allowed, $context)
    {
        // The Site Editor's settings serve every entity it opens in one load: library.js narrows them there.
        if (is_object($context) && 'core/edit-site' === ($context->name ?? '')) {
            return $allowed;
        }
        $post = is_object($context) && isset($context->post) ? $context->post : null;
        if (! $post instanceof \WP_Post) {
            return $allowed;
        }
        return self::isFrontPage((int) $post->ID) || self::isNewFrontPageTranslation($post) ? self::names() : $allowed;
    }

    /**
     * A translation of the front page WPML is starting: an auto-draft page opened from the languages column, whose
     * request names the translation group (`trid`) it will join when first saved.
     */
    public static function isNewFrontPageTranslation(\WP_Post $post): bool
    {
        if ('auto-draft' !== $post->post_status || 'page' !== $post->post_type || 'page' !== get_option('show_on_front')) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, to choose the inserter's blocks.
        $group = isset($_GET['trid']) ? absint(wp_unslash($_GET['trid'])) : 0;
        $front = (int) get_option('page_on_front');
        return $group > 0 && $front > 0 && $group === (int) apply_filters('wpml_element_trid', null, $front, 'post_page');
    }

    /** The page set as the front page, in any language. */
    public static function isFrontPage(int $postId): bool
    {
        if ($postId <= 0 || 'page' !== get_option('show_on_front') || 'page' !== get_post_type($postId)) {
            return false;
        }
        $front = (int) get_option('page_on_front');
        if ($front <= 0) {
            return false;
        }
        if ($front === $postId) {
            return true;
        }
        // WPML: the front page in another language is a translation of the one set, in the same translation group.
        $group = apply_filters('wpml_element_trid', null, $front, 'post_page');
        return null !== $group && (int) $group > 0 && (int) $group === (int) apply_filters('wpml_element_trid', null, $postId, 'post_page');
    }

    /**
     * A hidden section renders nothing; its content stays in the page for the editor.
     *
     * @param string|null         $pre
     * @param array<string,mixed> $block
     */
    public static function hideHidden($pre, array $block): ?string
    {
        if (null !== $pre || ! in_array($block['blockName'] ?? '', self::names(), true)) {
            return $pre;
        }
        return false === ($block['attrs'][self::VISIBLE] ?? true) ? '' : null;
    }
}

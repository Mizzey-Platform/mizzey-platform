<?php

/**
 * What a storefront page loads before it can be used on a phone (spec 066, K-6; D-21), and the first tap.
 *
 * - **Early taps.** assets/js/early-taps.js, printed inline first in the head: a tap on one of our buttons before the
 *   Interactivity runtime has hydrated is held and replayed once it has, and on touch a button acts on the finger's
 *   lift. The signal is a last, empty region at the end of the body whose init (ui.js, callbacks.ready) runs after
 *   every region before it has hydrated.
 * - **Lean pages.** The block theme's own pages (the home page and content pages) do not use WooCommerce's classic
 *   scripts and styles: the jQuery add to cart, the general script, blockUI, js.cookie and the classic stylesheets.
 *   They are not loaded there, so jQuery and jQuery Migrate are not either unless something else on the page still
 *   asks for them. Every WooCommerce page (shop and category archives, product, cart, checkout, My Account) and any
 *   page holding a WooCommerce shortcode or classic block keeps them all.
 * - **Order attribution** (WooCommerce's, on when its setting is on) loads after the page has loaded on a lean page,
 *   in the same order, with the consent switch of the site plugin unchanged; elsewhere it is WooCommerce's.
 * - **Emoji.** WordPress's emoji detection script and styles are not printed on the storefront.
 *
 * Register ids: NFR-04 (performance, D-21). See specs/066-stage-2-shop-front, the checks every slice passes (K-6).
 *
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Theme\Performance;

defined('ABSPATH') || exit;

/** WooCommerce's classic storefront scripts, not used by the block theme's own pages. */
const CLASSIC_SCRIPTS = ['wc-add-to-cart', 'woocommerce', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie'];

/** Loaded after the page's load event on a lean page. */
const AFTER_LOAD = ['sourcebuster-js', 'wc-order-attribution'];

/** Classic blocks that print WooCommerce's classic templates. */
const CLASSIC_BLOCKS = ['woocommerce/classic-shortcode', 'woocommerce/legacy-template'];

/**
 * Whether this storefront page is one of the block theme's own, with nothing on it that needs WooCommerce's classic
 * scripts. Filterable (`mizzey_theme_lean_page`) for a page that turns out to need them.
 */
function lean(): bool
{
    if (is_admin() || wp_doing_ajax() || is_embed() || is_feed()) {
        return false;
    }
    $woo = (function_exists('is_woocommerce') && is_woocommerce())
        || (function_exists('is_cart') && is_cart())
        || (function_exists('is_checkout') && is_checkout())
        || (function_exists('is_account_page') && is_account_page())
        || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url());
    $lean = ! $woo && (is_front_page() || is_singular());
    if ($lean && is_singular()) {
        $content = (string) get_post_field('post_content', get_queried_object_id());
        $lean    = ! has_classic_woo($content);
    }

    return (bool) apply_filters('mizzey_theme_lean_page', $lean);
}

/** Whether content holds a WooCommerce shortcode or one of its classic blocks. */
function has_classic_woo(string $content): bool
{
    foreach (CLASSIC_BLOCKS as $block) {
        if (has_block($block, $content)) {
            return true;
        }
    }
    global $shortcode_tags;
    foreach ((array) $shortcode_tags as $tag => $callback) {
        $owner = is_array($callback) ? $callback[0] : $callback;
        $owner = is_object($owner) ? get_class($owner) : (string) $owner;
        if ((str_starts_with($owner, 'WC_') || str_starts_with((string) $tag, 'woocommerce_')) && has_shortcode($content, (string) $tag)) {
            return true;
        }
    }

    return false;
}

/** A script of assets/js as printed inline: without its header comment, its whitespace taken out. */
function script(string $name): string
{
    $file = get_theme_file_path('assets/js/' . $name);
    if (! is_file($file)) {
        return '';
    }
    $source = (string) file_get_contents($file);
    $source = (string) preg_replace('#^\s*/\*\*.*?\*/#s', '', $source);

    $source = (string) preg_replace('/\s+/', ' ', $source);

    // No space next to punctuation (the script holds no string with a space in it).
    return trim((string) preg_replace('/\s*([(){};,=:!&|<>?+\-])\s*/', '$1', $source));
}

add_action(
    'wp_head',
    static function (): void {
        $script = script('early-taps.js');
        if ('' !== $script) {
            wp_print_inline_script_tag($script, ['id' => 'mizzey-early-taps']);
        }
    },
    0
);

/* The page's last region: its init runs once every region before it has hydrated (ui.js, callbacks.ready). */
add_action(
    'wp_footer',
    static function (): void {
        echo '<div hidden data-wp-interactive="mizzey/ui" data-wp-init="callbacks.ready"></div>' . "\n";
    },
    PHP_INT_MAX
);

add_action(
    'init',
    static function (): void {
        if (is_admin()) {
            return;
        }
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles');
        remove_action('wp_print_styles', 'print_emoji_styles');
    }
);

add_filter(
    'woocommerce_enqueue_styles',
    static function ($styles) {
        return lean() ? [] : $styles;
    }
);

add_action(
    'wp_enqueue_scripts',
    static function (): void {
        if (! lean()) {
            return;
        }
        foreach (CLASSIC_SCRIPTS as $handle) {
            wp_dequeue_script($handle);
        }
        // WooCommerce's styles for its classic parts in a block theme, outside woocommerce_enqueue_styles.
        wp_dequeue_style('woocommerce-blocktheme');
    },
    100
);

/*
 * Speculative loading (WordPress core's speculation rules): a storefront link is prefetched when the visitor touches
 * it or rests the pointer on it ("moderate"), so the next page is often there before the click completes. Core keeps
 * it off for a signed-in visitor and leaves out every address with a query (add to cart included), the admin and the
 * sign-in page; the cart, the checkout and My Account (its sign-out endpoint included) are left out here, in each
 * language.
 */
add_filter(
    'wp_speculation_rules_configuration',
    static function ($config) {
        return is_array($config) ? ['mode' => 'prefetch', 'eagerness' => 'moderate'] : $config;
    }
);

add_filter(
    'wp_speculation_rules_href_exclude_paths',
    static function ($paths): array {
        return array_merge((array) $paths, no_prefetch_paths());
    }
);

/**
 * The address patterns of the cart, the checkout and My Account, in every language of the store.
 *
 * @return list<string>
 */
function no_prefetch_paths(): array
{
    if (! function_exists('wc_get_page_id')) {
        return [];
    }
    $languages = array_keys((array) apply_filters('wpml_active_languages', null, ['skip_missing' => 0]));
    $languages = [] === $languages ? [null] : $languages;
    $out       = [];
    foreach (['cart', 'checkout', 'myaccount'] as $page) {
        $id = (int) wc_get_page_id($page);
        if ($id <= 0) {
            continue;
        }
        foreach ($languages as $language) {
            $local = null === $language ? $id : (int) apply_filters('wpml_object_id', $id, 'page', true, $language);
            $url   = (string) get_permalink($local);
            $url   = null === $language ? $url : (string) apply_filters('wpml_permalink', $url, $language);
            $path  = (string) wp_parse_url($url, PHP_URL_PATH);
            if ('' !== $path && '/' !== $path) {
                $out[] = trailingslashit($path) . '*';
            }
        }
    }

    return array_values(array_unique($out));
}

/* Order attribution after the load event: the tag is kept, as a data block the loader below starts. */
add_filter(
    'script_loader_tag',
    static function (string $tag, string $handle, string $src): string {
        if (! in_array($handle, AFTER_LOAD, true) || ! lean()) {
            return $tag;
        }

        return (string) preg_replace_callback(
            '#<script\b([^>]*?)\ssrc=(["\'])[^"\']*\2([^>]*)></script>#',
            static fn (array $m): string => sprintf('<script type="text/plain" data-mizzey-after-load="%s"%s></script>', esc_url($src), $m[1] . $m[3]),
            $tag,
            1
        );
    },
    10,
    3
);

add_action(
    'wp_print_footer_scripts',
    static function (): void {
        if (! lean() || ! wp_script_is('wc-order-attribution', 'done')) {
            return;
        }
        $loader = script('after-load.js');
        if ('' !== $loader) {
            wp_print_inline_script_tag($loader, ['id' => 'mizzey-after-load']);
        }
    },
    100
);

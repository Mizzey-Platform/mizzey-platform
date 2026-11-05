<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Marketing;

defined('ABSPATH') || exit;

/**
 * Google Tag Manager, the only route a tag reaches the storefront by (MKT-01, MKT-23, INT-09).
 *
 * - **The container** is named in Settings, Marketing tags (`manage_woocommerce`). With no container named nothing
 *   is loaded but the data layer, so staging and a fresh install send nothing anywhere.
 * - **Consent first**: Google's consent mode defaults are set before the container loads, denied until the visitor
 *   chooses (`ConsentBanner`, MKT-10). The Meta and TikTok pixels (MKT-02, MKT-03) are tags inside the container,
 *   configured in the client's own Tag Manager account (HND-06); no pixel is written into the site's code.
 * - Every page's data layer carries `language` (`en` or `ar`), as the tracking plan asks (specs/042).
 *
 * Register ids: MKT-01, MKT-02, MKT-03. See specs/043-measurement-tools.
 */
final class TagManager
{
    public const CONTAINER = 'mizzey_gtm_container';
    public const PAGE      = 'mizzey-marketing-tags';

    public static function register(): void
    {
        add_action('wp_head', [self::class, 'head'], 1);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'settings']);
        add_filter('option_page_capability_' . self::PAGE, static fn (): string => \MizzeySite\Roles\MarketingRole::TRACKING);
    }

    /** The container id, or nothing when none (or a malformed one) is set. */
    public static function container(): string
    {
        $id = strtoupper(trim((string) get_option(self::CONTAINER, '')));

        return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) === 1 ? $id : '';
    }

    public static function language(): string
    {
        return (string) apply_filters('wpml_current_language', null) === 'ar' ? 'ar' : 'en';
    }

    private static function asset(string $file): string
    {
        $path = WP_PLUGIN_DIR . '/mizzey-site/assets/' . $file;

        return is_readable($path) ? (string) file_get_contents($path) : '';
    }

    public static function head(): void
    {
        if (is_admin()) {
            return;
        }
        wp_print_inline_script_tag('window.mizzeyLanguage=' . wp_json_encode(self::language()) . ';' . self::asset('consent-default.js'), ['id' => 'mizzey-consent-default']);
        $id = self::container();
        if ($id !== '') {
            wp_print_inline_script_tag(str_replace('__MIZZEY_GTM_ID__', esc_js($id), self::asset('gtm.js')), ['id' => 'mizzey-gtm']);
        }
    }

    public static function menu(): void
    {
        add_options_page(__('Marketing tags', 'mizzey-site'), __('Marketing tags', 'mizzey-site'), \MizzeySite\Roles\MarketingRole::TRACKING, self::PAGE, [self::class, 'render']);
    }

    public static function settings(): void
    {
        register_setting(self::PAGE, self::CONTAINER, [
            'type'              => 'string',
            'sanitize_callback' => static function ($value): string {
                $id = strtoupper(trim(sanitize_text_field((string) $value)));

                return preg_match('/^GTM-[A-Z0-9]{4,12}$/', $id) === 1 ? $id : '';
            },
            'default'           => '',
        ]);
    }

    public static function render(): void
    {
        if (! current_user_can(\MizzeySite\Roles\MarketingRole::TRACKING)) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Marketing tags', 'mizzey-site') . '</h1>';
        echo '<p>' . esc_html__('Every tracking tag (Google Analytics, the Meta and TikTok pixels) is added in Google Tag Manager, never in the site. Name the container here; leave it empty to load nothing.', 'mizzey-site') . '</p>';
        printf('<form method="post" action="%s">', esc_url(admin_url('options.php')));
        settings_fields(self::PAGE);
        printf(
            '<table class="form-table"><tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%1$s" value="%3$s" placeholder="GTM-XXXXXXX" /></td></tr></table>',
            esc_attr(self::CONTAINER),
            esc_html__('Tag Manager container', 'mizzey-site'),
            esc_attr(self::container())
        );
        do_action('mizzey_marketing_tags_fields');
        submit_button();
        echo '</form>';
        printf(
            '<h2>%s</h2><p>%s</p><ul><li><code>%s</code></li><li><code>%s</code></li></ul>',
            esc_html__('Product feeds', 'mizzey-site'),
            esc_html__('For Google Merchant Center and the Meta catalogue, one feed per language:', 'mizzey-site'),
            esc_html(ProductFeed::url('en')),
            esc_html(ProductFeed::url('ar'))
        );
        echo '</div>';
    }
}

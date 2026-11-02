<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Seo;

defined('ABSPATH') || exit;

/**
 * Settings, Search engines: the controls marketing staff use without a developer (#287).
 *
 * - **Redirects** (MKT-13) and **missing pages** (MKT-14): see `Redirects`.
 * - **Sitemap** (MKT-15): which kinds of content WordPress's own sitemap lists (pages, products, categories,
 *   collections, brands); an item marked "do not index" is left out on its own (`SeoFields`).
 * - **Robots file** (MKT-15): lines staff add to WordPress's own robots.txt, after its rules and the sitemap line.
 * - **Verification** (MKT-17): the Google Search Console and Bing Webmaster codes, printed on the home page.
 *
 * For staff who manage the store (`manage_woocommerce`). Every change needs the screen's nonce.
 *
 * Register ids: MKT-13, MKT-14, MKT-15, MKT-17. See specs/038-seo-controls.
 */
final class SearchSettings
{
    public const SLUG     = 'mizzey-search-engines';
    public const CAP      = 'manage_woocommerce';
    public const NONCE    = 'mizzey_search_engines';
    public const EXCLUDED = 'mizzey_sitemap_excluded';
    public const ROBOTS   = 'mizzey_robots_extra';
    public const GOOGLE   = 'mizzey_verify_google';
    public const BING     = 'mizzey_verify_bing';

    /** The kinds of content the sitemap may list: post types and taxonomies by name. */
    public const KINDS = ['page' => 'posts', 'product' => 'posts', 'product_cat' => 'taxonomies', 'product_collection' => 'taxonomies', 'product_brand' => 'taxonomies'];

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_mizzey_search_engines', [self::class, 'handle']);
        add_filter('wp_sitemaps_post_types', [self::class, 'postTypes']);
        add_filter('wp_sitemaps_taxonomies', [self::class, 'taxonomies']);
        add_filter('robots_txt', [self::class, 'robotsTxt'], 20, 2);
        add_action('wp_head', [self::class, 'verification'], 2);
        add_action('wp_head', [self::class, 'organisation'], 3);
    }

    public static function menu(): void
    {
        add_options_page(__('Search engines', 'mizzey-site'), __('Search engines', 'mizzey-site'), self::CAP, self::SLUG, [self::class, 'render']);
    }

    /** @return list<string> */
    public static function excluded(): array
    {
        $v = get_option(self::EXCLUDED, []);

        return is_array($v) ? array_values(array_intersect(array_keys(self::KINDS), $v)) : [];
    }

    /**
     * @param mixed $types
     * @return mixed
     */
    public static function postTypes($types)
    {
        return is_array($types) ? array_diff_key($types, array_flip(self::excluded())) : $types;
    }

    /**
     * @param mixed $taxonomies
     * @return mixed
     */
    public static function taxonomies($taxonomies)
    {
        return is_array($taxonomies) ? array_diff_key($taxonomies, array_flip(self::excluded())) : $taxonomies;
    }

    /**
     * @param mixed $output
     * @param mixed $public
     * @return mixed
     */
    public static function robotsTxt($output, $public)
    {
        $extra = trim((string) get_option(self::ROBOTS, ''));

        return $extra === '' || ! $public ? $output : rtrim((string) $output) . "\n\n" . $extra . "\n";
    }

    public static function verification(): void
    {
        if (! is_front_page()) {
            return;
        }
        foreach (['google-site-verification' => self::GOOGLE, 'msvalidate.01' => self::BING] as $name => $option) {
            $code = (string) get_option($option, '');
            if ($code !== '') {
                printf('<meta name="%s" content="%s" />' . "\n", esc_attr($name), esc_attr($code));
            }
        }
    }

    /** The store as an organisation (MKT-16), on the home page of each language. */
    public static function organisation(): void
    {
        if (! is_front_page()) {
            return;
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => wp_strip_all_tags((string) get_bloginfo('name')),
            'url'      => home_url('/'),
        ];
        $logo = (int) get_theme_mod('custom_logo') ?: (int) get_option('site_icon');
        $src  = $logo > 0 ? wp_get_attachment_image_url($logo, 'full') : false;
        if (is_string($src) && $src !== '') {
            $data['logo'] = $src;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>
";
    }

    /** Only letters, digits, underscores and hyphens: what both services issue. */
    public static function code(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    }

    /** A robots line is kept only when it is a directive robots.txt knows. */
    public static function robotsLines(string $text): string
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $kept  = array_filter(array_map('trim', $lines), static fn (string $l): bool => $l === '' || preg_match('/^(user-agent|allow|disallow|sitemap|crawl-delay)\s*:\s*\S/i', $l) === 1);

        return trim(implode("\n", array_map('sanitize_text_field', $kept)));
    }

    public static function handle(): void
    {
        if (! current_user_can(self::CAP) || ! check_admin_referer(self::NONCE)) {
            wp_die(esc_html__('You are not allowed to change these settings.', 'mizzey-site'), '', ['response' => 403]);
        }
        $task    = isset($_POST['task']) ? sanitize_key(wp_unslash((string) $_POST['task'])) : '';
        $message = 'saved';
        if ($task === 'redirect_add') {
            $error   = Redirects::save(isset($_POST['from']) ? (string) wp_unslash($_POST['from']) : '', isset($_POST['to']) ? (string) wp_unslash($_POST['to']) : ''); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalised and esc_url_raw in save().
            $message = $error === null ? 'saved' : 'refused';
        } elseif ($task === 'redirect_remove') {
            Redirects::remove(isset($_POST['from']) ? sanitize_text_field(wp_unslash((string) $_POST['from'])) : '');
        } elseif ($task === 'missing_clear') {
            update_option(Redirects::MISSING, [], false);
        } elseif ($task === 'settings') {
            $kinds = isset($_POST['listed']) && is_array($_POST['listed']) ? array_map('sanitize_key', wp_unslash($_POST['listed'])) : [];
            update_option(self::EXCLUDED, array_values(array_diff(array_keys(self::KINDS), $kinds)), false);
            update_option(self::ROBOTS, self::robotsLines(isset($_POST['robots']) ? (string) wp_unslash($_POST['robots']) : ''), false); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- robotsLines() sanitises each line.
            update_option(self::GOOGLE, self::code(isset($_POST['google']) ? (string) wp_unslash($_POST['google']) : ''), false); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- code() keeps only safe characters.
            update_option(self::BING, self::code(isset($_POST['bing']) ? (string) wp_unslash($_POST['bing']) : ''), false); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- code() keeps only safe characters.
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'mizzey' => $message], admin_url('options-general.php')));
        exit;
    }

    private static function formStart(string $task): void
    {
        printf('<form method="post" action="%s">', esc_url(admin_url('admin-post.php')));
        echo '<input type="hidden" name="action" value="mizzey_search_engines" />';
        printf('<input type="hidden" name="task" value="%s" />', esc_attr($task));
        wp_nonce_field(self::NONCE);
    }

    public static function render(): void
    {
        if (! current_user_can(self::CAP)) {
            return;
        }
        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('Search engines', 'mizzey-site'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a message after a redirect.
        $msg = isset($_GET['mizzey']) ? sanitize_key(wp_unslash((string) $_GET['mizzey'])) : '';
        if ($msg !== '') {
            printf('<div class="notice notice-%s"><p>%s</p></div>', $msg === 'refused' ? 'error' : 'success', esc_html($msg === 'refused' ? __('The redirect was not saved: give the old path and a full new address, not the same page.', 'mizzey-site') : __('Saved.', 'mizzey-site')));
        }

        printf('<h2>%s</h2>', esc_html__('Redirects', 'mizzey-site'));
        self::formStart('redirect_add');
        printf('<p><label>%s <input type="text" name="from" placeholder="/old-page" /></label> <label>%s <input type="text" name="to" class="regular-text" placeholder="/new-page or https://..." /></label> ', esc_html__('Old path', 'mizzey-site'), esc_html__('New address', 'mizzey-site'));
        submit_button(__('Add permanent redirect', 'mizzey-site'), 'primary', 'submit', false);
        echo '</p></form>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Old path', 'mizzey-site') . '</th><th>' . esc_html__('New address', 'mizzey-site') . '</th><th>' . esc_html__('Used', 'mizzey-site') . '</th><th></th></tr></thead><tbody>';
        foreach (Redirects::all() as $from => $r) {
            echo '<tr><td>' . esc_html($from) . '</td><td>' . esc_html($r['to']) . '</td><td>' . esc_html(number_format_i18n((int) $r['hits'])) . '</td><td>';
            self::formStart('redirect_remove');
            printf('<input type="hidden" name="from" value="%s" />', esc_attr($from));
            submit_button(__('Remove', 'mizzey-site'), 'link', 'submit', false);
            echo '</form></td></tr>';
        }
        echo '</tbody></table>';

        printf('<h2>%s</h2>', esc_html__('Missing pages', 'mizzey-site'));
        $missing = Redirects::missing();
        uasort($missing, static fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Path', 'mizzey-site') . '</th><th>' . esc_html__('Times', 'mizzey-site') . '</th><th>' . esc_html__('Last asked', 'mizzey-site') . '</th><th>' . esc_html__('Came from', 'mizzey-site') . '</th></tr></thead><tbody>';
        foreach (array_slice($missing, 0, 100, true) as $path => $m) {
            printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html($path), esc_html(number_format_i18n((int) $m['hits'])), esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $m['last'])), esc_html((string) $m['referrer']));
        }
        echo '</tbody></table>';
        self::formStart('missing_clear');
        submit_button(__('Clear the list', 'mizzey-site'), 'secondary', 'submit', false);
        echo '</form>';

        printf('<h2>%s</h2>', esc_html__('Sitemap, robots file and verification', 'mizzey-site'));
        self::formStart('settings');
        printf('<p>%s</p><p>', esc_html__('The sitemap lists:', 'mizzey-site'));
        $labels = ['page' => __('Pages', 'mizzey-site'), 'product' => __('Products', 'mizzey-site'), 'product_cat' => __('Categories', 'mizzey-site'), 'product_collection' => __('Collections', 'mizzey-site'), 'product_brand' => __('Brands', 'mizzey-site')];
        foreach ($labels as $key => $label) {
            printf('<label style="margin-inline-end:16px"><input type="checkbox" name="listed[]" value="%s"%s /> %s</label>', esc_attr($key), checked(! in_array($key, self::excluded(), true), true, false), esc_html($label));
        }
        printf('</p><p><a href="%s">%s</a></p>', esc_url(home_url('/wp-sitemap.xml')), esc_html__('Open the sitemap', 'mizzey-site'));
        printf('<p><label>%s<br /><textarea name="robots" rows="5" cols="70">%s</textarea></label><br /><span class="description">%s</span></p>', esc_html__('Extra robots.txt lines', 'mizzey-site'), esc_textarea((string) get_option(self::ROBOTS, '')), esc_html__('User-agent, Allow, Disallow, Sitemap or Crawl-delay lines only.', 'mizzey-site'));
        printf('<p><label>%s <input type="text" name="google" value="%s" class="regular-text" /></label></p>', esc_html__('Google Search Console verification code', 'mizzey-site'), esc_attr((string) get_option(self::GOOGLE, '')));
        printf('<p><label>%s <input type="text" name="bing" value="%s" class="regular-text" /></label></p>', esc_html__('Bing Webmaster verification code', 'mizzey-site'), esc_attr((string) get_option(self::BING, '')));
        submit_button(__('Save', 'mizzey-site'));
        echo '</form></div>';
    }
}

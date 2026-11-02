<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\I18n;

defined('ABSPATH') || exit;

/**
 * The records and settings the Arabic storefront needs, made on any install, idempotently.
 *
 * The measured gap (audit of 2 November 2026). What makes `/ar/` work was written only in the test baseline
 * (`tests/integration/baseline/setup.php` and `ia-endpoints.php`), which staging replays: the Arabic records of the
 * WooCommerce system pages and of the contracted pages, the static front page, the terms page, the brand and
 * collection taxonomies registered as translatable, and the Arabic account endpoint slugs. A clean production
 * install would have served the Arabic brand and collection archives untranslated and kept English endpoint slugs.
 * The baseline now calls this class, and `wp mizzey languages provision` runs it at release.
 *
 * Every step keeps what is already there: an existing record, a front page or terms page already chosen, and an
 * endpoint translation already made (the client's wording) are never replaced. Page copy is content, never written
 * here: a new page is empty, except the order-tracking page, which holds WooCommerce's tracking form (IA-21).
 *
 * Status. A contracted page this class makes is a draft, marked as its own (`PROVISIONED`); `--publish` publishes
 * only those drafts, and an Arabic one only when its English record is published. A status staff set (private,
 * pending, a draft of their own) is never changed. The Arabic record of a WooCommerce system page carries
 * WooCommerce's blocks rather than copy, so it takes its English record's status when that is published.
 *
 * Records are matched by language: a page found by its slug in Arabic stands for its English record only through
 * its translation group, and an Arabic page with no English record is reported, never taken for English.
 *
 * The invariant of #241 (A11) throughout: every source record exists first, and the Arabic records are made in their
 * own pass, with the language switched before the insert so WPML does not suffix the slug.
 *
 * Register ids: IA-01 to IA-35, FIX-04. See specs/003-information-architecture-urls.
 */
final class LanguageSetup
{
    public const LANGUAGE = 'ar';

    /** The contracted pages that need a page record, slug => register id. Titles are in titles(). */
    public const PAGES = [
        'home'                   => 'IA-01',
        'wishlist'               => 'IA-11',
        'track-your-order'       => 'IA-21',
        'about'                  => 'IA-24',
        'contact-us'             => 'IA-25',
        'help'                   => 'IA-26',
        'authenticity-guarantee' => 'IA-27',
        'shipping-policy'        => 'IA-28',
        'returns-and-refunds'    => 'IA-29',
        'privacy-policy'         => 'IA-30',
        'terms-and-conditions'   => 'IA-31',
        'offers'                 => 'IA-32',
    ];

    /** The WooCommerce pages whose Arabic records the storefront needs (#241, C-2). */
    public const SYSTEM_PAGES = ['shop', 'cart', 'checkout', 'myaccount'];

    /** The taxonomies the storefront serves per language beside WooCommerce's own (IA-04, IA-05, IA-35). */
    public const TAXONOMIES = ['product_brand', 'product_collection'];

    /**
     * Working Arabic endpoint slugs, WooCommerce query var => slug: placeholders (D-10) until the client gives the
     * wording, which is then made in WPML's string translation and kept by this class.
     */
    public const ENDPOINTS = [
        'order-received'  => 'order-received-ar',
        'orders'          => 'orders-ar',
        'view-order'      => 'view-order-ar',
        'edit-address'    => 'edit-address-ar',
        'lost-password'   => 'lost-password-ar',
        'edit-account'    => 'edit-account-ar',
        'payment-methods' => 'payment-methods-ar',
        'customer-logout' => 'customer-logout-ar',
        'downloads'       => 'downloads-ar',
        'order-pay'       => 'order-pay-ar',
    ];

    /** The string context WPML and WooCommerce Multilingual keep endpoint slugs under. */
    private const ENDPOINT_CONTEXT = 'WP' . ' ' . 'Endpoints';

    /** Post meta marking a page this class made, so `--publish` touches no page staff made or set. */
    public const PROVISIONED = '_mizzey_provisioned';

    /**
     * Runs every step and returns what each found or made.
     *
     * @param bool                         $publish Publish a new page, and an existing draft of a contracted page;
     *                                              otherwise a new page is a draft.
     * @param null|callable(string):string $body    The body of a new page by slug (the test baseline's
     *                                              placeholders); none by default.
     *
     * @return array<string, mixed>
     */
    public static function provision(bool $publish = false, ?callable $body = null): array
    {
        if (! self::ready()) {
            return ['error' => 'wpml-not-ready'];
        }
        $report                   = [];
        $report['system_pages']   = self::systemPages();
        $report['pages']          = self::pages($publish, $body);
        $report['front_page']     = self::frontPage($report['pages']['en']['home'] ?? 0);
        $report['terms_page']     = self::termsPage($report['pages']['en']['terms-and-conditions'] ?? 0);
        $report['taxonomies']     = self::taxonomies();
        $report['endpoints']      = self::endpoints();
        flush_rewrite_rules(false);

        return $report;
    }

    /** WPML is active and set up, with Arabic among its languages. */
    public static function ready(): bool
    {
        global $sitepress;

        return $sitepress instanceof \SitePress
            && (bool) $sitepress->get_setting('setup_complete')
            && array_key_exists(self::LANGUAGE, (array) $sitepress->get_active_languages());
    }

    /**
     * Arabic records of the WooCommerce system pages, and WooCommerce's page options set per language.
     *
     * @return array{en: array<string, int>, ar: array<string, int>, missing: list<string>}
     */
    public static function systemPages(): array
    {
        global $sitepress;
        $sources = [];
        $missing = [];
        foreach (self::SYSTEM_PAGES as $slug) {
            $id = (int) get_option('woocommerce_' . $slug . '_page_id');
            if ($id > 0 && get_post_type($id) === 'page') {
                $sources[$slug] = $id;
            } else {
                $missing[] = $slug;
            }
        }
        $arabic = [];
        foreach ($sources as $slug => $source) {
            // No copy to write: the Arabic record goes live with its English record.
            $id = self::arabicRecord($source, get_post_status($source) === 'publish' ? 'publish' : 'draft', false);
            if ($id > 0) {
                $arabic[$slug] = $id;
            } else {
                $missing[] = $slug . ' (' . self::LANGUAGE . ')';
            }
        }
        // WPML keeps a per-language copy of an option it translates, and WCML registers WooCommerce's page options:
        // writing the option while Arabic is current sets the Arabic copy.
        if ($arabic !== []) {
            $previous = $sitepress->get_current_language();
            $sitepress->switch_lang(self::LANGUAGE, true);
            foreach ($arabic as $slug => $id) {
                update_option('woocommerce_' . $slug . '_page_id', $id);
            }
            $sitepress->switch_lang($previous, true);
            foreach ($sources as $slug => $id) {
                update_option('woocommerce_' . $slug . '_page_id', $id);
            }
        }

        return ['en' => $sources, 'ar' => $arabic, 'missing' => $missing];
    }

    /**
     * The contracted pages in English, then their Arabic records.
     *
     * @param bool                         $publish Publish this class's own drafts (see the class note).
     * @param null|callable(string):string $body
     *
     * @return array{en: array<string, int>, ar: array<string, int>, drafts: list<string>, failed: list<string>, conflicts: list<string>}
     */
    public static function pages(bool $publish, ?callable $body = null): array
    {
        global $sitepress;
        $english   = [];
        $failed    = [];
        $conflicts = [];
        $titles    = self::titles();
        foreach (array_keys(self::PAGES) as $slug) {
            $found = self::englishRecord($slug);
            if ($found === -1) {
                $conflicts[] = $slug;
                continue;
            }
            if ($found > 0) {
                if ($publish) {
                    self::publishOwnDraft($found);
                }
                $english[$slug] = $found;
                continue;
            }
            $id = wp_insert_post([
                'post_type'    => 'page',
                'post_status'  => $publish ? 'publish' : 'draft',
                'post_title'   => $titles[$slug] ?? $slug,
                'post_name'    => $slug,
                'post_content' => self::body($slug, $body),
                'meta_input'   => [self::PROVISIONED => 1],
            ], true);
            if (is_wp_error($id) || (int) $id === 0) {
                $failed[] = $slug;
                continue;
            }
            // WPML attaches a language on save_post, which an insert from a command does not always reach.
            $sitepress->set_element_language_details((int) $id, 'post_page', null, 'en');
            $english[$slug] = (int) $id;
        }
        foreach ($english as $id) {
            if (! $sitepress->get_element_trid($id, 'post_page')) {
                $sitepress->set_element_language_details($id, 'post_page', null, 'en');
            }
        }

        $arabic = [];
        foreach ($english as $slug => $source) {
            $live = $publish && get_post_status($source) === 'publish';
            $id   = self::arabicRecord($source, $live ? 'publish' : 'draft', $live);
            if ($id > 0) {
                $arabic[$slug] = $id;
            } else {
                $failed[] = $slug . ' (' . self::LANGUAGE . ')';
            }
        }

        $drafts = [];
        foreach (['en' => $english, self::LANGUAGE => $arabic] as $language => $records) {
            foreach ($records as $slug => $id) {
                if (get_post_status($id) !== 'publish') {
                    $drafts[] = $slug . ' (' . $language . ')';
                }
            }
        }

        return ['en' => $english, 'ar' => $arabic, 'drafts' => $drafts, 'failed' => $failed, 'conflicts' => $conflicts];
    }

    /**
     * What a provisioning report says could not be made. Empty when everything is in place.
     *
     * @param array<string, mixed> $report What provision() returned.
     *
     * @return list<string>
     */
    public static function problems(array $report): array
    {
        if (isset($report['error'])) {
            return [(string) $report['error']];
        }
        $problems = [];
        foreach ((array) ($report['system_pages']['missing'] ?? []) as $slug) {
            $problems[] = 'system-page=' . $slug;
        }
        foreach ((array) ($report['pages']['failed'] ?? []) as $slug) {
            $problems[] = 'page=' . $slug;
        }
        foreach ((array) ($report['pages']['conflicts'] ?? []) as $slug) {
            $problems[] = 'arabic-only-page=' . $slug;
        }
        foreach (array_keys(self::PAGES) as $slug) {
            if (! isset($report['pages']['en'][$slug], $report['pages']['ar'][$slug])) {
                $problems[] = 'incomplete-page=' . $slug;
            }
        }
        foreach (self::TAXONOMIES as $taxonomy) {
            if (empty($report['taxonomies'][$taxonomy])) {
                $problems[] = 'taxonomy=' . $taxonomy;
            }
        }
        foreach ((array) ($report['endpoints']['missing'] ?? []) as $key) {
            $problems[] = 'endpoint=' . $key;
        }

        return array_values(array_unique($problems));
    }

    /**
     * A static front page (IA-01), unless one is already chosen.
     *
     * @return array{show_on_front: string, page_on_front: int, changed: bool}
     */
    public static function frontPage(int $home): array
    {
        $chosen  = get_option('show_on_front') === 'page' && get_post_type((int) get_option('page_on_front')) === 'page';
        $changed = false;
        if (! $chosen && $home > 0) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $home);
            $changed = true;
        }

        return ['show_on_front' => (string) get_option('show_on_front'), 'page_on_front' => (int) get_option('page_on_front'), 'changed' => $changed];
    }

    /**
     * WooCommerce's terms page (IA-31), unless one is already chosen.
     *
     * @return array{page: int, changed: bool}
     */
    public static function termsPage(int $terms): array
    {
        $current = (int) get_option('woocommerce_terms_page_id');
        $changed = false;
        if (get_post_type($current) !== 'page' && $terms > 0) {
            update_option('woocommerce_terms_page_id', $terms);
            $changed = true;
        }

        return ['page' => (int) get_option('woocommerce_terms_page_id'), 'changed' => $changed];
    }

    /**
     * Brand and collection terms translatable, as `wpml-config.xml` also declares.
     *
     * @return array<string, bool> Taxonomy => translatable now.
     */
    public static function taxonomies(): array
    {
        global $sitepress;
        $helper = function_exists('wpml_load_settings_helper') ? wpml_load_settings_helper() : null;
        $result = [];
        foreach (self::TAXONOMIES as $taxonomy) {
            if (! taxonomy_exists($taxonomy)) {
                continue;
            }
            if ($helper !== null && ! $sitepress->is_translated_taxonomy($taxonomy)) {
                $helper->set_taxonomy_translatable($taxonomy);
                $helper->set_taxonomy_translation_unlocked_option($taxonomy, false);
            }
            $result[$taxonomy] = (bool) $sitepress->is_translated_taxonomy($taxonomy);
        }

        return $result;
    }

    /**
     * Arabic account and checkout endpoint slugs, through WPML string translation as WooCommerce Multilingual reads
     * them. An endpoint string is registered the way WooCommerce Multilingual registers it, so no admin page has to
     * be opened first. A translation already made is kept.
     *
     * @return array{made: array<string, string>, kept: array<string, string>, missing: list<string>}
     */
    public static function endpoints(): array
    {
        $result = ['made' => [], 'kept' => [], 'missing' => []];
        if (! function_exists('icl_get_string_id') || ! function_exists('icl_add_string_translation') || ! defined('ICL_TM_COMPLETE') || ! function_exists('WC')) {
            $result['missing'] = array_keys(self::ENDPOINTS);

            return $result;
        }
        // The stored slugs, before WooCommerce Multilingual translates them for the current language.
        $vars = (array) WC()->query->query_vars;
        /** @var array<string, string> $slugs */
        $slugs = (array) apply_filters('mizzey_arabic_endpoint_slugs', self::ENDPOINTS);
        foreach ($slugs as $key => $arabic) {
            $endpoint = (string) ($vars[$key] ?? $key);
            $id       = (int) icl_get_string_id($endpoint, self::ENDPOINT_CONTEXT, $key);
            if ($id === 0) {
                do_action('wpml_register_single_string', self::ENDPOINT_CONTEXT, $key, $endpoint);
                wp_cache_delete(self::ENDPOINT_CONTEXT, 'WCML_Endpoints');
                wp_cache_delete(self::ENDPOINT_CONTEXT, 'WPML_Endpoints_Support');
                $id = (int) icl_get_string_id($endpoint, self::ENDPOINT_CONTEXT, $key);
            }
            if ($id === 0) {
                $result['missing'][] = (string) $key;
                continue;
            }
            $current = (string) apply_filters('wpml_translate_single_string', $endpoint, self::ENDPOINT_CONTEXT, $key, self::LANGUAGE);
            if ($current !== $endpoint && $current !== '') {
                $result['kept'][$key] = $current;
                continue;
            }
            icl_add_string_translation($id, self::LANGUAGE, sanitize_title((string) $arabic), ICL_TM_COMPLETE);
            $result['made'][$key] = sanitize_title((string) $arabic);
        }

        return $result;
    }

    /**
     * The Arabic record of an English page, made if missing, with the same slug and title.
     *
     * @param string $status          The status of a new record.
     * @param bool   $publishOwnDraft Publish an existing Arabic record that is this class's own draft.
     */
    private static function arabicRecord(int $source, string $status, bool $publishOwnDraft): int
    {
        global $sitepress;
        $trid = $sitepress->get_element_trid($source, 'post_page');
        if (! $trid) {
            return 0;
        }
        $group = $sitepress->get_element_translations($trid, 'post_page');
        if (isset($group[self::LANGUAGE])) {
            $id = (int) $group[self::LANGUAGE]->element_id;
            if ($id === $source) {
                // A record cannot be its own counterpart.
                return 0;
            }
            if ($publishOwnDraft) {
                self::publishOwnDraft($id);
            }

            return $id;
        }
        // The language is switched before the insert: WPML allows a shared slug only for a known translation, and an
        // insert made first would get `shop-2` and a redirect (measured by t21).
        $previous = $sitepress->get_current_language();
        $sitepress->switch_lang(self::LANGUAGE, true);
        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => $status,
            'post_title'   => get_the_title($source),
            'post_name'    => (string) get_post_field('post_name', $source),
            'post_content' => (string) get_post_field('post_content', $source),
            'meta_input'   => [self::PROVISIONED => 1],
        ], true);
        $sitepress->switch_lang($previous, true);
        if (is_wp_error($id) || (int) $id === 0) {
            return 0;
        }
        $sitepress->set_element_language_details((int) $id, 'post_page', $trid, self::LANGUAGE, $sitepress->get_language_for_element($source, 'post_page'));

        return (int) $id;
    }

    /**
     * The English record of a contracted page by its slug: its id, 0 when there is none, or -1 when the slug belongs
     * only to an Arabic record with no English one (reported, never taken for English).
     */
    private static function englishRecord(string $slug): int
    {
        global $sitepress;
        $ids = get_posts([
            'post_type'        => 'page',
            'name'             => $slug,
            // The contracted pages are top level: a nested page with the same slug (/company/about/) is another page.
            'post_parent'      => 0,
            'post_status'      => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts'      => 10,
            'fields'           => 'ids',
            'suppress_filters' => true,
            'orderby'          => 'ID',
            'order'            => 'ASC',
        ]);
        foreach ($ids as $id) {
            $language = $sitepress->get_language_for_element((int) $id, 'post_page');
            if ($language === 'en' || $language === null || $language === '') {
                return (int) $id;
            }
        }
        $orphan = false;
        foreach ($ids as $id) {
            $trid  = $sitepress->get_element_trid((int) $id, 'post_page');
            $group = $trid ? $sitepress->get_element_translations($trid, 'post_page') : [];
            if (isset($group['en']) && (int) $group['en']->element_id !== (int) $id) {
                return (int) $group['en']->element_id;
            }
            $orphan = true;
        }

        return $orphan ? -1 : 0;
    }

    /** Publish a page only when it is a draft this class made. */
    private static function publishOwnDraft(int $id): void
    {
        if (get_post_status($id) === 'draft' && (int) get_post_meta($id, self::PROVISIONED, true) === 1) {
            wp_update_post(['ID' => $id, 'post_status' => 'publish']);
        }
    }

    /**
     * The English titles of new pages. The Arabic record starts with the same title: Arabic copy is content.
     *
     * @return array<string, string>
     */
    private static function titles(): array
    {
        return [
            'home'                   => __('Home', 'mizzey-site'),
            'wishlist'               => __('Wishlist', 'mizzey-site'),
            'track-your-order'       => __('Track your order', 'mizzey-site'),
            'about'                  => __('About', 'mizzey-site'),
            'contact-us'             => __('Contact us', 'mizzey-site'),
            'help'                   => __('Help and FAQ', 'mizzey-site'),
            'authenticity-guarantee' => __('Authenticity guarantee', 'mizzey-site'),
            'shipping-policy'        => __('Shipping policy', 'mizzey-site'),
            'returns-and-refunds'    => __('Return and refund policy', 'mizzey-site'),
            'privacy-policy'         => __('Privacy policy', 'mizzey-site'),
            'terms-and-conditions'   => __('Terms and conditions', 'mizzey-site'),
            'offers'                 => __('Offers', 'mizzey-site'),
        ];
    }

    /** @param null|callable(string):string $body */
    private static function body(string $slug, ?callable $body): string
    {
        if ($slug === 'track-your-order') {
            return '<!-- wp:shortcode -->' . '[woocommerce_order_tracking]' . '<!-- /wp:shortcode -->';
        }

        return $body !== null ? (string) $body($slug) : '';
    }
}

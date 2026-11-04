<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Search;

defined('ABSPATH') || exit;

/**
 * The curated synonym and correction list (SRCH-06, SRCH-07), managed by staff in WooCommerce > Search synonyms.
 *
 * One group per line: every way a shopper may write one thing, separated by commas, in English, in Arabic and in
 * Franco-Arabic, misspellings included, for example `lipstick, lip stick, روج, احمر شفاه, ahmar shafayef`. A search for
 * any form of a group finds what any other form finds. A form may be several words (`hair dryer, سشوار, seshwar`).
 * Forms are compared normalised (`Normalise`), so a group need not repeat the alef or taa marbuta variants.
 *
 * The list is the client's content (D-27: its first entries are a content input); it starts empty. Coverage is what it
 * contains: there is no automatic typo tolerance (Annex A section 1.2).
 *
 * Register ids: SRCH-06, SRCH-07. See specs/058-search.
 */
final class Synonyms
{
    public const OPTION = 'mizzey_search_synonyms';
    private const PAGE  = 'mizzey-search-synonyms';
    private const NONCE = 'mizzey_search_synonyms';

    /** @var list<list<string>>|null */
    private static ?array $groups = null;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 60);
        add_action('admin_post_mizzey_search_synonyms', [self::class, 'save']);
        add_action('update_option_' . self::OPTION, static function (): void {
            self::$groups = null;
        });
    }

    /**
     * The groups, each a list of normalised forms, two forms or more.
     *
     * @return list<list<string>>
     */
    public static function groups(): array
    {
        if (self::$groups !== null) {
            return self::$groups;
        }

        return self::$groups = self::parse((string) get_option(self::OPTION, ''));
    }

    /**
     * @return list<list<string>>
     */
    public static function parse(string $text): array
    {
        $groups = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $forms = array_values(array_unique(array_filter(array_map([Normalise::class, 'text'], explode(',', str_replace('،', ',', $line))), 'strlen')));
            if (count($forms) >= 2) {
                $groups[] = $forms;
            }
        }

        return $groups;
    }

    /** Clears what was read, for a list changed during this request. */
    public static function forget(): void
    {
        self::$groups = null;
    }

    /**
     * Every form a search should also look for: for each group with a form found in the normalised query as whole
     * words, the query with that form replaced by each of the group's other forms.
     *
     * @return list<string> Normalised alternatives to the query, the query itself not included.
     */
    public static function alternatives(string $query): array
    {
        $query = Normalise::text($query);
        if ($query === '') {
            return [];
        }
        $padded = ' ' . $query . ' ';
        $out    = [];
        foreach (self::groups() as $forms) {
            foreach ($forms as $form) {
                if (! str_contains($padded, ' ' . $form . ' ')) {
                    continue;
                }
                foreach ($forms as $other) {
                    if ($other !== $form) {
                        $out[] = trim(str_replace(' ' . $form . ' ', ' ' . $other . ' ', $padded));
                    }
                }
            }
        }

        return array_values(array_unique(array_diff($out, [$query])));
    }

    public static function menu(): void
    {
        add_submenu_page('woocommerce', __('Search synonyms', 'mizzey-site'), __('Search synonyms', 'mizzey-site'), 'manage_woocommerce', self::PAGE, [self::class, 'screen']);
    }

    public static function screen(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }
        $saved = isset($_GET['saved']) ? (int) $_GET['saved'] : -1; // phpcs:ignore WordPress.Security.NonceVerification
        echo '<div class="wrap"><h1>' . esc_html__('Search synonyms', 'mizzey-site') . '</h1>';
        if ($saved >= 0) {
            echo '<div class="notice notice-success"><p>' . esc_html(sprintf(
                /* translators: %d: number of groups. */
                _n('Saved: %d group.', 'Saved: %d groups.', $saved, 'mizzey-site'),
                $saved,
            )) . '</p></div>';
        }
        echo '<p>' . esc_html__('One group per line: every way a shopper may write one thing, separated by commas, in English, Arabic and Franco-Arabic, misspellings included. A search for any of them finds what the others find. Example: lipstick, lip stick, روج, احمر شفاه, ahmar shafayef', 'mizzey-site') . '</p>';
        echo '<p>' . esc_html__('Searches already treat the forms of alef, taa marbuta and alef maqsura as the same, and ignore short vowels, so they need not be listed. Only what is listed here is corrected: there is no automatic typo correction.', 'mizzey-site') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mizzey_search_synonyms">';
        wp_nonce_field(self::NONCE);
        echo '<textarea name="mizzey_search_synonyms" rows="20" class="large-text code" dir="auto">' . esc_textarea((string) get_option(self::OPTION, '')) . '</textarea>';
        submit_button(__('Save synonyms', 'mizzey-site'));
        echo '</form></div>';
    }

    public static function save(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to change the search synonyms.', 'mizzey-site'), '', ['response' => 403]);
        }
        check_admin_referer(self::NONCE);
        $text = isset($_POST['mizzey_search_synonyms']) ? sanitize_textarea_field(wp_unslash($_POST['mizzey_search_synonyms'])) : '';
        $groups = self::store($text);
        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'saved' => $groups], admin_url('admin.php')));
        exit;
    }

    /** Keeps the list as written, one group per line, empty lines dropped. Returns the number of groups. */
    public static function store(string $text): int
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $forms = array_values(array_unique(array_filter(array_map('trim', explode(',', str_replace('،', ',', $line))), 'strlen')));
            if ($forms !== []) {
                $lines[] = implode(', ', $forms);
            }
        }
        update_option(self::OPTION, implode("\n", $lines), false);
        self::$groups = null;

        return count(self::parse(implode("\n", $lines)));
    }
}

<?php

/**
 * @package MizzeySite
 */

declare(strict_types=1);

namespace MizzeySite\Signups;

defined('ABSPATH') || exit;

/**
 * WooCommerce, Sign-ups: the home page's sign-up list, read-only. Staff search it by address and see how many match
 * and how many of those are subscribed; each row shows the address, its language, when it signed up, whether it agreed
 * to news and offers (with the version of the words), and its status.
 *
 * Nothing here changes, exports or sends anything (the owner's ruling of 4 November 2026: no export features; DECISIONS
 * A10: no campaign engine). It is for the roles that manage the store's customers (`manage_woocommerce`: the owner,
 * administrators and shop managers); the agency's Marketing role has no customer data (MKT-22, specs/064).
 *
 * Register id: HOME-09. See specs/066-stage-2-shop-front, slice 5.
 */
final class SignupScreen
{
    public const SLUG     = 'mizzey-signups';
    public const CAP      = 'manage_woocommerce';
    public const PER_PAGE = 50;

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 60);
    }

    public static function menu(): void
    {
        add_submenu_page('woocommerce', __('Sign-ups', 'mizzey-site'), __('Sign-ups', 'mizzey-site'), self::CAP, self::SLUG, [self::class, 'render']);
    }

    public static function render(): void
    {
        if (! current_user_can(self::CAP)) {
            wp_die(esc_html__('You are not allowed to see the sign-up list.', 'mizzey-site'), '', ['response' => 403]);
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only screen.
        $words = isset($_GET['s']) && is_string($_GET['s']) ? mb_substr(sanitize_text_field(wp_unslash($_GET['s'])), 0, 100) : '';
        $page  = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        // phpcs:enable
        $count = SignupStore::count($words);
        $rows  = SignupStore::search($words, $page, self::PER_PAGE);

        echo '<div class="wrap mizzey-signups">';
        printf('<h1>%s</h1>', esc_html__('Sign-ups', 'mizzey-site'));
        printf('<p>%s</p>', esc_html__('Addresses that signed up on the home page. The list is read-only.', 'mizzey-site'));
        echo '<form method="get">';
        printf('<input type="hidden" name="page" value="%s" />', esc_attr(self::SLUG));
        printf('<label for="mizzey-signups-search">%s</label> <input type="search" id="mizzey-signups-search" name="s" value="%s" /> ', esc_html__('Email address', 'mizzey-site'), esc_attr($words));
        submit_button(__('Search', 'mizzey-site'), 'secondary', '', false);
        echo '</form>';
        printf(
            '<p class="mizzey-signups__count">%s</p>',
            esc_html(sprintf(
                /* translators: 1: how many sign-ups match, 2: how many of them are subscribed. */
                _n('%1$s sign-up, %2$s subscribed', '%1$s sign-ups, %2$s subscribed', $count['all'], 'mizzey-site'),
                number_format_i18n($count['all']),
                number_format_i18n($count['subscribed'])
            ))
        );

        echo '<table class="widefat striped"><thead><tr>';
        foreach ([__('Email address', 'mizzey-site'), __('Language', 'mizzey-site'), __('Signed up', 'mizzey-site'), __('News and offers', 'mizzey-site'), __('Status', 'mizzey-site')] as $heading) {
            printf('<th scope="col">%s</th>', esc_html($heading));
        }
        echo '</tr></thead><tbody>';
        $date = get_option('date_format') . ' ' . get_option('time_format');
        foreach ($rows as $row) {
            $agreed = (int) $row['consent'] === 1
                /* translators: %s: the version of the box's words the address agreed to. */
                ? sprintf(__('Yes (words of %s)', 'mizzey-site'), (string) $row['consent_version'])
                : __('No', 'mizzey-site');
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html((string) $row['email']),
                esc_html(strtoupper((string) $row['language'])),
                esc_html(wp_date($date, (int) strtotime($row['created_at'] . ' UTC'))),
                esc_html($agreed),
                esc_html($row['status'] === SignupStore::LEFT ? __('Left', 'mizzey-site') : __('Subscribed', 'mizzey-site'))
            );
        }
        if ($rows === []) {
            printf('<tr><td colspan="5">%s</td></tr>', esc_html__('No sign-ups match.', 'mizzey-site'));
        }
        echo '</tbody></table>';
        $pages = (int) ceil($count['all'] / self::PER_PAGE);
        if ($pages > 1) {
            echo '<p>';
            if ($page > 1) {
                printf('<a class="button" href="%s">%s</a> ', esc_url(add_query_arg('paged', $page - 1)), esc_html__('Newer', 'mizzey-site'));
            }
            if ($page < $pages) {
                printf('<a class="button" href="%s">%s</a>', esc_url(add_query_arg('paged', $page + 1)), esc_html__('Older', 'mizzey-site'));
            }
            echo '</p>';
        }
        echo '</div>';
    }
}
